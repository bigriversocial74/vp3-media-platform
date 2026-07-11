<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
 'src/Network/BridgeCredentialService.php'=>['platform_bridge_credentials','aes-256-gcm','bridge_replay_rejected','hash_hmac'],
 'src/Network/ClipBridgeService.php'=>['source_revision_conflict','platform_bridge_requests','clip.withdraw','contract_version'],
 'includes/bridge-api.php'=>['HTTP_X_VP3_SIGNATURE','HTTP_X_VP3_REQUEST_ID','vp3_bridge_bootstrap'],
 'api/v1/clips/bridge/publications.php'=>['POST','PUT','DELETE','clips:withdraw'],
 'admin/platform-bridge.php'=>['Shown once','Issue new credential','Revoke'],
 'database/migrations/20260711_vp3_clips_source_bridge_v1.sql'=>['platform_bridge_credentials','platform_bridge_nonces','platform_bridge_requests'],
];
foreach($required as $path=>$needles){$full=$root.'/'.$path;if(!is_file($full))throw new RuntimeException("Missing {$path}");$body=(string)file_get_contents($full);foreach($needles as $needle)if(strpos($body,$needle)===false)throw new RuntimeException("{$path} missing {$needle}");}
$service=(string)file_get_contents($root.'/src/Network/BridgeCredentialService.php');
if(strpos($service,'license_key')!==false)throw new RuntimeException('Bridge credential service must not require or persist the customer license key.');
if(strpos($service,'hash_hmac')===false||strpos($service,'platform_bridge_nonces')===false)throw new RuntimeException('Signed replay-resistant authentication is required.');
echo "VP3 Clips source bridge smoke: PASS\n";
