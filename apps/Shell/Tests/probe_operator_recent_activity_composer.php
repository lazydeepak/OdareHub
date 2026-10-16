<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Composers/OperatorRecentActivityComposer.php';

use Apps\Shell\Composers\OperatorRecentActivityComposer;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passed++ : $failed++;
};

$empty = static function (): array {
    return [
        'title' => 'Recent User Activity',
        'subtitle' => 'Latest logged actions for this operator account.',
        'empty_label' => 'No activity log entries found.',
        'total' => 0,
        'unique_events' => 0,
        'event_bars' => [],
        'rows' => [],
    ];
};

$queries = [];
$rows = [
    [
        'created_at' => '2026-01-02 12:00:00',
        'event_type' => 'workflow',
        'action_name' => 'approved',
        'actor_email' => 'operator@example.test',
        'actor_display_name' => '',
        'entity_type' => 'production_plan',
        'entity_id' => 5,
        'app_key' => 'manufacturing',
        'module_key' => 'production',
        'note_text' => 'Approved note',
        'reason_text' => '',
        'new_state' => 'approved',
    ],
    [
        'created_at' => 'not-a-date',
        'event_type' => 'approval',
        'action_name' => 'rejected',
        'actor_email' => 'reviewer@example.test',
        'actor_display_name' => 'Reviewer',
        'entity_type' => 'procurement_supplier',
        'entity_id' => 9,
        'app_key' => 'procurement',
        'module_key' => '',
        'note_text' => '',
        'reason_text' => 'Rejected reason',
        'new_state' => '',
    ],
    [
        'created_at' => '',
        'event_type' => 'workflow',
        'action_name' => 'submitted',
        'actor_email' => '',
        'actor_display_name' => '',
        'entity_type' => 'production_plan',
        'entity_id' => 5,
        'app_key' => '',
        'module_key' => '',
        'note_text' => '',
        'reason_text' => '',
        'new_state' => 'submitted',
    ],
];
$entityRows = [
    'production_plans' => [
        'plan_date' => '2026-01-05',
        'product_id' => 12,
        'part_name' => 'Widget A',
    ],
    'procurement_suppliers' => [
        'supplier_code' => 'SUP-9',
        'supplier_name' => 'Supplier Nine',
    ],
];

$fetchAll = static function (string $sql, array $params) use (&$queries, $rows): array {
    $queries[] = ['kind' => 'all', 'sql' => $sql, 'params' => $params];
    return $rows;
};
$fetchOne = static function (string $sql, array $params) use (&$queries, $entityRows): array {
    $queries[] = ['kind' => 'one', 'sql' => $sql, 'params' => $params];
    foreach ($entityRows as $table => $row) {
        if (str_contains($sql, $table)) {
            return $row;
        }
    }
    return [];
};
$translator = static function (string $key, string $fallback, array $params = []): string {
    $localized = [
        'operator.recent.event_workflow' => 'Localized Workflow',
        'operator.recent.action_approved' => 'Localized Approved',
    ];
    return $localized[$key] ?? $fallback;
};

$composer = new OperatorRecentActivityComposer($fetchAll, $fetchOne, $translator);
$result = $composer->build(['user_email' => 'operator@example.test']);
$assert($result['total'] === 3 && $result['unique_events'] === 2, 'event totals and unique-event count preserve parity');
$assert($result['event_bars'] === [
    ['label' => 'Localized Workflow', 'count' => 2, 'percent' => 100.0],
    ['label' => 'Approval Event', 'count' => 1, 'percent' => 50.0],
], 'event bars preserve sorted counts and percentage calculation');
$assert($result['rows'][0]['event_type'] === 'Localized Workflow', 'event localization is preserved');
$assert($result['rows'][0]['action'] === 'Localized Approved', 'action localization is preserved');
$assert($result['rows'][0]['when'] === '2026-01-02 12:00:00' && str_contains($result['rows'][0]['action_display'], '01/05'), 'valid activity and entity dates retain formatting');
$assert($result['rows'][1]['when'] === 'not-a-date', 'invalid activity dates retain the original fallback');
$assert($result['rows'][0]['actor'] === 'operator@example.test' && $result['rows'][1]['actor'] === 'Reviewer' && $result['rows'][2]['actor'] === 'System', 'actor display and fallback order are preserved');
$assert($result['rows'][0]['note'] === 'Approved note' && $result['rows'][1]['note'] === 'Rejected reason' && $result['rows'][2]['note'] === 'submitted', 'note/reason/state fallback order is preserved');
$assert($result['rows'][0]['module'] === 'manufacturing / production' && $result['rows'][2]['module'] === 'Unscoped', 'module display fallback is preserved');
$assert(count(array_filter($queries, static fn(array $query): bool => $query['kind'] === 'one')) === 2, 'entity summary cache prevents duplicate entity reads');
$assert($queries[0]['kind'] === 'all' && $queries[0]['params'] === ['operator@example.test'], 'audit query preserves the authenticated actor parameter');
$assert(str_contains($queries[0]['sql'], 'WHERE actor_email = ?') && str_contains($queries[0]['sql'], 'ORDER BY created_at DESC, id DESC') && str_contains($queries[0]['sql'], 'LIMIT 200'), 'audit predicates, ordering, and row limit remain unchanged');

$queryCalls = 0;
$missing = (new OperatorRecentActivityComposer(
    static function (string $sql, array $params) use (&$queryCalls): array {
        $queryCalls++;
        return [];
    },
    $fetchOne,
    $translator
))->build([]);
$assert($missing === $empty(), 'missing identity preserves the established empty payload');
$assert($queryCalls === 0, 'missing identity avoids activity queries');

$failure = (new OperatorRecentActivityComposer(
    static function (): array {
        throw new RuntimeException('audit storage unavailable');
    },
    $fetchOne,
    $translator
))->build(['user_email' => 'operator@example.test']);
$assert($failure === $empty(), 'audit query failure preserves the established empty fallback');

$rendered = (new class {
    public function tr(string $key, string $fallback): string
    {
        return $fallback;
    }

    public function render(array $data): string
    {
        ob_start();
        include APP_ROOT . '/apps/Shell/Views/operator/recent.php';
        return (string)ob_get_clean();
    }
})->render(['username' => 'operator', 'recent_activity' => $result]);
$assert(str_contains($rendered, 'Localized Workflow') && str_contains($rendered, 'Approved note'), 'existing recent-activity view consumes the unchanged payload structure');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";
