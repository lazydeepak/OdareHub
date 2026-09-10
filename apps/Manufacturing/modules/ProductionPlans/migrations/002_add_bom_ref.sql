-- Shared Items BOM / Recipe adoption: ProductionPlan reference to Manufacturing BOM
-- References manufacturing_bom table (session A added 013 migration for BOM foundation)
-- Adds optional pinning fields; does not alter existing plan identity.
ALTER TABLE production_plans
    ADD COLUMN bom_ref INT NULL DEFAULT NULL,
    ADD COLUMN bom_version VARCHAR(50) NULL DEFAULT NULL,
    ADD KEY idx_production_plans_bom_ref (bom_ref),
    ADD KEY idx_production_plans_bom_version (bom_version);
