-- Migration: 003_add_foreign_keys.sql
-- Adds ON DELETE CASCADE foreign key constraints to org_branches,
-- org_fiscal_settings, and branding_assets so that deleting an
-- org_companies row removes all dependent records automatically.

ALTER TABLE org_branches
    ADD CONSTRAINT fk_org_branches_company
    FOREIGN KEY (company_id) REFERENCES org_companies(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE org_fiscal_settings
    ADD CONSTRAINT fk_org_fiscal_company
    FOREIGN KEY (company_id) REFERENCES org_companies(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE branding_assets
    ADD CONSTRAINT fk_branding_assets_company
    FOREIGN KEY (company_id) REFERENCES org_companies(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
