<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function public_notifications_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function public_notifications_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicNotificationsDropdownAdapter.php';
$headerPath = APP_ROOT . '/public/views/layouts/header.php';

$adapter = public_notifications_read($adapterPath);
$header = public_notifications_read($headerPath);

public_notifications_assert($adapter !== '', 'public notifications dropdown adapter exists');
public_notifications_assert($header !== '', 'public header exists');

public_notifications_assert(
    str_contains($adapter, 'createPublicNotificationsDropdownAdapter'),
    'adapter exposes public notifications factory'
);

public_notifications_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    public_notifications_assert(
        !str_contains($adapter, $forbidden),
        "adapter does not reference internal framework service {$forbidden}"
    );
}

public_notifications_assert(
    str_contains($adapter, "overlayType = 'public_notifications_dropdown'"),
    'adapter maps legacy dropdown to stable overlay type'
);

public_notifications_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies non-modal/no-backdrop/no-scroll-lock behavior'
);

public_notifications_assert(
    str_contains($adapter, 'setLegacyOpen'),
    'adapter preserves legacy rendering callback for rollback'
);

public_notifications_assert(
    str_contains($header, 'PublicNotificationsDropdownAdapter.php')
        && str_contains($header, 'PublicNotificationsDropdownAdapter::renderScript()')
        && str_contains($header, 'createPublicNotificationsDropdownAdapter'),
    'public header loads the notifications adapter script'
);

public_notifications_assert(
    str_contains($adapter, 'onOpen: function () { legacySetOpen(true); }')
        && str_contains($adapter, 'onClose: function () { legacySetOpen(false); }')
        && str_contains($header, 'function requestNotifOpen(open, reason)')
        && str_contains($header, 'requestNotifOpen(nextState, \'trigger\')')
        && !str_contains($header, "notifDropdown && !event.target.closest('.notif-bell-wrap')")
        && !str_contains($header, "requestNotifOpen(false, 'escape')"),
    'controller owns notifications Escape/outside routing while trigger uses adapter boundary'
);

public_notifications_assert(
    str_contains($header, 'function setNotifOpen(open)')
        && str_contains($header, 'setLegacyOpen: setNotifOpen'),
    'legacy setNotifOpen remains as rollback-compatible rendering path'
);

public_notifications_assert(
    str_contains($header, 'notifDropdown.hidden = !open')
        && str_contains($header, "notifDropdown.classList.toggle('open', open)")
        && str_contains($header, "notifButton.setAttribute('aria-expanded', open ? 'true' : 'false')"),
    'legacy notification DOM behavior remains intact behind adapter'
);

echo '[probe] Public notifications dropdown adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
