<?php
/**
 * Owner reporting: what every branch has taken, what every member of the team
 * has taken, and the appointment history behind those figures.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$profile = Auth::requireProfile();

if (!Auth::isAdmin()) {
    header('Location: ' . url('barber/index.php'));
    exit;
}

// ------------------------------------------------------------------ filters
$filters = [
    'barber' => trim((string) ($_GET['barber'] ?? '')),
    'branch' => trim((string) ($_GET['branch'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'from' => trim((string) ($_GET['from'] ?? '')),
    'to' => trim((string) ($_GET['to'] ?? '')),
];

$page = max(1, (int) ($_GET['page'] ?? 1));

$summary = ReportDAO::historySummary($filters);
$totalRows = (int) ($summary['rows_'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / ReportDAO::PAGE_SIZE));
$page = min($page, $totalPages);
$offset = ($page - 1) * ReportDAO::PAGE_SIZE;

$history = ReportDAO::history($filters, ReportDAO::PAGE_SIZE, $offset);
$branches = ReportDAO::branchEarnings();
$workers = ReportDAO::workerTakings();
$branchOptions = BranchDAO::all(false);
$teamOptions = BarberDAO::all();

$headline = [
    'all_time' => (int) (DB::one("SELECT COALESCE(SUM(price_pence_snapshot),0) AS t FROM appointments WHERE status = 'completed'")['t'] ?? 0),
    'last_30d' => (int) (DB::one("SELECT COALESCE(SUM(price_pence_snapshot),0) AS t FROM appointments WHERE status = 'completed' AND starts_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY")['t'] ?? 0),
    'completed' => (int) (DB::one("SELECT COUNT(*) AS t FROM appointments WHERE status = 'completed'")['t'] ?? 0),
    'lost' => (int) (DB::one("SELECT COALESCE(SUM(price_pence_snapshot),0) AS t FROM appointments WHERE status IN ('cancelled','no_show')")['t'] ?? 0),
];

// Keep the chosen filters when moving between pages.
$activeFilters = array_filter($filters, static fn ($value) => $value !== '');
$pageUrl = static function (int $target) use ($activeFilters): string {
    $query = $activeFilters;
    if ($target > 1) {
        $query['page'] = (string) $target;
    }

    return url('admin/reports.php' . ($query === [] ? '' : '?' . http_build_query($query)));
};
$resetUrl = url('admin/reports.php');

$pageTitle = 'Reports';
$portal = [
    'kind' => 'staff',
    'name' => $profile['full_name'],
    'root' => 'admin',
    'subtitle' => 'Owner',
    'nav' => [
        'index.php' => 'Salon overview',
        'reports.php' => 'Reports',
        'services.php' => 'Treatments',
    ],
];
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Reports</h1>
<p class="lede">Takings by branch and by team member, with the appointment history behind them. Only completed appointments count as money taken.</p>

<div class="stat-row">
  <div class="stat"><p class="stat-label">Total earnings</p><p class="stat-value"><?= e(money($headline['all_time'])) ?></p></div>
  <div class="stat"><p class="stat-label">Last 30 days</p><p class="stat-value"><?= e(money($headline['last_30d'])) ?></p></div>
  <div class="stat"><p class="stat-label">Completed</p><p class="stat-value"><?= $headline['completed'] ?></p></div>
  <div class="stat"><p class="stat-label">Lost to cancellations</p><p class="stat-value"><?= e(money($headline['lost'])) ?></p></div>
</div>

<section class="panel">
  <h2>Earnings by branch</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Branch</th><th>Staff</th><th>Appointments</th><th>Completed</th><th>Last 30 days</th><th>Total earnings</th><th>Last sale</th></tr>
      </thead>
      <tbody>
        <?php foreach ($branches as $branch): ?>
          <tr>
            <td><?= e($branch['name']) ?></td>
            <td><?= (int) $branch['staff'] ?></td>
            <td><?= (int) $branch['appointments'] ?></td>
            <td><?= (int) $branch['completed'] ?></td>
            <td><?= e(money((int) $branch['earnings_30d_pence'])) ?></td>
            <td><?= e(money((int) $branch['earnings_pence'])) ?></td>
            <td><?= e($branch['last_sale'] !== null ? date('j M Y, H:i', strtotime((string) $branch['last_sale'])) : '&mdash;') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel">
  <h2>Earnings by team member</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Branch</th><th>Role</th><th>Appointments</th><th>Completed</th><th>Cancelled</th><th>No-shows</th><th>Earnings</th><th>Last sale</th></tr>
      </thead>
      <tbody>
        <?php foreach ($workers as $worker): ?>
          <tr>
            <td><a class="text-link" href="<?= e(url('barber.php?slug=' . urlencode((string) $worker['slug']))) ?>"><?= e($worker['name']) ?></a></td>
            <td><?= e($worker['branch_name'] ?? '&mdash;') ?></td>
            <td><?= e((string) $worker['role']) ?></td>
            <td><?= (int) $worker['appointments'] ?></td>
            <td><?= (int) $worker['completed'] ?></td>
            <td><?= (int) $worker['cancelled'] ?></td>
            <td><?= (int) $worker['no_shows'] ?></td>
            <td><?= e(money((int) $worker['earnings_pence'])) ?></td>
            <td><?= e($worker['last_sale'] !== null ? date('j M Y', strtotime((string) $worker['last_sale'])) : '&mdash;') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Appointment history</h2>
    <span class="muted"><?= $totalRows ?> matching <?= $totalRows === 1 ? 'appointment' : 'appointments' ?></span>
  </div>

  <form method="get" class="inline-form">
    <select name="barber">
      <option value="">All team</option>
      <?php foreach ($teamOptions as $member): ?>
        <option value="<?= e((string) $member['slug']) ?>"<?= $filters['barber'] === (string) $member['slug'] ? ' selected' : '' ?>><?= e($member['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="branch">
      <option value="">All branches</option>
      <?php foreach ($branchOptions as $branch): ?>
        <option value="<?= e((string) $branch['id']) ?>"<?= $filters['branch'] === (string) $branch['id'] ? ' selected' : '' ?>><?= e($branch['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Any status</option>
      <?php foreach (ReportDAO::STATUSES as $status): ?>
        <option value="<?= e($status) ?>"<?= $filters['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $status))) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From date">
    <input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To date">
    <button class="btn btn-primary" type="submit">Apply</button>
    <a class="text-link" href="<?= e($resetUrl) ?>">Reset</a>
  </form>

  <p class="muted">
    This selection: <?= e(money((int) $summary['earnings_pence'])) ?> earned
    &middot; <?= e(money((int) $summary['lost_pence'])) ?> lost to cancellations
  </p>

  <?php if ($history === []): ?>
    <p class="muted">No appointments match these filters.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>When</th><th>Reference</th><th>Customer</th><th>Treatment</th><th>Team member</th><th>Branch</th><th>Status</th><th>Price</th></tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <tr>
              <td><?= e(date('j M Y, H:i', strtotime((string) $row['starts_at']))) ?></td>
              <td><?= e($row['reference']) ?></td>
              <td><?= e($row['customer_name']) ?></td>
              <td><?= e($row['service_name'] ?? $row['service_name_snapshot']) ?></td>
              <td><?= e($row['barber_name']) ?></td>
              <td><?= e($row['branch_name'] ?? '&mdash;') ?></td>
              <td><span class="<?= e(status_class((string) $row['status'])) ?>"><?= e(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
              <td><?= e(money((int) $row['price_pence_snapshot'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <p class="muted">
        Page <?= $page ?> of <?= $totalPages ?>
        <?php if ($page > 1): ?>
          &middot; <a class="text-link" href="<?= e($pageUrl($page - 1)) ?>">Newer</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          &middot; <a class="text-link" href="<?= e($pageUrl($page + 1)) ?>">Older</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>
