<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function operator_hamburger_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function operator_hamburger_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/OperatorSurface/OperatorHamburgerDrawerAdapter.php';
$scriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$surfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';

$adapter = operator_hamburger_read($adapterPath);
$script = operator_hamburger_read($scriptPath);
$surface = operator_hamburger_read($surfacePath);

operator_hamburger_assert($adapter !== '', 'operator hamburger adapter exists');
operator_hamburger_assert($script !== '', 'operator interaction script exists');
operator_hamburger_assert($surface !== '', 'operator surface composer exists');

operator_hamburger_assert(str_contains($adapter, 'createOperatorHamburgerDrawerAdapter'), 'adapter exposes hamburger drawer factory');
operator_hamburger_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    operator_hamburger_assert(!str_contains($adapter, $forbidden), "adapter does not reference internal framework service {$forbidden}");
}

operator_hamburger_assert(str_contains($adapter, "overlayType = 'operator_hamburger_drawer'"), 'adapter maps hamburger drawer to stable overlay type');
operator_hamburger_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies modal/backdrop/scroll-lock intent'
);
operator_hamburger_assert(
    str_contains($surface, 'OperatorHamburgerDrawerAdapter::renderScript()')
        && str_contains($script, 'createOperatorHamburgerDrawerAdapter'),
    'operator surface renders adapter and interaction script creates it'
);
operator_hamburger_assert(
    str_contains($script, 'operatorHamburgerDrawerAdapter.syncState(isOpen')
        && str_contains($script, 'setLegacyOpen: setHamburgerMenuOpen')
        && str_contains($adapter, 'onClose: handleControllerClose')
        && !str_contains($script, "mainContent.style.overflow = isOpen ? 'hidden' : ''")
        && !str_contains($script, "document.body.style.overflow = isOpen ? 'hidden' : ''"),
    'controller owns hamburger dismissal/scroll lock while responsive DOM stays local'
);

echo '[probe] Operator hamburger drawer adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
