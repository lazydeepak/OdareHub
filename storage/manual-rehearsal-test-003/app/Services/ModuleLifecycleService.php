<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

final class ModuleLifecycleService
{
    private SetupStateService $state;
    private VersionCatalogService $versions;

    public function __construct(?SetupStateService $state = null, ?VersionCatalogService $versions = null)
    {
        $this->state = $state ?? new SetupStateService();
        $this->versions = $versions ?? new VersionCatalogService();
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function modulesBySuite(): array
    {
        $modules = [
            'core' => [],
            'sbaio' => [],
            'manufacturing' => [],
            'other' => [],
        ];

        foreach ($this->pluginManager()->manifests() as $name => $manifest) {
            if ($this->isLifecycleHidden($manifest)) {
                continue;
            }

            $suite = strtolower(trim((string)($manifest['suite'] ?? 'other')));
            if (!isset($modules[$suite])) {
                $suite = 'other';
            }

            $modules[$suite][] = [
                'name' => $name,
                'suite' => $suite,
                'owner_app' => trim((string)($manifest['owner_app'] ?? '')),
                'display_name' => trim((string)($manifest['display_name'] ?? $name)),
                'version' => trim((string)($manifest['version'] ?? '0.0.0')),
                'requires' => array_values(array_map('strval', (array)($manifest['requires'] ?? []))),
                'required_tables' => array_values(array_map('strval', (array)($manifest['required_tables'] ?? []))),
                'installed' => $this->pluginManager()->isInstalled($name),
                'status' => $this->pluginManager()->status($name) ?? 'missing',
                'versioning' => $this->versions->moduleInfo($name),
            ];
        }

        foreach ($modules as &$rows) {
            usort($rows, static function (array $left, array $right): int {
                return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
            });
        }
        unset($rows);

        return $modules;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function panelRows(): array
    {
        $verification = new InstallVerificationService($this);
        $verificationReport = $verification->verifyAll();
        $missingTables = [];
        foreach ((array)($verificationReport['missing_tables'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '') {
                $missingTables[$owner][] = $row;
            }
        }

        $schemaGaps = [];
        foreach ((array)($verificationReport['schema_gaps'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '') {
                $schemaGaps[$owner][] = $row;
            }
        }

        $panels = [];
        foreach ($this->modulesBySuite() as $suiteKey => $modules) {
            foreach ($modules as $module) {
                $name = (string)($module['name'] ?? '');
                $status = 'not_installed';
                $nextAction = 'Install module.';
                if (!empty($missingTables[$name]) || !empty($schemaGaps[$name])) {
                    $status = 'warning';
                    $nextAction = 'Repair or schema sync this module.';
                } elseif (!empty($module['installed']) && (string)($module['status'] ?? '') === 'active') {
                    $status = 'configured';
                    $nextAction = 'Verify dependencies and schema health.';
                } elseif (!empty($module['installed'])) {
                    $status = 'installed';
                    $nextAction = 'Enable through suite Configure or repair the module.';
                }

                $module['panel_status'] = $status;
                $module['next_action'] = $nextAction;
                $module['dependency_errors'] = $this->dependencyIssues($module);
                $module['missing_tables'] = $missingTables[$name] ?? [];
                $module['schema_gaps'] = $schemaGaps[$name] ?? [];
                $module['setup_run'] = $this->state->latestRun('module', $name);
                $panels[] = $module;
            }
        }

        usort($panels, static function (array $left, array $right): int {
            $suiteCompare = strcmp((string)($left['suite'] ?? ''), (string)($right['suite'] ?? ''));
            if ($suiteCompare !== 0) {
                return $suiteCompare;
            }

            return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
        });

        return $panels;
    }

    /**
     * @return array<string,mixed>
     */
    public function run(string $moduleName, string $operation): array
    {
        $moduleName = trim($moduleName);
        $operation = strtolower(trim($operation));
        $pm = $this->pluginManager();
        $manifest = $pm->manifests()[$moduleName] ?? null;
        if (!is_array($manifest)) {
            throw new \RuntimeException('Unknown module: ' . $moduleName);
        }

        $steps = [
            ['key' => 'dependency_validation', 'label' => 'Dependency validation'],
            ['key' => 'module_operation', 'label' => 'Module lifecycle operation'],
        ];
        $runId = $this->state->startRun('module', $moduleName, $operation, $steps, ['suite' => strtolower(trim((string)($manifest['suite'] ?? 'other')))]);

        $result = [
            'module' => $moduleName,
            'suite' => strtolower(trim((string)($manifest['suite'] ?? 'other'))),
            'operation' => $operation,
            'dependencies' => $this->dependencyReport($moduleName, $manifest, $pm),
            'messages' => [],
            'status_before' => $pm->status($moduleName) ?? 'missing',
            'status_after' => $pm->status($moduleName) ?? 'missing',
        ];
        try {
            $this->state->startStep($runId, 'dependency_validation');
            $dependencyIssues = $this->dependencyIssues($moduleName === '' ? [] : [
                'requires' => (array)($manifest['requires'] ?? []),
            ]);
            $this->state->completeStep($runId, 'dependency_validation', SetupStateService::STATUS_CONFIGURED, 'Dependency validation complete.', $dependencyIssues);

            if ($operation === 'validate') {
                $result['messages'][] = 'Dependency validation complete.';
                $this->state->finalizeRun($runId, SetupStateService::STATUS_CONFIGURED, $dependencyIssues, null, false, '', null, null, 'repair', $result);
                return $result;
            }

            $this->state->startStep($runId, 'module_operation');
            if ($operation === 'install') {
                if (!$pm->isInstalled($moduleName)) {
                    $pm->install($moduleName);
                    $result['messages'][] = 'Module installed.';
                } else {
                    $result['messages'][] = 'Module already installed.';
                }
            } elseif ($operation === 'update' || $operation === 'schema_sync') {
                if (!$pm->isInstalled($moduleName)) {
                    throw new \RuntimeException('Module must be installed before update/schema sync: ' . $moduleName);
                }
                $pm->update($moduleName);
                $result['messages'][] = $operation === 'schema_sync' ? 'Schema re-sync complete.' : 'Module update complete.';
            } elseif ($operation === 'enable' || $operation === 'activate') {
                if (!$pm->isInstalled($moduleName)) {
                    throw new \RuntimeException('Module must be installed before activation: ' . $moduleName);
                }
                $pm->enable($moduleName);
                $result['messages'][] = 'Module activated.';
            } elseif ($operation === 'repair') {
                $wasInstalled = $pm->isInstalled($moduleName);
                $wasActive = $pm->status($moduleName) === 'active';
                if (!$wasInstalled) {
                    $pm->install($moduleName);
                    $result['messages'][] = 'Module installed during repair.';
                } else {
                    $pm->update($moduleName);
                    $result['messages'][] = 'Module schema and hooks refreshed.';
                }

                if ($wasActive) {
                    $pm->enable($moduleName);
                    $result['messages'][] = 'Active runtime restored.';
                }
            } else {
                throw new \RuntimeException('Unsupported module lifecycle operation: ' . $operation);
            }

            $result['status_after'] = $pm->status($moduleName) ?? 'missing';
            $result['dependencies'] = $this->dependencyReport($moduleName, $manifest, $pm);
            $finalStatus = match ($operation) {
                'install' => SetupStateService::STATUS_INSTALLED,
                'update', 'schema_sync', 'enable', 'activate', 'repair' => SetupStateService::STATUS_CONFIGURED,
                default => SetupStateService::STATUS_CONFIGURED,
            };
            $this->state->completeStep($runId, 'module_operation', $finalStatus, implode(' ', $result['messages']));
            $this->state->finalizeRun($runId, $finalStatus, $dependencyIssues, null, false, '', null, null, 'repair', $result);
            return $result;
        } catch (\Throwable $e) {
            $failedStep = $this->latestRunningStepKey($runId);
            if ($failedStep !== '') {
                $this->state->failStep($runId, $failedStep, $e->getMessage(), [], false, 'Retry this module step or run module repair.');
            }
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Module lifecycle action failed.', $operation, $operation, 'repair', [
                'module' => $moduleName,
                'operation' => $operation,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<int,string>
     */
    public function orderedModules(array $moduleNames, bool $reverse = false): array
    {
        $pm = $this->pluginManager();
        $manifests = $pm->manifests();
        $moduleNames = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $moduleNames)))));
        usort($moduleNames, static function (string $left, string $right) use ($manifests): int {
            $leftOrder = (int)(($manifests[$left]['display_order'] ?? 999));
            $rightOrder = (int)(($manifests[$right]['display_order'] ?? 999));
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp($left, $right);
        });
        $graph = [];

        foreach ($moduleNames as $moduleName) {
            $manifest = $manifests[$moduleName] ?? [];
            $deps = [];
            foreach ((array)($manifest['requires'] ?? []) as $rawDependency) {
                $depName = preg_replace('/[<>=].*$/', '', trim((string)$rawDependency)) ?: '';
                if ($depName !== '' && in_array($depName, $moduleNames, true)) {
                    $deps[] = $depName;
                }
            }
            $graph[$moduleName] = array_values(array_unique($deps));
        }

        $sorted = [];
        $temp = [];
        $perm = [];

        $visit = static function (string $node) use (&$visit, &$sorted, &$temp, &$perm, $graph): void {
            if (isset($perm[$node]) || isset($temp[$node])) {
                return;
            }
            $temp[$node] = true;
            foreach ($graph[$node] ?? [] as $dependency) {
                $visit($dependency);
            }
            $perm[$node] = true;
            $sorted[] = $node;
        };

        foreach ($moduleNames as $moduleName) {
            $visit($moduleName);
        }

        return $reverse ? array_reverse($sorted) : $sorted;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,array<string,mixed>>
     */
    private function dependencyReport(string $moduleName, array $manifest, PluginManager $pm): array
    {
        $rows = [];
        foreach ((array)($manifest['requires'] ?? []) as $rawDependency) {
            $dependency = trim((string)$rawDependency);
            if ($dependency === '') {
                continue;
            }
            $depName = preg_replace('/[<>=].*$/', '', $dependency) ?: '';
            $rows[] = [
                'raw' => $dependency,
                'name' => $depName,
                'installed' => $depName !== '' ? $pm->isInstalled($depName) : false,
                'status' => $depName !== '' ? ($pm->status($depName) ?? 'missing') : 'missing',
                'required_by' => $moduleName,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function isLifecycleHidden(array $manifest): bool
    {
        return (bool)($manifest['hidden_from_catalog'] ?? false)
            || (bool)($manifest['compatibility_only'] ?? false);
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

    /**
     * @param array<string,mixed> $module
     * @return array<int,string>
     */
    private function dependencyIssues(array $module): array
    {
        $issues = [];
        foreach ((array)($module['requires'] ?? []) as $dependency) {
            $depName = preg_replace('/[<>=].*$/', '', trim((string)$dependency)) ?: '';
            if ($depName === '') {
                continue;
            }
            $depInstalled = $this->pluginManager()->isInstalled($depName);
            $depStatus = $this->pluginManager()->status($depName) ?? 'missing';
            if (!$depInstalled) {
                $issues[] = $depName . ' is not installed.';
                continue;
            }
            if ($depStatus !== 'active') {
                $issues[] = $depName . ' is installed but not active.';
            }
        }

        return $issues;
    }

    private function latestRunningStepKey(int $runId): string
    {
        $steps = $this->state->stepsForRun($runId);
        foreach (array_reverse($steps) as $step) {
            if ((string)($step['status'] ?? '') === SetupStateService::STATUS_RUNNING) {
                return (string)($step['step_key'] ?? '');
            }
        }

        return '';
    }
}
