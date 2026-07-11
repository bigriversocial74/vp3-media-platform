<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require VP3_ROOT.'/includes/viewer_api.php';
use VP3\Network\ViewerActionService;
$input=vp3_viewer_api_bootstrap(['POST']);
vp3_viewer_api_execute(fn():array=>(new ViewerActionService(vp3_db()))->recordView((string)($input['publication_uuid']??''),(int)($input['watch_seconds']??0),!empty($input['completed']),!empty($input['skipped']),vp3_viewer_identity()));
