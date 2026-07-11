<?php
declare(strict_types=1);
$viewer=vp3_require_viewer();
$extraStyles=['assets/css/reels.css','assets/css/community.css'];
$extraScripts=['assets/js/reels.js'];
$pageTitle=$pageTitle??'Viewer Account';
$pageDescription=$pageDescription??'Manage your VP3 Clips viewer profile, follows, saved clips, community activity, and watch history.';
$bodyClass=trim(($bodyClass??'').' viewer-account-page');
require VP3_ROOT.'/includes/header.php';
$viewerUnread=vp3_viewer_notification_count((int)$viewer['id']);
?>
<section class="viewer-account-hero"><div><span class="eyebrow">VP3 Viewer</span><h1><?=vp3_e($pageTitle)?></h1><p><?=vp3_e($pageDescription)?></p></div><a class="button secondary" href="<?=vp3_e(vp3_url('clips.php'))?>">Open Reels</a></section>
<nav class="viewer-account-nav" aria-label="Viewer account"><a href="<?=vp3_e(vp3_url('viewer.php'))?>">Profile</a><a href="<?=vp3_e(vp3_url('viewer-saved.php'))?>">Saved</a><a href="<?=vp3_e(vp3_url('viewer-liked.php'))?>">Liked</a><a href="<?=vp3_e(vp3_url('viewer-following.php'))?>">Following</a><a href="<?=vp3_e(vp3_url('viewer-history.php'))?>">History</a><a href="<?=vp3_e(vp3_url('viewer-notifications.php'))?>">Notifications<?php if($viewerUnread):?> <b class="nav-count"><?=$viewerUnread?></b><?php endif;?></a><a href="<?=vp3_e(vp3_url('viewer-safety.php'))?>">Safety</a><a href="<?=vp3_e(vp3_url('viewer-settings.php'))?>">Settings</a><form method="post" action="<?=vp3_e(vp3_url('viewer-logout.php'))?>"><?=vp3_csrf_field()?><button type="submit">Sign out</button></form></nav>
