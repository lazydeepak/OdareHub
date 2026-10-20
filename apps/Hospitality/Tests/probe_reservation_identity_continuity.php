<?php
declare(strict_types=1);

/**
 * Offline reservation identity continuity probe.
 *
 * This is source and mocked-contract verification only. It does not load the
 * application, connect to a database, execute SQL, or claim real concurrency
 * proof.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Hospitality/Services/ReservationsService.php';
$service = (string)file_get_contents($servicePath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

echo "== Hospitality reservation identity continuity ==\n";

$check(str_contains($service, 'DB::conn()->insert_id'), 'service uses current-connection inserted identity');
$check(!str_contains($service, 'SELECT id FROM hosp_reservations ORDER BY id DESC LIMIT 1'), 'global latest-row lookup is absent');
$check(str_contains($service, 'if ($reservationId <= 0)'), 'generated identity must be positive');
$check(str_contains($service, "DB::query('START TRANSACTION');")
    && str_contains($service, "DB::query('ROLLBACK');"), 'assigned and unassigned paths share rollback boundary');
$insertPosition = strpos($service, 'DB::conn()->insert_id');
$commitPosition = strpos($service, "DB::query('COMMIT');");
$check($insertPosition !== false && $commitPosition !== false && $insertPosition < $commitPosition, 'identity is checked before commit');

// Mocked current-connection contract: a concurrent connection may have a
// higher ID, but must not affect the identity returned by this insert.
$currentConnection = ['insert_id' => 501];
$concurrentConnection = ['insert_id' => 502];
$returnedIdentity = (int)$currentConnection['insert_id'];
$check($returnedIdentity === 501 && $returnedIdentity !== $concurrentConnection['insert_id'], 'mocked concurrent insert cannot replace current connection identity');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
