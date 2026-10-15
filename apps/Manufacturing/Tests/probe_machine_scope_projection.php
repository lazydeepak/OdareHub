<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/MachinesAdapter.php';

use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$fixture = [
    'assigned_machines' => [
        ['id' => 1, 'machine_no' => 'A', 'section' => 'Cutting'],
        ['id' => 2, 'machine_no' => 'B', 'section' => 'Forming'],
    ],
    'filters' => [
        'machines' => [
            ['id' => 1, 'machine_no' => 'A', 'section' => 'Cutting'],
            ['id' => 2, 'machine_no' => 'B', 'section' => 'Forming'],
        ],
        'sections' => ['Cutting', 'Forming'],
    ],
    'run_now' => [
        ['id' => 11, 'machine_id' => 1, 'product_id' => 10],
        ['id' => 12, 'machine_id' => 2, 'product_id' => 20],
    ],
    'next_queue' => [
        ['id' => 13, 'machine_id' => 1, 'product_id' => 10],
        ['id' => 14, 'machine_id' => 2, 'product_id' => 10],
    ],
    'delayed_jobs' => [
        ['id' => 15, 'machine_id' => 2, 'product_id' => 10],
    ],
    'waiting_qc' => [
        ['id' => 16, 'machine_id' => 1, 'product_id' => 20],
    ],
    'risk_jobs' => [],
    'kpi' => ['today_planned_qty' => 30.0, 'today_produced_qty' => 10.0],
];

$fixture['scope'] = ['scope_machine_ids' => [1], 'scope_part_ids' => []];
$fixture['quantity_provenance'] = [
    'source' => 'MachineLeaderDashboardService::fetchPlanRows',
    'scope_machine_ids' => [1],
    'scope_part_ids' => [],
    'complete_window' => true,
];
$machineA = MachinesAdapter::projectScopedData($fixture);
$assert(count($machineA['run_now']) === 1 && (int)$machineA['run_now'][0]['machine_id'] === 1, 'machine scope excludes machine B current work');
$assert(count($machineA['next_queue']) === 1 && (int)$machineA['next_queue'][0]['machine_id'] === 1, 'machine scope excludes machine B queue work');
$assert(count($machineA['delayed_jobs']) === 0, 'machine scope excludes machine B delayed work');
$assert(count($machineA['assigned_machines']) === 1 && (int)$machineA['assigned_machines'][0]['id'] === 1, 'machine scope narrows assigned-machine records');
$assert(count($machineA['filters']['machines']) === 1 && $machineA['filters']['sections'] === ['Cutting'], 'machine filters and sections use the same scope');
$assert($machineA['sections'] === $machineA['filters']['sections'], 'published top-level sections match scoped filters');
$assert((int)$machineA['kpi']['run_now_jobs'] === 1 && (int)$machineA['kpi']['next_queue_jobs'] === 1, 'machine KPIs use scoped queue records');
$assert((float)$machineA['kpi']['today_planned_qty'] === 30.0 && (float)$machineA['kpi']['today_produced_qty'] === 10.0, 'quantity KPIs retain explicit complete-window scoped provenance');

$partXFixture = $fixture;
$partXFixture['scope'] = ['scope_machine_ids' => [], 'scope_part_ids' => [10]];
$partXFixture['quantity_provenance']['scope_machine_ids'] = [];
$partXFixture['quantity_provenance']['scope_part_ids'] = [10];
$partX = MachinesAdapter::projectScopedData($partXFixture);
$assert(count($partX['run_now']) === 1 && (int)$partX['run_now'][0]['product_id'] === 10, 'part scope excludes unrelated current work');
$assert(count($partX['waiting_qc']) === 0, 'part scope excludes unrelated QC work');

$combinedFixture = $fixture;
$combinedFixture['scope'] = ['scope_machine_ids' => [1, 2], 'scope_part_ids' => [10]];
$combinedFixture['quantity_provenance']['scope_machine_ids'] = [1, 2];
$combinedFixture['quantity_provenance']['scope_part_ids'] = [10];
$combined = MachinesAdapter::projectScopedData($combinedFixture);
$assert(count($combined['next_queue']) === 2 && count($combined['delayed_jobs']) === 1, 'combined machine and part scope intersects records');

$unrestrictedFixture = $fixture;
$unrestrictedFixture['scope'] = ['scope_machine_ids' => [], 'scope_part_ids' => []];
$unrestrictedFixture['quantity_provenance']['scope_machine_ids'] = [];
$unrestrictedFixture['quantity_provenance']['scope_part_ids'] = [];
$unrestricted = MachinesAdapter::projectScopedData($unrestrictedFixture);
$assert(count($unrestricted['run_now']) === 2 && count($unrestricted['assigned_machines']) === 2, 'explicit empty scope preserves authorized unrestricted projection');
$assert(MachinesAdapter::projectScopedData(['scope' => ['scope_machine_ids' => null, 'scope_part_ids' => []]]) === null, 'malformed runtime scope fails closed');
$unverifiable = $fixture;
unset($unverifiable['quantity_provenance']);
$unverifiable = MachinesAdapter::projectScopedData($unverifiable);
$assert($unverifiable['kpi']['today_planned_qty'] === null && $unverifiable['kpi']['today_produced_qty'] === null, 'unverifiable quantity totals are suppressed');
$assert(!MachinesAdapter::operatorContextIsAuthorized(null), 'missing operator identity fails closed');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";
