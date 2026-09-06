<?php
declare(strict_types=1);

namespace App\Core;

use mysqli;

final class DB
{
    private static ?mysqli $conn = null;

    public static function conn(): mysqli
    {
        if (self::$conn instanceof mysqli) return self::$conn;

        if (!function_exists('app_db_config')) {
            require_once dirname(__DIR__, 2) . '/app/Core/helpers.php';
        }

        $cfg = app_db_config();
        if (!is_array($cfg)) {
            throw new \RuntimeException('Missing DB config. Write storage/db_config.php or set ERP_DB_HOST / ERP_DB_NAME / ERP_DB_USER / ERP_DB_PASS.');
        }

        date_default_timezone_set($cfg['timezone'] ?? 'Asia/Tokyo');

        $host = (string)($cfg['host'] ?? 'localhost');
        $name = (string)($cfg['name'] ?? '');
        $user = (string)($cfg['user'] ?? '');
        $pass = (string)($cfg['pass'] ?? '');
        $port = (int)($cfg['port'] ?? 3306);

        $mysqli = new mysqli($host, $user, $pass, $name, $port);
        if ($mysqli->connect_errno) {
            throw new \RuntimeException("DB connect failed: " . $mysqli->connect_error);
        }
        $mysqli->set_charset($cfg['charset'] ?? 'utf8mb4');
        $mysqli->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        self::$conn = $mysqli;
        return self::$conn;
    }

    public static function query(string $sql, array $params = []): \mysqli_result|bool
    {
        $db = self::conn();

        if (!$params) return $db->query($sql);

        $stmt = $db->prepare($sql);
        if (!$stmt) throw new \RuntimeException("Prepare failed: " . $db->error);

        $types = '';
        $bind = [];
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
            $bind[] = $p;
        }

        $stmt->bind_param($types, ...$bind);
        if (!$stmt->execute()) throw new \RuntimeException("Execute failed: " . $stmt->error);

        $res = $stmt->get_result();
        return $res ?: true;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $res = self::query($sql, $params);
        if ($res === true || $res === false) return [];
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = self::fetchAll($sql, $params);
        return $rows[0] ?? null;
    }
}
