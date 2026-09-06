<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Container;
use App\Core\DB;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\TOTP;
use App\Core\View;
use mysqli;

final class CoreSetupService
{
    private SetupProfileService $profiles;
    private SetupStateService $state;

    public function __construct(?SetupProfileService $profiles = null, ?SetupStateService $state = null)
    {
        $this->profiles = $profiles ?? new SetupProfileService();
        $this->state = $state ?? new SetupStateService();
    }

    /**
     * @return array<string,mixed>
     */
    public function preflight(?array $dbConfig = null): array
    {
        $dbConfig = is_array($dbConfig) ? $dbConfig : $this->readDbConfig();
        $paths = [
            APP_ROOT . '/storage',
            APP_ROOT . '/storage/logs',
            APP_ROOT . '/packages',
            APP_ROOT . '/packages/uploads',
            APP_ROOT . '/packages/installed',
            APP_ROOT . '/packages/extracted',
            APP_ROOT . '/public/assets',
            APP_ROOT . '/public/assets/system',
            APP_ROOT . '/public/assets/rendering',
            APP_ROOT . '/public/assets/effects',
            APP_ROOT . '/public/assets/themes',
        ];

        $environment = [
            [
                'label' => 'PHP >= 8.1',
                'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
                'detail' => PHP_VERSION,
            ],
            [
                'label' => 'mysqli extension',
                'ok' => extension_loaded('mysqli'),
                'detail' => extension_loaded('mysqli') ? 'loaded' : 'missing',
            ],
            [
                'label' => 'json extension',
                'ok' => extension_loaded('json'),
                'detail' => extension_loaded('json') ? 'loaded' : 'missing',
            ],
            [
                'label' => 'zip extension',
                'ok' => extension_loaded('zip'),
                'detail' => extension_loaded('zip') ? 'loaded' : 'missing',
            ],
        ];

        $writable = [];
        foreach ($paths as $path) {
            $exists = is_dir($path) || @mkdir($path, 0775, true);
            $writable[] = [
                'path' => $path,
                'ok' => $exists && self::isDirWritable($path),
            ];
        }

        $db = $this->databaseCheck($dbConfig);

        return [
            'environment' => $environment,
            'writable_paths' => $writable,
            'database' => $db,
            'needs_setup' => $this->needsSetupBootstrap(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function statusSummary(): array
    {
        $preflight = $this->preflight();
        $dbReady = (bool)($preflight['database']['ok'] ?? false);
        $coreTables = [
            'users',
            'installed_plugins',
            'core_settings',
            'core_apps',
            'core_app_modules',
            'core_app_migrations',
            'core_app_permissions',
            'core_app_hooks',
            'core_schema_snapshots',
        ];

        $tableRows = [];
        if ($dbReady) {
            foreach ($coreTables as $table) {
                $tableRows[] = [
                    'table' => $table,
                    'exists' => $this->tableExists($table),
                ];
            }
        }

        $moduleRows = $this->corePlatformAppRows();

        $coreBundleRows = [];
        if ($dbReady) {
            foreach ($this->coreBundleRows() as $bundle) {
                $coreBundleRows[] = [
                    'app_key' => (string)($bundle['row']['app_key'] ?? ''),
                    'name' => (string)($bundle['row']['app_name'] ?? $bundle['row']['app_key'] ?? ''),
                    'version' => (string)($bundle['row']['version'] ?? ''),
                    'status' => (string)($bundle['row']['status'] ?? AppRegistryService::STATUS_UPLOADED),
                ];
            }
        }

        $adminExists = false;
        if ($dbReady) {
            try {
                $adminExists = !Auth::noAdminExists();
            } catch (\Throwable $e) {
                $adminExists = false;
            }
        }

        $environmentIssues = count(array_filter((array)($preflight['environment'] ?? []), static fn(array $row): bool => empty($row['ok'])));
        $writableIssues = count(array_filter((array)($preflight['writable_paths'] ?? []), static fn(array $row): bool => empty($row['ok'])));
        $missingTables = count(array_filter($tableRows, static fn(array $row): bool => empty($row['exists'])));
        $inactiveModules = count(array_filter($moduleRows, static fn(array $row): bool => empty($row['installed']) || empty($row['active'])));
        $unhealthyModules = count(array_filter($moduleRows, static fn(array $row): bool => empty($row['healthy'])));
        $inactiveCoreBundles = count(array_filter($coreBundleRows, static fn(array $row): bool => (string)($row['status'] ?? '') !== AppRegistryService::STATUS_ENABLED));
        $healthyModules = count(array_filter($moduleRows, static fn(array $row): bool => !empty($row['healthy'])));
        $activeModules = count(array_filter($moduleRows, static fn(array $row): bool => !empty($row['active'])));
        $allPlatformModulesHealthy = $moduleRows !== [] && $unhealthyModules === 0;

        $status = 'verified';
        $nextAction = 'Core bootstrap is healthy.';
        if ($environmentIssues > 0 || $writableIssues > 0 || !$dbReady) {
            $status = 'warning';
            $nextAction = 'Fix environment, writable path, or database issues before running Core setup.';
        } elseif ($missingTables > 0 || $unhealthyModules > 0 || $inactiveCoreBundles > 0) {
            $status = 'installed';
            $nextAction = 'Run Core bootstrap repair to finish schema, platform module, and core bundle setup.';
        } elseif (!$adminExists) {
            $status = 'installed';
            $nextAction = 'Create or complete the admin bootstrap.';
        }

        return [
            'status' => $status,
            'next_action' => $nextAction,
            'preflight' => $preflight,
            'core_schema' => [
                'total' => count($tableRows),
                'ready' => count($tableRows) - $missingTables,
                'missing' => $missingTables,
                'tables' => $tableRows,
            ],
            'platform_modules' => [
                'total' => count($moduleRows),
                'active' => $activeModules,
                'healthy' => $healthyModules,
                'all_healthy' => $allPlatformModulesHealthy,
                'rows' => $moduleRows,
            ],
            'core_platform_apps' => [
                'total' => count($moduleRows),
                'active' => $activeModules,
                'healthy' => $healthyModules,
                'all_healthy' => $allPlatformModulesHealthy,
                'rows' => $moduleRows,
            ],
            'core_bundles' => [
                'total' => count($coreBundleRows),
                'active' => count(array_filter($coreBundleRows, static fn(array $row): bool => (string)($row['status'] ?? '') === AppRegistryService::STATUS_ENABLED)),
                'rows' => $coreBundleRows,
            ],
            'admin_bootstrap' => [
                'ready' => $adminExists,
                'label' => $adminExists ? 'Admin bootstrap complete' : 'Admin bootstrap pending',
            ],
            'setup_run' => $dbReady ? $this->state->latestRun('core', 'core') : null,
        ];
    }

    public function needsSetupBootstrap(): bool
    {
        $config = $this->readDbConfig();
        if (!is_array($config)) {
            return true;
        }

        try {
            $this->databaseCheck($config, true);
            $this->ensureBootstrapTables();
            return Auth::noAdminExists();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function run(array $input): array
    {
        $dbConfig = [
            'host' => trim((string)($input['db_host'] ?? 'localhost')),
            'port' => (int)($input['db_port'] ?? 3306),
            'name' => trim((string)($input['db_name'] ?? '')),
            'user' => trim((string)($input['db_user'] ?? '')),
            'pass' => (string)($input['db_pass'] ?? ''),
            'charset' => trim((string)($input['db_charset'] ?? 'utf8mb4')),
            'timezone' => trim((string)($input['timezone'] ?? 'Asia/Tokyo')),
        ];
        $createDatabase = !empty($input['db_create_if_missing']);
        $adminEmail = strtolower(trim((string)($input['admin_email'] ?? '')));
        $adminPassword = (string)($input['admin_password'] ?? '');
        $systemName = trim((string)($input['system_name'] ?? 'Susankhya OS'));
        $currency = trim((string)($input['currency'] ?? 'JPY'));
        $locale = trim((string)($input['locale'] ?? 'en'));
        $theme = trim((string)($input['theme'] ?? default_theme_preference()));
        $workspaceHome = trim((string)($input['workspace_home'] ?? '/admin/setup/onboarding'));

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('A valid admin email is required.');
        }
        if (strlen($adminPassword) < 10) {
            throw new \RuntimeException('Admin password must be at least 10 characters.');
        }
        if ($dbConfig['name'] === '' || $dbConfig['user'] === '') {
            throw new \RuntimeException('Database name and user are required.');
        }
        if ($workspaceHome === '' || !str_starts_with($workspaceHome, '/')) {
            throw new \RuntimeException('Workspace home must be a valid internal route.');
        }

        $runId = 0;
        $warnings = [];
        $dbProbe = [];
        $coreTables = [];
        $platformModules = [];
        $discoveredBundles = [];
        $coreBundleMigrations = [];
        $coreBundles = [];
        $adminSetup = [];
        $settings = [];

        try {
            $dbProbe = $this->databaseCheck($dbConfig, false, $createDatabase, 15);
            if (!(bool)($dbProbe['ok'] ?? false)) {
                throw new \RuntimeException((string)($dbProbe['message'] ?? 'Database connection failed.'));
            }

            $this->writeDbConfig($dbConfig);
            $this->ensureBootstrapTables();
            $runId = $this->state->startRun('core', 'core', 'core_setup', [
                ['key' => 'database_setup', 'label' => 'Database setup'],
                ['key' => 'bootstrap_tables', 'label' => 'Bootstrap tables'],
                ['key' => 'core_schema', 'label' => 'Core schema install'],
                ['key' => 'local_bundle_discovery', 'label' => 'Local bundle discovery'],
                ['key' => 'platform_modules', 'label' => 'Platform modules install'],
                ['key' => 'core_bundle_migrations', 'label' => 'Core bundle migrations'],
                ['key' => 'core_bundles', 'label' => 'Core bundle activation'],
                ['key' => 'admin_bootstrap', 'label' => 'Admin bootstrap'],
                ['key' => 'initial_settings', 'label' => 'Initial system settings'],
            ], ['profile' => 'core_only']);

            $this->state->startStep($runId, 'database_setup');
            $this->state->completeStep($runId, 'database_setup', SetupStateService::STATUS_INSTALLED, 'Database config written and connection verified.');

            $this->state->startStep($runId, 'bootstrap_tables');
            $this->ensureBootstrapTables();
            $this->state->completeStep($runId, 'bootstrap_tables', SetupStateService::STATUS_INSTALLED, 'Bootstrap tables are ready.');

            $this->state->startStep($runId, 'core_schema');
            $coreTables = $this->installCoreSchema();
            $this->state->completeStep($runId, 'core_schema', SetupStateService::STATUS_INSTALLED, 'Core schema installed: ' . implode(', ', $coreTables));

            $this->state->startStep($runId, 'local_bundle_discovery');
            $discoveredBundles = $this->discoverLocalBundles();
            $this->state->completeStep($runId, 'local_bundle_discovery', SetupStateService::STATUS_INSTALLED, implode(' ', $discoveredBundles));

            $this->state->startStep($runId, 'platform_modules');
            $platformModules = $this->installPlatformModules();
            $this->state->completeStep($runId, 'platform_modules', SetupStateService::STATUS_CONFIGURED, 'Platform modules active: ' . implode(', ', $platformModules));

            $this->state->startStep($runId, 'core_bundle_migrations');
            $coreBundleMigrations = $this->runCoreBundleMigrations();
            $this->state->completeStep($runId, 'core_bundle_migrations', SetupStateService::STATUS_INSTALLED, implode(' ', $coreBundleMigrations));

            $this->state->startStep($runId, 'core_bundles');
            $coreBundles = $this->installAndEnableCoreBundles();
            $this->state->completeStep($runId, 'core_bundles', SetupStateService::STATUS_CONFIGURED, implode(' ', $coreBundles));

            $this->state->startStep($runId, 'admin_bootstrap');
            $adminSetup = $this->createAdminUser($adminEmail, $adminPassword);
            $this->state->completeStep($runId, 'admin_bootstrap', SetupStateService::STATUS_CONFIGURED, 'Admin user created and 2FA bootstrap staged.');

            $this->state->startStep($runId, 'initial_settings');
            $settings = $this->writeInitialSettings([
                'system.name' => $systemName,
                'company.name' => $systemName,
                'system.timezone' => $dbConfig['timezone'],
                'system.default_currency' => strtoupper($currency),
                'system.currency' => strtoupper($currency),
                'system.default_language' => strtolower($locale),
                'system.locale' => strtolower($locale),
                'ui.theme' => $theme,
                'system.theme' => $theme,
                'ui.default_home' => $workspaceHome,
                'system.default_dashboard' => $workspaceHome,
                'setup.core.completed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->state->completeStep($runId, 'initial_settings', SetupStateService::STATUS_VERIFIED, 'Initial settings saved: ' . implode(', ', $settings));

            $result = [
                'phase' => 'core_setup',
                'status' => 'ok',
                'database' => $dbProbe,
                'core_schema' => $coreTables,
                'platform_modules' => $platformModules,
                'bundle_discovery' => $discoveredBundles,
                'core_bundle_migrations' => $coreBundleMigrations,
                'core_bundles' => $coreBundles,
                'admin' => $adminSetup,
                'settings' => $settings,
                'profile' => 'core_only',
            ];
            $this->state->finalizeRun($runId, SetupStateService::STATUS_VERIFIED, $warnings, null, false, '', null, null, 'core_repair', $result);
            return $result;
        } catch (\Throwable $e) {
            if ($runId > 0) {
                $failedStep = $this->latestRunningStepKey($runId);
                if ($failedStep !== '') {
                    $this->state->failStep($runId, $failedStep, $e->getMessage(), $warnings, false, 'Review completed steps and continue with Core repair.');
                }
                $this->state->finalizeRun(
                    $runId,
                    SetupStateService::STATUS_FAILED,
                    $warnings,
                    $e->getMessage(),
                    false,
                    'Core setup partially applied. Run Core repair or retry setup after fixing the failed step.',
                    'core_repair',
                    'core_setup',
                    'core_repair',
                    [
                        'database' => $dbProbe,
                        'core_schema' => $coreTables,
                        'platform_modules' => $platformModules,
                        'bundle_discovery' => $discoveredBundles,
                        'core_bundle_migrations' => $coreBundleMigrations,
                        'core_bundles' => $coreBundles,
                    ]
                );
            }
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function repairBootstrap(): array
    {
        $this->ensureBootstrapTables();
        $runId = $this->state->startRun('core', 'core', 'core_repair', [
            ['key' => 'bootstrap_tables', 'label' => 'Bootstrap tables'],
            ['key' => 'core_schema', 'label' => 'Core schema repair'],
            ['key' => 'local_bundle_discovery', 'label' => 'Local bundle discovery'],
            ['key' => 'platform_modules', 'label' => 'Platform module repair'],
            ['key' => 'core_bundle_migrations', 'label' => 'Core bundle migrations'],
            ['key' => 'core_bundles', 'label' => 'Core bundle activation'],
        ]);

        $coreTables = [];
        $platformModules = [];
        $discoveredBundles = [];
        $coreBundleMigrations = [];
        $coreBundles = [];
        try {
            $this->state->startStep($runId, 'bootstrap_tables');
            $this->ensureBootstrapTables();
            $this->state->completeStep($runId, 'bootstrap_tables', SetupStateService::STATUS_INSTALLED, 'Bootstrap tables verified.');

            $this->state->startStep($runId, 'core_schema');
            $coreTables = $this->installCoreSchema();
            $this->state->completeStep($runId, 'core_schema', SetupStateService::STATUS_INSTALLED, 'Core schema repaired.');

            $this->state->startStep($runId, 'local_bundle_discovery');
            $discoveredBundles = $this->discoverLocalBundles();
            $this->state->completeStep($runId, 'local_bundle_discovery', SetupStateService::STATUS_INSTALLED, implode(' ', $discoveredBundles));

            $this->state->startStep($runId, 'platform_modules');
            $platformModules = $this->installPlatformModules();
            $this->state->completeStep($runId, 'platform_modules', SetupStateService::STATUS_CONFIGURED, 'Platform modules repaired.');

            $this->state->startStep($runId, 'core_bundle_migrations');
            $coreBundleMigrations = $this->runCoreBundleMigrations();
            $this->state->completeStep($runId, 'core_bundle_migrations', SetupStateService::STATUS_INSTALLED, implode(' ', $coreBundleMigrations));

            $this->state->startStep($runId, 'core_bundles');
            $coreBundles = $this->installAndEnableCoreBundles();
            $this->state->completeStep($runId, 'core_bundles', SetupStateService::STATUS_CONFIGURED, implode(' ', $coreBundles));

            $result = [
                'phase' => 'core_repair',
                'status' => 'ok',
                'core_schema' => $coreTables,
                'platform_modules' => $platformModules,
                'core_bundles' => $coreBundles,
            ];
            $this->state->finalizeRun($runId, SetupStateService::STATUS_CONFIGURED, [], null, false, '', null, null, 'core_repair', $result);
            return $result;
        } catch (\Throwable $e) {
            $failedStep = $this->latestRunningStepKey($runId);
            if ($failedStep !== '') {
                $this->state->failStep($runId, $failedStep, $e->getMessage(), [], false, 'Repair the platform and re-run verification.');
            }
            $this->state->finalizeRun($runId, SetupStateService::STATUS_PARTIAL, [], $e->getMessage(), false, 'Core repair only completed partially.', 'core_repair', 'core_repair', 'core_repair', [
                'core_schema' => $coreTables,
                'platform_modules' => $platformModules,
                'core_bundles' => $coreBundles,
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function readDbConfig(): ?array
    {
        $config = app_db_config();
        return is_array($config) ? $config : null;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function testDatabaseConfig(array $config, bool $createDatabase = false): array
    {
        return $this->databaseCheck($config, false, $createDatabase, 15);
    }

    /**
     * @param array<string,mixed> $config
     */
    public function saveDbConfig(array $config): void
    {
        $this->writeDbConfig($config);
    }

    /**
     * @param array<string,mixed>|null $config
     * @param int $connectTimeout seconds for TCP connect; lower for bootstrap health checks, higher during actual setup
     * @return array<string,mixed>
     */
    private function databaseCheck(?array $config, bool $throwOnFailure = false, bool $createDatabase = false, int $connectTimeout = 3): array
    {
        if (!is_array($config)) {
            return [
                'ok' => false,
                'message' => 'Database configuration has not been written yet.',
            ];
        }

        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $user = (string)($config['user'] ?? '');
        $pass = (string)($config['pass'] ?? '');
        $name = (string)($config['name'] ?? '');
        $charset = strtolower(trim((string)($config['charset'] ?? 'utf8mb4')));
        $charsetCollation = [
            'utf8mb4' => 'utf8mb4_unicode_ci',
            'utf8' => 'utf8_unicode_ci',
            'utf8mb3' => 'utf8_unicode_ci',
        ];
        if (!isset($charsetCollation[$charset])) {
            if ($throwOnFailure) {
                throw new \RuntimeException('Unsupported database charset.');
            }
            return [
                'ok' => false,
                'message' => 'Unsupported database charset.',
            ];
        }

        $connection = \mysqli_init();
        \mysqli_options($connection, \MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
        @\mysqli_real_connect($connection, $host, $user, $pass, '', $port);
        if ($connection->connect_errno) {
            if ($throwOnFailure) {
                throw new \RuntimeException('DB connect failed: ' . $connection->connect_error);
            }
            return [
                'ok' => false,
                'message' => 'DB connect failed: ' . $connection->connect_error,
            ];
        }

        if ($createDatabase) {
            $quoted = '`' . str_replace('`', '``', $name) . '`';
            $collation = $charsetCollation[$charset];
            if (!$connection->query('CREATE DATABASE IF NOT EXISTS ' . $quoted . ' CHARACTER SET ' . $charset . ' COLLATE ' . $collation)) {
                if ($throwOnFailure) {
                    throw new \RuntimeException('Create database failed: ' . $connection->error);
                }
                return [
                    'ok' => false,
                    'message' => 'Create database failed: ' . $connection->error,
                ];
            }
        }

        if (!$connection->select_db($name)) {
            if ($throwOnFailure) {
                throw new \RuntimeException('Select database failed: ' . $connection->error);
            }
            return [
                'ok' => false,
                'message' => 'Select database failed: ' . $connection->error,
            ];
        }

        $connection->set_charset($charset);
        $connection->close();

        return [
            'ok' => true,
            'message' => 'Database connection verified.',
            'database' => $name,
        ];
    }

    private function writeDbConfig(array $config): void
    {
        $payload = "<?php\nreturn " . var_export([
            'host' => (string)$config['host'],
            'name' => (string)$config['name'],
            'user' => (string)$config['user'],
            'pass' => (string)$config['pass'],
            'port' => (int)$config['port'],
            'charset' => (string)$config['charset'],
            'timezone' => (string)$config['timezone'],
        ], true) . ";\n";

        $dir = APP_ROOT . '/storage';
        if (!is_dir($dir) || !self::isDirWritable($dir)) {
            throw new \RuntimeException('Storage directory is not writable; cannot persist database configuration.');
        }
        $file = $dir . '/db_config.php';
        if (@file_put_contents($file, $payload) === false) {
            throw new \RuntimeException('Unable to write storage/db_config.php');
        }
    }

    private function ensureBootstrapTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS installed_plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            version VARCHAR(20) NOT NULL,
            type ENUM('engine','business') DEFAULT 'business',
            status ENUM('active','inactive') DEFAULT 'active',
            requires_json JSON NULL,
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'User',
            twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
            twofa_secret VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_settings (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(190) NOT NULL UNIQUE,
            setting_value LONGTEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->state->ensureTables();
    }

    /**
     * @return array<int,string>
     */
    private function installCoreSchema(): array
    {
        $tables = ['installed_plugins', 'users', 'core_settings'];
        (new AppMigrationService())->ensureCoreTables();
        $tables = array_merge($tables, [
            'core_apps',
            'core_app_modules',
            'core_app_migrations',
            'core_app_permissions',
            'core_app_hooks',
            'core_schema_snapshots',
        ]);

        return $tables;
    }

    /**
     * @return array<int,string>
     */
    private function installPlatformModules(): array
    {
        $pm = $this->pluginManager();
        $installed = [];
        $modules = (new ModuleLifecycleService())->orderedModules($this->platformModuleList());
        foreach ($modules as $moduleName) {
            if (!$pm->isInstalled($moduleName)) {
                $pm->install($moduleName);
            } else {
                $pm->update($moduleName);
            }
            if ($pm->status($moduleName) !== 'active') {
                $pm->enable($moduleName);
            }
            $installed[] = $moduleName;
        }

        return $installed;
    }

    /**
     * @return array<int,string>
     */
    private function discoverLocalBundles(): array
    {
        (new AppLocalDiscoveryService())->syncLocalApps();

        $coreBundles = $this->coreBundleRows();
        if ($coreBundles === []) {
            return ['No core bundles discovered in the local app registry.'];
        }

        $messages = [];
        foreach ($coreBundles as $bundle) {
            $messages[] = 'Core bundle discovered: ' . (string)($bundle['row']['app_key'] ?? '') . '.';
        }

        return $messages;
    }

    /**
     * @return array<int,string>
     */
    private function runCoreBundleMigrations(): array
    {
        $messages = [];
        $migrationService = new AppMigrationService();

        foreach ($this->coreBundleRows() as $bundle) {
            $row = (array)($bundle['row'] ?? []);
            $manifest = (array)($bundle['manifest'] ?? []);
            $appKey = trim((string)($row['app_key'] ?? ''));
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if (!in_array($status, [
                AppRegistryService::STATUS_INSTALLED,
                AppRegistryService::STATUS_ENABLED,
                AppRegistryService::STATUS_UPGRADE_PENDING,
            ], true)) {
                continue;
            }

            $installPath = trim((string)($row['install_path'] ?? ''));
            $migrationsPath = trim((string)($manifest['migrations_path'] ?? ''));
            if ($appKey === '' || $installPath === '' || $migrationsPath === '') {
                continue;
            }

            $dir = rtrim($installPath, '/') . '/' . ltrim($migrationsPath, '/');
            if (!is_dir($dir)) {
                continue;
            }

            $applied = $migrationService->runMigrations($appKey, (string)($row['version'] ?? $manifest['version'] ?? '0.0.0'), $dir);
            if ($applied === []) {
                $messages[] = 'No pending migrations for core bundle: ' . $appKey . '.';
            } else {
                $messages[] = 'Applied core bundle migrations for ' . $appKey . ': ' . implode(', ', $applied) . '.';
            }
        }

        return $messages !== [] ? $messages : ['No core bundle migrations were required.'];
    }

    /**
     * @return array<int,string>
     */
    private function installAndEnableCoreBundles(): array
    {
        $messages = [];
        $installer = new AppInstallService();
        $lifecycle = new AppLifecycleService();

        foreach ($this->coreBundleRows() as $bundle) {
            $row = (array)($bundle['row'] ?? []);
            $appKey = trim((string)($row['app_key'] ?? ''));
            if ($appKey === '') {
                continue;
            }

            $status = (string)($row['status'] ?? AppRegistryService::STATUS_UPLOADED);
            if (in_array($status, [
                AppRegistryService::STATUS_UPLOADED,
                AppRegistryService::STATUS_UNINSTALLED,
                AppRegistryService::STATUS_UPGRADE_PENDING,
                AppRegistryService::STATUS_BROKEN,
            ], true)) {
                foreach ($installer->install($appKey) as $message) {
                    $messages[] = (string)$message;
                }
                $messages[] = 'Installed core bundle: ' . $appKey . '.';
                $row = (new AppRegistryService())->find($appKey) ?? $row;
                $status = (string)($row['status'] ?? AppRegistryService::STATUS_INSTALLED);
            }

            if ($status !== AppRegistryService::STATUS_ENABLED) {
                $lifecycle->enable($appKey);
                $messages[] = 'Activated core bundle: ' . $appKey . '.';
            } else {
                $messages[] = 'Core bundle already active: ' . $appKey . '.';
            }
        }

        return $messages !== [] ? array_values(array_unique($messages)) : ['No core bundles required activation.'];
    }

    /**
     * @return array<int,string>
     */
    private function platformModuleList(): array
    {
        return array_values(array_map(
            static fn(array $row): string => (string)($row['name'] ?? ''),
            $this->corePlatformCatalogRows()
        ));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function corePlatformCatalogRows(): array
    {
        $catalog = new PluginCatalogService();
        $rows = [];
        foreach ($catalog->corePlatformManifests((array)$this->pluginManager()->manifests()) as $moduleName => $manifest) {
            $rows[] = [
                'name' => (string)$moduleName,
                'display_name' => $catalog->normalizedDisplayName((string)$moduleName, (array)$manifest),
                'display_order' => (int)($manifest['display_order'] ?? 9999),
                'manifest' => (array)$manifest,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $orderCompare = ((int)($left['display_order'] ?? 9999)) <=> ((int)($right['display_order'] ?? 9999));
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function corePlatformAppRows(): array
    {
        $rows = [];
        $panelMap = [];

        try {
            foreach ((new ModuleLifecycleService())->panelRows() as $panel) {
                $name = strtolower(trim((string)($panel['name'] ?? '')));
                if ($name !== '') {
                    $panelMap[$name] = $panel;
                }
            }
        } catch (\Throwable) {
            $panelMap = [];
        }

        foreach ($this->corePlatformCatalogRows() as $catalogRow) {
            $name = (string)($catalogRow['name'] ?? '');
            $panel = is_array($panelMap[strtolower($name)] ?? null) ? (array)$panelMap[strtolower($name)] : [];
            $installed = !empty($panel['installed']);
            $runtimeStatus = strtolower(trim((string)($panel['status'] ?? ($installed ? 'inactive' : 'not_installed'))));
            $missingTables = count((array)($panel['missing_tables'] ?? []));
            $schemaGaps = count((array)($panel['schema_gaps'] ?? []));
            $dependencyErrors = count((array)($panel['dependency_errors'] ?? []));
            $schemaSynced = $installed && $missingTables === 0 && $schemaGaps === 0;
            $active = $installed && $runtimeStatus === 'active';
            $healthy = $installed && $active && $schemaSynced && $dependencyErrors === 0;

            $rows[] = [
                'name' => $name,
                'display_name' => (string)($catalogRow['display_name'] ?? $name),
                'installed' => $installed,
                'installed_label' => $installed ? 'Installed' : 'Not installed',
                'status' => $runtimeStatus,
                'active' => $active,
                'active_label' => $active ? 'Active' : 'Inactive',
                'schema_synced' => $schemaSynced,
                'schema_label' => $schemaSynced ? 'Synced' : 'Needs sync',
                'verified' => $healthy,
                'healthy' => $healthy,
                'healthy_label' => $healthy ? 'Healthy' : 'Needs attention',
                'success_tick' => $healthy,
                'missing_tables' => $missingTables,
                'schema_gaps' => $schemaGaps,
                'dependency_errors' => $dependencyErrors,
                'next_action' => $this->corePlatformModuleAction($name, $installed, $active, $schemaSynced, $dependencyErrors),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function corePlatformModuleAction(string $name, bool $installed, bool $active, bool $schemaSynced, int $dependencyErrors): ?array
    {
        if (!$installed) {
            return [
                'label' => 'Install',
                'operation' => 'install',
                'url' => '/admin/setup/modules/action',
            ];
        }

        if (!$active) {
            return [
                'label' => 'Activate',
                'operation' => 'enable',
                'url' => '/admin/setup/modules/action',
            ];
        }

        if (!$schemaSynced) {
            return [
                'label' => 'Sync Schema',
                'operation' => 'schema_sync',
                'url' => '/admin/setup/modules/action',
            ];
        }

        if ($dependencyErrors > 0) {
            return [
                'label' => 'Repair',
                'operation' => 'repair',
                'url' => '/admin/setup/modules/action',
            ];
        }

        return null;
    }

    /**
     * @return array<int,array{row:array<string,mixed>,manifest:array<string,mixed>}>
     */
    private function coreBundleRows(): array
    {
        $rows = [];
        try {
            foreach ((new AppRegistryService())->listAll() as $row) {
                $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
                if (!is_array($manifest)) {
                    continue;
                }

                if (!$this->isCoreBundle($row, $manifest)) {
                    continue;
                }

                $rows[] = [
                    'row' => $row,
                    'manifest' => $manifest,
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($left['row']['app_key'] ?? ''), (string)($right['row']['app_key'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $manifest
     */
    private function isCoreBundle(array $row, array $manifest): bool
    {
        $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
        if (in_array($appKey, ['platform', 'shell'], true)) {
            return true;
        }

        $packageType = strtolower(trim((string)($manifest['package_type'] ?? 'bundle')));
        if ($packageType !== 'bundle') {
            return false;
        }

        $appType = strtolower(trim((string)($row['app_type'] ?? $manifest['type'] ?? '')));
        $suite = strtolower(trim((string)($manifest['suite'] ?? '')));
        $ownerApp = strtolower(trim((string)($manifest['owner_app'] ?? '')));
        $isCore = (bool)($manifest['is_core'] ?? false);

        return $appType === 'engine' || $suite === 'core' || $ownerApp === 'platform' || $isCore;
    }

    private function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $safe = DB::conn()->real_escape_string($table);
        return DB::fetchOne("SHOW TABLES LIKE '{$safe}'") !== null;
    }

    private function latestRunningStepKey(int $runId): string
    {
        $steps = $this->state->stepsForRun($runId);
        foreach (array_reverse($steps) as $step) {
            if ((string)($step['status'] ?? '') === SetupStateService::STATUS_RUNNING) {
                return (string)($step['step_key'] ?? '');
            }
        }

        return '';
    }

    /**
     * @return array<string,mixed>
     */
    private function createAdminUser(string $email, string $password): array
    {
        $existing = DB::fetchOne('SELECT id, twofa_secret FROM users WHERE email=? LIMIT 1', [$email]);
        $existingAdmin = DB::fetchOne("SELECT id, email FROM users WHERE role='Admin' ORDER BY id ASC LIMIT 1");
        $secret = TOTP::randomSecret();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Public setup is a first-admin bootstrap flow. Safe retries may update the same
        // pending admin, but we do not create a second admin with a different email here.
        if (is_array($existingAdmin) && !is_array($existing) && strtolower(trim((string)($existingAdmin['email'] ?? ''))) !== $email) {
            throw new \RuntimeException('An admin account already exists for this installation. Retry with the existing admin email or complete 2FA for that account.');
        }

        if (is_array($existing)) {
            DB::query('UPDATE users SET password_hash=?, role=?, twofa_enabled=0, twofa_secret=? WHERE id=?', [
                $passwordHash,
                'Platform Operations',
                $secret,
                (int)$existing['id'],
            ]);
            $userId = (int)$existing['id'];
        } else {
            DB::query(
                'INSERT INTO users (email, password_hash, role, twofa_enabled, twofa_secret) VALUES (?,?,?,?,?)',
                [$email, $passwordHash, 'Platform Operations', 0, $secret]
            );
            $row = DB::fetchOne('SELECT id FROM users WHERE email=? LIMIT 1', [$email]);
            $userId = (int)($row['id'] ?? 0);
        }

        if ($userId > 0) {
            $this->provisionBootstrapAdminGovernance($userId, $email);

            // Bootstrap guarantee: first user should always be platform admin.
            $firstUserId = $this->bootstrapFirstUserId();
            if ($firstUserId > 0 && $firstUserId !== $userId) {
                $this->provisionBootstrapAdminGovernance($firstUserId, $email);
            }
        }

        $_SESSION['setup_email'] = $email;
        $_SESSION['setup_secret'] = $secret;

        return [
            'email' => $email,
            'user_id' => $userId,
            'twofa_pending' => true,
            'status' => is_array($existing) ? 'updated' : 'created',
        ];
    }

    private function provisionBootstrapAdminGovernance(int $userId, string $actorEmail): void
    {
        $setParts = ['role = ?'];
        $params = ['Platform Operations'];

        // Ensure authority_role column exists (may not exist on fresh installs before
        // UserDashboardAssignmentService has run its lazy schema upgrade).
        if (!$this->usersColumnExists('authority_role')) {
            try {
                DB::query("ALTER TABLE users ADD COLUMN authority_role VARCHAR(40) NULL AFTER role");
            } catch (\Throwable $e) {
                // Ignore duplicate column error (errno 1060)
                if (strpos($e->getMessage(), '1060') === false) {
                    throw $e;
                }
            }
        }
        if ($this->usersColumnExists('authority_role')) {
            $setParts[] = 'authority_role = ?';
            $params[] = 'platform_admin';
        }
        if ($this->usersColumnExists('role_tier')) {
            $setParts[] = 'role_tier = ?';
            $params[] = 'admin';
        }

        $params[] = $userId;
        DB::query('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1', $params);

        $assignmentTable = DB::fetchOne("SHOW TABLES LIKE 'user_dashboard_assignments'");
        if (is_array($assignmentTable) && $assignmentTable !== []) {
            DB::query(
                "INSERT INTO user_dashboard_assignments
                    (user_id, dashboard_type, default_app, default_landing_page, assigned_apps, access_profiles, permissions, dashboard_mode, default_app_mode, landing_mode, access_profiles_mode, module_visibility_mode, account_class, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'auto', 'auto', 'auto', 'auto', 'auto', ?, ?)
                 ON DUPLICATE KEY UPDATE
                    dashboard_type=VALUES(dashboard_type),
                    default_app=VALUES(default_app),
                    default_landing_page=VALUES(default_landing_page),
                    assigned_apps=VALUES(assigned_apps),
                    access_profiles=VALUES(access_profiles),
                    permissions=VALUES(permissions),
                    dashboard_mode=VALUES(dashboard_mode),
                    default_app_mode=VALUES(default_app_mode),
                    landing_mode=VALUES(landing_mode),
                    access_profiles_mode=VALUES(access_profiles_mode),
                    module_visibility_mode=VALUES(module_visibility_mode),
                    account_class=VALUES(account_class),
                    updated_by=VALUES(updated_by),
                    updated_at=NOW()",
                [
                    $userId,
                    'platform_admin',
                    'platform',
                    '/',
                    'platform,manufacturing,sbaio',
                    'platform_administration',
                    'admin.tools.access,acl.manage',
                    'platform_operations',
                    $actorEmail,
                ]
            );
        }
    }

    private function usersColumnExists(string $column): bool
    {
        $column = trim($column);
        if ($column === '') {
            return false;
        }

        $escaped = DB::conn()->real_escape_string($column);
        $row = DB::fetchOne("SHOW COLUMNS FROM users LIKE '{$escaped}'");
        return is_array($row) && $row !== [];
    }

    private function bootstrapFirstUserId(): int
    {
        $row = DB::fetchOne('SELECT id FROM users ORDER BY id ASC LIMIT 1');
        return (int)($row['id'] ?? 0);
    }

    /**
     * @param array<string,string> $settings
     * @return array<int,string>
     */
    private function writeInitialSettings(array $settings): array
    {
        foreach ($settings as $key => $value) {
            DB::query(
                'INSERT INTO core_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
                [$key, $value]
            );
        }

        return array_keys($settings);
    }

    private function pluginManager(): PluginManager
    {
        static $manager = null;
        if ($manager instanceof PluginManager) {
            return $manager;
        }

        $container = new Container();
        $router = new Router();
        $view = new View(APP_ROOT . '/public/views');
        $bus = new EventBus();
        $manager = new PluginManager(APP_ROOT . '/plugins', $container, $router, $view, $bus);

        return $manager;
    }

    /**
     * Cross-platform writability check for directories.
     *
     * Falls back to a temp-file probe when is_writable() is unreliable
     * (common on Windows with ACL-based permissions).
     */
    private static function isDirWritable(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        if (is_writable($dir)) {
            return true;
        }

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $probe = rtrim(str_replace('\\', '/', $dir), '/') . '/.wtmp_' . bin2hex(random_bytes(4));
            if (@touch($probe)) {
                @unlink($probe);
                return true;
            }
        }

        return false;
    }
}
