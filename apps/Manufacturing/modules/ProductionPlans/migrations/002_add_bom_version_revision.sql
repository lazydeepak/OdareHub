-- Manufacturing BOM version/revision tracking for ProductionPlan pinning
-- Complements 012_add_item_ref and 013_add_manufacturing_bom
ALTER TABLE production_plans
    ADD COLUMN bom_version VARCHAR(50) NULL DEFAULT NULL,
    ADD COLUMN bom_revision INT NULL DEFAULT NULL,
    ADD KEY idx_pp_plan_bom_version (bom_version);
