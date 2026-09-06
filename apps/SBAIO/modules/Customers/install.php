<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(190) NOT NULL,
        contact_name VARCHAR(190) NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(60) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
