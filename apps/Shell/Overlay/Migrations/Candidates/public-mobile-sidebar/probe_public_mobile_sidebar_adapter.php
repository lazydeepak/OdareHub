<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function public_sidebar_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function public_sidebar_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicMobileSidebarDrawerAdapter.php';
$headerPath = APP_ROOT . '/public/views/layouts/header.php';

$adapter = public_sidebar_read($adapterPath);
$header = public_sidebar_read($headerPath);

public_sidebar_assert($adapter !== '', 'public mobile sidebar drawer adapter exists');
public_sidebar_assert($header !== '', 'public header exists');

public_sidebar_assert(str_contains($adapter, 'createPublicMobileSidebarDrawerAdapter'), 'adapter exposes public sidebar factory');
public_sidebar_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    public_sidebar_assert(!str_contains($adapter, $forbidden), "adapter does not reference internal framework service {$forbidden}");
}

public_sidebar_assert(str_contains($adapter, "overlayType = 'public_mobile_sidebar_drawer'"), 'adapter maps sidebar drawer to stable overlay type');
public_sidebar_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata matches observed mobile drawer backdrop/no-scroll-lock behavior'
);
public_sidebar_assert(str_contains($adapter, 'setLegacyOpen'), 'adapter preserves legacy rendering callback for rollback');

public_sidebar_assert(
    str_contains($header, 'PublicMobileSidebarDrawerAdapter.php')
        && str_contains($header, 'createPublicMobileSidebarDrawerAdapter')
        && str_contains($header, 'requestSidebarOpen'),
    'public header loads and routes mobile sidebar through adapter boundary'
);
public_sidebar_assert(
    str_contains($header, "requestSidebarOpen(!sidebar.classList.contains('sidebar-open'), 'trigger')")
        && str_contains($header, "requestSidebarOpen(false, 'navigation')")
        && str_contains($adapter, 'onClose: handleControllerClose')
        && !str_contains($header, "requestSidebarOpen(false, 'backdrop')"),
    'trigger/navigation use adapter while controller owns outside/backdrop dismissal'
);
public_sidebar_assert(
    str_contains($header, 'function setSidebarOpen(open)')
        && str_contains($header, 'setLegacyOpen: setSidebarOpen'),
    'legacy sidebar rendering remains rollback path'
);

echo '[probe] Public mobile sidebar adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
