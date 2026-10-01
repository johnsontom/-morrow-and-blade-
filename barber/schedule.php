<?php
/**
 * The barber's own rota: recurring weekly hours plus one-off days off.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$dayNames = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try again.');
    } elseif ((string) ($_POST['action'] ?? '') === 'rota') {
        $days = [];

        foreach ($dayNames as $index => $name) {
            $days[$index] = [
                'working' => isset($_POST['working'][$index]),
                'start' => (string) ($_POST['start'][$index] ?? '09:00'),
                'end' => (string) ($_POST['end'][$index] ?? '18:00'),
            ];
        }

        StaffDAO::saveWeek($barberId, $days);
        Auth::flash('success', 'Your weekly hours are saved.');
        header('Location: ' . url('barber/schedule.php'));
        exit;
    } elseif ((string) ($_POST['action'] ?? '') === 'exception') {
        $date = trim((string) ($_POST['exception_date'] ?? ''));
        $fullDay = isset($_POST['full_day']);
        $start = $fullDay ? null : trim((string) ($_POST['exception_start'] ?? ''));
        $end = $fullDay ? null : trim((string) ($_POST['exception_end'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Auth::flash('error', 'Pick a date for the day off.');
        } elseif (!$fullDay && ($start === '' || $end === '')) {
            Auth::flash('error', 'Give a start and end time, or tick the whole day.');
        } else {
            StaffDAO::addException($barberId, $date, $start, $end, false, $note);
            Auth::flash('success', 'Day off added.');
        }

        header('Location: ' . url('barber/schedule.php'));
        exit;
    } elseif ((string) ($_POST['action'] ?? '') === 'delete') {
        StaffDAO::deleteException($barberId, (string) ($_POST['exception_id'] ?? ''));
        Auth::flash('success', 'Removed.');
        header('Location: ' . url('barber/schedule.php'));
        exit;
    }
}

$week = StaffDAO::week($barberId);
$exceptions = StaffDAO::exceptions($barberId);

$pageTitle = 'My rota';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">My rota</h1>
<p class="lede">These hours decide when customers can book you. Anything outside them is shown as offline.</p>

<section class="panel">
  <h2>Weekly hours</h2>
  <form method="post" class="rota-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="rota">

    <div class="rota-head"><span>Day</span><span>Working</span><span>Start</span><span>End</span></div>

    <?php foreach ($week as $index => $day): ?>
      <div class="rota-row">
        <span class="rota-day"><?= e($dayNames[$index]) ?></span>
        <label class="rota-check">
          <input type="checkbox" name="working[<?= (int) $index ?>]" value="1" <?= (int) $day['is_working'] === 1 ? 'checked' : '' ?>>
          <span class="visually-hidden">Working on <?= e($dayNames[$index]) ?></span>
        </label>
        <input type="time" name="start[<?= (int) $index ?>]" value="<?= e(substr((string) $day['start_time'], 0, 5)) ?>" step="900">
        <input type="time" name="end[<?= (int) $index ?>]" value="<?= e(substr((string) $day['end_time'], 0, 5)) ?>" step="900">
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Save weekly hours</button>
  </form>
</section>

<section class="panel">
  <h2>Days off and time off</h2>

  <form method="post" class="stack-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="exception">

    <label for="exception_date">Date</label>
    <input type="date" id="exception_date" name="exception_date" required>

    <label class="rota-check rota-check-wide">
      <input type="checkbox" name="full_day" value="1" checked>
      <span>Whole day off</span>
    </label>

    <div class="field-pair">
      <div>
        <label for="exception_start">From</label>
        <input type="time" id="exception_start" name="exception_start" step="900" value="09:00">
      </div>
      <div>
        <label for="exception_end">To</label>
        <input type="time" id="exception_end" name="exception_end" step="900" value="13:00">
      </div>
    </div>

    <label for="note">Note <span class="field-hint">only you and the owner see this</span></label>
    <input type="text" id="note" name="note" maxlength="500" placeholder="Dentist">

    <button type="submit" class="btn btn-primary">Add time off</button>
  </form>
</section>

<section class="panel">
  <h2>Upcoming time off</h2>
  <?php if ($exceptions === []): ?>
    <p class="muted">No time off booked in.</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($exceptions as $exception): ?>
        <li class="record">
          <div>
            <p class="record-title"><?= e(date('D j M Y', strtotime((string) $exception['exception_date']))) ?></p>
            <p class="record-meta">
              <?= $exception['start_time'] === null ? 'All day' : e(substr((string) $exception['start_time'], 0, 5) . ' - ' . substr((string) $exception['end_time'], 0, 5)) ?>
              <?= trim((string) $exception['note']) !== '' ? ' &middot; ' . e($exception['note']) : '' ?>
            </p>
          </div>
          <form method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="exception_id" value="<?= e($exception['id']) ?>">
            <button type="submit" class="btn btn-small btn-quiet">Remove</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>