<?php
/**
 * The barber's book. Only ever shows and edits their own appointments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try again.');
    } else {
        $appointmentId = (string) ($_POST['appointment_id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (StaffDAO::updateAppointmentStatus($barberId, $appointmentId, $status)) {
            Auth::flash('success', 'Appointment marked ' . str_replace('_', ' ', $status) . '.');
        } else {
            Auth::flash('error', 'That appointment could not be updated.');
        }
    }

    header('Location: ' . url('barber/appointments.php?scope=' . urlencode((string) ($_POST['scope'] ?? 'upcoming'))));
    exit;
}

$scope = (string) ($_GET['scope'] ?? 'upcoming');

if (!in_array($scope, ['today', 'upcoming', 'past', 'cancelled'], true)) {
    $scope = 'upcoming';
}

$appointments = StaffDAO::appointments($barberId, $scope, 200);
$labels = ['today' => 'Today', 'upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'];

$pageTitle = 'Appointments';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Appointments</h1>
<p class="lede">Confirm, complete or cancel your own bookings. Customers see the change immediately.</p>

<nav class="tab-row">
  <?php foreach ($labels as $key => $label): ?>
    <a class="tab <?= $scope === $key ? 'tab-active' : '' ?>" href="<?= e(url('barber/appointments.php?scope=' . $key)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="panel">
  <?php if ($appointments === []): ?>
    <p class="muted">Nothing here.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($appointments as $appointment): ?>
        <li class="record record-tall">
          <div>
            <p class="record-title"><?= e($appointment['service_name']) ?> &middot; <?= e(money((int) $appointment['price_pence_snapshot'])) ?></p>
            <p class="record-meta"><?= e(date('D j M Y, H:i', strtotime((string) $appointment['starts_at']))) ?> &ndash; <?= e(date('H:i', strtotime((string) $appointment['ends_at']))) ?></p>
            <p class="record-meta"><?= e($appointment['customer_name']) ?> &middot; <a class="text-link" href="tel:<?= e(preg_replace('/\s+/', '', (string) $appointment['customer_phone'])) ?>"><?= e($appointment['customer_phone']) ?></a> &middot; <?= e($appointment['customer_email']) ?></p>
            <?php if (trim((string) $appointment['notes']) !== ''): ?>
              <p class="record-note">Note: <?= e($appointment['notes']) ?></p>
            <?php endif; ?>
            <p class="record-meta">ref <?= e($appointment['reference']) ?></p>
          </div>
          <div class="record-side">
            <span class="<?= e(status_class($appointment['status'])) ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span>
            <?php if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled'): ?>
              <form method="post" class="stack-inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <?php if ($appointment['status'] === 'pending'): ?>
                  <button class="btn btn-small btn-primary" name="status" value="confirmed" type="submit">Confirm</button>
                <?php endif; ?>
                <?php if ($appointment['status'] !== 'in_progress'): ?>
                  <button class="btn btn-small btn-outline" name="status" value="in_progress" type="submit">Start</button>
                <?php endif; ?>
                <button class="btn btn-small btn-outline" name="status" value="completed" type="submit">Complete</button>
                <button class="btn btn-small btn-quiet" name="status" value="no_show" type="submit">No-show</button>
                <button class="btn btn-small btn-quiet" name="status" value="cancelled" type="submit" onclick="return confirm('Cancel this appointment?')">Cancel</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>