<?php
declare(strict_types=1);

namespace App\AppManager\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Services\AppExportService;
use App\Services\AppInstallService;
use App\Services\AppLifecycleService;
use App\Services\DependencyGraphService;
use App\Services\AppMigrationService;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeRegistryService;
use App\Services\UiSurfaceRegistryDiagnosticsService;
use App\Services\AppLegacyBridgeService;
use App\Services\AppPlatformLogger;
use App\Services\SuiteSetupService;
use App\Services\VersionCatalogService;
use App\Core\DB;

final class AppManagerController
{
    public static function index(View $view): void
    {
        self::requireAdminToolsAccess();

        $migrationService = new AppMigrationService();
        $migrationService->ensureCoreTables();

        $registry = new AppRegistryService();
        $versions = new VersionCatalogService();
        $dependencyGraph = new DependencyGraphService();
        $apps = $registry->listAll();
        $installedPlugins = DB::fetchAll('SELECT name, status, version, installed_at FROM installed_plugins ORDER BY name ASC');
        $pluginStatusMap = [];
        foreach ($installedPlugins as $row) {
            $pluginName = trim((string)($row['name'] ?? ''));
            if ($pluginName === '') {
                continue;
            }
            $pluginStatusMap[$pluginName] = $row;
        }

        $view->render('admin/app_manager/index.php', [
            'apps' => $apps,
            'pluginStatusMap' => $pluginStatusMap,
            'appDependencyImpacts' => $dependencyGraph->appActionImpacts(array_map(static fn(array $app): string => (string)($app['app_key'] ?? ''), $apps)),
            'appVersionInfo' => array_reduce($apps, static function (array $carry, array $app) use ($versions): array {
                $key = (string)($app['app_key'] ?? '');
                if ($key !== '') {
                    $carry[$key] = $versions->appDetailInfo($key);
                }
                return $carry;
            }, []),
            'csrf' => Auth::csrfToken(),
            'flash_ok' => (string)($_GET['ok'] ?? ''),
            'flash_err' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function upload(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        try {
            $installer = new AppInstallService();
            $result = $installer->uploadAndRegister((array)($_FILES['zip'] ?? []));
            header('Location: /admin/app-manager?ok=' . urlencode('Uploaded: ' . (string)($result['manifest']['name'] ?? 'app')));
            exit;
        } catch (\Throwable $e) {
            header('Location: /admin/app-manager?err=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public static function action(): void
    {
        self::requireAdminToolsAccess();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $action = (string)($_POST['action'] ?? '');
        $appKey = (string)($_POST['app_key'] ?? '');

        try {
            $installer = new AppInstallService();
            $lifecycle = new AppLifecycleService();

            if ($action === 'install') {
                $installer->install($appKey);
            } elseif ($action === 'enable') {
                $lifecycle->enable($appKey);
            } elseif ($action === 'disable') {
                $lifecycle->disable($appKey);
            } elseif ($action === 'repair') {
                $lifecycle->repair($appKey);
            } elseif ($action === 'uninstall') {
                $lifecycle->uninstallSoft($appKey);
            } elseif ($action === 'purge') {
                $lifecycle->purge($appKey);
            } elseif ($action === 'upgrade_pending') {
                $lifecycle->markUpgradePending($appKey);
            } elseif ($action === 'recover') {
                $lifecycle->recoverFromBroken($appKey);
            } else {
                throw new \RuntimeException('Unsupported app action');
            }

            // Keep menus/permissions immediately consistent after lifecycle actions.
            (new AppRuntimeRegistryService())->syncEnabledRuntimeRegistries();

            header('Location: /admin/app-manager?ok=' . urlencode('Action complete: ' . $action . ' (' . $appKey . ')'));
            exit;
        } catch (\Throwable $e) {
            header('Location: /admin/app-manager?err=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public static function export(): void
    {
        self::requireAdminToolsAccess();

        $appKey = (string)($_GET['app_key'] ?? '');
        try {
            $exporter = new AppExportService();
            $zip = $exporter->exportToZip($appKey);
            self::downloadZip($zip, basename($zip));
            return;
        } catch (\Throwable $e) {
            header('Location: /admin/app-manager?err=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public static function detail(View $view): void
    {
        self::requireAdminToolsAccess();

        $appKey = trim((string)($_GET['app_key'] ?? ''));
        if ($appKey === '') {
            header('Location: /admin/app-manager?err=' . urlencode('Missing app_key'));
            exit;
        }

        $registry = new AppRegistryService();
        $app = $registry->find($appKey);
        if (!$app) {
            header('Location: /admin/app-manager?err=' . urlencode('App not found'));
            exit;
        }

        $migrations = DB::fetchAll(
            'SELECT migration_name, version, status, error_text, applied_at FROM core_app_migrations WHERE app_key=? ORDER BY id DESC LIMIT 200',
            [$appKey]
        );
        $snapshots = DB::fetchAll(
            'SELECT app_version, checksum, created_by, created_at FROM core_schema_snapshots WHERE app_key=? ORDER BY id DESC LIMIT 50',
            [$appKey]
        );
        $hooks = DB::fetchAll(
            'SELECT hook_type, hook_key, is_enabled, created_at FROM core_app_hooks WHERE app_key=? ORDER BY id DESC LIMIT 200',
            [$appKey]
        );
        $uiSurfaceDiagnostics = (new UiSurfaceRegistryDiagnosticsService())->diagnosticsForApp($appKey);

        $lifecycleLog = self::readLifecycleLog($appKey, 150);

        $dependencyService = new DependencyGraphService();
        $suiteKeys = array_keys((new SuiteSetupService())->availableSuites());
        $view->render('admin/app_manager/detail.php', [
            'app' => $app,
            'migrations' => $migrations,
            'snapshots' => $snapshots,
            'hooks' => $hooks,
            'uiSurfaceDiagnostics' => $uiSurfaceDiagnostics,
            'lifecycleLog' => $lifecycleLog,
            'manifest' => json_decode((string)($app['manifest_json'] ?? '{}'), true),
            'legacyBridgePlugins' => AppLegacyBridgeService::legacyPluginsForApp($appKey, json_decode((string)($app['manifest_json'] ?? '{}'), true)),
            'versionInfo' => (new VersionCatalogService())->appDetailInfo($appKey),
            'dependencyDetail' => in_array($appKey, $suiteKeys, true) ? $dependencyService->suiteDetail($appKey) : null,
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function readLifecycleLog(string $appKey, int $maxRows = 100): array
    {
        $rows = [];
        $logFile = APP_ROOT . '/storage/logs/app-lifecycle.log';
        if (is_file($logFile)) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines) && $lines) {
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $line = trim((string)$lines[$i]);
                    if ($line === '') {
                        continue;
                    }

                    $jsonStart = strpos($line, '{');
                    if ($jsonStart !== false) {
                        $line = substr($line, $jsonStart);
                    }

                    $entry = json_decode($line, true);
                    if (!is_array($entry)) {
                        continue;
                    }

                    if ((string)($entry['app_key'] ?? '') !== $appKey) {
                        continue;
                    }

                    $rows[] = $entry;
                }
            }
        }

        $rows = array_merge($rows, AppPlatformLogger::dedupedEntriesForApp($appKey, $maxRows));
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($right['ts'] ?? ''), (string)($left['ts'] ?? ''));
        });

        return array_slice($rows, 0, $maxRows);
    }

    private static function requireAdminToolsAccess(): void
    {
        Auth::bootSession();
        if (function_exists('base_require_admin_tools_access')) {
            base_require_admin_tools_access();
            return;
        }

        Auth::requireAdmin();
    }

    private static function downloadZip(string $absolutePath, string $downloadName): void
    {
        $real = realpath($absolutePath);
        $exportsDir = realpath(APP_ROOT . '/packages/exports');

        if ($real === false || $exportsDir === false || !str_starts_with($real, $exportsDir . DIRECTORY_SEPARATOR) || !is_file($real)) {
            throw new \RuntimeException('Export file not found or outside allowed export directory');
        }

        if (!is_readable($real)) {
            throw new \RuntimeException('Export file is not readable');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string)filesize($real));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($real);
        exit;
    }
}
