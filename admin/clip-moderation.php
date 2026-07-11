<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('clips.moderate');
header('Location: ' . vp3_url('admin/clips.php?moderation=pending'), true, 302);
exit;
