<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/app/Services/PlatformModeService.php';
require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspacePageContextResolver.php';

use App\Services\PlatformModeService;
use Platform\Security\EngineeringWorkspacePageContextResolver;

$passed = 0;
$failed = 0;

function ds_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ds_assert_null(mixed $actual, string $label): void
{
    ds_assert_eq(null, $actual, $label);
}

function ds_assert_true(bool $actual, string $label): void
{
    ds_assert_eq(true, $actual, $label);
}

function ds_context(string $uri, array $query = [], ?array $actor = ['authority_role' => 'platform_admin']): ?array
{
    return EngineeringWorkspacePageContextResolver::resolveForRequest($uri, $actor, $query);
}

function ds_surface(string $uri, array $query = []): array
{
    return EngineeringWorkspacePageContextResolver::resolveSurfaceForRequest($uri, $query);
}

function ds_set_platform_mode(string $mode): void
{
    $ref = new ReflectionClass(PlatformModeService::class);
    $cacheLoaded = $ref->getProperty('cacheLoaded');
    $cacheLoaded->setValue(null, true);

    $requestCache = $ref->getProperty('requestCache');
    $requestCache->setValue(null, $mode);
}

putenv('ERP_ENABLE_DEV_TOOLS');
ds_set_platform_mode(PlatformModeService::MODE_DEVELOPMENT);

ds_assert_eq(true, app_dev_tools_enabled(), 'Development mode enables Developer Strip eligibility');

ds_assert_eq('Studio', ds_context('/apps/studio')['workspace_key'] ?? null, 'Studio home exact mapping');
ds_assert_eq('workspace_root', ds_surface('/apps/studio')['surface_classification'] ?? null, 'Studio home is classified as a Workspace Root');
ds_assert_eq('linked_valid', ds_surface('/apps/studio')['resolution_state'] ?? null, 'Studio home has linked valid workspace state');
ds_assert_null(ds_context('/apps/studio/legacy'), 'Studio root is not a catch-all prefix');
ds_assert_eq('Studio/tools/CustomizationStudio', ds_context('/apps/studio/tools/customization-studio/diagnose/style-compliance')['workspace_key'] ?? null, 'Customization Studio child mapping');
ds_assert_eq('workspace_child', ds_surface('/apps/studio/tools/customization-studio/diagnose/style-compliance')['surface_classification'] ?? null, 'Customization Studio diagnose page is classified as Workspace Child');
$customizationContext = ds_context('/apps/studio/tools/customization-studio/diagnose/style-compliance');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Customization Studio', 'url' => '/apps/studio/tools/customization-studio'],
], $customizationContext['breadcrumbs'] ?? null, 'Customization Studio breadcrumb maps only real pages');
ds_assert_eq('Studio/tools/LocalizationStudio', ds_context('/apps/studio/tools/localization-studio/editor')['workspace_key'] ?? null, 'Localization Studio child mapping');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Localization Studio', 'url' => '/apps/studio/tools/localization-studio'],
], ds_context('/apps/studio/tools/localization-studio/editor')['breadcrumbs'] ?? null, 'Localization Studio breadcrumb maps registered route');
ds_assert_eq('Studio/tools/LabelDesigner', ds_context('/apps/studio/tools/label-designer/preview/render')['workspace_key'] ?? null, 'Label Designer child mapping');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Label Designer', 'url' => '/apps/studio/tools/label-designer'],
], ds_context('/apps/studio/tools/label-designer/preview/render')['breadcrumbs'] ?? null, 'Label Designer breadcrumb maps registered route');
ds_assert_eq('Studio/tools/ReportDesigner', ds_context('/apps/studio/tools/report-designer/reports/new')['workspace_key'] ?? null, 'Report Designer child mapping');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Report Designer', 'url' => '/apps/studio/tools/report-designer'],
], ds_context('/apps/studio/tools/report-designer/reports/new')['breadcrumbs'] ?? null, 'Report Designer breadcrumb maps registered route');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Owner Structure Scan', 'url' => '/apps/studio/tools/owner-structure-scan'],
], EngineeringWorkspacePageContextResolver::workspaceBreadcrumbs('Studio/tools/OwnerStructureScan'), 'Owner Structure Scan breadcrumb maps registered route');
ds_assert_eq([
    ['label' => 'Studio', 'url' => '/apps/studio'],
    ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
    ['label' => 'Customization Studio', 'url' => '/apps/studio/tools/customization-studio'],
], EngineeringWorkspacePageContextResolver::workspaceBreadcrumbs('Studio/tools/CustomizationStudio/diagnose/StyleCompliance'), 'Namespace-only diagnose segment is not linked');
ds_assert_eq('Manufacturing/Products', ds_context('/products')['workspace_key'] ?? null, 'Manufacturing Products canonical route mapping');
ds_assert_eq('Manufacturing/Products', ds_context('/products/anything')['workspace_key'] ?? null, 'Manufacturing Products canonical child route mapping');
ds_assert_eq('workspace_child', ds_surface('/products/anything')['surface_classification'] ?? null, 'Products child route inherits Products workspace');
ds_assert_eq('Manufacturing/Products', ds_context('/apps/manufacturing/products/360?id=12')['workspace_key'] ?? null, 'Manufacturing Products compatibility owner mapping');
$productsContext = ds_context('/apps/manufacturing/products');
ds_assert_eq([
    ['label' => 'Apps', 'url' => ''],
    ['label' => 'Manufacturing', 'url' => '/apps/manufacturing'],
    ['label' => 'Products', 'url' => '/apps/manufacturing/products'],
], $productsContext['breadcrumbs'] ?? null, 'Manufacturing Products breadcrumb maps app and module routes');
ds_assert_eq([
    ['label' => 'Apps', 'url' => ''],
    ['label' => 'Manufacturing', 'url' => '/apps/manufacturing'],
    ['label' => 'Production Plans', 'url' => '/apps/manufacturing/production-plans'],
], EngineeringWorkspacePageContextResolver::workspaceBreadcrumbs('Manufacturing/ProductionPlans'), 'Manufacturing ProductionPlans breadcrumb maps registered route');
ds_assert_eq([
    ['label' => 'Apps', 'url' => ''],
    ['label' => 'SBAIO', 'url' => '/apps/sbaio'],
    ['label' => 'Staff', 'url' => '/apps/sbaio/staff'],
], EngineeringWorkspacePageContextResolver::workspaceBreadcrumbs('SBAIO/Staff'), 'SBAIO Staff breadcrumb maps registered route');
ds_assert_eq([], EngineeringWorkspacePageContextResolver::workspaceBreadcrumbs('Unknown/Workspace'), 'Unknown workspace falls back to plain identity');
ds_assert_null(ds_context('/products-legacy'), 'Products legacy-like route does not match canonical Products');
ds_assert_null(ds_context('/product'), 'Singular product route does not match canonical Products');
ds_assert_null(ds_context('/apps/manufacturing/product'), 'Singular Manufacturing product route does not match compatibility Products');
ds_assert_eq('Platform/Organization', ds_context('/ops/organization/company')['workspace_key'] ?? null, 'Platform Organization owner mapping');
ds_assert_eq('Plugin/Base', ds_context('/admin/base?module=products')['workspace_key'] ?? null, 'Plugin Base owner mapping');
ds_assert_eq('Manufacturing/Products', ds_context('/apps/studio/tools/owner-structure-scan?owner=Manufacturing%2FProducts', ['owner' => 'Manufacturing/Products'])['workspace_key'] ?? null, 'Owner Structure Scan selected owner mapping');
ds_assert_eq('Manufacturing/MaterialManagement', ds_context('/apps/studio/tools/owner-structure-scan?owner=Manufacturing%2FMaterialManagement', ['owner' => 'Manufacturing/MaterialManagement'])['workspace_key'] ?? null, 'Owner Structure Scan selected initialized owner mapping');
ds_assert_null(ds_context('/apps/studio/engineering-workspaces?workspace_key=Studio&document=overview'), 'Engineering Workspace viewer route is excluded');
ds_assert_null(ds_context('/apps/studio/tools/engineering-workspaces'), 'Engineering Workspace Hub route has no Developer Strip context');
ds_assert_eq('non_workspace', ds_surface('/apps/studio/tools/engineering-workspaces')['surface_classification'] ?? null, 'Engineering Workspace Hub is classified as Non-Workspace Surface');
ds_assert_eq('excluded', ds_surface('/apps/studio/tools/engineering-workspaces')['resolution_state'] ?? null, 'Engineering Workspace Hub resolves to excluded state');
ds_assert_null(ds_context('/apps/studio/tools/theme-tool'), 'Unmapped Studio tool has no strip context');
ds_assert_eq('unresolved', ds_surface('/apps/studio/tools/theme-tool')['resolution_state'] ?? null, 'Unknown Studio tool remains unresolved');
ds_assert_eq('Studio/tools/AppBuilder', ds_surface('/apps/studio/tools/app-builder')['workspace_key'] ?? null, 'Registered Studio tool root derives workspace key automatically');
ds_assert_eq('initialization_required', ds_surface('/apps/studio/tools/app-builder')['resolution_state'] ?? null, 'Registered Studio tool without workspace requires initialization');
ds_assert_null(ds_context('/apps/studio/tools/app-builder'), 'Registered Studio tool without workspace does not render strip');
ds_assert_null(ds_context('/u/lazydeepak/dashboard'), 'Operator route has no strip context');
ds_assert_null(ds_context('/apps/studio', [], ['authority_role' => 'app_admin']), 'Non-platform-admin has no strip context');

$helperWorkspaceDir = APP_ROOT . '/engineering/Studio/tools/HelperTool';
$helperPreexisting = is_dir($helperWorkspaceDir);
$helperCreatedFiles = [];
if (!$helperPreexisting) {
    mkdir($helperWorkspaceDir, 0755, true);
    foreach (\Platform\Security\EngineeringWorkspaceContentContract::allowedDocumentKeys() as $docKey) {
        $filename = \Platform\Security\EngineeringWorkspaceContentContract::canonicalFilename($docKey);
        if ($filename === null) {
            continue;
        }
        $path = $helperWorkspaceDir . '/' . $filename;
        file_put_contents($path, \Platform\Security\EngineeringWorkspaceContentContract::initialContent('Studio/tools/HelperTool', $docKey));
        $helperCreatedFiles[] = $path;
    }
}
ds_assert_eq('Studio/tools/HelperTool', ds_context('/apps/studio/tools/helper-tool')['workspace_key'] ?? null, 'Helper Tool resolves as Workspace Root once workspace folder exists');
ds_assert_eq('linked_valid', ds_surface('/apps/studio/tools/helper-tool')['resolution_state'] ?? null, 'Helper Tool temporary workspace is linked valid');
if (!$helperPreexisting) {
    foreach (array_reverse($helperCreatedFiles) as $path) {
        @unlink($path);
    }
    @rmdir($helperWorkspaceDir);
}

ds_set_platform_mode(PlatformModeService::MODE_PRODUCTION);
putenv('ERP_ENABLE_DEV_TOOLS=1');
ds_assert_eq(false, app_dev_tools_enabled(), 'Production mode keeps Developer Strip eligibility false even with env flag');
ds_assert_null(ds_context('/apps/studio'), 'Production mode has no strip context');

ds_set_platform_mode(PlatformModeService::MODE_DEMO);
ds_assert_eq(false, app_dev_tools_enabled(), 'Demo mode keeps Developer Strip eligibility false');
ds_assert_null(ds_context('/apps/studio'), 'Demo mode has no strip context');

ds_set_platform_mode(PlatformModeService::MODE_DEVELOPMENT);
putenv('ERP_ENABLE_DEV_TOOLS=0');
ds_assert_eq(false, app_dev_tools_enabled(), 'Deployment kill switch disables Developer Strip eligibility in Development mode');
ds_assert_null(ds_context('/apps/studio'), 'Developer tools kill switch has no strip context');

putenv('ERP_ENABLE_DEV_TOOLS');
ds_set_platform_mode(PlatformModeService::MODE_DEVELOPMENT);
ds_assert_null(ds_context('/apps/studio', [], ['authority_role' => 'app_admin']), 'Non-platform-admin remains hidden in Development mode');

$sample = ds_context('/apps/studio');
ds_assert_eq('/apps/studio/engineering-workspaces?workspace_key=Studio&document=overview', $sample['overview_url'] ?? null, 'Overview URL is resolver-generated');
ds_assert_eq(false, isset($sample['path']), 'No filesystem path is exposed in context');

$renderContext = $customizationContext;
ob_start();
$developerStripContext = $renderContext;
require APP_ROOT . '/apps/Shell/Views/partials/developer_strip.php';
$stripHtml = (string)ob_get_clean();
ds_assert_eq(true, str_contains($stripHtml, 'href="/apps/studio">Studio</a>'), 'Rendered strip links Studio breadcrumb');
ds_assert_eq(true, str_contains($stripHtml, 'href="/apps/studio/tools/registry">Tools</a>'), 'Rendered strip links Tools breadcrumb');
ds_assert_eq(true, str_contains($stripHtml, 'href="/apps/studio/tools/customization-studio">Customization Studio</a>'), 'Rendered strip links Customization Studio breadcrumb');
ds_assert_eq(true, str_contains($stripHtml, 'href="/apps/studio/engineering-workspaces?workspace_key=Studio%2Ftools%2FCustomizationStudio&amp;document=overview">Overview</a>'), 'Rendered strip preserves Overview document tab');
ds_assert_eq(false, str_contains($stripHtml, '/apps/studio/tools/customization-studio/diagnose'), 'Rendered strip does not expose namespace-only diagnose URL');

$renderContext = $productsContext;
ob_start();
$developerStripContext = $renderContext;
require APP_ROOT . '/apps/Shell/Views/partials/developer_strip.php';
$productsStripHtml = (string)ob_get_clean();
ds_assert_eq(true, str_contains($productsStripHtml, '<span>Apps</span>'), 'Rendered Products strip shows unlinked Apps root');
ds_assert_eq(true, str_contains($productsStripHtml, 'href="/apps/manufacturing">Manufacturing</a>'), 'Rendered Products strip links Manufacturing breadcrumb');
ds_assert_eq(true, str_contains($productsStripHtml, 'href="/apps/manufacturing/products">Products</a>'), 'Rendered Products strip links Products breadcrumb');
ds_assert_eq(true, str_contains($productsStripHtml, 'href="/apps/studio/engineering-workspaces?workspace_key=Manufacturing%2FProducts&amp;document=overview">Overview</a>'), 'Rendered Products strip preserves Overview document tab');

$fallbackContext = [
    'workspace_display_name' => 'Unknown / Workspace',
    'breadcrumbs' => [],
    'overview_url' => '/example/overview',
];
ob_start();
$developerStripContext = $fallbackContext;
require APP_ROOT . '/apps/Shell/Views/partials/developer_strip.php';
$fallbackStripHtml = (string)ob_get_clean();
ds_assert_eq(true, str_contains($fallbackStripHtml, 'Unknown / Workspace'), 'Rendered fallback strip keeps plain identity');

echo "DeveloperStripContext: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);
