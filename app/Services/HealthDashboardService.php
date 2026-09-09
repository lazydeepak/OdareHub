<?php
declare(strict_types=1);

namespace App\Services;

final class HealthDashboardService
{
    private CoreSetupService $core;
    private SuiteSetupService $suites;
    private ModuleLifecycleService $modules;
    private InstallVerificationService $verification;

    public function __construct(
        ?CoreSetupService $core = null,
        ?SuiteSetupService $suites = null,
        ?ModuleLifecycleService $modules = null,
        ?InstallVerificationService $verification = null
    ) {
        $this->core = $core ?? new CoreSetupService();
        $this->suites = $suites ?? new SuiteSetupService();
        $this->modules = $modules ?? new ModuleLifecycleService();
        $this->verification = $verification ?? new InstallVerificationService($this->modules);
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboard(): array
    {
        $coreStatus = $this->core->statusSummary();
        $suiteStatuses = $this->suites->statusCards();
        $modulePanels = $this->modules->panelRows();
        $verification = $this->verification->verifyAll();

        $suiteCards = [];
        foreach (['sbaio', 'manufacturing'] as $suiteKey) {
            $suiteCards[$suiteKey] = $this->suiteCard(
                $suiteStatuses[$suiteKey] ?? $this->suites->suiteStatus($suiteKey)
            );
        }

        $moduleGroups = [];
        foreach ($modulePanels as $panel) {
            $suiteKey = strtolower(trim((string)($panel['suite'] ?? 'other')));
            $moduleGroups[$suiteKey][] = $this->moduleCard($panel);
        }

        foreach ($moduleGroups as &$rows) {
            usort($rows, static function (array $left, array $right): int {
                return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
            });
        }
        unset($rows);

        return [
            'summary' => [
                'healthy_targets' => $this->countByHealth([$this->coreCard($coreStatus, $verification), ...array_values($suiteCards)], 'healthy'),
                'warning_targets' => $this->countByHealth([$this->coreCard($coreStatus, $verification), ...array_values($suiteCards)], 'warning'),
                'error_targets' => $this->countByHealth([$this->coreCard($coreStatus, $verification), ...array_values($suiteCards)], 'error'),
                'disabled_modules' => count(array_filter($modulePanels, static fn(array $row): bool => empty($row['installed']) || (string)($row['status'] ?? '') !== 'active')),
                'module_warnings' => count(array_filter($modulePanels, fn(array $row): bool => $this->moduleCard($row)['health_status'] === 'warning')),
                'module_errors' => count(array_filter($modulePanels, fn(array $row): bool => $this->moduleCard($row)['health_status'] === 'error')),
            ],
            'core' => $this->coreCard($coreStatus, $verification),
            'suites' => $suiteCards,
            'modules' => [
                'summary' => [
                    'total' => count($modulePanels),
                    'active' => count(array_filter($modulePanels, static fn(array $row): bool => !empty($row['installed']) && (string)($row['status'] ?? '') === 'active')),
                    'disabled' => count(array_filter($modulePanels, static fn(array $row): bool => empty($row['installed']) || (string)($row['status'] ?? '') !== 'active')),
                    'warnings' => count(array_filter($modulePanels, fn(array $row): bool => $this->moduleCard($row)['health_status'] === 'warning')),
                    'errors' => count(array_filter($modulePanels, fn(array $row): bool => $this->moduleCard($row)['health_status'] === 'error')),
                ],
                'by_suite' => $moduleGroups,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $coreStatus
     * @param array<string,mixed> $verification
     * @return array<string,mixed>
     */
    private function coreCard(array $coreStatus, array $verification): array
    {
        $environmentIssues = count(array_filter((array)($coreStatus['preflight']['environment'] ?? []), static fn(array $row): bool => empty($row['ok'])));
        $writableIssues = count(array_filter((array)($coreStatus['preflight']['writable_paths'] ?? []), static fn(array $row): bool => empty($row['ok'])));
        $missingTables = (int)($coreStatus['core_schema']['missing'] ?? 0);
        $failedHooks = count(array_filter((array)($verification['failed_hooks'] ?? []), static fn(array $row): bool => strtolower((string)($row['app_key'] ?? '')) === 'base'));
        $schemaGaps = count(array_filter((array)($verification['schema_gaps'] ?? []), static fn(array $row): bool => strtolower((string)($row['owner'] ?? '')) === 'base'));
        $inactiveModules = (int)($coreStatus['platform_modules']['total'] ?? 0) - (int)($coreStatus['platform_modules']['active'] ?? 0);
        $errors = $environmentIssues + $writableIssues + (!empty($coreStatus['preflight']['database']['ok']) ? 0 : 1);
        $warnings = $missingTables + $inactiveModules + ($coreStatus['admin_bootstrap']['ready'] ?? false ? 0 : 1);

        $healthStatus = 'healthy';
        $runtimeHealth = 'Core bootstrap is active and ready.';
        if ($errors > 0) {
            $healthStatus = 'error';
            $runtimeHealth = 'Core runtime is blocked by environment, path, or database issues.';
        } elseif ($warnings > 0 || $failedHooks > 0 || $schemaGaps > 0) {
            $healthStatus = 'warning';
            $runtimeHealth = 'Core runtime is available, but bootstrap or schema work still needs attention.';
        }

        return [
            'key' => 'core',
            'label' => 'Core OdareHub',
            'enabled_label' => !empty($coreStatus['preflight']['database']['ok']) ? 'Enabled' : 'Disabled',
            'health_status' => $healthStatus,
            'runtime_health' => $runtimeHealth,
            'missing_tables' => $missingTables,
            'failed_hooks' => $failedHooks,
            'schema_gaps' => $schemaGaps,
            'warnings' => $warnings,
            'errors' => $errors,
            'next_action' => (string)($coreStatus['next_action'] ?? ''),
            'status_detail' => [
                'database' => !empty($coreStatus['preflight']['database']['ok']) ? 'Ready' : 'Blocked',
                'platform_modules' => (int)($coreStatus['platform_modules']['active'] ?? 0) . '/' . (int)($coreStatus['platform_modules']['total'] ?? 0) . ' active',
                'admin_bootstrap' => !empty($coreStatus['admin_bootstrap']['ready']) ? 'Ready' : 'Pending',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $suiteStatus
     * @return array<string,mixed>
     */
    private function suiteCard(array $suiteStatus): array
    {
        $registryStatus = strtolower(trim((string)($suiteStatus['registry_status'] ?? 'uploaded')));
        $verification = is_array($suiteStatus['verification'] ?? null) ? $suiteStatus['verification'] : [];
        $missingTables = (int)($verification['missing_tables'] ?? 0);
        $failedHooks = (int)($verification['failed_hooks'] ?? 0);
        $schemaGaps = (int)($verification['schema_gaps'] ?? 0);
        $warnings = (int)($verification['warnings'] ?? 0);
        $errors = (int)($verification['errors'] ?? 0);

        $healthStatus = 'healthy';
        $enabledLabel = $registryStatus === AppRegistryService::STATUS_ENABLED ? 'Enabled' : 'Disabled';
        $runtimeHealth = 'Suite runtime is healthy.';
        if (in_array($registryStatus, [AppRegistryService::STATUS_UPLOADED, AppRegistryService::STATUS_UNINSTALLED], true)) {
            $healthStatus = 'disabled';
            $runtimeHealth = 'Suite is not installed yet.';
        } elseif ($registryStatus === AppRegistryService::STATUS_BROKEN || $errors > 0) {
            $healthStatus = 'error';
            $runtimeHealth = 'Suite runtime has blocking issues.';
        } elseif ($missingTables > 0 || $failedHooks > 0 || $schemaGaps > 0 || $warnings > 0 || $registryStatus === AppRegistryService::STATUS_DISABLED || $registryStatus === AppRegistryService::STATUS_UPGRADE_PENDING) {
            $healthStatus = 'warning';
            $runtimeHealth = 'Suite is available, but verification found issues or incomplete runtime state.';
        }

        $runtimeRows = (array)($verification['detail']['route_runtime_health'] ?? []);
        $runtimeRow = is_array($runtimeRows[0] ?? null) ? $runtimeRows[0] : [];

        return [
            'key' => (string)($suiteStatus['app_key'] ?? ''),
            'label' => (string)($suiteStatus['label'] ?? ''),
            'enabled_label' => $enabledLabel,
            'health_status' => $healthStatus,
            'runtime_health' => $runtimeHealth,
            'missing_tables' => $missingTables,
            'failed_hooks' => $failedHooks,
            'schema_gaps' => $schemaGaps,
            'warnings' => $warnings,
            'errors' => $errors,
            'next_action' => (string)($suiteStatus['next_action'] ?? ''),
            'module_summary' => (array)($suiteStatus['module_summary'] ?? []),
            'status_detail' => [
                'registry' => $registryStatus,
                'entry_file' => !empty($runtimeRow['entry_file_exists']) ? 'Present' : 'Missing',
                'runtime_hooks' => (string)($runtimeRow['runtime_hooks'] ?? 0),
                'routes_declared' => (string)($runtimeRow['routes_declared'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $panel
     * @return array<string,mixed>
     */
    private function moduleCard(array $panel): array
    {
        $missingTables = count((array)($panel['missing_tables'] ?? []));
        $schemaGaps = count((array)($panel['schema_gaps'] ?? []));
        $dependencyErrors = count((array)($panel['dependency_errors'] ?? []));
        $installed = !empty($panel['installed']);
        $runtimeStatus = strtolower(trim((string)($panel['status'] ?? 'missing')));

        $healthStatus = 'healthy';
        $enabledLabel = ($installed && $runtimeStatus === 'active') ? 'Enabled' : 'Disabled';
        $runtimeHealth = 'Module runtime is healthy.';
        if (!$installed) {
            $healthStatus = 'disabled';
            $runtimeHealth = 'Module is not installed.';
        } elseif ($dependencyErrors > 0) {
            $healthStatus = 'error';
            $runtimeHealth = 'Module runtime is blocked by dependency issues.';
        } elseif ($missingTables > 0 || $schemaGaps > 0) {
            $healthStatus = 'warning';
            $runtimeHealth = 'Module is installed, but schema work is still needed.';
        } elseif ($runtimeStatus !== 'active') {
            $healthStatus = 'disabled';
            $runtimeHealth = 'Module is installed but not active.';
        }

        return [
            'name' => (string)($panel['name'] ?? ''),
            'display_name' => (string)($panel['display_name'] ?? $panel['name'] ?? ''),
            'suite' => (string)($panel['suite'] ?? 'other'),
            'enabled_label' => $enabledLabel,
            'health_status' => $healthStatus,
            'runtime_health' => $runtimeHealth,
            'missing_tables' => $missingTables,
            'failed_hooks' => 0,
            'schema_gaps' => $schemaGaps,
            'warnings' => $missingTables + $schemaGaps,
            'errors' => $dependencyErrors,
            'status_detail' => [
                'runtime_status' => $runtimeStatus,
                'installed' => $installed ? 'yes' : 'no',
                'dependency_errors' => $dependencyErrors,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function suiteCardFromRow(array $row): array
    {
        return $this->suiteCard($row);
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     */
    private function countByHealth(array $cards, string $status): int
    {
        return count(array_filter($cards, static fn(array $row): bool => (string)($row['health_status'] ?? '') === $status));
    }
}
