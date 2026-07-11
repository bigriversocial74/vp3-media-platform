<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$checks=[
 'database/schema-viewer-audit.sql'=>['audit_logs','viewer'],
 'database/migrations/20260710_viewer_audit_actor_v1.sql'=>['audit_logs','viewer'],
 'database/schema-viewers.sql'=>['viewer_accounts','viewer_clip_actions','viewer_watch_history','viewer_session_claims','viewer_id'],
 'includes/viewer_auth.php'=>['vp3_attempt_viewer_login','password_verify','viewer_remember_tokens','session_regenerate_id'],
 'includes/viewer_identity.php'=>['vp3_viewer_claim_anonymous_activity','viewer_session_claims','clip_view_events','clip_engagement_events'],
 'src/Network/ViewerFeedService.php'=>['for-you','following','affinity','skipped_count','follows_show'],
 'src/Network/ViewerActionService.php'=>['toggleClip','toggleCreator','toggleShow','recordView'],
 'clips.php'=>['data-reels-app','For You','Following','data-reel-template'],
 'assets/js/reels.js'=>['IntersectionObserver','scrollIntoView','api/v1/viewer/view.php','follow_show'],
 'viewer-signup.php'=>['viewer_accounts','viewer_email_verifications','password_hash'],
 'viewer-settings.php'=>['Download viewer data','Delete viewer account','remembered devices'],
 'admin/viewers.php'=>['Viewer accounts','viewer_watch_history'],
];
foreach($checks as$file=>$needles){$path=$root.'/'.$file;if(!is_file($path))throw new RuntimeException("Missing {$file}");$body=(string)file_get_contents($path);foreach($needles as$needle)if(stripos($body,$needle)===false)throw new RuntimeException("{$file} missing {$needle}");}
echo "VP3 viewer accounts and personalized reels smoke: PASS\n";
