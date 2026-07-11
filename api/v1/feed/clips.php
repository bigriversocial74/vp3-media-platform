<?php
declare(strict_types=1);
require dirname(__DIR__,3) . '/bootstrap.php';
require VP3_ROOT . '/includes/api.php';
use VP3\Network\FeedService;
$input = vp3_public_api_bootstrap(['GET']);
$feed = in_array((string)($input['feed'] ?? 'featured'),['featured','trending','new'],true)?(string)($input['feed'] ?? 'featured'):'featured';
vp3_api_execute(fn(): array => (new FeedService(vp3_db()))->clips($feed,isset($input['cursor'])?(string)$input['cursor']:null,(int)($input['limit'] ?? 20)));
