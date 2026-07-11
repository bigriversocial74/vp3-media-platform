<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require VP3_ROOT.'/includes/viewer_api.php';
use VP3\Network\ViewerFeedService;
$input=vp3_viewer_api_bootstrap(['GET']);
$feed=(string)($input['feed']??'for-you');
$cursor=isset($input['cursor'])?(string)$input['cursor']:null;
$limit=(int)($input['limit']??10);
vp3_viewer_api_execute(fn():array=>(new ViewerFeedService(vp3_db()))->clips($feed,$cursor,$limit,vp3_viewer_identity()));
