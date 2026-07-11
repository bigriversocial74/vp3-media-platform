<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use VP3\Network\ViewerCommunityService;

$pageTitle='Safety & controls';
$pageDescription='Manage blocked viewers and muted creators or shows.';
require VP3_ROOT.'/includes/viewer-header.php';
$service=new ViewerCommunityService(vp3_db());

if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $action=vp3_input('action');
    if($action==='unblock'){
        $service->toggleBlock((int)$viewer['id'],vp3_input('viewer_uuid'));
    }elseif($action==='unmute'){
        $service->toggleMute((int)$viewer['id'],vp3_input('target_type'),vp3_input('target_uuid'));
    }
    vp3_redirect('viewer-safety.php');
}
$data=$service->safety((int)$viewer['id']);
?>
<section class="viewer-panel"><h2>Blocked viewers</h2><div class="safety-list">
<?php foreach($data['blocks']as$row):?><article><div><strong><?=vp3_e($row['display_name'])?></strong><small>@<?=vp3_e($row['handle'])?></small></div><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="action" value="unblock"><input type="hidden" name="viewer_uuid" value="<?=vp3_e($row['viewer_uuid'])?>"><button class="button small secondary">Unblock</button></form></article><?php endforeach;?>
<?php if(!$data['blocks']):?><p class="empty-state">You have not blocked any viewers.</p><?php endif;?>
</div></section>
<section class="viewer-panel"><h2>Muted creators and shows</h2><div class="safety-list">
<?php foreach($data['mutes']as$row):?><article><div><strong><?=vp3_e((string)$row['target_name'])?></strong><small><?=vp3_e($row['target_type'])?></small></div><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="action" value="unmute"><input type="hidden" name="target_type" value="<?=vp3_e($row['target_type'])?>"><input type="hidden" name="target_uuid" value="<?=vp3_e((string)$row['target_uuid'])?>"><button class="button small secondary">Unmute</button></form></article><?php endforeach;?>
<?php if(!$data['mutes']):?><p class="empty-state">No creators or shows are muted.</p><?php endif;?>
</div></section>
<?php require VP3_ROOT.'/includes/viewer-footer.php';?>
