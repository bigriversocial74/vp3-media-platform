<?php
declare(strict_types=1);
namespace VP3\Network;

use PDO;
use RuntimeException;

final class ViewerActionService
{
    public function __construct(private PDO $db) {}

    public function toggleClip(string $publicationUuid, string $action, array $identity): array
    {
        if (!in_array($action, ['like','save','hide'], true)) throw new RuntimeException('invalid_viewer_action');
        $clip = $this->clip($publicationUuid);
        $key = (string)$identity['identity_key'];
        $find = $this->db->prepare('SELECT id FROM viewer_clip_actions WHERE identity_key=? AND clip_publication_id=? AND action_type=? LIMIT 1');
        $find->execute([$key,(int)$clip['id'],$action]);
        $id = (int)$find->fetchColumn();
        if ($id > 0) {
            $this->db->prepare('DELETE FROM viewer_clip_actions WHERE id=?')->execute([$id]);
            return ['active'=>false,'action'=>$action,'publication_uuid'=>$publicationUuid];
        }
        $this->db->prepare('INSERT INTO viewer_clip_actions (identity_key,viewer_id,session_hash,clip_publication_id,action_type,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())')
            ->execute([$key,$identity['viewer_id'],$identity['session_hash'],(int)$clip['id'],$action]);
        if (in_array($action, ['like','save'], true)) {
            $this->db->prepare('INSERT INTO clip_engagement_events (clip_publication_id,viewer_id,session_hash,engagement_type,created_at) VALUES (?,?,?,?,NOW())')
                ->execute([(int)$clip['id'],$identity['viewer_id'],$identity['session_hash'],$action]);
        }
        return ['active'=>true,'action'=>$action,'publication_uuid'=>$publicationUuid];
    }

    public function toggleCreator(string $creatorUuid, array $identity): array
    {
        $stmt = $this->db->prepare("SELECT id FROM creators WHERE creator_uuid=? AND listing_status='published' LIMIT 1");
        $stmt->execute([$creatorUuid]);
        $creatorId = (int)$stmt->fetchColumn();
        if ($creatorId < 1) throw new RuntimeException('creator_not_found');
        return $this->toggleFollow('viewer_creator_follows','creator_id',$creatorId,$creatorUuid,$identity);
    }

    public function toggleShow(string $showUuid, array $identity): array
    {
        $stmt = $this->db->prepare("SELECT id FROM shows WHERE show_uuid=? AND status='published' LIMIT 1");
        $stmt->execute([$showUuid]);
        $showId = (int)$stmt->fetchColumn();
        if ($showId < 1) throw new RuntimeException('show_not_found');
        return $this->toggleFollow('viewer_show_follows','show_id',$showId,$showUuid,$identity);
    }

    public function recordView(string $publicationUuid, int $watchSeconds, bool $completed, bool $skipped, array $identity): array
    {
        $clip = $this->clip($publicationUuid);
        $watchSeconds = max(0, min($watchSeconds, (int)$clip['duration_seconds']));
        $key = (string)$identity['identity_key'];
        $this->db->prepare('INSERT INTO viewer_watch_history (identity_key,viewer_id,session_hash,clip_publication_id,watch_seconds,completion_count,view_count,skipped_count,first_viewed_at,last_viewed_at) VALUES (?,?,?,?,?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE viewer_id=VALUES(viewer_id),session_hash=VALUES(session_hash),watch_seconds=watch_seconds+VALUES(watch_seconds),completion_count=completion_count+VALUES(completion_count),view_count=view_count+1,skipped_count=skipped_count+VALUES(skipped_count),last_viewed_at=NOW()')
            ->execute([$key,$identity['viewer_id'],$identity['session_hash'],(int)$clip['id'],$watchSeconds,$completed?1:0,$skipped?1:0]);
        $this->db->prepare('INSERT INTO clip_view_events (clip_publication_id,viewer_id,session_hash,viewer_ip_hash,user_agent_hash,watch_seconds,completed,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([(int)$clip['id'],$identity['viewer_id'],$identity['session_hash'],hash('sha256',\vp3_client_ip()),hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'')),$watchSeconds,$completed?1:0]);
        return ['recorded'=>true,'publication_uuid'=>$publicationUuid];
    }

    public function library(int $viewerId, string $type, int $limit = 100): array
    {
        $limit = max(1,min($limit,200));
        if ($type === 'history') {
            $sql = "SELECT cp.publication_uuid,cp.title,cp.caption,cp.poster_url,cp.source_media_url,cp.duration_seconds,c.display_name creator_name,c.slug creator_slug,s.title show_title,s.slug show_slug,vwh.watch_seconds,vwh.completion_count,vwh.view_count,vwh.last_viewed_at FROM viewer_watch_history vwh JOIN clip_publications cp ON cp.id=vwh.clip_publication_id LEFT JOIN creators c ON c.id=cp.creator_id LEFT JOIN shows s ON s.id=cp.show_id WHERE vwh.viewer_id=? ORDER BY vwh.last_viewed_at DESC LIMIT {$limit}";
            $stmt=$this->db->prepare($sql);$stmt->execute([$viewerId]);return$stmt->fetchAll();
        }
        $action = $type === 'liked' ? 'like' : 'save';
        $sql = "SELECT cp.publication_uuid,cp.title,cp.caption,cp.poster_url,cp.source_media_url,cp.duration_seconds,c.display_name creator_name,c.slug creator_slug,s.title show_title,s.slug show_slug,vca.created_at FROM viewer_clip_actions vca JOIN clip_publications cp ON cp.id=vca.clip_publication_id LEFT JOIN creators c ON c.id=cp.creator_id LEFT JOIN shows s ON s.id=cp.show_id WHERE vca.viewer_id=? AND vca.action_type=? ORDER BY vca.created_at DESC LIMIT {$limit}";
        $stmt=$this->db->prepare($sql);$stmt->execute([$viewerId,$action]);return$stmt->fetchAll();
    }

    public function following(int $viewerId): array
    {
        $creators=$this->db->prepare('SELECT c.creator_uuid,c.slug,c.display_name,c.headline,c.avatar_url,vcf.created_at FROM viewer_creator_follows vcf JOIN creators c ON c.id=vcf.creator_id WHERE vcf.viewer_id=? ORDER BY vcf.created_at DESC');$creators->execute([$viewerId]);
        $shows=$this->db->prepare('SELECT s.show_uuid,s.slug,s.title,s.short_description,s.cover_url,vsf.created_at FROM viewer_show_follows vsf JOIN shows s ON s.id=vsf.show_id WHERE vsf.viewer_id=? ORDER BY vsf.created_at DESC');$shows->execute([$viewerId]);
        return ['creators'=>$creators->fetchAll(),'shows'=>$shows->fetchAll()];
    }

    private function toggleFollow(string $table,string $column,int $targetId,string $uuid,array $identity):array
    {
        $key=(string)$identity['identity_key'];$find=$this->db->prepare("SELECT id FROM {$table} WHERE identity_key=? AND {$column}=? LIMIT 1");$find->execute([$key,$targetId]);$id=(int)$find->fetchColumn();
        if($id>0){$this->db->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);return['active'=>false,'target_uuid'=>$uuid];}
        $this->db->prepare("INSERT INTO {$table} (identity_key,viewer_id,session_hash,{$column},created_at) VALUES (?,?,?,?,NOW())")->execute([$key,$identity['viewer_id'],$identity['session_hash'],$targetId]);
        return['active'=>true,'target_uuid'=>$uuid];
    }

    private function clip(string $uuid): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i',$uuid)) throw new RuntimeException('invalid_clip_identity');
        $stmt=$this->db->prepare("SELECT id,duration_seconds FROM clip_publications WHERE publication_uuid=? AND publication_status='published' AND moderation_status='approved' AND rights_status='confirmed' AND feed_eligible=1 LIMIT 1");$stmt->execute([$uuid]);$row=$stmt->fetch();if(!is_array($row))throw new RuntimeException('clip_not_found');return$row;
    }
}
