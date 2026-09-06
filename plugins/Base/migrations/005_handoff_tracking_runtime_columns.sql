-- Normalize handoff tracking schema with ownership/SLA columns required by runtime.

ALTER TABLE handoff_tracking
    ADD COLUMN owner_account_type VARCHAR(40) NULL AFTER owner_assigned_at,
    ADD COLUMN owner_profile_key VARCHAR(120) NULL AFTER owner_account_type,
    ADD COLUMN ownership_state VARCHAR(40) NULL AFTER owner_profile_key,
    ADD COLUMN next_stage VARCHAR(80) NULL AFTER ownership_state,
    ADD COLUMN next_owner_role VARCHAR(80) NULL AFTER next_stage,
    ADD COLUMN next_owner_profile VARCHAR(120) NULL AFTER next_owner_role,
    ADD COLUMN waiting_since DATETIME NULL AFTER ready_since,
    ADD COLUMN in_progress_since DATETIME NULL AFTER waiting_since,
    ADD COLUMN completed_at DATETIME NULL AFTER released_since,
    ADD COLUMN sla_warning_hours INT NULL AFTER escalated_at,
    ADD COLUMN sla_breach_hours INT NULL AFTER sla_warning_hours,
    ADD COLUMN sla_state VARCHAR(20) NULL AFTER sla_breach_hours;

CREATE INDEX idx_handoff_state ON handoff_tracking (ownership_state);
CREATE INDEX idx_handoff_sla_state ON handoff_tracking (sla_state);
