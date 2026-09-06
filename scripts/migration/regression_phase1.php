<?php
define('APP_ROOT', 'C:\Projects\Susankhya');
require_once APP_ROOT . '/app/Core/helpers.php';

echo "=== Regression Check: Core keys still work ===\n";
$tests = [
    'app.name' => null,
    'auth.email' => null,
    'common.record_one' => null,
    'common.record_other' => null,
    'auth.password' => null,
    'app.manufacturing.coverage.index.kpi.title' => null,
    'operator.dashboard.title' => 'Dashboard',
    'ops.workspace_profiles.page_title' => 'Workspace Profile Catalog',
    'nav.daily_orders' => 'Daily Orders',
];

$failures = 0;
foreach ($tests as $key => $expected) {
    $val = t($key);
    $status = 'OK';
    if ($val === $key) {
        $status = 'UNRESOLVED (returned key itself)';
        $failures++;
    } elseif ($val === '' || $val === null) {
        $status = 'EMPTY';
        $failures++;
    } elseif ($expected !== null && $val !== $expected) {
        $status = "UNEXPECTED: got '$val', expected '$expected'";
        $failures++;
    }
    echo "  $key => '$val' [$status]\n";
}

echo "\n=== Owner key conflicts check ===\n";
$en = load_locale('en');
// Check a Core key that might have per-owner override
$coreOnlyKeys = ['app.name', 'auth.email', 'auth.password', 'common.record_one'];
$conflicts = 0;
foreach ($coreOnlyKeys as $k) {
    $val = $en[$k] ?? null;
    if ($val === null) {
        echo "  WARN: Core key '$k' missing\n";
        $conflicts++;
    }
}

echo "\n=== Count checks ===\n";
echo "  EN total keys: " . count($en) . " (Core = ~6500, should be >= 6500)\n";

echo "\n" . ($failures === 0 ? 'ALL PASS' : "FAILURES: $failures") . "\n";
