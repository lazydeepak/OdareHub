<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$module = $root . '/apps/Manufacturing/modules/InjectionMolding';
$manifestPath = $module . '/plugin.json';
$migrationPath = $module . '/migrations/001_create_ipm_mold_revisions.sql';
$moldPath = $root . '/apps/Manufacturing/modules/Products/migrations/008_part_molds_and_material_yield.sql';
$manufacturingManifest = (string)file_get_contents($root . '/apps/Manufacturing/manifest.json');
$pluginManager = (string)file_get_contents($root . '/app/Core/PluginManager.php');
$installHook = (string)file_get_contents($module . '/install.php');
$updateHook = (string)file_get_contents($module . '/update.php');
$uninstallHook = (string)file_get_contents($module . '/uninstall.php');

$manifest = json_decode((string)file_get_contents($manifestPath), true);
$migration = (string)file_get_contents($migrationPath);
$moldMigration = (string)file_get_contents($moldPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
};

$check(is_array($manifest), 'InjectionMolding manifest parses');
$check(($manifest['name'] ?? null) === 'InjectionMolding', 'manifest declares canonical module name');
$check(($manifest['owner_app'] ?? null) === 'manufacturing', 'manifest is Manufacturing-owned');
$check(($manifest['module_type'] ?? null) === 'business_entity', 'manifest uses documented business-entity module type');
$check(($manifest['package_type'] ?? null) === 'module', 'manifest declares module package type');
$check(($manifest['migrations_path'] ?? null) === 'migrations', 'manifest uses normal module migration path');
$check(($manifest['activation_contract_enforced'] ?? null) === true, 'manifest enables existing activation contract enforcement');
$check(in_array('ipm_mold_revisions', (array)($manifest['required_tables'] ?? []), true), 'manifest declares the revision table contract');
$check(in_array('Products>=1.0.0', (array)($manifest['requires'] ?? []), true), 'module depends on canonical Products module');
$check(in_array('schema', (array)($manifest['declared_capabilities'] ?? []), true), 'schema capability is declared');
$check(in_array('migrations', (array)($manifest['declared_capabilities'] ?? []), true), 'migration capability is declared');
$check(is_file($module . '/routes.php') && is_file($module . '/install.php'), 'normal module lifecycle entry files exist');
$check(is_file($module . '/update.php') && is_file($module . '/uninstall.php'), 'update and uninstall lifecycle hooks exist');
$check(str_contains($updateHook, 'return;'), 'update hook is an explicit no-op include script');
$check(!str_contains($updateHook, 'DB::') && !str_contains($updateHook, 'CREATE TABLE'), 'update hook performs no DDL or data mutation');
$check(!str_contains($installHook, 'DB::') && !str_contains($installHook, 'CREATE TABLE'), 'install hook does not bypass migration ownership');
$check(!str_contains($uninstallHook, 'DB::') && !str_contains($uninstallHook, 'DROP TABLE'), 'uninstall hook preserves revision history');
$check(str_contains($manufacturingManifest, '"InjectionMolding"'), 'Manufacturing bundle manifest includes the module');
$check(str_contains($pluginManager, "glob(\$modulePath . '/migrations', '*.sql')") || str_contains($pluginManager, "runMigrations(\$this->runtimePath(\$m) . '/migrations', \$name)"), 'PluginManager owns module migration execution');
$installOrder = [
    strpos($pluginManager, "runMigrations(\$this->runtimePath(\$m) . '/migrations', \$name)"),
    strpos($pluginManager, "require \$install"),
    strpos($pluginManager, "\$this->assertSchemaReady(\$name, \$m, 'schema creation')"),
];
$check($installOrder[0] !== false && $installOrder[1] !== false && $installOrder[2] !== false && $installOrder[0] < $installOrder[1] && $installOrder[1] < $installOrder[2], 'install runs migrations, hook, then schema readiness');
$updateOrder = [
    strpos($pluginManager, "runMigrations(\$this->runtimePath(\$m) . '/migrations', \$name)"),
    strpos($pluginManager, "require \$updateHook"),
    strpos($pluginManager, "\$this->assertSchemaReady(\$name, \$m, 'schema update')"),
];
$check($updateOrder[0] !== false && $updateOrder[1] !== false && $updateOrder[2] !== false && $updateOrder[0] < $updateOrder[1] && $updateOrder[1] < $updateOrder[2], 'update runs migrations, hook, then schema readiness');
$check(str_contains($pluginManager, '$db->rollback();'), 'lifecycle failure propagation rolls back the transaction');
$check(!str_contains((string)file_get_contents($module . '/routes.php'), '->post('), 'foundation has no mutation routes');
$check(!str_contains((string)file_get_contents($module . '/routes.php'), '->get('), 'foundation has no runtime routes');
$check(str_contains($migration, 'CREATE TABLE IF NOT EXISTS ipm_mold_revisions'), 'revision migration creates the canonical table');
$check(str_contains($migration, 'revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'), 'revision identity uses the new IPM key convention');
$check(str_contains($migration, 'mold_id INT NOT NULL'), 'revision references legacy mold key type');
$check(str_contains($migration, 'organization_id INT NOT NULL'), 'revision organization ownership is mandatory');
$check(str_contains($migration, 'FOREIGN KEY (mold_id) REFERENCES part_molds(id)'), 'revision references Products mold master');
$check(str_contains($migration, 'FOREIGN KEY (organization_id) REFERENCES org_companies(id)'), 'revision organization references Platform tenant root');
$check(str_contains($migration, 'UNIQUE KEY uq_ipm_mold_revision (mold_id, revision_no)'), 'revision number is unique per canonical mold');
$check(str_contains($migration, "ENUM('draft','released','superseded','archived')"), 'minimal revision lifecycle is explicit');
$check(str_contains($migration, 'CHECK (revision_no > 0)'), 'revision number is positive');
$check(str_contains($migration, 'chk_ipm_revision_release_evidence'), 'released lifecycle states require release evidence');
$check(str_contains($migration, 'ON DELETE RESTRICT ON UPDATE CASCADE'), 'all revision identity FKs restrict deletion');
$check(!preg_match('/^\s*(?:UPDATE|INSERT|DELETE|DROP)\b/im', $migration), 'migration is additive DDL without data mutation');
$check(!str_contains($migration, 'CREATE TABLE IF NOT EXISTS part_molds'), 'module does not duplicate the mold master');
$check(str_contains($moldMigration, 'id INT AUTO_INCREMENT PRIMARY KEY'), 'canonical part_molds key is INT');
$check(!str_contains($moldMigration, 'organization_id'), 'canonical mold has no authoritative organization column yet');
$check(!is_file($module . '/Controllers/InjectionMoldingController.php'), 'no unauthorized runtime mutation controller exists');
$check(!is_file($module . '/Services/MoldRevisionService.php'), 'no unauthorized revision mutation service exists');
$check(!str_contains($migration, 'Part360Service'), 'migration does not wire Part 360');
$check(!str_contains($migration, 'manufacturing_bom'), 'migration does not change BOM identity');

echo "IPM-011 module foundation probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
