<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pageTitle = 'Network & Clips';
require VP3_ROOT . '/includes/account-header.php';
$listings = [];
$clips = [];
$networkReady = false;
if (vp3_db_available()) {
    try {
        $listingStmt = vp3_db()->prepare(
            'SELECT ppl.listing_uuid,ppl.display_name,ppl.public_domain,ppl.verification_id,ppl.verification_status,ppl.listing_status,ppl.auto_publish_clips,ppl.updated_at,
                    c.display_name creator_name,s.title show_title
             FROM public_platform_listings ppl
             LEFT JOIN creators c ON c.id=ppl.creator_id
             LEFT JOIN shows s ON s.id=ppl.show_id
             WHERE ppl.customer_id=? ORDER BY ppl.updated_at DESC'
        );
        $listingStmt->execute([(int)$customer['id']]);
        $listings = $listingStmt->fetchAll();
        $clipStmt = vp3_db()->prepare(
            'SELECT cp.publication_uuid,cp.title,cp.publication_status,cp.moderation_status,cp.rights_status,cp.feed_eligible,cp.published_at,cp.last_synced_at,ppl.display_name platform_name
             FROM clip_publications cp
             JOIN public_platform_listings ppl ON ppl.id=cp.public_listing_id
             WHERE ppl.customer_id=? ORDER BY cp.updated_at DESC LIMIT 100'
        );
        $clipStmt->execute([(int)$customer['id']]);
        $clips = $clipStmt->fetchAll();
        $networkReady = true;
    } catch (Throwable $e) {
        vp3_log('warning', 'Customer network tables unavailable', ['message'=>$e->getMessage()]);
    }
}
$published = count(array_filter($clips, static fn(array $clip): bool => $clip['publication_status'] === 'published'));
$pending = count(array_filter($clips, static fn(array $clip): bool => in_array($clip['moderation_status'], ['pending','flagged'], true)));
?>
<div class="page-head"><div><span class="eyebrow">Source-owned publishing</span><h1>Network & clips</h1><p class="helper">Your clips are created and controlled inside your licensed VP3 platform. This page shows the central syndication, verification, moderation, and feed status only.</p></div><a class="button small secondary" href="<?= vp3_e(vp3_url('network.php')) ?>">Open VP3 Network</a></div>
<div class="stats">
  <div class="stat"><span>Public platforms</span><strong><?= count($listings) ?></strong></div>
  <div class="stat"><span>Clip publications</span><strong><?= count($clips) ?></strong></div>
  <div class="stat"><span>Published</span><strong><?= $published ?></strong></div>
  <div class="stat"><span>Awaiting review</span><strong><?= $pending ?></strong></div>
</div>
<section class="section compact-section">
  <div class="grid two">
    <article class="card"><span class="card-number">01</span><h3>Create locally</h3><p>Select media, trim the clip, choose the poster frame, caption it, set its destination, and confirm rights inside your own platform.</p></article>
    <article class="card"><span class="card-number">02</span><h3>Syndicate to VP3 Clips</h3><p>Your platform signs the request with its active license and installation token. VP3 validates, moderates, distributes, and returns feed analytics.</p></article>
  </div>
</section>
<section class="section compact-section"><div class="section-heading"><div><span class="eyebrow">Verified destinations</span><h2>Your public platforms</h2></div></div>
<?php if (!$networkReady): ?><div class="notice">Import <code>database/migrations/20260710_network_clips_v1.sql</code> to enable the VP3 Network account view.</div>
<?php elseif (!$listings): ?><div class="empty-state"><h3>No public platform listing yet</h3><p>A VP3 administrator can connect an active license and verified domain to your creator or show profile.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Platform</th><th>Creator / show</th><th>Verification</th><th>Listing</th><th>Auto publish</th></tr></thead><tbody><?php foreach ($listings as $listing): ?><tr><td><strong><?= vp3_e($listing['display_name']) ?></strong><br><small><?= vp3_e($listing['public_domain']) ?></small></td><td><?= vp3_e($listing['creator_name'] ?: $listing['show_title'] ?: 'Platform') ?></td><td><?= vp3_e($listing['verification_status']) ?><br><small><?= vp3_e($listing['verification_id']) ?></small></td><td><?= vp3_e($listing['listing_status']) ?></td><td><?= (int)$listing['auto_publish_clips'] === 1 ? 'Enabled' : 'Review required' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="section compact-section"><div class="section-heading"><div><span class="eyebrow">Central feed status</span><h2>Recent clip publications</h2></div></div>
<?php if ($networkReady && $clips): ?><div class="table-wrap"><table><thead><tr><th>Clip</th><th>Platform</th><th>Publication</th><th>Moderation</th><th>Rights</th><th>Feed</th><th>Last sync</th></tr></thead><tbody><?php foreach ($clips as $clip): ?><tr><td><strong><?= vp3_e($clip['title']) ?></strong><br><small><?= vp3_e($clip['publication_uuid']) ?></small></td><td><?= vp3_e($clip['platform_name']) ?></td><td><?= vp3_e($clip['publication_status']) ?></td><td><?= vp3_e($clip['moderation_status']) ?></td><td><?= vp3_e($clip['rights_status']) ?></td><td><?= (int)$clip['feed_eligible'] === 1 ? 'Eligible' : 'Held' ?></td><td><?= vp3_e($clip['last_synced_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($networkReady): ?><div class="empty-state"><h3>No syndicated clips yet</h3><p>Use the clip publisher inside your VP3-powered platform. This central account does not duplicate the editing workflow.</p></div><?php endif; ?>
</section>
<?php require VP3_ROOT . '/includes/account-footer.php'; ?>
