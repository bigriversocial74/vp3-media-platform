<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('network.view');
$pageTitle='Shows';require VP3_ROOT.'/includes/admin-header.php';
$rows=vp3_db()->query("SELECT s.id,s.title,s.slug,s.show_type,s.genre,s.status,s.verification_status,s.featured_rank,c.display_name creator_name,COUNT(DISTINCT cp.id) clip_count FROM shows s LEFT JOIN show_creators sc ON sc.show_id=s.id AND sc.is_primary=1 LEFT JOIN creators c ON c.id=sc.creator_id LEFT JOIN clip_publications cp ON cp.show_id=s.id GROUP BY s.id,c.id ORDER BY s.featured_rank>0 DESC,s.featured_rank ASC,s.id DESC LIMIT 250")->fetchAll();
?>
<div class="admin-page-head"><div><span class="admin-kicker">VP3 Network</span><h1>Shows</h1><p>Series, music projects, microdramas, podcasts, documentaries, and mixed-media experiences.</p></div><?php if(vp3_admin_can($admin,'network.manage')):?><a class="button" href="show.php">Add show</a><?php endif;?></div><div class="table-wrap"><table class="table"><thead><tr><th>Show</th><th>Creator</th><th>Type</th><th>Clips</th><th>Status</th><th>Verification</th><th></th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><b><?=vp3_e($row['title'])?></b><br><small><?=vp3_e($row['slug'])?></small></td><td><?=vp3_e($row['creator_name']??'Unassigned')?></td><td><?=vp3_e($row['show_type'])?></td><td><?=(int)$row['clip_count']?></td><td><span class="badge <?=vp3_e($row['status'])?>"><?=vp3_e($row['status'])?></span></td><td><span class="badge <?=vp3_e($row['verification_status'])?>"><?=vp3_e($row['verification_status'])?></span></td><td><a href="show.php?id=<?=(int)$row['id']?>">Open</a></td></tr><?php endforeach;?></tbody></table></div>
<?php require VP3_ROOT.'/includes/admin-footer.php';?>
