<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('dashboard.view');
$pageTitle = 'Admin Dashboard';
require VP3_ROOT . '/includes/admin-header.php';
$tables = [
    'customers'=>'Customers','orders'=>'Orders','licenses'=>'Licenses','hosting_accounts'=>'Hosting',
    'installation_jobs'=>'Install jobs','support_tickets'=>'Tickets','creators'=>'Creators','shows'=>'Shows',
    'clip_publications'=>'Clips','public_platform_listings'=>'Public platforms',
];
$counts = [];
if (vp3_db_available()) {
    foreach ($tables as $table=>$label) {
        try { $counts[$table] = (int)vp3_db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(); }
        catch (Throwable) { $counts[$table] = 0; }
    }
}
?>
<div class="page-head"><div><span class="eyebrow">VP3 operating system</span><h1>Operations dashboard</h1><p class="helper">Licensing, hosting, customer operations, creators, shows, verified platforms, and syndicated clip distribution.</p></div></div>
<div class="stats"><?php foreach($tables as $table=>$label): ?><div class="stat"><span><?= vp3_e($label) ?></span><strong><?= vp3_e((string)($counts[$table] ?? 0)) ?></strong></div><?php endforeach; ?></div>
<section class="section" style="padding-left:0;padding-right:0"><div class="grid three">
  <article class="card"><h3>VP3 Network</h3><p>Manage public creator profiles, shows, verified destinations, feature placement, and network visibility.</p><a class="button small" href="creators.php">Manage network</a></article>
  <article class="card"><h3>Clip moderation</h3><p>Review source-owned clip publications, rights declarations, reports, feed eligibility, and sync status.</p><a class="button small" href="clips.php">Review clips</a></article>
  <article class="card"><h3>Licensing</h3><p>Create secure licenses, assign domains, control activations, expiration, editions, suspension, revocation, and updates.</p><a class="button small" href="licenses.php">Manage licenses</a></article>
  <article class="card"><h3>Hosted installs</h3><p>Track subdomains, environments, providers, installation progress, failures, versions, and health status.</p><a class="button small" href="installations.php">View jobs</a></article>
  <article class="card"><h3>Product releases</h3><p>Publish approved artifacts and metadata without exposing internal storage paths to customers or APIs.</p><a class="button small" href="releases.php">Manage releases</a></article>
  <article class="card"><h3>Appearance</h3><p>The administration console supports persistent light, dark, and system themes from the sidebar selector.</p><a class="button small secondary" href="settings.php">System settings</a></article>
</div></section>
<?php require VP3_ROOT . '/includes/admin-footer.php'; ?>
