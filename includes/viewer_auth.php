<?php
declare(strict_types=1);

function vp3_viewer(): ?array
{
    static $cachedId = null;
    static $cachedViewer = null;
    $id = (int)($_SESSION['viewer_id'] ?? 0);
    if ($cachedId === $id) return $cachedViewer;
    $cachedId = $id;$cachedViewer = null;
    if ($id < 1 || !vp3_db_available()) return null;
    $stmt = vp3_db()->prepare('SELECT id,viewer_uuid,email,display_name,handle,avatar_url,bio,profile_visibility,status,email_verified_at,last_login_at FROM viewer_accounts WHERE id=? LIMIT 1');
    $stmt->execute([$id]);$row = $stmt->fetch();
    if (is_array($row) && $row['status'] === 'active' && $row['email_verified_at'] !== null) $cachedViewer = $row;
    return $cachedViewer;
}

function vp3_require_viewer(): array
{
    $viewer = vp3_viewer();
    if (!$viewer) {vp3_flash('error','Sign in with a viewer account to continue.');$return=basename((string)($_SERVER['REQUEST_URI']??'viewer.php'));vp3_redirect('viewer-login.php?return='.rawurlencode($return));}
    return $viewer;
}

function vp3_attempt_viewer_login(string $email,string $password,bool $remember=false): bool
{
    if(!vp3_db_available()||!vp3_rate_limit('viewer-login:'.strtolower($email),(int)vp3_config('security.login_attempt_limit',5),900))return false;
    $stmt=vp3_db()->prepare('SELECT id,password_hash,status,email_verified_at FROM viewer_accounts WHERE email=? LIMIT 1');$stmt->execute([strtolower(trim($email))]);$row=$stmt->fetch();
    if(!is_array($row)||$row['status']!=='active'||$row['email_verified_at']===null||!password_verify($password,(string)$row['password_hash']))return false;
    session_regenerate_id(true);$_SESSION['viewer_id']=(int)$row['id'];vp3_db()->prepare('UPDATE viewer_accounts SET last_login_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);vp3_viewer_claim_anonymous_activity((int)$row['id']);if($remember)vp3_viewer_issue_remember_token((int)$row['id']);return true;
}

function vp3_viewer_remember_cookie_name(): string{return'vp3_viewer_remember';}

function vp3_viewer_issue_remember_token(int $viewerId): void
{
    if(!vp3_db_available()||headers_sent())return;
    $tokenUuid=vp3_uuid();$secret=vp3_secure_token(36);$hash=vp3_hash_token($secret);$agentHash=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''));
    vp3_db()->prepare('INSERT INTO viewer_remember_tokens (token_uuid,viewer_id,token_hash,user_agent_hash,expires_at,created_at) VALUES (?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())')->execute([$tokenUuid,$viewerId,$hash,$agentHash]);
    setcookie(vp3_viewer_remember_cookie_name(),$tokenUuid.'.'.$secret,['expires'=>time()+2592000,'path'=>'/','secure'=>(bool)vp3_config('app.session_secure',true),'httponly'=>true,'samesite'=>'Lax']);
}

function vp3_viewer_restore_remembered_login(): void
{
    if(!empty($_SESSION['viewer_id'])||!vp3_db_available())return;$raw=trim((string)($_COOKIE[vp3_viewer_remember_cookie_name()]??''));
    if(!preg_match('/^([0-9a-f-]{36})\.([A-Za-z0-9_-]{40,100})$/i',$raw,$matches))return;
    $stmt=vp3_db()->prepare("SELECT vrt.id,vrt.viewer_id,vrt.token_hash,vrt.user_agent_hash,va.status,va.email_verified_at FROM viewer_remember_tokens vrt JOIN viewer_accounts va ON va.id=vrt.viewer_id WHERE vrt.token_uuid=? AND vrt.expires_at>NOW() LIMIT 1");$stmt->execute([$matches[1]]);$row=$stmt->fetch();$agentHash=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''));
    if(!is_array($row)||$row['status']!=='active'||$row['email_verified_at']===null||!hash_equals((string)$row['token_hash'],vp3_hash_token($matches[2]))||!hash_equals((string)$row['user_agent_hash'],$agentHash)){vp3_viewer_clear_remember_cookie();return;}
    vp3_db()->prepare('DELETE FROM viewer_remember_tokens WHERE id=?')->execute([(int)$row['id']]);session_regenerate_id(true);$_SESSION['viewer_id']=(int)$row['viewer_id'];vp3_viewer_claim_anonymous_activity((int)$row['viewer_id']);vp3_viewer_issue_remember_token((int)$row['viewer_id']);
}

function vp3_viewer_clear_remember_cookie(): void
{
    if(!headers_sent())setcookie(vp3_viewer_remember_cookie_name(),'',['expires'=>time()-3600,'path'=>'/','secure'=>(bool)vp3_config('app.session_secure',true),'httponly'=>true,'samesite'=>'Lax']);
}

function vp3_viewer_logout(): void
{
    $raw=trim((string)($_COOKIE[vp3_viewer_remember_cookie_name()]??''));if(vp3_db_available()&&preg_match('/^([0-9a-f-]{36})\./i',$raw,$matches))vp3_db()->prepare('DELETE FROM viewer_remember_tokens WHERE token_uuid=?')->execute([$matches[1]]);unset($_SESSION['viewer_id']);vp3_viewer_clear_remember_cookie();session_regenerate_id(true);
}

function vp3_viewer_safe_return(string $fallback='clips.php'): string
{
    $return=trim((string)($_GET['return']??$_POST['return']??''));if($return===''||str_contains($return,'://')||str_starts_with($return,'//')||str_contains($return,"\n")||str_contains($return,"\r"))return$fallback;return ltrim($return,'/');
}
