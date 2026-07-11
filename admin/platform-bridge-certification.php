<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use VP3\Network\BridgeCertificationService;
$admin=vp3_require_admin_permission('network.manage');
$listingId=(int)vp3_input('id');
$stmt=vp3_db()->prepare('SELECT ppl.*,c.name customer_name,p.name product_name,l.edition FROM public_platform_listings ppl JOIN customers c ON c.id=ppl.customer_id JOIN products p ON p.id=ppl.product_id JOIN licenses l ON l.id=ppl.license_id WHERE ppl.id=?');
$stmt->execute([$listingId]);
$listing=$stmt->fetch();
if(!$listing){http_response_code(404);exit('Platform not found.');}
$service=new BridgeCertificationService(vp3_db());
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $action=vp3_input('action');
    try{
        if($action==='approve'){
            $service->approve((int)vp3_input('certification_id'),$listingId,(int)$admin['id']);
            vp3_flash('success','Live VP3 Clips publishing approved for this certified credential.');
        }elseif($action==='revoke'){
            $service->revoke((int)vp3_input('certification_id'),$listingId,(int)$admin['id']);
            vp3_flash('success','Live publishing certification revoked.');
        }
    }catch(Throwable $e){vp3_flash('error',$e->getMessage());}
    vp3_redirect('admin/platform-bridge-certification.php?id='.$listingId);
}
$rows=$service->history($listingId);
$pageTitle='VP3 Clips Certification';
require VP3_ROOT.'/includes/admin-header.php';
?>
<div class="admin-page-head"><div><span class="admin-kicker">Live integration certification</span><h1><?=vp3_e($listing['display_name'])?></h1><p>Review signed Stonefellow environment reports and explicitly approve live feed publishing for one exact bridge credential.</p></div><a class="button secondary" href="platform-bridge.php?id=<?=$listingId?>">Back to bridge</a></div>
<section class="card"><h2>Certification policy</h2><p>A passing report proves the source database, license receipt, encrypted bridge settings, TLS client, FFmpeg/FFprobe, private clip storage, public HTTPS base URL, signed context request, and synthetic render probe. Approval lasts 180 days and does not transfer to a rotated credential.</p></section>
<section class="card"><h2>Certification history</h2><div class="table-wrap"><table class="table"><thead><tr><th>Certification</th><th>Credential / installation</th><th>Status</th><th>Submitted</th><th>Approval</th><th>Failures</th><th></th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><code><?=vp3_e($row['certification_uuid'])?></code><br><small>v<?=vp3_e($row['certification_version'])?></small></td><td><code><?=vp3_e($row['bridge_uuid'])?></code><br><small><?=vp3_e($row['domain'].' · '.$row['installation_uuid'])?></small></td><td><span class="badge <?=vp3_e($row['status'])?>"><?=vp3_e($row['status'])?></span><?php if($row['expires_at']):?><br><small>through <?=vp3_e($row['expires_at'])?></small><?php endif;?></td><td><?=vp3_e($row['submitted_at'])?></td><td><?=vp3_e((string)($row['approved_by_name']?:'—'))?><br><small><?=vp3_e((string)($row['approved_at']?:''))?></small></td><td><?=vp3_e((string)($row['failure_summary']?:'—'))?></td><td><?php if($row['status']==='passed'):?><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="action" value="approve"><input type="hidden" name="certification_id" value="<?=(int)$row['id']?>"><button class="button small" type="submit">Approve live</button></form><?php elseif($row['status']==='approved'):?><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="action" value="revoke"><input type="hidden" name="certification_id" value="<?=(int)$row['id']?>"><button class="button small secondary" type="submit">Revoke</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7">No Stonefellow certification report has been submitted for this platform.</td></tr><?php endif;?></tbody></table></div></section>
<?php require VP3_ROOT.'/includes/admin-footer.php';?>
