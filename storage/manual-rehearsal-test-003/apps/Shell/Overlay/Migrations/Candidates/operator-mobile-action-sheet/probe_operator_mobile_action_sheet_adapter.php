<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function operator_action_sheet_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function operator_action_sheet_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/OperatorSurface/OperatorMobileActionSheetAdapter.php';
$wrapperPath = APP_ROOT . '/apps/Shell/Services/OperatorLayerWrapperComposer.php';
$scriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$surfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';

$adapter = operator_action_sheet_read($adapterPath);
$wrapper = operator_action_sheet_read($wrapperPath);
$script = operator_action_sheet_read($scriptPath);
$surface = operator_action_sheet_read($surfacePath);

operator_action_sheet_assert($adapter !== '', 'operator action sheet adapter exists');
operator_action_sheet_assert($wrapper !== '', 'operator wrapper exists');
operator_action_sheet_assert($script !== '', 'operator interaction script exists');
operator_action_sheet_assert($surface !== '', 'operator surface composer exists');

operator_action_sheet_assert(str_contains($adapter, 'createOperatorMobileActionSheetAdapter'), 'adapter exposes mobile action sheet factory');
operator_action_sheet_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    operator_action_sheet_assert(!str_contains($adapter, $forbidden), "adapter does not reference internal framework service {$forbidden}");
}

operator_action_sheet_assert(str_contains($adapter, "overlayType = 'operator_mobile_action_sheet'"), 'adapter maps action sheet to stable overlay type');
operator_action_sheet_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'a11yRole:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies modal dialog/backdrop/scroll-lock intent'
);
operator_action_sheet_assert(
    str_contains($surface, 'OperatorMobileActionSheetAdapter::renderScript()')
        && str_contains($wrapper, 'createOperatorMobileActionSheetAdapter'),
    'operator surface renders adapter and wrapper creates it'
);
operator_action_sheet_assert(
    str_contains($wrapper, 'mobileActionSheetAdapter.syncState(isOpen')
        && str_contains($wrapper, "mobileActionSheetAdapter.syncState(false, 'close')")
        && str_contains($wrapper, 'setLegacyOpen: setPanelOpen')
        && str_contains($adapter, 'onClose: handleControllerClose')
        && !str_contains($wrapper, "document.body.style.overflow = 'hidden'")
        && !str_contains($wrapper, "document.addEventListener('keydown', function (e)"),
    'controller owns action sheet dismissal/scroll lock while DOM and cross-close stay local'
);
operator_action_sheet_assert(
    str_contains($script, 'operatorMobileActionSheetAdapter.syncState(false, \'peer-opened\')'),
    'hamburger/avatar peer-open paths close action sheet adapter state'
);

echo '[probe] Operator mobile action sheet adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
