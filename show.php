<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$slug = preg_replace('/[^a-z0-9-]/', '', strtolower(vp3_input('slug', 'stonefellow'))) ?: 'stonefellow';
$show = vp3_network_show($slug);
if (!$show) { http_response_code(404); $pageTitle='Show not found'; require VP3_ROOT.'/includes/header.php'; echo '<section class="network-subhero"><h1>Show not found.</h1><p>The requested show is not currently listed.</p></section>'; require VP3_ROOT.'/includes/footer.php'; exit; }
$pageTitle = (string)$show['title'];
$pageDescription = (string)$show['short_description'];
$bodyClass = 'network-page';
require VP3_ROOT . '/includes/header.php';
?>
<section class="show-profile-hero"><div class="show-profile-art"><?php if (!empty($show['hero_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($show['hero_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$show['title']) ?><?php endif; ?></div><div class="show-profile-copy"><span class="eyebrow"><?= vp3_e($show['genre'] ?? $show['show_type']) ?></span><h1><?= vp3_e($show['title']) ?></h1><p><?= vp3_e($show['description']) ?></p><div class="profile-meta"><span>By <a href="creator.php?slug=<?= urlencode((string)($show['creator_slug'] ?? '')) ?>"><?= vp3_e($show['creator_name'] ?? 'Independent creator') ?></a></span><?php if (($show['verification_status'] ?? '') === 'verified'): ?><span>✓ VP3 verified</span><?php endif; ?></div><div class="hero-actions"><a class="button" href="<?= vp3_e(vp3_public_https_url($show['destination_url'], '#')) ?>">Enter official platform</a><a class="button ghost" href="#show-clips">Watch clips</a></div></div></section>
<section class="network-section light-network" id="show-clips"><div class="network-section-head"><div><span class="section-kicker">Latest clips</span><h2>Step into the story.</h2></div></div><div class="clip-rail"><?php foreach ($show['clips'] as $clip): ?><a class="clip-tile light" href="clip.php?id=<?= urlencode((string)$clip['publication_uuid']) ?>"><span class="clip-poster"><?= vp3_network_placeholder((string)$clip['title']) ?><i class="play-button">▶</i><small><?= (int)$clip['duration_seconds'] ?>s</small></span><b><?= vp3_e($clip['title']) ?></b><span><?= number_format((int)$clip['view_count']) ?> views</span></a><?php endforeach; ?></div></section>
<section class="final-story-cta"><span class="section-kicker">Powered by VP3</span><h2>Build a complete world around your story.</h2><p>Launch your own audience destination, create clips inside your platform, and join the VP3 discovery network.</p><div class="hero-actions"><a class="button" href="signup.php">Start your platform</a><a class="button ghost" href="contact.php">Talk with VP3</a></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
