<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/AdminDashboardLauncherComposer.php';
require_once APP_ROOT . '/apps/Platform/Services/AdminUpgradeWorkbenchService.php';

use Apps\Shell\Services\AdminDashboardLauncherComposer;
use Apps\Platform\Services\AdminUpgradeWorkbenchService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$groups = AdminDashboardLauncherComposer::compose([
    'sections' => [[
        'groups' => [
            ['label' => 'Governance', 'items' => [
                ['key' => 'access', 'label' => 'Access Control', 'url' => '/ops/access-control'],
                ['key' => 'duplicate', 'label' => 'Duplicate', 'url' => '/ops/access-control'],
            ]],
            ['label' => 'Developer', 'items' => [
                ['key' => 'health', 'label' => 'Architecture Health', 'url' => '/admin/architecture-health'],
                ['key' => 'auto.admin_architecture_health_detail', 'label' => 'Detail', 'url' => '/admin/architecture-health/detail'],
            ]],
            ['label' => 'Apps & Config', 'items' => [
                ['key' => 'apps', 'label' => 'Apps', 'url' => '/admin/apps'],
            ]],
            ['label' => 'Organization', 'items' => [
                ['key' => 'organization', 'label' => 'Organization', 'url' => '/ops/organization'],
            ]],
            ['label' => 'Operations', 'items' => [
                ['key' => 'ignored', 'label' => 'Ignored', 'url' => '/ops/ignored'],
            ]],
        ],
    ]],
]);

$assert(count($groups) === 3, 'only unified admin groups are projected');
$assert(($groups[0]['key'] ?? '') === 'governance', 'governance order is deterministic');
$assert(count((array)($groups[0]['items'] ?? [])) === 1, 'duplicate canonical paths are removed');
$assert(($groups[1]['key'] ?? '') === 'developer', 'developer navigation is projected');
$assert(($groups[1]['items'][0]['url'] ?? '') === '/admin/architecture-health', 'canonical developer link is preserved');
$assert(count((array)($groups[1]['items'] ?? [])) === 1, 'auto-discovered child routes are excluded');
$assert(($groups[2]['key'] ?? '') === 'apps_config', 'apps and organization share one dashboard group');
$assert(count((array)($groups[2]['items'] ?? [])) === 2, 'organization configuration is merged with app configuration');
$modeRestrictedGroups = AdminDashboardLauncherComposer::compose([
    'sections' => [[
        'groups' => [
            ['label' => 'Governance', 'items' => [['key' => 'access', 'label' => 'Access', 'url' => '/ops/access-control']]],
            ['label' => 'Developer', 'items' => [['key' => 'routes', 'label' => 'Routes', 'url' => '/admin/routes']]],
            ['label' => 'System Tools', 'items' => [['key' => 'mode', 'label' => 'Platform Mode', 'url' => '/admin/system-tools/platform-mode']]],
        ],
    ]],
], false);
$modeRestrictedKeys = array_column($modeRestrictedGroups, 'key');
$assert(!in_array('developer', $modeRestrictedKeys, true), 'production and demo modes hide the Developer launcher group');
$assert(in_array('governance', $modeRestrictedKeys, true), 'mode filtering preserves Governance');
$assert(in_array('system_tools', $modeRestrictedKeys, true), 'mode filtering preserves the Platform Mode recovery path');

$platformMenuContext = AdminDashboardLauncherComposer::menuContext(
    ['authority_role' => 'platform_admin'],
    ['authority_role' => 'platform_admin'],
    true,
    true,
    '/admin/platform.owner'
);
$appAdminMenuContext = AdminDashboardLauncherComposer::menuContext(
    ['authority_role' => 'app_admin'],
    ['authority_role' => 'app_admin'],
    false,
    true,
    '/admin/app.owner'
);
$assert(($platformMenuContext['isAdmin'] ?? false) === true, 'platform admin receives full menu authority context');
$assert(($appAdminMenuContext['isAdmin'] ?? true) === false, 'app admin is not promoted to platform administrator');
$assert(($appAdminMenuContext['devToolsEnabled'] ?? false) === true, 'environment flag remains separate from authority');
$assert(($appAdminMenuContext['canAccessBase'] ?? true) === false, 'app admin base access remains permission-derived');

$platformNavigation = require APP_ROOT . '/apps/Platform/navigation.php';
$platformAdminDashboardEntries = array_values(array_filter((array)($platformNavigation['items'] ?? []), static fn($item): bool => is_array($item) && ($item['key'] ?? '') === 'platform_admin_dashboard'));
$assert($platformAdminDashboardEntries === [], 'deleted platform dashboard has no navigation entry');

$platformWorkbench = AdminUpgradeWorkbenchService::build(['authority_role' => 'platform_admin', 'developer_tools_visible' => true]);
$productionPlatformStatus = AdminUpgradeWorkbenchService::build(['authority_role' => 'platform_admin', 'developer_tools_visible' => false]);
$appAdminWorkbench = AdminUpgradeWorkbenchService::build(['authority_role' => 'app_admin', 'active_assigned_apps' => ['manufacturing', 'sbaio']]);
$assert($platformWorkbench !== [], 'platform admin receives the status component');
$assert(($platformWorkbench['title_key'] ?? '') === 'admin.platform_status.title', 'platform status uses its graduated runtime identity');
$assert(($platformWorkbench['show_migration_details'] ?? false) === true, 'Development status exposes governed migration evidence');
$assert(($platformWorkbench['candidate_id'] ?? '') === 'ADM-MODE-001', 'workbench tracks the first stable inventory candidate');
$workbenchUrls = array_column((array)($platformWorkbench['links'] ?? []), 'url');
$assert(count($workbenchUrls) === 3, 'Development status exposes catalog, target, and architecture evidence');
$assert(!in_array('/ops/platform-admin-dashboard', $workbenchUrls, true), 'deleted source dashboard is not emitted');
$assert(in_array('/admin/system-tools/platform-mode', $workbenchUrls, true), 'workbench points to the implementing target workspace');
$productionStatusUrls = array_column((array)($productionPlatformStatus['links'] ?? []), 'url');
$assert(($productionPlatformStatus['show_migration_details'] ?? true) === false, 'normal runtime status hides migration evidence');
$assert($productionStatusUrls === ['/admin/system-tools/platform-mode'], 'normal runtime status retains only the canonical operational handoff');
$assert(!in_array('/ops/platform-admin-dashboard', $productionStatusUrls, true), 'deleted source dashboard is absent from normal status');
$assert(!isset($appAdminWorkbench['candidate_id']), 'App Admin status has no retired compatibility candidate');
$assert(($appAdminWorkbench['status_key'] ?? '') === 'admin.platform_status.status.operational', 'app admin status exposes a stable operational state');
$assert(($appAdminWorkbench['show_migration_details'] ?? true) === false, 'app admin status keeps migration evidence out of the runtime surface');
$assert(($appAdminWorkbench['assigned_apps_count'] ?? 0) === 2, 'app admin transition card reports assigned-app scope');
$appAdminWorkbenchUrls = array_column((array)($appAdminWorkbench['links'] ?? []), 'url');
$assert($appAdminWorkbenchUrls === [], 'App Admin status emits no legacy dashboard handoff');
$assert(!in_array('/admin/system-tools/platform-mode', $appAdminWorkbenchUrls, true), 'app admin transition does not expose Platform Mode');
$assert(!in_array('/admin/system-tools/upgrade-catalog', $appAdminWorkbenchUrls, true), 'app admin transition does not expose platform catalog');

$platformModeNavigation = null;
foreach ((array)($platformNavigation['items'] ?? []) as $navigationItem) {
    if (is_array($navigationItem) && ($navigationItem['key'] ?? '') === 'platform_mode') {
        $platformModeNavigation = $navigationItem;
        break;
    }
}
$assert(($platformModeNavigation['url'] ?? '') === '/admin/system-tools/platform-mode', 'platform mode target is registered under System Tools');
$selectorSource = (string)file_get_contents(APP_ROOT . '/plugins/Base/Views/ops/platform_mode_selector.php');
$assert(str_contains($selectorSource, 'name="return_to"'), 'shared selector carries an explicit feedback return path');

echo "Admin dashboard launcher probe: {$assertions}/{$assertions} assertions passed\n";
