-- IPM-002 Schema Proposal — PROPOSED DDL, NEVER EXECUTED
-- Date: 2026-11-17
-- Authority: Planner decisions D1–D5 (approved as design baseline) + IPM-001 baseline.
-- Compatibility: additive-only; no destructive ALTER; legacy columns preserved.
-- Verification companion: docs/architecture/ipm/architecture-verification.md
--
-- Type conventions:
--   Canonical item identity  : BIGINT UNSIGNED  (shared_items.item_id)
--   Platform org identity    : INT              (org_companies.id — matches Platform DDL)
--   Core user identity       : INT              (users.id — matches Core DDL)
--   Manufacturing legacy ids : INT              (products.id, machines.id, part_molds.id, ...)
--   New IPM surrogate keys   : BIGINT UNSIGNED
--
-- No PRIMARY KEY is ever changed. No column is retyped. No table is dropped or
-- renamed. Legacy columns (item_ref, finished_item_ref, component_item_ref)
-- are deprecated by documentation only in this phase.

/* =====================================================================
   PHASE 1 — Shared Foundation (additive; no legacy impact)
   Owner: Shared Foundation (shared/Item/Foundation)
   ===================================================================== */

CREATE TABLE IF NOT EXISTS shared_items (
    item_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code_ref      VARCHAR(255)    NOT NULL COMMENT 'Business reference / SKU (not Manufacturing format)',
    name          VARCHAR(500)    NOT NULL,
    status        ENUM('draft','active','deprecated','archived') NOT NULL DEFAULT 'draft',
    category_ref  VARCHAR(120)    NULL COMMENT 'Reference to consumer taxonomy (not a taxonomy master)',
    metadata_ref  VARCHAR(255)    NULL COMMENT 'Reference to consumer extension (not a data store)',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    UNIQUE KEY uq_shared_items_code_ref (code_ref),
    KEY idx_shared_items_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicit, verified mapping between legacy Manufacturing identities and
-- canonical item identity. THE ONLY SANCTIONED identity resolution path
-- during transition; no implicit casting anywhere.
CREATE TABLE IF NOT EXISTS shared_item_identity_map (
    map_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    legacy_table     VARCHAR(64)     NOT NULL COMMENT 'e.g. products, manufacturing_bom, materials',
    legacy_id        INT             NOT NULL COMMENT 'Legacy INT domain id',
    shared_item_id   BIGINT UNSIGNED NOT NULL,
    mapping_status   ENUM('proposed','verified','rejected') NOT NULL DEFAULT 'proposed',
    mapping_reason   VARCHAR(255)    NULL,
    verified_by_user_id INT          NULL COMMENT 'users.id (required before mapping_status=verified)',
    verified_at      DATETIME        NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (map_id),
    UNIQUE KEY uq_identity_map_legacy (legacy_table, legacy_id),
    KEY idx_identity_map_item (shared_item_id),
    KEY idx_identity_map_status (mapping_status),
    CONSTRAINT fk_identity_map_item
        FOREIGN KEY (shared_item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_identity_map_user
        FOREIGN KEY (verified_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_identity_map_verified
        CHECK (mapping_status <> 'verified' OR (verified_by_user_id IS NOT NULL AND verified_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =====================================================================
   PHASE 3 — Manufacturing tenant ownership (additive; nullable)
   Owner: Manufacturing. Applied table-by-table, module family by family.
   NOT NULL is deliberately deferred (see identity-and-tenant-migration-plan.md).
   ===================================================================== */

-- Pattern applied to EVERY Manufacturing-owned table that is currently
-- global (products, machines, part_molds, part_machine_map, part_material_map,
-- materials + material_* family, manufacturing_bom, manufacturing_bom_line,
-- production_plans, production_entries, daily_orders, pre_orders,
-- assembly_*, qc_*, dispatch_*, stock_ledger_entries, mfg_* plan/demand tables):
--
--   ALTER TABLE <table>
--     ADD COLUMN organization_id INT NULL AFTER id,
--     ADD KEY idx_<table>_organization (organization_id),
--     ADD CONSTRAINT fk_<table>_organization
--         FOREIGN KEY (organization_id) REFERENCES org_companies(id)
--         ON DELETE RESTRICT ON UPDATE CASCADE;
--
-- Backfill ONLY under the determinism rule (single is_primary org_companies
-- row AND all existing rows are unowned); otherwise rows remain NULL and are
-- reported as unresolved. Dependent children additionally carry the
-- denormalized organization_id and the application must assert
-- child.organization_id = parent.organization_id inside the same transaction.

/* =====================================================================
   PHASE 4 — Manufacturing identity reconciliation (additive BIGINT)
   ===================================================================== */

ALTER TABLE products
    ADD COLUMN item_id BIGINT UNSIGNED NULL AFTER item_ref,
    ADD KEY idx_products_item_id (item_id),
    ADD CONSTRAINT fk_products_item
        FOREIGN KEY (item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
-- products.id (INT) is NEVER retyped. products.item_ref (legacy INT) is
-- preserved unchanged and deprecated by documentation. Resolution order for
-- consumers: products.item_id (new) -> shared_item_identity_map (verified) ->
-- explicit unresolved state. Never silent fallback to parts_number.

ALTER TABLE manufacturing_bom
    ADD COLUMN organization_id INT NULL AFTER id,
    ADD COLUMN finished_item_id BIGINT UNSIGNED NULL AFTER finished_item_ref,
    ADD KEY idx_mfg_bom_organization (organization_id),
    ADD KEY idx_mfg_bom_finished_item (finished_item_id),
    ADD CONSTRAINT fk_mfg_bom_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_mfg_bom_finished_item
        FOREIGN KEY (finished_item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE manufacturing_bom_line
    ADD COLUMN organization_id INT NULL AFTER id,
    ADD COLUMN component_item_id BIGINT UNSIGNED NULL AFTER component_item_ref,
    ADD KEY idx_mfg_bom_line_organization (organization_id),
    ADD KEY idx_mfg_bom_line_component_item (component_item_id),
    ADD CONSTRAINT fk_mfg_bom_line_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_mfg_bom_line_component_item
        FOREIGN KEY (component_item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

/* =====================================================================
   PHASE 5 — IPM module (InjectionMolding). New tables only.
   Owner: apps/Manufacturing/modules/InjectionMolding
   Every table carries organization_id NOT NULL — IPM tables are created
   AFTER Phase 3 backfill attestation; legacy ambiguity never propagates.
   ===================================================================== */

-- 5.1 Mold revisions — versioned snapshot of the molding tool specification.
-- 1:N from part_molds. Snapshot overrides live mold columns only by revision,
-- never by mutating part_molds history.
CREATE TABLE IF NOT EXISTS ipm_mold_revisions (
    revision_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mold_id          INT             NOT NULL COMMENT 'part_molds.id (Manufacturing-owned)',
    organization_id  INT             NOT NULL,
    revision_no      INT             NOT NULL,
    status           ENUM('draft','released','superseded','archived') NOT NULL DEFAULT 'draft',
    -- snapshot of the molding tool specification at this revision
    cavity_count     INT             NULL,
    is_family_mold   TINYINT(1)      NULL,
    tool_weight_kg   DECIMAL(12,2)   NULL,
    mold_width_mm    DECIMAL(12,2)   NULL,
    mold_height_mm   DECIMAL(12,2)   NULL,
    mold_thickness_mm DECIMAL(12,2)  NULL,
    min_clamp_ton_required DECIMAL(12,2) NULL,
    min_shot_g_required    DECIMAL(12,2) NULL,
    runner_type      VARCHAR(80)     NULL,
    cycle_time_sec   DECIMAL(12,2)   NULL,
    preferred_machine_group VARCHAR(50) NULL,
    preferred_machine_type  VARCHAR(80) NULL,
    steel_grade      VARCHAR(120)    NULL,
    hot_runner_type  VARCHAR(80)     NULL,
    spec_notes       TEXT            NULL,
    effective_from   DATE            NULL,
    effective_to     DATE            NULL,
    approved_by_user_id INT          NULL,
    approved_at      DATETIME        NULL,
    notes            TEXT            NULL,
    created_by_user_id INT           NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (revision_id),
    UNIQUE KEY uq_ipm_mold_revision (mold_id, revision_no),
    KEY idx_ipm_mold_revision_org (organization_id, status),
    KEY idx_ipm_mold_revision_mold (mold_id, status),
    CONSTRAINT fk_ipm_revision_mold
        FOREIGN KEY (mold_id) REFERENCES part_molds(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_approved_by
        FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_revision_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ipm_revision_window
        CHECK (effective_from IS NULL OR effective_to IS NULL OR effective_from <= effective_to),
    CONSTRAINT chk_ipm_revision_release
        CHECK (status <> 'released' OR (approved_by_user_id IS NOT NULL AND approved_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.2 Mold cavities — one row per physical cavity per revision.
CREATE TABLE IF NOT EXISTS ipm_mold_cavities (
    cavity_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    revision_id      BIGINT UNSIGNED NOT NULL,
    organization_id  INT             NOT NULL,
    cavity_no        INT             NOT NULL,
    layout_position  VARCHAR(80)     NULL,
    part_ref         INT             NULL COMMENT 'products.id produced by this cavity (NULL for family-mold mixed refs)',
    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
    notes            TEXT            NULL,
    PRIMARY KEY (cavity_id),
    UNIQUE KEY uq_ipm_cavity (revision_id, cavity_no),
    KEY idx_ipm_cavity_org (organization_id),
    KEY idx_ipm_cavity_revision (revision_id),
    CONSTRAINT fk_ipm_cavity_revision
        FOREIGN KEY (revision_id) REFERENCES ipm_mold_revisions(revision_id)
        ON DELETE CASCADE ON UPDATE CASCADE,   -- snapshot children die with the revision draft
    CONSTRAINT fk_ipm_cavity_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.3 Mold maintenance & shot-count history.
CREATE TABLE IF NOT EXISTS ipm_mold_maintenance (
    maintenance_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mold_id          INT             NOT NULL,
    organization_id  INT             NOT NULL,
    maintenance_type ENUM('preventive','corrective','overhaul','inspection','trial','cleaning') NOT NULL,
    shot_count_at_service BIGINT     NULL COMMENT 'Cumulative shots at the time of service, if captured',
    service_date     DATE            NOT NULL,
    performed_by_user_id INT         NULL,
    description      TEXT            NULL,
    next_service_shot_count BIGINT   NULL,
    created_by_user_id INT           NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (maintenance_id),
    KEY idx_ipm_maintenance_mold (mold_id, service_date),
    KEY idx_ipm_maintenance_org (organization_id, service_date),
    CONSTRAINT fk_ipm_maintenance_mold
        FOREIGN KEY (mold_id) REFERENCES part_molds(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_maintenance_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_maintenance_performed_by
        FOREIGN KEY (performed_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_maintenance_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.4 Versioned molding process recipes.
-- Scope of "one effective recipe at a time": (organization_id, mold_revision_id,
-- machine_id, material_id). Enforced by the service layer under a
-- SELECT ... FOR UPDATE lock overlapping released rows; MySQL has no partial
-- unique index — do NOT fake one with a generated column workaround in Phase 1.
CREATE TABLE IF NOT EXISTS ipm_process_recipes (
    recipe_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  INT             NOT NULL,
    recipe_code      VARCHAR(120)    NOT NULL,
    recipe_version   VARCHAR(50)     NOT NULL DEFAULT '1.0',
    revision_id      BIGINT UNSIGNED NOT NULL,
    machine_id       INT             NULL COMMENT 'machines.id (NULL = any compatible machine)',
    material_id      INT             NULL COMMENT 'materials.id (NULL = material-agnostic)',
    status           ENUM('draft','released','superseded','archived') NOT NULL DEFAULT 'draft',
    melt_temp_c          DECIMAL(8,2) NULL,
    mold_temp_c          DECIMAL(8,2) NULL,
    injection_pressure_bar DECIMAL(10,2) NULL,
    holding_pressure_bar   DECIMAL(10,2) NULL,
    injection_speed_mm_s DECIMAL(10,2) NULL,
    holding_time_s       DECIMAL(10,2) NULL,
    cooling_time_s       DECIMAL(10,2) NULL,
    back_pressure_bar    DECIMAL(10,2) NULL,
    screw_rpm            INT           NULL,
    shot_size_g          DECIMAL(12,2) NULL,
    cushion_mm           DECIMAL(10,2) NULL,
    cycle_time_target_s  DECIMAL(12,2) NULL,
    extra_parameters     JSON          NULL COMMENT 'Bounded supplement only, never essential keys',
    effective_from   DATE            NULL,
    effective_to     DATE            NULL,
    approved_by_user_id INT          NULL,
    approved_at      DATETIME        NULL,
    notes            TEXT            NULL,
    created_by_user_id INT           NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (recipe_id),
    UNIQUE KEY uq_ipm_recipe (organization_id, recipe_code, recipe_version),
    KEY idx_ipm_recipe_revision (revision_id, status),
    KEY idx_ipm_recipe_machine (machine_id),
    KEY idx_ipm_recipe_material (material_id),
    KEY idx_ipm_recipe_effective (status, effective_from),
    CONSTRAINT fk_ipm_recipe_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_recipe_revision
        FOREIGN KEY (revision_id) REFERENCES ipm_mold_revisions(revision_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_recipe_machine
        FOREIGN KEY (machine_id) REFERENCES machines(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_recipe_material
        FOREIGN KEY (material_id) REFERENCES materials(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_recipe_approved_by
        FOREIGN KEY (approved_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_recipe_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ipm_recipe_window
        CHECK (effective_from IS NULL OR effective_to IS NULL OR effective_from <= effective_to),
    CONSTRAINT chk_ipm_recipe_release
        CHECK (status <> 'released' OR (approved_by_user_id IS NOT NULL AND approved_at IS NOT NULL)),
    CONSTRAINT chk_ipm_recipe_extra_json
        CHECK (extra_parameters IS NULL OR JSON_VALID(extra_parameters))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.5 Production context — extends a production entry with mold/recipe/operator
-- evidence WITHOUT altering production_entries.
CREATE TABLE IF NOT EXISTS ipm_production_context (
    production_entry_id INT       NOT NULL COMMENT 'production_entries.id',
    organization_id     INT       NOT NULL,
    revision_id         BIGINT UNSIGNED NULL,
    recipe_id           BIGINT UNSIGNED NULL,
    cavity_no           INT       NULL,
    operator_user_id    INT       NULL,
    shift               VARCHAR(30) NULL,
    notes               TEXT      NULL,
    PRIMARY KEY (production_entry_id),
    KEY idx_ipm_prodctx_org (organization_id),
    KEY idx_ipm_prodctx_revision (revision_id),
    KEY idx_ipm_prodctx_recipe (recipe_id),
    CONSTRAINT fk_ipm_prodctx_entry
        FOREIGN KEY (production_entry_id) REFERENCES production_entries(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_prodctx_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_prodctx_revision
        FOREIGN KEY (revision_id) REFERENCES ipm_mold_revisions(revision_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_prodctx_recipe
        FOREIGN KEY (recipe_id) REFERENCES ipm_process_recipes(recipe_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_prodctx_operator
        FOREIGN KEY (operator_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =====================================================================
   PHASE 6 — Lot-first genealogy (IPM module)
   ===================================================================== */

-- 6.1 Lots — raw material, WIP, finished goods, rework, quarantine, scrap.
CREATE TABLE IF NOT EXISTS ipm_lots (
    lot_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  INT             NOT NULL,
    lot_type         ENUM('raw_material','wip','finished_goods','rework','quarantine','scrap') NOT NULL,
    lot_code         VARCHAR(120)    NOT NULL,
    item_id          BIGINT UNSIGNED NULL COMMENT 'shared_items.item_id (NULL only for legacy-unmapped lots)',
    legacy_material_id INT           NULL COMMENT 'materials.id',
    legacy_product_id  INT           NULL COMMENT 'products.id',
    supplier_lot_ref VARCHAR(120)    NULL,
    parent_lot_id    BIGINT UNSIGNED NULL COMMENT 'Direct lineage for splits/reworks (multi-parent links use ipm_lot_relationships)',
    quantity         DECIMAL(14,4)   NULL,
    unit             VARCHAR(40)     NULL,
    status           ENUM('open','quarantine','consumed','shipped','scrapped','reworked') NOT NULL DEFAULT 'open',
    produced_by_production_entry_id INT NULL COMMENT 'production_entries.id',
    received_date    DATE            NULL,
    expiry_date      DATE            NULL,
    notes            TEXT            NULL,
    created_by_user_id INT           NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (lot_id),
    UNIQUE KEY uq_ipm_lot_code (organization_id, lot_code),
    KEY idx_ipm_lot_item (item_id),
    KEY idx_ipm_lot_type_status (lot_type, status),
    KEY idx_ipm_lot_parent (parent_lot_id),
    KEY idx_ipm_lot_org (organization_id, lot_type),
    KEY idx_ipm_lot_produced_by (produced_by_production_entry_id),
    CONSTRAINT fk_ipm_lot_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lot_item
        FOREIGN KEY (item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lot_parent
        FOREIGN KEY (parent_lot_id) REFERENCES ipm_lots(lot_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lot_produced_by
        FOREIGN KEY (produced_by_production_entry_id) REFERENCES production_entries(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lot_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.2 Lot relationships — split / merge / consumption lineage / rework /
-- scrap / quarantine / return / repackage.
CREATE TABLE IF NOT EXISTS ipm_lot_relationships (
    relationship_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  INT             NOT NULL,
    parent_lot_id    BIGINT UNSIGNED NOT NULL,
    child_lot_id     BIGINT UNSIGNED NOT NULL,
    relationship_type ENUM('split','merge','consumed_by','rework','scrap','quarantine','return','repackage') NOT NULL,
    quantity         DECIMAL(14,4)   NULL,
    reference_type   VARCHAR(60)     NULL COMMENT 'production|qc|dispatch|receipt|manual',
    reference_id     BIGINT UNSIGNED NULL COMMENT 'Polymorphic id within reference_type',
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (relationship_id),
    UNIQUE KEY uq_ipm_lot_rel (parent_lot_id, child_lot_id, relationship_type),
    KEY idx_ipm_lot_rel_org (organization_id),
    KEY idx_ipm_lot_rel_child (child_lot_id),
    KEY idx_ipm_lot_rel_reference (reference_type, reference_id),
    CONSTRAINT fk_ipm_lotrel_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lotrel_parent
        FOREIGN KEY (parent_lot_id) REFERENCES ipm_lots(lot_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_lotrel_child
        FOREIGN KEY (child_lot_id) REFERENCES ipm_lots(lot_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ipm_lotrel_not_self
        CHECK (parent_lot_id <> child_lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.3 Lot consumption — which lots a production entry consumed, and how much.
CREATE TABLE IF NOT EXISTS ipm_lot_consumptions (
    consumption_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  INT             NOT NULL,
    lot_id           BIGINT UNSIGNED NOT NULL,
    production_entry_id INT          NOT NULL,
    quantity         DECIMAL(14,4)   NOT NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (consumption_id),
    UNIQUE KEY uq_ipm_consumption (lot_id, production_entry_id),
    KEY idx_ipm_consumption_org (organization_id),
    KEY idx_ipm_consumption_entry (production_entry_id),
    CONSTRAINT fk_ipm_consumption_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_consumption_lot
        FOREIGN KEY (lot_id) REFERENCES ipm_lots(lot_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_consumption_entry
        FOREIGN KEY (production_entry_id) REFERENCES production_entries(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.4 Optional serial-level extension. NOT deployed unless the owner enables
-- serial tracking for the organization. Lot identity remains primary.
CREATE TABLE IF NOT EXISTS ipm_serials (
    serial_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  INT             NOT NULL,
    lot_id           BIGINT UNSIGNED NOT NULL,
    serial_no        VARCHAR(120)    NOT NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (serial_id),
    UNIQUE KEY uq_ipm_serial (organization_id, serial_no),
    KEY idx_ipm_serial_lot (lot_id),
    CONSTRAINT fk_ipm_serial_org
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ipm_serial_lot
        FOREIGN KEY (lot_id) REFERENCES ipm_lots(lot_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =====================================================================
   NOT PROPOSED (explicitly)
   - ALTER production_entries / qc_entries / dispatch_entries  (extension
     tables only; no destructive change to hot transaction tables)
   - shared_items NOT NULL backfill obligations in Phase 1
   - NOT NULL organization_id on legacy tables (deferred, audit-gated)
   - Any change to Core (users, ACL, Auth)
   ===================================================================== */
