<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Router;
use App\Core\View;

final class AppRuntimeLoader
{
    /**
     * Track routes that have been loaded to prevent duplicate loading.
     * @var array<string, bool>
     */
    private static array $loadedRoutes = [];

    private AppRegistryService $registry;

    public function __construct(?AppRegistryService $registry = null)
    {
        $this->registry = $registry ?? new AppRegistryService();
    }

    /**
     * Load enabled apps with safe loading guard to prevent duplicate inclusion.
     * Each route file is loaded exactly once per request.
     */
    public function loadEnabledApps(Container $c, Router $router, View $view): void
    {
        $apps = $this->registry->enabledApps();
        foreach ($apps as $app) {
            $manifest = json_decode((string)($app['manifest_json'] ?? '{}'), true);
            if (!is_array($manifest)) {
                continue;
            }

            $appKey = (string)($app['app_key'] ?? '');
            $installPath = (string)($app['install_path'] ?? '');
            if ($appKey === '' || $installPath === '') {
                continue;
            }

            $viewPath = $installPath . '/Views';
            if (is_dir($viewPath)) {
                $view->addNamespace($appKey, $viewPath);
            }

            $entry = trim((string)($manifest['entry'] ?? ''));
            if ($entry === '') {
                continue;
            }

            $entryFile = rtrim($installPath, '/') . '/' . ltrim($entry, '/');
            if (!is_file($entryFile)) {
                AppPlatformLogger::lifecycle('runtime_entry_missing', $appKey, ['entry' => $entryFile]);
                continue;
            }

            // Safe loading guard: prevent duplicate inclusion
            $fileKey = realpath($entryFile) ?: $entryFile;
            if (isset(self::$loadedRoutes[$fileKey])) {
                AppPlatformLogger::lifecycle('runtime_duplicate_load_skipped', $appKey, ['file' => $fileKey]);
                continue;
            }

            try {
                self::$loadedRoutes[$fileKey] = true;
                require $entryFile;
            } catch (\Throwable $e) {
                $this->registry->setStatus($appKey, AppRegistryService::STATUS_BROKEN, $e->getMessage());
                AppPlatformLogger::lifecycle('runtime_load_failed', $appKey, ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Get list of loaded route files (useful for diagnostics and testing).
     * @return array<int, string>
     */
    public static function getLoadedRouteFiles(): array
    {
        return array_keys(self::$loadedRoutes);
    }

    /**
     * Reset loaded routes cache (primarily for testing).
     */
    public static function resetLoadedRoutes(): void
    {
        self::$loadedRoutes = [];
    }
}
