<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use VP3\Network\ViewerNotificationService;

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}
$limit=max(1,min((int)($argv[1]??20),100));
$result=(new ViewerNotificationService(vp3_db()))->dispatchNewClips($limit);
fwrite(STDOUT,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
