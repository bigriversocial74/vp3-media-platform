<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';$pageTitle='Verify Viewer Email';$bodyClass='auth-page viewer-auth-page';$extraStyles=['assets/css/reels.css'];require VP3_ROOT.'/includes/header.php';?>
<section class="auth-layout compact-auth"><div class="auth-panel"><div class="auth-panel-inner"><span class="eyebrow">One more step</span><h1>Check your email.</h1><p>We sent a verification link<?=!empty($_SESSION['viewer_verification_email'])?' to '.vp3_e((string)$_SESSION['viewer_verification_email']):''?>. The link expires in 24 hours.</p><a class="button auth-submit" href="viewer-login.php">Return to viewer sign in</a></div></div></section>
<?php require VP3_ROOT.'/includes/footer.php';?>
