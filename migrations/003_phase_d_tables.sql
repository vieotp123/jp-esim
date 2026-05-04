-- Phase D: notifications, topup requests, audit log
-- Idempotent: safe to re-run (IF NOT EXISTS).

-- UP --

CREATE TABLE IF NOT EXISTS ctv_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id INT UNSIGNED NOT NULL,
  type VARCHAR(32) NOT NULL DEFAULT 'system',
  title VARCHAR(255) NOT NULL,
  message TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ctvnotif_ctv_read (ctv_id, is_read),
  KEY idx_ctvnotif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_topup_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id INT UNSIGNED NOT NULL,
  amount INT NOT NULL,
  proof_path VARCHAR(500) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolved_by VARCHAR(64) NULL,
  KEY idx_ctvtopreq_ctv (ctv_id),
  KEY idx_ctvtopreq_status (status),
  KEY idx_ctvtopreq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user VARCHAR(64) NOT NULL,
  action VARCHAR(64) NOT NULL,
  target_type VARCHAR(32) NULL,
  target_id VARCHAR(64) NULL,
  details_json TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (admin_user),
  KEY idx_audit_action (action),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN --
-- DROP TABLE IF EXISTS ctv_notifications;
-- DROP TABLE IF EXISTS ctv_topup_requests;
-- DROP TABLE IF EXISTS admin_audit_log;
