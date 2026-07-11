<?php
declare(strict_types=1);

namespace VP3\Network;

use PDO;

final class ViewerFeedService
{
    public function __construct(private PDO $db) {}

    public function clips(string $feed, ?string $cursor, int $limit, array $identity): array
    {
        $feed=in_array($feed,['for-you','following','trending','new'],true)?$feed:'for-you';
        $limit=max(1,min($limit,30));
        $offset=($cursor!==null&&preg_match('/^[0-9]{1,7}$/',$cursor))?min((int)$cursor,100000):0;
        $key=(string)$identity['identity_key'];
        $viewerId=(int)($identity['viewer_id']??0);
        $params=[$key,$key,$key,$key,$key,$key,$key,$key,$viewerId];
        $followingClause=$feed==='following'?' AND (vcf.id IS NOT NULL OR vsf.id IS NOT NULL)':'';
        $order=match($feed){
            'following'=>'cp.published_at DESC,cp.id DESC',
            'trending'=>'(COALESCE(v.views,0)+(COALESCE(e.engagements,0)*4)+(COALESCE(v.completions,0)*3)+(COALESCE(cm.comments,0)*2)) DESC,cp.id DESC',
            'new'=>'cp.published_at DESC,cp.id DESC',
            default=>'(CASE WHEN vcf.id IS NOT NULL THEN 160 ELSE 0 END + CASE WHEN vsf.id IS NOT NULL THEN 130 ELSE 0 END + LEAST(COALESCE(affinity.score,0),240) + CASE WHEN cp.featured_rank>0 THEN 30 ELSE 0 END - LEAST(COALESCE(vwh.view_count,0)*35,140)) DESC,cp.published_at DESC,cp.id DESC',
        };
        $sql="SELECT cp.publication_uuid,cp.title,cp.caption,cp.poster_url,cp.source_media_url,cp.destination_url,cp.duration_seconds,cp.aspect_ratio,cp.published_at,
                     c.creator_uuid,c.display_name creator_name,c.slug creator_slug,s.show_uuid,s.title show_title,s.slug show_slug,s.genre,
                     COALESCE(v.views,0) view_count,COALESCE(e.engagements,0) engagement_count,COALESCE(cm.comments,0) comment_count,
                     (vca_like.id IS NOT NULL) liked,(vca_save.id IS NOT NULL) saved,(vcf.id IS NOT NULL) follows_creator,(vsf.id IS NOT NULL) follows_show
              FROM clip_publications cp
              LEFT JOIN creators c ON c.id=cp.creator_id
              LEFT JOIN shows s ON s.id=cp.show_id
              LEFT JOIN (SELECT clip_publication_id,COUNT(*) views,SUM(completed=1) completions FROM clip_view_events GROUP BY clip_publication_id) v ON v.clip_publication_id=cp.id
              LEFT JOIN (SELECT clip_publication_id,COUNT(*) engagements FROM clip_engagement_events GROUP BY clip_publication_id) e ON e.clip_publication_id=cp.id
              LEFT JOIN (SELECT clip_publication_id,COUNT(*) comments FROM viewer_comments WHERE status='published' GROUP BY clip_publication_id) cm ON cm.clip_publication_id=cp.id
              LEFT JOIN viewer_clip_actions vca_like ON vca_like.clip_publication_id=cp.id AND vca_like.identity_key=? AND vca_like.action_type='like'
              LEFT JOIN viewer_clip_actions vca_save ON vca_save.clip_publication_id=cp.id AND vca_save.identity_key=? AND vca_save.action_type='save'
              LEFT JOIN viewer_creator_follows vcf ON vcf.creator_id=cp.creator_id AND vcf.identity_key=?
              LEFT JOIN viewer_show_follows vsf ON vsf.show_id=cp.show_id AND vsf.identity_key=?
              LEFT JOIN viewer_watch_history vwh ON vwh.clip_publication_id=cp.id AND vwh.identity_key=?
              LEFT JOIN (
                SELECT genre,SUM(signal_score) score FROM (
                  SELECT s2.genre,SUM(GREATEST(0,h.watch_seconds+(h.completion_count*25)+(h.view_count*5)-(h.skipped_count*20))) signal_score
                  FROM viewer_watch_history h JOIN clip_publications cp2 ON cp2.id=h.clip_publication_id LEFT JOIN shows s2 ON s2.id=cp2.show_id
                  WHERE h.identity_key=? AND s2.genre IS NOT NULL GROUP BY s2.genre
                  UNION ALL
                  SELECT s3.genre,COUNT(*)*45 signal_score
                  FROM viewer_clip_actions a JOIN clip_publications cp3 ON cp3.id=a.clip_publication_id LEFT JOIN shows s3 ON s3.id=cp3.show_id
                  WHERE a.identity_key=? AND a.action_type IN ('like','save') AND s3.genre IS NOT NULL GROUP BY s3.genre
                ) signals GROUP BY genre
              ) affinity ON affinity.genre=s.genre
              WHERE cp.publication_status='published' AND cp.moderation_status='approved' AND cp.rights_status='confirmed' AND cp.feed_eligible=1
                AND NOT EXISTS (SELECT 1 FROM viewer_clip_actions hidden WHERE hidden.clip_publication_id=cp.id AND hidden.identity_key=? AND hidden.action_type='hide')
                AND NOT EXISTS (
                    SELECT 1 FROM viewer_mutes muted
                    WHERE muted.viewer_id=? AND (
                        (muted.target_type='creator' AND muted.target_id=cp.creator_id)
                        OR (muted.target_type='show' AND muted.target_id=cp.show_id)
                    )
                )
                {$followingClause}
              ORDER BY {$order} LIMIT {$offset},{$limit}";
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        $items=$stmt->fetchAll();
        foreach($items as&$item){
            foreach(['view_count','engagement_count','comment_count','duration_seconds']as$field)$item[$field]=(int)$item[$field];
            foreach(['liked','saved','follows_creator','follows_show']as$field)$item[$field]=(bool)$item[$field];
        }
        $next=count($items)===$limit?(string)($offset+count($items)):null;
        return['items'=>$items,'next_cursor'=>$next,'feed'=>$feed,'authenticated'=>!empty($identity['viewer_id'])];
    }
}
