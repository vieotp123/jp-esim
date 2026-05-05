-- 007: Add company_name to ctv_users (referenced by admin UI but never declared in schema)
ALTER TABLE ctv_users
    ADD COLUMN company_name VARCHAR(255) NULL DEFAULT NULL AFTER display_name;
