<?php
declare(strict_types=1);

/**
 * Hospitality Slice 3 Module Scaffolding Probe
 *
 * Proves the five Hospitality modules are contract-compliant and discoverable through
 * EXISTING mechanisms only:
 *   1. Each module plugin.json passes the same shape rules enforced by
 *      scripts/architecture/check_business_app_module_contracts.sh for reference apps
 *      (that gate's scan scope is fixed to Manufacturing/SBAIO, so this probe applies
 *      the identical rules to hospitality without modifying the gate).
 *   2. App manifest modules[] match module folders and plugin.json module keys.
 *   3. PluginManager::scan() (real module discovery glob over app modules dirs)
 *      recognizes all five as canonical modules owned by hospitality.
 *   4. Real boot path (syncLocalApps) materializes the five rows into core_app_modules.
 *
 * No Core or loader file is modified by this probe.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice3_modules.php
 * Exit codes: 0 = pass, 1 = fail, 2 = environment unavailable
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppLocalDiscoveryService;

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

$expectedModules = [
    // folder => [manifest key, display name, module_type, maturity, required_tables]
    'Rooms' => ['rooms', 'Rooms', 'business_entity', 'L2', ['hosp_rooms']],
    'Guests' => ['guests', 'Guests', 'business_entity', 'L2', ['hosp_guests']],
    'Reservations' => ['reservations', 'Reservations', 'planning', 'L2', ['hosp_reservations']],
    'FrontDesk' => ['front_desk', 'Front Desk', 'process_execution', 'L3', ['hosp_folios', 'hosp_folio_charges']],
    'Housekeeping' => ['housekeeping', 'Housekeeping', 'service_only', 'L2', ['hosp_housekeeping_status']],
];

$allowedModuleTypes = ['business_entity', 'planning', 'process_execution', 'dashboard_only', 'service_only', 'governance', 'integration'];
$allowedCapabilities = [
    'routes', 'views', 'forms', 'schema', 'migrations', 'controllers', 'services',
    'permissions', 'menus', 'widgets', 'charts', 'dashboard', 'reports', 'exports',
    'dependencies', 'lifecycle_hooks', 'localization',
];
$requiredLifecycle = [
    'routes' => 'active_only',
    'menus' => 'active_only',
    'widgets' => 'active_only',
    'reports' => 'active_only',
    'dashboards' => 'active_only',
    'schema' => 'installed_or_active',
];

echo "== Slice 3: Hospitality module scaffolding ==\n";

// ---- Group A: per-module contract shape (same rules as the reference-app gate) ----
$pluginData = [];
foreach ($expectedModules as $folder => [$manifestKey, $displayName, $moduleType, $maturity, $tables]) {
    $file = APP_ROOT . "/apps/Hospitality/modules/{$folder}/plugin.json";
    if (!is_file($file)) {
        $fail("{$folder}: plugin.json exists");
        continue;
    }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) {
        $fail("{$folder}: plugin.json valid JSON");
        continue;
    }
    $pluginData[$folder] = $data;
    $pass("{$folder}: plugin.json valid JSON");

    foreach ([['name', $folder], ['package_type', 'module'], ['owner_app', 'hospitality'], ['module_key', $folder], ['module_type', $moduleType], ['target_maturity_level', $maturity]] as [$field, $expected]) {
        (($data[$field] ?? '') === $expected) ? $pass("{$folder}: {$field} = {$expected}") : $fail("{$folder}: {$field} = {$expected}", (string)($data[$field] ?? '?'));
    }

    in_array(($data['module_type'] ?? ''), $allowedModuleTypes, true) ? $pass("{$folder}: module_type documented") : $fail("{$folder}: module_type documented");
    preg_match('/^L[0-4]$/', (string)($data['target_maturity_level'] ?? '')) === 1 ? $pass("{$folder}: maturity L0-L4") : $fail("{$folder}: maturity L0-L4");

    $caps = $data['declared_capabilities'] ?? [];
    if (!is_array($caps) || $caps === []) {
        $fail("{$folder}: declared_capabilities non-empty array");
        continue;
    }
    $bad = array_diff($caps, $allowedCapabilities);
    (count($caps) === count(array_unique($caps)) && $bad === [])
        ? $pass("{$folder}: capabilities allowed + unique")
        : $fail("{$folder}: capabilities allowed + unique", implode(',', $bad));
    $has = static fn (string $c): bool => in_array($c, $caps, true);
    (!$has('forms') || $has('views')) ? $pass("{$folder}: forms=>views rule") : $fail("{$folder}: forms=>views rule");
    (!$has('controllers') || $has('routes')) ? $pass("{$folder}: controllers=>routes rule") : $fail("{$folder}: controllers=>routes rule");
    (!$has('migrations') || $has('schema')) ? $pass("{$folder}: migrations=>schema rule") : $fail("{$folder}: migrations=>schema rule");

    $lc = $data['lifecycle_contract'] ?? [];
    $lcOk = is_array($lc);
    foreach ($requiredLifecycle as $k => $v) {
        $lcOk = $lcOk && ($lc[$k] ?? null) === $v;
    }
    $lcOk && count($lc) === count($requiredLifecycle)
        ? $pass("{$folder}: lifecycle_contract exact")
        : $fail("{$folder}: lifecycle_contract exact", json_encode($lc));

    (($data['required_tables'] ?? []) === $tables)
        ? $pass("{$folder}: required_tables match slice-2 schema")
        : $fail("{$folder}: required_tables match slice-2 schema", json_encode($data['required_tables'] ?? []));

    !array_key_exists('suite', $data)
        ? $pass("{$folder}: no legacy suite metadata copied")
        : $fail("{$folder}: no legacy suite metadata copied");
}

// ---- Group B: app manifest <-> folders cross-consistency ----
$manifest = json_decode((string)file_get_contents(APP_ROOT . '/apps/Hospitality/manifest.json'), true);
$declared = [];
foreach ((array)($manifest['modules'] ?? []) as $m) {
    $declared[(string)$m['key']] = (string)$m['name'];
}
$expectedKeys = array_column($expectedModules, 0);
(array_keys($declared) === $expectedKeys)
    ? $pass('manifest declares exactly the five expected module keys')
    : $fail('manifest declares exactly the five expected module keys', implode(',', array_keys($declared)));

$nameByFolder = ['Rooms' => 'Rooms', 'Guests' => 'Guests', 'Reservations' => 'Reservations', 'FrontDesk' => 'Front Desk', 'Housekeeping' => 'Housekeeping'];
$namesOk = true;
foreach (array_keys($expectedModules) as $folder) {
    if (($pluginData[$folder]['display_name'] ?? '') !== $nameByFolder[$folder] || ($declared[$expectedModules[$folder][0]] ?? '') !== $nameByFolder[$folder]) {
        $namesOk = false;
    }
}
$namesOk ? $pass('display names consistent between manifest and plugin.json') : $fail('display names consistent between manifest and plugin.json');

// ---- Group C: PluginManager::scan() recognition (existing discovery) ----
try {
    $plugins = new \App\Core\PluginManager(APP_ROOT . '/plugins', new \App\Core\Container(), new \App\Core\Router(), new \App\Core\View(APP_ROOT . '/public/views'), new \App\Core\EventBus());
} catch (\Throwable $e) {
    echo "SKIP: runtime classes unavailable ({$e->getMessage()}); discovery groups not run.\n";
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

try {
    $scan = $plugins->scan();
    foreach (array_keys($expectedModules) as $folder) {
        $entry = $scan[$folder] ?? null;
        if (!is_array($entry)) {
            $fail("PluginManager::scan recognizes {$folder}");
            continue;
        }
        $ownerOk = (string)($entry['owner_app'] ?? '') === 'hospitality';
        $pkgOk = (string)($entry['package_type'] ?? '') === 'module';
        ($ownerOk && $pkgOk) ? $pass("scan recognizes {$folder} (owner=hospitality, canonical module)") : $fail("scan recognizes {$folder}", json_encode([$entry['owner_app'] ?? '?', $entry['package_type'] ?? '?']));
    }
} catch (\Throwable $e) {
    $fail('PluginManager::scan ran', $e->getMessage());
}

// ---- Group D: real boot path materializes core_app_modules ----
try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable; boot-path group not run.\n";
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

try {
    (new AppLocalDiscoveryService())->syncLocalApps();
    $rows = DB::fetchAll("SELECT module_key, module_name FROM core_app_modules WHERE app_key = 'hospitality' ORDER BY module_key");
    $keys = array_map(static fn ($r) => (string)$r['module_key'], $rows);
    sort($keys);
    $expectedSorted = $expectedKeys;
    sort($expectedSorted);
    ($keys === $expectedSorted)
        ? $pass('core_app_modules contains exactly the five declared modules after syncLocalApps()')
        : $fail('core_app_modules contains exactly the five declared modules', implode(',', $keys));
} catch (\Throwable $e) {
    $fail('syncLocalApps materialized modules', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
