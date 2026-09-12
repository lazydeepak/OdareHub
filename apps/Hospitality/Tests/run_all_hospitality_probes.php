<?php
declare(strict_types=1);

/**
 * Aggregate runner for Hospitality foundation + operator probes.
 *
 * Foundation group covers app/module/route/domain slices; operator group covers the
 * confined /u/{username}/hospitality contribution and action slices. Operator probes
 * are included so contributed operator behavior keeps regression coverage.
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
    'operator slice2 contribution' => 'apps/Hospitality/Tests/probe_operator_slice2_contribution.php',
    'operator slice3 view/route' => 'apps/Hospitality/Tests/probe_operator_slice3_view_route.php',
    'operator slice4 housekeeping action' => 'apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php',
    'operator slice5 front desk check-in' => 'apps/Hospitality/Tests/probe_operator_slice5_frontdesk_checkin.php',
    'operator slice6 front desk check-out' => 'apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php',
    'operator slice7 add charge' => 'apps/Hospitality/Tests/probe_operator_slice7_add_charge.php',
    'operator slice8 cancel' => 'apps/Hospitality/Tests/probe_operator_slice8_cancel.php',
    'operator slice9 no-show' => 'apps/Hospitality/Tests/probe_operator_slice9_no_show.php',
];

$passed = 0;
echo "== Hospitality Foundation + Operator Probe Suite ==\n";
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
