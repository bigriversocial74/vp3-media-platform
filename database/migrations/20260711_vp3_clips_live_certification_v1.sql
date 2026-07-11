SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_bridge_certifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 certification_uuid CHAR(36) NOT NULL,
 bridge_credential_id BIGINT UNSIGNED NOT NULL,
 status ENUM('submitted','passed','failed','approved','revoked') NOT NULL DEFAULT 'submitted',
 certification_version VARCHAR(20) NOT NULL DEFAULT '1.0',
 checks_json JSON NOT NULL,
 source_report_json JSON NULL,
 failure_summary VARCHAR(1000) NULL,
 submitted_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 approved_at DATETIME NULL,
 approved_by BIGINT UNSIGNED NULL,
 expires_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_platform_bridge_certification_uuid (certification_uuid),
 KEY idx_bridge_certification_credential_status (bridge_credential_id,status,created_at),
 KEY idx_bridge_certification_expiry (status,expires_at),
 CONSTRAINT fk_bridge_certification_credential FOREIGN KEY (bridge_credential_id) REFERENCES platform_bridge_credentials(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_bridge_certification_admin FOREIGN KEY (approved_by) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_bridge_certification_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 certification_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 event_status ENUM('success','failed','ignored') NOT NULL,
 actor_type ENUM('source','admin','system') NOT NULL DEFAULT 'system',
 actor_id BIGINT UNSIGNED NULL,
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL,
 KEY idx_bridge_certification_event (certification_id,created_at),
 KEY idx_bridge_certification_event_status (event_status,created_at),
 CONSTRAINT fk_bridge_certification_event_cert FOREIGN KEY (certification_id) REFERENCES platform_bridge_certifications(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
