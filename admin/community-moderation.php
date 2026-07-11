<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use VP3\Network\ViewerNotificationService;

$admin=vp3_require_admin_permission('clips.moderate');
$notifications=new ViewerNotificationService(vp3_db());

if(vp3_method()==='POST'){
    vp3_verify_csrf();
    $action=vp3_input('action');
    $commentId=(int)vp3_input('comment_id');
    $reportId=(int)vp3_input('report_id');

    if(in_array($action,['hide','restore','remove'],true)&&$commentId>0){
        $stmt=vp3_db()->prepare('SELECT vc.*,cp.publication_uuid FROM viewer_comments vc JOIN clip_publications cp ON cp.id=vc.clip_publication_id WHERE vc.id=? LIMIT 1');
        $stmt->execute([$commentId]);
        $comment=$stmt->fetch();
        if($comment){
            $status=$action==='restore'?'published':($action==='hide'?'hidden':'removed');
            $body=$action==='remove'?'[removed]':(string)$comment['body'];
            vp3_db()->prepare('UPDATE viewer_comments SET status=?,body=?,moderation_reason=?,updated_at=NOW() WHERE id=?')
                ->execute([$status,$body,vp3_input('moderation_reason')?:null,$commentId]);
            if($status!=='published'){
                $notifications->create(
                    (int)$comment['viewer_id'],
                    'moderation',
                    'Comment moderation update',
                    'A VP3 moderator changed the visibility of one of your comments.',
                    'clips.php?clip='.rawurlencode((string)$comment['publication_uuid']),
                    null,null,null,(int)$comment['clip_publication_id'],$commentId
                );
            }
            vp3_audit('admin',(int)$admin['id'],'viewer.comment_moderated','viewer_comment',(string)$comment['comment_uuid'],['status'=>$status]);
        }
    }

    if(in_array($action,['resolve_report','dismiss_report'],true)&&$reportId>0){
        $status=$action==='resolve_report'?'resolved':'dismissed';
        vp3_db()->prepare('UPDATE viewer_comment_reports SET report_status=?,resolved_by=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute([$status,(int)$admin['id'],$reportId]);
        vp3_audit('admin',(int)$admin['id'],'viewer.comment_report_closed','viewer_comment_report',(string)$reportId,['status'=>$status]);
    }
    vp3_redirect('admin/community-moderation.php');
}

$reports=vp3_db()->query(
    "SELECT r.id report_id,r.reason,r.details,r.report_status,r.created_at report_created,
            vc.id comment_id,vc.comment_uuid,vc.body,vc.status comment_status,vc.created_at comment_created,
            author.display_name author_name,author.handle author_handle,
            reporter.display_name reporter_name,reporter.handle reporter_handle,
            cp.publication_uuid,cp.title clip_title
     FROM viewer_comment_reports r
     JOIN viewer_comments vc ON vc.id=r.comment_id
     JOIN viewer_accounts author ON author.id=vc.viewer_id
     JOIN viewer_accounts reporter ON reporter.id=r.reporter_viewer_id
     JOIN clip_publications cp ON cp.id=vc.clip_publication_id
     WHERE r.report_status IN ('open','reviewing')
     ORDER BY r.created_at ASC LIMIT 200"
)->fetchAll();

$comments=vp3_db()->query(
    "SELECT vc.id,vc.comment_uuid,vc.body,vc.status,vc.like_count,vc.reply_count,vc.created_at,
            va.display_name,va.handle,cp.publication_uuid,cp.title clip_title
     FROM viewer_comments vc
     JOIN viewer_accounts va ON va.id=vc.viewer_id
     JOIN clip_publications cp ON cp.id=vc.clip_publication_id
     ORDER BY vc.created_at DESC LIMIT 200"
)->fetchAll();

$pageTitle='Community moderation';
$extraStyles=['assets/css/community.css'];
require VP3_ROOT.'/includes/admin-header.php';
?>
<div class="admin-page-head"><div><span class="admin-kicker">Viewer safety</span><h1>Community moderation</h1><p>Review reported comments, apply visibility controls, and preserve an auditable moderation history.</p></div></div>
<section class="card"><h2>Open comment reports</h2><div class="table-wrap"><table class="table"><thead><tr><th>Report</th><th>Comment</th><th>Viewer</th><th>Clip</th><th>Actions</th></tr></thead><tbody>
<?php foreach($reports as$row):?><tr><td><strong><?=vp3_e($row['reason'])?></strong><br><small><?=vp3_e((string)$row['details'])?></small><br><small><?=vp3_e($row['report_created'])?></small></td><td><?=vp3_e($row['body'])?><br><small><?=vp3_e($row['comment_status'])?></small></td><td>@<?=vp3_e($row['author_handle'])?><br><small>Reported by @<?=vp3_e($row['reporter_handle'])?></small></td><td><?=vp3_e($row['clip_title'])?></td><td><form method="post" class="admin-inline-actions"><?=vp3_csrf_field()?><input type="hidden" name="comment_id" value="<?=(int)$row['comment_id']?>"><input type="hidden" name="report_id" value="<?=(int)$row['report_id']?>"><input type="text" name="moderation_reason" placeholder="Moderator note"><button name="action" value="hide" class="button small">Hide comment</button><button name="action" value="remove" class="button small secondary">Remove</button><button name="action" value="resolve_report" class="button small">Resolve report</button><button name="action" value="dismiss_report" class="button small secondary">Dismiss</button></form></td></tr><?php endforeach;?>
<?php if(!$reports):?><tr><td colspan="5">No open comment reports.</td></tr><?php endif;?>
</tbody></table></div></section>
<section class="card"><h2>Recent comments</h2><div class="table-wrap"><table class="table"><thead><tr><th>Comment</th><th>Viewer</th><th>Clip</th><th>Signals</th><th>Visibility</th></tr></thead><tbody>
<?php foreach($comments as$row):?><tr><td><?=vp3_e($row['body'])?><br><small><?=vp3_e($row['created_at'])?></small></td><td>@<?=vp3_e($row['handle'])?></td><td><?=vp3_e($row['clip_title'])?></td><td><?=(int)$row['like_count']?> likes · <?=(int)$row['reply_count']?> replies</td><td><form method="post" class="admin-inline-actions"><?=vp3_csrf_field()?><input type="hidden" name="comment_id" value="<?=(int)$row['id']?>"><input type="text" name="moderation_reason" placeholder="Moderator note"><?php if($row['status']==='published'):?><button name="action" value="hide" class="button small secondary">Hide</button><?php else:?><button name="action" value="restore" class="button small">Restore</button><?php endif;?><button name="action" value="remove" class="button small secondary">Remove</button></form></td></tr><?php endforeach;?>
</tbody></table></div></section>
<?php require VP3_ROOT.'/includes/admin-footer.php';?>
