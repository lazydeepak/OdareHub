<?php
declare(strict_types=1);

/**
 * Hospitality Operator Composition Slice 2 Probe
 *
 * Covers:
 *   1. Manifest operator_surface hook declarations and provider files.
 *   2. Provider rejects unassigned/non-operator requests and returns sidebar/focus payloads.
 *   3. Existing OperatorSurfaceContributionRegistry exposes the sidebar for assigned users only.
 *   4. Read-only adapter returns null while schema is pending and summarized data after migrations.
 *   5. Locale parity for new hospitality.operator.* keys.
 *   6. No shared-app or Shell/Core coupling in the new app-owned files.
 *
 * Usage: php apps/Hospitality/Tests/probe_operator_slice2_contribution.php
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/apps/Shell/Services/OperatorSurfaceContributionRegistry.php';

use App\Core\DB;
use App\Services\AppLocalDiscoveryService;
use App\Services\AppMigrationService;
use App\Services\AppRegistryService;
use Apps\Hospitality\Services\FrontDeskService;
use Apps\Hospitality\Services\GuestsService;
use Apps\Hospitality\Services\HousekeepingService;
use Apps\Hospitality\Services\OperatorContributionService;
use Apps\Hospitality\Services\OperatorLayerAdapters\HospitalityBoardAdapter;
use Apps\Hospitality\Services\ReservationsService;
use Apps\Hospitality\Services\RoomsService;
use Apps\Shell\Services\OperatorSurfaceContributionRegistry;

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
$tables = ['hosp_rooms', 'hosp_guests', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];

echo "== Operator Composition Slice 2: Hospitality adapter + provider ==\n";

try {
    DB::conn();
} catch (\Throwable) {
    echo "SKIP: database unavailable.\n";
    exit(2);
}

// ---- Group A: manifest hooks and provider shape ----
$manifest = json_decode((string)file_get_contents($appRoot . '/manifest.json'), true);
if (!is_array($manifest)) {
    $fail('manifest JSON valid');
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(1);
}
$pass('manifest JSON valid');

$hooks = array_values(array_filter((array)($manifest['hooks'] ?? []), static function ($hook): bool {
    return is_array($hook)
        && ($hook['type'] ?? '') === 'operator_surface'
        && str_starts_with((string)($hook['key'] ?? ''), 'hospitality.operator.');
}));
count($hooks) === 2 ? $pass('two Hospitality operator hooks declared') : $fail('two Hospitality operator hooks declared', (string)count($hooks));

$regions = array_map(static fn(array $hook): string => (string)($hook['region'] ?? ''), $hooks);
sort($regions);
$regions === ['focus_views', 'sidebar'] ? $pass('sidebar + focus_views regions declared') : $fail('operator hook regions', implode(',', $regions));

$providerOk = true;
foreach ($hooks as $hook) {
    $providerOk = $providerOk
        && ($hook['provider'] ?? '') === 'Apps\\Hospitality\\Services\\OperatorContributionService::contribute'
        && ($hook['provider_file'] ?? '') === 'Services/OperatorContributionService.php'
        && is_file($appRoot . '/' . (string)$hook['provider_file']);
}
$providerOk ? $pass('hooks point to app-owned provider file') : $fail('hooks point to app-owned provider file');

$unassigned = OperatorContributionService::contribute([
    'surface' => 'operator',
    'region' => 'sidebar',
    'context' => ['active_assigned_apps' => ['manufacturing']],
]);
$unassigned === [] ? $pass('provider rejects unassigned context') : $fail('provider rejects unassigned context');

$wrongSurface = OperatorContributionService::contribute([
    'surface' => 'admin',
    'region' => 'sidebar',
    'context' => ['active_assigned_apps' => ['hospitality']],
]);
$wrongSurface === [] ? $pass('provider rejects non-operator surface') : $fail('provider rejects non-operator surface');

$sidebar = OperatorContributionService::contribute([
    'surface' => 'operator',
    'region' => 'sidebar',
    'context' => ['active_assigned_apps' => ['hospitality']],
]);
$route = (string)($sidebar['sections'][0]['items'][0]['route'] ?? '');
$route === '/u/{user}/hospitality' ? $pass('provider returns jailed operator sidebar route') : $fail('sidebar route', $route);

$focus = OperatorContributionService::contribute([
    'surface' => 'operator',
    'region' => 'focus_views',
    'context' => ['active_assigned_apps' => ['hospitality']],
]);
$focusPath = (string)($focus['view_map']['hospitality'] ?? '');
$focusPath === APP_ROOT . '/apps/Hospitality/Views/operator/hospitality.php'
    ? $pass('provider returns planned app-owned focus view path')
    : $fail('focus view path', $focusPath);

// ---- Group B: registry loads provider for assigned users ----
$registry = new AppRegistryService();
$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $row) {
    $statusSnapshot[(string)$row['app_key']] = (string)$row['status'];
}

try {
    (new AppLocalDiscoveryService())->syncLocalApps();
    $registry->setStatus('hospitality', AppRegistryService::STATUS_ENABLED);

    $assignedSections = OperatorSurfaceContributionRegistry::sidebarSections([
        'active_assigned_apps' => ['hospitality'],
    ]);
    $unassignedSections = OperatorSurfaceContributionRegistry::sidebarSections([
        'active_assigned_apps' => ['manufacturing'],
    ]);

    count($assignedSections) === 1 && ($assignedSections[0]['items'][0]['route'] ?? '') === '/u/{user}/hospitality'
        ? $pass('registry exposes Hospitality sidebar for assigned context')
        : $fail('registry exposes Hospitality sidebar for assigned context');
    $hasHospitality = false;
    foreach ($unassignedSections as $section) {
        foreach ((array)($section['items'] ?? []) as $item) {
            if (($item['route'] ?? '') === '/u/{user}/hospitality') {
                $hasHospitality = true;
            }
        }
    }
    !$hasHospitality ? $pass('registry hides Hospitality sidebar for unassigned context') : $fail('registry hides Hospitality sidebar for unassigned context');
} catch (\Throwable $e) {
    $fail('registry provider loading', $e->getMessage());
}

foreach ($statusSnapshot as $appKey => $originalStatus) {
    if ((string)($registry->find($appKey)['status'] ?? '') !== $originalStatus) {
        $registry->setStatus($appKey, $originalStatus);
    }
}
$pass('registry statuses restored');

// ---- Group C: adapter behavior over existing services ----
try {
    foreach ($tables as $table) {
        DB::query("DROP TABLE IF EXISTS `{$table}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");

    HospitalityBoardAdapter::summary() === null ? $pass('adapter returns null while schema pending') : $fail('adapter returns null while schema pending');

    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');

    $roomA = RoomsService::create(['room_number' => 'OP201', 'room_type' => 'single', 'floor' => '2']);
    $roomB = RoomsService::create(['room_number' => 'OP202', 'room_type' => 'double', 'floor' => '2']);
    $guest = GuestsService::create(['full_name' => 'Operator Guest', 'email' => 'operator@example.test']);
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $res = ReservationsService::create([
        'guest_id' => (string)$guest,
        'room_id' => (string)$roomA,
        'check_in_date' => $today,
        'check_out_date' => $tomorrow,
        'adults' => '1',
        'children' => '0',
        'rate' => '120.00',
    ]);

    HousekeepingService::updateStatus($roomA, ['hk_status' => 'dirty']);
    HousekeepingService::updateStatus($roomB, ['hk_status' => 'clean']);
    FrontDeskService::checkIn($res);
    $folioId = FrontDeskService::ensureFolio($res);
    FrontDeskService::addCharge($folioId, ['charge_type' => 'room_rate', 'description' => 'Night', 'qty' => '1', 'unit_amount' => '120.00']);

    $summary = HospitalityBoardAdapter::summary();
    is_array($summary) ? $pass('adapter returns summary after schema install') : $fail('adapter returns summary after schema install');

    $kpis = is_array($summary) ? (array)($summary['kpis'] ?? []) : [];
    ((int)($kpis['inhouse'] ?? -1) === 1 && (int)($kpis['rooms_attention'] ?? -1) === 1 && (float)($kpis['open_folio_total'] ?? -1) === 120.0)
        ? $pass('adapter KPI summary uses foundation service data')
        : $fail('adapter KPI summary', json_encode($kpis));

    $counts = is_array($summary) ? (array)($summary['housekeeping']['counts'] ?? []) : [];
    ((int)($counts['dirty'] ?? -1) === 1 && (int)($counts['clean'] ?? -1) === 1)
        ? $pass('adapter housekeeping counts are current-status only')
        : $fail('adapter housekeeping counts', json_encode($counts));
} catch (\Throwable $e) {
    $fail('adapter behavior', $e->getMessage());
}

// ---- Group D: locale parity and boundary scan ----
$localeFiles = ['en', 'ja', 'ne'];
$localeKeys = [];
foreach ($localeFiles as $locale) {
    $catalog = require $appRoot . "/Resources/lang/{$locale}.php";
    $localeKeys[$locale] = array_values(array_filter(array_keys($catalog), static fn(string $key): bool => str_starts_with($key, 'hospitality.operator.')));
    sort($localeKeys[$locale]);
}
($localeKeys['en'] === $localeKeys['ja'] && $localeKeys['en'] === $localeKeys['ne'] && count($localeKeys['en']) >= 10)
    ? $pass('operator locale keys are covered in en/ja/ne (parity across languages)')
    : $fail('operator locale key parity');

$scanFiles = [
    $appRoot . '/Services/OperatorContributionService.php',
    $appRoot . '/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php',
];
$source = strtolower(implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $scanFiles)));
$needles = ['pro' . 'curement', 'invent' . 'ory', 'bill' . 'ing', 'part' . 'ies', 'sb' . 'aio', 'mf' . 'g', 'apps\\shell', 'app\\core\\router'];
$hits = [];
foreach ($needles as $needle) {
    if (str_contains($source, strtolower($needle))) {
        $hits[] = $needle;
    }
}
$hits === [] ? $pass('new Hospitality operator files avoid shared-app/Shell/Core coupling') : $fail('boundary scan', implode(',', $hits));

// ---- Cleanup ----
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
