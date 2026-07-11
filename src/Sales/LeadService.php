<?php
declare(strict_types=1);

namespace VP3\Sales;

use PDO;
use RuntimeException;

final class LeadService
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $input, string $source = 'website'): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('valid_name_and_email_required');
        }
        $uuid = \vp3_uuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO leads (lead_uuid,source,source_detail,name,email,phone,company_name,project_name,project_type,budget_range,target_launch_date,summary,stage,priority,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NULLIF(?,\'\'),?,\'new\',?,NOW(),NOW())'
        );
        $stmt->execute([
            $uuid,
            substr($source, 0, 80),
            $this->text($input['source_detail'] ?? '', 190),
            $this->text($name, 150),
            $this->text($email, 190),
            $this->nullable($input['phone'] ?? '', 40),
            $this->nullable($input['company_name'] ?? '', 190),
            $this->nullable($input['project_name'] ?? '', 190),
            $this->nullable($input['project_type'] ?? '', 80),
            $this->nullable($input['budget_range'] ?? '', 80),
            $this->nullable($input['target_launch_date'] ?? '', 10),
            trim((string)($input['summary'] ?? '')),
            in_array(($input['priority'] ?? 'normal'), ['low','normal','high','urgent'], true) ? $input['priority'] : 'normal',
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->activity($id, null, 'created', 'Lead created', 'New lead captured from ' . $source, ['source' => $source]);
        return ['id' => $id, 'lead_uuid' => $uuid];
    }

    public function createDemoRequest(int $leadId, array $input): string
    {
        if ($leadId < 1) throw new RuntimeException('lead_required');
        $uuid = \vp3_uuid();
        $format = in_array(($input['meeting_format'] ?? 'video'), ['video','phone','in_person','flexible'], true) ? $input['meeting_format'] : 'video';
        $stmt = $this->pdo->prepare(
            'INSERT INTO demo_requests (request_uuid,lead_id,preferred_date,preferred_time_window,timezone,meeting_format,attendee_count,goals,status,created_at,updated_at)
             VALUES (?,?,NULLIF(?,\'\'),?,?,?,?,?,\'requested\',NOW(),NOW())'
        );
        $stmt->execute([
            $uuid,$leadId,$this->text($input['preferred_date'] ?? '', 10),$this->nullable($input['preferred_time_window'] ?? '', 80),
            $this->nullable($input['timezone'] ?? '', 80),$format,max(1,min(100,(int)($input['attendee_count'] ?? 1))),trim((string)($input['goals'] ?? '')),
        ]);
        $this->activity($leadId, null, 'meeting', 'Demo requested', 'A website visitor requested a VP3 discovery demo.', ['request_uuid' => $uuid]);
        return $uuid;
    }

    public function updateStage(int $leadId, string $stage, ?int $adminId, ?string $followUpAt = null, string $lostReason = ''): void
    {
        $allowed = ['new','qualified','discovery','proposal','negotiation','won','lost','archived'];
        if (!in_array($stage, $allowed, true)) throw new RuntimeException('invalid_lead_stage');
        $before = $this->pdo->prepare('SELECT stage FROM leads WHERE id=? FOR UPDATE');
        $before->execute([$leadId]);
        $previous = $before->fetchColumn();
        if ($previous === false) throw new RuntimeException('lead_not_found');
        $stmt = $this->pdo->prepare(
            'UPDATE leads SET stage=?,next_follow_up_at=NULLIF(?,\'\'),lost_reason=NULLIF(?,\'\'),converted_at=CASE WHEN ?=\'won\' THEN COALESCE(converted_at,NOW()) ELSE converted_at END,updated_at=NOW() WHERE id=?'
        );
        $stmt->execute([$stage,$followUpAt,$lostReason,$stage,$leadId]);
        $this->activity($leadId,$adminId,'stage_change','Lead stage changed',sprintf('%s → %s',(string)$previous,$stage),['previous'=>$previous,'current'=>$stage]);
    }

    public function addNote(int $leadId, int $adminId, string $note, string $visibility = 'internal'): void
    {
        $note = trim($note);
        if (\vp3_text_length($note) < 2) throw new RuntimeException('note_required');
        $visibility = in_array($visibility, ['internal','customer_safe'], true) ? $visibility : 'internal';
        $this->pdo->prepare('INSERT INTO lead_notes (lead_id,admin_id,note,visibility,created_at) VALUES (?,?,?,?,NOW())')->execute([$leadId,$adminId,$note,$visibility]);
        $this->activity($leadId,$adminId,'note','Note added',$visibility === 'internal' ? 'Internal note recorded.' : 'Customer-safe note recorded.');
    }

    public function activity(int $leadId, ?int $adminId, string $type, string $title, string $details = '', array $metadata = []): void
    {
        $allowed = ['created','email','phone','meeting','note','stage_change','follow_up','proposal','conversion','system'];
        if (!in_array($type, $allowed, true)) $type = 'system';
        $this->pdo->prepare(
            'INSERT INTO lead_activities (lead_id,admin_id,activity_type,title,details,metadata_json,occurred_at,created_at) VALUES (?,?,?,?,?,?,NOW(),NOW())'
        )->execute([$leadId,$adminId,$type,$this->text($title,190),trim($details),$metadata ? json_encode(\vp3_redact($metadata), JSON_THROW_ON_ERROR) : null]);
    }

    private function text(mixed $value, int $max): string { return \vp3_text_slice(trim((string)$value), $max); }
    private function nullable(mixed $value, int $max): ?string { $v = $this->text($value,$max); return $v === '' ? null : $v; }
}
