<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use VP3\Sales\LeadService;
$sent=false;$error='';$requestId='';
if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $name=vp3_input('name');$email=strtolower(vp3_input('email'));$summary=vp3_input('summary');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||vp3_text_length($summary)<20){$error='Please provide your name, a valid email, and at least a short project summary.';}
    elseif(!vp3_rate_limit('demo:'.$email,3,3600)){$error='Too many demo requests. Please try again later.';}
    else{
        try{
            if(vp3_db_available()){
                $service=new LeadService(vp3_db());
                $lead=$service->create([
                    'name'=>$name,'email'=>$email,'phone'=>vp3_input('phone'),'company_name'=>vp3_input('company_name'),'project_name'=>vp3_input('project_name'),
                    'project_type'=>vp3_input('project_type'),'budget_range'=>vp3_input('budget_range'),'target_launch_date'=>vp3_input('target_launch_date'),
                    'summary'=>$summary,'source_detail'=>vp3_input('service_interest'),'priority'=>'normal',
                ],'demo_request');
                $requestId=$service->createDemoRequest((int)$lead['id'],[
                    'preferred_date'=>vp3_input('preferred_date'),'preferred_time_window'=>vp3_input('preferred_time_window'),'timezone'=>vp3_input('timezone'),
                    'meeting_format'=>vp3_input('meeting_format','video'),'attendee_count'=>vp3_input('attendee_count','1'),'goals'=>vp3_input('goals'),
                ]);
                vp3_audit('public',null,'demo.requested','demo_request',$requestId,['lead_uuid'=>$lead['lead_uuid']]);
            }else{
                vp3_log('info','Demo request received without database',['name'=>$name,'email'=>$email,'project_type'=>vp3_input('project_type')]);
            }
            $sent=true;
        }catch(Throwable $e){vp3_log('error','Demo request failed',['message'=>$e->getMessage()]);$error='The request could not be saved. Please try again or use the contact page.';}
    }
}
$pageTitle='Book a Discovery Call';$pageDescription='Book a VP3 discovery call to discuss your story, platform, creative services, launch goals, and production needs.';
require VP3_ROOT.'/includes/header.php';
?>
<section class="intake-layout"><div class="intake-copy"><span class="eyebrow">Discovery before software</span><h1>Tell us what you are trying to create.</h1><p>We will use the call to understand the story, audience, release model, existing assets, launch timing, and the support needed to move forward.</p><div class="intake-points"><article><b>30–45 minutes</b><span>Focused project discovery</span></article><article><b>No generic sales script</b><span>Your story and operating needs first</span></article><article><b>Clear next step</b><span>Brief, proposal, or product path</span></article></div></div><div class="form-shell intake-form"><?php if($sent):?><div class="success-panel"><span>✓</span><h2>Your discovery request is in.</h2><p>VP3 has received your project details. A team member can now qualify the request, schedule the conversation, and prepare the next step.</p><a class="button" href="<?=vp3_e(vp3_url('services.php'))?>">Review services</a></div><?php else:?><?php if($error):?><div class="flash error"><?=vp3_e($error)?></div><?php endif;?><form method="post" novalidate><?=vp3_csrf_field()?><input type="hidden" name="service_interest" value="<?=vp3_e(vp3_input('service_interest',vp3_input('service')))?>"><div class="form-grid"><div class="field"><label>Name</label><input name="name" required value="<?=vp3_e(vp3_input('name'))?>"></div><div class="field"><label>Email</label><input name="email" type="email" required value="<?=vp3_e(vp3_input('email'))?>"></div><div class="field"><label>Phone</label><input name="phone" value="<?=vp3_e(vp3_input('phone'))?>"></div><div class="field"><label>Company, brand, or creator name</label><input name="company_name" value="<?=vp3_e(vp3_input('company_name'))?>"></div><div class="field"><label>Project name</label><input name="project_name" value="<?=vp3_e(vp3_input('project_name'))?>"></div><div class="field"><label>Project type</label><select name="project_type"><option value="">Select</option><?php foreach(['Show or series','Music or artist platform','Creator membership','Podcast or video network','Media brand','Existing platform upgrade','Other'] as $value):?><option <?=vp3_input('project_type')===$value?'selected':''?>><?=vp3_e($value)?></option><?php endforeach;?></select></div><div class="field"><label>Budget range</label><select name="budget_range"><option value="">Undecided</option><?php foreach(['Under $5,000','$5,000–$15,000','$15,000–$50,000','$50,000+','Ongoing monthly support'] as $value):?><option <?=vp3_input('budget_range')===$value?'selected':''?>><?=vp3_e($value)?></option><?php endforeach;?></select></div><div class="field"><label>Target launch date</label><input name="target_launch_date" type="date" value="<?=vp3_e(vp3_input('target_launch_date'))?>"></div><div class="field full"><label>What are you creating?</label><textarea name="summary" minlength="20" required><?=vp3_e(vp3_input('summary'))?></textarea></div><div class="field"><label>Preferred date</label><input name="preferred_date" type="date" value="<?=vp3_e(vp3_input('preferred_date'))?>"></div><div class="field"><label>Time window</label><input name="preferred_time_window" placeholder="Example: weekday afternoons" value="<?=vp3_e(vp3_input('preferred_time_window'))?>"></div><div class="field"><label>Timezone</label><input name="timezone" value="<?=vp3_e(vp3_input('timezone','America/Phoenix'))?>"></div><div class="field"><label>Meeting format</label><select name="meeting_format"><option value="video">Video</option><option value="phone">Phone</option><option value="in_person">In person</option><option value="flexible">Flexible</option></select></div><div class="field full"><label>What should the discovery call accomplish?</label><textarea name="goals"><?=vp3_e(vp3_input('goals'))?></textarea></div></div><button class="button" type="submit">Request discovery call</button></form><?php endif;?></div></section>
<?php require VP3_ROOT.'/includes/footer.php'; ?>
