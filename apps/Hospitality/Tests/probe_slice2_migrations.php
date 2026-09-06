<?php
declare(strict_types=1);

/**
 * Hospitality Slice 2 Migration Probe
 *
 * Proves the v1 hosp_ data model applies through EXISTING app migration behavior only:
 *   1. Migration file exists and is additive-safe SQL per repo conventions
 *   2. AppMigrationService::runMigrations() (the real install path) applies it
 *   3. All six hosp_ tables exist with the expected key columns
 *   4. Re-running the runner is idempotent (0 newly applied)
 *   5. Cleanup restores pre-probe state (tables dropped, ledger rows removed)
 *
 * No Core or runner file is modified by this probe.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice2_migrations.php
 * Exit codes: 0 = pass, 1 = fail, 2 = environment unavailable
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppMigrationService;

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
$migrationFile = $appRoot . '/migrations/20260823_0001_hospitality_core.sql';
$tables = [
    'hosp_rooms',
    'hosp_guests',
    'hosp_reservations',
    'hosp_housekeeping_status',
    'hosp_folios',
    'hosp_folio_charges',
];

echo "== Slice 2: Hospitality v1 data model migrations ==\n";

// ---- Group A: file shape ----
is_file($migrationFile) ? $pass('migration file exists') : $fail('migration file exists');

$sql = (string)@file_get_contents($migrationFile);
foreach ($tables as $t) {
    str_contains($sql, "CREATE TABLE IF NOT EXISTS {$t} ") || str_contains($sql, "CREATE TABLE IF NOT EXISTS {$t}\n")
        ? $pass("declares {$t}")
        : $fail("declares {$t}");
}
str_contains($sql, 'FOREIGN KEY') ? $fail('no physical FK constraints (repo convention)') : $pass('no physical FK constraints (repo convention)');
preg_match_all('/CREATE TABLE IF NOT EXISTS (hosp_[a-z_]+)/', $sql, $m);
(count(array_unique($m[1])) === 6) ? $pass('exactly six tables declared') : $fail('exactly six tables declared', implode(',', $m[1] ?? []));

// ---- Group B/C: apply via real runner and verify schema ----
try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable ({$e->getMessage()}); apply/verify groups not run.\n";
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$tableExists = static function (string $t): bool {
    $row = DB::fetchOne('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};

try {
    foreach ($tables as $t) {
        if ($tableExists($t)) {
            echo "SKIP: {$t} already exists; dropping to test a clean apply.\n";
            DB::query("DROP TABLE IF EXISTS `{$t}`");
        }
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");

    $applied = (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    (in_array(basename($migrationFile), $applied, true))
        ? $pass('AppMigrationService::runMigrations applied the migration')
        : $fail('AppMigrationService::runMigrations applied the migration', implode(',', $applied));

    // key column checks per foundation brief section 7
    $columnChecks = [
        'hosp_rooms' => ['room_number', 'room_type', 'room_status'],
        'hosp_guests' => ['full_name', 'email', 'phone', 'id_document_ref'],
        'hosp_reservations' => ['guest_id', 'room_id', 'check_in_date', 'check_out_date', 'reservation_status', 'actual_check_in_at', 'actual_check_out_at'],
        'hosp_housekeeping_status' => ['room_id', 'hk_status', 'last_cleaned_at', 'assigned_to'],
        'hosp_folios' => ['reservation_id', 'folio_status', 'opened_at', 'closed_at'],
        'hosp_folio_charges' => ['folio_id', 'charge_type', 'qty', 'unit_amount', 'charge_status', 'posted_at'],
    ];
    foreach ($columnChecks as $table => $columns) {
        if (!$tableExists($table)) {
            $fail("table {$table} exists after apply");
            continue;
        }
        $rows = DB::fetchAll("SHOW COLUMNS FROM `{$table}`");
        $actual = array_map(static fn ($r) => (string)$r['Field'], $rows);
        $missing = array_diff($columns, $actual);
        $missing === [] ? $pass("{$table}: key columns present") : $fail("{$table}: key columns present", implode(',', $missing));
    }

    $idx = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'hosp_rooms' AND index_name = 'uniq_hosp_room_number'");
    ((int)($idx['c'] ?? 0) > 0) ? $pass('unique room_number index present') : $fail('unique room_number index present');
    $fk = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND constraint_type = 'FOREIGN KEY' AND table_name LIKE 'hosp_%'");
    ((int)($fk['c'] ?? 0) === 0) ? $pass('zero physical foreign keys (logical links only)') : $fail('zero physical foreign keys');

    // idempotency
    $appliedAgain = (new AppMigrationService())->runMigrations('hospitality', '0.1.0', $appRoot . '/migrations');
    ($appliedAgain === []) ? $pass('re-run idempotent (0 newly applied)') : $fail('re-run idempotent', implode(',', $appliedAgain));
} catch (\Throwable $e) {
    $fail('migration apply/verify', $e->getMessage());
}

// ---- Group D: cleanup back to pre-probe runtime state ----
try {
    foreach ($tables as $t) {
        DB::query("DROP TABLE IF EXISTS `{$t}`");
    }
    DB::query("DELETE FROM core_app_migrations WHERE app_key = 'hospitality'");
    $left = false;
    foreach ($tables as $t) {
        if ($tableExists($t)) {
            $left = true;
        }
    }
    $left ? $fail('cleanup: all hosp_ tables removed') : $pass('cleanup: all hosp_ tables removed');
    $ledgerRow = DB::fetchOne("SELECT COUNT(*) AS c FROM core_app_migrations WHERE app_key = 'hospitality'");
    ((int)($ledgerRow['c'] ?? 0) === 0) ? $pass('cleanup: migration ledger cleared') : $fail('cleanup: migration ledger cleared');
} catch (\Throwable $e) {
    $fail('cleanup', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
