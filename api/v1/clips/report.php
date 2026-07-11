<?php
declare(strict_types=1);
require dirname(__DIR__,3) . '/bootstrap.php';
require VP3_ROOT . '/includes/api.php';
use VP3\Network\ClipSyndicationService;
$input = vp3_public_api_bootstrap(['POST']);
vp3_api_required($input, ['publication_uuid','session_id','reason']);
vp3_api_execute(fn(): array => (new ClipSyndicationService(vp3_db()))->recordReport(
    (string)$input['publication_uuid'],
    (string)$input['session_id'],
    (string)$input['reason'],
    (string)($input['details'] ?? '')
));
