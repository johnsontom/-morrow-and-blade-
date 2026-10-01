<?php
/**
 * Full booking history, split into upcoming and past.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$customer = Auth::requireCustomer();
$now = date('Y-m-d H:i:s');

// ------------------------------------------------------------- cancellation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try that again.');
    } else {
        $result = BookingDAO::cancelForCustomer((string) ($_POST['appointment_id'] ?? ''), (string) $customer['id']);
        Auth::flash($result['ok'] ? 'success' : 'error', $result['message']);
    }

    header('Location: ' . url('account/appointments.php'));
    exit;
}

$rows = DB::all(
    "SELECT a.*, b.name AS barber_name, b.slug AS barber_slug, s.name AS service_name
     FROM appointments a
     JOIN barbers b ON b.id = a.barber_id
     JOIN services s ON s.id = a.service_id
     WHERE a.customer_id = ?
     ORDER BY a.starts_at DESC",
    [$customer['id']]
);

$upcoming = array_values(array_filter($rows, static fn ($a) => $a['ends_at'] >= $now && $a['status'] !== 'cancelled'));
$past = array_values(array_filter($rows, static fn ($a) => $a['ends_at'] < $now || $a['status'] === 'cancelled'));
$unread = MessageDAO::unreadForCustomer((string) $customer['id']);

$pageTitle = 'My appointments';
$portal = [
    'kind' => 'customer',
    'name' => $customer['name'],
    'root' => 'account',
    'subtitle' => 'My account',
    'badges' => ['messages.php' => $unread],
    'nav' => [
        'index.php' => 'Overview',
        'appointments.php' => 'My appointments',
        'messages.php' => 'Messages',
        'profile.php' => 'My details',
    ],
];
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">My appointments</h1>
<p class="lede">Everything you have booked with us, newest first.</p>

<section class="panel">
  <h2>Upcoming</h2>
  <?php if ($upcoming === []): ?>
    <p class="muted">Nothing coming up. <a class="text-link" href="<?= e(url('booking.php')) ?>">Book a visit</a>.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach (array_reverse($upcoming) as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e($appointment['service_name']) ?></p>
            <p class="record-meta">
              <?= e(date('l j M Y, H:i', strtotime($appointment['starts_at']))) ?> &middot;
              <?= e($appointment['barber_name']) ?> &middot;
              <?= e(money((int) $appointment['price_pence_snapshot'])) ?> &middot;
              ref <?= e($appointment['reference']) ?>
            </p>
          </div>
          <div class="record-side">
            <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
            <?php if (in_array($appointment['status'], ['pending', 'confirmed'], true) && $appointment['starts_at'] > $now): ?>
              <form method="post" action="<?= e(url('account/appointments.php')) ?>"
                    onsubmit="return confirm('Cancel this appointment?');">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">
                <button class="btn btn-quiet btn-small" type="submit">Cancel</button>
              </form>
            <?php endif; ?>
            <a class="text-link" href="<?= e(url('account/messages.php?barber=' . urlencode((string) $appointment['barber_id']))) ?>">Message</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>History</h2>
  <?php if ($past === []): ?>
    <p class="muted">No past appointments yet.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach (array_slice($past, 0, 30) as $appointment): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e($appointment['service_name']) ?></p>
            <p class="record-meta"><?= e(date('D j M Y, H:i', strtotime($appointment['starts_at']))) ?> &middot; <?= e($appointment['barber_name']) ?></p>
          </div>
          <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>
