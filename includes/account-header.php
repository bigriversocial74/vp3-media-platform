<?php
declare(strict_types=1);
$customer=vp3_require_customer();
$pageTitle=$pageTitle??'Customer Account';
require VP3_ROOT.'/includes/header.php';
?>
<div class="dashboard">
<aside class="sidebar"><h3>Customer account</h3><p><?=vp3_e($customer['name'])?></p><nav>
<a href="<?=vp3_e(vp3_url('account.php'))?>">Overview</a>
<a href="<?=vp3_e(vp3_url('account-orders.php'))?>">Orders</a>
<a href="<?=vp3_e(vp3_url('account-licenses.php'))?>">Licenses</a>
<a href="<?=vp3_e(vp3_url('account-hosting.php'))?>">Hosting</a>
<a href="<?=vp3_e(vp3_url('account-downloads.php'))?>">Downloads</a>
<a href="<?=vp3_e(vp3_url('account-network.php'))?>">Network & clips</a>
<a href="<?=vp3_e(vp3_url('account-audience.php'))?>">Audience analytics</a>
<a href="<?=vp3_e(vp3_url('account-projects.php'))?>">Projects</a>
<a href="<?=vp3_e(vp3_url('account-proposals.php'))?>">Proposals</a>
<a href="<?=vp3_e(vp3_url('account-assets.php'))?>">Asset library</a>
<a href="<?=vp3_e(vp3_url('account-notifications.php'))?>">Notifications<?php $unread=vp3_notification_count('customer',(int)$customer['id']);if($unread):?> <b class="nav-count"><?=$unread?></b><?php endif;?></a>
<a href="<?=vp3_e(vp3_url('account-support.php'))?>">Support</a>
<form method="post" action="<?=vp3_e(vp3_url('logout.php'))?>"><?=vp3_csrf_field()?><button class="button small secondary" type="submit">Sign out</button></form>
</nav></aside><section class="dashboard-main">
