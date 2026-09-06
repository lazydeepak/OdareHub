-- Hospitality v1 local data model (foundation brief section 7).
-- All tables Hospitality-local with hosp_ prefix. No shared-app references.
-- Logical links only (+ indexed), matching existing app migration conventions.

CREATE TABLE IF NOT EXISTS hosp_rooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(40) NOT NULL,
    room_type VARCHAR(40) NOT NULL DEFAULT 'standard',
    floor INT NULL,
    room_status VARCHAR(30) NOT NULL DEFAULT 'active',
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_hosp_room_number (room_number),
    KEY idx_hosp_room_status (room_status),
    KEY idx_hosp_room_type (room_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosp_guests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(80) NULL,
    id_document_ref VARCHAR(190) NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_hosp_guest_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosp_reservations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    guest_id BIGINT NOT NULL,
    room_id BIGINT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
    children TINYINT UNSIGNED NOT NULL DEFAULT 0,
    rate DECIMAL(14,2) NULL,
    reservation_status VARCHAR(30) NOT NULL DEFAULT 'booked',
    source VARCHAR(80) NULL,
    actual_check_in_at DATETIME NULL,
    actual_check_out_at DATETIME NULL,
    note TEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_hosp_res_guest (guest_id),
    KEY idx_hosp_res_room (room_id),
    KEY idx_hosp_res_status (reservation_status),
    KEY idx_hosp_res_check_in (check_in_date),
    KEY idx_hosp_res_check_out (check_out_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosp_housekeeping_status (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT NOT NULL,
    hk_status VARCHAR(30) NOT NULL DEFAULT 'clean',
    last_cleaned_at DATETIME NULL,
    assigned_to VARCHAR(190) NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_hosp_hk_room (room_id),
    KEY idx_hosp_hk_status (hk_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosp_folios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT NOT NULL,
    folio_status VARCHAR(30) NOT NULL DEFAULT 'open',
    opened_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_hosp_folio_reservation (reservation_id),
    KEY idx_hosp_folio_status (folio_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hosp_folio_charges (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    folio_id BIGINT NOT NULL,
    charge_type VARCHAR(30) NOT NULL DEFAULT 'misc',
    description VARCHAR(255) NULL,
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    unit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    charge_status VARCHAR(30) NOT NULL DEFAULT 'posted',
    posted_at DATETIME NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_hosp_charge_folio (folio_id),
    KEY idx_hosp_charge_status (charge_status),
    KEY idx_hosp_charge_type (charge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
