<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class IdentitySecurityLogService
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        DB::query(
            'CREATE TABLE IF NOT EXISTS identity_security_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(80) NOT NULL,
                user_id INT NULL,
                email_mask VARCHAR(190) NULL,
                email_hash CHAR(64) NULL,
                ip_hash CHAR(64) NULL,
                outcome VARCHAR(40) NOT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_identity_event_type (event_type, created_at),
                INDEX idx_identity_user (user_id, created_at),
                INDEX idx_identity_email_hash (email_hash, created_at),
                INDEX idx_identity_ip_hash (ip_hash, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaEnsured = true;
    }

    public static function log(string $eventType, ?int $userId, string $email, string $ip, string $outcome, array $metadata = []): void
    {
        self::ensureSchema();

        DB::query(
            'INSERT INTO identity_security_events
             (event_type, user_id, email_mask, email_hash, ip_hash, outcome, metadata_json, created_at)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                strtolower(trim($eventType)),
                ($userId ?? 0) > 0 ? $userId : null,
                self::maskEmail($email),
                self::hashValue($email),
                self::hashValue($ip),
                strtolower(trim($outcome)) !== '' ? strtolower(trim($outcome)) : 'recorded',
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function countRecent(string $eventType, string $email, string $ip, int $windowSeconds): array
    {
        self::ensureSchema();
        $window = max(60, $windowSeconds);
        $since = date('Y-m-d H:i:s', time() - $window);

        $emailCount = 0;
        $ipCount = 0;

        $emailHash = self::hashValue($email);
        if ($emailHash !== null) {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c
                 FROM identity_security_events
                 WHERE event_type = ? AND email_hash = ? AND created_at >= ?',
                [strtolower(trim($eventType)), $emailHash, $since]
            );
            $emailCount = (int)($row['c'] ?? 0);
        }

        $ipHash = self::hashValue($ip);
        if ($ipHash !== null) {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c
                 FROM identity_security_events
                 WHERE event_type = ? AND ip_hash = ? AND created_at >= ?',
                [strtolower(trim($eventType)), $ipHash, $since]
            );
            $ipCount = (int)($row['c'] ?? 0);
        }

        return [
            'email' => $emailCount,
            'ip' => $ipCount,
        ];
    }

    private static function hashValue(string $value): ?string
    {
        $trimmed = trim(strtolower($value));
        if ($trimmed === '') {
            return null;
        }
        return hash('sha256', $trimmed);
    }

    private static function maskEmail(string $email): ?string
    {
        $value = strtolower(trim($email));
        if ($value === '' || !str_contains($value, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $value, 2);
        if ($local === '' || $domain === '') {
            return null;
        }

        $prefix = substr($local, 0, 1);
        return $prefix . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
    }
}
