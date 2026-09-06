ALTER TABLE machines
    ADD COLUMN machine_group VARCHAR(50) NULL AFTER section,
    ADD COLUMN machine_type VARCHAR(80) NULL AFTER machine_group,
    ADD COLUMN clamping_force_ton DECIMAL(10,2) NULL AFTER capacity_per_hour,
    ADD COLUMN shot_capacity_g DECIMAL(10,2) NULL AFTER clamping_force_ton,
    ADD COLUMN tie_bar_spacing_mm VARCHAR(80) NULL AFTER shot_capacity_g,
    ADD COLUMN platen_size_mm VARCHAR(80) NULL AFTER tie_bar_spacing_mm,
    ADD COLUMN min_mold_height_mm DECIMAL(10,2) NULL AFTER platen_size_mm,
    ADD COLUMN max_mold_height_mm DECIMAL(10,2) NULL AFTER min_mold_height_mm,
    ADD COLUMN preferred_materials VARCHAR(255) NULL AFTER max_mold_height_mm;

ALTER TABLE machines
    ADD KEY idx_machines_group (machine_group),
    ADD KEY idx_machines_type (machine_type);
