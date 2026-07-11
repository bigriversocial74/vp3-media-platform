<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use VP3\Sales\LeadService;
$sent=false;$error='';
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $name=vp3_input('name');$email=strtolower(vp3_input('email'));$message=vp3_input('message');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||vp3_text_length($message)<10){$error='Please provide a valid name, email, and message.';}
    elseif(!vp3_rate_limit('contact:'.$email,3,3600)){$error='Too many requests. Please try again later.';}
    else{
        try{
            if(vp3_db_available()){
                $lead=(new LeadService(vp3_db()))->create([
                    'name'=>$name,'email'=>$email,'company_name'=>vp3_input('company'),'project_name'=>vp3_input('project_name'),
                    'project_type'=>vp3_input('project_type'),'summary'=>$message,'source_detail'=>'contact_form',
                ],'contact');
                vp3_audit('public',null,'lead.created','lead',$lead['lead_uuid'],['source'=>'contact']);
            }else{
                vp3_log('info','Sales contact request',['name'=>$name,'email'=>$email,'message_length'=>strlen($message)]);
            }
            $sent=true;
        }catch(Throwable $e){vp3_log('error','Contact lead save failed',['message'=>$e->getMessage()]);$error='Your request could not be saved. Please try again.';}
    }
}
$pageTitle='Contact';require VP3_ROOT.'/includes/header.php';?>
<section class="form-shell"><span class="eyebrow">Talk to VP3</span><h1>Plan your media platform launch.</h1><p class="helper">Tell us about your story, audience, creative support, hosting preference, and launch goals.</p><?php if($sent):?><div class="flash success">Your request has been received and added to the VP3 discovery pipeline.</div><a class="button" href="book-demo.php">Book a discovery call</a><?php else:?><?php if($error):?><div class="flash error"><?=vp3_e($error)?></div><?php endif;?><form method="post" novalidate><?=vp3_csrf_field()?><div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?=vp3_e(vp3_input('name'))?>"></div><div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required value="<?=vp3_e(vp3_input('email'))?>"></div><div class="field"><label for="company">Company or brand</label><input id="company" name="company" value="<?=vp3_e(vp3_input('company'))?>"></div><div class="field"><label for="project_name">Project name</label><input id="project_name" name="project_name" value="<?=vp3_e(vp3_input('project_name'))?>"></div><div class="field"><label for="project_type">Project type</label><input id="project_type" name="project_type" value="<?=vp3_e(vp3_input('project_type'))?>" placeholder="Show, music, creator platform, media brand..."></div><div class="field"><label for="message">How can VP3 help?</label><textarea id="message" name="message" required><?=vp3_e(vp3_input('message'))?></textarea></div><button class="button" type="submit">Send request</button></form><?php endif;?></section>
<?php require VP3_ROOT.'/includes/footer.php';?>
