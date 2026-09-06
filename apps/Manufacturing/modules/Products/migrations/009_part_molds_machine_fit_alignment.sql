ALTER TABLE part_molds
    ADD COLUMN preferred_machine_type VARCHAR(80) NULL AFTER preferred_machine_group;
