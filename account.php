<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pageTitle = 'Account Overview';
require VP3_ROOT . '/includes/account-header.php';
$counts = ['orders'=>0,'licenses'=>0,'hosting'=>0,'tickets'=>0];
if (vp3_db_available()) {
    foreach (['orders','licenses','hosting_accounts','support_tickets'] as $table) {
        $stmt = vp3_db()->prepare("SELECT COUNT(*) FROM {$table} WHERE customer_id=?");
        $stmt->execute([$customer['id']]);
        $key = $table === 'hosting_accounts' ? 'hosting' : ($table === 'support_tickets' ? 'tickets' : $table);
        $counts[$key] = (int)$stmt->fetchColumn();
    }
}
?>
<h1>Welcome, <?= vp3_e($customer['name']) ?>.</h1>
<p class="helper">Manage your orders, licenses, hosted platforms, public network presence, product downloads, and support requests.</p>
<div class="stats">
  <div class="stat"><span>Orders</span><strong><?= $counts['orders'] ?></strong></div>
  <div class="stat"><span>Licenses</span><strong><?= $counts['licenses'] ?></strong></div>
  <div class="stat"><span>Hosted platforms</span><strong><?= $counts['hosting'] ?></strong></div>
  <div class="stat"><span>Support tickets</span><strong><?= $counts['tickets'] ?></strong></div>
</div>
<section class="section" style="padding-left:0;padding-right:0">
  <div class="grid two">
    <article class="card"><h3>Launch your first platform</h3><p>Choose a product and ownership model, then follow installation progress from your account.</p><a class="button small" href="pricing.php">View plans</a></article>
    <article class="card"><h3>Publish into VP3 Network</h3><p>Create clips inside your licensed platform, then syndicate approved clips into the VP3 Clips discovery feed.</p><a class="button small secondary" href="account-network.php">Network & clips</a></article>
  </div>
</section>
<?php require VP3_ROOT . '/includes/account-footer.php'; ?>
