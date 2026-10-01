<?php
/**
 * A single team member: bio, specialities, prices and how to reach them.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$barber = $slug !== '' ? BarberDAO::bySlug($slug) : null;

if ($barber === null) {
    http_response_code(404);
    $pageTitle = 'Team member not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="page-shell"><h1>We could not find that team member</h1>'
        . '<p class="lede">They may have moved on. <a class="text-link" href="' . e(url('barbers.php')) . '">See the current team</a>.</p>'
        . '</div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$live = null;
foreach (BarberDAO::withAvailability(null, $barber['salon_id']) as $member) {
    if ($member['id'] === $barber['id']) {
        $live = $member;
        break;
    }
}

$services = ServiceDAO::forStaff((string) $barber['id']);
if ($services === []) {
    $services = ServiceDAO::all();
}

$branch = $barber['salon_id'] !== null ? DB::one('SELECT * FROM salons WHERE id = ?', [$barber['salon_id']]) : null;

$pageTitle = $barber['name'] . ' - ' . $barber['role'];
$pageDescription = mb_strimwidth(trim((string) $barber['bio']), 0, 155, '...');
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell profile-hero">
    <div class="profile-photo">
      <?php if (staff_photo_url($barber['photo_url']) !== ''): ?>
        <img src="<?= e(staff_photo_url($barber['photo_url'])) ?>" alt="<?= e($barber['name']) ?>">
      <?php else: ?>
        <span class="profile-initial"><?= e(mb_substr((string) $barber['name'], 0, 1)) ?></span>
      <?php endif; ?>
    </div>

    <div class="profile-body">
      <p class="eyebrow"><?= e($barber['staff_kind_label']) ?><?= $branch !== null ? ' &middot; ' . e($branch['name']) : '' ?></p>
      <h1><?= e($barber['name']) ?></h1>
      <p class="lede"><?= e($barber['role']) ?> &middot; <?= (int) $barber['years_experience'] ?> years &middot; rating <?= e($barber['rating']) ?> from <?= (int) $barber['review_count'] ?> reviews</p>

      <p class="profile-status">
        <span class="<?= e(status_class($live['status'] ?? 'offline')) ?>"><?= e($live['status'] ?? 'offline') ?></span>
        <span class="muted"><?= e($live['status_message'] ?? '') ?> &middot; today <?= e($live['working_hours_label'] ?? 'Closed') ?></span>
      </p>

      <?php if (trim((string) $barber['bio']) !== ''): ?>
        <p><?= nl2br(e($barber['bio'])) ?></p>
      <?php endif; ?>

      <?php if ($barber['specialties_list'] !== []): ?>
        <ul class="chip-row">
          <?php foreach ($barber['specialties_list'] as $specialty): ?>
            <li class="chip"><?= e((string) $specialty) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('booking.php?barber=' . urlencode((string) $barber['id']))) ?>">Book with <?= e(explode(' ', trim((string) $barber['name']))[0]) ?></a>
        <a class="btn btn-outline" href="<?= e(url('account/messages.php?barber=' . urlencode((string) $barber['slug']))) ?>">Ask a question</a>
      </div>
      <p class="muted muted-small">Messaging needs a free account - it takes ten seconds and keeps your chat in one place.</p>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <p class="eyebrow">Prices</p>
        <h2>What <?= e(explode(' ', trim((string) $barber['name']))[0]) ?> offers</h2>
      </div>
      <a class="text-link" href="<?= e(url('services.php')) ?>">See the full menu</a>
    </div>

    <div class="price-grid">
      <?php foreach ($services as $service): ?>
        <article class="price-card">
          <p class="price-cat"><?= e($service['category_name'] ?? 'Treatment') ?></p>
          <h3><?= e($service['name']) ?></h3>
          <?php if (trim((string) $service['description']) !== ''): ?>
            <p class="price-desc"><?= e($service['description']) ?></p>
          <?php endif; ?>
          <p class="price-line"><span><?= e(money((int) $service['price_pence'])) ?></span><span><?= e(duration((int) $service['duration_minutes'])) ?></span></p>
          <a class="btn btn-outline btn-small" href="<?= e(url('booking.php?service=' . urlencode((string) $service['id']) . '&barber=' . urlencode((string) $barber['id']))) ?>">Book</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
