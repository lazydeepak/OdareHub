<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class DeploymentReadinessService
{
    private CoreSetupService $core;
    private SuiteSetupService $suites;
    private ModuleLifecycleService $modules;
    private InstallVerificationService $verification;
    private VersionCatalogService $versions;

    public function __construct(
        ?CoreSetupService $core = null,
        ?SuiteSetupService $suites = null,
        ?ModuleLifecycleService $modules = null,
        ?InstallVerificationService $verification = null,
        ?VersionCatalogService $versions = null
    ) {
        $this->core = $core ?? new CoreSetupService();
        $this->suites = $suites ?? new SuiteSetupService();
        $this->modules = $modules ?? new ModuleLifecycleService();
        $this->verification = $verification ?? new InstallVerificationService($this->modules);
        $this->versions = $versions ?? new VersionCatalogService();
    }

    /**
     * @return array<string,mixed>
     */
    public function evaluate(string $scopeKey, array $selectedSuites = [], array $selectedModules = []): array
    {
        $scopeKey = $this->normalizeScope($scopeKey);
        $selectedSuites = array_values(array_unique(array_filter(array_map('strval', $selectedSuites))));
        $selectedModules = array_values(array_unique(array_filter(array_map('strval', $selectedModules))));

        $coreStatus = $this->core->statusSummary();
        $verification = $this->verification->verifyAll();
        $suiteCards = $this->suites->statusCards();
        $modulePanels = $this->modules->panelRows();
        $moduleIndex = [];
        foreach ($modulePanels as $panel) {
            $moduleIndex[(string)($panel['name'] ?? '')] = $panel;
        }

        $included = $this->resolveIncludedComponents($scopeKey, $selectedSuites, $selectedModules);
        $pendingMigrations = $this->pendingMigrations($included['suites']);
        $envIssues = $this->environmentIssues((array)($coreStatus['preflight'] ?? []));
        $missingDependencies = $this->selectedDependencyIssues($included['modules'], $moduleIndex);
        $failedHooks = $this->filterFailedHooks((array)($verification['failed_hooks'] ?? []), $included);
        $schemaGaps = $this->filterSchemaGaps((array)($verification['schema_gaps'] ?? []), $included);
        $missingTables = $this->filterMissingTables((array)($verification['missing_tables'] ?? []), $included);

        $blocking = [];
        $warnings = [];

        if (in_array((string)($coreStatus['status'] ?? ''), ['warning'], true)) {
            $blocking[] = 'Core status is not deployment-ready.';
        }
        foreach ($envIssues as $issue) {
            $blocking[] = $issue;
        }
        foreach ($missingDependencies as $issue) {
            $blocking[] = $issue;
        }
        foreach ($failedHooks as $issue) {
            $blocking[] = 'Failed hook: ' . (string)($issue['hook_key'] ?? '');
        }
        foreach ($schemaGaps as $issue) {
            $blocking[] = 'Schema gap: ' . (string)($issue['owner'] ?? $issue['scope'] ?? 'unknown');
        }
        foreach ($pendingMigrations as $issue) {
            $blocking[] = $issue;
        }

        foreach ($missingTables as $issue) {
            $warnings[] = 'Missing table: ' . (string)($issue['table'] ?? '');
        }
        foreach ($this->suiteWarnings($included['suites'], $suiteCards) as $issue) {
            $warnings[] = $issue;
        }
        foreach ($this->moduleWarnings($included['modules'], $moduleIndex) as $issue) {
            $warnings[] = $issue;
        }

        return [
            'scope_key' => $scopeKey,
            'scope_label' => $this->scopeOptions()[$scopeKey] ?? $scopeKey,
            'ready' => $blocking === [],
            'status' => $blocking === [] ? 'ready' : 'not_ready',
            'core_status' => $coreStatus,
            'included_components' => $included,
            'version_context' => [
                'core' => $this->versions->coreInfo(),
                'suites' => array_values(array_map(fn(string $suiteKey): array => $this->versions->suiteInfo($suiteKey), $included['suites'])),
                'modules' => array_values(array_map(fn(string $moduleName): array => $this->versions->moduleInfo($moduleName), $included['modules'])),
            ],
            'blocking_issues' => array_values(array_unique($blocking)),
            'non_blocking_warnings' => array_values(array_unique($warnings)),
            'failed_hooks' => $failedHooks,
            'schema_gaps' => $schemaGaps,
            'missing_tables' => $missingTables,
            'pending_migrations' => $pendingMigrations,
            'config_env_issues' => $envIssues,
            'warning_text' => implode(' ', array_merge($blocking, $warnings)),
            'summary' => [
                'blocking_count' => count(array_unique($blocking)),
                'warning_count' => count(array_unique($warnings)),
                'suite_count' => count($included['suites']),
                'module_count' => count($included['modules']),
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    public function scopeOptions(): array
    {
        return [
            'core_only' => 'Core only',
            'core_selected_suites' => 'Core + selected suites',
            'suite_only' => 'Suite-only release',
            'module_only' => 'Module-only release',
        ];
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function componentChoices(): array
    {
        $suiteChoices = [];
        foreach ($this->suites->statusCards() as $suiteKey => $card) {
            if ((string)($card['registry_status'] ?? 'uploaded') === 'uploaded') {
                continue;
            }
            $suiteChoices[] = $suiteKey;
        }

        $moduleChoices = [];
        foreach ($this->modules->panelRows() as $panel) {
            if (empty($panel['installed'])) {
                continue;
            }
            $moduleChoices[] = (string)($panel['name'] ?? '');
        }

        return [
            'suites' => $suiteChoices,
            'modules' => $moduleChoices,
        ];
    }

    private function normalizeScope(string $scopeKey): string
    {
        $scopeKey = trim($scopeKey);
        if (!isset($this->scopeOptions()[$scopeKey])) {
            throw new \RuntimeException('Unsupported release scope.');
        }
        return $scopeKey;
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function resolveIncludedComponents(string $scopeKey, array $selectedSuites, array $selectedModules): array
    {
        return match ($scopeKey) {
            'core_only' => ['suites' => [], 'modules' => []],
            'core_selected_suites', 'suite_only' => [
                'suites' => $selectedSuites,
                'modules' => $this->modulesForSuites($selectedSuites),
            ],
            'module_only' => ['suites' => [], 'modules' => $selectedModules],
            default => ['suites' => [], 'modules' => []],
        };
    }

    /**
     * @param array<string,mixed> $preflight
     * @return array<int,string>
     */
    private function environmentIssues(array $preflight): array
    {
        $issues = [];
        foreach ((array)($preflight['environment'] ?? []) as $row) {
            if (empty($row['ok'])) {
                $issues[] = 'Environment check failed: ' . (string)($row['label'] ?? 'unknown');
            }
        }
        foreach ((array)($preflight['writable_paths'] ?? []) as $row) {
            if (empty($row['ok'])) {
                $issues[] = 'Writable path blocked: ' . (string)($row['path'] ?? '');
            }
        }
        if (empty($preflight['database']['ok'])) {
            $issues[] = 'Database status is not ready.';
        }
        return $issues;
    }

    /**
     * @param array<int,string> $suiteKeys
     * @return array<int,string>
     */
    private function pendingMigrations(array $suiteKeys): array
    {
        $issues = [];
        foreach ($suiteKeys as $suiteKey) {
            $row = DB::fetchOne('SELECT install_path, manifest_json, version FROM core_apps WHERE app_key=? LIMIT 1', [$suiteKey]);
            if (!is_array($row)) {
                continue;
            }
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $migrationsPath = trim((string)($manifest['migrations_path'] ?? ''));
            if ($migrationsPath === '') {
                continue;
            }
            $dir = rtrim((string)($row['install_path'] ?? ''), '/') . '/' . ltrim($migrationsPath, '/');
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.sql') ?: [] as $file) {
                $migrationName = basename($file);
                $exists = DB::fetchOne('SELECT id FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1', [$suiteKey, $migrationName]);
                if (!$exists) {
                    $issues[] = 'Pending migration for ' . $suiteKey . ': ' . $migrationName;
                }
            }
        }
        return $issues;
    }

    /**
     * @param array<int,string> $moduleNames
     * @param array<string,array<string,mixed>> $moduleIndex
     * @return array<int,string>
     */
    private function selectedDependencyIssues(array $moduleNames, array $moduleIndex): array
    {
        $issues = [];
        foreach ($moduleNames as $moduleName) {
            $panel = $moduleIndex[$moduleName] ?? null;
            if (!is_array($panel)) {
                $issues[] = 'Selected module is not installed: ' . $moduleName;
                continue;
            }
            foreach ((array)($panel['dependency_errors'] ?? []) as $error) {
                $issues[] = $moduleName . ': ' . (string)$error;
            }
        }
        return $issues;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,array<int,string>> $included
     * @return array<int,array<string,mixed>>
     */
    private function filterFailedHooks(array $rows, array $included): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($included): bool {
            $appKey = strtolower((string)($row['app_key'] ?? ''));
            return in_array($appKey, (array)$included['suites'], true);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,array<int,string>> $included
     * @return array<int,array<string,mixed>>
     */
    private function filterSchemaGaps(array $rows, array $included): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($included): bool {
            $owner = strtolower((string)($row['owner'] ?? ''));
            $dependency = strtolower((string)($row['dependency'] ?? ''));
            return in_array($owner, (array)$included['suites'], true)
                || in_array($owner, (array)$included['modules'], true)
                || in_array($dependency, (array)$included['modules'], true);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,array<int,string>> $included
     * @return array<int,array<string,mixed>>
     */
    private function filterMissingTables(array $rows, array $included): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($included): bool {
            $owner = strtolower((string)($row['owner'] ?? ''));
            $suite = strtolower((string)($row['suite'] ?? ''));
            return in_array($owner, (array)$included['modules'], true)
                || in_array($suite, (array)$included['suites'], true)
                || in_array($owner, (array)$included['suites'], true);
        }));
    }

    /**
     * @param array<int,string> $suiteKeys
     * @param array<string,array<string,mixed>> $suiteCards
     * @return array<int,string>
     */
    private function suiteWarnings(array $suiteKeys, array $suiteCards): array
    {
        $warnings = [];
        foreach ($suiteKeys as $suiteKey) {
            $card = $suiteCards[$suiteKey] ?? null;
            if (!is_array($card)) {
                continue;
            }
            if ((string)($card['status'] ?? '') === 'warning') {
                $warnings[] = 'Suite has non-blocking warnings: ' . $suiteKey;
            }
        }
        return $warnings;
    }

    /**
     * @param array<int,string> $moduleNames
     * @param array<string,array<string,mixed>> $moduleIndex
     * @return array<int,string>
     */
    private function moduleWarnings(array $moduleNames, array $moduleIndex): array
    {
        $warnings = [];
        foreach ($moduleNames as $moduleName) {
            $panel = $moduleIndex[$moduleName] ?? null;
            if (!is_array($panel)) {
                continue;
            }
            if ((string)($panel['panel_status'] ?? '') === 'warning') {
                $warnings[] = 'Module has non-blocking warnings: ' . $moduleName;
            }
        }
        return $warnings;
    }

    /**
     * @param array<int,string> $suiteKeys
     * @return array<int,string>
     */
    private function modulesForSuites(array $suiteKeys): array
    {
        $names = [];
        foreach ($suiteKeys as $suiteKey) {
            $suite = $this->suites->statusCards()[$suiteKey] ?? null;
            if (!is_array($suite)) {
                continue;
            }
            foreach ((array)($suite['modules'] ?? []) as $module) {
                $name = trim((string)($module['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }
        return array_values(array_unique($names));
    }
}
