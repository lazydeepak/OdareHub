-- Stage Readiness tracking: one row per (product_id, ref_date, stage)
-- Stores explicit release actions and manual overrides (blocked/skipped).
-- Computed stage status is derived in StageTransitionService; this table
-- holds only the explicit handoff records written by operators.
--
-- Stage flow: Production → Assembly → QC → Packaging → Dispatch
CREATE TABLE IF NOT EXISTS mfg_stage_readiness (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ref_date DATE NOT NULL,
    stage ENUM('production','assembly','qc','packaging','dispatch') NOT NULL,
    released_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    released_by VARCHAR(190) NULL,
    released_at DATETIME NULL,
    override_status ENUM('released','skipped','blocked') NULL
        COMMENT 'NULL = auto-computed from actuals; set manually to force skip or block',
    blocked_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mfg_stage_readiness (product_id, ref_date, stage),
    KEY idx_mfg_stage_readiness_date (ref_date),
    KEY idx_mfg_stage_readiness_stage (stage),
    KEY idx_mfg_stage_readiness_override (override_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
