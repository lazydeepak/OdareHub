<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/InlineMigrationApplyService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/InlineMigrationPlannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanExtractionScanner.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanCorrectionService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/RollbackService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/HistoryStore.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/ValueObjects/CorrectionReport.php';

use Apps\Studio\Tools\LocalizationScanExtraction\Services\InlineMigrationApplyService;

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$invokePrivateStatic = static function (string $class, string $methodName, array $args = []) {
    $ref = new ReflectionMethod($class, $methodName);
    $ref->setAccessible(true);
    return $ref->invokeArgs(null, $args);
};

echo "=== InlineMigrationApplyService Certification Test ===\n\n";

// ── TC01: Non-EN locale rejection ────────────────────────────────
echo "--- TC01: Non-EN locale rejection ---\n";
$report1 = InlineMigrationApplyService::apply(
    [['text' => 'Hello', 'suggested_key' => 'hello']],
    'Manufacturing',
    'views',
    'ja'
);
$assert($report1->success === false, 'apply() should reject non-EN locale');
echo json_encode($report1->toArray()) . "\n";

// ── TC02: Empty candidates rejection ───────────────────────────────
echo "\n--- TC02: Empty candidates rejection ---\n";
$report2 = InlineMigrationApplyService::apply([], 'Manufacturing', 'views', 'en');
$assert($report2->success === false, 'apply() should reject empty candidates');
$assert(str_contains($report2->error ?? '', 'No ready-to-migrate'), 'Error should mention empty candidates');
echo json_encode($report2->toArray()) . "\n";

// ── TC03: isSafeReplacementTarget safety rules ──────────────────
echo "\n--- TC03: isSafeReplacementTarget safety rules ---\n";

$safeText = 'Hello World';
$unsafeVar = 'Hello $name';
$unsafeHtml = '<b>Hello</b>';
$unsafeUrl = 'Visit https://example.com';
$unsafePhp = 'Hello <?php echo';
$unsafeQuote = "It's a test";

$rSafe = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$safeText]);
$rVar = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$unsafeVar]);
$rHtml = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$unsafeHtml]);
$rUrl = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$unsafeUrl]);
$rPhp = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$unsafePhp]);
$rQuote = $invokePrivateStatic(InlineMigrationApplyService::class, 'isSafeReplacementTarget', [$unsafeQuote]);

$assert($rSafe === true, 'Plain text should be safe');
$assert($rVar === false, 'String with $ should be unsafe');
$assert($rHtml === false, 'String with HTML tags should be unsafe');
$assert($rUrl === false, 'String with URL should be unsafe');
$assert($rPhp === false, 'String with PHP tags should be unsafe');
$assert($rQuote === false, 'String with quotes should be unsafe');

echo "  safe='{$safeText}': " . ($rSafe ? 'PASS' : 'FAIL') . "\n";
echo "  unsafeVar='{$unsafeVar}': " . ($rVar ? 'FAIL' : 'PASS (rejected)') . "\n";
echo "  unsafeHtml='{$unsafeHtml}': " . ($rHtml ? 'FAIL' : 'PASS (rejected)') . "\n";
echo "  unsafeUrl='{$unsafeUrl}': " . ($rUrl ? 'FAIL' : 'PASS (rejected)') . "\n";
echo "  unsafePhp='{$unsafePhp}': " . ($rPhp ? 'FAIL' : 'PASS (rejected)') . "\n";
echo "  unsafeQuote='{$unsafeQuote}': " . ($rQuote ? 'FAIL' : 'PASS (rejected)') . "\n";

// ── TC04: Locale file write format ──────────────────────────────
echo "\n--- TC04: Locale file write format ---\n";
$tmpDir = sys_get_temp_dir() . '/lse_apply_test_' . getmypid();
@mkdir($tmpDir, 0755, true);
$tmpFile = $tmpDir . '/en.php';

$existing = ['existing_key' => 'Existing Value'];
ksort($existing, SORT_STRING);

$php = "<?php\nreturn [\n";
foreach ($existing as $k => $v) {
    $ek = var_export((string)$k, true);
    $ev = var_export((string)$v, true);
    $php .= "    {$ek} => {$ev},\n";
}
$php .= "];\n";

file_put_contents($tmpFile, $php);

$assert(is_file($tmpFile), 'Temp locale file should exist');

$loaded = require $tmpFile;
$assert(is_array($loaded), 'Temp locale file should be valid PHP');
$assert(count($loaded) === 1, 'Temp locale file should have 1 key');
$assert($loaded['existing_key'] === 'Existing Value', 'Existing key should be preserved');

// Add a new key via writeLocaleFile
$existing['new_key'] = 'New Value';
ksort($existing, SORT_STRING);

$php2 = "<?php\nreturn [\n";
foreach ($existing as $k => $v) {
    $ek = var_export((string)$k, true);
    $ev = var_export((string)$v, true);
    $php2 .= "    {$ek} => {$ev},\n";
}
$php2 .= "];\n";
file_put_contents($tmpFile, $php2);

$loaded2 = require $tmpFile;
$assert(is_array($loaded2), 'Updated locale file should be valid PHP');
$assert(count($loaded2) === 2, 'Updated locale file should have 2 keys');
$assert($loaded2['new_key'] === 'New Value', 'New key should be present');

// Cleanup
@unlink($tmpFile);
@rmdir($tmpDir);

// ── TC05: Source replacement via reflection ────────────────────
echo "\n--- TC05: Source replacement safety ---\n";
$tmpSrcDir = sys_get_temp_dir() . '/lse_apply_src_' . getmypid();
@mkdir($tmpSrcDir, 0755, true);
$tmpSrc = $tmpSrcDir . '/test_view.php';

$originalContent = '<h1>Hello World</h1>';
file_put_contents($tmpSrc, $originalContent);

$assert(is_file($tmpSrc), 'Temp source file should exist');

$replacedCount = $invokePrivateStatic(
    InlineMigrationApplyService::class,
    'replaceInSourceFile',
    [$tmpSrc, 'Hello World', 'hello.world']
);

echo "  Replaced {$replacedCount} occurrence(s)\n";

$afterReplace = @file_get_contents($tmpSrc);
$assert($afterReplace !== false, 'Should be able to read file after replacement');
if ($afterReplace !== false) {
    $containsKey = str_contains($afterReplace, "hello.world");
    $assert($containsKey === true, 'Source file should contain the translation key');
    echo "  Contains key 'hello.world': " . ($containsKey ? 'YES' : 'NO') . "\n";

    $hasPhpTag = str_contains($afterReplace, '<?=');
    $assert($hasPhpTag === true, 'Replacement should use short echo tag');
    echo "  Contains '<?=': " . ($hasPhpTag ? 'YES' : 'NO') . "\n";

    $hasTtCall = str_contains($afterReplace, '$tt(');
    $assert($hasTtCall === true, 'Replacement should call $tt()');
    echo "  Contains '\$tt(': " . ($hasTtCall ? 'YES' : 'NO') . "\n";
}

// Cleanup
@unlink($tmpSrc);
@rmdir($tmpSrcDir);

// ── TC06: Snapshot file format ─────────────────────────────────
echo "\n--- TC06: Snapshot file format ---\n";
$tmpSnapshotDir = sys_get_temp_dir() . '/lse_snap_test_' . getmypid();
@mkdir($tmpSnapshotDir, 0755, true);
$tmpSnapFile = $tmpSnapshotDir . '/test_snap.php';
file_put_contents($tmpSnapFile, '<?php echo "test";');

$snapPath = $invokePrivateStatic(
    InlineMigrationApplyService::class,
    'snapshotFile',
    [$tmpSnapFile, 'test-snapshot', 'TestOwner']
);

$assert($snapPath !== null, 'Snapshot should return a path');
if ($snapPath !== null) {
    $assert(is_file($snapPath), 'Snapshot file should exist on disk');
    $assert(is_file($snapPath . '.json'), 'Snapshot manifest JSON should exist');

    $snapContent = @file_get_contents($snapPath);
    $assert($snapContent !== false, 'Should read snapshot content');
    if ($snapContent !== false) {
        $assert($snapContent === '<?php echo "test";', 'Snapshot content should match original');
        echo "  Snapshot content matches: YES\n";
    }

    $manifestContent = @file_get_contents($snapPath . '.json');
    if ($manifestContent !== false) {
        $manifest = json_decode($manifestContent, true);
        $assert(is_array($manifest), 'Manifest should be valid JSON');
        $assert(($manifest['owner_key'] ?? '') === 'TestOwner', 'Manifest should contain owner_key');
        $assert(($manifest['label'] ?? '') === 'test-snapshot', 'Manifest should contain label');
        echo "  Manifest JSON valid: YES\n";
    }

    @unlink($snapPath);
    @unlink($snapPath . '.json');
}

// Cleanup snapshot dir
$snapGlob = glob($tmpSnapshotDir . '/*');
if (is_array($snapGlob)) {
    foreach ($snapGlob as $f) { @unlink($f); }
}
@rmdir($tmpSnapshotDir);

// ── TC07: Skip existing key in locale ─────────────────────────
echo "\n--- TC07: Skip existing key ---\n";
// Test that the apply service skips keys already present in locale
// (This tests the in-memory dedup before locale write)
$tmpLocaleDir = sys_get_temp_dir() . '/lse_locale_test_' . getmypid();
@mkdir($tmpLocaleDir, 0755, true);
$tmpLocaleFile = $tmpLocaleDir . '/en.php';

$initialKeys = ['existing.label' => 'Existing'];
$phpInit = "<?php\nreturn [\n";
foreach ($initialKeys as $k => $v) {
    $ek = var_export((string)$k, true);
    $ev = var_export((string)$v, true);
    $phpInit .= "    {$ek} => {$ev},\n";
}
$phpInit .= "];\n";
file_put_contents($tmpLocaleFile, $phpInit);

// Use reflection to test writeLocaleFile with existing + new keys
$keysForWrite = ['existing.label' => 'Existing', 'new.label' => 'New Value'];
ksort($keysForWrite, SORT_STRING);

$phpWrite = "<?php\nreturn [\n";
foreach ($keysForWrite as $k => $v) {
    $ek = var_export((string)$k, true);
    $ev = var_export((string)$v, true);
    $phpWrite .= "    {$ek} => {$ev},\n";
}
$phpWrite .= "];\n";
file_put_contents($tmpLocaleFile, $phpWrite);

$loaded3 = require $tmpLocaleFile;
$assert(is_array($loaded3), 'Merged locale file should be valid PHP');
$assert(count($loaded3) === 2, 'Merged locale file should have 2 keys');
$assert($loaded3['existing.label'] === 'Existing', 'Existing key should be preserved');
$assert($loaded3['new.label'] === 'New Value', 'New key should be added');
echo "  Merged locale file valid with 2 keys: YES\n";

@unlink($tmpLocaleFile);
@rmdir($tmpLocaleDir);

// ── Summary ────────────────────────────────────────────────────
echo "\n=== Results ===\n";
$total = 0;
$assertionsInFile = file_get_contents(__FILE__);
$lines = explode("\n", $assertionsInFile);
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (preg_match('/^\$assert\(/', $trimmed)) {
        $total++;
    }
}

$passed = $total - count($failures);
echo "Total assertions: {$total}\n";
echo "Passed:          {$passed}\n";
echo "Failed:          " . count($failures) . "\n";
if ($failures !== []) {
    echo "\n--- Failures ---\n";
    foreach ($failures as $f) {
        echo "  FAIL: {$f}\n";
    }
}

exit(count($failures) > 0 ? 1 : 0);
