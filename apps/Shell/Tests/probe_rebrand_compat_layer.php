<?php
declare(strict_types=1);

/**
 * Rebrand compatibility layer probe.
 *
 * Goal: prove the Susankhya -> OdareHub rebrand keeps presentation identity
 * correct and keeps the JS overlay compatibility alias intact without leaving
 * legacy SusankhyaOS references in canonical runtime surfaces.
 */

if (!defined('APP_ROOT')) {
    // Resolve repo root from this file location:
    // apps/Shell/Tests/probe_rebrand_compat_layer.php -> go up 3 levels
    define('APP_ROOT', dirname(__DIR__, 3));
}

$assertions = 0;
$failed = 0;

function assertProbe(bool $condition, string $message): void
{
    global $assertions, $failed;
    $assertions++;

    if ($condition) {
        echo "  ok: {$message}\n";
        return;
    }

    $failed++;
    fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $c = file_get_contents($path);
    return is_string($c) ? $c : '';
}

function scanDirForString(string $root, string $needle): array
{
    $hits = [];
    if (!is_dir($root)) {
        return $hits;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        // Exclude this probe itself: its assertion strings intentionally
        // contain the legacy and canonical namespace literals.
        if (str_ends_with($file->getPathname(), 'probe_rebrand_compat_layer.php')) {
            continue;
        }
        $src = readFileSafe($file->getPathname());
        if ($src !== '' && str_contains($src, $needle)) {
            $hits[] = str_replace(APP_ROOT . '/', '', $file->getPathname());
        }
    }

    sort($hits);
    return $hits;
}

// 1) BrandIdentityService loads as a final Shell-owned static service.
require_once APP_ROOT . '/apps/Shell/Services/BrandIdentityService.php';

assertProbe(
    class_exists('Apps\\Shell\\Services\\BrandIdentityService'),
    'BrandIdentityService class is loadable'
);

$service = 'Apps\\Shell\\Services\\BrandIdentityService';

// 2) ODAREHUB_* identity takes precedence over SUSANKHYA_* (canonical wins).
putenv('ODAREHUB_PLATFORM_NAME=OdareHub_Prec');
putenv('SUSANKHYA_PLATFORM_NAME=Susankhya_Legacy');
putenv('ODAREHUB_APP_NAME=OdareHub_App_Prec');
putenv('SUSANKHYA_APP_NAME=Susankhya_App_Legacy');
putenv('ODAREHUB_VERSION_LABEL=OdareHub_Version_Prec');
putenv('SUSANKHYA_VERSION_LABEL=Susankhya_Version_Legacy');

$precedence = $service::runtime();
assertProbe($precedence['platform_name'] === 'OdareHub_Prec', 'ODAREHUB_PLATFORM_NAME wins over SUSANKHYA_PLATFORM_NAME');
assertProbe($precedence['app_name'] === 'OdareHub_App_Prec', 'ODAREHUB_APP_NAME wins over SUSANKHYA_APP_NAME');
assertProbe($precedence['version_label'] === 'OdareHub_Version_Prec', 'ODAREHUB_VERSION_LABEL wins over SUSANKHYA_VERSION_LABEL');
assertProbe($precedence['app_studio_name'] === 'ERP App Studio', 'app_studio_name is the Studio constant');

// 3) SUSANKHYA_* becomes the fallback when the ODAREHUB_* identity is absent.
putenv('ODAREHUB_PLATFORM_NAME=');
putenv('SUSANKHYA_PLATFORM_NAME=Susankhya_Fallback');
putenv('ODAREHUB_APP_NAME=');
putenv('SUSANKHYA_APP_NAME=Susankhya_App_Fallback');
putenv('ODAREHUB_VERSION_LABEL=');
putenv('SUSANKHYA_VERSION_LABEL=Susankhya_Version_Fallback');

$fallback = $service::runtime();
assertProbe($fallback['platform_name'] === 'Susankhya_Fallback', 'SUSANKHYA_PLATFORM_NAME is used when ODAREHUB_* is absent');
assertProbe($fallback['app_name'] === 'Susankhya_App_Fallback', 'SUSANKHYA_APP_NAME is used when ODAREHUB_* is absent');
assertProbe($fallback['version_label'] === 'Susankhya_Version_Fallback', 'SUSANKHYA_VERSION_LABEL is used when ODAREHUB_* is absent');

// 4) With every variable absent, defaults resolve to the OdareHub identity.
putenv('ODAREHUB_PLATFORM_NAME=');
putenv('SUSANKHYA_PLATFORM_NAME=');
putenv('ODAREHUB_APP_NAME=');
putenv('SUSANKHYA_APP_NAME=');
putenv('ODAREHUB_VERSION_LABEL=');
putenv('SUSANKHYA_VERSION_LABEL=');

$defaults = $service::runtime();
assertProbe($defaults['platform_name'] === 'OdareHub', 'default platform_name is OdareHub');
assertProbe($defaults['instance_name'] === 'OdareHub', 'default instance_name follows the platform name in CLI');
assertProbe($defaults['app_name'] === 'Workspace', 'default app_name is Workspace');
assertProbe($defaults['version_label'] === 'Identity Stabilization', 'default version_label is Identity Stabilization');
assertProbe($defaults['app_studio_name'] === 'ERP App Studio', 'default app_studio_name is ERP App Studio');

// 5) Canonical overlay namespace exists in the framework source.
$frameworkPath = APP_ROOT . '/apps/Shell/Services/ShellOverlayFramework.php';
$frameworkPhp = readFileSafe($frameworkPath);
assertProbe($frameworkPhp !== '', 'Probe can read ShellOverlayFramework.php');

assertProbe(
    str_contains($frameworkPhp, "const NS = 'OdareHubOS.ShellOverlay';"),
    'ShellOverlayFramework defines the canonical OdareHubOS.ShellOverlay namespace'
);
assertProbe(
    str_contains($frameworkPhp, 'window[NS] = {'),
    'ShellOverlayFramework exports the canonical namespace'
);

// 6) The legacy SusankhyaOS.ShellOverlay alias is the same object, emitted only
//    between the canonical export and the first applyVisual() call.
assertProbe(
    str_contains($frameworkPhp, "window['SusankhyaOS.ShellOverlay'] = window[NS];"),
    'SusankhyaOS.ShellOverlay alias references the canonical object'
);

$posAlias = strpos($frameworkPhp, "window['SusankhyaOS.ShellOverlay'] = window[NS];");
$posObjectClose = strrpos($frameworkPhp, '};');
$posApply = strrpos($frameworkPhp, 'applyVisual();');
assertProbe(
    $posAlias !== false && $posObjectClose !== false && $posApply !== false
        && $posObjectClose < $posAlias && $posAlias < $posApply,
    'SusankhyaOS alias sits between the canonical export close and the first applyVisual() call'
);

// 7) Canonical runtime surfaces keep the OdareHubOS.* namespace.
$adaptersHits = scanDirForString(APP_ROOT . '/apps/Shell/Overlay', 'SusankhyaOS');
assertProbe($adaptersHits === [], 'No SusankhyaOS references remain under apps/Shell/Overlay');

$composersHits = scanDirForString(APP_ROOT . '/apps/Shell/Composers', 'SusankhyaOS');
assertProbe($composersHits === [], 'No SusankhyaOS references remain under apps/Shell/Composers');

$testsHits = scanDirForString(APP_ROOT . '/apps/Shell/Tests', 'SusankhyaOS');
assertProbe($testsHits === [], 'No SusankhyaOS references remain under apps/Shell/Tests');

$canonicalAdapterHits = scanDirForString(APP_ROOT . '/apps/Shell/Overlay', 'OdareHubOS.ShellOverlay');
assertProbe(count($canonicalAdapterHits) > 0, 'Overlay adapters still reference the canonical OdareHubOS.ShellOverlay namespace');

// 8) Specific canonical call-site contracts remain intact.
$interactionComposer = readFileSafe(APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php');
assertProbe(
    str_contains($interactionComposer, 'window.OdareHubOS.ShellOverlayAdapters')
        && str_contains($interactionComposer, "window['OdareHubOS.ShellOverlay']"),
    'OperatorInteractionScriptComposer uses canonical OdareHubOS overlay binding'
);

$wrapperComposer = readFileSafe(APP_ROOT . '/apps/Shell/Services/OperatorLayerWrapperComposer.php');
assertProbe(
    str_contains($wrapperComposer, 'window.OdareHubOS.ShellOverlayAdapters'),
    'OperatorLayerWrapperComposer uses canonical OdareHubOS overlay binding'
);

$controllerProbe = readFileSafe(APP_ROOT . '/apps/Shell/Tests/probe_shell_overlay_controller_runtime.php');
assertProbe(
    str_contains($controllerProbe, "window['OdareHubOS.ShellOverlay']"),
    'Controller runtime probe references the canonical OdareHubOS namespace'
);

// Summary

echo "---\n";
echo "Rebrand compatibility layer probe: Checks={$assertions} Failed={$failed}\n";
exit($failed > 0 ? 1 : 0);