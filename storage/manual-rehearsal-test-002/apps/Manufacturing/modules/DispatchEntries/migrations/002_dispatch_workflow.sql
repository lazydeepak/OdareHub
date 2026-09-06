ALTER TABLE dispatch_entries
    ADD COLUMN status_reason VARCHAR(190) NULL AFTER remarks,
    ADD COLUMN status_note TEXT NULL AFTER status_reason,
    ADD COLUMN released_at DATETIME NULL AFTER status_note,
    ADD COLUMN blocked_at DATETIME NULL AFTER released_at,
    ADD COLUMN dispatched_at DATETIME NULL AFTER blocked_at,
    ADD COLUMN last_transition_at DATETIME NULL AFTER dispatched_at,
    ADD COLUMN status_updated_by INT NULL AFTER last_transition_at,
    ADD INDEX idx_dispatch_entries_status (dispatch_status),
    ADD INDEX idx_dispatch_entries_daily_order_status (daily_order_id, dispatch_status),
    ADD INDEX idx_dispatch_entries_qc_status (qc_entry_id, dispatch_status);

CREATE TABLE IF NOT EXISTS dispatch_entry_transitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_entry_id INT NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    transition_reason VARCHAR(190) NULL,
    transition_note TEXT NULL,
    performed_by INT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dispatch_entry_transition_entry (dispatch_entry_id),
    INDEX idx_dispatch_entry_transition_status (to_status),
    INDEX idx_dispatch_entry_transition_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;