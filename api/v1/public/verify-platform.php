<?php
declare(strict_types=1);
require dirname(__DIR__,3) . '/bootstrap.php';
require VP3_ROOT . '/includes/api.php';
use VP3\Network\PublicLicenseService;
$input = vp3_public_api_bootstrap(['GET']);
vp3_api_required($input,['verification_id']);
vp3_api_execute(function () use ($input): array {
    $record = (new PublicLicenseService(vp3_db()))->verify((string)$input['verification_id']);
    if (!$record) throw new RuntimeException('verification_not_found');
    return $record;
});
