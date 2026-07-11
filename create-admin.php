<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
[$script,$name,$email,$password,$role]=$argv+['','','','','owner'];
if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||!in_array($role,['owner','super_admin','operations','support','billing'],true)){
    fwrite(STDERR,"Usage: php create-admin.php \"Name\" email@example.com \"12+ char password\" [owner|super_admin|operations|support|billing]\n");exit(1);
}
$stmt=vp3_db()->prepare("INSERT INTO admins(name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?, 'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role=VALUES(role),status='active',updated_at=NOW()");
$stmt->execute([$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT),$role]);
echo "Administrator created or updated.\n";
