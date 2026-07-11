SET NAMES utf8mb4;

ALTER TABLE audit_logs
 MODIFY actor_type ENUM('admin','customer','viewer','system','api') NOT NULL;
