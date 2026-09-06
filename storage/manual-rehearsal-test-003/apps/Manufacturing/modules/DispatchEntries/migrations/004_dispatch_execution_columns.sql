-- DispatchEntries module: operational execution fields assumed by dispatch ops/workflow services.

ALTER TABLE dispatch_entries ADD COLUMN cases_count INT NOT NULL DEFAULT 0;
ALTER TABLE dispatch_entries ADD COLUMN pallets_count INT NOT NULL DEFAULT 0;
ALTER TABLE dispatch_entries ADD COLUMN dispatch_mode ENUM('in_house_dispatch','third_party_dispatch','company_origin_dispatch','third_party_direct_dispatch','third_party_to_company_then_destination') NOT NULL DEFAULT 'company_origin_dispatch';
ALTER TABLE dispatch_entries ADD COLUMN delivery_flow ENUM('company_to_destination','third_party_to_destination','third_party_to_company_to_destination') NOT NULL DEFAULT 'company_to_destination';
ALTER TABLE dispatch_entries ADD COLUMN source_type ENUM('in_house','third_party') NOT NULL DEFAULT 'in_house';
ALTER TABLE dispatch_entries ADD COLUMN prepared_by VARCHAR(190) NULL;
ALTER TABLE dispatch_entries ADD COLUMN prepared_at DATETIME NULL;
ALTER TABLE dispatch_entries ADD COLUMN dispatch_completed_by VARCHAR(190) NULL;
ALTER TABLE dispatch_entries ADD COLUMN dispatch_completed_at DATETIME NULL;
ALTER TABLE dispatch_entries ADD COLUMN completion_status ENUM('draft','ready','prepared','completed') NOT NULL DEFAULT 'draft';
ALTER TABLE dispatch_entries ADD COLUMN third_party_reference VARCHAR(190) NULL;
