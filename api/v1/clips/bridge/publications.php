<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require VP3_ROOT.'/includes/bridge-api.php';
use VP3\Network\BridgeCredentialService;
use VP3\Network\ClipBridgeService;
$request=vp3_bridge_bootstrap(['POST','PUT','DELETE']);$credentials=new BridgeCredentialService(vp3_db());$service=new ClipBridgeService(vp3_db());
vp3_bridge_execute(function()use($request,$credentials,$service):array{if(vp3_method()==='DELETE'){$credentials->requireScope($request['auth'],'clips:withdraw');return$service->withdraw($request['auth'],$request['input']);}$credentials->requireScope($request['auth'],'clips:publish');return$service->publish($request['auth'],$request['input']);});
