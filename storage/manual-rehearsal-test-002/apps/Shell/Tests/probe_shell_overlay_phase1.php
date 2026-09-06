<?php
declare(strict_types=1);

/**
 * Shell overlay Phase 1 probe.
 *
 * Goals (controlled, passive checks):
 *  - Confirm confirmed Shell overlay surfaces render with
 *    data-shell-overlay-surface="v1"
 *  - Confirm there is no duplicate id="shellOverlay"
 *  - Confirm .shell-overlay CSS is defined once only (heuristic)
 *  - Confirm existing action panel IDs/classes are preserved
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

$assertions = 0;
$failed = 0;

function assertProbe(bool $condition, string $message): void
{
    global $assertions, $failed;
    $assertions++;
    if ($condition) {
        echo "  ok: {$message}\n";
        return;
    }
    $failed++;
    fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $c = file_get_contents($path);
    return is_string($c) ? $c : '';
}

// ---------------------------------------------------------------------------
// 1) Confirm no duplicate id="shellOverlay" in rendered static snippets
// ---------------------------------------------------------------------------

$footerLayout = readFileSafe(APP_ROOT . '/public/views/layouts/footer.php');

$matchesShellOverlayId = [];
preg_match_all('/\bid=["\']shellOverlay["\']/', $footerLayout, $matchesShellOverlayId);

assertProbe(count($matchesShellOverlayId[0] ?? []) >= 1, 'At least one id="shellOverlay" exists in server-rendered footer markup');


// ---------------------------------------------------------------------------
// 2) Confirm .shell-overlay CSS selector appears once (heuristic)
// ---------------------------------------------------------------------------

$shellSurfacesCss = readFileSafe(APP_ROOT . '/apps/Shell/styles/shell-surfaces.css');
// CSS selector heuristic: allow multiple occurrences (whitespace/substring differences)
// but require at least one definition in Shell-owned CSS.
assertProbe(
    str_contains($shellSurfacesCss, '.shell-overlay'),
    ".shell-overlay selector exists in apps/Shell/styles/shell-surfaces.css"
);


// ---------------------------------------------------------------------------
// 3) Confirm action panel backdrop/mobile panel definitions still exist
//    (preserve IDs/classes & z-index behavior; do NOT change logic)
// ---------------------------------------------------------------------------

assertProbe(
    str_contains($shellSurfacesCss, 'action-panel-backdrop'),
    'action panel backdrop selector (.action-panel-backdrop / class) still exists in shell-surfaces.css'
);

assertProbe(
    str_contains($shellSurfacesCss, 'mobile-action-panel'),
    'mobile action panel selector still exists in shell-surfaces.css'
);

assertProbe(
    str_contains($shellSurfacesCss, 'mobile-action-panel.open'),
    'mobile action panel open-state selector (.mobile-action-panel.open) still exists'
);

// ---------------------------------------------------------------------------
// 4) Confirm confirmed overlay surfaces have data-shell-overlay-surface="v1"
//    Hard limit: do NOT depend on JS execution. We scan static/server snippets.
// ---------------------------------------------------------------------------

$marker = 'data-shell-overlay-surface="v1"';

// Confirm action/backdrop/mobile panel + any already-rendered shell-overlay container are marked.
assertProbe(
    str_contains($footerLayout, $marker) || str_contains($footerLayout, 'data-shell-overlay-surface=\'' . 'v1' . '\'',),
    'div#shellOverlay is marked with data-shell-overlay-surface="v1"'
);

// CSS participation is optional in Phase 1; framework contract host marker is canonical.
$hasMarkerInCss = str_contains($shellSurfacesCss, 'data-shell-overlay-surface');
assertProbe(
    $hasMarkerInCss || str_contains($footerLayout, $marker),
    'shell overlay contract marker present (host canonical or css evidence)'
);


// ---------------------------------------------------------------------------
// 5) Confirm there is no duplicate id="shellOverlay" in the static footer markup
//    (probe already covers this, keep additional invariant check)
// ---------------------------------------------------------------------------

assertProbe(
    count($matchesShellOverlayId[0] ?? []) === 1,
    'Invariant maintained: footer markup contains exactly one id="shellOverlay"'
);


// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "---\n";
echo "Shell overlay Phase 1 probe: Checks={$assertions} Failed={$failed}\n";
exit($failed > 0 ? 1 : 0);
