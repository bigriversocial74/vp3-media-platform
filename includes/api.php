<?php
declare(strict_types=1);

function vp3_api_bootstrap(array $allowedMethods = ['POST']): array
{
    vp3_require_https_for_api();
    $method = vp3_method();
    if (!in_array($method, $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));
        vp3_json(['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Unsupported request method.']],405);
    }
    if (!vp3_rate_limit('license-api', (int)vp3_config('security.api_rate_limit_per_minute',60), 60)) {
        vp3_json(['ok'=>false,'error'=>['code'=>'rate_limited','message'=>'Too many requests.']],429);
    }
    $input = $method === 'GET' ? $_GET : vp3_json_input();
    $timestamp=(int)($input['timestamp']??0);$nonce=(string)($input['nonce']??'');
    if(abs(time()-$timestamp)>(int)vp3_config('security.api_clock_skew_seconds',300)){
        vp3_json(['ok'=>false,'error'=>['code'=>'request_expired','message'=>'Request timestamp is outside the allowed window.']],401);
    }
    if(!preg_match('/^[A-Za-z0-9_-]{16,128}$/',$nonce)){
        vp3_json(['ok'=>false,'error'=>['code'=>'invalid_nonce','message'=>'A valid nonce is required.']],422);
    }
    if(vp3_db_available()){
        try{vp3_db()->prepare('INSERT INTO api_nonces(nonce_hash,expires_at,created_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())')->execute([hash('sha256',$nonce)]);}catch(PDOException $e){if($e->getCode()==='23000')vp3_json(['ok'=>false,'error'=>['code'=>'replay_detected','message'=>'Nonce already used.']],409);throw $e;}
    }
    return $input;
}

function vp3_api_required(array $input,array $fields): void
{
    foreach($fields as $field){if(!isset($input[$field])||trim((string)$input[$field])==='')vp3_json(['ok'=>false,'error'=>['code'=>'validation_error','message'=>"Missing field: {$field}"]],422);}
}

function vp3_api_execute(callable $callback): never
{
    try{vp3_json(['ok'=>true,'data'=>$callback()]);}
    catch(RuntimeException $e){$code=preg_replace('/[^a-z0-9_]+/','_',strtolower($e->getMessage()))?:'request_failed';vp3_log('warning','License API request failed',['code'=>$code]);vp3_json(['ok'=>false,'error'=>['code'=>$code,'message'=>'The license request could not be completed.']],422);}
}
