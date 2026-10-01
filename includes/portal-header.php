<?php
/**
 * Chrome for the signed-in areas (customer account and barber portal).
 *
 * Expects:
 *   $pageTitle  string
 *   $portal     array{kind:string, name:string, subtitle:string, root:string, nav:array<string,string>}
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$portalKind = $portal['kind'] ?? 'customer';
$portalRoot = $portal['root'] ?? 'account';
$portalNav = $portal['nav'] ?? [];
$portalName = $portal['name'] ?? '';
$portalSubtitle = $portal['subtitle'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="portal-body">

<header class="portal-bar">
  <a class="brand" href="<?= e(url('index.php')) ?>">
    <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset('img/brand-mark.png')) ?>" alt=""></span>
    <span class="brand-name">Morrow <span class="brand-amp">&amp;</span> Blade</span>
  </a>
  <div class="portal-bar-right">
    <span class="portal-chip portal-chip-<?= e($portalKind) ?>"><?= e($portalKind === 'staff' ? 'Team' : 'Member') ?></span>
    <span class="portal-who"><?= e($portalName) ?></span>
    <a class="portal-exit" href="<?= e(url($portalRoot . '/logout.php')) ?>">Sign out</a>
  </div>
</header>

<div class="portal-shell">
  <aside class="portal-side">
    <p class="portal-side-title"><?= e($portalSubtitle) ?></p>
    <nav class="portal-nav" aria-label="Dashboard">
      <?php foreach ($portalNav as $file => $label): ?>
        <a href="<?= e(url($portalRoot . '/' . $file)) ?>" <?= is_current($file) ? 'aria-current="page"' : '' ?>>
          <?= e($label) ?>
          <?php if (!empty($portal['badges'][$file])): ?>
            <span class="portal-badge"><?= (int) $portal['badges'][$file] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <p class="portal-side-foot">
      <a href="<?= e(url('index.php')) ?>">&larr; Back to the website</a>
    </p>
  </aside>

  <main class="portal-main" id="main">
    <?php foreach (Auth::takeFlashes() as $flash): ?>
      <p class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
    <?php endforeach; ?>
