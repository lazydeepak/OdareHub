<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function public_account_overflow_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function public_account_overflow_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicAccountOverflowAdapter.php';
$headerPath = APP_ROOT . '/public/views/layouts/header.php';

$adapter = public_account_overflow_read($adapterPath);
$header = public_account_overflow_read($headerPath);

public_account_overflow_assert($adapter !== '', 'public account overflow adapter exists');
public_account_overflow_assert($header !== '', 'public header exists');

public_account_overflow_assert(
    str_contains($adapter, 'createPublicAccountOverflowAdapter'),
    'adapter exposes public account overflow factory'
);

public_account_overflow_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    public_account_overflow_assert(
        !str_contains($adapter, $forbidden),
        "adapter does not reference internal framework service {$forbidden}"
    );
}

public_account_overflow_assert(
    str_contains($adapter, "overlayType = 'public_account_overflow'"),
    'adapter maps legacy dropdown to stable overlay type'
);

public_account_overflow_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies non-modal/no-backdrop/no-scroll-lock behavior'
);

public_account_overflow_assert(
    str_contains($adapter, 'setLegacyOpen'),
    'adapter preserves legacy rendering callback for rollback'
);

public_account_overflow_assert(
    str_contains($header, 'PublicAccountOverflowAdapter.php')
        && str_contains($header, 'renderScript()')
        && str_contains($header, 'createPublicAccountOverflowAdapter'),
    'public header loads the adapter script'
);

public_account_overflow_assert(
    str_contains($adapter, 'onOpen: function () { legacySetOpen(true); }')
        && str_contains($adapter, 'onClose: function () { legacySetOpen(false); }')
        && str_contains($header, 'function requestOverflowOpen(open, reason)')
        && str_contains($header, 'requestOverflowOpen(nextState, \'trigger\')')
        && !str_contains($header, "topbarOverflowDropdown && !event.target.closest('.topbar-overflow-wrap')")
        && !str_contains($header, "requestOverflowOpen(false, 'escape')"),
    'controller owns account overflow Escape/outside routing while trigger uses adapter boundary'
);

public_account_overflow_assert(
    str_contains($header, 'function setOverflowOpen(open)')
        && str_contains($header, 'setLegacyOpen: setOverflowOpen'),
    'legacy setOverflowOpen remains as rollback-compatible rendering path'
);

public_account_overflow_assert(
    str_contains($header, 'topbarOverflowDropdown.hidden = !open')
        && str_contains($header, "topbarOverflowDropdown.classList.toggle('open', open)")
        && str_contains($header, "topbarOverflowBtn.setAttribute('aria-expanded', open ? 'true' : 'false')"),
    'legacy DOM behavior remains intact behind adapter'
);

echo '[probe] Public account overflow adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
