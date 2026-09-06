<?php
declare(strict_types=1);

/**
 * Lightweight regression checks for global search behavior.
 *
 * Run:
 *   php tools/search_regression_check.php
 */

define('APP_ROOT', dirname(__DIR__));
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ERP');
}

require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/app/Core/helpers.php';
require APP_ROOT . '/app/Core/DB.php';
require APP_ROOT . '/app/Core/AclPolicy.php';
require APP_ROOT . '/app/Core/ModuleRegistry.php';
require APP_ROOT . '/app/Core/SidebarBuilder.php';
require APP_ROOT . '/app/Core/WorkflowRegistry.php';
require APP_ROOT . '/app/Core/SearchService.php';

use App\Core\SearchService;

/**
 * @param array<string,mixed> $user
 * @return array{groups:array<int,array<string,mixed>>,itemsByType:array<string,int>,totalItems:int,labels:array<int,string>}
 */
function searchSnapshot(string $query, array $user): array
{
    $response = SearchService::search($query, $user);
    $groups = (array)($response['groups'] ?? []);
    $itemsByType = [];
    $totalItems = 0;
    $labels = [];

    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }
        $type = (string)($group['type'] ?? 'unknown');
        $items = (array)($group['items'] ?? []);
        $count = count($items);
        $itemsByType[$type] = ($itemsByType[$type] ?? 0) + $count;
        $totalItems += $count;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $labels[] = strtolower(trim((string)($item['label'] ?? '')));
        }
    }

    return [
        'groups' => $groups,
        'itemsByType' => $itemsByType,
        'totalItems' => $totalItems,
        'labels' => $labels,
    ];
}

function hasLabelContaining(array $labels, string $needle): bool
{
    $needle = strtolower(trim($needle));
    foreach ($labels as $label) {
        if (str_contains((string)$label, $needle)) {
            return true;
        }
    }
    return false;
}

/** @var array<string,mixed> $admin */
$admin = ['id' => 1, 'role' => 'PlatformOperations', 'authority_role' => 'platform_admin'];
/** @var array<string,mixed> $prodLeader */
$prodLeader = ['id' => 4, 'role' => 'Production Leader'];
/** @var array<string,mixed> $viewer */
$viewer = ['id' => 12, 'role' => 'Readonly Observer'];

$checks = [];

$plan = searchSnapshot('plan', $admin);
$plans = searchSnapshot('plans', $admin);
$checks[] = [
    'name' => 'plural normalization plan/plans stays equivalent',
    'ok' => $plan['totalItems'] === $plans['totalItems'],
    'detail' => 'plan=' . $plan['totalItems'] . ', plans=' . $plans['totalItems'],
];

$logs = searchSnapshot('logs', $admin);
$checks[] = [
    'name' => 'log intent excludes noisy actions group',
    'ok' => !isset($logs['itemsByType']['actions']),
    'detail' => 'groups=' . implode(',', array_keys($logs['itemsByType'])),
];

$navTree = searchSnapshot('navigation tree', $admin);
$checks[] = [
    'name' => 'navigation tree searchable for admin',
    'ok' => hasLabelContaining($navTree['labels'], 'navigation tree'),
    'detail' => 'labels=' . implode(' | ', array_slice($navTree['labels'], 0, 6)),
];

$permissionAdmin = searchSnapshot('permission', $admin);
$permissionProd = searchSnapshot('permission', $prodLeader);
$checks[] = [
    'name' => 'permissions visible to admin and hidden for production leader',
    'ok' => isset($permissionAdmin['itemsByType']['permissions']) && !isset($permissionProd['itemsByType']['permissions']),
    'detail' => 'admin=' . (int)($permissionAdmin['itemsByType']['permissions'] ?? 0) . ', prod=' . (int)($permissionProd['itemsByType']['permissions'] ?? 0),
];

$endpoint = searchSnapshot('endpoint', $admin);
$checks[] = [
    'name' => 'form endpoints indexed',
    'ok' => isset($endpoint['itemsByType']['form_endpoints']) && $endpoint['itemsByType']['form_endpoints'] > 0,
    'detail' => 'form_endpoints=' . (int)($endpoint['itemsByType']['form_endpoints'] ?? 0),
];

$workflow = searchSnapshot('workflow approve', $admin);
$checks[] = [
    'name' => 'workflow actions indexed',
    'ok' => isset($workflow['itemsByType']['workflow_actions']) && $workflow['itemsByType']['workflow_actions'] > 0,
    'detail' => 'workflow_actions=' . (int)($workflow['itemsByType']['workflow_actions'] ?? 0),
];

$partsMaster = searchSnapshot('parts master', $prodLeader);
$checks[] = [
    'name' => 'parts master discoverable',
    'ok' => hasLabelContaining($partsMaster['labels'], 'parts master'),
    'detail' => 'labels=' . implode(' | ', array_slice($partsMaster['labels'], 0, 8)),
];

$accessControlViewer = searchSnapshot('access control board', $viewer);
$checks[] = [
    'name' => 'access control board remains scope-restricted from viewer',
    'ok' => !hasLabelContaining($accessControlViewer['labels'], 'access control board'),
    'detail' => 'viewer_total=' . $accessControlViewer['totalItems'],
];

$failed = 0;
echo "Search regression checks\n";
echo "========================\n";
foreach ($checks as $i => $check) {
    $ok = (bool)($check['ok'] ?? false);
    $status = $ok ? 'PASS' : 'FAIL';
    if (!$ok) {
        $failed++;
    }
    echo sprintf("%02d. [%s] %s", $i + 1, $status, (string)($check['name'] ?? 'check')) . PHP_EOL;
    echo '    ' . (string)($check['detail'] ?? '') . PHP_EOL;
}

echo PHP_EOL . 'Result: ' . ($failed === 0 ? 'PASS' : 'FAIL (' . $failed . ' failed)') . PHP_EOL;
exit($failed === 0 ? 0 : 1);
