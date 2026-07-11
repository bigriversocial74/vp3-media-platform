<?php
declare(strict_types=1);
namespace VP3\Network;

use PDO;

final class FeedService
{
    public function __construct(private PDO $db) {}

    public function clips(string $feed, ?string $cursor, int $limit): array
    {
        $limit = max(1, min($limit, 50));
        $params = [];
        $cursorClause = '';
        if ($cursor !== null && preg_match('/^[0-9]{1,20}$/', $cursor)) {
            $cursorClause = ' AND cp.id < ?';
            $params[] = (int)$cursor;
        }
        $order = match ($feed) {
            'trending' => '(COALESCE(v.views,0)+(COALESCE(e.engagements,0)*4)) DESC,cp.id DESC',
            'new' => 'cp.published_at DESC,cp.id DESC',
            default => 'cp.featured_rank>0 DESC,cp.featured_rank ASC,cp.id DESC',
        };
        $sql = "SELECT cp.id,cp.publication_uuid,cp.title,cp.caption,cp.poster_url,cp.source_media_url,cp.destination_url,
                       cp.duration_seconds,cp.aspect_ratio,cp.published_at,c.creator_uuid,c.display_name AS creator_name,c.slug AS creator_slug,
                       s.show_uuid,s.title AS show_title,s.slug AS show_slug,COALESCE(v.views,0) AS view_count,
                       COALESCE(e.engagements,0) AS engagement_count
                FROM clip_publications cp
                LEFT JOIN creators c ON c.id=cp.creator_id
                LEFT JOIN shows s ON s.id=cp.show_id
                LEFT JOIN (SELECT clip_publication_id,COUNT(*) views FROM clip_view_events GROUP BY clip_publication_id) v ON v.clip_publication_id=cp.id
                LEFT JOIN (SELECT clip_publication_id,COUNT(*) engagements FROM clip_engagement_events GROUP BY clip_publication_id) e ON e.clip_publication_id=cp.id
                WHERE cp.publication_status='published' AND cp.moderation_status='approved' AND cp.rights_status='confirmed'
                  AND cp.feed_eligible=1{$cursorClause}
                ORDER BY {$order} LIMIT {$limit}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $next = $items ? (string)end($items)['id'] : null;
        foreach ($items as &$item) {
            unset($item['id']);
            $item['view_count'] = (int)$item['view_count'];
            $item['engagement_count'] = (int)$item['engagement_count'];
            $item['duration_seconds'] = (int)$item['duration_seconds'];
        }
        return ['items' => $items, 'next_cursor' => $next];
    }
}
