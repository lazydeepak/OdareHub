-- Migration: 004_create_hierarchy.sql
-- Creates org_hierarchy table for multi-level department/region/cost-center trees.

CREATE TABLE IF NOT EXISTS org_hierarchy (
    id            INT          NOT NULL AUTO_INCREMENT,
    company_id    INT          NOT NULL,
    parent_id     INT          NULL DEFAULT NULL,
    hierarchy_code VARCHAR(64) NOT NULL,
    hierarchy_name VARCHAR(190) NOT NULL,
    hierarchy_type ENUM('department','region','costcenter') NOT NULL DEFAULT 'department',
    level         TINYINT      NOT NULL DEFAULT 0,
    sort_order    SMALLINT     NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hierarchy_code (company_id, hierarchy_code),
    KEY idx_hierarchy_company (company_id),
    KEY idx_hierarchy_parent  (company_id, parent_id),
    KEY idx_hierarchy_active  (company_id, is_active),
    CONSTRAINT fk_hierarchy_company
        FOREIGN KEY (company_id) REFERENCES org_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_hierarchy_parent
        FOREIGN KEY (parent_id)  REFERENCES org_hierarchy(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
