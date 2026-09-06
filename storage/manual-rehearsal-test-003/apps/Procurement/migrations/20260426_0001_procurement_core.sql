CREATE TABLE IF NOT EXISTS procurement_suppliers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(40) NULL,
    supplier_name VARCHAR(190) NOT NULL,
    contact_name VARCHAR(190) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(80) NULL,
    supplier_status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proc_supplier_status (supplier_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procurement_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_ref VARCHAR(60) NOT NULL,
    product_id INT NULL,
    requested_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    needed_date DATE NULL,
    source_app VARCHAR(80) NULL,
    source_ref_type VARCHAR(80) NULL,
    source_ref_id VARCHAR(80) NULL,
    request_status VARCHAR(30) NOT NULL DEFAULT 'draft',
    note TEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_proc_request_ref (request_ref),
    KEY idx_proc_request_status (request_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procurement_purchase_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    po_ref VARCHAR(60) NOT NULL,
    supplier_id BIGINT NULL,
    request_id BIGINT NULL,
    po_status VARCHAR(30) NOT NULL DEFAULT 'draft',
    order_date DATE NULL,
    expected_date DATE NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_proc_po_ref (po_ref),
    KEY idx_proc_po_status (po_status),
    KEY idx_proc_po_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procurement_purchase_order_lines (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    po_id BIGINT NOT NULL,
    product_id INT NULL,
    line_description VARCHAR(255) NULL,
    ordered_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proc_po_line_po (po_id),
    KEY idx_proc_po_line_status (line_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS procurement_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    receipt_ref VARCHAR(60) NOT NULL,
    po_id BIGINT NULL,
    po_line_id BIGINT NULL,
    received_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    receipt_date DATE NULL,
    receipt_status VARCHAR(30) NOT NULL DEFAULT 'received',
    note TEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_proc_receipt_ref (receipt_ref),
    KEY idx_proc_receipt_status (receipt_status),
    KEY idx_proc_receipt_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
