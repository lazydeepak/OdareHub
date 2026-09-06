CREATE TABLE IF NOT EXISTS pre_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forecast_type VARCHAR(60) NOT NULL,
    planning_priority VARCHAR(40) NOT NULL DEFAULT 'Normal',
    product_id INT NOT NULL,
    planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    required_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pre_orders_product (product_id),
    KEY idx_pre_orders_required_date (required_date),
    KEY idx_pre_orders_priority (planning_priority),
    KEY idx_pre_orders_forecast_type (forecast_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
