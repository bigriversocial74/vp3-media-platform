<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
 'src/Network/BridgeCertificationService.php'=>['REQUIRED_SOURCE_CHECKS','bridge_certification_required','live_publishing_approved','INTERVAL 180 DAY'],
 'api/v1/clips/bridge/certification.php'=>['submit','status','clips:context'],
 'api/v1/clips/bridge/context.php'=>['publishing_mode','certification_status'],
 'api/v1/clips/bridge/publications.php'=>['requireLive','clips:publish'],
 'admin/platform-bridge-certification.php'=>['Approve live','Certification policy','Revoke'],
 'database/migrations/20260711_vp3_clips_live_certification_v1.sql'=>['platform_bridge_certifications','platform_bridge_certification_events'],
];
foreach($required as $path=>$needles){$body=(string)file_get_contents($root.'/'.$path);if($body==='')throw new RuntimeException("Missing {$path}");foreach($needles as $needle)if(strpos($body,$needle)===false)throw new RuntimeException("{$path} missing {$needle}");}
$publication=(string)file_get_contents($root.'/api/v1/clips/bridge/publications.php');
if(strpos($publication,'requireLive')===false)throw new RuntimeException('Real publication must require approved certification.');
$service=(string)file_get_contents($root.'/src/Network/BridgeCertificationService.php');
if(strpos($service,'license_key')!==false)throw new RuntimeException('Certification must not require the product license key.');
echo "VP3 Clips live certification smoke: PASS\n";
