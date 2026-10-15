<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Composers/OperatorWorkerWorkComposer.php';
require_once APP_ROOT . '/apps/Shell/Services/OperatorLayerSidebarService.php';
require_once APP_ROOT . '/apps/Shell/Services/OperatorLayerWidgetService.php';

use Apps\Shell\Composers\OperatorWorkerWorkComposer;
use Apps\Shell\Services\OperatorLayerSidebarService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$tasks = [
    ['id' => 1, 'title' => 'Current item', 'assigned_to' => 7, 'status' => 'in_progress', 'priority' => 'high', 'due_date' => date('Y-m-d')],
    ['id' => 2, 'title' => 'Upcoming item', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'medium', 'due_date' => date('Y-m-d', strtotime('+2 days'))],
    ['id' => 3, 'title' => 'Overdue item', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'urgent', 'due_date' => date('Y-m-d', strtotime('-1 day'))],
    ['id' => 6, 'title' => 'Blocked item', 'assigned_to' => 7, 'status' => 'blocked', 'priority' => 'high', 'due_date' => date('Y-m-d', strtotime('+3 days'))],
    ['id' => 7, 'title' => 'Unsafe destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/apps/manufacturing/products', 'due_date' => date('Y-m-d')],
    ['id' => 4, 'title' => 'Unauthorized item', 'assigned_to' => 99, 'status' => 'open', 'priority' => 'high', 'due_date' => date('Y-m-d')],
    ['id' => 5, 'title' => 'Completed item', 'assigned_to' => 7, 'status' => 'done', 'priority' => 'low', 'due_date' => date('Y-m-d')],
];
$result = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['authenticated_user_id' => 7, 'scope' => ['part_ids' => [10]]], 'worker');
$assert(count($result['current']) === 2 && $result['current'][0]['item_id'] === 1, 'current work precedes upcoming classification');
$assert(count($result['upcoming']) === 1 && $result['upcoming'][0]['item_id'] === 2, 'future due item is upcoming');
$assert(count($result['attention']) === 2 && $result['attention'][0]['item_id'] === 3 && $result['attention'][0]['attention_reason'] === 'overdue' && $result['attention'][1]['attention_reason'] === 'blocked', 'overdue and blocked work are distinguishable as attention');
$assert(!in_array(99, array_column($result['current'], 'assigned_to'), true), 'unauthorized item remains hidden');
$assert($result['attention'][0]['status'] === 'open' && $result['attention'][0]['item_ref'] === 'operator-task:3', 'authoritative status and identifier are preserved');
$assert($result['current'][0]['operational_scope']['part_ids'] === [10], 'computed operational scope is retained');
$assert($result['upcoming'][0]['destination'] === '/u/worker/tasks', 'destination remains operator-confined');
$unsafe = array_values(array_filter($result['current'], static fn (array $item): bool => $item['item_id'] === 7));
$assert(($unsafe[0]['destination'] ?? '') === '/u/worker/tasks' && ($unsafe[0]['source_destination'] ?? '') === '/apps/manufacturing/products', 'unsafe provider destination is retained as evidence but not rendered');
$assert($result['current'][0]['item_scope'] === [], 'provider scope remains available without fabrication');
$empty = OperatorWorkerWorkComposer::compose(['tasks' => [], 'error' => ''], ['authenticated_user_id' => 7], 'worker');
$assert($empty['available'] === true && $empty['current'] === [] && $empty['upcoming'] === [] && $empty['attention'] === [], 'empty assignments produce safe empty lanes');
$unavailable = OperatorWorkerWorkComposer::compose(['tasks' => [], 'error' => 'operator.common.data_unavailable'], ['authenticated_user_id' => 7], 'worker');
$assert($unavailable['available'] === false && $unavailable['error'] !== '', 'provider failure remains an unavailable state');
$identityUnavailable = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['authenticated_user_id' => 7, 'operator_identity_bound' => false], 'other-worker');
$assert($identityUnavailable['available'] === false && $identityUnavailable['current'] === [], 'mismatched workspace identity cannot expose assignments');

$profile = [
    'nav_sections' => [[
        'title' => 'Profile',
        'items' => [
            ['label' => 'Allowed', 'route' => '/u/{user}/tasks'],
            ['label' => 'Denied view', 'route' => '/u/{user}/production'],
            ['label' => 'Escape', 'route' => '/apps/manufacturing/products'],
        ],
    ]],
];
$sidebar = new OperatorLayerSidebarService([
    'active_assigned_apps' => ['sbaio'],
    'resolved_operator_view_keys' => 'dashboard,tasks,account',
    'workspace_profile' => $profile,
]);
$sections = $sidebar->getContextualSidebar()['sections'] ?? [];
$profileItems = (array)($sections[0]['items'] ?? []);
$assert(count($profileItems) === 1 && ($profileItems[0]['route'] ?? '') === '/u/{user}/tasks', 'forged profile navigation cannot bypass effective access or confinement');
$formatted = $sidebar->formatSidebarItems(['sections' => $sections], 'worker');
$assert(($formatted['sections'][0]['items'][0]['route'] ?? '') === '/u/worker/tasks', 'formatted profile navigation remains operator confined');

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
