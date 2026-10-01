<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Book an appointment';

$currentBranch = BranchDAO::current();
$salon = $currentBranch ?? ($salon ?? SalonDAO::primary());
$branchId = $currentBranch['id'] ?? null;

$services = $branchId !== null ? ServiceDAO::allAtSalon($branchId) : ServiceDAO::all();
$team = BarberDAO::all($branchId);
$menu = $branchId !== null ? ServiceDAO::groupedByCategoryAtSalon($branchId) : ServiceDAO::groupedByCategory();

$errors = [];
$notice = null;

// ---------------------------------------------------------------- submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $input = [
        'barber_id' => (string) ($_POST['barber_id'] ?? ''),
        'service_id' => (string) ($_POST['service_id'] ?? ''),
        'starts_at' => (string) ($_POST['starts_at'] ?? ''),
        'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
        'customer_email' => trim((string) ($_POST['customer_email'] ?? '')),
        'customer_phone' => trim((string) ($_POST['customer_phone'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'marketing_opt_in' => isset($_POST['marketing_opt_in']),
    ];

    if ($input['customer_name'] === '' || mb_strlen($input['customer_name']) < 2) {
        $errors[] = 'Please enter your name.';
    }

    if (!filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen(preg_replace('/\D/', '', $input['customer_phone']) ?? '') < 7) {
        $errors[] = 'Please enter a contact phone number.';
    }

    if ($input['barber_id'] === '' || $input['service_id'] === '' || $input['starts_at'] === '') {
        $errors[] = 'Please choose a treatment, a team member and a time.';
    }

    if ($errors === []) {
        try {
            $result = BookingDAO::create($input);
            header('Location: ' . url('confirmation.php?reference=' . urlencode($result['reference'])
                . '&token=' . urlencode($result['token'])));
            exit;
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
        }
    }
}

// ------------------------------------------------------------------ the form
$selectedService = null;
$selectedStaff = null;
$selectedDate = (string) ($_GET['date'] ?? '');
$slots = [];

if (isset($_GET['service']) && $_GET['service'] !== '') {
    $selectedService = ServiceDAO::find((string) $_GET['service']);
}

if (isset($_GET['barber']) && $_GET['barber'] !== '') {
    $selectedStaff = BarberDAO::find((string) $_GET['barber']);
}

// A shared link can point at a treatment or person from another branch. The
// menu stays local, but a specifically requested item is still bookable.
if ($selectedService !== null && !in_array($selectedService['id'], array_column($services, 'id'), true)) {
    $services[] = $selectedService;
    $menu[$selectedService['category_name'] ?? 'Other'][] = $selectedService;
}

if ($selectedStaff !== null && !in_array($selectedStaff['id'], array_column($team, 'id'), true)) {
    $team[] = $selectedStaff;
}

if ($selectedService === null && isset($_POST['service_id'])) {
    foreach ($services as $service) {
        if ($service['id'] === ($_POST['service_id'] ?? '')) {
            $selectedService = $service;
        }
    }
}

if ($selectedStaff === null && isset($_POST['barber_id'])) {
    foreach ($team as $member) {
        if ($member['id'] === ($_POST['barber_id'] ?? '')) {
            $selectedStaff = $member;
        }
    }
}

// A date must be a real date and not in the past.
if ($selectedDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = (new DateTimeImmutable('now', new DateTimeZone(SITE_TIMEZONE)))->format('Y-m-d');
}

$today = (new DateTimeImmutable('now', new DateTimeZone(SITE_TIMEZONE)))->format('Y-m-d');
if ($selectedDate < $today) {
    $selectedDate = $today;
}

if ($selectedService !== null && $selectedStaff !== null) {
    $slots = BookingDAO::availableSlots($selectedStaff['id'], $selectedService['id'], $selectedDate);
}

require __DIR__ . '/includes/header.php';
?>

<section class="section section-alt" style="padding-top:3rem">
  <div class="page-shell">
    <p class="eyebrow">Booking &middot; <?= e($currentBranch['name'] ?? SITE_NAME) ?></p>
    <h2>Reserve your time</h2>
    <p class="lede" style="margin-top:1rem">
      Choose a treatment, pick who you would like to see, then select from the
      times that are genuinely free. Greyed out times are already taken.
    </p>
  </div>
</section>

<section class="section">
  <div class="page-shell">
    <?php foreach ($errors as $message): ?>
      <p style="background:rgba(109,37,49,.1);border:1px solid rgba(109,37,49,.3);color:var(--oxblood);padding:.85rem 1rem;border-radius:6px">
        <?= e($message) ?>
      </p>
    <?php endforeach; ?>

    <form method="get" action="<?= e(url('booking.php')) ?>" class="info-grid" style="align-items:end">
      <div>
        <label for="service"><strong>Treatment</strong></label><br>
        <select id="service" name="service" style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px;background:var(--white)">
          <option value="">Choose a treatment</option>
          <?php foreach ($menu as $category => $group): ?>
            <optgroup label="<?= e($category) ?>">
              <?php foreach ($group as $service): ?>
                <option value="<?= e($service['slug']) ?>" <?= ($selectedService['id'] ?? '') === $service['id'] ? 'selected' : '' ?>>
                  <?= e($service['name']) ?> - <?= e(money((int) $service['price_pence'])) ?> (<?= e(duration((int) $service['duration_minutes'])) ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="barber"><strong>Team member</strong></label><br>
        <select id="barber" name="barber" style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px;background:var(--white)">
          <option value="">No preference</option>
          <?php foreach ($team as $member): ?>
            <option value="<?= e($member['slug']) ?>" <?= ($selectedStaff['id'] ?? '') === $member['id'] ? 'selected' : '' ?>>
              <?= e($member['name']) ?> - <?= e($member['role']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="date"><strong>Date</strong></label><br>
        <input type="date" id="date" name="date" value="<?= e($selectedDate) ?>" min="<?= e($today) ?>"
               style="width:100%;padding:.65rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px;background:var(--white)">
      </div>

      <div>
        <button class="btn btn-primary" type="submit" style="width:100%">Show times</button>
      </div>
    </form>
  </div>
</section>

<?php if ($selectedService !== null && $selectedStaff !== null): ?>
<section class="section" style="padding-top:0">
  <div class="page-shell">
    <div class="section-head">
      <div>
        <h3><?= e($selectedService['name']) ?> with <?= e($selectedStaff['name']) ?></h3>
        <p class="lede" style="margin-top:.5rem">
          <?= e(date('l j F Y', strtotime($selectedDate))) ?> &middot;
          <?= e(duration((int) $selectedService['duration_minutes'])) ?> &middot;
          <?= e(money((int) $selectedService['price_pence'])) ?>
        </p>
      </div>
    </div>

    <?php if ($slots === []): ?>
      <p class="lede">There are no published hours for that day. Please try another date.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('booking.php')) ?>">
        <input type="hidden" name="service_id" value="<?= e($selectedService['id']) ?>">
        <input type="hidden" name="barber_id" value="<?= e($selectedStaff['id']) ?>">

        <fieldset style="border:1px solid var(--line);border-radius:8px;padding:1.25rem;background:var(--white)">
          <legend class="eyebrow">Available times</legend>
          <div style="display:grid;gap:.5rem;grid-template-columns:repeat(auto-fill,minmax(6rem,1fr))">
            <?php foreach ($slots as $index => $slot): ?>
              <label class="slot-label" style="display:block;text-align:center;padding:.6rem;border:1px solid var(--line);border-radius:6px;<?= $slot['available'] ? '' : 'opacity:.45;text-decoration:line-through' ?>">
                <?php if ($slot['available']): ?>
                  <input type="radio" name="starts_at" value="<?= e($slot['starts_at']) ?>" <?= $index === 0 ? '' : '' ?> required>
                <?php else: ?>
                  <input type="radio" disabled>
                <?php endif; ?>
                <span style="display:block"><?= e($slot['time']) ?></span>
                <?php if (!$slot['available']): ?>
                  <span style="display:block;font-size:.65rem;color:var(--muted)"><?= e($slot['reason']) ?></span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="info-grid" style="margin-top:2rem">
          <div>
            <label for="customer_name"><strong>Your name</strong></label>
            <input id="customer_name" name="customer_name" required maxlength="80"
                   value="<?= e((string) ($_POST['customer_name'] ?? '')) ?>"
                   style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px">
          </div>
          <div>
            <label for="customer_email"><strong>Email</strong></label>
            <input id="customer_email" name="customer_email" type="email" required maxlength="160"
                   value="<?= e((string) ($_POST['customer_email'] ?? '')) ?>"
                   style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px">
          </div>
          <div>
            <label for="customer_phone"><strong>Phone</strong></label>
            <input id="customer_phone" name="customer_phone" required maxlength="30"
                   value="<?= e((string) ($_POST['customer_phone'] ?? '')) ?>"
                   style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px">
          </div>
        </div>

        <p style="margin-top:1.25rem">
          <label for="notes"><strong>Anything we should know? (optional)</strong></label>
          <textarea id="notes" name="notes" rows="3" maxlength="500"
                    style="width:100%;padding:.7rem;margin-top:.4rem;border:1px solid var(--line);border-radius:6px"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
        </p>

        <p>
          <label style="font-size:.9rem">
            <input type="checkbox" name="marketing_opt_in" value="1"> Email me occasional offers
          </label>
        </p>

        <p class="muted" style="font-size:.85rem;margin-top:1.25rem">
          Your slot is held straight away. <?= e($selectedStaff['name']) ?> confirms the request
          and you get an email - you can cancel it yourself until they do.
        </p>

        <button class="btn btn-primary" type="submit">Request this booking</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
