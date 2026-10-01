<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Visit us';
$pageDescription = 'Address, opening hours, map and directions for ' . SITE_NAME . '.';
$salon = SalonDAO::primary();

$lat = $salon !== null ? (float) $salon['latitude'] : 51.5136;
$lon = $salon !== null ? (float) $salon['longitude'] : -0.1291;
$delta = 0.008;
$bbox = ($lon - $delta) . ',' . ($lat - $delta) . ',' . ($lon + $delta) . ',' . ($lat + $delta);
$embedSrc = 'https://www.openstreetmap.org/export/embed.html?bbox=' . urlencode($bbox) . '&layer=mapnik&marker=' . $lat . ',' . $lon;
$directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $lat . ',' . $lon;

require __DIR__ . '/includes/header.php';
?>

<section class="section section-alt" style="padding-top:3rem">
  <div class="page-shell">
    <p class="eyebrow">Visit us</p>
    <h2>Find the salon</h2>
    <p class="lede" style="margin-top:1rem">
      We are a short walk from Covent Garden station. The map below shows the exact
      entrance - use the directions button to open turn by turn navigation.
    </p>
  </div>
</section>

<section class="section">
  <div class="page-shell info-grid">
    <div>
      <h3>Address</h3>
      <ul class="info-list">
        <li><span>Street</span><span><?= e($salon['address_line_1'] ?? '') ?></span></li>
        <li><span>Area</span><span><?= e($salon['address_line_2'] ?? '') ?></span></li>
        <li><span>City</span><span><?= e(($salon['city'] ?? '') . ' ' . ($salon['postcode'] ?? '')) ?></span></li>
        <li><span>Coordinates</span><span><?= e(number_format($lat, 5)) ?>, <?= e(number_format($lon, 5)) ?></span></li>
      </ul>

      <h3 style="margin-top:2rem">Contact</h3>
      <ul class="info-list">
        <li><span>Phone</span><span><a href="tel:<?= e(preg_replace('/\s+/', '', $salon['phone'] ?? '')) ?>"><?= e($salon['phone'] ?? '') ?></a></span></li>
        <li><span>Email</span><span><a href="mailto:<?= e($salon['email'] ?? '') ?>"><?= e($salon['email'] ?? '') ?></a></span></li>
        <li><span>Instagram</span><span><?= e($salon['instagram'] ?? '') ?></span></li>
        <li><span>Open now</span><span><?= SalonDAO::isOpenNow($salon) ? 'Yes' : 'No' ?></span></li>
      </ul>

      <p style="margin-top:1.5rem">
        <a class="btn btn-primary" href="<?= e($directionsUrl) ?>" target="_blank" rel="noopener">Get directions</a>
        <a class="btn btn-outline" href="<?= e(url('booking.php')) ?>">Book an appointment</a>
      </p>
    </div>

    <div>
      <h3>Opening hours</h3>
      <ul class="info-list">
        <?php foreach (($salon['opening_hours_decoded'] ?? []) as $day): ?>
          <li>
            <span><?= e($day['day'] ?? '') ?></span>
            <span><?= e($day['hours'] ?? '') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="page-shell">
    <div class="map-frame">
      <iframe
        title="Map showing the location of <?= e(SITE_NAME) ?>"
        src="<?= e($embedSrc) ?>"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    <p style="margin-top:.75rem;font-size:.85rem;color:var(--muted)">
      Map data &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors.
    </p>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>