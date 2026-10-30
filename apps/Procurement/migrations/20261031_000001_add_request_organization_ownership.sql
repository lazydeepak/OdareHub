-- OH-AUTO4-PROC-003: authoritative Procurement request organization ownership.
-- Existing rows remain NULL/unowned; no creator, email, or provenance backfill.
ALTER TABLE procurement_requests
    ADD COLUMN organization_id INT NULL AFTER id,
    ADD KEY idx_procurement_requests_organization (organization_id),
    ADD CONSTRAINT fk_procurement_requests_organization
        FOREIGN KEY (organization_id) REFERENCES org_companies(id)
        ON DELETE RESTRICT ON UPDATE CASCADE;
