<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once VP3_ROOT.'/includes/network.php';
require_once VP3_ROOT.'/includes/reels.php';

use VP3\Network\ViewerFeedService;

$feed=in_array(vp3_input('feed','for-you'),['for-you','following','trending','new'],true)?vp3_input('feed','for-you'):'for-you';
$pageTitle='VP3 Reels';
$pageDescription='A personalized vertical feed of clips created inside independent VP3 creator platforms.';
$bodyClass='reels-page';
$extraStyles=['assets/css/reels.css','assets/css/community.css'];
$extraScripts=['assets/js/reels.js','assets/js/community.js'];
$identity=vp3_viewer_identity();
$initial=vp3_db_available()
    ?(new ViewerFeedService(vp3_db()))->clips($feed,null,8,$identity)
    :['items'=>vp3_network_clips(8,$feed==='new'?'new':($feed==='trending'?'trending':'featured')),'next_cursor'=>null,'feed'=>$feed,'authenticated'=>!empty($identity['viewer_id'])];
require VP3_ROOT.'/includes/header.php';
?>
<section class="reels-shell" data-reels-app data-feed="<?=vp3_e($feed)?>" data-next-cursor="<?=vp3_e((string)($initial['next_cursor']??''))?>" data-authenticated="<?=!empty($identity['viewer_id'])?'1':'0'?>">
<header class="reels-topbar"><div><span class="eyebrow">VP3 Clips</span><h1>Discover the story. Enter the world.</h1></div><nav class="reels-tabs" aria-label="Reels feeds"><a class="<?=$feed==='for-you'?'active':''?>" href="?feed=for-you">For You</a><a class="<?=$feed==='following'?'active':''?>" href="?feed=following">Following</a><a class="<?=$feed==='trending'?'active':''?>" href="?feed=trending">Trending</a><a class="<?=$feed==='new'?'active':''?>" href="?feed=new">New</a></nav></header>
<div class="reels-viewport" data-reels-viewport>
<?php foreach(($initial['items']??[])as$clip):?><?=vp3_reel_card($clip)?><?php endforeach;?>
<?php if(empty($initial['items'])):?><section class="reels-empty"><h2><?=$feed==='following'?'Follow creators and shows to build this feed.':'No clips are ready for this feed yet.'?></h2><p><?=$feed==='following'&&!$identity['viewer_id']?'Create or sign in to a viewer account, then follow the worlds you want to return to.':'VP3 only displays published, approved, rights-confirmed clips.'?></p><?php if(!$identity['viewer_id']):?><a class="button" href="viewer-signup.php?return=clips.php%3Ffeed%3Dfollowing">Create viewer account</a><?php endif;?></section><?php endif;?>
<div class="reels-loader" data-reels-loader hidden>Loading more clips…</div>
</div>
<nav class="reels-mobile-nav" aria-label="Viewer navigation"><a class="active" href="clips.php">Reels</a><a href="viewer-following.php">Following</a><a href="viewer-saved.php">Saved</a><a href="viewer-notifications.php">Alerts</a><a href="viewer.php">Profile</a></nav>
</section>

<aside class="community-drawer" data-community-drawer hidden>
  <section class="community-panel" role="dialog" aria-modal="true" aria-label="Clip comments">
    <header class="community-head"><div><span class="eyebrow">Discussion</span><h2 data-comment-title>Comments</h2></div><button class="community-close" type="button" data-community-close aria-label="Close comments">×</button></header>
    <div class="comment-list" data-comment-list></div>
    <?php if(!empty($identity['viewer_id'])):?>
      <form class="comment-form" data-comment-form><input type="hidden" name="parent_uuid"><textarea name="body" maxlength="1000" required placeholder="Add a comment…"></textarea><button class="button" type="submit">Post comment</button></form>
    <?php else:?>
      <div class="comment-login"><p>Sign in with a viewer account to join the discussion.</p><a class="button" href="viewer-login.php?return=clips.php">Viewer sign in</a></div>
    <?php endif;?>
  </section>
</aside>

<template data-reel-template><article class="reel" data-reel><div class="reel-stage"><video playsinline loop muted preload="metadata"></video><img class="reel-poster" alt=""><button class="reel-center-play" type="button" aria-label="Play or pause">▶</button><div class="reel-gradient"></div><div class="reel-copy"><a class="reel-creator" data-creator-link></a><h2 data-title></h2><p data-caption></p><a class="reel-show" data-show-link></a><a class="reel-destination button small" data-destination target="_blank" rel="noopener">Enter the full experience</a></div><aside class="reel-actions"><button type="button" data-action="like"><span>♡</span><small>Like</small></button><button type="button" data-comments-button><span>◌</span><small data-comment-count>0</small></button><button type="button" data-action="save"><span>▣</span><small>Save</small></button><button type="button" data-action="follow_creator"><span>＋</span><small>Creator</small></button><button type="button" data-action="follow_show"><span>◎</span><small>Show</small></button><button type="button" data-mute-button><span>⊘</span><small>Mute</small></button><button type="button" data-action="share"><span>↗</span><small>Share</small></button><button type="button" data-action="report"><span>!</span><small>Report</small></button></aside><div class="reel-progress"><i></i></div></div></article></template>
<script type="application/json" id="vp3-reels-initial"><?=json_encode($initial,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<?php require VP3_ROOT.'/includes/footer.php';?>
