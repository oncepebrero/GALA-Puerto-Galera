<?php
$m = page();
$siteName = cfg('site_name');
$title = $m['title'] ? $m['title'] . ' | ' . $siteName . ' · ' . cfg('tagline') : cfg('site_title');
$desc = $m['desc'] ?: 'Explore. Enjoy. Tara Galera! Discover beaches, dive sites, heritage landmarks, waterfalls and visitor information in Puerto Galera, Oriental Mindoro.';
$canon = abs_url($m['path'] ?: '');
$canon = $m['canonical'] ?? $canon;
$og = $m['og'] ?: abs_url(cfg('hero_image_small'));
$nav = [
    ['explore.php', 'Explore', 'explore'],
    ['map.php', 'Map', 'map'],
    ['events.php', 'Events', 'events'],
    ['travel.php', 'Travel guide', 'travel'],
    ['responsible.php', 'Responsible travel', 'responsible'],
];
$current = $m['nav'] ?? '';
?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($m['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta name="theme-color" content="#052233">
<meta property="og:type" content="website"><meta property="og:site_name" content="<?= e(cfg('site_title')) ?>">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>"><meta property="og:image" content="<?= e($og) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/brand/favicon-32.png')) ?>">
<link rel="icon" type="image/png" sizes="64x64" href="<?= e(asset('assets/img/brand/favicon-64.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('assets/img/brand/apple-touch-icon.png')) ?>">
<link rel="preload" href="<?= e(asset('assets/fonts/bricolage.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('assets/fonts/figtree.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
<?php if (!empty($m['hero_preload'])): ?><link rel="preload" as="image" href="<?= e(asset(cfg('hero_image'))) ?>" imagesrcset="<?= e(asset(cfg('hero_image_small'))) ?> 1200w, <?= e(asset(cfg('hero_image'))) ?> 2400w" imagesizes="100vw"><?php endif; ?>
<?php if ($m['schema']): ?><script type="application/ld+json"><?= json_embed($m['schema']) ?></script><?php endif; ?>
<script src="<?= e(asset('assets/js/js-flag.js')) ?>"></script>
</head>
<body class="<?= e($m['class']) ?>" data-base="<?= e(base_url()) ?>">
<a class="skip" href="#main">Skip to content</a>
<div class="wipe" aria-hidden="true"><svg class="wipe__wave" viewBox="0 0 1440 120" preserveAspectRatio="none"><path d="M0 120V60C120 20 240 20 360 52s240 44 360 12 240-52 360-24 240 40 360 8V120Z" fill="currentColor"/></svg><div class="wipe__body"><img src="<?= e(asset('assets/img/brand/gala-emblem.png')) ?>" alt="" width="84" height="84"><p>Tara Galera!</p></div></div>
<header class="site-header" id="site-header">
  <div class="site-header__bar">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="GALÁ-Explore. Enjoy. Tara Galera! Home">
      <span class="brand__mark"><img src="<?= e(asset('assets/img/brand/gala-emblem.png')) ?>" alt="" width="34" height="34"></span>
      <span class="brand__word">GALÁ</span>
      <span class="brand__tag" aria-hidden="true">Explore. Enjoy. Tara Galera!</span>
    </a>
    <nav class="nav" aria-label="Primary">
      <ul>
        <?php foreach ($nav as [$file, $label, $key]): ?>
        <li><a href="<?= e(url($file)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <a class="btn btn--glass btn--sm nav__cta" href="<?= e(url('feedback.php')) ?>">Leave feedback</a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu"><?= icon('menu', 24) ?></button>
  </div>
</header>
<div class="mobile-menu" id="mobile-menu" hidden>
  <div class="mobile-menu__inner" role="dialog" aria-modal="true" aria-label="Site menu">
    <button class="mobile-menu__close" type="button" aria-label="Close menu"><?= icon('close', 28) ?></button>
    <ul>
      <li><a href="<?= e(url('index.php')) ?>">Home</a></li>
      <?php foreach ($nav as [$file, $label, $key]): ?>
      <li><a href="<?= e(url($file)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?= e(url('feedback.php')) ?>">Leave feedback</a></li>
    </ul>
    <p class="mobile-menu__note">Explore. Enjoy. <span class="hand">Tara Galera!</span></p>
  </div>
</div>
<main id="main" tabindex="-1">
