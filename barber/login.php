<?php
/**
 * Staff sign-in. One door for owners, managers and barbers - the role on the
 * profile decides where they land.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

Auth::boot();

$email = '';
$error = null;
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');

if (Auth::profile() !== null) {
    header('Location: ' . url(Auth::isAdmin() ? 'admin/index.php' : 'barber/index.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!Auth::checkCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your work email and password.';
    } elseif (Auth::attemptProfile($email, $password)) {
        $profile = Auth::profile();

        if ($profile !== null && in_array($profile['role'], ['pending', 'staff'], true) && $profile['barber_id'] === null) {
            Auth::logout();
            $error = 'That account has not been given access to the portal yet. Ask the salon owner to activate it.';
        } else {
            $fallback = Auth::isAdmin() ? url('admin/index.php') : url('barber/index.php');
            $destination = $next !== '' && str_starts_with($next, '/') ? $next : $fallback;
            Auth::flash('success', 'Signed in as ' . ($profile['full_name'] ?? $email) . '.');
            header('Location: ' . $destination);
            exit;
        }
    } else {
        $error = 'Those details were not recognised.';
    }
}

$pageTitle = 'Team sign in';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="page-shell auth-shell">
    <div class="auth-card auth-card-dark">
      <p class="eyebrow">Staff only</p>
      <h2>Team sign in</h2>
      <p class="lede">Manage your own rota, availability and appointments. Owners also see the whole salon.</p>

      <?php if ($error !== null): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <?= Auth::csrfField() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label for="email">Work email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn btn-primary">Sign in</button>
      </form>

      <p class="auth-alt">Booking with us instead? <a class="text-link" href="<?= e(url('login.php')) ?>">Customer sign in</a>.</p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>