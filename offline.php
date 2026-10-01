<?php
/**
 * Shown by the service worker when the visitor is offline.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

http_response_code(200);
$pageTitle = 'You are offline';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell">
    <p class="eyebrow">No connection</p>
    <h1>You are offline</h1>
    <p class="lede">
      The pages you have already visited still work. Booking and messaging need a connection,
      so try again once you have signal.
    </p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Try the home page</a>
      <a class="btn btn-outline" href="tel:<?= e($salon['phone'] ?? '') ?>">Ring the salon</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>