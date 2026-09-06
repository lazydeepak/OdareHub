<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_ref VARCHAR(80) NOT NULL,
        customer_name VARCHAR(190) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        sale_status VARCHAR(40) NOT NULL DEFAULT 'open',
        sale_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
