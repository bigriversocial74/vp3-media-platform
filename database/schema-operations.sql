SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS hosting_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 hosting_uuid CHAR(36) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 plan_id BIGINT UNSIGNED NULL,
 license_id BIGINT UNSIGNED NULL,
 order_id BIGINT UNSIGNED NULL,
 subdomain VARCHAR(63) NOT NULL,
 custom_domain VARCHAR(253) NULL,
 hosting_status ENUM('pending','provisioning','active','suspended','cancelled','failed') NOT NULL DEFAULT 'pending',
 installation_status ENUM('not_started','queued','running','waiting_manual','completed','failed') NOT NULL DEFAULT 'not_started',
 installed_version VARCHAR(50) NULL,
 environment ENUM('production','staging','development') NOT NULL DEFAULT 'production',
 provider VARCHAR(50) NOT NULL DEFAULT 'manual',
 created_at DATETIME NOT NULL,
 provisioned_at DATETIME NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_hosting_uuid (hosting_uuid),
 UNIQUE KEY uq_hosting_subdomain (subdomain),
 UNIQUE KEY uq_hosting_custom_domain (custom_domain),
 KEY idx_hosting_customer_status (customer_id,hosting_status),
 KEY idx_hosting_product_status (product_id,hosting_status),
 CONSTRAINT fk_hosting_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_hosting_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_hosting_plan FOREIGN KEY (plan_id) REFERENCES product_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_hosting_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_hosting_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 job_uuid CHAR(36) NOT NULL,
 hosting_account_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 requested_version VARCHAR(50) NULL,
 job_status ENUM('queued','running','waiting_manual','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
 current_step VARCHAR(100) NOT NULL,
 progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
 failure_message VARCHAR(500) NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_installation_job_uuid (job_uuid),
 KEY idx_jobs_hosting_created (hosting_account_id,created_at),
 KEY idx_jobs_status_created (job_status,created_at),
 CONSTRAINT fk_jobs_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_jobs_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_job_progress CHECK (progress_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_job_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 installation_job_id BIGINT UNSIGNED NOT NULL,
 step VARCHAR(100) NOT NULL,
 status ENUM('started','waiting','completed','failed','skipped') NOT NULL,
 message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 KEY idx_job_events_job_created (installation_job_id,created_at),
 CONSTRAINT fk_job_events_job FOREIGN KEY (installation_job_id) REFERENCES installation_jobs(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
