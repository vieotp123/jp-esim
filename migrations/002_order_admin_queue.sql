-- Phase: retail bank fulfillment foundation
-- Idempotent: safe to re-run.

CREATE TABLE IF NOT EXISTS order_admin_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(32) NOT NULL,
  ref_id VARCHAR(64) NOT NULL,
  status ENUM('open','resolved','ignored') NOT NULL DEFAULT 'open',
  error_summary TEXT NULL,
  payload_redacted TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolver_note VARCHAR(1000) NULL,
  KEY idx_oaq_status (status),
  KEY idx_oaq_kind (kind),
  KEY idx_oaq_ref (ref_id),
  KEY idx_oaq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
