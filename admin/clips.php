<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('clips.moderate');
$moderation = vp3_input('moderation', 'all');
$publication = vp3_input('publication', 'all');
$params = [];
$where = [];
if (in_array($moderation, ['pending','approved','rejected','flagged'], true)) { $where[]='cp.moderation_status=?'; $params[]=$moderation; }
if (in_array($publication, ['pending','scheduled','published','withdrawn','suspended'], true)) { $where[]='cp.publication_status=?'; $params[]=$publication; }
$sql = "SELECT cp.id,cp.publication_uuid,cp.title,cp.source_clip_uuid,cp.publication_status,cp.moderation_status,cp.rights_status,cp.feed_eligible,cp.featured_rank,cp.published_at,cp.updated_at,
               c.display_name creator_name,s.title show_title,ppl.display_name platform_name,
               (SELECT COUNT(*) FROM clip_reports cr WHERE cr.clip_publication_id=cp.id AND cr.report_status='open') open_reports,
               (SELECT COUNT(*) FROM clip_view_events cv WHERE cv.clip_publication_id=cp.id) views
        FROM clip_publications cp
        JOIN public_platform_listings ppl ON ppl.id=cp.public_listing_id
        LEFT JOIN creators c ON c.id=cp.creator_id
        LEFT JOIN shows s ON s.id=cp.show_id" . ($where ? ' WHERE '.implode(' AND ',$where) : '') . " ORDER BY cp.updated_at DESC LIMIT 300";
$stmt=vp3_db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$stats=vp3_db()->query("SELECT COUNT(*) total,SUM(moderation_status='pending') pending,SUM(publication_status='published') published,SUM(rights_status='disputed') disputed FROM clip_publications")->fetch() ?: [];
$pageTitle='Clip Moderation';require VP3_ROOT.'/includes/admin-header.php';
?>
<div class="admin-page-head"><div><span class="admin-kicker">VP3 Clips</span><h1>Clip moderation</h1><p>Review syndicated clips. Source media remains owned and hosted by each creator platform.</p></div></div>
<div class="stats"><div class="stat"><span>Total clips</span><strong><?=(int)($stats['total']??0)?></strong></div><div class="stat"><span>Pending review</span><strong><?=(int)($stats['pending']??0)?></strong></div><div class="stat"><span>Published</span><strong><?=(int)($stats['published']??0)?></strong></div><div class="stat"><span>Rights disputes</span><strong><?=(int)($stats['disputed']??0)?></strong></div></div>
<form method="get" class="admin-filter"><label>Moderation<select name="moderation"><option value="all">All</option><?php foreach(['pending','approved','rejected','flagged'] as $v):?><option <?= $moderation===$v?'selected':'' ?>><?=$v?></option><?php endforeach;?></select></label><label>Publication<select name="publication"><option value="all">All</option><?php foreach(['pending','scheduled','published','withdrawn','suspended'] as $v):?><option <?= $publication===$v?'selected':'' ?>><?=$v?></option><?php endforeach;?></select></label><button class="button small">Filter</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>Clip</th><th>Platform</th><th>Creator / show</th><th>Moderation</th><th>Rights</th><th>Publication</th><th>Views</th><th>Reports</th><th></th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><b><?=vp3_e($row['title'])?></b><br><small><?=vp3_e($row['source_clip_uuid'])?></small></td><td><?=vp3_e($row['platform_name'])?></td><td><?=vp3_e($row['creator_name']??'Unassigned')?><?php if($row['show_title']):?><br><small><?=vp3_e($row['show_title'])?></small><?php endif;?></td><td><span class="badge <?=vp3_e($row['moderation_status'])?>"><?=vp3_e($row['moderation_status'])?></span></td><td><span class="badge <?=vp3_e($row['rights_status'])?>"><?=vp3_e($row['rights_status'])?></span></td><td><span class="badge <?=vp3_e($row['publication_status'])?>"><?=vp3_e($row['publication_status'])?></span></td><td><?=number_format((int)$row['views'])?></td><td><?= (int)$row['open_reports'] > 0 ? '<span class="badge failed">'.(int)$row['open_reports'].' open</span>' : '0' ?></td><td><a href="clip.php?id=<?=(int)$row['id']?>">Review</a></td></tr><?php endforeach;?></tbody></table></div>
<?php require VP3_ROOT.'/includes/admin-footer.php';?>
