<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use VP3\Mail\MailerFactory;
$error='';
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $name=vp3_input('name');$company=vp3_input('company_name');$email=strtolower(vp3_input('email'));$password=(string)($_POST['password']??'');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){$error='Use a valid name, email, and password of at least 12 characters.';}
    elseif(!vp3_db_available()){$error='Database configuration is required before account creation.';}
    else{
        try{
            $token=vp3_secure_token();
            vp3_transaction(function(PDO $pdo)use($name,$company,$email,$password,$token):void{
                $stmt=$pdo->prepare("INSERT INTO customers (customer_uuid,name,company_name,email,password_hash,status,created_at,updated_at) VALUES (?,?,?,?,?,'pending',NOW(),NOW())");
                $stmt->execute([vp3_uuid(),$name,$company?:null,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $customerId=(int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO email_verifications(customer_id,token_hash,expires_at,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW())')->execute([$customerId,vp3_hash_token($token)]);
                vp3_audit('customer',$customerId,'customer.registered','customer',(string)$customerId);
            });
            $url=vp3_url('verify-email.php?token='.rawurlencode($token));
            MailerFactory::make()->send($email,'Verify your VP3 Media Group account',"Verify your account: {$url}\n\nThis link expires in 24 hours.");
            $_SESSION['verification_email']=$email;
            vp3_redirect('verification-pending.php');
        }catch(PDOException $e){$error=$e->getCode()==='23000'?'An account already exists for that email.':'Account creation failed.';vp3_log('error','Signup failed',['code'=>$e->getCode()]);}
    }
}
$pageTitle='Create Account';$pageDescription='Create your VP3 account and begin planning your owned media platform launch.';$bodyClass='auth-page signup-page';require VP3_ROOT.'/includes/header.php';?>
<section class="auth-layout reverse-auth">
  <div class="auth-visual"><img src="<?=vp3_e(vp3_url('assets/images/site/signup-launch.svg'))?>" alt="Creator launching a media platform with VP3"><div class="auth-visual-copy"><span class="eyebrow">Start your platform</span><h2>Give your story a destination built to grow with it.</h2></div></div>
  <div class="auth-panel"><div class="auth-panel-inner"><span class="eyebrow">Create your account</span><h1>Begin your VP3 launch.</h1><p class="auth-intro">Set up the customer account that will hold your orders, licenses, hosted platforms, downloads, and support history.</p><?php if($error):?><div class="flash error"><?=vp3_e($error)?></div><?php endif;?><form method="post"><?=vp3_csrf_field()?><div class="field-row"><div class="field"><label for="name">Your name</label><input id="name" name="name" required autocomplete="name" value="<?=vp3_e((string)($_POST['name']??''))?>"></div><div class="field"><label for="company_name">Company or brand</label><input id="company_name" name="company_name" autocomplete="organization" value="<?=vp3_e((string)($_POST['company_name']??''))?>"></div></div><div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" required autocomplete="email" value="<?=vp3_e((string)($_POST['email']??''))?>"></div><div class="field"><label for="password">Create a password</label><input id="password" name="password" type="password" minlength="12" required autocomplete="new-password"><span class="helper">Use at least 12 characters.</span></div><button class="button auth-submit" type="submit">Create account</button></form><p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p><div class="signup-benefits"><span>Secure account verification</span><span>Product and license management</span><span>Hosted launch tracking</span></div></div></div>
</section>
<?php require VP3_ROOT.'/includes/footer.php';?>
