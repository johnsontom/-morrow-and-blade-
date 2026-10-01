<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Services & prices';
$pageDescription = 'Haircuts, beard work, grooming, massage, manicure and pedicure prices at '
    . SITE_NAME . '.';
$currentBranch = BranchDAO::current();
$salon = $currentBranch ?? ($salon ?? SalonDAO::primary());
$branchId = $currentBranch['id'] ?? null;
$grouped = $branchId !== null ? ServiceDAO::groupedByCategoryAtSalon($branchId) : ServiceDAO::groupedByCategory();

require __DIR__ . '/includes/header.php';
?>

<section class="section section-alt" style="padding-top:3rem">
  <div class="page-shell">
    <p class="eyebrow">The menu &middot; <?= e($currentBranch['name'] ?? SITE_NAME) ?></p>
    <h2>Everything you need. Nothing you do not.</h2>
    <p class="lede" style="margin-top:1rem">
      Every appointment starts with a short consultation. Prices are fixed and shown
      in full, so there is nothing to work out later. Hair and beard services are
      carried out by our barbers; massage and nail treatments are carried out by our
      therapists and nail technicians.
    </p>
  </div>
</section>

<section class="section">
  <div class="page-shell">
    <?php foreach ($grouped as $category => $services): ?>
      <div class="category-block">
        <div class="category-head">
          <h3><?= e($category) ?></h3>
          <span class="category-count"><?= count($services) ?> treatment<?= count($services) === 1 ? '' : 's' ?></span>
        </div>

        <table class="price-table">
          <thead>
            <tr>
              <th scope="col">Treatment</th>
              <th scope="col">Duration</th>
              <th scope="col" class="price">Price</th>
              <th scope="col"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $service): ?>
              <tr>
                <td>
                  <strong><?= e($service['name']) ?></strong>
                  <?php if ((int) $service['featured'] === 1): ?>
                    <span class="featured-flag">Most booked</span>
                  <?php endif; ?>
                  <br>
                  <span style="color:var(--muted);font-size:.9rem"><?= e($service['description']) ?></span>
                </td>
                <td class="dur"><?= e(duration((int) $service['duration_minutes'])) ?></td>
                <td class="price"><?= e(money((int) $service['price_pence'])) ?></td>
                <td>
                  <a class="text-link" href="<?= e(url('booking.php?service=' . urlencode($service['slug']))) ?>">Book</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
