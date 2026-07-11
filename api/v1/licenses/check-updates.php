<?php
declare(strict_types=1);require dirname(__DIR__,3).'/bootstrap.php';require VP3_ROOT.'/includes/api.php';use VP3\Licensing\LicenseService;$input=vp3_api_bootstrap();vp3_api_required($input,['license_key','product_id','domain','installation_uuid','installation_token','timestamp','nonce']);vp3_api_execute(fn()=>(new LicenseService(vp3_db()))->checkUpdates($input));
