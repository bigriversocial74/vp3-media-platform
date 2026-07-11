<?php
declare(strict_types=1);
namespace VP3\Payments;

use PDO;
use RuntimeException;

final class WebhookEventService
{
    public function __construct(private PDO $db) {}

    public function receive(string $provider, string $eventId, string $eventType, string $rawPayload): array
    {
        $provider = strtolower(trim($provider));
        $eventId = trim($eventId);
        $eventType = trim($eventType);
        if (!preg_match('/^[a-z0-9_-]{2,50}$/', $provider) || $eventId === '' || $eventType === '') {
            throw new RuntimeException('invalid_webhook_event');
        }
        $payloadHash = hash('sha256', $rawPayload);
        try {
            $stmt = $this->db->prepare("INSERT INTO payment_webhook_events (provider,event_id,event_type,payload_hash,processing_status,received_at) VALUES (?,?,?,?,'received',NOW())");
            $stmt->execute([$provider, $eventId, $eventType, $payloadHash]);
            return ['duplicate' => false, 'event_row_id' => (int)$this->db->lastInsertId()];
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $stmt = $this->db->prepare('SELECT id,payload_hash,processing_status FROM payment_webhook_events WHERE provider=? AND event_id=? LIMIT 1');
            $stmt->execute([$provider, $eventId]);
            $row = $stmt->fetch();
            if (!is_array($row) || !hash_equals((string)$row['payload_hash'], $payloadHash)) throw new RuntimeException('webhook_event_collision');
            return ['duplicate'=>true,'event_row_id'=>(int)$row['id'],'processing_status'=>(string)$row['processing_status']];
        }
    }

    public function markProcessed(int $eventRowId, string $status = 'processed'): void
    {
        if (!in_array($status, ['processed', 'ignored'], true)) throw new RuntimeException('invalid_webhook_status');
        $stmt = $this->db->prepare('UPDATE payment_webhook_events SET processing_status=?,failure_message=NULL,processed_at=NOW() WHERE id=?');
        $stmt->execute([$status, $eventRowId]);
    }

    public function markFailed(int $eventRowId, string $message): void
    {
        $stmt = $this->db->prepare("UPDATE payment_webhook_events SET processing_status='failed',failure_message=?,processed_at=NOW() WHERE id=?");
        $stmt->execute([substr($message, 0, 500), $eventRowId]);
    }
}
