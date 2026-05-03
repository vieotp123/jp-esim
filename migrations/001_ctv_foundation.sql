-- Phase B - CTV / Reseller foundation
-- Idempotent: safe to re-run on MySQL 8+.

CREATE TABLE IF NOT EXISTS ctv_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1, -- 1=active, 0=disabled
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  email_verify_token VARCHAR(128) NULL,
  email_verify_sent_at DATETIME NULL,
  email_verified_at DATETIME NULL,
  tier_id BIGINT UNSIGNED NULL,
  discount_per_esim INT NOT NULL DEFAULT 0,
  balance BIGINT NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ctv_email (email),
  KEY idx_ctv_status (status),
  KEY idx_ctv_tier (tier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  discount_per_esim INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tier_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_wallet_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NOT NULL,
  amount BIGINT NOT NULL, -- positive=credit, negative=debit
  balance_after BIGINT NOT NULL,
  reason VARCHAR(64) NOT NULL, -- e.g. admin_credit, order_charge, topup_charge, refund
  ref_type VARCHAR(32) NULL, -- ctv_order, ctv_topup, manual, etc
  ref_id VARCHAR(64) NULL,
  note VARCHAR(255) NULL,
  admin_user VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ctv_wt_ctv (ctv_id, created_at),
  KEY idx_ctv_wt_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_order_id VARCHAR(16) NOT NULL,
  ctv_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  pack_code VARCHAR(64) NULL,
  carrier VARCHAR(32) NULL,
  plan_name VARCHAR(190) NULL,
  retail_price INT NOT NULL,
  discount INT NOT NULL DEFAULT 0,
  ctv_price INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  total_charge BIGINT NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 0, -- 0=pending,1=processing,2=success,3=failed
  source VARCHAR(16) NOT NULL DEFAULT 'panel', -- panel | api
  client_ref VARCHAR(64) NULL,
  provider_order_no VARCHAR(128) NULL,
  provider_transaction_id VARCHAR(128) NULL,
  iccid VARCHAR(64) NULL,
  error_message TEXT NULL,
  needs_admin TINYINT(1) NOT NULL DEFAULT 0,
  email VARCHAR(190) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ctv_order_id (ctv_order_id),
  UNIQUE KEY uniq_ctv_client_ref (ctv_id, client_ref),
  KEY idx_ctv_orders_ctv (ctv_id, created_at),
  KEY idx_ctv_orders_status (status, needs_admin),
  KEY idx_ctv_orders_iccid (iccid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_esims (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NOT NULL,
  ctv_order_id VARCHAR(16) NOT NULL,
  iccid VARCHAR(64) NULL,
  qr_code_url TEXT NULL,
  short_url TEXT NULL,
  ac VARCHAR(255) NULL,
  apn VARCHAR(64) NULL,
  total_volume BIGINT NULL,
  total_duration INT NULL,
  duration_unit VARCHAR(16) NULL,
  expired_time VARCHAR(64) NULL,
  package_code VARCHAR(64) NULL,
  package_name VARCHAR(190) NULL,
  carrier VARCHAR(32) NULL,
  smdp_status VARCHAR(32) NULL,
  esim_status VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ctv_esims_ctv (ctv_id, created_at),
  KEY idx_ctv_esims_iccid (iccid),
  KEY idx_ctv_esims_order (ctv_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_topup_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_topup_id VARCHAR(16) NOT NULL,
  ctv_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  iccid VARCHAR(64) NOT NULL,
  carrier VARCHAR(32) NULL,
  plan_name VARCHAR(190) NULL,
  retail_price INT NOT NULL,
  discount INT NOT NULL DEFAULT 0,
  ctv_price INT NOT NULL,
  total_charge BIGINT NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 0,
  source VARCHAR(16) NOT NULL DEFAULT 'panel',
  client_ref VARCHAR(64) NULL,
  provider_response_json MEDIUMTEXT NULL,
  error_message TEXT NULL,
  needs_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ctv_topup_id (ctv_topup_id),
  UNIQUE KEY uniq_ctv_topup_ref (ctv_id, client_ref),
  KEY idx_ctv_topups_ctv (ctv_id, created_at),
  KEY idx_ctv_topups_iccid (iccid),
  KEY idx_ctv_topups_status (status, needs_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_api_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(64) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  key_hash VARCHAR(128) NOT NULL,
  last_used_at DATETIME NULL,
  last_used_ip VARCHAR(45) NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  UNIQUE KEY uniq_key_hash (key_hash),
  KEY idx_ctv_api_keys_ctv (ctv_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_api_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NULL,
  api_key_id BIGINT UNSIGNED NULL,
  endpoint VARCHAR(64) NOT NULL,
  method VARCHAR(8) NOT NULL,
  ip VARCHAR(45) NULL,
  request_summary TEXT NULL,
  response_status INT NULL,
  response_summary TEXT NULL,
  duration_ms INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ctv_api_logs_ctv (ctv_id, created_at),
  KEY idx_ctv_api_logs_endpoint (endpoint, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_provider_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NULL,
  ref_type VARCHAR(32) NOT NULL, -- ctv_order | ctv_topup
  ref_id VARCHAR(64) NULL,
  endpoint VARCHAR(64) NOT NULL, -- order|topup|query
  request_redacted MEDIUMTEXT NULL,
  response_redacted MEDIUMTEXT NULL,
  http_status INT NULL,
  success TINYINT(1) NULL,
  error_message TEXT NULL,
  duration_ms INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ctv_provider_logs_ref (ref_type, ref_id),
  KEY idx_ctv_provider_logs_ctv (ctv_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ctv_sessions (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  ctv_id BIGINT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  KEY idx_ctv_sessions_ctv (ctv_id),
  KEY idx_ctv_sessions_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
