<?php
declare(strict_types=1);
namespace VP3\Network;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use VP3\Licensing\LicenseKey;

final class ClipSyndicationService
{
    public function __construct(private PDO $db) {}

    public function publish(array $input): array
    {
        return \vp3_transaction(function (PDO $db) use ($input): array {
            $context = $this->authorize($input, true);
            $payload = $this->validatePayload($input, $context);
            $existing = $db->prepare('SELECT id,publication_uuid,source_updated_at FROM clip_publications WHERE source_platform_uuid=? AND source_clip_uuid=? LIMIT 1 FOR UPDATE');
            $existing->execute([$payload['source_platform_uuid'],$payload['source_clip_uuid']]);
            $row = $existing->fetch();

            if (is_array($row) && new DateTimeImmutable((string)$row['source_updated_at']) > new DateTimeImmutable((string)$payload['source_updated_at'])) {
                throw new RuntimeException('stale_source_update');
            }

            $rightsStatus = !empty($input['rights_confirmed']) ? 'confirmed' : 'pending';
            $autoPublish = (int)$context['auto_publish_clips'] === 1 && $rightsStatus === 'confirmed';
            $scheduledAt = $payload['scheduled_at'];
            $publishNow = $autoPublish && ($scheduledAt === null || new DateTimeImmutable($scheduledAt) <= new DateTimeImmutable());
            $publicationStatus = $publishNow ? 'published' : ($autoPublish ? 'scheduled' : 'pending');
            $moderationStatus = $autoPublish ? 'approved' : 'pending';
            $publishedAt = $publishNow ? date('Y-m-d H:i:s') : null;
            $contentHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

            if (is_array($row)) {
                $stmt = $db->prepare(
                    'UPDATE clip_publications SET creator_id=?,show_id=?,title=?,caption=?,media_type=?,source_media_url=?,poster_url=?,destination_url=?,duration_seconds=?,aspect_ratio=?,visibility=?,publication_status=?,moderation_status=?,rights_status=?,feed_eligible=?,scheduled_at=?,published_at=COALESCE(?,published_at),source_updated_at=?,last_synced_at=NOW(),content_hash=?,updated_at=NOW() WHERE id=?'
                );
                $stmt->execute([
                    $payload['creator_id'],$payload['show_id'],$payload['title'],$payload['caption'],$payload['media_type'],
                    $payload['source_media_url'],$payload['poster_url'],$payload['destination_url'],$payload['duration_seconds'],
                    $payload['aspect_ratio'],$payload['visibility'],$publicationStatus,$moderationStatus,$rightsStatus,
                    $payload['feed_eligible'],$scheduledAt,$publishedAt,$payload['source_updated_at'],$contentHash,(int)$row['id'],
                ]);
                $clipId = (int)$row['id'];
                $uuid = (string)$row['publication_uuid'];
                $event = 'updated';
            } else {
                $uuid = \vp3_uuid();
                $stmt = $db->prepare(
                    'INSERT INTO clip_publications (publication_uuid,public_listing_id,license_id,creator_id,show_id,source_platform_uuid,source_creator_uuid,source_show_uuid,source_clip_uuid,title,caption,media_type,source_media_url,poster_url,destination_url,duration_seconds,aspect_ratio,visibility,publication_status,moderation_status,rights_status,feed_eligible,featured_rank,scheduled_at,published_at,source_updated_at,last_synced_at,content_hash,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,NOW(),NOW())'
                );
                $stmt->execute([
                    $uuid,(int)$context['listing_id'],(int)$context['license_id'],$payload['creator_id'],$payload['show_id'],
                    $payload['source_platform_uuid'],$payload['source_creator_uuid'],$payload['source_show_uuid'],$payload['source_clip_uuid'],
                    $payload['title'],$payload['caption'],$payload['media_type'],$payload['source_media_url'],$payload['poster_url'],
                    $payload['destination_url'],$payload['duration_seconds'],$payload['aspect_ratio'],$payload['visibility'],
                    $publicationStatus,$moderationStatus,$rightsStatus,$payload['feed_eligible'],$scheduledAt,$publishedAt,
                    $payload['source_updated_at'],date('Y-m-d H:i:s'),$contentHash,
                ]);
                $clipId = (int)$db->lastInsertId();
                $event = 'created';
            }

            if ($rightsStatus === 'confirmed') {
                $rights = $db->prepare('INSERT INTO clip_rights_declarations (clip_publication_id,rights_owner_name,territory,expires_at,declaration_version,confirmed_at,created_at) VALUES (?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE rights_owner_name=VALUES(rights_owner_name),territory=VALUES(territory),expires_at=VALUES(expires_at),declaration_version=VALUES(declaration_version),confirmed_at=NOW()');
                $rights->execute([$clipId,(string)($input['rights_owner_name'] ?? $context['display_name']),'worldwide',($input['rights_expires_at'] ?? '') ?: null,'v1']);
            }
            $this->syncEvent($clipId, $event, 'success', ['publication_status'=>$publicationStatus,'moderation_status'=>$moderationStatus]);
            \vp3_audit('api', null, 'clip.' . $event, 'clip_publication', $uuid, ['source_platform_uuid'=>$payload['source_platform_uuid'],'source_clip_uuid'=>$payload['source_clip_uuid']]);
            return ['publication_uuid'=>$uuid,'publication_status'=>$publicationStatus,'moderation_status'=>$moderationStatus,'rights_status'=>$rightsStatus,'published_at'=>$publishedAt,'scheduled_at'=>$scheduledAt];
        });
    }

    public function unpublish(array $input): array
    {
        return \vp3_transaction(function (PDO $db) use ($input): array {
            $context = $this->authorize($input, true);
            $clip = $this->ownedClip($context, (string)($input['source_clip_uuid'] ?? ''), true);
            $db->prepare("UPDATE clip_publications SET publication_status='withdrawn',feed_eligible=0,updated_at=NOW(),last_synced_at=NOW() WHERE id=?")->execute([(int)$clip['id']]);
            $this->syncEvent((int)$clip['id'], 'withdrawn', 'success');
            return ['publication_uuid'=>$clip['publication_uuid'],'publication_status'=>'withdrawn'];
        });
    }

    public function status(array $input): array
    {
        $context = $this->authorize($input);
        $clip = $this->ownedClip($context, (string)($input['source_clip_uuid'] ?? ''));
        return [
            'publication_uuid'=>$clip['publication_uuid'],
            'publication_status'=>$clip['publication_status'],
            'moderation_status'=>$clip['moderation_status'],
            'rights_status'=>$clip['rights_status'],
            'feed_eligible'=>(bool)$clip['feed_eligible'],
            'scheduled_at'=>$clip['scheduled_at'],
            'published_at'=>$clip['published_at'],
            'last_synced_at'=>$clip['last_synced_at'],
        ];
    }

    public function analytics(array $input): array
    {
        $context = $this->authorize($input);
        $clip = $this->ownedClip($context, (string)($input['source_clip_uuid'] ?? ''));
        $views = $this->db->prepare('SELECT COUNT(*) total,COUNT(DISTINCT session_hash) unique_sessions FROM clip_view_events WHERE clip_publication_id=?');
        $views->execute([(int)$clip['id']]);
        $viewData = $views->fetch() ?: ['total'=>0,'unique_sessions'=>0];
        $engagement = $this->db->prepare('SELECT engagement_type,COUNT(*) total FROM clip_engagement_events WHERE clip_publication_id=? GROUP BY engagement_type');
        $engagement->execute([(int)$clip['id']]);
        $byType = [];
        foreach ($engagement->fetchAll() as $row) {
            $byType[(string)$row['engagement_type']] = (int)$row['total'];
        }
        return ['publication_uuid'=>$clip['publication_uuid'],'views'=>(int)$viewData['total'],'unique_sessions'=>(int)$viewData['unique_sessions'],'engagement'=>$byType];
    }

    public function recordView(string $publicationUuid, string $sessionId, array $metadata = []): array
    {
        $stmt = $this->db->prepare("SELECT id FROM clip_publications WHERE publication_uuid=? AND publication_status='published' AND feed_eligible=1 LIMIT 1");
        $stmt->execute([$publicationUuid]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('clip_not_found');
        $this->db->prepare('INSERT INTO clip_view_events (clip_publication_id,session_hash,viewer_ip_hash,user_agent_hash,watch_seconds,completed,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([
            $id,hash('sha256',$sessionId),hash('sha256',\vp3_client_ip()),hash('sha256',(string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
            max(0,(int)($metadata['watch_seconds'] ?? 0)),!empty($metadata['completed'])?1:0,
        ]);
        return ['recorded'=>true];
    }

    public function recordReport(string $publicationUuid, string $sessionId, string $reason, string $details = ''): array
    {
        $allowed = ['copyright','harassment','adult_content','violence','spam','misleading','other'];
        if (!in_array($reason, $allowed, true)) throw new RuntimeException('invalid_report_reason');
        $stmt = $this->db->prepare("SELECT id FROM clip_publications WHERE publication_uuid=? AND publication_status='published' LIMIT 1");
        $stmt->execute([$publicationUuid]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('clip_not_found');
        $sessionHash = hash('sha256', $sessionId);
        $recent = $this->db->prepare("SELECT COUNT(*) FROM clip_reports WHERE clip_publication_id=? AND session_hash=? AND created_at >= (NOW() - INTERVAL 24 HOUR)");
        $recent->execute([$id,$sessionHash]);
        if ((int)$recent->fetchColumn() > 0) throw new RuntimeException('report_already_received');
        $this->db->prepare("INSERT INTO clip_reports (clip_publication_id,session_hash,reason,details,report_status,created_at,updated_at) VALUES (?,?,?,?,'open',NOW(),NOW())")->execute([
            $id,$sessionHash,$reason,$this->textSlice(trim($details),1000),
        ]);
        return ['recorded'=>true,'report_status'=>'open'];
    }

    public function recordEngagement(string $publicationUuid, string $sessionId, string $type): array
    {
        if (!in_array($type, ['like','save','share','open_destination'], true)) throw new RuntimeException('invalid_engagement_type');
        $stmt = $this->db->prepare("SELECT id FROM clip_publications WHERE publication_uuid=? AND publication_status='published' AND feed_eligible=1 LIMIT 1");
        $stmt->execute([$publicationUuid]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('clip_not_found');
        $this->db->prepare('INSERT INTO clip_engagement_events (clip_publication_id,session_hash,engagement_type,created_at) VALUES (?,?,?,NOW())')->execute([$id,hash('sha256',$sessionId),$type]);
        return ['recorded'=>true,'engagement_type'=>$type];
    }

    private function authorize(array $input, bool $forUpdate = false): array
    {
        foreach (['license_key','product_id','domain','installation_uuid','installation_token','source_platform_uuid'] as $field) {
            if (trim((string)($input[$field] ?? '')) === '') throw new RuntimeException($field . '_required');
        }
        $sql = "SELECT ppl.id listing_id,ppl.listing_uuid,ppl.customer_id,ppl.creator_id,ppl.show_id,ppl.display_name,ppl.auto_publish_clips,
                       ppl.verification_status,ppl.listing_status,l.id license_id,l.status license_status,l.expires_at,p.product_id,
                       la.id activation_id,la.status activation_status,la.domain activation_domain,la.installation_token_hash
                FROM public_platform_listings ppl
                JOIN licenses l ON l.id=ppl.license_id
                JOIN products p ON p.id=ppl.product_id
                JOIN license_activations la ON la.license_id=l.id AND la.installation_uuid=?
                WHERE ppl.listing_uuid=? AND p.product_id=? AND l.license_key_hash=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(string)$input['installation_uuid'],(string)$input['source_platform_uuid'],(string)$input['product_id'],LicenseKey::hash((string)$input['license_key'])]);
        $context = $stmt->fetch();
        if (!is_array($context)) throw new RuntimeException('platform_not_authorized');
        if (!hash_equals((string)$context['installation_token_hash'], \vp3_hash_token((string)$input['installation_token']))) throw new RuntimeException('installation_not_authorized');
        if ($context['activation_status'] !== 'active') throw new RuntimeException('activation_inactive');
        if (!hash_equals((string)$context['activation_domain'], \vp3_normalize_domain((string)$input['domain']))) throw new RuntimeException('installation_domain_mismatch');
        if (!in_array($context['license_status'], ['active','development'], true)) throw new RuntimeException('license_' . $context['license_status']);
        if ($context['expires_at'] !== null && new DateTimeImmutable((string)$context['expires_at']) < new DateTimeImmutable('today')) throw new RuntimeException('license_expired');
        if ($context['verification_status'] !== 'verified' || $context['listing_status'] !== 'published') throw new RuntimeException('public_platform_not_verified');
        return $context;
    }

    private function validatePayload(array $input, array $context): array
    {
        $sourceClipUuid = trim((string)($input['source_clip_uuid'] ?? ''));
        if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceClipUuid)) throw new RuntimeException('invalid_source_clip_uuid');
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '' || $this->textLength($title) > 190) throw new RuntimeException('invalid_title');
        $duration = (int)($input['duration_seconds'] ?? 0);
        $max = (int)\vp3_config('network.max_clip_seconds', 180);
        if ($duration < 1 || $duration > $max) throw new RuntimeException('invalid_clip_duration');
        $mediaUrl = $this->httpsUrl((string)($input['source_media_url'] ?? ''), true);
        $posterUrl = $this->httpsUrl((string)($input['poster_url'] ?? ''), false);
        $destinationUrl = $this->httpsUrl((string)($input['destination_url'] ?? ''), true);
        $creatorId = $this->resolveCreator((int)$context['customer_id'], (string)($input['source_creator_uuid'] ?? ''), $context['creator_id']);
        $showId = $this->resolveShow((int)$context['customer_id'], (string)($input['source_show_uuid'] ?? ''), $context['show_id']);
        $scheduledAt = trim((string)($input['scheduled_at'] ?? ''));
        if ($scheduledAt !== '') {
            try { $scheduledAt = (new DateTimeImmutable($scheduledAt))->format('Y-m-d H:i:s'); } catch (\Throwable) { throw new RuntimeException('invalid_scheduled_at'); }
        } else {
            $scheduledAt = null;
        }
        $sourceUpdatedAt = trim((string)($input['source_updated_at'] ?? ''));
        try { $sourceUpdatedAt = $sourceUpdatedAt !== '' ? (new DateTimeImmutable($sourceUpdatedAt))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'); } catch (\Throwable) { throw new RuntimeException('invalid_source_updated_at'); }
        $aspect = (string)($input['aspect_ratio'] ?? '9:16');
        if (!in_array($aspect, ['9:16','1:1','4:5','16:9'], true)) throw new RuntimeException('invalid_aspect_ratio');
        return [
            'source_platform_uuid'=>(string)$input['source_platform_uuid'],
            'source_creator_uuid'=>(string)($input['source_creator_uuid'] ?? ''),
            'source_show_uuid'=>(string)($input['source_show_uuid'] ?? ''),
            'source_clip_uuid'=>$sourceClipUuid,
            'creator_id'=>$creatorId,
            'show_id'=>$showId,
            'title'=>$title,
            'caption'=>$this->textSlice(trim((string)($input['caption'] ?? '')), 2000),
            'media_type'=>in_array((string)($input['media_type'] ?? 'video'),['video','audio'],true)?(string)($input['media_type'] ?? 'video'):'video',
            'source_media_url'=>$mediaUrl,
            'poster_url'=>$posterUrl,
            'destination_url'=>$destinationUrl,
            'duration_seconds'=>$duration,
            'aspect_ratio'=>$aspect,
            'visibility'=>'public',
            'feed_eligible'=>!empty($input['feed_eligible'])?1:0,
            'scheduled_at'=>$scheduledAt,
            'source_updated_at'=>$sourceUpdatedAt,
        ];
    }

    private function resolveCreator(int $customerId, string $uuid, mixed $fallback): ?int
    {
        if ($uuid === '') return $fallback ? (int)$fallback : null;
        $stmt = $this->db->prepare('SELECT id FROM creators WHERE creator_uuid=? AND customer_id=? LIMIT 1');
        $stmt->execute([$uuid,$customerId]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('creator_not_owned_by_platform');
        return $id;
    }

    private function resolveShow(int $customerId, string $uuid, mixed $fallback): ?int
    {
        if ($uuid === '') return $fallback ? (int)$fallback : null;
        $stmt = $this->db->prepare('SELECT id FROM shows WHERE show_uuid=? AND customer_id=? LIMIT 1');
        $stmt->execute([$uuid,$customerId]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('show_not_owned_by_platform');
        return $id;
    }

    private function httpsUrl(string $url, bool $required): ?string
    {
        $url = trim($url);
        if ($url === '') {
            if ($required) throw new RuntimeException('https_url_required');
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https') throw new RuntimeException('https_url_required');
        return $this->textSlice($url, 1000);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function textSlice(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }

    private function ownedClip(array $context, string $sourceClipUuid, bool $forUpdate = false): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceClipUuid)) throw new RuntimeException('invalid_source_clip_uuid');
        $stmt = $this->db->prepare('SELECT * FROM clip_publications WHERE public_listing_id=? AND source_clip_uuid=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([(int)$context['listing_id'],$sourceClipUuid]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw new RuntimeException('clip_not_found');
        return $row;
    }

    private function syncEvent(int $clipId, string $eventType, string $status, array $metadata = []): void
    {
        $this->db->prepare('INSERT INTO clip_sync_events (clip_publication_id,event_type,event_status,metadata_json,created_at) VALUES (?,?,?,?,NOW())')->execute([$clipId,$eventType,$status,$metadata ? json_encode(\vp3_redact($metadata),JSON_THROW_ON_ERROR) : null]);
    }
}
