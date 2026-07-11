<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$feed = in_array(vp3_input('feed', 'featured'), ['featured','trending','new'], true) ? vp3_input('feed', 'featured') : 'featured';
$pageTitle = 'VP3 Clips';
$pageDescription = 'Discover clips created inside independent VP3 creator platforms and syndicated into the shared VP3 discovery feed.';
$bodyClass = 'clips-page network-page';
$clips = vp3_network_clips(30, $feed);
require VP3_ROOT . '/includes/header.php';
?>
<section class="clips-header"><div><span class="eyebrow">VP3 Clips</span><h1>Discover the story.<br><span>Enter the world.</span></h1><p>Creators make clips inside their own VP3 platforms. The shared feed helps new audiences find the complete show, release, membership, or creator experience.</p></div><nav class="feed-tabs"><a class="<?= $feed==='featured'?'active':'' ?>" href="?feed=featured">For you</a><a class="<?= $feed==='trending'?'active':'' ?>" href="?feed=trending">Trending</a><a class="<?= $feed==='new'?'active':'' ?>" href="?feed=new">New</a></nav></section>
<section class="reels-feed"><?php foreach ($clips as $clip): ?><article class="reel-card"><a class="reel-media" href="clip.php?id=<?= urlencode((string)$clip['publication_uuid']) ?>"><?php if (!empty($clip['poster_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($clip['poster_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$clip['show_title']) ?><?php endif; ?><i class="reel-play">▶</i><span class="reel-duration"><?= (int)$clip['duration_seconds'] ?>s</span></a><div class="reel-copy"><a class="reel-creator" href="creator.php?slug=<?= urlencode((string)$clip['creator_slug']) ?>"><?= vp3_e($clip['creator_name']) ?> <small>✓</small></a><h2><?= vp3_e($clip['title']) ?></h2><p><?= vp3_e($clip['caption']) ?></p><div class="reel-meta"><span>▶ <?= number_format((int)$clip['view_count']) ?></span><span>♡ <?= number_format((int)$clip['engagement_count']) ?></span><a href="show.php?slug=<?= urlencode((string)$clip['show_slug']) ?>">Open <?= vp3_e($clip['show_title']) ?> →</a></div></div></article><?php endforeach; ?></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
