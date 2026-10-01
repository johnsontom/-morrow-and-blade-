<?php
/**
 * Customer overview: next appointment, quick rebook, unread messages.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$customer = Auth::requireCustomer();

$appointments = DB::all(
    "SELECT a.*, b.name AS barber_name, b.slug AS barber_slug, s.name AS service_name
     FROM appointments a
     JOIN barbers b ON b.id = a.barber_id
     JOIN services s ON s.id = a.service_id
     WHERE a.customer_id = ?
     ORDER BY a.starts_at DESC
     LIMIT 50",
    [$customer['id']]
);

$now = date('Y-m-d H:i:s');
$upcoming = array_values(array_filter(
    $appointments,
    static fn ($a) => $a['ends_at'] >= $now && !in_array($a['status'], ['cancelled', 'no_show'], true)
));
$history = array_values(array_filter($appointments, static fn ($a) => $a['ends_at'] < $now));
$unread = MessageDAO::unreadForCustomer((string) $customer['id']);
$threads = MessageDAO::conversationsForCustomer((string) $customer['id']);

$pageTitle = 'My account';
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

<h1 class="portal-h1">Hello, <?= e(explode(' ', trim((string) $customer['name']))[0]) ?></h1>
<p class="lede">Your next visit, your history and a direct line to your barber.</p>

<div class="stat-row">
  <div class="stat"><p class="stat-label">Upcoming</p><p class="stat-value"><?= count($upcoming) ?></p></div>
  <div class="stat"><p class="stat-label">Visits</p><p class="stat-value"><?= count($history) ?></p></div>
  <div class="stat"><p class="stat-label">Messages</p><p class="stat-value"><?= $unread ?></p></div>
</div>

<?php if ($upcoming !== []): $next = $upcoming[count($upcoming) - 1]; ?>
  <section class="panel panel-accent">
    <p class="eyebrow">Next appointment</p>
    <h2><?= e($next['service_name']) ?></h2>
    <p class="panel-line">
      <?= e(date('l j F Y \a\t H:i', strtotime($next['starts_at']))) ?> with
      <a class="text-link" href="<?= e(url('barber.php?slug=' . urlencode($next['barber_slug']))) ?>"><?= e($next['barber_name']) ?></a>
    </p>
    <p class="panel-line">Reference <strong><?= e($next['reference']) ?></strong> &middot; <?= e(money((int) $next['price_pence_snapshot'])) ?></p>
    <div class="panel-actions">
      <a class="btn btn-primary" href="<?= e(url('booking.php?service=' . urlencode((string) $next['service_id']) . '&barber=' . urlencode((string) $next['barber_id']))) ?>">Book again</a>
      <a class="btn btn-outline" href="<?= e(url('account/messages.php?barber=' . urlencode((string) $next['barber_id']))) ?>">Message <?= e(explode(' ', trim((string) $next['barber_name']))[0]) ?></a>
    </div>
  </section>
<?php else: ?>
  <section class="panel">
    <h2>Nothing booked yet</h2>
    <p class="lede">Pick a treatment and a time that suits you - it takes about a minute.</p>
    <div class="panel-actions"><a class="btn btn-primary" href="<?= e(url('booking.php')) ?>">Book an appointment</a></div>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2>Recent activity</h2>
    <a class="text-link" href="<?= e(url('account/appointments.php')) ?>">See all</a>
  </div>

  <?php if ($appointments === []): ?>
    <p class="muted">No bookings on this account yet.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach (array_slice($appointments, 0, 5) as $appointment): ?>
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

<?php if ($threads !== []): ?>
  <section class="panel">
    <div class="panel-head">
      <h2>Conversations</h2>
      <a class="text-link" href="<?= e(url('account/messages.php')) ?>">Open inbox</a>
    </div>
    <ul class="record-list">
      <?php foreach (array_slice($threads, 0, 3) as $thread): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e($thread['barber_name']) ?></p>
            <p class="record-meta"><?= e(mb_strimwidth((string) ($thread['last_body'] ?? ''), 0, 70, '...')) ?></p>
          </div>
          <?php if ((int) $thread['customer_unread'] > 0): ?>
            <span class="portal-badge"><?= (int) $thread['customer_unread'] ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>