<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require VP3_ROOT.'/includes/bridge-api.php';
use VP3\Network\BridgeCredentialService;
use VP3\Network\BridgeCertificationService;
$request=vp3_bridge_bootstrap(['POST']);
vp3_bridge_execute(function()use($request):array{
    $credentials=new BridgeCredentialService(vp3_db());
    $credentials->requireScope($request['auth'],'clips:context');
    $service=new BridgeCertificationService(vp3_db());
    $action=strtolower(trim((string)($request['input']['action']??'status')));
    return match($action){
        'submit'=>$service->submit($request['auth'],$request['input']),
        'status'=>$service->status($request['auth']),
        default=>throw new RuntimeException('invalid_certification_action'),
    };
});
