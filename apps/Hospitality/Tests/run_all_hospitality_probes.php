<?php
declare(strict_types=1);

/**
 * Aggregate runner for Hospitality foundation probes.
 *
 * Usage: php apps/Hospitality/Tests/run_all_hospitality_probes.php
 */

$root = dirname(__DIR__, 3);
$probes = [
    'slice1 registration' => 'apps/Hospitality/Tests/probe_slice1_registration.php',
    'slice2 migrations' => 'apps/Hospitality/Tests/probe_slice2_migrations.php',
    'slice3 modules' => 'apps/Hospitality/Tests/probe_slice3_modules.php',
    'slice4 routes/navigation' => 'apps/Hospitality/Tests/probe_slice4_routes_navigation.php',
    'slice5 rooms/guests CRUD' => 'apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php',
    'slice6 reservations lifecycle' => 'apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php',
    'slice7 front desk/folio' => 'apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php',
    'slice8 housekeeping board' => 'apps/Hospitality/Tests/probe_slice8_housekeeping_board.php',
];

$passed = 0;
echo "== Hospitality Foundation Probe Suite ==\n";
foreach ($probes as $label => $relativePath) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        echo "\nFAIL: {$label} missing at {$relativePath}\n";
        exit(1);
    }

    echo "\n-- {$label} --\n";
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path);
    passthru($command, $code);
    if ($code !== 0) {
        echo "\nFAIL: {$label} exited with {$code}\n";
        echo "Result: {$passed}/" . count($probes) . " probe groups passed\n";
        exit($code);
    }
    $passed++;
}

echo "\nResult: {$passed}/" . count($probes) . " probe groups passed\n";
