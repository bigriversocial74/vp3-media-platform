<?php
declare(strict_types=1);

function vp3_viewer_api_bootstrap(array $methods=['GET']): array
{
    vp3_require_https_for_api();
    $method=vp3_method();
    if(!in_array($method,$methods,true)){
        header('Allow: '.implode(', ',$methods));
        vp3_json(['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Unsupported request method.']],405);
    }
    if(!vp3_rate_limit('viewer-api:'.vp3_client_ip(),180,60)){
        vp3_json(['ok'=>false,'error'=>['code'=>'rate_limited','message'=>'Too many viewer requests.']],429);
    }
    if($method!=='GET'){
        $provided=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
        if($provided===''||!hash_equals(vp3_csrf_token(),$provided)){
            vp3_json(['ok'=>false,'error'=>['code'=>'csrf_failed','message'=>'Security token validation failed.']],419);
        }
    }
    return $method==='GET'?$_GET:vp3_json_input();
}

function vp3_viewer_api_execute(callable $callback): never
{
    try{vp3_json(['ok'=>true,'data'=>$callback()]);}
    catch(RuntimeException $e){$code=preg_replace('/[^a-z0-9_]+/','_',strtolower($e->getMessage()))?:'viewer_request_failed';vp3_log('warning','Viewer API request failed',['code'=>$code]);vp3_json(['ok'=>false,'error'=>['code'=>$code,'message'=>'The viewer request could not be completed.']],422);}
}
