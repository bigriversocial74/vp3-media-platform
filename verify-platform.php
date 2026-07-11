<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$verificationId = strtoupper(vp3_input('verification_id'));
$result = $verificationId !== '' ? vp3_network_verify($verificationId) : null;
$pageTitle = 'Verify a VP3 Platform';
$pageDescription = 'Confirm a public VP3 platform verification ID.';
$bodyClass = 'network-page';
require VP3_ROOT . '/includes/header.php';
?>
<section class="verify-shell"><div class="verify-intro"><span class="eyebrow">VP3 verification</span><h1>Confirm an official platform.</h1><p>Enter the public verification ID shown on a creator platform or show. Verification never reveals the private license key or internal account data.</p><form method="get" class="verify-form"><label for="verification_id">Public verification ID</label><div><input id="verification_id" name="verification_id" value="<?= vp3_e($verificationId) ?>" placeholder="VP3-VRF-XXXXXXXX" maxlength="40" required><button class="button" type="submit">Verify</button></div></form></div><?php if ($verificationId !== ''): ?><div class="verification-result <?= $result ? 'verified' : 'not-verified' ?>"><?php if ($result): ?><span class="verification-icon">✓</span><small>Verified VP3 platform</small><h2><?= vp3_e($result['display_name']) ?></h2><dl><dt>Verification ID</dt><dd><?= vp3_e($result['verification_id']) ?></dd><dt>Domain</dt><dd><?= vp3_e($result['public_domain']) ?></dd><dt>Product</dt><dd><?= vp3_e($result['product_name']) ?></dd><dt>Edition</dt><dd><?= vp3_e($result['edition']) ?></dd><dt>Hosting</dt><dd><?= vp3_e(str_replace('_', ' ', $result['hosting_type'])) ?></dd><dt>Status</dt><dd><?= vp3_e($result['license_status'] ?? 'active') ?></dd></dl><?php else: ?><span class="verification-icon">!</span><small>Not verified</small><h2>No public record was found.</h2><p>Check the ID and try again, or contact VP3 support if a platform claims authorization without a valid public record.</p><?php endif; ?></div><?php endif; ?></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
