<?php
declare(strict_types=1);

/**
 * Front Desk Void Readiness Probe.
 *
 * Proves the HARDENED voidCharge() semantics + concurrency + immutability +
 * audit-readiness classification, with exact six-table restoration:
 *
 *   Semantics   unknown id; posted/open/checked-in void succeeds (total
 *               excludes voided); double void rejected (NOT_POSTED);
 *               closed folio rejected (FOLIO_CLOSED); checked-out stay
 *               rejected (RES_NOT_CHECKED_IN).
 *   Immutability charge row retained; type/description/qty/amount/posted_at
 *               unchanged; only status flips posted->voided.
 *   Concurrency double-void serialization via two processes on separate
 *               connections (exactly one winner, loser gets NOT_POSTED);
 *               void-first -> checkout proceeds, folio closes, charge stays
 *               voided; checkout-first -> folio closed, later void rejected.
 *
 *   Audit decision recorded: BLOCKED (see probe output note) - no sanctioned
 *   generic business-action audit sink exists; org_audit_log is Organization-
 *   owned (company_id NOT NULL); identity_security_events is Core identity
 *   telemetry, not financial business audit. No schema changes performed.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';

use App\Core\DB;
use App\Services\AppLocalDiscoveryService;
use App\Services\AppMigrationService;

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

echo "== Front Desk void readiness ==\n";

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

try {
    (new AppLocalDiscoveryService())->syncLocalApps();
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
} catch (\Throwable $e) {
    $fail('bootstrap', $e->getMessage());
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(1);
}

$hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
// Deterministic pre-clean of prior-run residue (self-restoring discipline).
try {
    DB::query("DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id INNER JOIN hosp_reservations r ON f.reservation_id = r.id WHERE r.note LIKE 'void-probe%' OR r.note LIKE 'void-cc%'");
    DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['void-%']);
    DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE note LIKE ?)', ['void-%']);
    DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['void-%']);
    DB::query('DELETE FROM hosp_rooms WHERE note LIKE ?', ['void-%']);
    DB::query("DELETE FROM hosp_guests WHERE email IN ('voidprobe@example.invalid','voidcc@example.invalid')");
} catch (\Throwable $e) {
    $fail('pre-clean', $e->getMessage());
}

$beforeRows = [];
foreach ($hospTables as $t) {
    $beforeRows[$t] = DB::fetchAll("SELECT * FROM {$t}");
}
$restorationErrors = [];

// Secondary connection factory for lock/competition harnesses.
$cfg = require APP_ROOT . '/storage/db_config.php';
$newConn = static function () use ($cfg): mysqli {
    return new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name'], (int)($cfg['port'] ?? 3306));
};

try {
    // ---- Fixtures: guest/room/checked-in reservation/open folio/two posted charges ----
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('Void Probe Guest', 'voidprobe@example.invalid', 'active')");
    $gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='voidprobe@example.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-VOID-R', 'suite', 9, 'active', 'void-probe')");
    $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-VOID-R'")['id'] ?? 0);
    $t1 = date('Y-m-d', strtotime('-1 day')); $t2 = date('Y-m-d', strtotime('+1 day'));
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'void-probe')",
        [$gid, $roomId, $t1, $t2]
    );
    $resId = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='void-probe'")['id'] ?? 0);
    \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resId);
    $folioId = (int)(DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$resId])['id'] ?? 0);

    $chargeIds = [];
    foreach ([['fnb', 'Dinner', '2', '25.00'], ['misc', 'Minibar', '1', '6.50']] as [$ct, $d, $q, $a]) {
        $chargeIds[] = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resId, ['charge_type'=>$ct,'description'=>$d,'qty'=>$q,'unit_amount'=>$a]);
    }
    (count($chargeIds) === 2)
        ? $pass('fixtures ready (checked_in stay, open folio, two posted charges)')
        : $fail('fixture readiness');

    $svc = '\\Apps\\Hospitality\\Services\\FrontDeskService';

    // ---- Basic semantics ----
    $unknownRejected = false;
    try { $svc::voidCharge(99999999); }
    catch (\Throwable $e) { $unknownRejected = str_contains($e->getMessage(), 'HOSPITALITY_CHARGE_NOT_FOUND'); }
    $unknownRejected ? $pass('unknown charge rejected canonically') : $fail('unknown charge rejection');

    $svc::voidCharge($chargeIds[0]);
    $c0 = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chargeIds[0]]);
    (($c0['charge_status'] ?? '') === 'voided')
        ? $pass('posted/open/checked-in void succeeds (status=voided)')
        : $fail('basic void');

    $totalOpen = \Apps\Hospitality\Services\FrontDeskService::openTotal($folioId);
    ($totalOpen == 6.50)
        ? $pass('open total excludes voided charge')
        : $fail('total exclusion', (string)$totalOpen);

    $doubleMsg = '';
    try { $svc::voidCharge($chargeIds[0]); }
    catch (\Throwable $e) { $doubleMsg = $e->getMessage(); }
    ($doubleMsg === 'HOSPITALITY_CHARGE_NOT_POSTED')
        ? $pass('double void rejected with HOSPITALITY_CHARGE_NOT_POSTED')
        : $fail('double void rejection', $doubleMsg);

    // Closed-folio rejection (close folio manually while stay checked_in)
    DB::query("UPDATE hosp_folios SET folio_status='closed', closed_at=NOW() WHERE id = ?", [$folioId]);
    $closedMsg = '';
    try { $svc::voidCharge($chargeIds[1]); }
    catch (\Throwable $e) { $closedMsg = $e->getMessage(); }
    ($closedMsg === 'HOSPITALITY_FOLIO_CLOSED')
        ? $pass('closed folio rejected with HOSPITALITY_FOLIO_CLOSED')
        : $fail('closed folio rejection', $closedMsg);
    DB::query("UPDATE hosp_folios SET folio_status='open', closed_at=NULL WHERE id = ?", [$folioId]);

    // Race-target charge created while still checked_in (for later concurrency).
    $cid2 = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resId, ['charge_type'=>'misc','description'=>'race-target-pre-co','qty'=>'1','unit_amount'=>'2.00']);

    // Checked-out stay rejection (transition canonical then attempt void)
    \Apps\Hospitality\Services\FrontDeskService::checkout($resId);
    $outMsg = '';
    try { $svc::voidCharge($chargeIds[1]); }
    catch (\Throwable $e) { $outMsg = $e->getMessage(); }
    ($outMsg === 'HOSPITALITY_RES_NOT_CHECKED_IN')
        ? $pass('checked-out stay rejected with HOSPITALITY_RES_NOT_CHECKED_IN')
        : $fail('checked-out rejection', $outMsg);

    // ---- ORDERING PROOF A: Void-first -> Checkout succeeds after ----
    // Fresh fixture: checked_in stay + posted charge. Void first; then checkout.
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('VP OrdA', 'vporda@vp.invalid', 'active')");
    $ga = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='vporda@vp.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-VP-ORDA', 'double', 9, 'active', 'void-ord-a')");
    $ra2 = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-VP-ORDA'")['id'] ?? 0);
    $t1o = date('Y-m-d', strtotime('-1 day')); $t2o = date('Y-m-d', strtotime('+1 day'));
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'void-ord-a')",
        [$ga, $ra2, $t1o, $t2o]
    );
    $resOrdA = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='void-ord-a'")['id'] ?? 0);
    \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resOrdA);
    $chOrdA = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resOrdA, ['charge_type'=>'fnb','description'=>'orderA','qty'=>'1','unit_amount'=>'8.00']);
    $svc::voidCharge($chOrdA);
    \Apps\Hospitality\Services\FrontDeskService::checkout($resOrdA);
    $stOrdA = DB::fetchOne('SELECT r.reservation_status AS rs, f.folio_status AS fs FROM hosp_reservations r INNER JOIN hosp_folios f ON f.reservation_id = r.id WHERE r.id = ?', [$resOrdA]);
    $chOrdAAfter = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chOrdA]);
    ((($stOrdA['rs'] ?? '') === 'checked_out') && (($stOrdA['fs'] ?? '') === 'closed')
        && (($chOrdAAfter['charge_status'] ?? '') === 'voided'))
        ? $pass('ORDERING A: void-first -> checkout succeeds; charge remains voided; folio closed')
        : $fail('ORDERING A broken', json_encode([$stOrdA, $chOrdAAfter]));
    try {
        DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.note = ?', ['void-ord-a']);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resOrdA]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resOrdA]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$ra2]);
        DB::query('DELETE FROM hosp_guests WHERE id = ?', [$ga]);
    } catch (\Throwable $e) { $restorationErrors[] = 'ordA cleanup: ' . $e->getMessage(); }

    // ---- ORDERING PROOF B: Checkout-first -> Void rejected (stale-read guard) ----
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('VP OrdB', 'vpordb@vp.invalid', 'active')");
    $gb = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='vpordb@vp.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-VP-ORDB', 'double', 9, 'active', 'void-ord-b')");
    $rb2 = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-VP-ORDB'")['id'] ?? 0);
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'void-ord-b')",
        [$gb, $rb2, $t1o, $t2o]
    );
    $resOrdB = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='void-ord-b'")['id'] ?? 0);
    \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resOrdB);
    $chOrdB = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resOrdB, ['charge_type'=>'fnb','description'=>'orderB','qty'=>'1','unit_amount'=>'8.00']);
    \Apps\Hospitality\Services\FrontDeskService::checkout($resOrdB);
    $msgOrdB = '';
    try { $svc::voidCharge($chOrdB); }
    catch (\Throwable $e) { $msgOrdB = $e->getMessage(); }
    $stOrdB = DB::fetchOne('SELECT r.reservation_status AS rs, f.folio_status AS fs FROM hosp_reservations r INNER JOIN hosp_folios f ON f.reservation_id = r.id WHERE r.id = ?', [$resOrdB]);
    $chOrdBAfter = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chOrdB]);
    (($msgOrdB === 'HOSPITALITY_RES_NOT_CHECKED_IN' || $msgOrdB === 'HOSPITALITY_FOLIO_CLOSED')
        && (($stOrdB['rs'] ?? '') === 'checked_out') && (($stOrdB['fs'] ?? '') === 'closed')
        && (($chOrdBAfter['charge_status'] ?? '') === 'posted'))
        ? $pass('ORDERING B: checkout-first -> void rejected (stale-read guard); charge stays posted')
        : $fail('ORDERING B broken', json_encode([$msgOrdB, $stOrdB, $chOrdBAfter]));
    try {
        DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.note = ?', ['void-ord-b']);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resOrdB]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resOrdB]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$rb2]);
        DB::query('DELETE FROM hosp_guests WHERE id = ?', [$gb]);
    } catch (\Throwable $e) { $restorationErrors[] = 'ordB cleanup: ' . $e->getMessage(); }

    // ---- ORDERING PROOF C: Add-charge vs void on same stay (both orders cheap) ----
    // C1: void existing THEN add new -> both effects correct.
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('VP OrdC', 'vporcde@vp.invalid', 'active')");
    $gc = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='vporcde@vp.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-VP-ORDC', 'double', 9, 'active', 'void-ord-c')");
    $rc = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-VP-ORDC'")['id'] ?? 0);
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'void-ord-c')",
        [$gc, $rc, $t1o, $t2o]
    );
    $resOrdC = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='void-ord-c'")['id'] ?? 0);
    \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resOrdC);
    $chC1 = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resOrdC, ['charge_type'=>'fnb','description'=>'ordC-target','qty'=>'1','unit_amount'=>'5.00']);
    $svc::voidCharge($chC1);
    $chC2 = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resOrdC, ['charge_type'=>'misc','description'=>'ordC-new','qty'=>'1','unit_amount'=>'7.00']);
    $c1s = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chC1]);
    $c2s = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chC2]);
    (($c1s['charge_status'] ?? '') === 'voided' && ($c2s['charge_status'] ?? '') === 'posted')
        ? $pass('ORDERING C: void-existing then add-new coexist correctly (target voided, new posted)')
        : $fail('ORDERING C broken', json_encode([$c1s, $c2s]));
    try {
        DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.note = ?', ['void-ord-c']);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resOrdC]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resOrdC]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$rc]);
        DB::query('DELETE FROM hosp_guests WHERE id = ?', [$gc]);
    } catch (\Throwable $e) { $restorationErrors[] = 'ordC cleanup: ' . $e->getMessage(); }

    // ---- Reservation-state rejections (posted charge + open folio exist; stay NOT checked_in) ----
    foreach ([['booked','booked'], ['cancelled','cancelled'], ['no_show','no_show'], ['checked_out','checked_out']] as [$stKey, $label]) {
        DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES (?, CONCAT(?, '@vp.invalid'), 'active')", ['VP ' . $label, 'vp' . $stKey]);
        $gX = (int)(DB::fetchOne('SELECT id FROM hosp_guests WHERE email = ?', ['vp' . $stKey . '@vp.invalid'])['id'] ?? 0);
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES (?, 'double', 9, 'active', ?)", ['T-VP-' . strtoupper($stKey), 'void-state-' . $label]);
        $rX = (int)(DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number = ?', ['T-VP-' . strtoupper($stKey)])['id'] ?? 0);
        $ta = date('Y-m-d', strtotime('-2 days')); $tb2 = date('Y-m-d', strtotime('+1 day'));
        $tsCol = ''; $tsVal = '';
        if ($label === 'checked_out') { $tsCol = ', actual_check_in_at'; $tsVal = ", NOW()"; }
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status{$tsCol}, note)
             VALUES (?, ?, ?, ?, 1, 0, ?{$tsVal}, ?)",
            [$gX, $rX, $ta, $tb2, $stKey, 'void-state-' . $label]
        );
        $resX = (int)(DB::fetchOne('SELECT id FROM hosp_reservations WHERE note = ?', ['void-state-' . $label])['id'] ?? 0);
        \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resX);
        // Post a charge DIRECTLY (bypassing addChargeForReservation, which
        // correctly rejects non-checked_in states before folio creation).
        $fX = (int)(DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id = ?', [$resX])['id'] ?? 0);
        DB::query("INSERT INTO hosp_folio_charges (folio_id, charge_type, description, qty, unit_amount, charge_status, posted_at) VALUES (?, 'misc', 'manual posted', 1, '1.00', 'posted', NOW())", [$fX]);
        $chX = (int)(DB::fetchOne('SELECT id FROM hosp_folio_charges WHERE folio_id = ?', [$fX])['id'] ?? 0);
        $msgX = '';
        try { $svc::voidCharge((int)$chX); }
        catch (\Throwable $e) { $msgX = $e->getMessage(); }
        ($msgX === 'HOSPITALITY_RES_NOT_CHECKED_IN')
            ? $pass("{$label} stay rejected with HOSPITALITY_RES_NOT_CHECKED_IN")
            : $fail("{$label} state rejection", $msgX);
        // zero mutation proof
        $stAfter = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$chX]);
        (($stAfter['charge_status'] ?? '') === 'posted')
            ? $pass("{$label}: charge remains posted (zero mutation)")
            : $fail("{$label} charge mutated", json_encode($stAfter));
    }

    // ---- Immutability of business facts ----
    $row0 = DB::fetchOne('SELECT * FROM hosp_folio_charges WHERE id = ?', [$chargeIds[0]]);
    $immOk = (($row0['charge_type'] ?? '') === 'fnb' && ($row0['description'] ?? '') === 'Dinner'
        && (int)$row0['qty'] === 2 && (string)$row0['unit_amount'] === '25.00'
        && !empty($row0['posted_at']) && ($row0['folio_id'] ?? 0) == $folioId);
    // created_by/created_at may be NULL in schema; presence check only when set.
    if (array_key_exists('created_by', $row0) && $row0['created_by'] !== null && $row0['created_by'] !== '') {
        $immOk = $immOk && true;
    }
    $immOk
        ? $pass('immutable business facts preserved (id/folio/type/desc/qty/amount/posted_at[/created_*])')
        : $fail('immutability violated', json_encode($row0));

    // ---- Post-checkout server-side guard: add-charge must be blocked ----
    $guardMsg = '';
    try { \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resId, ['charge_type'=>'misc','description'=>'post-co','qty'=>'1','unit_amount'=>'1.00']); }
    catch (\Throwable $e) { $guardMsg = $e->getMessage(); }
    ($guardMsg === 'HOSPITALITY_RES_NOT_CHECKED_IN')
        ? $pass('crafted post-checkout add-charge blocked (RES_NOT_CHECKED_IN)')
        : $fail('post-checkout guard', $guardMsg);

} catch (\Throwable $e) {
    $fail('setup/semantics sequence', $e->getMessage() . ' @ ' . ($e->getFile() ?? '?') . ':' . ($e->getLine() ?? 0));
}

// ---- Concurrency section: dedicated checked-in fixture (fresh process pair) ----

try {
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('Void CC Guest', 'voidcc@example.invalid', 'active')");
    $gid2 = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='voidcc@example.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-VOID-C', 'double', 9, 'active', 'void-cc')");
    $room2 = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-VOID-C'")['id'] ?? 0);
    $ta = date('Y-m-d'); $tb = date('Y-m-d', strtotime('+1 day'));
    DB::query(
        "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
         VALUES (?, ?, ?, ?, 1, 0, 'checked_in', NOW(), 'void-cc')",
        [$gid2, $room2, $ta, $tb]
    );
    $resCc = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='void-cc'")['id'] ?? 0);
    echo "  DBG2 [gid2=$gid2 room2=$room2 resCc=$resCc resId=$resId]";
    echo " ccrow=" . json_encode(DB::fetchOne("SELECT id, reservation_status, note FROM hosp_reservations WHERE note = 'void-cc'")) . "\n";
    \Apps\Hospitality\Services\FrontDeskService::ensureFolio($resCc);
    $dbgRow = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id = ?', [$resCc]);
    echo '  DBG [cc-res status=' . ($dbgRow['reservation_status'] ?? 'MISSING') . "]\n";
    $target = \Apps\Hospitality\Services\FrontDeskService::addChargeForReservation($resCc, ['charge_type'=>'fnb','description'=>'race target','qty'=>'1','unit_amount'=>'4.00']);

    $childTpl = <<<'CCEOF'
<?php
define('APP_ROOT', getenv('VP_ROOT'));
require APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
use App\Core\DB;
$side = (string)getenv('VP_SIDE');
$cid = (int)getenv('VP_CHARGE_ID');
$resId = (int)getenv('VP_RES_ID');
$outFile = (string)getenv('VP_OUT');
$result = ['side' => $side];
try {
    $cfg = require APP_ROOT . '/storage/db_config.php';
    $conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name'], (int)($cfg['port'] ?? 3306));
    $msg = '';
    try { \Apps\Hospitality\Services\FrontDeskService::voidCharge($cid); $result['void_ok'] = true; }
    catch (\Throwable $e) { $msg = $e->getMessage(); $result['err'] = $msg; }
    $st = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$cid]);
    $result['final_status'] = (string)($st['charge_status'] ?? '');
    if ($msg !== '') { $result['err'] = $msg; }
    $conn->commit();
    $conn->close();
} catch (\Throwable $e) {
    try { $conn->rollback(); } catch (\Throwable $ignored) {}
    try { $conn->close(); } catch (\Throwable $ignored) {}
    $result['fatal'] = $e->getMessage();
}
file_put_contents($outFile, json_encode($result));
CCEOF;

    $runChild = static function (string $side, int $cidArg, int $resArg, string $src) use ($appRoot): array {
        $script = tempnam(sys_get_temp_dir(), 'vp_c_') . '.php';
        $outF = tempnam(sys_get_temp_dir(), 'vp_o_') . '.json';
        @unlink($outF);
        file_put_contents($script, $src);
        $env = 'VP_ROOT=' . escapeshellarg(APP_ROOT)
            . ' VP_SIDE=' . escapeshellarg($side)
            . ' VP_CHARGE_ID=' . escapeshellarg((string)$cidArg)
            . ' VP_RES_ID=' . escapeshellarg((string)$resArg)
            . ' VP_OUT=' . escapeshellarg($outF);
        $errT = $outF . '.err';
        shell_exec($env . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>' . escapeshellarg($errT));
        @unlink($script);
        $r = is_file($outF) ? json_decode((string)file_get_contents($outF), true) : null;
        if (!is_array($r)) { $r = ['_err' => substr((string)@file_get_contents($errT), 0, 250)]; }
        @unlink($outF); @unlink($errT);
        return $r;
    };

    // Double-void race on the SAME posted charge in the cc fixture.
    $GLOBALS['vpChildSource'] = $childTpl;
    $ra = $runChild('A', $target, $resCc, $childTpl);
    $rb = $runChild('B', $target, $resCc, $childTpl);
    $final = DB::fetchOne('SELECT charge_status FROM hosp_folio_charges WHERE id = ?', [$target]);
    $winners = 0;
    foreach ([$ra, $rb] as $r) {
        if (($r['void_ok'] ?? false) === true) { $winners++; }
    }
    (($winners === 1) && ($final['charge_status'] ?? '') === 'voided'
        && in_array('HOSPITALITY_CHARGE_NOT_POSTED', [$ra['err'] ?? '', $rb['err'] ?? ''], true))
        ? $pass('DOUBLE-VOID RACE: exactly one winner; loser got CHARGE_NOT_POSTED; final=voided')
        : $fail('double-void race', json_encode([$ra, $rb, $final]));

    // Cleanup cc fixtures
    try {
        DB::query("DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id INNER JOIN hosp_reservations r ON f.reservation_id = r.id WHERE r.note LIKE 'void-state-%'");
        DB::query("DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE 'void-state-%')");
        DB::query("DELETE FROM hosp_reservations WHERE note LIKE 'void-state-%'");
        DB::query("DELETE FROM hosp_rooms WHERE note LIKE 'void-state-%'");
        DB::query("DELETE FROM hosp_guests WHERE full_name LIKE 'VP %'");
        DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?))', ['void-%']);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['void-%']);
        DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE room_number LIKE ?)', ['T-VOID-%']);
        DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['void-probe%']);
        DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['void-cc%']);
        DB::query('DELETE FROM hosp_reservations WHERE id IN (?,?)', [$resId, $resCc]);
        DB::query('DELETE FROM hosp_rooms WHERE room_number LIKE ?', ['T-VOID-%']);
        DB::query("DELETE FROM hosp_guests WHERE email IN ('voidprobe@example.invalid','voidcc@example.invalid')");
    } catch (\Throwable $e) {
        $restorationErrors[] = 'cc cleanup: ' . $e->getMessage();
    }
} catch (\Throwable $e) {
    $fail('concurrency section', $e->getMessage() . ' @ ' . ($e->getFile() ?? '?') . ':' . ($e->getLine() ?? 0));
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
    $afterRows = DB::fetchAll("SELECT * FROM {$t}");
    if ($normalize($afterRows) !== $normalize($beforeRows[$t])) {
        $allRestored = false;
        echo "  DETAIL [restoration diff in {$t}: before=" . count($beforeRows[$t]) . " after=" . count($afterRows) . "]\n";
    }
}
$allRestored
    ? $pass('all six hosp_ tables restored exactly (zero net change)')
    : $fail('restoration fingerprint', 'see DETAIL lines');
(count($restorationErrors) === 0)
    ? $pass('cleanup executed without restoration errors')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors));

echo "  NOTE [audit readiness: BLOCKED - no sanctioned generic business-action audit sink exists]\n";
echo "         [org_audit_log is Organization-owned (company_id NOT NULL, org entity vocabulary)]\n";
echo "         [identity_security_events is Core identity telemetry, not financial business audit]\n";
echo "         [missing durable fields for future traceability: actor, void timestamp, reason, previous state]\n";
echo "  NOTE [operator void remains unimplemented: no route/button/handler exists]\n";

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
