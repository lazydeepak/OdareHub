<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function public_admin_action_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function public_admin_action_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicAdminActionDropdownAdapter.php';
$headerPath = APP_ROOT . '/public/views/layouts/header.php';

$adapter = public_admin_action_read($adapterPath);
$header = public_admin_action_read($headerPath);

public_admin_action_assert($adapter !== '', 'public admin action dropdown adapter exists');
public_admin_action_assert($header !== '', 'public header exists');

public_admin_action_assert(
    str_contains($adapter, 'createPublicAdminActionDropdownAdapter'),
    'adapter exposes public admin action factory'
);

public_admin_action_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    public_admin_action_assert(
        !str_contains($adapter, $forbidden),
        "adapter does not reference internal framework service {$forbidden}"
    );
}

public_admin_action_assert(
    str_contains($adapter, "overlayType = 'public_admin_action_dropdown'"),
    'adapter maps legacy dropdown to stable overlay type'
);

public_admin_action_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies non-modal/no-backdrop/no-scroll-lock behavior'
);

public_admin_action_assert(
    str_contains($adapter, 'setLegacyOpen'),
    'adapter preserves legacy rendering callback for rollback'
);

public_admin_action_assert(
    str_contains($header, 'PublicAdminActionDropdownAdapter.php')
        && str_contains($header, 'PublicAdminActionDropdownAdapter::renderScript()')
        && str_contains($header, 'createPublicAdminActionDropdownAdapter'),
    'public header loads the admin action adapter script'
);

public_admin_action_assert(
    str_contains($adapter, 'onOpen: function () { legacySetOpen(true); }')
        && str_contains($adapter, 'onClose: function () { legacySetOpen(false); }')
        && str_contains($header, 'function requestActionOpen(open, reason)')
        && str_contains($header, 'requestActionOpen(nextState, \'trigger\')')
        && str_contains($header, 'requestActionOpen(false, \'close-button\')')
        && !str_contains($header, "topbarActionDropdown && !event.target.closest('.topbar-action-wrap')")
        && !str_contains($header, "requestActionOpen(false, 'escape')"),
    'controller owns admin-action Escape/outside routing while trigger/close use adapter boundary'
);

public_admin_action_assert(
    str_contains($header, 'function setActionOpen(open)')
        && str_contains($header, 'setLegacyOpen: setActionOpen'),
    'legacy setActionOpen remains as rollback-compatible rendering path'
);

public_admin_action_assert(
    str_contains($header, 'topbarActionDropdown.hidden = !open')
        && str_contains($header, "topbarActionDropdown.classList.toggle('open', open)")
        && str_contains($header, "topbarActionBtn.setAttribute('aria-expanded', open ? 'true' : 'false')"),
    'legacy admin action DOM behavior remains intact behind adapter'
);

echo '[probe] Public admin action dropdown adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
