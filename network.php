<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';
$pageTitle = 'VP3 Network';
$pageDescription = 'Discover shows, creators, clips, and verified media experiences powered by VP3.';
$bodyClass = 'network-page';
$shows = vp3_network_shows(4);
$creators = vp3_network_creators(4);
$clips = vp3_network_clips(6, 'trending');
$listings = vp3_network_listings(4);
require VP3_ROOT . '/includes/header.php';
?>
<section class="network-hero">
  <div>
    <span class="eyebrow">Stories powered by VP3</span>
    <h1>Discover the story.<br><span>Enter the world.</span></h1>
    <p>The VP3 Network connects original shows, creators, music, series, and independently owned audience experiences. Clips introduce the moment. Creator platforms hold the complete world.</p>
    <div class="hero-actions">
      <a class="button" href="<?= vp3_e(vp3_url('clips.php')) ?>">Watch VP3 Clips</a>
      <a class="button ghost" href="<?= vp3_e(vp3_url('shows.php')) ?>">Browse shows</a>
    </div>
  </div>
  <div class="network-orbit" aria-hidden="true">
    <span class="orbit-core">VP3<small>Network</small></span>
    <i class="orbit orbit-one"></i><i class="orbit orbit-two"></i><i class="orbit orbit-three"></i>
    <b class="orbit-node node-one">Shows</b><b class="orbit-node node-two">Creators</b><b class="orbit-node node-three">Clips</b>
  </div>
</section>

<section class="network-section light-network">
  <div class="network-section-head"><div><span class="section-kicker">Current shows</span><h2>Original worlds with somewhere deeper to go.</h2></div><a class="text-link" href="shows.php">View all shows →</a></div>
  <div class="show-grid">
    <?php foreach ($shows as $show): ?>
      <article class="show-card">
        <a class="show-art" href="show.php?slug=<?= urlencode((string)$show['slug']) ?>">
          <?php if (!empty($show['cover_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($show['cover_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$show['title']) ?><?php endif; ?>
          <?php if (($show['verification_status'] ?? '') === 'verified'): ?><span class="verified-mark">✓ VP3 Verified</span><?php endif; ?>
        </a>
        <div class="show-card-copy"><span><?= vp3_e($show['genre'] ?? $show['show_type']) ?></span><h3><a href="show.php?slug=<?= urlencode((string)$show['slug']) ?>"><?= vp3_e($show['title']) ?></a></h3><p><?= vp3_e($show['short_description']) ?></p><small>By <?= vp3_e($show['creator_name'] ?? 'Independent creator') ?> · <?= (int)($show['clip_count'] ?? 0) ?> clips</small></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="network-section dark-network">
  <div class="network-section-head"><div><span class="section-kicker">VP3 Clips</span><h2>Short moments that lead to complete experiences.</h2><p>Every clip is created and owned inside the creator's VP3 platform, then syndicated into the shared discovery feed.</p></div><a class="button small" href="clips.php">Open clips feed</a></div>
  <div class="clip-rail">
    <?php foreach ($clips as $clip): ?>
      <a class="clip-tile" href="clip.php?id=<?= urlencode((string)$clip['publication_uuid']) ?>">
        <span class="clip-poster"><?php if (!empty($clip['poster_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($clip['poster_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$clip['show_title']) ?><?php endif; ?><i class="play-button">▶</i><small><?= (int)$clip['duration_seconds'] ?>s</small></span>
        <b><?= vp3_e($clip['title']) ?></b><span><?= vp3_e($clip['show_title']) ?> · <?= number_format((int)$clip['view_count']) ?> views</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="network-section creator-network">
  <div class="network-section-head"><div><span class="section-kicker">Featured creators</span><h2>Meet the people building their own media worlds.</h2></div><a class="text-link" href="creators.php">Browse creators →</a></div>
  <div class="creator-grid">
    <?php foreach ($creators as $creator): ?>
      <article class="creator-card"><a class="creator-avatar" href="creator.php?slug=<?= urlencode((string)$creator['slug']) ?>"><?php if (!empty($creator['avatar_url'])): ?><img src="<?= vp3_e(vp3_public_https_url($creator['avatar_url'])) ?>" alt=""><?php else: ?><?= vp3_network_placeholder((string)$creator['display_name']) ?><?php endif; ?></a><div><span><?= ($creator['verification_status'] ?? '') === 'verified' ? '✓ Verified creator' : 'VP3 creator' ?></span><h3><a href="creator.php?slug=<?= urlencode((string)$creator['slug']) ?>"><?= vp3_e($creator['display_name']) ?></a></h3><p><?= vp3_e($creator['headline']) ?></p><small><?= (int)($creator['show_count'] ?? 0) ?> shows · <?= (int)($creator['clip_count'] ?? 0) ?> clips</small></div></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="network-section verified-network">
  <div class="network-section-head"><div><span class="section-kicker">Verified platforms</span><h2>Know when an experience is officially powered by VP3.</h2><p>Public verification confirms the platform, product, domain, and license status without exposing private license credentials.</p></div><a class="button secondary" href="verify-platform.php">Verify a platform</a></div>
  <div class="listing-grid">
    <?php foreach ($listings as $listing): ?><article><span class="license-seal">VP3</span><div><small><?= vp3_e(str_replace('_', ' ', $listing['hosting_type'])) ?></small><h3><?= vp3_e($listing['display_name']) ?></h3><p><?= vp3_e($listing['public_domain']) ?></p><b><?= vp3_e($listing['verification_id']) ?></b></div></article><?php endforeach; ?>
  </div>
</section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
