<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = SITE_NAME;
$currentBranch = BranchDAO::current();
$salon = $currentBranch ?? ($salon ?? SalonDAO::primary());
$branchId = $currentBranch['id'] ?? null;

$team = BarberDAO::withAvailability(null, $branchId);
$menu = $branchId !== null ? ServiceDAO::allAtSalon($branchId) : ServiceDAO::all();
$featured = array_values(array_filter($menu, fn ($s) => (int) $s['featured'] === 1));
$availableNow = count(array_filter($team, fn ($b) => $b['status'] === 'available'));
$areaLabel = salon_area_label($salon);
$heroList = array_slice(
    array_values(array_filter($team, fn ($b) => $b['status'] === 'available')) ?: $team,
    0,
    5
);

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="hero-art" aria-hidden="true">
    <span class="hero-glow hero-glow-gold"></span>
    <span class="hero-glow hero-glow-oxblood"></span>
    <span class="hero-glow hero-glow-forest"></span>
  </div>
  <div class="hero-noise" aria-hidden="true"></div>
  <div class="hero-scrim" aria-hidden="true"></div>

  <div class="page-shell hero-inner">
    <div class="hero-copy-block">
      <p class="eyebrow">Modern barbering, massage &amp; nail care &mdash; <?= e($areaLabel) ?></p>
      <h1>Morrow<span>&amp; Blade</span></h1>
      <p class="hero-tagline">Your cut. Your barber. Your time.</p>
      <p class="hero-lede">
        See who is free, choose the right treatment, and reserve your chair without the
        back-and-forth. Barbering, massage, manicure and pedicure, all under one roof.
      </p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('booking.php')) ?>">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/></svg>
          Book an appointment
        </a>
        <a class="btn btn-light" href="<?= e(url('barbers.php')) ?>">
          Meet the team
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
      <div class="hero-trust">
        <span>
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
          Secure instant booking
        </span>
        <span>
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0l1.58 6.14a2 2 0 0 0 1.44 1.44l6.14 1.58a.5.5 0 0 1 0 .96l-6.14 1.58a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0z"/></svg>
          4.8 average rating
        </span>
      </div>

      <div class="hero-panel-card">
        <div class="hero-panel-head">
          <p class="hero-panel-title"><?= $availableNow > 0 ? 'Free right now' : 'The team' ?></p>
          <p class="hero-panel-sub">
            <?php if ($availableNow > 0): ?>
              <?= $availableNow ?> of <?= count($team) ?> available this minute
            <?php else: ?>
              Nobody free this minute &mdash; book the next open slot
            <?php endif; ?>
          </p>
        </div>
        <div class="hero-panel-rows">
          <?php foreach ($heroList as $member): ?>
            <div class="hero-panel-row">
              <p class="hero-panel-name"><?= e($member['name']) ?></p>
              <span class="<?= e(status_class($member['status'])) ?>"><?= e(ucfirst($member['status'])) ?></span>
              <p class="hero-panel-meta"><?= e($member['role']) ?> &middot; next <?= e($member['next_available_label']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        <a class="hero-panel-link" href="<?= e(url('barbers.php')) ?>">See all <?= count($team) ?> team members &rarr;</a>
      </div>
    </div>

    <div class="hero-visual">
      <?php require __DIR__ . '/includes/hero-chair.php'; ?>
    </div>
  </div>

  <a class="hero-scroll" href="#availability">
    Explore
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
  </a>
</section>

<section class="section availability-section" id="availability">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <p class="eyebrow">Live availability</p>
        <h2>Who is free right now</h2>
      </div>
      <p class="lede"><?= $availableNow ?> of <?= count($team) ?> team members are available this minute. Status updates as appointments start and finish.</p>
    </div>

    <div class="availability-grid">
      <?php foreach ($team as $member): ?>
        <article class="availability-card">
          <div class="availability-top">
            <div>
              <h3 class="availability-name"><?= e($member['name']) ?></h3>
              <p class="availability-role"><?= e($member['role']) ?> &middot; <?= e($member['staff_kind_label']) ?></p>
            </div>
            <span class="<?= e(status_class($member['status'])) ?>"><?= e(ucfirst($member['status'])) ?></span>
          </div>
          <p class="availability-next"><?= e($member['status_message']) ?></p>
          <p class="availability-hours">Today <?= e($member['working_hours_label']) ?> &middot; next <?= e($member['next_available_label']) ?></p>
          <p style="margin:1rem 0 0">
            <a class="text-link" href="<?= e(url('barbers.php#' . $member['slug'])) ?>">View profile</a>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <p class="eyebrow">Most booked</p>
        <h2>Popular treatments</h2>
      </div>
      <a class="text-link" href="<?= e(url('services.php')) ?>">See the full menu &rarr;</a>
    </div>

    <div class="card-grid">
      <?php foreach (array_slice($featured, 0, 4) as $service): ?>
        <article class="card">
          <div class="card-body">
            <p class="featured-flag"><?= e($service['category_name'] ?? 'Treatment') ?></p>
            <h3><?= e($service['name']) ?></h3>
            <p><?= e($service['description']) ?></p>
            <div class="card-meta">
              <span class="card-price"><?= e(money((int) $service['price_pence'])) ?></span>
              <span>&middot;</span>
              <span><?= e(duration((int) $service['duration_minutes'])) ?></span>
            </div>
            <p style="margin-top:1rem">
              <a class="btn btn-outline" href="<?= e(url('booking.php?service=' . urlencode($service['slug']))) ?>">Book this</a>
            </p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="stat-row">
      <div>
        <p class="stat-value"><?= count($menu) ?></p>
        <p class="stat-label">Treatments on the menu</p>
      </div>
      <div>
        <p class="stat-value"><?= count($team) ?></p>
        <p class="stat-label">Barbers, therapists and nail techs</p>
      </div>
      <div>
        <p class="stat-value">4.8</p>
        <p class="stat-label">Average client rating</p>
      </div>
      <div>
        <p class="stat-value">2016</p>
        <p class="stat-label">Independent since</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <p class="eyebrow">The team</p>
        <h2>Choose your chair</h2>
      </div>
      <a class="text-link" href="<?= e(url('barbers.php')) ?>">All team members &rarr;</a>
    </div>

    <div class="card-grid card-grid-team">
      <?php foreach (array_slice($team, 0, 5) as $member): ?>
        <article class="card">
          <?php if (staff_photo_url($member['photo_url']) !== ''): ?>
            <img class="card-photo" src="<?= e(staff_photo_url($member['photo_url'])) ?>" alt="<?= e($member['name']) ?>">
          <?php endif; ?>
          <div class="card-body">
            <div class="availability-top">
              <div>
                <h3><?= e($member['name']) ?></h3>
                <p class="availability-role"><?= e($member['role']) ?></p>
              </div>
              <span class="<?= e(status_class($member['status'])) ?>"><?= e(ucfirst($member['status'])) ?></span>
            </div>
            <div class="chips">
              <?php foreach ($member['specialties_list'] as $specialty): ?>
                <span class="chip"><?= e($specialty) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
