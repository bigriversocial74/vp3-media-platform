SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS vp3_add_admin_theme_preference;
DELIMITER //
CREATE PROCEDURE vp3_add_admin_theme_preference()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'theme_preference'
    ) THEN
        ALTER TABLE admins ADD COLUMN theme_preference ENUM('light','dark','system') NOT NULL DEFAULT 'system' AFTER last_login_at;
    END IF;
END//
DELIMITER ;
CALL vp3_add_admin_theme_preference();
DROP PROCEDURE vp3_add_admin_theme_preference;

CREATE TABLE IF NOT EXISTS creators (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 creator_uuid CHAR(36) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 display_name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 headline VARCHAR(255) NULL,
 bio TEXT NULL,
 avatar_url VARCHAR(1000) NULL,
 cover_url VARCHAR(1000) NULL,
 website_url VARCHAR(1000) NULL,
 social_json JSON NULL,
 verification_status ENUM('pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
 listing_status ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft',
 featured_rank INT UNSIGNED NOT NULL DEFAULT 0,
 clips_syndication_default ENUM('off','manual','public') NOT NULL DEFAULT 'manual',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_creators_uuid (creator_uuid),
 UNIQUE KEY uq_creators_slug (slug),
 KEY idx_creators_customer_status (customer_id,listing_status),
 KEY idx_creators_featured (listing_status,featured_rank),
 CONSTRAINT fk_creators_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 show_uuid CHAR(36) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 short_description VARCHAR(500) NULL,
 description TEXT NULL,
 show_type ENUM('scripted_series','microdrama','reality','podcast','music_series','documentary','live_event','mixed_media') NOT NULL DEFAULT 'scripted_series',
 genre VARCHAR(190) NULL,
 cover_url VARCHAR(1000) NULL,
 hero_url VARCHAR(1000) NULL,
 destination_url VARCHAR(1000) NULL,
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 verification_status ENUM('pending','verified','rejected','suspended') NOT NULL DEFAULT 'pending',
 featured_rank INT UNSIGNED NOT NULL DEFAULT 0,
 launched_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_shows_uuid (show_uuid),
 UNIQUE KEY uq_shows_slug (slug),
 KEY idx_shows_customer_status (customer_id,status),
 KEY idx_shows_featured (status,featured_rank),
 CONSTRAINT fk_shows_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS show_creators (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 show_id BIGINT UNSIGNED NOT NULL,
 creator_id BIGINT UNSIGNED NOT NULL,
 role_name VARCHAR(100) NOT NULL DEFAULT 'Creator',
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_show_creator (show_id,creator_id),
 KEY idx_show_creators_creator (creator_id,sort_order),
 CONSTRAINT fk_show_creators_show FOREIGN KEY (show_id) REFERENCES shows(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_show_creators_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_platform_listings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 listing_uuid CHAR(36) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 license_id BIGINT UNSIGNED NOT NULL,
 hosting_account_id BIGINT UNSIGNED NULL,
 creator_id BIGINT UNSIGNED NULL,
 show_id BIGINT UNSIGNED NULL,
 display_name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 public_domain VARCHAR(253) NOT NULL,
 hosting_type ENUM('self_hosted','vp3_hosted') NOT NULL,
 verification_id VARCHAR(40) NOT NULL,
 verification_status ENUM('pending','verified','suspended','revoked') NOT NULL DEFAULT 'pending',
 listing_status ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft',
 auto_publish_clips TINYINT(1) NOT NULL DEFAULT 0,
 featured_rank INT UNSIGNED NOT NULL DEFAULT 0,
 launched_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_public_listing_uuid (listing_uuid),
 UNIQUE KEY uq_public_listing_slug (slug),
 UNIQUE KEY uq_public_listing_verification (verification_id),
 UNIQUE KEY uq_public_listing_domain (public_domain),
 KEY idx_public_listing_status (listing_status,verification_status,featured_rank),
 KEY idx_public_listing_license (license_id),
 CONSTRAINT fk_public_listing_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_public_listing_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_public_listing_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_public_listing_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_public_listing_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_public_listing_show FOREIGN KEY (show_id) REFERENCES shows(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_publications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 publication_uuid CHAR(36) NOT NULL,
 public_listing_id BIGINT UNSIGNED NOT NULL,
 license_id BIGINT UNSIGNED NOT NULL,
 creator_id BIGINT UNSIGNED NULL,
 show_id BIGINT UNSIGNED NULL,
 source_platform_uuid CHAR(36) NOT NULL,
 source_creator_uuid CHAR(36) NULL,
 source_show_uuid CHAR(36) NULL,
 source_clip_uuid CHAR(36) NOT NULL,
 title VARCHAR(190) NOT NULL,
 caption TEXT NULL,
 media_type ENUM('video','audio') NOT NULL DEFAULT 'video',
 source_media_url VARCHAR(1000) NOT NULL,
 poster_url VARCHAR(1000) NULL,
 destination_url VARCHAR(1000) NOT NULL,
 duration_seconds SMALLINT UNSIGNED NOT NULL,
 aspect_ratio ENUM('9:16','1:1','4:5','16:9') NOT NULL DEFAULT '9:16',
 visibility ENUM('public','unlisted') NOT NULL DEFAULT 'public',
 publication_status ENUM('pending','scheduled','published','withdrawn','suspended') NOT NULL DEFAULT 'pending',
 moderation_status ENUM('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
 rights_status ENUM('pending','confirmed','disputed','expired') NOT NULL DEFAULT 'pending',
 feed_eligible TINYINT(1) NOT NULL DEFAULT 0,
 featured_rank INT UNSIGNED NOT NULL DEFAULT 0,
 scheduled_at DATETIME NULL,
 published_at DATETIME NULL,
 source_updated_at DATETIME NOT NULL,
 last_synced_at DATETIME NOT NULL,
 content_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_clip_publication_uuid (publication_uuid),
 UNIQUE KEY uq_clip_source_identity (source_platform_uuid,source_clip_uuid),
 KEY idx_clip_feed (publication_status,moderation_status,rights_status,feed_eligible,published_at),
 KEY idx_clip_creator_show (creator_id,show_id,published_at),
 KEY idx_clip_listing_status (public_listing_id,publication_status),
 CONSTRAINT fk_clip_listing FOREIGN KEY (public_listing_id) REFERENCES public_platform_listings(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_clip_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_clip_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_clip_show FOREIGN KEY (show_id) REFERENCES shows(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT chk_clip_duration CHECK (duration_seconds > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_rights_declarations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 rights_owner_name VARCHAR(190) NOT NULL,
 territory VARCHAR(100) NOT NULL DEFAULT 'worldwide',
 expires_at DATETIME NULL,
 declaration_version VARCHAR(30) NOT NULL,
 confirmed_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_clip_rights (clip_publication_id),
 KEY idx_clip_rights_expiration (expires_at),
 CONSTRAINT fk_clip_rights_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_moderation_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 admin_id BIGINT UNSIGNED NOT NULL,
 decision ENUM('pending','approved','rejected','flagged') NOT NULL,
 review_notes TEXT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_clip_reviews_publication (clip_publication_id,created_at),
 CONSTRAINT fk_clip_reviews_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_clip_reviews_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_feed_placements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 feed_key VARCHAR(50) NOT NULL,
 placement_status ENUM('active','paused','expired') NOT NULL DEFAULT 'active',
 rank_score DECIMAL(12,4) NOT NULL DEFAULT 0,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_clip_feed_placement (clip_publication_id,feed_key),
 KEY idx_feed_placement_feed (feed_key,placement_status,rank_score),
 CONSTRAINT fk_clip_feed_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) NULL,
 reason ENUM('copyright','harassment','adult_content','violence','spam','misleading','other') NOT NULL,
 details VARCHAR(1000) NULL,
 report_status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
 resolved_by BIGINT UNSIGNED NULL,
 resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_clip_reports_status (report_status,created_at),
 KEY idx_clip_reports_publication (clip_publication_id,report_status),
 CONSTRAINT fk_clip_reports_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_clip_reports_admin FOREIGN KEY (resolved_by) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_view_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) NOT NULL,
 viewer_ip_hash CHAR(64) NULL,
 user_agent_hash CHAR(64) NULL,
 watch_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 completed TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 KEY idx_clip_views_publication_created (clip_publication_id,created_at),
 KEY idx_clip_views_session (session_hash,created_at),
 CONSTRAINT fk_clip_views_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_engagement_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 session_hash CHAR(64) NOT NULL,
 engagement_type ENUM('like','save','share','open_destination') NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_clip_engagement_publication (clip_publication_id,engagement_type,created_at),
 KEY idx_clip_engagement_session (session_hash,created_at),
 CONSTRAINT fk_clip_engagement_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clip_sync_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(50) NOT NULL,
 event_status ENUM('success','failed','ignored') NOT NULL,
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL,
 KEY idx_clip_sync_publication (clip_publication_id,created_at),
 KEY idx_clip_sync_status (event_status,created_at),
 CONSTRAINT fk_clip_sync_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creator_follows (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 creator_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_creator_follow_customer (creator_id,customer_id),
 KEY idx_creator_follow_session (creator_id,session_hash),
 CONSTRAINT fk_creator_follows_creator FOREIGN KEY (creator_id) REFERENCES creators(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_creator_follows_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_clips (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 clip_publication_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 session_hash CHAR(64) NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_saved_clip_customer (clip_publication_id,customer_id),
 KEY idx_saved_clip_session (clip_publication_id,session_hash),
 CONSTRAINT fk_saved_clip_publication FOREIGN KEY (clip_publication_id) REFERENCES clip_publications(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_saved_clip_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
