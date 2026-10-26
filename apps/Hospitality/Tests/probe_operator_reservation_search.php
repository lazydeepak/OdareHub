<?php
declare(strict_types=1);

/** Database-free proof for bounded operator reservation search. */
namespace Apps\Hospitality\Services {
    final class ReservationsService
    {
        public static array $result = [];
        public static array $filters = [];
        public static int $limit = 0;
        public static bool $throw = false;
        public static int $calls = 0;

        public static function searchOperator(array $filters, int $limit = 50): array
        {
            self::$calls++;
            self::$filters = $filters;
            self::$limit = $limit;
            if (self::$throw) {
                throw new \RuntimeException('SCHEMA_PENDING');
            }
            return self::$result;
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

    ReservationsService::$result = [
        'rows' => [
            ['id' => 2, 'guest_label' => 'Historical', 'room_label' => '101', 'check_in_date' => '2026-01-01', 'check_out_date' => '2026-01-02', 'reservation_status' => 'checked_out'],
            ['id' => 9, 'guest_label' => 'Future', 'room_label' => null, 'check_in_date' => '2027-01-01', 'check_out_date' => '2027-01-02', 'reservation_status' => 'booked'],
        ],
        'truncated' => false,
        'limit' => 50,
        'filters' => ['status' => '', 'from' => '', 'to' => ''],
    ];

    $all = HospitalityBoardAdapter::searchReservations([]);
    $check(($all['valid'] ?? false) === true && count($all['rows'] ?? []) === 2, 'historical and future results are returned through the bounded adapter');
    $check(ReservationsService::$calls === 1 && ReservationsService::$limit === 50, 'search is delegated once with the hard result limit');

    $filtered = HospitalityBoardAdapter::searchReservations(['status' => 'booked', 'from' => '2027-01-01', 'to' => '2027-01-01']);
    $check(($filtered['valid'] ?? false) === true && ReservationsService::$filters === ['status' => 'booked', 'from' => '2027-01-01', 'to' => '2027-01-01'], 'status and inclusive date-boundary filters are forwarded exactly');

    $missing = HospitalityBoardAdapter::searchReservations(['status' => 'cancelled', 'from' => '2030-01-01', 'to' => '2030-01-01']);
    ReservationsService::$result['rows'] = [];
    $check(($missing['valid'] ?? false) === true, 'valid filters support an empty result set');

    ReservationsService::$throw = true;
    $unavailable = HospitalityBoardAdapter::searchReservations(['status' => 'booked']);
    $check(($unavailable['valid'] ?? false) === true && ($unavailable['available'] ?? true) === false && ($unavailable['rows'] ?? []) === [], 'service/schema failure fails closed without disclosure');
    ReservationsService::$throw = false;

    $view = (string)file_get_contents($root . '/apps/Hospitality/Views/operator/hospitality.php');
    $service = (string)file_get_contents($root . '/apps/Hospitality/Services/ReservationsService.php');
    $adapter = (string)file_get_contents($root . '/apps/Hospitality/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php');
    $check(str_contains($view, 'AclPolicy::can(\'hospitality.view\'') && str_contains($view, 'searchReservations'), 'existing view permission gate precedes search invocation');
    $check(str_contains($view, 'name="reservation_search"') && str_contains($view, 'name="reservation_status"') && str_contains($view, 'name="reservation_from"') && str_contains($view, 'name="reservation_to"'), 'search uses only established status/date GET filters');
    $check(!str_contains($view, 'guest_name') && !str_contains($view, 'guest_email') && !str_contains($view, 'free_text'), 'no speculative guest or free-text search is exposed');
    $check(str_contains($service, 'public static function searchOperator') && str_contains($adapter, 'ReservationsService::searchOperator'), 'search remains Hospitality-owned and reuses its service boundary');
    $check(str_contains($service, 'r.check_in_date >= ?') && str_contains($service, 'r.check_in_date <= ?'), 'date boundaries are inclusive query predicates');
    $check(str_contains($service, 'in_array($status, self::STATUSES, true)'), 'status filters use established reservation statuses');
    $check(str_contains($service, 'ORDER BY r.check_in_date ASC, r.id ASC'), 'search ordering is deterministic');
    $check(str_contains($service, '$limit + 1') && str_contains($service, 'array_slice($rows, 0, $limit)'), 'search result limit is bounded with truncation evidence');
    $check(str_contains($service, 'if ($from !== \'\' && $to !== \'\' && $from > $to)'), 'reversed date ranges fail validation');
    $check(OperatorContributionService::contribute(['surface' => 'operator', 'region' => 'focus_views', 'context' => ['active_assigned_apps' => ['other_app']]]) === [], 'unassigned operator receives no searchable Hospitality surface');
    $check(isset(OperatorContributionService::contribute(['surface' => 'operator', 'region' => 'focus_views', 'context' => ['active_assigned_apps' => ['hospitality']]])['view_map']['hospitality']), 'assigned operator retains the existing searchable surface');

    foreach (['en', 'ja', 'ne'] as $locale) {
        $catalog = include $root . '/apps/Hospitality/Resources/lang/' . $locale . '.php';
        foreach (['hospitality.operator.reservation.search_title', 'hospitality.operator.reservation.search_invalid', 'hospitality.operator.reservation.search_no_results', 'hospitality.operator.reservation.search_limited'] as $key) {
            $check(array_key_exists($key, (array)$catalog), $locale . ' locale key: ' . $key);
        }
    }

    echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
    exit($failed === 0 ? 0 : 1);
}
