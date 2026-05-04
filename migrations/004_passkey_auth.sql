-- Phase E: WebAuthn/Passkey authentication tables
-- Idempotent: safe to re-run (IF NOT EXISTS).

-- UP --

CREATE TABLE IF NOT EXISTS user_passkeys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('ctv','admin') NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  credential_id VARCHAR(512) NOT NULL,
  public_key_pem TEXT NOT NULL,
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  transports JSON NULL,
  aaguid VARCHAR(36) NULL,
  device_name VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  UNIQUE KEY uk_credential_id (credential_id(255)),
  KEY idx_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webauthn_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge VARCHAR(128) NOT NULL,
  user_type ENUM('ctv','admin') NOT NULL,
  user_id INT UNSIGNED NULL,
  type ENUM('register','authenticate') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  KEY idx_challenge (challenge(64)),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOWN --
-- DROP TABLE IF EXISTS webauthn_challenges;
-- DROP TABLE IF EXISTS user_passkeys;
