<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require_once VP3_ROOT.'/includes/viewer_api.php';

use VP3\Network\ViewerCommunityService;

$input=vp3_viewer_api_bootstrap(['GET','POST']);
$service=new ViewerCommunityService(vp3_db());

if(vp3_method()==='GET'){
    $uuid=(string)($input['publication_uuid']??'');
    $identity=vp3_viewer_identity();
    vp3_viewer_api_execute(fn():array=>$service->comments($uuid,$identity,(int)($input['limit']??80),(int)($input['offset']??0)));
}

$viewer=vp3_require_viewer();
if(!vp3_rate_limit('viewer-comment:'.(int)$viewer['id'],20,300)){
    vp3_json(['ok'=>false,'error'=>['code'=>'comment_rate_limited','message'=>'Please wait before posting more comments.']],429);
}
vp3_viewer_api_execute(fn():array=>$service->addComment(
    (string)($input['publication_uuid']??''),
    (string)($input['body']??''),
    (int)$viewer['id'],
    isset($input['parent_uuid'])?(string)$input['parent_uuid']:null
));
