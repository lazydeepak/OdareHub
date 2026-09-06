-- QC Plans: Add 'assigned_to' field for tracking responsibility
ALTER TABLE qc_plans ADD COLUMN assigned_to VARCHAR(190) NULL AFTER notes;
