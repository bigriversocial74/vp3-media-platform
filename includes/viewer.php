<?php
declare(strict_types=1);

if(defined('VP3_VIEWER_LOADED')){
    return;
}
define('VP3_VIEWER_LOADED',true);

require_once VP3_ROOT.'/includes/viewer_identity.php';
require_once VP3_ROOT.'/includes/viewer_auth.php';

if(PHP_SAPI!=='cli'){
    vp3_viewer_restore_remembered_login();
}

function vp3_viewer_notification_count(int $viewerId):int
{
    if($viewerId<1||!vp3_db_available()){
        return 0;
    }
    try{
        return (new \VP3\Network\ViewerNotificationService(vp3_db()))->unreadCount($viewerId);
    }catch(Throwable $e){
        vp3_log('warning','Viewer notification count unavailable',['message'=>$e->getMessage()]);
        return 0;
    }
}
