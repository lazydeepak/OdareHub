<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/plugins/Base/Services/PlatformAdminStatusSnapshotService.php';

use Plugins\Base\Services\PlatformAdminStatusSnapshotService;

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$payload = [
    'cards' => [
        ['key' => 'active_apps', 'value' => 7, 'tone' => 'info', 'mini_action' => ['url' => '/legacy-write']],
        ['key' => 'installed_apps', 'value' => 11, 'tone' => 'info'],
        ['key' => 'failed_migrations', 'value' => 2, 'tone' => 'danger'],
        ['key' => 'schema_sync_gaps', 'value' => 3, 'tone' => 'danger', 'sync_action' => ['url' => '/legacy-write']],
        ['key' => 'log_files', 'value' => 4, 'tone' => 'warn'],
        ['key' => 'log_size_mb', 'value' => '1.25', 'tone' => 'warn'],
        ['key' => 'access_control_assignments', 'value' => 5, 'tone' => 'info'],
        ['key' => 'scope_assignment_rows', 'value' => 6, 'tone' => 'info'],
        ['key' => 'module_visibility_rules', 'value' => 8, 'tone' => 'info'],
        ['key' => 'unknown_legacy_metric', 'value' => 99, 'tone' => 'danger'],
    ],
    'sections' => [[
        'key' => 'environment',
        'items' => [
            ['key' => 'php_version', 'value' => '8.4-test'],
            ['key' => 'runtime_environment', 'value' => 'testing'],
            ['key' => 'route_integrity', 'value' => 'Use Admin Routes'],
            ['key' => 'unknown_environment', 'value' => 'ignore'],
        ],
    ]],
];

$snapshot = PlatformAdminStatusSnapshotService::composePayload($payload, true);
$assert($snapshot['state'] === 'ready', 'mapped owner payload is ready');
$assert(count($snapshot['cards']) === 9, 'all nine retained KPI cards map exactly once');
$assert(array_column($snapshot['cards'], 'value') === ['7', '11', '2', '3', '4', '1.25', '5', '6', '8'], 'KPI values preserve owner payload order and formatting');
$assert(($snapshot['cards'][2]['tone'] ?? '') === 'danger', 'KPI tone is preserved');
$assert(($snapshot['cards'][1]['target'] ?? '') === '/admin/app-manager', 'installed application KPI hands off to inventory owner');
$assert(($snapshot['cards'][3]['target'] ?? '') === '/admin/base', 'schema gap KPI hands off to Base instead of legacy bulk mutation');
$assert(($snapshot['cards'][8]['target'] ?? '') === '/ops/navigation-tree', 'visibility KPI hands off to navigation diagnostics');
$assert(!array_key_exists('mini_action', $snapshot['cards'][0]), 'legacy mini action is not imported');
$assert(!array_key_exists('sync_action', $snapshot['cards'][3]), 'legacy mutation action is not imported');
$assert(!in_array('99', array_column($snapshot['cards'], 'value'), true), 'unknown legacy metric fails closed');
$assert(count($snapshot['environment']) === 3, 'developer sees all three mapped environment facts');

$productionSnapshot = PlatformAdminStatusSnapshotService::composePayload($payload, false);
$assert(count($productionSnapshot['environment']) === 2, 'route diagnostic is hidden outside developer visibility');
$assert(!in_array('route_integrity', array_column($productionSnapshot['environment'], 'key'), true), 'hidden diagnostic cannot leak by key');

$empty = PlatformAdminStatusSnapshotService::composePayload(['cards' => [], 'sections' => []], true);
$assert($empty['state'] === 'degraded', 'empty owner payload is explicit degraded state');
$assert($empty['cards'] === [] && $empty['environment'] === [], 'degraded payload contains no invented metrics');

$unauthorized = PlatformAdminStatusSnapshotService::snapshot(['authority_role' => 'app_admin']);
$assert($unauthorized['state'] === 'not_authorized', 'non-platform role receives explicit not-authorized state');
$assert($unauthorized['cards'] === [] && $unauthorized['environment'] === [], 'non-platform role receives no platform metrics');

echo "Platform admin snapshot parity probe: {$checks}/{$checks} assertions passed\n";
