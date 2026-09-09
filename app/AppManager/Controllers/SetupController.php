<?php
declare(strict_types=1);

namespace App\AppManager\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\CompanySettingsService;
use App\Services\CoreSetupService;
use App\Services\ConfigManagementService;
use App\Services\DependencyGraphService;
use App\Services\EnvironmentCloneService;
use App\Services\EnvironmentPortabilityHistoryService;
use App\Services\EnvironmentSnapshotService;
use App\Services\HealthDashboardService;
use App\Services\InstallVerificationService;
use App\Services\ModuleLifecycleService;
use App\Services\OrganizationStatusService;
use App\Services\DeploymentReadinessService;
use App\Services\ReleaseHistoryService;
use App\Services\ReleasePackagingService;
use App\Services\ScaffoldGeneratorService;
use App\Services\SetupProfileService;
use App\Services\SetupStatusService;
use App\Services\SetupStateService;
use App\Services\TwoFactorQrService;
use App\Services\SuiteSetupService;
use App\Services\UpgradeAssistantService;

final class SetupController
{
    private const PUBLIC_WIZARD_SESSION_KEY = 'setup_public_wizard';
    private const PUBLIC_INSTALL_LOCK_TTL = 900;
    private const PUBLIC_WIZARD_NOTICE_STEP_REDIRECT = 'step_redirect';
    private const PUBLIC_WIZARD_SECRET_FIELDS = ['db_pass', 'admin_password', 'admin_password_confirm'];
    private const PUBLIC_WIZARD_DB_FIELDS = ['db_host', 'db_port', 'db_name', 'db_user', 'db_charset', 'db_create_if_missing'];
    private const PUBLIC_WIZARD_PLATFORM_FIELDS = ['instance_name', 'language', 'number_format', 'currency', 'timezone', 'theme', 'platform_profile', 'workspace_home'];

    /**
     * @var array<int,string>
     */
    private const PUBLIC_WIZARD_STEPS = ['welcome', 'readiness', 'database', 'core_install', 'admin', 'setup_2fa', 'verify'];

    private const STEP_LABELS = [
        'welcome' => 'Welcome',
        'readiness' => 'Readiness',
        'database' => 'Database',
        'core_install' => 'Core Install',
        'admin' => 'Admin',
        'setup_2fa' => '2FA Setup',
        'verify' => 'Verify',
    ];

    private const STEP_META = [
        'welcome' => ['eyebrow' => 'First Run Setup', 'title' => 'Set up '],
        'readiness' => ['eyebrow' => 'Step 2', 'title' => 'Check server readiness'],
        'database' => ['eyebrow' => 'Step 3', 'title' => 'Connect your database'],
        'core_install' => ['eyebrow' => 'Step 4', 'title' => 'Install core platform'],
        'admin' => ['eyebrow' => 'Step 5', 'title' => 'Create platform admin'],
        'setup_2fa' => ['eyebrow' => 'Step 6', 'title' => 'Secure admin account'],
        'verify' => ['eyebrow' => 'Step 7', 'title' => 'Verify installation'],
    ];

    private const STATUS_META = [
        'not_configured' => ['label' => 'Not Configured', 'tone' => 'color:#d7d7d7'],
        'needs_setup' => ['label' => 'Needs Setup', 'tone' => 'color:#ffd27d'],
        'not_installed' => ['label' => 'Not installed', 'tone' => 'color:#d7d7d7'],
        'installed' => ['label' => 'Installed', 'tone' => 'color:#ffd27d'],
        'configured' => ['label' => 'Configured', 'tone' => 'color:#8ec1ff'],
        'verified' => ['label' => 'Verified', 'tone' => 'color:#6df2a6'],
        'warning' => ['label' => 'Needs Review', 'tone' => 'color:#ffd27d'],
        'pending' => ['label' => 'Pending', 'tone' => 'color:#d7d7d7'],
        'running' => ['label' => 'Running', 'tone' => 'color:#8ec1ff'],
        'failed' => ['label' => 'Failed', 'tone' => 'color:#ff9b9b'],
        'rolled_back' => ['label' => 'Rolled Back', 'tone' => 'color:#ffd27d'],
        'partial' => ['label' => 'Partial', 'tone' => 'color:#ffd27d'],
        'created' => ['label' => 'Created', 'tone' => 'color:#6df2a6'],
        'previewed' => ['label' => 'Previewed', 'tone' => 'color:#8ec1ff'],
        'applied' => ['label' => 'Applied', 'tone' => 'color:#6df2a6'],
    ];

    public static function handlePublic(View $view): void
    {
        Auth::bootSession();
        $core = new CoreSetupService();
        $state = self::publicWizardState($core);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
            $action = self::resolvePublicWizardAction($_POST);
            if ($action !== 'back') {
                $previousData = (array)($state['data'] ?? []);
                $state['data'] = self::publicWizardMergeInput($previousData, $_POST);
                $state = self::publicWizardInvalidateChangedValidation($state, $previousData, (array)$state['data']);
            }
            $state = self::publicWizardClearRedirectNotice($state);
            $state['error'] = '';
            if (($state['notice_type'] ?? '') !== self::PUBLIC_WIZARD_NOTICE_STEP_REDIRECT) {
                $state['notice'] = '';
            }

            try {
                $state = self::handlePublicWizardAction($core, $state, $action);
            } catch (\Throwable $e) {
                $state['error'] = self::publicWizardUserErrorMessage($e);
            }

            $_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] = self::publicWizardSanitizeSessionState($state);

            $redirectStep = self::publicWizardResolvedStep($state, (string)($state['current_step'] ?? ''));
            header('Location: /setup?step=' . urlencode($redirectStep));
            exit;
        }

        $state = self::publicWizardNormalizeState($core, $state);
        $requestedStep = trim((string)($_GET['step'] ?? ''));
        $normalizedRequestedStep = $requestedStep !== '' ? self::publicWizardNormalizeStep($requestedStep) : '';
        $resolvedStep = self::publicWizardResolvedStep($state, $requestedStep);

        if ($requestedStep !== '' && $requestedStep !== $resolvedStep) {
            if (self::publicWizardShouldWarnForStepRedirect($state, $requestedStep, $normalizedRequestedStep, $resolvedStep)) {
                $state['notice'] = self::publicWizardStepRedirectNotice($state, $requestedStep, $resolvedStep);
                $state['notice_type'] = self::PUBLIC_WIZARD_NOTICE_STEP_REDIRECT;
            } else {
                $state = self::publicWizardClearRedirectNotice($state);
            }
            $_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] = self::publicWizardSanitizeSessionState($state);
            header('Location: /setup?step=' . urlencode($resolvedStep));
            exit;
        }

        $state['current_step'] = $requestedStep !== '' ? $resolvedStep : self::publicWizardResolvedStep($state);
        $state = self::publicWizardClearRedirectNotice($state);

        $twoFaQrPayload = self::publicWizardTwoFaQrPayload();
        $homeRouteOptions = self::publicWizardHomeRouteOptions($state);
        $publicCoreStatus = $core->statusSummary();
        $corePlatformReady = !empty($publicCoreStatus['core_platform_apps']['all_healthy']);

        $state = self::publicWizardSanitizeSessionState($state);
        $_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] = $state;

        $view->renderAuth('setup/core.php', [
            'pageTitle' => 'Setup Wizard',
            'csrf' => Auth::csrfToken(),
            'wizard' => self::publicWizardSanitizeSessionState($state),
            'setupStatus' => SetupStatusService::publicSnapshot([
                'wizard' => self::publicWizardSanitizeSessionState($state),
                'core_platform_ready' => $corePlatformReady,
                'current_step' => (string)($state['current_step'] ?? 'welcome'),
                'csrf' => Auth::csrfToken(),
                'current_page_url' => '/setup?step=' . urlencode((string)($state['current_step'] ?? 'welcome')),
            ]),
            'coreStatus' => $publicCoreStatus,
            'platformProfiles' => (new SetupProfileService())->platformProfiles(),
            'languageOptions' => (new CompanySettingsService())->languageOptions(),
            'currencyOptions' => (new CompanySettingsService())->currencyOptions(),
            'themeOptions' => (new CompanySettingsService())->themeOptions(),
            'homeRouteOptions' => $homeRouteOptions,
            'qrImageSrc' => (string)($twoFaQrPayload['data_uri'] ?? ''),
            'qrImageError' => (string)($twoFaQrPayload['error'] ?? ''),
        ]);

        if (self::publicWizardShouldCloseSessionAfterRender($state)) {
            unset($_SESSION[self::PUBLIC_WIZARD_SESSION_KEY]);
        }
    }

    public static function hasActivePublicWizardSession(): bool
    {
        Auth::bootSession();

        $state = $_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] ?? null;
        return is_array($state) && !empty($state['started']);
    }

    public static function index(View $view): void
    {
        self::requireAdminToolsAccess();

        $view->render('admin/setup/index.php', [
            'pageTitle' => 'Platform Setup',
            'csrf' => Auth::csrfToken(),
            'wizardCards' => self::wizardCards(),
            'platformProfiles' => self::localizedOverviewPlatformProfiles((new SetupProfileService())->platformProfiles()),
            'defaults' => (new SetupProfileService())->recommendedDefaults(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function onboardingPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $profiles = new SetupProfileService();
        $coreStatus = self::decorateStatus((new CoreSetupService())->statusSummary());
        $suiteService = new SuiteSetupService();
        $suiteCards = array_map([self::class, 'decorateStatus'], array_values($suiteService->statusCards()));
        $verification = (new InstallVerificationService())->verifyAll();

        $steps = [
            [
                'number' => 1,
                'title' => 'Core platform ready',
                'description' => 'The platform foundation is installed and the first administrator account is in place.',
                'state' => ($coreStatus['status'] ?? '') === 'warning' ? 'needs attention' : 'done',
            ],
            [
                'number' => 2,
                'title' => 'Choose a starting preset',
                'description' => 'Pick the optional business setup layer that matches how you want to begin using the system.',
                'state' => 'ready',
            ],
            [
                'number' => 3,
                'title' => 'Apply business setup',
                'description' => 'Install and configure the selected suites with their recommended starting settings.',
                'state' => count(array_filter($suiteCards, static fn(array $row): bool => in_array((string)($row['status'] ?? ''), ['configured', 'verified'], true))) > 0 ? 'in progress' : 'to do',
            ],
            [
                'number' => 4,
                'title' => 'Verify setup',
                'description' => 'Run the final setup check before inviting daily users into the system.',
                'state' => count((array)($verification['warnings'] ?? [])) === 0 ? 'done' : 'review',
            ],
            [
                'number' => 5,
                'title' => 'Open dashboard',
                'description' => 'Open the right workspace, suite dashboard, or setup page for the next task.',
                'state' => 'ready',
            ],
        ];

        $view->render('admin/setup/onboarding.php', [
            'pageTitle' => 'Guided Onboarding',
            'csrf' => Auth::csrfToken(),
            'steps' => $steps,
            'platformProfiles' => $profiles->platformProfiles(),
            'suiteProfiles' => $profiles->suiteProfiles(),
            'defaults' => $profiles->recommendedDefaults(),
            'coreStatus' => $coreStatus,
            'suiteCards' => $suiteCards,
            'verification' => $verification,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function completionPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $view->render('admin/setup/complete.php', [
            'pageTitle' => 'Setup Complete',
            'completion' => self::completionPayload(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function corePage(View $view): void
    {
        self::requireAdminToolsAccess();

        $core = new CoreSetupService();
        $verification = new InstallVerificationService();
        $view->render('admin/setup/core.php', [
            'pageTitle' => 'Core Setup',
            'csrf' => Auth::csrfToken(),
            'coreStatus' => self::decorateStatus($core->statusSummary()),
            'verification' => $verification->verifyAll(),
            'flash' => self::flashPayload(),
            'platformProfiles' => (new SetupProfileService())->platformProfiles(),
        ]);

        self::clearFlash();
    }

    public static function suitesPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $suiteService = new SuiteSetupService();
        $cards = [];
        foreach ($suiteService->statusCards() as $suiteKey => $card) {
            $cards[$suiteKey] = self::decorateStatus($card);
        }

        $view->render('admin/setup/suites.php', [
            'pageTitle' => 'Suite Setup',
            'csrf' => Auth::csrfToken(),
            'suiteCards' => $cards,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function suiteDetailPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $suiteKey = trim((string)($_GET['suite_key'] ?? ''));
        if ($suiteKey === '') {
            header('Location: /admin/setup/suites');
            exit;
        }

        $suite = self::decorateStatus((new SuiteSetupService())->suiteStatus($suiteKey));
        $view->render('admin/setup/suite_detail.php', [
            'pageTitle' => ucfirst($suiteKey) . ' Setup',
            'csrf' => Auth::csrfToken(),
            'suite' => $suite,
            'dependencyDetail' => (new DependencyGraphService())->suiteDetail($suiteKey),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function modulesPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $dependencyService = new DependencyGraphService();
        $panels = array_map([self::class, 'decorateStatus'], (new ModuleLifecycleService())->panelRows());
        foreach ($panels as &$panel) {
            $moduleName = trim((string)($panel['name'] ?? ''));
            if ($moduleName === '') {
                continue;
            }
            $panel['dependency_detail'] = $dependencyService->moduleDetail($moduleName);
        }
        unset($panel);
        $view->render('admin/setup/modules.php', [
            'pageTitle' => 'Module Lifecycle',
            'csrf' => Auth::csrfToken(),
            'modulePanels' => $panels,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function upgradesPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $service = new UpgradeAssistantService();
        $preview = $_SESSION['upgrade_assistant_preview'] ?? null;
        $runs = array_values(array_filter(
            array_map([self::class, 'decorateRun'], (new SetupStateService())->recentRuns(80)),
            static fn(array $row): bool => (string)($row['action_key'] ?? '') === 'upgrade_assistant'
        ));

        $view->render('admin/setup/upgrades.php', [
            'pageTitle' => 'Upgrade Assistant',
            'csrf' => Auth::csrfToken(),
            'scopeOptions' => $service->scopeOptions(),
            'targetOptions' => $service->targetOptions(),
            'preview' => is_array($preview) ? $preview : [],
            'runs' => $runs,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function healthPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $view->render('admin/setup/health.php', [
            'pageTitle' => 'Health Dashboards',
            'health' => (new HealthDashboardService())->dashboard(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function auditPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $runs = array_map([self::class, 'decorateRun'], (new SetupStateService())->recentRuns(80));
        $view->render('admin/setup/audit.php', [
            'pageTitle' => 'Setup Audit',
            'runs' => $runs,
            'flash' => self::flashPayload(),
            'csrf' => Auth::csrfToken(),
        ]);

        self::clearFlash();
    }

    public static function environmentPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $history = new EnvironmentPortabilityHistoryService();
        $snapshotService = new EnvironmentSnapshotService();
        $previewToken = trim((string)($_GET['preview'] ?? ($_SESSION['environment_clone_preview'] ?? '')));
        $preview = $previewToken !== '' ? $history->loadPreview($previewToken) : null;

        $view->render('admin/setup/environment.php', [
            'pageTitle' => 'Environment Snapshot & Clone',
            'csrf' => Auth::csrfToken(),
            'scopeOptions' => $snapshotService->scopeOptions(),
            'currentEnvironment' => $snapshotService->currentEnvironmentInfo(),
            'history' => $history->recentRuns(30),
            'preview' => $preview,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function configPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $service = new ConfigManagementService();
        $selectedEnvironment = trim((string)($_GET['environment'] ?? ''));
        $view->render('admin/setup/config.php', [
            'pageTitle' => 'Config Management',
            'csrf' => Auth::csrfToken(),
            'configDashboard' => $service->dashboard($selectedEnvironment !== '' ? $selectedEnvironment : null),
            'environmentOptions' => $service->environmentOptions(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function releasePage(View $view): void
    {
        self::requireAdminToolsAccess();

        $history = new ReleaseHistoryService();
        $previewToken = trim((string)($_GET['preview'] ?? ($_SESSION['release_preview'] ?? '')));
        $preview = $previewToken !== '' ? $history->loadPreview($previewToken) : null;
        $readiness = new DeploymentReadinessService();

        $view->render('admin/setup/release.php', [
            'pageTitle' => 'Release Workflow',
            'csrf' => Auth::csrfToken(),
            'scopeOptions' => $readiness->scopeOptions(),
            'componentChoices' => $readiness->componentChoices(),
            'history' => $history->recentRuns(30),
            'preview' => $preview,
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function dependenciesPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $view->render('admin/setup/dependencies.php', [
            'pageTitle' => 'Dependency Graph',
            'graph' => (new DependencyGraphService())->overview(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function scaffoldsPage(View $view): void
    {
        self::requireAdminToolsAccess();

        $service = new ScaffoldGeneratorService();
        $view->render('admin/setup/scaffolds.php', [
            'pageTitle' => 'Scaffolds',
            'csrf' => Auth::csrfToken(),
            'scaffoldTypes' => $service->scaffoldTypes(),
            'suiteOptions' => $service->suiteOptions(),
            'flash' => self::flashPayload(),
        ]);

        self::clearFlash();
    }

    public static function coreAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        try {
            $result = (new CoreSetupService())->repairBootstrap();
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Core bootstrap repaired.';
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/core');
        exit;
    }

    public static function platformProfileAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $profileKey = trim((string)($_POST['profile_key'] ?? ''));
        if ($profileKey === '') {
            $_SESSION['setup_console_err'] = 'Select a setup preset before continuing.';
            header('Location: /admin/setup/onboarding');
            exit;
        }

        $profiles = new SetupProfileService();
        $profile = $profiles->platformProfile($profileKey);
        if (!is_array($profile)) {
            $_SESSION['setup_console_err'] = 'Unknown setup profile.';
            header('Location: /admin/setup/onboarding');
            exit;
        }

        try {
            $results = [];
            $results[] = (new CoreSetupService())->repairBootstrap();
            $suiteService = new SuiteSetupService();
            foreach ((array)($profile['suites'] ?? []) as $suiteKey => $suiteProfile) {
                $results[] = $suiteService->install((string)$suiteKey);
                $results[] = $suiteService->configure((string)$suiteKey, (string)$suiteProfile);
                $results[] = $suiteService->verify((string)$suiteKey);
            }
            $_SESSION['setup_console_result'] = [
                'phase' => 'platform_profile',
                'profile' => $profileKey,
                'status' => 'ok',
                'steps' => $results,
            ];
            $_SESSION['setup_console_ok'] = 'Your setup preset is ready: ' . $profile['label'];
            header('Location: /admin/setup/complete?profile=' . urlencode($profileKey));
            exit;
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/onboarding');
        exit;
    }

    public static function suiteAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $suiteKey = trim((string)($_POST['suite_key'] ?? ''));
        $phase = trim((string)($_POST['phase'] ?? 'install'));
        $profileKey = trim((string)($_POST['profile_key'] ?? ''));
        $service = new SuiteSetupService();

        try {
            $result = match ($phase) {
                'install' => $service->install($suiteKey),
                'configure' => $service->configure($suiteKey, $profileKey),
                'profile' => $service->applyProfile($suiteKey, $profileKey),
                'verify' => $service->verify($suiteKey),
                default => throw new \RuntimeException('Unsupported suite phase: ' . $phase),
            };
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = self::plainSuiteMessage($suiteKey, $phase);
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/suites/detail?suite_key=' . urlencode($suiteKey));
        exit;
    }

    public static function moduleAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $moduleName = trim((string)($_POST['module_name'] ?? ''));
        $operation = trim((string)($_POST['operation'] ?? 'validate'));

        try {
            $result = (new ModuleLifecycleService())->run($moduleName, $operation);
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = self::plainModuleMessage($moduleName, $operation);
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/modules');
        exit;
    }

    public static function scaffoldAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $scaffoldType = trim((string)($_POST['scaffold_type'] ?? 'suite'));
        $service = new ScaffoldGeneratorService();

        try {
            $result = match ($scaffoldType) {
                'suite' => $service->generateSuite($_POST),
                'module' => $service->generateModule($_POST),
                default => throw new \RuntimeException('Unsupported scaffold type: ' . $scaffoldType),
            };
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = match ($scaffoldType) {
                'suite' => 'Suite scaffold created. Review the generated structure, then tailor the new bundle before enabling it.',
                'module' => 'Module scaffold created. The owning suite manifest was updated so the new module can be discovered.',
                default => 'Scaffold created.',
            };
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/scaffolds');
        exit;
    }

    public static function verifyAction(): void
    {
        self::requireAdminToolsAccess();

        try {
            $_SESSION['setup_console_result'] = [
                'phase' => 'verify_all',
                'status' => 'ok',
                'verification' => (new InstallVerificationService())->verifyAll(),
            ];
            $_SESSION['setup_console_ok'] = 'Setup check completed. Review any warnings before you go live.';
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup');
        exit;
    }

    public static function upgradePreviewAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $scope = trim((string)($_POST['upgrade_scope'] ?? 'core'));
        $targetKey = trim((string)($_POST['target_key'] ?? ''));

        try {
            $preview = (new UpgradeAssistantService())->preview($scope, $targetKey);
            $_SESSION['upgrade_assistant_preview'] = $preview;
            $_SESSION['setup_console_ok'] = 'Upgrade plan preview is ready. Review checks, migration warnings, and verification steps before applying it.';
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/upgrades');
        exit;
    }

    public static function upgradeApplyAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $scope = trim((string)($_POST['upgrade_scope'] ?? 'core'));
        $targetKey = trim((string)($_POST['target_key'] ?? ''));

        try {
            $result = (new UpgradeAssistantService())->apply($scope, $targetKey);
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Upgrade assistant run completed. Review post-upgrade verification and any recovery notes below.';
            unset($_SESSION['upgrade_assistant_preview']);
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/upgrades');
        exit;
    }

    public static function upgradeCancelAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        unset($_SESSION['upgrade_assistant_preview']);
        $_SESSION['setup_console_ok'] = 'Upgrade preview cleared.';
        header('Location: /admin/setup/upgrades');
        exit;
    }

    public static function recoverAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $targetType = trim((string)($_POST['target_type'] ?? ''));
        $targetKey = trim((string)($_POST['target_key'] ?? ''));
        $mode = trim((string)($_POST['mode'] ?? 'retry'));
        $latest = (new SetupStateService())->latestRun($targetType, $targetKey);

        try {
            if (!is_array($latest)) {
                throw new \RuntimeException('No setup history found for target.');
            }

            if ($targetType === 'core') {
                $service = new CoreSetupService();
                $result = $service->repairBootstrap();
                $_SESSION['setup_console_result'] = $result;
                $_SESSION['setup_console_ok'] = 'Core recovery complete.';
                header('Location: /admin/setup/core');
                exit;
            }

            if ($targetType === 'suite') {
                $service = new SuiteSetupService();
                $action = $mode === 'verify'
                    ? 'verify'
                    : ($mode === 'repair' ? 'configure' : (string)($latest['resume_action'] ?? $latest['retry_action'] ?? 'configure'));
                $result = match ($action) {
                    'install' => $service->install($targetKey),
                    'verify' => $service->verify($targetKey),
                    default => $service->configure($targetKey, (string)($latest['meta']['profile'] ?? '')),
                };
                $_SESSION['setup_console_result'] = $result;
                $_SESSION['setup_console_ok'] = 'Suite recovery complete.';
                header('Location: /admin/setup/suites/detail?suite_key=' . urlencode($targetKey));
                exit;
            }

            if ($targetType === 'module') {
                $service = new ModuleLifecycleService();
                $action = $mode === 'repair'
                    ? 'repair'
                    : ($mode === 'verify' ? 'validate' : (string)($latest['retry_action'] ?? $latest['action_key'] ?? 'repair'));
                $result = $service->run($targetKey, $action);
                $_SESSION['setup_console_result'] = $result;
                $_SESSION['setup_console_ok'] = 'Module recovery complete.';
                header('Location: /admin/setup/modules');
                exit;
            }

            throw new \RuntimeException('Unsupported recovery target.');
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
            header('Location: /admin/setup/audit');
            exit;
        }
    }

    public static function environmentSnapshotAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $scopeKey = trim((string)($_POST['scope_key'] ?? 'metadata_only'));
        $history = new EnvironmentPortabilityHistoryService();
        try {
            $result = (new EnvironmentSnapshotService())->createSnapshot($scopeKey);
            $history->recordSnapshotSuccess(
                $scopeKey,
                (string)($result['source_environment'] ?? ''),
                (string)($result['file_name'] ?? ''),
                (string)($result['file_path'] ?? ''),
                (array)($result['payload']['metadata'] ?? [])
            );
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Environment snapshot created. You can now download it or preview it for clone/import.';
        } catch (\Throwable $e) {
            $history->failRun(null, 'snapshot_create', $scopeKey, app_env(), null, '', $e->getMessage());
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/environment');
        exit;
    }

    public static function environmentPreviewAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $file = $_FILES['snapshot_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['setup_console_err'] = 'Choose an environment snapshot file before previewing clone/import.';
            header('Location: /admin/setup/environment');
            exit;
        }

        $name = trim((string)($file['name'] ?? 'environment_snapshot.zip'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['zip', 'json'], true)) {
            $_SESSION['setup_console_err'] = 'Use an environment snapshot in .zip or .json format.';
            header('Location: /admin/setup/environment');
            exit;
        }

        $previewToken = 'env_clone_' . bin2hex(random_bytes(8));
        $uploadPath = self::persistEnvironmentUpload((string)($file['tmp_name'] ?? ''), $previewToken, $name);
        $history = new EnvironmentPortabilityHistoryService();

        try {
            $preview = (new EnvironmentCloneService())->previewImport($uploadPath, $name);
            $runId = $history->startClonePreview(
                (string)($preview['scope_key'] ?? 'metadata_only'),
                (string)($preview['source_environment']['name'] ?? ''),
                (string)($preview['target_environment']['name'] ?? ''),
                $name,
                $previewToken,
                $preview
            );
            $payload = [
                'preview_token' => $previewToken,
                'run_id' => $runId,
                'source_file' => $name,
                'uploaded_path' => $uploadPath,
                'preview' => $preview,
            ];
            $history->savePreview($previewToken, $payload);
            $_SESSION['environment_clone_preview'] = $previewToken;
            $_SESSION['setup_console_ok'] = 'Clone preview is ready. Review source/target differences before applying anything.';
        } catch (\Throwable $e) {
            @unlink($uploadPath);
            $history->failRun(null, 'clone_preview', 'preview', null, app_env(), $name, $e->getMessage());
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/environment' . (isset($_SESSION['environment_clone_preview']) ? '?preview=' . urlencode((string)$_SESSION['environment_clone_preview']) : ''));
        exit;
    }

    public static function environmentApplyAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new EnvironmentPortabilityHistoryService();
        $payload = $history->loadPreview($previewToken);
        if (!is_array($payload)) {
            $_SESSION['setup_console_err'] = 'The environment clone preview expired. Upload the snapshot again.';
            header('Location: /admin/setup/environment');
            exit;
        }

        $options = [
            'apply_scope' => trim((string)($_POST['apply_scope'] ?? 'metadata_only')),
            'conflict_strategy' => trim((string)($_POST['conflict_strategy'] ?? 'merge')),
            'install_missing_dependencies' => !empty($_POST['install_missing_dependencies']),
        ];

        try {
            $result = (new EnvironmentCloneService())->applyPreview($payload, $options);
            $history->completeClone((int)($payload['run_id'] ?? 0), $result);
            $history->deletePreview($previewToken);
            @unlink((string)($payload['uploaded_path'] ?? ''));
            unset($_SESSION['environment_clone_preview']);
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Environment clone/import applied.';
            header('Location: /admin/setup/environment');
            exit;
        } catch (\Throwable $e) {
            $preview = (array)($payload['preview'] ?? []);
            $history->failRun(
                (int)($payload['run_id'] ?? 0),
                'clone_apply',
                (string)($preview['scope_key'] ?? 'metadata_only'),
                (string)($preview['source_environment']['name'] ?? ''),
                (string)($preview['target_environment']['name'] ?? ''),
                (string)($payload['source_file'] ?? ''),
                $e->getMessage(),
                $preview
            );
            $_SESSION['setup_console_err'] = $e->getMessage();
            header('Location: /admin/setup/environment?preview=' . urlencode($previewToken));
            exit;
        }
    }

    public static function environmentCancelAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new EnvironmentPortabilityHistoryService();
        $payload = $history->loadPreview($previewToken);
        if (is_array($payload)) {
            @unlink((string)($payload['uploaded_path'] ?? ''));
        }
        $history->deletePreview($previewToken);
        unset($_SESSION['environment_clone_preview']);
        $_SESSION['setup_console_ok'] = 'Environment clone review cancelled. No live configuration or data was changed.';
        header('Location: /admin/setup/environment');
        exit;
    }

    public static function configSaveAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $environment = trim((string)($_POST['environment'] ?? ''));
        $layerJson = (string)($_POST['layer_json'] ?? '{}');

        try {
            $result = (new ConfigManagementService())->saveLayerFromJson($environment, $layerJson);
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Environment config layer saved.';
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/config?environment=' . urlencode($environment));
        exit;
    }

    public static function configImportAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $environment = trim((string)($_POST['environment'] ?? ''));
        $mode = trim((string)($_POST['import_mode'] ?? 'merge'));
        $file = $_FILES['config_file'] ?? null;

        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['setup_console_err'] = 'Choose a config export file before importing.';
            header('Location: /admin/setup/config?environment=' . urlencode($environment));
            exit;
        }

        try {
            $result = (new ConfigManagementService())->importLayer(
                $environment,
                (string)($file['tmp_name'] ?? ''),
                (string)($file['name'] ?? 'config.json'),
                $mode
            );
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Environment config imported.';
        } catch (\Throwable $e) {
            $_SESSION['setup_console_err'] = $e->getMessage();
        }

        header('Location: /admin/setup/config?environment=' . urlencode($environment));
        exit;
    }

    public static function configExportAction(): void
    {
        self::requireAdminToolsAccess();

        $environment = trim((string)($_GET['environment'] ?? ''));
        try {
            $payload = (new ConfigManagementService())->exportPayload($environment);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo e($e->getMessage());
            exit;
        }

        $fileName = 'config_' . (string)($payload['environment'] ?? 'environment') . '_' . date('Ymd_His') . '.json';
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function environmentDownloadAction(): void
    {
        self::requireAdminToolsAccess();

        $id = (int)($_GET['id'] ?? 0);
        $row = (new EnvironmentPortabilityHistoryService())->findById($id);
        if (!is_array($row) || (string)($row['operation_type'] ?? '') !== 'snapshot_create') {
            http_response_code(404);
            echo 'Environment snapshot not found.';
            exit;
        }

        $path = (string)($row['snapshot_file_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Environment snapshot file not found.';
            exit;
        }

        $downloadName = basename((string)($row['snapshot_file_name'] ?? basename($path)));
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    public static function releasePreviewAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $scopeKey = trim((string)($_POST['scope_key'] ?? 'core_only'));
        $releaseVersion = trim((string)($_POST['release_version'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $selectedSuites = array_values(array_filter(array_map('strval', (array)($_POST['suite_keys'] ?? []))));
        $selectedModules = array_values(array_filter(array_map('strval', (array)($_POST['module_names'] ?? []))));
        if ($releaseVersion === '') {
            $_SESSION['setup_console_err'] = 'Release version is required.';
            header('Location: /admin/setup/release');
            exit;
        }

        $history = new ReleaseHistoryService();
        try {
            $preview = (new DeploymentReadinessService())->evaluate($scopeKey, $selectedSuites, $selectedModules);
            $previewToken = 'release_' . bin2hex(random_bytes(8));
            $runId = $history->startPreview($scopeKey, $releaseVersion, $selectedSuites, $selectedModules, $notes, $previewToken, $preview);
            $payload = [
                'preview_token' => $previewToken,
                'run_id' => $runId,
                'release_version' => $releaseVersion,
                'notes' => $notes,
                'selected_suites' => $selectedSuites,
                'selected_modules' => $selectedModules,
                'preview' => $preview,
            ];
            $history->savePreview($previewToken, $payload);
            $_SESSION['release_preview'] = $previewToken;
            $_SESSION['setup_console_ok'] = $preview['ready']
                ? 'Pre-release verification passed. Review included components and generate the package when ready.'
                : 'Pre-release verification found blocking issues. Review them before packaging.';
            header('Location: /admin/setup/release?preview=' . urlencode($previewToken));
            exit;
        } catch (\Throwable $e) {
            $history->failRun(null, $scopeKey, $releaseVersion, $selectedSuites, $selectedModules, $notes, $e->getMessage());
            $_SESSION['setup_console_err'] = $e->getMessage();
            header('Location: /admin/setup/release');
            exit;
        }
    }

    public static function releaseGenerateAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        $history = new ReleaseHistoryService();
        $payload = $history->loadPreview($previewToken);
        if (!is_array($payload)) {
            $_SESSION['setup_console_err'] = 'Release preview expired. Run pre-release verification again.';
            header('Location: /admin/setup/release');
            exit;
        }

        try {
            $result = (new ReleasePackagingService())->generateFromPreview($payload);
            $history->completeRelease((int)($payload['run_id'] ?? 0), (string)($result['package_name'] ?? ''), (string)($result['package_path'] ?? ''), $result);
            $history->deletePreview($previewToken);
            unset($_SESSION['release_preview']);
            $_SESSION['setup_console_result'] = $result;
            $_SESSION['setup_console_ok'] = 'Release package generated.';
            header('Location: /admin/setup/release');
            exit;
        } catch (\Throwable $e) {
            $history->failRun(
                (int)($payload['run_id'] ?? 0),
                (string)($payload['preview']['scope_key'] ?? 'core_only'),
                (string)($payload['release_version'] ?? ''),
                (array)($payload['selected_suites'] ?? []),
                (array)($payload['selected_modules'] ?? []),
                (string)($payload['notes'] ?? ''),
                $e->getMessage(),
                (array)($payload['preview'] ?? [])
            );
            $_SESSION['setup_console_err'] = $e->getMessage();
            header('Location: /admin/setup/release?preview=' . urlencode($previewToken));
            exit;
        }
    }

    public static function releaseCancelAction(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $previewToken = trim((string)($_POST['preview_token'] ?? ''));
        (new ReleaseHistoryService())->deletePreview($previewToken);
        unset($_SESSION['release_preview']);
        $_SESSION['setup_console_ok'] = 'Release preview cleared.';
        header('Location: /admin/setup/release');
        exit;
    }

    public static function releaseDownloadAction(): void
    {
        self::requireAdminToolsAccess();

        $id = (int)($_GET['id'] ?? 0);
        $row = (new ReleaseHistoryService())->findById($id);
        if (!is_array($row) || (string)($row['status'] ?? '') !== ReleaseHistoryService::STATUS_GENERATED) {
            http_response_code(404);
            echo 'Release package not found.';
            exit;
        }

        $path = (string)($row['package_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Release file not found.';
            exit;
        }

        $downloadName = basename((string)($row['package_name'] ?? basename($path)));
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($path);
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    private static function publicWizardState(CoreSetupService $core): array
    {
        $state = $_SESSION[self::PUBLIC_WIZARD_SESSION_KEY] ?? null;
        if (!is_array($state)) {
            $state = [];
        }

        $dbConfig = $core->readDbConfig() ?? [];
        $defaults = self::publicWizardDefaults($dbConfig);
        $state['data'] = array_merge($defaults, is_array($state['data'] ?? null) ? $state['data'] : []);
        $state['started'] = !empty($state['started']);
        if (!isset($state['current_step'])) {
            $state['current_step'] = 'welcome';
        }
        $state['current_step'] = self::publicWizardNormalizeStep((string)$state['current_step']);
        $state['readiness'] = is_array($state['readiness'] ?? null) ? $state['readiness'] : [];
        $state['db_test'] = is_array($state['db_test'] ?? null) ? $state['db_test'] : [];
        $state['verified_db'] = is_array($state['verified_db'] ?? null) ? $state['verified_db'] : [];
        $state['verified_platform'] = is_array($state['verified_platform'] ?? null) ? $state['verified_platform'] : [];
        $state['install_run'] = is_array($state['install_run'] ?? null) ? $state['install_run'] : [];
        $state['install_result'] = is_array($state['install_result'] ?? null) ? $state['install_result'] : [];
        $state['suite_setup_results'] = is_array($state['suite_setup_results'] ?? null) ? $state['suite_setup_results'] : [];
        $state['install_ready'] = !empty($state['install_ready']);
        $state['platform_validated'] = !empty($state['platform_validated']);
        $state['install_lock'] = self::publicWizardNormalizeInstallLock($state['install_lock'] ?? null);
        $state['install_submit_token'] = trim((string)($state['install_submit_token'] ?? ''));
        $state['last_visited_step'] = self::publicWizardNormalizeStep((string)($state['last_visited_step'] ?? 'welcome'));
        $state['error'] = (string)($state['error'] ?? '');
        $state['notice'] = (string)($state['notice'] ?? '');
        $state['notice_type'] = (string)($state['notice_type'] ?? '');

        if ($state['install_submit_token'] === '') {
            $state['install_submit_token'] = self::publicWizardIssueInstallToken();
        }

        return self::publicWizardSanitizeSessionState($state);
    }

    /**
     * @param array<string,mixed> $dbConfig
     * @return array<string,mixed>
     */
    private static function publicWizardDefaults(array $dbConfig): array
    {
        return [
            'db_host' => (string)($dbConfig['host'] ?? 'localhost'),
            'db_port' => (string)($dbConfig['port'] ?? 3306),
            'db_name' => (string)($dbConfig['name'] ?? ''),
            'db_user' => (string)($dbConfig['user'] ?? ''),
            'db_pass_configured' => trim((string)($dbConfig['pass'] ?? '')) !== '' ? '1' : '0',
            'db_charset' => (string)($dbConfig['charset'] ?? 'utf8mb4'),
            'db_create_if_missing' => '1',
            'instance_name' => 'OdareHub',
            'language' => 'en',
            'number_format' => '1,234.56',
            'currency' => 'JPY',
            'timezone' => (string)($dbConfig['timezone'] ?? 'Asia/Tokyo'),
            'theme' => default_theme_preference(),
            'platform_profile' => 'core_only',
            'workspace_home' => '/admin/setup',
            'admin_email' => '',
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function publicWizardMergeInput(array $current, array $input): array
    {
        $fields = [
            'db_host',
            'db_port',
            'db_name',
            'db_user',
            'db_charset',
            'instance_name',
            'language',
            'number_format',
            'currency',
            'timezone',
            'theme',
            'platform_profile',
            'workspace_home',
            'admin_email',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $current[$field] = (string)$input[$field];
            }
        }

        $updatesDatabaseFields = false;
        foreach (['db_host', 'db_port', 'db_name', 'db_user', 'db_charset', 'db_pass'] as $field) {
            if (array_key_exists($field, $input)) {
                $updatesDatabaseFields = true;
                break;
            }
        }

        if ($updatesDatabaseFields) {
            $current['db_create_if_missing'] = !empty($input['db_create_if_missing']) ? '1' : '0';
        }

        return $current;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardSanitizeSessionState(array $state): array
    {
        $data = is_array($state['data'] ?? null) ? (array)$state['data'] : [];
        foreach (self::PUBLIC_WIZARD_SECRET_FIELDS as $field) {
            unset($data[$field]);
        }
        $state['data'] = $data;

        foreach (self::PUBLIC_WIZARD_SECRET_FIELDS as $field) {
            unset($state[$field]);
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $previousData
     * @param array<string,mixed> $nextData
     * @return array<string,mixed>
     */
    private static function publicWizardInvalidateChangedValidation(array $state, array $previousData, array $nextData): array
    {
        if (self::publicWizardFieldsChanged($previousData, $nextData, self::PUBLIC_WIZARD_DB_FIELDS)) {
            $state['db_test'] = [];
            $state['verified_db'] = [];
            $state['platform_validated'] = false;
            $state['verified_platform'] = [];
            $state['install_ready'] = false;
        }

        if (self::publicWizardFieldsChanged($previousData, $nextData, self::PUBLIC_WIZARD_PLATFORM_FIELDS)) {
            $state['platform_validated'] = false;
            $state['verified_platform'] = [];
            $state['install_ready'] = false;
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $previousData
     * @param array<string,mixed> $nextData
     * @param array<int,string> $fields
     */
    private static function publicWizardFieldsChanged(array $previousData, array $nextData, array $fields): bool
    {
        foreach ($fields as $field) {
            if ((string)($previousData[$field] ?? '') !== (string)($nextData[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function handlePublicWizardAction(CoreSetupService $core, array $state, string $action): array
    {
        if ($action === 'back') {
            return self::publicWizardBack($state, (string)($_POST['back_step'] ?? $_POST['previous_step'] ?? 'welcome'));
        }

        $state = self::publicWizardNormalizeState($core, $state);

        return match ($action) {
            'start_setup' => self::publicWizardMove(array_merge($state, ['started' => true]), 'readiness'),
            'run_readiness' => self::publicWizardRunReadiness($core, $state),
            'continue_readiness' => self::publicWizardContinueReadiness($core, $state),
            'test_database' => self::publicWizardTestDatabase($core, $state),
            'continue_database' => self::publicWizardContinueDatabase($state),
            'continue_core_install' => self::publicWizardContinueCoreInstall($state),
            'install_core', 'install_core_platform', 'retry_install' => self::publicWizardInstall($core, $state),
            'continue_finish', 'continue_verify' => self::publicWizardContinueVerify($core, $state),
            default => $state,
        };
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function resolvePublicWizardAction(array $input): string
    {
        $action = trim((string)($input['wizard_action'] ?? ''));
        if ($action !== '') {
            return $action;
        }

        if (!empty($input['back_step']) || !empty($input['previous_step'])) {
            return 'back';
        }

        return '';
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardRunReadiness(CoreSetupService $core, array $state): array
    {
        $state['started'] = true;
        $state['readiness'] = self::buildPublicReadiness($core->preflight());
        $state['platform_validated'] = false;
        $state['notice'] = self::publicWizardReadinessPassed((array)$state['readiness'])
            ? (string)t('setup.notice.readiness_passed')
            : (string)t('setup.notice.readiness_attention');
        return self::publicWizardMove(self::publicWizardNormalizeState($core, $state), 'readiness');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardContinueReadiness(CoreSetupService $core, array $state): array
    {
        $state['started'] = true;
        $state['readiness'] = self::buildPublicReadiness($core->preflight());
        if (!self::publicWizardReadinessPassed((array)$state['readiness'])) {
            throw new \RuntimeException((string)t('setup.error.readiness_fix_before_continue'));
        }

        $state['notice'] = (string)t('setup.notice.readiness_continue_to_database');
        return self::publicWizardMove(self::publicWizardNormalizeState($core, $state), 'database');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardTestDatabase(CoreSetupService $core, array $state): array
    {
        $state['started'] = true;
        if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
            throw new \RuntimeException((string)t('setup.error.readiness_complete_before_db_test'));
        }

        $postedDbPassword = (string)($_POST['db_pass'] ?? '');
        $existingDbConfig = $core->readDbConfig() ?? [];
        if ($postedDbPassword === '' && trim((string)($existingDbConfig['pass'] ?? '')) !== '') {
            $postedDbPassword = (string)$existingDbConfig['pass'];
        }

        $dbConfig = self::publicWizardDbConfig(array_merge((array)$state['data'], [
            'db_pass' => $postedDbPassword,
        ]));
        $result = $core->testDatabaseConfig($dbConfig, !empty($state['data']['db_create_if_missing']) && (string)$state['data']['db_create_if_missing'] === '1');
        if (empty($result['ok'])) {
            throw new \RuntimeException((string)($result['message'] ?? 'Database connection failed.'));
        }

        $core->saveDbConfig($dbConfig);
        $state['data']['db_pass_configured'] = trim((string)($dbConfig['pass'] ?? '')) !== '' ? '1' : '0';
        $state['db_test'] = [
            'ok' => true,
            'message' => (string)($result['message'] ?? 'Database connection verified.'),
        ];
        $state['verified_db'] = self::publicWizardVerifiedDbMetadata($dbConfig, !empty($state['data']['db_create_if_missing']) && (string)$state['data']['db_create_if_missing'] === '1');
        $state['platform_validated'] = false;
        $state['verified_platform'] = [];
        $state['notice'] = 'Database connection verified and configuration saved.';
        return self::publicWizardMove(self::publicWizardNormalizeState($core, $state), 'database');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardContinueDatabase(array $state): array
    {
        $state['started'] = true;
        if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
            throw new \RuntimeException('Fix readiness checks before continuing to platform details.');
        }
        if (!self::publicWizardDbValidationMatches($state)) {
            throw new \RuntimeException('Test the database connection successfully before continuing.');
        }

        $state['notice'] = 'Database setup is validated.';
        return self::publicWizardMove($state, 'core_install');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardContinueCoreInstall(array $state): array
    {
        $state['started'] = true;
        if (!self::publicWizardDbValidationMatches($state)) {
            throw new \RuntimeException('Validate and save the database settings before continuing.');
        }

        $data = (array)($state['data'] ?? []);
        if (trim((string)($data['instance_name'] ?? '')) === '') {
            throw new \RuntimeException('Instance name is required.');
        }
        if (trim((string)($data['language'] ?? '')) === '') {
            throw new \RuntimeException('Language is required.');
        }
        if (trim((string)($data['number_format'] ?? '')) === '') {
            throw new \RuntimeException('Number format is required.');
        }
        if (trim((string)($data['currency'] ?? '')) === '') {
            throw new \RuntimeException('Currency is required.');
        }
        if (trim((string)($data['timezone'] ?? '')) === '') {
            throw new \RuntimeException('Timezone is required.');
        }
        if (trim((string)($data['theme'] ?? '')) === '') {
            throw new \RuntimeException('Theme is required.');
        }
        if (!self::publicWizardHasValidPlatformProfile($state)) {
            throw new \RuntimeException('Choose the platform setup profile you want to provision.');
        }
        if (trim((string)($data['workspace_home'] ?? '')) === '') {
            throw new \RuntimeException('Workspace home is required.');
        }
        if (!self::publicWizardWorkspaceHomeAllowed($state)) {
            throw new \RuntimeException('Choose a workspace home that matches the platform profile selected for setup.');
        }

        $state['platform_validated'] = true;
        $state['verified_platform'] = self::publicWizardVerifiedPlatformMetadata($state);
        $state['notice'] = 'Platform details saved for installation.';
        return self::publicWizardMove($state, 'admin');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardContinueVerify(CoreSetupService $core, array $state): array
    {
        if (!self::publicWizardInstallSucceeded($state)) {
            throw new \RuntimeException('Verify becomes available after the installation completes successfully.');
        }
        if (empty($core->statusSummary()['core_platform_apps']['all_healthy'])) {
            throw new \RuntimeException('Core / Platform Apps are not fully healthy yet. Run Core repair before completing verification.');
        }

        $state['notice'] = 'Platform bootstrap verified. Continue to the landing workspace.';
        return self::publicWizardMove($state, 'verify');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardInstall(CoreSetupService $core, array $state): array
    {
        $state['started'] = true;
        $state = self::publicWizardNormalizeState($core, $state);

        $globalLock = self::publicWizardAcquireGlobalInstallLock();
        if (!$globalLock['acquired']) {
            $state['notice'] = 'Installation is already running in another session. Refresh this page to follow progress.';
            return self::publicWizardMove($state, 'admin');
        }

        try {
            if (self::publicWizardInstallLocked($state)) {
                $state['notice'] = 'Installation is already running. Refresh the page to see current progress.';
                return self::publicWizardMove($state, 'admin');
            }

            self::publicWizardRequireInstallToken($state, (string)($_POST['install_submit_token'] ?? ''));

            if (!empty($state['install_ready'])) {
                if (!empty($core->statusSummary()['core_platform_apps']['all_healthy'])) {
                    $state['notice'] = 'Core installation already completed for this setup session. Continue with final verification.';
                    return self::publicWizardMove($state, 'verify');
                }

                try {
                    $repairResult = $core->repairBootstrap();
                    $state['install_result'] = array_merge((array)($state['install_result'] ?? []), [
                        'core_repair' => $repairResult,
                    ]);
                    $state['install_run'] = self::publicWizardLatestCoreRun();
                    $state['install_lock'] = ['active' => false, 'started_at' => 0];
                    $state['notice'] = 'Core platform repair completed. Recheck verification.';
                    return self::publicWizardMove(self::publicWizardNormalizeState($core, $state), 'verify');
                } catch (\Throwable $e) {
                    $state['install_ready'] = false;
                    $state['install_run'] = self::publicWizardLatestCoreRun();
                    $state['install_lock'] = ['active' => false, 'started_at' => 0];
                    throw new \RuntimeException(self::publicWizardFailureSummary($state['install_run'], $e));
                }
            }

            $data = (array)($state['data'] ?? []);
            if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
                throw new \RuntimeException('Complete readiness checks before installing the platform.');
            }
            if (!self::publicWizardDbValidationMatches($state)) {
                throw new \RuntimeException('Re-test the database connection before installation.');
            }
            if (empty($state['platform_validated'])) {
                throw new \RuntimeException('Save platform details before starting installation.');
            }
            if (!self::publicWizardPlatformValidationMatches($state)) {
                throw new \RuntimeException('Save platform details again before starting installation.');
            }

            $savedDbConfig = $core->readDbConfig();
            if (!is_array($savedDbConfig) || !self::publicWizardSavedDbConfigMatchesVerified($savedDbConfig, $state)) {
                throw new \RuntimeException('Re-test the database connection before installation.');
            }

            $email = strtolower(trim((string)($data['admin_email'] ?? '')));
            $password = (string)($_POST['admin_password'] ?? '');
            $confirm = (string)($_POST['admin_password_confirm'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('A valid admin email is required.');
            }
            (new \App\Services\PasswordPolicyService())->assertValid($password, $confirm);

            $state = self::publicWizardMove($state, 'admin');
            $state['notice'] = 'Installation run started.';
            $state['install_lock'] = [
                'active' => true,
                'started_at' => time(),
            ];
            $state['install_submit_token'] = self::publicWizardIssueInstallToken();
            try {
                $result = $core->run([
                    'db_host' => (string)($savedDbConfig['host'] ?? ''),
                    'db_port' => (string)($savedDbConfig['port'] ?? ''),
                    'db_name' => (string)($savedDbConfig['name'] ?? ''),
                    'db_user' => (string)($savedDbConfig['user'] ?? ''),
                    'db_pass' => (string)($savedDbConfig['pass'] ?? ''),
                    'db_charset' => (string)($savedDbConfig['charset'] ?? 'utf8mb4'),
                    'db_create_if_missing' => !empty($data['db_create_if_missing']) && (string)$data['db_create_if_missing'] === '1',
                    'admin_email' => $email,
                    'admin_password' => $password,
                    'system_name' => (string)($data['instance_name'] ?? 'OdareHub'),
                    'timezone' => (string)($data['timezone'] ?? 'Asia/Tokyo'),
                    'currency' => (string)($data['currency'] ?? 'JPY'),
                    'language' => (string)($data['language'] ?? 'en'),
                    'number_format' => (string)($data['number_format'] ?? '1,234.56'),
                    'theme' => (string)($data['theme'] ?? default_theme_preference()),
                    'workspace_home' => (string)($data['workspace_home'] ?? '/admin/setup'),
                ]);
                $state['install_result'] = $result;
                $state['suite_setup_results'] = self::publicWizardProvisionPlatformProfile($state);
                $state['install_ready'] = true;
                $state['install_run'] = self::publicWizardLatestCoreRun();
                $state['install_lock'] = ['active' => false, 'started_at' => 0];
                $state['notice'] = self::publicWizardProvisioningNotice($state);
            } catch (\Throwable $e) {
                $state['install_ready'] = false;
                $state['install_run'] = self::publicWizardLatestCoreRun();
                $state['install_lock'] = ['active' => false, 'started_at' => 0];
                throw new \RuntimeException(self::publicWizardFailureSummary($state['install_run'], $e));
            }

            return self::publicWizardMove($state, 'verify');
        } finally {
            self::publicWizardReleaseGlobalInstallLock($globalLock);
        }
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardProvisionPlatformProfile(array $state): array
    {
        $profile = self::publicWizardSelectedPlatformProfile($state);
        if (!is_array($profile)) {
            throw new \RuntimeException('The selected platform setup profile is not valid.');
        }

        $suiteProfiles = (array)($profile['suites'] ?? []);
        if ($suiteProfiles === []) {
            return [];
        }

        $service = new SuiteSetupService();
        $results = [];
        foreach ($suiteProfiles as $suiteKey => $profileKey) {
            $suiteKey = strtolower(trim((string)$suiteKey));
            $profileKey = trim((string)$profileKey);
            $suiteResult = [
                'install' => $service->install($suiteKey),
                'configure' => $service->configure($suiteKey, $profileKey),
                'verify' => $service->verify($suiteKey),
            ];

            self::publicWizardAssertSuiteProvisioning($suiteKey, $suiteResult);
            $results[$suiteKey] = $suiteResult;
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardProvisioningNotice(array $state): string
    {
        $profile = self::publicWizardSelectedPlatformProfile($state);
        $profileLabel = is_array($profile) ? trim((string)($profile['label'] ?? 'selected profile')) : 'selected profile';
        $suiteResults = (array)($state['suite_setup_results'] ?? []);
        if ($suiteResults === []) {
            return 'Core platform installation completed for ' . $profileLabel . '. Continue with final verification.';
        }

        return 'Core platform installation completed and the ' . $profileLabel . ' suites were installed, activated, and verified. Continue with final verification.';
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardBack(array $state, string $step): array
    {
        return self::publicWizardMove($state, $step);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardMove(array $state, string $step): array
    {
        $previousStep = self::publicWizardNormalizeStep((string)($state['current_step'] ?? 'welcome'));
        $nextStep = self::publicWizardNormalizeStep($step);

        if ($nextStep !== $previousStep) {
            $state['last_visited_step'] = $previousStep;
        }

        $state['current_step'] = $nextStep;
        return $state;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardCanOpenStep(array $state, string $step): bool
    {
        $step = self::publicWizardNormalizeStep($step);
        return self::publicWizardStepIndex($step) <= self::publicWizardStepIndex(self::publicWizardMaxSafeStep($state));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardNormalizeState(CoreSetupService $core, array $state): array
    {
        $state['readiness'] = self::buildPublicReadiness($core->preflight());
        $state['install_run'] = self::publicWizardLatestCoreRun();
        $state['install_lock'] = self::publicWizardNormalizeInstallLock($state['install_lock'] ?? null);

        if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
            $state['db_test'] = [];
        }

        if (!self::publicWizardDbValidationMatches($state)) {
            $state['db_test'] = [];
        }

        if (self::publicWizardInstallLocked($state) && self::publicWizardInstallTerminal($state)) {
            $state['install_lock'] = ['active' => false, 'started_at' => 0];
        }

        if (!self::publicWizardInstallLocked($state) && self::publicWizardInstallSucceeded($state)) {
            $state['install_ready'] = true;
        }

        if (!self::publicWizardInstallSucceeded($state) && empty($state['install_run'])) {
            $state['install_ready'] = false;
        }

        if ($state['install_submit_token'] === '') {
            $state['install_submit_token'] = self::publicWizardIssueInstallToken();
        }

        if (!self::publicWizardWorkspaceHomeAllowed($state)) {
            $state['data']['workspace_home'] = self::publicWizardDefaultWorkspaceHome($state);
        }

        return $state;
    }

    /**
     * @param array<string,mixed>
     */
    private static function publicWizardResolvedStep(array $state, string $requestedStep = ''): string
    {
        $requestedStep = trim($requestedStep);
        if ($requestedStep === '') {
            return self::publicWizardNextRequiredStep($state);
        }

        $requestedStep = self::publicWizardNormalizeStep($requestedStep);

        if (!self::publicWizardCanOpenStep($state, $requestedStep)) {
            return self::publicWizardMaxSafeStep($state);
        }

        return $requestedStep;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardNextRequiredStep(array $state): string
    {
        if (empty($state['started'])) {
            return 'welcome';
        }
        if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
            return 'readiness';
        }
        if (!self::publicWizardDbValidationMatches($state)) {
            return 'database';
        }
        if (!self::publicWizardPlatformValid($state)) {
            return 'core_install';
        }
        if (!self::publicWizardInstallSucceeded($state)) {
            return 'admin';
        }
        return 'verify';
    }

    /**
     * @param array<string,mixed>
     */
    private static function publicWizardMaxSafeStep(array $state): string
    {
        if (empty($state['started'])) {
            return 'welcome';
        }
        if (!self::publicWizardReadinessPassed((array)($state['readiness'] ?? []))) {
            return 'readiness';
        }
        if (!self::publicWizardDbValidationMatches($state)) {
            return 'database';
        }
        if (!self::publicWizardPlatformValid($state)) {
            return 'core_install';
        }
        if (!self::publicWizardInstallSucceeded($state)) {
            return 'admin';
        }
        return 'verify';
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function publicWizardTwoFaValid(array $data): bool
    {
        return true;
    }

    private static function publicWizardNormalizeStep(string $step): string
    {
        $step = trim($step);
        $legacyAliases = [
            'admin_2fa' => 'setup_2fa',
            'platform' => 'core_install',
            'provision' => 'core_install',
            'finish' => 'verify',
        ];
        $step = $legacyAliases[$step] ?? $step;

        return in_array($step, self::PUBLIC_WIZARD_STEPS, true) ? $step : 'welcome';
    }

    private static function publicWizardStepIndex(string $step): int
    {
        $index = array_search(self::publicWizardNormalizeStep($step), self::PUBLIC_WIZARD_STEPS, true);
        return $index === false ? 0 : (int)$index;
    }

    /**
     * @return array{ok:bool,png_bytes:string,data_uri:string,error:string}
     */
    private static function publicWizardTwoFaQrPayload(): array
    {
        $email = trim((string)($_SESSION['setup_email'] ?? ''));
        $secret = trim((string)($_SESSION['setup_secret'] ?? ''));
        if ($email === '' || $secret === '') {
            return [
                'ok' => false,
                'png_bytes' => '',
                'data_uri' => '',
                'error' => '',
            ];
        }

        return TwoFactorQrService::buildPayload(\App\Core\TOTP::provisioningUri(\App\Core\TOTP::defaultIssuer(), $email, $secret));
    }

    /**
     * @param array<string,mixed> $preflight
     * @return array<string,mixed>
     */
    private static function buildPublicReadiness(array $preflight): array
    {
        $checks = [];
        foreach ((array)($preflight['environment'] ?? []) as $row) {
            $rawLabel = (string)($row['label'] ?? 'Environment check');
            $rawDetail = (string)($row['detail'] ?? '');
            $checks[] = [
                'label' => self::publicWizardReadinessEnvironmentLabel($rawLabel),
                'detail' => self::publicWizardReadinessEnvironmentDetail($rawLabel, $rawDetail),
                'status' => !empty($row['ok']) ? 'passed' : 'failed',
                'required' => true,
            ];
        }

        foreach ((array)($preflight['writable_paths'] ?? []) as $row) {
            $checks[] = [
                'label' => (string)t('setup.readiness.check.writable_path_label'),
                'detail' => (string)($row['path'] ?? ''),
                'status' => !empty($row['ok']) ? 'passed' : 'failed',
                'required' => true,
            ];
        }

        $databaseMessage = self::publicWizardReadinessDatabaseDetail((array)($preflight['database'] ?? []));
        $checks[] = [
            'label' => (string)t('setup.readiness.check.database_configuration_label'),
            'detail' => $databaseMessage,
            'status' => !empty($preflight['database']['ok']) ? 'passed' : 'warning',
            'required' => false,
        ];

        return [
            'checks' => $checks,
            'can_continue' => self::publicWizardReadinessPassed(['checks' => $checks]),
        ];
    }

    private static function publicWizardReadinessEnvironmentLabel(string $rawLabel): string
    {
        $normalized = strtolower(trim($rawLabel));
        return match ($normalized) {
            'mysqli extension' => (string)t('setup.readiness.check.extension_label', ['name' => 'mysqli']),
            'json extension' => (string)t('setup.readiness.check.extension_label', ['name' => 'json']),
            'zip extension' => (string)t('setup.readiness.check.extension_label', ['name' => 'zip']),
            default => $rawLabel,
        };
    }

    private static function publicWizardReadinessEnvironmentDetail(string $rawLabel, string $rawDetail): string
    {
        $normalizedLabel = strtolower(trim($rawLabel));
        $normalizedDetail = strtolower(trim($rawDetail));

        if (in_array($normalizedLabel, ['mysqli extension', 'json extension', 'zip extension'], true)) {
            return match ($normalizedDetail) {
                'loaded' => (string)t('setup.readiness.check.extension_loaded'),
                'missing' => (string)t('setup.readiness.check.extension_missing'),
                default => $rawDetail,
            };
        }

        return $rawDetail;
    }

    /**
     * @param array<string,mixed> $database
     */
    private static function publicWizardReadinessDatabaseDetail(array $database): string
    {
        if (!empty($database['ok'])) {
            return (string)t('setup.readiness.check.database_verified');
        }

        $message = trim((string)($database['message'] ?? ''));
        if ($message === '' || str_contains($message, 'Database setup happens in the next step')) {
            return (string)t('setup.readiness.check.database_next_step');
        }

        if (str_contains($message, 'Database configuration has not been written yet')) {
            return (string)t('setup.readiness.check.database_not_written');
        }

        return $message;
    }

    /**
     * @param array<string,mixed> $readiness
     */
    private static function publicWizardReadinessPassed(array $readiness): bool
    {
        $hasRequiredChecks = false;

        foreach ((array)($readiness['checks'] ?? []) as $check) {
            if (empty($check['required'])) {
                continue;
            }

            $hasRequiredChecks = true;
            if ((string)($check['status'] ?? 'warning') === 'failed') {
                return false;
            }
        }

        return $hasRequiredChecks;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardPlatformValid(array $state): bool
    {
        if (empty($state['platform_validated'])) {
            return false;
        }

        $data = (array)($state['data'] ?? []);
        return trim((string)($data['instance_name'] ?? '')) !== ''
            && trim((string)($data['language'] ?? '')) !== ''
            && trim((string)($data['number_format'] ?? '')) !== ''
            && trim((string)($data['currency'] ?? '')) !== ''
            && trim((string)($data['timezone'] ?? '')) !== ''
            && trim((string)($data['theme'] ?? '')) !== ''
            && self::publicWizardHasValidPlatformProfile($state)
            && self::publicWizardWorkspaceHomeAllowed($state)
            && trim((string)($data['workspace_home'] ?? '')) !== ''
            && self::publicWizardPlatformValidationMatches($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardPlatformValidationMatches(array $state): bool
    {
        $verified = is_array($state['verified_platform'] ?? null) ? (array)$state['verified_platform'] : [];
        if (empty($verified['ok']) || trim((string)($verified['config_hash'] ?? '')) === '') {
            return false;
        }

        return hash_equals(
            (string)$verified['config_hash'],
            self::publicWizardPlatformConfigHash((array)($state['data'] ?? []))
        );
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardVerifiedPlatformMetadata(array $state): array
    {
        return [
            'ok' => true,
            'config_hash' => self::publicWizardPlatformConfigHash((array)($state['data'] ?? [])),
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function publicWizardPlatformConfigHash(array $data): string
    {
        return hash('sha256', json_encode([
            'instance_name' => trim((string)($data['instance_name'] ?? '')),
            'language' => trim((string)($data['language'] ?? '')),
            'number_format' => trim((string)($data['number_format'] ?? '')),
            'currency' => trim((string)($data['currency'] ?? '')),
            'timezone' => trim((string)($data['timezone'] ?? '')),
            'theme' => trim((string)($data['theme'] ?? '')),
            'platform_profile' => strtolower(trim((string)($data['platform_profile'] ?? 'core_only'))),
            'workspace_home' => trim((string)($data['workspace_home'] ?? '')),
        ], JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|null
     */
    private static function publicWizardSelectedPlatformProfile(array $state): ?array
    {
        $profileKey = strtolower(trim((string)(($state['data'] ?? [])['platform_profile'] ?? 'core_only')));
        $profile = (new SetupProfileService())->platformProfile($profileKey);
        return is_array($profile) ? $profile : null;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardHasValidPlatformProfile(array $state): bool
    {
        return is_array(self::publicWizardSelectedPlatformProfile($state));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<int,string>
     */
    private static function publicWizardRequestedSuites(array $state): array
    {
        $profile = self::publicWizardSelectedPlatformProfile($state);
        if (!is_array($profile)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', array_keys((array)($profile['suites'] ?? [])))));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    private static function publicWizardHomeRouteOptions(array $state): array
    {
        $options = (new CompanySettingsService())->homeRouteOptions();
        $requestedSuites = self::publicWizardRequestedSuites($state);
        $allowedRoutes = ['/admin/setup', '/admin/apps', '/'];

        foreach (array_keys((new SuiteSetupService())->availableSuites()) as $suiteKey) {
            $suiteRoute = '/apps/' . strtolower(trim((string)$suiteKey));
            if (in_array($suiteRoute, $allowedRoutes, true)) {
                continue;
            }
            $allowedRoutes[] = $suiteRoute;
        }

        foreach ($requestedSuites as $suiteKey) {
            $suiteRoute = '/apps/' . strtolower(trim((string)$suiteKey));
            if (!in_array($suiteRoute, $allowedRoutes, true)) {
                $allowedRoutes[] = $suiteRoute;
            }
        }

        $filtered = [];
        foreach ($allowedRoutes as $route) {
            if (isset($options[$route])) {
                $filtered[$route] = (string)$options[$route];
                continue;
            }

            if (str_starts_with($route, '/apps/')) {
                $suiteKey = trim((string)substr($route, 6));
                if ($suiteKey !== '') {
                    $filtered[$route] = strtoupper(substr($suiteKey, 0, 1)) . substr($suiteKey, 1) . ' Workspace';
                }
            }
        }

        if ($filtered === []) {
            $filtered['/admin/setup'] = 'Setup Wizard';
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardWorkspaceHomeAllowed(array $state): bool
    {
        $workspaceHome = trim((string)(($state['data'] ?? [])['workspace_home'] ?? '/admin/setup'));
        if ($workspaceHome === '') {
            return false;
        }

        return isset(self::publicWizardHomeRouteOptions($state)[$workspaceHome]);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardDefaultWorkspaceHome(array $state): string
    {
        $options = self::publicWizardHomeRouteOptions($state);
        if (isset($options['/admin/setup'])) {
            return '/admin/setup';
        }

        return (string)array_key_first($options);
    }

    /**
     * @param array<string,mixed> $suiteResult
     */
    private static function publicWizardAssertSuiteProvisioning(string $suiteKey, array $suiteResult): void
    {
        $verifyResult = (array)($suiteResult['verify'] ?? []);
        $verification = (array)($verifyResult['verification'] ?? []);
        $missingTables = (array)($verification['missing_tables'] ?? []);
        $schemaGaps = (array)($verification['schema_gaps'] ?? []);
        $dependencyGaps = (array)($verification['dependency_gaps'] ?? []);

        if ($missingTables === [] && $schemaGaps === [] && $dependencyGaps === []) {
            return;
        }

        throw new \RuntimeException(
            'Suite provisioning verification failed for ' . $suiteKey . '. Run setup repair for that package and retry installation.'
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function publicWizardAdminValid(array $data): bool
    {
        $email = strtolower(trim((string)($data['admin_email'] ?? '')));
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function publicWizardDbConfig(array $data): array
    {
        $host = trim((string)($data['db_host'] ?? ''));
        $port = (int)($data['db_port'] ?? 3306);
        $name = trim((string)($data['db_name'] ?? ''));
        $user = trim((string)($data['db_user'] ?? ''));
        $charset = trim((string)($data['db_charset'] ?? 'utf8mb4'));

        if ($host === '') {
            throw new \RuntimeException('Database host is required.');
        }
        if ($port <= 0) {
            throw new \RuntimeException('Database port must be a positive number.');
        }
        if ($name === '') {
            throw new \RuntimeException('Database name is required.');
        }
        if ($user === '') {
            throw new \RuntimeException('Database user is required.');
        }
        if ($charset === '') {
            throw new \RuntimeException('Database encoding is required.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'pass' => (string)($data['db_pass'] ?? ''),
            'charset' => $charset,
            'timezone' => trim((string)($data['timezone'] ?? 'Asia/Tokyo')),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardDbValidationMatches(array $state): bool
    {
        if (empty($state['db_test']['ok'])) {
            return false;
        }

        $verified = is_array($state['verified_db'] ?? null) ? (array)$state['verified_db'] : [];
        if (empty($verified['ok']) || trim((string)($verified['config_hash'] ?? '')) === '') {
            return false;
        }

        $data = (array)($state['data'] ?? []);
        foreach (self::PUBLIC_WIZARD_DB_FIELDS as $field) {
            if ($field === 'db_create_if_missing') {
                $current = !empty($data[$field]) && (string)$data[$field] === '1' ? '1' : '0';
                $saved = !empty($verified['create_database']) ? '1' : '0';
            } elseif ($field === 'db_port') {
                $current = (string)(int)($data[$field] ?? 3306);
                $saved = (string)(int)($verified['port'] ?? 3306);
            } elseif ($field === 'db_charset') {
                $current = trim((string)($data[$field] ?? 'utf8mb4'));
                $saved = trim((string)($verified['charset'] ?? 'utf8mb4'));
            } else {
                $current = trim((string)($data[$field] ?? ''));
                $verifiedKey = match ($field) {
                    'db_host' => 'host',
                    'db_name' => 'name',
                    'db_user' => 'user',
                    default => $field,
                };
                $saved = trim((string)($verified[$verifiedKey] ?? ''));
            }

            if ($current !== $saved) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $dbConfig
     */
    private static function publicWizardDbConfigHash(array $dbConfig, bool $createDatabase): string
    {
        return sha1(json_encode([
            'host' => (string)($dbConfig['host'] ?? ''),
            'port' => (int)($dbConfig['port'] ?? 3306),
            'name' => (string)($dbConfig['name'] ?? ''),
            'user' => (string)($dbConfig['user'] ?? ''),
            'pass' => (string)($dbConfig['pass'] ?? ''),
            'charset' => (string)($dbConfig['charset'] ?? 'utf8mb4'),
            'create' => $createDatabase,
        ], JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param array<string,mixed> $dbConfig
     * @return array<string,mixed>
     */
    private static function publicWizardVerifiedDbMetadata(array $dbConfig, bool $createDatabase): array
    {
        return [
            'ok' => true,
            'host' => trim((string)($dbConfig['host'] ?? '')),
            'port' => (int)($dbConfig['port'] ?? 3306),
            'name' => trim((string)($dbConfig['name'] ?? '')),
            'user' => trim((string)($dbConfig['user'] ?? '')),
            'charset' => trim((string)($dbConfig['charset'] ?? 'utf8mb4')),
            'create_database' => $createDatabase,
            'password_configured' => trim((string)($dbConfig['pass'] ?? '')) !== '',
            'config_hash' => self::publicWizardDbConfigHash($dbConfig, $createDatabase),
        ];
    }

    /**
     * @param array<string,mixed> $dbConfig
     * @param array<string,mixed> $state
     */
    private static function publicWizardSavedDbConfigMatchesVerified(array $dbConfig, array $state): bool
    {
        if (!self::publicWizardDbValidationMatches($state)) {
            return false;
        }

        $verified = is_array($state['verified_db'] ?? null) ? (array)$state['verified_db'] : [];
        $createDatabase = !empty($verified['create_database']);
        return hash_equals(
            (string)($verified['config_hash'] ?? ''),
            self::publicWizardDbConfigHash($dbConfig, $createDatabase)
        );
    }

    /**
     * @param mixed $lock
     * @return array<string,mixed>
     */
    private static function publicWizardNormalizeInstallLock(mixed $lock): array
    {
        if (!is_array($lock)) {
            return ['active' => false, 'started_at' => 0];
        }

        $startedAt = (int)($lock['started_at'] ?? 0);
        $isExpired = $startedAt > 0 && (time() - $startedAt) > self::PUBLIC_INSTALL_LOCK_TTL;
        return [
            'active' => !empty($lock['active']) && !$isExpired,
            'started_at' => $isExpired ? 0 : $startedAt,
        ];
    }

    /**
     * @return array{acquired:bool,handle:mixed}
     */
    private static function publicWizardAcquireGlobalInstallLock(): array
    {
        $dir = APP_ROOT . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !self::isDirWriteable($dir)) {
            return ['acquired' => false, 'handle' => null];
        }

        $path = $dir . '/public_setup_install.lock';
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) {
            return ['acquired' => false, 'handle' => null];
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            @fclose($handle);
            return ['acquired' => false, 'handle' => null];
        }

        return ['acquired' => true, 'handle' => $handle];
    }

    /**
     * @param array{acquired:bool,handle:mixed} $lock
     */
    private static function publicWizardReleaseGlobalInstallLock(array $lock): void
    {
        $handle = $lock['handle'] ?? null;
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardInstallLocked(array $state): bool
    {
        return !empty($state['install_lock']['active']);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardInstallSucceeded(array $state): bool
    {
        return !empty($state['install_ready'])
            && in_array((string)($state['install_run']['status'] ?? ''), ['verified', 'configured'], true);
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardInstallTerminal(array $state): bool
    {
        return in_array((string)($state['install_run']['status'] ?? ''), ['verified', 'configured', 'failed', 'partial', 'rolled_back'], true);
    }

    private static function publicWizardIssueInstallToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardRequireInstallToken(array $state, string $submittedToken): void
    {
        $expected = trim((string)($state['install_submit_token'] ?? ''));
        $submittedToken = trim($submittedToken);
        if ($expected === '' || $submittedToken === '' || !hash_equals($expected, $submittedToken)) {
            throw new \RuntimeException('This install action was already used or is no longer valid. Refresh the page and try again.');
        }
    }

    /**
     * @param array<string,mixed> $run
     */
    private static function publicWizardFailureSummary(array $run, \Throwable $fallback): string
    {
        $failedStep = is_array($run['failed_step'] ?? null) ? (array)$run['failed_step'] : [];
        $label = trim((string)($failedStep['step_label'] ?? 'Installation task'));
        $message = trim((string)($failedStep['error_text'] ?? (string)($run['error_text'] ?? '')));
        if ($message === '') {
            $message = trim($fallback->getMessage());
        }
        if ($message === '' || str_contains($message, "\n")) {
            $message = 'The installer stopped before completion. Review the task list and retry after fixing the underlying issue.';
        }
        return $label . ' failed: ' . $message;
    }

    private static function publicWizardUserErrorMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '' || str_contains($message, "\n") || str_contains($message, APP_ROOT)) {
            return 'Setup could not continue. Review the current step and try again.';
        }
        return $message;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardShouldWarnForStepRedirect(array $state, string $requestedStep, string $normalizedRequestedStep, string $resolvedStep): bool
    {
        if ($requestedStep === '' || $normalizedRequestedStep === '' || $normalizedRequestedStep === $resolvedStep) {
            return false;
        }

        return self::publicWizardStepIndex($normalizedRequestedStep) > self::publicWizardStepIndex(self::publicWizardMaxSafeStep($state));
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function publicWizardClearRedirectNotice(array $state): array
    {
        if ((string)($state['notice_type'] ?? '') === self::PUBLIC_WIZARD_NOTICE_STEP_REDIRECT) {
            $state['notice'] = '';
            $state['notice_type'] = '';
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardStepRedirectNotice(array $state, string $requestedStep, string $resolvedStep): string
    {
        if (empty($state['started'])) {
            return 'Your setup session has expired or has not started yet. Continue from Welcome.';
        }

        $label = self::STEP_LABELS[$resolvedStep] ?? ucfirst(str_replace('_', ' ', $resolvedStep));
        return 'That step is not available yet. Continue from ' . $label . '.';
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function publicWizardShouldCloseSessionAfterRender(array $state): bool
    {
        return (string)($state['current_step'] ?? '') === 'verify'
            && self::publicWizardInstallSucceeded($state);
    }

    /**
     * @return array<string,mixed>
     */
    private static function publicWizardLatestCoreRun(): array
    {
        try {
            $run = self::decorateRun((new SetupStateService())->latestRun('core', 'core') ?? []);
            if ((string)($run['status'] ?? '') === 'running') {
                $startedAt = strtotime((string)($run['started_at'] ?? '')) ?: 0;
                if ($startedAt > 0 && (time() - $startedAt) > self::PUBLIC_INSTALL_LOCK_TTL) {
                    $run['status'] = 'failed';
                    $run['status_label'] = self::STATUS_META['failed']['label'];
                    $run['status_tone'] = self::STATUS_META['failed']['tone'];
                    $run['error_text'] = 'A previous install run did not finish cleanly. Review the task list and retry.';
                }
            }
            return $run;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function flashPayload(): array
    {
        return [
            'ok' => (string)($_SESSION['setup_console_ok'] ?? ''),
            'err' => (string)($_SESSION['setup_console_err'] ?? ''),
            'result' => $_SESSION['setup_console_result'] ?? null,
        ];
    }

    private static function clearFlash(): void
    {
        unset($_SESSION['setup_console_ok'], $_SESSION['setup_console_err'], $_SESSION['setup_console_result']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function wizardCards(): array
    {
        $core = self::decorateStatus((new CoreSetupService())->statusSummary());
        $organization = (new OrganizationStatusService())->getSetupStatus();
        $organizationStatus = (string)($organization['status'] ?? 'not_configured');
        $organizationTone = match ($organizationStatus) {
            'configured' => 'color:#6df2a6',
            'needs_setup' => 'color:#ffd27d',
            default => 'color:#d7d7d7',
        };
        $suiteService = new SuiteSetupService();
        $suiteStatuses = array_map([self::class, 'decorateStatus'], array_values($suiteService->statusCards()));
        $modulePanels = array_map([self::class, 'decorateStatus'], (new ModuleLifecycleService())->panelRows());
        $suiteWarnings = array_sum(array_map(static fn(array $row): int => (int)($row['verification']['warnings'] ?? 0), $suiteStatuses));
        $moduleWarnings = count(array_filter($modulePanels, static fn(array $row): bool => (string)($row['status'] ?? '') === 'warning'));

        return [
            [
                'title' => (string)t('setup.overview.card.onboarding.title'),
                'subtitle' => (string)t('setup.overview.card.onboarding.subtitle'),
                'url' => '/admin/setup/onboarding',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.onboarding.next'),
                'summary' => (string)t('setup.overview.card.onboarding.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.core.title'),
                'subtitle' => (string)t('setup.overview.card.core.subtitle'),
                'url' => '/admin/setup/core',
                'status' => $core['status'],
                'status_label' => self::localizedSetupStatusLabel((string)($core['status'] ?? 'installed')),
                'status_tone' => $core['status_tone'],
                'next_action' => self::overviewCoreNextAction((string)($core['status'] ?? 'installed')),
                'summary' => (string)t('setup.overview.card.core.summary', [
                    'missing' => (string)($core['core_schema']['missing'] ?? 0),
                    'active' => (string)($core['platform_modules']['active'] ?? 0),
                ]),
            ],
            [
                'title' => (string)t('setup.overview.card.company.title'),
                'subtitle' => (string)t('setup.overview.card.company.subtitle'),
                'url' => '/ops/organization/company',
                'status' => $organizationStatus,
                'status_label' => self::localizedSetupStatusLabel($organizationStatus),
                'status_tone' => $organizationTone,
                'next_action' => self::overviewCompanyNextAction($organizationStatus),
                'summary' => (string)t('setup.overview.card.company.summary', [
                    'name' => ((string)($organization['name'] ?? '') !== '' ? (string)$organization['name'] : '-'),
                    'country' => ((string)($organization['country'] ?? '') !== '' ? (string)$organization['country'] : '-'),
                    'currency' => ((string)($organization['currency'] ?? '') !== '' ? (string)$organization['currency'] : '-'),
                ]),
            ],
            [
                'title' => (string)t('setup.overview.card.suites.title'),
                'subtitle' => (string)t('setup.overview.card.suites.subtitle'),
                'url' => '/admin/setup/suites',
                'status' => $suiteWarnings > 0 ? 'warning' : 'configured',
                'status_label' => self::localizedSetupStatusLabel($suiteWarnings > 0 ? 'warning' : 'configured'),
                'status_tone' => $suiteWarnings > 0 ? self::STATUS_META['warning']['tone'] : self::STATUS_META['configured']['tone'],
                'next_action' => $suiteWarnings > 0
                    ? (string)t('setup.overview.card.suites.next_warning')
                    : (string)t('setup.overview.card.suites.next_ready'),
                'summary' => (string)t('setup.overview.card.suites.summary', [
                    'count' => (string)count($suiteStatuses),
                    'warnings' => (string)$suiteWarnings,
                ]),
                'hint' => in_array((string)($organization['status'] ?? 'not_configured'), ['not_configured', 'needs_setup'], true)
                    ? (string)t('setup.overview.card.suites.hint')
                    : '',
            ],
            [
                'title' => (string)t('setup.overview.card.modules.title'),
                'subtitle' => (string)t('setup.overview.card.modules.subtitle'),
                'url' => '/admin/setup/modules',
                'status' => $moduleWarnings > 0 ? 'warning' : 'configured',
                'status_label' => self::localizedSetupStatusLabel($moduleWarnings > 0 ? 'warning' : 'configured'),
                'status_tone' => $moduleWarnings > 0 ? self::STATUS_META['warning']['tone'] : self::STATUS_META['configured']['tone'],
                'next_action' => $moduleWarnings > 0
                    ? (string)t('setup.overview.card.modules.next_warning')
                    : (string)t('setup.overview.card.modules.next_ready'),
                'summary' => (string)t('setup.overview.card.modules.summary', [
                    'count' => (string)count($modulePanels),
                    'warnings' => (string)$moduleWarnings,
                ]),
            ],
            [
                'title' => (string)t('setup.overview.card.health.title'),
                'subtitle' => (string)t('setup.overview.card.health.subtitle'),
                'url' => '/admin/setup/health',
                'status' => ($core['status'] === 'warning' || $suiteWarnings > 0 || $moduleWarnings > 0) ? 'warning' : 'configured',
                'status_label' => self::localizedSetupStatusLabel(($core['status'] === 'warning' || $suiteWarnings > 0 || $moduleWarnings > 0) ? 'warning' : 'configured'),
                'status_tone' => ($core['status'] === 'warning' || $suiteWarnings > 0 || $moduleWarnings > 0) ? self::STATUS_META['warning']['tone'] : self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.health.next'),
                'summary' => (string)t('setup.overview.card.health.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.upgrades.title'),
                'subtitle' => (string)t('setup.overview.card.upgrades.subtitle'),
                'url' => '/admin/setup/upgrades',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.upgrades.next'),
                'summary' => (string)t('setup.overview.card.upgrades.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.environment.title'),
                'subtitle' => (string)t('setup.overview.card.environment.subtitle'),
                'url' => '/admin/setup/environment',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.environment.next'),
                'summary' => (string)t('setup.overview.card.environment.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.config.title'),
                'subtitle' => (string)t('setup.overview.card.config.subtitle'),
                'url' => '/admin/setup/config',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.config.next'),
                'summary' => (string)t('setup.overview.card.config.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.release.title'),
                'subtitle' => (string)t('setup.overview.card.release.subtitle'),
                'url' => '/admin/setup/release',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.release.next'),
                'summary' => (string)t('setup.overview.card.release.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.dependencies.title'),
                'subtitle' => (string)t('setup.overview.card.dependencies.subtitle'),
                'url' => '/admin/setup/dependencies',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.dependencies.next'),
                'summary' => (string)t('setup.overview.card.dependencies.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.scaffolds.title'),
                'subtitle' => (string)t('setup.overview.card.scaffolds.subtitle'),
                'url' => '/admin/setup/scaffolds',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.scaffolds.next'),
                'summary' => (string)t('setup.overview.card.scaffolds.summary'),
            ],
            [
                'title' => (string)t('setup.overview.card.audit.title'),
                'subtitle' => (string)t('setup.overview.card.audit.subtitle'),
                'url' => '/admin/setup/audit',
                'status' => 'configured',
                'status_label' => self::localizedSetupStatusLabel('configured'),
                'status_tone' => self::STATUS_META['configured']['tone'],
                'next_action' => (string)t('setup.overview.card.audit.next'),
                'summary' => (string)t('setup.overview.card.audit.summary'),
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $profiles
     * @return array<string,array<string,mixed>>
     */
    private static function localizedOverviewPlatformProfiles(array $profiles): array
    {
        foreach ($profiles as $key => $profile) {
            $profiles[$key]['label'] = (string)t('setup.overview.profile.' . $key . '.label');
            $profiles[$key]['description'] = (string)t('setup.overview.profile.' . $key . '.description');
        }

        return $profiles;
    }

    private static function localizedSetupStatusLabel(string $status): string
    {
        return (string)t('setup.overview.status.' . $status);
    }

    private static function overviewCoreNextAction(string $status): string
    {
        return match ($status) {
            'warning' => (string)t('setup.overview.card.core.next_warning'),
            'installed' => (string)t('setup.overview.card.core.next_installed'),
            default => (string)t('setup.overview.card.core.next_ready'),
        };
    }

    private static function overviewCompanyNextAction(string $status): string
    {
        return match ($status) {
            'configured' => (string)t('setup.overview.card.company.next_configured'),
            'needs_setup' => (string)t('setup.overview.card.company.next_needs_setup'),
            default => (string)t('setup.overview.card.company.next_not_configured'),
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function decorateStatus(array $payload): array
    {
        $setupRun = is_array($payload['setup_run'] ?? null) ? self::decorateRun((array)$payload['setup_run']) : null;
        $status = trim((string)($payload['panel_status'] ?? ($payload['status'] ?? 'installed')));
        if (is_array($setupRun) && in_array((string)($setupRun['status'] ?? ''), ['failed', 'partial', 'running', 'pending', 'rolled_back'], true)) {
            $status = (string)$setupRun['status'];
        }
        $meta = self::STATUS_META[$status] ?? self::STATUS_META['installed'];
        $payload['status'] = $status;
        $payload['status_label'] = $meta['label'];
        $payload['status_tone'] = $meta['tone'];
        $payload['setup_run'] = $setupRun;
        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private static function completionPayload(): array
    {
        $suiteService = new SuiteSetupService();
        $suiteCards = array_map([self::class, 'decorateStatus'], array_values($suiteService->statusCards()));
        $modulesBySuite = (new ModuleLifecycleService())->modulesBySuite();
        $verification = (new InstallVerificationService())->verifyAll();

        $installedSuites = array_values(array_filter($suiteCards, static fn(array $row): bool => (string)($row['registry_status'] ?? 'uploaded') !== 'uploaded'));
        $activeModules = [];
        foreach ($modulesBySuite as $suiteKey => $modules) {
            foreach ($modules as $module) {
                if (!empty($module['installed']) && (string)($module['status'] ?? '') === 'active') {
                    $activeModules[] = [
                        'suite' => $suiteKey,
                        'name' => (string)($module['display_name'] ?? $module['name'] ?? ''),
                    ];
                }
            }
        }

        $links = [
            ['label' => '/ (Home)', 'url' => '/'],
            ['label' => '/ops/organization', 'url' => '/ops/organization'],
            ['label' => '/admin/apps', 'url' => '/admin/apps'],
            ['label' => '/ops/access-control', 'url' => '/ops/access-control'],
        ];
        foreach ($installedSuites as $suite) {
            $dashboard = match ((string)($suite['app_key'] ?? '')) {
                'sbaio' => '/apps/sbaio',
                'manufacturing' => '/apps/manufacturing',
                default => '',
            };
            if ($dashboard !== '') {
                $links[] = [
                    'label' => ucfirst((string)($suite['app_key'] ?? '')) . ' dashboard',
                    'url' => $dashboard,
                ];
            }
        }

        return [
            'installed_suites' => $installedSuites,
            'active_modules' => $activeModules,
            'warnings' => (array)($verification['warnings'] ?? []),
            'errors' => (array)($verification['errors'] ?? []),
            'recommended_next_actions' => [
                'Open Organization and confirm the company profile, branches, fiscal defaults, and branding.',
                'Sign in as the first admin and confirm access for each team.',
                'Open access control and assign roles before daily users start working.',
                'Open each installed suite dashboard and review any warning badges.',
            ],
            'links' => $links,
        ];
    }

    private static function plainSuiteMessage(string $suiteKey, string $phase): string
    {
        return match ($phase) {
            'install' => ucfirst($suiteKey) . ' was installed. Next, apply the recommended settings.',
            'configure' => ucfirst($suiteKey) . ' was configured. Next, run the setup check.',
            'profile' => ucfirst($suiteKey) . ' preset applied. Review the verification results next.',
            'verify' => ucfirst($suiteKey) . ' check completed. Review any warnings shown below.',
            default => ucfirst($suiteKey) . ' setup step finished.',
        };
    }

    private static function plainModuleMessage(string $moduleName, string $operation): string
    {
        return match ($operation) {
            'install' => $moduleName . ' is now installed.',
            'enable', 'activate' => $moduleName . ' is now active.',
            'update' => $moduleName . ' was updated.',
            'repair' => $moduleName . ' was repaired.',
            'schema_sync' => $moduleName . ' schema was checked and refreshed.',
            'validate' => $moduleName . ' dependency check completed.',
            default => $moduleName . ' setup step finished.',
        };
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private static function decorateRun(array $run): array
    {
        $status = trim((string)($run['status'] ?? 'pending'));
        $meta = self::STATUS_META[$status] ?? self::STATUS_META['pending'];
        $run['status_label'] = $meta['label'];
        $run['status_tone'] = $meta['tone'];
        return $run;
    }

    private static function requireAdminToolsAccess(): void
    {
        Auth::bootSession();
        if (function_exists('base_require_admin_tools_access')) {
            base_require_admin_tools_access();
            return;
        }

        Auth::requireAdmin();
    }

    private static function persistEnvironmentUpload(string $tmpPath, string $token, string $sourceName): string
    {
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Upload failed. Please try again.');
        }

        $dir = APP_ROOT . '/storage/environment_clone_uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $sourceName) ?: 'environment_snapshot.zip';
        $target = $dir . '/' . $token . '_' . $safeName;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException('Could not store the uploaded environment snapshot.');
        }

        return $target;
    }

    /**
     * Cross-platform directory writability check.
     *
     * Falls back to a temp-file probe when is_writable() is unreliable
     * (common on Windows with ACL-based permissions).
     */
    private static function isDirWriteable(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        if (is_writable($dir)) {
            return true;
        }

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $probe = rtrim(str_replace('\\', '/', $dir), '/') . '/.wtmp_' . bin2hex(random_bytes(4));
            if (@touch($probe)) {
                @unlink($probe);
                return true;
            }
        }

        return false;
    }
}
