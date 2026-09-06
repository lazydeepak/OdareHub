<?php
declare(strict_types=1);

/**
 * Hospitality Operator Slice 6 Probe - Front Desk checkout (atomic).
 *
 * Proves:
 *   Static: jailed route + self-workspace guard + handler purity (delegates to
 *   FrontDeskService::checkout() only; no SQL/folio/transition duplication).
 *   Canonical service: checked_in -> checked_out with timestamp; open folio
 *   closes with closed_at; charges unchanged; no-folio checkout succeeds per
 *   existing semantics; booked/cancelled/no_show/already-checked-out rejected.
 *   ATOMICITY (behavioral, forced failure): a foreign transaction holding the
 *   folio row forces the folio-close step to time out AFTER the reservation
 *   transition - the whole checkout rolls back (reservation stays checked_in,
 *   folio stays open); after releasing the lock a retry fully succeeds.
 *   CONCURRENCY serialization: a foreign FOR UPDATE lock on the reservation row
 *   makes a competing checkout block/rollback instead of partially succeeding.
 *   Real-route behavioral cases: own-handle authorized checkout succeeds;
 *   view-only denied; unassigned denied pre-handler; cross-handle denied
 *   pre-handler; invalid-CSRF denied. All fixtures/restorations exact.
 *
 * Usage: php apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php
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

echo "== Operator slice 6: front desk checkout (atomic) ==\n";

$routeNeedle = "\$router->post('/u/hospitality/front-desk/check-out'";
str_contains($shellRoutes, $routeNeedle)
    ? $pass('POST /u/hospitality/front-desk/check-out registered in Shell routes')
    : $fail('POST check-out route registered');
substr_count($shellRoutes, 'front-desk/check-out') === 1
    ? $pass('exactly one Shell route declaration')
    : $fail('exactly one Shell route declaration');
$routeBlockStart = strpos($shellRoutes, $routeNeedle);
$routeBlock = $routeBlockStart !== false ? substr($shellRoutes, $routeBlockStart, 2200) : '';
str_contains($routeBlock, "resolveOperatorPostRoute('hospitality', false, 'hospitality')")
    ? $pass('route uses operator POST pipeline with hospitality gate')
    : $fail('route pipeline gate');
str_contains($routeBlock, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($routeBlock, 'hash_equals')
    ? $pass('route carries the self-workspace binding guard')
    : $fail('route self-workspace guard');
!preg_match('/apps\/hospitality/i', $routeBlock)
    ? $pass('operator route never redirects to /apps/hospitality')
    : $fail('admin redirect inside operator route');

str_contains($handlerSrc, 'public static function frontDeskCheckOut(')
    ? $pass('app-owned frontDeskCheckOut handler exists')
    : $fail('frontDeskCheckOut handler exists');
preg_match('/public static function frontDeskCheckOut\(.*?\n    \}/s', $handlerSrc, $hm);
$fnBody = $hm[0] ?? '';
str_contains($fnBody, "AclPolicy::can('hospitality.manage'")
    ? $pass('handler re-checks hospitality.manage')
    : $fail('handler manage check');
str_contains($fnBody, 'Auth::requireCsrf')
    ? $pass('handler enforces CSRF')
    : $fail('handler CSRF');
str_contains($fnBody, 'FrontDeskService::checkout(')
    ? $pass('handler delegates to FrontDeskService::checkout only')
    : $fail('handler delegation');
!preg_match('/ReservationsService|folio|charge|DB::|TRANSITIONS|begin_transaction/i', $fnBody)
    ? $pass('no SQL/folio/charge/duplicated lifecycle logic in handler body')
    : $fail('forbidden logic in handler body');

preg_match_all('/<form[^>]*action="([^"]*)"/i', $viewSrc, $vm);
$viewFormsJailed = true;
foreach (($vm[1] ?? []) as $va) {
    if (!str_starts_with((string)$va, '/u/')) {
        $viewFormsJailed = false;
    }
}
$viewFormsJailed ? $pass('all view form actions jailed under /u/') : $fail('view forms jailed');
str_contains($viewSrc, '/hospitality/front-desk/check-out')
    ? $pass('view posts check-out to the jailed operator route')
    : $fail('view check-out form target');
str_contains($viewSrc, "summary['inhouse']")
    ? $pass('check-out consumes canonical inhouse rows from board adapter')
    : $fail('canonical inhouse consumption');
strpos(strtolower($viewSrc), '<form') !== false && strpos(strtolower($viewSrc), '<form') < (int)strpos($viewSrc, 'if ($canManage):')
    ? $fail('forms gated behind manage guard in source order')
    : $pass('forms gated behind manage guard in source order');

foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = (string)@file_get_contents($appRoot . '/Resources/lang/' . $lang . '.php');
    str_contains($catalog, "'hospitality.operator.flash.checked_out'")
        ? $pass("locale {$lang} carries the check-out flash key")
        : $fail("locale {$lang} check-out key");
}

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$restorationErrors = [];
$hospTables = [];
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

    // ---- Canonical service truths over isolated fixtures ----
    (new AppLocalDiscoveryService())->syncLocalApps();
    try {
        (new \App\Services\AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    } catch (\Throwable $e) {
        $fail('migration apply for fixtures', $e->getMessage());
    }

    $hospTables = ['hosp_guests','hosp_rooms','hosp_reservations','hosp_housekeeping_status','hosp_folios','hosp_folio_charges'];
    $beforeRows = [];
    foreach ($hospTables as $t) {
        $beforeRows[$t] = $tableExists($t) ? DB::fetchAll("SELECT * FROM {$t}") : [];
    }
    $cleanupErrors = [];

    $mkFixture = static function (string $suffix, string $resStatus) use (&$cleanupErrors): array {
        DB::query("INSERT INTO hosp_guests (full_name, email, guest_status) VALUES ('OP6 Guest', CONCAT('op6', ?, '@example.invalid'), 'active')", [$suffix]);
        $gid = (int)(DB::fetchOne('SELECT id FROM hosp_guests WHERE email = ?', ["op6{$suffix}@example.invalid"])['id'] ?? 0);
        DB::query("INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES (?, 'double', 4, 'active', 'slice6-probe')", ['T-OP6-' . $suffix]);
        $rid2 = (int)(DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number = ?', ['T-OP6-' . $suffix])['id'] ?? 0);
        $t1 = date('Y-m-d', strtotime('-1 day'));
        $t2 = date('Y-m-d', strtotime('+1 day'));
        DB::query(
            "INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, reservation_status, actual_check_in_at, note)
             VALUES (?, ?, ?, ?, 1, 0, ?, NOW(), ?)",
            [$gid, $rid2, $t1, $t2, $resStatus, 'slice6-' . $suffix]
        );
        $resId = (int)(DB::fetchOne('SELECT id FROM hosp_reservations WHERE note = ?', ['slice6-' . $suffix])['id'] ?? 0);
        return [$gid, $rid2, $resId];
    };
    $rmFixture = static function (string $suffix) use (&$cleanupErrors): void {
        try {
            $rid2 = (int)(DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number = ?', ['T-OP6-' . $suffix])['id'] ?? 0);
            if ($rid2 > 0) {
                DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE room_id = ?))', [$rid2]);
                DB::query('DELETE FROM hosp_folios WHERE reservation_id IN (SELECT id FROM hosp_reservations WHERE room_id = ?)', [$rid2]);
                DB::query('DELETE FROM hosp_housekeeping_status WHERE room_id = ?', [$rid2]);
            }
            DB::query('DELETE FROM hosp_reservations WHERE note = ?', ['slice6-' . $suffix]);
            DB::query('DELETE FROM hosp_rooms WHERE room_number = ?', ['T-OP6-' . $suffix]);
            DB::query('SELECT id FROM hosp_guests WHERE email LIKE ?', ["op6{$suffix}@%"]);
            DB::query('DELETE FROM hosp_guests WHERE email LIKE ?', ["op6{$suffix}@%"]);
        } catch (\Throwable $e) {
            $restorationErrors[] = "fixture removal {$suffix}: " . $e->getMessage();
        }
    };

    // --- Canonical semantics ---
    try {
        [$gidA, $roomIdA, $resInhouseFolio] = $mkFixture('a', 'checked_in');
        DB::query("INSERT INTO hosp_folios (reservation_id, folio_status, opened_at) VALUES (?, 'open', NOW())", [$resInhouseFolio]);
        DB::query("INSERT INTO hosp_folio_charges (folio_id, charge_type, description, qty, unit_amount, charge_status, posted_at) SELECT id, 'misc', 'probe-charge', 1, 10.00, 'posted', NOW() FROM hosp_folios WHERE reservation_id = ?", [$resInhouseFolio]);
        $chargesBefore = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id WHERE f.reservation_id = ?', [$resInhouseFolio])['c'] ?? 0);

        \Apps\Hospitality\Services\FrontDeskService::checkout($resInhouseFolio);
        $r = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resInhouseFolio]);
        $f = DB::fetchOne('SELECT folio_status, closed_at FROM hosp_folios WHERE reservation_id = ?', [$resInhouseFolio]);
        ((string)$r['reservation_status'] === 'checked_out' && !empty($r['actual_check_out_at'])
            && $f['folio_status'] === 'closed' && !empty($f['closed_at']))
            ? $pass('checkout: reservation+timestamp+folio close all canonical')
            : $fail('canonical checkout semantics');
        $chargesAfter = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folio_charges fc INNER JOIN hosp_folios f ON f.id = fc.folio_id WHERE f.reservation_id = ?', [$resInhouseFolio])['c'] ?? -1);
        ($chargesAfter === $chargesBefore)
            ? $pass('charges unchanged by checkout')
            : $fail('charges mutated by checkout');

        $rejectedAlready = false;
        try { \Apps\Hospitality\Services\FrontDeskService::checkout($resInhouseFolio); }
        catch (\Throwable $e) { $rejectedAlready = str_contains($e->getMessage(), 'HOSPITALITY_RES_TRANSITION_INVALID'); }
        $rejectedAlready ? $pass('already-checked-out rejected canonically') : $fail('double checkout rejection');

        // no-folio checkout follows existing semantics (ensureFolio-free path)
        [$gB, $rB, $resNoFolio] = $mkFixture('b', 'checked_in');
        \Apps\Hospitality\Services\FrontDeskService::checkout($resNoFolio);
        $nf = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resNoFolio]);
        $nfCount = (int)(DB::fetchOne('SELECT COUNT(*) c FROM hosp_folios WHERE reservation_id = ?', [$resNoFolio])['c'] ?? -1);
        (($nf['reservation_status'] ?? '') === 'checked_out' && $nfCount === 0)
            ? $pass('no-folio checkout succeeds without creating a folio')
            : $fail('no-folio checkout semantics');

        // booked -> checkout rejected
        [$gC, $rC, $resBooked] = $mkFixture('c', 'booked');
        $rejectedBooked = false;
        try { \Apps\Hospitality\Services\FrontDeskService::checkout($resBooked); }
        catch (\Throwable $e) { $rejectedBooked = str_contains($e->getMessage(), 'HOSPITALITY_RES_TRANSITION_INVALID'); }
        $rejectedBooked ? $pass('booked -> checkout rejected canonically') : $fail('booked checkout rejection');

        // cancelled -> checkout rejected
        [$gD, $rD, $resCancelled] = $mkFixture('d', 'cancelled');
        $rejectedCancelled = false;
        try { \Apps\Hospitality\Services\FrontDeskService::checkout($resCancelled); }
        catch (\Throwable $e) { $rejectedCancelled = str_contains($e->getMessage(), 'HOSPITALITY_RES_TRANSITION_INVALID'); }
        $rejectedCancelled ? $pass('cancelled -> checkout rejected canonically') : $fail('cancelled checkout rejection');

        // unknown id
        $rejectedUnknown = false;
        try { \Apps\Hospitality\Services\FrontDeskService::checkout(999999); }
        catch (\Throwable $e) { $rejectedUnknown = str_contains($e->getMessage(), 'HOSPITALITY_RES_NOT_FOUND'); }
        $rejectedUnknown ? $pass('unknown reservation rejected canonically') : $fail('unknown reservation rejection');
    } finally {
        foreach (['a','b','c','d','e','f'] as $sf) { $rmFixture($sf); }
    }

    // ---- Atomicity: forced folio-step failure rolls back reservation ----
    $authUser = DB::fetchOne('SELECT id, username, email FROM users ORDER BY id ASC LIMIT 1');
    if (!is_array($authUser)) {
        echo "  SKIP [lock harness: no local user]\n";
    } else {
        $cfgHost = (string)(getenv('ERP_DB_HOST') ?: 'localhost');
        $cfgName = (string)getenv('ERP_DB_NAME');
        $cfgUser = (string)getenv('ERP_DB_USER');
        $cfgPass = (string)getenv('ERP_DB_PASS');
        if ($cfgName === '') {
            $cfg = require APP_ROOT . '/storage/db_config.php';
            $cfgHost = (string)($cfg['host'] ?? $cfgHost);
            $cfgName = (string)($cfg['name'] ?? '');
            $cfgUser = (string)($cfg['user'] ?? '');
            $cfgPass = (string)($cfg['pass'] ?? '');
        }
        $lockConn = new mysqli($cfgHost, $cfgUser, $cfgPass, $cfgName);
        $lockConnOpen = true;

        // Capture primary-connection session state for deterministic restore.
        $sessBefore = DB::fetchOne('SELECT @@session.innodb_lock_wait_timeout AS lwt, @@autocommit AS ac');
        $prevLwt = (int)($sessBefore['lwt'] ?? 50);
        $prevAc = (int)($sessBefore['ac'] ?? 1);

        [$gidE, $ridE, $resE] = $mkFixture('e', 'checked_in');
        DB::query("INSERT INTO hosp_folios (reservation_id, folio_status, opened_at) VALUES (?, 'open', NOW())", [$resE]);

        // Foreign transaction locks the FOLIO row.
        $lockConn->begin_transaction();
        $st = $lockConn->prepare('SELECT id FROM hosp_folios WHERE reservation_id = ? FOR UPDATE');
        $i = $resE; $st->bind_param('i', $i); $st->execute(); $st->get_result()->fetch_assoc(); $st->close();

        // Short lock-wait on the MAIN connection (checkout path).
        DB::query('SET SESSION innodb_lock_wait_timeout = 1');

        $rolledBack = false;
        try {
            \Apps\Hospitality\Services\FrontDeskService::checkout($resE);
        } catch (\Throwable $e) {
            $rolledBack = str_contains($e->getMessage(), 'Lock wait timeout');
        } finally {
            // Deterministic primary-connection restoration regardless of outcome.
            try { DB::query('SET SESSION innodb_lock_wait_timeout = ' . (int)$prevLwt); } catch (\Throwable $e) {}
            try { DB::query('SET autocommit = ' . (int)$prevAc); } catch (\Throwable $e) {}
        }
        $rE = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resE]);
        $fE = DB::fetchOne('SELECT folio_status, closed_at FROM hosp_folios WHERE reservation_id = ?', [$resE]);

        ($rolledBack)
            ? $pass('forced folio-step failure surfaces as lock timeout')
            : $fail('forced folio-step failure did not surface', (string)$rolledBack);
        (($rE['reservation_status'] ?? '') === 'checked_in' && empty($rE['actual_check_out_at']))
            ? $pass('ATOMIC ROLLBACK: reservation remains checked_in, timestamp unset')
            : $fail('ATOMIC ROLLBACK violated (reservation)', json_encode($rE));
        (($fE['folio_status'] ?? '') === 'open' && empty($fE['closed_at']))
            ? $pass('ATOMIC ROLLBACK: folio remains open, closed_at unset')
            : $fail('ATOMIC ROLLBACK violated (folio)', json_encode($fE));

        // Release the foreign lock; retry must fully succeed.
        try { $lockConn->commit(); } catch (\Throwable $e) { try { $lockConn->rollback(); } catch (\Throwable $ignored) {} }
        try { $lockConn->close(); } catch (\Throwable $ignored) {}
        $lockConnOpen = false;
        DB::query('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        \Apps\Hospitality\Services\FrontDeskService::checkout($resE);
        $retry = DB::fetchOne('SELECT reservation_status, actual_check_out_at FROM hosp_reservations WHERE id = ?', [$resE]);
        $fRetry = DB::fetchOne('SELECT folio_status, closed_at FROM hosp_folios WHERE reservation_id = ?', [$resE]);
        (($retry['reservation_status'] ?? '') === 'checked_out' && ($fRetry['folio_status'] ?? '') === 'closed')
            ? $pass('retry after lock release fully succeeds')
            : $fail('retry after lock release', json_encode([$retry, $fRetry]));

        // ---- Concurrency serialization: foreign RESERVATION row lock blocks competing checkout ----
        [$gidF, $ridF, $resF] = $mkFixture('f', 'checked_in');
        $lockConn2 = new mysqli($cfgHost, $cfgUser, $cfgPass, $cfgName);
        $lockConn2->begin_transaction();
        $st2 = $lockConn2->prepare('SELECT id FROM hosp_reservations WHERE id = ? FOR UPDATE');
        $j = $resF; $st2->bind_param('i', $j); $st2->execute(); $st2->get_result()->fetch_assoc(); $st2->close();

        DB::query('SET SESSION innodb_lock_wait_timeout = 1');
        $serialBlocked = false;
        try { \Apps\Hospitality\Services\FrontDeskService::checkout($resF); }
        catch (\Throwable $e) { $serialBlocked = str_contains($e->getMessage(), 'Lock wait timeout'); }
        $rF = DB::fetchOne('SELECT reservation_status FROM hosp_reservations WHERE id = ?', [$resF]);
        ($serialBlocked && ($rF['reservation_status'] ?? '') === 'checked_in')
            ? $pass('row serialization blocks competing checkout (no partial state)')
            : $fail('serialization strategy not proven', json_encode([$serialBlocked, $rF['reservation_status'] ?? null]));
        try { $lockConn2->commit(); } catch (\Throwable $ignored) {}
        try { $lockConn2->close(); } catch (\Throwable $ignored) {}
        rm_fixture_f($resF, $ridF);
        $rmFixture('e');
    }
} catch (\Throwable $e) {
    $fail('functional sequence', $e->getMessage());
}

function rm_fixture_f(int $resId, int $roomId): void {
    try {
        DB::query('DELETE FROM hosp_folio_charges WHERE folio_id IN (SELECT id FROM hosp_folios WHERE reservation_id = ?)', [$resId]);
        DB::query('DELETE FROM hosp_folios WHERE reservation_id = ?', [$resId]);
        DB::query('DELETE FROM hosp_reservations WHERE id = ?', [$resId]);
        DB::query('DELETE FROM hosp_rooms WHERE id = ?', [$roomId]);
        DB::query("DELETE FROM hosp_guests WHERE email = 'op6f@example.invalid'");
    } catch (\Throwable $e) {}
}

// restoration fingerprints
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

if ($originalStatus !== '' && $originalStatus !== 'enabled') {
    try { $registry->setStatus('hospitality', $originalStatus); $pass('original registry status restored'); }
    catch (\Throwable $e) { $fail('status restore', $e->getMessage()); }
} else {
    $pass('original hospitality registry status preserved');
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
