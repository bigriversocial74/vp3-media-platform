<?php
declare(strict_types=1);
$admin = vp3_require_admin();
$pageTitle = $pageTitle ?? 'Administration';
$bodyClass = trim(($bodyClass ?? '') . ' admin-page');
$htmlAdminTheme = in_array((string)($admin['theme_preference'] ?? 'system'), ['light','dark','system'], true) ? (string)$admin['theme_preference'] : 'system';
require VP3_ROOT . '/includes/header.php';
$adminLinks = [
    ['dashboard.view', 'admin/index.php', 'Dashboard'],
    ['customers.view', 'admin/customers.php', 'Customers'],
    ['products.view', 'admin/products.php', 'Products'],
    ['products.view', 'admin/plans.php', 'Plans'],
    ['orders.view', 'admin/orders.php', 'Orders'],
    ['licenses.view', 'admin/licenses.php', 'Licenses'],
    ['hosting.view', 'admin/hosting.php', 'Hosting'],
    ['installations.manage', 'admin/installations.php', 'Installations'],
    ['releases.manage', 'admin/releases.php', 'Releases'],
    ['support.manage', 'admin/support.php', 'Support'],
    ['network.view', 'admin/creators.php', 'Creators'],
    ['network.view', 'admin/shows.php', 'Shows'],
    ['clips.moderate', 'admin/clips.php', 'Clip moderation'],
    ['network.view', 'admin/public-listings.php', 'Public platforms'],
    ['audit.view', 'admin/audit.php', 'Audit history'],
    ['settings.manage', 'admin/settings.php', 'Settings'],
];
?>
<div class="dashboard"><aside class="sidebar"><h3>VP3 Administration</h3><p><?=vp3_e($admin['name'])?> · <?=vp3_e($admin['role'])?></p><form class="admin-theme-switcher" method="post" action="<?=vp3_e(vp3_url('admin/theme.php'))?>"><?=vp3_csrf_field()?><?php foreach(['light'=>'☀ Light','dark'=>'◐ Dark','system'=>'◒ System'] as $value=>$label): ?><button type="submit" name="theme" value="<?=$value?>" class="<?= $htmlAdminTheme===$value?'active':'' ?>"><?=$label?></button><?php endforeach; ?></form><nav>
<?php foreach ($adminLinks as [$permission, $path, $label]): ?>
<?php if (vp3_admin_can($admin, $permission)): ?><a href="<?=vp3_e(vp3_url($path))?>"><?=vp3_e($label)?></a><?php endif; ?>
<?php endforeach; ?>
<form method="post" action="<?=vp3_e(vp3_url('admin/logout.php'))?>"><?=vp3_csrf_field()?><button class="button small secondary" type="submit">Sign out</button></form>
</nav></aside><section class="dashboard-main">
