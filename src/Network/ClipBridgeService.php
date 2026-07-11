<?php
declare(strict_types=1);
namespace VP3\Network;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class ClipBridgeService
{
    public function __construct(private PDO $db) {}

    public function context(array $auth): array
    {
        return [
            'contract_version'=>'1.0',
            'bridge_uuid'=>$auth['bridge_uuid'],
            'listing_uuid'=>$auth['listing_uuid'],
            'installation_uuid'=>$auth['installation_uuid'],
            'product_id'=>$auth['product_key'],
            'domain'=>$auth['activation_domain'],
            'source_creator_uuid'=>$auth['creator_uuid'],
            'source_show_uuid'=>$auth['show_uuid'],
            'max_clip_seconds'=>(int)\vp3_config('network.max_clip_seconds',180),
            'auto_publish_clips'=>(bool)$auth['auto_publish_clips'],
            'server_time'=>date(DATE_ATOM),
        ];
    }

    public function publish(array $auth,array $input): array
    {
        return $this->idempotent($auth,'clip.publish',function() use($auth,$input):array {
            return \vp3_transaction(function(PDO $db) use($auth,$input):array {
                $payload=$this->validatePayload($auth,$input);
                $existing=$db->prepare('SELECT * FROM clip_publications WHERE public_listing_id=? AND source_clip_uuid=? LIMIT 1 FOR UPDATE');
                $existing->execute([(int)$auth['public_listing_id'],$payload['source_clip_uuid']]);$row=$existing->fetch();
                if(is_array($row)){
                    $stored=new DateTimeImmutable((string)$row['source_updated_at']);$incoming=new DateTimeImmutable($payload['source_updated_at']);
                    if($stored>$incoming)throw new RuntimeException('stale_source_update');
                    if($stored==$incoming && !hash_equals((string)$row['content_hash'],$payload['content_hash']))throw new RuntimeException('source_revision_conflict');
                    if($stored==$incoming && hash_equals((string)$row['content_hash'],$payload['content_hash']))return $this->response($row);
                }
                $rightsStatus=!empty($input['rights_confirmed'])?'confirmed':'pending';
                $auto=(int)$auth['auto_publish_clips']===1&&$rightsStatus==='confirmed';
                $scheduled=$payload['scheduled_at'];$publishNow=$auto&&($scheduled===null||new DateTimeImmutable($scheduled)<=new DateTimeImmutable());
                $publicationStatus=$publishNow?'published':($auto?'scheduled':'pending');$moderationStatus=$auto?'approved':'pending';
                $publishedAt=$publishNow?date('Y-m-d H:i:s'):null;
                if(is_array($row)){
                    $stmt=$db->prepare('UPDATE clip_publications SET creator_id=?,show_id=?,source_creator_uuid=?,source_show_uuid=?,title=?,caption=?,media_type=?,source_media_url=?,poster_url=?,destination_url=?,duration_seconds=?,aspect_ratio=?,visibility=?,publication_status=?,moderation_status=?,rights_status=?,feed_eligible=?,scheduled_at=?,published_at=CASE WHEN ? IS NULL THEN published_at ELSE ? END,source_updated_at=?,last_synced_at=NOW(),content_hash=?,updated_at=NOW() WHERE id=?');
                    $stmt->execute([$payload['creator_id'],$payload['show_id'],$payload['source_creator_uuid'],$payload['source_show_uuid'],$payload['title'],$payload['caption'],$payload['media_type'],$payload['source_media_url'],$payload['poster_url'],$payload['destination_url'],$payload['duration_seconds'],$payload['aspect_ratio'],$payload['visibility'],$publicationStatus,$moderationStatus,$rightsStatus,$payload['feed_eligible'],$scheduled,$publishedAt,$publishedAt,$payload['source_updated_at'],$payload['content_hash'],(int)$row['id']]);
                    $clipId=(int)$row['id'];$uuid=(string)$row['publication_uuid'];$event='bridge_updated';
                }else{
                    $uuid=\vp3_uuid();
                    $stmt=$db->prepare('INSERT INTO clip_publications (publication_uuid,public_listing_id,license_id,creator_id,show_id,source_platform_uuid,source_creator_uuid,source_show_uuid,source_clip_uuid,title,caption,media_type,source_media_url,poster_url,destination_url,duration_seconds,aspect_ratio,visibility,publication_status,moderation_status,rights_status,feed_eligible,featured_rank,scheduled_at,published_at,source_updated_at,last_synced_at,content_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,NOW(),NOW())');
                    $stmt->execute([$uuid,(int)$auth['public_listing_id'],(int)$auth['license_id'],$payload['creator_id'],$payload['show_id'],$auth['listing_uuid'],$payload['source_creator_uuid'],$payload['source_show_uuid'],$payload['source_clip_uuid'],$payload['title'],$payload['caption'],$payload['media_type'],$payload['source_media_url'],$payload['poster_url'],$payload['destination_url'],$payload['duration_seconds'],$payload['aspect_ratio'],$payload['visibility'],$publicationStatus,$moderationStatus,$rightsStatus,$payload['feed_eligible'],$scheduled,$publishedAt,$payload['source_updated_at'],date('Y-m-d H:i:s'),$payload['content_hash']]);
                    $clipId=(int)$db->lastInsertId();$event='bridge_created';
                }
                if($rightsStatus==='confirmed'){
                    $owner=$this->slice(trim((string)($input['rights_owner_name']??$auth['display_name'])),190);
                    if($owner==='')throw new RuntimeException('rights_owner_required');
                    $expires=$this->dateOrNull((string)($input['rights_expires_at']??''));
                    $db->prepare('INSERT INTO clip_rights_declarations (clip_publication_id,rights_owner_name,territory,expires_at,declaration_version,confirmed_at,created_at) VALUES (?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE rights_owner_name=VALUES(rights_owner_name),territory=VALUES(territory),expires_at=VALUES(expires_at),declaration_version=VALUES(declaration_version),confirmed_at=NOW()')->execute([$clipId,$owner,'worldwide',$expires,'bridge-v1']);
                }
                $this->syncEvent($clipId,$event,'success',['request_uuid'=>$auth['request_uuid'],'publication_status'=>$publicationStatus]);
                \vp3_audit('api',null,'clip.'.$event,'clip_publication',$uuid,['bridge_uuid'=>$auth['bridge_uuid'],'source_clip_uuid'=>$payload['source_clip_uuid']]);
                $fresh=$db->prepare('SELECT * FROM clip_publications WHERE id=?');$fresh->execute([$clipId]);return $this->response($fresh->fetch()?:[]);
            });
        });
    }

    public function withdraw(array $auth,array $input): array
    {
        return $this->idempotent($auth,'clip.withdraw',function() use($auth,$input):array {
            return \vp3_transaction(function(PDO $db) use($auth,$input):array {
                $clip=$this->ownedClip($auth,(string)($input['source_clip_uuid']??''),true);
                $db->prepare("UPDATE clip_publications SET publication_status='withdrawn',feed_eligible=0,last_synced_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$clip['id']]);
                $this->syncEvent((int)$clip['id'],'bridge_withdrawn','success',['request_uuid'=>$auth['request_uuid']]);
                $clip['publication_status']='withdrawn';$clip['feed_eligible']=0;$clip['last_synced_at']=date('Y-m-d H:i:s');
                return $this->response($clip);
            });
        });
    }

    public function status(array $auth,array $input): array
    {
        return $this->response($this->ownedClip($auth,(string)($input['source_clip_uuid']??''),false));
    }

    public function analytics(array $auth,array $input): array
    {
        $clip=$this->ownedClip($auth,(string)($input['source_clip_uuid']??''),false);
        $views=$this->db->prepare('SELECT COUNT(*) total,COUNT(DISTINCT session_hash) unique_sessions,COALESCE(SUM(watch_seconds),0) watch_seconds,SUM(completed=1) completions FROM clip_view_events WHERE clip_publication_id=?');$views->execute([(int)$clip['id']]);$view=$views->fetch()?:[];
        $eng=$this->db->prepare('SELECT engagement_type,COUNT(*) total FROM clip_engagement_events WHERE clip_publication_id=? GROUP BY engagement_type');$eng->execute([(int)$clip['id']]);$types=[];foreach($eng->fetchAll() as $row)$types[(string)$row['engagement_type']]=(int)$row['total'];
        return ['contract_version'=>'1.0','publication_uuid'=>$clip['publication_uuid'],'source_clip_uuid'=>$clip['source_clip_uuid'],'views'=>(int)($view['total']??0),'unique_sessions'=>(int)($view['unique_sessions']??0),'watch_seconds'=>(int)($view['watch_seconds']??0),'completions'=>(int)($view['completions']??0),'engagement'=>$types,'server_time'=>date(DATE_ATOM)];
    }

    private function idempotent(array $auth,string $operation,callable $callback):array
    {
        $stmt=$this->db->prepare('SELECT request_hash,response_json FROM platform_bridge_requests WHERE bridge_credential_id=? AND request_uuid=? LIMIT 1');$stmt->execute([(int)$auth['id'],$auth['request_uuid']]);$row=$stmt->fetch();
        if(is_array($row)){
            if(!hash_equals((string)$row['request_hash'],(string)$auth['request_hash']))throw new RuntimeException('request_id_conflict');
            $decoded=json_decode((string)$row['response_json'],true);if(is_array($decoded))return $decoded;
        }
        $result=$callback();
        $this->db->prepare('INSERT INTO platform_bridge_requests (bridge_credential_id,request_uuid,operation,request_hash,response_status,response_json,created_at,updated_at) VALUES (?,?,?,?,200,?,NOW(),NOW())')->execute([(int)$auth['id'],$auth['request_uuid'],$operation,$auth['request_hash'],json_encode($result,JSON_THROW_ON_ERROR)]);
        return $result;
    }

    private function validatePayload(array $auth,array $input):array
    {
        $uuid=trim((string)($input['source_clip_uuid']??''));if(!$this->isUuid($uuid))throw new RuntimeException('invalid_source_clip_uuid');
        $title=trim((string)($input['title']??''));if($title===''||$this->length($title)>190)throw new RuntimeException('invalid_title');
        $duration=(int)($input['duration_seconds']??0);$max=(int)\vp3_config('network.max_clip_seconds',180);if($duration<1||$duration>$max)throw new RuntimeException('invalid_clip_duration');
        $media=$this->httpsUrl((string)($input['source_media_url']??''),true);$poster=$this->httpsUrl((string)($input['poster_url']??''),false);$destination=$this->httpsUrl((string)($input['destination_url']??''),true);
        $creatorUuid=trim((string)($input['source_creator_uuid']??$auth['creator_uuid']??''));$showUuid=trim((string)($input['source_show_uuid']??$auth['show_uuid']??''));
        $creatorId=$this->resolve('creators','creator_uuid',(int)$auth['customer_id'],$creatorUuid,$auth['creator_id']);$showId=$this->resolve('shows','show_uuid',(int)$auth['customer_id'],$showUuid,$auth['show_id']);
        $scheduled=$this->dateOrNull((string)($input['scheduled_at']??''));$updated=$this->dateOrNull((string)($input['source_updated_at']??''))??date('Y-m-d H:i:s');
        $aspect=(string)($input['aspect_ratio']??'9:16');if(!in_array($aspect,['9:16','1:1','4:5','16:9'],true))throw new RuntimeException('invalid_aspect_ratio');
        $visibility=(string)($input['visibility']??'public');if(!in_array($visibility,['public','unlisted'],true))throw new RuntimeException('invalid_visibility');
        $payload=['source_creator_uuid'=>$creatorUuid?:null,'source_show_uuid'=>$showUuid?:null,'source_clip_uuid'=>$uuid,'creator_id'=>$creatorId,'show_id'=>$showId,'title'=>$title,'caption'=>$this->slice(trim((string)($input['caption']??'')),2000),'media_type'=>in_array((string)($input['media_type']??'video'),['video','audio'],true)?(string)$input['media_type']:'video','source_media_url'=>$media,'poster_url'=>$poster,'destination_url'=>$destination,'duration_seconds'=>$duration,'aspect_ratio'=>$aspect,'visibility'=>$visibility,'feed_eligible'=>!empty($input['feed_eligible'])?1:0,'scheduled_at'=>$scheduled,'source_updated_at'=>$updated];
        $payload['content_hash']=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));return $payload;
    }

    private function response(array $clip):array{return ['contract_version'=>'1.0','publication_uuid'=>$clip['publication_uuid']??null,'source_clip_uuid'=>$clip['source_clip_uuid']??null,'publication_status'=>$clip['publication_status']??null,'moderation_status'=>$clip['moderation_status']??null,'rights_status'=>$clip['rights_status']??null,'feed_eligible'=>(bool)($clip['feed_eligible']??false),'scheduled_at'=>$clip['scheduled_at']??null,'published_at'=>$clip['published_at']??null,'last_synced_at'=>$clip['last_synced_at']??date('Y-m-d H:i:s'),'server_time'=>date(DATE_ATOM)];}
    private function ownedClip(array $auth,string $uuid,bool $lock):array{if(!$this->isUuid($uuid))throw new RuntimeException('invalid_source_clip_uuid');$stmt=$this->db->prepare('SELECT * FROM clip_publications WHERE public_listing_id=? AND source_clip_uuid=? LIMIT 1'.($lock?' FOR UPDATE':''));$stmt->execute([(int)$auth['public_listing_id'],$uuid]);$row=$stmt->fetch();if(!is_array($row))throw new RuntimeException('clip_not_found');return $row;}
    private function resolve(string $table,string $column,int $customerId,string $uuid,mixed $fallback):?int{if($uuid==='')return $fallback?(int)$fallback:null;if(!$this->isUuid($uuid))throw new RuntimeException('invalid_source_identity');$stmt=$this->db->prepare("SELECT id FROM {$table} WHERE {$column}=? AND customer_id=? LIMIT 1");$stmt->execute([$uuid,$customerId]);$id=(int)$stmt->fetchColumn();if($id<1)throw new RuntimeException('source_identity_not_owned');return $id;}
    private function httpsUrl(string $url,bool $required):?string{$url=trim($url);if($url===''){if($required)throw new RuntimeException('https_url_required');return null;}if(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https')throw new RuntimeException('https_url_required');return $this->slice($url,1000);}
    private function dateOrNull(string $value):?string{$value=trim($value);if($value==='')return null;try{return(new DateTimeImmutable($value))->format('Y-m-d H:i:s');}catch(Throwable){throw new RuntimeException('invalid_datetime');}}
    private function syncEvent(int $id,string $type,string $status,array $metadata=[]):void{$this->db->prepare('INSERT INTO clip_sync_events (clip_publication_id,event_type,event_status,metadata_json,created_at) VALUES (?,?,?,?,NOW())')->execute([$id,$type,$status,$metadata?json_encode(\vp3_redact($metadata),JSON_THROW_ON_ERROR):null]);}
    private function isUuid(string $value):bool{return(bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$value);}
    private function length(string $v):int{return function_exists('mb_strlen')?mb_strlen($v,'UTF-8'):strlen($v);}
    private function slice(string $v,int $n):string{return function_exists('mb_substr')?mb_substr($v,0,$n,'UTF-8'):substr($v,0,$n);}
}
