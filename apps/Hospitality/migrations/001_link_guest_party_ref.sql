ALTER TABLE hosp_guests
    ADD COLUMN party_ref BIGINT NULL COMMENT 'Shared Parties reference (optional; set only when linking to canonical Party)',
    ADD INDEX idx_hosp_guests_party_ref (party_ref);
