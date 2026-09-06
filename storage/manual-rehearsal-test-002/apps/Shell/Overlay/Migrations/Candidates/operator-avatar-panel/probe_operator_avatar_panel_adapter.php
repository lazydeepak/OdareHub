<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function operator_avatar_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function operator_avatar_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/OperatorSurface/OperatorAvatarPanelAdapter.php';
$scriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$surfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';
$markupPath = APP_ROOT . '/apps/Shell/Composers/OperatorAvatarMenuComposer.php';

$adapter = operator_avatar_read($adapterPath);
$script = operator_avatar_read($scriptPath);
$surface = operator_avatar_read($surfacePath);
$markup = operator_avatar_read($markupPath);

operator_avatar_assert($adapter !== '', 'operator avatar adapter exists');
operator_avatar_assert($script !== '', 'operator interaction script exists');
operator_avatar_assert($surface !== '', 'operator surface composer exists');
operator_avatar_assert($markup !== '', 'operator avatar markup exists');

operator_avatar_assert(str_contains($adapter, 'createOperatorAvatarPanelAdapter'), 'adapter exposes avatar panel factory');
operator_avatar_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    operator_avatar_assert(!str_contains($adapter, $forbidden), "adapter does not reference internal framework service {$forbidden}");
}

operator_avatar_assert(str_contains($adapter, "overlayType = 'operator_avatar_panel'"), 'adapter maps avatar panel to stable overlay type');
operator_avatar_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies modal/backdrop/scroll-lock intent'
);
operator_avatar_assert(
    str_contains($surface, 'OperatorAvatarPanelAdapter::renderScript()')
        && str_contains($script, 'createOperatorAvatarPanelAdapter'),
    'operator surface renders adapter and interaction script creates it'
);
operator_avatar_assert(
    str_contains($script, 'operatorAvatarPanelAdapter.syncState(shouldOpen')
        && str_contains($script, 'setLegacyOpen: setAvatarPanelOpen')
        && str_contains($adapter, 'onClose: handleControllerClose')
        && str_contains($adapter, "scrollTargetSelector: config.scrollTargetSelector || '.main-content'")
        && !str_contains($script, "document.body.style.overflow = shouldOpen ? 'hidden' : ''"),
    'controller owns avatar dismissal/scroll lock while DOM rendering stays in callback'
);
operator_avatar_assert(
    str_contains($markup, 'id="headerAvatarPanel"') && str_contains($markup, 'id="avatarBackdrop"'),
    'legacy avatar panel/backdrop markup remains intact for rollback'
);

echo '[probe] Operator avatar panel adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
