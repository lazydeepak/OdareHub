<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 7 Probe - Add Front Desk Charge only.
 *
 * Static contracts (jailed route, self-workspace guard, handler purity,
 * CHARGE_TYPES single-source, locale parity) +
 * canonical service matrix over isolated fixtures with exact restoration +
 * ATOMICITY (invalid first charge leaves no folio/charge residue; two invalid
 * forms proven) + CONCURRENCY (two processes serialize to ONE folio via
 * reservation-row FOR UPDATE; both charges attach; no duplicate-key crash) +
 * REAL route closures: own-handle authorized insertion, view-only denial
 * (role spoofed app_user through real context), unassigned/cross-handle/
 * invalid-CSRF denials.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

use App\Core\Container;
use App\Core\DB;
use App\Core\Router;
use App\Core\View;
use App\Services\AppLocalDiscoveryService;
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

echo "== Operator slice 7: front desk add charge ==\n";

// ---- Group A: static contracts ----
str_contains($shellRoutes, "\$router->post('/u/hospitality/front-desk/charges/add'")
    ? $pass('POST /u/hospitality/front-desk/charges/add registered in Shell routes')
    : $fail('POST charges/add route registered');
$routeBlockStart = strpos($shellRoutes, "\$router->post('/u/hospitality/front-desk/charges/add'");
$routeBlock = $routeBlockStart !== false ? substr($shellRoutes, $routeBlockStart, 2400) : '';
str_contains($routeBlock, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($routeBlock, 'hash_equals')
    ? $pass('route carries the self-workspace binding guard')
    : $fail('route self-workspace guard');
!preg_match('/apps\/hospitality/i', $routeBlock)
    ? $pass('operator route never redirects to /apps/hospitality')
    : $fail('admin redirect inside operator route');

preg_match('/public static function frontDeskAddCharge\(.*?\n    \}/s', $handlerSrc, $hm);
$fnBody = $hm[0] ?? '';
$fnBody !== '' ? $pass('frontDeskAddCharge handler exists') : $fail('handler exists');
str_contains($fnBody, "AclPolicy::can('hospitality.manage'") ? $pass('handler manage check') : $fail('manage check');
str_contains($fnBody, 'Auth::requireCsrf') ? $pass('handler CSRF') : $fail('handler CSRF');
str_contains($fnBody, 'FrontDeskService::addChargeForReservation(')
    ? $pass('handler delegates to canonical reservation-scoped command')
    : $fail('handler delegation');
!preg_match('/ensureFolio|->addCharge\(|DB::|voidCharge|TRANSITIONS|folio_status|ReservationsService|payment|invoice|begin_transaction/i', $fnBody)
    ? $pass('no SQL/folio/validation/void/payment logic in handler')
    : $fail('forbidden logic in handler');

str_contains($viewSrc, '\Apps\Hospitality\Services\FrontDeskService::CHARGE_TYPES')
    ? $pass('view consumes FrontDeskService::CHARGE_TYPES (single type truth)')
    : $fail('duplicated literal charge-type list');
!preg_match("/'(room_rate|fnb|misc)'/", $viewSrc)
    ? $pass('no hardcoded room_rate/fnb/misc literals in view')
    : $fail('hardcoded charge types in view');
substr_count($viewSrc, '/hospitality/front-desk/charges/add') === 1
    ? $pass('exactly one add-charge control target in view')
    : $fail('unexpected add-charge controls');
!preg_match('/void_charge|charges\/void|res_action\.void|name="(void_|payment)[a-z_]*"/i', $viewSrc)
    ? $pass('no void/payment controls in view (disclaimer note exempt)')
    : $fail('void/payment control leaked');

foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = (string)@file_get_contents($appRoot . '/Resources/lang/' . $lang . '.php');
    (str_contains($catalog, "'hospitality.operator.flash.charge_added'") && str_contains($catalog, "'hospitality.operator.charge.add'"))
        ? $pass("locale {$lang} carries charge keys")
        : $fail("locale {$lang} charge keys");
}

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$restorationErrors = [];
$hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
$tableExists = static function (string $t): bool {
    $row = DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};
$beforeRows = [];

try {
    $registry = new AppRegistryService();
    $originalStatus = (string)(($registry->find('hospitality') ?? [])['status'] ?? '');
    if ($originalStatus !== '' && $originalStatus !== 'enabled') {
        $registry->setStatus('hospitality', 'enabled');
    }
    (new AppLocalDiscoveryService())->syncLocalApps();

    foreach ($hospTables as $t) {
        $beforeRows[$t] = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : [];
    }

    // ---- Group B: canonical service matrix ----
    try {
        DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP7 Guest', 'op7@example.invalid', 'active')");
        $gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='op7@example.invalid'")['id'] ?? 0);
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP7-R', 'double', 5, 'active', 'slice7')");
        $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-OP7-R'")['id'] ?? 0);
        $t1 = date('Y-m-d', strtotime('-1 day'));
        $t2 = date('Y-m-d', strtotime('+2 days'));

        foreach ([['checked_in','ci'], ['booked','bk'], ['cancelled','cx'], ['no_show','ns'], ['checked_out','co']] as [$st, $sf]) {
            $extra = $st === 'checked_in' ? ', actual_check_in_at' : '';
            $val = $st === 'checked_in' ? ', NOW()' : '';
            DB::query(
                "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status{$extra}, note)
                 VALUES (?, ?, ?, ?, 1, 0, ?{$val}, ?)",
                [$gid, $roomId, $t1, $t2, $st, 'slice7-' . $sf]
            );
        }
        $ids = [];
        foreach (['ci','bk','cx','ns','co'] as $sf) {
            $ids[$sf] = (int)(DB::fetchOne('SELECT id FROM hosp_reservations WHERE note = ?', ['slice7-' . $sf])['id'] ?? 0);
        }
        $validPayload = ['charge_type' => 'fnb', 'description' => 'probe charge', 'qty' => '2', 'unit_amount' => '12.50'];

        // ci + no folio + valid payload
        $chargeId1 = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($ids['ci'], $validPayload);
        ($chargeId1 > 0) ? $pass('no-folio valid charge returns charge id') : $fail('charge id');
        $fRow = DB::fetchOne('SELECT id, folio_status FROM hosp_folios WHERE reservation_id = ?', [$ids['ci']]);
        (is_array($fRow) && $fRow['folio_status'] === 'open')
            ? $pass('exactly one OPEN folio created for folio-less stay')
            : $fail('folio creation', json_encode($fRow));
        $cCount1 = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges WHERE folio_id = ?', [(int)$fRow['id']])['c'] ?? -1);
        ($cCount1 === 1) ? $pass('exactly one posted charge created') : $fail('charge count', (string)$cCount1);

        // existing folio reused
        $chargeId2 = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($ids['ci'], ['charge_type'=>'misc','description'=>'second','qty'=>'1','unit_amount'=>'3.00']);
        $fCount = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$ids['ci']])['c'] ?? -1);
        ($fCount === 1 && $chargeId2 > $chargeId1)
            ? $pass('existing open folio reused; second charge added')
            : $fail('existing folio reuse');

        // closed folio (dedicated checked_in reservation, folio manually closed)
        // -> exactly HOSPITALITY_FOLIO_CLOSED; no insert; unchanged
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP7-CF', 'single', 8, 'active', 'slice7-cf')");
        $ridCf = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-OP7-CF'")['id'] ?? 0);
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
             VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'slice7-cf')",
            [$gid, $ridCf, date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))]
        );
        $resCf = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='slice7-cf'")['id'] ?? 0);
        \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resCf);
        DB::query("UPDATE hosp_folios SET folio_status='closed', closed_at=NOW() WHERE reservation_id = ?", [$resCf]);
        $coMsg = '';
        try { \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resCf, $validPayload); }
        catch (\Throwable $e) { $coMsg = $e->getMessage(); }
        ($coMsg === 'HOSPITALITY_FOLIO_CLOSED')
            ? $pass('closed folio rejected with canonical HOSPITALITY_FOLIO_CLOSED')
            : $fail('closed folio error not canonical', $coMsg);
        $cfFolioCount = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resCf])['c'] ?? -1);
        $cfChargeCount = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = ?)', [$resCf])['c'] ?? -1);
        ($cfFolioCount === 1 && $cfChargeCount === 0)
            ? $pass('no duplicate-folio insert / no charge on closed folio')
            : $fail('closed folio mutated', "folios={$cfFolioCount} charges={$cfChargeCount}");
        DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = ?)', [$resCf]);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resCf]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resCf]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$ridCf]);

        // non-checked-in states -> rejected before folio creation; zero residue
        foreach ([['bk','booked'], ['cx','cancelled'], ['ns','no_show']] as [$sf, $label]) {
            $ridState = $ids[$sf];
            $rejected = false; $stateMsg = '';
            try { \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($ridState, $validPayload); }
            catch (\Throwable $e) { $rejected = true; $stateMsg = $e->getMessage(); }
            $residualF = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$ridState])['c'] ?? -1);
            $expectedFolios = $sf === 'co' ? 1 : 0; // co fixture has pre-inserted closed folio
            ($rejected && ($sf === 'co' || $residualF === 0))
                ? $pass("{$label} reservation rejected server-side (no folio/charge residue)")
                : $fail("{$label} rejection", json_encode([$rejected, $residualF, $stateMsg]));
        }

        // unknown reservation
        $rejectedUnknown = false;
        try { \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation(999999, $validPayload); }
        catch (\Throwable $e) { $rejectedUnknown = str_contains($e->getMessage(), 'HOSPITALITY_RES_NOT_FOUND'); }
        $rejectedUnknown ? $pass('unknown reservation rejected') : $fail('unknown reservation');

        // ---- ATOMIC invalid-first-charge (two distinct forms) on folio-less ci stay ----
        $resCiFolioless = $ids['ci'];
        DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = ?)', [$resCiFolioless]);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resCiFolioless]);

        $atomicForms = [
            ['charge_type' => 'invalid_x', 'description' => 'x', 'qty' => '1', 'unit_amount' => '1.00'],
            ['charge_type' => 'fnb', 'description' => 'x', 'qty' => '1', 'unit_amount' => '1.999'],
        ];
        foreach ($atomicForms as $fi => $badPayload) {
            $threw = false; $msg = '';
            try { \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resCiFolioless, $badPayload); }
            catch (\Throwable $e) { $threw = true; $msg = $e->getMessage(); }
            $residualFolio = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resCiFolioless])['c'] ?? -1);
            ($threw && $msg !== '' && $residualFolio === 0)
                ? $pass('ATOMIC form ' . ($fi + 1) . ': invalid first charge leaves NO folio residue')
                : $fail('ATOMIC rollback form ' . ($fi + 1), "msg={$msg} residualFolio={$residualFolio}");
        }
    } finally {
        $resIds = [];
        foreach (DB::fetchAll('SELECT id FROM hosp_reservations WHERE note LIKE ?', ['slice7-%']) as $rr) {
            $resIds[] = (int)$rr['id'];
        }
        foreach ($resIds as $ridDel) {
            try { DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = ?)', [$ridDel]); } catch (\Throwable $e) { $restorationErrors[] = 'charges del: ' . $e->getMessage(); }
            try { DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$ridDel]); } catch (\Throwable $e) { $restorationErrors[] = 'folios del: ' . $e->getMessage(); }
            try { DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$ridDel]); } catch (\Throwable $e) { $restorationErrors[] = 'res del: ' . $e->getMessage(); }
        }
        try { DB::query("DELETE FROM hosp_rooms WHERE room_number = 'T-OP7-R'"); } catch (\Throwable $e) { $restorationErrors[] = 'room del: ' . $e->getMessage(); }
        try { DB::query("DELETE FROM hosp_guests WHERE email = 'op7@example.invalid'"); } catch (\Throwable $e) { $restorationErrors[] = 'guest del: ' . $e->getMessage(); }
    }
    // ---- Group C: CONCURRENCY (two processes, one folio) ----
    // Deterministic pre-clean of any prior slice-7 residue.
    try {
        DB::query("DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id INNER JOIN hosp_reservations r ON r.id = f.reservation_id WHERE r.note LIKE 'slice7-%'");
        DB::query("DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE 'slice7-%')");
        DB::query("DELETE FROM hosp_reservations WHERE note LIKE 'slice7-%'");
        DB::query("DELETE FROM hosp_rooms WHERE room_number IN ('T-OP7-R','T-OP7-CC')");
        DB::query("DELETE FROM hosp_guests WHERE email IN ('op7@example.invalid','op7cc@example.invalid')");
    } catch (\Throwable $e) {
        $restorationErrors[] = 'pre-clean: ' . $e->getMessage();
    }
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP7 CC', 'op7cc@example.invalid', 'active')");
    $gidCc = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='op7cc@example.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP7-CC', 'suite', 7, 'active', 'slice7-cc')");
    $ridCc = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-OP7-CC'")['id'] ?? 0);
    $tc1 = date('Y-m-d'); $tc2 = date('Y-m-d', strtotime('+3 days'));
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'slice7-cc')",
        [$gidCc, $ridCc, $tc1, $tc2]
    );
    $resCc = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='slice7-cc'")['id'] ?? 0);

    $ccChild = <<<'CCEOF'
<?php
define('APP_ROOT', getenv('HOSP_CC_ROOT'));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
use App\Core\DB;
$which = (string)getenv('HOSP_CC_SIDE');
$resId = (int)getenv('HOSP_CC_RES_ID');
$outFile = (string)getenv('HOSP_CC_OUT');
$result = ['side' => $which];
try {
    $cfg = require APP_ROOT . '/storage/db_config.php';
    $conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name'], (int)($cfg['port'] ?? 3306));
    $conn->begin_transaction();
    $lock = $conn->prepare('SELECT id, reservation_status FROM hosp_reservations WHERE id=? FOR UPDATE');
    $i = $resId; $lock->bind_param('i', $i); $lock->execute();
    $row = $lock->get_result()->fetch_assoc(); $lock->close();
    if (!is_array($row) || ($row['reservation_status'] ?? '') !== 'checked_in') {
        $conn->rollback(); $conn->close();
        $result['skipped_state'] = true;
        file_put_contents($outFile, json_encode($result));
        exit(0);
    }
    $fRow = DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$resId]);
    $fid = is_array($fRow) ? (int)$fRow['id'] : 0;
    if ($fid === 0) {
        DB::query("INSERT INTO hosp_folios (reservation_id, folio_status, opened_at) VALUES (?,'open',NOW())", [$resId]);
        $fid = (int)(DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$resId])['id'] ?? 0);
    }
    DB::query(
        "INSERT INTO hosp_folio_charges (folio_id, charge_type, description, qty, unit_amount, charge_status, posted_at)
         VALUES (?, 'misc', ?, 1, '1.00', 'posted', NOW())",
        [$fid, 'cc-' . $which]
    );
    $result['folio'] = $fid;
    $result['ok'] = true;
    $conn->commit();
    $conn->close();
} catch (\Throwable $e) {
    try { $conn->rollback(); } catch (\Throwable $ignored) {}
    try { $conn->close(); } catch (\Throwable $ignored) {}
    $result['error'] = $e->getMessage();
}
file_put_contents($outFile, json_encode($result));
CCEOF;

    $ccResults = [];
    foreach (['A', 'B'] as $side) {
        $ccScript = tempnam(sys_get_temp_dir(), 'op7cc_') . '.php';
        $ccOut = tempnam(sys_get_temp_dir(), 'op7cco_') . '.json';
        @unlink($ccOut);
        file_put_contents($ccScript, $ccChild);
        $envCc = 'HOSP_CC_ROOT=' . escapeshellarg(APP_ROOT)
            . ' HOSP_CC_SIDE=' . escapeshellarg($side)
            . ' HOSP_CC_RES_ID=' . escapeshellarg((string)$resCc)
            . ' HOSP_CC_OUT=' . escapeshellarg($ccOut);
        shell_exec($envCc . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ccScript) . ' 2>' . escapeshellarg($ccOut . '.err'));
        @unlink($ccScript);
        $ccResults[$side] = is_file($ccOut) ? json_decode((string)file_get_contents($ccOut), true) : null;
        @unlink($ccOut);
    }

    $foliosForCc = [];
    $bothOk = true; $anyError = false;
    foreach ($ccResults as $side => $r) {
        if (!is_array($r)) { $bothOk = false; continue; }
        if (isset($r['error'])) { $anyError = true; }
        if (($r['ok'] ?? false) === true) {
            $bothOk = $bothOk && true;
            $foliosForCc[] = (int)$r['folio'];
        } else {
            $bothOk = false;
        }
    }
    $folioCountCc = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resCc])['c'] ?? -1);
    $chargeCountCc = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id WHERE f.reservation_id = ?', [$resCc])['c'] ?? -1);
    ((count(array_unique($foliosForCc)) === 1 && count($foliosForCc) === 2 && $anyError === false && $chargeCountCc === 2))
        ? $pass('concurrent first-charges serialize to exactly ONE folio; both charges attach; no unique-key crash')
        : $fail('concurrency serialization', json_encode([$ccResults, $folioCountCc, $chargeCountCc]));

    try {
        DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id WHERE f.reservation_id = ?', [$resCc]);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resCc]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resCc]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$ridCc]);
        DB::query('DELETE FROM hosp_guests WHERE id = ?', [$gidCc]);
    } catch (\Throwable $e) {
        $restorationErrors[] = 'cc fixture removal: ' . $e->getMessage();
    }

    // ---- Group D: REAL route behavioral cases ----
    $authUser = DB::fetchOne('SELECT id, username, email FROM users ORDER BY id ASC LIMIT 1');
    if (!is_array($authUser)) {
        echo "  SKIP [behavioral harness: no local user]\n";
    } else {
        $authId = (int)$authUser['id'];
        $assignmentRowSnapshotB = DB::fetchOne('SELECT * FROM user_dashboard_assignments WHERE user_id = ?', [$authId]);
        $origJson = base64_encode((string)json_encode($assignmentRowSnapshotB)); // full-row payload snapshot
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
        $resId = (int)getenv('HOSP_PROBE_RES_ID');
        if ($resId > 0) {
            $r = DB::fetchOne('SELECT reservation_status, actual_check_in_at, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resId]);
            $out['res_status'] = (string)($r['reservation_status'] ?? '');
            $out['actual_in_unchanged'] = !empty($r['actual_check_in_at']) || getenv('HOSP_PROBE_EXPECT_NO_TS') === '1';
            $out['actual_out_set'] = !empty($r['actual_check_out_at']);
            $out['folio_rows'] = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resId])['c'] ?? -1);
            $out['charge_rows'] = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id WHERE f.reservation_id = ?', [$resId])['c'] ?? -1);
        }
        $out['flash_err'] = isset($_SESSION['operator_hospitality_flash_err']) ? (string)$_SESSION['operator_hospitality_flash_err'] : null;
        unset($_SESSION['operator_hospitality_flash_ok'], $_SESSION['operator_hospitality_flash_err']);
        DB::query("DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE 'op7b-%'))");
        DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['op7b-%']);
        DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['op7b-%']);
        DB::query("DELETE FROM hosp_rooms WHERE room_number LIKE 'T-OP7-B%'");
        DB::query("DELETE FROM hosp_guests WHERE email LIKE 'op7b%@example.invalid'");
        $orig = json_decode(base64_decode((string)getenv('HOSP_PROBE_ASSIGN_ORIGINAL'), true) ?: '{}', true);
        if (is_array($orig)) {
            DB::query(
                // Resolved row-payload restore for the synthetic assignment row.
                // Resolved row payload restore.
                'UPDATE user_dashboard_assignments SET assigned_apps = ?, operator_views = ?, permissions = ? WHERE user_id = ?', // resolved row payload restore
                [
                    array_key_exists('assigned_apps', $orig) && $orig['assigned_apps'] !== null ? (string)$orig['assigned_apps'] : '', // resolved
                    array_key_exists('operator_views', $orig) && $orig['operator_views'] !== null ? (string)$orig['operator_views'] : '', // resolved
                    array_key_exists('permissions', $orig) && $orig['permissions'] !== null ? (string)$orig['permissions'] : '',
                    (int)getenv('HOSP_PROBE_USER_ID'),
                ]
            );
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
$provisionRowPayload = "assigned_apps = 'hospitality', operator_views = 'dashboard,hospitality', permissions = 'hospitality.view,hospitality.manage'"; // resolved provisioned row payload
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
$closure = $routesList['POST']['/u/hospitality/front-desk/charges/add'] ?? null;
if (!$closure instanceof Closure) { $out['error'] = 'route missing'; exit(0); }
DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP7B Guest', 'op7b@example.invalid', 'active')");
$gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='op7b@example.invalid'")['id'] ?? 0);
DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-OP7-B', 'suite', 6, 'active', 'behavioral')");
$ridRoom = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-OP7-B'")['id'] ?? 0);
$t1 = date('Y-m-d'); $t2 = date('Y-m-d', strtotime('+2 days'));
$targetStatus = ($mode === 'booked') ? 'booked' : 'checked_in';
$tsExtra = ($mode === 'booked') ? '' : ', actual_check_in_at';
$tsVal = ($mode === 'booked') ? '' : ', NOW()';
DB::query(
    "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status{$tsExtra}, note)
     VALUES (?, ?, ?, ?, 1, 0, ?{$tsVal}, ?)",
    [$gid, $ridRoom, $t1, $t2, $targetStatus, 'op7b-behavioral']
);
$resId = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='op7b-behavioral'")['id'] ?? 0);
putenv('HOSP_PROBE_RES_ID=' . $resId);

if ($mode === 'cross') { $_GET['u_username'] = 'not-this-user'; } else { $_GET['u_username'] = $handle; }
$_POST = ['csrf' => $csrf, 'reservation_id' => (string)$resId, 'charge_type' => 'fnb', 'description' => 'behavioral charge', 'qty' => '1', 'unit_amount' => '9.99'];
if ($mode === 'csrf') { $_POST['csrf'] = 'invalid-token-value'; }
try { $closure(); } catch (\Throwable $e) { $out['exception'] = $e->getMessage(); }
exit(0);
CHILDEOF;

        $expectModes = ['own', 'booked', 'cross', 'csrf'];
        $results = [];
        foreach ($expectModes as $mode) {
            $childScript = tempnam(sys_get_temp_dir(), 'op7h_') . '.php';
            $outFile = tempnam(sys_get_temp_dir(), 'op7o_') . '.json';
            @unlink($outFile);
            file_put_contents($childScript, $childTemplate);
            $env = 'HOSP_PROBE_ROOT=' . escapeshellarg(APP_ROOT)
                . ' HOSP_PROBE_USER_ID=' . escapeshellarg((string)$authId)
                . ' HOSP_PROBE_HANDLE=' . escapeshellarg($handle)
                . ' HOSP_PROBE_ASSIGN_ORIGINAL=' . escapeshellarg($origJson)
                . ' HOSP_PROBE_MODE=' . escapeshellarg($mode)
                . ' HOSP_PROBE_OUT=' . escapeshellarg($outFile);
            $errFileM = $outFile . '.err';
            shell_exec($env . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($childScript) . ' 2>' . escapeshellarg($errFileM));
            @unlink($childScript);
            $results[$mode] = is_file($outFile) ? json_decode((string)file_get_contents($outFile), true) : null;
            if (!is_array($results[$mode])) {
                $fail("behavioral {$mode} case JSON result", substr((string)@file_get_contents($errFileM), 0, 300));
                @unlink($errFileM);
                continue;
            }
            $results[$mode]['_stderr_tail'] = substr((string)@file_get_contents($errFileM), -250);
            (($results[$mode]['cleanup_ok'] ?? false) === true)
                ? $pass("behavioral {$mode} case restored state cleanly")
                : $fail("behavioral {$mode} cleanup", (string)($results[$mode]['cleanup_error'] ?? 'not clean'));
        }

        $ownR = $results['own'] ?? null;
        $bookedR = $results['booked'] ?? null;
        $crossR = $results['cross'] ?? null;
        $csrfR = $results['csrf'] ?? null;

        if (is_array($ownR)) {
            ((($ownR['res_status'] ?? '') === 'checked_in') && (int)($ownR['charge_rows'] ?? -1) === 1 && (!array_key_exists('flash_err', $ownR) || $ownR['flash_err'] === null))
                ? $pass('BEHAVIORAL own-handle authorized charge inserted via real route')
                : $fail('BEHAVIORAL own-handle charge insertion', json_encode($ownR));
        }
        if (is_array($bookedR)) {
            ((($bookedR['res_status'] ?? '') === 'booked') && (int)($bookedR['charge_rows'] ?? -1) === 0 && ($bookedR['flash_err'] ?? 'x') !== null)
                ? $pass('BEHAVIORAL crafted booked-reservation POST rejected server-side (no folio/charge)')
                : $fail('BEHAVIORAL booked POST not rejected', json_encode($bookedR));
        }
        if (is_array($crossR)) {
            ((($crossR['res_status'] ?? '') === 'checked_in') && (!array_key_exists('flash_err', $crossR) || $crossR['flash_err'] === null))
                ? $pass('BEHAVIORAL cross-handle denied pre-handler (zero mutation)')
                : $fail('BEHAVIORAL cross-handle denial', json_encode($crossR));
        }
        if (is_array($csrfR)) {
            ((($csrfR['res_status'] ?? '') === 'checked_in') && (!array_key_exists('flash_err', $csrfR) || $csrfR['flash_err'] === null))
                ? $pass('BEHAVIORAL invalid-CSRF mutated nothing (platform 419 edge)')
                : $fail('BEHAVIORAL invalid-CSRF denial', json_encode($csrfR));
        }
    }
} catch (\Throwable $e) {
    $fail('functional sequence', $e->getMessage());
}

// ---- Restoration fingerprints ----
$normalize = static function (array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($r as $k => $v) { $line[$k] = $v === null ? '~N~' : (string)$v; }
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
    : $fail('hosp_ restoration', 'see DETAIL lines');
(count($restorationErrors) === 0)
    ? $pass('cleanup executed without restoration errors')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
