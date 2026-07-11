<?php
declare(strict_types=1);
namespace VP3\Licensing;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class LicenseService
{
    public function __construct(private PDO $db) {}

    public function issue(array $data): array
    {
        $key = LicenseKey::generate();
        $uuid = \vp3_uuid();
        $domains = array_values(array_unique(array_filter(array_map(static fn($domain): string => \vp3_normalize_domain((string)$domain), (array)($data['domains'] ?? [])))));
        if (!$domains) {
            throw new RuntimeException('authorized_domain_required');
        }
        return \vp3_transaction(function (PDO $db) use ($data,$key,$uuid,$domains): array {
            $customer = $db->prepare("SELECT id FROM customers WHERE id=? AND status='active' LIMIT 1");
            $customer->execute([(int)$data['customer_id']]);
            if (!$customer->fetchColumn()) throw new RuntimeException('active_customer_required');
            $product = $db->prepare("SELECT id FROM products WHERE id=? AND status='active' LIMIT 1");
            $product->execute([(int)$data['product_id']]);
            if (!$product->fetchColumn()) throw new RuntimeException('active_product_required');
            $planId = (int)($data['plan_id'] ?? 0);
            if ($planId > 0) {
                $plan = $db->prepare("SELECT id FROM product_plans WHERE id=? AND product_id=? AND status='active' LIMIT 1");
                $plan->execute([$planId,(int)$data['product_id']]);
                if (!$plan->fetchColumn()) throw new RuntimeException('plan_product_mismatch');
            }
            $stmt = $db->prepare('INSERT INTO licenses (license_uuid,license_key_hash,license_key_prefix,license_fingerprint,customer_id,product_id,plan_id,edition,status,max_activations,activation_count,issued_at,expires_at,updates_until,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,NOW(),?,?,NOW(),NOW())');
            $stmt->execute([
                $uuid, LicenseKey::hash($key), LicenseKey::prefix($key), LicenseKey::fingerprint($key),
                (int)$data['customer_id'], (int)$data['product_id'], $planId ?: null,
                (string)$data['edition'], (string)($data['status'] ?? 'active'), max(1, (int)$data['max_activations']),
                $data['expires_at'] ?: null, $data['updates_until'] ?: null,
            ]);
            $licenseId = (int)$db->lastInsertId();
            $domainStmt = $db->prepare("INSERT INTO license_domains (license_id,domain,domain_type,status,created_at,updated_at) VALUES (?,?,'production','active',NOW(),NOW())");
            foreach ($domains as $domain) {
                $domainStmt->execute([$licenseId,$domain]);
            }
            \vp3_audit('admin', isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null, 'license.issued', 'license', $uuid, ['customer_id'=>$data['customer_id'],'product_id'=>$data['product_id'],'domains'=>$domains]);
            \vp3_log('info','License issued',['license_uuid'=>$uuid,'customer_id'=>$data['customer_id'],'product_id'=>$data['product_id']]);
            return ['license_uuid'=>$uuid,'license_key'=>$key,'prefix'=>LicenseKey::prefix($key),'fingerprint'=>LicenseKey::fingerprint($key)];
        });
    }

    public function activate(array $input): array
    {
        return \vp3_transaction(function(PDO $db) use ($input): array {
            $license = $this->findLicense((string)$input['license_key'], (string)$input['product_id'], true);
            $this->assertUsable($license, (string)$input['domain']);
            $installationUuid = trim((string)$input['installation_uuid']);
            if ($installationUuid === '') throw new RuntimeException('installation_uuid_required');
            $existing = $db->prepare('SELECT id,status,domain FROM license_activations WHERE license_id=? AND installation_uuid=? LIMIT 1 FOR UPDATE');
            $existing->execute([(int)$license['id'],$installationUuid]);
            $row = $existing->fetch();
            $token = \vp3_secure_token(32);
            $tokenHash = \vp3_hash_token($token);
            if (is_array($row)) {
                if ($row['status'] === 'active' && !hash_equals((string)$row['domain'], \vp3_normalize_domain((string)$input['domain']))) {
                    throw new RuntimeException('installation_domain_mismatch');
                }
                if ($row['status'] !== 'active') {
                    if ((int)$license['activation_count'] >= (int)$license['max_activations']) throw new RuntimeException('activation_limit_reached');
                    $db->prepare('UPDATE licenses SET activation_count=activation_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$license['id']]);
                }
                $db->prepare("UPDATE license_activations SET domain=?,ip_address=?,product_version=?,status='active',installation_token_hash=?,last_validated_at=NOW(),deactivated_at=NULL,updated_at=NOW() WHERE id=?")->execute([\vp3_normalize_domain((string)$input['domain']),\vp3_client_ip(),(string)($input['product_version']??''),$tokenHash,(int)$row['id']]);
            } else {
                if ((int)$license['activation_count'] >= (int)$license['max_activations']) throw new RuntimeException('activation_limit_reached');
                $db->prepare("INSERT INTO license_activations (license_id,installation_uuid,domain,ip_address,product_version,status,installation_token_hash,activated_at,last_validated_at,created_at,updated_at) VALUES (?,?,?,?,?,'active',?,NOW(),NOW(),NOW(),NOW())")->execute([(int)$license['id'],$installationUuid,\vp3_normalize_domain((string)$input['domain']),\vp3_client_ip(),(string)($input['product_version']??''),$tokenHash]);
                $db->prepare('UPDATE licenses SET activation_count=activation_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$license['id']]);
            }
            $this->audit((int)$license['id'],'activate',$installationUuid,(string)$input['domain'],'success');
            return $this->publicLicense($license) + ['installation_uuid'=>$installationUuid,'installation_token'=>$token];
        });
    }

    public function validate(array $input): array
    {
        $license = $this->findLicense((string)$input['license_key'], (string)$input['product_id']);
        $this->assertUsable($license, (string)$input['domain']);
        $activation = $this->requireActivation((int)$license['id'], (string)$input['installation_uuid'], (string)($input['installation_token']??''));
        if (!hash_equals((string)$activation['domain'], \vp3_normalize_domain((string)$input['domain']))) throw new RuntimeException('installation_domain_mismatch');
        $this->db->prepare('UPDATE license_activations SET last_validated_at=NOW(),product_version=?,domain=?,ip_address=?,updated_at=NOW() WHERE id=?')->execute([(string)($input['product_version']??''),\vp3_normalize_domain((string)$input['domain']),\vp3_client_ip(),(int)$activation['id']]);
        $this->audit((int)$license['id'],'validate',(string)$input['installation_uuid'],(string)$input['domain'],'success');
        return $this->publicLicense($license) + ['installation_uuid'=>$input['installation_uuid'],'valid'=>true];
    }

    public function deactivate(array $input): array
    {
        return \vp3_transaction(function(PDO $db) use ($input): array {
            $license = $this->findLicense((string)$input['license_key'], (string)$input['product_id'], true);
            $activation = $this->requireActivation((int)$license['id'], (string)$input['installation_uuid'], (string)($input['installation_token']??''), true);
            if ($activation['status'] === 'active') {
                $db->prepare("UPDATE license_activations SET status='deactivated',deactivated_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$activation['id']]);
                $db->prepare('UPDATE licenses SET activation_count=GREATEST(activation_count-1,0),updated_at=NOW() WHERE id=?')->execute([(int)$license['id']]);
            }
            $this->audit((int)$license['id'],'deactivate',(string)$input['installation_uuid'],(string)($activation['domain']??''),'success');
            return ['deactivated'=>true,'installation_uuid'=>$input['installation_uuid']];
        });
    }

    public function checkUpdates(array $input): array
    {
        $license = $this->findLicense((string)$input['license_key'], (string)$input['product_id']);
        $this->assertUsable($license, (string)$input['domain']);
        $activation = $this->requireActivation((int)$license['id'], (string)$input['installation_uuid'], (string)($input['installation_token']??''));
        if (!hash_equals((string)$activation['domain'], \vp3_normalize_domain((string)$input['domain']))) throw new RuntimeException('installation_domain_mismatch');
        $eligible = $license['updates_until'] === null || $license['updates_until'] >= date('Y-m-d');
        $stmt = $this->db->prepare("SELECT version,release_channel,release_notes,published_at FROM product_releases WHERE product_id=? AND status='published' ORDER BY published_at DESC,id DESC LIMIT 1");
        $stmt->execute([(int)$license['product_db_id']]);
        $release = $stmt->fetch();
        return ['eligible'=>$eligible,'current_version'=>(string)($input['product_version']??''),'latest_release'=>$eligible&&is_array($release)?$release:null];
    }

    private function findLicense(string $key, string $productId, bool $forUpdate=false): array
    {
        $sql = 'SELECT l.*,p.id product_db_id,p.product_id AS catalog_product_id,p.current_version FROM licenses l JOIN products p ON p.id=l.product_id WHERE l.license_key_hash=? AND p.product_id=? LIMIT 1' . ($forUpdate?' FOR UPDATE':'');
        $stmt=$this->db->prepare($sql);$stmt->execute([LicenseKey::hash($key),$productId]);$row=$stmt->fetch();
        if(!is_array($row)) throw new RuntimeException('license_not_found');
        return $row;
    }

    private function assertUsable(array $license,string $domain): void
    {
        if(!in_array($license['status'],['active','development'],true)) throw new RuntimeException('license_'.$license['status']);
        if($license['expires_at']!==null && new DateTimeImmutable((string)$license['expires_at'])<new DateTimeImmutable('today')) throw new RuntimeException('license_expired');
        $normalized=\vp3_normalize_domain($domain);
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM license_domains WHERE license_id=? AND status='active' AND (domain=? OR domain='*')");$stmt->execute([(int)$license['id'],$normalized]);
        if((int)$stmt->fetchColumn()<1) throw new RuntimeException('domain_not_authorized');
    }

    private function requireActivation(int $licenseId,string $installationUuid,string $token,bool $forUpdate=false): array
    {
        if($installationUuid===''||$token==='') throw new RuntimeException('installation_credentials_required');
        $sql='SELECT * FROM license_activations WHERE license_id=? AND installation_uuid=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');$stmt=$this->db->prepare($sql);$stmt->execute([$licenseId,$installationUuid]);$row=$stmt->fetch();
        if(!is_array($row)||!hash_equals((string)$row['installation_token_hash'],\vp3_hash_token($token))) throw new RuntimeException('installation_not_authorized');
        if(!$forUpdate && $row['status']!=='active') throw new RuntimeException('activation_inactive');
        return $row;
    }

    private function publicLicense(array $license): array
    {
        return ['license_uuid'=>$license['license_uuid'],'product_id'=>$license['catalog_product_id'],'edition'=>$license['edition'],'status'=>$license['status'],'expires_at'=>$license['expires_at'],'updates_until'=>$license['updates_until'],'max_activations'=>(int)$license['max_activations']];
    }

    private function audit(int $licenseId,string $action,string $installationUuid,string $domain,string $result): void
    {
        \vp3_audit('api', null, 'license.' . $action, 'license', (string)$licenseId, ['installation_uuid'=>$installationUuid,'result'=>$result]);
        $this->db->prepare('INSERT INTO license_validation_logs (license_id,action,installation_uuid,domain,ip_address,result,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$licenseId,$action,$installationUuid,\vp3_normalize_domain($domain),\vp3_client_ip(),$result]);
    }
}
