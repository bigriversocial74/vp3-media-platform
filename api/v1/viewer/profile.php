<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
require VP3_ROOT.'/includes/viewer_api.php';
$input=vp3_viewer_api_bootstrap(['POST']);$viewer=vp3_viewer();if(!$viewer)vp3_json(['ok'=>false,'error'=>['code'=>'viewer_auth_required','message'=>'Viewer sign in is required.']],401);
vp3_viewer_api_execute(function()use($input,$viewer):array{
 $name=trim((string)($input['display_name']??''));$handle=strtolower(trim((string)($input['handle']??'')));$bio=trim((string)($input['bio']??''));$visibility=(string)($input['profile_visibility']??'public');
 if($name===''||strlen($name)>150)throw new RuntimeException('invalid_display_name');
 if(!preg_match('/^[a-z0-9_]{3,30}$/',$handle))throw new RuntimeException('invalid_handle');
 if(strlen($bio)>500)throw new RuntimeException('bio_too_long');
 if(!in_array($visibility,['public','private'],true))throw new RuntimeException('invalid_profile_visibility');
 try{vp3_db()->prepare('UPDATE viewer_accounts SET display_name=?,handle=?,bio=?,profile_visibility=?,updated_at=NOW() WHERE id=?')->execute([$name,$handle,$bio?:null,$visibility,(int)$viewer['id']]);}
 catch(PDOException $e){if($e->getCode()==='23000')throw new RuntimeException('handle_already_in_use');throw$e;}
 return['updated'=>true,'handle'=>$handle];
});
