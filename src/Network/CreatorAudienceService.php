<?php
declare(strict_types=1);

namespace VP3\Network;

use PDO;

final class CreatorAudienceService
{
    public function __construct(private PDO $db) {}

    public function summary(int $customerId): array
    {
        $metricJoin = "
          LEFT JOIN (SELECT clip_publication_id,COUNT(*) views,SUM(completed=1) completions FROM clip_view_events GROUP BY clip_publication_id) v ON v.clip_publication_id=cp.id
          LEFT JOIN (SELECT clip_publication_id,SUM(engagement_type='open_destination') destination_opens FROM clip_engagement_events GROUP BY clip_publication_id) e ON e.clip_publication_id=cp.id
          LEFT JOIN (SELECT clip_publication_id,SUM(action_type='like') likes,SUM(action_type='save') saves FROM viewer_clip_actions GROUP BY clip_publication_id) a ON a.clip_publication_id=cp.id
          LEFT JOIN (SELECT clip_publication_id,COUNT(*) comments FROM viewer_comments WHERE status='published' GROUP BY clip_publication_id) cm ON cm.clip_publication_id=cp.id";

        $creators = $this->db->prepare(
            "SELECT c.creator_uuid,c.display_name,c.slug,
                    COALESCE(f.followers,0) followers,
                    COALESCE(m.clips,0) clips,COALESCE(m.views,0) views,COALESCE(m.completions,0) completions,
                    COALESCE(m.destination_opens,0) destination_opens,COALESCE(m.likes,0) likes,
                    COALESCE(m.saves,0) saves,COALESCE(m.comments,0) comments
             FROM creators c
             LEFT JOIN (
               SELECT creator_id,COUNT(DISTINCT viewer_id) followers
               FROM viewer_creator_follows WHERE viewer_id IS NOT NULL GROUP BY creator_id
             ) f ON f.creator_id=c.id
             LEFT JOIN (
               SELECT cp.creator_id,COUNT(*) clips,
                      SUM(COALESCE(v.views,0)) views,SUM(COALESCE(v.completions,0)) completions,
                      SUM(COALESCE(e.destination_opens,0)) destination_opens,SUM(COALESCE(a.likes,0)) likes,
                      SUM(COALESCE(a.saves,0)) saves,SUM(COALESCE(cm.comments,0)) comments
               FROM clip_publications cp {$metricJoin}
               WHERE cp.creator_id IS NOT NULL GROUP BY cp.creator_id
             ) m ON m.creator_id=c.id
             WHERE c.customer_id=? ORDER BY followers DESC,c.display_name"
        );
        $creators->execute([$customerId]);

        $shows = $this->db->prepare(
            "SELECT s.show_uuid,s.title,s.slug,
                    COALESCE(f.followers,0) followers,
                    COALESCE(m.clips,0) clips,COALESCE(m.views,0) views,COALESCE(m.completions,0) completions,
                    COALESCE(m.destination_opens,0) destination_opens,COALESCE(m.likes,0) likes,
                    COALESCE(m.saves,0) saves,COALESCE(m.comments,0) comments
             FROM shows s
             LEFT JOIN (
               SELECT show_id,COUNT(DISTINCT viewer_id) followers
               FROM viewer_show_follows WHERE viewer_id IS NOT NULL GROUP BY show_id
             ) f ON f.show_id=s.id
             LEFT JOIN (
               SELECT cp.show_id,COUNT(*) clips,
                      SUM(COALESCE(v.views,0)) views,SUM(COALESCE(v.completions,0)) completions,
                      SUM(COALESCE(e.destination_opens,0)) destination_opens,SUM(COALESCE(a.likes,0)) likes,
                      SUM(COALESCE(a.saves,0)) saves,SUM(COALESCE(cm.comments,0)) comments
               FROM clip_publications cp {$metricJoin}
               WHERE cp.show_id IS NOT NULL GROUP BY cp.show_id
             ) m ON m.show_id=s.id
             WHERE s.customer_id=? ORDER BY followers DESC,s.title"
        );
        $shows->execute([$customerId]);

        $clips = $this->db->prepare(
            "SELECT cp.publication_uuid,cp.title,cp.published_at,c.display_name creator_name,s.title show_title,
                    COALESCE(v.views,0) views,COALESCE(v.completions,0) completions,
                    COALESCE(e.destination_opens,0) destination_opens,COALESCE(a.likes,0) likes,
                    COALESCE(a.saves,0) saves,COALESCE(cm.comments,0) comments
             FROM clip_publications cp
             JOIN public_platform_listings ppl ON ppl.id=cp.public_listing_id
             LEFT JOIN creators c ON c.id=cp.creator_id
             LEFT JOIN shows s ON s.id=cp.show_id
             {$metricJoin}
             WHERE ppl.customer_id=?
             ORDER BY cp.published_at DESC,cp.id DESC LIMIT 100"
        );
        $clips->execute([$customerId]);

        return ['creators'=>$creators->fetchAll(),'shows'=>$shows->fetchAll(),'clips'=>$clips->fetchAll()];
    }
}
