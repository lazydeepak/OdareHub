<?php
declare(strict_types=1);

/**
 * Database-free proof for the exact post-create Hospitality continuation seam.
 * The adapter may read only the server-retained reservation identity; it does
 * not accept a browser selector or create a new route/authority.
 */

namespace Apps\Hospitality\Services {
    final class ReservationsService
    {
        public static ?array $row = null;
        public static bool $throw = false;
        public static int $calls = 0;

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
    require_once $root . '/apps/Hospitality/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php';

    use Apps\Hospitality\Services\OperatorLayerAdapters\HospitalityBoardAdapter;
    use Apps\Hospitality\Services\ReservationsService;

    $passed = 0;
    $failed = 0;
    $pass = static function (string $label) use (&$passed): void {
        $passed++;
        echo "  PASS [{$label}]\n";
    };
    $fail = static function (string $label, string $detail = '') use (&$failed): void {
        $failed++;
        echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
    };

    $row = [
        'id' => 42,
        'guest_label' => 'Created Guest',
        'room_label' => null,
        'check_in_date' => '2026-10-01',
        'check_out_date' => '2026-10-03',
        'reservation_status' => 'booked',
    ];
    ReservationsService::$row = $row;

    $resolved = HospitalityBoardAdapter::createdReservation(42);
    $resolved === $row
        ? $pass('exact session identity resolves through owner service')
        : $fail('exact session identity resolution', json_encode($resolved));

    HospitalityBoardAdapter::createdReservation(0) === null && ReservationsService::$calls === 1
        ? $pass('non-positive identity fails closed without a read')
        : $fail('non-positive identity boundary');

    HospitalityBoardAdapter::createdReservation(43) === null
        ? $pass('unknown identity returns no continuation card')
        : $fail('unknown identity boundary');

    ReservationsService::$throw = true;
    HospitalityBoardAdapter::createdReservation(42) === null
        ? $pass('unavailable owner read fails closed')
        : $fail('owner read failure boundary');

    $view = (string)file_get_contents($root . '/apps/Hospitality/Views/operator/hospitality.php');
    $requiredViewMarkers = [
        'createdReservation',
        'hospitality.operator.reservation.created_title',
        'hospitality.operator.reservation.created_note',
        '/front-desk/check-in',
        '/front-desk/cancel',
        '/front-desk/no-show',
    ];
    foreach ($requiredViewMarkers as $marker) {
        str_contains($view, $marker)
            ? $pass("continuation view marker: {$marker}")
            : $fail("continuation view marker: {$marker}");
    }

    $service = (string)file_get_contents($root . '/apps/Hospitality/Services/ReservationsService.php');
    str_contains($service, 'public static function findById(int $id): ?array')
        ? $pass('owner service exact-read method exists')
        : $fail('owner service exact-read method');
    str_contains($service, 'WHERE r.id = ?')
        ? $pass('exact identity predicate is present')
        : $fail('exact identity predicate');

    $locales = ['en', 'ja', 'ne'];
    foreach ($locales as $locale) {
        $catalog = (string)file_get_contents($root . '/apps/Hospitality/Resources/lang/' . $locale . '.php');
        foreach (['hospitality.field.reservation_id', 'hospitality.operator.reservation.created_title', 'hospitality.operator.reservation.created_note'] as $key) {
            str_contains($catalog, "'{$key}'")
                ? $pass("{$locale} locale key: {$key}")
                : $fail("{$locale} locale key: {$key}");
        }
    }

    echo "RESULT: {$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}
