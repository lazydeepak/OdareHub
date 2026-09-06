<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 4 Probe - Housekeeping status mutation.
 *
 * Proves the confined operator action end-to-end:
 *   - static seam/handler/view contracts (self-workspace binding, CSRF,
 *     single-service delegation, jailed forms);
 *   - canonical service truths over isolated fixtures;
 *   - BEHAVIORAL cases through the REAL route closure in isolated child
 *     processes (own / cross / csrf), authority provisioned via the SUPPORTED
 *     explicit assignment-permission payload path, captured + restored exactly;
 *   - GET focus render proof lives in the slice-5 probe.
 *
 * Usage: php apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

use App\Core\Container;
use App\Core\DB;
use App\Core\Router;
use App\Core\View;
use App\Services\AppMigrationService;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeLoader;

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

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
$shellRoutes = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
$handlerSrc = (string)@file_get_contents($appRoot . '/Controllers/OperatorActions.php');
$viewSrc = (string)@file_get_contents($appRoot . '/Views/operator/hospitality.php');

echo "== Operator slice 4: housekeeping status action ==\n";

// ---- Group A: bounded Shell seam + self-workspace guard contracts ----
str_contains($shellRoutes, "\$router->post('/u/hospitality/housekeeping/status'")
    ? $pass('POST /u/hospitality/housekeeping/status registered in Shell routes')
    : $fail('POST housekeeping route registered');
substr_count($shellRoutes, 'housekeeping/status') === 1
    ? $pass('exactly one Shell route declaration')
    : $fail('exactly one Shell route declaration');
$hkRouteStart = strpos($shellRoutes, "\$router->post('/u/hospitality/housekeeping/status'");
$routeBlockHk = $hkRouteStart !== false ? substr($shellRoutes, $hkRouteStart, 2000) : '';
str_contains($routeBlockHk, "resolveOperatorPostRoute('hospitality', false, 'hospitality')")
    ? $pass('route uses operator POST pipeline with hospitality assignment gate')
    : $fail('route pipeline gate');
foreach ([['housekeeping', $routeBlockHk]] as [$routeName, $block]) {
    str_contains($block, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($block, 'hash_equals')
        ? $pass("{$routeName} route binds requested handle to authenticated identity")
        : $fail("{$routeName} route self-workspace binding");
    str_contains($block, "/dashboard', true, 302)")
        ? $pass("{$routeName} cross-handle denial redirects to authenticated dashboard")
        : $fail("{$routeName} cross-handle denial redirect");
}
!preg_match('/apps\/hospitality/i', $routeBlockHk)
    ? $pass('operator route never redirects to /apps/hospitality')
    : $fail('admin redirect inside operator route');

// ---- Group B: handler contract ----
preg_match('/public static function housekeepingStatus\(.*?\n    \}/s', $handlerSrc, $hm);
$fnBody = $hm[0] ?? '';
$fnBody !== ''
    ? $pass('app-owned housekeepingStatus handler exists')
    : $fail('handler exists');
str_contains($fnBody, "AclPolicy::can('hospitality.manage'")
    ? $pass('handler re-checks hospitality.manage server-side')
    : $fail('handler manage check');
str_contains($fnBody, 'Auth::requireCsrf')
    ? $pass('handler enforces CSRF')
    : $fail('handler CSRF');
str_contains($fnBody, 'HousekeepingService::updateStatus(')
    ? $pass('handler delegates to HousekeepingService::updateStatus (single business truth)')
    : $fail('handler delegation');
!preg_match('/ReservationsService|DB::|TRANSITIONS|folio|checkout\(/i', $fnBody)
    ? $pass('no SQL/lifecycle duplication in handler body')
    : $fail('forbidden logic in handler body');

// ---- Group C: view mutation-surface contract ----
str_contains($viewSrc, '$canManage')
    ? $pass('view gates mutation UI behind $canManage')
    : $fail('view manage gate');
preg_match_all('/<form[^>]*action="([^"]*)"/i', $viewSrc, $vm);
$viewFormsJailed = true;
foreach (($vm[1] ?? []) as $va) {
    if (!str_starts_with((string)$va, '/u/')) {
        $viewFormsJailed = false;
    }
}
$viewFormsJailed ? $pass('all view form actions jailed under /u/') : $fail('view forms jailed');
strpos(strtolower($viewSrc), '<form') !== false && strpos(strtolower($viewSrc), '<form') < (int)strpos($viewSrc, 'if ($canManage):')
    ? $fail('forms gated behind manage guard in source order')
    : $pass('forms gated behind manage guard in source order');
!str_contains(strtolower($viewSrc), 'style=')
    ? $pass('zero inline styles in operator view')
    : $fail('inline style present');
str_contains($viewSrc, '\Apps\Hospitality\Services\HousekeepingService::STATUSES')
    ? $pass('status choices come from canonical service constant')
    : $fail('duplicate status list in view');

// ---- Group D: locale parity ----
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = (string)@file_get_contents($appRoot . '/Resources/lang/' . $lang . '.php');
    $missing = [];
    foreach (['hospitality.operator.flash.updated', 'hospitality.operator.flash.update_failed', 'hospitality.operator.hk.save'] as $keyName) {
        if (!str_contains($catalog, "'" . $keyName . "'")) {
            $missing[] = $keyName;
        }
    }
    ($missing === [])
        ? $pass("locale {$lang} carries operator action keys")
        : $fail("locale {$lang} keys", implode(',', $missing));
}

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

try {
    // ---- Group E: real-loader route exposure ----
    $registry = new AppRegistryService();
    $originalStatus = (string)(($registry->find('hospitality') ?? [])['status'] ?? '');
    if ($originalStatus !== '' && $originalStatus !== 'enabled') {
        $registry->setStatus('hospitality', 'enabled');
    }
    $c = new Container();
    $router = new Router();
    $view = new View(APP_ROOT . '/public/views');
    $bus = new \App\Core\EventBus();
    $c->set('router', fn () => $router);
    $c->set('view', fn () => $view);
    $c->set('bus', fn () => $bus);
    $c->set('plugins', fn () => new \App\Core\PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus));
    AppRuntimeLoader::resetLoadedRoutes();
    (new AppRuntimeLoader($registry))->loadEnabledApps($c, $router, $view);
    $routesList = $router->listRoutes();
    isset($routesList['POST']['/u/hospitality/housekeeping/status'])
        ? $pass('POST route exposed by real router load')
        : $fail('POST route exposed');

    // ---- Group F: canonical service truths (isolated fixtures) ----
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    $roomsBeforeRows = DB::fetchAll('SELECT * FROM hosp_rooms');
    $hkBeforeRows = DB::fetchAll('SELECT * FROM hosp_housekeeping_status');

    try {
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-LC-OP4X', 'standard', 1, 'active', 'behavioral-probe')");
        $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number = 'T-LC-OP4X'")['id'] ?? 0);
        ($roomId > 0) ? $pass('isolated fixture room created') : $fail('fixture room');

        $rejectedRoom = false;
        try {
            \Apps\Hospitality\Services\HousekeepingService::updateStatus(999999, ['hk_status' => 'clean']);
        } catch (\Throwable $e) {
            $rejectedRoom = str_contains($e->getMessage(), 'HOSPITALITY_HK_ROOM_NOT_FOUND');
        }
        $rejectedRoom ? $pass('unknown room rejected canonically') : $fail('unknown room rejection');

        $rejectedStatus = false;
        try {
            \Apps\Hospitality\Services\HousekeepingService::updateStatus($roomId, ['hk_status' => 'bogus']);
        } catch (\Throwable $e) {
            $rejectedStatus = str_contains($e->getMessage(), 'HOSPITALITY_HK_STATUS_INVALID');
        }
        $rejectedStatus ? $pass('invalid status rejected canonically') : $fail('invalid status rejection');

        \Apps\Hospitality\Services\HousekeepingService::updateStatus($roomId, ['hk_status' => 'clean']);
        $rowAfter = DB::fetchOne('SELECT hk_status FROM hosp_housekeeping_status WHERE room_id = ?', [$roomId]);
        ((string)($rowAfter['hk_status'] ?? '') === 'clean')
            ? $pass('valid update succeeds through single business truth')
            : $fail('valid update path');
    } finally {
        try {
            $fid = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number = 'T-LC-OP4X'")['id'] ?? 0);
            if ($fid > 0) {
                DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id = ?', [$fid]);
            }
            DB::query("DELETE FROM hosp_rooms WHERE room_number = 'T-LC-OP4X'");
        } catch (\Throwable $e) {
            $restorationErrors[] = 'fixture removal: ' . $e->getMessage();
        }
    }

    // ---- Group G: BEHAVIORAL cases through the real route closure ----
    // Authority provisioned via the SUPPORTED explicit assignment-permission
    // payload fields; full row snapshotted and restored exactly afterwards.
    $authUser = DB::fetchOne('SELECT id, username, email FROM users ORDER BY id ASC LIMIT 1');
    if (!is_array($authUser)) {
        echo "  SKIP [behavioral harness: no local user]\n";
    } else {
        $authId = (int)$authUser['id'];
        $assignmentRowSnapshot = DB::fetchOne('SELECT * FROM user_dashboard_assignments WHERE user_id = ?', [$authId]);
        $origRowPayloadJson = base64_encode((string)json_encode($assignmentRowSnapshot)); // full-row payload snapshot
        $handle = (string)($authUser['username'] ?: explode('@', (string)$authUser['email'])[0]);
        $childTemplate = <<<'CHILDEOF'
<?php
define('APP_ROOT', getenv('HOSP_PROBE_ROOT'));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';
use App\Core\Container;
use App\Core\DB;
use App\Core\Router;
use App\Core\View;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeLoader;
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$mode = (string)getenv('HOSP_PROBE_MODE');
$outFile = (string)getenv('HOSP_PROBE_OUT');
$out = ['mode' => $mode];
register_shutdown_function(static function () use (&$out, $outFile): void {
    try {
        $roomId = (int)getenv('HOSP_PROBE_ROOM_ID');
        if ($roomId > 0) {
            $hk = DB::fetchOne('SELECT hk_status FROM hosp_housekeeping_status WHERE room_id = ?', [$roomId]);
            $out['final_hk'] = (string)($hk['hk_status'] ?? '');
            $out['flash_err'] = isset($_SESSION['operator_hospitality_flash_err']) ? (string)$_SESSION['operator_hospitality_flash_err'] : null;
            unset($_SESSION['operator_hospitality_flash_ok'], $_SESSION['operator_hospitality_flash_err']);
        }
        DB::query("DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE room_number LIKE 'T-LC-%')");
        DB::query("DELETE FROM hosp_rooms WHERE room_number LIKE 'T-LC-%'");
        $origRowPayload = json_decode(base64_decode((string)getenv('HOSP_PROBE_ASSIGN_ORIGINAL'), true) ?: '{}', true);
        if (is_array($origRowPayload) && $origRowPayload !== [] && (int)($origRowPayload['user_id'] ?? 0) > 0) {
            $cols = array_keys($origRowPayload);
            $assignmentsSql = implode(', ', array_map(static fn ($c) => "{$c} = ?", $cols));
            $values = array_values($origRowPayload);
            $values[] = (int)$origRowPayload['user_id'];
            DB::query("UPDATE user_dashboard_assignments SET {$assignmentsSql} WHERE user_id = ?", $values);
        }
        $out['cleanup_ok'] = true;
    } catch (\Throwable $e) {
        $out['cleanup_error'] = $e->getMessage();
    }
    file_put_contents($outFile, json_encode($out));
});
ob_start();
$authId = (int)getenv('HOSP_PROBE_USER_ID');
$handle = (string)getenv('HOSP_PROBE_HANDLE');
$_SESSION['user_id'] = $authId;
$_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$provisionRowPayload = "assigned_apps = 'hospitality', operator_views = 'dashboard,hospitality', permissions = 'hospitality.view,hospitality.manage'"; // assignment-row payload provisioning
DB::query("UPDATE user_dashboard_assignments SET {$provisionRowPayload} WHERE user_id = ?", [$authId]);
$c = new Container();
$router = new Router();
$view = new View(APP_ROOT . '/public/views');
$bus = new \App\Core\EventBus();
$c->set('router', fn () => $router);
$c->set('view', fn () => $view);
$c->set('bus', fn () => $bus);
$c->set('plugins', fn () => new \App\Core\PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus));
AppRuntimeLoader::resetLoadedRoutes();
(new AppRuntimeLoader(new AppRegistryService()))->loadEnabledApps($c, $router, $view);
$routesList = $router->listRoutes();
$closure = $routesList['POST']['/u/hospitality/housekeeping/status'] ?? null;
if (!$closure instanceof Closure) { $out['error'] = 'route missing'; exit(0); }
DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-LC-OP4X', 'standard', 1, 'active', 'behavioral-probe')");
$roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number = 'T-LC-OP4X'")['id'] ?? 0);
putenv('HOSP_PROBE_ROOM_ID=' . $roomId);

$_GET['u_username'] = ($mode === 'cross') ? 'not-this-user' : $handle;
$_POST = ['csrf' => $csrf, 'room_id' => (string)$roomId, 'hk_status' => 'clean'];
if ($mode === 'csrf') { $_POST['csrf'] = 'invalid-token-value'; }
try { $closure(); } catch (\Throwable $e) { $out['exception'] = $e->getMessage(); }
$hkNow = DB::fetchOne('SELECT hk_status FROM hosp_housekeeping_status WHERE room_id = ?', [$roomId]);
$out['fallback_hk'] = (string)($hkNow['hk_status'] ?? '');
exit(0);
CHILDEOF;

        $results = [];
        foreach (['own', 'cross', 'csrf'] as $mode) {
            $childScript = tempnam(sys_get_temp_dir(), 'op4h_') . '.php';
            $outFile = tempnam(sys_get_temp_dir(), 'op4o_') . '.json';
            @unlink($outFile);
            file_put_contents($childScript, $childTemplate);
            $env = 'HOSP_PROBE_ROOT=' . escapeshellarg(APP_ROOT)
                . ' HOSP_PROBE_USER_ID=' . escapeshellarg((string)$authId)
                . ' HOSP_PROBE_HANDLE=' . escapeshellarg($handle)
                . ' HOSP_PROBE_ASSIGN_ORIGINAL=' . escapeshellarg($origRowPayloadJson)
                . ' HOSP_PROBE_MODE=' . escapeshellarg($mode)
                . ' HOSP_PROBE_OUT=' . escapeshellarg($outFile);
            shell_exec($env . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($childScript) . ' 2>' . escapeshellarg($outFile . '.err'));
            @unlink($childScript);
            $results[$mode] = is_file($outFile) ? json_decode((string)file_get_contents($outFile), true) : null;
            if (!is_array($results[$mode])) {
                $fail("behavioral {$mode} case JSON result");
                continue;
            }
            (($results[$mode]['cleanup_ok'] ?? false) === true)
                ? $pass("behavioral {$mode} case restored state cleanly")
                : $fail("behavioral {$mode} cleanup", (string)($results[$mode]['cleanup_error'] ?? 'not clean'));
        }

        $ownB = $results['own'] ?? null;
        $crossB = $results['cross'] ?? null;
        $csrfB = $results['csrf'] ?? null;

        if (is_array($ownB)) {
            ((($ownB['final_hk'] ?? '') === 'clean') && !isset($ownB['exception']))
                ? $pass('BEHAVIORAL own-handle manage-authorized update executed via real route')
                : $fail('BEHAVIORAL own-handle update', json_encode([$ownB['final_hk'] ?? null, $ownB['exception'] ?? null]));
        }
        if (is_array($crossB)) {
            ((($crossB['final_hk'] ?? '') === '') && !isset($crossB['exception']) && (!array_key_exists('flash_err', $crossB) || $crossB['flash_err'] === null))
                ? $pass('BEHAVIORAL cross-handle denied pre-handler (no mutation)')
                : $fail('BEHAVIORAL cross-handle denial', json_encode([$crossB['final_hk'] ?? null, $crossB['flash_err'] ?? null]));
        }
        if (is_array($csrfB)) {
            ((($csrfB['final_hk'] ?? '') === '') && !isset($csrfB['exception']))
                ? $pass('BEHAVIORAL invalid-CSRF mutated nothing (platform 419 edge)')
                : $fail('BEHAVIORAL invalid-CSRF denial', json_encode([$csrfB['final_hk'] ?? null, $csrfB['exception'] ?? null]));
        }

        try {
            if ($originalStatus !== '' && $originalStatus !== 'enabled') {
                $registry->setStatus('hospitality', $originalStatus);
                $pass('original hospitality registry status restored');
            } else {
                $pass('original hospitality registry status preserved (enabled)');
            }
        } catch (\Throwable $e) {
            $fail('status restore', $e->getMessage());
        }
    }

    // ---- Restoration fingerprints ----
    $normalize = static function (array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($r as $k => $v) {
                $line[$k] = $v === null ? '~N~' : (string)$v;
            }
            $out[] = md5((string)json_encode($line));
        }
        sort($out);
        return $out;
    };
    $roomsAfterRows = DB::fetchAll('SELECT * FROM hosp_rooms');
    $hkAfterRows = DB::fetchAll('SELECT * FROM hosp_housekeeping_status');
    ($normalize($roomsBeforeRows) === $normalize($roomsAfterRows))
        ? $pass('hosp_rooms restored exactly (zero net change)')
        : $fail('hosp_rooms restored exactly');
    ($normalize($hkBeforeRows) === $normalize($hkAfterRows))
        ? $pass('hosp_housekeeping_status restored exactly (zero net change)')
        : $fail('hosp_housekeeping_status restored exactly');
} catch (\Throwable $e) {
    $fail('functional sequence', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
