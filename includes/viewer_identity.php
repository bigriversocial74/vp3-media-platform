<?php
declare(strict_types=1);

function vp3_viewer_cookie_name(): string
{
    return 'vp3_viewer_anon';
}

function vp3_viewer_anonymous_token(): string
{
    $name = vp3_viewer_cookie_name();
    $token = trim((string)($_COOKIE[$name] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{40,100}$/', $token)) {
        $token = vp3_secure_token(36);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie($name, $token, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => (bool)vp3_config('app.session_secure', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[$name] = $token;
        }
    }
    return $token;
}

function vp3_viewer_session_hash(): string
{
    return hash_hmac('sha256', vp3_viewer_anonymous_token(), (string)vp3_config('security.app_key'));
}

function vp3_viewer_identity(): array
{
    $viewer = function_exists('vp3_viewer') ? vp3_viewer() : null;
    if ($viewer) {
        return [
            'identity_key' => 'v:' . (int)$viewer['id'],
            'viewer_id' => (int)$viewer['id'],
            'session_hash' => vp3_viewer_session_hash(),
            'viewer' => $viewer,
        ];
    }
    $hash = vp3_viewer_session_hash();
    return ['identity_key' => 's:' . $hash, 'viewer_id' => null, 'session_hash' => $hash, 'viewer' => null];
}

function vp3_viewer_claim_anonymous_activity(int $viewerId): void
{
    if (!vp3_db_available() || $viewerId < 1) {
        return;
    }
    $sessionHash = vp3_viewer_session_hash();
    $oldKey = 's:' . $sessionHash;
    $newKey = 'v:' . $viewerId;

    vp3_transaction(function (PDO $db) use ($viewerId, $sessionHash, $oldKey, $newKey): void {
        $tables = [
            'viewer_clip_actions' => ['clip_publication_id','action_type'],
            'viewer_creator_follows' => ['creator_id'],
            'viewer_show_follows' => ['show_id'],
        ];
        foreach ($tables as $table => $columns) {
            $columnList = implode(',', $columns);
            $selectList = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
            $sql = "INSERT IGNORE INTO {$table} (identity_key,viewer_id,session_hash,{$columnList},created_at" . ($table === 'viewer_clip_actions' ? ',updated_at' : '') . ") " .
                   "SELECT ?,?,NULL,{$selectList},created_at" . ($table === 'viewer_clip_actions' ? ',NOW()' : '') . " FROM {$table} WHERE identity_key=?";
            $db->prepare($sql)->execute([$newKey, $viewerId, $oldKey]);
            $db->prepare("DELETE FROM {$table} WHERE identity_key=?")->execute([$oldKey]);
        }

        $history = $db->prepare('SELECT * FROM viewer_watch_history WHERE identity_key=?');
        $history->execute([$oldKey]);
        foreach ($history->fetchAll() as $row) {
            $db->prepare('INSERT INTO viewer_watch_history (identity_key,viewer_id,session_hash,clip_publication_id,watch_seconds,completion_count,view_count,skipped_count,first_viewed_at,last_viewed_at) VALUES (?,?,NULL,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE watch_seconds=watch_seconds+VALUES(watch_seconds),completion_count=completion_count+VALUES(completion_count),view_count=view_count+VALUES(view_count),skipped_count=skipped_count+VALUES(skipped_count),first_viewed_at=LEAST(first_viewed_at,VALUES(first_viewed_at)),last_viewed_at=GREATEST(last_viewed_at,VALUES(last_viewed_at))')
                ->execute([$newKey,$viewerId,(int)$row['clip_publication_id'],(int)$row['watch_seconds'],(int)$row['completion_count'],(int)$row['view_count'],(int)$row['skipped_count'],$row['first_viewed_at'],$row['last_viewed_at']]);
        }
        $db->prepare('DELETE FROM viewer_watch_history WHERE identity_key=?')->execute([$oldKey]);
        $db->prepare('UPDATE clip_view_events SET viewer_id=? WHERE viewer_id IS NULL AND session_hash=?')->execute([$viewerId,$sessionHash]);
        $db->prepare('UPDATE clip_engagement_events SET viewer_id=? WHERE viewer_id IS NULL AND session_hash=?')->execute([$viewerId,$sessionHash]);
        $db->prepare('INSERT IGNORE INTO viewer_session_claims (viewer_id,session_hash,claimed_at) VALUES (?,?,NOW())')->execute([$viewerId,$sessionHash]);
    });
}
