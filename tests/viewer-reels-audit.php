<?php
declare(strict_types=1);
$root=dirname(__DIR__);$runtime='';
foreach(['includes/viewer_auth.php','includes/viewer_identity.php','includes/viewer_api.php','src/Network/ViewerFeedService.php','src/Network/ViewerActionService.php','viewer-signup.php','viewer-login.php','viewer-delete.php','api/v1/viewer/action.php','api/v1/viewer/profile.php']as$file){$body=(string)file_get_contents($root.'/'.$file);$runtime.=$body;}
$required=['password_hash','password_verify','vp3_verify_csrf','X_CSRF_TOKEN','hash_hmac','session_regenerate_id','viewer_id','session_hash','identity_key'];foreach($required as$needle)if(stripos($runtime,$needle)===false)throw new RuntimeException("Viewer runtime missing security control: {$needle}");
if(stripos($runtime,'license_key')!==false)throw new RuntimeException('Viewer accounts must not require or store a product license key.');
if(stripos((string)file_get_contents($root.'/includes/viewer_auth.php'),'token_hash')===false)throw new RuntimeException('Remembered viewer devices must store hashed tokens.');
if(stripos((string)file_get_contents($root.'/viewer-delete.php'),'password_verify')===false)throw new RuntimeException('Viewer deletion requires password confirmation.');
if(stripos((string)file_get_contents($root.'/api/v1/viewer/profile.php'),'viewer_auth_required')===false)throw new RuntimeException('Viewer profile writes require authenticated viewer identity.');
$auditSchema=(string)file_get_contents($root.'/database/schema-viewer-audit.sql');
if(stripos($auditSchema,"'viewer'")===false)throw new RuntimeException('Audit actor enum must support viewer identities.');
echo "VP3 viewer accounts and personalized reels audit: PASS\n";
