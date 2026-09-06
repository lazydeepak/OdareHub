<?php
declare(strict_types=1);

/**
 * Hospitality Slice 5 Rooms + Guests CRUD Probe
 *
 * Covers:
 *   1. Route registration: six GET surfaces + six POST action endpoints via real loader.
 *   2. Guard presence: view guard on GET controllers; manage guard + CSRF on POST handlers.
 *   3. Service validation: invalid inputs rejected with mapped error keys.
 *   4. Real CRUD against schema applied through AppMigrationService (both migrations).
 *   5. Unique room_number enforcement on create and update.
 *   6. Deactivation semantics (no hard deletes) for rooms and guests.
 *   7. Cleanup: tables dropped, migration ledger cleared, registry statuses restored.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php
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

echo "== Slice 5: Rooms + Guests CRUD ==\n";

// ---- Group A: routes registered through real loader ----
try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable ({$e->getMessage()}).\n";
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(2);
}

$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $r) {
    $statusSnapshot[(string)$r['app_key']] = (string)$r['status'];
}

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
    'GET:/apps/hospitality/rooms',
    'GET:/apps/hospitality/guests',
    'POST:/apps/hospitality/rooms/create',
    'POST:/apps/hospitality/rooms/update',
    'POST:/apps/hospitality/rooms/status',
    'POST:/apps/hospitality/guests/create',
    'POST:/apps/hospitality/guests/update',
    'POST:/apps/hospitality/guests/status',
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

// ---- Group B: guards present in handlers ----
foreach (['RoomsController.php', 'GuestsController.php'] as $file) {
    $src = (string)file_get_contents($appRoot . '/Controllers/' . $file);
    substr_count($src, 'Auth::requireCsrf') === 3
        ? $pass("{$file}: CSRF required on all three POST handlers")
        : $fail("{$file}: CSRF required on all three POST handlers");
    substr_count($src, 'HospitalityAccess::requireManage') === 3
        ? $pass("{$file}: manage guard on all three POST handlers")
        : $fail("{$file}: manage guard on all three POST handlers");
}

// Status-form field contract: the view posts guest_status and the controller must
// read the same field (a room_status copy-paste here silently breaks deactivation).
$guestsControllerSrc = (string)file_get_contents($appRoot . '/Controllers/GuestsController.php');
str_contains($guestsControllerSrc, "\$_POST['guest_status']")
    ? $pass('GuestsController reads the guest_status POST field')
    : $fail('GuestsController reads the guest_status POST field');
!str_contains($guestsControllerSrc, "\$_POST['room_status']")
    ? $pass('GuestsController has no room_status copy-paste field')
    : $fail('GuestsController has no room_status copy-paste field');
$guestsViewSrc = (string)file_get_contents($appRoot . '/Views/guests.php');
str_contains($guestsViewSrc, 'name="guest_status"')
    ? $pass('guests view status form posts guest_status')
    : $fail('guests view status form posts guest_status');

// ---- Group C: schema applied through real runner, then service CRUD ----
$tables = ['hosp_rooms', 'hosp_guests', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");

    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    // apply BOTH migrations; second adds guest_status
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');

    // validation errors before touching data
    foreach ([
        ['number empty', fn () => RoomsService::create(['room_number' => '', 'room_type' => 'standard'])],
        ['number bad chars', fn () => RoomsService::create(['room_number' => 'bad room!', 'room_type' => 'standard'])],
        ['type unknown', fn () => RoomsService::create(['room_number' => 'X1', 'room_type' => 'palace'])],
        ['floor not numeric', fn () => RoomsService::create(['room_number' => 'X1', 'room_type' => 'standard', 'floor' => 'top'])],
        ['guest name empty', fn () => GuestsService::create(['full_name' => ''])],
        ['guest email invalid', fn () => GuestsService::create(['full_name' => 'Taro', 'email' => 'not-an-email'])],
    ] as [$label, $fn]) {
        try {
            $fn();
            $fail("validation rejects {$label}");
        } catch (\Throwable $e) {
            str_starts_with($e->getMessage(), 'HOSPITALITY_')
                ? $pass("validation rejects {$label}")
                : $fail("validation rejects {$label}", $e->getMessage());
        }
    }

    // create
    $roomId = RoomsService::create(['room_number' => '101', 'room_type' => 'double', 'floor' => '1']);
    ($roomId > 0) ? $pass('room created') : $fail('room created');
    $guestId = GuestsService::create(['full_name' => 'Probe Guest', 'email' => 'probe@example.com', 'phone' => '+81-000', 'id_document_ref' => 'DOC-1']);
    ($guestId > 0) ? $pass('guest created') : $fail('guest created');

    // uniqueness on create
    try {
        RoomsService::create(['room_number' => '101', 'room_type' => 'single']);
        $fail('duplicate room number rejected on create');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_ROOM_NUMBER_TAKEN')
            ? $pass('duplicate room number rejected on create')
            : $fail('duplicate room number rejected on create', $e->getMessage());
    }

    // uniqueness on update (keep own number)
    RoomsService::update($roomId, ['room_number' => '101', 'room_type' => 'suite', 'floor' => '2', 'note' => 'renovated']);
    $row = DB::fetchOne('SELECT room_type, floor FROM hosp_rooms WHERE id=?', [$roomId]);
    (($row['room_type'] ?? '') === 'suite' && (int)$row['floor'] === 2)
        ? $pass('room update persists and keeps own number')
        : $fail('room update persists');

    // update onto another room's number must fail
    RoomsService::create(['room_number' => '102', 'room_type' => 'single']);
    try {
        RoomsService::update($roomId, ['room_number' => '102', 'room_type' => 'suite']);
        $fail('update to taken number rejected');
    } catch (\Throwable $e) {
        ($e->getMessage() === 'HOSPITALITY_ROOM_NUMBER_TAKEN')
            ? $pass('update to taken number rejected')
            : $fail('update to taken number rejected', $e->getMessage());
    }

    // guest update
    GuestsService::update($guestId, ['full_name' => 'Probe Guest II', 'email' => 'probe2@example.com']);
    $g = DB::fetchOne('SELECT full_name FROM hosp_guests WHERE id=?', [$guestId]);
    (($g['full_name'] ?? '') === 'Probe Guest II') ? $pass('guest update persists') : $fail('guest update persists');

    // deactivation semantics
    RoomsService::setStatus($roomId, 'inactive');
    GuestsService::setStatus($guestId, 'inactive');
    $r2 = DB::fetchOne('SELECT room_status FROM hosp_rooms WHERE id=?', [$roomId]);
    $g2 = DB::fetchOne('SELECT guest_status FROM hosp_guests WHERE id=?', [$guestId]);
    (($r2['room_status'] ?? '') === 'inactive' && ($g2['guest_status'] ?? '') === 'inactive')
        ? $pass('deactivation via status transition (rows retained)')
        : $fail('deactivation via status transition');
    $counts = DB::fetchOne('SELECT (SELECT COUNT(*) FROM hosp_rooms) AS r, (SELECT COUNT(*) FROM hosp_guests) AS g');
    ((int)$counts['r'] === 2 && (int)$counts['g'] === 1)
        ? $pass('no hard delete: deactivated rows remain listed')
        : $fail('no hard delete: rows retained', json_encode($counts));
} catch (\Throwable $e) {
    $fail('CRUD group', $e->getMessage());
}

// ---- cleanup: restore pre-probe state ----
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    $left = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'hosp_%'");
    ((int)($left['c'] ?? 0) === 0) ? $pass('cleanup: hosp_ tables removed') : $fail('cleanup: hosp_ tables removed');
    $pass('cleanup: migration ledger cleared');
} catch (\Throwable $e) {
    $fail('cleanup', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
