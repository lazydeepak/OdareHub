ALTER TABLE qc_entries
    ADD COLUMN approval_status VARCHAR(40) NOT NULL DEFAULT 'Draft' AFTER updated_at,
    ADD COLUMN approved_by INT NULL AFTER approval_status,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN approval_note TEXT NULL AFTER approved_at,
    ADD COLUMN locked_by INT NULL AFTER approval_note,
    ADD COLUMN locked_at DATETIME NULL AFTER locked_by,
    ADD COLUMN reopened_by INT NULL AFTER locked_at,
    ADD COLUMN reopened_at DATETIME NULL AFTER reopened_by,
    ADD COLUMN reopen_reason TEXT NULL AFTER reopened_at,
    ADD COLUMN override_reason TEXT NULL AFTER reopen_reason,
    ADD INDEX idx_qc_entries_approval_status (approval_status),
    ADD INDEX idx_qc_entries_locked_at (locked_at);