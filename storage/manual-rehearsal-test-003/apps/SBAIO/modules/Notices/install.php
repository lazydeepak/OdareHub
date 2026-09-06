<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        body TEXT NULL,
        notice_type VARCHAR(80) NULL,
        target_period VARCHAR(40) NULL,
        related_staff_id INT NULL,
        requires_ack TINYINT(1) NOT NULL DEFAULT 0,
        notice_level VARCHAR(40) NOT NULL DEFAULT 'info',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
