SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS viewer_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 viewer_uuid CHAR(36) NOT NULL,
 email VARCHAR(190) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 display_name VARCHAR(150) NOT NULL,
 handle VARCHAR(40) NOT NULL,
 avatar_url VARCHAR(1000) NULL,
 bio VARCHAR(500) NULL,
 profile_visibility ENUM('public','private') NOT NULL DEFAULT 'public',
 status ENUM('pending','active','suspended','deleted') NOT NULL DEFAULT 'pending',
 email_verified_at DATETIME NULL,
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewers_uuid (viewer_uuid),
 UNIQUE KEY uq_viewers_email (email),
 UNIQUE KEY uq_viewers_handle (handle),
 KEY idx_viewers_status_created (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_email_verifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 viewer_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_email_token (token_hash),
 KEY idx_viewer_email_expiry (viewer_id,expires_at),
 CONSTRAINT fk_viewer_email_account FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_password_resets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 viewer_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_reset_token (token_hash),
 KEY idx_viewer_reset_expiry (viewer_id,expires_at),
 CONSTRAINT fk_viewer_reset_account FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_remember_tokens (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 token_uuid CHAR(36) NOT NULL,
 viewer_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 user_agent_hash CHAR(64) NULL,
 expires_at DATETIME NOT NULL,
 last_used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_remember_uuid (token_uuid),
 KEY idx_viewer_remember_account (viewer_id,expires_at),
 CONSTRAINT fk_viewer_remember_account FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_clip_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_key VARCHAR(80) NOT NULL,
 viewer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 action_type ENUM('like','save','hide') NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_clip_action (identity_key,clip_publication_id,action_type),
 KEY idx_viewer_clip_actions_viewer (viewer_id,action_type,created_at),
 KEY idx_viewer_clip_actions_session (session_hash,action_type,created_at),
 CONSTRAINT fk_viewer_clip_actions_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_clip_actions_clip FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_creator_follows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_key VARCHAR(80) NOT NULL,
 viewer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 creator_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_creator_follow (identity_key,creator_id),
 KEY idx_viewer_creator_follows_viewer (viewer_id,created_at),
 KEY idx_viewer_creator_follows_session (session_hash,created_at),
 CONSTRAINT fk_viewer_creator_follows_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_creator_follows_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_show_follows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_key VARCHAR(80) NOT NULL,
 viewer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 show_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_show_follow (identity_key,show_id),
 KEY idx_viewer_show_follows_viewer (viewer_id,created_at),
 KEY idx_viewer_show_follows_session (session_hash,created_at),
 CONSTRAINT fk_viewer_show_follows_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_show_follows_show FOREIGN KEY (show_id) REFERENCES shows(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_watch_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 identity_key VARCHAR(80) NOT NULL,
 viewer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 watch_seconds INT UNSIGNED NOT NULL DEFAULT 0,
 completion_count INT UNSIGNED NOT NULL DEFAULT 0,
 view_count INT UNSIGNED NOT NULL DEFAULT 0,
 skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
 first_viewed_at DATETIME NOT NULL,
 last_viewed_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_watch_history (identity_key,clip_publication_id),
 KEY idx_viewer_watch_viewer_recent (viewer_id,last_viewed_at),
 KEY idx_viewer_watch_session_recent (session_hash,last_viewed_at),
 CONSTRAINT fk_viewer_watch_account FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_watch_clip FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_session_claims (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 viewer_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) NOT NULL,
 claimed_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_session_claim (viewer_id,session_hash),
 KEY idx_viewer_session_hash (session_hash),
 CONSTRAINT fk_viewer_session_claim_account FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS vp3_add_viewer_id_to_clip_events;
DELIMITER //
CREATE PROCEDURE vp3_add_viewer_id_to_clip_events()
BEGIN
 IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clip_view_events' AND COLUMN_NAME='viewer_id') THEN
  ALTER TABLE clip_view_events ADD COLUMN viewer_id BIGINT UNSIGNED NULL AFTER clip_publication_id,
   ADD KEY idx_clip_views_viewer_created (viewer_id,created_at),
   ADD CONSTRAINT fk_clip_views_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL;
 END IF;
 IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clip_engagement_events' AND COLUMN_NAME='viewer_id') THEN
  ALTER TABLE clip_engagement_events ADD COLUMN viewer_id BIGINT UNSIGNED NULL AFTER clip_publication_id,
   ADD KEY idx_clip_engagement_viewer_created (viewer_id,created_at),
   ADD CONSTRAINT fk_clip_engagement_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL;
 END IF;
END//
DELIMITER ;
CALL vp3_add_viewer_id_to_clip_events();
DROP PROCEDURE vp3_add_viewer_id_to_clip_events;
