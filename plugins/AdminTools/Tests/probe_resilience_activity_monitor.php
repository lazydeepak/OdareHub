<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/plugins/AdminTools/Services/ResilienceActivityMonitorService.php';

use Plugins\AdminTools\Services\ResilienceActivityMonitorService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$snapshot = ResilienceActivityMonitorService::compose([
    'exports' => [[
        'id' => 1, 'suite_key' => 'manufacturing', 'target_type' => 'suite', 'target_key' => 'manufacturing',
        'export_type' => 'full', 'status' => 'completed', 'created_at' => '2026-07-13 10:00:00', 'created_by' => 'admin@example.test',
    ]],
    'imports' => [[
        'id' => 2, 'suite_key' => 'sbaio', 'import_type' => 'legacy_workbook', 'source_file' => 'people.xlsx',
        'status' => 'failed', 'error_text' => 'Invalid workbook', 'created_at' => '2026-07-13 12:00:00', 'created_by' => 'admin@example.test',
    ]],
    'restores' => [[
        'id' => 3, 'suite_key' => 'manufacturing', 'target_type' => 'suite', 'target_key' => 'manufacturing',
        'restore_type' => 'full', 'status' => 'previewed', 'created_at' => '2026-07-13 11:00:00', 'created_by' => 'admin@example.test',
    ]],
    'environment' => [[
        'id' => 4, 'operation_type' => 'snapshot_create', 'scope_key' => 'platform', 'source_environment' => 'production',
        'status' => 'created', 'warning_text' => 'Large artifact', 'created_at' => '2026-07-13 09:00:00', 'created_by' => 'admin@example.test',
    ]],
    'releases' => [[
        'id' => 5, 'scope_key' => 'platform', 'release_version' => '1.2.3', 'package_name' => 'release.zip',
        'status' => 'generated', 'created_at' => '2026-07-13 13:00:00', 'created_by' => 'admin@example.test',
    ]],
]);

$assert(($snapshot['source_count'] ?? 0) === 5, 'five authoritative histories are declared');
$assert(($snapshot['available_source_count'] ?? 0) === 5, 'all supplied histories are available');
$assert(($snapshot['degraded'] ?? true) === false, 'complete input is not degraded');
$assert(count((array)($snapshot['activity'] ?? [])) === 5, 'all records are composed');
$assert(($snapshot['activity'][0]['source_key'] ?? '') === 'releases', 'activity is newest first');
$assert(($snapshot['activity'][1]['status_bucket'] ?? '') === 'attention', 'failures require attention');
$assert(($snapshot['activity'][2]['status_bucket'] ?? '') === 'review', 'preview state remains review');
$assert(($snapshot['activity'][4]['status_bucket'] ?? '') === 'attention', 'warnings require attention');
$assert(($snapshot['summary']['successful'] ?? 0) === 2, 'successful outcomes are counted');
$assert(($snapshot['summary']['attention'] ?? 0) === 2, 'warning and failure outcomes are counted');
$assert(($snapshot['summary']['review'] ?? 0) === 1, 'review outcomes are counted');
$assert(($snapshot['activity'][1]['message'] ?? '') === 'Invalid workbook', 'owner error evidence is retained');
$assert(($snapshot['activity'][0]['owner_url'] ?? '') === '/admin/setup/release', 'activity links to its owner');

$degraded = ResilienceActivityMonitorService::compose([], ['imports' => 'unavailable']);
$assert(($degraded['degraded'] ?? false) === true, 'source errors produce an explicit degraded state');
$assert(($degraded['available_source_count'] ?? 0) === 4, 'degraded source count is accurate');
$importCoverage = array_values(array_filter((array)$degraded['coverage'], static fn (array $row): bool => ($row['key'] ?? '') === 'imports'));
$assert(($importCoverage[0]['available'] ?? true) === false, 'failed history is marked unavailable');

echo "Resilience activity monitor probe: {$assertions}/{$assertions}\n";
