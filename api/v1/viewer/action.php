<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require VP3_ROOT.'/includes/viewer_api.php';
use VP3\Network\ViewerActionService;
$input=vp3_viewer_api_bootstrap(['POST']);$type=(string)($input['type']??'');$service=new ViewerActionService(vp3_db());$identity=vp3_viewer_identity();
vp3_viewer_api_execute(function()use($type,$input,$service,$identity):array{return match($type){
 'like','save','hide'=>$service->toggleClip((string)($input['publication_uuid']??''),$type,$identity),
 'follow_creator'=>$service->toggleCreator((string)($input['creator_uuid']??''),$identity),
 'follow_show'=>$service->toggleShow((string)($input['show_uuid']??''),$identity),
 default=>throw new RuntimeException('invalid_viewer_action'),
};});
