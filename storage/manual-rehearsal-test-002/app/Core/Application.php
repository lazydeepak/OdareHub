<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AppMigrationService;
use App\Services\AppLocalDiscoveryService;
use App\Services\AppRuntimeRegistryService;
use App\Services\AppRuntimeLoader;
use App\Core\RouteRuntimeAuthority;

final class Application
{
    public Container $c;
    public Router $router;
    public View $view;
    public EventBus $bus;
    public PluginManager $plugins;
    public AppRuntimeLoader $appRuntime;

    public function __construct()
    {
        $this->c = new Container();
        $this->router = new Router();
        $this->view = new View(dirname(__DIR__, 2) . '/public/views');
        $this->bus = new EventBus();

        // container bindings
        $this->c->set('router', fn() => $this->router);
        $this->c->set('view', fn() => $this->view);
        $this->c->set('bus', fn() => $this->bus);

        $this->plugins = new PluginManager(dirname(__DIR__, 2) . '/plugins', $this->c, $this->router, $this->view, $this->bus);
        $this->c->set('plugins', fn() => $this->plugins);

        $this->appRuntime = new AppRuntimeLoader();
        $this->c->set('app_runtime', fn() => $this->appRuntime);
    }

    public function boot(): void
    {
        // Ensure base table exists even before Base installed (for first run)
        DB::query("CREATE TABLE IF NOT EXISTS installed_plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            version VARCHAR(20) NOT NULL,
            type ENUM('engine','business') DEFAULT 'business',
            status ENUM('active','inactive') DEFAULT 'active',
            requires_json JSON NULL,
            installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Ensure auth table exists so setup/login does not fail on fresh DBs.
        DB::query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'User',
            twofa_enabled TINYINT(1) NOT NULL DEFAULT 0,
            twofa_secret VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $this->autoPromoteSingleAccountToPlatformAdmin();

        // App platform core tables are permanent and available before app lifecycle operations.
        (new AppMigrationService())->ensureCoreTables();
        (new AppLocalDiscoveryService())->syncLocalApps();

        // Auto-install Base if not installed (Odoo-like 'base')
        $manifests = $this->plugins->scan();
        if (isset($manifests['Base']) && !$this->plugins->isInstalled('Base')) {
            $this->plugins->install('Base');
        }

        if (isset($manifests['ACL']) && !$this->plugins->isInstalled('ACL')) {
            $this->plugins->install('ACL');
        }

        $this->plugins->loadActivePlugins();

        // Register Architecture Health Board route after plugins, before app manager routes.
        $archHealthRoute = dirname(__DIR__, 2) . '/app/Routes/admin_architecture_health.php';
        if (is_file($archHealthRoute)) {
            $router = $this->router;
            $view = $this->view;
            $c = $this->c;
            require $archHealthRoute;
        }

        // Register app manager routes after plugin routes to avoid unintended route overrides.
        $appManagerRoutes = dirname(__DIR__, 2) . '/app/AppManager/routes.php';
        if (is_file($appManagerRoutes)) {
            $router = $this->router;
            $view = $this->view;
            $c = $this->c;
            require $appManagerRoutes;
        }

        // Load enabled installable apps from /apps registry.
        $this->appRuntime->loadEnabledApps($this->c, $this->router, $this->view);

        // Materialize menu/permission runtime registries and execute enabled app boot hooks.
        $runtimeRegistry = new AppRuntimeRegistryService();
        $runtimeRegistry->syncEnabledRuntimeRegistries();
        $runtimeRegistry->emitBootHooks();

        // Final runtime route map is the single source of truth after boot.
        RouteRuntimeAuthority::seed($this->router->listRoutes());
    }

    private function autoPromoteSingleAccountToPlatformAdmin(): void
    {
        try {
            $countRow = DB::fetchOne('SELECT COUNT(*) AS c FROM users');
            $userCount = (int)($countRow['c'] ?? 0);
            if ($userCount !== 1) {
                return;
            }

            $user = DB::fetchOne('SELECT id, role FROM users ORDER BY id ASC LIMIT 1');
            if (!is_array($user) || (int)($user['id'] ?? 0) <= 0) {
                return;
            }

            $roleRaw = strtolower(trim((string)($user['role'] ?? '')));
            $roleFlat = preg_replace('/[^a-z0-9]+/', '', $roleRaw) ?? '';

            $isPlatformAdmin = $roleFlat === 'platformadmin';
            if ($isPlatformAdmin) {
                return;
            }

            $hasRoleTier = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'role_tier'") !== null;
            $hasAccountType = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'authority_role'") !== null;
            if ($hasRoleTier && $hasAccountType) {
                DB::query('UPDATE users SET role=?, role_tier=?, authority_role=? WHERE id=? LIMIT 1', ['PLATFORM ADMIN', 'admin', 'platform_admin', (int)$user['id']]);
                return;
            }

            if ($hasRoleTier) {
                DB::query('UPDATE users SET role=?, role_tier=? WHERE id=? LIMIT 1', ['PLATFORM ADMIN', 'admin', (int)$user['id']]);
                return;
            }

            DB::query('UPDATE users SET role=? WHERE id=? LIMIT 1', ['PLATFORM ADMIN', (int)$user['id']]);
        } catch (\Throwable $e) {
            // Keep boot resilient on partially initialized databases.
        }
    }

    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $this->router->dispatch($method, $path);
    }
}
