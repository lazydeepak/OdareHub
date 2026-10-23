<?php
declare(strict_types=1);

/** Offline contract probe for the read-only Procurement operator queue. */
$root = dirname(__DIR__, 3);
$service = file_get_contents($root . '/apps/Procurement/Services/ProcurementOverviewService.php');
$provider = file_get_contents($root . '/apps/Procurement/Services/OperatorSurfaceContributionService.php');
$view = file_get_contents($root . '/apps/Procurement/Views/operator/requests.php');
$manifest = file_get_contents($root . '/apps/Procurement/manifest.json');
$routes = file_get_contents($root . '/apps/Shell/routes.php');
$composer = file_get_contents($root . '/apps/Shell/Composers/OperatorDashboardComposer.php');
$providerPath = $root . '/apps/Procurement/Services/OperatorSurfaceContributionService.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}
require_once $providerPath;
$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $label) use (&$passed, &$failed): void {
    if ($ok) { $passed++; echo "PASS [$label]\n"; } else { $failed++; echo "FAIL [$label]\n"; }
};

$check(str_contains($service, 'public static function ownedOperatorRequests'), 'projection exists');
$check(str_contains($service, "\$where = ['r.created_by = ?']") && str_contains($service, 'implode(\' AND \', $where)'), 'exact creator filter');
$check(str_contains($service, 'LEFT JOIN procurement_purchase_orders'), 'safe PO linkage');
$check(str_contains($service, 'min(50'), 'bounded limit');
$check(str_contains($provider, "in_array('procurement', \$apps, true)"), 'assignment gate');
$check(str_contains($provider, "in_array('procurement.view', \$permissions, true)"), 'explicit view permission gate');
$check(!str_contains($provider, 'procurement.manage'), 'manage does not grant queue');
$check(!str_contains($provider, 'procurement.approve'), 'approve does not grant queue');
$check(str_contains($view, 'data-procurement-operator-queue="1"'), 'queue view exists');
$check(str_contains($view, 'requested_qty') && str_contains($view, 'po_ref'), 'approved fields rendered');
$check(!str_contains($view, 'method="post"') && !str_contains($view, 'type="submit" name="'), 'queue has no mutation form');
$check(str_contains($manifest, 'procurement.operator.focus_views'), 'operator focus hook registered');
$check(str_contains($routes, "\$router->get('/u/procurement'"), 'operator route registered');
$check(str_contains($routes, 'bool $allowContributedFocus = false'), 'contributed route opt-in is explicit');
$check(str_contains($composer, '$isContributedFocus'), 'generic contributed focus rendering');

$authorized = \Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.view']],
]);
$check(isset($authorized['view_map']['procurement']), 'authorized focus contribution');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.manage']],
]) === [], 'manage-only denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => ['procurement.approve']],
]) === [], 'approve-only denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['procurement'], 'permissions' => []],
]) === [], 'missing view denied');
$check(\Apps\Procurement\Services\OperatorSurfaceContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => [], 'permissions' => ['procurement.view']],
]) === [], 'unassigned denied');

echo "RESULT: {$passed}/" . ($passed + $failed) . "\n";
exit($failed === 0 ? 0 : 1);
