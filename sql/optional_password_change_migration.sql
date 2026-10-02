ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS password_change_token_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS password_change_expires_at DATETIME NULL;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS password_change_token_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS password_change_expires_at DATETIME NULL;
