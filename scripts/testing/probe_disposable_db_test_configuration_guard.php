<?php
declare(strict_types=1);

/**
 * Read-only-in-system probe for DisposableDbTestConfigurationGuard.
 *
 * It uses temporary PHP configuration fixtures only. It never loads the
 * application DB layer, opens a socket, or executes SQL.
 */

require_once __DIR__ . '/DisposableDbTestConfigurationGuard.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};
$root = sys_get_temp_dir() . '/oh-auto1-042-' . bin2hex(random_bytes(5));
$appRoot = $root . '/app-root';
$storage = $appRoot . '/storage';
$config = $storage . '/db_config.php';
mkdir($storage, 0700, true);
$write = static function (array $values) use ($config): void {
    $export = var_export($values, true);
    file_put_contents($config, "<?php\nreturn {$export};\n");
};
$input = static function (string $name = 'oh_test_042_fixture', string $user = 'oh042_runner') use ($root, $appRoot): array {
    return [
        'repo' => dirname(__DIR__, 2),
        'app_root' => $appRoot,
        'resource_root' => $root,
        'host' => '127.0.0.1',
        'port' => '33142',
        'name' => $name,
        'user' => $user,
    ];
};
$valid = [
    'host' => '127.0.0.1',
    'port' => 33142,
    'name' => 'oh_test_042_fixture',
    'user' => 'oh042_runner',
    'pass' => 'synthetic-secret-only',
];

try {
    $write($valid);
    $snapshot = DisposableDbTestConfigurationGuard::authorize($input());
    $check(is_array($snapshot) && isset($snapshot['config_hash']), 'authorized fixture identity accepted');
    DisposableDbTestConfigurationGuard::assertUnchanged($snapshot);
    $check(true, 'unchanged authorized fixture remains valid');

    putenv('ERP_DB_NAME=installed_like_target');
    try { DisposableDbTestConfigurationGuard::authorize($input()); $check(false, 'contradictory ambient target fails closed'); }
    catch (Throwable) { $check(true, 'contradictory ambient target fails closed'); }
    putenv('ERP_DB_NAME');

    unlink($config);
    try { DisposableDbTestConfigurationGuard::authorize($input()); $check(false, 'missing config fails closed'); }
    catch (Throwable) { $check(true, 'missing config fails closed'); }

    $write(array_replace($valid, ['port' => 33143]));
    try { DisposableDbTestConfigurationGuard::authorize($input()); $check(false, 'contradictory port fails closed'); }
    catch (Throwable) { $check(true, 'contradictory port fails closed'); }

    $write(array_replace($valid, ['name' => 'erp_production']));
    try { DisposableDbTestConfigurationGuard::authorize($input()); $check(false, 'production-like database name fails closed'); }
    catch (Throwable) { $check(true, 'production-like database name fails closed'); }

    $write(array_replace($valid, ['user' => 'root']));
    try { DisposableDbTestConfigurationGuard::authorize($input('oh_test_042_fixture', 'root')); $check(false, 'privileged user fails closed'); }
    catch (Throwable) { $check(true, 'privileged user fails closed'); }

    $write($valid);
    $snapshot = DisposableDbTestConfigurationGuard::authorize($input());
    $write(array_replace($valid, ['name' => 'oh_test_042_redirected']));
    try { DisposableDbTestConfigurationGuard::assertUnchanged($snapshot); $check(false, 'post-authorization target change fails closed'); }
    catch (Throwable) { $check(true, 'post-authorization target change fails closed'); }

    $write($valid);
    try {
        DisposableDbTestConfigurationGuard::authorize([
            'repo' => dirname(__DIR__, 2),
            'app_root' => dirname(__DIR__, 2),
            'resource_root' => $root,
            'host' => '127.0.0.1',
            'port' => '33142',
            'name' => 'oh_test_042_fixture',
            'user' => 'oh042_runner',
        ]);
        $check(false, 'repository installed config target fails closed');
    } catch (Throwable) { $check(true, 'repository installed config target fails closed'); }

    $sourceFiles = [
        dirname(__DIR__, 2) . '/apps/Procurement/Tests/probe_procurement_concurrency.php',
        dirname(__DIR__, 2) . '/apps/Procurement/Tests/probe_behavioral_cancellation.php',
        dirname(__DIR__, 2) . '/apps/Procurement/Tests/probe_behavioral_request_cancel.php',
        dirname(__DIR__, 2) . '/apps/Procurement/Tests/probe_behavioral_supplier_update.php',
        dirname(__DIR__, 2) . '/apps/Manufacturing/Tests/probe_upstream_supply_transaction.php',
        dirname(__DIR__, 2) . '/apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_reservation_create_concurrency.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_reservation_transition_concurrency.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice1_registration.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice2_migrations.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice3_modules.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice4_routes_navigation.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_slice8_housekeeping_board.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_frontdesk_void_readiness.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_install_admin_lifecycle.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice2_contribution.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice5_frontdesk_checkin.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice7_add_charge.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice8_cancel.php',
        dirname(__DIR__, 2) . '/apps/Hospitality/Tests/probe_operator_slice9_no_show.php',
    ];
    foreach ($sourceFiles as $sourceFile) {
        $source = (string)file_get_contents($sourceFile);
        $guardPositions = array_filter([
            strpos($source, 'DisposableDbTestConfigurationGuard::authorize'),
            strpos($source, 'DisposableDbTestEntryPointGuard::authorize'),
        ], static fn ($position): bool => $position !== false);
        $guardPos = $guardPositions === [] ? false : min($guardPositions);
        $dbLayerPos = strpos($source, 'vendor/autoload.php');
        $bootstrapPos = $dbLayerPos === false ? strpos($source, 'passthru(') : $dbLayerPos;
        $check($guardPos !== false && $bootstrapPos !== false && $guardPos < $bootstrapPos, basename($sourceFile) . ' guards before DB layer load');
        $check(strpos($source, 'DB::') !== false, basename($sourceFile) . ' behavioral assertions remain present');
    }
    $runner = (string)file_get_contents(dirname(__DIR__, 2) . '/scripts/testing/run_business_integrity_regression.sh');
    $guardPos = strpos($runner, 'configuration-guard=PASS');
    $sqlPos = strpos($runner, 'CREATE DATABASE');
    $check($guardPos !== false && $sqlPos !== false && $guardPos < $sqlPos, 'runner validates configuration before disposable SQL');
    $hospitalityRunner = (string)file_get_contents(dirname(__DIR__, 2) . '/apps/Hospitality/Tests/run_all_hospitality_probes.php');
    $check(strpos($hospitalityRunner, 'DisposableDbTestEntryPointGuard::authorize') !== false, 'Hospitality aggregate runner has entry-point guard');
    $entryGuard = (string)file_get_contents(__DIR__ . '/DisposableDbTestEntryPointGuard.php');
    $check(strpos($entryGuard, 'DB::') === false, 'entry-point guard has no direct SQL authority');
    $check(strpos($entryGuard, 'register_shutdown_function') !== false && strpos($entryGuard, 'assertUnchanged') !== false, 'entry-point guard rechecks identity at teardown');
} finally {
    if (is_file($config)) { unlink($config); }
    if (is_dir($storage)) { rmdir($storage); }
    if (is_dir($appRoot)) { rmdir($appRoot); }
    if (is_dir($root)) { rmdir($root); }
}

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
