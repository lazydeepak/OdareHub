<?php
declare(strict_types=1);

/**
 * Hospitality Slice 8 Housekeeping Board Probe
 *
 * Covers:
 *   1. GET/POST route registration through the real loader.
 *   2. Guard presence: manage guard + CSRF on the status POST handler.
 *   3. Board lists every active room and shows missing status as pending.
 *   4. Status update lazily creates the current-status row.
 *   5. Valid status updates, invalid status rejection, active-room constraint.
 *   6. No shared-app references in Housekeeping service/controller.
 *   7. Cleanup restores pre-probe state.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice8_housekeeping_board.php
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
use Apps\Hospitality\Services\HousekeepingService;
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
echo "== Slice 8: Housekeeping current-status board ==\n";

try {
    DB::conn();
} catch (\Throwable) {
    echo "SKIP: database unavailable.\n";
    exit(2);
}

$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $row) {
    $statusSnapshot[(string)$row['app_key']] = (string)$row['status'];
}

// ---- Group A: route registration through real loader ----
$registry = new AppRegistryService();
$registry->setStatus('hospitality', AppRegistryService::STATUS_ENABLED);
$container = new Container();
$router = new Router();
$view = new View(APP_ROOT . '/public/views');
$bus = new EventBus();
$container->set('router', fn() => $router);
$container->set('view', fn() => $view);
$container->set('bus', fn() => $bus);
$container->set('plugins', fn() => new PluginManager(APP_ROOT . '/plugins', $container, $router, $view, $bus));
AppRuntimeLoader::resetLoadedRoutes();
(new AppRuntimeLoader($registry))->loadEnabledApps($container, $router, $view);
$routes = $router->listRoutes();

foreach ([
    'GET:/apps/hospitality/housekeeping',
    'POST:/apps/hospitality/housekeeping/status',
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

// ---- Group B: guards and locality ----
$controllerSrc = (string)file_get_contents($appRoot . '/Controllers/HousekeepingController.php');
substr_count($controllerSrc, 'Auth::requireCsrf') === 1 ? $pass('CSRF required on status POST') : $fail('CSRF guard count');
substr_count($controllerSrc, 'HospitalityAccess::requireManage') === 1 ? $pass('manage guard on status POST') : $fail('manage guard count');

$serviceSrc = strtolower((string)file_get_contents($appRoot . '/Services/HousekeepingService.php'));
$controllerLower = strtolower($controllerSrc);
$needles = ['pro' . 'curement', 'invent' . 'ory', 'bill' . 'ing', 'part' . 'ies', 'sb' . 'aio', 'mf' . 'g'];
$hits = [];
foreach ($needles as $needle) {
    if (str_contains($serviceSrc, $needle) || str_contains($controllerLower, $needle)) {
        $hits[] = $needle;
    }
}
$hits === [] ? $pass('housekeeping code references no shared-app concepts') : $fail('foreign reference scan', implode(',', $hits));

// ---- Group C: behavior ----
$tables = ['hosp_rooms', 'hosp_guests', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];
try {
    foreach ($tables as $table) {
        DB::query("DROP TABLE IF EXISTS `{$table}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');

    $roomA = RoomsService::create(['room_number' => 'HK101', 'room_type' => 'single', 'floor' => '1']);
    $roomB = RoomsService::create(['room_number' => 'HK102', 'room_type' => 'double', 'floor' => '1']);
    $inactive = RoomsService::create(['room_number' => 'HK999', 'room_type' => 'standard']);
    RoomsService::setStatus($inactive, 'inactive');

    $board = HousekeepingService::board();
    $roomIds = array_map(static fn(array $row): int => (int)$row['room_id'], $board ?? []);
    (is_array($board) && count($board) === 2 && in_array($roomA, $roomIds, true) && in_array($roomB, $roomIds, true) && !in_array($inactive, $roomIds, true))
        ? $pass('board lists active rooms only')
        : $fail('active-room board filter');

    $pending = array_filter($board ?? [], static fn(array $row): bool => (int)$row['room_id'] === $roomA && ($row['hk_status'] ?? null) === null);
    count($pending) === 1 ? $pass('missing housekeeping row appears as pending') : $fail('pending row display');

    try {
        HousekeepingService::updateStatus($roomA, ['hk_status' => 'spa_ready']);
        $fail('invalid status rejected');
    } catch (\Throwable $e) {
        $e->getMessage() === 'HOSPITALITY_HK_STATUS_INVALID' ? $pass('invalid status rejected') : $fail('invalid status code', $e->getMessage());
    }

    try {
        HousekeepingService::updateStatus($inactive, ['hk_status' => 'clean']);
        $fail('inactive room rejected');
    } catch (\Throwable $e) {
        $e->getMessage() === 'HOSPITALITY_HK_ROOM_NOT_FOUND' ? $pass('inactive room rejected') : $fail('inactive room code', $e->getMessage());
    }

    try {
        HousekeepingService::updateStatus($roomA, ['hk_status' => 'dirty', 'assigned_to' => str_repeat('x', 191)]);
        $fail('assigned_to length rejected');
    } catch (\Throwable $e) {
        $e->getMessage() === 'HOSPITALITY_HK_ASSIGNED_INVALID' ? $pass('assigned_to length rejected') : $fail('assigned_to code', $e->getMessage());
    }

    HousekeepingService::updateStatus($roomA, ['hk_status' => 'dirty', 'assigned_to' => 'Mina', 'note' => 'Turnover']);
    $row = DB::fetchOne('SELECT hk_status, assigned_to, note, last_cleaned_at FROM hosp_housekeeping_status WHERE room_id=?', [$roomA]);
    (($row['hk_status'] ?? '') === 'dirty' && ($row['assigned_to'] ?? '') === 'Mina' && ($row['note'] ?? '') === 'Turnover' && empty($row['last_cleaned_at']))
        ? $pass('dirty update lazily creates current-status row')
        : $fail('dirty update row');

    HousekeepingService::updateStatus($roomA, ['hk_status' => 'clean', 'assigned_to' => 'Mina', 'note' => 'Ready']);
    $row2 = DB::fetchOne('SELECT hk_status, note, last_cleaned_at FROM hosp_housekeeping_status WHERE room_id=?', [$roomA]);
    (($row2['hk_status'] ?? '') === 'clean' && ($row2['note'] ?? '') === 'Ready' && !empty($row2['last_cleaned_at']))
        ? $pass('clean update records last_cleaned_at')
        : $fail('clean update timestamp');

    foreach (['inspected', 'maintenance', 'out_of_service'] as $status) {
        HousekeepingService::updateStatus($roomB, ['hk_status' => $status]);
        $current = DB::fetchOne('SELECT hk_status FROM hosp_housekeeping_status WHERE room_id=?', [$roomB]);
        (($current['hk_status'] ?? '') === $status) ? $pass("valid status {$status}") : $fail("valid status {$status}");
    }

    $hkCount = DB::fetchOne('SELECT COUNT(*) AS c FROM hosp_housekeeping_status');
    ((int)($hkCount['c'] ?? 0) === 2) ? $pass('one current-status row per updated room') : $fail('current row count', (string)($hkCount['c'] ?? '?'));
} catch (\Throwable $e) {
    $fail('behavior group', $e->getMessage());
}

// ---- cleanup ----
try {
    foreach ($tables as $table) {
        DB::query("DROP TABLE IF EXISTS `{$table}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    $left = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'hosp_%'");
    ((int)($left['c'] ?? 0) === 0) ? $pass('cleanup: hosp_ tables removed') : $fail('cleanup: hosp_ tables removed');
} catch (\Throwable $e) {
    $fail('cleanup', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
