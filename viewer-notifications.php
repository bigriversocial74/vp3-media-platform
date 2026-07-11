<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use VP3\Network\ViewerNotificationService;

$pageTitle='Notifications';
$pageDescription='Replies, activity, and new releases from creators and shows you follow.';
require VP3_ROOT.'/includes/viewer-header.php';

$service=new ViewerNotificationService(vp3_db());
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $action=vp3_input('action');
    if($action==='mark_all'){
        $service->markRead((int)$viewer['id']);
        vp3_flash('success','Notifications marked as read.');
    }elseif($action==='preferences'){
        $service->updatePreferences((int)$viewer['id'],$_POST);
        vp3_flash('success','Notification preferences updated.');
    }
    vp3_redirect('viewer-notifications.php');
}
$items=$service->list((int)$viewer['id'],100);
$prefs=$service->preferences((int)$viewer['id']);
?>
<section class="viewer-panel viewer-notifications">
  <div class="viewer-panel-head"><div><h2>Inbox</h2><p>Community activity and new clips from followed worlds.</p></div><form method="post"><?=vp3_csrf_field()?><input type="hidden" name="action" value="mark_all"><button class="button small secondary">Mark all read</button></form></div>
  <div class="notification-list">
    <?php foreach($items as$item):?>
      <a class="notification-item <?=$item['read_at']?'':'unread'?>" href="<?=vp3_e(vp3_url((string)$item['destination_path']))?>">
        <span class="notification-dot"></span><div><strong><?=vp3_e($item['title'])?></strong><p><?=vp3_e((string)$item['body'])?></p><small><?=vp3_e($item['created_at'])?></small></div>
      </a>
    <?php endforeach;?>
    <?php if(!$items):?><p class="empty-state">No notifications yet.</p><?php endif;?>
  </div>
</section>
<section class="viewer-panel">
  <h2>Notification preferences</h2>
  <form method="post" class="viewer-settings-form"><?=vp3_csrf_field()?><input type="hidden" name="action" value="preferences">
    <label><input type="checkbox" name="in_app_replies" value="1" <?=!empty($prefs['in_app_replies'])?'checked':''?>> Replies to my comments</label>
    <label><input type="checkbox" name="in_app_comment_likes" value="1" <?=!empty($prefs['in_app_comment_likes'])?'checked':''?>> Likes on my comments</label>
    <label><input type="checkbox" name="in_app_new_clips" value="1" <?=!empty($prefs['in_app_new_clips'])?'checked':''?>> New clips from followed creators and shows</label>
    <label>Email digest <select name="email_digest"><?php foreach(['off'=>'Off','daily'=>'Daily','weekly'=>'Weekly']as$value=>$label):?><option value="<?=$value?>" <?=$prefs['email_digest']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
    <button class="button">Save preferences</button>
  </form>
</section>
<?php require VP3_ROOT.'/includes/viewer-footer.php';?>
