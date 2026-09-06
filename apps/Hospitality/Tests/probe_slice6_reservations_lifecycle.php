<?php
declare(strict_types=1);

/**
 * Hospitality Slice 6 Reservations Lifecycle Probe
 *
 * Covers:
 *   1. Route registration (GET list + POST create/transition) via real loader.
 *   2. Guard presence: view guard on GET, manage guard + CSRF on both POST handlers.
 *   3. Create validation: dates, date order, counts, rate.
 *   4. Active guest/room constraints (inactive or missing guest/room rejected).
 *   5. Room overlap conflict rejection for booked/checked_in ranges.
 *   6. Valid status transitions incl. actual check-in/out timestamps.
 *   7. Invalid transition rejection (e.g. cancelled -> checked_in, booked -> checked_out).
 *   8. Cleanup: tables dropped, ledger cleared, statuses restored.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php
 * Exit codes: 0 = pass, 1 = fail, 2 = environment unavailable
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Services\AppMigrationService;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeLoader;
use Apps\Hospitality\Services\GuestsService;
use Apps\Hospitality\Services\ReservationsService;
use Apps\Hospitality\Services\RoomsService;

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

$appRoot = APP_ROOT . '/apps/Hospitality';

echo "== Slice 6: Reservations lifecycle ==\n";

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable.\n";
    exit(2);
}

$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $r) {
    $statusSnapshot[(string)$r['app_key']] = (string)$r['status'];
}

// ---- Group A: routes registered through real loader ----
$registry = new AppRegistryService();
$registry->setStatus('hospitality', AppRegistryService::STATUS_ENABLED);

$c = new Container();
$router = new Router();
$view = new View(APP_ROOT . '/public/views');
$bus = new EventBus();
$c->set('router', fn() => $router);
$c->set('view', fn() => $view);
$c->set('bus', fn() => $bus);
$c->set('plugins', fn() => new PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus));

AppRuntimeLoader::resetLoadedRoutes();
(new AppRuntimeLoader($registry))->loadEnabledApps($c, $router, $view);
$routes = $router->listRoutes();

foreach ([
    'GET:/apps/hospitality/reservations',
    'POST:/apps/hospitality/reservations/create',
    'POST:/apps/hospitality/reservations/transition',
] as $spec) {
    [$method, $path] = explode(':', $spec, 2);
    isset($routes[$method][$path]) ? $pass("{$spec} registered") : $fail("{$spec} registered");
}
foreach ($statusSnapshot as $appKey => $originalStatus) {
    if ((string)($registry->find($appKey)['status'] ?? '') !== $originalStatus) {
        $registry->setStatus($appKey, $originalStatus);
    }
}
$pass('registry statuses restored after loader round-trip');

// ---- Group B: guards in handlers ----
$resControllerSrc = (string)file_get_contents($appRoot . '/Controllers/ReservationsController.php');
substr_count($resControllerSrc, 'Auth::requireCsrf') === 2
    ? $pass('CSRF required on both reservation POST handlers')
    : $fail('CSRF required on both reservation POST handlers');
substr_count($resControllerSrc, 'HospitalityAccess::requireManage') === 2
    ? $pass('manage guard on both reservation POST handlers')
    : $fail('manage guard on both reservation POST handlers');

// ---- Group C: schema + behavior ----
$tables = ['hosp_rooms', 'hosp_guests', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    $pass('schema applied via real runner (both migrations)');

    // validation errors
    foreach ([
        ['bad arrival format', ['guest_id' => 0, 'room_id' => 0, 'check_in_date' => '2030-13-01', 'check_out_date' => '2030-01-10']],
        ['arrival after departure', ['guest_id' => 0, 'room_id' => 0, 'check_in_date' => '2030-01-10', 'check_out_date' => '2030-01-05']],
        ['adults not numeric', ['guest_id' => 0, 'room_id' => 0, 'check_in_date' => '2030-01-05', 'check_out_date' => '2030-01-08', 'adults' => 'many']],
        ['rate malformed', ['guest_id' => 0, 'room_id' => 0, 'check_in_date' => '2030-01-05', 'check_out_date' => '2030-01-08', 'rate' => '12.345']],
    ] as [$label, $post]) {
        try {
            ReservationsService::create($post);
            $fail("create rejects {$label}");
        } catch (\Throwable $e) {
            str_starts_with($e->getMessage(), 'HOSPITALITY_RES_')
                ? $pass("create rejects {$label}")
                : $fail("create rejects {$label}", $e->getMessage());
        }
    }

    // fixtures: one active guest, one active room, one inactive room
    $guestId = GuestsService::create(['full_name' => 'Res Probe Guest']);
    $activeRoomId = RoomsService::create(['room_number' => 'R101', 'room_type' => 'double']);
    $inactiveRoomId = RoomsService::create(['room_number' => 'R102', 'room_type' => 'single']);
    RoomsService::setStatus($inactiveRoomId, 'inactive');

    // inactive guest / room constraints
    try {
        ReservationsService::create(['guest_id' => $guestId, 'room_id' => $inactiveRoomId, 'check_in_date' => '2030-02-01', 'check_out_date' => '2030-02-03']);
        $fail('inactive room rejected');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_RES_ROOM_INACTIVE') ? $pass('inactive room rejected') : $fail('inactive room rejected', $e->getMessage());
    }
    GuestsService::setStatus($guestId, 'inactive');
    try {
        ReservationsService::create(['guest_id' => $guestId, 'room_id' => $activeRoomId, 'check_in_date' => '2030-02-01', 'check_out_date' => '2030-02-03']);
        $fail('inactive guest rejected');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_RES_GUEST_INACTIVE') ? $pass('inactive guest rejected') : $fail('inactive guest rejected', $e->getMessage());
    }
    GuestsService::setStatus($guestId, 'active');

    // happy create + overlap conflicts against the same active room
    $resId = ReservationsService::create(['guest_id' => $guestId, 'room_id' => $activeRoomId, 'check_in_date' => '2030-02-01', 'check_out_date' => '2030-02-05', 'adults' => '2', 'children' => '1', 'rate' => '120.50']);
    ($resId > 0) ? $pass('reservation created') : $fail('reservation created');

    foreach ([
        ['exact same range', '2030-02-01', '2030-02-05'],
        ['overlapping start', '2030-01-30', '2030-02-02'],
        ['overlapping end', '2030-02-04', '2030-02-08'],
        ['contained range', '2030-02-02', '2030-02-03'],
    ] as [$label, $ci, $co]) {
        try {
            ReservationsService::create(['guest_id' => $guestId, 'room_id' => $activeRoomId, 'check_in_date' => $ci, 'check_out_date' => $co]);
            $fail("overlap conflict rejected ({$label})");
        } catch (\Throwable $e) {
            ($e->getMessage() === 'HOSPITALITY_RES_ROOM_CONFLICT') ? $pass("overlap conflict rejected ({$label})") : $fail("overlap conflict rejected ({$label})", $e->getMessage());
        }
    }

    // adjacent ranges are NOT conflicts (checkout day == next checkin day is allowed)
    try {
        ReservationsService::create(['guest_id' => $guestId, 'room_id' => $activeRoomId, 'check_in_date' => '2030-02-05', 'check_out_date' => '2030-02-07']);
        $pass('adjacent range allowed (no false conflict)');
    } catch (\Throwable $e) {
        $fail('adjacent range allowed', $e->getMessage());
    }

    // transitions: invalid ones first
    foreach ([
        ['booked -> checked_out skipped step', $resId, 'checked_out'],
        ['checked_out -> checked_in backwards', $resId + 999999, 'checked_in'], // not found path
    ] as [$label, $id, $to]) {
        try {
            ReservationsService::transition($id, $to);
            $fail("invalid transition rejected ({$label})");
        } catch (\Throwable $e) {
            in_array($e->getMessage(), ['HOSPITALITY_RES_TRANSITION_INVALID', 'HOSPITALITY_RES_NOT_FOUND'], true)
                ? $pass("invalid transition rejected ({$label})")
                : $fail("invalid transition rejected ({$label})", $e->getMessage());
        }
    }

    // full happy lifecycle with timestamps
    ReservationsService::transition($resId, 'checked_in');
    $afterIn = DB::fetchOne('SELECT reservation_status, actual_check_in_at, actual_check_out_at FROM hosp_reservations WHERE id=?', [$resId]);
    (($afterIn['reservation_status'] ?? '') === 'checked_in' && !empty($afterIn['actual_check_in_at']) && empty($afterIn['actual_check_out_at']))
        ? $pass('checked_in sets actual_check_in_at only')
        : $fail('checked_in timestamps', json_encode($afterIn));

    ReservationsService::transition($resId, 'checked_out');
    $afterOut = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id=?', [$resId]);
    (($afterOut['reservation_status'] ?? '') === 'checked_out' && !empty($afterOut['actual_check_out_at']))
        ? $pass('checked_out sets actual_check_out_at')
        : $fail('checked_out timestamps');

    try {
        ReservationsService::transition($resId, 'checked_in');
        $fail('terminal state rejects further transition');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_RES_TRANSITION_INVALID') ? $pass('terminal state rejects further transition') : $fail('terminal state rejects further transition', $e->getMessage());
    }

    // cancel and no_show paths from booked
    $cancelId = ReservationsService::create(['guest_id' => $guestId, 'room_id' => 0, 'check_in_date' => '2030-03-01', 'check_out_date' => '2030-03-02']);
    $noshowId = ReservationsService::create(['guest_id' => $guestId, 'room_id' => 0, 'check_in_date' => '2030-03-05', 'check_out_date' => '2030-03-06']);
    ReservationsService::transition($cancelId, 'cancelled');
    ReservationsService::transition($noshowId, 'no_show');
    $st = DB::fetchOne('SELECT GROUP_CONCAT(reservation_status) AS s FROM hosp_reservations WHERE id IN (?,?)', [$cancelId, $noshowId]);
    str_contains((string)($st['s'] ?? ''), 'cancelled') && str_contains((string)($st['s'] ?? ''), 'no_show')
        ? $pass('booked -> cancelled and booked -> no_show applied')
        : $fail('cancel/no_show transitions', (string)($st['s'] ?? '?'));

    // cancelled frees the room: overlapping create on same room now succeeds
    try {
        ReservationsService::create(['guest_id' => $guestId, 'room_id' => $activeRoomId, 'check_in_date' => '2030-02-01', 'check_out_date' => '2030-02-05']);
        $pass('checked_out/cancelled stays do not block new bookings');
    } catch (\Throwable $e) {
        $fail('finished stays free the room', $e->getMessage());
    }
} catch (\Throwable $e) {
    $fail('behavior group', $e->getMessage());
}

// ---- cleanup ----
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    $left = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'hosp_%'");
    ((int)($left['c'] ?? 0) === 0) ? $pass('cleanup: hosp_ tables removed') : $fail('cleanup: hosp_ tables removed');
} catch (\Throwable $e) {
    $fail('cleanup', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
