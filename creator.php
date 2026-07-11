<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower(vp3_input('slug', 'roger-huston'))) ?: 'roger-huston';
$creator = vp3_network_creator($slug);
if (!$creator) { http_response_code(404); $pageTitle='Creator not found'; require VP3_ROOT.'/includes/header.php'; echo '<section class="network-subhero"><h1>Creator not found.</h1></section>'; require VP3_ROOT.'/includes/footer.php'; exit; }
$pageTitle = (string)$creator['display_name'];
$pageDescription = (string)$creator['headline'];
$bodyClass = 'network-page';
require VP3_ROOT . '/includes/header.php';
?>
<section class="creator-profile-hero"><div class="creator-profile-avatar"><?php if (!empty($creator['avatar_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($creator['avatar_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$creator['display_name']) ?><?php endif; ?></div><div><span class="eyebrow"><?= ($creator['verification_status'] ?? '') === 'verified' ? '✓ Verified VP3 creator' : 'VP3 creator' ?></span><h1><?= vp3_e($creator['display_name']) ?></h1><h2><?= vp3_e($creator['headline']) ?></h2><p><?= vp3_e($creator['bio']) ?></p><div class="profile-meta"><span><?= count($creator['shows']) ?> shows</span><span><?= count($creator['clips']) ?> published clips</span></div></div></section>
<section class="network-section light-network"><div class="network-section-head"><div><span class="section-kicker">Creator projects</span><h2>Shows and worlds.</h2></div></div><div class="show-grid"><?php foreach ($creator['shows'] as $show): ?><article class="show-card"><a class="show-art" href="show.php?slug=<?= urlencode((string)$show['slug']) ?>"><?= vp3_network_placeholder((string)$show['title']) ?><span class="verified-mark">✓ VP3 Verified</span></a><div class="show-card-copy"><span><?= vp3_e($show['genre']) ?></span><h3><?= vp3_e($show['title']) ?></h3><p><?= vp3_e($show['short_description']) ?></p></div></article><?php endforeach; ?></div></section>
<section class="network-section dark-network"><div class="network-section-head"><div><span class="section-kicker">Latest clips</span><h2>Recent moments from <?= vp3_e($creator['display_name']) ?>.</h2></div></div><div class="clip-rail"><?php foreach ($creator['clips'] as $clip): ?><a class="clip-tile" href="clip.php?id=<?= urlencode((string)$clip['publication_uuid']) ?>"><span class="clip-poster"><?= vp3_network_placeholder((string)$clip['title']) ?><i class="play-button">▶</i></span><b><?= vp3_e($clip['title']) ?></b><span><?= number_format((int)$clip['view_count']) ?> views</span></a><?php endforeach; ?></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
