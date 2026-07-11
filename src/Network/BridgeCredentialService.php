<?php
declare(strict_types=1);
namespace VP3\Network;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class BridgeCredentialService
{
    private const CLOCK_SKEW = 300;
    private const DEFAULT_SCOPES = ['clips:context','clips:publish','clips:read','clips:withdraw'];

    public function __construct(private PDO $db) {}

    public function issue(int $listingId, int $activationId, int $adminId, ?string $expiresAt = null): array
    {
        $context = $this->issueContext($listingId, $activationId, true);
        $secret = \vp3_secure_token(48);
        [$ciphertext, $nonce, $tag] = $this->encrypt($secret);
        $bridgeUuid = \vp3_uuid();
        $this->db->prepare("UPDATE platform_bridge_credentials SET status='rotated',rotated_at=NOW(),updated_at=NOW() WHERE public_listing_id=? AND license_activation_id=? AND status='active'")
            ->execute([$listingId,$activationId]);
        $stmt = $this->db->prepare('INSERT INTO platform_bridge_credentials (bridge_uuid,public_listing_id,license_activation_id,secret_ciphertext,secret_nonce,secret_tag,status,scopes_json,issued_by,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,\'active\',?,?,?,?,NOW())');
        $stmt->execute([$bridgeUuid,$listingId,$activationId,$ciphertext,$nonce,$tag,json_encode(self::DEFAULT_SCOPES,JSON_THROW_ON_ERROR),$adminId,$this->normalizeDate($expiresAt),date('Y-m-d H:i:s')]);
        \vp3_audit('admin',$adminId,'platform_bridge.issued','public_platform',(string)$context['listing_uuid'],['bridge_uuid'=>$bridgeUuid,'installation_uuid'=>$context['installation_uuid']]);
        return [
            'bridge_uuid'=>$bridgeUuid,
            'bridge_secret'=>$secret,
            'contract_version'=>'1.0',
            'listing_uuid'=>$context['listing_uuid'],
            'installation_uuid'=>$context['installation_uuid'],
            'product_id'=>$context['product_key'],
            'domain'=>$context['activation_domain'],
            'source_creator_uuid'=>$context['creator_uuid'],
            'source_show_uuid'=>$context['show_uuid'],
            'expires_at'=>$this->normalizeDate($expiresAt),
        ];
    }

    public function revoke(int $credentialId, int $listingId, int $adminId): void
    {
        $stmt = $this->db->prepare("UPDATE platform_bridge_credentials SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE id=? AND public_listing_id=? AND status IN ('active','rotated')");
        $stmt->execute([$credentialId,$listingId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('bridge_credential_not_found');
        \vp3_audit('admin',$adminId,'platform_bridge.revoked','public_platform',(string)$listingId,['credential_id'=>$credentialId]);
    }

    public function authenticate(string $method, string $path, string $rawBody, array $headers): array
    {
        $bridgeUuid = trim((string)($headers['bridge_id'] ?? ''));
        $timestamp = trim((string)($headers['timestamp'] ?? ''));
        $nonce = trim((string)($headers['nonce'] ?? ''));
        $signature = strtolower(trim((string)($headers['signature'] ?? '')));
        $requestId = trim((string)($headers['request_id'] ?? ''));
        if (!$this->isUuid($bridgeUuid) || !$this->isUuid($requestId)) throw new RuntimeException('invalid_bridge_identity');
        if (!ctype_digit($timestamp) || abs(time()-(int)$timestamp) > self::CLOCK_SKEW) throw new RuntimeException('bridge_timestamp_invalid');
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/',$nonce) || !preg_match('/^[a-f0-9]{64}$/',$signature)) throw new RuntimeException('bridge_signature_invalid');

        $sql = "SELECT pbc.*,pbc.expires_at bridge_expires_at,ppl.listing_uuid,ppl.customer_id,ppl.creator_id,ppl.show_id,ppl.display_name,ppl.public_domain,ppl.auto_publish_clips,ppl.verification_status,ppl.listing_status,
                       l.id license_id,l.status license_status,l.expires_at license_expires_at,p.product_id product_key,
                       la.installation_uuid,la.domain activation_domain,la.status activation_status,
                       c.creator_uuid,s.show_uuid
                FROM platform_bridge_credentials pbc
                JOIN public_platform_listings ppl ON ppl.id=pbc.public_listing_id
                JOIN licenses l ON l.id=ppl.license_id
                JOIN products p ON p.id=ppl.product_id
                JOIN license_activations la ON la.id=pbc.license_activation_id AND la.license_id=l.id
                LEFT JOIN creators c ON c.id=ppl.creator_id
                LEFT JOIN shows s ON s.id=ppl.show_id
                WHERE pbc.bridge_uuid=? LIMIT 1";
        $stmt = $this->db->prepare($sql);$stmt->execute([$bridgeUuid]);$context=$stmt->fetch();
        if (!is_array($context)) throw new RuntimeException('bridge_not_authorized');
        if ($context['status'] !== 'active') throw new RuntimeException('bridge_' . $context['status']);
        if ($context['bridge_expires_at'] !== null && new DateTimeImmutable((string)$context['bridge_expires_at']) < new DateTimeImmutable()) throw new RuntimeException('bridge_expired');
        if ($context['activation_status'] !== 'active') throw new RuntimeException('activation_inactive');
        if (!in_array($context['license_status'],['active','development'],true)) throw new RuntimeException('license_' . $context['license_status']);
        if ($context['license_status'] !== 'development' && $context['license_expires_at'] !== null && new DateTimeImmutable((string)$context['license_expires_at']) < new DateTimeImmutable('today')) throw new RuntimeException('license_expired');
        if ($context['verification_status'] !== 'verified' || $context['listing_status'] !== 'published') throw new RuntimeException('public_platform_not_verified');

        $secret = $this->decrypt((string)$context['secret_ciphertext'],(string)$context['secret_nonce'],(string)$context['secret_tag']);
        $canonical = strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256',$rawBody);
        $expected = hash_hmac('sha256',$canonical,$secret);
        if (!hash_equals($expected,$signature)) throw new RuntimeException('bridge_signature_invalid');

        $nonceHash = hash('sha256',$nonce);
        try {
            $this->db->prepare('DELETE FROM platform_bridge_nonces WHERE expires_at<NOW()')->execute();
            $this->db->prepare('INSERT INTO platform_bridge_nonces (bridge_credential_id,nonce_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW())')->execute([(int)$context['id'],$nonceHash]);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()),'duplicate')) throw new RuntimeException('bridge_replay_rejected');
            throw $e;
        }
        $this->db->prepare('UPDATE platform_bridge_credentials SET last_used_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$context['id']]);
        $context['request_uuid']=$requestId;
        $context['request_hash']=hash('sha256',$rawBody);
        $context['scopes']=json_decode((string)$context['scopes_json'],true)?:[];
        return $context;
    }

    public function requireScope(array $context, string $scope): void
    {
        if (!in_array($scope,(array)($context['scopes']??[]),true)) throw new RuntimeException('bridge_scope_denied');
    }

    private function issueContext(int $listingId,int $activationId,bool $lock=false): array
    {
        $stmt=$this->db->prepare("SELECT ppl.listing_uuid,ppl.license_id,ppl.verification_status,ppl.listing_status,ppl.creator_id,ppl.show_id,
            l.status license_status,p.product_id product_key,la.installation_uuid,la.domain activation_domain,la.status activation_status,
            c.creator_uuid,s.show_uuid
            FROM public_platform_listings ppl JOIN licenses l ON l.id=ppl.license_id JOIN products p ON p.id=ppl.product_id
            JOIN license_activations la ON la.id=? AND la.license_id=l.id
            LEFT JOIN creators c ON c.id=ppl.creator_id LEFT JOIN shows s ON s.id=ppl.show_id
            WHERE ppl.id=? LIMIT 1".($lock?' FOR UPDATE':''));
        $stmt->execute([$activationId,$listingId]);$row=$stmt->fetch();
        if(!is_array($row))throw new RuntimeException('listing_activation_mismatch');
        if($row['activation_status']!=='active'||!in_array($row['license_status'],['active','development'],true))throw new RuntimeException('active_installation_required');
        if($row['verification_status']!=='verified'||$row['listing_status']!=='published')throw new RuntimeException('verified_public_listing_required');
        return $row;
    }

    private function key(): string { return hash('sha256',(string)\vp3_config('security.app_key'),true); }
    private function encrypt(string $secret): array
    {
        $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$nonce,$tag,'vp3-clips-bridge-v1');
        if($cipher===false)throw new RuntimeException('bridge_secret_encryption_failed');
        return [base64_encode($cipher),base64_encode($nonce),base64_encode($tag)];
    }
    private function decrypt(string $cipher,string $nonce,string $tag): string
    {
        $plain=openssl_decrypt(base64_decode($cipher,true)?:'','aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,base64_decode($nonce,true)?:'',base64_decode($tag,true)?:'','vp3-clips-bridge-v1');
        if(!is_string($plain)||$plain==='')throw new RuntimeException('bridge_secret_unavailable');
        return $plain;
    }
    private function normalizeDate(?string $value): ?string
    {
        $value=trim((string)$value);if($value==='')return null;
        try{return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');}catch(Throwable){throw new RuntimeException('invalid_bridge_expiration');}
    }
    private function isUuid(string $value): bool{return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$value);}
}
