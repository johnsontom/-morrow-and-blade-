<?php
/**
 * The barber's day at a glance: who is in, what is next, unread messages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$today = StaffDAO::appointments($barberId, 'today');
$upcoming = StaffDAO::appointments($barberId, 'upcoming', 12);
$override = StaffDAO::currentOverride($barberId);
$live = null;

foreach (BarberDAO::withAvailability() as $member) {
    if ($member['id'] === $barberId) {
        $live = $member;
        break;
    }
}

$pageTitle = 'Today';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Good to see you, <?= e(explode(' ', trim((string) $barber['name']))[0]) ?></h1>
<p class="lede">
  <?= e(date('l j F Y')) ?> &middot; you are at
  <?php $branch = DB::one('SELECT name FROM salons WHERE id = ?', [$barber['salon_id'] ?? '']); ?>
  <?= e($branch['name'] ?? 'your branch') ?>.
</p>

<div class="stat-row">
  <div class="stat"><p class="stat-label">Today</p><p class="stat-value"><?= $staffStats['today'] ?></p></div>
  <div class="stat"><p class="stat-label">Upcoming</p><p class="stat-value"><?= $staffStats['upcoming'] ?></p></div>
  <div class="stat"><p class="stat-label">Completed</p><p class="stat-value"><?= $staffStats['completed'] ?></p></div>
  <div class="stat"><p class="stat-label">Last 30 days</p><p class="stat-value"><?= e(money($staffStats['revenue'])) ?></p></div>
</div>

<section class="panel <?= ($live['status'] ?? '') === 'available' ? 'panel-accent' : '' ?>">
  <div class="panel-head">
    <div>
      <p class="eyebrow">Your live status</p>
      <h2>
        <span class="<?= e(status_class($live['status'] ?? 'offline')) ?>"><?= e($live['status'] ?? 'offline') ?></span>
        <?= e($live['status_message'] ?? '') ?>
      </h2>
    </div>
    <a class="btn btn-outline" href="<?= e(url('barber/status.php')) ?>">Change status</a>
  </div>
  <p class="record-meta">Today's hours: <?= e($live['working_hours_label'] ?? 'Closed') ?></p>
  <?php if ($override !== null): ?>
    <p class="record-meta">Manual override in force<?= $override['valid_until'] !== null ? ' until ' . e(date('D j M, H:i', strtotime((string) $override['valid_until']))) : '' ?><?= $override['reason'] !== '' ? ': ' . e($override['reason']) : '' ?>.</p>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Today's appointments</h2>
    <a class="text-link" href="<?= e(url('barber/appointments.php')) ?>">All appointments</a>
  </div>

  <?php if ($today === []): ?>
    <p class="muted">Nothing in the book today.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($today as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?> &ndash; <?= e(date('H:i', strtotime((string) $appointment['ends_at']))) ?> &middot; <?= e($appointment['service_name']) ?></p>
            <p class="record-meta"><?= e($appointment['customer_name']) ?> &middot; <?= e($appointment['customer_phone']) ?> &middot; ref <?= e($appointment['reference']) ?></p>
          </div>
          <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Coming up</h2>
    <a class="text-link" href="<?= e(url('barber/schedule.php')) ?>">Edit my rota</a>
  </div>

  <?php if ($upcoming === []): ?>
    <p class="muted">No future bookings yet.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach (array_slice($upcoming, 0, 6) as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e($appointment['service_name']) ?></p>
            <p class="record-meta"><?= e(date('D j M, H:i', strtotime((string) $appointment['starts_at']))) ?> &middot; <?= e($appointment['customer_name']) ?></p>
          </div>
          <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>