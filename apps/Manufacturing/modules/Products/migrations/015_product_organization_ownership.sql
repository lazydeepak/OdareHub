-- IPM-006: additive Product ownership foundation.
-- Existing Products remain NULL/unresolved; no backfill or inferred ownership.
ALTER TABLE products
    ADD COLUMN organization_id INT NULL AFTER id,
    ADD KEY idx_products_organization (organization_id),
    ADD CONSTRAINT fk_products_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
