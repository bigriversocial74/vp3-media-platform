<?php
declare(strict_types=1);

if (defined('VP3_VIEWER_LOADED')) {
    return;
}
define('VP3_VIEWER_LOADED', true);

require_once VP3_ROOT . '/includes/viewer_identity.php';
require_once VP3_ROOT . '/includes/viewer_auth.php';

if (PHP_SAPI !== 'cli') {
    vp3_viewer_restore_remembered_login();
}
