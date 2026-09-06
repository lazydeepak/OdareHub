<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AppLegacyBridgeService;

$packageManagerPath = APP_ROOT . '/app/Core/PackageManager.php';
if (!class_exists(PackageManager::class) && is_file($packageManagerPath)) {
    require_once $packageManagerPath;
}

final class PluginManager
{
    private string $pluginsPath;
    private Container $c;
    private Router $router;
    private View $view;
    private EventBus $bus;

    /** @var array<string, array> */
    private array $manifests = [];
    /** @var array<string, array<string,mixed>> */
    private array $legacyCompatibility = [];

    public function __construct(string $pluginsPath, Container $c, Router $router, View $view, EventBus $bus)
    {
        $this->pluginsPath = rtrim($pluginsPath, '/');
        $this->c = $c;
        $this->router = $router;
        $this->view = $view;
        $this->bus = $bus;
    }

    public function scan(): array
    {
        $out = [];
        $this->legacyCompatibility = [];

        $moduleDirs = glob(APP_ROOT . '/apps/*/modules/*', GLOB_ONLYDIR) ?: [];
        foreach ($moduleDirs as $dir) {
            $manifestPath = $dir . '/plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $json = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($json) || empty($json['name'])) {
                continue;
            }

            $name = trim((string)$json['name']);
            if ($name === '') {
                continue;
            }

            $ownerApp = basename(dirname(dirname($dir)));
            if ($ownerApp !== '' && !isset($json['owner_app'])) {
                $json['owner_app'] = $ownerApp;
            }
            if (!isset($json['package_type'])) {
                $json['package_type'] = 'module';
            }
            $out[$name] = $this->normalizeManifest($json, $dir, 'canonical_module');
        }

        $dirs = glob($this->pluginsPath . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $manifestPath = $dir . '/plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $json = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($json) || empty($json['name'])) {
                continue;
            }

            $name = trim((string)$json['name']);
            if ($name === '') {
                continue;
            }

            $ownerApp = trim((string)($json['owner_app'] ?? ''));
            $isBundleModule = $this->isBundleOwnedModuleManifest($name, $json);
            $canonicalPath = PackageManager::canonicalModulePath($name, $ownerApp);
            if ($isBundleModule) {
                $realLegacy = realpath($dir) ?: $dir;
                $realCanonical = $canonicalPath !== '' ? (realpath($canonicalPath) ?: $canonicalPath) : '';
                $mode = $realCanonical !== '' ? 'duplicate_legacy_path' : 'legacy_module_only';
                $this->legacyCompatibility[$name] = [
                    'module' => $name,
                    'legacy_path' => $dir,
                    'canonical_path' => $canonicalPath,
                    'mode' => $mode,
                ];

                if ($realCanonical !== '' && $realLegacy !== $realCanonical) {
                    error_log('Legacy module path detected for ' . $name . ' at ' . $dir . '; canonical runtime path is ' . $canonicalPath);
                } elseif ($realCanonical !== '' && is_link($dir)) {
                    error_log('Legacy module symlink detected for ' . $name . ' at ' . $dir . '; canonical runtime path is ' . $canonicalPath);
                } elseif ($realCanonical === '') {
                    error_log('Legacy-only module detected for ' . $name . ' at ' . $dir . '; runtime loading remains disabled until moved under apps/<Bundle>/modules.');
                }

                if (isset($out[$name])) {
                    continue;
                }

                $out[$name] = $this->normalizeManifest($json, $dir, 'legacy_detection');
                continue;
            }

            if (isset($out[$name])) {
                continue;
            }
            $out[$name] = $this->normalizeManifest($json, $dir, 'legacy_plugin');
        }

        $this->manifests = $out;
        return $out;
    }

    public function manifests(): array
    {
        if (!$this->manifests) {
            $this->scan();
        }
        return $this->manifests;
    }

    public function architectureReport(): array
    {
        $manifests = $this->manifests();

        $report = [
            'suites' => [
                'core' => [],
                'manufacturing' => [],
                'future' => [],
                'uncategorized' => [],
            ],
            'dependencies' => [],
            'plugins' => [],
            'violations' => [],
            'summary' => [
                'plugins' => count($manifests),
                'warnings' => 0,
                'errors' => 0,
                'missing_metadata' => 0,
                'strict_mode' => $this->isStrictMode(),
            ],
        ];

        foreach ($manifests as $name => $m) {
            $suite = (string)($m['suite'] ?? 'uncategorized');
            if (!isset($report['suites'][$suite])) {
                $suite = 'uncategorized';
            }
            $report['suites'][$suite][] = $name;

            $missing = (array)($m['__missing_fields'] ?? []);
            if ($missing) {
                $report['summary']['missing_metadata']++;
            }

            $warnings = [];
            $errors = [];

            if (in_array('suite', $missing, true)) {
                $warnings[] = 'Missing suite metadata (default applied).';
            }
            if (in_array('group', $missing, true)) {
                $warnings[] = 'Missing group metadata (default applied).';
            }
            if (in_array('display_name', $missing, true)) {
                $warnings[] = 'Missing display_name metadata (name used as fallback).';
            }

            $legacyMode = (string)($m['__legacy_mode'] ?? '');
            if ($legacyMode === 'duplicate_legacy_path') {
                $warnings[] = 'Legacy flat module path detected alongside canonical bundle module path. Runtime uses the canonical module path.';
            } elseif ($legacyMode === 'legacy_module_only') {
                $warnings[] = 'Legacy-only module detected. It is discoverable for compatibility, but runtime loading is disabled until moved into apps/<Bundle>/modules.';
            }

            $category = (string)($m['category'] ?? '');
            if ($suite === 'core' && $category === 'business') {
                $warnings[] = 'Category mismatch: core plugin marked as business.';
            }
            if ($suite === 'manufacturing' && $category === 'system') {
                $warnings[] = 'Category mismatch: manufacturing plugin marked as system.';
            }
            if ((bool)($m['is_core'] ?? false) && $suite !== 'core') {
                $warnings[] = 'Flag mismatch: is_core=true while suite is not core.';
            }
            if ((bool)($m['is_business'] ?? false) && $suite === 'core') {
                $warnings[] = 'Flag mismatch: is_business=true while suite is core.';
            }

            $depRows = [];
            foreach ((array)($m['requires'] ?? []) as $rawReq) {
                $rawReq = (string)$rawReq;
                $depName = $this->dependencyName($rawReq);
                if ($depName === '') {
                    continue;
                }

                $dep = $manifests[$depName] ?? null;
                $isKnown = is_array($dep);
                $depSuite = $isKnown ? (string)($dep['suite'] ?? 'uncategorized') : 'unknown';
                $validDirection = !($suite === 'core' && $depSuite === 'manufacturing');

                if (!$isKnown) {
                    $errors[] = "Unknown dependency: {$depName}.";
                    $report['violations'][] = [
                        'plugin' => $name,
                        'severity' => 'error',
                        'type' => 'unknown_dependency',
                        'message' => "Unknown dependency: {$depName}",
                    ];
                } elseif (!$validDirection) {
                    $warnings[] = "Architecture violation: core plugin depends on manufacturing plugin {$depName}.";
                    $report['violations'][] = [
                        'plugin' => $name,
                        'severity' => 'warning',
                        'type' => 'core_depends_on_manufacturing',
                        'message' => "Core plugin {$name} depends on manufacturing plugin {$depName}",
                    ];
                }

                $depRows[] = [
                    'raw' => $rawReq,
                    'name' => $depName,
                    'known' => $isKnown,
                    'suite' => $depSuite,
                    'valid_direction' => $validDirection,
                ];
            }

            $report['dependencies'][$name] = $depRows;
            $report['plugins'][$name] = [
                'suite' => $suite,
                'group' => (string)($m['group'] ?? ''),
                'type' => (string)($m['type'] ?? ''),
                'warnings' => $warnings,
                'errors' => $errors,
                'missing_fields' => $missing,
                'runtime_source' => (string)($m['__runtime_source'] ?? ''),
                'legacy_mode' => $legacyMode,
            ];

            $report['summary']['warnings'] += count($warnings);
            $report['summary']['errors'] += count($errors);
        }

        $cycleNodes = $this->detectCycles($manifests);
        foreach ($cycleNodes as $pluginName) {
            $report['plugins'][$pluginName]['errors'][] = 'Circular dependency detected.';
            $report['violations'][] = [
                'plugin' => $pluginName,
                'severity' => 'error',
                'type' => 'circular_dependency',
                'message' => "Circular dependency includes {$pluginName}",
            ];
            $report['summary']['errors']++;
        }

        return $report;
    }

    public function isInstalled(string $name): bool
    {
        $row = DB::fetchOne("SELECT * FROM installed_plugins WHERE name = ?", [$name]);
        return (bool)$row;
    }

    public function status(string $name): ?string
    {
        $row = DB::fetchOne("SELECT status FROM installed_plugins WHERE name = ?", [$name]);
        return $row['status'] ?? null;
    }

    public function loadActivePlugins(): void
    {
        $this->scan();
        $architecture = $this->architectureReport();
        $pluginAudit = (array)($architecture['plugins'] ?? []);

        DB::query("TRUNCATE TABLE menus");

        $active = DB::fetchAll("SELECT name FROM installed_plugins WHERE status='active'");
        $activeNames = array_map(static fn($r) => (string)$r['name'], $active);
        $activeMap = array_fill_keys($activeNames, true);

        $sorted = $this->sortByDependencies($activeNames);

        foreach ($sorted as $name) {
            $m = $this->manifests[$name] ?? null;
            if (!$m) {
                continue;
            }

            if (!$this->isRuntimeLoadable($m)) {
                continue;
            }

            if (class_exists(AppLegacyBridgeService::class) && !AppLegacyBridgeService::shouldLoadLegacyPlugin($name)) {
                continue;
            }

            $auditNode = (array)($pluginAudit[$name] ?? []);
            $auditErrors = (array)($auditNode['errors'] ?? []);
            if ($auditErrors) {
                error_log('Plugin skipped due to architecture errors [' . $name . ']: ' . implode(' | ', $auditErrors));
                continue;
            }

            $runtimeErrors = $this->runtimeDependencyErrors($name, $activeMap);
            if ($runtimeErrors) {
                error_log('Plugin skipped due to unsafe dependencies [' . $name . ']: ' . implode(' | ', $runtimeErrors));
                continue;
            }

            $runtimeWarnings = $this->runtimeArchitectureWarnings($name);
            if ($runtimeWarnings) {
                if ($this->isStrictMode()) {
                    error_log('Plugin skipped due to strict architecture policy [' . $name . ']: ' . implode(' | ', $runtimeWarnings));
                    continue;
                }
                error_log('Plugin architecture warnings [' . $name . ']: ' . implode(' | ', $runtimeWarnings));
            }

            $runtimePath = $this->runtimePath($m);
            $this->view->addNamespace($name, $runtimePath . '/Views');

            $boot = $runtimePath . '/bootstrap.php';
            if (is_file($boot)) {
                $c = $this->c;
                $router = $this->router;
                $view = $this->view;
                $bus = $this->bus;
                require $boot;
            }

            $entry = $m['entry'] ?? 'routes.php';
            $routes = $runtimePath . '/' . $entry;
            if (is_file($routes)) {
                $router = $this->router;
                $c = $this->c;
                $view = $this->view;
                $bus = $this->bus;
                require $routes;
            }
        }
    }

    private function sortByDependencies(array $names): array
    {
        $this->scan();
        $names = array_values(array_unique($names));
        usort($names, function (string $left, string $right): int {
            $leftOrder = (int)(($this->manifests[$left]['display_order'] ?? 999));
            $rightOrder = (int)(($this->manifests[$right]['display_order'] ?? 999));
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp($left, $right);
        });
        $graph = [];

        foreach ($names as $n) {
            $req = $this->manifests[$n]['requires'] ?? [];
            $reqNames = [];
            foreach ((array)$req as $r) {
                $dep = $this->dependencyName((string)$r);
                if ($dep !== '') {
                    $reqNames[] = $dep;
                }
            }
            $graph[$n] = array_values(array_filter($reqNames, static fn($x) => in_array($x, $names, true)));
        }

        $sorted = [];
        $temp = [];
        $perm = [];

        $visit = static function ($n) use (&$visit, &$sorted, &$temp, &$perm, $graph): void {
            if (isset($perm[$n])) {
                return;
            }
            if (isset($temp[$n])) {
                return;
            }
            $temp[$n] = true;
            foreach ($graph[$n] ?? [] as $d) {
                $visit($d);
            }
            $perm[$n] = true;
            $sorted[] = $n;
        };

        foreach ($names as $n) {
            $visit($n);
        }

        return $sorted;
    }

    public function canUninstall(string $name): array
    {
        $deps = DB::fetchAll("SELECT name, requires_json FROM installed_plugins");
        $blockedBy = [];
        $manifests = $this->manifests();
        $targetManifest = is_array($manifests[$name] ?? null) ? (array)$manifests[$name] : [];
        $targetOwnerApp = trim((string)($targetManifest['owner_app'] ?? ''));

        foreach ($deps as $row) {
            $p = (string)$row['name'];
            if ($p === $name) {
                continue;
            }
            $status = DB::fetchOne("SELECT status FROM installed_plugins WHERE name=?", [$p])['status'] ?? 'inactive';
            if ($status !== 'active') {
                continue;
            }

            $manifestReq = is_array($manifests[$p] ?? null) ? (array)($manifests[$p]['requires'] ?? []) : [];
            $req = $manifestReq !== [] ? $manifestReq : json_decode((string)($row['requires_json'] ?? '[]'), true);
            $req = is_array($req) ? $req : [];
            $dependentOwnerApp = is_array($manifests[$p] ?? null) ? trim((string)($manifests[$p]['owner_app'] ?? '')) : '';
            $dependentSuite = is_array($manifests[$p] ?? null) ? strtolower(trim((string)($manifests[$p]['suite'] ?? ''))) : '';
            $ignoreCrossBundleSharedBlock = $targetOwnerApp !== ''
                && $dependentOwnerApp !== ''
                && $dependentOwnerApp !== $targetOwnerApp
                && $dependentSuite === 'core';
            foreach ($req as $r) {
                $reqName = $this->dependencyName((string)$r);
                if ($reqName === $name) {
                    if ($ignoreCrossBundleSharedBlock) {
                        continue;
                    }
                    $blockedBy[] = $p;
                }
            }
        }

        return array_values(array_unique($blockedBy));
    }

    public function install(string $name): void
    {
        $m = $this->manifests()[$name] ?? null;
        if (!$m) {
            throw new \RuntimeException("Plugin not found: {$name}");
        }

        $warnings = $this->runtimeArchitectureWarnings($name);
        if ($warnings) {
            if ($this->isStrictMode()) {
                throw new \RuntimeException('STRICT_POLICY_BLOCK: ' . implode(' | ', $warnings));
            }
            error_log('Plugin architecture warnings on install [' . $name . ']: ' . implode(' | ', $warnings));
        }

        $db = DB::conn();
        $db->begin_transaction();

        try {
            $requires = (array)($m['requires'] ?? []);
            foreach ($requires as $r) {
                $reqName = $this->dependencyName((string)$r);
                if ($reqName === '') {
                    continue;
                }
                if (!$this->isInstalled($reqName)) {
                    throw new \RuntimeException("Dependency validation failed: missing installed module {$reqName} (required by {$name})");
                }
            }

            $this->runMigrations($this->runtimePath($m) . '/migrations', $name);

            $install = $this->runtimePath($m) . '/install.php';
            if (is_file($install)) {
                require $install;
            }

            $this->assertSchemaReady($name, $m, 'schema creation');

            DB::query(
                "INSERT INTO installed_plugins (name, version, type, status, requires_json, installed_at) VALUES (?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE version=VALUES(version), type=VALUES(type), status='inactive', requires_json=VALUES(requires_json)",
                [
                    $name,
                    (string)($m['version'] ?? '0.0.0'),
                    (string)($m['type'] ?? 'business'),
                    'inactive',
                    json_encode($requires, JSON_UNESCAPED_SLASHES),
                ]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw new \RuntimeException(
                'Module install failed'
                . ' | module: ' . $name
                . ' | phase: install/schema creation'
                . ' | likely cause: module schema or install hook ran before tables were ready'
                . ' | detail: ' . $e->getMessage()
                . ' | recommended next step: inspect the module migrations/install hook and rerun Install or Repair.',
                0,
                $e
            );
        }
    }

    public function update(string $name): void
    {
        $m = $this->manifests()[$name] ?? null;
        if (!$m) {
            throw new \RuntimeException("Plugin not found: {$name}");
        }

        if (!$this->isInstalled($name)) {
            throw new \RuntimeException("Plugin is not installed: {$name}");
        }

        $warnings = $this->runtimeArchitectureWarnings($name);
        if ($warnings) {
            if ($this->isStrictMode()) {
                throw new \RuntimeException('STRICT_POLICY_BLOCK: ' . implode(' | ', $warnings));
            }
            error_log('Plugin architecture warnings on update [' . $name . ']: ' . implode(' | ', $warnings));
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $this->runMigrations($this->runtimePath($m) . '/migrations', $name);

            $updateHook = $this->runtimePath($m) . '/update.php';
            if (is_file($updateHook)) {
                require $updateHook;
            }

            $this->assertSchemaReady($name, $m, 'schema update');

            DB::query(
                "UPDATE installed_plugins SET version=?, type=?, requires_json=? WHERE name=?",
                [
                    (string)($m['version'] ?? '0.0.0'),
                    (string)($m['type'] ?? 'business'),
                    json_encode((array)($m['requires'] ?? []), JSON_UNESCAPED_SLASHES),
                    $name,
                ]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw new \RuntimeException(
                'Module update failed'
                . ' | module: ' . $name
                . ' | phase: update/schema creation'
                . ' | detail: ' . $e->getMessage()
                . ' | recommended next step: inspect the module migrations/update hook and rerun Repair or Update.',
                0,
                $e
            );
        }
    }

    public function updateAllInstalled(): array
    {
        $this->scan();

        $installed = DB::fetchAll("SELECT name FROM installed_plugins ORDER BY name ASC");
        $names = [];
        foreach ($installed as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name !== '' && isset($this->manifests[$name])) {
                $names[] = $name;
            }
        }

        $names = $this->sortByDependencies($names);

        $updated = [];
        foreach ($names as $name) {
            $this->update($name);
            $updated[] = $name;
        }

        return $updated;
    }

    public function enable(string $name): void
    {
        if (!$this->isInstalled($name)) {
            throw new \RuntimeException('Cannot activate module before install: ' . $name);
        }

        $m = $this->manifests()[$name] ?? null;
        if (!is_array($m)) {
            throw new \RuntimeException('Module manifest missing during activation: ' . $name);
        }

        foreach ((array)($m['requires'] ?? []) as $r) {
            $reqName = $this->dependencyName((string)$r);
            if ($reqName === '') {
                continue;
            }
            if (!$this->isInstalled($reqName)) {
                throw new \RuntimeException("Activation blocked: dependency {$reqName} is not installed (required by {$name})");
            }
            if ($this->status($reqName) !== 'active') {
                throw new \RuntimeException("Activation blocked: dependency {$reqName} is not active (required by {$name})");
            }
        }

        $this->assertSchemaReady($name, $m, 'activation');
        $this->assertActivationContractReady($name, $m);
        DB::query("UPDATE installed_plugins SET status='active' WHERE name=?", [$name]);
    }

    public function disable(string $name): void
    {
        if ($name === 'Base') {
            throw new \RuntimeException('Base cannot be disabled.');
        }

        $blockedBy = $this->canUninstall($name);
        if ($blockedBy) {
            throw new \RuntimeException('DEPENDENCY_BLOCK:' . implode(',', $blockedBy));
        }

        DB::query("UPDATE installed_plugins SET status='inactive' WHERE name=?", [$name]);
    }

    public function uninstall(string $name, bool $purge = false): void
    {
        if ($name === 'Base') {
            throw new \RuntimeException('Base cannot be uninstalled.');
        }

        $blockedBy = $this->canUninstall($name);
        if ($blockedBy) {
            throw new \RuntimeException('DEPENDENCY_BLOCK:' . implode(',', $blockedBy));
        }

        $m = $this->manifests()[$name] ?? null;
        if (!$m) {
            throw new \RuntimeException("Plugin not found: {$name}");
        }

        if ($purge && $this->status($name) !== 'inactive') {
            throw new \RuntimeException('Purge requires the plugin to be inactive first.');
        }

        $db = DB::conn();
        $db->begin_transaction();

        try {
            $un = $this->runtimePath($m) . '/uninstall.php';
            if (is_file($un)) {
                $PURGE = $purge;
                require $un;
            }

            if ($purge) {
                DB::query('DELETE FROM migrations WHERE plugin = ?', [$name]);
                DB::query('DELETE FROM installed_plugins WHERE name=?', [$name]);
            } else {
                DB::query("UPDATE installed_plugins SET status='inactive' WHERE name=?", [$name]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    private function runMigrations(string $dir, string $pluginName): void
    {
        if (!is_dir($dir)) {
            return;
        }

        DB::query("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plugin VARCHAR(100) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_plugin_file (plugin, filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
        $files = $this->sortMigrationFiles($files);

        $plugin = $pluginName !== '' ? $pluginName : basename(dirname($dir));
        foreach ($files as $file) {
            $fn = basename($file);
            $exists = DB::fetchOne('SELECT id FROM migrations WHERE plugin=? AND filename=?', [$plugin, $fn]);
            if ($exists) {
                continue;
            }

            $sql = (string)file_get_contents($file);
            $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: []));
            foreach ($stmts as $stmt) {
                if ($stmt === '') {
                    continue;
                }
                try {
                    DB::query($stmt);
                } catch (\Throwable $e) {
                    if (!self::isIgnorableMigrationError($e)) {
                        throw new \RuntimeException(
                            'Migration failed for module ' . $plugin
                            . ' in file ' . $fn
                            . ': ' . $e->getMessage(),
                            0,
                            $e
                        );
                    }
                }
            }
            DB::query('INSERT INTO migrations (plugin, filename) VALUES (?,?)', [$plugin, $fn]);
        }
    }

    private static function isIgnorableMigrationError(\Throwable $e): bool
    {
        $code = (int)$e->getCode();
        if (in_array($code, [1060, 1061], true)) {
            return true;
        }

        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'duplicate column name')) {
            return true;
        }
        if (str_contains($msg, 'duplicate key name')) {
            return true;
        }
        if (str_contains($msg, 'already exists')) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int,string> $files
     * @return array<int,string>
     */
    private function sortMigrationFiles(array $files): array
    {
        usort($files, function (string $left, string $right): int {
            $leftBase = basename($left);
            $rightBase = basename($right);
            $leftPrefix = preg_replace('/[^0-9].*/', '', $leftBase) ?: $leftBase;
            $rightPrefix = preg_replace('/[^0-9].*/', '', $rightBase) ?: $rightBase;
            if ($leftPrefix !== $rightPrefix) {
                return strnatcmp($leftBase, $rightBase);
            }

            $leftPhase = $this->migrationPhaseWeight($left);
            $rightPhase = $this->migrationPhaseWeight($right);
            if ($leftPhase !== $rightPhase) {
                return $leftPhase <=> $rightPhase;
            }

            return strnatcmp($leftBase, $rightBase);
        });

        return $files;
    }

    private function migrationPhaseWeight(string $file): int
    {
        $sql = strtolower((string)@file_get_contents($file));
        if ($sql !== '' && preg_match('/create\s+table\s+if\s+not\s+exists|create\s+table/i', $sql)) {
            return 0;
        }
        if ($sql !== '' && preg_match('/insert\s+into|replace\s+into/i', $sql)) {
            return 1;
        }
        if ($sql !== '' && preg_match('/alter\s+table|update\s+|delete\s+from|drop\s+/i', $sql)) {
            return 2;
        }

        return 3;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function assertSchemaReady(string $name, array $manifest, string $phase): void
    {
        $tables = $this->expectedTablesForManifest($manifest);
        foreach ($tables as $table) {
            $exists = DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            );
            if (!is_array($exists)) {
                throw new \RuntimeException(
                    'Missing table after ' . $phase . ': ' . $table
                    . ' | module: ' . $name
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function assertActivationContractReady(string $name, array $manifest): void
    {
        if (empty($manifest['activation_contract_enforced'])) {
            return;
        }

        $gaps = $this->activationCapabilityGaps($manifest);
        if ($gaps === []) {
            return;
        }

        throw new \RuntimeException(
            'Activation blocked: declared capability contract is incomplete for '
            . $name
            . ' | missing: '
            . implode(', ', $gaps)
        );
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private function activationCapabilityGaps(array $manifest): array
    {
        $modulePath = $this->runtimePath($manifest);
        $declared = array_values(array_unique(array_map('strval', (array)($manifest['declared_capabilities'] ?? []))));
        $gaps = [];

        foreach ($declared as $capability) {
            if (!$this->capabilityPresent($modulePath, $manifest, $capability)) {
                $gaps[] = $capability;
            }
        }

        return $gaps;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function capabilityPresent(string $modulePath, array $manifest, string $capability): bool
    {
        return match ($capability) {
            'routes' => is_file($modulePath . '/' . (string)($manifest['entry'] ?? 'routes.php')),
            'views' => $this->countCapabilityFiles($modulePath . '/Views', '*.php') > 0,
            'forms' => $this->anyCapabilityFileExists($modulePath . '/Views', ['add.php', 'edit.php', 'form.php']),
            'schema', 'migrations' => $this->countCapabilityFiles($modulePath . '/migrations', '*.sql') > 0
                || (!empty($manifest['required_tables']) && is_array($manifest['required_tables'])),
            'controllers' => $this->countCapabilityFiles($modulePath . '/Controllers', '*.php') > 0,
            'services' => $this->hasCapabilityServices($modulePath),
            'permissions' => $this->hasCapabilityPermissions($modulePath),
            'menus' => $this->anyCapabilityFileExists($modulePath, ['menu.php', 'navigation.php']),
            'widgets' => $this->countCapabilityFiles($modulePath . '/Services', '*WidgetRegistry.php') > 0,
            'charts' => $this->hasCapabilityNamedFile($modulePath, 'chart'),
            'dashboard' => $this->anyCapabilityFileExists($modulePath, ['dashboard.php'])
                || $this->anyCapabilityFileExists($modulePath . '/Views', ['dashboard.php']),
            'reports' => $this->hasCapabilityNamedFile($modulePath, 'report')
                || $this->hasCapabilityNamedFile($modulePath, 'pdf')
                || $this->hasCapabilityNamedFile($modulePath, 'print'),
            'exports' => $this->hasCapabilityNamedFile($modulePath, 'export')
                || $this->hasCapabilityNamedFile($modulePath, 'pdf')
                || $this->hasCapabilityNamedFile($modulePath, 'print'),
            'lifecycle_hooks' => $this->anyCapabilityFileExists($modulePath, ['install.php', 'uninstall.php', 'update.php', 'bootstrap.php'])
                || $this->hasCapabilityNamedFile($modulePath, 'Hooks'),
            'localization' => $this->hasCapabilityLocalization($modulePath),
            'dependencies' => !empty($manifest['requires']) && is_array($manifest['requires']),
            default => true,
        };
    }

    private function countCapabilityFiles(string $dir, string $pattern): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        return count(glob(rtrim($dir, '/') . '/' . $pattern) ?: []);
    }

    /**
     * @param array<int,string> $names
     */
    private function anyCapabilityFileExists(string $dir, array $names): bool
    {
        foreach ($names as $name) {
            if (is_file(rtrim($dir, '/') . '/' . $name)) {
                return true;
            }
        }
        return false;
    }

    private function hasCapabilityServices(string $modulePath): bool
    {
        if ($this->countCapabilityFiles($modulePath . '/Services', '*.php') > 0) {
            return true;
        }

        foreach (glob($modulePath . '/*Service.php') ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }

    private function hasCapabilityPermissions(string $modulePath): bool
    {
        foreach (glob($modulePath . '/*Policies.php') ?: [] as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return $this->hasCapabilityNamedFile($modulePath, 'Permission')
            || $this->hasCapabilityNamedFile($modulePath, 'Policy');
    }

    private function hasCapabilityNamedFile(string $modulePath, string $needle): bool
    {
        if (!is_dir($modulePath)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePath, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (stripos($file->getFilename(), $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hasCapabilityLocalization(string $modulePath): bool
    {
        foreach (['lang', 'Lang', 'Locale', 'locale', 'locales', 'Resources/lang'] as $dir) {
            if (is_dir($modulePath . '/' . $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private function expectedTablesForManifest(array $manifest): array
    {
        $migrationsDir = $this->runtimePath($manifest) . '/migrations';
        if (!is_dir($migrationsDir)) {
            return [];
        }

        $tables = [];
        $files = glob($migrationsDir . '/*.sql') ?: [];
        foreach ($files as $file) {
            $sql = strtolower((string)@file_get_contents($file));
            if ($sql === '') {
                continue;
            }

            if (preg_match_all('/create\s+table(?:\s+if\s+not\s+exists)?\s+`?([a-z0-9_]+)`?/i', $sql, $matches)) {
                foreach ((array)($matches[1] ?? []) as $table) {
                    $table = trim((string)$table);
                    if ($table !== '') {
                        $tables[$table] = $table;
                    }
                }
            }
        }

        return array_values($tables);
    }

    private function normalizeManifest(array $json, string $dir, string $sourceType = 'legacy_plugin'): array
    {
        $missing = [];

        $name = trim((string)($json['name'] ?? ''));
        $type = trim((string)($json['type'] ?? 'business'));
        if ($type === '') {
            $type = 'business';
        }

        $suite = trim((string)($json['suite'] ?? ''));
        if ($suite === '') {
            $suite = ($type === 'engine') ? 'core' : 'manufacturing';
            $missing[] = 'suite';
        }

        $group = trim((string)($json['group'] ?? ''));
        if ($group === '') {
            $group = $suite === 'core' ? 'platform' : ($suite === 'manufacturing' ? 'ipm' : 'general');
            $missing[] = 'group';
        }

        $displayName = trim((string)($json['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $name;
            $missing[] = 'display_name';
        }

        $category = trim((string)($json['category'] ?? ''));
        if ($category === '') {
            $category = $suite === 'core' ? 'system' : 'business';
            $missing[] = 'category';
        }

        $requires = $json['requires'] ?? [];
        if (!is_array($requires)) {
            $requires = [];
        }

        $out = $json;
        $out['display_name'] = $displayName;
        $out['version'] = (string)($json['version'] ?? '0.0.0');
        $out['type'] = $type;
        $out['suite'] = $suite;
        $out['group'] = $group;
        $out['category'] = $category;
        $out['display_order'] = (int)($json['display_order'] ?? 999);
        $out['requires'] = $requires;
        $out['is_core'] = array_key_exists('is_core', $json) ? (bool)$json['is_core'] : ($suite === 'core');
        $out['is_system'] = array_key_exists('is_system', $json) ? (bool)$json['is_system'] : ($suite === 'core' || $type === 'engine');
        $out['is_business'] = array_key_exists('is_business', $json) ? (bool)$json['is_business'] : ($suite !== 'core');
        $out['admin_only'] = array_key_exists('admin_only', $json) ? (bool)$json['admin_only'] : ($suite === 'core');
        $out['experimental'] = array_key_exists('experimental', $json) ? (bool)$json['experimental'] : ($suite === 'future');
        $out['entry'] = (string)($json['entry'] ?? 'routes.php');
        $out['package_type'] = (string)($json['package_type'] ?? 'module');
        $out['owner_app'] = (string)($json['owner_app'] ?? '');
        $out['module_key'] = (string)($json['module_key'] ?? $name);
        $out['__path'] = $dir;
        $out['__source_type'] = $sourceType;
        $out['__runtime_source'] = $this->resolveRuntimePath($name, $out, $dir, $sourceType);
        $out['__runtime_loadable'] = $this->isRuntimeSourceLoadable($out, $sourceType);
        $out['__legacy_mode'] = $this->resolveLegacyMode($name, $out, $dir, $sourceType);
        $out['__missing_fields'] = $missing;

        return $out;
    }

    private function isBundleOwnedModuleManifest(string $name, array $json): bool
    {
        $ownerApp = trim((string)($json['owner_app'] ?? ''));
        $packageType = strtolower(trim((string)($json['package_type'] ?? '')));
        if ($ownerApp !== '') {
            return true;
        }
        if ($packageType === 'module') {
            return true;
        }
        return class_exists(AppLegacyBridgeService::class) && AppLegacyBridgeService::isManufacturingOwnedPlugin($name);
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function runtimePath(array $manifest): string
    {
        $runtime = trim((string)($manifest['__runtime_source'] ?? ''));
        if ($runtime !== '') {
            return $runtime;
        }
        return rtrim((string)($manifest['__path'] ?? ''), '/');
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function isRuntimeLoadable(array $manifest): bool
    {
        return (bool)($manifest['__runtime_loadable'] ?? true);
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function resolveRuntimePath(string $name, array $manifest, string $dir, string $sourceType): string
    {
        $ownerApp = trim((string)($manifest['owner_app'] ?? ''));
        if ($sourceType === 'canonical_module') {
            return $dir;
        }

        if ($this->isBundleOwnedModuleManifest($name, $manifest)) {
            $canonical = PackageManager::canonicalModulePath($name, $ownerApp);
            return $canonical !== '' ? $canonical : $dir;
        }

        return $dir;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function isRuntimeSourceLoadable(array $manifest, string $sourceType): bool
    {
        if ($sourceType === 'legacy_detection') {
            return false;
        }

        $runtimePath = trim((string)($manifest['__runtime_source'] ?? ''));
        return $runtimePath !== '' && is_dir($runtimePath);
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function resolveLegacyMode(string $name, array $manifest, string $dir, string $sourceType): string
    {
        if ($sourceType === 'canonical_module' || $sourceType === 'legacy_plugin') {
            return '';
        }

        $canonical = trim((string)($manifest['__runtime_source'] ?? ''));
        $realLegacy = realpath($dir) ?: $dir;
        $realCanonical = $canonical !== '' ? (realpath($canonical) ?: $canonical) : '';

        if ($sourceType === 'legacy_detection' && $realCanonical === '') {
            return 'legacy_module_only';
        }
        if ($realCanonical !== '' && $realLegacy !== $realCanonical) {
            return 'duplicate_legacy_path';
        }
        if ($realCanonical !== '' && is_link($dir)) {
            return 'duplicate_legacy_path';
        }

        return '';
    }

    private function runtimeDependencyErrors(string $pluginName, array $activeMap): array
    {
        $m = $this->manifests[$pluginName] ?? null;
        if (!$m) {
            return ["Manifest missing for plugin {$pluginName}."];
        }

        $errors = [];
        foreach ((array)($m['requires'] ?? []) as $rawReq) {
            $depName = $this->dependencyName((string)$rawReq);
            if ($depName === '') {
                continue;
            }
            if (!isset($this->manifests[$depName])) {
                $errors[] = "Unknown dependency {$depName}.";
                continue;
            }
            if (!isset($activeMap[$depName])) {
                $errors[] = "Required dependency {$depName} is not active.";
            }
        }

        return $errors;
    }

    private function runtimeArchitectureWarnings(string $pluginName): array
    {
        $m = $this->manifests[$pluginName] ?? null;
        if (!$m) {
            return [];
        }

        $warnings = [];
        $suite = (string)($m['suite'] ?? 'uncategorized');
        foreach ((array)($m['requires'] ?? []) as $rawReq) {
            $depName = $this->dependencyName((string)$rawReq);
            if ($depName === '') {
                continue;
            }
            $depSuite = (string)(($this->manifests[$depName] ?? [])['suite'] ?? 'unknown');
            if ($suite === 'core' && $depSuite === 'manufacturing') {
                $warnings[] = "Core plugin depends on manufacturing dependency {$depName}.";
            }
        }

        return $warnings;
    }

    private function dependencyName(string $raw): string
    {
        return trim((string)preg_replace('/[<>=].*$/', '', $raw));
    }

    private function isStrictMode(): bool
    {
        $env = getenv('ERP_ARCH_STRICT_MODE');
        if (is_string($env) && trim($env) !== '') {
            $value = strtolower(trim($env));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        if (defined('APP_ROOT')) {
            $configPath = APP_ROOT . '/storage/architecture_policy.php';
            if (is_file($configPath)) {
                $cfg = require $configPath;
                if (is_array($cfg) && array_key_exists('strict_mode', $cfg)) {
                    return (bool)$cfg['strict_mode'];
                }
            }
        }

        return true;
    }

    /** @return array<int, string> */
    private function detectCycles(array $manifests): array
    {
        $graph = [];
        foreach ($manifests as $name => $m) {
            $deps = [];
            foreach ((array)($m['requires'] ?? []) as $rawReq) {
                $dep = $this->dependencyName((string)$rawReq);
                if ($dep !== '' && isset($manifests[$dep])) {
                    $deps[] = $dep;
                }
            }
            $graph[$name] = array_values(array_unique($deps));
        }

        $state = [];
        $cycle = [];

        $visit = static function (string $node) use (&$visit, &$state, &$cycle, $graph): void {
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                $s = $state[$next] ?? 0;
                if ($s === 0) {
                    $visit($next);
                } elseif ($s === 1) {
                    $cycle[$node] = true;
                    $cycle[$next] = true;
                }
            }
            $state[$node] = 2;
        };

        foreach (array_keys($graph) as $name) {
            if (($state[$name] ?? 0) === 0) {
                $visit($name);
            }
        }

        return array_keys($cycle);
    }
}
