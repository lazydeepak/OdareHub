<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/apps/Platform/Services/AppAdminDashboardScopeService.php';

use Apps\Platform\Services\AppAdminDashboardScopeService;

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$manufacturing = ['active_assigned_apps' => ['Manufacturing', 'platform', 'manufacturing'], 'assigned_apps' => ['sbaio']];
$assert(AppAdminDashboardScopeService::assignedApps($manufacturing) === ['manufacturing'], 'active assignments are authoritative and normalized');
$assert(AppAdminDashboardScopeService::allowsApp($manufacturing, 'manufacturing'), 'Manufacturing contribution is allowed when actively assigned');
$assert(!AppAdminDashboardScopeService::allowsApp($manufacturing, 'sbaio'), 'inactive fallback assignments do not broaden active scope');

$sbaio = ['assigned_apps' => 'SBAIO, platform'];
$assert(AppAdminDashboardScopeService::assignedApps($sbaio) === ['sbaio'], 'CSV assignments are normalized and platform is excluded');
$assert(AppAdminDashboardScopeService::allowsApp($sbaio, 'sbaio'), 'assigned SBAIO contribution is allowed');
$assert(!AppAdminDashboardScopeService::allowsApp($sbaio, 'manufacturing'), 'Manufacturing contribution is blocked for SBAIO-only admin');

$fallback = ['default_app' => 'Procurement'];
$assert(AppAdminDashboardScopeService::assignedApps($fallback) === ['procurement'], 'default app is used only when assignment lists are empty');
$assert(AppAdminDashboardScopeService::assignedApps(['default_app' => 'platform']) === [], 'platform default does not grant a business-app contribution');
$assert(AppAdminDashboardScopeService::assignedApps([]) === [], 'empty context remains empty');
$assert(!AppAdminDashboardScopeService::allowsApp([], 'manufacturing'), 'empty context fails closed');

echo "App Admin dashboard scope probe: {$checks}/{$checks} assertions passed\n";
