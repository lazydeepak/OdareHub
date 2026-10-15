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

    /**
     * Execute exactly one application migration from independently validated
     * plan evidence. Existing runMigrations() behavior is intentionally
     * unchanged; this path never deletes failed ledger rows or discovers
     * neighboring files.
     *
     * @return array<string,mixed>
     */
    public function executePlannedStep(
        string $appKey,
        string $version,
        string $migrationsDir,
        string $migrationName,
        string $expectedChecksum,
        int $expectedPlanPosition,
        ?\Platform\Updates\ReleaseMigrationPlanEvidence $validatedPlanEvidence
    ): array {
        if (!$validatedPlanEvidence instanceof \Platform\Updates\ReleaseMigrationPlanEvidence) {
            return $this->migrationFailure('migration_plan_malformed', 'Independently validated migration plan evidence is required.');
        }
        $step = null;
        foreach ($validatedPlanEvidence->steps() as $candidate) {
            if (($candidate['position'] ?? null) === $expectedPlanPosition) {
                $step = $candidate;
                break;
            }
        }
        if (!is_array($step)
            || $step['app_key'] !== $appKey
            || $step['version'] !== $version
            || $step['migration_name'] !== $migrationName
            || $step['checksum'] !== $expectedChecksum) {
            return $this->migrationFailure('migration_plan_position_contradiction', 'Requested migration does not match the validated plan position.');
        }
        if ($expectedPlanPosition < 1 || $migrationName !== basename($migrationName)
            || str_contains($migrationName, '/') || str_contains($migrationName, '\\')
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,254}\.sql\z/D', $migrationName) !== 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $expectedChecksum) !== 1
            || !is_dir($migrationsDir) || is_link($migrationsDir)) {
            return $this->migrationFailure('migration_path_unsafe', 'Migration owner directory, basename, or checksum is unsafe.');
        }
        $directory = realpath($migrationsDir);
        $file = $directory === false ? false : realpath($directory . '/' . $migrationName);
        if ($directory === false || $file === false || !is_file($file) || is_link($directory . '/' . $migrationName)
            || !str_starts_with($file, rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return $this->migrationFailure('migration_path_unsafe', 'Migration file is missing or escapes the owner directory.');
        }
        $sql = @file_get_contents($file);
        if (!is_string($sql)) {
            return $this->migrationFailure('migration_unavailable', 'Exact migration SQL bytes could not be read.');
        }
        $checksum = hash('sha256', $sql);
        if (!hash_equals($expectedChecksum, $checksum)) {
            return $this->migrationFailure('migration_checksum_mismatch', 'Exact migration SQL checksum differs from plan evidence.');
        }

        try {
            $owner = DB::fetchOne('SELECT app_key FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
        } catch (\Throwable $e) {
            return $this->migrationFailure('migration_owner_unrecognized', 'Application owner registry could not be read.', ['error_text' => $e->getMessage()]);
        }
        if (!is_array($owner) || (string)($owner['app_key'] ?? '') !== $appKey) {
            return $this->migrationFailure('migration_owner_unrecognized', 'Application owner is not registered.');
        }

        $existing = DB::fetchOne(
            'SELECT id, version, checksum, status, error_text FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1',
            [$appKey, $migrationName]
        );
        if (is_array($existing)) {
            $status = (string)($existing['status'] ?? '');
            $ledgerChecksum = (string)($existing['checksum'] ?? '');
            if ($status === 'applied' && hash_equals($expectedChecksum, $ledgerChecksum)) {
                return ['ok' => true, 'state' => 'migration_already_applied', 'migration_name' => $migrationName, 'plan_position' => $expectedPlanPosition, 'sql_executed' => false];
            }
            if ($status === 'applied') {
                return $this->migrationFailure('migration_checksum_mismatch', 'Applied ledger checksum differs from plan evidence.');
            }
            if ($status === 'failed') {
                return $this->migrationFailure('migration_recovery_required', 'Existing failed migration evidence blocks automatic retry.', ['error_text' => $existing['error_text'] ?? null]);
            }
            return $this->migrationFailure('migration_state_ambiguous', 'Existing migration ledger status is unsupported.');
        }

        foreach ($step['requires'] as $requirement) {
            [$requiredApp, $requiredMigration] = explode(':', (string)$requirement, 2);
            $required = DB::fetchOne(
                'SELECT checksum, status FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1',
                [$requiredApp, $requiredMigration]
            );
            if (!is_array($required) || ($required['status'] ?? null) !== 'applied') {
                return $this->migrationFailure('migration_dependency_missing', 'A required prior migration is not applied.', ['requires' => $requirement]);
            }
            $requiredStep = null;
            foreach ($validatedPlanEvidence->steps() as $candidate) {
                if (($candidate['app_key'] . ':' . $candidate['migration_name']) === $requirement) {
                    $requiredStep = $candidate;
                    break;
                }
            }
            if (!is_array($requiredStep) || !hash_equals((string)$required['checksum'], (string)$requiredStep['checksum'])) {
                return $this->migrationFailure('migration_checksum_mismatch', 'A required prior migration checksum is contradictory.', ['requires' => $requirement]);
            }
        }
        foreach ($validatedPlanEvidence->steps() as $prior) {
            if ($prior['position'] >= $expectedPlanPosition) {
                break;
            }
            $priorRow = DB::fetchOne(
                'SELECT checksum, status FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1',
                [$prior['app_key'], $prior['migration_name']]
            );
            if (!is_array($priorRow) || ($priorRow['status'] ?? null) !== 'applied' || !hash_equals((string)$priorRow['checksum'], (string)$prior['checksum'])) {
                return $this->migrationFailure('migration_plan_position_contradiction', 'A prior plan position is not applied with the expected checksum.', ['position' => $prior['position']]);
            }
        }

        try {
            AppSchemaSyncService::runAdditiveSql($sql);
        } catch (\Throwable $e) {
            try {
                DB::query(
                    'INSERT INTO core_app_migrations (app_key, version, migration_name, checksum, status, error_text) VALUES (?,?,?,?,?,?)',
                    [$appKey, $version, $migrationName, $expectedChecksum, 'failed', $e->getMessage()]
                );
                return $this->migrationFailure('migration_recovery_required', 'Migration SQL failed; durable failure evidence was preserved.', ['error_text' => $e->getMessage()]);
            } catch (\Throwable $ledgerFailure) {
                return $this->migrationFailure('migration_state_ambiguous', 'Migration SQL failed and failure evidence could not be persisted.', ['error_text' => $e->getMessage(), 'ledger_error' => $ledgerFailure->getMessage()]);
            }
        }
        try {
            DB::query(
                'INSERT INTO core_app_migrations (app_key, version, migration_name, checksum, status) VALUES (?,?,?,?,?)',
                [$appKey, $version, $migrationName, $expectedChecksum, 'applied']
            );
        } catch (\Throwable $e) {
            return $this->migrationFailure('migration_state_ambiguous', 'Migration SQL succeeded but applied ledger evidence could not be persisted.', ['error_text' => $e->getMessage()]);
        }
        return ['ok' => true, 'state' => 'migration_applied', 'migration_name' => $migrationName, 'plan_position' => $expectedPlanPosition, 'sql_executed' => true, 'checksum' => $expectedChecksum];
    }

    /** @param array<string,mixed> $details @return array<string,mixed> */
    private function migrationFailure(string $state, string $message, array $details = []): array
    {
        return ['ok' => false, 'state' => $state, 'message' => $message, 'details' => $details, 'sql_executed' => false];
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
