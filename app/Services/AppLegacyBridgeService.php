<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Container;
use App\Core\EventBus;
use App\Core\PackageManager;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;

final class AppLegacyBridgeService
{
    /** @var array<int,string> */
    private const MANUFACTURING_PLUGINS = [
        'Products',
        'Workflow',
        'Supply',
        'Coverage',
        'Machines',
        'PartMachineMap',
        'PreOrders',
        'DailyOrders',
        'ProductionPlans',
        'ProductionQueue',
        'Ledger',
        'ProductionEntries',
        'QCPlans',
        'QCEntries',
        'DispatchEntries',
    ];

    /** @var array<string,array<int,string>> */
    private const REQUIRED_TABLES = [
        'Products' => ['products'],
        'Machines' => ['machines'],
        'PartMachineMap' => ['part_machine_map'],
        'DailyOrders' => ['daily_orders'],
        'PreOrders' => ['pre_orders'],
        'ProductionPlans' => ['production_plans'],
        'ProductionEntries' => ['production_entries'],
        'QCPlans' => ['qc_plans'],
        'QCEntries' => ['qc_entries'],
        'DispatchEntries' => ['dispatch_entries'],
        'Ledger' => ['stock_ledger_entries'],
    ];

    public static function shouldLoadLegacyPlugin(string $pluginName): bool
    {
        if (!in_array($pluginName, self::MANUFACTURING_PLUGINS, true)) {
            return true;
        }

        // Manufacturing-owned plugins may only run when Manufacturing app is enabled.
        // If app is disabled/uninstalled/purged/missing, these plugins must disappear.
        return self::manufacturingAppEnabled();
    }

    public static function shouldLoadLegacyWidget(string $pluginName): bool
    {
        return self::shouldLoadLegacyPlugin($pluginName);
    }

    public static function manufacturingAppEnabled(): bool
    {
        $app = self::manufacturingAppRow();
        return is_array($app) && ((string)($app['status'] ?? '')) === AppRegistryService::STATUS_ENABLED;
    }

    private static function manufacturingAppRow(): ?array
    {
        return DB::fetchOne('SELECT status FROM core_apps WHERE app_key = ? LIMIT 1', ['manufacturing']);
    }

    public static function manufacturingPluginList(): array
    {
        return self::MANUFACTURING_PLUGINS;
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    public static function legacyPluginsForApp(string $appKey, ?array $manifest = null): array
    {
        if (is_array($manifest) && !empty($manifest['legacy_bridge_plugins']) && is_array($manifest['legacy_bridge_plugins'])) {
            $plugins = [];
            foreach ((array)$manifest['legacy_bridge_plugins'] as $pluginName) {
                $pluginName = trim((string)$pluginName);
                if ($pluginName !== '') {
                    $plugins[$pluginName] = $pluginName;
                }
            }
            if ($plugins !== []) {
                return array_values($plugins);
            }
        }

        if ($appKey === 'manufacturing') {
            return self::MANUFACTURING_PLUGINS;
        }

        return [];
    }

    public static function isManufacturingOwnedPlugin(string $pluginName): bool
    {
        return in_array($pluginName, self::MANUFACTURING_PLUGINS, true);
    }

    public static function setManufacturingPluginRuntimeStatus(bool $active): void
    {
        self::setLegacyPluginRuntimeStatus('manufacturing', null, $active);
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    public static function installLegacyPluginsForApp(string $appKey, ?array $manifest = null): array
    {
        return self::installOrRepairLegacyPluginsForApp($appKey, $manifest, false, true);
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    public static function repairLegacyPluginsForApp(string $appKey, ?array $manifest = null, bool $activateRuntime = false): array
    {
        return self::installOrRepairLegacyPluginsForApp($appKey, $manifest, true, $activateRuntime);
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    public static function prepareLegacyPluginsForActivation(string $appKey, ?array $manifest = null): array
    {
        $plugins = self::orderedLegacyPluginList(self::legacyPluginsForApp($appKey, $manifest));
        if ($plugins === []) {
            return [];
        }

        $pm = self::pluginManager();
        $results = [];

        foreach ($plugins as $pluginName) {
            try {
                if (!$pm->isInstalled($pluginName)) {
                    $pm->install($pluginName);
                    $results[] = $pluginName . ':installed';
                } else {
                    $pm->update($pluginName);
                    $results[] = $pluginName . ':updated';
                }

                if ($pm->status($pluginName) !== 'active') {
                    $pm->enable($pluginName);
                    $results[] = $pluginName . ':enabled';
                }

                self::assertLegacyPluginSchemaReady($pluginName);
                $results[] = $pluginName . ':schema_ready';
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'Bundle activation failed in schema_ready phase for child plugin '
                    . $pluginName
                    . ': '
                    . $e->getMessage()
                    . '. Recommended next step: inspect the plugin migrations/install hooks for '
                    . $pluginName
                    . ' and rerun Repair Bundle.',
                    0,
                    $e
                );
            }
        }

        return $results;
    }

    /**
     * @param array<string,mixed>|null $manifest
     */
    public static function setLegacyPluginRuntimeStatus(string $appKey, ?array $manifest, bool $active): void
    {
        $plugins = self::legacyPluginsForApp($appKey, $manifest);
        if ($plugins === []) {
            return;
        }

        $pm = self::pluginManager();
        $ordered = self::orderedLegacyPluginList($plugins);
        if (!$active) {
            $ordered = array_reverse($ordered);
        }

        foreach ($ordered as $pluginName) {
            if (!$pm->isInstalled($pluginName)) {
                continue;
            }

            if ($active) {
                $pm->enable($pluginName);
            } else {
                $pm->disable($pluginName);
            }
        }
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    public static function purgeLegacyPluginsForApp(string $appKey, ?array $manifest = null): array
    {
        $plugins = array_reverse(self::orderedLegacyPluginList(self::legacyPluginsForApp($appKey, $manifest)));
        if ($plugins === []) {
            return [];
        }

        $pm = self::pluginManager();
        $purged = [];
        foreach ($plugins as $pluginName) {
            if (!$pm->isInstalled($pluginName)) {
                continue;
            }

            if ($pm->status($pluginName) !== 'inactive') {
                $pm->disable($pluginName);
            }

            $pm->uninstall($pluginName, true);
            $purged[] = $pluginName;
        }

        return array_reverse($purged);
    }

    /**
     * @return array<int,string>
     */
    public static function purgeManufacturingLegacyPlugins(): array
    {
        return self::purgeLegacyPluginsForApp('manufacturing');
    }

    /**
     * @param array<string,mixed>|null $manifest
     * @return array<int,string>
     */
    private static function installOrRepairLegacyPluginsForApp(string $appKey, ?array $manifest, bool $repair, bool $activateRuntime): array
    {
        $plugins = self::orderedLegacyPluginList(self::legacyPluginsForApp($appKey, $manifest));
        if ($plugins === []) {
            return [];
        }

        $pm = self::pluginManager();
        $results = [];
        foreach ($plugins as $pluginName) {
            if (!$pm->isInstalled($pluginName)) {
                $pm->install($pluginName);
                $results[] = $pluginName . ':installed';
                if ($activateRuntime && $pm->status($pluginName) !== 'active') {
                    $pm->enable($pluginName);
                    $results[] = $pluginName . ':enabled';
                } elseif (!$activateRuntime && $pm->status($pluginName) === 'active') {
                    $pm->disable($pluginName);
                    $results[] = $pluginName . ':disabled';
                }
                continue;
            }

            if ($repair) {
                $pm->update($pluginName);
                $results[] = $pluginName . ':updated';
            }

            if ($activateRuntime && $pm->status($pluginName) !== 'active') {
                $pm->enable($pluginName);
                $results[] = $pluginName . ':enabled';
            } elseif (!$activateRuntime && $pm->status($pluginName) === 'active') {
                $pm->disable($pluginName);
                $results[] = $pluginName . ':disabled';
            }
        }

        return $results;
    }

    private static function assertLegacyPluginSchemaReady(string $pluginName): void
    {
        $requiredTables = self::requiredTablesForPlugin($pluginName);
        foreach ($requiredTables as $table) {
            $exists = DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            );

            if (!is_array($exists)) {
                throw new \RuntimeException('Required table missing: ' . $table);
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private static function requiredTablesForPlugin(string $pluginName): array
    {
        $manifests = self::pluginManager()->manifests();
        $manifest = is_array($manifests[$pluginName] ?? null) ? (array)$manifests[$pluginName] : null;
        if (is_array($manifest)) {
            $required = [];
            foreach ((array)($manifest['required_tables'] ?? []) as $table) {
                $table = trim((string)$table);
                if ($table !== '') {
                    $required[$table] = $table;
                }
            }
            if ($required !== []) {
                return array_values($required);
            }
        }

        return self::REQUIRED_TABLES[$pluginName] ?? [];
    }

    /**
     * @param array<int,string> $plugins
     * @return array<int,string>
     */
    private static function orderedLegacyPluginList(array $plugins): array
    {
        $plugins = array_values(array_unique(array_filter(array_map('strval', $plugins), static fn(string $name): bool => trim($name) !== '')));
        if ($plugins === []) {
            return [];
        }

        $pluginSet = array_fill_keys($plugins, true);
        $manifestMap = [];
        foreach ($plugins as $pluginName) {
            $sourcePath = PackageManager::pluginSourcePath($pluginName);
            $path = rtrim($sourcePath, '/') . '/plugin.json';
            $manifest = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
            $manifestMap[$pluginName] = is_array($manifest) ? $manifest : [];
        }

        usort($plugins, static function (string $left, string $right) use ($manifestMap): int {
            $leftOrder = (int)($manifestMap[$left]['display_order'] ?? 999);
            $rightOrder = (int)($manifestMap[$right]['display_order'] ?? 999);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcasecmp($left, $right);
        });

        $ordered = [];
        $visiting = [];
        $visited = [];
        $visit = static function (string $pluginName) use (&$visit, &$ordered, &$visiting, &$visited, $manifestMap, $pluginSet): void {
            if (isset($visited[$pluginName]) || isset($visiting[$pluginName])) {
                return;
            }
            $visiting[$pluginName] = true;
            foreach ((array)($manifestMap[$pluginName]['requires'] ?? []) as $rawDep) {
                $depName = self::dependencyName((string)$rawDep);
                if ($depName !== '' && isset($pluginSet[$depName])) {
                    $visit($depName);
                }
            }
            unset($visiting[$pluginName]);
            $visited[$pluginName] = true;
            $ordered[] = $pluginName;
        };

        foreach ($plugins as $pluginName) {
            $visit($pluginName);
        }

        return $ordered;
    }

    private static function dependencyName(string $rawDependency): string
    {
        $rawDependency = trim($rawDependency);
        if ($rawDependency === '') {
            return '';
        }

        $parts = preg_split('/[<>=!]/', $rawDependency, 2);
        return trim((string)($parts[0] ?? ''));
    }

    private static function pluginManager(): PluginManager
    {
        return new PluginManager(
            APP_ROOT . '/plugins',
            new Container(),
            new Router(),
            new View(APP_ROOT . '/public/views'),
            new EventBus()
        );
    }
}
