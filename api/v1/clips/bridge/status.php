<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
require VP3_ROOT.'/includes/bridge-api.php';
use VP3\Network\BridgeCredentialService;
use VP3\Network\ClipBridgeService;
$request=vp3_bridge_bootstrap(['POST']);
vp3_bridge_execute(function()use($request):array{(new BridgeCredentialService(vp3_db()))->requireScope($request['auth'],'clips:read');return(new ClipBridgeService(vp3_db()))->status($request['auth'],$request['input']);});
