<?php
/**
 * Live status. This is the override the public site reads first, so a barber
 * can step out or go on a break without touching their rota.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $status = (string) ($_POST['status'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $until = trim((string) ($_POST['valid_until'] ?? ''));

        if ($status !== '' && !in_array($status, ['available', 'busy', 'booked', 'offline'], true)) {
            $errors[] = 'Pick a valid status.';
        }

        if ($errors === []) {
            StaffDAO::setStatus(
                $barberId,
                $status === '' ? null : $status,
                $reason,
                $until === '' ? null : str_replace('T', ' ', $until) . ':00',
                (string) $profile['id']
            );

            Auth::flash('success', $status === '' ? 'Back to your normal rota.' : 'Status updated.');
            header('Location: ' . url('barber/status.php'));
            exit;
        }
    }
}

$current = StaffDAO::currentOverride($barberId);
$live = null;

foreach (BarberDAO::withAvailability() as $member) {
    if ($member['id'] === $barberId) {
        $live = $member;
        break;
    }
}

$pageTitle = 'My status';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">My status</h1>
<p class="lede">What customers see right now:
  <span class="<?= e(status_class($live['status'] ?? 'offline')) ?>"><?= e($live['status'] ?? 'offline') ?></span>
  <?= e($live['status_message'] ?? '') ?>
</p>

<?php foreach ($errors as $error): ?>
  <p class="flash flash-error"><?= e($error) ?></p>
<?php endforeach; ?>

<section class="panel">
  <h2>Set a status</h2>
  <p class="muted">An override outranks your rota until it expires or you clear it.</p>

  <form method="post" class="stack-form">
    <?= Auth::csrfField() ?>

    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="" <?= $current === null ? 'selected' : '' ?>>Use my rota</option>
      <option value="available" <?= ($current['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available now</option>
      <option value="busy" <?= ($current['status'] ?? '') === 'busy' ? 'selected' : '' ?>>Busy &mdash; with a customer</option>
      <option value="booked" <?= ($current['status'] ?? '') === 'booked' ? 'selected' : '' ?>>Appointment approaching</option>
      <option value="offline" <?= ($current['status'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline &mdash; not taking walk-ins</option>
    </select>

    <label for="reason">Reason <span class="field-hint">optional, shown to customers</span></label>
    <input type="text" id="reason" name="reason" maxlength="500" value="<?= e((string) ($current['reason'] ?? '')) ?>" placeholder="On a break until 14:30">

    <label for="valid_until">Revert automatically at <span class="field-hint">optional</span></label>
    <input type="datetime-local" id="valid_until" name="valid_until"
           value="<?= e($current !== null && $current['valid_until'] !== null ? date('Y-m-d\TH:i', strtotime((string) $current['valid_until'])) : '') ?>">

    <button type="submit" class="btn btn-primary">Update status</button>
  </form>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>