<?php
/**
 * The treatment menu. The owner adds, edits, hides and deletes services here,
 * and everything on this page feeds the public menu and the booking form.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$profile = Auth::requireProfile();

if (!Auth::isAdmin()) {
    header('Location: ' . url('barber/index.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        Auth::flash('error', 'Your session expired. Please try again.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $serviceId = (string) ($_POST['service_id'] ?? '');

        if ($action === 'create') {
            $result = ServiceDAO::save(null, $_POST);
        } elseif ($action === 'update') {
            $result = ServiceDAO::save($serviceId, $_POST);
        } elseif ($action === 'toggle') {
            $result = ServiceDAO::setActive($serviceId, !empty($_POST['activate']));
        } elseif ($action === 'delete') {
            $result = ServiceDAO::remove($serviceId);
        } else {
            $result = ['ok' => false, 'message' => 'That change was not understood.'];
        }

        Auth::flash($result['ok'] ? 'success' : 'error', $result['message']);
    }

    header('Location: ' . url('admin/services.php'));
    exit;
}

$services = ServiceDAO::allForAdmin();
$categories = ServiceDAO::categoriesForAdmin();
$team = BarberDAO::all();
$teamCounts = ServiceDAO::barberCounts();

// ?edit=<id> loads that treatment into the form at the top of the page.
$editing = isset($_GET['edit']) ? ServiceDAO::byId((string) $_GET['edit']) : null;
$assigned = $editing !== null ? ServiceDAO::barberIdsFor((string) $editing['id']) : [];

$form = [
    'name' => (string) ($editing['name'] ?? ''),
    'slug' => (string) ($editing['slug'] ?? ''),
    'category_id' => (string) ($editing['category_id'] ?? ''),
    'description' => (string) ($editing['description'] ?? ''),
    'duration_minutes' => (string) ($editing['duration_minutes'] ?? 45),
    'price' => $editing !== null ? number_format(((int) $editing['price_pence']) / 100, 2, '.', '') : '',
    'featured' => (int) ($editing['featured'] ?? 0) === 1,
    'active' => $editing === null || (int) $editing['active'] === 1,
    'display_order' => (string) ($editing['display_order'] ?? 0),
];

$liveCount = count(array_filter($services, static fn ($service) => (int) $service['active'] === 1));

$pageTitle = 'Treatments';
$portal = [
    'kind' => 'staff',
    'name' => $profile['full_name'],
    'root' => 'admin',
    'subtitle' => 'Owner',
    'nav' => [
        'index.php' => 'Salon overview',
        'reports.php' => 'Reports',
        'services.php' => 'Treatments',
    ],
];
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">Treatments</h1>
<p class="lede">
  <?= count($services) ?> on the menu, <?= $liveCount ?> showing on the website.
  Prices and timings here drive the booking form.
</p>

<section class="panel" id="treatment-form">
  <div class="panel-head">
    <h2><?= $editing !== null ? 'Edit treatment' : 'Add a treatment' ?></h2>
    <?php if ($editing !== null): ?>
      <a class="text-link" href="<?= e(url('admin/services.php')) ?>">Cancel edit</a>
    <?php endif; ?>
  </div>

  <?php if ($editing === null && $categories === []): ?>
    <p class="muted">There are no categories yet, so new treatments will show under "Other".</p>
  <?php endif; ?>

  <form method="post" class="stack-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
    <?php if ($editing !== null): ?>
      <input type="hidden" name="service_id" value="<?= e((string) $editing['id']) ?>">
    <?php endif; ?>

    <label for="name">Name</label>
    <input type="text" id="name" name="name" maxlength="150" required value="<?= e($form['name']) ?>">

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id">
      <option value="">No category</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= e((string) $category['id']) ?>" <?= $form['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
          <?= e($category['name']) ?><?= (int) $category['active'] === 1 ? '' : ' (hidden category)' ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="description">Description <span class="field-hint">what the customer reads</span></label>
    <textarea id="description" name="description" rows="3" maxlength="1000"><?= e($form['description']) ?></textarea>

    <div class="field-pair">
      <div>
        <label for="price">Price <span class="field-hint">in pounds</span></label>
        <input type="text" id="price" name="price" inputmode="decimal" required placeholder="48.00" value="<?= e($form['price']) ?>">
      </div>
      <div>
        <label for="duration_minutes">Minutes <span class="field-hint">10 to 240</span></label>
        <input type="number" id="duration_minutes" name="duration_minutes" min="10" max="240" step="5" required value="<?= e($form['duration_minutes']) ?>">
      </div>
    </div>

    <div class="field-pair">
      <div>
        <label for="display_order">Position <span class="field-hint">lower shows first</span></label>
        <input type="number" id="display_order" name="display_order" value="<?= e($form['display_order']) ?>">
      </div>
      <div>
        <label for="slug">Web address <span class="field-hint">optional</span></label>
        <input type="text" id="slug" name="slug" maxlength="100" placeholder="made from the name" value="<?= e($form['slug']) ?>">
      </div>
    </div>

    <label class="rota-check">
      <input type="checkbox" name="featured" value="1" <?= $form['featured'] ? 'checked' : '' ?>>
      <span>Feature on the home page</span>
    </label>

    <label class="rota-check">
      <input type="checkbox" name="active" value="1" <?= $form['active'] ? 'checked' : '' ?>>
      <span>Show on the website</span>
    </label>

    <fieldset class="service-picker">
      <legend>Performed by</legend>
      <p class="field-hint">Only the people ticked here can take bookings for this treatment.</p>
      <div class="service-picker-grid">
        <?php foreach ($team as $member): ?>
          <?php $isAssigned = $editing === null || isset($assigned[(string) $member['id']]); ?>
          <label class="picker-item">
            <input type="checkbox" name="barbers[]" value="<?= e((string) $member['id']) ?>" <?= $isAssigned ? 'checked' : '' ?>>
            <span>
              <strong><?= e($member['name']) ?></strong>
              <em><?= e($member['role']) ?> &middot; <?= e(staff_kind_label((string) $member['staff_kind'])) ?></em>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button class="btn btn-primary" type="submit"><?= $editing !== null ? 'Save treatment' : 'Add treatment' ?></button>
  </form>
</section>

<section class="panel">
  <h2>The menu</h2>

  <?php if ($services === []): ?>
    <p class="muted">Nothing on the menu yet. Add the first treatment above.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>Treatment</th><th>Category</th><th>Time</th><th>Price</th><th>Team</th><th>Featured</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($services as $service): ?>
            <?php $isActive = (int) $service['active'] === 1; ?>
            <tr>
              <td>
                <strong><?= e($service['name']) ?></strong>
                <p class="muted muted-small" style="margin:.15rem 0 0"><?= e($service['slug']) ?></p>
              </td>
              <td><?= e($service['category_name'] ?? 'None') ?></td>
              <td><?= e(duration((int) $service['duration_minutes'])) ?></td>
              <td><?= e(money((int) $service['price_pence'])) ?></td>
              <td><?php $teamCount = $teamCounts[(string) $service['id']] ?? 0; ?>
                <?= $teamCount === 0 ? '<span class="badge badge-offline">nobody</span>' : $teamCount ?>
              </td>
              <td><?= (int) $service['featured'] === 1 ? 'Yes' : '' ?></td>
              <td><span class="badge <?= $isActive ? 'badge-available' : 'badge-offline' ?>"><?= $isActive ? 'Live' : 'Hidden' ?></span></td>
              <td>
                <div class="stack-inline">
                  <a class="btn btn-small btn-outline" href="<?= e(url('admin/services.php?edit=' . urlencode((string) $service['id']))) ?>#treatment-form">Edit</a>

                  <form method="post" class="inline-form inline-form-tight">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                    <input type="hidden" name="activate" value="<?= $isActive ? '0' : '1' ?>">
                    <button class="btn btn-small btn-quiet" type="submit"><?= $isActive ? 'Hide' : 'Show' ?></button>
                  </form>

                  <form method="post" onsubmit="return confirm('Delete this treatment for good?');">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                    <button class="btn btn-small btn-quiet" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="muted">
    A treatment that already has appointments can be hidden but not deleted, so past
    bookings keep the name and price the customer was quoted.
  </p>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>
