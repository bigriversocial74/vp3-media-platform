<?php
declare(strict_types=1);

use VP3\Network\BridgeCredentialService;

function vp3_bridge_headers(): array
{
    return [
        'bridge_id'=>(string)($_SERVER['HTTP_X_VP3_BRIDGE_ID']??''),
        'timestamp'=>(string)($_SERVER['HTTP_X_VP3_TIMESTAMP']??''),
        'nonce'=>(string)($_SERVER['HTTP_X_VP3_NONCE']??''),
        'signature'=>(string)($_SERVER['HTTP_X_VP3_SIGNATURE']??''),
        'request_id'=>(string)($_SERVER['HTTP_X_VP3_REQUEST_ID']??''),
    ];
}

function vp3_bridge_bootstrap(array $methods): array
{
    \vp3_require_https_for_api();$method=\vp3_method();
    if(!in_array($method,$methods,true)){header('Allow: '.implode(', ',$methods));\vp3_json(['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Unsupported request method.']],405);}
    $raw=file_get_contents('php://input');if(!is_string($raw))$raw='';
    $input=$raw===''?[]:json_decode($raw,true);if(!is_array($input))\vp3_json(['ok'=>false,'error'=>['code'=>'invalid_json','message'=>'Request body must be valid JSON.']],400);
    $headers=vp3_bridge_headers();$bucket='bridge:'.($headers['bridge_id']?:\vp3_client_ip());if(!\vp3_rate_limit($bucket,120,60))\vp3_json(['ok'=>false,'error'=>['code'=>'rate_limited','message'=>'Too many bridge requests.']],429);
    try{$auth=(new BridgeCredentialService(\vp3_db()))->authenticate($method,(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/'),$raw,$headers);}
    catch(RuntimeException $e){$code=preg_replace('/[^a-z0-9_]+/','_',strtolower($e->getMessage()))?:'bridge_request_failed';\vp3_log('warning','Bridge authentication failed',['code'=>$code]);\vp3_json(['ok'=>false,'error'=>['code'=>$code,'message'=>'The platform bridge request could not be authenticated.']],401);}
    return ['auth'=>$auth,'input'=>$input];
}

function vp3_bridge_execute(callable $callback): never
{
    try{\vp3_json(['ok'=>true,'data'=>$callback()]);}
    catch(RuntimeException $e){$code=preg_replace('/[^a-z0-9_]+/','_',strtolower($e->getMessage()))?:'bridge_request_failed';\vp3_log('warning','Bridge request failed',['code'=>$code]);\vp3_json(['ok'=>false,'error'=>['code'=>$code,'message'=>'The platform bridge request could not be completed.']],422);}
}
