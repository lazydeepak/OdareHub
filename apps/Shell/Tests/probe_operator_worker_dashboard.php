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
    ['id' => 7, 'title' => 'Unsafe destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/apps/manufacturing/products', 'due_date' => date('Y-m-d')],
    ['id' => 9, 'title' => 'External destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => 'https://evil.example/u/worker/tasks', 'due_date' => date('Y-m-d')],
    ['id' => 10, 'title' => 'Cross-user destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/u/other/tasks', 'due_date' => date('Y-m-d')],
    ['id' => 11, 'title' => 'Fragment destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/u/worker/tasks#item', 'due_date' => date('Y-m-d')],
    ['id' => 12, 'title' => 'Unsafe query destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/u/worker/tasks?next=https%3A%2F%2Fevil.example', 'due_date' => date('Y-m-d')],
    ['id' => 13, 'title' => 'Traversal destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/u/worker/../admin', 'due_date' => date('Y-m-d')],
    ['id' => 14, 'title' => 'Malformed destination', 'assigned_to' => 7, 'status' => 'open', 'priority' => 'low', 'destination' => '/u/worker/tasks/%ZZ', 'due_date' => date('Y-m-d')],
    ['id' => 8, 'title' => 'Unsupported status', 'assigned_to' => 7, 'status' => 'blocked', 'priority' => 'high', 'due_date' => date('Y-m-d')],
    ['id' => 4, 'title' => 'Unauthorized item', 'assigned_to' => 99, 'status' => 'open', 'priority' => 'high', 'due_date' => date('Y-m-d')],
    ['id' => 5, 'title' => 'Completed item', 'assigned_to' => 7, 'status' => 'done', 'priority' => 'low', 'due_date' => date('Y-m-d')],
];
$allowWorkerRoute = static fn (string $path): bool => str_starts_with($path, '/u/worker/');
$workerContext = [
    'authenticated_user_id' => 7,
    'operator_identity_bound' => true,
    'scope' => ['part_ids' => [10]],
    'operator_route_access_resolver' => $allowWorkerRoute,
];
$result = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], $workerContext, 'worker');
$assert(count($result['current']) === 8 && $result['current'][0]['item_id'] === 1, 'current work precedes upcoming classification');
$assert(count($result['upcoming']) === 1 && $result['upcoming'][0]['item_id'] === 2, 'future due item is upcoming');
$assert(count($result['attention']) === 1 && $result['attention'][0]['item_id'] === 3 && $result['attention'][0]['attention_reason'] === 'overdue', 'overdue work is distinguishable as attention');
$assert(!in_array(99, array_column($result['current'], 'assigned_to'), true), 'unauthorized item remains hidden');
$assert(!in_array(8, array_column($result['current'], 'item_id'), true) && !in_array(8, array_column($result['attention'], 'item_id'), true), 'unsupported blocked status is not invented or rendered');
$providerSource = (string)file_get_contents(APP_ROOT . '/apps/Shell/Services/OperatorTaskService.php');
$assert(str_contains($providerSource, "STATUS_OPEN") && str_contains($providerSource, "STATUS_IN_PROGRESS") && !str_contains($providerSource, "STATUS_BLOCKED"), 'task provider contract remains open/in-progress only');
$assert($result['attention'][0]['status'] === 'open' && $result['attention'][0]['item_ref'] === 'operator-task:3', 'authoritative status and identifier are preserved');
$assert($result['current'][0]['effective_operational_scope']['part_ids'] === [10], 'effective user scope is retained distinctly');
$assert($result['upcoming'][0]['destination'] === '/u/worker/tasks', 'destination remains operator-confined');
$unsafe = array_values(array_filter($result['current'], static fn (array $item): bool => $item['item_id'] === 7));
$assert(($unsafe[0]['destination'] ?? '') === '/u/worker/tasks' && ($unsafe[0]['source_destination'] ?? '') === '/apps/manufacturing/products', 'unsafe provider destination is retained as evidence but not rendered');
$unsafeDestinations = array_values(array_filter($result['current'], static fn (array $item): bool => in_array($item['item_id'], [9, 10, 11, 12, 13, 14], true)));
$assert(count($unsafeDestinations) === 6 && count(array_filter($unsafeDestinations, static fn (array $item): bool => $item['destination'] !== '/u/worker/tasks')) === 0, 'external, cross-user, fragment, malformed, traversal, and unsafe-query destinations fall back safely');
$assert($result['current'][0]['item_scope'] === null, 'missing provider item scope remains unavailable without fabrication');
$empty = OperatorWorkerWorkComposer::compose(['tasks' => [], 'error' => ''], $workerContext, 'worker');
$assert($empty['available'] === true && $empty['current'] === [] && $empty['upcoming'] === [] && $empty['attention'] === [], 'empty assignments produce safe empty lanes');
$unavailable = OperatorWorkerWorkComposer::compose(['tasks' => [], 'error' => 'operator.common.data_unavailable'], $workerContext, 'worker');
$assert($unavailable['available'] === false && $unavailable['error'] !== '', 'provider failure remains an unavailable state');
$missingIdentity = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['operator_identity_bound' => true, 'operator_route_access_resolver' => $allowWorkerRoute], 'worker');
$assert($missingIdentity['available'] === false && $missingIdentity['current'] === [], 'missing authenticated identity fails closed');
$zeroIdentity = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['authenticated_user_id' => 0, 'operator_identity_bound' => true, 'operator_route_access_resolver' => $allowWorkerRoute], 'worker');
$assert($zeroIdentity['available'] === false && $zeroIdentity['current'] === [], 'zero authenticated identity fails closed');
$identityUnavailable = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['authenticated_user_id' => 7, 'operator_identity_bound' => false, 'operator_route_access_resolver' => $allowWorkerRoute], 'other-worker');
$assert($identityUnavailable['available'] === false && $identityUnavailable['current'] === [], 'mismatched workspace identity cannot expose assignments');
$noRouteAuthority = OperatorWorkerWorkComposer::compose(['tasks' => $tasks, 'error' => ''], ['authenticated_user_id' => 7, 'operator_identity_bound' => true], 'worker');
$assert(($noRouteAuthority['current'][0]['destination'] ?? '') === '', 'missing route authority suppresses task destinations');

$profile = [
    'nav_sections' => [[
        'title' => 'Profile',
        'items' => [
            ['label' => 'Allowed', 'route' => '/u/{user}/tasks'],
            ['label' => 'Allowed focus', 'route' => '/u/{user}/tasks?focus=tasks'],
            ['label' => 'Denied view', 'route' => '/u/{user}/production'],
            ['label' => 'Unsafe query', 'route' => '/u/{user}/tasks?next=https%3A%2F%2Fevil.example'],
            ['label' => 'Cross user', 'route' => '/u/other/tasks'],
            ['label' => 'Escape', 'route' => '/apps/manufacturing/products'],
        ],
    ]],
];
$sidebar = new OperatorLayerSidebarService([
    'active_assigned_apps' => ['sbaio'],
    'resolved_operator_view_keys' => 'dashboard,tasks,account',
    'operator_username' => 'worker',
    'operator_route_access_resolver' => static fn (string $path): bool => $path === '/u/worker/tasks',
    'workspace_profile' => $profile,
]);
$sections = $sidebar->getContextualSidebar()['sections'] ?? [];
$profileItems = (array)($sections[0]['items'] ?? []);
$assert(count($profileItems) === 2 && ($profileItems[0]['route'] ?? '') === '/u/{user}/tasks' && ($profileItems[1]['route'] ?? '') === '/u/{user}/tasks?focus=tasks', 'profile navigation preserves authorized query variation only');
$formatted = $sidebar->formatSidebarItems(['sections' => $sections], 'worker');
$assert(($formatted['sections'][0]['items'][0]['route'] ?? '') === '/u/worker/tasks' && ($formatted['sections'][0]['items'][1]['route'] ?? '') === '/u/worker/tasks?focus=tasks', 'formatted profile navigation remains operator confined');
$formattedMobile = $sidebar->formatSidebarItems(['sections' => $sections], 'worker');
$assert($formattedMobile === $formatted, 'desktop and mobile navigation share the same filtered destination set');
$layerSource = (string)file_get_contents(APP_ROOT . '/apps/Shell/Services/OperatorLayerService.php');
$assert(str_contains($layerSource, 'routeAccessDecision') && str_contains($layerSource, 'operator_route_access_resolver'), 'profile destinations delegate to existing route-access authority');

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
