<?php
declare(strict_types=1);

namespace VP3\Projects;

use PDO;
use RuntimeException;

final class ProjectService
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $input, int $adminId): array
    {
        $customerId = (int)($input['customer_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        if ($customerId < 1 || $title === '') throw new RuntimeException('project_customer_and_title_required');
        $this->assertProjectReferences($customerId, $input);
        $uuid = \vp3_uuid();
        $slug = preg_replace('/[^a-z0-9-]+/','-',strtolower(trim((string)($input['slug'] ?? $title))));
        $slug = trim((string)$slug,'-') ?: substr($uuid,0,8);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO creative_projects (project_uuid,customer_id,brief_id,proposal_id,creator_id,show_id,project_manager_admin_id,title,slug,project_type,summary,phase,health_status,readiness_percent,target_launch_date,status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?, ?,?,?,?,?,?,?,?,NULLIF(?,\'\'),?,NOW(),NOW())'
            );
            $stmt->execute([$uuid,$customerId,$this->nullableId($input['brief_id'] ?? 0),$this->nullableId($input['proposal_id'] ?? 0),$this->nullableId($input['creator_id'] ?? 0),$this->nullableId($input['show_id'] ?? 0),$adminId,$this->text($title,190),$this->text($slug,190),$this->text($input['project_type'] ?? 'media_platform',80),trim((string)($input['summary'] ?? '')),$this->enum($input['phase'] ?? 'discovery',['discovery','strategy','development','production','launch','active','paused','completed','cancelled'],'discovery'),$this->enum($input['health_status'] ?? 'on_track',['on_track','at_risk','blocked','complete'],'on_track'),max(0,min(100,(int)($input['readiness_percent'] ?? 0))),$this->text($input['target_launch_date'] ?? '',10),$this->enum($input['status'] ?? 'active',['draft','active','paused','completed','archived'],'active')]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO project_members (project_id,member_type,admin_id,project_role,status,created_at,updated_at) VALUES (?,\'admin\',?,\'Project Manager\',\'active\',NOW(),NOW())')->execute([$id,$adminId]);
            $this->pdo->prepare('INSERT INTO project_members (project_id,member_type,customer_id,project_role,status,created_at,updated_at) VALUES (?,\'customer\',?,\'Client Owner\',\'active\',NOW(),NOW())')->execute([$id,$customerId]);
            $this->activity($id,'admin',$adminId,'project.created','project',$uuid,'Project created and assigned.');
            $this->notifyCustomer($customerId,'project','Your VP3 project is active','Your project workspace is ready.','account-project.php?id='.$id,'project',$uuid);
            $this->pdo->commit();
            return ['id'=>$id,'project_uuid'=>$uuid];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addMilestone(int $projectId, array $input, int $adminId): string
    {
        $title = trim((string)($input['title'] ?? '')); if ($title === '') throw new RuntimeException('milestone_title_required');
        $this->projectCustomerId($projectId);
        $uuid = \vp3_uuid();
        $this->pdo->prepare('INSERT INTO project_milestones (milestone_uuid,project_id,title,description,phase,due_date,status,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,NULLIF(?,\'\'),?,?,NOW(),NOW())')
            ->execute([$uuid,$projectId,$this->text($title,190),trim((string)($input['description'] ?? '')),$this->text($input['phase'] ?? '',80),$this->text($input['due_date'] ?? '',10),$this->enum($input['status'] ?? 'planned',['planned','active','at_risk','blocked','completed','cancelled'],'planned'),max(0,(int)($input['sort_order'] ?? 0))]);
        $this->activity($projectId,'admin',$adminId,'milestone.created','milestone',$uuid,'Milestone added: '.$title);
        return $uuid;
    }

    public function addTask(int $projectId, array $input, int $adminId): string
    {
        $title = trim((string)($input['title'] ?? '')); if ($title === '') throw new RuntimeException('task_title_required');
        $customerId = $this->projectCustomerId($projectId);
        $assignment = (string)($input['assignment'] ?? 'vp3');
        $assignedAdmin = $assignment === 'vp3' ? ($this->nullableId($input['assigned_admin_id'] ?? $adminId) ?? $adminId) : null;
        $assignedCustomer = $assignment === 'customer' ? $customerId : null;
        $milestoneId = $this->nullableId($input['milestone_id'] ?? 0);
        if ($milestoneId !== null) $this->assertBelongs('project_milestones', $milestoneId, $projectId, 'milestone_project_mismatch');
        if ($assignedAdmin !== null) { $check=$this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE id=? AND status='active'"); $check->execute([$assignedAdmin]); if ((int)$check->fetchColumn() < 1) throw new RuntimeException('assigned_admin_not_active'); }
        $uuid = \vp3_uuid();
        $this->pdo->prepare('INSERT INTO project_tasks (task_uuid,project_id,milestone_id,assigned_admin_id,assigned_customer_id,created_by_type,created_by_id,title,description,task_type,priority,status,due_at,estimated_minutes,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,\'admin\',?,?,?,?,?,?,NULLIF(?,\'\'),?,?,NOW(),NOW())')
            ->execute([$uuid,$projectId,$milestoneId,$assignedAdmin,$assignedCustomer,$adminId,$this->text($title,190),trim((string)($input['description'] ?? '')),$this->text($input['task_type'] ?? 'general',80),$this->enum($input['priority'] ?? 'normal',['low','normal','high','urgent'],'normal'),$this->enum($input['status'] ?? 'ready',['backlog','ready','in_progress','waiting','review','completed','cancelled'],'ready'),$this->text($input['due_at'] ?? '',19),$this->nullableId($input['estimated_minutes'] ?? 0),max(0,(int)($input['sort_order'] ?? 0))]);
        $this->activity($projectId,'admin',$adminId,'task.created','task',$uuid,'Task added: '.$title);
        if ($assignedCustomer) $this->notifyCustomer($assignedCustomer,'task','New project task',$title,'account-project.php?id='.$projectId,'task',$uuid);
        $this->recalculateReadiness($projectId);
        return $uuid;
    }

    public function requestApproval(int $projectId, array $input, int $adminId): string
    {
        $customerId = $this->projectCustomerId($projectId);
        $taskId = $this->nullableId($input['task_id'] ?? 0); $assetId = $this->nullableId($input['asset_id'] ?? 0);
        if ($taskId === null && $assetId === null) throw new RuntimeException('approval_subject_required');
        if ($taskId !== null) $this->assertBelongs('project_tasks', $taskId, $projectId, 'task_project_mismatch');
        if ($assetId !== null) $this->assertBelongs('project_assets', $assetId, $projectId, 'asset_project_mismatch');
        $title = trim((string)($input['title'] ?? '')); if ($title === '') throw new RuntimeException('approval_title_required');
        $uuid = \vp3_uuid();
        $this->pdo->prepare('INSERT INTO project_approvals (approval_uuid,project_id,task_id,asset_id,requested_by_admin_id,requested_from_customer_id,title,instructions,status,due_at,requested_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?, ?,\'pending\',NULLIF(?,\'\'),NOW(),NOW(),NOW())')
            ->execute([$uuid,$projectId,$taskId,$assetId,$adminId,$customerId,$this->text($title,190),trim((string)($input['instructions'] ?? '')),$this->text($input['due_at'] ?? '',19)]);
        $this->activity($projectId,'admin',$adminId,'approval.requested','approval',$uuid,'Approval requested: '.$title);
        $this->notifyCustomer($customerId,'approval','Approval requested',$title,'account-project.php?id='.$projectId,'approval',$uuid);
        return $uuid;
    }

    public function customerTaskStatus(int $taskId, int $customerId, string $status): void
    {
        $allowed = ['ready','in_progress','waiting','review','completed'];
        if (!in_array($status,$allowed,true)) throw new RuntimeException('invalid_task_status');
        $stmt = $this->pdo->prepare('SELECT pt.project_id,pt.task_uuid,pt.depends_on_task_id FROM project_tasks pt JOIN creative_projects p ON p.id=pt.project_id WHERE pt.id=? AND pt.assigned_customer_id=? AND p.customer_id=?');
        $stmt->execute([$taskId,$customerId,$customerId]); $task=$stmt->fetch(); if(!is_array($task)) throw new RuntimeException('task_not_found');
        if ($status === 'completed' && !empty($task['depends_on_task_id'])) { $dependency=$this->pdo->prepare("SELECT status FROM project_tasks WHERE id=? AND project_id=?"); $dependency->execute([(int)$task['depends_on_task_id'],(int)$task['project_id']]); if ($dependency->fetchColumn() !== 'completed') throw new RuntimeException('task_dependency_incomplete'); }
        $this->pdo->prepare('UPDATE project_tasks SET status=?,started_at=CASE WHEN ?=\'in_progress\' THEN COALESCE(started_at,NOW()) ELSE started_at END,completed_at=CASE WHEN ?=\'completed\' THEN NOW() ELSE NULL END,updated_at=NOW() WHERE id=?')->execute([$status,$status,$status,$taskId]);
        $this->activity((int)$task['project_id'],'customer',$customerId,'task.status_updated','task',$task['task_uuid'],'Customer changed task status to '.$status.'.');
        $this->recalculateReadiness((int)$task['project_id']);
    }

    public function respondApproval(int $approvalId, int $customerId, string $status, string $notes): void
    {
        if (!in_array($status,['approved','revision_requested','rejected'],true)) throw new RuntimeException('invalid_approval_response');
        $stmt=$this->pdo->prepare("SELECT pa.project_id,pa.approval_uuid,pa.task_id,pa.asset_id FROM project_approvals pa JOIN creative_projects p ON p.id=pa.project_id WHERE pa.id=? AND pa.requested_from_customer_id=? AND p.customer_id=? AND pa.status='pending' FOR UPDATE");
        $stmt->execute([$approvalId,$customerId,$customerId]);$approval=$stmt->fetch();if(!is_array($approval))throw new RuntimeException('approval_not_found');
        $this->pdo->prepare('UPDATE project_approvals SET status=?,response_notes=?,responded_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status,trim($notes),$approvalId]);
        if ($approval['asset_id']) $this->pdo->prepare('UPDATE project_assets SET approval_status=?,updated_at=NOW() WHERE id=?')->execute([$status,$approval['asset_id']]);
        if ($approval['task_id'] && $status==='approved') $this->pdo->prepare("UPDATE project_tasks SET status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$approval['task_id']]);
        $this->activity((int)$approval['project_id'],'customer',$customerId,'approval.responded','approval',$approval['approval_uuid'],'Approval response: '.$status.'.');
        $this->recalculateReadiness((int)$approval['project_id']);
    }

    public function registerAsset(int $projectId, int $customerId, array $input): string
    {
        if ($this->projectCustomerId($projectId)!==$customerId) throw new RuntimeException('project_not_found');
        $title=trim((string)($input['title']??''));$url=\vp3_public_https_url($input['external_url']??'');if($title===''||$url==='')throw new RuntimeException('asset_title_and_https_url_required');
        $uuid=\vp3_uuid();
        $this->pdo->prepare('INSERT INTO project_assets (asset_uuid,project_id,uploaded_by_type,uploaded_by_id,category,title,description,external_url,visibility,approval_status,status,created_at,updated_at) VALUES (?, ?,\'customer\',?,?,?,?,?,\'shared\',\'not_required\',\'active\',NOW(),NOW())')
            ->execute([$uuid,$projectId,$customerId,$this->text($input['category']??'general',80),$this->text($title,190),trim((string)($input['description']??'')),$url]);
        $this->activity($projectId,'customer',$customerId,'asset.registered','asset',$uuid,'Customer registered asset: '.$title);
        return $uuid;
    }

    public function recalculateReadiness(int $projectId): int
    {
        $stmt=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status='completed') completed FROM project_tasks WHERE project_id=? AND status<>'cancelled'");$stmt->execute([$projectId]);$row=$stmt->fetch();
        $tasks=(int)($row['total']??0);$done=(int)($row['completed']??0);
        $stmt=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status='approved') approved FROM project_approvals WHERE project_id=? AND status<>'cancelled'");$stmt->execute([$projectId]);$approvals=$stmt->fetch();
        $total=$tasks+(int)($approvals['total']??0);$complete=$done+(int)($approvals['approved']??0);$percent=$total>0?(int)round(($complete/$total)*100):0;
        $this->pdo->prepare('UPDATE creative_projects SET readiness_percent=?,updated_at=NOW() WHERE id=?')->execute([$percent,$projectId]);
        return $percent;
    }

    public function activity(int $projectId,string $actorType,?int $actorId,string $action,?string $entityType,?string $entityUuid,string $summary,array $metadata=[]):void
    {
        $this->pdo->prepare('INSERT INTO project_activity (project_id,actor_type,actor_id,action,entity_type,entity_uuid,summary,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
            ->execute([$projectId,$actorType,$actorId,$this->text($action,100),$entityType,$entityUuid,$this->text($summary,500),$metadata?json_encode(\vp3_redact($metadata),JSON_THROW_ON_ERROR):null]);
    }

    private function notifyCustomer(int $customerId,string $category,string $title,string $message,string $url,?string $entityType,?string $entityUuid):void
    {
        $this->pdo->prepare('INSERT INTO notifications (notification_uuid,recipient_type,recipient_id,category,title,message,action_url,entity_type,entity_uuid,status,created_at) VALUES (?,\'customer\',?,?,?,?,?,?,?,\'unread\',NOW())')
            ->execute([\vp3_uuid(),$customerId,$category,$this->text($title,190),$this->text($message,1000),$this->text($url,500),$entityType,$entityUuid]);
    }
    private function assertProjectReferences(int $customerId, array $input): void
    {
        $customer=$this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE id=? AND status IN ('active','pending')");$customer->execute([$customerId]);if((int)$customer->fetchColumn()<1)throw new RuntimeException('customer_not_available');
        $checks=[
            ['project_briefs','brief_id','customer_id','brief_customer_mismatch'],
            ['proposals','proposal_id','customer_id','proposal_customer_mismatch'],
            ['creators','creator_id','customer_id','creator_customer_mismatch'],
            ['shows','show_id','customer_id','show_customer_mismatch'],
        ];
        foreach($checks as [$table,$inputKey,$ownerColumn,$error]){
            $id=$this->nullableId($input[$inputKey]??0);if($id===null)continue;
            $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND {$ownerColumn}=?");$stmt->execute([$id,$customerId]);if((int)$stmt->fetchColumn()<1)throw new RuntimeException($error);
        }
    }
    private function assertBelongs(string $table,int $id,int $projectId,string $error):void
    {
        $allowed=['project_milestones','project_tasks','project_assets'];if(!in_array($table,$allowed,true))throw new RuntimeException('invalid_reference_table');
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND project_id=?");$stmt->execute([$id,$projectId]);if((int)$stmt->fetchColumn()<1)throw new RuntimeException($error);
    }
    private function projectCustomerId(int $projectId):int{$s=$this->pdo->prepare('SELECT customer_id FROM creative_projects WHERE id=?');$s->execute([$projectId]);$id=(int)$s->fetchColumn();if($id<1)throw new RuntimeException('project_not_found');return $id;}
    private function nullableId(mixed $v):?int{$i=(int)$v;return $i>0?$i:null;}
    private function text(mixed $v,int $max):string{return \vp3_text_slice(trim((string)$v),$max);}
    private function enum(mixed $v,array $allowed,string $default):string{$v=(string)$v;return in_array($v,$allowed,true)?$v:$default;}
}
