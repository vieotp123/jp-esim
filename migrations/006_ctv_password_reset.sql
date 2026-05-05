-- 006: Add password reset token columns to ctv_users
ALTER TABLE ctv_users
    ADD COLUMN password_reset_token VARCHAR(64) NULL DEFAULT NULL AFTER password_hash,
    ADD COLUMN password_reset_sent_at DATETIME NULL DEFAULT NULL AFTER password_reset_token;

ALTER TABLE ctv_users ADD INDEX idx_ctv_pw_reset_token (password_reset_token);
