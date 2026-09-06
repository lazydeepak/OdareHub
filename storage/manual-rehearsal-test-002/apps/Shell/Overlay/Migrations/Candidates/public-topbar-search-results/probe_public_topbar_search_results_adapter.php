<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function public_topbar_search_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function public_topbar_search_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/PublicSurface/PublicTopbarSearchResultsAdapter.php';
$headerPath = APP_ROOT . '/public/views/layouts/header.php';

$adapter = public_topbar_search_read($adapterPath);
$header = public_topbar_search_read($headerPath);

public_topbar_search_assert($adapter !== '', 'public topbar search results adapter exists');
public_topbar_search_assert($header !== '', 'public header exists');

public_topbar_search_assert(
    str_contains($adapter, 'createPublicTopbarSearchResultsAdapter'),
    'adapter exposes public topbar search factory'
);

public_topbar_search_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    public_topbar_search_assert(
        !str_contains($adapter, $forbidden),
        "adapter does not reference internal framework service {$forbidden}"
    );
}

public_topbar_search_assert(
    str_contains($adapter, "overlayType = 'public_topbar_search_results'"),
    'adapter maps legacy search results to stable overlay type'
);

public_topbar_search_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'a11yRole:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies non-modal/listbox/no-backdrop/no-scroll-lock behavior'
);

public_topbar_search_assert(
    str_contains($adapter, 'syncState')
        && str_contains($adapter, "normalized === 'results' || normalized === 'empty'")
        && str_contains($adapter, 'return close(reason || normalized'),
    'adapter translates search states into framework open/close intents'
);

public_topbar_search_assert(
    str_contains($header, 'PublicTopbarSearchResultsAdapter.php')
        && str_contains($header, 'PublicTopbarSearchResultsAdapter::renderScript()')
        && str_contains($header, 'createPublicTopbarSearchResultsAdapter'),
    'public header loads the topbar search adapter script'
);

public_topbar_search_assert(
    str_contains($header, 'function setSearchState(state)')
        && str_contains($header, 'topbarSearchResultsAdapter.syncState(state, \'search-state\')')
        && str_contains($adapter, 'escape: false')
        && str_contains($adapter, 'restoreFocus: false')
        && str_contains($header, "onControllerClose: function() { setSearchState('idle'); }")
        && !str_contains($header, "document.addEventListener('mousedown', function(event)"),
    'controller owns outside dismissal while content-specific Escape/search state remains local'
);

public_topbar_search_assert(
    str_contains($header, 'function renderTopbarSearchResults(items, query)')
        && str_contains($header, 'function fetchTopbarSearchResults(query)')
        && str_contains($header, 'function moveActiveSearchResult(delta)')
        && str_contains($header, 'topbarSearchInput.setAttribute(\'aria-activedescendant\''),
    'legacy search rendering, async fetch, keyboard navigation, and active descendant remain intact'
);

public_topbar_search_assert(
    str_contains($header, "setSearchState('idle')")
        && str_contains($header, "setSearchState('empty')")
        && str_contains($header, "setSearchState('results')"),
    'legacy search states remain explicit and rollback-compatible'
);

echo '[probe] Public topbar search results adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
