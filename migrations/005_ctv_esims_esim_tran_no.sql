-- Safe idempotent migration for CTV eSIM provider profile identity.
-- Do not run automatically from tests; apply during the normal production migration window.

ALTER TABLE ctv_esims
  ADD COLUMN IF NOT EXISTS esimTranNo VARCHAR(128) NULL AFTER iccid,
  ADD INDEX IF NOT EXISTS idx_ctv_esims_tran (esimTranNo);
