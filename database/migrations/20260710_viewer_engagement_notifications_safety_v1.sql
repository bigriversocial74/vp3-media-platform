SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS viewer_comments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 comment_uuid CHAR(36) NOT NULL,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 viewer_id BIGINT UNSIGNED NOT NULL,
 parent_comment_id BIGINT UNSIGNED NULL,
 body VARCHAR(1000) NOT NULL,
 status ENUM('published','hidden','removed') NOT NULL DEFAULT 'published',
 moderation_reason VARCHAR(500) NULL,
 like_count INT UNSIGNED NOT NULL DEFAULT 0,
 reply_count INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 edited_at DATETIME NULL,
 UNIQUE KEY uq_viewer_comment_uuid (comment_uuid),
 KEY idx_viewer_comments_clip_status (clip_publication_id,status,created_at),
 KEY idx_viewer_comments_parent (parent_comment_id,status,created_at),
 KEY idx_viewer_comments_viewer (viewer_id,created_at),
 CONSTRAINT fk_viewer_comments_clip FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_comments_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES viewer_comments(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_comment_reactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 comment_id BIGINT UNSIGNED NOT NULL,
 viewer_id BIGINT UNSIGNED NOT NULL,
 reaction_type ENUM('like') NOT NULL DEFAULT 'like',
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_comment_reaction (comment_id,viewer_id,reaction_type),
 KEY idx_comment_reactions_viewer (viewer_id,created_at),
 CONSTRAINT fk_comment_reactions_comment FOREIGN KEY (comment_id) REFERENCES viewer_comments(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_comment_reactions_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_blocks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 blocker_viewer_id BIGINT UNSIGNED NOT NULL,
 blocked_viewer_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_block (blocker_viewer_id,blocked_viewer_id),
 KEY idx_viewer_blocks_blocked (blocked_viewer_id,created_at),
 CONSTRAINT fk_viewer_blocks_blocker FOREIGN KEY (blocker_viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_blocks_blocked FOREIGN KEY (blocked_viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT chk_viewer_block_self CHECK (blocker_viewer_id <> blocked_viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_mutes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 viewer_id BIGINT UNSIGNED NOT NULL,
 target_type ENUM('creator','show') NOT NULL,
 target_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_mute (viewer_id,target_type,target_id),
 KEY idx_viewer_mutes_target (target_type,target_id,created_at),
 CONSTRAINT fk_viewer_mutes_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_notification_preferences (
 viewer_id BIGINT UNSIGNED PRIMARY KEY,
 in_app_replies TINYINT(1) NOT NULL DEFAULT 1,
 in_app_comment_likes TINYINT(1) NOT NULL DEFAULT 1,
 in_app_new_clips TINYINT(1) NOT NULL DEFAULT 1,
 email_digest ENUM('off','daily','weekly') NOT NULL DEFAULT 'off',
 updated_at DATETIME NOT NULL,
 CONSTRAINT fk_viewer_notification_preferences FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notification_uuid CHAR(36) NOT NULL,
 viewer_id BIGINT UNSIGNED NOT NULL,
 notification_type ENUM('reply','comment_like','new_clip','moderation','system') NOT NULL,
 actor_viewer_id BIGINT UNSIGNED NULL,
 creator_id BIGINT UNSIGNED NULL,
 show_id BIGINT UNSIGNED NULL,
 clip_publication_id BIGINT UNSIGNED NULL,
 comment_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 body VARCHAR(500) NULL,
 destination_path VARCHAR(500) NOT NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_notification_uuid (notification_uuid),
 KEY idx_viewer_notifications_inbox (viewer_id,read_at,created_at),
 KEY idx_viewer_notifications_clip (clip_publication_id,created_at),
 CONSTRAINT fk_viewer_notifications_viewer FOREIGN KEY (viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_notifications_actor FOREIGN KEY (actor_viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_viewer_notifications_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_viewer_notifications_show FOREIGN KEY (show_id) REFERENCES shows(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_viewer_notifications_clip FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_viewer_notifications_comment FOREIGN KEY (comment_id) REFERENCES viewer_comments(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_notification_dispatches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
 dispatched_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_notification_dispatch_clip (clip_publication_id),
 CONSTRAINT fk_viewer_notification_dispatch_clip FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS viewer_comment_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 comment_id BIGINT UNSIGNED NOT NULL,
 reporter_viewer_id BIGINT UNSIGNED NOT NULL,
 reason ENUM('harassment','spam','hate','sexual_content','violence','misinformation','privacy','other') NOT NULL,
 details VARCHAR(1000) NULL,
 report_status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
 resolved_by BIGINT UNSIGNED NULL,
 resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_viewer_comment_reporter (comment_id,reporter_viewer_id),
 KEY idx_viewer_comment_reports_status (report_status,created_at),
 CONSTRAINT fk_viewer_comment_reports_comment FOREIGN KEY (comment_id) REFERENCES viewer_comments(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_comment_reports_viewer FOREIGN KEY (reporter_viewer_id) REFERENCES viewer_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_viewer_comment_reports_admin FOREIGN KEY (resolved_by) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
