<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$runtime='';
foreach([
 'src/Network/ViewerCommunityService.php',
 'src/Network/ViewerNotificationService.php',
 'api/v1/viewer/comments.php',
 'api/v1/viewer/comment-action.php',
 'api/v1/viewer/notifications.php',
 'api/v1/viewer/safety.php',
 'admin/community-moderation.php',
 'jobs/viewer-notifications-worker.php',
]as$file){$runtime.=(string)file_get_contents($root.'/'.$file);}
$required=[
 'vp3_verify_csrf','vp3_require_viewer','vp3_rate_limit','verified_viewer_required',
 'viewer_blocks','viewer_mutes','viewer_comment_reports','safePath','INSERT IGNORE','viewer_interaction_blocked',
 "publication_status='published'","moderation_status='approved'","rights_status='confirmed'",
];
foreach($required as$needle){
    if(stripos($runtime,$needle)===false)throw new RuntimeException("Community runtime missing security control: {$needle}");
}
if(stripos($runtime,'license_key')!==false)throw new RuntimeException('Viewer community must not depend on product license keys.');
if(stripos((string)file_get_contents($root.'/assets/js/community.js'),'innerHTML')!==false)throw new RuntimeException('Comment rendering must not use innerHTML.');
if(stripos((string)file_get_contents($root.'/src/Network/ViewerNotificationService.php'),'://')===false)throw new RuntimeException('Notification paths require external URL rejection.');
echo "VP3 viewer engagement, notifications, and safety audit: PASS\n";
