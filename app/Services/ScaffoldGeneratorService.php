<?php
declare(strict_types=1);

namespace App\Services;

final class ScaffoldGeneratorService
{
    private const DEFAULT_VERSION = '1.0.0';
    private const MIN_CORE_VERSION = '3.0.0';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function suiteOptions(): array
    {
        $appsRoot = APP_ROOT . '/apps';
        $options = [];
        foreach (glob($appsRoot . '/*/manifest.json') ?: [] as $manifestPath) {
            try {
                $manifest = AppManifestService::loadFromFile($manifestPath);
            } catch (\Throwable) {
                continue;
            }

            $options[] = [
                'key' => (string)($manifest['id'] ?? ''),
                'label' => (string)($manifest['name'] ?? ''),
                'directory_name' => (string)($manifest['directory_name'] ?? basename(dirname($manifestPath))),
                'path' => dirname($manifestPath),
            ];
        }

        usort($options, static fn(array $a, array $b): int => strcmp((string)$a['label'], (string)$b['label']));
        return $options;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function scaffoldTypes(): array
    {
        return [
            [
                'key' => 'suite',
                'label' => 'New Suite',
                'summary' => 'Creates a full app/bundle shell with manifest, routes, navigation, bootstrap, dashboard controller, host-surface placeholder, and starter views.',
            ],
            [
                'key' => 'module',
                'label' => 'New Module',
                'summary' => 'Adds a child module into an existing suite with plugin metadata, routes, navigation, bootstrap, install/update scripts, controller, service, and starter views.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function generateSuite(array $input): array
    {
        $suiteName = $this->requireLabel((string)($input['suite_name'] ?? ''), 'Suite name is required.');
        $suiteKey = $this->normalizeSlug((string)($input['suite_key'] ?? $suiteName));
        $directoryName = $this->normalizeDirectoryName((string)($input['directory_name'] ?? $suiteName));
        $routeSlug = $this->normalizeSlug((string)($input['route_slug'] ?? $suiteKey));
        $permissionPrefix = $this->normalizeSlug((string)($input['permission_prefix'] ?? $suiteKey));

        if ($suiteKey === '') {
            throw new \RuntimeException('Suite key is required.');
        }

        $suiteDir = APP_ROOT . '/apps/' . $directoryName;
        if (is_dir($suiteDir)) {
            throw new \RuntimeException('Suite directory already exists: ' . $directoryName);
        }

        $controllerClass = $directoryName . 'DashboardController';
        $dashboardRoute = '/apps/' . $routeSlug;
        $suiteNamespace = $directoryName;
        $dashboardViewAlias = strtolower($suiteKey) . '::dashboard.php';
        $navFeatureKey = $routeSlug . '_dashboard';
        $date = date('Y-m-d');

        $manifest = [
            'id' => $suiteKey,
            'app_key' => $suiteKey,
            'package_type' => 'bundle',
            'directory_name' => $directoryName,
            'name' => $suiteName,
            'version' => self::DEFAULT_VERSION,
            'type' => 'business',
            'min_core_version' => self::MIN_CORE_VERSION,
            'dependencies' => [],
            'entry' => 'routes.php',
            'native_modules' => [],
            'legacy_bridge_plugins' => [],
            'migrations_path' => 'migrations',
            'permissions' => [
                $permissionPrefix . '.view',
                $permissionPrefix . '.manage',
            ],
            'modules' => [],
            'hooks' => [
                [
                    'type' => 'boot',
                    'key' => $suiteKey . '.bootstrap',
                    'handler_file' => 'bootstrap.php',
                ],
                [
                    'type' => 'host_surface',
                    'key' => $suiteKey . '.me.header_actions',
                    'surface' => 'me',
                    'region' => 'header_actions',
                    'provider' => 'Apps\\' . $suiteNamespace . '\\Services\\HostSurfaceContributionService::contribute',
                    'provider_file' => 'Services/HostSurfaceContributionService.php',
                    'order' => 50,
                ],
                [
                    'type' => 'host_surface',
                    'key' => $suiteKey . '.me.summary_cards',
                    'surface' => 'me',
                    'region' => 'summary_cards',
                    'provider' => 'Apps\\' . $suiteNamespace . '\\Services\\HostSurfaceContributionService::contribute',
                    'provider_file' => 'Services/HostSurfaceContributionService.php',
                    'order' => 60,
                ],
                [
                    'type' => 'host_surface',
                    'key' => $suiteKey . '.me.quick_links',
                    'surface' => 'me',
                    'region' => 'quick_links',
                    'provider' => 'Apps\\' . $suiteNamespace . '\\Services\\HostSurfaceContributionService::contribute',
                    'provider_file' => 'Services/HostSurfaceContributionService.php',
                    'order' => 70,
                ],
                [
                    'type' => 'host_surface',
                    'key' => $suiteKey . '.me.monitoring_sections',
                    'surface' => 'me',
                    'region' => 'monitoring_sections',
                    'provider' => 'Apps\\' . $suiteNamespace . '\\Services\\HostSurfaceContributionService::contribute',
                    'provider_file' => 'Services/HostSurfaceContributionService.php',
                    'order' => 80,
                ],
            ],
            'routes' => [
                ['path' => $dashboardRoute],
            ],
            'menus' => [
                [
                    'key' => 'app.' . $suiteKey . '.root',
                    'label_key' => 'nav.' . $suiteKey . '_dashboard',
                    'label' => $suiteName . ' Dashboard',
                    'url' => $dashboardRoute,
                    'parent' => 'apps.root',
                    'order' => 50,
                    'perm' => $permissionPrefix . '.view',
                ],
            ],
            'widgets' => [
                [
                    'key' => $suiteKey . '.widget.dashboard',
                    'title_key' => 'nav.' . $suiteKey . '_dashboard',
                    'title' => $suiteName . ' Dashboard',
                    'url' => $dashboardRoute,
                    'order' => 20,
                    'domain' => $suiteKey,
                    'zone' => $suiteKey,
                ],
            ],
            'search_entries' => [
                [
                    'key' => $suiteKey . '_dashboard',
                    'label_key' => 'nav.' . $suiteKey . '_dashboard',
                    'url' => $dashboardRoute,
                ],
            ],
            'notifications' => [],
            'can_disable' => true,
            'can_uninstall' => true,
            'can_export' => true,
        ];

        $files = [];
        $directories = [
            $suiteDir . '/Controllers',
            $suiteDir . '/Services',
            $suiteDir . '/Views',
            $suiteDir . '/Views/partials',
            $suiteDir . '/migrations',
            $suiteDir . '/modules',
        ];
        foreach ($directories as $dir) {
            $this->ensureDirectory($dir);
        }

        $this->writeFile($suiteDir . '/manifest.json', $this->json($manifest), $files);
        AppManifestService::loadFromFile($suiteDir . '/manifest.json');
        $this->writeFile($suiteDir . '/bootstrap.php', $this->suiteBootstrapTemplate($suiteName), $files);
        $this->writeFile($suiteDir . '/navigation.php', $this->suiteNavigationTemplate($suiteKey, $suiteName, $dashboardRoute, $navFeatureKey), $files);
        $this->writeFile($suiteDir . '/versioning.php', $this->suiteVersioningTemplate($suiteName, $date), $files);
        $this->writeFile($suiteDir . '/routes.php', $this->suiteRoutesTemplate($suiteKey, $directoryName, $controllerClass, $dashboardRoute), $files);
        $this->writeFile($suiteDir . '/Controllers/' . $controllerClass . '.php', $this->suiteControllerTemplate($directoryName, $controllerClass, $suiteName, $dashboardViewAlias), $files);
        $this->writeFile($suiteDir . '/Services/HostSurfaceContributionService.php', $this->suiteHostServiceTemplate($directoryName), $files);
        $this->writeFile($suiteDir . '/Views/dashboard.php', $this->suiteDashboardViewTemplate($suiteName), $files);
        $this->writeFile($suiteDir . '/Views/partials/.gitkeep', "", $files);
        $this->writeFile($suiteDir . '/migrations/.gitkeep', "", $files);
        $this->writeFile($suiteDir . '/modules/.gitkeep', "", $files);

        (new AppLocalDiscoveryService())->syncLocalApps();

        return [
            'scaffold_type' => 'suite',
            'status' => 'created',
            'suite_key' => $suiteKey,
            'suite_name' => $suiteName,
            'directory_name' => $directoryName,
            'route' => $dashboardRoute,
            'files_changed' => $files,
            'output_structure' => [
                'manifest' => $suiteDir . '/manifest.json',
                'routes' => $suiteDir . '/routes.php',
                'navigation' => $suiteDir . '/navigation.php',
                'bootstrap' => $suiteDir . '/bootstrap.php',
                'controllers' => $suiteDir . '/Controllers',
                'services' => $suiteDir . '/Services',
                'views' => $suiteDir . '/Views',
                'migrations' => $suiteDir . '/migrations',
                'modules' => $suiteDir . '/modules',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function generateModule(array $input): array
    {
        $suiteKey = $this->normalizeSlug((string)($input['suite_key'] ?? ''));
        if ($suiteKey === '') {
            throw new \RuntimeException('Choose a suite before creating a module.');
        }

        $suiteInfo = $this->suiteByKey($suiteKey);
        if (!is_array($suiteInfo)) {
            throw new \RuntimeException('Suite not found: ' . $suiteKey);
        }

        $moduleName = $this->normalizeDirectoryName((string)($input['module_name'] ?? ''));
        $moduleDisplayName = $this->requireLabel((string)($input['module_display_name'] ?? $moduleName), 'Module name is required.');
        $moduleSlug = $this->normalizeSlug((string)($input['module_slug'] ?? $moduleDisplayName));
        if ($moduleName === '' || $moduleSlug === '') {
            throw new \RuntimeException('Module name and slug are required.');
        }

        $suiteDir = (string)$suiteInfo['path'];
        $moduleDir = $suiteDir . '/modules/' . $moduleName;
        if (is_dir($moduleDir)) {
            throw new \RuntimeException('Module directory already exists: ' . $moduleName);
        }

        $suiteDirectoryName = (string)$suiteInfo['directory_name'];
        $suiteLabel = (string)$suiteInfo['label'];
        $route = '/apps/' . $suiteKey . '/' . $moduleSlug;
        $tableName = $suiteKey . '_' . str_replace('-', '_', $moduleSlug);
        $displayOrder = $this->nextModuleDisplayOrder($suiteDir);
        $controllerClass = $moduleName . 'Controller';
        $serviceClass = $moduleName . 'Service';
        $viewAlias = $moduleName . '::index.php';

        $plugin = [
            'name' => $moduleName,
            'display_name' => $moduleDisplayName,
            'suite' => $suiteKey,
            'group' => 'operations',
            'category' => 'business',
            'display_order' => $displayOrder,
            'type' => 'business',
            'version' => self::DEFAULT_VERSION,
            'description' => $moduleDisplayName . ' module for the ' . $suiteLabel . ' suite.',
            'requires' => [
                'Base>=' . self::MIN_CORE_VERSION,
            ],
            'optional' => [
                'ACL>=1.0.0',
            ],
            'entry' => 'routes.php',
            'package_type' => 'module',
            'owner_app' => $suiteKey,
            'module_key' => $moduleName,
            'required_tables' => [$tableName],
        ];

        $manifestPath = $suiteDir . '/manifest.json';
        $manifest = AppManifestService::loadFromFile($manifestPath);
        $manifest['native_modules'] = $this->pushUnique((array)($manifest['native_modules'] ?? []), $moduleSlug);
        $manifest['legacy_bridge_plugins'] = $this->pushUnique((array)($manifest['legacy_bridge_plugins'] ?? []), $moduleName);
        $manifest['modules'] = $this->pushModuleEntry((array)($manifest['modules'] ?? []), $moduleSlug, $moduleName);
        $manifest['routes'] = $this->pushPathEntry((array)($manifest['routes'] ?? []), $route);
        $manifest['menus'] = $this->pushMenuEntry((array)($manifest['menus'] ?? []), $suiteKey, $moduleSlug, $moduleDisplayName, $route, $displayOrder);
        $manifest['search_entries'] = $this->pushSearchEntry((array)($manifest['search_entries'] ?? []), $suiteKey, $moduleSlug, $route);

        $files = [];
        $directories = [
            $moduleDir,
            $moduleDir . '/Controllers',
            $moduleDir . '/Services',
            $moduleDir . '/Views',
        ];
        foreach ($directories as $dir) {
            $this->ensureDirectory($dir);
        }

        $this->writeFile($moduleDir . '/plugin.json', $this->json($plugin), $files);
        $this->writeFile($moduleDir . '/bootstrap.php', $this->moduleBootstrapTemplate(), $files);
        $this->writeFile($moduleDir . '/navigation.php', $this->moduleNavigationTemplate($suiteKey, $moduleName, $moduleDisplayName, $route, $displayOrder), $files);
        $this->writeFile($moduleDir . '/routes.php', $this->moduleRoutesTemplate($suiteKey, $moduleName, $controllerClass, $route), $files);
        $this->writeFile($moduleDir . '/Controllers/' . $controllerClass . '.php', $this->moduleControllerTemplate($moduleName, $controllerClass, $serviceClass, $moduleDisplayName, $viewAlias, $suiteKey, $moduleSlug), $files);
        $this->writeFile($moduleDir . '/Services/' . $serviceClass . '.php', $this->moduleServiceTemplate($moduleName, $serviceClass, $tableName), $files);
        $this->writeFile($moduleDir . '/Views/index.php', $this->moduleViewTemplate($moduleDisplayName, $suiteLabel, $suiteKey, $moduleSlug), $files);
        $this->writeFile($moduleDir . '/install.php', $this->moduleInstallTemplate($tableName), $files);
        $this->writeFile($moduleDir . '/update.php', $this->moduleUpdateTemplate($tableName), $files);

        $this->overwriteFile($manifestPath, $this->json($manifest), $files);
        AppManifestService::loadFromFile($manifestPath);

        (new AppLocalDiscoveryService())->syncLocalApps();

        return [
            'scaffold_type' => 'module',
            'status' => 'created',
            'suite_key' => $suiteKey,
            'module_name' => $moduleName,
            'module_display_name' => $moduleDisplayName,
            'route' => $route,
            'files_changed' => $files,
            'output_structure' => [
                'plugin' => $moduleDir . '/plugin.json',
                'routes' => $moduleDir . '/routes.php',
                'navigation' => $moduleDir . '/navigation.php',
                'bootstrap' => $moduleDir . '/bootstrap.php',
                'install' => $moduleDir . '/install.php',
                'update' => $moduleDir . '/update.php',
                'controllers' => $moduleDir . '/Controllers',
                'services' => $moduleDir . '/Services',
                'views' => $moduleDir . '/Views',
                'suite_manifest' => $manifestPath,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function suiteByKey(string $suiteKey): ?array
    {
        foreach ($this->suiteOptions() as $suite) {
            if ((string)($suite['key'] ?? '') === $suiteKey) {
                return $suite;
            }
        }

        return null;
    }

    private function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create scaffold directory: ' . $dir);
        }
    }

    /**
     * @param array<int,string> $files
     */
    private function writeFile(string $path, string $contents, array &$files): void
    {
        if (is_file($path)) {
            throw new \RuntimeException('Scaffold target already exists: ' . $path);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->ensureDirectory($dir);
        }

        if (@file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write scaffold file: ' . $path);
        }

        $files[] = $path;
    }

    /**
     * @param array<int,string> $files
     */
    private function overwriteFile(string $path, string $contents, array &$files): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            $this->ensureDirectory($dir);
        }

        if (@file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to update scaffold file: ' . $path);
        }

        $files[] = $path;
    }

    private function requireLabel(string $value, string $error): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException($error);
        }

        return $value;
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function normalizeDirectoryName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', trim($value)) ?? '';
        $value = str_replace(' ', '', ucwords(strtolower($value)));
        if ($value === '' || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $value)) {
            throw new \RuntimeException('Use a name that can become a valid suite/module directory.');
        }

        return $value;
    }

    private function json(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Unable to encode scaffold metadata.');
        }

        return $json . PHP_EOL;
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,mixed>
     */
    private function pushUnique(array $values, string $item): array
    {
        $values = array_values(array_map('strval', $values));
        if (!in_array($item, $values, true)) {
            $values[] = $item;
        }

        return $values;
    }

    /**
     * @param array<int,mixed> $modules
     * @return array<int,array<string,string>>
     */
    private function pushModuleEntry(array $modules, string $moduleKey, string $moduleName): array
    {
        foreach ($modules as $module) {
            if (is_array($module) && (string)($module['name'] ?? '') === $moduleName) {
                return $modules;
            }
        }

        $modules[] = ['key' => $moduleKey, 'name' => $moduleName];
        return array_values($modules);
    }

    /**
     * @param array<int,mixed> $entries
     * @return array<int,array<string,string>>
     */
    private function pushPathEntry(array $entries, string $path): array
    {
        foreach ($entries as $entry) {
            if (is_array($entry) && (string)($entry['path'] ?? '') === $path) {
                return $entries;
            }
        }

        $entries[] = ['path' => $path];
        return array_values($entries);
    }

    /**
     * @param array<int,mixed> $entries
     * @return array<int,mixed>
     */
    private function pushMenuEntry(array $entries, string $suiteKey, string $moduleSlug, string $moduleLabel, string $route, int $displayOrder): array
    {
        $key = 'app.' . $suiteKey . '.' . $moduleSlug;
        foreach ($entries as $entry) {
            if (is_array($entry) && (string)($entry['key'] ?? '') === $key) {
                return $entries;
            }
        }

        $entries[] = [
            'key' => $key,
            'label_key' => 'nav.' . $suiteKey . '_' . $moduleSlug,
            'label' => $moduleLabel,
            'url' => $route,
            'parent' => 'app.' . $suiteKey . '.root',
            'order' => $displayOrder,
            'perm' => $suiteKey . '.view',
        ];

        return array_values($entries);
    }

    /**
     * @param array<int,mixed> $entries
     * @return array<int,mixed>
     */
    private function pushSearchEntry(array $entries, string $suiteKey, string $moduleSlug, string $route): array
    {
        $key = $suiteKey . '_' . $moduleSlug;
        foreach ($entries as $entry) {
            if (is_array($entry) && (string)($entry['key'] ?? '') === $key) {
                return $entries;
            }
        }

        $entries[] = [
            'key' => $key,
            'label_key' => 'nav.' . $suiteKey . '_' . $moduleSlug,
            'url' => $route,
        ];

        return array_values($entries);
    }

    private function nextModuleDisplayOrder(string $suiteDir): int
    {
        $orders = [];
        foreach (glob($suiteDir . '/modules/*/plugin.json') ?: [] as $pluginPath) {
            $json = json_decode((string)file_get_contents($pluginPath), true);
            if (is_array($json)) {
                $orders[] = (int)($json['display_order'] ?? 0);
            }
        }

        $max = $orders === [] ? 40 : max($orders);
        return $max + 5;
    }

    private function suiteBootstrapTemplate(string $suiteName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// {$suiteName} boot hook placeholder.
PHP;
    }

    private function suiteNavigationTemplate(string $suiteKey, string $suiteName, string $dashboardRoute, string $featureKey): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => '{$suiteKey}',
    'items' => [
        [
            'source_key' => 'apps.{$suiteKey}.dashboard',
            'group' => 'Apps',
            'section' => '{$suiteName}',
            'module' => '{$suiteKey}',
            'owner' => '{$suiteKey}',
            'key' => '{$suiteKey}_home',
            'feature_key' => '{$featureKey}',
            'label_key' => 'nav.{$suiteKey}_dashboard',
            'label' => '{$suiteName} Dashboard',
            'url' => '{$dashboardRoute}',
            'visible_if' => 'logged_in',
            'nav_visible' => true,
            'order' => 10,
        ],
    ],
];
PHP;
    }

    private function suiteVersioningTemplate(string $suiteName, string $date): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

return [
    'suite' => [
        'releases' => [
            [
                'version' => '1.0.0',
                'release_date' => '{$date}',
                'scope' => 'suite',
                'summary' => '{$suiteName} scaffold created with standard manifest, routes, navigation, bootstrap, and dashboard structure.',
                'breaking_changes' => [],
                'migration_notes' => [
                    'Add suite-specific schema and verify after the first real install.',
                ],
                'setup_upgrade_notes' => [
                    'Replace scaffold placeholders with suite-specific controllers, defaults, and host contributions before rollout.',
                ],
            ],
        ],
    ],
    'modules' => [],
];
PHP;
    }

    private function suiteRoutesTemplate(string $suiteKey, string $directoryName, string $controllerClass, string $dashboardRoute): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Services\AppManifestService;
use Apps\\{$directoryName}\\Controllers\\{$controllerClass};

require_once __DIR__ . '/Controllers/{$controllerClass}.php';
require_once __DIR__ . '/Services/HostSurfaceContributionService.php';

if (is_file(__DIR__ . '/bootstrap.php')) {
    require_once __DIR__ . '/bootstrap.php';
}

\$router->get('{$dashboardRoute}', function () use (\$view) {
    Auth::requireAppAccess('{$suiteKey}');
    Auth::bootSession();
    {$controllerClass}::index(\$view);
    return null;
});

\$manifest = AppManifestService::loadFromFile(__DIR__ . '/manifest.json');
foreach ((array) (\$manifest['legacy_bridge_plugins'] ?? []) as \$pluginName) {
    \$moduleDir = __DIR__ . '/modules/' . \$pluginName;
    if (!is_dir(\$moduleDir)) {
        continue;
    }

    if (is_file(\$moduleDir . '/bootstrap.php')) {
        require_once \$moduleDir . '/bootstrap.php';
    }
    if (is_file(\$moduleDir . '/routes.php')) {
        require_once \$moduleDir . '/routes.php';
    }
}
PHP;
    }

    private function suiteControllerTemplate(string $directoryName, string $controllerClass, string $suiteName, string $viewAlias): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace Apps\\{$directoryName}\\Controllers;

final class {$controllerClass}
{
    public static function index(\$view): void
    {
        \$view->render('{$viewAlias}', [
            'pageTitle' => '{$suiteName}',
            'suiteTitle' => '{$suiteName}',
            'suiteDescription' => '{$suiteName} is scaffolded and ready for suite-specific modules, setup, and dashboard work.',
            'cards' => [
                [
                    'label' => 'Install ready',
                    'value' => 'Yes',
                    'meta' => 'Manifest, routes, navigation, and bootstrap are in place.',
                ],
                [
                    'label' => 'Child modules',
                    'value' => '0',
                    'meta' => 'Add modules from the scaffold generator as the suite grows.',
                ],
            ],
        ]);
    }
}
PHP;
    }

    private function suiteHostServiceTemplate(string $directoryName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace Apps\\{$directoryName}\\Services;

final class HostSurfaceContributionService
{
    /**
     * @param array<string,mixed> \$request
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public static function contribute(array \$request): array
    {
        return [];
    }
}
PHP;
    }

    private function suiteDashboardViewTemplate(string $suiteName): string
    {
        return <<<PHP
<?php require APP_ROOT . '/public/views/layouts/header.php'; ?>
<?php \$cards = is_array(\$cards ?? null) ? \$cards : []; ?>

<div class="card">
  <h2 style="margin:0 0 8px"><?= e((string)(\$suiteTitle ?? '{$suiteName}')) ?></h2>
  <div class="muted"><?= e((string)(\$suiteDescription ?? '')) ?></div>
</div>

<div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <?php foreach (\$cards as \$card): ?>
    <div class="card" style="margin:0">
      <strong><?= e((string)(\$card['label'] ?? '')) ?></strong>
      <div style="font-size:1.8rem;margin:8px 0 4px"><?= e((string)(\$card['value'] ?? '0')) ?></div>
      <div class="muted"><?= e((string)(\$card['meta'] ?? '')) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3 style="margin:0 0 8px">Next Steps</h3>
  <div class="muted">Use the scaffold generator to add child modules, then replace these starter cards with suite-specific data and actions.</div>
</div>

<?php require APP_ROOT . '/public/views/layouts/footer.php'; ?>
PHP;
    }

    private function moduleBootstrapTemplate(): string
    {
        return <<<PHP
<?php
declare(strict_types=1);
PHP;
    }

    private function moduleNavigationTemplate(string $suiteKey, string $moduleName, string $moduleLabel, string $route, int $displayOrder): string
    {
        $featureKey = strtolower($moduleName);
        return <<<PHP
<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => '{$suiteKey}',
    'items' => [
        [
            'source_key' => 'apps.{$suiteKey}.modules',
            'group' => 'Apps',
            'section' => ucfirst('{$suiteKey}'),
            'module' => '{$suiteKey}',
            'owner' => '{$moduleName}',
            'key' => '{$suiteKey}_{$featureKey}',
            'feature_key' => '{$featureKey}',
            'label_key' => 'nav.{$suiteKey}_{$featureKey}',
            'label' => '{$moduleLabel}',
            'url' => '{$route}',
            'visible_if' => 'logged_in',
            'nav_visible' => true,
            'order' => {$displayOrder},
        ],
    ],
];
PHP;
    }

    private function moduleRoutesTemplate(string $suiteKey, string $moduleName, string $controllerClass, string $route): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

use App\Core\Auth;
use Plugins\\{$moduleName}\\Controllers\\{$controllerClass};

require_once __DIR__ . '/Controllers/{$controllerClass}.php';

\$router->get('{$route}', function () use (\$view) {
    Auth::requireAppAccess('{$suiteKey}');
    Auth::bootSession();
    {$controllerClass}::index(\$view);
    return null;
});

\$router->post('{$route}/create', function () {
    Auth::requireAppAccess('{$suiteKey}');
    Auth::bootSession();
    {$controllerClass}::create();
    return null;
});
PHP;
    }

    private function moduleControllerTemplate(string $moduleName, string $controllerClass, string $serviceClass, string $moduleLabel, string $viewAlias, string $suiteKey, string $moduleSlug): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace Plugins\\{$moduleName}\\Controllers;

use App\Core\Auth;
use Plugins\\{$moduleName}\\Services\\{$serviceClass};

require_once __DIR__ . '/../Services/{$serviceClass}.php';

final class {$controllerClass}
{
    public static function index(\$view): void
    {
        \$view->render('{$viewAlias}', [
            'pageTitle' => '{$moduleLabel}',
            'moduleTitle' => '{$moduleLabel}',
            'moduleDescription' => '{$moduleLabel} was scaffolded with a standard controller, service, routes, and view structure.',
            'rows' => {$serviceClass}::all(),
            'message' => (string) (\$_GET['ok'] ?? ''),
            'error' => (string) (\$_GET['err'] ?? ''),
            'createAction' => '/apps/{$suiteKey}/{$moduleSlug}/create',
            'dashboardUrl' => '/apps/{$suiteKey}',
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string) (\$_POST['csrf'] ?? ''));

        \$recordName = trim((string) (\$_POST['record_name'] ?? ''));
        if (\$recordName === '') {
            self::redirect('err', 'Record name is required.');
        }

        {$serviceClass}::create(\$recordName, trim((string) (\$_POST['notes'] ?? '')));
        self::redirect('ok', '{$moduleLabel} record created.');
    }

    private static function redirect(string \$key, string \$message): void
    {
        header('Location: /apps/{$suiteKey}/{$moduleSlug}?' . \$key . '=' . rawurlencode(\$message));
        exit;
    }
}
PHP;
    }

    private function moduleServiceTemplate(string $moduleName, string $serviceClass, string $tableName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace Plugins\\{$moduleName}\\Services;

use App\Core\DB;

final class {$serviceClass}
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        return DB::fetchAll("SELECT * FROM {$tableName} ORDER BY id DESC LIMIT 50");
    }

    public static function create(string \$recordName, string \$notes = ''): void
    {
        DB::query(
            "INSERT INTO {$tableName} (record_name, notes, record_status, created_at, updated_at) VALUES (?,?, 'active', NOW(), NOW())",
            [\$recordName, \$notes !== '' ? \$notes : null]
        );
    }
}
PHP;
    }

    private function moduleViewTemplate(string $moduleLabel, string $suiteLabel, string $suiteKey, string $moduleSlug): string
    {
        return <<<PHP
<?php require APP_ROOT . '/public/views/layouts/header.php'; ?>
<?php
\$rows = is_array(\$rows ?? null) ? \$rows : [];
\$createAction = (string) (\$createAction ?? '/apps/{$suiteKey}/{$moduleSlug}/create');
\$dashboardUrl = (string) (\$dashboardUrl ?? '/apps/{$suiteKey}');
?>

<div class="card">
  <h2 style="margin:0 0 8px"><?= e((string) (\$moduleTitle ?? '{$moduleLabel}')) ?></h2>
  <div class="muted"><?= e((string) (\$moduleDescription ?? '')) ?></div>
</div>

<?php if ((string) (\$message ?? '') !== ''): ?>
  <div class="card" style="border-color:rgba(0,200,120,.4);color:#7df0b0"><?= e((string) \$message) ?></div>
<?php endif; ?>
<?php if ((string) (\$error ?? '') !== ''): ?>
  <div class="card" style="border-color:rgba(255,80,80,.45);color:#ffb3b3"><?= e((string) \$error) ?></div>
<?php endif; ?>

<section class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
    <div>
      <h3 style="margin:0 0 6px">Starter Records</h3>
      <div class="muted">Use this scaffolded page as a baseline for {$suiteLabel} module work.</div>
    </div>
    <a class="btn" href="<?= e(\$dashboardUrl) ?>">Open Suite Dashboard</a>
  </div>

  <form method="post" action="<?= e(\$createAction) ?>" style="display:grid;gap:10px;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto;align-items:end;margin-top:14px">
    <input type="hidden" name="csrf" value="<?= e(\\App\\Core\\Auth::csrfToken()) ?>">
    <label>Record Name<input type="text" name="record_name" required></label>
    <label>Notes<input type="text" name="notes" placeholder="Optional notes"></label>
    <button class="btn btn-primary" type="submit">Create</button>
  </form>

  <?php if (\$rows === []): ?>
    <div class="card" style="margin:14px 0 0">
      <strong>No {$moduleLabel} records yet.</strong>
      <div class="muted" style="margin-top:6px">The scaffold is wired up. Replace the starter fields, queries, and table structure with the real module workflow next.</div>
    </div>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:14px">
      <table>
        <thead>
          <tr><th>ID</th><th>Name</th><th>Status</th><th>Notes</th><th>Updated</th></tr>
        </thead>
        <tbody>
          <?php foreach (\$rows as \$row): ?>
            <tr>
              <td><?= (int) (\$row['id'] ?? 0) ?></td>
              <td><?= e((string) (\$row['record_name'] ?? '')) ?></td>
              <td><?= e((string) (\$row['record_status'] ?? '')) ?></td>
              <td><?= e((string) (\$row['notes'] ?? '')) ?></td>
              <td><?= e((string) (\$row['updated_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/footer.php'; ?>
PHP;
    }

    private function moduleInstallTemplate(string $tableName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS {$tableName} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_name VARCHAR(190) NOT NULL,
        record_status VARCHAR(40) NOT NULL DEFAULT 'active',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
PHP;
    }

    private function moduleUpdateTemplate(string $tableName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

use App\Core\DB;

if (!DB::fetchOne("SHOW COLUMNS FROM {$tableName} LIKE 'notes'")) {
    DB::query("ALTER TABLE {$tableName} ADD COLUMN notes TEXT NULL AFTER record_status");
}
PHP;
    }
}
