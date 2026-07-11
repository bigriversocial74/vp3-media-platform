<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
vp3_require_admin_permission('dashboard.view');
$pageTitle = 'Admin Dashboard';
require VP3_ROOT . '/includes/admin-header.php';
$tables = [
    'leads'=>'Leads','demo_requests'=>'Demo requests','proposals'=>'Proposals','creative_projects'=>'Projects',
    'project_tasks'=>'Project tasks','project_approvals'=>'Approvals','customers'=>'Customers','orders'=>'Orders',
    'licenses'=>'Licenses','hosting_accounts'=>'Hosting','support_tickets'=>'Tickets','clip_publications'=>'Clips',
];
$counts=[];if(vp3_db_available()){foreach($tables as $table=>$label){try{$counts[$table]=(int)vp3_db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();}catch(Throwable){$counts[$table]=0;}}}
$dueFollowups=$atRisk=$pendingApprovals=0;
if(vp3_db_available()){
    try{$dueFollowups=(int)vp3_db()->query("SELECT COUNT(*) FROM leads WHERE next_follow_up_at IS NOT NULL AND next_follow_up_at<=NOW() AND stage NOT IN ('won','lost','archived')")->fetchColumn();}catch(Throwable){}
    try{$atRisk=(int)vp3_db()->query("SELECT COUNT(*) FROM creative_projects WHERE health_status IN ('at_risk','blocked') AND status='active'")->fetchColumn();}catch(Throwable){}
    try{$pendingApprovals=(int)vp3_db()->query("SELECT COUNT(*) FROM project_approvals WHERE status='pending'")->fetchColumn();}catch(Throwable){}
}
?>
<div class="page-head"><div><span class="eyebrow">VP3 operating system</span><h1>Operations dashboard</h1><p class="helper">Sales, creative delivery, licensing, hosting, customer operations, network discovery, and syndicated clip distribution.</p></div></div>
<div class="priority-strip"><article><span>Follow-ups due</span><strong><?=$dueFollowups?></strong><a href="leads.php">Open pipeline</a></article><article><span>Projects at risk</span><strong><?=$atRisk?></strong><a href="projects.php">Review projects</a></article><article><span>Approvals waiting</span><strong><?=$pendingApprovals?></strong><a href="projects.php">Open delivery</a></article></div>
<div class="stats"><?php foreach($tables as $table=>$label):?><div class="stat"><span><?=vp3_e($label)?></span><strong><?=vp3_e((string)($counts[$table]??0))?></strong></div><?php endforeach;?></div>
<section class="section" style="padding-left:0;padding-right:0"><div class="grid three"><article class="card"><h3>Sales pipeline</h3><p>Qualify inbound requests, schedule discovery, track follow-up, and move leads into proposal and project stages.</p><a class="button small" href="leads.php">Manage pipeline</a></article><article class="card"><h3>Creative operations</h3><p>Run briefs, proposals, projects, milestones, tasks, approvals, assets, and launch-readiness workflows.</p><a class="button small" href="projects.php">Manage projects</a></article><article class="card"><h3>Service catalog</h3><p>Configure story development, platform production, launch, and ongoing management packages.</p><a class="button small" href="service-packages.php">Manage packages</a></article><article class="card"><h3>VP3 Network</h3><p>Manage public creator profiles, shows, verified destinations, feature placement, and network visibility.</p><a class="button small" href="creators.php">Manage network</a></article><article class="card"><h3>Clip moderation</h3><p>Review source-owned clip publications, rights declarations, reports, feed eligibility, and sync status.</p><a class="button small" href="clips.php">Review clips</a></article><article class="card"><h3>Licensing and hosting</h3><p>Operate licenses, domains, activations, hosted installs, releases, and customer support.</p><a class="button small" href="licenses.php">Manage licenses</a></article></div></section>
<?php require VP3_ROOT . '/includes/admin-footer.php'; ?>
