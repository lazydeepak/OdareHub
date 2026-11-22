-- IPM-011: additive, organization-owned mold revision foundation.
-- part_molds remains the Products-owned canonical mold master.
-- No revision mutation or Part 360 integration is enabled by this migration.
CREATE TABLE IF NOT EXISTS ipm_mold_revisions (
    revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mold_id INT NOT NULL COMMENT 'Products-owned part_molds.id',
    organization_id INT NOT NULL COMMENT 'Validated Platform organization context required by future services',
    revision_no INT NOT NULL,
    status ENUM('draft','released','superseded','archived') NOT NULL DEFAULT 'draft',
    cavity_count INT NULL,
    is_family_mold TINYINT(1) NULL,
    tool_weight_kg DECIMAL(12,2) NULL,
    mold_width_mm DECIMAL(12,2) NULL,
    mold_height_mm DECIMAL(12,2) NULL,
    mold_thickness_mm DECIMAL(12,2) NULL,
    min_clamp_ton_required DECIMAL(12,2) NULL,
    min_shot_g_required DECIMAL(12,2) NULL,
    runner_type VARCHAR(80) NULL,
    cycle_time_sec DECIMAL(12,2) NULL,
    preferred_machine_group VARCHAR(50) NULL,
    preferred_machine_type VARCHAR(80) NULL,
    spec_notes TEXT NULL,
    effective_from DATE NULL,
    effective_to DATE NULL,
    released_at DATETIME NULL,
    released_by_user_id INT NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (revision_id),
    UNIQUE KEY uq_ipm_mold_revision (mold_id, revision_no),
    KEY idx_ipm_mold_revision_org_status (organization_id, status),
    KEY idx_ipm_mold_revision_mold_status (mold_id, status),
    CONSTRAINT fk_ipm_revision_mold
        FOREIGN KEY (mold_id) REFERENCES part_molds(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_released_by
        FOREIGN KEY (released_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ipm_revision_no_positive
        CHECK (revision_no > 0),
    CONSTRAINT chk_ipm_revision_window
        CHECK (effective_from IS NULL OR effective_to IS NULL OR effective_from <= effective_to),
    CONSTRAINT chk_ipm_revision_release_evidence
        CHECK (
            status NOT IN ('released', 'superseded', 'archived')
            OR released_at IS NOT NULL
        )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
