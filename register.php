<?php
/**
 * Customer registration. If the email already has guest bookings we attach
 * the new password to that record so the history is not lost.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

Auth::boot();

$name = $email = $phone = '';
$error = null;

if (Auth::customer() !== null) {
    header('Location: ' . url('account/index.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($name === '' || $email === '' || $phone === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address does not look right.';
    } elseif (strlen($password) < 8) {
        $error = 'Use at least 8 characters for your password.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $id = Auth::registerCustomer($name, $email, $phone, $password);

        if ($id === null) {
            $error = 'An account already exists for that email. Try signing in instead.';
        } else {
            Auth::flash('success', 'Welcome to Morrow & Blade, ' . $name . '. Your account is ready.');
            header('Location: ' . url('account/index.php'));
            exit;
        }
    }
}

$pageTitle = 'Create an account';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell auth-shell">
    <div class="auth-card">
      <p class="eyebrow">Join us</p>
      <h2>Create your account</h2>
      <p class="lede">Save your details, keep every booking in one place and ask your barber for advice between visits.</p>

      <?php if ($error !== null): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <?= Auth::csrfField() ?>

        <label for="name">Full name</label>
        <input type="text" id="name" name="name" value="<?= e($name) ?>" required autocomplete="name">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">

        <label for="phone">Mobile number</label>
        <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" required autocomplete="tel">

        <label for="password">Password <span class="field-hint">at least 8 characters</span></label>
        <input type="password" id="password" name="password" required autocomplete="new-password">

        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">

        <button type="submit" class="btn btn-primary">Create account</button>
      </form>

      <p class="auth-alt">Already with us? <a class="text-link" href="<?= e(url('login.php')) ?>">Sign in</a>.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>