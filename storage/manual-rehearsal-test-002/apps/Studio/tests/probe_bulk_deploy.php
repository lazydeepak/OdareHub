<?php
declare(strict_types=1);

/**
 * Engineering Workspaces — Bulk Deploy Missing Templates Probe
 *
 * 12 scenarios testing collectApplicableWorkspaceKeys and
 * bulkCreateMissingDocuments. Tests assert service-level results
 * (counts, status codes, correct grouping) without depending on
 * fixture engineering paths, since the service writes to the
 * real APP_ROOT/engineering/ directory.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceProvisioningService.php';

use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceProvisioningService;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function p_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function p_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function p_assert_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected string containing ' . var_export($needle, true) . PHP_EOL;
}

// Cleanup helper for test workspaces created in the real engineering dir
$p_cleanWs = static function (string $wsKey): void {
    $dir = APP_ROOT . '/engineering/' . $wsKey;
    if (!is_dir($dir)) {
        return;
    }
    $entries = scandir($dir);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $p = $dir . '/' . $entry;
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }
    @rmdir($dir);
};

echo "── Engineering Workspace Bulk Deploy Probe ──\n\n";

// ════════════════════════════════════════════════════════════
// TC01 — collectApplicableWorkspaceKeys returns keys from owner discovery
// ════════════════════════════════════════════════════════════
$ownerKeys = [
    ['owner_key' => 'Manufacturing/Products', 'owner_type' => 'module'],
    ['owner_key' => 'Manufacturing/Coverage', 'owner_type' => 'module'],
    ['owner_key' => 'Platform/Organization', 'owner_type' => 'system'],
];
$resultKeys = EngineeringWorkspaceProvisioningService::collectApplicableWorkspaceKeys($ownerKeys);
p_assert_true(in_array('Manufacturing/Products', $resultKeys, true), 'TC01a. owner Manufacturing/Products included');
p_assert_true(in_array('Manufacturing/Coverage', $resultKeys, true), 'TC01b. owner Manufacturing/Coverage included');
p_assert_true(in_array('Platform/Organization', $resultKeys, true), 'TC01c. owner Platform/Organization included');
p_assert_true(count($resultKeys) >= 3, 'TC01d. at least 3 keys (may include existing engineering dirs)');

// ════════════════════════════════════════════════════════════
// TC02 — collectApplicableWorkspaceKeys deduplicates across sources
// ════════════════════════════════════════════════════════════
$coverageRows = [
    ['workspace_key' => 'Manufacturing/Products', 'state' => 'linked_valid'],
    ['workspace_key' => 'ExtraModule/Foo', 'state' => 'initialization_required'],
];
$resultKeys2 = EngineeringWorkspaceProvisioningService::collectApplicableWorkspaceKeys($ownerKeys, $coverageRows);
p_assert_true(in_array('Manufacturing/Products', $resultKeys2, true), 'TC02a. Manufacturing/Products present (deduplicated)');
p_assert_true(in_array('Manufacturing/Coverage', $resultKeys2, true), 'TC02b. Manufacturing/Coverage present');
p_assert_true(in_array('ExtraModule/Foo', $resultKeys2, true), 'TC02c. ExtraModule/Foo from coverage');
p_assert_true(count($resultKeys2) >= 4, 'TC02d. at least 4 unique keys (may include existing engineering dirs)');

// ════════════════════════════════════════════════════════════
// TC03 — collectApplicableWorkspaceKeys excludes reserved template roots
// ════════════════════════════════════════════════════════════
$keysWithTemplate = [
    ['owner_key' => 'Manufacturing/Products', 'owner_type' => 'module'],
    ['owner_key' => '_templates', 'owner_type' => 'system'],
    ['owner_key' => '_template', 'owner_type' => 'system'],
    ['owner_key' => '_templates/MyTemplate', 'owner_type' => 'system'],
];
$resultKeys3 = EngineeringWorkspaceProvisioningService::collectApplicableWorkspaceKeys($keysWithTemplate);
p_assert_true(!in_array('_templates', $resultKeys3, true), 'TC03a. _templates excluded');
p_assert_true(!in_array('_template', $resultKeys3, true), 'TC03b. _template excluded');
p_assert_true(!in_array('_templates/MyTemplate', $resultKeys3, true), 'TC03c. _templates/MyTemplate excluded');
p_assert_true(in_array('Manufacturing/Products', $resultKeys3, true), 'TC03d. Manufacturing/Products still present after exclusion');

// ════════════════════════════════════════════════════════════
// TC04 — unsafe/traversal keys are passed through collect but rejected
//        by validateDeployWorkspaceKey in bulkCreateMissingDocuments
// ════════════════════════════════════════════════════════════
$keysWithUnsafe = [
    ['owner_key' => '../etc/passwd', 'owner_type' => 'module'],
    ['owner_key' => '/absolute/path', 'owner_type' => 'module'],
];
$resultKeys4 = EngineeringWorkspaceProvisioningService::collectApplicableWorkspaceKeys($keysWithUnsafe);
p_assert_true(in_array('../etc/passwd', $resultKeys4, true), 'TC04a. unsafe keys pass through collection');
p_assert_true(in_array('/absolute/path', $resultKeys4, true), 'TC04b. absolute paths pass through collection');

// ════════════════════════════════════════════════════════════
// TC05 — bulkCreateMissingDocuments creates missing docs for new workspace
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC05');
$result = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC05'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result['ok']), 'TC05a. bulk result ok');
p_assert_eq(1, $result['total_created'] ?? 0, 'TC05b. 1 workspace created');
p_assert_eq(1, count($result['created'] ?? []), 'TC05c. created array has 1 entry');
p_assert_eq('BulkDeployTest/TC05', $result['created'][0]['workspace_key'] ?? '', 'TC05d. created workspace key matches');
p_assert_eq(4, $result['created'][0]['count'] ?? 0, 'TC05e. 4 documents created');
// Verify at real engineering path
$wsDir = APP_ROOT . '/engineering/BulkDeployTest/TC05';
foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
    p_assert_true(is_file($wsDir . '/' . $fn), 'TC05f. ' . $fn . ' created in engineering/');
}
$p_cleanWs('BulkDeployTest/TC05');

// ════════════════════════════════════════════════════════════
// TC06 — bulkCreateMissingDocuments skips already-complete workspaces
// ════════════════════════════════════════════════════════════
$completeDir = APP_ROOT . '/engineering/BulkDeployTest/TC06';
@mkdir($completeDir, 0755, true);
foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
    file_put_contents($completeDir . '/' . $fn, '# ' . $fn . "\n\nExisting content.\n");
}

$result6 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC06'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result6['ok']), 'TC06a. bulk result ok');
p_assert_eq(1, $result6['total_already_complete'] ?? 0, 'TC06b. 1 workspace already complete');
p_assert_eq(0, $result6['total_created'] ?? 0, 'TC06c. 0 created');
p_assert_eq(0, $result6['total_failed'] ?? 0, 'TC06d. 0 failed');
$p_cleanWs('BulkDeployTest/TC06');

// ════════════════════════════════════════════════════════════
// TC07 — bulkCreateMissingDocuments rejects invalid/traversal keys
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC07');
$result7 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC07', '../etc/passwd', '/absolute/path'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result7['ok']), 'TC07a. bulk result ok even with invalid keys');
p_assert_eq(2, $result7['total_skipped'] ?? 0, 'TC07b. 2 skipped (traversal + absolute)');
p_assert_eq(1, $result7['total_created'] ?? 0, 'TC07c. 1 valid workspace created');
$p_cleanWs('BulkDeployTest/TC07');

// ════════════════════════════════════════════════════════════
// TC08 — one workspace failure doesn't block others
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC08a');
$p_cleanWs('BulkDeployTest/TC08b');
$result8 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC08a', '../etc/passwd', 'BulkDeployTest/TC08b'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result8['ok']), 'TC08a. bulk result ok');
p_assert_eq(2, $result8['total_created'] ?? 0, 'TC08b. 2 valid workspaces created');
p_assert_eq(1, $result8['total_skipped'] ?? 0, 'TC08c. 1 invalid skipped');
$p_cleanWs('BulkDeployTest/TC08a');
$p_cleanWs('BulkDeployTest/TC08b');

// ════════════════════════════════════════════════════════════
// TC09 — created documents pass content contract validation
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC09');
$result9 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC09'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result9['ok']), 'TC09a. bulk result ok');

$wsDir9 = APP_ROOT . '/engineering/BulkDeployTest/TC09';
foreach (['overview', 'work', 'rules', 'decisions'] as $docKey) {
    $fn = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
    $content = is_file($wsDir9 . '/' . $fn) ? file_get_contents($wsDir9 . '/' . $fn) : '';
    $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content ?: '');
    p_assert_true(!empty($validation['ok']), 'TC09b. ' . $docKey . ' content passes validation');
}
$p_cleanWs('BulkDeployTest/TC09');

// ════════════════════════════════════════════════════════════
// TC10 — snapshot directories and manifests created for each workspace
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC10');
$result10 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC10'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result10['ok']), 'TC10a. bulk result ok');
p_assert_eq(1, $result10['total_created'] ?? 0, 'TC10b. 1 created');
$txId = $result10['created'][0]['transaction_id'] ?? '';
p_assert_true($txId !== '', 'TC10c. transaction id present');
$archivePath = $result10['created'][0]['archive_path'] ?? '';
p_assert_true($archivePath !== '', 'TC10d. archive path present');

$manifestPath = APP_ROOT . '/storage/studio-snapshots/engineering-workspaces/BulkDeployTest/TC10/' . $txId . '/manifest.json';
p_assert_true(is_file($manifestPath), 'TC10e. snapshot manifest exists');
$mc = is_file($manifestPath) ? file_get_contents($manifestPath) : '';
p_assert_contains($mc ?: '', 'create_missing', 'TC10f. manifest action is create_missing');
$p_cleanWs('BulkDeployTest/TC10');

// ════════════════════════════════════════════════════════════
// TC11 — idempotent: second run reports already complete
// ════════════════════════════════════════════════════════════
$p_cleanWs('BulkDeployTest/TC11');
$result11a = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC11'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result11a['ok']), 'TC11a. first run ok');
p_assert_eq(1, $result11a['total_created'] ?? 0, 'TC11b. 1 created on first run');

$result11b = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    ['BulkDeployTest/TC11'],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result11b['ok']), 'TC11c. second run ok');
p_assert_eq(1, $result11b['total_already_complete'] ?? 0, 'TC11d. 1 already complete on second run');
p_assert_eq(0, $result11b['total_created'] ?? 0, 'TC11e. 0 created on second run');
p_assert_eq(0, $result11b['total_failed'] ?? 0, 'TC11f. 0 failed on second run');

// Verify original content preserved (not overwritten)
$wsDir11 = APP_ROOT . '/engineering/BulkDeployTest/TC11';
$overviewContent = is_file($wsDir11 . '/overview.md') ? file_get_contents($wsDir11 . '/overview.md') : '';
p_assert_contains($overviewContent ?: '', 'BulkDeployTest/TC11', 'TC11g. original content preserved with workspace key');

$p_cleanWs('BulkDeployTest/TC11');

// ════════════════════════════════════════════════════════════
// TC12 — bulk with empty key list returns graceful empty result
// ════════════════════════════════════════════════════════════
$result12 = EngineeringWorkspaceProvisioningService::bulkCreateMissingDocuments(
    [],
    ['login' => 'test-admin']
);
p_assert_true(!empty($result12['ok']), 'TC12a. empty list result ok');
p_assert_eq(0, $result12['total_requested'] ?? -1, 'TC12b. total_requested is 0');
p_assert_eq(0, $result12['total_created'] ?? -1, 'TC12c. total_created is 0');
p_assert_eq(0, $result12['total_already_complete'] ?? -1, 'TC12d. total_already_complete is 0');
p_assert_eq(0, $result12['total_failed'] ?? -1, 'TC12e. total_failed is 0');

// ── Summary ──
$total = $passed + $failed;
echo "\n──  Bulk Deploy Probe Results  ──\n";
echo "  {$passed}/{$total} passed, {$failed} failed\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
