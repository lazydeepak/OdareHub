ALTER TABLE dispatch_entries
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
    ADD INDEX idx_dispatch_entries_approval_status (approval_status),
    ADD INDEX idx_dispatch_entries_locked_at (locked_at);

CREATE TABLE IF NOT EXISTS workflow_approval_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_name VARCHAR(60) NOT NULL,
    record_id INT NOT NULL,
    action_name VARCHAR(60) NOT NULL,
    previous_approval_status VARCHAR(40) NULL,
    new_approval_status VARCHAR(40) NULL,
    previous_locked_state TINYINT(1) NOT NULL DEFAULT 0,
    new_locked_state TINYINT(1) NOT NULL DEFAULT 0,
    reason_text TEXT NULL,
    note_text TEXT NULL,
    acted_by INT NULL,
    acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workflow_events_record (module_name, record_id),
    INDEX idx_workflow_events_action (action_name),
    INDEX idx_workflow_events_acted_at (acted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;