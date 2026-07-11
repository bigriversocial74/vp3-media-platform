SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS product_releases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 version VARCHAR(50) NOT NULL,
 release_channel ENUM('stable','beta','development') NOT NULL DEFAULT 'stable',
 commit_sha VARCHAR(64) NULL,
 artifact_path VARCHAR(500) NOT NULL,
 artifact_sha256 CHAR(64) NOT NULL,
 release_notes TEXT NULL,
 minimum_php_version VARCHAR(20) NULL,
 minimum_database_version VARCHAR(50) NULL,
 status ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
 published_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_product_release (product_id,version,release_channel),
 KEY idx_releases_product_status_published (product_id,status,published_at),
 CONSTRAINT fk_releases_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_number VARCHAR(50) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 hosting_account_id BIGINT UNSIGNED NULL,
 license_id BIGINT UNSIGNED NULL,
 subject VARCHAR(255) NOT NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 ticket_status ENUM('open','in_progress','waiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 closed_at DATETIME NULL,
 UNIQUE KEY uq_tickets_number (ticket_number),
 KEY idx_tickets_customer_status (customer_id,ticket_status),
 KEY idx_tickets_priority_status (priority,ticket_status),
 CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_tickets_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_tickets_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('customer','admin','system') NOT NULL,
 sender_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_ticket_messages_ticket_created (ticket_id,created_at),
 CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_password_reset_token (token_hash),
 KEY idx_password_resets_customer_expires (customer_id,expires_at),
 CONSTRAINT fk_password_resets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_email_verification_token (token_hash),
 KEY idx_email_verification_customer (customer_id,expires_at),
 CONSTRAINT fk_email_verifications_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_validation_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 license_id BIGINT UNSIGNED NULL,
 action VARCHAR(50) NOT NULL,
 installation_uuid CHAR(36) NULL,
 domain VARCHAR(253) NULL,
 ip_address VARCHAR(45) NULL,
 result VARCHAR(50) NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_validation_license_created (license_id,created_at),
 KEY idx_validation_installation_created (installation_uuid,created_at),
 CONSTRAINT fk_validation_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_nonces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nonce_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_api_nonce_hash (nonce_hash),
 KEY idx_api_nonces_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_uuid CHAR(36) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 plan_id BIGINT UNSIGNED NOT NULL,
 hosting_account_id BIGINT UNSIGNED NULL,
 provider VARCHAR(50) NOT NULL,
 subscription_reference VARCHAR(190) NOT NULL,
 subscription_status ENUM('pending','trialing','active','past_due','paused','cancelled','expired') NOT NULL DEFAULT 'pending',
 current_period_start DATETIME NULL,
 current_period_end DATETIME NULL,
 cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
 renewal_notice_at DATETIME NULL,
 cancelled_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_subscription_uuid (subscription_uuid),
 UNIQUE KEY uq_subscription_provider_reference (provider,subscription_reference),
 KEY idx_subscriptions_customer_status (customer_id,subscription_status),
 KEY idx_subscriptions_renewal (subscription_status,current_period_end,renewal_notice_at),
 CONSTRAINT fk_subscriptions_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE SET NULL,
 CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES product_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_subscriptions_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider VARCHAR(50) NOT NULL,
 event_id VARCHAR(190) NOT NULL,
 event_type VARCHAR(190) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 processing_status ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
 failure_message VARCHAR(500) NULL,
 received_at DATETIME NOT NULL,
 processed_at DATETIME NULL,
 UNIQUE KEY uq_webhook_provider_event (provider,event_id),
 KEY idx_webhooks_status_received (processing_status,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_type ENUM('admin','customer','system','api') NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(100) NULL,
 entity_uuid VARCHAR(100) NULL,
 ip_address VARCHAR(45) NULL,
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL,
 KEY idx_audit_actor_created (actor_type,actor_id,created_at),
 KEY idx_audit_entity_created (entity_type,entity_uuid,created_at),
 KEY idx_audit_action_created (action,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 setting_key VARCHAR(190) NOT NULL,
 setting_value TEXT NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_system_setting_key (setting_key),
 CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (product_id,name,slug,description,product_type,current_version,status,self_hosted_enabled,hosted_enabled,created_at,updated_at)
VALUES ('VP3-STONEFELLOW-001','Stonefellow Membership Platform','stonefellow-membership-platform','Media membership platform with music, video, episodes, subscriptions, merchandise, playlists, progress tracking, administration, commerce, licensing, and monitoring.','media_membership_platform',NULL,'active',1,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),updated_at=NOW();

INSERT INTO product_plans (product_id,plan_key,name,hosting_type,billing_type,price_cents,billing_interval,activation_limit,updates_months,support_level,status,created_at,updated_at)
SELECT id,'self-hosted-standard','Self-hosted Standard','self_hosted','one_time',0,'none',1,12,'standard','active',NOW(),NOW() FROM products WHERE product_id='VP3-STONEFELLOW-001'
ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW();
INSERT INTO product_plans (product_id,plan_key,name,hosting_type,billing_type,price_cents,billing_interval,activation_limit,updates_months,support_level,status,created_at,updated_at)
SELECT id,'vp3-hosted-standard','VP3 Hosted Standard','vp3_hosted','subscription',0,'monthly',1,0,'managed','active',NOW(),NOW() FROM products WHERE product_id='VP3-STONEFELLOW-001'
ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW();

SET FOREIGN_KEY_CHECKS=1;
