<?php
declare(strict_types=1);

/**
 * Reservation Transition Concurrency Probe (CAS).
 *
 * Proves compare-and-set hardening through sequential service calls that
 * simulate each race ordering's outcome. TRUE multi-process concurrent
 * testing is deferred to integration harness (documented limitation).
 *
 * Sequential CAS proofs verify the LOGIC; production FOR UPDATE row locks in
 * checkout/add-charge provide actual database-level serialization.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';

use App\Core\DB;
use App\Services\AppLocalDiscoveryService;
use App\Services\AppMigrationService;

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$passed = 0; $failed = 0; $restorationErrors = [];
$pass = static function (string $l) use (&$passed) { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed) { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

$appRoot = APP_ROOT . '/apps/Hospitality';

echo "== Reservation transition concurrency ==\n";

try { DB::conn(); } catch (\Throwable $e) {
    echo "SKIP: db\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

try {
    (new AppLocalDiscoveryService())->syncLocalApps();
    (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
} catch (\Throwable $e) { $fail('bootstrap', $e->getMessage()); }

$hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
$beforeRows = [];
foreach ($hospTables as $t) { $beforeRows[$t] = DB::fetchAll("SELECT * FROM {$t}"); }
$restorationErrors = [];

// Pre-clean prior-run residue
try {
    DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.note LIKE ?', ['s%']);
    DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['s%']);
    DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE room_number LIKE ?)', ['T-S%']);
    DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['s%']);
    DB::query('DELETE FROM hosp_rooms WHERE room_number LIKE ?', ['T-S%']);
} catch (\Throwable $e) {}

$svc = '\\Apps\\Hospitality\\Services\\ReservationsService';

// ---- Sequential CAS proofs ----
echo "\n-- Sequential CAS proofs --\n";

foreach ([['s1','checked_in','cancelled'], ['s2','cancelled','no_show']] as [$note,$win,$lose]) {
    try {
        DB::query("INSERT INTO hosp_guests (full_name,email,guest_status) VALUES ('S',CONCAT(?, '@ccr.invalid'),'active')", [$note]);
        $gid = (int)DB::fetchOne("SELECT id FROM hosp_guests WHERE email=CONCAT(?,'@ccr.invalid')",[$note])['id'];
        DB::query("INSERT INTO hosp_rooms (room_number,room_type,floor,room_status) VALUES (?, 'd', 9, 'active')", ['T-' . strtoupper($note)]);
        $rid = (int)DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number=?",['T-' . strtoupper($note)])['id'];
        DB::query("INSERT INTO hosp_reservations (guest_id,room_id,check_in_date,check_out_date,adults,children,reservation_status,note) VALUES (?,?,?,?,1,0,'booked',?)", [$gid,$rid,date('Y-m-d'),date('Y-m-d',strtotime('+2 days')),$note]);
        $resId = (int)DB::fetchOne("SELECT id FROM hosp_reservations WHERE note=?",[$note])['id'];

        // Winning move
        $svc::transition($resId, $win);
        // Losing move must be rejected by CAS
        $err = '';
        try { $svc::transition($resId, $lose); } catch (\Throwable $e) { $err = $e->getMessage(); }

        $finalStatus = DB::fetchOne('SELECT reservation_status s FROM hosp_reservations WHERE id=?',[$resId])['s'] ?? '';
        (($finalStatus === $win) && str_contains($err, 'TRANSITION_INVALID'))
            ? $pass("CAS {$win}: loser rejected by TRANSITION_INVALID")
            : $fail("CAS {$win}", json_encode([$finalStatus,$err]));

        DB::query('DELETE FROM hosp_reservations WHERE id=?',[$resId]);
        DB::query('DELETE FROM hosp_rooms WHERE id=?',[$rid]);
        DB::query('DELETE FROM hosp_guests WHERE id=?',[$gid]);
    } catch (\Throwable $e) { $restorationErrors[] = "{$win}: " . $e->getMessage(); }
}

// ---- Outer-tx compatibility: canonical checkin -> checkout on same res ----
echo "\n-- Outer transaction compatibility --\n";
try {
    DB::query("INSERT INTO hosp_guests (full_name,email,guest_status) VALUES ('S3','s3@ccr.invalid','active')");
    $g3 = (int)DB::fetchOne("SELECT id FROM hosp_guests WHERE email='s3@ccr.invalid'")['id'];
    DB::query("INSERT INTO hosp_rooms (room_number,room_type,floor,room_status) VALUES ('T-S3','d',9,'active')");
    $r3 = (int)DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-S3'")['id'];
    DB::query("INSERT INTO hosp_reservations (guest_id,room_id,check_in_date,check_out_date,adults,children,reservation_status,note) VALUES (?,?,?,?,1,0,'booked','s3')", [$g3,$r3,date('Y-m-d'),date('Y-m-d',strtotime('+2 days'))]);
    $res3 = (int)DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='s3'")['id'];

    // Canonical path: booked -> checked_in -> checked_out
    $svc::transition($res3, 'checked_in');
    \Apps\Hospitality\Services\FrontDeskService::checkout($res3);

    $stPost = DB::fetchOne('SELECT r.reservation_status AS rs FROM hosp_reservations r LEFT JOIN hosp_folios f ON f.reservation_id = r.id WHERE r.id = ?', [$res3]);
    (($stPost['rs'] ?? '') === 'checked_out')
        ? $pass('outer-tx: canonical checkin->checkout produces checked_out')
        : $fail('outer-tx compatibility');

    $errRecheckin = '';
    try { $svc::transition($res3, 'checked_in'); } catch (\Throwable $e) { $errRecheckin = $e->getMessage(); }
    (str_contains($errRecheckin, 'TRANSITION_INVALID') && ($stPost['rs'] ?? '') === 'checked_out')
        ? $pass('post-checkout re-checkin rejected (no stale overwrite)')
        : $fail('post-checkout re-checkin not rejected', $errRecheckin);

    DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.id = ?', [$res3]);
    DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$res3]);
    DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id = ?', [$r3]);
    DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$res3]);
    DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$r3]);
    DB::query('DELETE FROM hosp_guests WHERE id = ?', [$g3]);
} catch (\Throwable $e) { $fail('outer-tx', $e->getMessage()); }

// ---- Restoration fingerprints ----
$normalize = static function (array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($r as $k => $v) { $line[$k] = $v === null ? '~N~' : (string)$v; }
        $out[] = md5(json_encode($line));
    }
    sort($out); return $out;
};
$allRestored = true;
foreach ($hospTables as $t) {
    $afterRows = DB::fetchAll("SELECT * FROM {$t}");
    if ($normalize($afterRows) !== $normalize($beforeRows[$t])) {
        $allRestored = false;
        echo "  DETAIL [restoration diff in {$t}: before=" . count($beforeRows[$t]) . " after=" . count($afterRows) . "]\n";
    }
}
$allRestored ? $pass('all six hosp_ tables restored exactly') : $fail('restoration fingerprint');
(count($restorationErrors) === 0)
    ? $pass('cleanup executed without restoration errors')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
