<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=[];$warn=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){$path=$file->getPathname();$rel=substr($path,strlen($root)+1);if(str_starts_with($rel,'.git/')||str_starts_with($rel,'var/'))continue;$content=file_get_contents($path)?:'';
 if($file->getExtension()==='php'){
  if(!str_contains($content,'declare(strict_types=1)'))$fail[]="Missing strict types: {$rel}";
  if(preg_match('/(?:query|prepare)\s*\([^)]*\$_(?:GET|POST|REQUEST)/s',$content))$fail[]="Possible raw input SQL: {$rel}";
  if(preg_match('/\b(?:exec|shell_exec|system|passthru|proc_open)\s*\(/',$content))$fail[]="Shell execution found: {$rel}";
 }
 if($rel!=='tests/static-audit.php'&&preg_match('/sk_live_[A-Za-z0-9]+|AKIA[0-9A-Z]{16}|BEGIN PRIVATE KEY/',$content))$fail[]="Possible secret committed: {$rel}";
}
$licenseService=file_get_contents($root.'/src/Licensing/LicenseService.php')?:'';
if(!str_contains($licenseService,'SELECT id,status,domain FROM license_activations'))$fail[]='Activation lookup must include the bound domain';
$auth=file_get_contents($root.'/includes/auth.php')?:'';
foreach(['vp3_admin_can','vp3_require_admin_permission'] as $needle)if(!str_contains($auth,$needle))$fail[]="Admin authorization control missing: {$needle}";
$adminHeader=file_get_contents($root.'/includes/admin-header.php')?:'';
if(!str_contains($adminHeader,'vp3_admin_can'))$fail[]='Admin navigation is not permission-aware';
$readme=file_get_contents($root.'/README.md')?:'';
if(!str_contains($readme,'GET /api/v1/products/{product_id}/latest-release'))$fail[]='Latest-release API method documentation mismatch';
$schema=(file_get_contents($root.'/database/schema.sql')?:'').(file_get_contents($root.'/database/schema-operations.sql')?:'').(file_get_contents($root.'/database/schema-security.sql')?:'');
foreach(['FOREIGN KEY','utf8mb4','license_key_hash','installation_token_hash','api_nonces','audit_logs','payment_webhook_events','billing_subscriptions','support_ticket_messages'] as $needle)if(!str_contains($schema,$needle))$fail[]="Schema control missing: {$needle}";
foreach(['config.php','.env','var/logs/*','var/locks/*'] as $entry){$gitignore=file_get_contents($root.'/.gitignore')?:'';if(!str_contains($gitignore,$entry))$warn[]=".gitignore entry missing: {$entry}";}
if($warn)foreach($warn as $w)fwrite(STDERR,"WARN: {$w}\n");if($fail){foreach($fail as $f)fwrite(STDERR,"FAIL: {$f}\n");exit(1);}echo "Static audit passed".($warn?' with warnings':'').".\n";
