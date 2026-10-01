<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$salon = $salon ?? SalonDAO::primary();
$currentBranch = BranchDAO::current();
$branchChosen = BranchDAO::hasChosen();
$allBranches = BranchDAO::all();
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? 'Live availability, transparent prices and instant booking at '
    . SITE_NAME . ' in London.';
$navItems = [
    'index.php' => 'Home',
    'services.php' => 'Services',
    'barbers.php' => 'Team',
    'branches.php' => 'Salons',
    'ai.php' => 'Ask us',
    'contact.php' => 'Visit us',
];
$navCustomer = Auth::customer();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle === SITE_NAME ? SITE_NAME . ' | Modern London Barbers' : $pageTitle . ' | ' . SITE_NAME) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="theme-color" content="#11110f">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(asset('img/icon.png')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="page-shell header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset('img/brand-mark.png')) ?>" alt="Morrow & Blade"></span>
      <span class="brand-text">
        <span class="brand-name">Morrow &amp; Blade</span>
        <span class="brand-sub"><?= e(salon_area_label($currentBranch ?? $salon)) ?></span>
      </span>
    </a>

    <input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>
    <label for="nav-toggle" class="nav-button" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </label>

    <nav class="site-nav" aria-label="Main navigation">
      <?php foreach ($navItems as $file => $label): ?>
        <a href="<?= e(url($file)) ?>" <?= is_current($file) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($navCustomer !== null): ?>
        <a href="<?= e(url('account/index.php')) ?>" <?= is_current('index.php') && str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/account/') ? 'aria-current="page"' : '' ?>>My account</a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Sign in</a>
      <?php endif; ?>
      <a class="nav-cta" href="<?= e(url('booking.php')) ?>">Book now</a>
    </nav>
  </div>
</header>

<?php if ($allBranches !== []): ?>
<div class="branch-bar">
  <div class="page-shell branch-bar-inner">
    <p class="branch-bar-where">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
      <span>
        Showing <strong><?= e($currentBranch['name'] ?? SITE_NAME) ?></strong><?= $currentBranch !== null ? ', ' . e($currentBranch['city']) : '' ?>
        <?php if (!$branchChosen): ?>
          <em class="branch-bar-hint">&middot; share your location for the closest one</em>
        <?php endif; ?>
        <span class="branch-bar-distance" id="branch-distance" hidden></span>
      </span>
    </p>

    <form class="branch-bar-form" method="get" action="<?= e(url('index.php')) ?>">
      <label class="visually-hidden" for="branch-picker">Choose a salon</label>
      <select id="branch-picker" name="branch">
        <?php foreach ($allBranches as $option): ?>
          <option value="<?= e((string) $option['slug']) ?>" <?= ($currentBranch['slug'] ?? '') === $option['slug'] ? 'selected' : '' ?>>
            <?= e($option['name']) ?> &middot; <?= e($option['city']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-small btn-outline" type="submit">Switch</button>
    </form>

    <button type="button" class="btn btn-small btn-primary branch-bar-locate" id="branch-locate"
            data-api="<?= e(url('api/nearest-salon.php')) ?>"
            data-chosen="<?= $branchChosen ? '1' : '0' ?>">Use my location</button>

    <p class="branch-bar-status" id="branch-locate-status" role="status"></p>
  </div>
</div>
<script src="<?= e(asset('js/locality.js')) ?>" defer></script>
<?php endif; ?>

<main id="main">
<?php $flashes = Auth::takeFlashes(); ?>
<?php if ($flashes !== []): ?>
  <div class="page-shell flash-stack">
    <?php foreach ($flashes as $flash): ?>
      <p class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
