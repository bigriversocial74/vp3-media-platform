<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=[];$warn=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    $path=$file->getPathname();$rel=substr($path,strlen($root)+1);
    if(str_starts_with($rel,'.git/')||str_starts_with($rel,'var/'))continue;
    $content=file_get_contents($path)?:'';
    if($file->getExtension()==='php'){
        if(!str_contains($content,'declare(strict_types=1)'))$fail[]="Missing strict types: {$rel}";
        if(preg_match('/(?:query|prepare)\s*\([^)]*\$_(?:GET|POST|REQUEST)/s',$content))$fail[]="Possible raw input SQL: {$rel}";
        if(preg_match('/\b(?:exec|shell_exec|system|passthru|proc_open)\s*\(/',$content))$fail[]="Shell execution found: {$rel}";
        if($rel!=='tests/static-audit.php'&&str_contains($content,'password_hash')&&preg_match('/echo\s+\$password\b/',$content))$fail[]="Possible password output: {$rel}";
    }
    if($rel!=='tests/static-audit.php'&&preg_match('/sk_live_[A-Za-z0-9]+|AKIA[0-9A-Z]{16}|BEGIN PRIVATE KEY/',$content))$fail[]="Possible secret committed: {$rel}";
}

$licenseService=file_get_contents($root.'/src/Licensing/LicenseService.php')?:'';
if(!str_contains($licenseService,'SELECT id,status,domain FROM license_activations'))$fail[]='Activation lookup must include the bound domain';
$clipService=file_get_contents($root.'/src/Network/ClipSyndicationService.php')?:'';
foreach(['LicenseKey::hash','installation_token_hash','public_platform_not_verified','httpsUrl','rights_status','source_clip_uuid','content_hash','stale_source_update','recordReport','textSlice'] as $needle)if(!str_contains($clipService,$needle))$fail[]="Clip authorization control missing: {$needle}";
if(!str_contains($clipService,"publication_status='withdrawn'"))$fail[]='Source withdrawal control missing';
$statusApi=file_get_contents($root.'/api/v1/clips/status.php')?:'';
$analyticsApi=file_get_contents($root.'/api/v1/clips/analytics.php')?:'';
$reportApi=file_get_contents($root.'/api/v1/clips/report.php')?:'';
if(str_contains($statusApi,"['GET']")||str_contains($analyticsApi,"['GET']")||str_contains($reportApi,"['GET']"))$fail[]='Private clip credentials must not be accepted through GET URLs';
if(!str_contains($reportApi,'recordReport'))$fail[]='Clip report endpoint missing service boundary';
$auth=file_get_contents($root.'/includes/auth.php')?:'';
foreach(['vp3_admin_can','vp3_require_admin_permission','network.manage','clips.moderate','sales.manage','projects.manage','theme_preference'] as $needle)if(!str_contains($auth,$needle))$fail[]="Admin authorization control missing: {$needle}";
$accountHeader=file_get_contents($root.'/includes/account-header.php')?:'';
foreach(['account-network.php','account-projects.php','account-proposals.php','account-assets.php','account-notifications.php'] as $needle)if(!str_contains($accountHeader,$needle))$fail[]="Customer operations navigation missing: {$needle}";
$adminHeader=file_get_contents($root.'/includes/admin-header.php')?:'';
foreach(['vp3_admin_can','admin-theme-switcher','admin/clips.php','admin/public-listings.php','admin/leads.php','admin/proposals.php','admin/projects.php'] as $needle)if(!str_contains($adminHeader,$needle))$fail[]="Admin navigation/theme control missing: {$needle}";
$css=file_get_contents($root.'/assets/css/app.css')?:'';
foreach(['data-admin-theme="light"','data-admin-theme="dark"','data-admin-theme="system"','--admin-bg','prefers-color-scheme:dark'] as $needle)if(!str_contains($css,$needle))$fail[]="Admin theme CSS missing: {$needle}";
$readme=file_get_contents($root.'/README.md')?:'';
if(!str_contains($readme,'GET /api/v1/products/{product_id}/latest-release'))$fail[]='Latest-release API method documentation mismatch';
if(!str_contains($readme,'POST /api/v1/clips/status'))$fail[]='Clip status API documentation mismatch';
if(!str_contains($readme,'20260711_sales_creative_operations_v1.sql'))$fail[]='Creative operations migration documentation missing';

$proposalService=file_get_contents($root.'/src/Sales/ProposalService.php')?:'';
foreach(['acceptance_email_must_match_account','lead_customer_mismatch','proposal_items_required','customer_not_available'] as $needle)if(!str_contains($proposalService,$needle))$fail[]="Proposal control missing: {$needle}";
$projectService=file_get_contents($root.'/src/Projects/ProjectService.php')?:'';
foreach(['task_project_mismatch','asset_project_mismatch','creator_customer_mismatch','show_customer_mismatch','task_dependency_incomplete','assertBelongs'] as $needle)if(!str_contains($projectService,$needle))$fail[]="Project control missing: {$needle}";
$operations=file_get_contents($root.'/includes/operations.php')?:'';
foreach(["function_exists('mb_strlen')","function_exists('mb_substr')",'vp3_public_https_url'] as $needle)if(!str_contains($operations,$needle)&&$needle!=='vp3_public_https_url')$fail[]="Operations portability control missing: {$needle}";
$brief=file_get_contents($root.'/account-project-brief.php')?:'';
if(!str_contains($brief,'This brief is locked or no longer available'))$fail[]='Locked brief handling missing';
$projectAdmin=file_get_contents($root.'/admin/project.php')?:'';
foreach(['milestone_project_mismatch','project_manager_not_available','task_update','milestone_update'] as $needle)if(!str_contains($projectAdmin,$needle))$fail[]="Admin project control missing: {$needle}";
$rootSchema=file_get_contents($root.'/database/schema.sql')?:'';
if(str_contains($rootSchema,"CREATE TABLE IF NOT EXISTS hosting_accounts (\n\n-- Extended controls"))$fail[]='Root schema contains a dangling hosting table declaration';
$sql='';foreach(glob($root.'/database/*.sql')?:[] as $file)$sql.=file_get_contents($file)?:'';
foreach(['FOREIGN KEY','utf8mb4','license_key_hash','installation_token_hash','api_nonces','audit_logs','payment_webhook_events','billing_subscriptions','support_ticket_messages','creators','shows','public_platform_listings','clip_publications','clip_rights_declarations','clip_moderation_reviews','clip_view_events','clip_engagement_events','clip_sync_events','theme_preference','leads','demo_requests','service_packages','proposals','proposal_acceptances','project_briefs','creative_projects','project_milestones','project_tasks','project_assets','project_approvals','production_plans','notifications'] as $needle)if(!str_contains($sql,$needle))$fail[]="Schema control missing: {$needle}";
foreach(['config.php','.env','var/logs/*','var/locks/*'] as $entry){$gitignore=file_get_contents($root.'/.gitignore')?:'';if(!str_contains($gitignore,$entry))$warn[]=".gitignore entry missing: {$entry}";}
if($warn)foreach($warn as $w)fwrite(STDERR,"WARN: {$w}\n");
if($fail){foreach($fail as $f)fwrite(STDERR,"FAIL: {$f}\n");exit(1);}echo "Static audit passed".($warn?' with warnings':'').".\n";
