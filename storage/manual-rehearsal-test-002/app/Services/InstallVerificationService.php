<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class InstallVerificationService
{
    private ModuleLifecycleService $modules;

    public function __construct(?ModuleLifecycleService $modules = null)
    {
        $this->modules = $modules ?? new ModuleLifecycleService();
    }

    /**
     * @return array<string,mixed>
     */
    public function verifyAll(): array
    {
        $apps = DB::fetchAll('SELECT app_key, app_name, status, install_path, manifest_json, error_text FROM core_apps ORDER BY app_key ASC');
        $installedModules = DB::fetchAll('SELECT name, version, status, installed_at FROM installed_plugins ORDER BY name ASC');

        $report = [
            'installed_apps' => [],
            'installed_modules' => $installedModules,
            'missing_tables' => [],
            'failed_hooks' => [],
            'schema_gaps' => [],
            'route_runtime_health' => [],
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($apps as $app) {
            $appKey = strtolower(trim((string)($app['app_key'] ?? '')));
            $manifest = json_decode((string)($app['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $installPath = trim((string)($app['install_path'] ?? ''));
            $status = trim((string)($app['status'] ?? 'unknown'));

            $hookIssues = $this->hookFailuresForApp($appKey, $installPath);
            $tableIssues = $this->missingTablesForApp($manifest);
            $uiDiagnostics = (new UiSurfaceRegistryDiagnosticsService())->diagnosticsForApp($appKey);
            $failedMigrations = DB::fetchAll(
                "SELECT migration_name, error_text FROM core_app_migrations WHERE app_key=? AND status='failed' ORDER BY id DESC",
                [$appKey]
            );

            $routeHealth = [
                'app_key' => $appKey,
                'status' => $status,
                'entry_file_exists' => $this->fileExistsWithinInstallPath($installPath, (string)($manifest['entry'] ?? '')),
                'routes_declared' => count((array)($manifest['routes'] ?? [])),
                'runtime_hooks' => (int)(DB::fetchOne('SELECT COUNT(*) AS c FROM core_app_hooks WHERE app_key=?', [$appKey])['c'] ?? 0),
                'ui_warnings' => (int)($uiDiagnostics['summary']['duplicate_declarations'] ?? 0) + (int)($uiDiagnostics['summary']['missing_locale_keys'] ?? 0),
            ];

            if ($status === AppRegistryService::STATUS_ENABLED && !$routeHealth['entry_file_exists']) {
                $report['errors'][] = 'Enabled suite ' . $appKey . ' is missing its entry file.';
            }
            if ($hookIssues !== []) {
                foreach ($hookIssues as $issue) {
                    $report['failed_hooks'][] = $issue;
                }
            }
            if ($tableIssues !== []) {
                foreach ($tableIssues as $issue) {
                    $report['missing_tables'][] = $issue;
                }
            }
            if ($failedMigrations !== []) {
                foreach ($failedMigrations as $failure) {
                    $report['schema_gaps'][] = [
                        'scope' => 'suite',
                        'owner' => $appKey,
                        'migration' => (string)($failure['migration_name'] ?? ''),
                        'error' => (string)($failure['error_text'] ?? ''),
                    ];
                }
            }

            $report['installed_apps'][] = [
                'app_key' => $appKey,
                'app_name' => (string)($app['app_name'] ?? $appKey),
                'status' => $status,
                'hook_failures' => count($hookIssues),
                'missing_tables' => count($tableIssues),
                'ui_diagnostics' => $uiDiagnostics['summary'] ?? [],
                'error_text' => (string)($app['error_text'] ?? ''),
            ];
            $report['route_runtime_health'][] = $routeHealth;
        }

        foreach ($this->modules->modulesBySuite() as $suiteKey => $modules) {
            foreach ($modules as $module) {
                if (empty($module['installed'])) {
                    continue;
                }

                foreach ((array)($module['required_tables'] ?? []) as $table) {
                    if (!$this->tableExists((string)$table)) {
                        $report['missing_tables'][] = [
                            'scope' => 'module',
                            'owner' => (string)($module['name'] ?? ''),
                            'suite' => $suiteKey,
                            'table' => (string)$table,
                        ];
                    }
                }

                foreach ((array)($module['requires'] ?? []) as $dependency) {
                    $depName = preg_replace('/[<>=].*$/', '', trim((string)$dependency)) ?: '';
                    if ($depName !== '' && !$this->moduleInstalled($depName)) {
                        $report['schema_gaps'][] = [
                            'scope' => 'module_dependency',
                            'owner' => (string)($module['name'] ?? ''),
                            'dependency' => $depName,
                        ];
                    }
                }
            }
        }

        if ($report['missing_tables'] !== []) {
            $report['warnings'][] = 'One or more required tables are missing.';
        }
        if ($report['failed_hooks'] !== []) {
            $report['warnings'][] = 'One or more runtime hooks could not be verified.';
        }
        if ($report['schema_gaps'] !== []) {
            $report['warnings'][] = 'Schema gaps or dependency gaps were found.';
        }

        return $report;
    }

    /**
     * @return array<string,mixed>
     */
    public function verifySuite(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $report = $this->verifyAll();
        $report['installed_apps'] = array_values(array_filter(
            (array)$report['installed_apps'],
            static fn(array $row): bool => strtolower((string)($row['app_key'] ?? '')) === $suiteKey
        ));
        $report['missing_tables'] = array_values(array_filter(
            (array)$report['missing_tables'],
            static fn(array $row): bool => strtolower((string)($row['suite'] ?? ($row['owner'] ?? ''))) === $suiteKey
                || strtolower((string)($row['owner'] ?? '')) === $suiteKey
        ));
        $report['failed_hooks'] = array_values(array_filter(
            (array)$report['failed_hooks'],
            static fn(array $row): bool => strtolower((string)($row['app_key'] ?? '')) === $suiteKey
        ));
        $report['route_runtime_health'] = array_values(array_filter(
            (array)$report['route_runtime_health'],
            static fn(array $row): bool => strtolower((string)($row['app_key'] ?? '')) === $suiteKey
        ));
        $report['schema_gaps'] = array_values(array_filter(
            (array)$report['schema_gaps'],
            static fn(array $row): bool => strtolower((string)($row['suite'] ?? ($row['owner'] ?? ''))) === $suiteKey
                || strtolower((string)($row['owner'] ?? '')) === $suiteKey
        ));
        $report['warnings'] = [];
        $report['errors'] = [];

        if ($report['missing_tables'] !== []) {
            $report['warnings'][] = 'One or more required tables are missing.';
        }
        if ($report['failed_hooks'] !== []) {
            $report['warnings'][] = 'One or more runtime hooks could not be verified.';
        }
        if ($report['schema_gaps'] !== []) {
            $report['warnings'][] = 'Schema gaps or dependency gaps were found.';
        }
        foreach ((array)$report['route_runtime_health'] as $row) {
            if ((string)($row['status'] ?? '') === AppRegistryService::STATUS_ENABLED && empty($row['entry_file_exists'])) {
                $report['errors'][] = 'Enabled suite ' . $suiteKey . ' is missing its entry file.';
            }
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,array<string,mixed>>
     */
    private function missingTablesForApp(array $manifest): array
    {
        $issues = [];
        foreach ((array)($manifest['notifications'] ?? []) as $notification) {
            if (!is_array($notification)) {
                continue;
            }
            $targetTable = trim((string)($notification['target_table'] ?? ''));
            if ($targetTable === '' || !$this->tableExists($targetTable)) {
                $issues[] = [
                    'scope' => 'suite_notification',
                    'owner' => trim((string)($manifest['id'] ?? '')),
                    'suite' => trim((string)($manifest['id'] ?? '')),
                    'table' => $targetTable,
                ];
            }
        }

        return $issues;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function hookFailuresForApp(string $appKey, string $installPath): array
    {
        $issues = [];
        $hooks = DB::fetchAll('SELECT hook_type, hook_key, payload_json, is_enabled FROM core_app_hooks WHERE app_key=? ORDER BY id ASC', [$appKey]);
        foreach ($hooks as $hook) {
            $payload = json_decode((string)($hook['payload_json'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $file = trim((string)($payload['provider_file'] ?? ($payload['handler_file'] ?? '')));
            if ($file === '') {
                continue;
            }

            $absolute = rtrim($installPath, '/') . '/' . ltrim($file, '/');
            if (!is_file($absolute)) {
                $issues[] = [
                    'app_key' => $appKey,
                    'hook_type' => (string)($hook['hook_type'] ?? ''),
                    'hook_key' => (string)($hook['hook_key'] ?? ''),
                    'file' => $absolute,
                    'enabled' => (int)($hook['is_enabled'] ?? 0) === 1,
                ];
            }
        }

        return $issues;
    }

    private function fileExistsWithinInstallPath(string $installPath, string $relativePath): bool
    {
        if ($installPath === '' || $relativePath === '') {
            return false;
        }

        return is_file(rtrim($installPath, '/') . '/' . ltrim($relativePath, '/'));
    }

    private function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $safe = DB::conn()->real_escape_string($table);
        return DB::fetchOne("SHOW TABLES LIKE '{$safe}'") !== null;
    }

    private function moduleInstalled(string $moduleName): bool
    {
        return DB::fetchOne('SELECT name FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]) !== null;
    }
}
