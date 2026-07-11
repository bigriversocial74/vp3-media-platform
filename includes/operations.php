<?php
declare(strict_types=1);

function vp3_service_package_fallbacks(): array
{
    return [
        ['slug'=>'story-launch','name'=>'Story Launch','category'=>'creative','short_description'=>'Shape the concept, audience journey, launch plan, and owned media destination.','price_cents'=>250000,'billing_type'=>'one_time','items'=>['Discovery workshop','Story and audience brief','Launch roadmap','Platform experience plan']],
        ['slug'=>'platform-production','name'=>'Platform Production','category'=>'platform','short_description'=>'Configure, brand, populate, and launch a VP3-powered creator or show platform.','price_cents'=>750000,'billing_type'=>'one_time','items'=>['Platform setup','Brand implementation','Catalog structure','Launch readiness review']],
        ['slug'=>'creative-operations','name'=>'Creative Operations','category'=>'management','short_description'=>'Ongoing production planning, editorial coordination, approvals, and release management.','price_cents'=>250000,'billing_type'=>'monthly','items'=>['Weekly production plan','Milestone tracking','Content review workflow','Launch and release coordination']],
    ];
}

function vp3_service_packages(bool $activeOnly = true): array
{
    if (!vp3_db_available()) return vp3_service_package_fallbacks();
    try {
        $sql='SELECT * FROM service_packages'.($activeOnly?" WHERE status='active'":'').' ORDER BY featured_rank DESC,id';
        $rows=vp3_db()->query($sql)->fetchAll();
        if(!$rows)return vp3_service_package_fallbacks();
        $stmt=vp3_db()->prepare('SELECT label,description,item_type FROM service_package_items WHERE package_id=? ORDER BY sort_order,id');
        foreach($rows as &$row){$stmt->execute([$row['id']]);$row['items']=$stmt->fetchAll();}
        return $rows;
    } catch(Throwable $e){vp3_log('warning','Service package tables unavailable',['message'=>$e->getMessage()]);return vp3_service_package_fallbacks();}
}

function vp3_project_phase_label(string $phase): string
{
    return ucwords(str_replace('_',' ',$phase));
}

function vp3_due_state(?string $date): string
{
    if(!$date)return 'unscheduled';
    $ts=strtotime($date);if($ts===false)return 'unscheduled';
    $today=strtotime(gmdate('Y-m-d 00:00:00'));
    if($ts<$today)return 'overdue';
    if($ts<=$today+7*86400)return 'due-soon';
    return 'scheduled';
}

function vp3_project_access(int $projectId,int $customerId): ?array
{
    if(!vp3_db_available())return null;
    $stmt=vp3_db()->prepare('SELECT * FROM creative_projects WHERE id=? AND customer_id=? LIMIT 1');$stmt->execute([$projectId,$customerId]);$row=$stmt->fetch();return is_array($row)?$row:null;
}

function vp3_notification_count(string $recipientType,int $recipientId):int
{
    if(!vp3_db_available())return 0;
    try{$stmt=vp3_db()->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_type=? AND recipient_id=? AND status='unread'");$stmt->execute([$recipientType,$recipientId]);return(int)$stmt->fetchColumn();}catch(Throwable){return 0;}
}

function vp3_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function vp3_text_slice(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}
