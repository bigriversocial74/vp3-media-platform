<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$pageTitle = 'Shows powered by VP3';
$pageDescription = 'Browse current series, music projects, microdramas, podcasts, and audience experiences powered by VP3.';
$bodyClass = 'network-page';
$shows = vp3_network_shows(48);
require VP3_ROOT . '/includes/header.php';
?>
<section class="network-subhero"><span class="eyebrow">Current shows</span><h1>Stories with their own <span>place to live.</span></h1><p>Browse scripted series, microdramas, music projects, podcasts, documentary work, live experiences, and creator-owned media worlds.</p></section>
<section class="network-section light-network"><div class="filter-row"><span><?= count($shows) ?> current experiences</span><div><a class="active" href="#">All</a><a href="#">Series</a><a href="#">Music</a><a href="#">Microdrama</a><a href="#">Podcast</a></div></div><div class="show-grid show-grid-large"><?php foreach ($shows as $show): ?><article class="show-card"><a class="show-art" href="show.php?slug=<?= urlencode((string)$show['slug']) ?>"><?php if (!empty($show['cover_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($show['cover_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$show['title']) ?><?php endif; ?><?php if (($show['verification_status'] ?? '') === 'verified'): ?><span class="verified-mark">✓ VP3 Verified</span><?php endif; ?></a><div class="show-card-copy"><span><?= vp3_e($show['genre'] ?? $show['show_type']) ?></span><h3><a href="show.php?slug=<?= urlencode((string)$show['slug']) ?>"><?= vp3_e($show['title']) ?></a></h3><p><?= vp3_e($show['short_description']) ?></p><small>By <?= vp3_e($show['creator_name'] ?? 'Independent creator') ?> · <?= (int)($show['clip_count'] ?? 0) ?> clips</small></div></article><?php endforeach; ?></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
