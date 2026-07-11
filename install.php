<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$checks=[
 'PHP 8.2+'=>version_compare(PHP_VERSION,'8.2.0','>='),
 'PDO extension'=>extension_loaded('pdo'),
 'PDO MySQL extension'=>extension_loaded('pdo_mysql'),
 'OpenSSL extension'=>extension_loaded('openssl'),
 'JSON extension'=>extension_loaded('json'),
 'Config file present'=>is_file(VP3_ROOT.'/config.php'),
 'Application key replaced'=>(string)vp3_config('security.app_key')!=='replace-with-64-random-characters',
 'License pepper replaced'=>(string)vp3_config('security.license_pepper')!=='replace-with-a-separate-random-secret',
 'Log directory writable'=>is_writable(VP3_ROOT.'/var/logs'),
 'Lock directory writable'=>is_writable(VP3_ROOT.'/var/locks'),
 'Database connected'=>vp3_db_available(),
];
$ready=!in_array(false,$checks,true);$pageTitle='Environment Check';require VP3_ROOT.'/includes/header.php';?>
<section class="section dark"><div class="section-head"><span class="eyebrow">VP3 Foundation v1</span><h2>Environment readiness check.</h2><p>This page verifies configuration and runtime requirements. It does not expose credentials or run schema changes.</p></div></section><section class="section"><div class="table-wrap"><table class="table"><thead><tr><th>Requirement</th><th>Status</th></tr></thead><tbody><?php foreach($checks as $label=>$passed):?><tr><td><?=vp3_e($label)?></td><td><span class="badge <?=$passed?'active':'failed'?>"><?=$passed?'Ready':'Action required'?></span></td></tr><?php endforeach;?></tbody></table></div><div class="card"><h3><?=$ready?'Environment ready':'Complete the remaining requirements'?></h3><ol><li>Copy <code>config-example.php</code> to <code>config.php</code>.</li><li>Generate separate application and license secrets.</li><li>Create a MySQL database and import <code>database/schema.sql</code>.</li><li>Run <code>php create-admin.php</code> from the command line.</li><li>Delete or restrict <code>install.php</code> after deployment validation.</li></ol></div></section><?php require VP3_ROOT.'/includes/footer.php';?>
