<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require_once VP3_ROOT.'/includes/viewer_api.php';

use VP3\Network\ViewerNotificationService;

$input=vp3_viewer_api_bootstrap(['GET','POST']);
$viewer=vp3_viewer_api_require_auth();
$service=new ViewerNotificationService(vp3_db());

if(vp3_method()==='GET'){
    vp3_viewer_api_execute(fn():array=>[
        'items'=>$service->list((int)$viewer['id'],(int)($input['limit']??50),(int)($input['offset']??0)),
        'unread'=>$service->unreadCount((int)$viewer['id']),
        'preferences'=>$service->preferences((int)$viewer['id']),
    ]);
}

$action=(string)($input['action']??'');
vp3_viewer_api_execute(function()use($service,$viewer,$input,$action):array{
    if($action==='mark_read'){
        return['updated'=>$service->markRead((int)$viewer['id'],isset($input['notification_uuid'])?(string)$input['notification_uuid']:null)];
    }
    if($action==='preferences'){
        return['preferences'=>$service->updatePreferences((int)$viewer['id'],$input)];
    }
    throw new RuntimeException('invalid_notification_action');
});
