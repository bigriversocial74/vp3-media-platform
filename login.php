<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$error='';
if(vp3_method()==='POST'){
    vp3_verify_csrf();$email=strtolower(vp3_input('email'));$password=(string)($_POST['password']??'');
    if(!vp3_db_available()){$error='Database configuration is required before sign in.';}
    elseif(vp3_attempt_customer_login($email,$password)){vp3_redirect('account.php');}
    else{$error='The email or password was not accepted.';}
}
$pageTitle='Sign In';$pageDescription='Sign in to manage your VP3 products, licenses, hosting, downloads, and support.';$bodyClass='auth-page';require VP3_ROOT.'/includes/header.php';?>
<section class="auth-layout">
  <div class="auth-visual"><img src="<?=vp3_e(vp3_url('assets/images/site/signin-security.svg'))?>" alt="Secure VP3 creator account workspace"><div class="auth-visual-copy"><span class="eyebrow">Secure creator access</span><h2>Your platform, releases, licenses, and support—one account.</h2></div></div>
  <div class="auth-panel"><div class="auth-panel-inner"><span class="eyebrow">Welcome back</span><h1>Sign in to VP3.</h1><p class="auth-intro">Continue managing the media experience behind your story.</p><?php if($error):?><div class="flash error"><?=vp3_e($error)?></div><?php endif;?><form method="post"><?=vp3_csrf_field()?><div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" required autocomplete="email" value="<?=vp3_e((string)($_POST['email']??''))?>"></div><div class="field"><div class="field-label-row"><label for="password">Password</label><a href="forgot-password.php">Forgot password?</a></div><input id="password" name="password" type="password" required autocomplete="current-password"></div><button class="button auth-submit" type="submit">Sign in</button></form><div class="auth-divider"><span>New to VP3?</span></div><a class="button secondary auth-submit" href="signup.php">Create your account</a><p class="auth-note">Protected by secure sessions, password hashing, login throttling, and customer authorization boundaries.</p></div></div>
</section>
<?php require VP3_ROOT.'/includes/footer.php';?>
