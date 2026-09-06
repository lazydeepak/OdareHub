<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class AppLifecycleService
{
    private AppRegistryService $registry;

    public function __construct(?AppRegistryService $registry = null)
    {
        $this->registry = $registry ?? new AppRegistryService();
    }

    public function enable(string $appKey): void
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }

        if ((string)$row['status'] === AppRegistryService::STATUS_UPLOADED) {
            throw new \RuntimeException('Install app before enabling');
        }

        $this->assertRuntimeReady($row);

        $manifest = $this->resolvedManifest($row);
        $runtimeRegistry = new AppRuntimeRegistryService();
        $previousStatus = (string)($row['status'] ?? AppRegistryService::STATUS_INSTALLED);

        try {
            $legacyBridgeResults = AppLegacyBridgeService::prepareLegacyPluginsForActivation($appKey, is_array($manifest) ? $manifest : null);
            $refresh = $runtimeRegistry->refreshAppRuntimeArtifacts($appKey, is_array($manifest) ? $manifest : null, true);
            $runtimeRegistry->syncEnabledRuntimeRegistries();
            $runtimeRegistry->emitBootHooksForApp($appKey);
            $this->registry->setStatus($appKey, AppRegistryService::STATUS_ENABLED, null);
            AppPlatformLogger::lifecycle('enabled', $appKey, ['legacy_bridge' => $legacyBridgeResults, 'runtime_refresh' => $refresh]);
        } catch (\Throwable $e) {
            try {
                AppLegacyBridgeService::setLegacyPluginRuntimeStatus($appKey, is_array($manifest) ? $manifest : null, false);
                $runtimeRegistry->refreshAppRuntimeArtifacts($appKey, is_array($manifest) ? $manifest : null, false);
                $runtimeRegistry->syncEnabledRuntimeRegistries();
            } catch (\Throwable $rollbackError) {
                AppPlatformLogger::lifecycle('enable_rollback_failed', $appKey, [
                    'phase' => 'bundle_activation',
                    'error' => $rollbackError->getMessage(),
                ]);
            }

            $fallbackStatus = in_array($previousStatus, [
                AppRegistryService::STATUS_INSTALLED,
                AppRegistryService::STATUS_DISABLED,
                AppRegistryService::STATUS_UNINSTALLED,
                AppRegistryService::STATUS_UPLOADED,
            ], true) ? $previousStatus : AppRegistryService::STATUS_DISABLED;

            $this->registry->setStatus($appKey, $fallbackStatus, $e->getMessage());
            AppPlatformLogger::lifecycle('enable_failed', $appKey, [
                'phase' => 'bundle_activation',
                'error' => $e->getMessage(),
                'restored_status' => $fallbackStatus,
            ]);
            throw new \RuntimeException('Bundle activation failed for ' . $appKey . ': ' . $e->getMessage(), 0, $e);
        }
    }

    public function disable(string $appKey): void
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }
        if ((string)($row['app_type'] ?? '') === 'engine') {
            throw new \RuntimeException('Engine components are permanent and cannot be disabled');
        }

        $manifest = $this->resolvedManifest($row);
        $canDisable = (bool)($manifest['can_disable'] ?? true);
        if (!$canDisable) {
            throw new \RuntimeException('App cannot be disabled according to manifest');
        }

        $this->registry->setStatus($appKey, AppRegistryService::STATUS_DISABLED, null);
        $runtimeRegistry = new AppRuntimeRegistryService();
        $refresh = $runtimeRegistry->refreshAppRuntimeArtifacts($appKey, is_array($manifest) ? $manifest : null, false);
        AppLegacyBridgeService::setLegacyPluginRuntimeStatus($appKey, is_array($manifest) ? $manifest : null, false);
        $runtimeRegistry->syncEnabledRuntimeRegistries();

        AppPlatformLogger::lifecycle('disabled', $appKey, ['runtime_refresh' => $refresh]);
    }

    public function uninstallSoft(string $appKey): void
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }
        if ((string)($row['app_type'] ?? '') === 'engine') {
            throw new \RuntimeException('Engine components are permanent and cannot be uninstalled');
        }

        $manifest = $this->resolvedManifest($row);
        $canUninstall = (bool)($manifest['can_uninstall'] ?? true);
        if (!$canUninstall) {
            throw new \RuntimeException('App cannot be uninstalled according to manifest');
        }

        $runtimeRegistry = new AppRuntimeRegistryService();
        $refresh = $runtimeRegistry->refreshAppRuntimeArtifacts($appKey, is_array($manifest) ? $manifest : null, false);
        AppLegacyBridgeService::setLegacyPluginRuntimeStatus($appKey, is_array($manifest) ? $manifest : null, false);

        $this->registry->setStatus($appKey, AppRegistryService::STATUS_UNINSTALLED, null);
        $runtimeRegistry->syncEnabledRuntimeRegistries();
        AppPlatformLogger::lifecycle('uninstalled_soft', $appKey, ['destructive' => false, 'runtime_refresh' => $refresh]);
    }

    public function purge(string $appKey): void
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }
        if ((string)($row['app_type'] ?? '') === 'engine') {
            throw new \RuntimeException('Engine components are permanent and cannot be purged');
        }

        $manifest = $this->resolvedManifest($row);

        $canPurge = (bool)($manifest['can_uninstall'] ?? true);
        if (!$canPurge) {
            throw new \RuntimeException('App cannot be purged according to manifest');
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            (new AppRuntimeRegistryService())->refreshAppRuntimeArtifacts($appKey, $manifest, false);

            AppLegacyBridgeService::setLegacyPluginRuntimeStatus($appKey, $manifest, false);
            $purgedLegacyPlugins = AppLegacyBridgeService::purgeLegacyPluginsForApp($appKey, $manifest);

            $this->purgeManifestDataArtifacts($manifest);

            DB::query('DELETE FROM core_app_hooks WHERE app_key=?', [$appKey]);
            DB::query('DELETE FROM core_app_permissions WHERE app_key=?', [$appKey]);
            DB::query('DELETE FROM core_app_modules WHERE app_key=?', [$appKey]);
            DB::query('DELETE FROM core_app_migrations WHERE app_key=?', [$appKey]);
            DB::query('DELETE FROM core_schema_snapshots WHERE app_key=?', [$appKey]);

            $this->registry->setStatus($appKey, AppRegistryService::STATUS_UNINSTALLED, null);

            $installPath = trim((string)($row['install_path'] ?? ''));
            if ($installPath !== '' && is_dir($installPath) && str_starts_with(realpath($installPath) ?: '', realpath(dirname(__DIR__, 2) . '/apps') ?: '')) {
                $this->rrmdir($installPath);
            }

            DB::query('DELETE FROM core_apps WHERE app_key=?', [$appKey]);

            $db->commit();
            AppPlatformLogger::lifecycle('purged', $appKey, [
                'destructive' => true,
                'legacy_plugins' => $purgedLegacyPlugins,
            ]);
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public function markUpgradePending(string $appKey): void
    {
        $this->registry->setStatus($appKey, AppRegistryService::STATUS_UPGRADE_PENDING, null);
        AppPlatformLogger::lifecycle('upgrade_pending', $appKey);
    }

    public function recoverFromBroken(string $appKey): void
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }

        $status = (string)($row['status'] ?? '');
        if ($status !== AppRegistryService::STATUS_BROKEN && $status !== AppRegistryService::STATUS_UPGRADE_PENDING) {
            throw new \RuntimeException('Recovery is allowed only for broken or upgrade_pending apps');
        }

        $this->assertRuntimeReady($row);
        $manifest = $this->resolvedManifest($row);
        $runtimeRegistry = new AppRuntimeRegistryService();
        $refresh = $runtimeRegistry->refreshAppRuntimeArtifacts($appKey, is_array($manifest) ? $manifest : null, false);
        $legacyBridgeResults = AppLegacyBridgeService::repairLegacyPluginsForApp($appKey, is_array($manifest) ? $manifest : null, false);
        $this->registry->setStatus($appKey, AppRegistryService::STATUS_DISABLED, null);
        $runtimeRegistry->syncEnabledRuntimeRegistries();
        AppPlatformLogger::lifecycle('recovered_to_disabled', $appKey, ['legacy_bridge' => $legacyBridgeResults, 'runtime_refresh' => $refresh]);
    }

    /**
     * @return array<int,string>
     */
    public function repair(string $appKey): array
    {
        $row = $this->registry->find($appKey);
        if (!$row) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }

        $status = (string)($row['status'] ?? '');
        if (!in_array($status, [
            AppRegistryService::STATUS_INSTALLED,
            AppRegistryService::STATUS_ENABLED,
            AppRegistryService::STATUS_DISABLED,
            AppRegistryService::STATUS_BROKEN,
            AppRegistryService::STATUS_UPGRADE_PENDING,
        ], true)) {
            throw new \RuntimeException('Repair is allowed only for installed apps');
        }

        $this->assertRuntimeReady($row);
        $manifest = $this->resolvedManifest($row);
        $runtimeRegistry = new AppRuntimeRegistryService();
        $installPath = trim((string)($row['install_path'] ?? ''));
        $migrationsPath = trim((string)($manifest['migrations_path'] ?? ''));
        $appliedMigrations = [];
        if ($installPath !== '' && $migrationsPath !== '') {
            $migrationService = new AppMigrationService();
            $appliedMigrations = $migrationService->runMigrations(
                $appKey,
                (string)($row['version'] ?? '0.0.0'),
                rtrim($installPath, '/') . '/' . ltrim($migrationsPath, '/')
            );
            if ($appliedMigrations !== []) {
                $snapshot = [
                    'app_key' => $appKey,
                    'version' => (string)($row['version'] ?? '0.0.0'),
                    'applied_migrations' => $appliedMigrations,
                ];
                $migrationService->writeSchemaSnapshot($appKey, (string)($row['version'] ?? '0.0.0'), $snapshot, (string)(Auth::user()['email'] ?? 'system'));
            }
        }
        $legacyBridgeResults = AppLegacyBridgeService::repairLegacyPluginsForApp(
            $appKey,
            is_array($manifest) ? $manifest : null,
            $status === AppRegistryService::STATUS_ENABLED
        );
        $refresh = $runtimeRegistry->refreshAppRuntimeArtifacts(
            $appKey,
            is_array($manifest) ? $manifest : null,
            $status === AppRegistryService::STATUS_ENABLED
        );

        if ($status === AppRegistryService::STATUS_ENABLED) {
            AppLegacyBridgeService::setLegacyPluginRuntimeStatus($appKey, is_array($manifest) ? $manifest : null, true);
            $runtimeRegistry->syncEnabledRuntimeRegistries();
            $runtimeRegistry->emitBootHooksForApp($appKey);
        } else {
            $runtimeRegistry->syncEnabledRuntimeRegistries();
        }

        if (in_array($status, [AppRegistryService::STATUS_BROKEN, AppRegistryService::STATUS_UPGRADE_PENDING], true)) {
            $this->registry->setStatus($appKey, AppRegistryService::STATUS_DISABLED, null);
        }

        AppPlatformLogger::lifecycle('repaired', $appKey, [
            'legacy_bridge' => $legacyBridgeResults,
            'runtime_refresh' => $refresh,
            'bundle_migrations' => $appliedMigrations,
        ]);
        return $legacyBridgeResults;
    }

    private function assertRuntimeReady(array $row): void
    {
        $installPath = trim((string)($row['install_path'] ?? ''));
        if ($installPath === '' || !is_dir($installPath)) {
            throw new \RuntimeException('App install_path is missing or invalid');
        }

        $manifest = $this->resolvedManifest($row);

        $entry = trim((string)($manifest['entry'] ?? ''));
        if ($entry === '') {
            throw new \RuntimeException('App manifest entry is empty');
        }

        $entryFile = rtrim($installPath, '/') . '/' . ltrim($entry, '/');
        if (!is_file($entryFile)) {
            throw new \RuntimeException('App runtime entry file not found: ' . $entry);
        }
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function purgeManifestDataArtifacts(array $manifest): void
    {
        $purge = is_array($manifest['purge'] ?? null) ? (array)$manifest['purge'] : [];

        foreach ((array)($purge['tables'] ?? []) as $tableRaw) {
            $table = trim((string)$tableRaw);
            if ($table === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }
            DB::query('DROP TABLE IF EXISTS `' . $table . '`');
        }

        foreach ((array)($purge['drop_columns'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $table = trim((string)($entry['table'] ?? ''));
            $column = trim((string)($entry['column'] ?? ''));
            if ($table === '' || $column === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                continue;
            }

            $exists = DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                [$table, $column]
            );
            if (!$exists) {
                continue;
            }

            DB::query('ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`');
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function resolvedManifest(array $row): array
    {
        $installPath = trim((string)($row['install_path'] ?? ''));
        $manifestPath = $installPath !== '' ? rtrim($installPath, '/') . '/manifest.json' : '';

        if ($manifestPath !== '' && is_file($manifestPath)) {
            try {
                return AppManifestService::loadFromFile($manifestPath);
            } catch (\Throwable $e) {
                // Fall back to the stored manifest when the live file is temporarily invalid.
            }
        }

        $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
        return is_array($manifest) ? $manifest : [];
    }
}
