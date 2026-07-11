SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admins (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('owner','super_admin','operations','support','billing') NOT NULL DEFAULT 'operations',
 status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active',
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_admins_email (email),
 KEY idx_admins_status_role (status,role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_uuid CHAR(36) NOT NULL,
 name VARCHAR(150) NOT NULL,
 company_name VARCHAR(190) NULL,
 email VARCHAR(190) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 phone VARCHAR(40) NULL,
 status ENUM('pending','active','suspended','closed') NOT NULL DEFAULT 'pending',
 email_verified_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_customers_uuid (customer_uuid),
 UNIQUE KEY uq_customers_email (email),
 KEY idx_customers_status_created (status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id VARCHAR(100) NOT NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 product_type VARCHAR(100) NOT NULL,
 current_version VARCHAR(50) NULL,
 status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
 self_hosted_enabled TINYINT(1) NOT NULL DEFAULT 0,
 hosted_enabled TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_products_product_id (product_id),
 UNIQUE KEY uq_products_slug (slug),
 KEY idx_products_status_type (status,product_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_plans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 plan_key VARCHAR(100) NOT NULL,
 name VARCHAR(190) NOT NULL,
 hosting_type ENUM('self_hosted','vp3_hosted','enterprise') NOT NULL,
 billing_type ENUM('one_time','subscription','custom') NOT NULL,
 price_cents INT UNSIGNED NOT NULL DEFAULT 0,
 billing_interval ENUM('none','monthly','yearly','custom') NOT NULL DEFAULT 'none',
 activation_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 updates_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
 support_level VARCHAR(100) NOT NULL DEFAULT 'standard',
 status ENUM('draft','active','retired') NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_product_plan_key (product_id,plan_key),
 KEY idx_plans_status_hosting (status,hosting_type),
 CONSTRAINT fk_plans_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(50) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 order_status ENUM('pending','processing','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
 payment_status ENUM('pending','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
 subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
 tax_cents INT UNSIGNED NOT NULL DEFAULT 0,
 total_cents INT UNSIGNED NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'USD',
 payment_provider VARCHAR(50) NULL,
 payment_reference VARCHAR(190) NULL,
 idempotency_key VARCHAR(190) NULL,
 created_at DATETIME NOT NULL,
 paid_at DATETIME NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_orders_number (order_number),
 UNIQUE KEY uq_orders_provider_reference (payment_provider,payment_reference),
 UNIQUE KEY uq_orders_idempotency (idempotency_key),
 KEY idx_orders_customer_created (customer_id,created_at),
 KEY idx_orders_payment_status (payment_status,order_status),
 CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 plan_id BIGINT UNSIGNED NULL,
 item_name VARCHAR(190) NOT NULL,
 quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
 total_cents INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 KEY idx_order_items_order (order_id),
 KEY idx_order_items_product_plan (product_id,plan_id),
 CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_order_items_plan FOREIGN KEY (plan_id) REFERENCES product_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS licenses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 license_uuid CHAR(36) NOT NULL,
 license_key_hash CHAR(64) NOT NULL,
 license_key_prefix VARCHAR(20) NOT NULL,
 license_fingerprint VARCHAR(20) NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 plan_id BIGINT UNSIGNED NULL,
 edition VARCHAR(100) NOT NULL,
 status ENUM('pending','active','suspended','revoked','expired','development') NOT NULL DEFAULT 'pending',
 max_activations SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 activation_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 issued_at DATETIME NULL,
 expires_at DATE NULL,
 updates_until DATE NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_licenses_uuid (license_uuid),
 UNIQUE KEY uq_licenses_hash (license_key_hash),
 KEY idx_licenses_customer_status (customer_id,status),
 KEY idx_licenses_product_status (product_id,status),
 KEY idx_licenses_expiration (expires_at,updates_until),
 CONSTRAINT fk_licenses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_licenses_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_licenses_plan FOREIGN KEY (plan_id) REFERENCES product_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_license_activation_count CHECK (activation_count <= max_activations)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_domains (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 license_id BIGINT UNSIGNED NOT NULL,
 domain VARCHAR(253) NOT NULL,
 domain_type ENUM('production','staging','development','wildcard') NOT NULL DEFAULT 'production',
 status ENUM('pending','active','disabled') NOT NULL DEFAULT 'active',
 verified_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_license_domain (license_id,domain),
 KEY idx_license_domains_domain_status (domain,status),
 CONSTRAINT fk_license_domains_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_activations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 license_id BIGINT UNSIGNED NOT NULL,
 installation_uuid CHAR(36) NOT NULL,
 domain VARCHAR(253) NOT NULL,
 ip_address VARCHAR(45) NULL,
 product_version VARCHAR(50) NULL,
 status ENUM('active','deactivated','blocked') NOT NULL DEFAULT 'active',
 installation_token_hash CHAR(64) NOT NULL,
 activated_at DATETIME NOT NULL,
 last_validated_at DATETIME NULL,
 deactivated_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_license_installation (license_id,installation_uuid),
 KEY idx_activations_domain_status (domain,status),
 KEY idx_activations_last_validated (last_validated_at),
 CONSTRAINT fk_activations_license FOREIGN KEY (license_id) REFERENCES licenses(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extended controls: api_nonces audit_logs payment_webhook_events billing_subscriptions support_ticket_messages
SOURCE database/schema-operations.sql;
SOURCE database/schema-security.sql;

SET FOREIGN_KEY_CHECKS=1;
