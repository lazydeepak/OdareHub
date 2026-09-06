<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\Router;
use App\Core\View;

final class SuiteSetupService
{
    private SetupProfileService $profiles;
    private ModuleLifecycleService $modules;
    private InstallVerificationService $verification;
    private SetupStateService $state;
    private VersionCatalogService $versions;

    public function __construct(
        ?SetupProfileService $profiles = null,
        ?ModuleLifecycleService $modules = null,
        ?InstallVerificationService $verification = null,
        ?SetupStateService $state = null,
        ?VersionCatalogService $versions = null
    ) {
        $this->profiles = $profiles ?? new SetupProfileService();
        $this->modules = $modules ?? new ModuleLifecycleService();
        $this->verification = $verification ?? new InstallVerificationService($this->modules);
        $this->state = $state ?? new SetupStateService();
        $this->versions = $versions ?? new VersionCatalogService();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function availableSuites(): array
    {
        $suites = [];
        foreach (glob(APP_ROOT . '/apps/*/manifest.json') ?: [] as $manifestFile) {
            if (!is_file($manifestFile)) {
                continue;
            }

            $manifest = AppManifestService::loadFromFile($manifestFile);
            $appKey = strtolower(trim((string)($manifest['id'] ?? '')));
            if ($appKey === '') {
                continue;
            }

            $appType = strtolower(trim((string)($manifest['type'] ?? 'business')));
            if ($appType !== 'business') {
                continue;
            }

            $defaultProfile = $this->profiles->defaultProfileForSuite($appKey);
            $suites[$appKey] = [
                'app_key' => $appKey,
                'label' => (string)($manifest['name'] ?? $appKey),
                'manifest' => $manifest,
                'default_profile' => $defaultProfile,
                'available_profiles' => array_values(array_filter(
                    $this->profiles->suiteProfiles(),
                    static fn(array $profile): bool => strtolower((string)($profile['suite'] ?? '')) === $appKey
                )),
            ];
        }

        ksort($suites);

        return $suites;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function statusCards(): array
    {
        $cards = [];
        foreach ($this->availableSuites() as $suiteKey => $suite) {
            $cards[$suiteKey] = $this->suiteStatus($suiteKey);
        }

        return $cards;
    }

    /**
     * @return array<string,mixed>
     */
    public function suiteStatus(string $suiteKey): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $app = (new AppRegistryService())->find($suiteKey);
        $verification = $this->verification->verifySuite($suiteKey);
        $uiDiagnostics = (new UiSurfaceRegistryDiagnosticsService())->diagnosticsForApp($suiteKey);
        $profileRow = DB::fetchOne('SELECT setting_value FROM core_settings WHERE setting_key=? LIMIT 1', [
            'setup.suite.' . $suiteKey . '.profile',
        ]);
        $configuredProfile = trim((string)($profileRow['setting_value'] ?? ''));

        $moduleNames = array_values(array_map('strval', (array)($suite['manifest']['legacy_bridge_plugins'] ?? [])));
        $moduleRows = [];
        $activeModules = 0;
        $installedModules = 0;
        $disabledModules = 0;
        $moduleIndex = $this->moduleIndex();
        foreach ($moduleNames as $moduleName) {
            $row = $moduleIndex[$moduleName] ?? [
                'name' => $moduleName,
                'display_name' => $moduleName,
                'installed' => false,
                'status' => 'missing',
                'requires' => [],
            ];
            if (!empty($row['installed'])) {
                $installedModules++;
            }
            if ((string)($row['status'] ?? '') === 'active') {
                $activeModules++;
            } elseif (!empty($row['installed'])) {
                $disabledModules++;
            }
            $moduleRows[] = $row;
        }

        $issueModel = $this->buildVerificationIssueModel(
            $suiteKey,
            $verification,
            $uiDiagnostics,
            is_array($app) ? (string)($app['install_path'] ?? '') : ''
        );
        $issueCounts = is_array($issueModel['counts'] ?? null) ? $issueModel['counts'] : [];
        $errorCount = (int)($issueCounts['errors'] ?? 0);
        $warningCount = (int)($issueCounts['warnings'] ?? 0);

        $status = 'not_installed';
        $nextAction = 'Run Install for this suite.';
        if (is_array($app)) {
            $appStatus = (string)($app['status'] ?? '');
            if ($errorCount > 0 || $appStatus === AppRegistryService::STATUS_BROKEN) {
                $status = 'warning';
                $nextAction = 'Resolve the blocking verification issues, then repair and verify the suite again.';
            } elseif ($warningCount > 0) {
                $status = 'warning';
                $nextAction = 'Run Verify, then repair the suite or affected modules.';
            } elseif ($appStatus === AppRegistryService::STATUS_ENABLED && $configuredProfile !== '' && $warningCount === 0 && $errorCount === 0) {
                $status = 'verified';
                $nextAction = 'Suite is healthy.';
            } elseif ($appStatus === AppRegistryService::STATUS_ENABLED) {
                $status = 'configured';
                $nextAction = 'Run Verify to confirm the configured suite is healthy.';
            } elseif (in_array($appStatus, [
                AppRegistryService::STATUS_INSTALLED,
                AppRegistryService::STATUS_DISABLED,
                AppRegistryService::STATUS_UPGRADE_PENDING,
            ], true)) {
                $status = 'installed';
                $nextAction = 'Run Configure to enable runtime and apply defaults/profile.';
            }
        }

        return [
            'app_key' => $suiteKey,
            'label' => (string)($suite['label'] ?? $suiteKey),
            'status' => $status,
            'registry_status' => is_array($app) ? (string)($app['status'] ?? 'uploaded') : 'uploaded',
            'next_action' => $nextAction,
            'configured_profile' => $configuredProfile,
            'default_profile' => (string)($suite['default_profile'] ?? ''),
            'available_profiles' => (array)($suite['available_profiles'] ?? []),
            'module_summary' => [
                'total' => count($moduleRows),
                'installed' => $installedModules,
                'active' => $activeModules,
                'disabled' => $disabledModules,
            ],
            'modules' => $moduleRows,
            'versioning' => $this->versions->suiteInfo($suiteKey),
            'ui_diagnostics' => $uiDiagnostics,
            'verification' => [
                'missing_tables' => count((array)($verification['missing_tables'] ?? [])),
                'failed_hooks' => count((array)($verification['failed_hooks'] ?? [])),
                'schema_gaps' => count((array)($verification['schema_gaps'] ?? [])),
                'warnings' => count((array)($verification['warnings'] ?? [])),
                'errors' => count((array)($verification['errors'] ?? [])),
                'detail' => $verification,
                'issue_model' => $issueModel,
            ],
            'setup_run' => $this->state->latestRun('suite', $suiteKey),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function install(string $suiteKey): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $runId = $this->state->startRun('suite', $suiteKey, 'install', [
            ['key' => 'local_discovery', 'label' => 'Local discovery'],
            ['key' => 'dependency_prepare', 'label' => 'Dependency preparation'],
            ['key' => 'bundle_install', 'label' => 'Bundle install'],
            ['key' => 'post_install_verify', 'label' => 'Post-install verification'],
        ], ['label' => (string)($suite['label'] ?? $suiteKey)]);

        $registry = new AppRegistryService();
        $installer = new AppInstallService();
        $messages = [];
        try {
            $this->state->startStep($runId, 'local_discovery');
            (new AppLocalDiscoveryService())->syncLocalApps();
            $this->state->completeStep($runId, 'local_discovery', SetupStateService::STATUS_INSTALLED, 'Local suite discovery refreshed.');

            $app = $registry->find($suiteKey);
            if (!$app) {
                throw new \RuntimeException('Suite is not discoverable in /apps: ' . $suiteKey);
            }

            $this->state->startStep($runId, 'dependency_prepare');
            $dependencyMessages = $installer->prepareInstallDependencies($suiteKey);
            if ($dependencyMessages === []) {
                $dependencyMessages[] = 'All bundle and module dependencies are already ready.';
            }
            foreach ($dependencyMessages as $message) {
                $messages[] = (string)$message;
            }
            $this->state->completeStep($runId, 'dependency_prepare', SetupStateService::STATUS_CONFIGURED, implode(' ', $dependencyMessages));

            $status = (string)($app['status'] ?? AppRegistryService::STATUS_UPLOADED);
            $this->state->startStep($runId, 'bundle_install');
            if (in_array($status, [
                AppRegistryService::STATUS_UPLOADED,
                AppRegistryService::STATUS_UNINSTALLED,
                AppRegistryService::STATUS_UPGRADE_PENDING,
                AppRegistryService::STATUS_BROKEN,
            ], true)) {
                $installMessages = $installer->install($suiteKey);
                foreach ($installMessages as $message) {
                    $messages[] = (string)$message;
                }
                $messages[] = 'Bundle registered and installed.';
            } else {
                $messages[] = 'Bundle already installed; install phase skipped.';
            }
            $this->state->completeStep($runId, 'bundle_install', SetupStateService::STATUS_INSTALLED, implode(' ', $messages));

            $this->state->startStep($runId, 'post_install_verify');
            $verification = $this->verification->verifySuite($suiteKey);
            $messages[] = 'Child modules are staged through the module lifecycle layer.';
            $this->state->completeStep($runId, 'post_install_verify', SetupStateService::STATUS_VERIFIED, 'Suite install verification recorded.');

            $result = [
                'suite' => $suiteKey,
                'phase' => 'install',
                'status' => 'ok',
                'messages' => $messages,
                'verification' => $verification,
                'manifest_name' => (string)($suite['label'] ?? $suiteKey),
            ];
            $this->state->finalizeRun($runId, SetupStateService::STATUS_INSTALLED, [], null, false, '', 'configure', null, 'configure', $result);
            return $result;
        } catch (\Throwable $e) {
            $failedStep = $this->latestRunningStepKey($runId);
            if ($failedStep !== '') {
                $this->state->failStep($runId, $failedStep, $e->getMessage(), [], false, 'Bundle install may be partial. Review completed steps before retrying.');
            }
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Suite install failed. Retry install or run repair on affected modules.', 'install', 'install', 'configure', [
                'messages' => $messages,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function configure(string $suiteKey, ?string $profileKey = null): array
    {
        $suite = $this->suiteDefinition($suiteKey);
        $profileKey = $this->normalizeSuiteProfile($suiteKey, $profileKey);
        $selectedModules = $this->profiles->modulesForSuiteProfile($profileKey);
        $allModules = array_values(array_map('strval', (array)($suite['manifest']['legacy_bridge_plugins'] ?? [])));
        $requiredModules = $this->resolveRequiredModules($selectedModules, $allModules);
        $messages = [];
        $runId = $this->state->startRun('suite', $suiteKey, 'configure', [
            ['key' => 'enable_runtime', 'label' => 'Enable suite runtime'],
            ['key' => 'selected_modules', 'label' => 'Configure selected modules'],
            ['key' => 'disable_non_profile_modules', 'label' => 'Disable non-profile modules'],
            ['key' => 'suite_defaults', 'label' => 'Persist suite defaults'],
            ['key' => 'suite_verify', 'label' => 'Verify configured suite'],
        ], ['profile' => $profileKey]);

        $app = (new AppRegistryService())->find($suiteKey);
        if (!is_array($app)) {
            throw new \RuntimeException('Suite must be installed before configuration: ' . $suiteKey);
        }

        try {
            $this->state->startStep($runId, 'enable_runtime');
            if ((string)($app['status'] ?? '') !== AppRegistryService::STATUS_ENABLED) {
                (new AppLifecycleService())->enable($suiteKey);
                $messages[] = 'Suite runtime enabled and hooks refreshed.';
            } else {
                $messages[] = 'Suite runtime already enabled.';
            }
            $this->state->completeStep($runId, 'enable_runtime', SetupStateService::STATUS_CONFIGURED, end($messages) ?: 'Runtime enabled.');

            $this->state->startStep($runId, 'selected_modules');
            $orderedSelected = $this->modules->orderedModules($requiredModules);
            foreach ($orderedSelected as $moduleName) {
                $result = $this->modules->run($moduleName, 'repair');
                if (($result['status_after'] ?? '') !== 'active') {
                    $pm = $this->pluginManager();
                    $pm->enable($moduleName);
                }
                $messages[] = in_array($moduleName, $selectedModules, true)
                    ? $moduleName . ' selected module ready.'
                    : $moduleName . ' dependency module ready.';
            }
            $this->state->completeStep($runId, 'selected_modules', SetupStateService::STATUS_CONFIGURED, 'Selected profile modules configured.');

            $this->state->startStep($runId, 'disable_non_profile_modules');
            $toDisable = array_values(array_diff($allModules, $requiredModules));
            $disableWarnings = [];
            foreach ($this->modules->orderedModules($toDisable, true) as $moduleName) {
                $pm = $this->pluginManager();
                if (!$pm->isInstalled($moduleName) || $pm->status($moduleName) !== 'active') {
                    continue;
                }
                try {
                    $pm->disable($moduleName);
                    $messages[] = $moduleName . ' disabled for selected profile.';
                } catch (\Throwable $e) {
                    $disableWarnings[] = $moduleName . ': ' . $e->getMessage();
                    $messages[] = $moduleName . ' kept active: ' . $e->getMessage();
                }
            }
            $this->state->completeStep($runId, 'disable_non_profile_modules', SetupStateService::STATUS_CONFIGURED, 'Non-profile modules processed.', $disableWarnings);

            $this->state->startStep($runId, 'suite_defaults');
            $this->applySuiteSettings($suiteKey, $profileKey);
            $messages[] = 'Suite defaults/configuration applied.';
            $this->state->completeStep($runId, 'suite_defaults', SetupStateService::STATUS_CONFIGURED, 'Suite defaults stored.');

            $this->state->startStep($runId, 'suite_verify');
            $verification = $this->verification->verifySuite($suiteKey);
            $this->assertConfiguredModulesHealthy($suiteKey, $selectedModules, $requiredModules, $verification);
            $this->state->completeStep($runId, 'suite_verify', SetupStateService::STATUS_VERIFIED, 'Suite verification complete.');

            $result = [
                'suite' => $suiteKey,
                'phase' => 'configure',
                'profile' => $profileKey,
                'status' => 'ok',
                'messages' => $messages,
                'verification' => $verification,
            ];
            $this->state->finalizeRun($runId, SetupStateService::STATUS_CONFIGURED, $disableWarnings ?? [], null, false, '', 'verify', null, 'configure', $result);
            return $result;
        } catch (\Throwable $e) {
            $failedStep = $this->latestRunningStepKey($runId);
            $rollbackPerformed = false;
            if ($failedStep === 'enable_runtime') {
                $current = (new AppRegistryService())->find($suiteKey);
                $rollbackPerformed = is_array($current) && (string)($current['status'] ?? '') !== AppRegistryService::STATUS_ENABLED;
            }
            if ($failedStep !== '') {
                $this->state->failStep($runId, $failedStep, $e->getMessage(), [], $rollbackPerformed, 'Suite configure stopped partway through. Review active modules and retry.');
            }
            $this->state->finalizeRun($runId, SetupStateService::STATUS_PARTIAL, [], $e->getMessage(), $rollbackPerformed, 'Run Configure again, or repair affected modules before Verify.', 'configure', 'configure', 'configure', [
                'messages' => $messages,
                'profile' => $profileKey,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function applyProfile(string $suiteKey, string $profileKey): array
    {
        $this->normalizeSuiteProfile($suiteKey, $profileKey);

        return $this->configure($suiteKey, $profileKey);
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(string $suiteKey): array
    {
        $this->suiteDefinition($suiteKey);
        $runId = $this->state->startRun('suite', $suiteKey, 'verify', [
            ['key' => 'suite_verify', 'label' => 'Suite verification'],
        ]);
        try {
            $this->state->startStep($runId, 'suite_verify');
            $verification = $this->verification->verifySuite($suiteKey);
            $this->state->completeStep($runId, 'suite_verify', SetupStateService::STATUS_VERIFIED, 'Suite verification complete.');
            $result = [
                'suite' => $suiteKey,
                'phase' => 'verify',
                'status' => 'ok',
                'messages' => ['Suite verification complete.'],
                'verification' => $verification,
            ];
            $this->state->finalizeRun($runId, SetupStateService::STATUS_VERIFIED, [], null, false, '', null, null, 'configure', $result);
            return $result;
        } catch (\Throwable $e) {
            $this->state->failStep($runId, 'suite_verify', $e->getMessage(), [], false, 'Retry verification after repairing the suite.');
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Suite verification failed.', 'verify', 'verify', 'configure');
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function suiteDefinition(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $suites = $this->availableSuites();
        if (!isset($suites[$suiteKey])) {
            throw new \RuntimeException('Unknown suite: ' . $suiteKey);
        }

        return $suites[$suiteKey];
    }

    private function normalizeSuiteProfile(string $suiteKey, ?string $profileKey): string
    {
        $profileKey = trim((string)$profileKey);
        if ($profileKey === '') {
            $profileKey = $this->profiles->defaultProfileForSuite($suiteKey);
        }

        $profile = $this->profiles->suiteProfile($profileKey);
        if (!is_array($profile) || strtolower((string)($profile['suite'] ?? '')) !== strtolower(trim($suiteKey))) {
            throw new \RuntimeException('Invalid suite profile for ' . $suiteKey . ': ' . $profileKey);
        }

        return $profileKey;
    }

    private function applySuiteSettings(string $suiteKey, string $profileKey): void
    {
        $profile = $this->profiles->suiteProfile($profileKey) ?? [];
        $settings = (array)($profile['settings'] ?? []);
        $settings['setup.suite.' . $suiteKey . '.profile'] = $profileKey;
        $settings['setup.suite.' . $suiteKey . '.configured_at'] = date('Y-m-d H:i:s');
        $settings['setup.suite.' . $suiteKey . '.configured_by'] = (string)(Auth::user()['email'] ?? 'system');

        foreach ($settings as $key => $value) {
            DB::query(
                'INSERT INTO core_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
                [(string)$key, (string)$value]
            );
        }
    }

    private function pluginManager(): \App\Core\PluginManager
    {
        static $manager = null;
        if ($manager instanceof \App\Core\PluginManager) {
            return $manager;
        }

        $container = new Container();
        $router = new Router();
        $view = new View(APP_ROOT . '/public/views');
        $bus = new EventBus();
        $manager = new \App\Core\PluginManager(APP_ROOT . '/plugins', $container, $router, $view, $bus);

        return $manager;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function moduleIndex(): array
    {
        $index = [];
        foreach ($this->modules->modulesBySuite() as $suiteModules) {
            foreach ($suiteModules as $row) {
                $index[(string)($row['name'] ?? '')] = $row;
            }
        }

        return $index;
    }

    /**
     * @param array<string,mixed> $verification
     * @param array<string,mixed> $uiDiagnostics
     * @return array<string,mixed>
     */
    private function buildVerificationIssueModel(string $suiteKey, array $verification, array $uiDiagnostics, string $installPath): array
    {
        $schemaIssues = [];
        foreach ((array)($verification['schema_gaps'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $schemaIssues[] = $this->normalizeSchemaGapIssue($suiteKey, $row, $installPath);
        }
        foreach ((array)($verification['missing_tables'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $schemaIssues[] = $this->normalizeMissingTableIssue($row);
        }

        $runtimeIssues = [];
        foreach ((array)($verification['failed_hooks'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $runtimeIssues[] = $this->normalizeFailedHookIssue($row);
        }
        foreach ((array)($verification['route_runtime_health'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $issue = $this->normalizeRuntimeHealthIssue($row);
            if (is_array($issue)) {
                $runtimeIssues[] = $issue;
            }
        }
        if ($runtimeIssues === []) {
            foreach ((array)($verification['errors'] ?? []) as $message) {
                $message = trim((string)$message);
                if ($message === '') {
                    continue;
                }
                $runtimeIssues[] = [
                    'category' => 'runtime',
                    'severity' => 'error',
                    'classification' => 'runtime',
                    'type' => 'Runtime Verification Error',
                    'affected_item' => $suiteKey,
                    'status' => 'Blocking',
                    'reason' => $message,
                    'explanation' => 'Verification returned a runtime-level error for this suite. Review the runtime state and rerun verification after repair.',
                    'technical_detail' => $message,
                    'details' => [
                        ['label' => 'Suite', 'value' => $suiteKey],
                        ['label' => 'Reason', 'value' => $message],
                        ['label' => 'Explanation', 'value' => 'Verification returned a runtime-level error for this suite. Review the runtime state and rerun verification after repair.'],
                    ],
                    'suggested_actions' => [
                        'Inspect the current runtime state for the suite and confirm the entry file and hooks are present.',
                        'Run targeted repair to refresh runtime registration and bootstrapping.',
                        'Run Verify again after repair to confirm the error is cleared.',
                    ],
                    'file_path' => '',
                ];
            }
        }

        $uiIssues = $this->normalizeUiDiagnosticIssues($uiDiagnostics);

        $sections = [
            [
                'key' => 'schema',
                'label' => 'Schema Issues',
                'description' => 'Schema gaps, blocked migrations, missing tables, and dependency gaps.',
                'count' => count($schemaIssues),
                'issues' => $schemaIssues,
            ],
            [
                'key' => 'runtime',
                'label' => 'Runtime / Hook Issues',
                'description' => 'Runtime boot issues, missing hook files, and route or entry health problems.',
                'count' => count($runtimeIssues),
                'issues' => $runtimeIssues,
            ],
            [
                'key' => 'ui',
                'label' => 'UI / Diagnostics',
                'description' => 'Navigation mismatches, duplicate declarations, locale gaps, and deprecated aliases.',
                'count' => count($uiIssues),
                'issues' => $uiIssues,
            ],
        ];

        $allIssues = array_merge($schemaIssues, $runtimeIssues, $uiIssues);
        usort($allIssues, fn(array $left, array $right): int => $this->issuePriority($left) <=> $this->issuePriority($right));

        $primaryIssue = $allIssues[0] ?? null;
        $counts = [
            'errors' => count(array_filter($allIssues, static fn(array $issue): bool => (string)($issue['severity'] ?? '') === 'error')),
            'warnings' => count(array_filter($allIssues, static fn(array $issue): bool => (string)($issue['severity'] ?? '') === 'warning')),
            'info' => count(array_filter($allIssues, static fn(array $issue): bool => (string)($issue['severity'] ?? '') === 'info')),
            'schema' => count($schemaIssues),
            'runtime' => count($runtimeIssues),
            'ui' => count($uiIssues),
            'total' => count($allIssues),
        ];

        return [
            'counts' => $counts,
            'primary_issue' => $primaryIssue,
            'sections' => $sections,
            'actions' => [
                'verify' => [
                    'label' => 'Run Verify',
                    'context' => 'Re-check schema, hooks, and runtime health for this suite.',
                ],
                'repair' => [
                    'label' => 'Run Targeted Repair',
                    'context' => $this->repairActionContext($counts, $primaryIssue),
                ],
            ],
            'raw_diagnostics' => [
                'verification' => $verification,
                'ui_diagnostics' => $uiDiagnostics,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeSchemaGapIssue(string $suiteKey, array $row, string $installPath): array
    {
        $migration = trim((string)($row['migration'] ?? ''));
        $error = trim((string)($row['error'] ?? ''));
        if ($migration !== '') {
            [$reason, $explanation] = $this->classifyTechnicalDetail($error, 'migration');
            $migrationPath = $this->migrationFilePath($installPath, $migration);
            return [
                'category' => 'schema',
                'severity' => 'warning',
                'classification' => 'schema_gap',
                'type' => str_contains(strtolower($reason), 'non-additive sql') ? 'Schema Migration Blocked' : 'Schema Migration Failed',
                'affected_item' => $migration,
                'status' => 'Not Applied',
                'reason' => $reason,
                'explanation' => $explanation,
                'technical_detail' => $error,
                'details' => [
                    ['label' => 'Migration', 'value' => $migration],
                    ['label' => 'Reason', 'value' => $reason],
                    ['label' => 'Explanation', 'value' => $explanation],
                    ['label' => 'Migration File', 'value' => $migrationPath !== '' ? $migrationPath : 'Not resolved'],
                ],
                'suggested_actions' => [
                    'Rewrite the migration as additive-safe SQL only.',
                    'Split destructive or manual changes into a controlled repair step.',
                    'Run targeted repair after the migration is made safe.',
                ],
                'file_path' => $migrationPath,
            ];
        }

        $dependency = trim((string)($row['dependency'] ?? ''));
        if ($dependency !== '') {
            $owner = trim((string)($row['owner'] ?? $suiteKey));
            return [
                'category' => 'schema',
                'severity' => 'warning',
                'classification' => 'dependency_gap',
                'type' => 'Dependency Gap',
                'affected_item' => $dependency,
                'status' => 'Missing Dependency',
                'reason' => 'A required module dependency is not installed.',
                'explanation' => 'This suite module cannot complete schema or runtime setup until the missing dependency is installed and healthy.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Owner', 'value' => $owner],
                    ['label' => 'Missing Dependency', 'value' => $dependency],
                    ['label' => 'Explanation', 'value' => 'This suite module cannot complete schema or runtime setup until the missing dependency is installed and healthy.'],
                ],
                'suggested_actions' => [
                    'Install or repair the missing dependency before rerunning suite verification.',
                    'Confirm module install order so dependency modules come first.',
                    'Run Verify again after the dependency is available.',
                ],
                'file_path' => '',
            ];
        }

        return [
            'category' => 'schema',
            'severity' => 'warning',
            'classification' => 'schema_gap',
            'type' => 'Schema Gap',
            'affected_item' => trim((string)($row['owner'] ?? $suiteKey)),
            'status' => 'Needs Review',
            'reason' => 'Verification found a schema gap for this suite.',
            'explanation' => 'The schema is not aligned with what the suite expects. Review the affected owner and rerun repair or verification as needed.',
            'technical_detail' => '',
            'details' => [
                ['label' => 'Owner', 'value' => trim((string)($row['owner'] ?? $suiteKey))],
                ['label' => 'Reason', 'value' => 'Verification found a schema gap for this suite.'],
                ['label' => 'Explanation', 'value' => 'The schema is not aligned with what the suite expects. Review the affected owner and rerun repair or verification as needed.'],
            ],
            'suggested_actions' => [
                'Review the affected owner and confirm the expected schema objects exist.',
                'Run targeted repair for the suite or affected module.',
                'Re-run verification after the schema is aligned.',
            ],
            'file_path' => '',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeMissingTableIssue(array $row): array
    {
        $table = trim((string)($row['table'] ?? ''));
        $owner = trim((string)($row['owner'] ?? ($row['suite'] ?? '')));
        return [
            'category' => 'schema',
            'severity' => 'warning',
            'classification' => 'missing_table',
            'type' => 'Missing Required Table',
            'affected_item' => $table !== '' ? $table : 'unknown table',
            'status' => 'Missing',
            'reason' => 'A required database table was not found during verification.',
            'explanation' => 'The suite expects this table to exist before runtime can be considered healthy. Create or restore it, then rerun verification.',
            'technical_detail' => '',
            'details' => [
                ['label' => 'Table', 'value' => $table !== '' ? $table : 'unknown table'],
                ['label' => 'Owner', 'value' => $owner !== '' ? $owner : 'suite'],
                ['label' => 'Explanation', 'value' => 'The suite expects this table to exist before runtime can be considered healthy. Create or restore it, then rerun verification.'],
            ],
            'suggested_actions' => [
                'Run targeted repair to create any missing additive schema objects.',
                'If the table should already exist, verify the migration chain and database connection.',
                'Run Verify again after the table is present.',
            ],
            'file_path' => '',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeFailedHookIssue(array $row): array
    {
        $enabled = !empty($row['enabled']);
        $file = trim((string)($row['file'] ?? ''));
        $hookKey = trim((string)($row['hook_key'] ?? ''));
        $hookType = trim((string)($row['hook_type'] ?? ''));
        return [
            'category' => 'runtime',
            'severity' => $enabled ? 'warning' : 'info',
            'classification' => 'runtime_hook',
            'type' => 'Runtime Hook File Missing',
            'affected_item' => $hookKey !== '' ? $hookKey : $hookType,
            'status' => $enabled ? 'Unavailable' : 'Disabled',
            'reason' => 'The registered runtime hook points to a file that does not exist.',
            'explanation' => 'The hook cannot be loaded until the referenced handler file is restored or the hook registration is corrected.',
            'technical_detail' => $file,
            'details' => [
                ['label' => 'Hook', 'value' => $hookKey !== '' ? $hookKey : $hookType],
                ['label' => 'Hook Type', 'value' => $hookType !== '' ? $hookType : 'unknown'],
                ['label' => 'Missing File', 'value' => $file !== '' ? $file : 'unknown'],
            ],
            'suggested_actions' => [
                'Restore the missing hook file or update the hook registration to the correct path.',
                'If the hook is obsolete, remove or disable the stale registration.',
                'Run Verify again after the runtime hook path is corrected.',
            ],
            'file_path' => $file,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function normalizeRuntimeHealthIssue(array $row): ?array
    {
        $status = trim((string)($row['status'] ?? ''));
        $entryExists = !empty($row['entry_file_exists']);
        if ($status === AppRegistryService::STATUS_ENABLED && !$entryExists) {
            return [
                'category' => 'runtime',
                'severity' => 'error',
                'classification' => 'runtime_health',
                'type' => 'Suite Entry File Missing',
                'affected_item' => trim((string)($row['app_key'] ?? 'suite')),
                'status' => 'Blocking',
                'reason' => 'The suite is enabled, but its entry file cannot be found at runtime.',
                'explanation' => 'Without the declared entry file, routes and runtime bootstrapping for the suite may not load correctly.',
                'technical_detail' => 'Entry file missing while suite status is enabled.',
                'details' => [
                    ['label' => 'Suite', 'value' => trim((string)($row['app_key'] ?? 'suite'))],
                    ['label' => 'Registry Status', 'value' => $status],
                    ['label' => 'Declared Routes', 'value' => (string)((int)($row['routes_declared'] ?? 0))],
                    ['label' => 'Runtime Hooks', 'value' => (string)((int)($row['runtime_hooks'] ?? 0))],
                ],
                'suggested_actions' => [
                    'Repair the suite so the runtime entry file and registration are refreshed.',
                    'Confirm the suite package still contains its declared entry file.',
                    'Run Verify again after the suite entry point is restored.',
                ],
                'file_path' => '',
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $uiDiagnostics
     * @return array<int,array<string,mixed>>
     */
    private function normalizeUiDiagnosticIssues(array $uiDiagnostics): array
    {
        $summary = is_array($uiDiagnostics['summary'] ?? null) ? $uiDiagnostics['summary'] : [];
        $issues = [];

        $duplicateCount = (int)($summary['duplicate_navigation_ownership'] ?? ($summary['duplicate_declarations'] ?? 0));
        if ($duplicateCount > 0) {
            $issues[] = [
                'category' => 'ui',
                'severity' => 'warning',
                'classification' => 'ui_duplicates',
                'type' => 'Duplicate Navigation Ownership',
                'affected_item' => $duplicateCount . ' ownership conflict(s)',
                'status' => 'Review Required',
                'reason' => 'Multiple navigation owners still claim the same key or canonical route for this suite.',
                'explanation' => 'Only one navigation owner should control each sidebar destination. Widgets, charts, dashboards, and search entries may reuse the same URL without owning navigation.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Ownership Conflicts', 'value' => (string)$duplicateCount],
                    ['label' => 'Explanation', 'value' => 'Only one navigation owner should control each sidebar destination. Widgets, charts, dashboards, and search entries may reuse the same URL without owning navigation.'],
                ],
                'suggested_actions' => [
                    'Keep one canonical navigation owner for each sidebar route or menu key.',
                    'Move non-owning reuse into widgets, dashboards, charts, or search entries when the shared URL is intentional.',
                    'Run Verify again after navigation ownership is cleaned up.',
                ],
                'file_path' => '',
            ];
        }

        $navContractCount = (int)($summary['nav_contract_items'] ?? 0);
        $navigationPhpCount = (int)($summary['navigation_php_items'] ?? 0);
        $navMismatchCount = (int)($summary['navigation_contract_mismatches'] ?? (($navContractCount !== $navigationPhpCount) ? 1 : 0));
        if ($navMismatchCount > 0) {
            $issues[] = [
                'category' => 'ui',
                'severity' => 'warning',
                'classification' => 'nav_mismatch',
                'type' => 'Navigation Contract Mismatch',
                'affected_item' => 'manifest contract vs navigation.php',
                'status' => 'Review Required',
                'reason' => 'The canonical navigation contract does not align with navigation.php.',
                'explanation' => 'When the ownership contract and navigation.php diverge, sidebar visibility and route ownership can drift out of sync.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Manifest Contract Items', 'value' => (string)$navContractCount],
                    ['label' => 'navigation.php Items', 'value' => (string)$navigationPhpCount],
                    ['label' => 'Mismatch Count', 'value' => (string)$navMismatchCount],
                    ['label' => 'Explanation', 'value' => 'When the ownership contract and navigation.php diverge, sidebar visibility and route ownership can drift out of sync.'],
                ],
                'suggested_actions' => [
                    'Align the canonical navigation contract with navigation.php so the runtime ownership stays consistent.',
                    'Resolve duplicate owners before changing the navigation contract.',
                    'Run Verify again after the navigation sources are aligned.',
                ],
                'file_path' => '',
            ];
        }

        $sharedRouteReuseCount = (int)($summary['shared_route_reuse'] ?? 0);
        if ($sharedRouteReuseCount > 0) {
            $issues[] = [
                'category' => 'ui',
                'severity' => 'info',
                'classification' => 'shared_route_reuse',
                'type' => 'Shared Route Reuse',
                'affected_item' => $sharedRouteReuseCount . ' shared route reuse pattern(s)',
                'status' => 'Tracked',
                'reason' => 'Some non-owning UI surfaces intentionally point to the same canonical screens.',
                'explanation' => 'This is expected for discoverability across dashboards, widgets, charts, and search as long as navigation ownership remains singular.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Shared Routes', 'value' => (string)$sharedRouteReuseCount],
                    ['label' => 'Explanation', 'value' => 'Dashboards, widgets, charts, and search entries may intentionally share canonical targets without creating a navigation conflict.'],
                ],
                'suggested_actions' => [
                    'No action is required unless a shared route is accidentally owning navigation in more than one place.',
                    'Keep canonical routes stable so reuse remains intentional and predictable.',
                ],
                'file_path' => '',
            ];
        }

        $missingLocaleCount = (int)($summary['missing_locale_keys'] ?? 0);
        if ($missingLocaleCount > 0) {
            $issues[] = [
                'category' => 'ui',
                'severity' => 'warning',
                'classification' => 'ui_locale',
                'type' => 'Missing Locale Keys',
                'affected_item' => $missingLocaleCount . ' locale gap(s)',
                'status' => 'Review Required',
                'reason' => 'One or more declared UI surfaces are missing locale keys.',
                'explanation' => 'Missing locale keys leave parts of the suite UI untranslated or inconsistent across supported languages.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Missing Locale Keys', 'value' => (string)$missingLocaleCount],
                    ['label' => 'Explanation', 'value' => 'Missing locale keys leave parts of the suite UI untranslated or inconsistent across supported languages.'],
                ],
                'suggested_actions' => [
                    'Add the missing locale keys in supported language files.',
                    'Check newly introduced surfaces so label keys are declared consistently.',
                    'Run Verify again after the locale gaps are closed.',
                ],
                'file_path' => '',
            ];
        }

        $deprecatedAliasCount = (int)($summary['deprecated_ui_aliases'] ?? 0);
        if ($deprecatedAliasCount > 0) {
            $issues[] = [
                'category' => 'ui',
                'severity' => 'info',
                'classification' => 'ui_aliases',
                'type' => 'Compatibility Aliases Present',
                'affected_item' => $deprecatedAliasCount . ' compatibility alias(es)',
                'status' => 'Review',
                'reason' => 'This suite still exposes deprecated compatibility aliases.',
                'explanation' => 'Compatibility aliases can remain temporarily for safe route continuity, but they should stay separate from canonical navigation ownership.',
                'technical_detail' => '',
                'details' => [
                    ['label' => 'Compatibility Aliases', 'value' => (string)$deprecatedAliasCount],
                    ['label' => 'Explanation', 'value' => 'Compatibility aliases can remain temporarily for safe route continuity, but they should stay separate from canonical navigation ownership.'],
                ],
                'suggested_actions' => [
                    'Confirm which aliases are still required for compatibility and remove stale ones later.',
                    'Continue to prefer canonical routes in new navigation and UI work.',
                    'Keep alias cleanup documented until it is safe to remove them.',
                ],
                'file_path' => '',
            ];
        }

        return $issues;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function classifyTechnicalDetail(string $technicalDetail, string $context): array
    {
        $detail = trim($technicalDetail);
        $detailLower = strtolower($detail);
        if ($detail !== '' && str_contains($detailLower, 'non-additive sql')) {
            return [
                'Non-additive SQL blocked by schema sync policy',
                'This migration contains operations that modify, remove, or rename existing schema elements, so automatic sync will not apply it in safe mode.',
            ];
        }
        if ($detail !== '' && str_contains($detailLower, 'unknown column')) {
            return [
                'Migration references a column that does not exist yet',
                'This usually means the migration order is wrong or a prerequisite column was never created. Add the required column earlier in the chain or repair the prerequisite module first.',
            ];
        }
        if ($detail !== '' && str_contains($detailLower, 'unknown table')) {
            return [
                'Migration references a table that does not exist yet',
                'The migration expects a table that has not been created yet. Verify suite/module install order and prerequisite migrations before retrying.',
            ];
        }
        if ($detail !== '' && str_contains($detailLower, 'duplicate column')) {
            return [
                'Migration attempted to add a column that already exists',
                'The schema is already partially ahead of this migration. Review the live schema and make the migration idempotent or split out the manual repair step.',
            ];
        }
        if ($detail !== '' && $context === 'migration') {
            return [
                'Migration failed during schema verification',
                'The migration did not apply successfully, so the suite schema is behind the expected version. Review the migration error, fix the root cause, and rerun repair.',
            ];
        }

        return [
            'Verification found a technical issue',
            'Review the technical detail, correct the underlying schema or runtime state, and rerun verification.',
        ];
    }

    /**
     * @param array<string,mixed>|null $primaryIssue
     * @param array<string,int> $counts
     */
    private function repairActionContext(array $counts, ?array $primaryIssue): string
    {
        if (($counts['total'] ?? 0) <= 0) {
            return 'No active repair target is detected. Use Verify to confirm the suite is still healthy.';
        }

        $primaryType = trim((string)($primaryIssue['type'] ?? 'issue'));
        $schemaCount = (int)($counts['schema'] ?? 0);
        $runtimeCount = (int)($counts['runtime'] ?? 0);
        if ($primaryType === 'Schema Migration Blocked' && $schemaCount === 1) {
            return 'Fix 1 schema issue (blocked migration).';
        }
        if ($schemaCount > 0) {
            return 'Fix ' . $schemaCount . ' schema issue' . ($schemaCount === 1 ? '' : 's') . ' and refresh the suite verification state.';
        }
        if ($runtimeCount > 0) {
            return 'Repair ' . $runtimeCount . ' runtime or hook issue' . ($runtimeCount === 1 ? '' : 's') . ' and re-check boot health.';
        }

        return 'Review the current diagnostics and rerun repair for the affected suite target.';
    }

    /**
     * @param array<string,mixed> $issue
     */
    private function issuePriority(array $issue): int
    {
        $severityWeight = match ((string)($issue['severity'] ?? 'info')) {
            'error' => 0,
            'warning' => 1,
            default => 2,
        };
        $categoryWeight = match ((string)($issue['category'] ?? 'ui')) {
            'schema' => 0,
            'runtime' => 1,
            default => 2,
        };

        return ($severityWeight * 10) + $categoryWeight;
    }

    private function migrationFilePath(string $installPath, string $migration): string
    {
        $installPath = rtrim($installPath, '/');
        $migration = trim($migration);
        if ($installPath === '' || $migration === '') {
            return '';
        }

        $path = $installPath . '/migrations/' . $migration;
        return is_file($path) ? $path : '';
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

    /**
     * @param array<int,string> $selectedModules
     * @param array<int,string> $suiteModules
     * @return array<int,string>
     */
    private function resolveRequiredModules(array $selectedModules, array $suiteModules): array
    {
        $selectedModules = array_values(array_unique(array_filter(array_map('strval', $selectedModules))));
        $suiteModules = array_values(array_unique(array_filter(array_map('strval', $suiteModules))));
        if ($selectedModules === []) {
            return $suiteModules;
        }

        $manifests = $this->pluginManager()->manifests();
        $required = [];
        $queue = $selectedModules;
        while ($queue !== []) {
            $moduleName = array_shift($queue);
            if (!is_string($moduleName) || $moduleName === '' || isset($required[$moduleName])) {
                continue;
            }

            $required[$moduleName] = true;
            $manifest = $manifests[$moduleName] ?? null;
            if (!is_array($manifest)) {
                continue;
            }

            foreach ((array)($manifest['requires'] ?? []) as $rawDependency) {
                $dependency = $this->dependencyNameFromRequirement((string)$rawDependency);
                if ($dependency === '' || isset($required[$dependency])) {
                    continue;
                }

                if (!isset($manifests[$dependency])) {
                    continue;
                }

                $queue[] = $dependency;
            }
        }

        foreach ($suiteModules as $moduleName) {
            if (isset($required[$moduleName])) {
                continue;
            }
            if (in_array($moduleName, $selectedModules, true)) {
                $required[$moduleName] = true;
            }
        }

        return array_keys($required);
    }

    /**
     * @param array<int,string> $selectedModules
     * @param array<int,string> $requiredModules
     * @param array<string,mixed> $verification
     */
    private function assertConfiguredModulesHealthy(string $suiteKey, array $selectedModules, array $requiredModules, array $verification): void
    {
        $moduleIndex = $this->moduleIndex();
        $required = array_values(array_unique(array_filter(array_map('strval', $requiredModules))));
        $selected = array_values(array_unique(array_filter(array_map('strval', $selectedModules))));
        $missing = [];

        foreach ($required as $moduleName) {
            $row = $moduleIndex[$moduleName] ?? null;
            if (!is_array($row) || empty($row['installed']) || (string)($row['status'] ?? 'missing') !== 'active') {
                $missing[] = $moduleName;
            }
        }

        $brokenVerificationModules = [];
        foreach ((array)($verification['missing_tables'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '' && in_array($owner, $required, true)) {
                $brokenVerificationModules[$owner] = true;
            }
        }
        foreach ((array)($verification['schema_gaps'] ?? []) as $row) {
            $owner = trim((string)($row['owner'] ?? ''));
            if ($owner !== '' && in_array($owner, $required, true)) {
                $brokenVerificationModules[$owner] = true;
            }
            $dependency = trim((string)($row['dependency'] ?? ''));
            if ($dependency !== '' && in_array($dependency, $required, true)) {
                $brokenVerificationModules[$dependency] = true;
            }
        }

        if ($missing !== [] || $brokenVerificationModules !== []) {
            $selectedLabel = $selected !== [] ? implode(', ', $selected) : 'all suite modules';
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'inactive modules: ' . implode(', ', array_values(array_unique($missing)));
            }
            if ($brokenVerificationModules !== []) {
                $parts[] = 'verification issues: ' . implode(', ', array_keys($brokenVerificationModules));
            }

            throw new \RuntimeException(
                'Suite ' . $suiteKey . ' did not reach healthy state for selected modules (' . $selectedLabel . '): ' . implode('; ', $parts)
            );
        }
    }

    private function dependencyNameFromRequirement(string $rawRequirement): string
    {
        $rawRequirement = trim($rawRequirement);
        if ($rawRequirement === '') {
            return '';
        }

        return preg_replace('/[<>=].*$/', '', $rawRequirement) ?: '';
    }
}
