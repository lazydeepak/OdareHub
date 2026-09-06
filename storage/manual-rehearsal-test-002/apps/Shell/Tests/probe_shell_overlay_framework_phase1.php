<?php
declare(strict_types=1);

/**
 * Shell overlay framework Phase 1 probe.
 *
 * Goal: verify infrastructure-only primitives exist and do not require
 * migrations of existing overlays.
 */

if (!defined('APP_ROOT')) {
    // Resolve repo root from this file location:
    // apps/Shell/Tests/probe_shell_overlay_framework_phase1.php -> go up 3 levels
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

// 1) Host exists once
$footerPath = APP_ROOT . '/public/views/layouts/footer.php';
$footerLayout = readFileSafe($footerPath);
assertProbe($footerLayout !== '', 'Probe can read footer.php');

preg_match_all('/\bid=["\']shellOverlay["\']/', $footerLayout, $matchesShellOverlayId);
$hostCount = count($matchesShellOverlayId[0] ?? []);
assertProbe($hostCount >= 1, 'At least one id="shellOverlay" exists in footer markup');
assertProbe($hostCount === 1, 'Invariant: exactly one id="shellOverlay" in footer markup');


// Ensure legacy/non-v1 selectors aren't required in Phase 1.
assertProbe(
    str_contains($footerLayout, 'class="shell-overlay"') || str_contains($footerLayout, "class='shell-overlay'"),
    'Footer contains .shell-overlay container class'
);


// 2) Infrastructure script registers the expected global namespace name
$frameworkPath = APP_ROOT . '/apps/Shell/Services/ShellOverlayFramework.php';
$frameworkPhp = readFileSafe($frameworkPath);
assertProbe($frameworkPhp !== '', 'Probe can read ShellOverlayFramework.php (services)');


assertProbe(
    str_contains($frameworkPhp, "const NS = 'SusankhyaOS.ShellOverlay'") || str_contains($frameworkPhp, 'SusankhyaOS.ShellOverlay'),
    'ShellOverlayFramework defines JS namespace SusankhyaOS.ShellOverlay'
);


// 3) Infrastructure API exposes one contract + manager without a duplicate policy registry.
assertProbe(
    str_contains($frameworkPhp, 'window[NS] = {')
        && str_contains($frameworkPhp, 'contract')
        && str_contains($frameworkPhp, 'manager')
        && !str_contains($frameworkPhp, 'registry:'),
    'ShellOverlayFramework exports one contract/manager without duplicate registry state'
);

assertProbe(
    !str_contains($frameworkPhp, 'closeInstance(inst)') && str_contains($frameworkPhp, 'close(inst, reason)'),
    'ShellOverlayFramework exposes one stable-ID close path without compatibility dispatch'
);

assertProbe(
    str_contains($frameworkPhp, 'toggle(payload)')
        && str_contains($frameworkPhp, 'closeTop(reason, eligibility)')
        && str_contains($frameworkPhp, 'isOpen(id)'),
    'ShellOverlayFramework exposes the minimal dynamic controller API'
);

assertProbe(
    str_contains($frameworkPhp, 'const VISUAL_PRESETS = Object.freeze')
        && str_contains($frameworkPhp, 'const DEFINITIONS = Object.freeze')
        && str_contains($frameworkPhp, 'dropdown: { backdrop: false')
        && str_contains($frameworkPhp, 'drawer: { backdrop: true')
        && str_contains($frameworkPhp, "page: { effect: 'blur-dim', scope: 'page', strength: 40 }")
        && str_contains($frameworkPhp, "viewport: { effect: 'blur-dim', scope: 'viewport', strength: 40 }"),
    'ShellOverlayFramework owns overlay definitions and visual presets'
);

assertProbe(
    str_contains($frameworkPhp, "root.dataset.shellOverlayOpen = 'true'")
        && str_contains($frameworkPhp, 'delete root.dataset.shellOverlayOpen;'),
    'ShellOverlayFramework publishes and clears the explicit overlay open-state contract'
);

$layoutCss = readFileSafe(APP_ROOT . '/apps/Shell/styles/shell-layout.css');
$surfacesCss = readFileSafe(APP_ROOT . '/apps/Shell/styles/shell-surfaces.css');
assertProbe(
    str_contains($layoutCss, 'html[data-shell-overlay-open="true"][data-shell-overlay-effect="blur-dim"]')
        && !str_contains($layoutCss, 'html[data-shell-overlay-effect="blur-dim"] body.has-shell-overlay-visual'),
    'Blur and dim effects require the explicit overlay open state'
);
assertProbe(
    str_contains($surfacesCss, '.shell-overlay:empty{display:none}'),
    'Empty overlay host fails safe as display none'
);


// 4) Contract document exists
$contractPath = APP_ROOT . '/apps/Shell/DesignSystem/Contracts/shell-overlay-contract.md';
$contractDoc = readFileSafe($contractPath);
assertProbe($contractDoc !== '', 'Probe can read shell-overlay-contract.md');


assertProbe($contractDoc !== '', 'OverlayContract.md exists');
assertProbe(str_contains($contractDoc, '# Shell Overlay Contract') && str_contains($contractDoc, 'Controller ownership'), 'OverlayContract.md contains current minimal-controller contract');


// 5) Ownership boundaries doc exists
$ownershipPath = APP_ROOT . '/apps/Shell/DesignSystem/Contracts/shell-overlay-ownership-boundaries.md';
$ownershipDoc = readFileSafe($ownershipPath);
assertProbe($ownershipDoc !== '', 'Probe can read shell-overlay-ownership-boundaries.md');


assertProbe($ownershipDoc !== '', 'ownership-boundaries.md exists');
assertProbe(str_contains($ownershipDoc, 'Forbidden in Phase 1') || str_contains($ownershipDoc, 'Forbidden') || str_contains($ownershipDoc, 'No migrations') || str_contains($ownershipDoc, 'No behavior changes'), 'ownership-boundaries.md includes forbidden rules');


// 6) Minimal controller owns generic Escape and reference-counted scroll lock.
assertProbe(
    str_contains($frameworkPhp, "document.addEventListener('keydown'")
        && str_contains($frameworkPhp, "manager.closeTop('escape'")
        && str_contains($frameworkPhp, 'function acquireScrollLock(payload)')
        && str_contains($frameworkPhp, 'function releaseScrollLock(payload)'),
    'ShellOverlay controller owns generic Escape routing and minimal reference-counted scroll lock'
);


// Summary

echo "---\n";
echo "Shell overlay framework Phase 1 probe: Checks={$assertions} Failed={$failed}\n";
exit($failed > 0 ? 1 : 0);
