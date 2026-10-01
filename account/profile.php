<?php
/**
 * Customer details: name, mobile and a preferred barber.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$customer = Auth::requireCustomer();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $preferred = trim((string) ($_POST['preferred_barber_id'] ?? ''));

        if ($name === '' || $phone === '') {
            $errors[] = 'Name and mobile number are both required.';
        }

        $newPassword = (string) ($_POST['new_password'] ?? '');

        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $errors[] = 'Use at least 8 characters for a new password.';
            } elseif ($newPassword !== (string) ($_POST['new_password_confirm'] ?? '')) {
                $errors[] = 'The new passwords do not match.';
            }
        }

        if ($errors === []) {
            AccountDAO::updateCustomerProfile((string) $customer['id'], $name, $phone, $preferred !== '' ? $preferred : null);

            if ($newPassword !== '') {
                AccountDAO::setCustomerPassword((string) $customer['id'], $newPassword);
            }

            Auth::flash('success', 'Your details are saved.');
            header('Location: ' . url('account/profile.php'));
            exit;
        }
    }
}

$customer = AccountDAO::findCustomerById((string) $customer['id']);
$team = BarberDAO::all();
$unread = MessageDAO::unreadForCustomer((string) $customer['id']);

$pageTitle = 'My details';
$portal = [
    'kind' => 'customer',
    'name' => $customer['name'],
    'root' => 'account',
    'subtitle' => 'My account',
    'badges' => ['messages.php' => $unread],
    'nav' => [
        'index.php' => 'Overview',
        'appointments.php' => 'My appointments',
        'messages.php' => 'Messages',
        'profile.php' => 'My details',
    ],
];
require __DIR__ . '/../includes/portal-header.php';
?>

<h1 class="portal-h1">My details</h1>
<p class="lede">Keep your contact number current so we can reach you about your appointment.</p>

<?php foreach ($errors as $error): ?>
  <p class="flash flash-error"><?= e($error) ?></p>
<?php endforeach; ?>

<section class="panel">
  <form method="post" class="stack-form">
    <?= Auth::csrfField() ?>

    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?= e($customer['name']) ?>" required>

    <label for="email">Email address <span class="field-hint">contact us to change this</span></label>
    <input type="email" id="email" value="<?= e($customer['email']) ?>" disabled>

    <label for="phone">Mobile number</label>
    <input type="tel" id="phone" name="phone" value="<?= e($customer['phone']) ?>" required>

    <label for="preferred_barber_id">Preferred team member</label>
    <select id="preferred_barber_id" name="preferred_barber_id">
      <option value="">No preference</option>
      <?php foreach ($team as $member): ?>
        <option value="<?= e($member['id']) ?>" <?= $customer['preferred_barber_id'] === $member['id'] ? 'selected' : '' ?>>
          <?= e($member['name']) ?> &middot; <?= e($member['staff_kind_label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="new_password">New password <span class="field-hint">leave blank to keep your current one</span></label>
    <input type="password" id="new_password" name="new_password" autocomplete="new-password">

    <label for="new_password_confirm">Confirm new password</label>
    <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password">

    <button type="submit" class="btn btn-primary">Save changes</button>
  </form>
</section>

<?php require __DIR__ . '/../includes/portal-footer.php'; ?>