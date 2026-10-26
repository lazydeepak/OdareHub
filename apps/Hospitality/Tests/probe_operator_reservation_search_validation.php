<?php
declare(strict_types=1);

/** Database-free validation proof for operator reservation search filters. */
$root = dirname(__DIR__, 3);
require_once $root . '/apps/Hospitality/Services/ReservationsService.php';

$method = new ReflectionMethod('Apps\\Hospitality\\Services\\ReservationsService', 'normalizeOperatorSearchFilters');
$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$valid = $method->invoke(null, ['status' => 'BOOKED', 'from' => '2026-01-01', 'to' => '2026-01-01']);
$check($valid === ['status' => 'booked', 'from' => '2026-01-01', 'to' => '2026-01-01'], 'status normalization and inclusive equal date boundary');

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

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
