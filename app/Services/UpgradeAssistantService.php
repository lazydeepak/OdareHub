<?php
declare(strict_types=1);

namespace App\Services;

final class UpgradeAssistantService
{
    private CoreSetupService $core;
    private SuiteSetupService $suites;
    private ModuleLifecycleService $modules;
    private InstallVerificationService $verification;
    private VersionCatalogService $versions;
    private SetupStateService $state;
    private DependencyGraphService $dependencies;

    public function __construct(
        ?CoreSetupService $core = null,
        ?SuiteSetupService $suites = null,
        ?ModuleLifecycleService $modules = null,
        ?InstallVerificationService $verification = null,
        ?VersionCatalogService $versions = null,
        ?SetupStateService $state = null,
        ?DependencyGraphService $dependencies = null
    ) {
        $this->core = $core ?? new CoreSetupService();
        $this->suites = $suites ?? new SuiteSetupService();
        $this->modules = $modules ?? new ModuleLifecycleService();
        $this->verification = $verification ?? new InstallVerificationService($this->modules);
        $this->versions = $versions ?? new VersionCatalogService();
        $this->state = $state ?? new SetupStateService();
        $this->dependencies = $dependencies ?? new DependencyGraphService();
    }

    /**
     * @return array<string,string>
     */
    public function scopeOptions(): array
    {
        return [
            'core' => 'Core OdareHub',
            'suite' => 'Suite / Bundle',
            'module' => 'Child Module',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function targetOptions(): array
    {
        $suiteChoices = [];
        foreach ($this->suites->statusCards() as $suiteKey => $card) {
            $suiteChoices[] = [
                'key' => $suiteKey,
                'label' => (string)($card['label'] ?? $suiteKey),
            ];
        }

        $moduleChoices = [];
        foreach ($this->modules->panelRows() as $panel) {
            $moduleChoices[] = [
                'key' => (string)($panel['name'] ?? ''),
                'label' => (string)($panel['display_name'] ?? $panel['name'] ?? ''),
                'suite' => (string)($panel['suite'] ?? 'other'),
            ];
        }

        return [
            'suites' => $suiteChoices,
            'modules' => $moduleChoices,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function preview(string $scope, string $targetKey): array
    {
        return match ($scope) {
            'core' => $this->previewCore(),
            'suite' => $this->previewSuite($targetKey),
            'module' => $this->previewModule($targetKey),
            default => throw new \RuntimeException('Unsupported upgrade scope: ' . $scope),
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function apply(string $scope, string $targetKey): array
    {
        return match ($scope) {
            'core' => $this->applyCoreUpgrade(),
            'suite' => $this->applySuiteUpgrade($targetKey),
            'module' => $this->applyModuleUpgrade($targetKey),
            default => throw new \RuntimeException('Unsupported upgrade scope: ' . $scope),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function previewCore(): array
    {
        $versioning = $this->versions->coreInfo();
        $coreStatus = $this->core->statusSummary();
        $verification = $this->verification->verifyAll();
        $blocking = [];
        $warnings = [];

        if (count(array_filter((array)($coreStatus['preflight']['environment'] ?? []), static fn(array $row): bool => empty($row['ok']))) > 0) {
            $blocking[] = 'Environment prechecks must pass before a Core upgrade can run.';
        }
        if (count(array_filter((array)($coreStatus['preflight']['writable_paths'] ?? []), static fn(array $row): bool => empty($row['ok']))) > 0) {
            $blocking[] = 'Writable path checks must pass before a Core upgrade can run.';
        }
        if (empty($coreStatus['preflight']['database']['ok'])) {
            $blocking[] = 'Database connection is not ready.';
        }
        foreach ((array)($versioning['latest_entry']['migration_notes'] ?? []) as $note) {
            $warnings[] = (string)$note;
        }
        foreach ((array)($versioning['latest_entry']['setup_upgrade_notes'] ?? []) as $note) {
            $warnings[] = (string)$note;
        }
        if (count((array)($verification['failed_hooks'] ?? [])) > 0) {
            $warnings[] = 'Failed hooks already exist. Verify and repair them after the upgrade.';
        }
        if (count((array)($verification['schema_gaps'] ?? [])) > 0) {
            $warnings[] = 'Schema gaps already exist. Core upgrade will re-run verification, but may still require follow-up repair.';
        }

        return [
            'scope' => 'core',
            'scope_label' => 'Core OdareHub',
            'target_key' => 'core',
            'target_label' => 'Core OdareHub',
            'current_version' => (string)($versioning['current_version'] ?? APP_VERSION),
            'target_version' => (string)($versioning['target_version'] ?? APP_VERSION),
            'ready' => $blocking === [],
            'blocking_issues' => $blocking,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'plan_steps' => [
                'Run pre-upgrade checks for environment, writable paths, and database readiness.',
                'Apply Core bootstrap repair/update actions.',
                'Run full post-upgrade verification for schema, hooks, and runtime health.',
            ],
            'migration_warnings' => $this->migrationWarnings($versioning),
            'failure_recovery_notes' => [
                'If Core upgrade fails, review the latest failed step and use Core recovery from the setup area.',
                'Re-run verification after fixing environment or database issues before retrying.',
            ],
            'changelog_summary' => (string)($versioning['latest_entry']['summary'] ?? ''),
            'verification_summary' => $this->verificationCounts($verification),
            'latest_run' => $this->state->latestRun('core', 'core'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function previewSuite(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        if ($suiteKey === '') {
            throw new \RuntimeException('Choose a suite before previewing upgrade.');
        }

        $suite = $this->suites->suiteStatus($suiteKey);
        $versioning = is_array($suite['versioning'] ?? null) ? $suite['versioning'] : $this->versions->suiteInfo($suiteKey);
        $verification = (array)($suite['verification']['detail'] ?? $this->verification->verifySuite($suiteKey));
        $dependency = $this->dependencies->suiteDetail($suiteKey);
        $blocking = [];
        $warnings = [];

        if ((string)($suite['registry_status'] ?? '') === AppRegistryService::STATUS_UPLOADED) {
            $blocking[] = 'Install the suite before running an upgrade.';
        }
        if ((string)($suite['registry_status'] ?? '') === AppRegistryService::STATUS_BROKEN) {
            $blocking[] = 'Suite is currently broken. Recover or repair it before upgrading.';
        }
        foreach ((array)($dependency['dependency_health']['blocking_dependencies'] ?? []) as $dep) {
            $blocking[] = 'Missing required dependency: ' . (string)$dep;
        }
        if (!empty($dependency['action_impacts'])) {
            foreach ((array)$dependency['action_impacts'] as $impact) {
                if ((string)($impact['action'] ?? '') === 'update' && (string)($impact['status'] ?? '') === 'blocked') {
                    $blocking[] = (string)($impact['summary'] ?? 'Upgrade is blocked by dependency impact.');
                } elseif ((string)($impact['action'] ?? '') === 'update' && (string)($impact['status'] ?? '') !== 'safe') {
                    $warnings[] = (string)($impact['summary'] ?? 'Upgrade has dependency impact.');
                }
            }
        }
        if (empty($versioning['upgrade_available']) && (string)($suite['registry_status'] ?? '') !== AppRegistryService::STATUS_UPGRADE_PENDING) {
            $warnings[] = 'Current suite version is already aligned with the target package version.';
        }
        foreach ($this->migrationWarnings($versioning) as $warning) {
            $warnings[] = $warning;
        }
        foreach ((array)($verification['warnings'] ?? []) as $warning) {
            $warnings[] = (string)$warning;
        }

        return [
            'scope' => 'suite',
            'scope_label' => 'Suite',
            'target_key' => $suiteKey,
            'target_label' => (string)($suite['label'] ?? ucfirst($suiteKey)),
            'current_version' => (string)($versioning['current_version'] ?? ''),
            'target_version' => (string)($versioning['target_version'] ?? ''),
            'ready' => $blocking === [],
            'blocking_issues' => array_values(array_unique(array_filter($blocking))),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'plan_steps' => [
                'Run pre-upgrade checks for suite status, dependencies, and current verification gaps.',
                'Apply the suite package/runtime upgrade path and refresh configured modules/defaults.',
                'Run suite post-upgrade verification and review child-module health.',
            ],
            'migration_warnings' => $this->migrationWarnings($versioning),
            'failure_recovery_notes' => [
                'If suite upgrade fails, use suite recovery or module repair from setup.',
                'Review the suite failed step, then re-run suite verify after recovery.',
            ],
            'changelog_summary' => (string)($versioning['latest_entry']['summary'] ?? ''),
            'verification_summary' => $this->verificationCounts($verification),
            'dependency_summary' => [
                'status' => (string)($dependency['dependency_health']['status'] ?? 'safe'),
                'summary' => (string)($dependency['dependency_health']['summary'] ?? ''),
            ],
            'latest_run' => $this->state->latestRun('suite', $suiteKey),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function previewModule(string $moduleName): array
    {
        $moduleName = trim($moduleName);
        if ($moduleName === '') {
            throw new \RuntimeException('Choose a module before previewing upgrade.');
        }

        $panel = $this->modulePanel($moduleName);
        $versioning = is_array($panel['versioning'] ?? null) ? $panel['versioning'] : $this->versions->moduleInfo($moduleName);
        $dependency = $this->dependencies->moduleDetail($moduleName);
        $blocking = [];
        $warnings = [];

        if (empty($panel['installed'])) {
            $blocking[] = 'Install the module before running an upgrade.';
        }
        foreach ((array)($panel['dependency_errors'] ?? []) as $error) {
            $blocking[] = (string)$error;
        }
        if (!empty($dependency['action_impacts'])) {
            foreach ((array)$dependency['action_impacts'] as $impact) {
                if ((string)($impact['action'] ?? '') === 'update' && (string)($impact['status'] ?? '') === 'blocked') {
                    $blocking[] = (string)($impact['summary'] ?? 'Upgrade is blocked by dependency impact.');
                } elseif ((string)($impact['action'] ?? '') === 'update' && (string)($impact['status'] ?? '') !== 'safe') {
                    $warnings[] = (string)($impact['summary'] ?? 'Upgrade has dependency impact.');
                }
            }
        }
        if (!empty($panel['missing_tables'])) {
            $warnings[] = 'Module is already missing required tables.';
        }
        if (!empty($panel['schema_gaps'])) {
            $warnings[] = 'Module already has schema gaps.';
        }
        if (empty($versioning['upgrade_available'])) {
            $warnings[] = 'Current module version is already aligned with the target version.';
        }
        foreach ($this->migrationWarnings($versioning) as $warning) {
            $warnings[] = $warning;
        }

        return [
            'scope' => 'module',
            'scope_label' => 'Module',
            'target_key' => $moduleName,
            'target_label' => (string)($panel['display_name'] ?? $moduleName),
            'current_version' => (string)($versioning['current_version'] ?? ''),
            'target_version' => (string)($versioning['target_version'] ?? ''),
            'ready' => $blocking === [],
            'blocking_issues' => array_values(array_unique(array_filter($blocking))),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'plan_steps' => [
                'Run pre-upgrade checks for dependency and schema health.',
                'Apply module update/schema sync through the module lifecycle layer.',
                'Re-run module dependency validation and health checks.',
            ],
            'migration_warnings' => $this->migrationWarnings($versioning),
            'failure_recovery_notes' => [
                'If module upgrade fails, use module repair to restore schema and runtime hooks.',
                'Review dependency blockers before retrying the upgrade step.',
            ],
            'changelog_summary' => (string)($versioning['latest_entry']['summary'] ?? ''),
            'verification_summary' => [
                'missing_tables' => count((array)($panel['missing_tables'] ?? [])),
                'failed_hooks' => 0,
                'schema_gaps' => count((array)($panel['schema_gaps'] ?? [])),
                'warnings' => count((array)($panel['missing_tables'] ?? [])) + count((array)($panel['schema_gaps'] ?? [])),
                'errors' => count((array)($panel['dependency_errors'] ?? [])),
            ],
            'dependency_summary' => [
                'status' => (string)($dependency['dependency_health']['status'] ?? 'safe'),
                'summary' => (string)($dependency['dependency_health']['summary'] ?? ''),
            ],
            'latest_run' => $this->state->latestRun('module', $moduleName),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function applyCoreUpgrade(): array
    {
        $preview = $this->previewCore();
        if (empty($preview['ready'])) {
            throw new \RuntimeException('Core upgrade is blocked. Review the preview first.');
        }

        $runId = $this->state->startRun('core', 'core', 'upgrade_assistant', [
            ['key' => 'pre_upgrade_checks', 'label' => 'Pre-upgrade checks'],
            ['key' => 'apply_upgrade', 'label' => 'Apply Core upgrade'],
            ['key' => 'post_upgrade_verify', 'label' => 'Post-upgrade verification'],
        ], ['scope' => 'core', 'preview' => $preview]);

        try {
            $this->state->startStep($runId, 'pre_upgrade_checks');
            $this->state->completeStep($runId, 'pre_upgrade_checks', SetupStateService::STATUS_CONFIGURED, 'Core pre-upgrade checks passed.', (array)($preview['warnings'] ?? []));

            $this->state->startStep($runId, 'apply_upgrade');
            $coreResult = $this->core->repairBootstrap();
            $this->state->completeStep($runId, 'apply_upgrade', SetupStateService::STATUS_CONFIGURED, 'Core upgrade actions completed.');

            $this->state->startStep($runId, 'post_upgrade_verify');
            $verification = $this->verification->verifyAll();
            $warnings = (array)($verification['warnings'] ?? []);
            $status = empty($verification['errors']) ? SetupStateService::STATUS_VERIFIED : SetupStateService::STATUS_PARTIAL;
            $this->state->completeStep($runId, 'post_upgrade_verify', $status, 'Core post-upgrade verification complete.', $warnings, false, empty($verification['errors']) ? '' : 'Review Core verification errors before going live.');

            $result = [
                'scope' => 'core',
                'target_key' => 'core',
                'status' => 'ok',
                'pre_upgrade_preview' => $preview,
                'upgrade_result' => $coreResult,
                'post_upgrade_verification' => $verification,
                'failure_recovery_notes' => $preview['failure_recovery_notes'],
            ];
            $this->state->finalizeRun($runId, $status, $warnings, empty($verification['errors']) ? null : 'Core verification still has errors.', false, empty($verification['errors']) ? '' : 'Use Core recovery or repair bootstrap, then verify again.', 'recover', 'retry', 'repair', $result);
            return $result;
        } catch (\Throwable $e) {
            $this->state->failStep($runId, 'apply_upgrade', $e->getMessage(), [], false, 'Review Core bootstrap and environment checks before retrying.');
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Use Core recovery after fixing the blocking issue.', 'recover', 'retry', 'repair', [
                'scope' => 'core',
                'target_key' => 'core',
                'pre_upgrade_preview' => $preview,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function applySuiteUpgrade(string $suiteKey): array
    {
        $suiteKey = strtolower(trim($suiteKey));
        $preview = $this->previewSuite($suiteKey);
        if (empty($preview['ready'])) {
            throw new \RuntimeException('Suite upgrade is blocked. Review the preview first.');
        }

        $suite = $this->suites->suiteStatus($suiteKey);
        $profileKey = trim((string)($suite['configured_profile'] ?? ($suite['default_profile'] ?? '')));
        $runId = $this->state->startRun('suite', $suiteKey, 'upgrade_assistant', [
            ['key' => 'pre_upgrade_checks', 'label' => 'Pre-upgrade checks'],
            ['key' => 'apply_upgrade', 'label' => 'Apply suite upgrade'],
            ['key' => 'post_upgrade_verify', 'label' => 'Post-upgrade verification'],
        ], ['scope' => 'suite', 'profile' => $profileKey, 'preview' => $preview]);

        try {
            $this->state->startStep($runId, 'pre_upgrade_checks');
            $this->state->completeStep($runId, 'pre_upgrade_checks', SetupStateService::STATUS_CONFIGURED, 'Suite pre-upgrade checks passed.', (array)($preview['warnings'] ?? []));

            $this->state->startStep($runId, 'apply_upgrade');
            $app = (new AppRegistryService())->find($suiteKey);
            if (is_array($app) && in_array((string)($app['status'] ?? ''), [AppRegistryService::STATUS_UPGRADE_PENDING, AppRegistryService::STATUS_UPLOADED], true)) {
                (new AppInstallService())->install($suiteKey);
            }
            $upgradeMessages = [];
            if (is_array($app) && (string)($app['status'] ?? '') === AppRegistryService::STATUS_ENABLED) {
                (new AppLifecycleService())->repair($suiteKey);
                $upgradeMessages[] = 'Suite runtime repaired after package upgrade.';
            }
            $configureResult = $this->suites->configure($suiteKey, $profileKey);
            $upgradeMessages[] = 'Suite configuration refreshed for profile ' . ($profileKey !== '' ? $profileKey : 'default') . '.';
            $this->state->completeStep($runId, 'apply_upgrade', SetupStateService::STATUS_CONFIGURED, implode(' ', $upgradeMessages));

            $this->state->startStep($runId, 'post_upgrade_verify');
            $verification = $this->suites->verify($suiteKey);
            $verificationWarnings = (array)(($verification['verification']['warnings'] ?? []));
            $verificationErrors = (array)(($verification['verification']['errors'] ?? []));
            $status = $verificationErrors === [] ? SetupStateService::STATUS_VERIFIED : SetupStateService::STATUS_PARTIAL;
            $this->state->completeStep($runId, 'post_upgrade_verify', $status, 'Suite post-upgrade verification complete.', $verificationWarnings, false, $verificationErrors === [] ? '' : 'Review suite verification errors and child-module health before continuing.');

            $result = [
                'scope' => 'suite',
                'target_key' => $suiteKey,
                'status' => 'ok',
                'pre_upgrade_preview' => $preview,
                'upgrade_result' => [
                    'configure' => $configureResult,
                    'messages' => $upgradeMessages,
                ],
                'post_upgrade_verification' => $verification,
                'failure_recovery_notes' => $preview['failure_recovery_notes'],
            ];
            $this->state->finalizeRun($runId, $status, $verificationWarnings, $verificationErrors === [] ? null : implode(' ', array_map('strval', $verificationErrors)), false, $verificationErrors === [] ? '' : 'Use suite recovery or module repair, then verify again.', 'recover', 'retry', 'repair', $result);
            return $result;
        } catch (\Throwable $e) {
            $this->state->failStep($runId, 'apply_upgrade', $e->getMessage(), [], false, 'Review suite dependencies, package state, and profile before retrying.');
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Use suite recovery after resolving the failed step.', 'recover', 'retry', 'repair', [
                'scope' => 'suite',
                'target_key' => $suiteKey,
                'pre_upgrade_preview' => $preview,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function applyModuleUpgrade(string $moduleName): array
    {
        $moduleName = trim($moduleName);
        $preview = $this->previewModule($moduleName);
        if (empty($preview['ready'])) {
            throw new \RuntimeException('Module upgrade is blocked. Review the preview first.');
        }

        $runId = $this->state->startRun('module', $moduleName, 'upgrade_assistant', [
            ['key' => 'pre_upgrade_checks', 'label' => 'Pre-upgrade checks'],
            ['key' => 'apply_upgrade', 'label' => 'Apply module upgrade'],
            ['key' => 'post_upgrade_verify', 'label' => 'Post-upgrade verification'],
        ], ['scope' => 'module', 'preview' => $preview]);

        try {
            $this->state->startStep($runId, 'pre_upgrade_checks');
            $this->state->completeStep($runId, 'pre_upgrade_checks', SetupStateService::STATUS_CONFIGURED, 'Module pre-upgrade checks passed.', (array)($preview['warnings'] ?? []));

            $this->state->startStep($runId, 'apply_upgrade');
            $updateResult = $this->modules->run($moduleName, 'update');
            $this->state->completeStep($runId, 'apply_upgrade', SetupStateService::STATUS_CONFIGURED, 'Module update/schema sync completed.');

            $this->state->startStep($runId, 'post_upgrade_verify');
            $validateResult = $this->modules->run($moduleName, 'validate');
            $panel = $this->modulePanel($moduleName);
            $warnings = [];
            if (!empty($panel['missing_tables'])) {
                $warnings[] = 'Module still has missing tables after upgrade.';
            }
            if (!empty($panel['schema_gaps'])) {
                $warnings[] = 'Module still has schema gaps after upgrade.';
            }
            $status = empty($panel['dependency_errors']) ? SetupStateService::STATUS_VERIFIED : SetupStateService::STATUS_PARTIAL;
            $manualAttention = empty($panel['dependency_errors']) ? '' : 'Run module repair after resolving dependency blockers.';
            $this->state->completeStep($runId, 'post_upgrade_verify', $status, 'Module post-upgrade verification complete.', $warnings, false, $manualAttention);

            $result = [
                'scope' => 'module',
                'target_key' => $moduleName,
                'status' => 'ok',
                'pre_upgrade_preview' => $preview,
                'upgrade_result' => $updateResult,
                'post_upgrade_verification' => [
                    'validate' => $validateResult,
                    'health' => $panel,
                ],
                'failure_recovery_notes' => $preview['failure_recovery_notes'],
            ];
            $this->state->finalizeRun($runId, $status, $warnings, empty($panel['dependency_errors']) ? null : 'Module still has dependency errors after upgrade.', false, $manualAttention, 'recover', 'retry', 'repair', $result);
            return $result;
        } catch (\Throwable $e) {
            $this->state->failStep($runId, 'apply_upgrade', $e->getMessage(), [], false, 'Review module dependency and schema state before retrying.');
            $this->state->finalizeRun($runId, SetupStateService::STATUS_FAILED, [], $e->getMessage(), false, 'Use module repair after resolving the failed step.', 'recover', 'retry', 'repair', [
                'scope' => 'module',
                'target_key' => $moduleName,
                'pre_upgrade_preview' => $preview,
            ]);
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $versioning
     * @return array<int,string>
     */
    private function migrationWarnings(array $versioning): array
    {
        $latest = is_array($versioning['latest_entry'] ?? null) ? $versioning['latest_entry'] : [];
        return array_values(array_unique(array_filter(array_map('strval', array_merge(
            (array)($latest['breaking_changes'] ?? []),
            (array)($latest['migration_notes'] ?? []),
            (array)($latest['setup_upgrade_notes'] ?? [])
        )))));
    }

    /**
     * @param array<string,mixed> $verification
     * @return array<string,int>
     */
    private function verificationCounts(array $verification): array
    {
        return [
            'missing_tables' => count((array)($verification['missing_tables'] ?? [])),
            'failed_hooks' => count((array)($verification['failed_hooks'] ?? [])),
            'schema_gaps' => count((array)($verification['schema_gaps'] ?? [])),
            'warnings' => count((array)($verification['warnings'] ?? [])),
            'errors' => count((array)($verification['errors'] ?? [])),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function modulePanel(string $moduleName): array
    {
        foreach ($this->modules->panelRows() as $panel) {
            if ((string)($panel['name'] ?? '') === $moduleName) {
                return $panel;
            }
        }
        throw new \RuntimeException('Unknown module: ' . $moduleName);
    }
}
