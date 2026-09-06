<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_title VARCHAR(190) NOT NULL,
        owner_name VARCHAR(190) NULL,
        task_type VARCHAR(80) NULL,
        task_bucket VARCHAR(80) NULL,
        related_staff_id INT NULL,
        related_date DATE NULL,
        task_status VARCHAR(40) NOT NULL DEFAULT 'open',
        priority VARCHAR(40) NOT NULL DEFAULT 'normal',
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
