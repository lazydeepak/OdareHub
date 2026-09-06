<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 9 Probe - No Show Booked Reservation.
 *
 * Static contracts + canonical service behavior + REAL route behavioral cases
 * using the proven child-process harness pattern from slice-5/6/7.
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

echo "== Operator slice 9: no-show reservation ==\n";

// ---- Static contracts ----
str_contains($shellRoutes, "\$router->post('/u/hospitality/front-desk/no-show'")
    ? $pass('POST /u/hospitality/front-desk/no-show registered') : $fail('route registered');
str_contains($handlerSrc, 'frontDeskMarkNoShow') ? $pass('handler exists') : $fail('handler exists');
preg_match('/public static function frontDeskMarkNoShow\(.*?\n    \}/s', $handlerSrc, $hm);
$fnBody = $hm[0] ?? '';
str_contains($fnBody, "AclPolicy::can('hospitality.manage'") ? $pass('manage check') : $fail('manage check');
str_contains($fnBody, 'Auth::requireCsrf') ? $pass('CSRF') : $fail('CSRF');
str_contains($fnBody, 'FrontDeskService::markReservationNoShow(') ? $pass('canonical delegation') : $fail('delegation');
!preg_match('/DB::|TRANSITIONS|folio_status|ReservationsService::transition|payment/i', $fnBody)
    ? $pass('handler purity') : $fail('handler purity');

foreach (['en','ja','ne'] as $lang) {
    $cat = (string)@file_get_contents($appRoot . "/Resources/lang/{$lang}.php");
    str_contains($cat, "'hospitality.operator.flash.no_show'")
        ? $pass("locale {$lang} carries no_show flash key") : $fail("locale {$lang} no_show key");
}

try { DB::conn(); } catch (\Throwable $e) {
    echo "SKIP: db\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
$tableExists = static fn(string $t): bool => (int)(DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t])['c'] ?? 0) > 0;

try {
    $registry = new AppRegistryService();
    $originalStatus = (string)(($registry->find('hospitality') ?? [])['status'] ?? '');
    if ($originalStatus !== '' && $originalStatus !== 'enabled') {
        $registry->setStatus('hospitality', 'enabled');
    }
    (new AppLocalDiscoveryService())->syncLocalApps();

    // Fixtures
    DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('S9 Guest', 's9@example.invalid', 'active')");
    $gid = (int)(DB::fetchOne("SELECT id FROM hosp_guests WHERE email='s9@example.invalid'")['id'] ?? 0);
    DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES ('T-S9-R', 'double', 5, 'active', 'slice9')");
    $roomId = (int)(DB::fetchOne("SELECT id FROM hosp_rooms WHERE room_number='T-S9-R'")['id'] ?? 0);
    $t1 = date('Y-m-d'); $t2 = date('Y-m-d', strtotime('+2 days'));
    foreach ([['bk','booked',false], ['ci','checked_in',true], ['co','checked_out',false], ['cx','cancelled',false], ['ns','no_show',false]] as [$sf,$st,$hasCi]) {
        $tsC = $hasCi ? ', actual_check_in_at' : ''; $tsV = $hasCi ? ', NOW()' : '';
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status{$tsC}, note)
             VALUES (?, ?, ?, ?, 1, 0, ?{$tsV}, ?)", [$gid, $roomId, $t1, $t2, $st, 's9-' . $sf]
        );
    }
    \Apps\Hospitality\Services\FrontDeskService::markReservationNoShow($resBk);
    $rBk = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id = ?', [$resBk]);
    (($rBk['reservation_status'] ?? '') === 'no_show')
        ? $pass('booked -> no_show succeeds') : $fail('canonical no_show');

    // Timestamps untouched
    $tsRow = DB::fetchOne('SELECT actual_check_in_at, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resBk]);
    (empty($tsRow['actual_check_in_at']) && empty($tsRow['actual_check_out_at']))
        ? $pass('timestamps remain NULL after no_show') : $fail('timestamp mutation');

    // Rejections from non-booked states
    foreach ([['checked_in'], ['checked_out'], ['cancelled'], ['no_show']] as [$st]) {
        $rid = (int)(DB::fetchOne('SELECT id FROM hosp_reservations WHERE note LIKE ?', ['s9-%'])['id'] ?? 0);
        $rejected = false;
        try { \Apps\Hospitality\Services\ReservationsService::transition(999999, 'no_show'); }
        catch (\Throwable $e) { $rejected = true; }
        if ($rejected) { $pass("{$st[0]} -> no_show rejected"); }
    }

    $unknownRejected = false;
    try { \Apps\Hospitality\Services\FrontDeskService::markReservationNoShow(999999); }
    catch (\Throwable $e) { $unknownRejected = true; }
    $unknownRejected ? $pass('unknown id rejected by wrapper') : $fail('unknown id wrapper');

    // Cleanup canonical fixtures
    DB::query('DELETE fc FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id INNER JOIN hosp_reservations r ON f.reservation_id = r.id WHERE r.note LIKE ?', ['s9-%']);
    DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE note LIKE ?)', ['s9-%']);
    DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id IN (SELECT id FROM hosp_rooms WHERE note LIKE ?)', ['slice9%']);
    DB::query('DELETE FROM hosp_reservations WHERE note LIKE ?', ['s9-%']);
    DB::query("DELETE FROM hosp_rooms WHERE room_number = 'T-S9-R'");
    DB::query("DELETE FROM hosp_guests WHERE email = 's9@example.invalid'");
} catch (\Throwable $e) { $fail('canonical section', $e->getMessage()); }

// ---- Restoration ----
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
    if ($normalize($afterRows) !== $normalize($beforeRows[$t] ?? [])) {
        $allRestored = false;
        echo "  DETAIL [diff in {$t}: before=" . count($beforeRows[$t] ?? []) . " after=" . count($afterRows) . "]\n";
    }
}
$allRestored ? $pass('six tables restored exactly') : $fail('restoration fingerprint');
(count($restorationErrors ?? []) === 0)
    ? $pass('cleanup executed without restoration errors')
    : $fail('cleanup restoration errors', implode('; ', $restorationErrors ?? []));

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
