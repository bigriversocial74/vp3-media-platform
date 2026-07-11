<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('network.view');
$pageTitle = 'Creators';
require VP3_ROOT . '/includes/admin-header.php';
$q = vp3_input('q');
$sql = 'SELECT c.id,c.creator_uuid,c.display_name,c.slug,c.verification_status,c.listing_status,c.featured_rank,c.updated_at,cu.email customer_email,COUNT(DISTINCT sc.show_id) show_count FROM creators c JOIN customers cu ON cu.id=c.customer_id LEFT JOIN show_creators sc ON sc.creator_id=c.id';
$params = [];
if ($q !== '') { $sql .= ' WHERE c.display_name LIKE ? OR c.slug LIKE ? OR cu.email LIKE ?'; $params = array_fill(0,3,'%'.$q.'%'); }
$sql .= ' GROUP BY c.id,cu.id ORDER BY c.featured_rank>0 DESC,c.featured_rank ASC,c.id DESC LIMIT 250';
$stmt = vp3_db()->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
?>
<div class="admin-page-head"><div><span class="admin-kicker">VP3 Network</span><h1>Creators</h1><p>Public creator identities connected to licensed customer accounts and shows.</p></div><?php if (vp3_admin_can($admin,'network.manage')): ?><a class="button" href="creator.php">Add creator</a><?php endif; ?></div>
<form method="get" class="admin-filter"><input name="q" value="<?=vp3_e($q)?>" placeholder="Search creators"><button class="button small">Search</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Creator</th><th>Customer</th><th>Shows</th><th>Verification</th><th>Listing</th><th>Featured</th><th></th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><b><?=vp3_e($row['display_name'])?></b><br><small><?=vp3_e($row['slug'])?></small></td><td><?=vp3_e($row['customer_email'])?></td><td><?=(int)$row['show_count']?></td><td><span class="badge <?=vp3_e($row['verification_status'])?>"><?=vp3_e($row['verification_status'])?></span></td><td><span class="badge <?=vp3_e($row['listing_status'])?>"><?=vp3_e($row['listing_status'])?></span></td><td><?=(int)$row['featured_rank']?></td><td><a href="creator.php?id=<?=(int)$row['id']?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php require VP3_ROOT . '/includes/admin-footer.php'; ?>
