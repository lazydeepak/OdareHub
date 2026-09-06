-- Parties v1 canonical data owner (Slice 4 skeleton).
-- Minimal DB-wide identity: display_name + nullable email/phone + nullable party_type.
-- No company_id, branch_id, tenant_id, status, note, id_document_ref, etc.

CREATE TABLE IF NOT EXISTS parties (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    party_type VARCHAR(20) NULL,
    display_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_parties_party_type CHECK (party_type IS NULL OR party_type IN ('person', 'organization')),
    KEY idx_parties_display_name (display_name),
    KEY idx_parties_email (email),
    KEY idx_parties_phone (phone),
    KEY idx_parties_party_type (party_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
