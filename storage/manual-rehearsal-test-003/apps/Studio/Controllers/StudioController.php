<?php
declare(strict_types=1);

namespace Apps\Studio\Controllers;

use App\Core\Auth;
use App\Core\Router;
use App\Core\View;
use Apps\Studio\Services\AppStudioRegistryService;
use Apps\Studio\Services\GuiStudioService;
use Apps\Studio\Services\StudioDataContractService;
use Apps\Studio\Services\StudioDependencyGraphService;
use Apps\Studio\Services\StudioGovernedToolRegistryService;
use Apps\Studio\Services\StudioToolPresentationService;
use Apps\Studio\Services\StudioToolInstancePolicyService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorPlaceholderService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorCssSourceService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorPreviewFeedService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorPreviewSanitizer;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorSourceResolver;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorStyleSourceService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorTargetProvider;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorTargetService;
use Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services\CssLiveEditorTemplateTargetService;
use Apps\Studio\Tools\CustomizationStudio\Controllers\VisualCustomizerController;
use Apps\Studio\Tools\CustomizationStudio\Effects\SpecialEffects\Application\SpecialEffectsRegistryService;
use Apps\Shell\Services\AppearanceReaderComparisonService;
use Apps\Shell\Services\AppearanceReaderInventoryService;
use Apps\Shell\Services\AppearanceStateResolver;
use Apps\Shell\Services\ThemePreferenceService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services\ThemeDoctorAnalyzer;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services\ThemeRegistryReaderService;
use Apps\Studio\Tools\CustomizationStudio\DesignSystem\DesignTokenEditor\Services\CssTokenEditorSaveService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerDiscoveryService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerDataSourceDiscoveryService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerContextCreateService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerTemplatePreviewService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerResourceReadinessService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerTemplateCreateService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerPreviewRendererService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerResourceMetadataService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuleSandboxService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuleCreateService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerResourceDiagnosticsService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerMetadataMigrationService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuntimeDryRunValidator;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerContextEditService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerTemplateEditService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerRuleEditService;
use Apps\Studio\Tools\LabelDesigner\Services\LabelDesignerDuplicateService;
use Apps\Studio\Tools\LabelDesigner\ValueObjects\ResolvedLabelPreview;
use Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceHubService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceProvisioningService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageAssignmentService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureArtifactClassifierService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureContractV2DiagnosisService;
use Apps\Studio\Tools\OwnerStructureScan\Services\EngineeringWorkspaceArtifactService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureFilesystemScannerService;
use Apps\Studio\Tools\OwnerStructureScan\Services\EngineeringWorkspaceInitializationService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureMigrationPlannerService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureReferenceDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureScanSectionGuardService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\InlineMigrationPlannerService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanExtractionScanner;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanCorrectionService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\RollbackService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\HistoryStore;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\FullSystemScanService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\InlineMigrationApplyService;
use Apps\Studio\Tools\LocalizationStudio\Services\LocalizationStudioDiscoveryService;
use Apps\Studio\Tools\LocalizationStudio\Services\LocalizationStudioEditService;
use Apps\Studio\Tools\ReportDesigner\Services\ReportDesignerSourceCatalogService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceRepairReadinessService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceRepairEngineService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceScannerService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceGuardedRepairCapabilityService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceDeterministicFixOneService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleCompliancePresentationSummaryService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\TokenImpactExplorer\Services\TokenImpactDiscoveryService;
use Platform\Reports\ReportDefinitionRepository;
use Platform\Reports\ReportDefinitionValidator;
use Platform\Engineering\EngineeringWorkspaceAgentDispatcher;
use Platform\Security\MarkdownRenderer;
use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspacePageContextResolver;
use Platform\Security\EngineeringWorkspaceResolver;
use Platform\Security\PlatformAuthority;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
require_once APP_ROOT . '/apps/Studio/Services/AppStudioRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDataContractService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDependencyGraphService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioGovernedToolRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioToolPresentationService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioToolInstancePolicyService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorPlaceholderService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorCssSourceService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorPreviewFeedService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorPreviewSanitizer.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorSourceResolver.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorStyleSourceService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTargetProvider.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTargetService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTemplateRenderer.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorTemplateTargetService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Controllers/VisualCustomizerController.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Services/ThemeDoctorAnalyzer.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Services/ThemeRegistryReaderService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Services/CssTokenEditorSaveService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerDataSourceDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerContextCreateService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerTemplatePreviewService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerPreviewRendererService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerResourceMetadataService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuleSandboxService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuleCreateService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuntimeDryRunValidator.php';
require_once APP_ROOT . '/apps/Studio/Tools/LabelDesigner/ValueObjects/ResolvedLabelPreview.php';
require_once APP_ROOT . '/apps/Studio/Tools/HelperTool/Services/RepoTreeScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceHubService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceProvisioningService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureFilesystemScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureArtifactClassifierService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureContractV2DiagnosisService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureMigrationPlannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureReferenceDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/EngineeringWorkspaceArtifactService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/EngineeringWorkspaceInitializationService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureScanSectionGuardService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioEditService.php';
require_once APP_ROOT . '/apps/Studio/Tools/ReportDesigner/Services/ReportDesignerDbDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/ReportDesigner/Services/ReportDesignerSourceCatalogService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/TokenImpactExplorer/Services/TokenImpactDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairReadinessService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairEngineService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceDeterministicFixOneService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleCompliancePresentationSummaryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanExtractionScanner.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanCorrectionService.php';
require_once APP_ROOT . '/platform/Reports/ReportDefinitionValidator.php';
require_once APP_ROOT . '/platform/Reports/ReportDefinitionRepository.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentPreflightService.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentBootstrap.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentExecutionGate.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentDispatcher.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspacePageContextResolver.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspace/Services/WorkTaskParser.php';

final class StudioController
{
    public static function index(View $view, Router $router): void
    {
        self::guardPlatformAdmin('/apps/studio');

        $view->render('studio::pages/home.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'studioHomeCards' => self::homeCards(),
        ]);
    }

    public static function legacy(View $view, Router $router): void
    {
        self::guardPlatformAdmin('/apps/studio/legacy');

        $flash = (string)($_SESSION['ops_gui_studio_flash'] ?? '');
        $error = (string)($_SESSION['ops_gui_studio_error'] ?? '');
        $result = isset($_SESSION['ops_gui_studio_result']) && is_array($_SESSION['ops_gui_studio_result'])
            ? $_SESSION['ops_gui_studio_result']
            : null;
        $inputs = isset($_SESSION['ops_gui_studio_inputs']) && is_array($_SESSION['ops_gui_studio_inputs'])
            ? $_SESSION['ops_gui_studio_inputs']
            : [];
        unset($_SESSION['ops_gui_studio_flash'], $_SESSION['ops_gui_studio_error'], $_SESSION['ops_gui_studio_result'], $_SESSION['ops_gui_studio_inputs']);

        $templates = GuiStudioService::loadTemplates();
        foreach ($templates as $key => $value) {
            if (!isset($inputs[$key]) || !is_string($inputs[$key])) {
                $inputs[$key] = $value;
            }
        }

        $globalLibraryContext = self::buildGlobalLibraryContext($router, is_array($_GET) ? $_GET : []);
        $studioDataContract = self::buildStudioDataContractContext($inputs);
        $studioDependencyGraph = self::buildStudioDependencyGraphContext($inputs, $studioDataContract);

        $view->render('studio::pages/legacy_gui_studio.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'csrf' => Auth::csrfToken(),
            'flash' => $flash,
            'error' => $error,
            'result' => $result,
            'inputs' => $inputs,
            'studioProject' => GuiStudioService::studioProjectModel(),
            'templateLibrary' => GuiStudioService::templateLibrary(),
            'draftLifecycle' => GuiStudioService::draftLifecycle(),
            'appLifecycleEntries' => GuiStudioService::generatedAppLifecycleEntries(),
            'generatedLibraryEntries' => GuiStudioService::listGeneratedAppsWithModules(),
            'libraryInspectorBundle' => null,
            'studioMode' => 'create_new',
            'globalLibrary' => $globalLibraryContext['library'] ?? [],
            'globalLibrarySelected' => $globalLibraryContext['selected'] ?? null,
            'globalLibrarySelectedId' => $globalLibraryContext['selected_id'] ?? '',
            'studioDataContract' => $studioDataContract,
            'studioDependencyGraph' => $studioDependencyGraph,
        ]);
    }

    public static function toolPage(View $view, string $intendedUrl, string $toolManifestPath): void
    {
        self::guardPlatformAdmin($intendedUrl);

        $toolModel = [];
        if (is_file($toolManifestPath)) {
            $loaded = require $toolManifestPath;
            if (is_array($loaded)) {
                $toolModel = $loaded;
            }
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolModel' => $toolModel,
        ]);
    }

    public static function reportDesignerPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/report-designer');

        $user = Auth::user();
        $userDisplayName = trim((string)($user['display_name'] ?? ''));
        if ($userDisplayName === '') {
            $userDisplayName = trim((string)($user['email'] ?? 'User'));
        }
        $companySettings = (new \App\Services\CompanySettingsService())->currentSettings();
        $companyName = (string)($companySettings['company.name'] ?? '');
        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $branchCode = (string)($ctx['scope']['branch_code'] ?? '');
        $flash = isset($_SESSION['studio_report_designer_flash']) && is_array($_SESSION['studio_report_designer_flash'])
            ? $_SESSION['studio_report_designer_flash']
            : [];
        unset($_SESSION['studio_report_designer_flash']);

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/ReportDesigner/Views/index.php',
            'reportDesignerModel' => [
                'source_catalog' => ReportDesignerSourceCatalogService::catalog(),
                'saved_reports' => ReportDefinitionRepository::all(),
                'definition_diagnostics' => ReportDefinitionRepository::diagnostics(),
                'flash' => $flash,
                'csrf' => Auth::csrfToken(),
                'org_metadata' => [
                    'company' => $companyName,
                    'branch' => $branchCode,
                    'fiscal_period' => '',
                    'current_user' => $userDisplayName,
                ],
            ],
        ]);
    }

    public static function reportDesignerSaveDefinition(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/report-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/report-designer');

        $rawDefinition = trim((string)($_POST['report_definition'] ?? ''));
        $definition = json_decode($rawDefinition, true);
        $originalKey = trim((string)($_POST['original_report_key'] ?? ''));
        if (!is_array($definition)) {
            $result = ['ok' => false, 'errors' => ['Invalid report definition payload.']];
        } else {
            if (trim((string)($definition['report_key'] ?? '')) === '') {
                $definition['report_key'] = ReportDefinitionValidator::generateKey(
                    (string)($definition['report_name'] ?? '')
                );
            }
            $result = ReportDefinitionRepository::save($definition, $originalKey);
        }
        $submittedKey = is_array($definition) ? (string)($definition['report_key'] ?? '') : '';

        $_SESSION['studio_report_designer_flash'] = [
            'type' => !empty($result['ok']) ? 'success' : 'error',
            'message' => !empty($result['ok']) ? 'Definition saved successfully.' : 'Definition could not be saved.',
            'errors' => isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [],
            'report_key' => (string)($result['definition']['report_key'] ?? $submittedKey),
            'path' => (string)($result['path'] ?? ''),
            'snapshot' => (string)($result['snapshot'] ?? ''),
        ];

        header('Location: /apps/studio/tools/report-designer?workspace=save#rd-workspace-content');
        exit;
    }

    public static function governedToolPlaceholder(View $view, string $toolKey, string $intendedUrl): void
    {
        self::guardPlatformAdmin($intendedUrl);

        $tool = StudioGovernedToolRegistryService::findTool($toolKey);
        if ($tool === null || empty($tool['placeholder'])) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $decorated = StudioToolPresentationService::decorateTools([$tool]);
        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolModel' => $decorated[0] ?? $tool,
            'studioToolLocale' => StudioToolPresentationService::locale(),
        ]);
    }

    public static function cssLiveEditorPlaceholder(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/advanced/css-live-editor');

        $themeOptions = self::resolveCssLiveEditorThemeOptions();
        $selection = CssLiveEditorTargetService::resolveSelection(
            (string)($_GET['target_id'] ?? ''),
            (string)($_GET['target'] ?? ''),
            (string)($_GET['template_target_id'] ?? '')
        );
        $selectedTarget = is_array($selection['target'] ?? null) ? $selection['target'] : [];
        $providerTargets = CssLiveEditorTargetProvider::targets();
        $templateTargets = CssLiveEditorTemplateTargetService::targets();
        $selectedTargetId = trim((string)($selectedTarget['id'] ?? ''));
        $runtimeTheme = $themeOptions['runtime_preference'];
        $previewFrameUrl = !empty($selectedTarget['eligible'])
            ? '/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame?target_id=' . rawurlencode($selectedTargetId)
                . '&feed=' . rawurlencode((string)($selectedTarget['adapter_id'] ?? ''))
                . '&theme=' . rawurlencode($runtimeTheme)
            : '';

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/cssliveditor.php',
            'cssLiveEditorModel' => CssLiveEditorPlaceholderService::model([
                'target' => (string)($_GET['target'] ?? ($selectedTarget['route'] ?? '')),
                'target_error' => (string)($selection['error'] ?? ''),
                'target_options' => $providerTargets,
                'template_options' => $templateTargets,
                'css_source_resolution_catalog' => CssLiveEditorCssSourceService::catalog(
                    array_merge($providerTargets, $templateTargets)
                ),
                'selected_target' => $selectedTarget,
                'preview_frame_url' => $previewFrameUrl,
                'source_owner' => (string)($selectedTarget['owner'] ?? ''),
                'source_target' => (string)($selectedTarget['source_hint'] ?? ''),
                'css_target' => '',
                'sanitizer_policy' => CssLiveEditorPreviewSanitizer::policy(),
                'style_source_catalog' => CssLiveEditorStyleSourceService::catalog(),
                'theme_options' => $themeOptions['options'],
                'theme_option_provider_available' => $themeOptions['available'],
                'runtime_theme_preference' => $themeOptions['runtime_preference'],
            ]),
        ]);
    }

    public static function cssLiveEditorPreviewFrame(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame');

        $targetId = (string)($_GET['target_id'] ?? '');
        $target = CssLiveEditorTargetProvider::find($targetId)
            ?? CssLiveEditorTemplateTargetService::find($targetId);
        $feed = trim((string)($_GET['feed'] ?? ''));
        if ($target === null || empty($target['eligible']) || $feed === '' || $feed !== (string)($target['adapter_id'] ?? '')) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Unavailable preview feed.';
            return;
        }

        header('Cache-Control: no-store, max-age=0');
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");

        $theme = trim((string)($_GET['theme'] ?? ''));
        $themeServicePath = APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';
        if (is_file($themeServicePath)) {
            require_once $themeServicePath;
        }
        if (class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')) {
            $theme = \Apps\Shell\Services\ThemePreferenceService::normalizePreference($theme);
        }

        echo CssLiveEditorPreviewFeedService::render($target, $theme);
    }

    public static function workflowPage(View $view, string $intendedUrl, string $workflowStage): void
    {
        self::guardPlatformAdmin($intendedUrl);

        $view->render('studio::pages/workflow_stage.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'workflowStage' => $workflowStage,
        ]);
    }

    public static function themeToolPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/theme-doctor');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Views/preview.php',
            'themeToolPreviewModel' => self::buildThemeToolPreviewModel(),
        ]);
    }

    public static function customizationStudioLanding(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Views/landing.php',
            'customizationStudioLandingModel' => self::buildCustomizationStudioLandingModel(),
        ]);
    }

    public static function visualCustomizerPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => VisualCustomizerController::viewPath(),
            'vcModel' => VisualCustomizerController::placeholderModel(),
        ]);
    }

    public static function customizationStudioSpecialEffects(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/effects');

        $theme = isset($_GET['theme']) ? (string)$_GET['theme'] : null;
        $effectsEnabled = !isset($_GET['effects']) || (string)$_GET['effects'] !== '0';
        $motionMode = isset($_GET['motion']) ? (string)$_GET['motion'] : 'system';
        $scanScope = isset($_GET['scan_scope']) ? (string)$_GET['scan_scope'] : 'owner';
        $scanOwner = isset($_GET['scan_owner']) ? (string)$_GET['scan_owner'] : 'Studio';

        $systemDefault = '';
        $shellAppearanceState = [];
        try {
            $systemDefault = ThemePreferenceService::defaultPreference();
            $currentUserIdAvailable = (bool)(Auth::isLoggedIn() ? (Auth::user()['id'] ?? false) : false);
            $resolvedSurface = (string)($_GET['_surface'] ?? 'admin');
            $shellAppearanceState = AppearanceStateResolver::resolve([
                'surface' => $resolvedSurface,
                'system_default_combined_mode' => $systemDefault,
                'current_user_id_available' => $currentUserIdAvailable,
            ]);
        } catch (\Throwable $e) {
            $shellAppearanceState = [
                'appearance_contract_version' => 'error',
                'error' => $e->getMessage(),
            ];
        }
        $shellAppearanceState['browser_normalization_map'] = AppearanceStateResolver::browserNormalizationMap();

        $model = SpecialEffectsRegistryService::buildWorkspaceModel($theme, $effectsEnabled, $motionMode, $scanScope, $scanOwner, $_GET);
        $model['shell_appearance_state'] = $shellAppearanceState;

        // Auth server-render parallel comparison.
        $authComparison = [];
        $authEvidenceLedger = [];
        $authComparisonInventory = [];
        $authComparisonInventoryFolded = [];
        try {
            $authMode = $systemDefault;
            $authSource = 'ThemePreferenceService::defaultPreference()';
            $authComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
                'auth_legacy_mode' => $authMode,
                'auth_resolution_source' => $authSource,
                'system_default' => $systemDefault,
                'evidence_basis' => 'source_contract',
            ]);
            $authEvidenceLedger = AppearanceReaderComparisonService::authParityEvidenceLedger();

            $authComparisonInventory = AppearanceReaderInventoryService::inventoryWithComparison($authComparison, $authEvidenceLedger);
            $authComparisonInventoryFolded = $authComparison;
        } catch (\Throwable $e) {
            $authComparison = [
                'status' => 'unknown',
                'reason' => 'Comparison computation failed: ' . $e->getMessage(),
                'diagnostic_only' => true,
                'cutover_status' => 'not_ready',
                'browser_override_included' => false,
                'browser_effective_state_claimed' => false,
            ];
            $authEvidenceLedger = [];
            $authComparisonInventory = AppearanceReaderInventoryService::inventory();
            $authComparisonInventoryFolded = $authComparison;
        }
        $model['auth_server_comparison'] = $authComparison;
        $model['auth_parity_evidence_ledger'] = $authEvidenceLedger;
        $model['auth_comparison_inventory'] = $authComparisonInventory;
        $model['auth_comparison_inventory_folded'] = $authComparisonInventoryFolded;

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Views/index.php',
            'specialEffectsModel' => $model,
        ]);
    }

    public static function customizationStudioSpecialEffectsPreview(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/effects/preview');

        header('Cache-Control: no-store, max-age=0');
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");

        echo SpecialEffectsRegistryService::renderPreviewDocument($_GET);
    }

    public static function visualCustomizerDraftUpdate(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleDraftUpdate();
    }

    public static function visualCustomizerRecheckReadiness(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleRecheckReadiness();
    }

    public static function visualCustomizerCreateApprovalRequest(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleCreateApprovalRequest();
    }

    public static function visualCustomizerViewRequest(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => VisualCustomizerController::requestDetailViewPath(),
            'vcModel' => VisualCustomizerController::requestDetailModel(),
        ]);
    }

    public static function visualCustomizerApproveRequest(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleApproveRequest();
    }

    public static function visualCustomizerRejectRequest(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleRejectRequest();
    }

    public static function visualCustomizerCancelRequest(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleCancelRequest();
    }

    public static function visualCustomizerTakeSnapshot(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleTakeSnapshot();
    }

    public static function visualCustomizerApplyRequest(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/visual-customizer');
        VisualCustomizerController::handleApplyRequest();
    }

    public static function localizationStudioPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-studio');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Views/preview.php',
            'localizationStudioModel' => LocalizationStudioDiscoveryService::scan(),
        ]);
    }

    public static function helperToolPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/helper-tool');

        $scanRequested = ((string)($_GET['scan'] ?? '') === '1');
        $helperToolModel = [
            'scan_requested' => $scanRequested,
            'scan_result' => null,
        ];

        if ($scanRequested) {
            $helperToolModel['scan_result'] = RepoTreeScannerService::scanFromRoot();
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => 'Repository Scanner',
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/HelperTool/Views/preview.php',
            'helperToolModel' => $helperToolModel,
        ]);
    }

    public static function helperToolInspectFile(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/helper-tool');

        $path = (string)($_GET['path'] ?? '');
        $result = RepoTreeScannerService::inspectFileFromRoot($path);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function engineeringWorkspacesHub(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');

        $tab = (string)($_GET['tab'] ?? 'workspaces');
        if ($tab === 'provision') {
            $tab = 'deploy';
        }
        $hubModel = EngineeringWorkspaceHubService::buildModel($tab);
        $coverage = EngineeringWorkspaceCoverageService::buildCoverage();
        $hubModel['coverage'] = $coverage;
        if ($tab === 'deploy') {
            $hubModel['deploy_model'] = EngineeringWorkspaceProvisioningService::buildDeployModel($coverage);
        }
        if ($tab === 'coverage-assignment') {
            $assignments = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
            $hubModel['assignment_rows'] = EngineeringWorkspaceCoverageAssignmentService::buildAssignmentRows($coverage, $assignments);
            $hubModel['existing_workspaces'] = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();
            $hubModel['assignment_flash'] = (string)($_SESSION['studio_assignment_flash'] ?? '');
            unset($_SESSION['studio_assignment_flash']);
        }
        if ($tab === 'agent-context') {
            $hubModel['agent_context_existing_workspaces'] = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();
            $agentOwnerKey = trim((string)($_GET['owner_key'] ?? ''));
            $agentRequestedMode = (string)($_GET['requested_mode'] ?? '');
            $agentScopeMode = (string)($_GET['scope_mode'] ?? '');
            if ($agentOwnerKey !== '') {
                $hubModel['agent_context_request'] = [
                    'owner_key' => $agentOwnerKey,
                    'requested_mode' => $agentRequestedMode ?: 'read_only',
                    'scope_mode' => $agentScopeMode ?: 'owner',
                ];
                $hubModel['agent_context_result'] = EngineeringWorkspaceAgentDispatcher::dispatch(
                    'Prepared Engineering Workspace Agent Context',
                    $agentOwnerKey,
                    [],
                    ['role' => 'platform_admin'],
                    $agentRequestedMode ?: 'read_only',
                    $agentScopeMode ?: 'owner'
                );
            }
        }
        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => 'Engineering Workspaces',
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Views/preview.php',
            'engineeringWorkspaceHubModel' => $hubModel,
        ]);
    }

    public static function engineeringWorkspacesProvisionPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $selectedDocuments = isset($_POST['documents']) && is_array($_POST['documents'])
            ? array_map('trim', $_POST['documents'])
            : [];
        $lane = (string)($_POST['lane'] ?? '');

        if ($workspaceKey === '') {
            $_SESSION['studio_flash_error'] = 'Workspace key is required';
            header('Location: /apps/studio/tools/engineering-workspaces', true, 302);
            return;
        }

        $coverage = EngineeringWorkspaceCoverageService::buildCoverage();
        $plan = EngineeringWorkspaceProvisioningService::generatePlan(
            $workspaceKey,
            $selectedDocuments,
            $lane,
            $coverage,
            $_SESSION['actor'] ?? null
        );

        if (empty($plan['ok'])) {
            $_SESSION['studio_flash_error'] = $plan['error'] ?? 'Could not generate provisioning plan';
            header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
            return;
        }

        $preview = EngineeringWorkspaceProvisioningService::previewPlan($plan);

        if (empty($preview['ok'])) {
            $_SESSION['studio_flash_error'] = $preview['error'] ?? 'Could not preview provisioning plan';
            header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
            return;
        }

        // Store plan in session for apply
        $_SESSION['studio_provision_plan'] = $plan;
        $_SESSION['studio_provision_preview'] = $preview;

        $tab = 'provision';
        $hubModel = EngineeringWorkspaceHubService::buildModel($tab);
        $hubModel['coverage'] = $coverage;
        $hubModel['provision_model'] = EngineeringWorkspaceProvisioningService::buildProvisionModel($coverage);
        $hubModel['provision_plan'] = $plan;
        $hubModel['provision_preview'] = $preview;
        $hubModel['provision_flash'] = 'Preview generated. Review the plan below and confirm to apply.';

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => 'Engineering Workspaces',
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Views/preview.php',
            'engineeringWorkspaceHubModel' => $hubModel,
        ]);
    }

    public static function engineeringWorkspacesProvisionApply(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $plan = isset($_SESSION['studio_provision_plan']) && is_array($_SESSION['studio_provision_plan'])
            ? $_SESSION['studio_provision_plan']
            : null;

        if ($plan === null) {
            $_SESSION['studio_flash_error'] = 'No provisioning plan found. Please generate a preview first.';
            header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
            return;
        }

        $workspaceKey = $plan['workspace_key'] ?? '';
        $lane = $plan['lane'] ?? '';

        // For archive_and_reset, require typed confirmation
        if ($lane === 'archive_and_reset') {
            $typedConfirmation = trim((string)($_POST['reset_confirmation'] ?? ''));
            $expected = 'RESET ' . $workspaceKey;
            if ($typedConfirmation !== $expected) {
                $_SESSION['studio_flash_error'] = 'Type "RESET ' . $workspaceKey . '" to confirm archive and reset.';
                header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
                return;
            }
        }

        $result = EngineeringWorkspaceProvisioningService::applyPlan($plan, $_SESSION['actor'] ?? null);

        if (!empty($result['ok'])) {
            $_SESSION['studio_flash_success'] = 'Provisioning completed successfully. Transaction: '
                . ($result['transaction_id'] ?? 'unknown');
            $_SESSION['studio_provision_result'] = $result;
        } else {
            $_SESSION['studio_flash_error'] = $result['error'] ?? 'Provisioning failed';
            if (!empty($result['rollback_completed'])) {
                $_SESSION['studio_flash_error'] .= ' (rolled back)';
            }
        }

        // Clear plan after apply attempt
        unset($_SESSION['studio_provision_plan']);
        unset($_SESSION['studio_provision_preview']);

        header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
    }

    public static function engineeringWorkspacesProvisionRollback(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        if ($workspaceKey === '') {
            $_SESSION['studio_flash_error'] = 'Workspace key is required for rollback';
            header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
            return;
        }

        $result = EngineeringWorkspaceProvisioningService::rollbackTransaction(
            $workspaceKey,
            $_SESSION['actor'] ?? null
        );

        if (!empty($result['ok'])) {
            $opCount = count($result['operations'] ?? []);
            $_SESSION['studio_flash_success'] = 'Rolled back ' . (string)$opCount . ' operation(s) for "'
                . $workspaceKey . '". Transaction: ' . ($result['transaction_id'] ?? 'unknown');
        } else {
            $_SESSION['studio_flash_error'] = 'Rollback failed: ' . ($result['error'] ?? 'Unknown error');
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=provision', true, 302);
    }

    // ── Simplified Deploy — Create Missing Files ──

    public static function engineeringWorkspacesDeployCreate(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $selectedDocuments = isset($_POST['documents']) && is_array($_POST['documents'])
            ? array_map('trim', $_POST['documents'])
            : [];

        $result = EngineeringWorkspaceProvisioningService::createMissingDocuments(
            $workspaceKey,
            $selectedDocuments,
            $_SESSION['actor'] ?? null
        );

        if (!empty($result['ok'])) {
            $_SESSION['studio_flash_success'] = 'Created ' . (string)count($selectedDocuments) . ' missing document(s) for "' . $workspaceKey . '". Transaction: ' . ($result['transaction_id'] ?? '');
        } else {
            $_SESSION['studio_flash_error'] = $result['error'] ?? 'Create missing files failed';
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=deploy', true, 302);
    }

    // ── Simplified Deploy — Replace Selected Files ──

    public static function engineeringWorkspacesDeployReplace(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $selectedDocuments = isset($_POST['documents']) && is_array($_POST['documents'])
            ? array_map('trim', $_POST['documents'])
            : [];

        $result = EngineeringWorkspaceProvisioningService::replaceDocuments(
            $workspaceKey,
            $selectedDocuments,
            $_SESSION['actor'] ?? null
        );

        if (!empty($result['ok'])) {
            $_SESSION['studio_flash_success'] = 'Replaced ' . (string)count($selectedDocuments) . ' document(s) for "' . $workspaceKey . '". Transaction: ' . ($result['transaction_id'] ?? '');
        } else {
            $_SESSION['studio_flash_error'] = $result['error'] ?? 'Replace selected files failed';
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=deploy', true, 302);
    }

    // ── Simplified Deploy — Restore Last Backup ──

    public static function engineeringWorkspacesDeployRestore(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));

        $result = EngineeringWorkspaceProvisioningService::restoreLastBackup(
            $workspaceKey,
            $_SESSION['actor'] ?? null
        );

        if (!empty($result['ok'])) {
            $_SESSION['studio_flash_success'] = 'Restored ' . (string)($result['count'] ?? 0) . ' document(s) from backup for "' . $workspaceKey . '".';
        } else {
            $_SESSION['studio_flash_error'] = $result['error'] ?? 'Restore last backup failed';
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=deploy', true, 302);
    }

    // ── Simplified Deploy — New Workspace ──

    public static function engineeringWorkspacesNewWorkspace(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));

        $result = EngineeringWorkspaceProvisioningService::newWorkspace(
            $workspaceKey,
            $_SESSION['actor'] ?? null
        );

        if (!empty($result['ok'])) {
            $_SESSION['studio_flash_success'] = 'Workspace "' . $workspaceKey . '" created with ' . (string)($result['count'] ?? 0) . ' starter documents.';
        } else {
            $_SESSION['studio_flash_error'] = $result['error'] ?? 'New workspace creation failed';
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=deploy', true, 302);
    }

    // ── Simplified Deploy — Create Missing for All Applicable Owners ──

    public static function engineeringWorkspacesDeployCreateMissingAll(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        // Discover applicable workspace keys from owner catalogue + coverage + filesystem scan
        $ownerKeys = OwnerStructureOwnerDiscoveryService::discover();
        $coverage = EngineeringWorkspaceCoverageService::buildCoverage();
        $coverageRows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : null;

        $workspaceKeys = EngineeringWorkspaceProvisioningService::collectApplicableWorkspaceKeys($ownerKeys, $coverageRows);

        $result = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
            $workspaceKeys,
            $_SESSION['actor'] ?? null
        );

        $created = $result['created'] ?? [];
        $already = $result['already_complete'] ?? [];
        $failed = $result['failed'] ?? [];
        $skipped = $result['skipped'] ?? [];

        if ($failed === []) {
            $msg = 'Created missing documents for ' . count($created) . ' workspace(s). '
                . count($already) . ' already complete. '
                . ($skipped !== [] ? count($skipped) . ' skipped. ' : '')
                . ' Transaction batch completed.';
            $_SESSION['studio_flash_success'] = $msg;
        } else {
            $msg = 'Created: ' . count($created) . ', Already complete: ' . count($already)
                . ', Failed: ' . count($failed) . ', Skipped: ' . count($skipped) . '.';
            $detail = '';
            foreach ($failed as $f) {
                $detail .= ($detail !== '' ? '; ' : '') . ($f['workspace_key'] ?? '?') . ': ' . ($f['error'] ?? 'unknown');
            }
            $_SESSION['studio_flash_error'] = $msg . ' Failures: ' . $detail;
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=deploy', true, 302);
    }

    public static function engineeringWorkspacesCoverageAssign(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/engineering-workspaces');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/engineering-workspaces');

        $ownerKey = trim((string)($_POST['owner_key'] ?? ''));
        $mode = trim((string)($_POST['mode'] ?? ''));
        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));

        $existingWorkspaces = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();
        $result = EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
            $ownerKey,
            $mode,
            $workspaceKey,
            $_SESSION['actor'] ?? null,
            $existingWorkspaces
        );

        if (!empty($result['ok'])) {
            $_SESSION['studio_assignment_flash'] = 'Coverage assignment saved for "' . $ownerKey . '" → ' . $mode;
        } else {
            $_SESSION['studio_assignment_flash'] = 'Error: ' . ($result['error'] ?? 'Unknown error');
        }

        header('Location: /apps/studio/tools/engineering-workspaces?tab=coverage-assignment', true, 302);
    }

    public static function ownerStructureScanPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/owner-structure-scan');

        $scanRequested = ((string)($_GET['scan'] ?? '') === '1');
        $ownerKey = (string)($_GET['owner'] ?? '');
        $ownerResolution = OwnerStructureOwnerDiscoveryService::resolve($ownerKey);
        $selectedOwnerKey = (string)($ownerResolution['selected_owner_key'] ?? '');
        if ($selectedOwnerKey === '') {
            $scanRequested = false;
        }
        $ownerStructureScanModel = [
            'scan_requested' => $scanRequested,
            'scan_result' => null,
            'classification' => null,
            'contract_v2_diagnosis' => null,
            'migration_plan' => null,
            'core_owner_scan_section' => null,
            'engineering_workspace_artifacts_section' => null,
            'migration_plan_section' => null,
            'engineering_workspace_initialization_section' => null,
            'reference_discovery_section' => null,
            'engineering_workspace_initialization' => null,
            'engineering_workspace_initialization_result' => isset($_SESSION['studio_owner_structure_workspace_init_result']) && is_array($_SESSION['studio_owner_structure_workspace_init_result'])
                ? $_SESSION['studio_owner_structure_workspace_init_result']
                : null,
            'reference_discovery' => null,
            'owners' => isset($ownerResolution['owners']) && is_array($ownerResolution['owners']) ? $ownerResolution['owners'] : [],
            'selected_owner_key' => $selectedOwnerKey,
            'owner_error' => (string)($ownerResolution['error'] ?? ''),
            'csrf' => Auth::csrfToken(),
        ];
        unset($_SESSION['studio_owner_structure_workspace_init_result']);

        if ($scanRequested) {
            $selectedOwner = isset($ownerResolution['selected_owner']) && is_array($ownerResolution['selected_owner']) ? $ownerResolution['selected_owner'] : [];
            $coreOwnerScanSection = OwnerStructureScanSectionGuardService::safeSection(
                'core_owner_scan',
                static function () use ($selectedOwnerKey): array {
                    $scanResult = OwnerStructureFilesystemScannerService::scan($selectedOwnerKey);
                    $entries = isset($scanResult['entries']) && is_array($scanResult['entries']) ? $scanResult['entries'] : [];

                    return [
                        'scan_result' => $scanResult,
                        'classification' => OwnerStructureArtifactClassifierService::classify($entries),
                        'contract_v2_diagnosis' => OwnerStructureContractV2DiagnosisService::diagnose($scanResult),
                    ];
                },
                'OSS_CORE_SCAN_FAILED',
                'Owner structure scan could not complete.'
            );
            $ownerStructureScanModel['core_owner_scan_section'] = $coreOwnerScanSection;

            if ((string)($coreOwnerScanSection['status'] ?? '') === 'ready') {
                $coreData = isset($coreOwnerScanSection['data']) && is_array($coreOwnerScanSection['data']) ? $coreOwnerScanSection['data'] : [];
                $ownerStructureScanModel['scan_result'] = isset($coreData['scan_result']) && is_array($coreData['scan_result']) ? $coreData['scan_result'] : null;
                $ownerStructureScanModel['classification'] = isset($coreData['classification']) && is_array($coreData['classification']) ? $coreData['classification'] : null;
                $ownerStructureScanModel['contract_v2_diagnosis'] = isset($coreData['contract_v2_diagnosis']) && is_array($coreData['contract_v2_diagnosis']) ? $coreData['contract_v2_diagnosis'] : null;
            }

            $engineeringWorkspaceArtifactsSection = (string)($coreOwnerScanSection['status'] ?? '') === 'ready'
                ? OwnerStructureScanSectionGuardService::safeSection(
                    'engineering_workspace_artifacts',
                    static fn (): array => EngineeringWorkspaceArtifactService::resolveArtifacts($selectedOwnerKey),
                    'EWS_ARTIFACT_SERVICE_FAILED',
                    'Engineering Workspace artifact scan could not complete.'
                )
                : OwnerStructureScanSectionGuardService::emptySection('OSS_CORE_SCAN_REQUIRED', 'Owner structure scan must complete before Engineering Workspace artifacts can be checked.');
            $ownerStructureScanModel['engineering_workspace_artifacts_section'] = $engineeringWorkspaceArtifactsSection;
            if ((string)($engineeringWorkspaceArtifactsSection['status'] ?? '') === 'ready') {
                $ownerStructureScanModel['engineering_workspace_artifacts'] = isset($engineeringWorkspaceArtifactsSection['data']) && is_array($engineeringWorkspaceArtifactsSection['data'])
                    ? $engineeringWorkspaceArtifactsSection['data']
                    : [];
            }

            $migrationPlanSection = (
                (string)($coreOwnerScanSection['status'] ?? '') === 'ready'
                && (string)($engineeringWorkspaceArtifactsSection['status'] ?? '') === 'ready'
                && is_array($ownerStructureScanModel['scan_result'])
                && is_array($ownerStructureScanModel['classification'])
                && is_array($ownerStructureScanModel['contract_v2_diagnosis'])
            )
                ? OwnerStructureScanSectionGuardService::safeSection(
                    'migration_plan',
                    static fn (): array => OwnerStructureMigrationPlannerService::plan(
                        $selectedOwner,
                        $ownerStructureScanModel['scan_result'],
                        $ownerStructureScanModel['classification'],
                        $ownerStructureScanModel['contract_v2_diagnosis'],
                        $ownerStructureScanModel['engineering_workspace_artifacts']
                    ),
                    'OSS_MIGRATION_PLANNER_FAILED',
                    'Migration planner could not complete.'
                )
                : OwnerStructureScanSectionGuardService::emptySection('OSS_MIGRATION_PREREQUISITE_UNAVAILABLE', 'Migration planner is waiting for required scan sections.');
            $ownerStructureScanModel['migration_plan_section'] = $migrationPlanSection;
            if ((string)($migrationPlanSection['status'] ?? '') === 'ready') {
                $ownerStructureScanModel['migration_plan'] = isset($migrationPlanSection['data']) && is_array($migrationPlanSection['data'])
                    ? $migrationPlanSection['data']
                    : [];
            }

            $engineeringWorkspaceInitializationSection = (string)($migrationPlanSection['status'] ?? '') === 'ready'
                ? OwnerStructureScanSectionGuardService::safeSection(
                    'engineering_workspace_initialization',
                    static fn (): array => EngineeringWorkspaceInitializationService::buildInitializationPlan($selectedOwnerKey, $ownerStructureScanModel['migration_plan']),
                    'EWS_INITIALIZATION_READINESS_FAILED',
                    'Engineering Workspace initialization readiness could not complete.'
                )
                : OwnerStructureScanSectionGuardService::emptySection('EWS_INITIALIZATION_PREREQUISITE_UNAVAILABLE', 'Initialization controls are disabled until artifact and planner sections complete.');
            $ownerStructureScanModel['engineering_workspace_initialization_section'] = $engineeringWorkspaceInitializationSection;
            if ((string)($engineeringWorkspaceInitializationSection['status'] ?? '') === 'ready') {
                $ownerStructureScanModel['engineering_workspace_initialization'] = isset($engineeringWorkspaceInitializationSection['data']) && is_array($engineeringWorkspaceInitializationSection['data'])
                    ? $engineeringWorkspaceInitializationSection['data']
                    : [];
            }

            $referenceDiscoverySection = (
                (string)($migrationPlanSection['status'] ?? '') === 'ready'
                && is_array($ownerStructureScanModel['scan_result'])
            )
                ? OwnerStructureScanSectionGuardService::safeSection(
                    'reference_discovery',
                    static fn (): array => OwnerStructureReferenceDiscoveryService::discover(
                        $selectedOwner,
                        $ownerStructureScanModel['scan_result'],
                        $ownerStructureScanModel['migration_plan']
                    ),
                    'OSS_REFERENCE_DISCOVERY_FAILED',
                    'Reference discovery could not complete.'
                )
                : OwnerStructureScanSectionGuardService::emptySection('OSS_REFERENCE_PREREQUISITE_UNAVAILABLE', 'Reference discovery is waiting for migration planner results.');
            $ownerStructureScanModel['reference_discovery_section'] = $referenceDiscoverySection;
            if ((string)($referenceDiscoverySection['status'] ?? '') === 'ready') {
                $ownerStructureScanModel['reference_discovery'] = isset($referenceDiscoverySection['data']) && is_array($referenceDiscoverySection['data'])
                    ? $referenceDiscoverySection['data']
                    : [];
            }
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Views/preview.php',
            'ownerStructureScanModel' => $ownerStructureScanModel,
        ]);
    }

    public static function ownerStructureScanInitializeWorkspaceArtifacts(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/owner-structure-scan');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $fingerprint = trim((string)($_POST['plan_fingerprint'] ?? ''));
        $selectedOperationIds = isset($_POST['operation_ids']) && is_array($_POST['operation_ids'])
            ? array_map('strval', $_POST['operation_ids'])
            : [];

        $result = EngineeringWorkspaceInitializationService::initialize(
            $ownerKey,
            $selectedOperationIds,
            $fingerprint,
            PlatformAuthority::resolveCurrentActor()
        );

        $_SESSION['studio_owner_structure_workspace_init_result'] = $result;

        $target = '/apps/studio/tools/owner-structure-scan';
        if ($ownerKey !== '') {
            $target .= '?owner=' . rawurlencode($ownerKey) . '&scan=1';
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    public static function localizationStudioEdit(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-studio/edit');

        $owner = trim((string)($_GET['owner'] ?? ''));
        $locale = trim((string)($_GET['locale'] ?? ''));
        $selectedKey = trim((string)($_GET['key'] ?? ''));
        $returnTo = self::studioSafeInternalReturnTo((string)($_GET['return_to'] ?? ''));
        $owners = LocalizationStudioEditService::getOwnersWithPaths();

        $filePath = '';
        $keyValues = [];
        $refValues = [];

        if ($owner !== '' && $locale !== '' && in_array($locale, ['en', 'ja', 'ne'], true)) {
            foreach ($owners as $o) {
                if ($o['key'] === $owner && isset($o['paths'][$locale])) {
                    $filePath = $o['paths'][$locale];
                    $keyValues = LocalizationStudioEditService::read($filePath);
                    break;
                }
            }

            if ($locale !== 'en') {
                $enPath = '';
                foreach ($owners as $o) {
                    if ($o['key'] === $owner && isset($o['paths']['en'])) {
                        $enPath = $o['paths']['en'];
                        break;
                    }
                }
                if ($enPath !== '') {
                    $refValues = LocalizationStudioEditService::read($enPath);
                }
            }
        }

        $flash = null;
        if (isset($_SESSION['studio_locale_editor_flash']) && is_array($_SESSION['studio_locale_editor_flash'])) {
            $flash = $_SESSION['studio_locale_editor_flash'];
            unset($_SESSION['studio_locale_editor_flash']);
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Views/editor.php',
            'editOwners' => $owners,
            'editSelectedOwner' => $owner,
            'editSelectedLocale' => $locale,
            'editSelectedKey' => $selectedKey,
            'editFilePath' => $filePath,
            'editKeyValues' => $keyValues,
            'editRefValues' => $refValues,
            'editFlash' => $flash,
            'editReturnTo' => $returnTo,
        ]);
    }

    public static function localizationStudioSave(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-studio/edit');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-studio/edit');

        $owner = trim((string)($_POST['owner'] ?? ''));
        $locale = trim((string)($_POST['locale'] ?? ''));
        $selectedKey = trim((string)($_POST['key'] ?? ''));
        $returnTo = self::studioSafeInternalReturnTo((string)($_POST['return_to'] ?? ''));
        $valuesJson = trim((string)($_POST['values_json'] ?? ''));

        $flash = [
            'type' => 'error',
            'message' => 'save_error',
            'details' => [],
        ];

        do {
            if ($owner === '' || $locale === '' || $valuesJson === '') {
                $flash['details'][] = 'Missing owner, locale, or values.';
                break;
            }

            $decoded = json_decode($valuesJson, true);
            if (!is_array($decoded)) {
                $flash['details'][] = 'Invalid values JSON.';
                break;
            }

            $owners = LocalizationStudioEditService::getOwnersWithPaths();
            $filePath = '';
            foreach ($owners as $o) {
                if ($o['key'] === $owner && isset($o['paths'][$locale])) {
                    $filePath = $o['paths'][$locale];
                    break;
                }
            }

            if ($filePath === '' || !is_file($filePath)) {
                $flash['details'][] = 'Locale file not found: ' . $filePath;
                break;
            }

            $errors = LocalizationStudioEditService::validate($decoded, $locale);
            if ($errors !== []) {
                $flash['details'][] = 'Validation errors: ' . implode(', ', $errors);
                break;
            }

            try {
                $snapPath = LocalizationStudioEditService::snapshot($filePath);
            } catch (\RuntimeException $e) {
                $flash['details'][] = 'Snapshot failed: ' . $e->getMessage();
                break;
            }

            if ($snapPath === null) {
                $flash['details'][] = 'Failed to create backup snapshot.';
                break;
            }

            try {
                $written = LocalizationStudioEditService::write($filePath, $decoded);
            } catch (\RuntimeException $e) {
                $flash['details'][] = 'Write failed: ' . $e->getMessage();
                break;
            }

            if (!$written) {
                $flash['details'][] = 'Failed to write locale file.';
                break;
            }

            $diagnostics = LocalizationStudioEditService::runPostApplyDiagnostics($filePath);
            if (!$diagnostics['parse_ok']) {
                $flash['details'][] = 'Post-apply diagnostics failed: parse error';
                break;
            }

            $relPath = str_replace(APP_ROOT . '/', '', $snapPath);
            $flash = [
                'type' => 'success',
                'message' => 'save_success',
                'details' => [
                    'Backup: ' . $relPath,
                    'Keys written: ' . count($decoded),
                    'Diagnostics: PASS',
                ],
            ];
        } while (false);

        $_SESSION['studio_locale_editor_flash'] = $flash;
        $qs = '?owner=' . rawurlencode($owner) . '&locale=' . rawurlencode($locale);
        if ($selectedKey !== '') {
            $qs .= '&key=' . rawurlencode($selectedKey);
        }
        if ($returnTo !== '') {
            $qs .= '&return_to=' . rawurlencode($returnTo);
        }
        header('Location: /apps/studio/tools/localization-studio/edit' . $qs, true, 302);
        exit;
    }

    private static function studioSafeInternalReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '') {
            return '';
        }
        if (!str_starts_with($returnTo, '/')
            || str_starts_with($returnTo, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnTo) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $returnTo) === 1) {
            return '';
        }

        return $returnTo;
    }

    public static function localizationStudioCreateFile(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-studio/create-file');

        $owner = trim((string)($_GET['owner'] ?? ''));
        $locale = trim((string)($_GET['locale'] ?? ''));
        $owners = LocalizationStudioEditService::getOwnersWithPaths();

        $enPath = '';
        $previewKeys = [];
        $previewKeyCount = 0;
        $previewError = null;

        if ($owner !== '' && $locale !== '' && in_array($locale, ['ja', 'ne'], true)) {
            foreach ($owners as $o) {
                if ($o['key'] === $owner && isset($o['paths']['en'])) {
                    $enPath = $o['paths']['en'];
                    break;
                }
            }

            if ($enPath === '' || !is_file($enPath)) {
                $previewError = 'English locale file not found for this owner.';
            } elseif (isset($o) && in_array($locale, $o['locales'] ?? [], true)) {
                $previewError = 'This locale file already exists for this owner.';
            } else {
                try {
                    $previewData = LocalizationStudioEditService::previewCreateFromEnglish($enPath, $locale);
                    $previewKeys = $previewData['keys'];
                    $previewKeyCount = $previewData['key_count'];
                } catch (\RuntimeException $e) {
                    $previewError = $e->getMessage();
                }
            }
        }

        $flash = null;
        if (isset($_SESSION['studio_locale_editor_flash']) && is_array($_SESSION['studio_locale_editor_flash'])) {
            $flash = $_SESSION['studio_locale_editor_flash'];
            unset($_SESSION['studio_locale_editor_flash']);
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Views/create-file.php',
            'createFileOwners' => $owners,
            'createFileSelectedOwner' => $owner,
            'createFileSelectedLocale' => $locale,
            'createFileEnPath' => $enPath,
            'createFilePreviewKeys' => $previewKeys,
            'createFilePreviewKeyCount' => $previewKeyCount,
            'createFilePreviewError' => $previewError,
            'createFileFlash' => $flash,
        ]);
    }

    public static function localizationStudioDoCreateFile(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-studio/create-file');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-studio/create-file');

        $owner = trim((string)($_POST['owner'] ?? ''));
        $locale = trim((string)($_POST['locale'] ?? ''));

        $flash = [
            'type' => 'error',
            'message' => 'save_error',
            'details' => [],
        ];

        do {
            if ($owner === '' || $locale === '') {
                $flash['details'][] = 'Missing owner or locale.';
                break;
            }

            if (!in_array($locale, ['ja', 'ne'], true)) {
                $flash['details'][] = 'Can only create ja or ne locale files.';
                break;
            }

            $owners = LocalizationStudioEditService::getOwnersWithPaths();
            $enPath = '';
            foreach ($owners as $o) {
                if ($o['key'] === $owner && isset($o['paths']['en'])) {
                    $enPath = $o['paths']['en'];
                    break;
                }
            }

            if ($enPath === '' || !is_file($enPath)) {
                $flash['details'][] = 'English locale file not found for owner: ' . $owner;
                break;
            }

            try {
                $result = LocalizationStudioEditService::createFromEnglish($enPath, $locale);
            } catch (\RuntimeException $e) {
                $flash['details'][] = 'Failed to create locale file: ' . $e->getMessage();
                break;
            }

            $relPath = str_replace(APP_ROOT . '/', '', $result['path']);
            $diag = $result['diagnostics'];

            $flash = [
                'type' => 'success',
                'message' => 'save_success',
                'details' => [
                    'Created: ' . $relPath,
                    'Keys added: ' . $result['key_count'],
                    'Diagnostics: ' . ($diag['parse_ok'] ? 'PASS' : 'FAIL'),
                ],
            ];
        } while (false);

        $_SESSION['studio_locale_editor_flash'] = $flash;
        $qs = '?owner=' . rawurlencode($owner) . '&locale=' . rawurlencode($locale);
        header('Location: /apps/studio/tools/localization-studio/create-file' . $qs, true, 302);
        exit;
    }

    public static function localizationScanExtractionPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-scan-extraction');

        $owners = [];
        $ownerDiscoveryWired = false;
        $selectedOwner = trim((string)($_GET['owner'] ?? ''));
        $selectedScope = trim((string)($_GET['scope'] ?? 'full'));
        $selectedLocale = trim((string)($_GET['locale'] ?? 'en'));
        $scanResults = null;

        try {
            $ownersRaw = LocalizationStudioEditService::getOwnersWithPaths();
            foreach ($ownersRaw as $o) {
                if (isset($o['key']) && $o['key'] !== '' && isset($o['label'])) {
                    $owners[$o['key']] = $o['label'];
                } elseif (isset($o['key']) && $o['key'] !== '') {
                    $owners[$o['key']] = $o['key'];
                }
            }
            $ownerDiscoveryWired = true;
        } catch (\Throwable $e) {
            $owners = [];
            $ownerDiscoveryWired = false;
        }

        $correctionReport = null;
        if (isset($_SESSION['studio_lse_correction_report'])) {
            $correctionReport = $_SESSION['studio_lse_correction_report'];
            unset($_SESSION['studio_lse_correction_report']);
        }

        $historyReports = [];
        try {
            $historyReports = HistoryStore::getRecent(10);
        } catch (\Throwable $e) {
            $historyReports = [];
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => 'Inline Localization Correction Tool',
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Views/preview.php',
            'localizationScanExtractionModel' => [
                'owners' => $owners,
                'owner_discovery_wired' => $ownerDiscoveryWired,
                'selected_owner' => $selectedOwner,
                'selected_scope' => $selectedScope,
                'selected_locale' => $selectedLocale,
                'scan_results' => $scanResults,
                'correction_report' => $correctionReport,
                'history_reports' => $historyReports,
            ],
        ]);
    }

    public static function localizationScanExtractionScanAsync(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-scan-extraction');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $scope = trim((string)($_POST['scope'] ?? 'full'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));
        $selectedOwners = isset($_POST['owners']) && is_array($_POST['owners'])
            ? array_values(array_map('strval', $_POST['owners']))
            : [];
        if ($selectedOwners === [] && isset($_POST['owner'])) {
            $selectedOwners = [(string)$_POST['owner']];
        }

        $selectedOwners = array_values(array_filter(array_map(
            static fn(string $owner): string => trim($owner),
            $selectedOwners
        ), static fn(string $owner): bool => $owner !== ''));

        $ownerDetail = null;
        $specificOwners = array_values(array_filter(
            $selectedOwners,
            static fn(string $owner): bool => $owner !== '__all__'
        ));

        try {
            $fullScan = FullSystemScanService::scanAll($scope, $locale, $specificOwners);
            if (count($specificOwners) === 1) {
                $ownerDetail = LocalizationScanExtractionScanner::scan($specificOwners[0], $scope, $locale);
                if ($ownerDetail !== null && !empty($ownerDetail['findings'])) {
                    try {
                        $planner = new InlineMigrationPlannerService($specificOwners[0]);
                        $ownerDetail['migration_plan'] = $planner->buildPlan($ownerDetail['findings']);
                    } catch (\Throwable $e) {
                        $ownerDetail['migration_plan'] = null;
                    }
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'ok' => !empty($fullScan['scan_ok']),
                'full_scan' => $fullScan,
                'owner_scan' => $ownerDetail,
            ]);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Scan failed: ' . $e->getMessage(),
            ]);
            exit;
        }
    }

    public static function localizationScanExtractionApplySafeCorrectionsAsync(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/localization-scan-extraction');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'full'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));
        $selectedKeys = isset($_POST['selected_keys']) && is_array($_POST['selected_keys'])
            ? array_values(array_filter(array_map(
                static fn($key): string => trim((string)$key),
                $_POST['selected_keys']
            ), static fn(string $key): bool => $key !== ''))
            : [];

        if ($ownerKey === '') {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Owner is required.']);
            exit;
        }

        if ($selectedKeys === []) {
            header('Content-Type: application/json');
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No safe correction keys were selected.']);
            exit;
        }

        try {
            $report = LocalizationScanCorrectionService::addMissingEnglishKeysSelectedWithReport($ownerKey, $scope, $locale, $selectedKeys);

            if (!$report->success && $report->snapshotPaths !== []) {
                $rollbackResult = RollbackService::rollbackFromReport($report->snapshotPaths);
                $report = $report->withRollbackStatus(!empty($rollbackResult['ok']) ? 'completed' : 'failed');
            }

            HistoryStore::store($report);

            $ownerScan = LocalizationScanExtractionScanner::scan($ownerKey, $scope, $locale);

            header('Content-Type: application/json');
            echo json_encode([
                'ok' => $report->success,
                'report' => $report->toArray(),
                'owner_scan' => $ownerScan,
                'error' => $report->success ? null : ($report->error ?? 'Correction failed.'),
            ]);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Correction failed: ' . $e->getMessage(),
            ]);
            exit;
        }
    }

    public static function tokenImpactExplorerPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/token-impact-explorer');

        $selectedToken = trim((string)($_GET['token'] ?? ''));
        $allTokens = TokenImpactDiscoveryService::discoverAllTokens();

        $catalogGrouped = [];
        $runtimeGrouped = [];
        foreach ($allTokens as $t) {
            $source = $t['source'] ?? 'runtime';
            if ($source === 'catalog') {
                $cat = $t['catalog_id'] ?? 'other';
                if (!isset($catalogGrouped[$cat])) {
                    $catalogGrouped[$cat] = ['name' => $cat, 'tokens' => []];
                }
                $catalogGrouped[$cat]['tokens'][] = $t;
            } else {
                $runtimeGrouped[] = $t;
            }
        }

        $exploreResult = null;
        if ($selectedToken !== '') {
            $exploreResult = TokenImpactDiscoveryService::exploreToken($selectedToken);
        }

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/TokenImpactExplorer/Views/impact-explorer.php',
            'tieAllTokens' => $allTokens,
            'tieCatalogGrouped' => $catalogGrouped,
            'tieRuntimeGrouped' => $runtimeGrouped,
            'tieSelectedToken' => $selectedToken,
            'tieExploreResult' => $exploreResult,
        ]);
    }

    public static function styleCompliancePreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/style-compliance');

        $scope = (string)($_GET['scope'] ?? StyleComplianceScannerService::SCOPE_ALL_OWNERS);
        $ownerKey = (string)($_GET['owner'] ?? '');
        $workspace = (string)($_GET['workspace'] ?? 'overview');

        $styleComplianceResult = StyleComplianceScannerService::initialState($scope, $ownerKey);
        $styleComplianceResult['workspace'] = $workspace;
        $styleComplianceResult['csrf'] = Auth::csrfToken();
        $styleComplianceResult['execution_capability'] = StyleComplianceGuardedRepairCapabilityService::contract();

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php',
            'styleComplianceResult' => $styleComplianceResult,
        ]);
    }

    public static function styleComplianceScanAsync(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/style-compliance');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/customization-studio/diagnose/style-compliance');
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $scope = (string)($_POST['scope'] ?? StyleComplianceScannerService::SCOPE_ALL_OWNERS);
        $ownerKey = $scope === StyleComplianceScannerService::SCOPE_OWNER ? (string)($_POST['owner'] ?? '') : '';
        $workspace = (string)($_POST['workspace'] ?? 'overview');
        $requestScanId = (string)($_POST['scan_id'] ?? '');
        $requestScanId = preg_match('/^[a-f0-9]{32}$/', $requestScanId) === 1 ? $requestScanId : '';

        try {
            $styleComplianceResult = StyleComplianceScannerService::scan($scope, $ownerKey);
            $styleComplianceResult['workspace'] = $workspace;
            $styleComplianceResult['execution_capability'] = StyleComplianceGuardedRepairCapabilityService::contract();

            $result = $styleComplianceResult;
            $extract = static function (string $key, mixed $default = []) use ($result): mixed {
                return isset($result[$key]) && (is_array($result[$key]) || is_string($result[$key]) || is_int($result[$key]) || is_bool($result[$key]) || is_float($result[$key]))
                    ? $result[$key]
                    : $default;
            };

            $summary = $extract('summary', []);
            $tokens = $extract('tokens', []);
            $candidates = $extract('candidates', []);
            $boundary = $extract('boundary_findings', []);
            $affected = $extract('affected_files', []);
            $foundationConcerns = $extract('foundation_concerns', []);
            $shellInventory = $extract('shell_inventory', []);
            $themeRepairProposals = $extract('theme_repair_proposals', []);
            $executionCapability = $extract('execution_capability', []);
            if ($executionCapability === [] && is_array($themeRepairProposals)) {
                $executionCapability = isset($themeRepairProposals['execution_capability']) && is_array($themeRepairProposals['execution_capability'])
                    ? $themeRepairProposals['execution_capability']
                    : StyleComplianceGuardedRepairCapabilityService::contract();
            }
            $governance = $extract('governance', []);
            $scopeDescriptor = $extract('scope_descriptor', []);
            $declarationCount = (int)($summary['in_scope'] ?? 0);
            $awareCount = (int)($summary['in_scope_aware'] ?? 0);
            $nonCompliantCount = (int)($summary['in_scope_unaware'] ?? 0);
            $repairReadyCount = (int)($summary['repairable'] ?? 0);
            $boundaryCount = (int)($summary['boundary_findings'] ?? 0);
            $inScopeCount = (int)($summary['in_scope'] ?? 0);
            $evidenceOnlyCount = (int)($summary['evidence_only'] ?? 0);
            $complianceScore = $declarationCount > 0 ? (int)round(($awareCount / $declarationCount) * 100) : null;
            $repairReadinessScore = $nonCompliantCount > 0 ? (int)round(($repairReadyCount / $nonCompliantCount) * 100) : null;
            $primaryAffectedFile = '';
            foreach ($affected as $affectedRow) {
                if (!is_array($affectedRow)) continue;
                $file = (string)($affectedRow['file'] ?? '');
                if ($file !== '') { $primaryAffectedFile = $file; break; }
            }
            $assessmentKey = 'assessment_status_compliant';
            if ($declarationCount === 0) {
                $assessmentKey = 'assessment_not_assessed';
            } elseif ($boundaryCount > 0) {
                $assessmentKey = 'assessment_status_blocked';
            } elseif ($nonCompliantCount > 0) {
                $assessmentKey = 'assessment_status_attention';
            }
            $nextStepKey = 'assessment_next_clear';
            if ($declarationCount === 0) {
                $nextStepKey = 'assessment_next_not_assessed';
            } elseif ($nonCompliantCount > 0) {
                $nextStepKey = $repairReadyCount > 0 ? 'assessment_next_repair' : 'assessment_next_review';
            }
            $proposalByFile = [];
            $proposalBlockedCount = 0;
            $proposalLaneCounts = ['theme_scope_migration' => 0, 'semantic_alias' => 0, 'replace_literal_with_token' => 0, 'semantic_token_correction' => 0, 'manual_review' => 0, 'blocked' => 0];
            $darkVariantQueue = [];
            $candidateTokenKeys = [];
            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) continue;
                $token = (string)($candidate['token'] ?? '');
                if ($token === '') continue;
                $candidateTokenKeys[$token] = true;
                $files = isset($candidate['files']) && is_array($candidate['files']) ? $candidate['files'] : [];
                $isBlocked = str_contains((string)($candidate['proposed_dark'] ?? ''), 'TODO');
                if ($isBlocked) {
                    $proposalBlockedCount++;
                    $proposalLaneCounts['blocked']++;
                    $darkVariantQueue[] = ['token' => $token, 'current_value' => (string)($candidate['current_value'] ?? ''), 'proposed_semantic' => (string)($candidate['proposed_semantic'] ?? ''), 'repair_action' => (string)($candidate['repair_action'] ?? 'semantic_alias'), 'files' => $files];
                }
                $repairAction = (string)($candidate['repair_action'] ?? 'semantic_alias');
                if (!array_key_exists($repairAction, $proposalLaneCounts)) $repairAction = 'semantic_alias';
                $proposalLaneCounts[$repairAction]++;
                foreach ($files as $fileValue) {
                    $file = (string)$fileValue;
                    if ($file === '') continue;
                    if (!isset($proposalByFile[$file])) $proposalByFile[$file] = ['file' => $file, 'replacements' => [], 'blocked' => 0];
                    $proposalByFile[$file]['replacements'][] = ['token' => $token, 'current_value' => (string)($candidate['current_value'] ?? ''), 'proposed_semantic' => (string)($candidate['proposed_semantic'] ?? ''), 'repair_action' => $repairAction, 'confidence' => (string)($candidate['confidence'] ?? 'low'), 'blocked' => $isBlocked];
                    if ($isBlocked) $proposalByFile[$file]['blocked']++;
                }
            }
            $manualReviewTokens = [];
            $manualReviewQueue = [];
            foreach ($tokens as $tokenRow) {
                if (!is_array($tokenRow) || !empty($tokenRow['theme_aware'])) continue;
                $complianceScope = (string)($tokenRow['compliance_scope'] ?? 'in_scope');
                if ($complianceScope !== 'in_scope') continue;
                $scanCat = (string)($tokenRow['scan_category'] ?? '');
                if (in_array($scanCat, ['effect_candidate', 'dynamic_unsupported', 'print_pdf'], true)) continue;
                $token = (string)($tokenRow['token'] ?? '');
                if ($token === '' || isset($candidateTokenKeys[$token])) continue;
                $manualReviewTokens[$token] = true;
                if (!isset($manualReviewQueue[$token])) {
                    $cls = isset($tokenRow['value_classification']) && is_array($tokenRow['value_classification']) ? $tokenRow['value_classification'] : ['canonical' => '', 'type' => ''];
                    $manualReviewQueue[$token] = ['token' => $token, 'current_value' => (string)($cls['canonical'] ?? ''), 'value_type' => (string)($cls['type'] ?? ''), 'files' => []];
                }
                $file = (string)($tokenRow['file'] ?? '');
                if ($file !== '') $manualReviewQueue[$token]['files'][$file] = true;
            }
            $manualReviewQueue = array_values(array_map(static function (array $row): array {
                $files = array_keys($row['files']); sort($files, SORT_STRING); $row['files'] = $files; return $row;
            }, $manualReviewQueue));
            $proposalLaneCounts['manual_review'] = count($manualReviewTokens);
            $proposalStatusKey = $proposalBlockedCount > 0 ? 'proposal_status_blocked' : 'proposal_status_ready';
            $applyReadiness = [
                ['key' => 'readiness_boundary_clear', 'state' => $boundaryCount === 0 ? 'pass' : 'blocked'],
                ['key' => 'readiness_dark_variants', 'state' => $proposalBlockedCount === 0 ? 'pass' : 'blocked'],
                ['key' => 'readiness_manual_review', 'state' => $proposalLaneCounts['manual_review'] === 0 ? 'pass' : 'blocked'],
                ['key' => 'readiness_candidate_plan', 'state' => $repairReadyCount > 0 || $nonCompliantCount === 0 ? 'pass' : 'blocked'],
                ['key' => 'readiness_snapshot', 'state' => 'pending'],
                ['key' => 'readiness_approval', 'state' => 'pending'],
                ['key' => 'readiness_apply_route', 'state' => !empty($executionCapability['executor_enabled']) ? 'pass' : 'blocked'],
            ];

            $scanId = $requestScanId !== '' ? $requestScanId : bin2hex(random_bytes(16));
            $state = StyleCompliancePresentationSummaryService::build($styleComplianceResult, $scope, $ownerKey, $workspace);
            $state['scan_id'] = $scanId;
            $presentationSummary = isset($state['summary']) && is_array($state['summary']) ? $state['summary'] : [];
            $canonicalUrl = '/apps/studio/tools/customization-studio/diagnose/style-compliance';
            $queryParts = [];
            if ($scope !== StyleComplianceScannerService::SCOPE_ALL_OWNERS) {
                $queryParts['scope'] = $scope;
            }
            if ($scope === StyleComplianceScannerService::SCOPE_OWNER && $ownerKey !== '') {
                $queryParts['owner'] = $ownerKey;
            }
            if ($workspace !== 'overview') {
                $queryParts['workspace'] = $workspace;
            }
            if ($queryParts !== []) {
                $canonicalUrl .= '?' . http_build_query($queryParts);
            }

            require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php';

            // Save state before _result_sections.php overwrites it via variable collision
            $scanStateContract = $state;
            ob_start();
            require APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php';
            $html = ob_get_clean();

            header('Content-Type: application/json');
            echo json_encode([
                'html' => $html,
                'state' => $scanStateContract,
                'canonical_url' => $canonicalUrl,
                'count' => (int)($presentationSummary['findings_total'] ?? 0),
                'status' => $assessmentKey,
                'scope' => $scope,
                'owner' => $ownerKey,
                'workspace' => $workspace,
                'error' => null,
            ]);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'html' => null,
                'count' => 0,
                'status' => 'error',
                'scope' => $scope,
                'owner' => $ownerKey,
                'error' => 'Scan failed: ' . $e->getMessage(),
                'state' => [
                    'scan_id' => $requestScanId !== '' ? $requestScanId : null,
                    'scope' => $scope,
                    'owner_key' => $scope === StyleComplianceScannerService::SCOPE_OWNER && $ownerKey !== '' ? $ownerKey : null,
                    'workspace' => $workspace,
                    'scan_status' => 'failed',
                ],
            ]);
            exit;
        }
    }

    public static function styleComplianceRepairReadinessAsync(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/style-compliance');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/customization-studio/diagnose/style-compliance');

        $proposalId = (string)($_POST['proposal_id'] ?? '');

        try {
            $result = StyleComplianceRepairReadinessService::checkByProposalId($proposalId);
            header('Content-Type: application/json');
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'state' => 'blocked',
                'reason' => $e->getMessage(),
                'checks' => [],
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public static function styleComplianceFixOneAsync(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/style-compliance');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/customization-studio/diagnose/style-compliance');
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $proposalId = (string)($_POST['proposal_id'] ?? '');

        try {
            $result = StyleComplianceDeterministicFixOneService::fixByProposalId($proposalId);
            if (($result['state'] ?? '') === StyleComplianceDeterministicFixOneService::STATE_FIXED) {
                $evidence = isset($result['evidence']) && is_array($result['evidence']) ? $result['evidence'] : [];
                $rescanOwner = (string)($evidence['rescan_owner'] ?? '');
                if ($rescanOwner !== '') {
                    $rescan = StyleComplianceScannerService::scan(StyleComplianceScannerService::SCOPE_OWNER, $rescanOwner);
                    $result['scan_state'] = StyleCompliancePresentationSummaryService::build(
                        $rescan,
                        StyleComplianceScannerService::SCOPE_OWNER,
                        $rescanOwner,
                        'overview'
                    );
                }
            }
            header('Content-Type: application/json');
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'proposal_id' => $proposalId,
                'state' => 'failed',
                'reason' => $e->getMessage(),
                'evidence' => [],
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public static function styleComplianceRepairExecute(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/diagnose/style-compliance');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/customization-studio/diagnose/style-compliance');

        $proposalId = (string)($_POST['proposal_id'] ?? '');
        $scope = (string)($_POST['scope'] ?? '');
        $ownerKey = (string)($_POST['owner_key'] ?? '');

        try {
            $executionCapability = StyleComplianceGuardedRepairCapabilityService::contract();
            if (empty($executionCapability['executor_enabled'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'proposal_id' => $proposalId,
                    'state' => 'executor_unavailable',
                    'reason' => 'Guarded repair execution is not enabled for this tool.',
                    'future_executor_contract' => $executionCapability,
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            // Server-resolved: re-read proposal from fresh scan
            $readiness = StyleComplianceRepairReadinessService::checkByProposalId($proposalId);
            $readinessState = (string)($readiness['state'] ?? 'blocked');
            $proposal = isset($readiness['expected_change']) && is_array($readiness['expected_change'])
                ? $readiness['expected_change'] : [];
            $proposal['proposal_id'] = $proposalId;

            if ($readinessState !== StyleComplianceRepairReadinessService::STATE_READY) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => false,
                    'proposal_id' => $proposalId,
                    'state' => 'preflight_blocked',
                    'reason' => 'Preflight readiness did not pass: ' . ($readiness['reason'] ?? 'Unknown'),
                    'checks' => $readiness['checks'] ?? [],
                ], JSON_UNESCAPED_SLASHES);
                exit;
            }

            $result = StyleComplianceRepairEngineService::executeRepair($proposal, [
                'scan_scope' => $scope,
                'scan_owner_key' => $ownerKey,
            ]);

            header('Content-Type: application/json');
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'proposal_id' => $proposalId,
                'state' => 'error',
                'reason' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    public static function cssTokenEditorPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/design-system/tokens');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/preview.php',
            'cssTokenEditorModel' => self::buildCssTokenEditorModel(),
        ]);
    }

    public static function cssTokenEditorLivePreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/design-system/tokens/preview');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/live_preview.php',
        ]);
    }

    public static function cssTokenEditorSave(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/design-system/tokens');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/customization-studio/design-system/tokens');

        $selectorKey = trim((string)($_POST['selector_key'] ?? ''));
        $tokens = isset($_POST['tokens']) && is_array($_POST['tokens'])
            ? $_POST['tokens']
            : [];

        $payload = [
            'selector_key' => $selectorKey,
            'source_id' => trim((string)($_POST['source_id'] ?? '')),
            'source_path' => trim((string)($_POST['source_path'] ?? '')),
            'tokens' => $tokens,
            'mode' => trim((string)($_POST['mode'] ?? 'simple')),
            'safety_override' => trim((string)($_POST['safety_override'] ?? '')),
        ];

        $result = CssTokenEditorSaveService::save($payload);

        if (!empty($result['ok'])) {
            $changes = (int)($result['changes'] ?? 0);
            $msgKey = $changes > 0 ? 'feedback_saved' : 'feedback_no_changes';
            $_SESSION['studio_css_token_editor_flash'] = [
                'type' => 'success',
                'message' => $msgKey,
            ];
            if (!empty($result['backup'])) {
                $_SESSION['studio_css_token_editor_flash']['backup'] = $result['backup'];
            }
        } else {
            $errors = isset($result['errors']) && is_array($result['errors'])
                ? $result['errors']
                : ['save_error_write_failed'];
            $_SESSION['studio_css_token_editor_flash'] = [
                'type' => 'error',
                'message' => 'feedback_save_failed',
                'errors' => $errors,
            ];
        }

        header('Location: /apps/studio/tools/customization-studio/design-system/tokens', true, 302);
        exit;
    }

    public static function cssTokenEditorVerify(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/design-system/tokens');

        $input = @file_get_contents('php://input');
        $payload = [];
        if (is_string($input) && $input !== '') {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if ($payload === []) {
            $payload = [
                'selector_key' => (string)($_POST['selector_key'] ?? ''),
                'source_id' => (string)($_POST['source_id'] ?? ''),
                'source_path' => (string)($_POST['source_path'] ?? ''),
                'tokens' => isset($_POST['tokens']) && is_array($_POST['tokens']) ? $_POST['tokens'] : [],
            ];
        }

        $result = CssTokenEditorSaveService::verify($payload);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    public static function cssTokenEditorSourceSnapshot(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/customization-studio/design-system/tokens');

        $model = self::buildCssTokenEditorModel();
        $selectors = isset($model['selectors']) && is_array($model['selectors']) ? $model['selectors'] : [];

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'selectors' => $selectors,
        ]);
        exit;
    }

    public static function labelDesignerPreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');

        $view->render('studio::pages/tool_placeholder.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'toolTemplatePath' => APP_ROOT . '/apps/Studio/Tools/LabelDesigner/Views/index.php',
            'labelDesignerPreviewModel' => self::buildLabelDesignerPreviewModel(),
        ]);
    }

    public static function labelDesignerCreateContext(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $owner = trim((string)($_POST['owner'] ?? ''));
        $purpose = trim((string)($_POST['purpose'] ?? ''));
        $source = trim((string)($_POST['source'] ?? ''));
        $contextKey = trim((string)($_POST['context_key'] ?? ''));
        $fields = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
        $confirmCreate = trim((string)($_POST['confirm_create'] ?? ''));

        $payload = [
            'owner' => $owner,
            'purpose' => $purpose,
            'source' => $source,
            'context_key' => $contextKey,
            'fields' => $fields,
            'confirm_create' => $confirmCreate,
        ];

        $actor = Auth::user();
        $result = LabelDesignerContextCreateService::createContext($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok'])) {
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'context_create_success',
                'details' => [
                    'Created context path: ' . (string)($result['created_path'] ?? ''),
                    'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                    'Validation status: PASS',
                ],
            ];
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Context create failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'context_create_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            [
                'owner' => $owner,
                'purpose' => $purpose,
                'source' => $source,
                'context_key' => $contextKey,
                'fields' => $fields,
            ]
        );
    }

    public static function labelDesignerCreateResourceFolders(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $ownerKey = trim((string)($_POST['owner_key'] ?? ''));
        $confirm = trim((string)($_POST['confirm_create'] ?? ''));

        $payload = [
            'owner_key' => $ownerKey,
            'confirm_create' => $confirm,
        ];

        $actor = Auth::user();
        $result = LabelDesignerResourceReadinessService::createMissingFolders($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok'])) {
            $details = [];
            if (!empty($result['created'])) {
                $details[] = 'Created folders: ' . implode(', ', $result['created']);
                $details[] = 'Snapshot path: ' . (string)($result['snapshot_path'] ?? '');
            } else {
                $details[] = (string)($result['message'] ?? 'All folders already exist.');
            }
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'resource_folders_ready',
                'details' => $details,
            ];
        } else {
            $errors = isset($result['errors']) && is_array($result['errors'])
                ? $result['errors']
                : ['Failed to create resource folders.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'resource_folders_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(trim((string)($_POST['workspace'] ?? '')));
    }

    public static function labelDesignerCreateTemplate(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $templateContext = trim((string)($_POST['template_context'] ?? ''));
        $templateSize = trim((string)($_POST['template_size'] ?? '100x50_mm'));
        $templateFields = isset($_POST['template_fields']) && is_array($_POST['template_fields']) ? $_POST['template_fields'] : [];
        $confirmCreate = trim((string)($_POST['confirm_create'] ?? ''));

        $payload = [
            'template_context' => $templateContext,
            'template_size' => $templateSize,
            'template_fields' => $templateFields,
            'confirm_create' => $confirmCreate,
        ];

        $actor = Auth::user();
        $result = LabelDesignerTemplateCreateService::createTemplate($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok'])) {
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'template_create_success',
                'details' => [
                    'Created template: ' . (string)($result['template_key'] ?? ''),
                    'Path: ' . (string)($result['created_path'] ?? ''),
                    'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                    'Diagnostics: ' . (string)($result['diagnostics']['status'] ?? 'FAIL'),
                ],
            ];
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Template create failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'template_create_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            array_filter(array_merge(
                $templateContext !== ''
                    ? [
                        'template_context' => $templateContext,
                        'template_size' => $templateSize,
                        'template_fields' => $templateFields,
                    ]
                    : [],
                ['owner' => trim((string)($_POST['owner'] ?? ''))]
            ), static fn ($v): bool => $v !== '')
        );
    }

    public static function labelDesignerRenderPreview(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');

        $contextId = trim((string)($_POST['preview_context_id'] ?? ''));
        $templateId = trim((string)($_POST['preview_template_id'] ?? ''));
        $loadRules = !empty($_POST['load_rules']);

        $result = LabelDesignerPreviewRendererService::buildResolvedPreview([
            'context_id' => $contextId,
            'template_id' => $templateId,
            'load_rules' => $loadRules,
        ]);

        $_SESSION['studio_label_designer_preview'] = $result;
        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            ['owner' => trim((string)($_POST['owner'] ?? ''))]
        );
    }

    public static function labelDesignerRuleSandbox(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');

        $contextId = trim((string)($_POST['preview_context_id'] ?? ''));
        $templateId = trim((string)($_POST['preview_template_id'] ?? ''));

        $conditionField = trim((string)($_POST['rs_condition_field'] ?? ''));
        $operator = trim((string)($_POST['rs_operator'] ?? ''));
        $compareValue = trim((string)($_POST['rs_compare_value'] ?? ''));
        $effectType = trim((string)($_POST['rs_effect_type'] ?? ''));
        $effectTarget = trim((string)($_POST['rs_effect_target'] ?? ''));
        $effectValue = trim((string)($_POST['rs_effect_value'] ?? ''));

        $previewResult = LabelDesignerPreviewRendererService::buildResolvedPreview([
            'context_id' => $contextId,
            'template_id' => $templateId,
        ]);

        $ruleResult = LabelDesignerRuleSandboxService::evaluateTemporaryRule($previewResult, [
            'condition_field' => $conditionField,
            'operator' => $operator,
            'compare_value' => $compareValue,
            'effect_type' => $effectType,
            'effect_target' => $effectTarget,
            'effect_value' => $effectValue,
        ]);

        $_SESSION['studio_label_designer_preview'] = $previewResult;
        $_SESSION['studio_label_designer_rule_sandbox'] = $ruleResult;
        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            ['owner' => trim((string)($_POST['owner'] ?? ''))]
        );
    }

    public static function labelDesignerCreateRule(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $payload = [
            'rule_context_id' => trim((string)($_POST['rule_context_id'] ?? '')),
            'rule_template_id' => trim((string)($_POST['rule_template_id'] ?? '')),
            'rule_key' => trim((string)($_POST['rule_key'] ?? '')),
            'rc_condition_field' => trim((string)($_POST['rc_condition_field'] ?? '')),
            'rc_operator' => trim((string)($_POST['rc_operator'] ?? '')),
            'rc_condition_value' => trim((string)($_POST['rc_condition_value'] ?? '')),
            'rc_effect_type' => trim((string)($_POST['rc_effect_type'] ?? '')),
            'rc_effect_target' => trim((string)($_POST['rc_effect_target'] ?? '')),
            'rc_effect_value' => trim((string)($_POST['rc_effect_value'] ?? '')),
            'rule_enabled' => trim((string)($_POST['rule_enabled'] ?? 'yes')),
            'confirm_create' => trim((string)($_POST['confirm_create'] ?? '')),
        ];

        $actor = Auth::user();
        $result = LabelDesignerRuleCreateService::createRule($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok'])) {
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'rule_create_success',
                'details' => [
                    'Created rule: ' . (string)($result['rule_key'] ?? ''),
                    'Path: ' . (string)($result['created_path'] ?? ''),
                    'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                    'Diagnostics: ' . (string)($result['diagnostics']['status'] ?? 'FAIL'),
                ],
            ];
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Rule create failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'rule_create_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            [
                'owner' => trim((string)($_POST['owner'] ?? '')),
                'rule_preview' => '1',
                'rule_context_id' => (string)$payload['rule_context_id'],
                'rule_template_id' => (string)$payload['rule_template_id'],
                'rule_key' => (string)$payload['rule_key'],
                'rc_condition_field' => (string)$payload['rc_condition_field'],
                'rc_operator' => (string)$payload['rc_operator'],
                'rc_condition_value' => (string)$payload['rc_condition_value'],
                'rc_effect_type' => (string)$payload['rc_effect_type'],
                'rc_effect_target' => (string)$payload['rc_effect_target'],
                'rc_effect_value' => (string)$payload['rc_effect_value'],
                'rule_enabled' => (string)$payload['rule_enabled'],
            ]
        );
    }

    public static function labelDesignerDuplicateResource(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $payload = [
            'resource_type' => trim((string)($_POST['resource_type'] ?? '')),
            'source_key' => trim((string)($_POST['source_key'] ?? '')),
            'new_key' => trim((string)($_POST['new_key'] ?? '')),
            'owner_key' => trim((string)($_POST['owner_key'] ?? '')),
            'confirm_duplicate' => trim((string)($_POST['confirm_duplicate'] ?? '')),
        ];

        $actor = Auth::user();
        $result = LabelDesignerDuplicateService::duplicate($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok']) && empty($result['preview'])) {
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'duplicate_success',
                'details' => [
                    'Duplicated ' . (string)($result['resource_type'] ?? '') . ': ' . (string)($result['source_key'] ?? '') . ' -> ' . (string)($result['new_key'] ?? ''),
                    'Path: ' . (string)($result['created_path'] ?? ''),
                    'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                    'Diagnostics: ' . (!empty($result['diagnostics']['status']) ? $result['diagnostics']['status'] : 'PASS'),
                ],
            ];
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Duplicate failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'duplicate_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            [
                'owner' => trim((string)($_POST['owner_key'] ?? '')),
                'resource_type' => (string)($payload['resource_type']),
                'source_key' => (string)($payload['source_key']),
                'new_key' => (string)($payload['new_key']),
            ]
        );
    }

    public static function labelDesignerEditContext(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $rawFields = isset($_POST['fields']) && is_array($_POST['fields']) ? $_POST['fields'] : [];
        $normalizedFields = [];
        foreach ($rawFields as $rawField) {
            if (is_array($rawField)) {
                $normalizedFields[] = [
                    'field_key' => trim((string)($rawField['field_key'] ?? '')),
                    'label' => trim((string)($rawField['label'] ?? '')),
                    'data_type' => trim((string)($rawField['data_type'] ?? '')),
                ];
            }
        }

        $payload = [
            'owner_key' => trim((string)($_POST['owner_key'] ?? '')),
            'context_key' => trim((string)($_POST['context_key'] ?? '')),
            'purpose' => trim((string)($_POST['purpose'] ?? '')),
            'fields' => $normalizedFields,
            'confirm_edit' => trim((string)($_POST['confirm_edit'] ?? '')),
        ];

        $actor = Auth::user();
        $result = LabelDesignerContextEditService::editContext($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok']) && empty($result['preview'])) {
            $diagChecks = isset($result['diagnostics']['checks']) && is_array($result['diagnostics']['checks'])
                ? $result['diagnostics']['checks']
                : [];
            $details = [
                'Context updated: ' . (string)($result['context_key'] ?? ''),
                'Path: ' . (string)($result['created_path'] ?? ''),
                'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                'Diagnostics status: ' . (!empty($result['diagnostics']['status']) ? $result['diagnostics']['status'] : 'PASS'),
            ];
            foreach ($diagChecks as $check) {
                $severity = (string)($check['severity'] ?? '');
                $label = (string)($check['label'] ?? $check['code'] ?? '');
                $details[] = $severity . ': ' . $label;
            }
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'context_edit_success',
                'details' => $details,
            ];
            if ($diagChecks !== []) {
                $_SESSION['studio_label_designer_edit_diagnostics'] = $result['diagnostics'];
            }
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Edit failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'context_edit_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            [
                'owner' => trim((string)($_POST['owner_key'] ?? '')),
                'view_resource' => 'context',
                'context_key' => trim((string)($_POST['context_key'] ?? '')),
            ]
        );
    }

    public static function labelDesignerEditTemplate(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $fieldLabels = isset($_POST['field_labels']) && is_array($_POST['field_labels'])
            ? $_POST['field_labels']
            : [];
        $normalizedFieldLabels = [];
        foreach ($fieldLabels as $fk => $fl) {
            if (is_string($fk) && $fk !== '') {
                $normalizedFieldLabels[$fk] = is_string($fl) ? trim($fl) : '';
            }
        }

        $blockSummaries = isset($_POST['block_summaries']) && is_array($_POST['block_summaries'])
            ? $_POST['block_summaries']
            : [];
        $normalizedBlockSummaries = [];
        foreach ($blockSummaries as $bk => $bs) {
            if (is_string($bk) && $bk !== '') {
                $normalizedBlockSummaries[$bk] = is_string($bs) ? trim($bs) : '';
            }
        }

        $payload = [
            'owner_key' => trim((string)($_POST['owner_key'] ?? '')),
            'template_key' => trim((string)($_POST['template_key'] ?? '')),
            'label' => trim((string)($_POST['label'] ?? '')),
            'purpose' => trim((string)($_POST['purpose'] ?? '')),
            'size_label' => trim((string)($_POST['size_label'] ?? '')),
            'size_width' => trim((string)($_POST['size_width'] ?? '')),
            'size_height' => trim((string)($_POST['size_height'] ?? '')),
            'field_labels' => $normalizedFieldLabels,
            'block_summaries' => $normalizedBlockSummaries,
            'confirm_edit' => trim((string)($_POST['confirm_edit'] ?? '')),
        ];

        $actor = Auth::user();
        $result = LabelDesignerTemplateEditService::editTemplate($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok']) && empty($result['preview'])) {
            $diagChecks = isset($result['diagnostics']['checks']) && is_array($result['diagnostics']['checks'])
                ? $result['diagnostics']['checks']
                : [];
            $details = [
                'Template updated: ' . (string)($result['template_key'] ?? ''),
                'Path: ' . (string)($result['created_path'] ?? ''),
                'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                'Diagnostics status: ' . (!empty($result['diagnostics']['status']) ? $result['diagnostics']['status'] : 'PASS'),
            ];
            foreach ($diagChecks as $check) {
                $severity = (string)($check['severity'] ?? '');
                $label = (string)($check['label'] ?? $check['code'] ?? '');
                $details[] = $severity . ': ' . $label;
            }
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'template_edit_success',
                'details' => $details,
            ];
            if ($diagChecks !== []) {
                $_SESSION['studio_label_designer_edit_diagnostics'] = $result['diagnostics'];
            }
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Edit failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'template_edit_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            [
                'owner' => trim((string)($_POST['owner_key'] ?? '')),
                'view_resource' => 'template',
                'template_key' => trim((string)($_POST['template_key'] ?? '')),
            ]
        );
    }

    public static function labelDesignerEditRule(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $conditionValues = isset($_POST['condition_values']) && is_array($_POST['condition_values'])
            ? $_POST['condition_values']
            : [];
        $normalizedConditionValues = [];
        foreach ($conditionValues as $idx => $cv) {
            $idx = (int)$idx;
            if (is_array($cv)) {
                $normalizedConditionValues[$idx] = [
                    'value' => trim((string)($cv['value'] ?? '')),
                ];
            }
        }

        $effectLabels = isset($_POST['effect_labels']) && is_array($_POST['effect_labels'])
            ? $_POST['effect_labels']
            : [];
        $normalizedEffectLabels = [];
        foreach ($effectLabels as $idx => $el) {
            $idx = (int)$idx;
            if (is_array($el)) {
                $normalizedEffectLabels[$idx] = [
                    'label' => trim((string)($el['label'] ?? '')),
                    'value' => trim((string)($el['value'] ?? '')),
                ];
            }
        }

        $payload = [
            'owner_key' => trim((string)($_POST['owner_key'] ?? '')),
            'rule_key' => trim((string)($_POST['rule_key'] ?? '')),
            'label' => trim((string)($_POST['label'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'priority' => trim((string)($_POST['priority'] ?? '')),
            'enabled' => trim((string)($_POST['enabled'] ?? '')),
            'condition_values' => $normalizedConditionValues,
            'effect_labels' => $normalizedEffectLabels,
            'confirm_edit' => trim((string)($_POST['confirm_edit'] ?? '')),
        ];

        $actor = Auth::user();
        $result = LabelDesignerRuleEditService::editRule($payload, is_array($actor) ? $actor : []);

        if (!empty($result['ok']) && empty($result['preview'])) {
            $diagChecks = isset($result['diagnostics']['checks']) && is_array($result['diagnostics']['checks'])
                ? $result['diagnostics']['checks']
                : [];
            $details = [
                'Rule updated: ' . (string)($result['rule_key'] ?? ''),
                'Path: ' . (string)($result['created_path'] ?? ''),
                'Snapshot path: ' . (string)($result['snapshot_path'] ?? ''),
                'Diagnostics status: ' . (!empty($result['diagnostics']['status']) ? $result['diagnostics']['status'] : 'PASS'),
            ];
            foreach ($diagChecks as $check) {
                $severity = (string)($check['severity'] ?? '');
                $label = (string)($check['label'] ?? $check['code'] ?? '');
                $details[] = $severity . ': ' . $label;
            }
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'success',
                'message' => 'rule_edit_success',
                'details' => $details,
            ];
            if ($diagChecks !== []) {
                $_SESSION['studio_label_designer_edit_diagnostics'] = $result['diagnostics'];
            }
        } else {
            $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : ['Edit failed.'];
            $_SESSION['studio_label_designer_flash'] = [
                'type' => 'error',
                'message' => 'rule_edit_failed',
                'details' => $errors,
            ];
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? 'rules')),
            [
                'owner' => trim((string)($_POST['owner_key'] ?? '')),
                'view_rule' => trim((string)($_POST['rule_key'] ?? '')),
            ]
        );
    }

    public static function labelDesignerMigrationPreview(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');

        $resourcePath = trim((string)($_POST['resource_path'] ?? ''));
        $result = LabelDesignerResourceMetadataService::previewMigration([
            'resource_path' => $resourcePath,
        ]);
        $_SESSION['studio_label_designer_migration_preview'] = $result;

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            array_filter(array_merge(
                [
                    'rule_context_id' => trim((string)($_POST['rule_context_id'] ?? '')),
                    'rule_template_id' => trim((string)($_POST['rule_template_id'] ?? '')),
                    'rule_key' => trim((string)($_POST['rule_key'] ?? '')),
                    'rc_condition_field' => trim((string)($_POST['rc_condition_field'] ?? '')),
                    'rc_operator' => trim((string)($_POST['rc_operator'] ?? '')),
                    'rc_condition_value' => trim((string)($_POST['rc_condition_value'] ?? '')),
                    'rc_effect_type' => trim((string)($_POST['rc_effect_type'] ?? '')),
                    'rc_effect_target' => trim((string)($_POST['rc_effect_target'] ?? '')),
                    'rc_effect_value' => trim((string)($_POST['rc_effect_value'] ?? '')),
                    'rule_enabled' => trim((string)($_POST['rule_enabled'] ?? 'yes')),
                ],
                ['owner' => trim((string)($_POST['owner'] ?? ''))]
            ), static fn ($v): bool => $v !== '')
        );
    }

    public static function labelDesignerApplyMetadataMigration(): void
    {
        self::guardPlatformAdmin('/apps/studio/tools/label-designer');
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/label-designer');

        $resourcePath = trim((string)($_POST['resource_path'] ?? ''));
        $confirmApply = trim((string)($_POST['confirm_apply'] ?? ''));
        $result = LabelDesignerMetadataMigrationService::applyMigration([
            'resource_path' => $resourcePath,
            'confirm_apply' => $confirmApply,
        ]);

        if (!empty($result['applied'])) {
            $_SESSION['studio_label_designer_migration_apply'] = $result;

            // Also re-run the preview to refresh M003/M004 diagnostics
            $previewResult = LabelDesignerResourceMetadataService::previewMigration([
                'resource_path' => $resourcePath,
            ]);
            if (!empty($previewResult)) {
                $_SESSION['studio_label_designer_migration_preview'] = $previewResult;
            }
        } else {
            $_SESSION['studio_label_designer_migration_apply'] = $result;
        }

        self::labelDesignerRedirect(
            trim((string)($_POST['workspace'] ?? '')),
            array_filter([
                'owner' => trim((string)($_POST['owner'] ?? '')),
                'rule_context_id' => trim((string)($_POST['rule_context_id'] ?? '')),
                'rule_template_id' => trim((string)($_POST['rule_template_id'] ?? '')),
                'rule_key' => trim((string)($_POST['rule_key'] ?? '')),
            ], static fn ($v): bool => $v !== '')
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildCssTokenEditorModel(): array
    {
        $themeSources = self::resolveThemeSourceFiles();
        $selectors = self::readThemeSourceTokenSelectors($themeSources);
        $availableStyles = self::resolveAvailableThemeStyles();
        $flash = isset($_SESSION['studio_css_token_editor_flash']) && is_array($_SESSION['studio_css_token_editor_flash'])
            ? $_SESSION['studio_css_token_editor_flash']
            : [];
        unset($_SESSION['studio_css_token_editor_flash']);

        $normalized = [];
        foreach ($selectors as $sel) {
            $blockIndex = (int)($sel['block_index'] ?? 1);
            $rawSelector = strtolower(trim(preg_replace('/\s+/', ' ', $sel['selector'])));
            $key = $rawSelector . '::' . $blockIndex;
            $normalized[] = [
                'key' => $key,
                'label' => $sel['label'],
                'selector' => $sel['selector'],
                'theme' => $sel['theme'],
                'style' => $sel['style'],
                'tokens' => $sel['tokens'],
                'block_index' => $blockIndex,
                'source_id' => (string)($sel['source_id'] ?? ''),
                'source_path' => (string)($sel['source_path'] ?? ''),
                'source_label' => (string)($sel['source_label'] ?? ''),
                'source_kind' => (string)($sel['source_kind'] ?? ''),
                'label_key' => (string)($sel['label_key'] ?? ''),
                'label_value' => (string)($sel['label_value'] ?? ''),
            ];
        }

        return [
            'source_path' => '/resources/themes/*',
            'source_snapshot_url' => '/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot',
            'selectors' => $normalized,
            'available_styles' => $availableStyles,
            'csrf' => Auth::csrfToken(),
            'flash' => $flash,
        ];
    }

    private static function guardPlatformAdmin(string $intendedUrl): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl);
            header('Location: /login', true, 302);
            exit;
        }

        $ctx = platform_user_context_contract()->resolveUserContext(Auth::user());
        $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
        if ($role !== 'platform_admin') {
            header('Location: /ops/dashboard', true, 302);
            exit;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildThemeToolPreviewModel(): array
    {
        $themeCssPath = APP_ROOT . '/public/assets/theme.css';
        $selectors = self::readThemeTokenSelectors($themeCssPath);
        $registry = ThemeRegistryReaderService::readSummary($selectors);
        $availableStyles = self::resolveAvailableThemeStyles();
        $sourceFiles = self::resolveThemeSourceFiles();
        $analysis = ThemeDoctorAnalyzer::analyze($registry, $sourceFiles, $availableStyles);

        return [
            'source_path' => '/assets/theme.css',
            'token_selectors' => $selectors,
            'available_styles' => $availableStyles,
            'focus_tokens' => ['bg', 'panel', 'text', 'muted', 'line', 'accent', 'font_sans', 'style_content_bg'],
            'theme_registry' => $registry,
            'findings' => $analysis['findings'],
            'health_score' => $analysis['health_score'],
            'preview_only' => true,
            'write_enabled' => false,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function resolveAvailableThemeStyles(): array
    {
        $servicePath = APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';
        if (is_file($servicePath)) {
            require_once $servicePath;
        }

        if (class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')) {
            $styles = \Apps\Shell\Services\ThemePreferenceService::availableColorStyles();
            if (is_array($styles) && $styles !== []) {
                return $styles;
            }
        }

        return [];
    }

    /**
     * @return array{options:array<string,string>,available:bool,runtime_preference:string}
     */
    private static function resolveCssLiveEditorThemeOptions(): array
    {
        $servicePath = APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';
        if (is_file($servicePath)) {
            require_once $servicePath;
        }

        if (class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')) {
            $options = \Apps\Shell\Services\ThemePreferenceService::themeChoices();
            if (is_array($options) && $options !== []) {
                return [
                    'options' => $options,
                    'available' => true,
                    'runtime_preference' => \Apps\Shell\Services\ThemePreferenceService::defaultPreference(),
                ];
            }
        }

        $runtimePreference = function_exists('default_theme_preference')
            ? trim((string)default_theme_preference())
            : '';

        return [
            'options' => $runtimePreference !== '' ? [$runtimePreference => $runtimePreference] : [],
            'available' => false,
            'runtime_preference' => $runtimePreference,
        ];
    }

    /**
     * @return array<int,array{id:string,path:string,label:string,kind:string,absolute_path:string}>
     */
    private static function resolveThemeSourceFiles(): array
    {
        $themesRoot = APP_ROOT . '/resources/themes';
        $manifestPath = $themesRoot . '/theme-manifest.json';
        if (!is_dir($themesRoot)) {
            return [];
        }

        $manifestSources = [];
        $disabledIds = [];
        $disabledPaths = [];
        if (is_file($manifestPath)) {
            $raw = @file_get_contents($manifestPath);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($decoded) && is_array($decoded['sources'] ?? null)) {
                $manifestSources = $decoded['sources'];
            }
        }

        $sources = [];
        $includedPaths = [];
        foreach ($manifestSources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $id = strtolower(trim((string)($source['id'] ?? '')));
            $path = self::normalizeThemeSourcePath((string)($source['path'] ?? ''));
            $kind = strtolower(trim((string)($source['kind'] ?? '')));
            $enabled = !empty($source['enabled']);
            if ($id === '' || $path === '') {
                continue;
            }
            if (!$enabled) {
                $disabledIds[$id] = true;
                $disabledPaths[$path] = true;
                continue;
            }

            $absolutePath = $themesRoot . '/' . $path;
            if (!self::isSafeThemeSourcePath($themesRoot, $absolutePath)) {
                continue;
            }

            $sources[] = [
                'id' => $id,
                'path' => $path,
                'label' => self::formatThemeTokenLabelPart($id),
                'kind' => $kind !== '' ? $kind : 'style',
                'absolute_path' => $absolutePath,
            ];
            $includedPaths[$path] = true;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themesRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $normalizedRoot = rtrim(str_replace('\\', '/', $themesRoot), '/');
        foreach ($iter as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            if (strtolower((string)$fileInfo->getExtension()) !== 'css') {
                continue;
            }

            $absolutePath = str_replace('\\', '/', (string)$fileInfo->getPathname());
            if (!str_starts_with($absolutePath, $normalizedRoot . '/')) {
                continue;
            }
            $path = self::normalizeThemeSourcePath(substr($absolutePath, strlen($normalizedRoot) + 1));
            $id = strtolower(str_replace('/', '.', preg_replace('/\.css$/i', '', $path) ?? ''));
            if (
                $path === ''
                || $id === ''
                || isset($includedPaths[$path])
                || isset($disabledPaths[$path])
                || isset($disabledIds[$id])
            ) {
                continue;
            }

            $sources[] = [
                'id' => $id,
                'path' => $path,
                'label' => self::formatThemeTokenLabelPart(str_replace('.', ' ', $id)),
                'kind' => 'custom',
                'absolute_path' => $absolutePath,
            ];
            $includedPaths[$path] = true;
        }

        return $sources;
    }

    private static function normalizeThemeSourcePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private static function isSafeThemeSourcePath(string $themesRoot, string $absolutePath): bool
    {
        $root = realpath($themesRoot);
        $resolved = realpath($absolutePath);
        if (!is_string($root) || !is_string($resolved) || !is_file($resolved)) {
            return false;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $resolved);
        return str_starts_with($normalizedPath, $normalizedRoot)
            && strtolower((string)pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'css';
    }

    /**
     * @param array<int,array{id:string,path:string,label:string,kind:string,absolute_path:string}> $sources
     * @return array<int,array<string,mixed>>
     */
    private static function readThemeSourceTokenSelectors(array $sources): array
    {
        $selectors = [];
        $countsBySelector = [];
        foreach ($sources as $source) {
            $sourcePath = (string)($source['absolute_path'] ?? '');
            if ($sourcePath === '' || !is_file($sourcePath)) {
                continue;
            }

            $css = @file_get_contents($sourcePath);
            if (!is_string($css) || $css === '') {
                continue;
            }

            $matches = [];
            preg_match_all(
                '/(*NO_JIT)(?P<selector>(?::root|\[[^\]]+\](?:\[[^\]]+\])?))\s*\{(?P<body>(?:[^{}]|(?:\{[^{}]*\}))*)\}/s',
                $css,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $entry) {
                $selector = trim((string)($entry['selector'] ?? ''));
                $body = (string)($entry['body'] ?? '');
                if ($selector === '' || $body === '') {
                    continue;
                }

                $tokenMap = [];
                $tokenMatches = [];
                preg_match_all('/--([a-z0-9_-]+)\s*:\s*([^;]+);/i', $body, $tokenMatches, PREG_SET_ORDER);
                foreach ($tokenMatches as $tokenMatch) {
                    $tokenName = strtolower(trim((string)($tokenMatch[1] ?? '')));
                    $tokenValue = trim((string)($tokenMatch[2] ?? ''));
                    if ($tokenName !== '' && $tokenValue !== '') {
                        $tokenMap[$tokenName] = $tokenValue;
                    }
                }
                if ($tokenMap === []) {
                    continue;
                }

                $themeName = 'default';
                $colorStyle = 'base';
                if (preg_match('/data-theme="([^"]+)"/i', $selector, $themeMatch) === 1) {
                    $themeName = strtolower(trim((string)($themeMatch[1] ?? 'default')));
                }
                if (preg_match('/data-color-style="([^"]+)"/i', $selector, $styleMatch) === 1) {
                    $colorStyle = strtolower(trim((string)($styleMatch[1] ?? 'base')));
                }

                $normalizedSelector = strtolower(trim(preg_replace('/\s+/', ' ', $selector)));
                $countsBySelector[$normalizedSelector] = ($countsBySelector[$normalizedSelector] ?? 0) + 1;
                $blockIndex = $countsBySelector[$normalizedSelector];
                $sourceLabel = trim((string)($source['label'] ?? ''));

                $labelMeta = self::classifyThemeSourceSelectorLabel(
                    $normalizedSelector,
                    $themeName,
                    $colorStyle,
                    $blockIndex,
                    $sourceLabel
                );

                $selectors[] = [
                    'selector' => $selector,
                    'theme' => $themeName,
                    'style' => $colorStyle,
                    'label' => $sourceLabel !== '' ? $sourceLabel : $selector,
                    'label_key' => $labelMeta['key'],
                    'label_value' => $labelMeta['value'],
                    'tokens' => $tokenMap,
                    'block_index' => $blockIndex,
                    'source_id' => (string)($source['id'] ?? ''),
                    'source_path' => 'resources/themes/' . (string)($source['path'] ?? ''),
                    'source_label' => $sourceLabel,
                    'source_kind' => (string)($source['kind'] ?? ''),
                ];
            }
        }

        return $selectors;
    }

    /**
     * @return array{key:string,value:string}
     */
    private static function classifyThemeSourceSelectorLabel(
        string $normalizedSelector,
        string $themeName,
        string $colorStyle,
        int $blockIndex,
        string $sourceLabel
    ): array {
        if ($normalizedSelector === ':root') {
            return ['key' => $blockIndex === 1 ? 'selector_global_defaults' : 'selector_safe_area', 'value' => ''];
        }
        if ($colorStyle === 'base') {
            return [
                'key' => 'selector_named_tokens',
                'value' => $sourceLabel !== '' ? $sourceLabel : self::formatThemeTokenLabelPart($themeName),
            ];
        }
        if ($themeName === 'default') {
            return ['key' => 'selector_system_tokens', 'value' => ''];
        }

        return ['key' => 'selector_named_tokens', 'value' => self::formatThemeTokenLabelPart($themeName)];
    }

    private static function labelDesignerRedirect(string $workspace, array $extraParams = []): never
    {
        $ws = self::resolveLabelDesignerWorkspace($workspace);
        $params = $extraParams;
        if ($ws !== 'overview') {
            $params['workspace'] = $ws;
        }
        $suffix = $params !== [] ? '?' . http_build_query($params) : '';
        header('Location: /apps/studio/tools/label-designer' . $suffix, true, 302);
        exit;
    }

    /**
     * @return array<string,string>
     */
    private static function labelDesignerWorkspaceAliases(): array
    {
        return [
            'contexts' => 'build',
            'templates' => 'build',
            'maintenance' => 'governance',
        ];
    }

    private static function resolveLabelDesignerWorkspace(string $workspace): string
    {
        $workspace = trim($workspace);
        $workspaceAliases = self::labelDesignerWorkspaceAliases();
        $workspace = $workspaceAliases[$workspace] ?? $workspace;
        $validWorkspaces = ['overview', 'build', 'rules', 'preview', 'governance'];
        return in_array($workspace, $validWorkspaces, true) ? $workspace : 'overview';
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildLabelDesignerPreviewModel(): array
    {
        $requestedWorkspace = isset($_GET['workspace']) ? trim((string)$_GET['workspace']) : 'overview';
        $owner = isset($_GET['owner']) ? trim((string)$_GET['owner']) : '';
        $purpose = isset($_GET['purpose']) ? trim((string)$_GET['purpose']) : 'Product Label';
        $source = isset($_GET['source']) ? trim((string)$_GET['source']) : '';
        $contextKey = isset($_GET['context_key']) ? trim((string)$_GET['context_key']) : '';
        $fields = isset($_GET['fields']) && is_array($_GET['fields']) ? $_GET['fields'] : [];
        $viewResource = isset($_GET['view_resource']) ? trim((string)$_GET['view_resource']) : '';
        $viewResourceContextKey = isset($_GET['context_key']) && $viewResource === 'context' ? trim((string)$_GET['context_key']) : '';
        $viewResourceTemplateKey = isset($_GET['template_key']) && $viewResource === 'template' ? trim((string)$_GET['template_key']) : '';
        $templateContext = isset($_GET['template_context']) ? trim((string)$_GET['template_context']) : '';
        $templateSize = isset($_GET['template_size']) ? trim((string)$_GET['template_size']) : '100x50_mm';
        $templateFields = isset($_GET['template_fields']) && is_array($_GET['template_fields']) ? $_GET['template_fields'] : [];
        $ruleContextId = isset($_GET['rule_context_id']) ? trim((string)$_GET['rule_context_id']) : '';
        $ruleTemplateId = isset($_GET['rule_template_id']) ? trim((string)$_GET['rule_template_id']) : '';
        $ruleKey = isset($_GET['rule_key']) ? trim((string)$_GET['rule_key']) : '';
        $ruleConditionField = isset($_GET['rc_condition_field']) ? trim((string)$_GET['rc_condition_field']) : '';
        $ruleOperator = isset($_GET['rc_operator']) ? trim((string)$_GET['rc_operator']) : 'equals';
        $ruleConditionValue = isset($_GET['rc_condition_value']) ? trim((string)$_GET['rc_condition_value']) : '';
        $ruleEffectType = isset($_GET['rc_effect_type']) ? trim((string)$_GET['rc_effect_type']) : 'show_warning';
        $ruleEffectTarget = isset($_GET['rc_effect_target']) ? trim((string)$_GET['rc_effect_target']) : '';
        $ruleEffectValue = isset($_GET['rc_effect_value']) ? trim((string)$_GET['rc_effect_value']) : '';
        $ruleEnabled = isset($_GET['rule_enabled']) ? trim((string)$_GET['rule_enabled']) : 'yes';
        $ruleValidationRequested = isset($_GET['rule_preview']) && (string)$_GET['rule_preview'] === '1';
        $viewRule = isset($_GET['view_rule']) ? trim((string)$_GET['view_rule']) : '';
        $previewGetContextKey = isset($_GET['context_key']) ? trim((string)$_GET['context_key']) : '';
        $previewGetTemplateKey = isset($_GET['template_key']) ? trim((string)$_GET['template_key']) : '';
        $previewRulesEnabled = isset($_GET['rules_enabled']) ? trim((string)$_GET['rules_enabled']) : '';

        $dataSourceDiscovery = LabelDesignerDataSourceDiscoveryService::discover(
            $owner !== '' ? $owner : null
        );
        $selectedOwnerKey = trim((string)($dataSourceDiscovery['selected_owner_key'] ?? ''));

        $contextCreatePreview = LabelDesignerContextCreateService::buildPreview([
            'owner' => $selectedOwnerKey,
            'purpose' => $purpose,
            'source' => $source,
            'context_key' => $contextKey,
            'fields' => $fields,
        ]);

        $templateCreatePreview = LabelDesignerTemplatePreviewService::buildPreview([
            'template_context' => $templateContext,
            'template_size' => $templateSize,
            'template_fields' => $templateFields,
        ]);

        $ruleCreatePreview = LabelDesignerRuleCreateService::buildPreview([
            'owner_key' => $selectedOwnerKey,
            'validation_requested' => $ruleValidationRequested,
            'rule_context_id' => $ruleContextId,
            'rule_template_id' => $ruleTemplateId,
            'rule_key' => $ruleKey,
            'rc_condition_field' => $ruleConditionField,
            'rc_operator' => $ruleOperator,
            'rc_condition_value' => $ruleConditionValue,
            'rc_effect_type' => $ruleEffectType,
            'rc_effect_target' => $ruleEffectTarget,
            'rc_effect_value' => $ruleEffectValue,
            'rule_enabled' => $ruleEnabled,
        ]);

        $flash = isset($_SESSION['studio_label_designer_flash']) && is_array($_SESSION['studio_label_designer_flash'])
            ? $_SESSION['studio_label_designer_flash']
            : [];
        unset($_SESSION['studio_label_designer_flash']);

        $editDiagnostics = isset($_SESSION['studio_label_designer_edit_diagnostics']) && is_array($_SESSION['studio_label_designer_edit_diagnostics'])
            ? $_SESSION['studio_label_designer_edit_diagnostics']
            : [];
        unset($_SESSION['studio_label_designer_edit_diagnostics']);

        $previewResult = isset($_SESSION['studio_label_designer_preview']) && is_array($_SESSION['studio_label_designer_preview'])
            ? $_SESSION['studio_label_designer_preview']
            : [];
        unset($_SESSION['studio_label_designer_preview']);

        $ruleSandboxResult = isset($_SESSION['studio_label_designer_rule_sandbox']) && is_array($_SESSION['studio_label_designer_rule_sandbox'])
            ? $_SESSION['studio_label_designer_rule_sandbox']
            : [];
        unset($_SESSION['studio_label_designer_rule_sandbox']);

        $previewContextOptions = LabelDesignerPreviewRendererService::resolveContextOptionsFromDiscovery(
            LabelDesignerDiscoveryService::discover()
        );
        $previewTemplateOptions = LabelDesignerPreviewRendererService::resolveTemplateOptionsFromDiscovery(
            LabelDesignerDiscoveryService::discover()
        );

        $previewSelectedTemplateId = '';
        $previewSelectedContextId = '';

        // Honor GET params for preview deep-linking
        if ($previewGetContextKey !== '') {
            foreach ($previewContextOptions as $ctxOpt) {
                if ((string)($ctxOpt['context_key'] ?? '') === $previewGetContextKey) {
                    $previewSelectedContextId = (string)($ctxOpt['context_id'] ?? '');
                    break;
                }
            }
        }

        if ($previewGetTemplateKey !== '') {
            foreach ($previewTemplateOptions as $tplOpt) {
                if ((string)($tplOpt['template_key'] ?? '') === $previewGetTemplateKey) {
                    $previewSelectedTemplateId = (string)($tplOpt['template_id'] ?? '');
                    break;
                }
            }
        }

        // Fallback: auto-select first compatible template when no GET params
        if ($previewSelectedContextId === '' && $previewSelectedTemplateId === '') {
            $firstContext = $previewContextOptions[0] ?? [];
            if ($firstContext !== []) {
                $firstOwnerKey = (string)($firstContext['owner_key'] ?? '');
                $firstContextKey = (string)($firstContext['context_key'] ?? '');
                $compatibleTemplates = array_values(array_filter(
                    $previewTemplateOptions,
                    static fn (array $tpl): bool =>
                        (string)($tpl['owner_key'] ?? '') === $firstOwnerKey
                        && (string)($tpl['context_key'] ?? '') === $firstContextKey
                ));
                if (count($compatibleTemplates) === 1) {
                    $previewSelectedTemplateId = (string)($compatibleTemplates[0]['template_id'] ?? '');
                }
            }
        }

        // Normalize source: if the requested source is not in the candidate list for the selected owner, reset to empty
        $candidateSources = isset($dataSourceDiscovery['candidate_sources']) && is_array($dataSourceDiscovery['candidate_sources'])
            ? array_values(array_filter($dataSourceDiscovery['candidate_sources'], 'is_array'))
            : [];
        if ($source !== '') {
            $sourceValid = false;
            foreach ($candidateSources as $cs) {
                if ((string)($cs['source_name'] ?? '') === $source) {
                    $sourceValid = true;
                    break;
                }
            }
            if (!$sourceValid) {
                $source = '';
            }
        }

        // Build rule sandbox field list from preview resolved fields
        $ruleSandboxFields = isset($previewResult['resolved_fields']) && is_array($previewResult['resolved_fields'])
            ? array_values(array_filter($previewResult['resolved_fields'], 'is_array'))
            : [];

        $previewActiveRules = isset($previewResult['active_rules']) && is_array($previewResult['active_rules'])
            ? $previewResult['active_rules']
            : [];
        $previewRuleEvaluation = isset($previewResult['rule_evaluation']) && is_array($previewResult['rule_evaluation'])
            ? $previewResult['rule_evaluation']
            : [];
        $previewRuleCount = (int)($previewResult['rule_count'] ?? 0);
        $previewRuleMatchedCount = (int)($previewResult['rule_matched_count'] ?? 0);

        $resourceDiagnostics = LabelDesignerResourceDiagnosticsService::scanAll();
        $runtimeDryRun = LabelDesignerRuntimeDryRunValidator::validateSample();
        $runtimeDryRunMatrix = LabelDesignerRuntimeDryRunValidator::validateScenarioMatrix();

        $metadataAnalysis = LabelDesignerResourceMetadataService::analyzeAll();
        $migrationPreviewResult = isset($_SESSION['studio_label_designer_migration_preview']) && is_array($_SESSION['studio_label_designer_migration_preview'])
            ? $_SESSION['studio_label_designer_migration_preview']
            : [];
        unset($_SESSION['studio_label_designer_migration_preview']);

        $migrationApplyResult = isset($_SESSION['studio_label_designer_migration_apply']) && is_array($_SESSION['studio_label_designer_migration_apply'])
            ? $_SESSION['studio_label_designer_migration_apply']
            : [];
        unset($_SESSION['studio_label_designer_migration_apply']);

        return [
            // Reference implementation: Manufacturing QR Product Label
            'source_surfaces' => [
                '/apps/manufacturing/products/360?id={product_id}#output-labels',
                '/qr/product/label',
            ],
            'print_route' => '/qr/product/label',
            'scan_route_template' => '/qr/product/scan?product_id={product_id}&part_number={parts_number}',
            'defaults' => [
                'copies' => 8,
                'min_copies' => 1,
                'max_copies' => 48,
                'include_date' => true,
                'include_serial_number' => false,
                'include_machine_no' => true,
                'include_case_number' => true,
                'include_qty_per_case' => true,
                'include_case_spec' => true,
                'include_cases_per_pallet' => true,
            ],
            'field_order' => [
                'production_date',
                'serial_number',
                'machine_no',
                'case_number',
                'qty_per_case',
                'case_spec',
                'cases_per_pallet',
            ],
            'reference_implementation_name' => 'Manufacturing QR Product Label',
            'label_active_workspace' => self::resolveLabelDesignerWorkspace($requestedWorkspace),

            // Architecture data
            'architecture_owner_resource_paths' => [
                '{OwnerRoot}/Resources/labels/contexts/',
                '{OwnerRoot}/Resources/labels/templates/',
                '{OwnerRoot}/Resources/labels/rules/',
            ],
            'future_label_examples' => [
                'Product labels',
                'Pallet labels',
                'Case labels',
                'Part labels',
                'Material labels',
                'Bin/location labels',
                'Dispatch labels',
                'QC labels',
                'Asset labels',
                'Service/job labels',
            ],
            'resource_contracts' => [
                [
                    'name' => 'Label Context Resource',
                    'meaning' => 'Owner, business purpose, allowed fields, data source boundary, and print/use location.',
                    'path' => '{OwnerRoot}/Resources/labels/contexts/',
                ],
                [
                    'name' => 'Label Template Resource',
                    'meaning' => 'Visual layout and design bound to one label context.',
                    'path' => '{OwnerRoot}/Resources/labels/templates/',
                ],
                [
                    'name' => 'Label Rule Set Resource',
                    'meaning' => 'Reusable owner-owned conditional style and business presentation rules that contexts or templates may attach.',
                    'path' => '{OwnerRoot}/Resources/labels/rules/',
                ],
            ],
            'resource_examples' => [
                'manufacturing.pallet',
                'manufacturing.part',
                'inventory.bin_location',
                'lazypos.product_price',
                'dispatch.delivery_case',
            ],
            'label_resource_discovery' => $fullDiscovery = LabelDesignerDiscoveryService::discover(),
            'label_existing_contexts' => $existingContexts = self::loadExistingResources(
                $fullDiscovery,
                $selectedOwnerKey,
                'contexts'
            ),
            'label_existing_templates' => $existingTemplates = self::loadExistingResources(
                $fullDiscovery,
                $selectedOwnerKey,
                'templates'
            ),
            'label_selected_context' => $viewResource === 'context' && $viewResourceContextKey !== ''
                ? (function() use ($existingContexts, $viewResourceContextKey) {
                    foreach ($existingContexts as $ctx) {
                        if (is_array($ctx) && ((string)($ctx['context_key'] ?? '')) === $viewResourceContextKey) {
                            return $ctx;
                        }
                    }
                    return null;
                })()
                : null,
            'label_view_resource' => $viewResource,
            'label_selected_template' => $viewResource === 'template' && $viewResourceTemplateKey !== ''
                ? (function() use ($existingTemplates, $viewResourceTemplateKey) {
                    foreach ($existingTemplates as $tpl) {
                        if (is_array($tpl) && ((string)($tpl['template_key'] ?? '')) === $viewResourceTemplateKey) {
                            return $tpl;
                        }
                    }
                    return null;
                })()
                : null,
            'label_existing_rules' => $existingRules = self::loadExistingResources(
                $fullDiscovery,
                $selectedOwnerKey,
                'rules'
            ),
            'label_view_rule' => $viewRule,
            'label_selected_rule' => $viewRule !== ''
                ? (function() use ($existingRules, $viewRule) {
                    foreach ($existingRules as $rule) {
                        if (is_array($rule) && ((string)($rule['rule_key'] ?? '')) === $viewRule) {
                            return $rule;
                        }
                    }
                    return null;
                })()
                : null,
            'label_rule_summary' => (function() use ($viewRule, $existingRules) {
                if ($viewRule === '') return '';
                $selected = null;
                foreach ($existingRules as $rule) {
                    if (is_array($rule) && ((string)($rule['rule_key'] ?? '')) === $viewRule) {
                        $selected = $rule;
                        break;
                    }
                }
                if ($selected === null) return '';
                $parts = [];
                $conditions = isset($selected['conditions']) && is_array($selected['conditions']) ? $selected['conditions'] : [];
                $effects = isset($selected['effects']) && is_array($selected['effects']) ? $selected['effects'] : [];
                foreach ($conditions as $cond) {
                    if (is_array($cond)) {
                        $field = (string)($cond['field_key'] ?? '');
                        $op = (string)($cond['operator'] ?? '');
                        $val = (string)($cond['value'] ?? '');
                        $parts[] = 'When ' . $field . ' ' . str_replace('_', ' ', $op) . ($val !== '' ? ' "' . $val . '"' : '');
                    }
                }
                foreach ($effects as $eff) {
                    if (is_array($eff)) {
                        $type = (string)($eff['type'] ?? '');
                        $target = (string)($eff['target'] ?? '');
                        $value = (string)($eff['value'] ?? '');
                        $parts[] = $type . ($target !== '' ? ' "' . $value . '"' : '');
                    }
                }
                return implode(', ', $parts);
            })(),
            'label_data_source_discovery' => $dataSourceDiscovery,
            'label_context_create_preview' => $contextCreatePreview,
            'label_template_create_preview' => $templateCreatePreview,
            'label_rule_create_preview' => $ruleCreatePreview,
            'label_resource_readiness' => LabelDesignerResourceReadinessService::checkReadiness(),
            'label_designer_flash' => $flash,
            'label_edit_diagnostics' => $editDiagnostics,
            'label_preview_result' => $previewResult,
            'label_preview_context_options' => $previewContextOptions,
            'label_preview_template_options' => $previewTemplateOptions,
            'label_preview_selected_template_id' => $previewSelectedTemplateId,
            'label_preview_selected_context_id' => $previewSelectedContextId,
            'label_preview_rules_enabled' => $previewRulesEnabled === '1' ? '1' : '',
            'label_preview_active_rules' => $previewActiveRules,
            'label_preview_rule_evaluation' => $previewRuleEvaluation,
            'label_preview_rule_count' => $previewRuleCount,
            'label_preview_rule_matched_count' => $previewRuleMatchedCount,
            'label_rule_sandbox_result' => $ruleSandboxResult,
            'label_rule_sandbox_fields' => $ruleSandboxFields,
            'label_resource_diagnostics' => $resourceDiagnostics,
            'label_runtime_dry_run' => $runtimeDryRun,
            'label_runtime_dry_run_matrix' => $runtimeDryRunMatrix,
            'label_metadata_analysis' => $metadataAnalysis,
            'label_migration_preview' => $migrationPreviewResult,
            'label_migration_apply' => $migrationApplyResult,
            'csrf' => Auth::csrfToken(),
        ];
    }

    /**
     * Read existing context/template JSON files from discovery and return summary arrays.
     *
     * @param array<string,mixed> $fullDiscovery
     * @return array<int,array<string,mixed>>
     */
    private static function loadExistingResources(array $fullDiscovery, string $ownerKey, string $type): array
    {
        if ($ownerKey === '') {
            return [];
        }

        $ownerData = null;
        foreach (($fullDiscovery['owners'] ?? []) as $owner) {
            if (is_array($owner) && strcasecmp((string)($owner['owner_key'] ?? ''), $ownerKey) === 0) {
                $ownerData = $owner;
                break;
            }
        }
        if ($ownerData === null) {
            return [];
        }

        $resources = isset($ownerData['resources'][$type]['files']) && is_array($ownerData['resources'][$type]['files'])
            ? $ownerData['resources'][$type]['files']
            : [];

        $result = [];
        foreach ($resources as $file) {
            $path = (string)($file['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $fullPath = APP_ROOT . '/' . $path;
            $content = is_file($fullPath) ? @file_get_contents($fullPath) : null;
            if (!is_string($content)) {
                continue;
            }
            $data = @json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }
            $result[] = $data;
        }
        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildCustomizationStudioLandingModel(): array
    {
        $capabilityInventory = \Apps\Studio\Tools\CustomizationStudio\Services\CustomizationStudioCapabilityInventory::inventoryWithDiagnostics();

        return [
            'runtime_status' => 'disabled_preview_skeleton',
            'capability_inventory' => $capabilityInventory,
            'style_tools' => [
                // Theme System
                [
                    'key' => 'theme_doctor',
                    'name_key' => 'style_tool_theme_doctor',
                    'desc_key' => 'style_tool_theme_doctor_desc',
                    'href' => '/apps/studio/tools/customization-studio/diagnose/theme-doctor',
                    'status_key' => 'style_tools_status_readonly',
                    'group' => 'theme_system',
                ],
                [
                    'key' => 'style_compliance',
                    'name_key' => 'style_tool_style_compliance',
                    'desc_key' => 'style_tool_style_compliance_desc',
                    'href' => '/apps/studio/tools/customization-studio/diagnose/style-compliance',
                    'status_key' => 'style_tools_status_available',
                    'group' => 'theme_system',
                ],
                [
                    'key' => 'css_token_editor',
                    'name_key' => 'style_tool_css_token_editor',
                    'desc_key' => 'style_tool_css_token_editor_desc',
                    'href' => '/apps/studio/tools/customization-studio/design-system/tokens',
                    'status_key' => 'style_tools_status_governed',
                    'group' => 'theme_system',
                ],
                [
                    'key' => 'token_impact_explorer',
                    'name_key' => 'style_tool_token_impact_explorer',
                    'desc_key' => 'style_tool_token_impact_explorer_desc',
                    'href' => '/apps/studio/tools/customization-studio/diagnose/token-impact-explorer',
                    'status_key' => 'style_tools_status_available',
                    'group' => 'theme_system',
                ],
                // CSS Authoring & Inspection
                [
                    'key' => 'css_live_editor',
                    'name_key' => 'style_tool_css_live_editor',
                    'desc_key' => 'style_tool_css_live_editor_desc',
                    'href' => '/apps/studio/tools/customization-studio/advanced/css-live-editor',
                    'status_key' => 'style_tools_status_planned',
                    'group' => 'css_authoring',
                ],
                [
                    'key' => 'css_selector_tool',
                    'name_key' => 'style_tool_css_selector_tool',
                    'desc_key' => 'style_tool_css_selector_tool_desc',
                    'href' => '/apps/studio/tools/customization-studio/diagnose/css-selector-inspector',
                    'status_key' => 'style_tools_status_planned',
                    'group' => 'css_authoring',
                ],
                // Visual Customization
                [
                    'key' => 'visual_customizer',
                    'name_key' => 'style_tool_visual_customizer',
                    'desc_key' => 'style_tool_visual_customizer_desc',
                    'href' => '/apps/studio/tools/customization-studio/visual-customizer',
                    'status_key' => 'style_tools_status_disabled',
                    'group' => 'visual_customization',
                ],
                [
                    'key' => 'special_effects',
                    'name_key' => 'style_tool_special_effects',
                    'desc_key' => 'style_tool_special_effects_desc',
                    'href' => '/apps/studio/tools/customization-studio/effects',
                    'status_key' => 'style_tools_status_readonly',
                    'group' => 'visual_customization',
                ],
            ],
            'subtools' => [
                [
                    'key' => 'special_effects',
                    'title_key' => 'subtool_special_effects',
                    'description_key' => 'subtool_special_effects_desc',
                    'href' => '/apps/studio/tools/customization-studio/effects',
                    'is_enabled' => true,
                ],
                [
                    'key' => 'visual_customizer',
                    'title_key' => 'subtool_visual_customizer',
                    'description_key' => 'subtool_visual_customizer_desc',
                    'href' => '/apps/studio/tools/customization-studio/visual-customizer',
                    'is_enabled' => true,
                ],
                [
                    'key' => 'theme_manager',
                    'title_key' => 'subtool_theme_manager',
                    'description_key' => 'subtool_theme_manager_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'component_style_editor',
                    'title_key' => 'subtool_component_style_editor',
                    'description_key' => 'subtool_component_style_editor_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'layout_style_editor',
                    'title_key' => 'subtool_layout_style_editor',
                    'description_key' => 'subtool_layout_style_editor_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'motion_style_editor',
                    'title_key' => 'subtool_motion_style_editor',
                    'description_key' => 'subtool_motion_style_editor_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'visualization_style_editor',
                    'title_key' => 'subtool_visualization_style_editor',
                    'description_key' => 'subtool_visualization_style_editor_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'print_style_editor',
                    'title_key' => 'subtool_print_style_editor',
                    'description_key' => 'subtool_print_style_editor_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
                [
                    'key' => 'advanced_css_tool',
                    'title_key' => 'subtool_advanced_css_tool',
                    'description_key' => 'subtool_advanced_css_tool_desc',
                    'href' => '',
                    'is_enabled' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function readThemeTokenSelectors(string $themeCssPath): array
    {
        if (!is_file($themeCssPath)) {
            return [];
        }

        $css = @file_get_contents($themeCssPath);
        if (!is_string($css) || $css === '') {
            return [];
        }

        $matches = [];
        preg_match_all(
            '/(*NO_JIT)(?P<selector>(?::root|\[[^\]]+\](?:\[[^\]]+\])?))\s*\{(?P<body>(?:[^{}]|(?:\{[^{}]*\}))*)\}/s',
            $css,
            $matches,
            PREG_SET_ORDER
        );

        $selectors = [];
        $countsBySelector = [];
        foreach ($matches as $entry) {
            $selector = trim((string)($entry['selector'] ?? ''));
            $body = (string)($entry['body'] ?? '');
            if ($selector === '' || $body === '') {
                continue;
            }

            $tokenMap = [];
            $tokenMatches = [];
            preg_match_all('/--([a-z0-9_-]+)\s*:\s*([^;]+);/i', $body, $tokenMatches, PREG_SET_ORDER);
            foreach ($tokenMatches as $tokenMatch) {
                $tokenName = strtolower(trim((string)($tokenMatch[1] ?? '')));
                $tokenValue = trim((string)($tokenMatch[2] ?? ''));
                if ($tokenName === '' || $tokenValue === '') {
                    continue;
                }
                $tokenMap[$tokenName] = $tokenValue;
            }

            if ($tokenMap === []) {
                continue;
            }

            $themeName = 'default';
            $colorStyle = 'base';
            if (preg_match('/data-theme="([^"]+)"/i', $selector, $themeMatch) === 1) {
                $themeName = strtolower(trim((string)($themeMatch[1] ?? 'default')));
            }
            if (preg_match('/data-color-style="([^"]+)"/i', $selector, $styleMatch) === 1) {
                $colorStyle = strtolower(trim((string)($styleMatch[1] ?? 'base')));
            }

            $normalizedSelector = strtolower(trim(preg_replace('/\s+/', ' ', $selector)));
            if (!isset($countsBySelector[$normalizedSelector])) {
                $countsBySelector[$normalizedSelector] = 0;
            }
            $countsBySelector[$normalizedSelector]++;
            $blockIndex = $countsBySelector[$normalizedSelector];

            $purposeSuffix = '';
            if ($normalizedSelector === ':root' && $blockIndex === 2) {
                $purposeSuffix = ' / Safe Area';
            } elseif ($blockIndex > 1) {
                $purposeSuffix = ' #' . $blockIndex;
            }

            $selectors[] = [
                'selector' => $selector,
                'theme' => $themeName,
                'style' => $colorStyle,
                'label' => self::formatThemeTokenSelectorLabel($normalizedSelector, $themeName, $colorStyle, $blockIndex),
                'tokens' => $tokenMap,
                'block_index' => $blockIndex,
            ];
        }

        return $selectors;
    }

    private static function formatThemeTokenSelectorLabel(string $normalizedSelector, string $themeName, string $colorStyle, int $blockIndex): string
    {
        if ($normalizedSelector === ':root') {
            return $blockIndex === 1
                ? 'Foundation Tokens / Global Defaults'
                : 'Foundation Tokens / Safe Area';
        }

        $themeLabel = self::formatThemeTokenLabelPart($themeName);
        $styleLabel = self::formatThemeTokenLabelPart($colorStyle);

        if ($themeName === 'default' && $colorStyle === 'liquid-glass') {
            return 'System / Liquid Glass';
        }

        if ($themeName === 'default') {
            return 'Global ' . $styleLabel . ' Tokens';
        }

        if ($colorStyle === 'base') {
            return $themeLabel . ' / Base Tokens';
        }

        return $themeLabel . ' / ' . $styleLabel;
    }

    private static function formatThemeTokenLabelPart(string $value): string
    {
        $words = preg_split('/[-_\s]+/', strtolower(trim($value))) ?: [];
        $label = [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $label[] = strtoupper(substr($word, 0, 1)) . substr($word, 1);
        }
        return $label !== [] ? implode(' ', $label) : 'Default';
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function homeCards(): array
    {
        $cards = [];
        $tools = StudioToolPresentationService::decorateTools(StudioGovernedToolRegistryService::listTools());
        foreach ($tools as $tool) {
            if (empty($tool['visible_on_home'])) {
                continue;
            }

            $toolKey = trim((string)($tool['key'] ?? ''));
            $status = trim((string)($tool['status'] ?? 'planned'));
            $isEnabled = StudioToolInstancePolicyService::isEnabled($toolKey, $tool);
            $statusKey = 'studio_home_status_enabled';
            if (!$isEnabled) {
                $statusKey = 'studio_home_status_disabled';
            } elseif (!empty($tool['placeholder']) || $status === 'planned') {
                $statusKey = 'studio_home_status_placeholder';
            } elseif (in_array($status, ['read_only', 'inspection', 'available'], true) && empty($tool['can_modify'])) {
                $statusKey = 'studio_home_status_inspection_only';
            } elseif (!in_array($status, ['active', 'available'], true)) {
                $statusKey = 'studio_home_status_migrating';
            }

            $cards[] = [
                'id' => $toolKey,
                'tool_key' => $toolKey,
                'display_name' => (string)($tool['display_name'] ?? $tool['name'] ?? $toolKey),
                'description' => (string)($tool['description'] ?? ''),
                'home_group' => (string)($tool['home_group'] ?? 'governance'),
                'status_key' => $statusKey,
                'href' => $isEnabled ? (string)($tool['canonical_route'] ?? '') : '',
                'is_enabled' => $isEnabled,
            ];
        }

        $cards = array_merge($cards, [
            [
                'id' => 'workflow_analyze',
                'name_key' => 'studio_home_workflow_analyze',
                'desc_key' => 'studio_home_workflow_analyze_desc',
                'home_group' => 'workflow',
                'href' => '/apps/studio/workflow/analyze',
            ],
            [
                'id' => 'workflow_changes',
                'name_key' => 'studio_home_workflow_changes',
                'desc_key' => 'studio_home_workflow_changes_desc',
                'home_group' => 'workflow',
                'href' => '/apps/studio/workflow/changes',
            ],
            [
                'id' => 'workflow_apply',
                'name_key' => 'studio_home_workflow_apply',
                'desc_key' => 'studio_home_workflow_apply_desc',
                'home_group' => 'workflow',
                'href' => '/apps/studio/workflow/apply',
            ],
            [
                'id' => 'legacy_all_in_one',
                'name_key' => 'studio_home_legacy',
                'desc_key' => 'studio_home_legacy_desc',
                'home_group' => 'migration',
                'status_key' => 'studio_home_status_migrating',
                'href' => '/apps/studio/legacy',
            ],
        ]);

        foreach ($cards as &$card) {
            if (array_key_exists('is_enabled', $card)) {
                continue;
            }
            $toolKey = trim((string)($card['tool_key'] ?? ''));
            $isEnabled = true;
            if ($toolKey !== '') {
                $isEnabled = StudioToolInstancePolicyService::isEnabled($toolKey);
            }

            $card['is_enabled'] = $isEnabled;
            $card['status_key'] = $isEnabled
                ? (string)($card['status_key'] ?? 'studio_home_status_enabled')
                : 'studio_home_status_disabled';
            if (!$isEnabled) {
                $card['href'] = '';
            }
        }
        unset($card);

        return $cards;
    }

    /**
     * @return array{library:array<string,mixed>,selected:?array<string,mixed>,selected_id:string}
     */
    private static function buildGlobalLibraryContext(Router $router, array $query): array
    {
        $library = AppStudioRegistryService::buildGlobalLibrary($router->listRoutes());
        $selectedId = trim((string)($query['library_item'] ?? ''));
        return [
            'library' => $library,
            'selected' => AppStudioRegistryService::findNodeById($library, $selectedId),
            'selected_id' => $selectedId,
        ];
    }

    /**
     * @param array<string,mixed> $inputs
     * @param array<string,mixed>|null $bundle
     * @param array<string,mixed> $importModel
     * @return array<string,mixed>
     */
    private static function buildStudioDataContractContext(array $inputs, ?array $bundle = null, array $importModel = []): array
    {
        $resolvedBundle = is_array($bundle) ? $bundle : GuiStudioService::decodeStructuredBundlePayload($inputs);
        return StudioDataContractService::extract($resolvedBundle, $importModel);
    }

    /**
     * @param array<string,mixed> $inputs
     * @param array<string,mixed> $dataContract
     * @param array<string,mixed>|null $bundle
     * @return array<string,mixed>
     */
    private static function buildStudioDependencyGraphContext(array $inputs, array $dataContract, ?array $bundle = null): array
    {
        $resolvedBundle = is_array($bundle) ? $bundle : GuiStudioService::decodeStructuredBundlePayload($inputs);
        return StudioDependencyGraphService::build($resolvedBundle, $dataContract);
    }

    public static function localizationScanExtractionAddMissingKeys(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));

        if ($ownerKey === '') {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Owner is required.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction');
            return;
        }

        try {
            $report = LocalizationScanCorrectionService::addMissingEnglishKeysWithReport($ownerKey, $scope, $locale);

            if (!$report->success && $report->snapshotPaths !== []) {
                $rollbackResult = RollbackService::rollbackFromReport($report->snapshotPaths);
                $report = $report->withRollbackStatus(!empty($rollbackResult['ok']) ? 'completed' : 'failed');
            }

            HistoryStore::store($report);
            $_SESSION['studio_lse_correction_report'] = $report->toArray();

            if ($report->success) {
                $_SESSION['studio_lse_correction_flash'] = [
                    'success' => true,
                    'message' => $report->message ?? ('Added ' . $report->addedCount . ' keys.'),
                    'added_count' => $report->addedCount,
                    'skipped' => $report->skippedCount,
                ];
            } else {
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => $report->error ?? 'Correction failed.'];
            }
        } catch (\Throwable $e) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Correction failed: ' . $e->getMessage()];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
    }

    public static function localizationScanExtractionAddSelectedKeys(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));
        $selectedKeys = (array)($_POST['selected_keys'] ?? []);

        if ($ownerKey === '') {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Owner is required.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction');
            return;
        }

        if ($selectedKeys === []) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'No keys selected.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
            return;
        }

        try {
            $report = LocalizationScanCorrectionService::addMissingEnglishKeysSelectedWithReport($ownerKey, $scope, $locale, $selectedKeys);

            if (!$report->success && $report->snapshotPaths !== []) {
                $rollbackResult = RollbackService::rollbackFromReport($report->snapshotPaths);
                $report = $report->withRollbackStatus(!empty($rollbackResult['ok']) ? 'completed' : 'failed');
            }

            HistoryStore::store($report);
            $_SESSION['studio_lse_correction_report'] = $report->toArray();

            if ($report->success) {
                $_SESSION['studio_lse_correction_flash'] = [
                    'success' => true,
                    'message' => $report->message ?? ('Added ' . $report->addedCount . ' selected key(s).'),
                    'added_count' => $report->addedCount,
                    'skipped' => $report->skippedCount,
                ];
            } else {
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => $report->error ?? 'Correction failed.'];
            }
        } catch (\Throwable $e) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Correction failed: ' . $e->getMessage()];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
    }

    public static function localizationScanExtractionExtractInlineText(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));
        $findingIndex = (int)($_POST['finding_index'] ?? -1);

        if ($ownerKey === '') {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Owner is required.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction');
            return;
        }

        if ($findingIndex < 0) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Valid finding index is required.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction');
            return;
        }

        try {
            $report = LocalizationScanCorrectionService::extractInlineTextWithReport($ownerKey, $findingIndex, $scope, $locale);

            if (!$report->success && $report->snapshotPaths !== []) {
                $rollbackResult = RollbackService::rollbackFromReport($report->snapshotPaths);
                $report = $report->withRollbackStatus(!empty($rollbackResult['ok']) ? 'completed' : 'failed');
            }

            HistoryStore::store($report);
            $_SESSION['studio_lse_correction_report'] = $report->toArray();

            if ($report->success) {
                $_SESSION['studio_lse_correction_flash'] = [
                    'success' => true,
                    'message' => $report->message ?? 'Extraction completed.',
                    'added_count' => 1,
                ];
            } else {
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => $report->error ?? 'Extraction failed.'];
            }
        } catch (\Throwable $e) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Extraction failed: ' . $e->getMessage()];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
    }

    public static function localizationScanExtractionApplyInlineMigration(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));

        if ($ownerKey === '') {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Owner is required.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction');
            return;
        }

        try {
            $scanResults = \Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanService::scan($ownerKey, $scope, $locale);
            if (empty($scanResults['scan_ok'])) {
                $err = $scanResults['error'] ?? 'Re-scan failed before migration';
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => $err];
                self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
                return;
            }

            $findings = $scanResults['findings'] ?? [];
            $readyCandidates = InlineMigrationApplyService::getReadyCandidatesFromFindings($findings, $ownerKey);

            if ($readyCandidates === []) {
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'No ready-to-migrate candidates found in current scan.'];
                self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
                return;
            }

            $report = InlineMigrationApplyService::apply($readyCandidates, $ownerKey, $scope, $locale);

            if (!$report->success && $report->snapshotPaths !== []) {
                $rollbackResult = RollbackService::rollbackFromReport($report->snapshotPaths);
                $report = $report->withRollbackStatus(!empty($rollbackResult['ok']) ? 'completed' : 'failed');
            }

            HistoryStore::store($report);
            $_SESSION['studio_lse_correction_report'] = $report->toArray();

            if ($report->success) {
                $_SESSION['studio_lse_correction_flash'] = [
                    'success' => true,
                    'message' => $report->message ?? ('Inline migration completed.'),
                    'added_count' => $report->addedCount,
                ];
            } else {
                $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => $report->error ?? 'Inline migration failed.'];
            }
        } catch (\Throwable $e) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Inline migration failed: ' . $e->getMessage()];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
    }

    public static function localizationScanExtractionRollback(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $ownerKey = trim((string)($_POST['owner'] ?? ''));
        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));
        $snapshotKeys = (array)($_POST['snapshot_keys'] ?? []);

        if ($snapshotKeys === []) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'No snapshot keys provided for rollback.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
            return;
        }

        $snapshotPaths = [];
        $historyDir = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction';
        foreach ($snapshotKeys as $key) {
            $safe = trim(str_replace('\\', '/', (string)$key), '/');
            if ($safe === '' || str_contains($safe, '..')) {
                continue;
            }
            $path = $historyDir . '/' . $safe;
            if (is_file($path)) {
                $snapshotPaths[] = $path;
            }
        }

        if ($snapshotPaths === []) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'None of the specified snapshot files were found.'];
            self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
            return;
        }

        $result = RollbackService::rollbackFromReport($snapshotPaths);

        if ($result['ok']) {
            $_SESSION['studio_lse_correction_flash'] = [
                'success' => true,
                'message' => 'Rollback complete. ' . count($result['restored']) . ' file(s) restored from snapshots.',
                'restored' => $result['restored'],
            ];
        } else {
            $_SESSION['studio_lse_correction_flash'] = [
                'success' => false,
                'error' => 'Rollback partially failed. ' . count($result['restored']) . ' restored, ' . count($result['failed']) . ' failed.',
                'restored' => $result['restored'],
                'failed' => $result['failed'],
            ];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction?owner=' . rawurlencode($ownerKey) . '&scope=' . rawurlencode($scope) . '&locale=' . rawurlencode($locale));
    }

    public static function localizationScanExtractionFullScan(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/tools/localization-scan-extraction');

        $scope = trim((string)($_POST['scope'] ?? 'views'));
        $locale = trim((string)($_POST['locale'] ?? 'en'));

        try {
            $scan = FullSystemScanService::scanAll($scope, $locale);
            $_SESSION['studio_lse_full_scan'] = $scan;
            $_SESSION['studio_lse_correction_flash'] = [
                'success' => !empty($scan['scan_ok']),
                'message' => 'Full system scan complete. ' . $scan['totals']['owners'] . ' owners scanned, ' . $scan['totals']['total_findings'] . ' findings.',
                'error' => empty($scan['scan_ok']) ? ($scan['error'] ?? 'Full system scan failed') : null,
            ];
        } catch (\Throwable $e) {
            $_SESSION['studio_lse_correction_flash'] = ['success' => false, 'error' => 'Full scan failed: ' . $e->getMessage()];
        }

        self::redirect('/apps/studio/tools/localization-scan-extraction');
    }

    /**
     * Render the engineering workspace document viewer/editor.
     */
    public static function engineeringWorkspaceViewer(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/engineering-workspaces');

        $workspaceKey = trim((string)($_GET['workspace_key'] ?? ''));
        $documentType = trim((string)($_GET['document'] ?? 'overview'));
        $mode = (string)($_GET['mode'] ?? 'view');

        $allowedTypes = EngineeringWorkspaceResolver::getAllowedDocumentTypes();
        if (!in_array($documentType, $allowedTypes, true)) {
            $documentType = 'overview';
        }

        $actor = PlatformAuthority::resolveCurrentActor();
        $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentType, $actor);

        if (empty($resolved['authorized'])) {
            http_response_code(403);
            self::renderErrorView($view, 'Forbidden', 'You do not have permission to access engineering workspaces.');
            return;
        }

        $documentLabels = [];
        foreach ($allowedTypes as $type) {
            $documentLabels[$type] = EngineeringWorkspaceContentContract::documentLabel($type) ?? ucfirst($type);
        }

        // Determine error state — never fall back to a default workspace
        $errorState = self::categorizeWorkspaceError($resolved, $workspaceKey, $documentType);

        $rawContent = '';
        $safeContent = '';
        $workTasks = [];
        $isWorkDocument = $documentType === 'work';

        if ($errorState === null && !empty($resolved['exists']) && $resolved['content'] !== null) {
            $rawContent = (string)$resolved['content'];
            $contentForRender = EngineeringWorkspaceContentContract::markdownForDisplay($documentType, $rawContent);
            $safeContent = MarkdownRenderer::render($contentForRender);

            if ($isWorkDocument) {
                try {
                    $parsed = WorkTaskParser::parseSupportedTaskLines($rawContent);
                    $workTasks = is_array($parsed) ? $parsed : [];
                } catch (\Throwable $e) {
                    $workTasks = [];
                    error_log('EngineeringWorkspace: WorkTaskParser failed for ' . $workspaceKey . '/work: ' . $e->getMessage());
                }
            }
        }

        $toggleSuccess = !empty($_SESSION['ew_toggle_success']);
        $toggleError = (string)($_SESSION['ew_toggle_error'] ?? '');
        $toggleStale = !empty($_SESSION['ew_toggle_stale']);
        unset($_SESSION['ew_toggle_success'], $_SESSION['ew_toggle_error'], $_SESSION['ew_toggle_stale']);

        $engineeringWorkspaceView = [
            'authorized' => !empty($resolved['authorized']),
            'workspace_key' => $workspaceKey,
            'document_type' => $documentType,
            'document_label' => $documentLabels[$documentType] ?? 'Document',
            'document_tabs' => $documentLabels,
            'expected_sections' => EngineeringWorkspaceContentContract::requiredHeadings($documentType),
            'exists' => !empty($resolved['exists']),
            'rendered_content' => $safeContent,
            'raw_content' => $rawContent,
            'draft_content' => $rawContent,
            'fingerprint' => (string)($resolved['fingerprint'] ?? ''),
            'error' => (string)($resolved['error'] ?? ''),
            'error_state' => $errorState,
            'source_path' => (string)($resolved['path'] ?? ''),
            'mode' => ($mode === 'edit' && !empty($resolved['exists'])) ? 'edit' : 'view',
            'csrf' => \App\Core\Auth::csrfToken(),
            'save_ok' => false,
            'save_error' => '',
            'work_tasks' => $workTasks,
            'toggle_success' => $toggleSuccess,
            'toggle_error' => $toggleError,
            'toggle_stale' => $toggleStale,
        ];

        self::renderEngineeringWorkspaceView($view, $engineeringWorkspaceView);
    }

    /**
     * Categorize the workspace resolution result into a controlled error state.
     *
     * Returns null if the document is available and ready to display.
     * Returns 'not_found' for invalid/tampered keys (no details exposed).
     * Returns 'workspace_unavailable' when the workspace is not initialized.
     * Returns 'document_unavailable' when the workspace exists but this specific doc is missing.
     *
     * @param array<string,mixed> $resolved
     */
    private static function categorizeWorkspaceError(array $resolved, string $workspaceKey, string $documentType): ?string
    {
        $error = (string)($resolved['error'] ?? '');

        if (str_contains($error, 'Invalid workspace key') || str_contains($error, 'Path traversal')) {
            return 'not_found';
        }

        if (!empty($resolved['exists'])) {
            return null;
        }

        if ($workspaceKey !== '' && EngineeringWorkspaceContentContract::isSupportedWorkspaceKey($workspaceKey)) {
            $workspaceHasFiles = false;
            foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $docKey) {
                $docPath = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $docKey);
                if ($docPath !== null && is_file($docPath)) {
                    $workspaceHasFiles = true;
                    break;
                }
            }
            return $workspaceHasFiles ? 'document_unavailable' : 'workspace_unavailable';
        }

        return 'not_found';
    }

    /**
     * Render a simple error view without revealing workspace details.
     */
    private static function renderEngineeringWorkspaceView(View $view, array $engineeringWorkspaceView): void
    {
        $workspaceKey = trim((string)($engineeringWorkspaceView['workspace_key'] ?? ''));
        if (!isset($engineeringWorkspaceView['parent_page']) || !is_array($engineeringWorkspaceView['parent_page'])) {
            $engineeringWorkspaceView['parent_page'] = EngineeringWorkspacePageContextResolver::parentPageForWorkspace($workspaceKey);
        }
        $documentLabel = trim((string)($engineeringWorkspaceView['document_label'] ?? 'Engineering Workspace'));
        $pageTitle = $workspaceKey !== ''
            ? $workspaceKey . ' - ' . $documentLabel
            : 'Engineering Workspace';

        require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';
        $view->renderRaw('studio::engineering-workspace/viewer.php', ['engineeringWorkspaceView' => $engineeringWorkspaceView]);
        require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
    }

    /**
     * Render a controlled engineering workspace error inside the Admin wrapper.
     */
    private static function renderErrorView(View $view, string $title, string $message): void
    {
        http_response_code(in_array($title, ['Forbidden', 'Not Found'], true) ? (
            $title === 'Forbidden' ? 403 : 404
        ) : 404);
        self::renderEngineeringWorkspaceView($view, [
            'workspace_key' => '',
            'document_type' => 'overview',
            'document_label' => $title,
            'document_tabs' => [],
            'expected_sections' => [],
            'exists' => false,
            'rendered_content' => '',
            'raw_content' => '',
            'draft_content' => '',
            'fingerprint' => '',
            'error' => $message,
            'error_state' => 'access_error',
            'source_path' => '',
            'mode' => 'view',
            'csrf' => \App\Core\Auth::csrfToken(),
            'save_ok' => false,
            'save_error' => '',
            'error_title' => $title,
            'error_message' => $message,
        ]);
    }

    /**
     * Handle engineering workspace save (POST).
     */
    public static function engineeringWorkspaceSave(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/engineering-workspaces/save');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $documentType = trim((string)($_POST['document'] ?? 'overview'));
        $content = (string)($_POST['markdown'] ?? '');
        $fingerprint = trim((string)($_POST['fingerprint'] ?? ''));

        $allowedTypes = EngineeringWorkspaceResolver::getAllowedDocumentTypes();
        if (!in_array($documentType, $allowedTypes, true)) {
            $documentType = 'overview';
        }

        $actor = PlatformAuthority::resolveCurrentActor();
        $writeResult = EngineeringWorkspaceResolver::writeWithFingerprint($workspaceKey, $documentType, $content, $fingerprint, $actor);

        $documentLabels = [];
        foreach ($allowedTypes as $type) {
            $documentLabels[$type] = EngineeringWorkspaceContentContract::documentLabel($type) ?? ucfirst($type);
        }

        if (empty($writeResult['ok'])) {
            // Re-render the viewer with the error
            $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentType, $actor);
            $rawContent = (string)($resolved['content'] ?? '');
            $safeContent = MarkdownRenderer::render(EngineeringWorkspaceContentContract::markdownForDisplay($documentType, $content));
            $engineeringWorkspaceView = [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'document_type' => $documentType,
                'document_label' => $documentLabels[$documentType] ?? 'Document',
                'document_tabs' => $documentLabels,
                'expected_sections' => EngineeringWorkspaceContentContract::requiredHeadings($documentType),
                'exists' => !empty($resolved['exists']),
                'rendered_content' => $safeContent,
                'raw_content' => $rawContent,
                'draft_content' => $content,
                'fingerprint' => $fingerprint,
                'stale_write' => !empty($writeResult['stale']),
                'error' => (string)($resolved['error'] ?? ''),
                'source_path' => (string)($resolved['path'] ?? ''),
                'mode' => 'edit',
                'csrf' => \App\Core\Auth::csrfToken(),
                'save_ok' => false,
                'save_error' => (string)($writeResult['error'] ?? 'Save failed'),
            ];
            self::renderEngineeringWorkspaceView($view, $engineeringWorkspaceView);
            return;
        }

        // Re-read and render the saved content
        $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentType, $actor);
        $rawContent = (string)($resolved['content'] ?? '');
        $safeContent = MarkdownRenderer::render(EngineeringWorkspaceContentContract::markdownForDisplay($documentType, $rawContent));
        $engineeringWorkspaceView = [
            'authorized' => true,
            'workspace_key' => $workspaceKey,
            'document_type' => $documentType,
            'document_label' => $documentLabels[$documentType] ?? 'Document',
            'document_tabs' => $documentLabels,
            'expected_sections' => EngineeringWorkspaceContentContract::requiredHeadings($documentType),
            'exists' => !empty($resolved['exists']),
            'rendered_content' => $safeContent,
            'raw_content' => $rawContent,
            'draft_content' => $rawContent,
            'fingerprint' => (string)($resolved['fingerprint'] ?? ''),
            'error' => '',
            'source_path' => (string)($resolved['path'] ?? ''),
            'mode' => 'view',
            'csrf' => \App\Core\Auth::csrfToken(),
            'save_ok' => true,
            'save_error' => '',
        ];

        self::renderEngineeringWorkspaceView($view, $engineeringWorkspaceView);
    }

    public static function engineeringWorkspacePreview(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/engineering-workspaces/preview');
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/apps/studio/engineering-workspaces');

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $documentType = trim((string)($_POST['document'] ?? 'overview'));
        $content = (string)($_POST['markdown'] ?? '');
        $fingerprint = trim((string)($_POST['fingerprint'] ?? ''));

        $allowedTypes = EngineeringWorkspaceResolver::getAllowedDocumentTypes();
        if (!in_array($documentType, $allowedTypes, true)) {
            $documentType = 'overview';
        }

        $actor = PlatformAuthority::resolveCurrentActor();
        $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentType, $actor);

        $documentLabels = [];
        foreach ($allowedTypes as $type) {
            $documentLabels[$type] = EngineeringWorkspaceContentContract::documentLabel($type) ?? ucfirst($type);
        }

        $safeContent = MarkdownRenderer::render(EngineeringWorkspaceContentContract::markdownForDisplay($documentType, $content));
        $engineeringWorkspaceView = [
            'authorized' => !empty($resolved['authorized']),
            'workspace_key' => $workspaceKey,
            'document_type' => $documentType,
            'document_label' => $documentLabels[$documentType] ?? 'Document',
            'document_tabs' => $documentLabels,
            'expected_sections' => EngineeringWorkspaceContentContract::requiredHeadings($documentType),
            'exists' => !empty($resolved['exists']),
            'rendered_content' => $safeContent,
            'raw_content' => (string)($resolved['content'] ?? ''),
            'draft_content' => $content,
            'fingerprint' => $fingerprint !== '' ? $fingerprint : (string)($resolved['fingerprint'] ?? ''),
            'error' => (string)($resolved['error'] ?? ''),
            'source_path' => (string)($resolved['path'] ?? ''),
            'mode' => 'edit',
            'preview_active' => true,
            'csrf' => \App\Core\Auth::csrfToken(),
            'save_ok' => false,
            'save_error' => '',
        ];

        if (empty($resolved['authorized'])) {
            http_response_code(403);
            self::renderErrorView($view, 'Forbidden', 'You do not have permission.');
            return;
        }

        self::renderEngineeringWorkspaceView($view, $engineeringWorkspaceView);
    }

    /**
     * Toggle a work.md task-list checkbox.
     *
     * POST /apps/studio/engineering-workspaces/toggle-work-item
     */
    public static function engineeringWorkspaceToggleWorkItem(View $view): void
    {
        self::guardPlatformAdmin('/apps/studio/engineering-workspaces/toggle-work-item');
        \App\Core\Auth::requireCsrf(
            (string)($_POST['csrf'] ?? ''),
            '/apps/studio/engineering-workspaces'
        );

        $workspaceKey = trim((string)($_POST['workspace_key'] ?? ''));
        $documentType = trim((string)($_POST['document'] ?? ''));
        $taskOrdinal = (int)($_POST['task_ordinal'] ?? -1);
        $targetState = (int)($_POST['target_state'] ?? -1);
        $submittedFingerprint = trim((string)($_POST['fingerprint'] ?? ''));
        $submittedLineHash = trim((string)($_POST['task_line_hash'] ?? ''));

        // Only work.md supports checkbox toggles
        if ($documentType !== 'work') {
            $_SESSION['ew_toggle_error'] = 'Checkbox toggle is only supported for work.md';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=' . rawurlencode($documentType)
            );
            return;
        }

        if ($taskOrdinal < 0) {
            $_SESSION['ew_toggle_error'] = 'Invalid task ordinal';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        if ($targetState !== 0 && $targetState !== 1) {
            $_SESSION['ew_toggle_error'] = 'Invalid target state';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        if ($submittedFingerprint === '') {
            $_SESSION['ew_toggle_error'] = 'Missing fingerprint';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        $actor = PlatformAuthority::resolveCurrentActor();
        $resolved = EngineeringWorkspaceResolver::resolve($workspaceKey, $documentType, $actor);

        if (empty($resolved['authorized'])) {
            http_response_code(403);
            self::renderErrorView($view, 'Forbidden', 'You do not have permission.');
            return;
        }

        if (empty($resolved['exists']) || $resolved['content'] === null) {
            $_SESSION['ew_toggle_error'] = 'Document does not exist';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        $currentContent = (string)$resolved['content'];
        $currentFingerprint = EngineeringWorkspaceResolver::fingerprint($currentContent);

        // Validate fingerprint matches current content
        if (!hash_equals($currentFingerprint, $submittedFingerprint)) {
            $_SESSION['ew_toggle_error'] = 'This document changed after you opened it. Reload the current document before toggling.';
            $_SESSION['ew_toggle_stale'] = true;
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        // Parse tasks and validate the requested ordinal and line hash
        $tasks = WorkTaskParser::parseSupportedTaskLines($currentContent);
        $task = WorkTaskParser::findTaskByOrdinal($tasks, $taskOrdinal);
        if ($task === null) {
            $_SESSION['ew_toggle_error'] = 'Task ordinal not found in current document';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        if ($submittedLineHash === '' || !hash_equals(hash('sha256', $task['original_line']), $submittedLineHash)) {
            $_SESSION['ew_toggle_error'] = 'Task line content has changed. Reload the document and try again.';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        // Toggle the task line
        $toggleResult = WorkTaskParser::toggleTaskLine($currentContent, $taskOrdinal, $targetState === 1);
        if (empty($toggleResult['ok'])) {
            $_SESSION['ew_toggle_error'] = $toggleResult['error'] ?? 'Toggle failed';
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        $newContent = (string)$toggleResult['content'];

        // Use the existing atomic writer
        $writeResult = EngineeringWorkspaceResolver::writeWithFingerprint(
            $workspaceKey,
            $documentType,
            $newContent,
            $currentFingerprint,
            $actor
        );

        if (empty($writeResult['ok'])) {
            $isStale = !empty($writeResult['stale']);
            $_SESSION['ew_toggle_error'] = $isStale
                ? 'This document changed after you opened it. Reload the current document before toggling.'
                : ($writeResult['error'] ?? 'Toggle write failed');
            $_SESSION['ew_toggle_stale'] = $isStale;
            self::redirect(
                '/apps/studio/engineering-workspaces?workspace_key='
                . rawurlencode($workspaceKey) . '&document=work'
            );
            return;
        }

        $_SESSION['ew_toggle_success'] = true;
        self::redirect(
            '/apps/studio/engineering-workspaces?workspace_key='
            . rawurlencode($workspaceKey) . '&document=work'
        );
    }

    private static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
