<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Launch and own your media platform';
$pageDescription = $pageDescription ?? 'VP3 Media Group helps storytellers, creators, artists, and entertainment brands build and launch owned media experiences.';
$bodyClass = $bodyClass ?? '';
$customer = vp3_customer();
$currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$navItems = [
    'index.php' => 'Home',
    'products.php' => 'Products',
    'hosting.php' => 'Hosting',
    'pricing.php' => 'Pricing',
    'about.php' => 'About',
    'contact.php' => 'Contact',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= vp3_e($pageTitle) ?> | VP3 Media Group</title>
<meta name="description" content="<?= vp3_e($pageDescription) ?>">
<link rel="stylesheet" href="<?= vp3_e(vp3_url('assets/css/app.css')) ?>">
<script defer src="<?= vp3_e(vp3_url('assets/js/app.js')) ?>"></script>
</head>
<body class="<?= vp3_e($bodyClass) ?>">
<header class="site-header">
  <a class="brand" href="<?= vp3_e(vp3_url('index.php')) ?>" aria-label="VP3 Media Group home">
    <span class="brand-symbol" aria-hidden="true"><i></i><i></i><i></i></span>
    <span class="brand-copy"><b>VP3</b><small>Media Group</small></span>
  </a>
  <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false"><span></span><span></span><span></span></button>
  <nav class="main-nav" aria-label="Primary">
    <?php foreach ($navItems as $href => $label): ?>
      <a class="<?= $currentPage === $href ? 'active' : '' ?>" href="<?= vp3_e(vp3_url($href)) ?>"><?= vp3_e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($customer): ?>
      <a class="nav-login" href="<?= vp3_e(vp3_url('account.php')) ?>">My account</a>
    <?php else: ?>
      <a class="nav-login <?= $currentPage === 'login.php' ? 'active' : '' ?>" href="<?= vp3_e(vp3_url('login.php')) ?>">Sign in</a>
      <a class="button small" href="<?= vp3_e(vp3_url('signup.php')) ?>">Start your platform</a>
    <?php endif; ?>
  </nav>
</header>
<main>
<?php if ($msg = vp3_flash('success')): ?><div class="flash success global-flash"><?= vp3_e($msg) ?></div><?php endif; ?>
<?php if ($msg = vp3_flash('error')): ?><div class="flash error global-flash"><?= vp3_e($msg) ?></div><?php endif; ?>
