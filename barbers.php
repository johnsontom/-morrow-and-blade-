<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Our team';
$pageDescription = 'Barbers, massage therapists and nail technicians at ' . SITE_NAME . '.';
$currentBranch = BranchDAO::current();
$salon = $currentBranch ?? ($salon ?? SalonDAO::primary());
$branchId = $currentBranch['id'] ?? null;
$team = BarberDAO::withAvailability(null, $branchId);
$teamCount = count($team);

require __DIR__ . '/includes/header.php';
?>

<section class="section section-alt" style="padding-top:3rem">
  <div class="page-shell">
    <p class="eyebrow">The team &middot; <?= e($currentBranch['name'] ?? SITE_NAME) ?></p>
    <h2><?= $teamCount === 1 ? 'One barber, one point of view.' : $teamCount . ' people, ' . $teamCount . ' points of view.' ?></h2>
    <p class="lede" style="margin-top:1rem">
      Compare specialities, experience and current availability before you book.
      Each profile lists exactly which treatments that person carries out.
    </p>
  </div>
</section>

<section class="section">
  <div class="page-shell">
    <div class="card-grid">
      <?php foreach ($team as $member): ?>
        <article class="card" id="<?= e($member['slug']) ?>">
          <?php if (staff_photo_url($member['photo_url']) !== ''): ?>
            <img class="card-photo" src="<?= e(staff_photo_url($member['photo_url'])) ?>" alt="<?= e($member['name']) ?>">
          <?php endif; ?>
          <div class="card-body">
            <div class="availability-top">
              <div>
                <h3><a class="card-link" href="<?= e(url('barber.php?slug=' . urlencode((string) $member['slug']))) ?>"><?= e($member['name']) ?></a></h3>
                <p class="availability-role"><?= e($member['role']) ?></p>
              </div>
              <span class="<?= e(status_class($member['status'])) ?>"><?= e(ucfirst($member['status'])) ?></span>
            </div>

            <p style="margin-top:.9rem"><?= e($member['bio']) ?></p>
            <p class="availability-next"><?= e($member['status_message']) ?></p>
            <p class="availability-hours">Today <?= e($member['working_hours_label']) ?></p>

            <div class="chips">
              <?php foreach ($member['specialties_list'] as $specialty): ?>
                <span class="chip"><?= e($specialty) ?></span>
              <?php endforeach; ?>
            </div>

            <div class="card-meta">
              <strong><?= e(number_format((float) $member['rating'], 1)) ?></strong>
              <span>&middot;</span>
              <span><?= (int) $member['review_count'] ?> reviews</span>
              <span>&middot;</span>
              <span><?= (int) $member['years_experience'] ?> yrs</span>
            </div>

            <?php $offered = ServiceDAO::forStaff($member['id']); ?>
            <?php if ($offered !== []): ?>
              <p style="margin-top:1rem;font-size:.8rem;color:var(--muted)">
                <?= count($offered) ?> treatment<?= count($offered) === 1 ? '' : 's' ?> available to book
              </p>
            <?php endif; ?>

            <p class="card-actions">
              <a class="btn btn-primary btn-small" href="<?= e(url('booking.php?barber=' . urlencode((string) $member['slug']))) ?>">Book <?= e(explode(' ', (string) $member['name'])[0]) ?></a>
              <a class="btn btn-outline btn-small" href="<?= e(url('barber.php?slug=' . urlencode((string) $member['slug']))) ?>">Full profile</a>
            </p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
