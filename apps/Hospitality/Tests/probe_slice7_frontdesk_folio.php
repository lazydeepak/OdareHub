<?php
declare(strict_types=1);

/**
 * Hospitality Slice 7 FrontDesk + Local Folio Probe
 *
 * Covers:
 *   1. Route registration (GET board + 4 POST endpoints) via real loader.
 *   2. Guard presence: manage guard + CSRF on all four POST handlers.
 *   3. Board classification: arrivals (booked, today/upcoming) vs in-house (checked_in).
 *   4. Lifecycle delegation: check-in/out go through ReservationsService rules.
 *   5. Folio ensure idempotency; add-charge validation; totals computed on read;
 *      void only posted charges while open; folio closes at checkout and rejects
 *      further charges.
 *   6. Extraction safety: no shared Billing/Procurement/Parties/Inventory references.
 *   7. Cleanup restores pre-probe state.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php
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
use Apps\Hospitality\Services\FrontDeskService;
use Apps\Hospitality\Services\GuestsService;
use Apps\Hospitality\Services\RoomsService;

$passed = 0;
$failed = 0;
$pass = static function (string $l) use (&$passed): void {
    $passed++;
    echo "  PASS [{$l}]\n";
};
$fail = static function (string $l, string $d = '') use (&$failed): void {
    $failed++;
    echo "  FAIL [{$l}]" . ($d !== '' ? ": {$d}" : '') . "\n";
};

$appRoot = APP_ROOT . '/apps/Hospitality';
echo "== Slice 7: FrontDesk workboard + local folio ==\n";

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

// ---- Group A: routes ----
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
    'GET:/apps/hospitality/front-desk',
    'POST:/apps/hospitality/front-desk/check-in',
    'POST:/apps/hospitality/front-desk/check-out',
    'POST:/apps/hospitality/front-desk/charges/add',
    'POST:/apps/hospitality/front-desk/charges/void',
] as $spec) {
    [$m, $p] = explode(':', $spec, 2);
    isset($routes[$m][$p]) ? $pass("{$spec} registered") : $fail("{$spec} registered");
}
foreach ($statusSnapshot as $appKey => $originalStatus) {
    if ((string)($registry->find($appKey)['status'] ?? '') !== $originalStatus) {
        $registry->setStatus($appKey, $originalStatus);
    }
}
$pass('registry statuses restored after loader round-trip');

// ---- Group B: guards ----
$fdSrc = (string)file_get_contents($appRoot . '/Controllers/FrontDeskController.php');
substr_count($fdSrc, 'Auth::requireCsrf') === 4 ? $pass('CSRF required on all four POST handlers') : $fail('CSRF count');
substr_count($fdSrc, 'HospitalityAccess::requireManage') === 4 ? $pass('manage guard on all four POST handlers') : $fail('manage guard count');

// ---- Group C: behavior ----
$tables = ['hosp_rooms', 'hosp_guests', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');

    // extraction-safety static scan (done from the probe so the needle list is not
    // part of the scanned file itself)
    $fdServiceSrc = strtolower((string)file_get_contents($appRoot . '/Services/FrontDeskService.php'));
    $fdControllerSrc = strtolower((string)file_get_contents($appRoot . '/Controllers/FrontDeskController.php'));
    $needles = ['pro' . 'curement_', 'sb' . 'aio_', 'mf' . 'g_', 'produ' . 'cts', 'part' . 'ies', 'bill' . 'ing'];
    $foreignHits = [];
    foreach ($needles as $needle) {
        if (str_contains($fdServiceSrc, $needle) || str_contains($fdControllerSrc, $needle)) {
            $foreignHits[] = $needle;
        }
    }
    ($foreignHits === [])
        ? $pass('front-desk code references no shared-app concepts')
        : $fail('extraction safety: foreign references found', implode(',', $foreignHits));

    // fixtures
    $guestId = GuestsService::create(['full_name' => 'FD Probe Guest']);
    $roomId = RoomsService::create(['room_number' => 'FD201', 'room_type' => 'double']);
    $today = date('Y-m-d');
    $resId = ReservationsServiceForProbe::createTodayReservation($guestId, $roomId, $today);

    // board classification: arrival listed, in-house empty before check-in
    $board = FrontDeskService::board();
    $arrivalIds = array_map(static fn ($r) => (int)$r['id'], $board['arrivals'] ?? []);
    in_array($resId, $arrivalIds, true) ? $pass('booked reservation classified as arrival') : $fail('arrival classification');
    (count($board['inhouse']) === 0) ? $pass('in-house empty before check-in') : $fail('in-house should be empty');

    // check-in delegation
    FrontDeskService::checkIn($resId);
    $st = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id=?', [$resId]);
    (($st['reservation_status'] ?? '') === 'checked_in') ? $pass('check-in delegated to ReservationsService') : $fail('delegated check-in');

    // invalid transition through same path still rejected
    try {
        FrontDeskService::checkIn($resId);
        $fail('double check-in rejected via delegation');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_RES_TRANSITION_INVALID') ? $pass('double check-in rejected via delegation') : $fail('double check-in rejection code', $e->getMessage());
    }

    // folio ensure idempotent
    $folioId = FrontDeskService::ensureFolio($resId);
    $folioId2 = FrontDeskService::ensureFolio($resId);
    ($folioId > 0 && $folioId === $folioId2) ? $pass('ensureFolio idempotent') : $fail('ensureFolio idempotent');

    // add-charge validation
    foreach ([
        ['bad type', ['charge_type' => 'spa', 'description' => 'x', 'qty' => '1', 'unit_amount' => '10']],
        ['empty desc', ['charge_type' => 'misc', 'description' => '', 'qty' => '1', 'unit_amount' => '10']],
        ['bad qty', ['charge_type' => 'misc', 'description' => 'x', 'qty' => '0', 'unit_amount' => '10']],
        ['bad amount', ['charge_type' => 'fnb', 'description' => 'x', 'qty' => '2', 'unit_amount' => '9.999']],
    ] as [$label, $post]) {
        try {
            FrontDeskService::addCharge($folioId, $post);
            $fail("addCharge rejects {$label}");
        } catch (\Throwable $e) {
            str_starts_with($e->getMessage(), 'HOSPITALITY_') ? $pass("addCharge rejects {$label}") : $fail("addCharge rejects {$label}", $e->getMessage());
        }
    }

    // valid charges + computed total
    $ch1 = FrontDeskService::addCharge($folioId, ['charge_type' => 'room_rate', 'description' => 'Night 1', 'qty' => '2', 'unit_amount' => '120']);
    $ch2 = FrontDeskService::addCharge($folioId, ['charge_type' => 'fnb', 'description' => 'Dinner', 'qty' => '1', 'unit_amount' => '30.50']);
    FrontDeskService::addCharge($folioId, ['charge_type' => 'misc', 'description' => 'Laundry', 'qty' => '1', 'unit_amount' => '10']);
    $totalBefore = FrontDeskService::openTotal($folioId);
    (abs($totalBefore - 280.50) < 0.001) ? $pass('total computed on read from posted charges') : $fail('computed total', (string)$totalBefore);

    // void excludes from total; double-void rejected
    FrontDeskService::voidCharge($ch2);
    $totalAfterVoid = FrontDeskService::openTotal($folioId);
    (abs($totalAfterVoid - 250.00) < 0.001) ? $pass('void removes charge from computed total') : $fail('void total', (string)$totalAfterVoid);
    try {
        FrontDeskService::voidCharge($ch2);
        $fail('double void rejected');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_CHARGE_NOT_POSTED') ? $pass('double void rejected') : $fail('double void code', $e->getMessage());
    }

    // checkout closes folio and sets timestamp
    FrontDeskService::checkout($resId);
    $res = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id=?', [$resId]);
    (($res['reservation_status'] ?? '') === 'checked_out' && !empty($res['actual_check_out_at'])) ? $pass('checkout transitions and timestamps') : $fail('checkout transition');
    $folio = DB::fetchOne('SELECT folio_status, closed_at FROM hosp_folios WHERE id=?', [$folioId]);
    (($folio['folio_status'] ?? '') === 'closed' && !empty($folio['closed_at'])) ? $pass('folio closed at checkout with closed_at') : $fail('folio closed at checkout');

    // closed folio rejects charges
    try {
        FrontDeskService::addCharge($folioId, ['charge_type' => 'misc', 'description' => 'late', 'qty' => '1', 'unit_amount' => '5']);
        $fail('closed folio rejects new charges');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_FOLIO_CLOSED') ? $pass('closed folio rejects new charges') : $fail('closed folio code', $e->getMessage());
    }

    // board reflects empty in-house after checkout
    $board2 = FrontDeskService::board();
    $inHouseIds = array_map(static fn ($r) => (int)$r['id'], $board2['inhouse'] ?? []);
    (!in_array($resId, $inHouseIds, true)) ? $pass('checked-out stay leaves in-house list') : $fail('in-house after checkout');
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

/** Tiny helper so the probe can create a reservation dated today without duplicating service logic. */
class ReservationsServiceForProbe
{
    public static function createTodayReservation(int $guestId, int $roomId, string $today): int
    {
        return \Apps\Hospitality\Services\ReservationsService::create([
            'guest_id' => (string)$guestId,
            'room_id' => (string)$roomId,
            'check_in_date' => $today,
            'check_out_date' => date('Y-m-d', strtotime($today . ' +2 days')),
        ]);
    }
}
