<?php declare(strict_types=1); ?>
</main>

<footer class="site-footer">
  <div class="page-shell footer-grid">
    <div>
      <p class="footer-brand">Morrow &amp; Blade</p>
      <p class="footer-copy">
        Considered barbering, massage and nail care, with live availability and
        appointments shaped around your day.
      </p>
    </div>

    <div>
      <p class="footer-heading">Visit</p>
      <?php if ($salon !== null): ?>
        <p class="footer-line"><?= e($salon['address_line_1']) ?><br><?= e($salon['address_line_2']) ?><br><?= e($salon['city']) ?> <?= e($salon['postcode']) ?></p>
        <p class="footer-line"><a href="tel:<?= e(preg_replace('/\s+/', '', $salon['phone'])) ?>"><?= e($salon['phone']) ?></a></p>
        <p class="footer-line"><a href="mailto:<?= e($salon['email']) ?>"><?= e($salon['email']) ?></a></p>
      <?php endif; ?>
    </div>

    <div>
      <p class="footer-heading">Opening hours</p>
      <?php foreach (($salon['opening_hours_decoded'] ?? []) as $day): ?>
        <p class="footer-line footer-hours">
          <span><?= e($day['shortDay'] ?? '') ?></span>
          <span><?= e($day['hours'] ?? '') ?></span>
        </p>
      <?php endforeach; ?>
    </div>

    <div>
      <p class="footer-heading">Explore</p>
      <p class="footer-line"><a href="<?= e(url('services.php')) ?>">Services &amp; prices</a></p>
      <p class="footer-line"><a href="<?= e(url('barbers.php')) ?>">Meet the team</a></p>
      <p class="footer-line"><a href="<?= e(url('branches.php')) ?>">Find your salon</a></p>
      <p class="footer-line"><a href="<?= e(url('ai.php')) ?>">Ask the assistant</a></p>
      <p class="footer-line"><a href="<?= e(url('booking.php')) ?>">Book an appointment</a></p>
      <p class="footer-line"><a href="<?= e(url('barber/login.php')) ?>">Team login</a></p>
    </div>
  </div>

  <div class="page-shell footer-base">
    <p>&copy; <?= date('Y') ?> Morrow &amp; Blade. All rights reserved.</p>
    <p><?= e(SITE_INSTAGRAM) ?></p>
  </div>
</footer>

<?php require __DIR__ . '/assistant-widget.php'; ?>

<script src="<?= e(asset('js/nav.js')) ?>" defer></script>
<script src="<?= e(asset('js/pwa.js')) ?>" defer></script>
</body>
</html>