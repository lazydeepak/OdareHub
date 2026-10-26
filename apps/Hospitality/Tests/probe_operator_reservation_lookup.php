<?php
declare(strict_types=1);

/** Database-free proof for persistent, exact-reference operator lookup. */
namespace Apps\Hospitality\Services {
    final class ReservationsService
    {
        public static int $calls = 0;
        public static bool $throw = false;
        public static ?array $row = null;

        public static function findById(int $id): ?array
        {
            self::$calls++;
            if (self::$throw) {
                throw new \RuntimeException('SCHEMA_PENDING');
            }
            return $id === 42 ? self::$row : null;
        }
    }
}

namespace {
    $root = dirname(__DIR__, 3);
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', $root);
    }
    require_once $root . '/apps/Hospitality/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php';
    require_once $root . '/apps/Hospitality/Services/OperatorContributionService.php';

    use Apps\Hospitality\Services\OperatorLayerAdapters\HospitalityBoardAdapter;
    use Apps\Hospitality\Services\OperatorContributionService;
    use Apps\Hospitality\Services\ReservationsService;

    $passed = 0;
    $failed = 0;
    $check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
        echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
        $condition ? $passed++ : $failed++;
    };

    ReservationsService::$row = [
        'id' => 42,
        'guest_label' => 'Past Guest',
        'room_label' => '204',
        'check_in_date' => '2026-09-01',
        'check_out_date' => '2026-09-03',
        'reservation_status' => 'booked',
    ];

    $found = HospitalityBoardAdapter::lookupReservation('42');
    $check(($found['valid'] ?? false) === true && ($found['reservation']['id'] ?? 0) === 42, 'valid exact reference resolves through existing reservation service');
    $check(($found['reservation']['check_in_date'] ?? '') === '2026-09-01' && ($found['reservation']['guest_label'] ?? '') === 'Past Guest', 'past-date reservation details remain retrievable');
    $check(ReservationsService::$calls === 1, 'valid lookup performs one bounded owner read');

    $missing = HospitalityBoardAdapter::lookupReservation('43');
    $check(($missing['valid'] ?? false) === true && ($missing['reservation'] ?? null) === null, 'missing exact reference returns no disclosure');

    foreach (['', ' 42 ', '0', '-1', '1e2', '42abc', '42.0', str_repeat('9', 100)] as $invalidReference) {
        $before = ReservationsService::$calls;
        $result = HospitalityBoardAdapter::lookupReservation($invalidReference);
        $check(($result['valid'] ?? true) === false && ReservationsService::$calls === $before, 'malformed reference fails before service read: ' . ($invalidReference === '' ? 'empty' : substr($invalidReference, 0, 12)));
    }

    ReservationsService::$throw = true;
    $unavailable = HospitalityBoardAdapter::lookupReservation('42');
    $check(($unavailable['valid'] ?? false) === true && ($unavailable['available'] ?? true) === false && ($unavailable['reservation'] ?? null) === null, 'service/schema failure fails closed');

    $view = (string)file_get_contents($root . '/apps/Hospitality/Views/operator/hospitality.php');
    $adapter = (string)file_get_contents($root . '/apps/Hospitality/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php');
    $check(str_contains($view, '$currentQuery') && str_contains($view, 'reservation_ref'), 'lookup uses existing operator query context');
    $permissionPos = strpos($view, "AclPolicy::can('hospitality.view'");
    $lookupPos = strpos($view, 'HospitalityBoardAdapter::lookupReservation');
    $check($permissionPos !== false && $lookupPos !== false && $permissionPos < $lookupPos, 'view permission gate precedes lookup branch');
    $check(OperatorContributionService::contribute([
        'surface' => 'operator',
        'region' => 'focus_views',
        'context' => ['active_assigned_apps' => ['other_app']],
    ]) === [], 'unassigned operator receives no Hospitality focus surface');
    $assigned = OperatorContributionService::contribute([
        'surface' => 'operator',
        'region' => 'focus_views',
        'context' => ['active_assigned_apps' => ['hospitality']],
    ]);
    $check(isset($assigned['view_map']['hospitality']), 'assigned operator receives the existing Hospitality focus surface');
    $check(!str_contains($adapter, 'created_by') && !str_contains($adapter, 'Auth::user'), 'lookup adds no creator or actor ownership inference');
    $check(str_contains($view, 'method="get"') && str_contains($view, 'name="reservation_ref"'), 'lookup is a read-only GET form');
    $check(str_contains($view, 'hospitality.field.guest') && str_contains($view, 'hospitality.field.room') && str_contains($view, 'hospitality.field.status'), 'identity, guest, room, and status are rendered with locale keys');
    $check(str_contains($view, 'hospitality.field.check_in_date') && str_contains($view, 'hospitality.field.check_out_date'), 'planned stay dates are rendered with locale keys');
    $check(str_contains($adapter, 'ReservationsService::findById((int)$reference)'), 'lookup reuses the canonical owner read');
    $check(str_contains($adapter, "preg_match('/^[1-9][0-9]*$/D'"), 'lookup accepts only canonical positive decimal references');
    $check(!str_contains($view, 'reservation_ref\'] ?? $_POST'), 'lookup does not read a POST selector');

    foreach (['en', 'ja', 'ne'] as $locale) {
        $catalog = include $root . '/apps/Hospitality/Resources/lang/' . $locale . '.php';
        foreach (['hospitality.operator.reservation.lookup_title', 'hospitality.operator.reservation.lookup_note', 'hospitality.operator.reservation.lookup_invalid', 'hospitality.operator.reservation.lookup_not_found'] as $key) {
            $check(array_key_exists($key, (array)$catalog), $locale . ' locale key: ' . $key);
        }
    }

    echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
    exit($failed === 0 ? 0 : 1);
}
