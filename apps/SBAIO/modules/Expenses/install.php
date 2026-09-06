<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_ref VARCHAR(80) NOT NULL,
        category_name VARCHAR(120) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        expense_status VARCHAR(40) NOT NULL DEFAULT 'submitted',
        expense_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
