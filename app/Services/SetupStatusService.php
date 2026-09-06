<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class SetupStatusService
{
    public const PUBLIC_WIZARD_SESSION_KEY = 'setup_public_wizard';

    /**
     * @var array<int,string>
     */
    private const STEP_ORDER = ['welcome', 'readiness', 'database', 'core_install', 'admin', 'setup_2fa', 'verify'];

    /**
     * @var array<string,array<string,string>>
     */
    private const STEP_META = [
        'welcome' => ['label' => 'Welcome', 'short' => 'Welcome', 'path' => '/setup?step=welcome'],
        'readiness' => ['label' => 'Readiness', 'short' => 'Readiness', 'path' => '/setup?step=readiness'],
        'database' => ['label' => 'Database', 'short' => 'Database', 'path' => '/setup?step=database'],
        'core_install' => ['label' => 'Provisioning', 'short' => 'Provisioning', 'path' => '/setup?step=core_install'],
        'admin' => ['label' => 'Admin', 'short' => 'Admin', 'path' => '/setup?step=admin'],
        'setup_2fa' => ['label' => '2FA', 'short' => '2FA', 'path' => '/setup?step=setup_2fa'],
        'verify' => ['label' => 'Verify', 'short' => 'Verify', 'path' => '/setup?step=verify'],
    ];

    /**
     * @return array<string,mixed>
     */
    public static function publicSnapshot(array $context = []): array
    {
        Auth::bootSession();

        $wizard = self::wizardState($context);
        $wizardData = is_array($wizard['data'] ?? null) ? (array)$wizard['data'] : [];
        $readiness = is_array($wizard['readiness'] ?? null) ? (array)$wizard['readiness'] : [];
        $readinessChecks = array_values((array)($readiness['checks'] ?? []));
        $dbTest = is_array($wizard['db_test'] ?? null) ? (array)$wizard['db_test'] : [];
        $installRun = is_array($wizard['install_run'] ?? null) ? (array)$wizard['install_run'] : [];
        if ($installRun === []) {
            $installRun = self::latestCoreRun();
        }

        $mode = strtolower(trim((string)($context['mode'] ?? 'wizard')));
        $currentStageKey = self::normalizeStageKey((string)($context['current_step'] ?? (string)($wizard['current_step'] ?? '')));
        $csrf = trim((string)($context['csrf'] ?? Auth::csrfToken()));
        $loginUrl = trim((string)($context['login_url'] ?? '/login'));
        $currentPageUrl = trim((string)($context['current_page_url'] ?? self::currentPageUrl()));
        $error = trim((string)($context['error'] ?? (string)($wizard['error'] ?? '')));
        $notice = trim((string)($context['notice'] ?? (string)($wizard['notice'] ?? '')));
        $pendingTwoFa = self::pendingTwoFa();
        $adminState = self::adminBootstrapState();
        $hasAdmin = !empty($adminState['exists']);
        $adminTwoFaEnabled = !empty($adminState['twofa_enabled']);
        $loggedIn = Auth::isLoggedIn();
        $corePlatformReady = !array_key_exists('core_platform_ready', $context) || !empty($context['core_platform_ready']);
        $started = self::setupStarted($wizard, $installRun, $pendingTwoFa, $hasAdmin, $adminTwoFaEnabled);

        $readinessFailed = count(array_filter($readinessChecks, static fn(array $row): bool => !empty($row['required']) && (string)($row['status'] ?? '') === 'failed'));
        $readinessWarnings = count(array_filter($readinessChecks, static fn(array $row): bool => (string)($row['status'] ?? '') === 'warning'));
        $readinessPassed = self::readinessPassed($readinessChecks);
        $dbValidated = !empty($dbTest['ok']);
        $dbTouched = trim((string)($wizardData['db_host'] ?? '')) !== ''
            || trim((string)($wizardData['db_name'] ?? '')) !== ''
            || trim((string)($wizardData['db_user'] ?? '')) !== '';
        $platformValid = !empty($wizard['platform_validated']);
        $installStatus = strtolower(trim((string)($installRun['status'] ?? '')));
        $installSucceeded = in_array($installStatus, ['configured', 'verified'], true);
        $adminFormValid = self::adminFormValid($wizardData) || $installSucceeded;
        $installFailed = $installStatus === 'failed';
        $installWarning = in_array($installStatus, ['partial', 'rolled_back'], true);
        $twoFaConfigured = !empty($wizardData['two_fa_configured']) || ($hasAdmin && $adminTwoFaEnabled && !$pendingTwoFa);
        $verificationReady = $installSucceeded && $corePlatformReady;

        $progressStep = self::resolveProgressStep([
            'started' => $started,
            'readiness_passed' => $readinessPassed,
            'db_validated' => $dbValidated,
            'platform_valid' => $platformValid,
            'admin_form_valid' => $adminFormValid,
            'install_succeeded' => $installSucceeded,
            'two_fa_configured' => $twoFaConfigured,
            'pending_two_fa' => $pendingTwoFa,
            'has_admin' => $hasAdmin,
            'admin_two_fa_enabled' => $adminTwoFaEnabled,
            'core_platform_ready' => $corePlatformReady,
            'current_stage_key' => $currentStageKey,
        ]);

        $currentStageLabel = self::stageLabel($currentStageKey, $progressStep, $mode);
        [$overallKey, $overallLabel, $overallTone] = self::overallStatus([
            'started' => $started,
            'readiness_failed' => $readinessFailed,
            'install_failed' => $installFailed,
            'install_warning' => $installWarning,
            'verification_ready' => $verificationReady,
            'pending_two_fa' => $pendingTwoFa,
            'has_admin' => $hasAdmin,
            'core_platform_ready' => $corePlatformReady,
        ]);

        $stepRows = self::stepRows([
            'progress_step' => $progressStep,
            'current_stage_key' => $currentStageKey,
            'readiness_failed' => $readinessFailed,
            'readiness_warnings' => $readinessWarnings,
            'db_validated' => $dbValidated,
            'db_touched' => $dbTouched,
            'platform_valid' => $platformValid,
            'admin_form_valid' => $adminFormValid,
            'install_failed' => $installFailed,
            'install_warning' => $installWarning,
            'two_fa_configured' => $twoFaConfigured,
            'verification_ready' => $verificationReady,
            'pending_two_fa' => $pendingTwoFa,
            'mode' => $mode,
        ]);

        $alert = self::alertPayload([
            'mode' => $mode,
            'current_stage_key' => $currentStageKey,
            'progress_step' => $progressStep,
            'error' => $error,
            'notice' => $notice,
            'readiness_passed' => $readinessPassed,
            'readiness_failed' => $readinessFailed,
            'readiness_warnings' => $readinessWarnings,
            'db_validated' => $dbValidated,
            'db_touched' => $dbTouched,
            'platform_valid' => $platformValid,
            'admin_form_valid' => $adminFormValid,
            'install_failed' => $installFailed,
            'install_warning' => $installWarning,
            'install_run' => $installRun,
            'pending_two_fa' => $pendingTwoFa,
            'two_fa_configured' => $twoFaConfigured,
            'verification_ready' => $verificationReady,
            'core_platform_ready' => $corePlatformReady,
            'logged_in' => $loggedIn,
        ]);

        $quickActions = self::quickActions([
            'mode' => $mode,
            'csrf' => $csrf,
            'current_page_url' => $currentPageUrl,
            'current_stage_key' => $currentStageKey,
            'progress_step' => $progressStep,
            'readiness_failed' => $readinessFailed,
            'db_validated' => $dbValidated,
            'platform_valid' => $platformValid,
            'admin_form_valid' => $adminFormValid,
            'install_failed' => $installFailed,
            'install_warning' => $installWarning,
            'install_submit_token' => trim((string)($wizard['install_submit_token'] ?? '')),
            'pending_two_fa' => $pendingTwoFa,
            'two_fa_configured' => $twoFaConfigured,
            'verification_ready' => $verificationReady,
            'logged_in' => $loggedIn,
            'login_url' => $loginUrl,
        ]);

        return [
            'overall_status' => [
                'key' => $overallKey,
                'label' => $overallLabel,
                'tone' => $overallTone,
            ],
            'current_stage' => [
                'key' => $currentStageKey,
                'label' => $currentStageLabel,
            ],
            'progress_step' => $progressStep,
            'step_states' => $stepRows,
            'current_alert' => $alert,
            'recommended_action' => (string)($alert['recommendation'] ?? ''),
            'quick_actions' => $quickActions,
            'meta' => self::metaSummary($loggedIn, $pendingTwoFa, $hasAdmin, $adminTwoFaEnabled),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function wizardState(array $context): array
    {
        $wizard = $context['wizard'] ?? ($_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] ?? []);
        if (!is_array($wizard)) {
            return [];
        }

        $data = is_array($wizard['data'] ?? null) ? (array)$wizard['data'] : [];
        unset($data['db_pass'], $data['admin_password'], $data['admin_password_confirm']);
        $wizard['data'] = $data;
        unset($wizard['db_pass'], $wizard['admin_password'], $wizard['admin_password_confirm']);

        return $wizard;
    }

    private static function pendingTwoFa(): bool
    {
        return trim((string)($_SESSION['setup_email'] ?? '')) !== ''
            && trim((string)($_SESSION['setup_secret'] ?? '')) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private static function adminBootstrapState(): array
    {
        try {
            $hasAccountType = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'authority_role'") !== null;
            $adminWhere = "LOWER(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(role, '')), ' ', ''), '-', ''), '_', '')) IN ('admin', 'itadmin', 'itadministrator', 'sysadmin', 'systemadmin', 'systemadministrator')";
            if ($hasAccountType) {
                $adminWhere = "COALESCE(NULLIF(TRIM(authority_role), ''), '') = 'platform_admin' OR {$adminWhere}";
            }

            $row = DB::fetchOne("SELECT id, email, twofa_enabled FROM users WHERE {$adminWhere} ORDER BY id ASC LIMIT 1");
            if (!is_array($row)) {
                return ['exists' => false, 'email' => '', 'twofa_enabled' => false];
            }

            return [
                'exists' => true,
                'email' => (string)($row['email'] ?? ''),
                'twofa_enabled' => !empty($row['twofa_enabled']),
            ];
        } catch (\Throwable) {
            return ['exists' => false, 'email' => '', 'twofa_enabled' => false];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function latestCoreRun(): array
    {
        try {
            $run = (new SetupStateService())->latestRun('core', 'core');
            return is_array($run) ? $run : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $wizard
     * @param array<string,mixed> $installRun
     */
    private static function setupStarted(array $wizard, array $installRun, bool $pendingTwoFa, bool $hasAdmin, bool $adminTwoFaEnabled): bool
    {
        if (!empty($wizard['started'])) {
            return true;
        }

        if ($pendingTwoFa || !empty($wizard['platform_validated']) || !empty($wizard['db_test']['ok']) || !empty($wizard['data']['two_fa_configured'])) {
            return true;
        }

        if (trim((string)($installRun['status'] ?? '')) !== '') {
            return true;
        }

        return $hasAdmin && $adminTwoFaEnabled;
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private static function readinessPassed(array $checks): bool
    {
        $requiredSeen = false;
        foreach ($checks as $check) {
            if (empty($check['required'])) {
                continue;
            }

            $requiredSeen = true;
            if ((string)($check['status'] ?? '') === 'failed') {
                return false;
            }
        }

        return $requiredSeen;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function adminFormValid(array $data): bool
    {
        $email = strtolower(trim((string)($data['admin_email'] ?? '')));

        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function resolveProgressStep(array $state): string
    {
        $currentStageKey = (string)($state['current_stage_key'] ?? '');
        if (self::isCanonicalStep($currentStageKey)) {
            return $currentStageKey;
        }

        if (empty($state['started']) && !empty($state['has_admin'])) {
            return 'verify';
        }

        if (empty($state['started']) && empty($state['pending_two_fa']) && empty($state['has_admin'])) {
            return 'welcome';
        }
        if (!empty($state['verification_ready'])) {
            return 'verify';
        }
        if (empty($state['readiness_passed'])) {
            return 'readiness';
        }
        if (empty($state['db_validated'])) {
            return 'database';
        }
        if (empty($state['platform_valid'])) {
            return 'core_install';
        }
        if (empty($state['admin_form_valid']) && empty($state['install_succeeded'])) {
            return 'admin';
        }
        if (empty($state['install_succeeded'])) {
            return 'admin';
        }
        return 'verify';
    }

    /**
     * @param array<string,mixed> $state
     * @return array{0:string,1:string,2:string}
     */
    private static function overallStatus(array $state): array
    {
        if (!empty($state['readiness_failed']) || !empty($state['install_failed']) || !empty($state['install_warning'])) {
            return ['blocked', self::tr('setup.overall.blocked', 'Setup Blocked'), 'danger'];
        }

        if (!empty($state['verification_ready'])) {
            return ['ready', self::tr('setup.overall.ready', 'Ready for Verification'), 'success'];
        }

        if (!empty($state['started']) || !empty($state['pending_two_fa']) || !empty($state['has_admin'])) {
            return ['in_progress', self::tr('setup.overall.in_progress', 'Setup In Progress'), 'info'];
        }

        return ['incomplete', self::tr('setup.overall.incomplete', 'Setup Incomplete'), 'neutral'];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<int,array<string,mixed>>
     */
    private static function stepRows(array $state): array
    {
        $rows = [];
        $progressStep = self::normalizeStep((string)($state['progress_step'] ?? 'welcome'));
        $progressIndex = self::stepIndex($progressStep);
        $currentStageKey = self::normalizeStageKey((string)($state['current_stage_key'] ?? $progressStep));
        $activeStep = self::isCanonicalStep($currentStageKey) ? $currentStageKey : $progressStep;

        foreach (self::STEP_ORDER as $index => $stepKey) {
            $stateKey = 'pending';
            $completed = $index < $progressIndex;

            if ($completed) {
                $stateKey = 'completed';
            } elseif ($stepKey === 'readiness' && !empty($state['readiness_failed'])) {
                $stateKey = 'failed';
            } elseif ($stepKey === 'readiness' && !empty($state['readiness_warnings']) && $activeStep === 'readiness') {
                $stateKey = 'warning';
            } elseif ($stepKey === 'database' && $activeStep === 'database' && !empty($state['db_touched']) && empty($state['db_validated'])) {
                $stateKey = 'warning';
            } elseif ($stepKey === 'core_install' && $activeStep === 'core_install' && empty($state['platform_valid'])) {
                $stateKey = 'current';
            } elseif ($stepKey === 'admin' && !empty($state['install_failed'])) {
                $stateKey = 'failed';
            } elseif ($stepKey === 'admin' && !empty($state['install_warning'])) {
                $stateKey = 'warning';
            } elseif ($stepKey === 'setup_2fa' && $activeStep === 'setup_2fa' && empty($state['two_fa_configured']) && !empty($state['pending_two_fa'])) {
                $stateKey = 'current';
            } elseif ($stepKey === $progressStep) {
                $stateKey = 'current';
            } elseif ($index > $progressIndex) {
                $stateKey = in_array($stepKey, ['verify'], true) && !empty($state['verification_ready']) ? 'pending' : 'blocked';
            }

            $href = '';
            if (in_array($stateKey, ['completed', 'current', 'warning', 'failed'], true)) {
                $href = self::stepPath($stepKey, (string)($state['mode'] ?? '') === 'standalone_2fa');
            }

            $rows[] = [
                'key' => $stepKey,
                'label' => self::stepShortLabel($stepKey),
                'full_label' => self::stepLabel($stepKey),
                'marker' => (string)($index + 1),
                'state' => $stateKey,
                'is_current' => $stepKey === $activeStep,
                'href' => $href,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    private static function alertPayload(array $state): array
    {
        $currentStageKey = (string)($state['current_stage_key'] ?? '');
        $progressStep = (string)($state['progress_step'] ?? 'welcome');
        $error = trim((string)($state['error'] ?? ''));
        $notice = trim((string)($state['notice'] ?? ''));

        if ((string)($state['mode'] ?? '') === 'resume' && empty($state['logged_in'])) {
            return [
                'tone' => 'warning',
                'title' => self::tr('setup.alert.resume_login_required.title', 'Administrator sign-in required'),
                'summary' => self::tr('setup.alert.resume_login_required.summary', 'Sign in to continue setup safely from the correct recovery point.'),
                'recommendation' => self::tr('setup.alert.resume_login_required.recommendation', 'Use the recovery login action, then continue from the stage the platform reports as incomplete.'),
            ];
        }

        if ($error !== '') {
            $recommendation = $currentStageKey === 'setup_2fa'
                ? self::tr('setup.alert.error.two_fa_recommendation', 'Enter the current 6-digit code from your authenticator app and submit again.')
                : self::tr('setup.alert.error.default_recommendation', 'Review the active setup stage, correct the issue, and continue from there.');

            return [
                'tone' => 'danger',
                'title' => self::tr('setup.alert.error.title', 'Action needed'),
                'summary' => $error,
                'recommendation' => $recommendation,
            ];
        }

        if (!empty($state['readiness_failed'])) {
            return [
                'tone' => 'danger',
                'title' => self::tr('setup.alert.readiness_failed.title', 'Readiness checks are blocking setup'),
                'summary' => self::tr('setup.alert.readiness_failed.summary', 'Required environment checks are still failing.'),
                'recommendation' => self::tr('setup.alert.readiness_failed.recommendation', 'Fix the failing readiness items, then run the checks again before moving forward.'),
            ];
        }

        if ($currentStageKey === 'readiness' && !empty($state['readiness_passed'])) {
            return [
                'tone' => 'info',
                'title' => self::tr('setup.alert.readiness_passed.title', 'Environment checks passed'),
                'summary' => self::tr('setup.alert.readiness_passed.summary', 'Server requirements are satisfied, but installation has not started yet.'),
                'recommendation' => self::tr('setup.alert.readiness_passed.recommendation', 'Continue to database setup next. Database configuration is still required before installation can proceed.'),
            ];
        }

        if (!empty($state['install_failed']) || !empty($state['install_warning'])) {
            $summary = trim((string)(($state['install_run']['failed_step']['error_text'] ?? '') ?: ($state['install_run']['error_text'] ?? '')));
            if ($summary === '') {
                $summary = !empty($state['install_warning'])
                    ? self::tr('setup.alert.install_warning.summary', 'Provisioning did not finish cleanly and needs review before setup can continue.')
                    : self::tr('setup.alert.install_failed.summary', 'Provisioning failed before completion and needs a retry after the underlying issue is fixed.');
            }

            return [
                'tone' => !empty($state['install_warning']) ? 'warning' : 'danger',
                'title' => !empty($state['install_warning'])
                    ? self::tr('setup.alert.install_warning.title', 'Provisioning partially failed')
                    : self::tr('setup.alert.install_failed.title', 'Provisioning failed'),
                'summary' => $summary,
                'recommendation' => self::tr('setup.alert.install_failed.recommendation', 'Open the admin stage, review the failed task, and retry provisioning when ready.'),
            ];
        }

        if ($progressStep === 'database' && empty($state['db_validated'])) {
            return [
                'tone' => 'warning',
                'title' => self::tr('setup.alert.database_pending.title', 'Database verification pending'),
                'summary' => !empty($state['db_touched'])
                    ? self::tr('setup.alert.database_pending.summary_changed', 'Connection details changed or still need a successful test.')
                    : self::tr('setup.alert.database_pending.summary_default', 'Database configuration has not been verified yet.'),
                'recommendation' => self::tr('setup.alert.database_pending.recommendation', 'Test and save the database connection before continuing to provisioning.'),
            ];
        }

        if ($progressStep === 'core_install' && empty($state['platform_valid'])) {
            return [
                'tone' => 'info',
                'title' => self::tr('setup.alert.provision_plan.title', 'Provisioning plan needs confirmation'),
                'summary' => self::tr('setup.alert.provision_plan.summary', 'Platform profile, landing route, or defaults are still incomplete.'),
                'recommendation' => self::tr('setup.alert.provision_plan.recommendation', 'Complete the provisioning choices, then continue to the admin bootstrap stage.'),
            ];
        }

        if ($progressStep === 'admin' && empty($state['admin_form_valid'])) {
            return [
                'tone' => 'info',
                'title' => self::tr('setup.alert.admin_waiting.title', 'Administrator bootstrap is waiting'),
                'summary' => self::tr('setup.alert.admin_waiting.summary', 'The first administrator account details are not complete yet.'),
                'recommendation' => self::tr('setup.alert.admin_waiting.recommendation', 'Enter a valid admin email and matching password to start provisioning.'),
            ];
        }

        if (!empty($state['pending_two_fa']) || ($currentStageKey === 'setup_2fa' && empty($state['two_fa_configured']))) {
            return [
                'tone' => 'warning',
                'title' => self::tr('setup.alert.two_fa_pending.title', '2FA pending'),
                'summary' => self::tr('setup.alert.two_fa_pending.summary', 'The administrator account still needs authenticator verification.'),
                'recommendation' => self::tr('setup.alert.two_fa_pending.recommendation', 'Complete 2FA to unlock the final verification stage.'),
            ];
        }

        if ($currentStageKey === 'verify' && empty($state['core_platform_ready'])) {
            return [
                'tone' => 'warning',
                'title' => self::tr('setup.alert.core_verify_pending.title', 'Core verification still needs attention'),
                'summary' => self::tr('setup.alert.core_verify_pending.summary', 'One or more Core / Platform Apps are not fully healthy yet.'),
                'recommendation' => self::tr('setup.alert.core_verify_pending.recommendation', 'Run Core repair and confirm every required Core / Platform App is installed, active, schema-synced, and healthy.'),
            ];
        }

        if (!empty($state['verification_ready'])) {
            return [
                'tone' => 'success',
                'title' => self::tr('setup.alert.verification_ready.title', 'Verification is ready'),
                'summary' => self::tr('setup.alert.verification_ready.summary', 'Core provisioning, admin bootstrap, and 2FA are complete.'),
                'recommendation' => self::tr('setup.alert.verification_ready.recommendation', 'Open the Verify step, confirm the final checks, and land in the platform.'),
            ];
        }

        if ($notice !== '') {
            return [
                'tone' => 'info',
                'title' => self::tr('setup.alert.notice.title', 'Setup update'),
                'summary' => $notice,
                'recommendation' => self::tr('setup.alert.notice.recommendation', 'Continue with the highlighted stage.'),
            ];
        }

        return [
            'tone' => 'info',
            'title' => self::tr('setup.alert.not_started.title', 'Bootstrap not started'),
            'summary' => self::tr('setup.alert.not_started.summary', 'Start the guided setup to move from readiness through provisioning and verification.'),
            'recommendation' => self::tr('setup.alert.not_started.recommendation', 'Begin with the Welcome action, then follow the highlighted stages in order.'),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<int,array<string,mixed>>
     */
    private static function quickActions(array $state): array
    {
        $actions = [];
        $csrf = trim((string)($state['csrf'] ?? ''));
        $currentPageUrl = trim((string)($state['current_page_url'] ?? '/setup'));
        $progressStep = self::normalizeStep((string)($state['progress_step'] ?? 'welcome'));
        $currentStageKey = self::normalizeStageKey((string)($state['current_stage_key'] ?? $progressStep));

        if ((string)($state['mode'] ?? '') === 'resume' && empty($state['logged_in'])) {
            $actions[] = self::linkAction(self::tr('setup.action.login_to_continue', 'Login to Continue'), (string)($state['login_url'] ?? '/login'), 'ok');
            $actions[] = self::linkAction(self::tr('setup.action.setup_home', 'Setup Home'), '/setup', 'default');
            return self::dedupeActions($actions);
        }

        if ((string)($state['mode'] ?? '') === 'initial') {
            $actions[] = self::linkAction(self::tr('setup.action.create_admin_below', 'Create Admin Below'), '#initial-admin-setup', 'ok');
            $actions[] = self::linkAction(self::tr('common.login', 'Login'), (string)($state['login_url'] ?? '/login'), 'default');
            return self::dedupeActions($actions);
        }

        if ($currentStageKey === 'welcome' && $progressStep === 'welcome') {
            $actions[] = self::postAction(self::tr('setup.action.start', 'Start Setup'), '/setup', 'ok', [
                'csrf' => $csrf,
                'wizard_action' => 'start_setup',
            ]);
            return self::dedupeActions($actions);
        }

        if (!empty($state['readiness_failed']) && self::isWizardPage($currentPageUrl)) {
            $actions[] = self::postAction(self::tr('setup.action.run_readiness_again', 'Run Readiness Again'), '/setup', 'default', [
                'csrf' => $csrf,
                'wizard_action' => 'run_readiness',
            ]);

            if ($currentStageKey !== 'readiness') {
                $actions[] = self::linkAction(self::tr('setup.action.open_readiness', 'Open Readiness'), '/setup?step=readiness', 'ok');
            }

            return self::dedupeActions($actions);
        }

        if ($currentStageKey === 'readiness' && !empty($state['readiness_passed']) && self::isWizardPage($currentPageUrl)) {
            $actions[] = self::postAction(self::tr('setup.action.continue_to_database', 'Continue to Database Setup'), '/setup', 'ok', [
                'csrf' => $csrf,
                'wizard_action' => 'continue_readiness',
            ]);
            $actions[] = self::linkAction(self::tr('setup.action.open_database', 'Open Database Setup'), '/setup?step=database', 'default');
            return self::dedupeActions($actions);
        }

        if ((!empty($state['install_failed']) || !empty($state['install_warning'])) && self::isWizardPage($currentPageUrl)) {
            $installSubmitToken = trim((string)($state['install_submit_token'] ?? ''));
            if ($installSubmitToken !== '') {
                $actions[] = self::postAction(self::tr('setup.action.retry_provisioning', 'Retry Provisioning'), '/setup', 'ok', [
                    'csrf' => $csrf,
                    'wizard_action' => 'retry_install',
                    'install_submit_token' => $installSubmitToken,
                ]);
            }
            $actions[] = self::linkAction(self::tr('setup.action.open_admin', 'Open Admin'), '/setup?step=admin', 'default');
            return self::dedupeActions($actions);
        }

        if (!empty($state['pending_two_fa']) || $currentStageKey === 'setup_2fa') {
            $actions[] = self::linkAction(self::tr('setup.action.complete_two_fa', 'Complete 2FA'), '/setup/2fa', 'ok');
            if (self::isWizardPage($currentPageUrl)) {
                $actions[] = self::linkAction(self::tr('setup.action.open_verify', 'Open Verify'), '/setup?step=verify', 'default');
            }
            return self::dedupeActions($actions);
        }

        if (!empty($state['verification_ready'])) {
            $actions[] = self::linkAction(self::tr('setup.action.open_verify', 'Open Verify'), '/setup?step=verify', 'ok');
            if (!empty($state['logged_in'])) {
                $actions[] = self::linkAction(self::tr('setup.action.open_my_work', 'Open Home'), '/', 'default');
            }
            return self::dedupeActions($actions);
        }

        $actions[] = self::linkAction(
            self::tr('setup.action.open_stage', 'Open {stage}', ['stage' => self::stepLabel($progressStep)]),
            self::stepPath($progressStep, false),
            'ok'
        );
        if (!empty($state['logged_in'])) {
            $actions[] = self::linkAction(self::tr('setup.action.open_my_work', 'Open Home'), '/', 'default');
        }

        return self::dedupeActions($actions);
    }

    /**
     * @return array<string,mixed>
     */
    private static function linkAction(string $label, string $href, string $tone = 'default'): array
    {
        return [
            'type' => 'link',
            'label' => $label,
            'href' => $href,
            'tone' => $tone,
        ];
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private static function postAction(string $label, string $action, string $tone, array $fields): array
    {
        return [
            'type' => 'post',
            'label' => $label,
            'action' => $action,
            'tone' => $tone,
            'fields' => $fields,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     * @return array<int,array<string,mixed>>
     */
    private static function dedupeActions(array $actions): array
    {
        $seen = [];
        $deduped = [];
        foreach ($actions as $action) {
            $signature = strtolower(trim((string)($action['type'] ?? ''))) . '|' . trim((string)($action['label'] ?? '')) . '|' . trim((string)($action['href'] ?? ($action['action'] ?? '')));
            if ($signature === '||' || isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $deduped[] = $action;
        }

        return array_slice($deduped, 0, 3);
    }

    /**
     * @return array<int,string>
     */
    private static function metaSummary(bool $loggedIn, bool $pendingTwoFa, bool $hasAdmin, bool $adminTwoFaEnabled): array
    {
        $summary = [$loggedIn
            ? self::tr('setup.meta.authenticated', 'Authenticated')
            : self::tr('setup.meta.guest', 'Guest')];
        if ($pendingTwoFa) {
            $summary[] = self::tr('setup.meta.two_fa_pending', '2FA Session Pending');
        } elseif ($hasAdmin && $adminTwoFaEnabled) {
            $summary[] = self::tr('setup.meta.admin_security_ready', 'Admin Security Ready');
        } elseif ($hasAdmin) {
            $summary[] = self::tr('setup.meta.admin_detected', 'Admin Account Detected');
        } else {
            $summary[] = self::tr('setup.meta.admin_missing', 'Admin Not Created');
        }

        return $summary;
    }

    private static function stageLabel(string $currentStageKey, string $progressStep, string $mode): string
    {
        if ($currentStageKey === 'resume') {
            return self::tr('setup.stage.recovery', 'Recovery');
        }

        $key = self::isCanonicalStep($currentStageKey) ? $currentStageKey : $progressStep;
        return self::stepLabel($key);
    }

    private static function stepPath(string $stepKey, bool $preferStandaloneTwoFa): string
    {
        if ($stepKey === 'setup_2fa' && $preferStandaloneTwoFa) {
            return '/setup/2fa';
        }

        return self::STEP_META[$stepKey]['path'] ?? '/setup';
    }

    private static function normalizeStageKey(string $step): string
    {
        $step = strtolower(trim($step));
        if ($step === 'resume' || $step === 'recovery') {
            return 'resume';
        }

        return self::normalizeStep($step);
    }

    private static function normalizeStep(string $step): string
    {
        $step = strtolower(trim($step));
        $aliases = [
            'platform' => 'core_install',
            'provision' => 'core_install',
            'admin_2fa' => 'setup_2fa',
            'finish' => 'verify',
        ];

        $step = $aliases[$step] ?? $step;
        return self::isCanonicalStep($step) ? $step : 'welcome';
    }

    private static function isCanonicalStep(string $step): bool
    {
        return in_array($step, self::STEP_ORDER, true);
    }

    private static function stepIndex(string $step): int
    {
        $index = array_search(self::normalizeStep($step), self::STEP_ORDER, true);
        return $index === false ? 0 : (int)$index;
    }

    private static function currentPageUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/setup');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/setup';
        $query = trim((string)(parse_url($uri, PHP_URL_QUERY) ?? ''));
        return $query !== '' ? $path . '?' . $query : $path;
    }

    private static function isWizardPage(string $currentPageUrl): bool
    {
        return str_starts_with($currentPageUrl, '/setup');
    }

    private static function stepLabel(string $stepKey): string
    {
        $stepKey = self::normalizeStep($stepKey);
        return self::tr('setup.step.' . $stepKey, self::STEP_META[$stepKey]['label'] ?? ucfirst(str_replace('_', ' ', $stepKey)));
    }

    private static function stepShortLabel(string $stepKey): string
    {
        $stepKey = self::normalizeStep($stepKey);
        return self::tr('setup.step_short.' . $stepKey, self::STEP_META[$stepKey]['short'] ?? self::stepLabel($stepKey));
    }

    /**
     * @param array<string,scalar> $params
     */
    private static function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        if ($params === []) {
            return $fallback;
        }

        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }

        return strtr($fallback, $replace);
    }
}
