<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$pageTitle = 'Verified VP3 Platforms';
$pageDescription = 'Browse public-safe license verification records for shows and creator platforms powered by VP3.';
$bodyClass = 'network-page';
$listings = vp3_network_listings(100);
require VP3_ROOT . '/includes/header.php';
?>
<section class="network-subhero"><span class="eyebrow">Public license directory</span><h1>Official experiences.<br><span>Safely verified.</span></h1><p>Confirm that a show, creator platform, or media destination is running an authorized VP3 product—without exposing private license keys, hashes, activations, IP addresses, or billing records.</p><div class="hero-actions"><a class="button" href="verify-platform.php">Verify an ID</a></div></section>
<section class="network-section light-network"><div class="listing-grid listing-grid-large"><?php foreach ($listings as $listing): ?><article><span class="license-seal">VP3</span><div><small><?= vp3_e(str_replace('_', ' ', $listing['hosting_type'])) ?></small><h3><?= vp3_e($listing['display_name']) ?></h3><p><?= vp3_e($listing['description'] ?? '') ?></p><dl><dt>Product</dt><dd><?= vp3_e($listing['product_name']) ?></dd><dt>Edition</dt><dd><?= vp3_e($listing['edition']) ?></dd><dt>Domain</dt><dd><?= vp3_e($listing['public_domain']) ?></dd><dt>Verification</dt><dd><?= vp3_e($listing['verification_id']) ?></dd></dl></div></article><?php endforeach; ?></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
