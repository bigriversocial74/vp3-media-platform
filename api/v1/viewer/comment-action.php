<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require_once VP3_ROOT.'/includes/viewer_api.php';

use VP3\Network\ViewerCommunityService;

$input=vp3_viewer_api_bootstrap(['POST']);
$viewer=vp3_viewer_api_require_auth();
$service=new ViewerCommunityService(vp3_db());
$action=(string)($input['action']??'');

vp3_viewer_api_execute(function()use($service,$viewer,$input,$action):array{
    return match($action){
        'like'=>$service->toggleLike((string)($input['comment_uuid']??''),(int)$viewer['id']),
        'delete'=>$service->removeOwn((string)($input['comment_uuid']??''),(int)$viewer['id']),
        'report'=>$service->report(
            (string)($input['comment_uuid']??''),
            (int)$viewer['id'],
            (string)($input['reason']??'other'),
            (string)($input['details']??'')
        ),
        'block'=>$service->toggleBlock((int)$viewer['id'],(string)($input['viewer_uuid']??'')),
        'mute'=>$service->toggleMute(
            (int)$viewer['id'],
            (string)($input['target_type']??''),
            (string)($input['target_uuid']??'')
        ),
        default=>throw new RuntimeException('invalid_community_action'),
    };
});
