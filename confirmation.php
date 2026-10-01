<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Your booking';
$reference = (string) ($_GET['reference'] ?? '');
$token = (string) ($_GET['token'] ?? '');

// ------------------------------------------------------------- cancellation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $postedReference = (string) ($_POST['reference'] ?? '');
    $postedToken = (string) ($_POST['token'] ?? '');

    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try that again.');
    } else {
        $result = BookingDAO::cancelByReference($postedReference, $postedToken);
        Auth::flash($result['ok'] ? 'success' : 'error', $result['message']);
    }

    $backReference = $postedReference !== '' ? $postedReference : $reference;
    $backToken = $postedToken !== '' ? $postedToken : $token;
    header('Location: ' . url('confirmation.php?reference=' . urlencode($backReference) . '&token=' . urlencode($backToken)));
    exit;
}

$booking = BookingDAO::findByReference($reference, $token);

$canCancel = $booking !== null
    && in_array($booking['status'], ['pending', 'confirmed'], true)
    && strtotime($booking['starts_at']) > time();

$timezone = new DateTimeZone(SITE_TIMEZONE);

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell" style="max-width:44rem">
    <?php if ($booking === null): ?>
      <p class="eyebrow">Not found</p>
      <h2>We could not find that booking</h2>
      <p class="lede" style="margin-top:1rem">
        Check the link from your confirmation email. Both the reference and the
        private token are needed, so the address must match exactly.
      </p>
      <p><a class="btn btn-outline" href="<?= e(url('booking.php')) ?>">Make a new booking</a></p>
    <?php else: ?>
      <?php
        $starts = (new DateTimeImmutable($booking['starts_at'], new DateTimeZone('UTC')))->setTimezone($timezone);
        $ends = (new DateTimeImmutable($booking['ends_at'], new DateTimeZone('UTC')))->setTimezone($timezone);
      ?>
      <p class="eyebrow">Booking <?= e($booking['reference']) ?></p>
      <?php if ($booking['status'] === 'cancelled'): ?>
        <h2>This booking is cancelled</h2>
        <p class="lede" style="margin-top:1rem">
          Nothing else is needed. If that was not what you wanted, you can
          <a class="text-link" href="<?= e(url('booking.php')) ?>">make a new booking</a>.
        </p>
      <?php elseif ($booking['status'] === 'pending'): ?>
        <h2>Your request is with the team</h2>
        <p class="lede" style="margin-top:1rem">
          We have sent your booking to <?= e($booking['barber_name']) ?>. It shows as
          pending until they confirm, and you will get an email the moment they do.
          You can cancel it here while it is still pending.
        </p>
      <?php else: ?>
        <h2>You are booked in</h2>
        <p class="lede" style="margin-top:1rem">
          Keep this page or bookmark it. The link contains a private token, so anyone
          with it can view these details.
        </p>
      <?php endif; ?>

      <ul class="info-list" style="margin-top:2rem">
        <li><span>Status</span><span><span class="<?= e(status_class($booking['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', $booking['status']))) ?></span></span></li>
        <li><span>Treatment</span><span><?= e($booking['service_name']) ?></span></li>
        <li><span>With</span><span><?= e($booking['barber_name']) ?></span></li>
        <li><span>Date</span><span><?= e($starts->format('l j F Y')) ?></span></li>
        <li><span>Time</span><span><?= e($starts->format('H:i')) ?> - <?= e($ends->format('H:i')) ?></span></li>
        <li><span>Price</span><span><?= e(money((int) $booking['price_pence_snapshot'])) ?></span></li>
        <li><span>Name</span><span><?= e($booking['customer_name']) ?></span></li>
        <li><span>Email</span><span><?= e($booking['customer_email']) ?></span></li>
        <?php if ($booking['notes'] !== ''): ?>
          <li><span>Notes</span><span><?= e($booking['notes']) ?></span></li>
        <?php endif; ?>
      </ul>

      <?php $salon = SalonDAO::primary(); ?>
      <?php if ($salon !== null): ?>
        <h3 style="margin-top:2rem">Where to find us</h3>
        <p>
          <?= e($salon['address_line_1']) ?>, <?= e($salon['address_line_2']) ?><br>
          <?= e($salon['city']) ?> <?= e($salon['postcode']) ?><br>
          <a href="tel:<?= e(preg_replace('/\s+/', '', $salon['phone'])) ?>"><?= e($salon['phone']) ?></a>
        </p>
      <?php endif; ?>

      <p style="margin-top:1.5rem">
        <a class="btn btn-outline" href="<?= e(url('index.php')) ?>">Back to the home page</a>
      </p>

      <?php if ($canCancel): ?>
        <form method="post" action="<?= e(url('confirmation.php')) ?>" style="margin-top:1.5rem"
              onsubmit="return confirm('Cancel this booking?');">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="reference" value="<?= e($booking['reference']) ?>">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <button class="btn btn-quiet" type="submit">Cancel this booking</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
