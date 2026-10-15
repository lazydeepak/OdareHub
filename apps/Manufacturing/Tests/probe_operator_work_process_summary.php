<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorWorkProcessSummaryService.php';

use Apps\Manufacturing\Services\OperatorWorkProcessSummaryService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
};

$context = [
    'operational_role' => 'assembly_operator',
    'cross_functional_access' => [],
];
$allowProcessing = static fn(string $path): bool => $path === '/u/worker/processing';

$assemblyProjection = [
    'authorized' => true,
    'can_mutate' => false,
    'assembly_allowed' => true,
    'qc_allowed' => false,
    'assembly_rows' => [[
        'id' => 11,
        'product_id' => 10,
        'demand_date' => '2026-10-15',
        'status' => 'adjusted',
        'planned_qty' => 5,
        'part_name' => 'Part X',
        'part_number' => 'PX-10',
    ]],
    'qc_rows' => [[
        'id' => 41,
        'product_id' => 99,
        'plan_date' => '2026-10-15',
        'status' => 'Open',
        'part_name' => 'Unauthorized Part Y',
    ]],
];
$summary = OperatorWorkProcessSummaryService::summarizeProjection(
    $assemblyProjection,
    $context,
    'worker',
    $allowProcessing,
    '2026-10-15'
);
$assert($summary['available'] === true, 'authorized Assembly projection is available');
$assert(count($summary['lanes']) === 1, 'Assembly-only authority exposes one lane');
$assert(($summary['lanes'][0]['key'] ?? '') === 'assembly', 'Assembly lane identity is preserved');
$assert(($summary['lanes'][0]['count'] ?? -1) === 1, 'count uses only authorized projection rows');
$assert(($summary['lanes'][0]['records'][0]['part_name'] ?? '') === 'Part X', 'unauthorized Part Y cannot enter aggregation');
$assert(($summary['lanes'][0]['destination'] ?? '') === '/u/worker/processing?focus=processing&processing_date=2026-10-15', 'same-user Processing destination is retained');
$assert(($summary['lanes'][0]['presentation_area'] ?? '') !== '', 'area label is presentation metadata for the authorized lane');

$qcProjection = [
    'authorized' => true,
    'can_mutate' => false,
    'assembly_allowed' => false,
    'qc_allowed' => true,
    'assembly_rows' => [['id' => 12, 'product_id' => 10]],
    'qc_rows' => [[
        'id' => 42,
        'product_id' => 10,
        'plan_date' => '2026-10-15',
        'status' => 'System Generated',
        'priority' => 'High',
        'part_name' => 'Part X',
        'part_number' => 'PX-10',
    ]],
];
$qcSummary = OperatorWorkProcessSummaryService::summarizeProjection(
    $qcProjection,
    ['operational_role' => 'qc_operator', 'cross_functional_access' => []],
    'worker',
    $allowProcessing,
    '2026-10-15'
);
$assert(count($qcSummary['lanes']) === 1 && ($qcSummary['lanes'][0]['key'] ?? '') === 'qc', 'QC-only authority exposes only QC');
$assert(($qcSummary['lanes'][0]['count'] ?? -1) === 1, 'QC count is computed from QC projection rows');

$denied = OperatorWorkProcessSummaryService::summarizeProjection(
    ['authorized' => false, 'assembly_allowed' => true, 'qc_allowed' => true, 'assembly_rows' => [], 'qc_rows' => []],
    $context,
    'worker',
    $allowProcessing,
    '2026-10-15'
);
$assert($denied['available'] === false, 'denied Processing projection is unavailable');
$assert($denied['lanes'] === [], 'denied projection leaks no lanes');
$assert(($denied['total_count'] ?? -1) === 0, 'denied projection leaks no count');

$emptyLane = OperatorWorkProcessSummaryService::summarizeProjection(
    ['authorized' => true, 'can_mutate' => false, 'assembly_allowed' => true, 'qc_allowed' => false, 'assembly_rows' => [], 'qc_rows' => []],
    $context,
    'worker',
    static fn(string $path): bool => false,
    '2026-10-15'
);
$assert($emptyLane['available'] === true && $emptyLane['state'] === 'empty', 'authorized empty lane is distinct from unavailable');
$assert(($emptyLane['lanes'][0]['count'] ?? -1) === 0, 'authorized empty lane has zero activity');
$assert(($emptyLane['lanes'][0]['destination'] ?? '') === '', 'route authority failure omits destination');

$malformed = OperatorWorkProcessSummaryService::summarizeProjection(
    ['authorized' => true, 'assembly_allowed' => true, 'qc_allowed' => false, 'assembly_rows' => [['id' => 'bad', 'product_id' => 0]], 'qc_rows' => []],
    $context,
    'worker',
    $allowProcessing,
    '2026-10-15'
);
$assert($malformed['available'] === true && ($malformed['lanes'][0]['count'] ?? -1) === 0, 'malformed records are suppressed without inventing data');

$serviceSource = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorWorkProcessSummaryService.php');
$assert(!str_contains($serviceSource, 'ensureSchema'), 'summary service introduces no schema initializer');
$assert(!str_contains($serviceSource, 'StageTransitionService'), 'summary service does not derive stages without product attributes');
$assert(!str_contains($serviceSource, 'MyWorkEntityQuery'), 'summary service does not consume broad My Work queries');

$dashboardRenderer = new class {
    public function tr(string $key, string $fallback, array $params = []): string
    {
        return $fallback;
    }

    /** @param array<string,mixed> $data */
    public function render(array $data): string
    {
        ob_start();
        include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php';
        return (string)ob_get_clean();
    }
};
$rendered = $dashboardRenderer->render([
    'dashboard_title' => 'Overview',
    'operator_role_home_enabled' => false,
    'operator_role_home_mode' => 'worker',
    'operator_role_regions' => [],
    'worker_work' => ['available' => true, 'current' => [], 'upcoming' => [], 'attention' => []],
    'work_process_summary' => $summary,
    'common_home_tiles' => [],
    'dashboard_tiles' => [],
    'manufacturing_available' => false,
]);
$assert(str_contains($rendered, 'data-work-process-lane="assembly"'), 'dashboard renders the authorized Assembly lane');
$assert(!str_contains($rendered, 'data-work-process-lane="qc"'), 'dashboard does not render unauthorized QC lane');
$assert(str_contains($rendered, 'Part X'), 'dashboard renders the scope-safe part record');
$assert(str_contains($rendered, '/u/worker/processing?focus=processing'), 'dashboard renders only the same-user Processing destination');

echo "Operator work/process summary: {$passed}/" . ($passed + $failed) . " passed\n";
exit($failed === 0 ? 0 : 1);
