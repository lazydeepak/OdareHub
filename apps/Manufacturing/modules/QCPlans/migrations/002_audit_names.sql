ALTER TABLE qc_plans
    ADD COLUMN added_by VARCHAR(190) NULL AFTER assigned_to,
    ADD COLUMN verified_by VARCHAR(190) NULL AFTER added_by,
    ADD COLUMN approved_by VARCHAR(190) NULL AFTER verified_by,
    ADD KEY idx_qc_plans_added_by (added_by),
    ADD KEY idx_qc_plans_verified_by (verified_by),
    ADD KEY idx_qc_plans_approved_by (approved_by);
