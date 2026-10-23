<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/modules/Machines/Services/MachineLeaderDashboardService.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/MachinesAdapter.php';

use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;
use Plugins\Machines\Services\MachineLeaderDashboardService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$valid = MachineLeaderDashboardService::normalizeFilters([
    'machine_id' => '12',
    'section' => ' Cutting ',
]);
$assert($valid['valid'] === true && $valid['machine_id'] === 12 && $valid['section'] === 'Cutting', 'valid scalar filters normalize without changing domain meaning');

$cleared = MachineLeaderDashboardService::normalizeFilters([
    'machine_id' => '',
    'section' => '   ',
]);
$assert($cleared['valid'] === true && $cleared['machine_id'] === 0 && $cleared['section'] === '', 'blank values represent intentional filter clearing');

$invalidMachineCases = [
    ['machine_id' => ['12']],
    ['machine_id' => '-1'],
    ['machine_id' => '0'],
    ['machine_id' => '12.5'],
    ['machine_id' => '12junk'],
    ['machine_id' => '999999999999999999999999999999'],
    ['machine_id' => true],
];
foreach ($invalidMachineCases as $index => $input) {
    $normalized = MachineLeaderDashboardService::normalizeFilters($input);
    $assert(!$normalized['valid'] && in_array('machine_id', $normalized['invalid'], true), 'invalid machine identifier case ' . ($index + 1) . ' fails closed');
}

$invalidSectionCases = [
    ['section' => ['Cutting']],
    ['section' => str_repeat('x', 121)],
    ['section' => "Cut\x00ting"],
    ['section' => "\xC3\x28"],
];
foreach ($invalidSectionCases as $index => $input) {
    $normalized = MachineLeaderDashboardService::normalizeFilters($input);
    $assert(!$normalized['valid'] && in_array('section', $normalized['invalid'], true), 'invalid section case ' . ($index + 1) . ' fails closed');
}

$fixture = [
    'scope' => ['scope_machine_ids' => [1, 2], 'scope_part_ids' => []],
    'assigned_machines' => [
        ['id' => 1, 'machine_no' => 'M-01', 'section' => 'Cutting'],
        ['id' => 2, 'machine_no' => 'M-02', 'section' => 'Forming'],
    ],
    'filters' => [
        'machines' => [
            ['id' => 1, 'machine_no' => 'M-01', 'section' => 'Cutting'],
            ['id' => 2, 'machine_no' => 'M-02', 'section' => 'Forming'],
        ],
        'sections' => ['Cutting', 'Forming'],
    ],
    'run_now' => [
        ['id' => 10, 'machine_id' => 1, 'product_id' => 101],
        ['id' => 11, 'machine_id' => 2, 'product_id' => 102],
    ],
    'next_queue' => [
        ['id' => 12, 'machine_id' => 1, 'product_id' => 101],
        ['id' => 13, 'machine_id' => 2, 'product_id' => 102],
    ],
    'delayed_jobs' => [],
    'waiting_qc' => [],
    'risk_jobs' => [],
    'kpi' => ['today_planned_qty' => 30.0, 'today_produced_qty' => 10.0],
    'quantity_provenance' => [
        'source' => 'MachineLeaderDashboardService::fetchPlanRows',
        'scope_machine_ids' => [1, 2],
        'scope_part_ids' => [],
        'complete_window' => true,
    ],
];

$projected = MachinesAdapter::projectScopedData($fixture);
$assert($projected !== null && count($projected['run_now']) === 2 && count($projected['next_queue']) === 2, 'unfiltered projection parity retains all authorized rows');

$machineFiltered = $fixture;
$machineFiltered['scope']['scope_machine_ids'] = [1];
$machineFiltered['quantity_provenance']['scope_machine_ids'] = [1];
$machineFiltered['assigned_machines'] = [$fixture['assigned_machines'][0]];
$machineFiltered['filters']['machines'] = [$fixture['filters']['machines'][0]];
$machineFiltered['filters']['sections'] = ['Cutting'];
$machineFiltered['run_now'] = [$fixture['run_now'][0]];
$machineFiltered['next_queue'] = [$fixture['next_queue'][0]];
$machineResult = MachinesAdapter::projectScopedData($machineFiltered);
$assert($machineResult !== null && count($machineResult['run_now']) === 1 && (int)$machineResult['run_now'][0]['machine_id'] === 1, 'machine filter projection retains only selected authorized machine');
$assert($machineResult !== null && $machineResult['filters']['sections'] === ['Cutting'], 'machine filter options remain scope-aligned');

$invalidProjection = $fixture;
$invalidProjection['scope'] = ['scope_machine_ids' => [], 'scope_part_ids' => []];
$invalidProjection['run_now'] = [];
$invalidProjection['next_queue'] = [];
$invalidProjection['assigned_machines'] = [];
$invalidProjection['filters'] = ['machines' => [], 'sections' => []];
$invalidProjection['invalid_filters'] = ['machine_id'];
$invalidResult = MachinesAdapter::projectScopedData($invalidProjection);
$assert($invalidResult !== null && $invalidResult['run_now'] === [] && $invalidResult['next_queue'] === [], 'invalid filter result cannot broaden into an unfiltered projection');

$assert(MachinesAdapter::projectScopedData(['scope' => ['scope_machine_ids' => [1], 'scope_part_ids' => []], 'run_now' => []]) !== null, 'existing minimal scoped projection remains accepted');

$view = file_get_contents(APP_ROOT . '/apps/Manufacturing/Views/operator/machines.php');
$assert(is_string($view) && str_contains($view, 'method="get"') && str_contains($view, 'name="machine_id"') && str_contains($view, 'name="section"'), 'operator view exposes GET machine and section controls');
$assert(is_string($view) && str_contains($view, '/machines"') && str_contains($view, 'operator.machines.filter.clear'), 'filter and clear controls preserve the same-user machines route');
$assert(is_string($view) && str_contains($view, 'htmlspecialchars($mchFilterMachineId') && str_contains($view, 'htmlspecialchars($mchFilterSection'), 'filter values are escaped before rendering');

$localeKeys = [
    APP_ROOT . '/apps/Shell/Resources/lang/en.php',
    APP_ROOT . '/apps/Shell/Resources/lang/ja.php',
    APP_ROOT . '/apps/Shell/Resources/lang/ne.php',
];
foreach ($localeKeys as $localePath) {
    $locale = require $localePath;
    $assert(is_array($locale) && isset($locale['operator.machines.filter.title'], $locale['operator.machines.filter.clear']), 'machine filter localization keys exist: ' . basename($localePath));
}

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";
