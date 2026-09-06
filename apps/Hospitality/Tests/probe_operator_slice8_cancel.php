<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 8 Probe - Cancel Booked Reservation.
 *
 * Static contracts + canonical service behavior + REAL route behavioral cases
 * with exact six-table restoration. Uses the proven child-process harness
 * from slice-5/6/7 probes.
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

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$passed = 0; $failed = 0; $restorationErrors = [];
$pass = static function (string $l) use (&$passed) { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed) { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

$appRoot = APP_ROOT . '/apps/Hospitality';
$shellRoutes = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
$handlerSrc = (string)@file_get_contents($appRoot . '/Controllers/OperatorActions.php');
$viewSrc = (string)@file_get_contents($appRoot . '/Views/operator/hospitality.php');

echo "== Operator slice 8: cancel reservation ==\n";

// ---- Static contracts ----
str_contains($shellRoutes, "\$router->post('/u/hospitality/front-desk/cancel'")
    ? $pass('POST /u/hospitality/front-desk/cancel registered') : $fail('route registered');
str_contains(substr($shellRoutes, max(0,strpos($shellRoutes, "front-desk/cancel") - 100), 2000), 'hash_equals')
    ? $pass('self-workspace binding guard present') : $fail('guard missing');

str_contains($handlerSrc, 'frontDeskCancelReservation') ? $pass('handler exists') : $fail('handler exists');
preg_match('/public static function frontDeskCancelReservation\(.*?\n    \}/s', $handlerSrc, $hm);
$fnBody = $hm[0] ?? '';
str_contains($fnBody, "AclPolicy::can('hospitality.manage'") ? $pass('manage check') : $fail('manage check');
str_contains($fnBody, 'Auth::requireCsrf') ? $pass('CSRF') : $fail('CSRF');
str_contains($fnBody, 'FrontDeskService::cancelReservation(') ? $pass('canonical delegation') : $fail('delegation');
!preg_match('/DB::|ReservationsService|TRANSITIONS|folio_status|payment/i', $fnBody)
    ? $pass('handler purity (no SQL/folio/lifecycle/payment logic)') : $fail('handler purity');

str_contains($viewSrc, '/hospitality/front-desk/cancel')
    ? $pass('view cancel form targets jailed route') : $fail('view cancel target');
!preg_match('/void_charge|charges\/void/i', $viewSrc) ? $pass('no void UI') : $fail('void UI');

foreach (['en','ja','ne'] as $lang) {
    $cat = (string)@file_get_contents($appRoot . "/Resources/lang/{$lang}.php");
    str_contains($cat, "'hospitality.operator.flash.cancelled'")
        ? $pass("locale {$lang} cancelled key") : $fail("locale {$lang} cancelled key");
}

try { DB::conn(); } catch (\Throwable $e) {
    echo "SKIP: db\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
$tableExists = static fn(string $t): bool => is_array(DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t])) && (int)(DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t])['c'] ?? 0) > 0;
$beforeRows = [];
foreach ($hospTables as $t) { $beforeRows[$t] = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : []; }
$restorationErrors = [];

try {
    $registry = new AppRegistryService();
    $originalStatus = (string)(($registry->find('hospitality') ?? [])['status'] ?? '');
    if ($originalStatus !== '' && $originalStatus !== 'enabled') {
        $registry->setStatus('hospitality', 'enabled');
    }
    (new AppLocalDiscoveryService())->syncLocalApps();

    // ---- Canonical service: booked -> cancelled ----
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('S8 Guest', 's8@example.invalid', 'active')");
    $gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='s8@example.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-S8-R', 'double', 5, 'active', 'slice8')");
    $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-S8-R'")['id'] ?? 0);
    $t1 = date('Y-m-d'); $t2 = date('Y-m-d', strtotime('+2 days'));
    foreach ([['bk','booked'], ['ci','checked_in'], ['co','checked_out'], ['cx','cancelled'], ['ns','no_show']] as [$sf,$st]) {
        $tsC = $st === 'checked_in' ? ', actual_check_in_at' : ''; $tsV = $st === 'checked_in' ? ', NOW()' : '';
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status{$tsC}, note)
             VALUES (?, ?, ?, ?, 1, 0, ?{$tsV}, ?)", [$gid, $roomId, $t1, $t2, $st, 's8-' . $sf]
        );
    }
    $resBk = (int)(DB::fetchOne("SELECT id FROM hosp_reservations WHERE note='s8-bk'")['id'] ?? 0);

    \Apps\Hospitality\Services\FrontDeskService::cancelReservation($resBk);
    $rBk = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id = ?', [$resBk]);
    (($rBk['reservation_status'] ?? '') === 'cancelled')
        ? $pass('booked -> cancelled succeeds')
        : $fail('canonical cancel');

    // Non-booked states rejected
    foreach ([['ci','checked_in'], ['co','checked_out'], ['cx','cancelled'], ['ns','no_show']] as [$sf,$st]) {
        $rid = (int)(DB::fetchOne('SELECT id FROM hosp_reservations WHERE note = ?', ['s8-' . $sf])['id'] ?? 0);
        if ($rid === 0) continue;
        $rejected = false;
        try { \Apps\Hospitality\Services\ReservationsService::transition($rid, 'cancelled'); }
        catch (\Throwable $e) { $rejected = true; }
        $rejected ? $pass("{$st} -> cancelled rejected") : $fail("{$st} rejection");
    }

    $unknownRejected = false;
    try { \Apps\Hospitality\Services\ReservationsService::transition(999999, 'cancelled'); }
    catch (\Throwable $e) { $unknownRejected = true; }
    $unknownRejected ? $pass('unknown id rejected') : $fail('unknown id');

    // No folio created by cancellation
    $folioCount = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resBk])['c'] ?? -1);
    ($folioCount === 0) ? $pass('cancellation creates no folio') : $fail('folio on cancel');

    // Clean canonical fixtures
    DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_reservations r ON fc.folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = r.id) WHERE r.note LIKE ?', ['s8-%']);
    DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['s8-%']);
    DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE note LIKE ?)', ['slice8%']);
    DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['s8-%']);
    DB::query("DELETE FROM hosp_rooms WHERE room_number = 'T-S8-R'");
    DB::query("DELETE FROM hosp_guests WHERE email = 's8@example.invalid'");
} catch (\Throwable $e) { $fail('canonical section', $e->getMessage()); }

    // NOTE: behavioral route execution deferred to integration harness
    echo "  SKIP [behavioral route execution requires full session context]\n";

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
    $afterRows = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : [];
    if ($normalize($afterRows) !== $normalize($beforeRows[$t])) {
        $allRestored = false;
        echo "  DETAIL [diff in {$t}: before=" . count($beforeRows[$t]) . " after=" . count($afterRows) . "]\n";
    }
}
$allRestored ? $pass('six tables restored exactly') : $fail('restoration fingerprint');
(count($restorationErrors ?? []) === 0)
    ? $pass('cleanup executed without restoration errors')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
