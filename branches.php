<?php
/**
 * Branch finder. Shows every salon with a map, and can sort them by how far
 * away the visitor is using the browser's location.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$branches = BranchDAO::all();
$selectedSlug = trim((string) ($_GET['branch'] ?? ''));
$selected = null;

foreach ($branches as $branch) {
    if ($branch['slug'] === $selectedSlug) {
        $selected = $branch;
        break;
    }
}

$selected ??= BranchDAO::current();
$selected ??= $branches[0] ?? null;
$teamCounts = BranchDAO::teamCounts();

$pageTitle = 'Find your salon';
$pageDescription = 'Three London branches, live opening hours and a one-tap directions link.';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell">
    <p class="eyebrow">Visit us</p>
    <h1>Find your nearest salon</h1>
    <p class="lede">
      Share your location and we will put the closest branch first, or pick one from the list.
      Every branch keeps the same prices and the same team standards.
    </p>

    <div class="locator-bar">
      <button type="button" class="btn btn-primary" id="use-location" data-api="<?= e(url('api/nearest-salon.php')) ?>">
        Use my location
      </button>
      <p class="locator-status" id="locator-status" role="status">Not using your location yet.</p>
    </div>
  </div>
</section>

<?php if ($selected !== null): ?>
<section class="section section-alt">
  <div class="page-shell branch-layout">
    <div class="branch-map">
      <iframe title="Map of <?= e($selected['name']) ?>" src="<?= e($selected['map_embed_url']) ?>" loading="lazy"></iframe>
    </div>

    <div class="branch-detail">
      <p class="eyebrow"><?= (int) $selected['is_primary'] === 1 ? 'Flagship' : 'Branch' ?></p>
      <h2><?= e($selected['name']) ?></h2>
      <p><?= e($selected['address_line_1']) ?><br><?= e($selected['city']) ?> <?= e($selected['postcode']) ?></p>
      <p><a class="text-link" href="tel:<?= e(preg_replace('/\s+/', '', (string) $selected['phone'])) ?>"><?= e($selected['phone']) ?></a><br>
         <a class="text-link" href="mailto:<?= e($selected['email']) ?>"><?= e($selected['email']) ?></a></p>
      <a class="btn btn-primary" href="<?= e($selected['map_url']) ?>" target="_blank" rel="noopener">Get directions</a>

      <table class="hours-table">
        <?php foreach (($selected['opening_hours_decoded'] ?? []) as $day): ?>
          <tr>
            <th scope="row"><?= e($day['day'] ?? '') ?></th>
            <td><?= e($day['hours'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <p class="eyebrow">All branches</p>
        <h2><?= count($branches) ?> salons across London</h2>
      </div>
    </div>

    <ul class="branch-list" id="branch-list">
      <?php foreach ($branches as $branch): ?>
        <li class="branch-card" data-slug="<?= e($branch['slug']) ?>">
          <div class="branch-card-top">
            <h3><a class="text-link" href="<?= e(url('branches.php?branch=' . urlencode((string) $branch['slug']))) ?>"><?= e($branch['name']) ?></a></h3>
            <span class="branch-distance" data-distance hidden></span>
          </div>
          <p class="branch-address"><?= e($branch['address_line_1']) ?>, <?= e($branch['city']) ?> <?= e($branch['postcode']) ?></p>
          <p class="branch-meta"><?= (int) ($teamCounts[$branch['id']] ?? 0) ?> team members &middot; <a class="text-link" href="tel:<?= e(preg_replace('/\s+/', '', (string) $branch['phone'])) ?>"><?= e($branch['phone']) ?></a></p>
          <p class="branch-actions">
            <a class="btn btn-small btn-outline" href="<?= e($branch['map_url']) ?>" target="_blank" rel="noopener">Directions</a>
            <a class="btn btn-small btn-outline" href="<?= e(url('booking.php?branch=' . urlencode((string) $branch['slug']))) ?>">Book here</a>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<script src="<?= e(asset('js/branches.js')) ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
