<?php
/**
 * The barber's public profile and the services they offer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/staff-boot.php';

$barber = StaffDAO::barber($barberId);

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $photo = trim((string) ($_POST['photo_url'] ?? ''));
        $years = (int) ($_POST['years_experience'] ?? 0);
        $salonId = trim((string) ($_POST['salon_id'] ?? ''));
        $removePhoto = isset($_POST['remove_photo']);
        $specialties = preg_split('/\r\n|\r|\n|,/', (string) ($_POST['specialties'] ?? '')) ?: [];

        // An uploaded file wins over whatever is in the link box.
        $uploaded = null;

        if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploaded = Uploads::storeTeamPhoto($_FILES['photo'], (string) ($barber['slug'] ?? 'team'));
            } catch (RuntimeException $error) {
                $errors[] = $error->getMessage();
            }
        }

        if ($name === '' || $role === '') {
            $errors[] = 'Name and job title are both required.';
        }

        if ($years < 0 || $years > 70) {
            $errors[] = 'Years of experience should be between 0 and 70.';
        }

        if ($uploaded === null && !$removePhoto && $photo !== '' && !filter_var($photo, FILTER_VALIDATE_URL)) {
            $errors[] = 'The photo link must be a full web address (https://...), or upload a file instead.';
        }

        if (mb_strlen($bio) > 2000) {
            $errors[] = 'Please keep the bio under 2000 characters.';
        }

        if ($errors === []) {
            if ($uploaded !== null) {
                Uploads::remove($barber['photo_url'] ?? null);
                $photo = $uploaded;
            } elseif ($removePhoto) {
                Uploads::remove($barber['photo_url'] ?? null);
                $photo = '';
            }

            StaffDAO::updateProfile($barberId, [
                'name' => $name,
                'role' => $role,
                'bio' => $bio,
                'photo_url' => $photo,
                'specialties' => $specialties,
                'years_experience' => $years,
                'salon_id' => $salonId,
                'active' => isset($_POST['active']),
            ]);

            StaffDAO::setOfferedServices($barberId, (array) ($_POST['services'] ?? []));

            Auth::flash('success', 'Your profile is updated.');
            header('Location: ' . url('barber/profile.php'));
            exit;
        }

        // Something else was wrong, so do not leave a stray file behind.
        if ($uploaded !== null) {
            Uploads::remove($uploaded);
        }
    }
}

$branches = BranchDAO::all();
$services = ServiceDAO::all();
$offered = StaffDAO::offeredServiceIds($barberId);
$currentPhoto = staff_photo_url($barber['photo_url'] ?? null);

$pageTitle = 'My profile';
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">My profile</h1>
<p class="lede">This is what customers read on the Team page before they choose you.</p>

<?php foreach ($errors as $error): ?>
  <p class="flash flash-error"><?= e($error) ?></p>
<?php endforeach; ?>

<section class="panel">
  <form method="post" class="stack-form" enctype="multipart/form-data">
    <?= Auth::csrfField() ?>

    <div class="profile-preview">
      <span class="thread-avatar thread-avatar-lg">
        <?php if ($currentPhoto !== ''): ?>
          <img src="<?= e($currentPhoto) ?>" alt="">
        <?php else: ?>
          <?= e(mb_substr((string) $barber['name'], 0, 1)) ?>
        <?php endif; ?>
      </span>
      <div>
        <p class="record-title"><?= e($barber['name']) ?> &middot; <?= e($barber['role']) ?></p>
        <p class="record-meta"><a class="text-link" href="<?= e(url('barber.php?slug=' . urlencode((string) $barber['slug']))) ?>">View public page</a></p>
      </div>
    </div>

    <label for="name">Display name</label>
    <input type="text" id="name" name="name" value="<?= e($barber['name']) ?>" required>

    <label for="role">Job title</label>
    <input type="text" id="role" name="role" value="<?= e($barber['role']) ?>" required>

    <label for="bio">Short bio</label>
    <textarea id="bio" name="bio" rows="5" maxlength="2000"><?= e($barber['bio']) ?></textarea>

    <label for="specialties">Specialities <span class="field-hint">one per line</span></label>
    <textarea id="specialties" name="specialties" rows="3"><?= e(implode("\n", json_list($barber['specialties']))) ?></textarea>

    <div class="field-pair">
      <div>
        <label for="years_experience">Years of experience</label>
        <input type="number" id="years_experience" name="years_experience" min="0" max="70" value="<?= (int) $barber['years_experience'] ?>">
      </div>
      <div>
        <label for="salon_id">Branch</label>
        <select id="salon_id" name="salon_id">
          <?php foreach ($branches as $branch): ?>
            <option value="<?= e($branch['id']) ?>" <?= $barber['salon_id'] === $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <fieldset class="upload-field">
      <legend>Profile photo</legend>

      <label for="photo">Upload a photo <span class="field-hint">JPG, PNG or WebP, up to <?= e(Uploads::maxLabel()) ?></span></label>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">

      <label for="photo_url">Or paste a link to one <span class="field-hint">leave blank if you uploaded a file</span></label>
      <input type="url" id="photo_url" name="photo_url"
             value="<?= Uploads::isUploaded($barber['photo_url'] ?? null) ? '' : e($barber['photo_url']) ?>"
             placeholder="https://...">

      <?php if (Uploads::isUploaded($barber['photo_url'] ?? null)): ?>
        <label class="rota-check rota-check-wide">
          <input type="checkbox" name="remove_photo" value="1">
          <span>Remove the photo I uploaded</span>
        </label>
      <?php endif; ?>
    </fieldset>

    <label class="rota-check rota-check-wide">
      <input type="checkbox" name="active" value="1" <?= (int) $barber['active'] === 1 ? 'checked' : '' ?>>
      <span>Show me on the public site</span>
    </label>

    <fieldset class="service-picker">
      <legend>Services I offer</legend>
      <p class="field-hint">Untick anything you do not perform. Customers only see you for these.</p>
      <div class="service-picker-grid">
        <?php foreach ($services as $service): ?>
          <label class="picker-item">
            <input type="checkbox" name="services[]" value="<?= e($service['id']) ?>" <?= isset($offered[$service['id']]) ? 'checked' : '' ?>>
            <span>
              <strong><?= e($service['name']) ?></strong>
              <em><?= e($service['category_name'] ?? '') ?> &middot; <?= e(money((int) $service['price_pence'])) ?></em>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <button type="submit" class="btn btn-primary">Save profile</button>
  </form>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>