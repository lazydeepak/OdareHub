<?php
define('APP_ROOT', 'C:\Projects\Susankhya');

// Simulate the helpers we need to test
require_once APP_ROOT . '/app/Core/helpers.php';

echo "=== Testing Phase 1 Resolver ===\n\n";

// Test 1: Core locale still works
echo "Test 1: Core locale (en) - app.name\n";
$name = t('app.name');
echo "  Result: " . ($name ?: 'EMPTY') . "\n";
echo "  PASS: " . ($name !== '' && $name !== null ? 'YES' : 'NO') . "\n\n";

// Test 2: Owner locale (Shell) - Shell keys should now be resolvable
echo "Test 2: Owner locale (Shell operator.dashboard.title)\n";
$shellKey = t('operator.dashboard.title');
echo "  Result: " . ($shellKey ?: 'EMPTY') . "\n";
echo "  PASS: " . ($shellKey !== '' && $shellKey !== 'operator.dashboard.title' ? 'YES' : 'NO') . "\n\n";

// Test 3: Owner locale (Base plugin)
echo "Test 3: Owner locale (Base - ops.workspace_profiles.page_title)\n";
$baseKey = t('ops.workspace_profiles.page_title');
echo "  Result: " . ($baseKey ?: 'EMPTY') . "\n";
echo "  PASS: " . ($baseKey !== '' && $baseKey !== 'ops.workspace_profiles.page_title' ? 'YES' : 'NO') . "\n\n";

// Test 4: Owner locale (Manufacturing/Coverage)
echo "Test 4: Owner locale (Coverage)\n";
$covEn = load_locale('en');
$covKeys = array_filter(array_keys($covEn), fn($k) => str_starts_with($k, 'app.manufacturing.coverage.'));
echo "  Coverage keys in en: " . count($covKeys) . "\n";
foreach ($covKeys as $k) echo "    $k => " . $covEn[$k] . "\n";
echo "  PASS: " . (count($covKeys) > 0 ? 'YES' : 'NO') . "\n\n";

// Test 5: JA empty files should NOT override Core
echo "Test 5: Empty JA files should NOT blank Core\n";
$jaAll = load_locale('ja');
// Check if Core's ja still has content
$jaHasContent = count($jaAll) > 100; // Core has thousands
echo "  JA total keys: " . count($jaAll) . "\n";
echo "  PASS: " . ($jaHasContent ? 'YES (Core JA preserved)' : 'NO (JA was blanked!)') . "\n\n";

// Test 6: Performance - second call uses cache
echo "Test 6: Cache test\n";
$start = microtime(true);
$en1 = load_locale('en');
$time1 = microtime(true) - $start;
$start = microtime(true);
$en2 = load_locale('en');
$time2 = microtime(true) - $start;
echo "  First call: " . round($time1 * 1000, 2) . "ms\n";
echo "  Second call: " . round($time2 * 1000, 2) . "ms\n";
echo "  PASS: " . ($time2 < $time1 ? 'YES (cache works)' : 'Check cache') . "\n\n";

echo "=== Done ===\n";
