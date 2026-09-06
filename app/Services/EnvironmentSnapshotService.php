<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use ZipArchive;

final class EnvironmentSnapshotService
{
    private ModuleLifecycleService $modules;

    public function __construct(?ModuleLifecycleService $modules = null)
    {
        $this->modules = $modules ?? new ModuleLifecycleService();
    }

    /**
     * @return array<string,string>
     */
    public function scopeOptions(): array
    {
        return [
            'metadata_only' => 'Metadata only',
            'config_only' => 'Config only',
            'core_only' => 'Core only',
            'core_suites' => 'Core + suites',
            'core_suites_data' => 'Core + suites + data',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function createSnapshot(string $scopeKey): array
    {
        $scopeKey = $this->normalizeScope($scopeKey);
        $payload = $this->buildSnapshotPayload($scopeKey);
        $dir = APP_ROOT . '/storage/environment_snapshots';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('Unable to create the environment snapshots directory.');
        }

        $timestamp = date('Ymd_His');
        $baseName = 'environment_' . $scopeKey . '_' . $timestamp;

        if ($scopeKey === 'metadata_only') {
            $filePath = $dir . '/' . $baseName . '.json';
            file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $filePath = $dir . '/' . $baseName . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create environment snapshot archive.');
            }
            $zip->addFromString('metadata.json', json_encode((array)$payload['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if (isset($payload['config'])) {
                $zip->addFromString('config.json', json_encode((array)$payload['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            if (isset($payload['data'])) {
                $zip->addFromString('data.json', json_encode((array)$payload['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            $zip->addFromString('environment_manifest.json', json_encode([
                'created_at' => date('c'),
                'scope_key' => $scopeKey,
                'source_environment' => (string)($payload['metadata']['source_environment']['name'] ?? ''),
                'app_version' => APP_VERSION,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->close();
        }

        return [
            'scope_key' => $scopeKey,
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'source_environment' => (string)($payload['metadata']['source_environment']['name'] ?? 'unknown'),
            'summary' => $payload['metadata']['summary'] ?? [],
            'warning_text' => (string)($payload['metadata']['warning_text'] ?? ''),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function currentEnvironmentInfo(): array
    {
        $dbRow = DB::fetchOne('SELECT DATABASE() AS db_name');
        return [
            'name' => app_env() . '@' . php_uname('n'),
            'environment' => app_env(),
            'host' => php_uname('n'),
            'app_version' => APP_VERSION,
            'php_version' => PHP_VERSION,
            'database_name' => (string)($dbRow['db_name'] ?? ''),
            'generated_at' => date('c'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildSnapshotPayload(string $scopeKey): array
    {
        $scopeKey = $this->normalizeScope($scopeKey);
        $includeConfig = in_array($scopeKey, ['config_only', 'core_only', 'core_suites', 'core_suites_data'], true);
        $includeSuites = in_array($scopeKey, ['core_suites', 'core_suites_data'], true);
        $includeData = $scopeKey === 'core_suites_data';

        $environment = $this->currentEnvironmentInfo();
        $installedSuites = $includeSuites ? $this->installedSuites() : [];
        $installedModules = $includeSuites ? $this->installedModules() : [];
        $config = $includeConfig ? $this->configPayload() : [];
        $data = $includeData ? $this->dataPayload($installedModules) : [];

        return [
            'metadata' => [
                'snapshot_kind' => 'environment_portability',
                'scope_key' => $scopeKey,
                'scope_label' => $this->scopeOptions()[$scopeKey] ?? $scopeKey,
                'created_at' => date('c'),
                'source_environment' => $environment,
                'core' => [
                    'app_version' => APP_VERSION,
                    'installed_core_modules' => array_values(array_filter($this->installedModules(), static fn(array $module): bool => (string)($module['suite'] ?? '') === 'core')),
                    'settings_count' => count((array)($config['settings'] ?? [])),
                ],
                'installed_suites' => $installedSuites,
                'installed_modules' => $installedModules,
                'summary' => [
                    'suite_count' => count($installedSuites),
                    'module_count' => count($installedModules),
                    'config_keys' => count((array)($config['settings'] ?? [])),
                    'data_tables' => count((array)($data['tables'] ?? [])),
                ],
                'warning_text' => $includeData ? '' : 'This snapshot does not include business data rows.',
            ],
            'config' => $includeConfig ? $config : null,
            'data' => $includeData ? $data : null,
        ];
    }

    private function normalizeScope(string $scopeKey): string
    {
        $scopeKey = trim($scopeKey);
        if (!isset($this->scopeOptions()[$scopeKey])) {
            throw new \RuntimeException('Unsupported environment snapshot scope.');
        }
        return $scopeKey;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function installedSuites(): array
    {
        return DB::fetchAll(
            "SELECT app_key, app_name, version, status
             FROM core_apps
             WHERE status IN ('installed','enabled','disabled','upgrade_pending','broken')
             ORDER BY app_key ASC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function installedModules(): array
    {
        $rows = [];
        foreach ($this->modules->modulesBySuite() as $suiteKey => $modules) {
            foreach ($modules as $module) {
                if (empty($module['installed'])) {
                    continue;
                }
                $rows[] = [
                    'name' => (string)($module['name'] ?? ''),
                    'display_name' => (string)($module['display_name'] ?? ''),
                    'suite' => (string)($module['suite'] ?? $suiteKey),
                    'version' => (string)($module['version'] ?? ''),
                    'status' => (string)($module['status'] ?? 'missing'),
                    'required_tables' => array_values(array_map('strval', (array)($module['required_tables'] ?? []))),
                ];
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private function configPayload(): array
    {
        $settings = DB::fetchAll('SELECT setting_key, setting_value FROM core_settings ORDER BY setting_key ASC');
        $map = [];
        foreach ($settings as $row) {
            $map[(string)($row['setting_key'] ?? '')] = (string)($row['setting_value'] ?? '');
        }

        return [
            'settings' => $map,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $installedModules
     * @return array<string,mixed>
     */
    private function dataPayload(array $installedModules): array
    {
        $tables = [];
        $seen = [];
        foreach ($installedModules as $module) {
            foreach ((array)($module['required_tables'] ?? []) as $table) {
                $table = trim((string)$table);
                if ($table === '' || isset($seen[$table])) {
                    continue;
                }
                $seen[$table] = true;
                try {
                    $tables[$table] = DB::fetchAll('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
                } catch (\Throwable $e) {
                    $tables[$table] = [];
                }
            }
        }

        return ['tables' => $tables];
    }
}
