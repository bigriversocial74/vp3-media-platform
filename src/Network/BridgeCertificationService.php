<?php
declare(strict_types=1);
namespace VP3\Network;

use PDO;
use RuntimeException;

final class BridgeCertificationService
{
    private const REQUIRED_SOURCE_CHECKS = [
        'database_schema',
        'license_receipt',
        'bridge_settings',
        'curl_tls',
        'openssl',
        'ffmpeg',
        'ffprobe',
        'clip_storage',
        'public_https_base',
        'signed_context',
        'render_probe',
    ];

    public function __construct(private PDO $db) {}

    public function submit(array $auth, array $input): array
    {
        $checks = $this->normalizeChecks($input['checks'] ?? null);
        $serverChecks = [
            'credential_active' => $this->pass('Authenticated active bridge credential.'),
            'license_active' => $this->pass('License status accepted: ' . (string)$auth['license_status']),
            'installation_active' => $this->pass('Installation is active: ' . (string)$auth['installation_uuid']),
            'domain_match' => $this->pass('Installation domain verified: ' . (string)$auth['activation_domain']),
            'listing_verified' => $this->pass('Public listing is verified and published.'),
            'product_match' => $this->pass('Product identity verified: ' . (string)$auth['product_key']),
            'signed_request' => $this->pass('Timestamp, nonce, request ID, and HMAC signature accepted.'),
        ];
        $allChecks = array_merge($checks, $serverChecks);
        $failed = [];
        foreach (self::REQUIRED_SOURCE_CHECKS as $name) {
            if (($checks[$name]['status'] ?? 'fail') !== 'pass') {
                $failed[] = $name;
            }
        }
        $status = $failed ? 'failed' : 'passed';
        $uuid = \vp3_uuid();
        $report = $input['source_report'] ?? [];
        if (!is_array($report)) {
            $report = [];
        }
        $failureSummary = $failed ? 'Failed checks: ' . implode(', ', $failed) : null;
        $stmt = $this->db->prepare('INSERT INTO platform_bridge_certifications (certification_uuid,bridge_credential_id,status,certification_version,checks_json,source_report_json,failure_summary,submitted_at,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW(),NOW(),NOW())');
        $stmt->execute([
            $uuid,
            (int)$auth['id'],
            $status,
            $this->slice((string)($input['certification_version'] ?? '1.0'), 20),
            json_encode($allChecks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            json_encode(\vp3_redact($report), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $failureSummary,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->event($id, 'source_report_submitted', $status === 'passed' ? 'success' : 'failed', 'source', null, [
            'request_uuid' => $auth['request_uuid'],
            'failed_checks' => $failed,
        ]);
        \vp3_audit('api', null, 'platform_bridge.certification_submitted', 'platform_bridge_certification', $uuid, [
            'bridge_uuid' => $auth['bridge_uuid'],
            'status' => $status,
        ]);
        return $this->responseById($id);
    }

    public function status(array $auth): array
    {
        $stmt = $this->db->prepare("SELECT * FROM platform_bridge_certifications WHERE bridge_credential_id=? AND status='approved' AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY approved_at DESC,id DESC LIMIT 1");
        $stmt->execute([(int)$auth['id']]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $stmt = $this->db->prepare('SELECT * FROM platform_bridge_certifications WHERE bridge_credential_id=? ORDER BY id DESC LIMIT 1');
            $stmt->execute([(int)$auth['id']]);
            $row = $stmt->fetch();
        }
        if (!is_array($row)) {
            return [
                'certification_status' => 'not_started',
                'publishing_mode' => 'certification',
                'certification_uuid' => null,
                'expires_at' => null,
                'server_time' => date(DATE_ATOM),
            ];
        }
        return $this->response($row);
    }

    public function requireLive(array $auth): void
    {
        $stmt = $this->db->prepare("SELECT id FROM platform_bridge_certifications WHERE bridge_credential_id=? AND status='approved' AND (expires_at IS NULL OR expires_at>=NOW()) ORDER BY approved_at DESC,id DESC LIMIT 1");
        $stmt->execute([(int)$auth['id']]);
        if (!(int)$stmt->fetchColumn()) {
            throw new RuntimeException('bridge_certification_required');
        }
    }

    public function approve(int $certificationId, int $listingId, int $adminId): void
    {
        $stmt = $this->db->prepare("SELECT pbc.id,pbc.certification_uuid,pbc.bridge_credential_id,pbc.status,pc.public_listing_id,pc.status credential_status FROM platform_bridge_certifications pbc JOIN platform_bridge_credentials pc ON pc.id=pbc.bridge_credential_id WHERE pbc.id=? AND pc.public_listing_id=? LIMIT 1");
        $stmt->execute([$certificationId, $listingId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('bridge_certification_not_found');
        }
        if ($row['status'] !== 'passed') {
            throw new RuntimeException('passing_certification_required');
        }
        if ($row['credential_status'] !== 'active') {
            throw new RuntimeException('active_bridge_credential_required');
        }
        $this->db->prepare("UPDATE platform_bridge_certifications SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE bridge_credential_id=? AND status='approved'")
            ->execute([(int)$row['bridge_credential_id']]);
        $this->db->prepare("UPDATE platform_bridge_certifications SET status='approved',approved_at=NOW(),approved_by=?,expires_at=DATE_ADD(NOW(),INTERVAL 180 DAY),updated_at=NOW() WHERE id=? AND status='passed'")
            ->execute([$adminId, $certificationId]);
        $this->event($certificationId, 'live_publishing_approved', 'success', 'admin', $adminId);
        \vp3_audit('admin', $adminId, 'platform_bridge.certification_approved', 'platform_bridge_certification', (string)$row['certification_uuid'], [
            'listing_id' => $listingId,
            'bridge_credential_id' => $row['bridge_credential_id'],
        ]);
    }

    public function revoke(int $certificationId, int $listingId, int $adminId): void
    {
        $stmt = $this->db->prepare("UPDATE platform_bridge_certifications pbc JOIN platform_bridge_credentials pc ON pc.id=pbc.bridge_credential_id SET pbc.status='revoked',pbc.revoked_at=NOW(),pbc.updated_at=NOW() WHERE pbc.id=? AND pc.public_listing_id=? AND pbc.status IN ('passed','approved')");
        $stmt->execute([$certificationId, $listingId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('bridge_certification_not_found');
        }
        $this->event($certificationId, 'live_publishing_revoked', 'success', 'admin', $adminId);
        \vp3_audit('admin', $adminId, 'platform_bridge.certification_revoked', 'platform_bridge_certification', (string)$certificationId, ['listing_id' => $listingId]);
    }

    public function history(int $listingId): array
    {
        $stmt = $this->db->prepare('SELECT pbc.*,pc.bridge_uuid,la.installation_uuid,la.domain,a.name approved_by_name FROM platform_bridge_certifications pbc JOIN platform_bridge_credentials pc ON pc.id=pbc.bridge_credential_id JOIN license_activations la ON la.id=pc.license_activation_id LEFT JOIN admins a ON a.id=pbc.approved_by WHERE pc.public_listing_id=? ORDER BY pbc.id DESC LIMIT 100');
        $stmt->execute([$listingId]);
        return $stmt->fetchAll() ?: [];
    }

    private function normalizeChecks(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('certification_checks_required');
        }
        $checks = [];
        foreach (self::REQUIRED_SOURCE_CHECKS as $name) {
            $check = $value[$name] ?? null;
            if (!is_array($check)) {
                $checks[$name] = $this->fail('Check was not reported.');
                continue;
            }
            $status = strtolower(trim((string)($check['status'] ?? 'fail')));
            $checks[$name] = [
                'status' => $status === 'pass' ? 'pass' : 'fail',
                'detail' => $this->slice(trim((string)($check['detail'] ?? '')), 500),
            ];
        }
        return $checks;
    }

    private function responseById(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM platform_bridge_certifications WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('bridge_certification_not_found');
        }
        return $this->response($row);
    }

    private function response(array $row): array
    {
        $approved = ($row['status'] ?? '') === 'approved' && (empty($row['expires_at']) || strtotime((string)$row['expires_at']) >= time());
        return [
            'certification_uuid' => $row['certification_uuid'] ?? null,
            'certification_status' => $row['status'] ?? 'not_started',
            'publishing_mode' => $approved ? 'live' : 'certification',
            'failure_summary' => $row['failure_summary'] ?? null,
            'submitted_at' => $row['submitted_at'] ?? null,
            'approved_at' => $row['approved_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'checks' => json_decode((string)($row['checks_json'] ?? ''), true) ?: [],
            'server_time' => date(DATE_ATOM),
        ];
    }

    private function event(int $certificationId, string $type, string $status, string $actorType, ?int $actorId, array $metadata = []): void
    {
        $this->db->prepare('INSERT INTO platform_bridge_certification_events (certification_id,event_type,event_status,actor_type,actor_id,metadata_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([$certificationId, $type, $status, $actorType, $actorId, $metadata ? json_encode(\vp3_redact($metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null]);
    }

    private function pass(string $detail): array { return ['status' => 'pass', 'detail' => $detail]; }
    private function fail(string $detail): array { return ['status' => 'fail', 'detail' => $detail]; }
    private function slice(string $value, int $length): string { return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length); }
}
