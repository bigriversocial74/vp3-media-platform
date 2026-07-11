<?php
declare(strict_types=1);

namespace VP3\Network;

use PDO;
use RuntimeException;

final class ViewerCommunityService
{
    private ViewerNotificationService $notifications;

    public function __construct(private PDO $db)
    {
        $this->notifications = new ViewerNotificationService($db);
    }

    public function comments(string $publicationUuid, array $identity, int $limit = 80, int $offset = 0): array
    {
        $clip = $this->clip($publicationUuid);
        $viewerId = (int)($identity['viewer_id'] ?? 0);
        $limit = max(1, min($limit, 100));
        $offset = max(0, min($offset, 10000));
        $stmt = $this->db->prepare(
            "SELECT vc.id,vc.comment_uuid,vc.body,vc.like_count,vc.reply_count,vc.created_at,vc.updated_at,
                    parent.comment_uuid parent_uuid,
                    va.viewer_uuid,va.display_name,va.handle,va.avatar_url,
                    (reaction.id IS NOT NULL) liked,
                    (vc.viewer_id=?) own
             FROM viewer_comments vc
             JOIN viewer_accounts va ON va.id=vc.viewer_id AND va.status='active'
             LEFT JOIN viewer_comments parent ON parent.id=vc.parent_comment_id
             LEFT JOIN viewer_comment_reactions reaction ON reaction.comment_id=vc.id AND reaction.viewer_id=? AND reaction.reaction_type='like'
             WHERE vc.clip_publication_id=? AND vc.status='published'
               AND NOT EXISTS (
                 SELECT 1 FROM viewer_blocks b
                 WHERE ? > 0 AND (
                   (b.blocker_viewer_id=? AND b.blocked_viewer_id=vc.viewer_id)
                   OR (b.blocker_viewer_id=vc.viewer_id AND b.blocked_viewer_id=?)
                 )
               )
             ORDER BY COALESCE(vc.parent_comment_id,vc.id),vc.parent_comment_id IS NOT NULL,vc.created_at
             LIMIT {$offset},{$limit}"
        );
        $stmt->execute([$viewerId,$viewerId,(int)$clip['id'],$viewerId,$viewerId,$viewerId]);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            unset($item['id']);
            $item['like_count'] = (int)$item['like_count'];
            $item['reply_count'] = (int)$item['reply_count'];
            $item['liked'] = (bool)$item['liked'];
            $item['own'] = (bool)$item['own'];
            $item['avatar_url'] = \vp3_public_https_url((string)($item['avatar_url'] ?? ''));
        }
        return ['items' => $items, 'next_offset' => count($items) === $limit ? $offset + count($items) : null];
    }

    public function addComment(string $publicationUuid, string $body, int $viewerId, ?string $parentUuid = null): array
    {
        $clip = $this->clip($publicationUuid);
        $viewer = $this->activeViewer($viewerId);
        $body = $this->body($body);
        $parentId = null;
        $parentViewerId = null;
        if ($parentUuid !== null && $parentUuid !== '') {
            if (!preg_match('/^[0-9a-f-]{36}$/i', $parentUuid)) {
                throw new RuntimeException('invalid_parent_comment');
            }
            $stmt = $this->db->prepare(
                "SELECT id,viewer_id,parent_comment_id FROM viewer_comments
                 WHERE comment_uuid=? AND clip_publication_id=? AND status='published' LIMIT 1"
            );
            $stmt->execute([$parentUuid,(int)$clip['id']]);
            $parent = $stmt->fetch();
            if (!is_array($parent) || $parent['parent_comment_id'] !== null) {
                throw new RuntimeException('reply_target_not_found');
            }
            $parentId = (int)$parent['id'];
            $parentViewerId = (int)$parent['viewer_id'];
        }

        $uuid = \vp3_uuid();
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO viewer_comments
                 (comment_uuid,clip_publication_id,viewer_id,parent_comment_id,body,status,created_at,updated_at)
                 VALUES (?,?,?,?,?,\'published\',NOW(),NOW())'
            )->execute([$uuid,(int)$clip['id'],$viewerId,$parentId,$body]);
            $commentId = (int)$this->db->lastInsertId();
            if ($parentId !== null) {
                $this->db->prepare('UPDATE viewer_comments SET reply_count=reply_count+1,updated_at=NOW() WHERE id=?')->execute([$parentId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($parentViewerId !== null) {
            $this->notifications->create(
                $parentViewerId,
                'reply',
                'New reply',
                (string)$viewer['display_name'] . ' replied to your comment.',
                'clips.php?clip=' . rawurlencode($publicationUuid) . '&comment=' . rawurlencode($uuid),
                $viewerId,
                (int)$clip['creator_id'] ?: null,
                (int)$clip['show_id'] ?: null,
                (int)$clip['id'],
                $commentId
            );
        }
        \vp3_audit('viewer',$viewerId,'viewer.comment_created','viewer_comment',$uuid,['publication_uuid'=>$publicationUuid]);
        return ['comment_uuid'=>$uuid,'published'=>true];
    }

    public function toggleLike(string $commentUuid, int $viewerId): array
    {
        $comment = $this->comment($commentUuid);
        $this->activeViewer($viewerId);
        $stmt = $this->db->prepare("SELECT id FROM viewer_comment_reactions WHERE comment_id=? AND viewer_id=? AND reaction_type='like' LIMIT 1");
        $stmt->execute([(int)$comment['id'],$viewerId]);
        $reactionId = (int)$stmt->fetchColumn();
        if ($reactionId > 0) {
            $this->db->beginTransaction();
            try {
                $this->db->prepare('DELETE FROM viewer_comment_reactions WHERE id=?')->execute([$reactionId]);
                $this->db->prepare('UPDATE viewer_comments SET like_count=GREATEST(like_count-1,0),updated_at=NOW() WHERE id=?')->execute([(int)$comment['id']]);
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
            return ['active'=>false,'comment_uuid'=>$commentUuid];
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO viewer_comment_reactions (comment_id,viewer_id,reaction_type,created_at) VALUES (?,?,'like',NOW())")
                ->execute([(int)$comment['id'],$viewerId]);
            $this->db->prepare('UPDATE viewer_comments SET like_count=like_count+1,updated_at=NOW() WHERE id=?')->execute([(int)$comment['id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->notifications->create(
            (int)$comment['viewer_id'],
            'comment_like',
            'Your comment was liked',
            'A viewer liked your comment.',
            'clips.php?clip=' . rawurlencode((string)$comment['publication_uuid']) . '&comment=' . rawurlencode($commentUuid),
            $viewerId,
            null,
            null,
            (int)$comment['clip_publication_id'],
            (int)$comment['id']
        );
        return ['active'=>true,'comment_uuid'=>$commentUuid];
    }

    public function removeOwn(string $commentUuid, int $viewerId): array
    {
        $comment = $this->comment($commentUuid);
        if ((int)$comment['viewer_id'] !== $viewerId) {
            throw new RuntimeException('comment_not_owned');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE viewer_comments SET status='removed',body='[removed]',updated_at=NOW() WHERE id=?")->execute([(int)$comment['id']]);
            if ($comment['parent_comment_id'] !== null) {
                $this->db->prepare('UPDATE viewer_comments SET reply_count=GREATEST(reply_count-1,0),updated_at=NOW() WHERE id=?')
                    ->execute([(int)$comment['parent_comment_id']]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        \vp3_audit('viewer',$viewerId,'viewer.comment_removed','viewer_comment',$commentUuid);
        return ['removed'=>true,'comment_uuid'=>$commentUuid];
    }

    public function report(string $commentUuid, int $viewerId, string $reason, string $details): array
    {
        $comment = $this->comment($commentUuid);
        $allowed = ['harassment','spam','hate','sexual_content','violence','misinformation','privacy','other'];
        if (!in_array($reason, $allowed, true)) {
            throw new RuntimeException('invalid_report_reason');
        }
        if ((int)$comment['viewer_id'] === $viewerId) {
            throw new RuntimeException('cannot_report_own_comment');
        }
        $this->db->prepare(
            'INSERT INTO viewer_comment_reports
             (comment_id,reporter_viewer_id,reason,details,report_status,created_at,updated_at)
             VALUES (?,?,?,?,\'open\',NOW(),NOW())
             ON DUPLICATE KEY UPDATE reason=VALUES(reason),details=VALUES(details),report_status=\'open\',updated_at=NOW()'
        )->execute([(int)$comment['id'],$viewerId,$reason,$this->truncate(trim($details),1000)]);
        return ['reported'=>true,'comment_uuid'=>$commentUuid];
    }

    public function toggleBlock(int $viewerId, string $targetViewerUuid): array
    {
        $target = $this->viewerByUuid($targetViewerUuid);
        if ((int)$target['id'] === $viewerId) {
            throw new RuntimeException('cannot_block_self');
        }
        $stmt = $this->db->prepare('SELECT id FROM viewer_blocks WHERE blocker_viewer_id=? AND blocked_viewer_id=? LIMIT 1');
        $stmt->execute([$viewerId,(int)$target['id']]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            $this->db->prepare('DELETE FROM viewer_blocks WHERE id=?')->execute([$id]);
            return ['active'=>false,'viewer_uuid'=>$targetViewerUuid];
        }
        $this->db->prepare('INSERT INTO viewer_blocks (blocker_viewer_id,blocked_viewer_id,created_at) VALUES (?,?,NOW())')
            ->execute([$viewerId,(int)$target['id']]);
        return ['active'=>true,'viewer_uuid'=>$targetViewerUuid];
    }

    public function toggleMute(int $viewerId, string $type, string $uuid): array
    {
        if (!in_array($type,['creator','show'],true)) {
            throw new RuntimeException('invalid_mute_target');
        }
        $targetId = $this->targetId($type,$uuid);
        $stmt = $this->db->prepare('SELECT id FROM viewer_mutes WHERE viewer_id=? AND target_type=? AND target_id=? LIMIT 1');
        $stmt->execute([$viewerId,$type,$targetId]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            $this->db->prepare('DELETE FROM viewer_mutes WHERE id=?')->execute([$id]);
            return ['active'=>false,'target_type'=>$type,'target_uuid'=>$uuid];
        }
        $this->db->prepare('INSERT INTO viewer_mutes (viewer_id,target_type,target_id,created_at) VALUES (?,?,?,NOW())')
            ->execute([$viewerId,$type,$targetId]);
        return ['active'=>true,'target_type'=>$type,'target_uuid'=>$uuid];
    }

    public function safety(int $viewerId): array
    {
        $blocks = $this->db->prepare(
            'SELECT va.viewer_uuid,va.display_name,va.handle,va.avatar_url,b.created_at
             FROM viewer_blocks b JOIN viewer_accounts va ON va.id=b.blocked_viewer_id
             WHERE b.blocker_viewer_id=? ORDER BY b.created_at DESC'
        );
        $blocks->execute([$viewerId]);
        $mutes = $this->db->prepare(
            "SELECT m.target_type,m.target_id,m.created_at,
                    CASE WHEN m.target_type='creator' THEN c.creator_uuid ELSE s.show_uuid END target_uuid,
                    CASE WHEN m.target_type='creator' THEN c.display_name ELSE s.title END target_name
             FROM viewer_mutes m
             LEFT JOIN creators c ON m.target_type='creator' AND c.id=m.target_id
             LEFT JOIN shows s ON m.target_type='show' AND s.id=m.target_id
             WHERE m.viewer_id=? ORDER BY m.created_at DESC"
        );
        $mutes->execute([$viewerId]);
        return ['blocks'=>$blocks->fetchAll(),'mutes'=>$mutes->fetchAll()];
    }

    private function clip(string $uuid): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i',$uuid)) {
            throw new RuntimeException('invalid_clip_identity');
        }
        $stmt = $this->db->prepare(
            "SELECT id,publication_uuid,creator_id,show_id FROM clip_publications
             WHERE publication_uuid=? AND publication_status='published' AND moderation_status='approved'
               AND rights_status='confirmed' AND feed_eligible=1 LIMIT 1"
        );
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('clip_not_found');
        }
        return $row;
    }

    private function comment(string $uuid): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i',$uuid)) {
            throw new RuntimeException('invalid_comment_identity');
        }
        $stmt = $this->db->prepare(
            "SELECT vc.*,cp.publication_uuid FROM viewer_comments vc
             JOIN clip_publications cp ON cp.id=vc.clip_publication_id
             WHERE vc.comment_uuid=? AND vc.status='published' LIMIT 1"
        );
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('comment_not_found');
        }
        return $row;
    }

    private function activeViewer(int $viewerId): array
    {
        $stmt = $this->db->prepare("SELECT id,display_name FROM viewer_accounts WHERE id=? AND status='active' AND email_verified_at IS NOT NULL LIMIT 1");
        $stmt->execute([$viewerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('verified_viewer_required');
        }
        return $row;
    }

    private function viewerByUuid(string $uuid): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i',$uuid)) {
            throw new RuntimeException('invalid_viewer_identity');
        }
        $stmt = $this->db->prepare("SELECT id,viewer_uuid FROM viewer_accounts WHERE viewer_uuid=? AND status='active' LIMIT 1");
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('viewer_not_found');
        }
        return $row;
    }

    private function targetId(string $type,string $uuid): int
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i',$uuid)) {
            throw new RuntimeException('invalid_mute_identity');
        }
        $table = $type === 'creator' ? 'creators' : 'shows';
        $column = $type === 'creator' ? 'creator_uuid' : 'show_uuid';
        $status = $type === 'creator' ? "listing_status='published'" : "status='published'";
        $stmt = $this->db->prepare("SELECT id FROM {$table} WHERE {$column}=? AND {$status} LIMIT 1");
        $stmt->execute([$uuid]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) {
            throw new RuntimeException('mute_target_not_found');
        }
        return $id;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? (int)mb_strlen($value,'UTF-8') : strlen($value);
    }

    private function truncate(string $value, int $limit): string
    {
        return function_exists('mb_substr') ? (string)mb_substr($value,0,$limit,'UTF-8') : substr($value,0,$limit);
    }

    private function body(string $body): string
    {
        $body = trim(preg_replace('/\s+/u',' ',$body) ?? '');
        $body = strip_tags($body);
        if ($body === '' || $this->length($body) > 1000) {
            throw new RuntimeException('comment_length_invalid');
        }
        if (substr_count($body,'http://') + substr_count($body,'https://') > 2) {
            throw new RuntimeException('too_many_comment_links');
        }
        return $body;
    }
}
