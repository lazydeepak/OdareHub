-- IPM-013: additive mold ownership preparation.
-- Existing rows remain NULL/unresolved; no backfill or inferred ownership.
ALTER TABLE part_molds
    ADD COLUMN organization_id INT NULL AFTER product_id,
    ADD KEY idx_part_molds_organization (organization_id),
    ADD CONSTRAINT fk_part_molds_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
