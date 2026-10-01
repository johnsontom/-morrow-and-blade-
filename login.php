<?php
/**
 * Customer sign-in. Booking as a guest still works; signing in adds booking
 * history and lets the customer message their barber.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

Auth::boot();

$email = '';
$error = null;
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');

if (Auth::customer() !== null) {
    header('Location: ' . url('account/index.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email address and password.';
    } elseif (Auth::attemptCustomer($email, $password)) {
        $destination = $next !== '' && str_starts_with($next, '/') ? $next : url('account/index.php');
        header('Location: ' . $destination);
        exit;
    } else {
        $error = 'We could not match those details. Check the password, or create an account below.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="page-shell auth-shell">
    <div class="auth-card">
      <p class="eyebrow">Your account</p>
      <h2>Sign in</h2>
      <p class="lede">See your appointments, rebook in a tap and message the person who cuts your hair.</p>

      <?php if ($error !== null): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <?= Auth::csrfField() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>

      <p class="auth-alt">New here? <a class="text-link" href="<?= e(url('register.php')) ?>">Create an account</a>.</p>
      <p class="auth-alt">Work at the salon? <a class="text-link" href="<?= e(url('barber/login.php')) ?>">Team sign in</a>.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>