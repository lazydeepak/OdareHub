<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AppRegistryService
{
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_ENABLED = 'enabled';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_BROKEN = 'broken';
    public const STATUS_UPGRADE_PENDING = 'upgrade_pending';
    public const STATUS_UNINSTALLED = 'uninstalled';

    public const ALLOWED_STATUSES = [
        self::STATUS_UPLOADED,
        self::STATUS_INSTALLED,
        self::STATUS_ENABLED,
        self::STATUS_DISABLED,
        self::STATUS_BROKEN,
        self::STATUS_UPGRADE_PENDING,
        self::STATUS_UNINSTALLED,
    ];

    public function upsertUploaded(array $manifest, string $installPath, string $checksum, string $uploadedBy): void
    {
        DB::query(
            "INSERT INTO core_apps (
                app_key, app_name, version, app_type, status, install_path, manifest_json, checksum, installed_by
            ) VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                app_name=VALUES(app_name),
                version=VALUES(version),
                app_type=VALUES(app_type),
                status=VALUES(status),
                install_path=VALUES(install_path),
                manifest_json=VALUES(manifest_json),
                checksum=VALUES(checksum),
                installed_by=VALUES(installed_by),
                updated_at=NOW()",
            [
                (string)$manifest['id'],
                (string)$manifest['name'],
                (string)$manifest['version'],
                (string)$manifest['type'],
                self::STATUS_UPLOADED,
                $installPath,
                json_encode($manifest, JSON_UNESCAPED_SLASHES),
                $checksum,
                $uploadedBy,
            ]
        );
    }

    public function setStatus(string $appKey, string $status, ?string $errorText = null): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \RuntimeException('Unsupported app status: ' . $status);
        }

        DB::query(
            'UPDATE core_apps SET status=?, error_text=?, enabled_at = CASE WHEN ? = "enabled" THEN NOW() ELSE enabled_at END, disabled_at = CASE WHEN ? = "disabled" THEN NOW() ELSE disabled_at END, updated_at=NOW() WHERE app_key=?',
            [$status, $errorText, $status, $status, $appKey]
        );
    }

    public function listAll(): array
    {
        return DB::fetchAll('SELECT * FROM core_apps ORDER BY app_key ASC');
    }

    public function find(string $appKey): ?array
    {
        return DB::fetchOne('SELECT * FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
    }

    public function enabledApps(): array
    {
        return DB::fetchAll('SELECT * FROM core_apps WHERE status = ? ORDER BY app_key ASC', [self::STATUS_ENABLED]);
    }
}
