SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_bridge_credentials (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bridge_uuid CHAR(36) NOT NULL,
 public_listing_id BIGINT UNSIGNED NOT NULL,
 license_activation_id BIGINT UNSIGNED NOT NULL,
 secret_ciphertext TEXT NOT NULL,
 secret_nonce VARCHAR(64) NOT NULL,
 secret_tag VARCHAR(64) NOT NULL,
 status ENUM('active','rotated','revoked','expired') NOT NULL DEFAULT 'active',
 scopes_json JSON NOT NULL,
 issued_by BIGINT UNSIGNED NOT NULL,
 expires_at DATETIME NULL,
 last_used_at DATETIME NULL,
 rotated_at DATETIME NULL,
 revoked_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_platform_bridge_uuid (bridge_uuid),
 KEY idx_platform_bridge_listing_status (public_listing_id,status),
 KEY idx_platform_bridge_activation_status (license_activation_id,status),
 CONSTRAINT fk_platform_bridge_listing FOREIGN KEY (public_listing_id) REFERENCES public_platform_listings(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_platform_bridge_activation FOREIGN KEY (license_activation_id) REFERENCES license_activations(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_platform_bridge_admin FOREIGN KEY (issued_by) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_bridge_nonces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bridge_credential_id BIGINT UNSIGNED NOT NULL,
 nonce_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_platform_bridge_nonce (bridge_credential_id,nonce_hash),
 KEY idx_platform_bridge_nonce_expiry (expires_at),
 CONSTRAINT fk_platform_bridge_nonce_credential FOREIGN KEY (bridge_credential_id) REFERENCES platform_bridge_credentials(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_bridge_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bridge_credential_id BIGINT UNSIGNED NOT NULL,
 request_uuid CHAR(36) NOT NULL,
 operation VARCHAR(80) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 response_status SMALLINT UNSIGNED NOT NULL DEFAULT 200,
 response_json JSON NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_platform_bridge_request (bridge_credential_id,request_uuid),
 KEY idx_platform_bridge_request_created (created_at),
 CONSTRAINT fk_platform_bridge_request_credential FOREIGN KEY (bridge_credential_id) REFERENCES platform_bridge_credentials(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
