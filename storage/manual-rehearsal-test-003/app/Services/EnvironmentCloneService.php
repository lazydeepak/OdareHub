<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use ZipArchive;

final class EnvironmentCloneService
{
    private EnvironmentSnapshotService $snapshots;
    private ModuleLifecycleService $modules;

    public function __construct(
        ?EnvironmentSnapshotService $snapshots = null,
        ?ModuleLifecycleService $modules = null
    ) {
        $this->snapshots = $snapshots ?? new EnvironmentSnapshotService();
        $this->modules = $modules ?? new ModuleLifecycleService();
    }

    /**
     * @return array<string,mixed>
     */
    public function previewImport(string $snapshotPath, string $sourceName): array
    {
        $parsed = $this->parseSnapshot($snapshotPath, $sourceName);
        $metadata = (array)($parsed['metadata'] ?? []);
        $config = (array)($parsed['config'] ?? []);
        $data = (array)($parsed['data'] ?? []);
        $scopeKey = (string)($metadata['scope_key'] ?? 'metadata_only');

        $sourceEnv = (array)($metadata['source_environment'] ?? []);
        $targetEnv = $this->snapshots->currentEnvironmentInfo();
        $targetSuites = $this->currentSuitesIndex();
        $targetModules = $this->currentModulesIndex();

        $sourceSuites = [];
        foreach ((array)($metadata['installed_suites'] ?? []) as $row) {
            if (is_array($row) && trim((string)($row['app_key'] ?? '')) !== '') {
                $sourceSuites[(string)$row['app_key']] = $row;
            }
        }

        $sourceModules = [];
        foreach ((array)($metadata['installed_modules'] ?? []) as $row) {
            if (is_array($row) && trim((string)($row['name'] ?? '')) !== '') {
                $sourceModules[(string)$row['name']] = $row;
            }
        }

        $missingSuites = [];
        $versionDifferences = [];
        foreach ($sourceSuites as $appKey => $suite) {
            $target = $targetSuites[$appKey] ?? null;
            if (!is_array($target)) {
                $missingSuites[] = $appKey;
                continue;
            }
            $sourceVersion = (string)($suite['version'] ?? '');
            $targetVersion = (string)($target['version'] ?? '');
            if ($sourceVersion !== '' && $targetVersion !== '' && $sourceVersion !== $targetVersion) {
                $versionDifferences[] = [
                    'type' => 'suite',
                    'name' => $appKey,
                    'source_version' => $sourceVersion,
                    'target_version' => $targetVersion,
                ];
            }
        }

        $missingModules = [];
        foreach ($sourceModules as $moduleName => $module) {
            $target = $targetModules[$moduleName] ?? null;
            if (!is_array($target)) {
                $missingModules[] = $moduleName;
                continue;
            }
            $sourceVersion = (string)($module['version'] ?? '');
            $targetVersion = (string)($target['version'] ?? '');
            if ($sourceVersion !== '' && $targetVersion !== '' && $sourceVersion !== $targetVersion) {
                $versionDifferences[] = [
                    'type' => 'module',
                    'name' => $moduleName,
                    'source_version' => $sourceVersion,
                    'target_version' => $targetVersion,
                ];
            }
        }

        $schemaDrift = [];
        $overwriteRisks = [];
        foreach (array_keys((array)($data['tables'] ?? [])) as $table) {
            try {
                $count = (int)(DB::fetchOne('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`')['c'] ?? 0);
                if ($count > 0) {
                    $overwriteRisks[] = $table . ' already contains ' . $count . ' row(s).';
                }
            } catch (\Throwable $e) {
                $schemaDrift[] = 'Target environment is missing table: ' . $table;
            }
        }

        $configDifferences = [];
        $currentSettings = $this->currentSettings();
        foreach ((array)($config['settings'] ?? []) as $key => $value) {
            $key = (string)$key;
            $incoming = (string)$value;
            $current = (string)($currentSettings[$key] ?? '');
            if ($current !== $incoming) {
                $configDifferences[] = [
                    'key' => $key,
                    'source_value' => $incoming,
                    'target_value' => $current,
                ];
            }
        }

        $warnings = [];
        $errors = [];
        if ($missingSuites !== []) {
            $warnings[] = 'Missing suites on target: ' . implode(', ', $missingSuites);
        }
        if ($missingModules !== []) {
            $warnings[] = 'Missing modules on target: ' . implode(', ', $missingModules);
        }
        if ($versionDifferences !== []) {
            $warnings[] = 'Version differences were found between source and target.';
        }
        if ($schemaDrift !== []) {
            $errors[] = 'Schema drift detected between the snapshot and the target environment.';
        }
        if ($overwriteRisks !== []) {
            $warnings[] = 'Applying data will overwrite or collide with existing live rows.';
        }

        return [
            'scope_key' => $scopeKey,
            'scope_label' => $this->snapshots->scopeOptions()[$scopeKey] ?? $scopeKey,
            'source_environment' => $sourceEnv,
            'target_environment' => $targetEnv,
            'metadata' => $metadata,
            'has_config' => $config !== [],
            'has_data' => $data !== [],
            'available_apply_scopes' => $this->availableApplyScopes($scopeKey, $config !== [], $data !== []),
            'version_differences' => $versionDifferences,
            'missing_suites' => $missingSuites,
            'missing_modules' => $missingModules,
            'schema_drift' => $schemaDrift,
            'config_differences' => $configDifferences,
            'overwrite_risks' => $overwriteRisks,
            'warnings' => $warnings,
            'errors' => $errors,
            'warning_text' => implode(' ', array_merge($warnings, $errors)),
            'conflict_summary' => [
                'warning_count' => count($warnings),
                'error_count' => count($errors),
                'missing_suite_count' => count($missingSuites),
                'missing_module_count' => count($missingModules),
                'version_difference_count' => count($versionDifferences),
                'schema_drift_count' => count($schemaDrift),
                'config_difference_count' => count($configDifferences),
            ],
            'snapshot_path' => $snapshotPath,
            'source_file' => $sourceName,
        ];
    }

    /**
     * @param array<string,mixed> $previewPayload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function applyPreview(array $previewPayload, array $options): array
    {
        $preview = (array)($previewPayload['preview'] ?? []);
        $snapshotPath = (string)($preview['snapshot_path'] ?? '');
        if ($snapshotPath === '' || !is_file($snapshotPath)) {
            throw new \RuntimeException('Environment clone preview has expired. Upload the snapshot again.');
        }

        $applyScope = trim((string)($options['apply_scope'] ?? 'metadata_only'));
        $availableScopes = array_values(array_map('strval', (array)($preview['available_apply_scopes'] ?? [])));
        if (!in_array($applyScope, $availableScopes, true)) {
            throw new \RuntimeException('Selected apply scope is not available for this snapshot.');
        }

        $installMissing = !empty($options['install_missing_dependencies']);
        $conflictStrategy = trim((string)($options['conflict_strategy'] ?? 'merge'));
        if (!in_array($conflictStrategy, ['merge', 'overwrite'], true)) {
            throw new \RuntimeException('Unsupported environment clone conflict strategy.');
        }

        if (!$installMissing && (!empty($preview['missing_suites']) || !empty($preview['missing_modules']))) {
            throw new \RuntimeException('Install missing suites/modules first, or choose the install-missing option.');
        }
        if (!empty($preview['schema_drift']) && $applyScope === 'core_suites_data') {
            throw new \RuntimeException('Schema drift must be resolved before applying snapshot data.');
        }

        $parsed = $this->parseSnapshot($snapshotPath, (string)($preview['source_file'] ?? basename($snapshotPath)));
        $metadata = (array)($parsed['metadata'] ?? []);
        $config = (array)($parsed['config'] ?? []);
        $data = (array)($parsed['data'] ?? []);

        if (in_array($applyScope, ['config_only', 'core_only', 'core_suites', 'core_suites_data'], true)) {
            $this->applyConfig((array)($config['settings'] ?? []), $conflictStrategy);
        }

        if (in_array($applyScope, ['core_suites', 'core_suites_data'], true)) {
            $this->applySuitesAndModules($metadata, $installMissing);
        }

        if ($applyScope === 'core_suites_data') {
            $this->restoreData((array)($data['tables'] ?? []), $conflictStrategy);
        }

        return [
            'apply_scope' => $applyScope,
            'source_environment' => (array)($preview['source_environment'] ?? []),
            'target_environment' => $this->snapshots->currentEnvironmentInfo(),
            'conflict_strategy' => $conflictStrategy,
            'installed_missing_dependencies' => $installMissing,
            'warning_text' => (string)($preview['warning_text'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function parseSnapshot(string $snapshotPath, string $sourceName): array
    {
        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $decoded = json_decode((string)file_get_contents($snapshotPath), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Environment snapshot JSON is invalid.');
            }
            return [
                'metadata' => (array)($decoded['metadata'] ?? $decoded),
                'config' => (array)($decoded['config'] ?? []),
                'data' => (array)($decoded['data'] ?? []),
            ];
        }

        if ($ext !== 'zip') {
            throw new \RuntimeException('Unsupported snapshot type. Use an environment snapshot .zip or .json file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($snapshotPath) !== true) {
            throw new \RuntimeException('Could not open environment snapshot archive.');
        }

        $metadataRaw = $zip->getFromName('metadata.json');
        if (!is_string($metadataRaw)) {
            $zip->close();
            throw new \RuntimeException('Environment snapshot archive is missing metadata.json.');
        }
        $metadata = json_decode($metadataRaw, true);
        if (!is_array($metadata)) {
            $zip->close();
            throw new \RuntimeException('Environment snapshot metadata is invalid.');
        }

        $config = [];
        $configRaw = $zip->getFromName('config.json');
        if (is_string($configRaw)) {
            $decoded = json_decode($configRaw, true);
            $config = is_array($decoded) ? $decoded : [];
        }

        $data = [];
        $dataRaw = $zip->getFromName('data.json');
        if (is_string($dataRaw)) {
            $decoded = json_decode($dataRaw, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        $zip->close();

        return [
            'metadata' => $metadata,
            'config' => $config,
            'data' => $data,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function availableApplyScopes(string $snapshotScope, bool $hasConfig, bool $hasData): array
    {
        return match ($snapshotScope) {
            'metadata_only' => ['metadata_only'],
            'config_only' => ['metadata_only', 'config_only'],
            'core_only' => ['metadata_only', 'config_only', 'core_only'],
            'core_suites' => ['metadata_only', 'config_only', 'core_only', 'core_suites'],
            'core_suites_data' => $hasData
                ? ['metadata_only', 'config_only', 'core_only', 'core_suites', 'core_suites_data']
                : ['metadata_only', 'config_only', 'core_only', 'core_suites'],
            default => $hasConfig ? ['metadata_only', 'config_only'] : ['metadata_only'],
        };
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function currentSuitesIndex(): array
    {
        $rows = DB::fetchAll('SELECT app_key, version, status FROM core_apps ORDER BY app_key ASC');
        $index = [];
        foreach ($rows as $row) {
            $index[(string)($row['app_key'] ?? '')] = $row;
        }
        return $index;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function currentModulesIndex(): array
    {
        $index = [];
        foreach ($this->modules->modulesBySuite() as $modules) {
            foreach ($modules as $module) {
                $index[(string)($module['name'] ?? '')] = $module;
            }
        }
        return $index;
    }

    /**
     * @return array<string,string>
     */
    private function currentSettings(): array
    {
        $rows = DB::fetchAll('SELECT setting_key, setting_value FROM core_settings');
        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['setting_key'] ?? '')] = (string)($row['setting_value'] ?? '');
        }
        return $map;
    }

    /**
     * @param array<string,string> $settings
     */
    private function applyConfig(array $settings, string $strategy): void
    {
        foreach ($settings as $key => $value) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }
            $existing = DB::fetchOne('SELECT setting_key FROM core_settings WHERE setting_key=? LIMIT 1', [$key]);
            if ($existing && $strategy === 'merge') {
                continue;
            }
            if ($existing) {
                DB::query('UPDATE core_settings SET setting_value=? WHERE setting_key=?', [(string)$value, $key]);
            } else {
                DB::query('INSERT INTO core_settings (setting_key, setting_value) VALUES (?, ?)', [$key, (string)$value]);
            }
        }
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function applySuitesAndModules(array $metadata, bool $installMissing): void
    {
        (new AppLocalDiscoveryService())->syncLocalApps();

        foreach ((array)($metadata['installed_suites'] ?? []) as $suite) {
            if (!is_array($suite)) {
                continue;
            }
            $appKey = trim((string)($suite['app_key'] ?? ''));
            if ($appKey === '') {
                continue;
            }
            $current = DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
            if (!$current && !$installMissing) {
                throw new \RuntimeException('Missing suite on target: ' . $appKey);
            }
            if ((!$current || in_array((string)($current['status'] ?? ''), ['uploaded', 'uninstalled'], true)) && $installMissing) {
                (new AppInstallService())->install($appKey);
            }
            if ((string)($suite['status'] ?? '') === AppRegistryService::STATUS_ENABLED) {
                try {
                    (new AppLifecycleService())->enable($appKey);
                } catch (\Throwable $e) {
                }
            }
        }

        $pluginManager = $this->pluginManager();
        $sourceModules = [];
        foreach ((array)($metadata['installed_modules'] ?? []) as $module) {
            if (is_array($module) && trim((string)($module['name'] ?? '')) !== '') {
                $sourceModules[] = (string)$module['name'];
            }
        }

        foreach ($this->modules->orderedModules($sourceModules) as $moduleName) {
            $currentInstalled = $pluginManager->isInstalled($moduleName);
            if (!$currentInstalled && !$installMissing) {
                throw new \RuntimeException('Missing module on target: ' . $moduleName);
            }
            if (!$currentInstalled && $installMissing) {
                $this->modules->run($moduleName, 'install');
            } else {
                $this->modules->run($moduleName, 'repair');
            }
        }
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $tables
     */
    private function restoreData(array $tables, string $strategy): void
    {
        if ($tables === []) {
            return;
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            DB::query('SET FOREIGN_KEY_CHECKS=0');
            if ($strategy === 'overwrite') {
                foreach (array_reverse(array_keys($tables)) as $table) {
                    DB::query('DELETE FROM `' . str_replace('`', '``', $table) . '`');
                }
            }

            foreach ($tables as $table => $rows) {
                $columns = $this->existingColumns($table);
                if ($columns === []) {
                    throw new \RuntimeException('Cannot import snapshot data into missing table: ' . $table);
                }
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $payload = array_intersect_key($row, array_flip($columns));
                    if ($payload === []) {
                        continue;
                    }
                    $sql = $this->insertSql($table, array_keys($payload), $strategy === 'merge');
                    DB::query($sql, array_values($payload));
                }
            }
            DB::query('SET FOREIGN_KEY_CHECKS=1');
            $db->commit();
        } catch (\Throwable $e) {
            try {
                DB::query('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable $ignored) {
            }
            $db->rollback();
            throw $e;
        }
    }

    /**
     * @return array<int,string>
     */
    private function existingColumns(string $table): array
    {
        try {
            $rows = DB::fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        } catch (\Throwable $e) {
            return [];
        }
        return array_values(array_map(static fn(array $row): string => (string)($row['Field'] ?? ''), $rows));
    }

    /**
     * @param array<int,string> $columns
     */
    private function insertSql(string $table, array $columns, bool $ignore): string
    {
        $quoted = array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        return 'INSERT ' . ($ignore ? 'IGNORE ' : '') . 'INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $quoted) . ') VALUES (' . $placeholders . ')';
    }

    private function pluginManager(): PluginManager
    {
        static $manager = null;
        if ($manager instanceof PluginManager) {
            return $manager;
        }

        $manager = new PluginManager(
            APP_ROOT . '/plugins',
            new Container(),
            new Router(),
            new View(APP_ROOT . '/public/views'),
            new EventBus()
        );

        return $manager;
    }
}
