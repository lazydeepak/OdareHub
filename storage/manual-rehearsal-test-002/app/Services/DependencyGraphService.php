<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

final class DependencyGraphService
{
    private ModuleLifecycleService $modules;
    private AppRegistryService $apps;
    private SuiteSetupService $suites;
    private InstallVerificationService $verification;

    public function __construct(
        ?ModuleLifecycleService $modules = null,
        ?AppRegistryService $apps = null,
        ?SuiteSetupService $suites = null,
        ?InstallVerificationService $verification = null
    ) {
        $this->modules = $modules ?? new ModuleLifecycleService();
        $this->apps = $apps ?? new AppRegistryService();
        $this->suites = $suites ?? new SuiteSetupService();
        $this->verification = $verification ?? new InstallVerificationService($this->modules);
    }

    /**
     * @return array<string,mixed>
     */
    public function overview(): array
    {
        $modules = $this->moduleMap();
        $suiteDefinitions = $this->suites->availableSuites();

        $coreRows = [];
        foreach ((array)($this->modules->modulesBySuite()['core'] ?? []) as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $detail = $this->moduleDetail($name, $modules);
            $coreRows[] = [
                'name' => $name,
                'display_name' => (string)($row['display_name'] ?? $name),
                'requires' => $detail['direct_dependencies'],
                'required_by' => $detail['reverse_dependencies'],
                'health' => $detail['dependency_health'],
            ];
        }

        $suiteRows = [];
        foreach ($suiteDefinitions as $suiteKey => $suite) {
            $detail = $this->suiteDetail($suiteKey, $modules, $suiteDefinitions);
            $suiteRows[] = [
                'suite_key' => $suiteKey,
                'label' => (string)($suite['label'] ?? $suiteKey),
                'status' => (string)($detail['status'] ?? 'not_installed'),
                'child_modules' => $detail['child_modules'],
                'shared_platform_dependencies' => $detail['shared_platform_dependencies'],
                'reverse_dependencies' => $detail['reverse_dependencies'],
                'dependency_health' => $detail['dependency_health'],
            ];
        }

        usort($suiteRows, static fn(array $left, array $right): int => strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? '')));

        $moduleRows = [];
        foreach ($modules as $name => $detail) {
            $moduleRows[] = [
                'name' => $name,
                'display_name' => (string)($detail['display_name'] ?? $name),
                'suite' => (string)($detail['owner_suite'] ?? 'other'),
                'direct_dependencies' => $detail['direct_dependencies'],
                'reverse_dependencies' => $detail['reverse_dependencies'],
                'shared_dependencies' => $detail['shared_dependencies'],
                'dependency_health' => $detail['dependency_health'],
            ];
        }

        usort($moduleRows, static function (array $left, array $right): int {
            $suiteCompare = strcmp((string)($left['suite'] ?? ''), (string)($right['suite'] ?? ''));
            if ($suiteCompare !== 0) {
                return $suiteCompare;
            }

            return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
        });

        return [
            'core' => $coreRows,
            'suites' => $suiteRows,
            'modules' => $moduleRows,
            'summary' => [
                'core_modules' => count($coreRows),
                'suites' => count($suiteRows),
                'modules' => count($moduleRows),
                'blocked_modules' => count(array_filter($moduleRows, static fn(array $row): bool => (string)(($row['dependency_health']['status'] ?? 'safe')) === 'blocked')),
                'warning_modules' => count(array_filter($moduleRows, static fn(array $row): bool => (string)(($row['dependency_health']['status'] ?? 'safe')) === 'warning')),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function suiteDetail(string $suiteKey, ?array $modules = null, ?array $suiteDefinitions = null): array
    {
        $modules = is_array($modules) ? $modules : $this->moduleMap();
        $suiteDefinitions = is_array($suiteDefinitions) ? $suiteDefinitions : $this->suites->availableSuites();
        $suite = (array)($suiteDefinitions[$suiteKey] ?? []);
        $childModules = array_values(array_filter(array_map('strval', (array)($suite['manifest']['legacy_bridge_plugins'] ?? []))));
        $directDependencies = array_values(array_filter(array_map([$this, 'dependencyName'], (array)($suite['manifest']['requires'] ?? []))));

        $sharedPlatformDependencies = [];
        $reverseDependencies = [];
        $blockingModules = [];

        foreach ($childModules as $moduleName) {
            $module = $modules[$moduleName] ?? null;
            if (!is_array($module)) {
                $blockingModules[] = $moduleName . ' is not discoverable.';
                continue;
            }

            foreach ((array)($module['direct_dependencies'] ?? []) as $depName) {
                $dependencyModule = $modules[$depName] ?? null;
                if (is_array($dependencyModule) && (string)($dependencyModule['owner_suite'] ?? '') === 'core') {
                    $sharedPlatformDependencies[] = $depName;
                }
            }
        }

        foreach ($modules as $moduleName => $module) {
            $ownerSuite = (string)($module['owner_suite'] ?? '');
            if ($ownerSuite === $suiteKey) {
                continue;
            }

            $dependencies = array_values(array_map('strval', (array)($module['direct_dependencies'] ?? [])));
            foreach ($dependencies as $dependencyName) {
                if (in_array($dependencyName, $childModules, true)) {
                    $reverseDependencies[] = $ownerSuite !== '' ? $ownerSuite . ':' . $moduleName : $moduleName;
                }
            }
        }

        $verification = $this->verification->verifySuite($suiteKey);
        $verificationWarnings = count((array)($verification['warnings'] ?? []));
        $verificationErrors = count((array)($verification['errors'] ?? []));
        $healthStatus = 'safe';
        $healthSummary = 'No dependency blockers detected.';

        if ($blockingModules !== []) {
            $healthStatus = 'blocked';
            $healthSummary = 'One or more child modules are missing from discovery.';
        } elseif ($verificationErrors > 0) {
            $healthStatus = 'warning';
            $healthSummary = 'Verification has errors that may affect suite lifecycle actions.';
        } elseif ($verificationWarnings > 0 || $reverseDependencies !== []) {
            $healthStatus = 'warning';
            $healthSummary = $reverseDependencies !== []
                ? 'Other modules depend on this suite.'
                : 'Suite verification has warnings to review.';
        }

        return [
            'suite_key' => $suiteKey,
            'label' => (string)($suite['label'] ?? $suiteKey),
            'status' => (string)($this->suites->suiteStatus($suiteKey)['status'] ?? 'not_installed'),
            'direct_dependencies' => array_values(array_unique($directDependencies)),
            'child_modules' => $childModules,
            'reverse_dependencies' => array_values(array_unique($reverseDependencies)),
            'shared_platform_dependencies' => array_values(array_unique($sharedPlatformDependencies)),
            'dependency_health' => [
                'status' => $healthStatus,
                'summary' => $healthSummary,
                'blocking_dependencies' => array_values(array_unique($blockingModules)),
            ],
            'action_impacts' => [
                'update' => $this->suiteActionImpact($suiteKey, 'update', $childModules, $reverseDependencies, $verification, $blockingModules),
                'disable' => $this->suiteActionImpact($suiteKey, 'disable', $childModules, $reverseDependencies, $verification, $blockingModules),
                'uninstall' => $this->suiteActionImpact($suiteKey, 'uninstall', $childModules, $reverseDependencies, $verification, $blockingModules),
                'purge' => $this->suiteActionImpact($suiteKey, 'purge', $childModules, $reverseDependencies, $verification, $blockingModules),
                'restore' => $this->suiteActionImpact($suiteKey, 'restore', $childModules, $reverseDependencies, $verification, $blockingModules),
                'release' => $this->suiteActionImpact($suiteKey, 'release', $childModules, $reverseDependencies, $verification, $blockingModules),
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>>|null $modules
     * @return array<string,mixed>
     */
    public function moduleDetail(string $moduleName, ?array $modules = null): array
    {
        $modules = is_array($modules) ? $modules : $this->moduleMap();
        $module = (array)($modules[$moduleName] ?? []);
        $directDependencies = array_values(array_map('strval', (array)($module['requires'] ?? [])));
        $reverseDependencies = [];
        $sharedDependencies = [];
        $blockingDependencies = [];

        foreach ($directDependencies as $dependencyName) {
            $dependency = $modules[$dependencyName] ?? null;
            if (!is_array($dependency)) {
                $blockingDependencies[] = $dependencyName . ' is not discoverable.';
                continue;
            }
            if (empty($dependency['installed'])) {
                $blockingDependencies[] = $dependencyName . ' is not installed.';
            } elseif ((string)($dependency['status'] ?? 'missing') !== 'active') {
                $blockingDependencies[] = $dependencyName . ' is installed but not active.';
            }

            if ((string)($dependency['owner_suite'] ?? '') !== (string)($module['owner_suite'] ?? '')) {
                $sharedDependencies[] = $dependencyName;
            }
        }

        foreach ($modules as $candidateName => $candidate) {
            if ($candidateName === $moduleName) {
                continue;
            }
            $candidateDependencies = array_values(array_map('strval', (array)($candidate['requires'] ?? [])));
            if (in_array($moduleName, $candidateDependencies, true)) {
                $reverseDependencies[] = $candidateName;
            }
        }

        $missingTableCount = count((array)($module['missing_tables'] ?? []));
        $schemaGapCount = count((array)($module['schema_gaps'] ?? []));
        $dependencyErrorCount = count((array)($module['dependency_errors'] ?? []));

        $healthStatus = 'safe';
        $healthSummary = 'Dependencies look healthy.';
        if ($blockingDependencies !== []) {
            $healthStatus = 'blocked';
            $healthSummary = 'Direct dependencies need attention before lifecycle work.';
        } elseif ($dependencyErrorCount > 0 || $missingTableCount > 0 || $schemaGapCount > 0) {
            $healthStatus = 'warning';
            $healthSummary = 'Schema or runtime validation has warnings.';
        }

        return [
            'name' => $moduleName,
            'display_name' => (string)($module['display_name'] ?? $moduleName),
            'owner_suite' => (string)($module['suite'] ?? $module['owner_suite'] ?? 'other'),
            'direct_dependencies' => $directDependencies,
            'reverse_dependencies' => array_values(array_unique($reverseDependencies)),
            'shared_dependencies' => array_values(array_unique($sharedDependencies)),
            'optional_dependencies' => [],
            'dependency_health' => [
                'status' => $healthStatus,
                'summary' => $healthSummary,
                'blocking_dependencies' => array_values(array_unique($blockingDependencies)),
            ],
            'action_impacts' => [
                'update' => $this->moduleActionImpact($moduleName, 'update', $modules),
                'disable' => $this->moduleActionImpact($moduleName, 'disable', $modules),
                'uninstall' => $this->moduleActionImpact($moduleName, 'uninstall', $modules),
                'purge' => $this->moduleActionImpact($moduleName, 'purge', $modules),
                'restore' => $this->moduleActionImpact($moduleName, 'restore', $modules),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function appActionImpact(string $appKey, string $action): array
    {
        $action = strtolower(trim($action));
        $suiteDefinitions = $this->suites->availableSuites();
        if (isset($suiteDefinitions[$appKey])) {
            $suiteDetail = $this->suiteDetail($appKey);
            return (array)($suiteDetail['action_impacts'][$action] ?? $this->emptyImpact($action));
        }

        return $this->emptyImpact($action);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function appActionImpacts(array $appKeys, array $actions = ['enable', 'disable', 'uninstall', 'purge', 'repair', 'restore', 'release']): array
    {
        $rows = [];
        foreach ($appKeys as $appKey) {
            $appKey = trim((string)$appKey);
            if ($appKey === '') {
                continue;
            }
            foreach ($actions as $action) {
                $rows[$appKey][$action] = $this->appActionImpact($appKey, (string)$action);
            }
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    private function suiteActionImpact(string $suiteKey, string $action, array $childModules, array $reverseDependencies, array $verification, array $blockingModules): array
    {
        $activeChildren = 0;
        foreach ($childModules as $moduleName) {
            if (($this->pluginManager()->status($moduleName) ?? 'missing') === 'active') {
                $activeChildren++;
            }
        }

        $schemaRisks = count((array)($verification['schema_gaps'] ?? []));
        $runtimeRisks = count((array)($verification['failed_hooks'] ?? [])) + count((array)($verification['warnings'] ?? []));
        $status = 'safe';
        $summary = 'No dependency blockers detected.';
        $blocking = array_values(array_unique($blockingModules));
        $manualAttention = [];

        if (in_array($action, ['disable', 'uninstall', 'purge'], true) && $reverseDependencies !== []) {
            $status = 'blocked';
            $summary = 'Other suites or modules depend on this suite.';
            $blocking = array_merge($blocking, $reverseDependencies);
        } elseif ($action === 'purge' && $activeChildren > 0) {
            $status = 'warning';
            $summary = 'This destructive action will remove an installed suite footprint.';
        } elseif (in_array($action, ['update', 'restore', 'release'], true) && ($schemaRisks > 0 || $runtimeRisks > 0)) {
            $status = 'warning';
            $summary = 'Verification warnings should be reviewed before this action.';
            if ($schemaRisks > 0) {
                $manualAttention[] = 'Review schema gaps first.';
            }
            if ($runtimeRisks > 0) {
                $manualAttention[] = 'Check failed hooks or runtime warnings.';
            }
        } elseif (in_array($action, ['update', 'restore'], true) && $blocking !== []) {
            $status = 'requires_prior_action';
            $summary = 'Missing child modules or shared dependencies need attention first.';
        } elseif ($action === 'disable' && $activeChildren > 0) {
            $status = 'warning';
            $summary = 'Active child modules will be disabled indirectly.';
        }

        return [
            'action' => $action,
            'status' => $status,
            'label' => $this->impactLabel($status),
            'summary' => $summary,
            'depends_on' => [],
            'required_by' => array_values(array_unique($reverseDependencies)),
            'indirect_changes' => $activeChildren > 0 ? [$activeChildren . ' active child module(s) are in scope.'] : [],
            'schema_runtime_risks' => array_values(array_filter([
                $schemaRisks > 0 ? $schemaRisks . ' schema gap(s)' : '',
                $runtimeRisks > 0 ? $runtimeRisks . ' runtime warning(s)' : '',
            ])),
            'blocking_dependencies' => array_values(array_unique($blocking)),
            'manual_attention' => $manualAttention,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $modules
     * @return array<string,mixed>
     */
    private function moduleActionImpact(string $moduleName, string $action, array $modules): array
    {
        $detail = $this->moduleDetailShallow($moduleName, $modules);
        $blocking = array_values(array_unique((array)($detail['blocking_dependencies'] ?? [])));
        $reverse = array_values(array_unique((array)($detail['reverse_dependencies'] ?? [])));
        $schemaRisks = array_values(array_filter([
            !empty($detail['missing_tables']) ? count((array)$detail['missing_tables']) . ' missing table(s)' : '',
            !empty($detail['schema_gaps']) ? count((array)$detail['schema_gaps']) . ' schema gap(s)' : '',
            !empty($detail['dependency_errors']) ? count((array)$detail['dependency_errors']) . ' runtime dependency issue(s)' : '',
        ]));
        $status = 'safe';
        $summary = 'No dependency blockers detected.';
        $manualAttention = [];

        if (in_array($action, ['disable', 'uninstall', 'purge'], true) && $reverse !== []) {
            $activeDependents = array_values(array_filter($reverse, function (string $name) use ($modules): bool {
                return (($modules[$name]['status'] ?? 'missing') === 'active');
            }));
            if ($activeDependents !== []) {
                $status = 'blocked';
                $summary = 'Active reverse dependencies would break.';
                $blocking = array_merge($blocking, $activeDependents);
            } else {
                $status = 'warning';
                $summary = 'Installed reverse dependencies will need follow-up.';
            }
        } elseif (in_array($action, ['update', 'restore'], true) && $blocking !== []) {
            $status = 'requires_prior_action';
            $summary = 'Fix direct dependencies before this action.';
        } elseif (in_array($action, ['update', 'restore'], true) && $schemaRisks !== []) {
            $status = 'warning';
            $summary = 'Schema or runtime validation has warnings.';
            $manualAttention[] = 'Review validation output before proceeding.';
        } elseif ($action === 'purge') {
            $status = 'warning';
            $summary = 'This destructive action removes the module footprint.';
        }

        return [
            'action' => $action,
            'status' => $status,
            'label' => $this->impactLabel($status),
            'summary' => $summary,
            'depends_on' => array_values(array_map('strval', (array)($detail['direct_dependencies'] ?? []))),
            'required_by' => $reverse,
            'indirect_changes' => $reverse !== [] ? [count($reverse) . ' dependent module(s) may need disabling or repair.'] : [],
            'schema_runtime_risks' => $schemaRisks,
            'blocking_dependencies' => array_values(array_unique($blocking)),
            'manual_attention' => $manualAttention,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $modules
     * @return array<string,mixed>
     */
    private function moduleDetailShallow(string $moduleName, array $modules): array
    {
        $module = (array)($modules[$moduleName] ?? []);
        $directDependencies = array_values(array_map('strval', (array)($module['requires'] ?? [])));
        $reverseDependencies = [];
        $blockingDependencies = [];

        foreach ($modules as $candidateName => $candidate) {
            if ($candidateName === $moduleName) {
                continue;
            }
            $candidateDependencies = array_values(array_map('strval', (array)($candidate['requires'] ?? [])));
            if (in_array($moduleName, $candidateDependencies, true)) {
                $reverseDependencies[] = $candidateName;
            }
        }

        foreach ($directDependencies as $dependencyName) {
            $dependency = $modules[$dependencyName] ?? null;
            if (!is_array($dependency)) {
                $blockingDependencies[] = $dependencyName . ' is not discoverable.';
                continue;
            }
            if (empty($dependency['installed'])) {
                $blockingDependencies[] = $dependencyName . ' is not installed.';
            } elseif ((string)($dependency['status'] ?? 'missing') !== 'active') {
                $blockingDependencies[] = $dependencyName . ' is installed but not active.';
            }
        }

        return [
            'direct_dependencies' => $directDependencies,
            'reverse_dependencies' => array_values(array_unique($reverseDependencies)),
            'blocking_dependencies' => array_values(array_unique($blockingDependencies)),
            'missing_tables' => (array)($module['missing_tables'] ?? []),
            'schema_gaps' => (array)($module['schema_gaps'] ?? []),
            'dependency_errors' => (array)($module['dependency_errors'] ?? []),
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function moduleMap(): array
    {
        $verification = $this->verification->verifyAll();
        $missingTables = [];
        foreach ((array)($verification['missing_tables'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '') {
                $missingTables[$owner][] = $row;
            }
        }

        $schemaGaps = [];
        foreach ((array)($verification['schema_gaps'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '') {
                $schemaGaps[$owner][] = $row;
            }
        }

        $rows = [];
        foreach ($this->modules->panelRows() as $module) {
            $name = trim((string)($module['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $requires = array_values(array_filter(array_map([$this, 'dependencyName'], (array)($module['requires'] ?? []))));
            $module['requires'] = $requires;
            $module['owner_suite'] = (string)($module['suite'] ?? 'other');
            $module['missing_tables'] = $missingTables[$name] ?? (array)($module['missing_tables'] ?? []);
            $module['schema_gaps'] = $schemaGaps[$name] ?? (array)($module['schema_gaps'] ?? []);
            $rows[$name] = $module;
        }

        return $rows;
    }

    private function dependencyName(string $dependency): string
    {
        return preg_replace('/[<>=].*$/', '', trim($dependency)) ?: '';
    }

    private function impactLabel(string $status): string
    {
        return match ($status) {
            'safe' => 'Safe',
            'warning' => 'Warning',
            'blocked' => 'Blocked',
            'requires_prior_action' => 'Requires Prior Action',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyImpact(string $action): array
    {
        return [
            'action' => $action,
            'status' => 'safe',
            'label' => 'Safe',
            'summary' => 'No additional dependency analysis is available for this target.',
            'depends_on' => [],
            'required_by' => [],
            'indirect_changes' => [],
            'schema_runtime_risks' => [],
            'blocking_dependencies' => [],
            'manual_attention' => [],
        ];
    }

    private function pluginManager(): PluginManager
    {
        static $manager = null;
        if ($manager instanceof PluginManager) {
            return $manager;
        }

        $container = new Container();
        $router = new Router();
        $view = new View(APP_ROOT . '/public/views');
        $bus = new EventBus();
        $manager = new PluginManager(APP_ROOT . '/plugins', $container, $router, $view, $bus);

        return $manager;
    }
}
