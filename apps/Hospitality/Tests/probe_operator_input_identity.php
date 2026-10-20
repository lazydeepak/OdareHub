<?php
declare(strict_types=1);

/**
 * Offline Hospitality operator input-identity probe.
 *
 * Verifies that operator action IDs are normalized before service dispatch,
 * without loading application bootstrap or opening a database connection.
 */

$root = dirname(__DIR__, 3);
$handlerPath = $root . '/apps/Hospitality/Controllers/OperatorActions.php';
$source = (string)file_get_contents($handlerPath);

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "  PASS [{$label}]\n";
};
$fail = static function (string $label) use (&$failed): void {
    $failed++;
    echo "  FAIL [{$label}]\n";
};

echo "== Hospitality operator input identity ==\n";

foreach ([
    'room_id' => 'HousekeepingService::updateStatus(self::normalizePositiveId($_POST[\'room_id\'] ?? null), $_POST)',
    'reservation_id' => 'FrontDeskService::addChargeForReservation(self::normalizePositiveId($_POST[\'reservation_id\'] ?? null), $_POST)',
    'no_show_id' => 'FrontDeskService::markReservationNoShow(self::normalizePositiveId($_POST[\'reservation_id\'] ?? null))',
    'charge_id' => 'FrontDeskService::voidCharge(self::normalizePositiveId($_POST[\'charge_id\'] ?? null))',
    'cancel_id' => 'FrontDeskService::cancelReservation(self::normalizePositiveId($_POST[\'reservation_id\'] ?? null))',
    'checkout_id' => 'FrontDeskService::checkout(self::normalizePositiveId($_POST[\'reservation_id\'] ?? null))',
    'checkin_id' => 'FrontDeskService::checkIn(self::normalizePositiveId($_POST[\'reservation_id\'] ?? null))',
    'reservation_guest_id' => "\$payload['guest_id'] = self::normalizePositiveId(\$_POST['guest_id'] ?? null)",
    'reservation_room_id' => "\$payload['room_id'] = self::normalizeOptionalPositiveId(\$_POST['room_id'] ?? null)",
] as $label => $needle) {
    str_contains($source, $needle) ? $pass("{$label} normalized before dispatch") : $fail("{$label} normalized before dispatch");
}

str_contains($source, 'private static function normalizePositiveId(mixed $value): int')
    ? $pass('normalizer is private and typed') : $fail('normalizer declaration');
str_contains($source, "preg_match('/^[1-9][0-9]*$/D', \$value)")
    ? $pass('only canonical positive decimal strings accepted') : $fail('decimal validation');
str_contains($source, "'HOSPITALITY_ID_INVALID'")
    ? $pass('invalid identity fails closed') : $fail('invalid identity failure');

require_once $handlerPath;
$method = new ReflectionMethod('Apps\\Hospitality\\Controllers\\OperatorActions', 'normalizePositiveId');
$optionalMethod = new ReflectionMethod('Apps\\Hospitality\\Controllers\\OperatorActions', 'normalizeOptionalPositiveId');

$cases = [
    ['7', 7, true, 'canonical string'],
    [7, 7, true, 'positive integer'],
    ['0', null, false, 'zero rejected'],
    ['-1', null, false, 'negative rejected'],
    ['7abc', null, false, 'suffix rejected'],
    ['1e2', null, false, 'scientific notation rejected'],
    [(string)PHP_INT_MAX . '0', null, false, 'overflow rejected'],
    [[], null, false, 'array rejected'],
];

foreach ($cases as [$input, $expected, $shouldPass, $label]) {
    try {
        $actual = $method->invoke(null, $input);
        ($shouldPass && $actual === $expected)
            ? $pass($label)
            : $fail($label);
    } catch (Throwable) {
        !$shouldPass ? $pass($label) : $fail($label);
    }
}

foreach ([
    ['', 0, true, 'empty optional room preserved as unassigned'],
    ['0', 0, true, 'zero optional room preserved as unassigned'],
    ['7', 7, true, 'assigned optional room remains exact'],
    ['7abc', null, false, 'malformed optional room rejected'],
] as [$input, $expected, $shouldPass, $label]) {
    try {
        $actual = $optionalMethod->invoke(null, $input);
        ($shouldPass && $actual === $expected)
            ? $pass($label)
            : $fail($label);
    } catch (Throwable) {
        !$shouldPass ? $pass($label) : $fail($label);
    }
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
