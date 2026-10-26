<?php
declare(strict_types=1);

/** Database-free validation proof for operator reservation search filters. */
$root = dirname(__DIR__, 3);
require_once $root . '/apps/Hospitality/Services/ReservationsService.php';

$method = new ReflectionMethod('Apps\\Hospitality\\Services\\ReservationsService', 'normalizeOperatorSearchFilters');
$encode = new ReflectionMethod('Apps\\Hospitality\\Services\\ReservationsService', 'encodeOperatorSearchCursor');
$decode = new ReflectionMethod('Apps\\Hospitality\\Services\\ReservationsService', 'decodeOperatorSearchCursor');
$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$valid = $method->invoke(null, ['status' => 'BOOKED', 'from' => '2026-01-01', 'to' => '2026-01-01']);
$check($valid === ['status' => 'booked', 'from' => '2026-01-01', 'to' => '2026-01-01'], 'status normalization and inclusive equal date boundary');
$cursor = $encode->invoke(null, '2026-01-01', '50', $valid);
$decoded = $decode->invoke(null, $cursor, $valid);
$check(is_string($cursor) && ($decoded['date'] ?? '') === '2026-01-01' && ($decoded['id'] ?? 0) === 50, 'duplicate-arrival cursor preserves the final reservation ID');
try {
    $decode->invoke(null, $cursor, ['status' => 'booked', 'from' => '2026-01-02', 'to' => '2026-01-02']);
    $check(false, 'changed filters invalidate the cursor');
} catch (Throwable) {
    $check(true, 'changed filters invalidate the cursor');
}

foreach ([
    [['status' => 'unknown'], 'unknown status'],
    [['from' => '2026-02-30'], 'invalid from date'],
    [['to' => 'not-a-date'], 'invalid to date'],
    [['from' => '2026-02-01', 'to' => '2026-01-31'], 'reversed date range'],
    [['status' => ['booked']], 'array status'],
    [['from' => ['2026-01-01']], 'array date'],
] as [$filters, $label]) {
    try {
        $method->invoke(null, $filters);
        $check(false, $label . ' rejected');
    } catch (Throwable) {
        $check(true, $label . ' rejected');
    }
}

foreach (['malformed', '%%%%', str_repeat('a', 257)] as $badCursor) {
    try {
        $decode->invoke(null, $badCursor, $valid);
        $check(false, 'malformed cursor rejected');
    } catch (Throwable) {
        $check(true, 'malformed cursor rejected');
    }
}

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
