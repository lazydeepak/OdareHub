<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AppMigrationService
{
    public function ensureCoreTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_apps (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL UNIQUE,
            app_name VARCHAR(190) NOT NULL,
            version VARCHAR(50) NOT NULL,
            app_type VARCHAR(50) NOT NULL,
            status ENUM('uploaded','installed','enabled','disabled','broken','upgrade_pending','uninstalled') NOT NULL DEFAULT 'uploaded',
            install_path VARCHAR(255) NOT NULL,
            manifest_json JSON NOT NULL,
            checksum VARCHAR(128) NULL,
            installed_at TIMESTAMP NULL DEFAULT NULL,
            enabled_at TIMESTAMP NULL DEFAULT NULL,
            disabled_at TIMESTAMP NULL DEFAULT NULL,
            installed_by VARCHAR(190) NULL,
            error_text TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_core_apps_status (status),
            INDEX idx_core_apps_type (app_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_app_modules (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL,
            module_key VARCHAR(120) NOT NULL,
            module_name VARCHAR(190) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_app_module (app_key, module_key),
            INDEX idx_core_app_modules_app_key (app_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_app_migrations (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL,
            version VARCHAR(50) NOT NULL,
            migration_name VARCHAR(255) NOT NULL,
            checksum VARCHAR(128) NULL,
            status ENUM('applied','failed') NOT NULL DEFAULT 'applied',
            error_text TEXT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_core_app_migration (app_key, migration_name),
            INDEX idx_core_app_migrations_app_key (app_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_app_permissions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL,
            permission_key VARCHAR(190) NOT NULL,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_core_app_permission (app_key, permission_key),
            INDEX idx_core_app_permissions_app_key (app_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_app_hooks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL,
            hook_type VARCHAR(50) NOT NULL,
            hook_key VARCHAR(190) NOT NULL,
            payload_json JSON NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_core_app_hooks_app_key (app_key),
            INDEX idx_core_app_hooks_lookup (hook_type, hook_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_schema_snapshots (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(120) NOT NULL,
            app_version VARCHAR(50) NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            checksum VARCHAR(128) NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_core_schema_snapshots_app_key (app_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function runMigrations(string $appKey, string $version, string $migrationsDir): array
    {
        if (!is_dir($migrationsDir)) {
            return [];
        }

        $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $migrationName = basename($file);
            $existing = DB::fetchOne(
                'SELECT id, status FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1',
                [$appKey, $migrationName]
            );
            if (is_array($existing) && (string)($existing['status'] ?? '') === 'applied') {
                continue;
            }
            if (is_array($existing) && (string)($existing['status'] ?? '') === 'failed') {
                DB::query('DELETE FROM core_app_migrations WHERE id=? LIMIT 1', [(int)($existing['id'] ?? 0)]);
            }

            $sql = (string)file_get_contents($file);
            $checksum = hash('sha256', $sql);

            try {
                AppSchemaSyncService::runAdditiveSql($sql);
                DB::query(
                    'INSERT INTO core_app_migrations (app_key, version, migration_name, checksum, status) VALUES (?,?,?,?,?)',
                    [$appKey, $version, $migrationName, $checksum, 'applied']
                );
                $applied[] = $migrationName;
            } catch (\Throwable $e) {
                DB::query(
                    'INSERT INTO core_app_migrations (app_key, version, migration_name, checksum, status, error_text) VALUES (?,?,?,?,?,?)',
                    [$appKey, $version, $migrationName, $checksum, 'failed', $e->getMessage()]
                );
                throw $e;
            }
        }

        return $applied;
    }

    public function writeSchemaSnapshot(string $appKey, string $appVersion, array $snapshot, string $createdBy): void
    {
        $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        DB::query(
            'INSERT INTO core_schema_snapshots (app_key, app_version, snapshot_json, checksum, created_by) VALUES (?,?,?,?,?)',
            [$appKey, $appVersion, $json, hash('sha256', $json), $createdBy]
        );
    }
}
