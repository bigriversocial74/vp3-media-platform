<?php
declare(strict_types=1);
require dirname(__DIR__,3) . '/bootstrap.php';
require VP3_ROOT . '/includes/api.php';
use VP3\Network\ClipSyndicationService;
$input = vp3_api_bootstrap(['POST','PUT','DELETE']);
vp3_api_required($input,['license_key','product_id','domain','installation_uuid','installation_token','source_platform_uuid','source_clip_uuid','timestamp','nonce']);
$service = new ClipSyndicationService(vp3_db());
vp3_api_execute(static function () use ($service,$input): array {
    return vp3_method() === 'DELETE' ? $service->unpublish($input) : $service->publish($input);
});
