<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$pageTitle = 'VP3 Creators';
$pageDescription = 'Meet independent creators, artists, producers, and studios building owned audience experiences with VP3.';
$bodyClass = 'network-page';
$creators = vp3_network_creators(48);
require VP3_ROOT . '/includes/header.php';
?>
<section class="network-subhero"><span class="eyebrow">Creator network</span><h1>Independent voices.<br><span>Owned experiences.</span></h1><p>Discover artists, producers, studios, writers, and entertainment brands building direct relationships with their audiences.</p></section>
<section class="network-section creator-network"><div class="creator-grid creator-grid-large"><?php foreach ($creators as $creator): ?><article class="creator-card"><a class="creator-avatar" href="creator.php?slug=<?= urlencode((string)$creator['slug']) ?>"><?php if (!empty($creator['avatar_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($creator['avatar_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$creator['display_name']) ?><?php endif; ?></a><div><span><?= ($creator['verification_status'] ?? '') === 'verified' ? '✓ Verified creator' : 'VP3 creator' ?></span><h3><a href="creator.php?slug=<?= urlencode((string)$creator['slug']) ?>"><?= vp3_e($creator['display_name']) ?></a></h3><p><?= vp3_e($creator['headline']) ?></p><small><?= (int)($creator['show_count'] ?? 0) ?> shows · <?= (int)($creator['clip_count'] ?? 0) ?> clips</small></div></article><?php endforeach; ?></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
