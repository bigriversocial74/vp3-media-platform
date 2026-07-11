<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require VP3_ROOT.'/includes/bridge-api.php';
use VP3\Network\BridgeCredentialService;
use VP3\Network\BridgeCertificationService;
use VP3\Network\ClipBridgeService;
$request=vp3_bridge_bootstrap(['POST']);
vp3_bridge_execute(function()use($request):array{
    (new BridgeCredentialService(vp3_db()))->requireScope($request['auth'],'clips:context');
    $data=(new ClipBridgeService(vp3_db()))->context($request['auth']);
    $certification=(new BridgeCertificationService(vp3_db()))->status($request['auth']);
    return array_merge($data,[
        'publishing_mode'=>$certification['publishing_mode'],
        'certification_status'=>$certification['certification_status'],
        'certification_uuid'=>$certification['certification_uuid'],
        'certification_expires_at'=>$certification['expires_at'],
    ]);
});
