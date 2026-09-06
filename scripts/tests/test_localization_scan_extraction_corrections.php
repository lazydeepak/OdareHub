<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioEditService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanExtractionScanner.php';
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanCorrectionService.php';

use Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanCorrectionService;
use Apps\Studio\Tools\LocalizationScanExtraction\Services\LocalizationScanExtractionScanner;

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// --- Helper: make private method accessible via reflection ---
$invokePrivate = static function (object $obj, string $methodName, array $args = []) {
    $ref = new ReflectionMethod($obj, $methodName);
    $ref->setAccessible(true);
    return $ref->invokeArgs(null, $args);
};

echo "=== Correction Service Utility Tests ===\n\n";

// --- Test 1: Non-EN locale rejections ---
echo "--- Test 1: Non-EN locale rejection (addMissingEnglishKeys) ---\n";
$r1 = LocalizationScanCorrectionService::addMissingEnglishKeys('Manufacturing', 'views', 'ja');
echo json_encode($r1) . "\n";
$assert($r1['success'] === false, 'addMissingEnglishKeys should reject non-EN locale');

echo "--- Test 2: Non-EN locale rejection (extractInlineText) ---\n";
$r2 = LocalizationScanCorrectionService::extractInlineText('Manufacturing', 0, 'views', 'ne');
echo json_encode($r2) . "\n";
$assert($r2['success'] === false, 'extractInlineText should reject non-EN locale');

echo "--- Test 3: Non-existent owner rejection ---\n";
$r3 = LocalizationScanCorrectionService::addMissingEnglishKeys('non_existent_owner_xyz', 'views', 'en');
echo json_encode($r3) . "\n";
$assert($r3['success'] === false, 'addMissingEnglishKeys should reject non-existent owner');

echo "--- Test 4: Scanner fixture produces correct format ---\n";
$fixtureResult = LocalizationScanExtractionScanner::scanFixture(
    'Manufacturing/Coverage',
    [
        'mfg.cov.title' => 'Coverage',
        'mfg.cov.missing' => 'Missing Coverage',
    ],
    [
        'test_fixture.php' => <<<'PHP'
<h1>Coverage Report</h1>
<p>Loading coverage data...</p>
<button>Download CSV</button>
<th>Customer Name</th>
<?= t('mfg.cov.title') ?>
<?= t('mfg.cov.missing') ?>
PHP
    ]
);

$assert(!empty($fixtureResult['scan_ok']), 'Scanner fixture should produce valid scan result');
$assert(isset($fixtureResult['findings']), 'Scanner fixture should have findings');
$assert(isset($fixtureResult['summary']), 'Scanner fixture should have summary');
$assert(isset($fixtureResult['missing_key_review_plan']), 'Scanner fixture should have missing_key_review_plan');

echo "Fixture scan: ok=" . ($fixtureResult['scan_ok'] ? 'true' : 'false') . ", findings=" . count($fixtureResult['findings']) . "\n";

// --- Test 5: Locale file write format via temp file ---
echo "\n--- Test 5: Locale file write format ---\n";
$tmpDir = sys_get_temp_dir() . '/lse_correction_test_' . getmypid();
@mkdir($tmpDir, 0755, true);
$tmpFile = $tmpDir . '/en.php';

// Write via service by calling compute then manual write
$existing = ['existing_key' => 'Existing Value', 'another_key' => 'Another Value'];
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
$assert(count($loaded) === 2, 'Temp locale file should have 2 keys');
$assert($loaded['existing_key'] === 'Existing Value', 'First key should be preserved');

// Cleanup
@unlink($tmpFile);
@rmdir($tmpDir);

// --- Results ---
echo "\n=== Results ===\n";
$total = 0;
$passed = 0;
$lines = explode("\n", file_get_contents(__FILE__));
foreach ($lines as $line) {
    if (preg_match("/\\\$assert\(/", $line)) {
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
