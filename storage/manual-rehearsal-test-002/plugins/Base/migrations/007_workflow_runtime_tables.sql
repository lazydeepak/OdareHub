-- Normalize workflow runtime notification/escalation schema.

CREATE TABLE IF NOT EXISTS workflow_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_role VARCHAR(80) NULL,
    recipient_user_id INT NULL,
    user_id INT NULL,
    severity VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    event_type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    message TEXT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT NOT NULL,
    stage VARCHAR(50) NOT NULL,
    action_url VARCHAR(255) NULL,
    delivery_status VARCHAR(20) NOT NULL DEFAULT 'sent',
    sent_at DATETIME NULL,
    dedupe_key VARCHAR(64) NOT NULL,
    read_at DATETIME NULL,
    dismissed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_workflow_notification_dedupe (dedupe_key),
    KEY idx_workflow_notification_target (recipient_role, recipient_user_id, status),
    KEY idx_workflow_notification_entity (entity_type, entity_id, stage),
    KEY idx_workflow_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE workflow_notifications ADD COLUMN user_id INT NULL AFTER recipient_user_id;
ALTER TABLE workflow_notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER stage;
ALTER TABLE workflow_notifications ADD COLUMN delivery_status VARCHAR(20) NOT NULL DEFAULT 'sent' AFTER action_url;
ALTER TABLE workflow_notifications ADD COLUMN sent_at DATETIME NULL AFTER delivery_status;

CREATE INDEX idx_workflow_notification_user ON workflow_notifications (user_id, status, created_at);

CREATE TABLE IF NOT EXISTS workflow_escalation_runs (
    engine_key VARCHAR(80) PRIMARY KEY,
    last_run_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
