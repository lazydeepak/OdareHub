<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function camera_scan_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function camera_scan_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/Shared/CameraScanOverlayAdapter.php';
$publicHeaderPath = APP_ROOT . '/public/views/layouts/header.php';
$operatorScriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$operatorSurfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';

$adapter = camera_scan_read($adapterPath);
$publicHeader = camera_scan_read($publicHeaderPath);
$operatorScript = camera_scan_read($operatorScriptPath);
$operatorSurface = camera_scan_read($operatorSurfacePath);

camera_scan_assert($adapter !== '', 'camera scan adapter exists');
camera_scan_assert($publicHeader !== '', 'public header exists');
camera_scan_assert($operatorScript !== '', 'operator interaction script exists');
camera_scan_assert($operatorSurface !== '', 'operator surface composer exists');

camera_scan_assert(str_contains($adapter, 'createCameraScanOverlayAdapter'), 'adapter exposes shared camera scan factory');
camera_scan_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    camera_scan_assert(!str_contains($adapter, $forbidden), "adapter does not reference internal framework service {$forbidden}");
}

camera_scan_assert(str_contains($adapter, "overlayType = 'camera_scan_overlay'"), 'adapter maps public/operator scanner family to one stable overlay type');
camera_scan_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'a11yRole:')
        && !str_contains($adapter, 'backdropDefaults:'),
    'adapter metadata certifies scanner as modal dialog-like overlay'
);
camera_scan_assert(
    str_contains($publicHeader, 'CameraScanOverlayAdapter.php')
        && str_contains($publicHeader, "sourceSurface: 'public'")
        && str_contains($publicHeader, "cameraScanAdapter.open('launch')")
        && str_contains($publicHeader, 'cameraScanAdapter.close'),
    'public scanner launch and cleanup notify shared adapter'
);
camera_scan_assert(
    str_contains($operatorSurface, 'CameraScanOverlayAdapter::renderScript()')
        && str_contains($operatorScript, "sourceSurface: 'operator'")
        && str_contains($operatorScript, "cameraScanAdapter.open('launch')")
        && str_contains($operatorScript, 'cameraScanAdapter.close'),
    'operator scanner launch and cleanup notify shared adapter'
);
camera_scan_assert(
    str_contains($publicHeader, 'document.body.appendChild(overlay)')
        && str_contains($operatorScript, 'document.body.appendChild(overlay)')
        && str_contains($adapter, 'outsideSurfaceSelf: true')
        && str_contains($adapter, 'onClose: handleControllerClose')
        && str_contains($publicHeader, "onControllerClose: function() { cleanup({ ok: false, reason: 'outside-click' }); }")
        && str_contains($operatorScript, "onControllerClose: () => close({ ok: false, reason: 'outside-click' })")
        && !str_contains($publicHeader, "overlay.addEventListener('click', function(event)")
        && !str_contains($operatorScript, "overlay.addEventListener('click', (event)"),
    'dynamic rendering stays local while controller owns overlay-root dismissal and cleanup callback'
);

echo '[probe] Camera scan overlay adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
