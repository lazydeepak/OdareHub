<?php
declare(strict_types=1);

/**
 * Database-free Shared Item persistence contract probe.
 *
 * This verifies ownership, lifecycle wiring, autoload declarations, schema
 * scope, and the separation between the JSON compatibility service and the
 * database-backed implementation. It never loads DB configuration or executes
 * SQL.
 */
$root = dirname(__DIR__, 4);
require_once $root . '/vendor/autoload.php';

use App\Services\AppManifestService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
};

$composer = json_decode((string)file_get_contents($root . '/composer.json'), true);
$manifest = json_decode((string)file_get_contents($root . '/apps/Shared/manifest.json'), true);
$migration = (string)file_get_contents($root . '/apps/Shared/migrations/20261123_000001_create_shared_items.sql');
$routes = (string)file_get_contents($root . '/apps/Shared/routes.php');
$databaseService = (string)file_get_contents($root . '/shared/Item/Foundation/Services/DatabaseItemIdentityService.php');
$repository = (string)file_get_contents($root . '/shared/Item/Foundation/Repositories/MysqlItemRepository.php');
$jsonService = (string)file_get_contents($root . '/shared/Item/Foundation/Services/ItemIdentityService.php');

$check(is_array($composer), 'composer.json parses');
$check((($composer['autoload']['psr-4']['Shared\\'] ?? null) === 'shared/'), 'Shared namespace uses Composer PSR-4');
$check(is_array($manifest), 'Shared manifest parses');
$normalizedManifest = AppManifestService::loadFromFile($root . '/apps/Shared/manifest.json');
$check(($normalizedManifest['id'] ?? null) === 'shared', 'Shared manifest passes existing AppManifestService validation');
$check(($manifest['id'] ?? null) === 'shared' && ($manifest['type'] ?? null) === 'system', 'Shared manifest uses existing system app fields');
$check(($manifest['migrations_path'] ?? null) === 'migrations', 'Shared manifest declares existing migration field');
$check(($manifest['permissions'] ?? null) === [], 'Shared manifest declares no permissions');
$check(($manifest['can_disable'] ?? null) === false && ($manifest['can_uninstall'] ?? null) === false, 'Shared foundation cannot be disabled or uninstalled');
$check(!isset($manifest['menus'], $manifest['widgets'], $manifest['hooks'], $manifest['routes']), 'Shared manifest declares no runtime surfaces');
$check(($manifest['shared_contract']['kind'] ?? null) === 'shared_app' && ($manifest['shared_contract']['consumers'] ?? null) === ['manufacturing'], 'Shared manifest uses existing Shared App governance metadata');
$check(!preg_match('/(?:license|tenant|organization|billing|activation)/i', json_encode($manifest, JSON_THROW_ON_ERROR)), 'Shared manifest has no licensing or tenant activation contract');
$check(str_contains($routes, 'no web routes'), 'Shared entry is an inert supported app entry');
$check(str_contains($routes, 'no web routes') && !str_contains($routes, 'Router'), 'Shared entry cannot register routes');
$installSource = (string)file_get_contents($root . '/app/Services/AppInstallService.php');
$check(str_contains($installSource, 'runMigrations') && str_contains($installSource, 'begin_transaction'), 'Existing app install path runs migrations inside its transaction');
$check(str_contains($installSource, "manifest['migrations_path']"), 'Existing app install path resolves manifest migrations_path');
$migrationSource = (string)file_get_contents($root . '/app/Services/AppMigrationService.php');
$check(str_contains($migrationSource, 'core_app_migrations') && str_contains($migrationSource, 'hash(\'sha256\', $sql)'), 'Existing migration ledger records migration checksums');
$check(str_contains($migrationSource, "status'] ?? '') === 'applied'"), 'Existing migration path skips applied migrations on replay');

$check(substr_count(strtoupper($migration), 'CREATE TABLE') === 1, 'Shared migration declares one table');
$check(!preg_match('/\b(?:ALTER|DROP|TRUNCATE)\b/i', $migration), 'Shared migration is additive and non-destructive');
$check(!str_contains($migration, 'shared_item_identity_map'), 'Generic identity map is deferred');
$check(!preg_match('/\b(?:products|machines|materials|manufacturing|organization_id|users)\b/i', $migration), 'Shared migration has no Manufacturing, tenant, or Core-user dependency');
$check(str_contains($migration, 'BIGINT UNSIGNED AUTO_INCREMENT'), 'Shared identity uses BIGINT UNSIGNED');
$check(str_contains($migration, 'UNIQUE KEY uniq_shared_items_code_ref'), 'code_ref has global database uniqueness');
$check(!str_contains($migration, 'idx_shared_items_code_ref'), 'Redundant non-unique code index is not declared');

$check(str_contains($repository, 'DB::query(') && str_contains($repository, 'WHERE item_id = ?'), 'Repository uses prepared DB boundary for identity reads');
$check(str_contains($repository, 'WHERE code_ref = ?'), 'Repository supports exact code lookup');
$check(str_contains($repository, 'ORDER BY item_id ASC LIMIT '), 'Repository has deterministic bounded pagination');
$check(str_contains($databaseService, 'begin_transaction()') && str_contains($databaseService, '->rollback()'), 'Service owns transaction rollback boundary');
$check(str_contains($databaseService, 'Invalid Shared Item status transition'), 'Service validates lifecycle transitions');
$check(str_contains($databaseService, 'FORBIDDEN_FIELDS'), 'Service preserves canonical identity field boundary');
$check(!str_contains($databaseService, 'shared_item_identity_map'), 'Database service does not implement deferred mapping');
$check($jsonService !== '' && !str_contains($jsonService, 'DatabaseItemIdentityService'), 'Historical JSON service remains independent');

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_THROW_ON_ERROR) . "\n";
exit($failed === 0 ? 0 : 1);
