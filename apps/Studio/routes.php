<?php
declare(strict_types=1);

use Apps\Studio\Controllers\StudioController;
use Apps\Studio\Services\StudioGovernedToolRegistryService;
use Apps\Studio\Services\StudioToolInstancePolicyService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Controllers/StudioController.php';
require_once __DIR__ . '/Routes/gui_studio_routes.php';
require_once __DIR__ . '/Services/StudioGovernedToolRegistryService.php';
require_once __DIR__ . '/Services/StudioToolInstancePolicyService.php';

$studioRedirect = static function (string $target, int $status = 302): void {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $target .= (str_contains($target, '?') ? '&' : '?') . $query;
    }
    header('Location: ' . $target, true, $status);
};

$router->get('/apps/studio', function () use ($view, $router) {
    StudioController::index($view, $router);
    return null;
});

$router->get('/apps/studio/legacy', function () use ($view, $router) {
    StudioController::legacy($view, $router);
    return null;
});

$router->get('/apps/studio/all-in-one', function () use ($view, $router) {
    StudioController::legacy($view, $router);
    return null;
});

$router->get('/apps/studio/tools/view-editor', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('view_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::toolPage($view, '/apps/studio/tools/view-editor', APP_ROOT . '/apps/Studio/Tools/ViewEditor/Views/index.php');
    return null;
});

$router->get('/apps/studio/tools/menu-editor', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('menu_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::toolPage($view, '/apps/studio/tools/menu-editor', APP_ROOT . '/apps/Studio/Tools/MenuEditor/Views/index.php');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/diagnose/theme-doctor', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('theme_tool')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::themeToolPreview($view);
    return null;
});

$router->get('/apps/studio/tools/theme-tool', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/theme-doctor');
    return null;
});

$router->get('/apps/studio/tools/customization-studio', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::customizationStudioLanding($view);
    return null;
});

$router->get('/apps/studio/tools/customization-studio/visual-customizer', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerPreview($view);
    return null;
});

$router->get('/apps/studio/tools/customization-studio/effects', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::customizationStudioSpecialEffects($view);
    return null;
});

$router->get('/apps/studio/tools/customization-studio/effects/preview', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::customizationStudioSpecialEffectsPreview();
    return null;
});

$router->get('/apps/studio/tools/customization-studio/special-effects', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/effects');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/special-effects/preview', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/effects/preview');
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/draft/update', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerDraftUpdate();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/draft/recheck-readiness', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerRecheckReadiness();
    return null;
});

$router->get('/apps/studio/tools/customization-studio/visual-customizer/request', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerViewRequest($view);
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/draft/create-approval-request', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerCreateApprovalRequest();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/request/approve', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerApproveRequest();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/request/reject', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerRejectRequest();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/request/cancel', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerCancelRequest();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/request/take-snapshot', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerTakeSnapshot();
    return null;
});

$router->post('/apps/studio/tools/customization-studio/visual-customizer/request/apply', function () {
    if (!StudioToolInstancePolicyService::isEnabled('customization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::visualCustomizerApplyRequest();
    return null;
});

$router->get('/apps/studio/tools/engineering-workspaces', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('engineering_workspaces')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::engineeringWorkspacesHub($view);
    return null;
});

$router->get('/apps/studio/engineering-workspaces', function () use ($view) {
    if (trim((string)($_GET['workspace_key'] ?? '')) === '') {
        header('Location: /apps/studio/tools/engineering-workspaces', true, 302);
        return null;
    }
    StudioController::engineeringWorkspaceViewer($view);
    return null;
});

$router->post('/apps/studio/engineering-workspaces/save', function () use ($view) {
    StudioController::engineeringWorkspaceSave($view);
    return null;
});

$router->post('/apps/studio/engineering-workspaces/preview', function () use ($view) {
    StudioController::engineeringWorkspacePreview($view);
    return null;
});

$router->post('/apps/studio/engineering-workspaces/toggle-work-item', function () use ($view) {
    StudioController::engineeringWorkspaceToggleWorkItem($view);
    return null;
});

// ── V1.3 Guarded provisioning endpoints ──
$router->post('/apps/studio/tools/engineering-workspaces/provision/preview', function () use ($view) {
    StudioController::engineeringWorkspacesProvisionPreview($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/provision/apply', function () use ($view) {
    StudioController::engineeringWorkspacesProvisionApply($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/provision/rollback', function () use ($view) {
    StudioController::engineeringWorkspacesProvisionRollback($view);
    return null;
});

// ── Simplified Deploy routes ──
$router->post('/apps/studio/tools/engineering-workspaces/deploy/create', function () use ($view) {
    StudioController::engineeringWorkspacesDeployCreate($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/deploy/replace', function () use ($view) {
    StudioController::engineeringWorkspacesDeployReplace($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/deploy/restore', function () use ($view) {
    StudioController::engineeringWorkspacesDeployRestore($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/deploy/create-missing-all', function () use ($view) {
    StudioController::engineeringWorkspacesDeployCreateMissingAll($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/new-workspace', function () use ($view) {
    StudioController::engineeringWorkspacesNewWorkspace($view);
    return null;
});

$router->post('/apps/studio/tools/engineering-workspaces/coverage-assign', function () use ($view) {
    StudioController::engineeringWorkspacesCoverageAssign($view);
    return null;
});

$router->get('/apps/studio/tools/helper-tool', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('helper_tool')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::helperToolPreview($view);
    return null;
});

$router->get('/apps/studio/tools/helper-tool/inspect-file', function () {
    if (!StudioToolInstancePolicyService::isEnabled('helper_tool')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::helperToolInspectFile();
    return null;
});

$router->get('/apps/studio/tools/owner-structure-scan', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('owner_structure_scan')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::ownerStructureScanPreview($view);
    return null;
});

$router->post('/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts', function () {
    if (!StudioToolInstancePolicyService::isEnabled('owner_structure_scan')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::ownerStructureScanInitializeWorkspaceArtifacts();
    return null;
});

$router->get('/apps/studio/tools/app-builder', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('app_builder')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::toolPage($view, '/apps/studio/tools/app-builder', APP_ROOT . '/apps/Studio/Tools/AppBuilder/Views/index.php');
    return null;
});

$router->get('/apps/studio/tools/module-builder', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('module_builder')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::toolPage($view, '/apps/studio/tools/module-builder', APP_ROOT . '/apps/Studio/Tools/ModuleBuilder/Views/index.php');
    return null;
});

$router->get('/apps/studio/tools/report-designer', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('report_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::reportDesignerPreview($view);
    return null;
});

$router->post('/apps/studio/tools/report-designer/save', function () {
    if (!StudioToolInstancePolicyService::isEnabled('report_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::reportDesignerSaveDefinition();
    return null;
});

$router->get('/apps/studio/tools/label-designer', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerPreview($view);
    return null;
});

$router->post('/apps/studio/tools/label-designer/context/create', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerCreateContext();
    return null;
});

$router->post('/apps/studio/tools/label-designer/create-folders', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerCreateResourceFolders();
    return null;
});

$router->post('/apps/studio/tools/label-designer/template/create', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerCreateTemplate();
    return null;
});

$router->post('/apps/studio/tools/label-designer/preview/render', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerRenderPreview();
    return null;
});

$router->post('/apps/studio/tools/label-designer/preview/rule-sandbox', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerRuleSandbox();
    return null;
});

$router->post('/apps/studio/tools/label-designer/rule/create', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerCreateRule();
    return null;
});

$router->post('/apps/studio/tools/label-designer/migration-preview', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerMigrationPreview();
    return null;
});

$router->post('/apps/studio/tools/label-designer/migration-apply', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerApplyMetadataMigration();
    return null;
});

$router->post('/apps/studio/tools/label-designer/duplicate-resource', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerDuplicateResource();
    return null;
});

$router->post('/apps/studio/tools/label-designer/context/edit', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerEditContext();
    return null;
});

$router->post('/apps/studio/tools/label-designer/template/edit', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerEditTemplate();
    return null;
});

$router->post('/apps/studio/tools/label-designer/rule/edit', function () {
    if (!StudioToolInstancePolicyService::isEnabled('label_designer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::labelDesignerEditRule();
    return null;
});

$router->get('/apps/studio/tools/localization-studio', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('localization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationStudioPreview($view);
    return null;
});

$router->get('/apps/studio/tools/localization-studio/edit', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('localization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationStudioEdit($view);
    return null;
});

$router->post('/apps/studio/tools/localization-studio/edit/save', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationStudioSave();
    return null;
});

$router->get('/apps/studio/tools/localization-studio/create-file', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('localization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationStudioCreateFile($view);
    return null;
});

$router->post('/apps/studio/tools/localization-studio/create-file/do', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_studio')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationStudioDoCreateFile();
    return null;
});

$router->get('/apps/studio/tools/localization-scan-extraction', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionPreview($view);
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/scan', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionScanAsync();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/apply-safe-corrections', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionApplySafeCorrectionsAsync();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/add-missing-keys', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionAddMissingKeys();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/add-selected-keys', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionAddSelectedKeys();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/extract-inline-text', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionExtractInlineText();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/rollback', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionRollback();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/full-scan', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionFullScan();
    return null;
});

$router->post('/apps/studio/tools/localization-scan-extraction/apply-inline-migration', function () {
    if (!StudioToolInstancePolicyService::isEnabled('localization_scan_extraction')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::localizationScanExtractionApplyInlineMigration();
    return null;
});

$router->get('/apps/studio/tools/customization-studio/design-system/tokens', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('css_token_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssTokenEditorPreview($view);
    return null;
});

$router->get('/apps/studio/tools/css-token-editor', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/design-system/tokens');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/design-system/tokens/preview', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('css_token_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssTokenEditorLivePreview($view);
    return null;
});

$router->get('/apps/studio/tools/css-token-editor/preview', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/design-system/tokens/preview');
    return null;
});

$router->post('/apps/studio/tools/customization-studio/design-system/tokens/verify', function () {
    if (!StudioToolInstancePolicyService::isEnabled('css_token_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssTokenEditorVerify();
    return null;
});

$router->post('/apps/studio/tools/css-token-editor/verify', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/design-system/tokens/verify', 307);
    return null;
});

$router->post('/apps/studio/tools/customization-studio/design-system/tokens/save', function () {
    if (!StudioToolInstancePolicyService::isEnabled('css_token_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssTokenEditorSave();
    return null;
});

$router->post('/apps/studio/tools/css-token-editor/save', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/design-system/tokens/save', 307);
    return null;
});

$router->get('/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot', function () {
    if (!StudioToolInstancePolicyService::isEnabled('css_token_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssTokenEditorSourceSnapshot();
    return null;
});

$router->get('/apps/studio/tools/css-token-editor/source-snapshot', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/diagnose/token-impact-explorer', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('token_impact_explorer')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::tokenImpactExplorerPreview($view);
    return null;
});

$router->get('/apps/studio/tools/token-impact-explorer', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/token-impact-explorer');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/diagnose/style-compliance', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('style_compliance')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::styleCompliancePreview($view);
    return null;
});

$router->get('/apps/studio/tools/style-compliance', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/style-compliance');
    return null;
});

$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/scan', function () {
    if (!StudioToolInstancePolicyService::isEnabled('style_compliance')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::styleComplianceScanAsync();
    return null;
});

$router->post('/apps/studio/tools/style-compliance/scan', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/style-compliance/scan', 307);
    return null;
});

$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness', function () {
    if (!StudioToolInstancePolicyService::isEnabled('style_compliance')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::styleComplianceRepairReadinessAsync();
    return null;
});

$router->post('/apps/studio/tools/style-compliance/repair-readiness', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-readiness', 307);
    return null;
});

$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one', function () {
    if (!StudioToolInstancePolicyService::isEnabled('style_compliance')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::styleComplianceFixOneAsync();
    return null;
});

$router->post('/apps/studio/tools/style-compliance/fix-one', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/style-compliance/fix-one', 307);
    return null;
});

$router->post('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute', function () {
    if (!StudioToolInstancePolicyService::isEnabled('style_compliance')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::styleComplianceRepairExecute();
    return null;
});

$router->post('/apps/studio/tools/style-compliance/repair-execute', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute', 307);
    return null;
});

$router->get('/apps/studio/tools/customization-studio/advanced/css-live-editor', function () use ($view) {
    if (!StudioToolInstancePolicyService::isEnabled('css_live_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssLiveEditorPlaceholder($view);
    return null;
});

$router->get('/apps/studio/tools/css-live-editor', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/advanced/css-live-editor');
    return null;
});

$router->get('/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame', function () {
    if (!StudioToolInstancePolicyService::isEnabled('css_live_editor')) {
        http_response_code(404);
        echo 'Not Found';
        return null;
    }
    StudioController::cssLiveEditorPreviewFrame();
    return null;
});

$router->get('/apps/studio/tools/css-live-editor/preview-frame', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame');
    return null;
});

$router->get('/apps/studio/tools/css-selector-tool', function () use ($studioRedirect) {
    $studioRedirect('/apps/studio/tools/customization-studio/diagnose/css-selector-inspector');
    return null;
});

foreach (StudioGovernedToolRegistryService::listTools() as $studioTool) {
    if (empty($studioTool['placeholder'])) {
        continue;
    }

    $toolKey = trim((string)($studioTool['key'] ?? ''));
    $toolRoute = trim((string)($studioTool['canonical_route'] ?? ''));
    if ($toolKey === '' || $toolRoute === '') {
        continue;
    }

    $router->get($toolRoute, function () use ($view, $toolKey, $toolRoute, $studioTool) {
        if (!StudioToolInstancePolicyService::isEnabled($toolKey, $studioTool)) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        StudioController::governedToolPlaceholder($view, $toolKey, $toolRoute);
        return null;
    });
}

$router->get('/apps/studio/workflow/analyze', function () use ($view) {
    StudioController::workflowPage($view, '/apps/studio/workflow/analyze', 'analyze');
    return null;
});

$router->get('/apps/studio/workflow/changes', function () use ($view) {
    StudioController::workflowPage($view, '/apps/studio/workflow/changes', 'changes');
    return null;
});

$router->get('/apps/studio/workflow/apply', function () use ($view) {
    StudioController::workflowPage($view, '/apps/studio/workflow/apply', 'apply');
    return null;
});

studio_register_gui_studio_routes($router, $view, '/apps/studio');
