<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$checks=[
 'database/schema-viewer-community.sql'=>['viewer_comments','viewer_notifications','viewer_blocks','viewer_mutes','viewer_comment_reports','viewer_notification_dispatches'],
 'includes/viewer_api.php'=>['vp3_viewer_api_require_auth','viewer_auth_required'],
 'src/Network/ViewerCommunityService.php'=>['addComment','toggleLike','toggleBlock','toggleMute','report','blockedPair','viewer_interaction_blocked'],
 'src/Network/ViewerNotificationService.php'=>['dispatchNewClips','markRead','updatePreferences','viewer_notification_dispatches'],
 'src/Network/CreatorAudienceService.php'=>['followers','destination_opens','comments'],
 'api/v1/viewer/comments.php'=>['ViewerCommunityService','GET','POST','vp3_viewer_api_require_auth'],
 'api/v1/viewer/comment-action.php'=>['like','delete','report','block','mute','vp3_viewer_api_require_auth'],
 'viewer-notifications.php'=>['Notification preferences','Mark all read'],
 'viewer-safety.php'=>['Blocked viewers','Muted creators and shows'],
 'account-audience.php'=>['Audience analytics','Destination opens'],
 'admin/community-moderation.php'=>['Open comment reports','viewer.comment_moderated'],
 'assets/js/community.js'=>['data-comments-button','data-mute-button','comment-action.php','parent_uuid'],
 'clips.php'=>['data-community-drawer','community.js','data-comments-button','data-mute-button'],
 'jobs/viewer-notifications-worker.php'=>['dispatchNewClips'],
];
foreach($checks as$file=>$needles){
    $path=$root.'/'.$file;
    if(!is_file($path))throw new RuntimeException("Missing {$file}");
    $body=(string)file_get_contents($path);
    foreach($needles as$needle){
        if(stripos($body,$needle)===false)throw new RuntimeException("{$file} missing {$needle}");
    }
}
echo "VP3 viewer engagement, notifications, and safety smoke: PASS\n";
