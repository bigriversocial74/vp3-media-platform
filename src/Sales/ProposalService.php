<?php
declare(strict_types=1);

namespace VP3\Sales;

use PDO;
use RuntimeException;

final class ProposalService
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $proposal, array $items, int $adminId): array
    {
        if (!$items) throw new RuntimeException('proposal_items_required');
        $leadId = (int)($proposal['lead_id'] ?? 0) ?: null;
        $customerId = (int)($proposal['customer_id'] ?? 0) ?: null;
        if ($leadId === null && $customerId === null) throw new RuntimeException('proposal_owner_required');
        $title = $this->text($proposal['title'] ?? '',190); if ($title === '') throw new RuntimeException('proposal_title_required');
        $this->assertOwner($leadId,$customerId);
        [$subtotal,$normalized] = $this->normalizeItems($items);
        $discount = max(0,(int)($proposal['discount_cents'] ?? 0));
        $tax = max(0,(int)($proposal['tax_cents'] ?? 0));
        $total = max(0,$subtotal-$discount+$tax);
        $deposit = min($total,max(0,(int)($proposal['deposit_cents'] ?? 0)));
        $uuid = \vp3_uuid();
        $number = 'VP3-P-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)),0,8));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO proposals (proposal_uuid,proposal_number,lead_id,customer_id,created_by_admin_id,title,summary,scope_text,terms_text,currency,subtotal_cents,discount_cents,tax_cents,total_cents,deposit_cents,status,valid_until,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,\'draft\',NULLIF(?,\'\'),NOW(),NOW())'
            );
            $stmt->execute([$uuid,$number,$leadId,$customerId,$adminId,$title,trim((string)($proposal['summary'] ?? '')),trim((string)($proposal['scope_text'] ?? '')),trim((string)($proposal['terms_text'] ?? '')),strtoupper($this->text($proposal['currency'] ?? 'USD',3)),$subtotal,$discount,$tax,$total,$deposit,$this->text($proposal['valid_until'] ?? '',10)]);
            $id = (int)$this->pdo->lastInsertId();
            $itemStmt = $this->pdo->prepare('INSERT INTO proposal_items (proposal_id,service_package_id,item_type,name,description,quantity,unit_price_cents,total_cents,billing_type,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            foreach ($normalized as $index=>$item) $itemStmt->execute([$id,$item['service_package_id'],$item['item_type'],$item['name'],$item['description'],$item['quantity'],$item['unit_price_cents'],$item['total_cents'],$item['billing_type'],$index]);
            if ($leadId) (new LeadService($this->pdo))->activity($leadId,$adminId,'proposal','Proposal created',$number,['proposal_uuid'=>$uuid,'total_cents'=>$total]);
            $this->pdo->commit();
            return ['id'=>$id,'proposal_uuid'=>$uuid,'proposal_number'=>$number,'total_cents'=>$total];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $proposalId, string $status, int $adminId): void
    {
        $allowed = ['draft','sent','viewed','accepted','declined','expired','cancelled'];
        if (!in_array($status,$allowed,true)) throw new RuntimeException('invalid_proposal_status');
        $stmt = $this->pdo->prepare(
            'UPDATE proposals SET status=?,sent_at=CASE WHEN ?=\'sent\' THEN COALESCE(sent_at,NOW()) ELSE sent_at END,declined_at=CASE WHEN ?=\'declined\' THEN NOW() ELSE declined_at END,updated_at=NOW() WHERE id=? AND status<>\'accepted\''
        );
        $stmt->execute([$status,$status,$status,$proposalId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('proposal_not_updated');
        \vp3_audit('admin',$adminId,'proposal.status_updated','proposal',(string)$proposalId,['status'=>$status]);
    }

    public function accept(int $proposalId, int $customerId, string $acceptedName, string $acceptedEmail, string $termsVersion = '2026-07'): array
    {
        if ($acceptedName === '' || !filter_var($acceptedEmail,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('acceptance_identity_required');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT p.*,c.email customer_email FROM proposals p JOIN customers c ON c.id=p.customer_id WHERE p.id=? AND p.customer_id=? FOR UPDATE');
            $stmt->execute([$proposalId,$customerId]);
            $proposal = $stmt->fetch();
            if (!is_array($proposal)) throw new RuntimeException('proposal_not_found');
            if (!hash_equals(strtolower((string)$proposal['customer_email']), strtolower($acceptedEmail))) throw new RuntimeException('acceptance_email_must_match_account');
            if (!in_array($proposal['status'],['sent','viewed'],true)) throw new RuntimeException('proposal_not_accepting');
            if ($proposal['valid_until'] && $proposal['valid_until'] < gmdate('Y-m-d')) throw new RuntimeException('proposal_expired');
            $statement = 'I accept the proposal scope, pricing, delivery assumptions, and terms presented by VP3 Media Group.';
            $this->pdo->prepare('INSERT INTO proposal_acceptances (proposal_id,customer_id,accepted_name,accepted_email,terms_version,ip_address,user_agent_hash,acceptance_statement,accepted_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
                ->execute([$proposalId,$customerId,$this->text($acceptedName,190),strtolower($this->text($acceptedEmail,190)),$this->text($termsVersion,50),\vp3_client_ip(),hash('sha256',(string)($_SERVER['HTTP_USER_AGENT'] ?? '')),$statement]);
            $this->pdo->prepare("UPDATE proposals SET status='accepted',accepted_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$proposalId]);
            if (!empty($proposal['lead_id'])) $this->pdo->prepare("UPDATE leads SET stage='won',customer_id=COALESCE(customer_id,?),converted_at=COALESCE(converted_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$customerId,$proposal['lead_id']]);
            $this->pdo->prepare('INSERT INTO notifications (notification_uuid,recipient_type,recipient_id,category,title,message,action_url,entity_type,entity_uuid,status,created_at) VALUES (?,\'customer\',?,\'proposal\',?,?,?,?,?,\'unread\',NOW())')
                ->execute([\vp3_uuid(),$customerId,'Proposal accepted','Your VP3 proposal has been accepted and is ready for project activation.','account-proposal.php?id='.$proposalId,'proposal',$proposal['proposal_uuid']]);
            $this->pdo->commit();
            \vp3_audit('customer',$customerId,'proposal.accepted','proposal',$proposal['proposal_uuid'],['proposal_number'=>$proposal['proposal_number']]);
            return $proposal;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function recalculate(int $proposalId): void
    {
        $subtotal = (int)$this->pdo->query('SELECT COALESCE(SUM(total_cents),0) FROM proposal_items WHERE proposal_id='.(int)$proposalId)->fetchColumn();
        $this->pdo->prepare('UPDATE proposals SET subtotal_cents=?,total_cents=GREATEST(0,?-discount_cents+tax_cents),deposit_cents=LEAST(deposit_cents,GREATEST(0,?-discount_cents+tax_cents)),updated_at=NOW() WHERE id=?')->execute([$subtotal,$subtotal,$subtotal,$proposalId]);
    }

    private function assertOwner(?int $leadId, ?int $customerId): void
    {
        $leadCustomer=null;
        if($leadId!==null){$stmt=$this->pdo->prepare('SELECT customer_id FROM leads WHERE id=?');$stmt->execute([$leadId]);$value=$stmt->fetchColumn();if($value===false)throw new RuntimeException('lead_not_found');$leadCustomer=$value!==null?(int)$value:null;}
        if($customerId!==null){$stmt=$this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE id=? AND status IN ('active','pending')");$stmt->execute([$customerId]);if((int)$stmt->fetchColumn()<1)throw new RuntimeException('customer_not_available');}
        if($leadCustomer!==null&&$customerId!==null&&$leadCustomer!==$customerId)throw new RuntimeException('lead_customer_mismatch');
    }

    private function normalizeItems(array $items): array
    {
        $subtotal = 0; $normalized = [];
        foreach ($items as $item) {
            $name = $this->text($item['name'] ?? '',190); if ($name === '') continue;
            $qty = max(0.01,(float)($item['quantity'] ?? 1));
            $unit = max(0,(int)($item['unit_price_cents'] ?? 0));
            $total = (int)round($qty*$unit);
            $subtotal += $total;
            $normalized[] = [
                'service_package_id'=>(int)($item['service_package_id'] ?? 0) ?: null,
                'item_type'=>in_array(($item['item_type'] ?? 'service'),['package','service','license','hosting','deposit','discount','custom'],true)?$item['item_type']:'service',
                'name'=>$name,'description'=>trim((string)($item['description'] ?? '')),'quantity'=>$qty,'unit_price_cents'=>$unit,'total_cents'=>$total,
                'billing_type'=>in_array(($item['billing_type'] ?? 'one_time'),['one_time','monthly','yearly','custom'],true)?$item['billing_type']:'one_time',
            ];
        }
        if (!$normalized) throw new RuntimeException('proposal_items_required');
        return [$subtotal,$normalized];
    }
    private function text(mixed $value, int $max): string { return \vp3_text_slice(trim((string)$value),$max); }
}
