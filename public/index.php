<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);

$themeFingerprintLibrary = APP_ROOT . '/scripts/assets/theme_source_fingerprint.php';
if (is_file($themeFingerprintLibrary)) {
    require_once $themeFingerprintLibrary;
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

/**
 * Keep runtime theme asset in sync with source-theme files.
 *
 * This is a system-level bridge so Shell/runtime recognizes source theme files
 * under resources/themes without requiring Studio flows.
 */
function shouldRecompileThemeCss(string $runtimeThemePath): bool
{
    $manifestPath = APP_ROOT . '/resources/themes/theme-manifest.json';
    $compilerPath = APP_ROOT . '/scripts/assets/compile_theme_sources.php';

    if (!is_file($manifestPath) || !is_file($compilerPath)) {
        return false;
    }

    if (!is_file($runtimeThemePath)) {
        return true;
    }

    if (function_exists('susankhyaThemeSourceFingerprint') && function_exists('susankhyaCompiledThemeFingerprint')) {
        $sourceFingerprint = susankhyaThemeSourceFingerprint(APP_ROOT);
        $targetFingerprint = susankhyaCompiledThemeFingerprint($runtimeThemePath);
        if ($sourceFingerprint !== null && ($targetFingerprint === null || !hash_equals($sourceFingerprint, $targetFingerprint))) {
            return true;
        }
    }

    $runtimeMtime = (int)@filemtime($runtimeThemePath);
    if ($runtimeMtime <= 0) {
        return true;
    }

    $manifestMtime = (int)@filemtime($manifestPath);
    $compilerMtime = (int)@filemtime($compilerPath);
    if ($manifestMtime > $runtimeMtime || $compilerMtime > $runtimeMtime) {
        return true;
    }

    $manifestRaw = @file_get_contents($manifestPath);
    if (!is_string($manifestRaw) || $manifestRaw === '') {
        return false;
    }

    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest)) {
        return false;
    }

    $manifestStatus = strtolower(trim((string)($manifest['status'] ?? '')));
    if ($manifestStatus !== 'runtime_wired_ready') {
        return false;
    }

    $sources = $manifest['sources'] ?? [];
    $disabledPaths = [];
    $includedPaths = [];
    if (is_array($sources)) {
        foreach ($sources as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $path = trim((string)($entry['path'] ?? ''));
            if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
                continue;
            }
            $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

            if (empty($entry['enabled'])) {
                $kind = strtolower(trim((string)($entry['kind'] ?? '')));
                if ($kind === 'style' || $kind === 'custom') {
                    $disabledPaths[$normalizedPath] = true;
                }
                continue;
            }

            $includedPaths[$normalizedPath] = true;
            $sourcePath = APP_ROOT . '/resources/themes/' . ltrim(str_replace('\\', '/', $path), '/');
            if (is_file($sourcePath) && (int)@filemtime($sourcePath) > $runtimeMtime) {
                return true;
            }
        }
    }

    $themeRoot = APP_ROOT . '/resources/themes';
    if (is_dir($themeRoot)) {
        $excludedBase = [
            'foundation.css' => true,
            'light.css' => true,
            'dark.css' => true,
        ];

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $rootNorm = rtrim(str_replace('\\', '/', $themeRoot), '/');
        foreach ($iter as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            if (strtolower((string)$fileInfo->getExtension()) !== 'css') {
                continue;
            }

            $absPath = str_replace('\\', '/', (string)$fileInfo->getPathname());
            if (!str_starts_with($absPath, $rootNorm . '/')) {
                continue;
            }
            $relativePath = ltrim((string)substr($absPath, strlen($rootNorm) + 1), '/');
            if ($relativePath === '' || isset($excludedBase[$relativePath])) {
                continue;
            }
            if (isset($disabledPaths[$relativePath])) {
                continue;
            }

            if ((int)@filemtime($fileInfo->getPathname()) > $runtimeMtime) {
                return true;
            }

            // New non-manifest style files should trigger runtime compile once discovered.
            if (!isset($includedPaths[$relativePath])) {
                return true;
            }
        }
    }

    $legacyBase = $manifest['legacy_base'] ?? null;
    $legacyEnabled = false;
    if (is_array($legacyBase)) {
        $legacyEnabled = !array_key_exists('enabled', $legacyBase) || (bool)($legacyBase['enabled'] ?? false);
    }
    if ($legacyEnabled) {
        $legacyPath = trim((string)($legacyBase['path'] ?? 'public/assets/theme.legacy.css'));
        if ($legacyPath !== '' && !str_contains($legacyPath, '..') && !str_starts_with($legacyPath, '/')) {
            $legacyAbs = APP_ROOT . '/' . ltrim(str_replace('\\', '/', $legacyPath), '/');
            if (is_file($legacyAbs) && (int)@filemtime($legacyAbs) > $runtimeMtime) {
                return true;
            }
        }
    }

    return false;
}

function maybeRecompileThemeCss(string $runtimeThemePath): void
{
    if (!shouldRecompileThemeCss($runtimeThemePath)) {
        return;
    }

    $compilerPath = normalizePath(APP_ROOT . '/scripts/assets/compile_theme_sources.php');
    $compilerAvailable = is_file($compilerPath);

    if ($compilerAvailable) {
        $phpBin = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== ''
            ? PHP_BINARY
            : 'php';

        $command = escapeshellarg($phpBin)
            . ' '
            . escapeshellarg($compilerPath)
            . ' --apply --json 2>&1';

        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);

        if ($exitCode === 0 && is_file($runtimeThemePath) && filesize($runtimeThemePath) > 0) {
            return;
        }
    }

    // Fallback: in-process aggregation when exec() is unavailable or compiler fails.
    $fallbackCompiler = normalizePath(APP_ROOT . '/scripts/assets/first_boot_css_compiler.php');
    if (is_file($fallbackCompiler)) {
        require_once $fallbackCompiler;
        if (function_exists('compileThemeCssIfAvailable')) {
            $result = compileThemeCssIfAvailable(APP_ROOT, true);
            if (!$result['ok']) {
                error_log('[theme-runtime] in-process fallback also failed; status=' . ($result['theme_css_status'] ?? 'unknown'));
            }
            return;
        }
    }

    if (!$compilerAvailable) {
        error_log('[theme-runtime] no compiler available (compile_theme_sources.php missing)');
    } else {
        error_log('[theme-runtime] compile failed while serving /assets/theme.css; exit=' . (string)$exitCode);
    }
}

function firstBootAssetTargets(): array
{
    return [
        '/assets/system/shell-essential.css' => PUBLIC_ROOT . '/assets/system/shell-essential.css',
        '/assets/rendering/foundation.css' => PUBLIC_ROOT . '/assets/rendering/foundation.css',
        '/assets/effects/effects-none.css' => PUBLIC_ROOT . '/assets/effects/effects-none.css',
        '/assets/themes/liquid-glass-system.css' => PUBLIC_ROOT . '/assets/themes/liquid-glass-system.css',
        '/assets/system/semantic-aliases.css' => PUBLIC_ROOT . '/assets/system/semantic-aliases.css',
        '/assets/system/setup.css' => PUBLIC_ROOT . '/assets/system/setup.css',
        '/assets/system/auth.css' => PUBLIC_ROOT . '/assets/system/auth.css',
        '/assets/theme.css' => PUBLIC_ROOT . '/assets/theme.css',
    ];
}

function shouldRecompileFirstBootCss(array $assetTargets): bool
{
    $manifestPath = APP_ROOT . '/scripts/assets/first_boot_css_manifest.json';
    $compilerPath = APP_ROOT . '/scripts/assets/compile_first_boot_css.php';

    if (!is_file($manifestPath) || !is_file($compilerPath)) {
        return false;
    }

    $runtimeMtime = null;
    foreach ($assetTargets as $targetPath) {
        if (!is_file($targetPath) || !is_readable($targetPath)) {
            return true;
        }
        $mtime = (int)@filemtime($targetPath);
        if ($mtime <= 0) {
            return true;
        }
        $runtimeMtime = $runtimeMtime === null ? $mtime : min($runtimeMtime, $mtime);
    }

    if ($runtimeMtime === null || (int)@filemtime($manifestPath) > $runtimeMtime || (int)@filemtime($compilerPath) > $runtimeMtime) {
        return true;
    }

    $manifestRaw = @file_get_contents($manifestPath);
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || !is_array($manifest['assets'] ?? null)) {
        return false;
    }

    foreach ($manifest['assets'] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $sources = is_array($asset['sources'] ?? null) ? $asset['sources'] : [];
        foreach ($sources as $source) {
            if (!is_string($source) || trim($source) === '') {
                continue;
            }
            $sourcePath = APP_ROOT . '/' . ltrim(str_replace('\\', '/', $source), '/');
            if (is_file($sourcePath) && (int)@filemtime($sourcePath) > $runtimeMtime) {
                return true;
            }
        }
    }

    return false;
}

function maybeRecompileFirstBootCss(): void
{
    $assetTargets = array_values(firstBootAssetTargets());
    if (!shouldRecompileFirstBootCss($assetTargets)) {
        return;
    }

    $compilerLibraryPath = normalizePath(APP_ROOT . '/scripts/assets/first_boot_css_compiler.php');
    if (!is_file($compilerLibraryPath)) {
        return;
    }

    require_once $compilerLibraryPath;
    if (!function_exists('compileFirstBootCssAssets')) {
        return;
    }

    try {
        $result = compileFirstBootCssAssets(APP_ROOT, true);
    } catch (\Throwable $e) {
        error_log('[first-boot-css-runtime] compile threw: ' . $e->getMessage());
        return;
    }

    if (($result['ok'] ?? false) !== true) {
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        error_log('[first-boot-css-runtime] compile failed while serving first-boot css; errors=' . implode(',', $errors));
    }
}

// 1) Serve favicon.ico with a minimal tiny image to avoid 404s
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (isset($requestPath) && $requestPath === '/favicon.ico') {
    header('Content-Type: image/png');
    // 1x1 PNG (transparent)
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
    exit;
}

// 0) Keep theme.css synchronized before any dynamic page render. The fingerprint
//    check is deterministic across deployments and catches missing, stale, or
//    timestamp-preserved assets without making generated CSS source truth.
$themeCssEarlyPath = PUBLIC_ROOT . '/assets/theme.css';
try {
    maybeRecompileThemeCss($themeCssEarlyPath);
    if (!is_file($themeCssEarlyPath) || filesize($themeCssEarlyPath) === 0) {
        maybeRecompileFirstBootCss();
    }
} catch (\Throwable $e) {
    error_log('[theme-runtime] early theme synchronization failed: ' . $e->getMessage());
}

$firstBootAssetMap = firstBootAssetTargets();
if (isset($firstBootAssetMap[$requestPath])) {
    $assetPath = $firstBootAssetMap[$requestPath];
    maybeRecompileFirstBootCss();
    if (is_file($assetPath) && is_readable($assetPath)) {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('ETag: "' . md5_file($assetPath) . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($assetPath)));
        readfile($assetPath);
        exit;
    }
}

// 1a) Shared theme CSS asset used by governed Studio tools.
if ($requestPath === '/assets/theme.css') {
    $themeCssPath = PUBLIC_ROOT . '/assets/theme.css';
    maybeRecompileThemeCss($themeCssPath);
    if (is_file($themeCssPath) && is_readable($themeCssPath)) {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('ETag: "' . md5_file($themeCssPath) . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($themeCssPath)));
        readfile($themeCssPath);
        exit;
    }
}

// 1b) App-scoped CSS route: /assets/apps/{app}/styles/{file}.css -> apps/{app}/styles/{file}.css
// Also handles module CSS: /assets/apps/{app}/modules/{module}/styles.css -> apps/{app}/modules/{module}/styles.css
if (preg_match('#^/assets/apps/([a-z0-9._-]+)/(styles|modules)/(.+\.css)$#i', $requestPath, $matches)) {
    $appKey = $matches[1];
    $styleType = $matches[2]; // 'styles' or 'modules'
    $filePath = $matches[3];
    $normalizeAssetKey = static fn(string $value): string => strtolower((string)preg_replace('/[^a-z0-9]/i', '', $value));
    $resolveChildDir = static function (string $parent, string $key) use ($normalizeAssetKey): ?string {
        $target = $normalizeAssetKey($key);
        if ($target === '' || !is_dir($parent)) {
            return null;
        }
        foreach (scandir($parent) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $parent . '/' . $entry;
            if (is_dir($path) && $normalizeAssetKey($entry) === $target) {
                return $path;
            }
        }
        return null;
    };
    $appDir = $resolveChildDir(APP_ROOT . '/apps', $appKey);

    $candidates = [];
    if ($styleType === 'styles') {
        if ($appDir !== null) {
            $candidates[] = $appDir . '/styles/' . $filePath;
        }
        $candidates[] = APP_ROOT . '/apps/Generated/' . strtolower($appKey) . '/' . $filePath;
    } else {
        // For modules, the structure is /assets/apps/{app}/modules/{moduleKey}/styles.css
        // which maps to apps/{app}/modules/{ModuleKey}/styles.css
        $parts = explode('/', $filePath);
        if (count($parts) >= 2 && $parts[count($parts) - 1] === 'styles.css') {
            array_pop($parts);
            $moduleKey = implode('/', $parts);
            if ($appDir !== null) {
                $moduleDir = $resolveChildDir($appDir . '/modules', $moduleKey);
                if ($moduleDir !== null) {
                    $candidates[] = $moduleDir . '/styles.css';
                }
            }
            $candidates[] = APP_ROOT . '/apps/Generated/' . strtolower($appKey) . '/' . strtolower($moduleKey) . '/styles.css';
        }
    }

    foreach ($candidates as $absolutePath) {
        if (is_file($absolutePath)) {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=31536000');
            header('ETag: "' . md5_file($absolutePath) . '"');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', filemtime($absolutePath)));
            readfile($absolutePath);
            exit;
        }
    }
}

// 1b) Operator layer preprocessor: /u/username -> /u with username in query
// Skip fixed operator endpoints that are not username-addressed.
if (preg_match('#^/u/(?!kpi-feed(?:/|$))([a-zA-Z0-9._@%-]+)((?:/[^?]*)?)#', $requestPath, $matches)) {
    $username = urldecode($matches[1]);
    $uSubpath = $matches[2] ?? ''; // e.g. '/work-entry' or ''
    $_GET['u_username'] = $username;
    $uQs = (($qpos = strpos($_SERVER['REQUEST_URI'] ?? '', '?')) !== false) ? substr($_SERVER['REQUEST_URI'], $qpos) : '';
    $_SERVER['REQUEST_URI'] = '/u' . $uSubpath . $uQs;
}

// 1c) Admin layer preprocessor: /admin/username -> /admin with username in query
// Only match if the segment after /admin/ is a username, not a known sub-route keyword.
// Known fixed sub-routes: apps, architecture-health, base, routes, setup,
// system-tools, platform-admin-links, app-manager.
if (preg_match('#^/admin/(?!apps|architecture-health|base|routes|setup|system-tools|platform-admin-links|app-manager)([a-zA-Z0-9._@%-]+)(?:/[^?]*)?#', $requestPath, $matches)) {
    $username = urldecode($matches[1]);
    $_GET['admin_username'] = $username;
    $adminQs = (($qpos = strpos($_SERVER['REQUEST_URI'] ?? '', '?')) !== false) ? substr($_SERVER['REQUEST_URI'], $qpos) : '';
    $_SERVER['REQUEST_URI'] = '/admin' . $adminQs;
}

// 1d) Display layer preprocessor: /displays/user/username -> /displays/user with username in query
if (preg_match('#^/displays/user/([a-zA-Z0-9._@%-]+)#', $requestPath, $matches)) {
    $username = urldecode($matches[1]);
    $_GET['display_username'] = $username;
    $displayQs = (($qpos = strpos($_SERVER['REQUEST_URI'] ?? '', '?')) !== false) ? substr($_SERVER['REQUEST_URI'], $qpos) : '';
    $_SERVER['REQUEST_URI'] = '/displays/user' . $displayQs;
}

// 1e) Display layer preprocessor: /displays/device/device_id -> /displays/device with device_id in query
if (preg_match('#^/displays/device/([a-zA-Z0-9._@%-]+)#', $requestPath, $matches)) {
    $deviceId = urldecode($matches[1]);
    $_GET['display_device_id'] = $deviceId;
    $displayQs = (($qpos = strpos($_SERVER['REQUEST_URI'] ?? '', '?')) !== false) ? substr($_SERVER['REQUEST_URI'], $qpos) : '';
    $_SERVER['REQUEST_URI'] = '/displays/device' . $displayQs;
}

// 1f) Legacy /admin entrypoint: redirect to /me (which now redirects to /admin/{username})
if ($requestPath === '/admin' || $requestPath === '/admin/') {
    header('Location: /me', true, 302);
    exit;
}

// 2) Smart landing is handled by the route callbacks (plugins/Base/routes.php `/`
// and apps/Shell/routes.php `/me`) which run after the full bootstrap + autoload.
// Do NOT attempt LandingPageService here — DB and Auth are not yet loaded.

require_once APP_ROOT . '/app/Core/helpers.php';

// First-boot public routes must degrade cleanly even when storage/db_config.php
// points at a database that does not exist yet.
$bootstrapWarningSuppressed = (
    $requestPath === '/setup'
    || $requestPath === '/setup/2fa'
    || $requestPath === '/setup/2fa/qr'
    || $requestPath === '/login'
    || $requestPath === '/forgot-password'
    || $requestPath === '/reset-password'
    || $requestPath === '/account/setup'
    || str_starts_with($requestPath, '/recovery/')
    || str_starts_with($requestPath, '/maintenance/')
);

// First-boot safety: setup preflight expects mysqli calls to return false on
// missing DB, not throw exceptions that crash /setup before the wizard can render.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$debugEnabled = app_debug_enabled();
$errorLevel = $debugEnabled ? E_ALL : (E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if ($bootstrapWarningSuppressed) {
    $errorLevel &= ~E_WARNING;
}
error_reporting($errorLevel);
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once APP_ROOT . '/app/Core/DB.php';
require_once APP_ROOT . '/app/Core/Container.php';
require_once APP_ROOT . '/app/Core/EventBus.php';
require_once APP_ROOT . '/app/Core/Router.php';
require_once APP_ROOT . '/app/Core/View.php';
require_once APP_ROOT . '/app/Core/PdfService.php';
require_once APP_ROOT . '/app/Core/PluginManager.php';
require_once APP_ROOT . '/app/Core/Application.php';
require_once APP_ROOT . '/app/Core/ModuleRegistry.php';
require_once APP_ROOT . '/app/Core/AclPolicy.php';
require_once APP_ROOT . '/app/Core/DashboardBuilder.php';
require_once APP_ROOT . '/app/Core/ActionBuilder.php';
require_once APP_ROOT . '/app/Core/SidebarBuilder.php';
require_once APP_ROOT . '/app/Core/RouteRuntimeAuthority.php';
require_once APP_ROOT . '/app/Core/SupplyModel.php';
require_once APP_ROOT . '/app/Core/AuditLogService.php';
require_once APP_ROOT . '/app/Core/HandoffEngine.php';
require_once APP_ROOT . '/app/Core/WorkflowRegistry.php';
require_once APP_ROOT . '/app/Core/WorkflowTransitionEngine.php';
require_once APP_ROOT . '/app/Core/WorkflowPolicy.php';
require_once APP_ROOT . '/app/Core/WorkflowGovernance.php';

require_once APP_ROOT . '/app/Core/Auth.php';
require_once APP_ROOT . '/app/Core/TOTP.php';

require_once APP_ROOT . '/app/Services/AppPlatformLogger.php';
require_once APP_ROOT . '/app/Services/AppManifestService.php';
require_once APP_ROOT . '/app/Services/AppRegistryService.php';
require_once APP_ROOT . '/app/Services/AppSchemaSyncService.php';
require_once APP_ROOT . '/app/Services/AppMigrationService.php';
require_once APP_ROOT . '/app/Services/AppPackageService.php';
require_once APP_ROOT . '/app/Services/AppInstallService.php';
require_once APP_ROOT . '/app/Services/AppLifecycleService.php';
require_once APP_ROOT . '/app/Services/AppExportService.php';
require_once APP_ROOT . '/app/Services/AppRuntimeLoader.php';
require_once APP_ROOT . '/app/Services/AppRuntimeRegistryService.php';
require_once APP_ROOT . '/app/Services/UiSurfaceRegistryDiagnosticsService.php';
require_once APP_ROOT . '/app/Services/AppLegacyBridgeService.php';
require_once APP_ROOT . '/app/Services/AppLocalDiscoveryService.php';
require_once APP_ROOT . '/app/Services/SetupProfileService.php';
require_once APP_ROOT . '/app/Services/ModuleLifecycleService.php';
require_once APP_ROOT . '/app/Services/InstallVerificationService.php';
require_once APP_ROOT . '/app/Services/SuiteSetupService.php';
require_once APP_ROOT . '/app/Services/CoreSetupService.php';
require_once APP_ROOT . '/app/Services/SetupStateService.php';
require_once APP_ROOT . '/app/Services/SetupStatusService.php';
require_once APP_ROOT . '/app/Services/ConfigManagementService.php';
require_once APP_ROOT . '/app/Services/CompanySettingsService.php';
require_once APP_ROOT . '/app/Services/DependencyGraphService.php';
require_once APP_ROOT . '/app/Services/DeploymentReadinessService.php';
require_once APP_ROOT . '/app/Services/EnvironmentCloneService.php';
require_once APP_ROOT . '/app/Services/EnvironmentPortabilityHistoryService.php';
require_once APP_ROOT . '/app/Services/EnvironmentSnapshotService.php';
require_once APP_ROOT . '/app/Services/ExportHistoryService.php';
require_once APP_ROOT . '/app/Services/HealthDashboardService.php';
require_once APP_ROOT . '/app/Services/IdentitySecurityLogService.php';
require_once APP_ROOT . '/app/Services/ImportHistoryService.php';
require_once APP_ROOT . '/app/Services/InviteLifecycleService.php';
require_once APP_ROOT . '/app/Services/MailConfigService.php';
require_once APP_ROOT . '/app/Services/MailTemplateService.php';
require_once APP_ROOT . '/app/Services/MailService.php';
require_once APP_ROOT . '/app/Services/PasswordPolicyService.php';
require_once APP_ROOT . '/app/Services/PasswordResetService.php';
require_once APP_ROOT . '/app/Services/ReleaseHistoryService.php';
require_once APP_ROOT . '/app/Services/ReleasePackagingService.php';
require_once APP_ROOT . '/app/Services/RestoreHistoryService.php';
require_once APP_ROOT . '/app/Services/ScaffoldGeneratorService.php';
require_once APP_ROOT . '/app/Services/SuiteExportService.php';
require_once APP_ROOT . '/app/Services/SuiteRestoreService.php';
require_once APP_ROOT . '/app/Services/TabularImportReaderService.php';
require_once APP_ROOT . '/app/Services/TwoFactorQrService.php';
require_once APP_ROOT . '/app/Services/UpgradeAssistantService.php';
require_once APP_ROOT . '/app/Services/VersionCatalogService.php';
require_once APP_ROOT . '/app/Core/SearchService.php';
require_once APP_ROOT . '/app/AppManager/Controllers/AppManagerController.php';
require_once APP_ROOT . '/app/AppManager/Controllers/SearchController.php';
require_once APP_ROOT . '/app/AppManager/Controllers/SetupController.php';
require_once APP_ROOT . '/apps/Shell/Services/BrandIdentityService.php';
// (No longer needed: now loaded via Application::boot())

use App\Core\Application;
use App\Core\Auth;
use App\Core\View;
use App\Services\CoreSetupService;
use App\AppManager\Controllers\SetupController;

Auth::bootSession();
apply_global_preferences_from_request();

// Redirect to clean URL after lang/currency preference is persisted.
// These are temporary command params, not permanent page-state params.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!empty($_GET['lang']) || !empty($_GET['currency']))) {
    $clean = $_GET;
    unset($clean['lang'], $clean['currency']);
    $query = http_build_query($clean);
    $redirectPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($query !== '') {
        $redirectPath .= '?' . $query;
    }
    // 303 See Other: redirect after state-changing GET to avoid re-submit on refresh
    http_response_code(303);
    header('Location: ' . $redirectPath);
    exit;
}

// Global public allowlist (auth/setup/static)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bootstrapPublicAllow = [
    '/setup',
    '/setup/2fa',
    '/setup/2fa/qr',
];

$coreSetup = new CoreSetupService();
$hasActivePublicSetup = SetupController::hasActivePublicWizardSession();
$needsSetupBootstrap = $coreSetup->needsSetupBootstrap();
if ($path === '/setup' && ($needsSetupBootstrap || $hasActivePublicSetup)) {
    $view = new View(APP_ROOT . '/public/views');
    SetupController::handlePublic($view);
    exit;
}

if ($needsSetupBootstrap) {
    $bootstrapAllowed =
        in_array($path, $bootstrapPublicAllow, true) ||
        str_starts_with($path, '/assets') ||
        str_starts_with($path, '/public/assets');

    if (!$bootstrapAllowed) {
        header('Location: /setup');
        exit;
    }
}

// Allow auth/setup endpoints + static assets
$publicAllow = [
    '/login', '/2fa', '/logout', '/forgot-password', '/reset-password', '/account/setup',
    '/passkey/challenge', '/passkey/authenticate',
    '/qr/product/scan',
];

if ($needsSetupBootstrap || $hasActivePublicSetup) {
    $publicAllow[] = '/setup';
    $publicAllow[] = '/setup/2fa';
    $publicAllow[] = '/setup/2fa/qr';
}

$isAllowed =
    in_array($path, $publicAllow, true) ||
    str_starts_with($path, '/assets') ||
    str_starts_with($path, '/public/assets');

if (!$isAllowed && !Auth::isLoggedIn()) {
    Auth::rememberIntendedUrl();
    header('Location: /login');
    exit;
}

$app = new Application();
$app->boot();
$app->run();
