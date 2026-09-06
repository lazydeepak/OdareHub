-- Migration: 005_create_audit_log.sql
-- Creates org_audit_log table for compliance-grade change tracking.

CREATE TABLE IF NOT EXISTS org_audit_log (
    id            BIGINT       NOT NULL AUTO_INCREMENT,
    company_id    INT          NOT NULL,
    entity_type   VARCHAR(40)  NOT NULL,
    entity_id     INT          NOT NULL,
    action        ENUM('create','update','delete') NOT NULL,
    changed_fields JSON        NULL,
    user_id       INT          NULL,
    user_email    VARCHAR(190) NULL,
    ip_address    VARCHAR(64)  NULL,
    created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_company_ts (company_id, created_at),
    KEY idx_audit_entity     (entity_type, entity_id),
    KEY idx_audit_user       (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
