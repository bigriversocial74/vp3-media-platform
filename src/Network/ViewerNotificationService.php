<?php
declare(strict_types=1);

namespace VP3\Network;

use PDO;
use RuntimeException;

final class ViewerNotificationService
{
    public function __construct(private PDO $db) {}

    public function unreadCount(int $viewerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM viewer_notifications WHERE viewer_id=? AND read_at IS NULL');
        $stmt->execute([$viewerId]);
        return (int)$stmt->fetchColumn();
    }

    public function list(int $viewerId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, min($offset, 100000));
        $stmt = $this->db->prepare(
            "SELECT vn.notification_uuid,vn.notification_type,vn.title,vn.body,vn.destination_path,vn.read_at,vn.created_at,
                    actor.display_name actor_name,actor.handle actor_handle
             FROM viewer_notifications vn
             LEFT JOIN viewer_accounts actor ON actor.id=vn.actor_viewer_id
             WHERE vn.viewer_id=?
             ORDER BY vn.created_at DESC
             LIMIT {$offset},{$limit}"
        );
        $stmt->execute([$viewerId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $viewerId, ?string $notificationUuid = null): int
    {
        if ($notificationUuid === null || $notificationUuid === '') {
            $stmt = $this->db->prepare('UPDATE viewer_notifications SET read_at=COALESCE(read_at,NOW()) WHERE viewer_id=? AND read_at IS NULL');
            $stmt->execute([$viewerId]);
            return $stmt->rowCount();
        }
        if (!preg_match('/^[0-9a-f-]{36}$/i', $notificationUuid)) {
            throw new RuntimeException('invalid_notification_identity');
        }
        $stmt = $this->db->prepare('UPDATE viewer_notifications SET read_at=COALESCE(read_at,NOW()) WHERE viewer_id=? AND notification_uuid=?');
        $stmt->execute([$viewerId, $notificationUuid]);
        return $stmt->rowCount();
    }

    public function preferences(int $viewerId): array
    {
        $this->db->prepare(
            "INSERT IGNORE INTO viewer_notification_preferences
             (viewer_id,in_app_replies,in_app_comment_likes,in_app_new_clips,email_digest,updated_at)
             VALUES (?,1,1,1,'off',NOW())"
        )->execute([$viewerId]);
        $stmt = $this->db->prepare('SELECT in_app_replies,in_app_comment_likes,in_app_new_clips,email_digest FROM viewer_notification_preferences WHERE viewer_id=?');
        $stmt->execute([$viewerId]);
        return $stmt->fetch() ?: [
            'in_app_replies' => 1,
            'in_app_comment_likes' => 1,
            'in_app_new_clips' => 1,
            'email_digest' => 'off',
        ];
    }

    public function updatePreferences(int $viewerId, array $input): array
    {
        $digest = in_array((string)($input['email_digest'] ?? 'off'), ['off','daily','weekly'], true)
            ? (string)$input['email_digest']
            : 'off';
        $this->db->prepare(
            "INSERT INTO viewer_notification_preferences
             (viewer_id,in_app_replies,in_app_comment_likes,in_app_new_clips,email_digest,updated_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
             in_app_replies=VALUES(in_app_replies),
             in_app_comment_likes=VALUES(in_app_comment_likes),
             in_app_new_clips=VALUES(in_app_new_clips),
             email_digest=VALUES(email_digest),
             updated_at=NOW()"
        )->execute([
            $viewerId,
            !empty($input['in_app_replies']) ? 1 : 0,
            !empty($input['in_app_comment_likes']) ? 1 : 0,
            !empty($input['in_app_new_clips']) ? 1 : 0,
            $digest,
        ]);
        return $this->preferences($viewerId);
    }

    public function create(
        int $viewerId,
        string $type,
        string $title,
        string $body,
        string $destinationPath,
        ?int $actorViewerId = null,
        ?int $creatorId = null,
        ?int $showId = null,
        ?int $clipId = null,
        ?int $commentId = null
    ): void {
        if ($viewerId < 1 || $viewerId === $actorViewerId) {
            return;
        }
        $allowed = ['reply','comment_like','new_clip','moderation','system'];
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException('invalid_notification_type');
        }
        if (!$this->enabled($viewerId, $type)) {
            return;
        }
        $destinationPath = $this->safePath($destinationPath);
        $this->db->prepare(
            'INSERT INTO viewer_notifications
             (notification_uuid,viewer_id,notification_type,actor_viewer_id,creator_id,show_id,clip_publication_id,comment_id,title,body,destination_path,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            \vp3_uuid(),
            $viewerId,
            $type,
            $actorViewerId,
            $creatorId,
            $showId,
            $clipId,
            $commentId,
            $this->truncate($title, 190),
            $this->truncate($body, 500),
            $destinationPath,
        ]);
    }

    public function dispatchNewClips(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $clips = $this->db->query(
            "SELECT cp.id,cp.publication_uuid,cp.title,cp.creator_id,cp.show_id,c.display_name creator_name,s.title show_title
             FROM clip_publications cp
             LEFT JOIN creators c ON c.id=cp.creator_id
             LEFT JOIN shows s ON s.id=cp.show_id
             LEFT JOIN viewer_notification_dispatches d ON d.clip_publication_id=cp.id
             WHERE d.id IS NULL
               AND cp.publication_status='published'
               AND cp.moderation_status='approved'
               AND cp.rights_status='confirmed'
               AND cp.feed_eligible=1
             ORDER BY cp.published_at ASC,cp.id ASC
             LIMIT {$limit}"
        )->fetchAll();

        $dispatched = 0;
        $recipients = 0;
        foreach ($clips as $clip) {
            $viewerIds = $this->followersForClip((int)$clip['creator_id'], (int)$clip['show_id']);
            foreach ($viewerIds as $viewerId) {
                $this->create(
                    $viewerId,
                    'new_clip',
                    'New VP3 Reel',
                    trim(((string)($clip['creator_name'] ?: $clip['show_title'] ?: 'A creator you follow')) . ' published “' . (string)$clip['title'] . '”.'),
                    'clips.php?clip=' . rawurlencode((string)$clip['publication_uuid']),
                    null,
                    (int)$clip['creator_id'] ?: null,
                    (int)$clip['show_id'] ?: null,
                    (int)$clip['id'],
                    null
                );
                $recipients++;
            }
            $this->db->prepare(
                'INSERT IGNORE INTO viewer_notification_dispatches (clip_publication_id,recipient_count,dispatched_at) VALUES (?,?,NOW())'
            )->execute([(int)$clip['id'], count($viewerIds)]);
            $dispatched++;
        }
        return ['clips_dispatched' => $dispatched, 'recipients_considered' => $recipients];
    }

    private function enabled(int $viewerId, string $type): bool
    {
        $prefs = $this->preferences($viewerId);
        return match ($type) {
            'reply' => !empty($prefs['in_app_replies']),
            'comment_like' => !empty($prefs['in_app_comment_likes']),
            'new_clip' => !empty($prefs['in_app_new_clips']),
            default => true,
        };
    }

    private function followersForClip(int $creatorId, int $showId): array
    {
        $conditions = [];
        $params = [];
        if ($creatorId > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM viewer_creator_follows f WHERE f.viewer_id=va.id AND f.creator_id=?)';
            $params[] = $creatorId;
        }
        if ($showId > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM viewer_show_follows f WHERE f.viewer_id=va.id AND f.show_id=?)';
            $params[] = $showId;
        }
        if (!$conditions) {
            return [];
        }
        $muteClauses = [];
        if ($creatorId > 0) {
            $muteClauses[] = "NOT EXISTS (SELECT 1 FROM viewer_mutes m WHERE m.viewer_id=va.id AND m.target_type='creator' AND m.target_id={$creatorId})";
        }
        if ($showId > 0) {
            $muteClauses[] = "NOT EXISTS (SELECT 1 FROM viewer_mutes m WHERE m.viewer_id=va.id AND m.target_type='show' AND m.target_id={$showId})";
        }
        $sql = "SELECT DISTINCT va.id
                FROM viewer_accounts va
                LEFT JOIN viewer_notification_preferences p ON p.viewer_id=va.id
                WHERE va.status='active'
                  AND (" . implode(' OR ', $conditions) . ")
                  AND COALESCE(p.in_app_new_clips,1)=1
                  AND " . implode(' AND ', $muteClauses);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function truncate(string $value, int $limit): string
    {
        return function_exists('mb_substr') ? (string)mb_substr($value,0,$limit,'UTF-8') : substr($value,0,$limit);
    }

    private function safePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '://') || str_starts_with($path, '//') || str_contains($path, "\n")) {
            return 'viewer-notifications.php';
        }
        return ltrim($path, '/');
    }
}
