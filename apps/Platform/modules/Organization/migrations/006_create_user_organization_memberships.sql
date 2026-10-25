-- Migration: 006_create_user_organization_memberships.sql
-- Platform-owned explicit user-to-organization membership evidence.
-- No legacy assignments, roles, branches, Staff, email, or name data is imported.

CREATE TABLE IF NOT EXISTS org_user_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    status ENUM('active','inactive','revoked') NOT NULL DEFAULT 'inactive',
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    activated_at DATETIME NULL,
    activated_by_user_id INT NULL,
    revoked_at DATETIME NULL,
    revoked_by_user_id INT NULL,
    revocation_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active_uniqueness_key TINYINT GENERATED ALWAYS AS (
        CASE WHEN status = 'active' THEN 1 ELSE NULL END
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_org_user_membership_active (user_id, organization_id, active_uniqueness_key),
    KEY idx_org_user_membership_user (user_id, status),
    KEY idx_org_user_membership_organization (organization_id, status),
    KEY idx_org_user_membership_validity (user_id, organization_id, valid_from, valid_until),
    CONSTRAINT chk_org_user_membership_validity
        CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until),
    CONSTRAINT chk_org_user_membership_revocation
        CHECK (
            (status = 'revoked' AND revoked_at IS NOT NULL)
            OR (status <> 'revoked' AND revoked_at IS NULL)
        ),
    CONSTRAINT chk_org_user_membership_activation
        CHECK (status <> 'active' OR activated_at IS NOT NULL),
    CONSTRAINT fk_org_user_membership_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_org_user_membership_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_org_user_membership_revoked_by
        FOREIGN KEY (revoked_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_org_user_membership_activated_by
        FOREIGN KEY (activated_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_org_user_membership_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
