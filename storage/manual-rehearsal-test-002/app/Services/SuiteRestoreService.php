<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PackageManager;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use ZipArchive;

final class SuiteRestoreService
{
    private RestoreHistoryService $history;
    private AppRegistryService $apps;
    private PluginManager $plugins;
    private ModuleLifecycleService $modules;

    public function __construct(
        ?RestoreHistoryService $history = null,
        ?AppRegistryService $apps = null,
        ?PluginManager $plugins = null,
        ?ModuleLifecycleService $modules = null
    ) {
        $this->history = $history ?? new RestoreHistoryService();
        $this->apps = $apps ?? new AppRegistryService();
        $this->plugins = $plugins ?? self::pluginManager();
        $this->modules = $modules ?? new ModuleLifecycleService();
    }

    /**
     * @return array<string,mixed>
     */
    public function previewRestore(string $suiteKey, string $backupPath, string $sourceName): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $parsed = $this->parseBackup($backupPath, $sourceName);
        $metadata = (array)($parsed['metadata'] ?? []);
        $targetType = (string)($metadata['target_type'] ?? 'suite');
        $targetKey = (string)($metadata['target_key'] ?? $suiteKey);
        $backupSuite = strtolower(trim((string)($metadata['suite_key'] ?? $suiteKey)));
        $restoreType = (string)($metadata['export_type'] ?? 'metadata_only');
        $modules = array_values(array_map('strval', array_column((array)($metadata['modules'] ?? []), 'name')));
        $liveVersion = null;
        $liveStatus = null;

        $conflicts = [];
        $warnings = [];
        $schema = [
            'missing_tables' => [],
            'existing_tables' => [],
        ];

        if ($backupSuite !== $suiteKey) {
            $conflicts[] = 'Backup belongs to ' . $backupSuite . ', not ' . $suiteKey . '.';
        }

        $hasPackage = !empty($parsed['package_entries']);
        $dataTablesPayload = (array)(($parsed['data']['tables'] ?? []) ?: []);
        $hasData = $dataTablesPayload !== [];
        if ($restoreType === 'metadata_only') {
            $warnings[] = 'Metadata-only backups can be reviewed, but they do not restore package files or business data.';
        }

        $packageInfo = [];
        foreach ((array)($parsed['package_entries'] ?? []) as $entry) {
            $info = $this->readEmbeddedPackageInfo($backupPath, (string)$entry);
            if ($info !== []) {
                $packageInfo[] = $info;
            }
        }

        $overwriteRisk = [];
        if ($targetType === 'suite') {
            $app = $this->apps->find($targetKey);
            if (is_array($app)) {
                $liveVersion = (string)($app['version'] ?? '');
                $liveStatus = (string)($app['status'] ?? 'unknown');
                $overwriteRisk[] = 'Suite is already registered with status ' . (string)($app['status'] ?? 'unknown') . '.';
            }
        } else {
            if ($this->plugins->isInstalled($targetKey)) {
                $liveStatus = (string)($this->plugins->status($targetKey) ?? 'unknown');
                $overwriteRisk[] = 'Module is already installed with status ' . $liveStatus . '.';
            }
            $manifest = $this->plugins->manifests()[$targetKey] ?? null;
            if (is_array($manifest)) {
                $liveVersion = (string)($manifest['version'] ?? '');
            }
        }

        $dataTables = array_keys($dataTablesPayload);
        $duplicateRiskRows = 0;
        foreach ($dataTables as $table) {
            try {
                $count = (int)(DB::fetchOne('SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $table) . '`')['c'] ?? 0);
                $schema['existing_tables'][$table] = $count;
                if ($count > 0) {
                    $duplicateRiskRows += $count;
                    $overwriteRisk[] = $table . ' already contains ' . $count . ' row(s).';
                }
            } catch (\Throwable $e) {
                $schema['missing_tables'][] = $table;
            }
        }

        if ($schema['missing_tables'] !== []) {
            $conflicts[] = 'Backup references tables that are not present in the live schema.';
        }

        $missingDependencies = [];
        foreach ($packageInfo as $info) {
            foreach ((array)($info['requires'] ?? []) as $dependency) {
                $dependency = preg_replace('/[<>=].*$/', '', trim((string)$dependency)) ?: '';
                if ($dependency === '') {
                    continue;
                }
                if ($targetType === 'module') {
                    if (!$this->plugins->isInstalled($dependency)) {
                        $missingDependencies[] = $dependency;
                    }
                }
            }
        }
        $missingDependencies = array_values(array_unique($missingDependencies));
        if ($missingDependencies !== []) {
            $conflicts[] = 'Missing dependent modules: ' . implode(', ', $missingDependencies);
        }

        $versionConflicts = [];
        foreach ($packageInfo as $info) {
            $packageVersion = trim((string)($info['version'] ?? ''));
            if ($packageVersion !== '' && $liveVersion !== null && $liveVersion !== '' && $packageVersion !== $liveVersion) {
                $versionConflicts[] = [
                    'package_name' => (string)($info['name'] ?? $targetKey),
                    'backup_version' => $packageVersion,
                    'live_version' => $liveVersion,
                ];
                $conflicts[] = 'Version mismatch for ' . (string)($info['name'] ?? $targetKey) . ': backup ' . $packageVersion . ', live ' . $liveVersion . '.';
            }
        }

        $availableScopes = [];
        if ($hasPackage && $hasData) {
            $availableScopes = ['full', 'package_only', 'data_only'];
        } elseif ($hasPackage) {
            $availableScopes = ['package_only'];
        } elseif ($hasData) {
            $availableScopes = ['data_only'];
        }

        $recommendedActions = ['cancel'];
        if ($overwriteRisk !== []) {
            $recommendedActions[] = 'overwrite';
            $recommendedActions[] = 'merge';
        } else {
            $recommendedActions[] = 'restore';
        }
        if ($missingDependencies !== []) {
            $recommendedActions[] = 'install_missing_dependency_first';
        }
        if ($hasPackage && $hasData) {
            $recommendedActions[] = 'restore_package_only';
            $recommendedActions[] = 'restore_data_only';
        }

        return [
            'suite_key' => $suiteKey,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'restore_type' => $restoreType,
            'source_backup' => $sourceName,
            'metadata' => $metadata,
            'backup_type' => $restoreType,
            'package_info' => $packageInfo,
            'live_state' => [
                'status' => $liveStatus,
                'version' => $liveVersion,
            ],
            'has_package' => $hasPackage,
            'has_data' => $hasData,
            'available_restore_scopes' => $availableScopes,
            'schema' => $schema,
            'schema_compatibility' => $schema['missing_tables'] === [] ? 'compatible' : 'schema_gap',
            'missing_dependencies' => $missingDependencies,
            'overwrite_risk' => $overwriteRisk,
            'duplicate_risk_rows' => $duplicateRiskRows,
            'version_conflicts' => $versionConflicts,
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'warning_text' => implode(' ', array_merge($warnings, $overwriteRisk)),
            'recommended_actions' => $recommendedActions,
            'data_summary' => [
                'tables' => $dataTables,
                'table_count' => count($dataTables),
            ],
            'conflict_summary' => [
                'conflict_count' => count($conflicts),
                'warning_count' => count($warnings),
                'overwrite_items' => count($overwriteRisk),
                'missing_dependency_count' => count($missingDependencies),
                'version_conflict_count' => count($versionConflicts),
            ],
            'backup_path' => $backupPath,
        ];
    }

    /**
     * @param array<string,mixed> $previewPayload
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function restorePreview(array $previewPayload, array $options): array
    {
        $preview = (array)($previewPayload['preview'] ?? []);
        $backupPath = (string)($preview['backup_path'] ?? '');
        if ($backupPath === '' || !is_file($backupPath)) {
            throw new \RuntimeException('Restore preview has expired. Upload the backup again.');
        }

        $parsed = $this->parseBackup($backupPath, (string)($preview['source_backup'] ?? basename($backupPath)));
        $mode = trim((string)($options['restore_scope'] ?? 'full'));
        $strategy = trim((string)($options['conflict_strategy'] ?? 'cancel'));
        $installDeps = !empty($options['install_missing_dependencies']);
        $targetType = (string)($preview['target_type'] ?? 'suite');
        $targetKey = (string)($preview['target_key'] ?? '');
        $suiteKey = (string)($preview['suite_key'] ?? '');

        $doPackage = in_array($mode, ['full', 'package_only'], true) && !empty($preview['has_package']);
        $doData = in_array($mode, ['full', 'data_only'], true) && !empty($preview['has_data']);
        if (!$doPackage && !$doData) {
            throw new \RuntimeException('This restore scope is not available for the selected backup.');
        }

        if ($strategy === 'cancel') {
            throw new \RuntimeException('Restore was cancelled before any changes were applied.');
        }

        if (!empty($preview['missing_dependencies']) && !$installDeps) {
            throw new \RuntimeException('Missing dependent modules must be installed first, or choose the install dependency option.');
        }

        if ($doPackage) {
            foreach ((array)($preview['missing_dependencies'] ?? []) as $dependency) {
                $dependency = trim((string)$dependency);
                if ($dependency === '') {
                    continue;
                }
                if (!$this->plugins->isInstalled($dependency) && $installDeps) {
                    $this->modules->run($dependency, 'install');
                }
            }

            foreach ((array)($parsed['package_entries'] ?? []) as $entry) {
                $tempZip = $this->extractZipEntryToTemp($backupPath, (string)$entry);
                try {
                    if ($targetType === 'suite') {
                        $modeToUse = is_array($this->apps->find($targetKey)) ? 'repair' : 'install';
                        \App\Core\PackageManager::applyPackage($tempZip, $modeToUse);
                    } else {
                        $exists = $this->plugins->isInstalled($targetKey);
                        $modeToUse = $exists ? 'repair' : 'install';
                        \App\Core\PackageManager::applyPackage($tempZip, $modeToUse);
                    }
                } finally {
                    @unlink($tempZip);
                }
            }
        }

        $rollbackAttempted = false;
        if ($doData) {
            $this->restoreData((array)($parsed['data']['tables'] ?? []), $strategy);
        }

        return [
            'suite_key' => $suiteKey,
            'target_type' => $targetType,
            'target_key' => $targetKey,
            'restore_type' => (string)($preview['restore_type'] ?? ''),
            'restore_scope' => $mode,
            'conflict_strategy' => $strategy,
            'rollback_attempted' => $rollbackAttempted,
            'warning_text' => trim((string)($preview['warning_text'] ?? '')),
            'restored_package' => $doPackage,
            'restored_data' => $doData,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function parseBackup(string $backupPath, string $sourceName): array
    {
        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $decoded = json_decode((string)file_get_contents($backupPath), true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Backup JSON is invalid.');
            }
            return [
                'metadata' => (array)($decoded['metadata'] ?? $decoded),
                'data' => (array)($decoded['data'] ?? []),
                'package_entries' => [],
            ];
        }

        if ($ext !== 'zip') {
            throw new \RuntimeException('Unsupported backup type. Use a suite/module export archive or JSON backup.');
        }

        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new \RuntimeException('Could not open backup archive.');
        }

        $metadataRaw = $zip->getFromName('metadata.json');
        if (!is_string($metadataRaw)) {
            $zip->close();
            throw new \RuntimeException('Backup archive is missing metadata.json.');
        }
        $metadata = json_decode($metadataRaw, true);
        if (!is_array($metadata)) {
            $zip->close();
            throw new \RuntimeException('Backup metadata is invalid.');
        }

        $data = [];
        $dataRaw = $zip->getFromName('data.json');
        if (is_string($dataRaw)) {
            $decoded = json_decode($dataRaw, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $packageEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (str_starts_with($name, 'package/') && str_ends_with(strtolower($name), '.zip')) {
                $packageEntries[] = $name;
            }
        }
        $zip->close();

        return [
            'metadata' => $metadata,
            'data' => $data,
            'package_entries' => $packageEntries,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readEmbeddedPackageInfo(string $backupPath, string $entryName): array
    {
        $temp = $this->extractZipEntryToTemp($backupPath, $entryName);
        try {
            $meta = \App\Core\PackageManager::readManifestFromZip($temp);
            return is_array($meta) ? $meta : [];
        } finally {
            @unlink($temp);
        }
    }

    private function extractZipEntryToTemp(string $backupPath, string $entryName): string
    {
        $dir = APP_ROOT . '/storage/restore_tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $temp = $dir . '/' . bin2hex(random_bytes(8)) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new \RuntimeException('Could not open backup archive for package extraction.');
        }
        $raw = $zip->getFromName($entryName);
        $zip->close();
        if (!is_string($raw)) {
            throw new \RuntimeException('Embedded package file is missing from backup archive.');
        }
        file_put_contents($temp, $raw);
        return $temp;
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
                    throw new \RuntimeException('Cannot restore data into missing table: ' . $table);
                }
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $payload = array_intersect_key($row, array_flip($columns));
                    if ($payload === []) {
                        continue;
                    }
                    if ($strategy === 'merge' && array_key_exists('id', $payload)) {
                        // Preserve references but skip duplicate id collisions safely.
                        $sql = $this->insertSql($table, array_keys($payload), true);
                    } else {
                        $sql = $this->insertSql($table, array_keys($payload), false);
                    }
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

    private static function pluginManager(): PluginManager
    {
        return new PluginManager(
            APP_ROOT . '/plugins',
            new Container(),
            new Router(),
            new View(APP_ROOT . '/public/views'),
            new EventBus()
        );
    }
}
