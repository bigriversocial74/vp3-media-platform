<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$admin = vp3_require_admin();
if (vp3_method() !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
vp3_verify_csrf();
$theme = vp3_input('theme', 'system');
if (!in_array($theme, ['light','dark','system'], true)) {
    $theme = 'system';
}
vp3_db()->prepare('UPDATE admins SET theme_preference=?,updated_at=NOW() WHERE id=?')->execute([$theme,(int)$admin['id']]);
$_SESSION['admin_theme_preference'] = $theme;
vp3_audit('admin',(int)$admin['id'],'admin.theme_updated','admin',(string)$admin['id'],['theme'=>$theme]);
$referer = str_replace(["\r","\n"], '', (string)($_SERVER['HTTP_REFERER'] ?? vp3_url('admin/index.php')));
$host = (string)parse_url(vp3_url(), PHP_URL_HOST);
$refererHost = (string)parse_url($referer, PHP_URL_HOST);
header('Location: ' . (($refererHost === '' || $refererHost === $host) ? $referer : vp3_url('admin/index.php')), true, 303);
exit;
