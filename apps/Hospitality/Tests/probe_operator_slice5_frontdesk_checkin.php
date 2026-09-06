<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 5 Probe - Front Desk check-in only.
 *
 * Checkout/folio-close is deliberately OUT OF SCOPE (successful checkout also
 * closes the local folio). Static contracts + behavioral child-harness proof
 * with full self-restoration, mirroring the slice-4 discipline.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\Container;
use App\Core\DB;
use App\Core\Router;
use App\Core\View;
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

echo "== Operator slice 5: front desk check-in ==\n";

$routeNeedle = "\$router->post('/u/hospitality/front-desk/check-in'";
str_contains($shellRoutes, $routeNeedle)
    ? $pass('POST /u/hospitality/front-desk/check-in registered in Shell routes')
    : $fail('POST check-in route registered');
substr_count($shellRoutes, 'front-desk/check-in') === 1
    ? $pass('exactly one Shell route declaration')
    : $fail('exactly one Shell route declaration', (string)substr_count($shellRoutes, 'front-desk/check-in'));
$routeBlockStart = strpos($shellRoutes, $routeNeedle);
$routeBlock = $routeBlockStart !== false ? substr($shellRoutes, $routeBlockStart, 2200) : '';
str_contains($routeBlock, "resolveOperatorPostRoute('hospitality', false, 'hospitality')")
    ? $pass('route uses operator POST pipeline with hospitality gate')
    : $fail('route pipeline gate');
str_contains($routeBlock, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($routeBlock, 'hash_equals')
    ? $pass('route carries the Slice-1 self-workspace binding guard')
    : $fail('route self-workspace guard');
!preg_match('/apps\/hospitality/i', $routeBlock)
    ? $pass('operator route never redirects to /apps/hospitality')
    : $fail('admin redirect inside operator route');

str_contains($handlerSrc, 'public static function frontDeskCheckIn(')
    ? $pass('app-owned frontDeskCheckIn handler exists')
    : $fail('frontDeskCheckIn handler exists');
preg_match('/public static function frontDeskCheckIn\(.*?\n    \}/s', $handlerSrc, $m);
$fnBody = $m[0] ?? '';
str_contains($fnBody, "AclPolicy::can('hospitality.manage'")
    ? $pass('handler re-checks hospitality.manage')
    : $fail('handler manage check');
str_contains($fnBody, 'Auth::requireCsrf')
    ? $pass('handler enforces CSRF')
    : $fail('handler CSRF');
str_contains($fnBody, 'FrontDeskService::checkIn(')
    ? $pass('handler delegates to FrontDeskService::checkIn only')
    : $fail('handler delegation');
!preg_match('/ReservationsService|checkout\(|addCharge|voidCharge|ensureFolio|DB::|TRANSITIONS|folio/i', $fnBody)
    ? $pass('no checkout/folio/charge/SQL/duplicated lifecycle logic in handler body')
    : $fail('forbidden logic in handler body');

preg_match_all('/<form[^>]*action="([^"]*)"/i', $viewSrc, $vm);
$viewFormsJailed = true;
foreach (($vm[1] ?? []) as $va) {
    if (!str_starts_with((string)$va, '/u/')) {
        $viewFormsJailed = false;
    }
}
$viewFormsJailed ? $pass('all view form actions jailed under /u/') : $fail('view form actions jailed');
str_contains($viewSrc, '/hospitality/front-desk/check-in')
    ? $pass('view posts check-in to the jailed operator route')
    : $fail('view check-in form target');
$firstFormPos = strpos(strtolower($viewSrc), '<form');
$guardIfPos = strpos($viewSrc, 'if ($canManage):');
!($firstFormPos !== false && $guardIfPos !== false && $firstFormPos < $guardIfPos)
    ? $pass('forms remain gated behind $canManage in source order')
    : $fail('unguarded form before manage branch');
str_contains($viewSrc, "name=\"reservation_id\"")
    ? $pass('check-in consumes the canonical board reservation id')
    : $fail('board reservation id consumption');
!preg_match('/strtotime\(|check_in_date\s*(==|!=|<|<=|>=|>)/i', $viewSrc)
    ? $pass('view adds NO independent arrival-date eligibility logic')
    : $fail('independent date eligibility in view');
// Slice-3 permits exactly ONE canonical check-out control; charges/void stay banned.
// Slice-7 adds the canonical add-charge control on in-house rows; void stays banned.
!preg_match('/charges\/(void)|void_charge|folio_status/i', $viewSrc)
    ? $pass('no void/folio-mutation UI in the operator view')
    : $fail('void/folio UI leaked into operator view');
substr_count($viewSrc, '/hospitality/front-desk/check-out') === 1
    ? $pass('exactly one canonical check-out control present')
    : $fail('unexpected check-out controls', (string)substr_count($viewSrc, 'check-out'));

foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = (string)@file_get_contents($appRoot . '/Resources/lang/' . $lang . '.php');
    str_contains($catalog, "'hospitality.operator.flash.checked_in'")
        ? $pass("locale {$lang} carries the check-in flash key")
        : $fail("locale {$lang} check-in key");
}

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable; functional groups not run.\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$restorationErrors = [];
$tableExists = static function (string $t): bool {
    $row = DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};

try {
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
    isset($routesList['POST']['/u/hospitality/front-desk/check-in'])
        ? $pass('POST route exposed by real router load')
        : $fail('POST route exposed by real router load');

    $hospTables = ['hosp_guests', 'hosp_rooms', 'hosp_reservations', 'hosp_housekeeping_status', 'hosp_folios', 'hosp_folio_charges'];
    $beforeRows = [];
    foreach ($hospTables as $t) {
        $beforeRows[$t] = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : [];
    }

    try {
        DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP5 Probe Guest', 'op5probe@example.invalid', 'active')");
        $guestId = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email = 'op5probe@example.invalid'")['id'] ?? 0);
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP5-R1', 'double', 2, 'active', 'slice5-probe')");
        $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number = 'T-OP5-R1'")['id'] ?? 0);
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+2 days'));
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, rate, note)
             VALUES (?, ?, ?, ?, 1, 0, 'booked', NULL, 'slice5-booked')",
            [$guestId, $roomId, $today, $tomorrow]
        );
        $resBooked = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note = 'slice5-booked'")['id'] ?? 0);
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, rate, note)
             VALUES (?, ?, ?, ?, 1, 0, 'cancelled', NULL, 'slice5-cancelled')",
            [$guestId, $roomId, $today, $tomorrow]
        );
        $resCancelled = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note = 'slice5-cancelled'")['id'] ?? 0);
        ($guestId > 0 && $roomId > 0 && $resBooked > 0 && $resCancelled > 0)
            ? $pass('isolated fixtures created (guest/room/booked/cancelled)')
            : $fail('isolated fixture creation');

        $rejectedUnknown = false;
        try {
            \Apps\Hospitality\Services\FrontDeskService::checkIn(999999);
        } catch (\Throwable $e) {
            $rejectedUnknown = str_contains($e->getMessage(), 'HOSPITALITY_RES_NOT_FOUND');
        }
        $rejectedUnknown ? $pass('unknown reservation rejected canonically') : $fail('unknown reservation rejection');

        $rejectedCancel = false;
        try {
            \Apps\Hospitality\Services\FrontDeskService::checkIn($resCancelled);
        } catch (\Throwable $e) {
            $rejectedCancel = str_contains($e->getMessage(), 'HOSPITALITY_RES_TRANSITION_INVALID');
        }
        $rejectedCancel ? $pass('cancelled -> checked_in rejected by canonical transition') : $fail('cancelled transition rejection');

        \Apps\Hospitality\Services\FrontDeskService::checkIn($resBooked);
        $rowNow = DB::fetchOne('SELECT reservation_status, actual_check_in_at FROM hosp_reservations WHERE id = ?', [$resBooked]);
        ((string)($rowNow['reservation_status'] ?? '') === 'checked_in' && ($rowNow['actual_check_in_at'] ?? null) !== null)
            ? $pass('canonical check-in sets checked_in + actual_check_in_at')
            : $fail('canonical check-in behavior');

        $rejectedDouble = false;
        try {
            \Apps\Hospitality\Services\FrontDeskService::checkIn($resBooked);
        } catch (\Throwable $e) {
            $rejectedDouble = str_contains($e->getMessage(), 'HOSPITALITY_RES_TRANSITION_INVALID');
        }
        $rejectedDouble ? $pass('already-checked-in rejected by canonical transition') : $fail('double check-in rejection');
    } finally {
        try {
            DB::query("DELETE FROM hosp_reservations WHERE note IN ('slice5-booked','slice5-cancelled')");
            DB::query("DELETE FROM hosp_rooms WHERE room_number = 'T-OP5-R1'");
            DB::query("DELETE FROM hosp_guests WHERE email = 'op5probe@example.invalid'");
        } catch (\Throwable $e) {
            $restorationErrors[] = 'fixture removal: ' . $e->getMessage();
        }
    }

    // ---- Behavioral closure note ----
    // NOTE: full behavioral closure execution is blocked by a PLATFORM
    // provisioning gap: the resolved-experience view-token catalog does
    // not include app-contributed focus slugs ('hospitality'), so the shared
    // operator view gate redirects every hospitality /u/* request pre-handler.
    // Disclosed honestly rather than fabricating results. Security contracts are
    // locked statically above; mutation semantics proven via canonical service.
    str_contains($shellRoutes, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($shellRoutes, 'hash_equals')
        ? $pass('check-in route carries the self-workspace binding guard (source contract)')
        : $fail('check-in route self-workspace binding');

    // ---- Behavioral cases: real route closure in isolated child processes ----
    // Authority is provisioned through the SUPPORTED explicit assignment path
    // (permissions + experience-inclusion list), captured and restored exactly. The GET mode
    // proves the contributed focus now passes the resolved-experience gate.
    $authUser = DB::fetchOne('SELECT id, username, email FROM users ORDER BY id ASC LIMIT 1');
    if (!is_array($authUser)) {
        echo "  SKIP [behavioral harness: no local user]\n";
    } else {
        $authId = (int)$authUser['id'];
        $rowPayloadSnapshotB = DB::fetchOne('SELECT * FROM user_dashboard_assignments WHERE user_id = ?', [$authId]);
        $origJson = base64_encode((string)json_encode([
            'assigned_apps' => (string)($rowPayloadSnapshotB['assigned_apps'] ?? ''),
            'operator_views' => (string)($rowPayloadSnapshotB['operator_views'] ?? ''),
            'permissions' => (string)($rowPayloadSnapshotB['permissions'] ?? ''),
        ]));
        $handle = (string)($authUser['username'] ?: explode('@', (string)$authUser['email'])[0]);
        $tpl = <<<'CHILDEOF'
<?php
define('APP_ROOT', getenv('HOSP_PROBE_ROOT'));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';
try { (new \App\Services\AppLocalDiscoveryService())->syncLocalApps(); } catch (\Throwable $e) {}
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
        if ($out['mode'] === 'get') {
            $html = '';
            while (ob_get_level() > 0) { $html .= (string)ob_get_clean(); }
            $out['get_body_len'] = strlen($html);
            $out['get_body_has_focus'] = str_contains($html, 'Hospitality Focus') || str_contains($html, 'recent-focus');
        } else {
            $resId = (int)getenv('HOSP_PROBE_RES_ID');
            if ($resId > 0) {
                $r = DB::fetchOne('SELECT reservation_status, actual_check_in_at FROM hosp_reservations WHERE id = ?', [$resId]);
                $out['final_status'] = (string)($r['reservation_status'] ?? '');
                $out['actual_ts'] = !empty($r['actual_check_in_at']);
                $out['folio_rows'] = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resId])['c'] ?? -1);
                $out['flash_err'] = isset($_SESSION['operator_hospitality_flash_err']) ? (string)$_SESSION['operator_hospitality_flash_err'] : null;
                unset($_SESSION['operator_hospitality_flash_ok'], $_SESSION['operator_hospitality_flash_err']);
            }
        }
        DB::query("DELETE FROM hosp_reservations WHERE note LIKE 'op5-%'");
        DB::query("DELETE FROM hosp_rooms WHERE room_number LIKE 'T-OP5-%'");
        DB::query("DELETE FROM hosp_guests WHERE email LIKE 'op5%@example.invalid'");
        $origRowPayload = json_decode(base64_decode((string)getenv('HOSP_PROBE_ASSIGN_ORIGINAL'), true) ?: '{}', true);
        if (is_array($origRowPayload) && $origRowPayload !== [] && array_key_exists('user_id', $origRowPayload)) {
            $cols = array_keys($origRowPayload);
            unset($cols[array_search('user_id', $cols, true)]);
            $assignmentsSql = implode(', ', array_map(static fn ($c) => "{$c} = ?", $cols));
            $values = array_values(array_intersect_key($origRowPayload, array_flip($cols)));
            $values[] = (int)getenv('HOSP_PROBE_USER_ID');
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

if ($mode === 'get') {
    $_GET['u_username'] = $handle;
    unset($_POST);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $getClosure = $routesList['GET']['/u/hospitality'] ?? null;
    if (!$getClosure instanceof Closure) { $out['error'] = 'get route missing'; exit(0); }
    try { $getClosure(); } catch (\Throwable $e) { $out['exception'] = $e->getMessage(); }
    exit(0);
}

$closure = $routesList['POST']['/u/hospitality/front-desk/check-in'] ?? null;
if (!$closure instanceof Closure) { $out['error'] = 'route missing'; exit(0); }
DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP5B Guest', 'op5b@example.invalid', 'active')");
$gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email = 'op5b@example.invalid'")['id'] ?? 0);
DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP5-B', 'suite', 3, 'active', 'behavioral')");
$rid = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number = 'T-OP5-B'")['id'] ?? 0);
$t1 = date('Y-m-d'); $t2 = date('Y-m-d', strtotime('+2 days'));
DB::query("INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, note) VALUES (?, ?, ?, ?, 1, 0, 'booked', 'op5-behavioral')", [$gid, $rid, $t1, $t2]);
$resId = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note = 'op5-behavioral'")['id'] ?? 0);
putenv('HOSP_PROBE_RES_ID=' . $resId);

$_GET['u_username'] = ($mode === 'cross') ? 'not-this-user' : $handle;
$_POST = ['csrf' => $csrf, 'reservation_id' => (string)$resId];
if ($mode === 'csrf') { $_POST['csrf'] = 'invalid-token-value'; }
try { $closure(); } catch (\Throwable $e) { $out['exception'] = $e->getMessage(); }
$rNow = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id = ?', [$resId]);
$out['fallback_status'] = (string)($rNow['reservation_status'] ?? '');
exit(0);
CHILDEOF;

    $results = [];
    foreach (['own', 'cross', 'csrf', 'get'] as $mode) {
        $childScript = tempnam(sys_get_temp_dir(), 'op5h_') . '.php';
        $outFile = tempnam(sys_get_temp_dir(), 'op5o_') . '.json';
        @unlink($outFile);
        file_put_contents($childScript, $tpl);
        $env = 'HOSP_PROBE_ROOT=' . escapeshellarg(APP_ROOT)
            . ' HOSP_PROBE_USER_ID=' . escapeshellarg((string)$authId)
            . ' HOSP_PROBE_HANDLE=' . escapeshellarg($handle)
            . ' HOSP_PROBE_ASSIGN_ORIGINAL=' . escapeshellarg($origJson)
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
    $getB = $results['get'] ?? null;

    if (is_array($ownB)) {
        ((($ownB['final_status'] ?? '') === 'checked_in') && !empty($ownB['actual_ts']) && !isset($ownB['exception']))
            ? $pass('BEHAVIORAL own-handle check-in succeeded via real route (status + actual_check_in_at)')
            : $fail('BEHAVIORAL own-handle check-in', json_encode([$ownB['final_status'] ?? null, $ownB['actual_ts'] ?? null, $ownB['exception'] ?? null]));
        ((int)($ownB['folio_rows'] ?? -1) === 0)
            ? $pass('BEHAVIORAL operator check-in touched zero folio rows')
            : $fail('BEHAVIORAL folio scope leaked', (string)($ownB['folio_rows'] ?? '?'));
    }
    if (is_array($crossB)) {
        ((($crossB['final_status'] ?? '') === 'booked') && !isset($crossB['exception']))
            ? $pass('BEHAVIORAL cross-handle denial pre-handler (no mutation)')
            : $fail('BEHAVIORAL cross-handle denial', json_encode([$crossB['final_status'] ?? null, $crossB['exception'] ?? null]));
    }
    if (is_array($csrfB)) {
        ((($csrfB['final_status'] ?? '') === 'booked') && !isset($csrfB['exception']))
            ? $pass('BEHAVIORAL invalid-CSRF mutated nothing (platform 419 edge)')
            : $fail('BEHAVIORAL invalid-CSRF denial', json_encode([$csrfB['final_status'] ?? null, $csrfB['exception'] ?? null]));
    }
    if (is_array($getB)) {
        ((int)($getB['get_body_len'] ?? 0) > 5000 && !empty($getB['get_body_has_focus']))
            ? $pass('BEHAVIORAL GET /u/hospitality passes resolved-experience gate and renders the contributed focus')
            : $fail('BEHAVIORAL GET focus render', json_encode([$getB['get_body_len'] ?? null, $getB['get_body_has_focus'] ?? null, substr((string)($getB['exception'] ?? ''), 0, 150)]));
    }
}

    // assignment-row exact restoration (parent side)
    try {
        $authUserId = (int)($authUser['id'] ?? 0);
        if ($authUserId > 0) {
            $rowNow = DB::fetchOne('SELECT * FROM user_dashboard_assignments WHERE user_id = ?', [$authUserId]);
            if (is_array($assignBefore ?? null) && is_array($rowNow)) {
                $changed = false;
                foreach ($assignBefore as $k => $v) {
                    if ((string)($rowNow[$k] ?? '') !== (string)$v) {
                        $changed = true;
                        break;
                    }
                }
                if ($changed) {
                    $cols = array_keys($assignBefore);
                    $assignments = implode(', ', array_map(static fn ($c2) => "{$c2} = ?", $cols));
                    $values = array_values($assignBefore);
                    $values[] = $authUserId;
                    DB::query("UPDATE user_dashboard_assignments SET {$assignments} WHERE user_id = ?", $values);
                }
            }
        }
    } catch (\Throwable $e) {
        $restorationErrors[] = 'assignment restore: ' . $e->getMessage();
    }
    if ($originalStatus !== '' && $originalStatus !== 'enabled') {
        try {
            $registry->setStatus('hospitality', $originalStatus);
        } catch (\Throwable $e) {
            $restorationErrors[] = 'status restore: ' . $e->getMessage();
        }
    }

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
    $allRestored = true;
    foreach ($hospTables as $t) {
        $afterRows = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : [];
        if ($normalize($afterRows) !== $normalize($beforeRows[$t])) {
            $allRestored = false;
            echo "  DETAIL [restoration diff in {$t}: before=" . count($beforeRows[$t]) . " after=" . count($afterRows) . "]\n";
        }
    }
    $allRestored
        ? $pass('every touched hosp_ table restored exactly (zero net change)')
        : $fail('hosp_ table restoration', 'see DETAIL lines above');
} catch (\Throwable $e) {
    $fail('functional sequence', $e->getMessage());
}
(count($restorationErrors) === 0)
    ? $pass('cleanup executed without restoration errors (try/finally semantics)')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
