<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$sent=false;$error='';
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $name=vp3_input('name');$email=strtolower(vp3_input('email'));$message=vp3_input('message');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($message)<10){$error='Please provide a valid name, email, and message.';}
    elseif(!vp3_rate_limit('contact:'.$email,3,3600)){$error='Too many requests. Please try again later.';}
    else{vp3_log('info','Sales contact request',['name'=>$name,'email'=>$email,'message_length'=>strlen($message)]);$sent=true;}
}
$pageTitle='Contact';require VP3_ROOT.'/includes/header.php';?>
<section class="form-shell"><span class="eyebrow">Talk to VP3</span><h1>Plan your media platform launch.</h1><p class="helper">Tell us about your product, audience, hosting preference, and launch goals.</p><?php if($sent):?><div class="flash success">Your request has been received.</div><?php else:?><?php if($error):?><div class="flash error"><?=vp3_e($error)?></div><?php endif;?><form method="post" novalidate><?=vp3_csrf_field()?><div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?=vp3_e(vp3_input('name'))?>"></div><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required value="<?=vp3_e(vp3_input('email'))?>"></div><div class="field"><label for="company">Company or brand</label><input id="company" name="company" value="<?=vp3_e(vp3_input('company'))?>"></div><div class="field"><label for="message">How can VP3 help?</label><textarea id="message" name="message" required><?=vp3_e(vp3_input('message'))?></textarea></div><button class="button" type="submit">Send request</button></form><?php endif;?></section>
<?php require VP3_ROOT.'/includes/footer.php';?>