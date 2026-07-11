<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$id = vp3_input('id');
$clip = vp3_network_clip($id);
if (!$clip) { http_response_code(404); $pageTitle='Clip not found'; require VP3_ROOT.'/includes/header.php'; echo '<section class="network-subhero"><h1>Clip not found.</h1></section>'; require VP3_ROOT.'/includes/footer.php'; exit; }
$pageTitle = (string)$clip['title'];
$pageDescription = (string)$clip['caption'];
$bodyClass = 'clips-page network-page';
require VP3_ROOT . '/includes/header.php';
?>
<section class="clip-detail"><div class="clip-detail-media"><?php if (!empty($clip['source_media_url'])): ?><video controls poster="<?= vp3_e(vp3_public_https_url($clip['poster_url'])) ?>"><source src="<?= vp3_e(vp3_public_https_url($clip['source_media_url'])) ?>"></video><?php else: ?><?= vp3_network_placeholder((string)$clip['show_title']) ?><span class="clip-preview-label">Media is served by the creator's platform</span><?php endif; ?></div><div class="clip-detail-copy"><a class="reel-creator" href="creator.php?slug=<?= urlencode((string)$clip['creator_slug']) ?>"><?= vp3_e($clip['creator_name']) ?> <small>✓</small></a><h1><?= vp3_e($clip['title']) ?></h1><p><?= vp3_e($clip['caption']) ?></p><div class="profile-meta"><span><?= (int)$clip['duration_seconds'] ?> seconds</span><span><?= number_format((int)$clip['view_count']) ?> views</span><span><?= number_format((int)$clip['engagement_count']) ?> engagements</span></div><a class="button" href="<?= vp3_e(vp3_public_https_url($clip['destination_url'], '#')) ?>">Enter the full <?= vp3_e($clip['show_title']) ?> experience</a><p class="helper">This clip was created in the creator's licensed platform and syndicated to VP3 Clips. The creator remains the source owner.</p></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
