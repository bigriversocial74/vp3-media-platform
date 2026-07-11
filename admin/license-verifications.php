<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('network.view');
header('Location: ' . vp3_url('admin/public-listings.php'), true, 302);
exit;
