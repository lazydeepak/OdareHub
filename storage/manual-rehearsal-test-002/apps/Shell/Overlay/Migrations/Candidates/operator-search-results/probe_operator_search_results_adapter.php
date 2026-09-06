<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 6));

$assertions = 0;

function operator_search_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function operator_search_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$adapterPath = APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/OperatorSurface/OperatorSearchResultsAdapter.php';
$scriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$surfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';

$adapter = operator_search_read($adapterPath);
$script = operator_search_read($scriptPath);
$surface = operator_search_read($surfacePath);

operator_search_assert($adapter !== '', 'operator search results adapter exists');
operator_search_assert($script !== '', 'operator interaction script exists');
operator_search_assert($surface !== '', 'operator surface composer exists');

operator_search_assert(
    str_contains($adapter, 'createOperatorSearchResultsAdapter'),
    'adapter exposes operator search factory'
);

operator_search_assert(
    str_contains($adapter, 'fw.manager.open') && str_contains($adapter, 'fw.manager.close(instance.id'),
    'adapter interacts with framework through Registry and Manager only'
);

foreach (['fw.registry', 'fw.portal', 'SessionStore', 'OverlayStack', 'OverlayFocus', 'OverlayBackdrop', 'OverlayDismissal', 'OverlayScrollLock'] as $forbidden) {
    operator_search_assert(
        !str_contains($adapter, $forbidden),
        "adapter does not reference internal framework service {$forbidden}"
    );
}

operator_search_assert(
    str_contains($adapter, "overlayType = 'operator_search_results'"),
    'adapter maps legacy operator search to stable overlay type'
);

operator_search_assert(
    !str_contains($adapter, 'defaultIsModal:')
        && !str_contains($adapter, 'a11yRole:')
        && !str_contains($adapter, 'backdropDefaults:')
        && !str_contains($adapter, 'scrollLockDefaults:'),
    'adapter metadata certifies non-modal/listbox/no-backdrop/no-scroll-lock behavior'
);

operator_search_assert(
    str_contains($adapter, 'syncState')
        && str_contains($adapter, "normalized === 'results' || normalized === 'empty'")
        && str_contains($adapter, 'return close(reason || normalized'),
    'adapter translates operator search states into framework open/close intents'
);

operator_search_assert(
    str_contains($surface, 'ShellOverlayFramework::renderInfrastructureScript()')
        && str_contains($surface, 'OperatorSearchResultsAdapter::renderScript()'),
    'operator surface renders overlay infrastructure and operator search adapter before interaction script'
);

operator_search_assert(
    str_contains($script, 'createOperatorSearchResultsAdapter')
        && str_contains($adapter, 'escape: false')
        && str_contains($adapter, 'restoreFocus: false')
        && str_contains($script, 'onControllerClose: function () { hideOperatorSearchResults(); }')
        && str_contains($script, "operatorSearchResultsAdapter.syncState('hidden', 'hide')")
        && str_contains($script, "operatorSearchResultsAdapter.syncState('empty', 'empty')")
        && str_contains($script, "operatorSearchResultsAdapter.syncState('results', 'results')"),
    'operator interaction script routes hide/empty/results states through adapter boundary'
);

operator_search_assert(
    !str_contains($script, 'function buildOperatorLiveSearchCandidates()')
        && !str_contains($script, 'function liberalSearchScore(')
        && str_contains($script, 'function renderOperatorSearchResults(rawQuery)')
        && str_contains($script, "'/api/search?q='")
        && str_contains($script, 'function openOperatorSearchCandidate(candidate)')
        && str_contains($script, 'function syncOperatorSearchActiveRow()'),
    'operator search uses the canonical server endpoint while local rendering, activation, and keyboard navigation remain intact'
);

operator_search_assert(
    str_contains($script, "headerRouteSearch.setAttribute('aria-activedescendant'")
        && str_contains($script, "operatorSearchResults.addEventListener('click'")
        && str_contains($script, "if (e.key === 'Escape')")
        && !str_contains($script, "if (!operatorSearchResults.contains(e.target) && !headerRouteSearch.contains(e.target))"),
    'active descendant, click activation, and content-specific Escape remain local while outside dismissal is centralized'
);

echo '[probe] Operator search results adapter: ' . $assertions . '/' . $assertions . " assertions passed\n";
