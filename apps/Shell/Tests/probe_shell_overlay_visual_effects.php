<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$assertions = 0;

function visual_effects_assert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "ASSERTION FAILED: {$message}\n");
        exit(1);
    }
}

function visual_effects_read(string $path): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    return is_string($content) ? $content : '';
}

$frameworkPath = APP_ROOT . '/apps/Shell/Services/ShellOverlayFramework.php';
$layoutCssPath = APP_ROOT . '/apps/Shell/styles/shell-layout.css';
$navigationCssPath = APP_ROOT . '/apps/Shell/styles/shell-navigation.css';
$publicHeaderPath = APP_ROOT . '/public/views/layouts/header.php';
$operatorSurfacePath = APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php';
$operatorScriptPath = APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php';
$operatorAvatarPath = APP_ROOT . '/apps/Shell/Composers/OperatorAvatarMenuComposer.php';

$framework = visual_effects_read($frameworkPath);
$layoutCss = visual_effects_read($layoutCssPath);
$navigationCss = visual_effects_read($navigationCssPath);
$publicHeader = visual_effects_read($publicHeaderPath);
$operatorSurface = visual_effects_read($operatorSurfacePath);
$operatorScript = visual_effects_read($operatorScriptPath);
$operatorAvatar = visual_effects_read($operatorAvatarPath);

visual_effects_assert($framework !== '', 'ShellOverlayFramework service exists');
visual_effects_assert($layoutCss !== '', 'Shell layout CSS exists');
visual_effects_assert($navigationCss !== '', 'Shell navigation CSS exists');
visual_effects_assert($publicHeader !== '', 'public header exists');
visual_effects_assert($operatorSurface !== '', 'operator surface composer exists');
visual_effects_assert($operatorScript !== '', 'operator interaction script exists');
visual_effects_assert($operatorAvatar !== '', 'operator avatar composer exists');

visual_effects_assert(
    str_contains($framework, 'function currentVisual()')
        && str_contains($framework, 'function applyVisual()')
        && str_contains($framework, 'visualEffects: {'),
    'single ShellOverlay controller owns visual state and API'
);

visual_effects_assert(
    str_contains($framework, 'setStrength(value)')
        && str_contains($framework, 'clampStrength')
        && str_contains($framework, 'Math.max(0, Math.min(100'),
    'central visual controller exposes numeric 0-100 strength control'
);

visual_effects_assert(
    str_contains($framework, 'strength: 40'),
    'central visual controller default strength is 40'
);

visual_effects_assert(
    str_contains($framework, 'const VISUAL_PRESETS = Object.freeze')
        && str_contains($framework, 'function visualPolicy(payload)'),
    'single controller owns named visual policy resolution'
);

visual_effects_assert(
    str_contains($framework, "root.dataset.shellOverlayEffect")
        && str_contains($framework, "root.dataset.shellOverlayScope")
        && str_contains($framework, "root.dataset.shellOverlayStrength")
        && str_contains($framework, "body.classList.add('has-shell-overlay-visual')"),
    'central visual controller writes the single visual state surface'
);

visual_effects_assert(
    str_contains($framework, "--shell-overlay-blur-radius")
        && str_contains($framework, "--shell-overlay-dim-opacity")
        && str_contains($framework, "--shell-overlay-saturate"),
    'central visual controller writes strength-derived CSS variables'
);

visual_effects_assert(
    str_contains($framework, 'payload: inst.payload')
        && str_contains($framework, "window.dispatchEvent(new CustomEvent('shell-overlay:opened'")
        && str_contains($framework, "window.dispatchEvent(new CustomEvent('shell-overlay:closed'"),
    'framework events expose payload for independent visual policy consumers'
);

 visual_effects_assert(
     str_contains($layoutCss, 'html[data-shell-overlay-open="true"][data-shell-overlay-effect="blur-dim"]')
        && str_contains($layoutCss, 'body.has-shell-overlay-visual .shell-inactive-layer')
        && str_contains($layoutCss, 'var(--shell-overlay-blur-radius)'),
     'central CSS renders visual effect only from explicit open state and controller variables'
 );

visual_effects_assert(
    !str_contains($layoutCss, '--shell-overlay-blur-radius: min(3px, var(--shell-overlay-blur-radius))'),
    'central CSS does not self-reference the blur variable for local scope'
);

visual_effects_assert(
    str_contains($layoutCss, '.shell-overlay-strength-control')
        && str_contains($layoutCss, '.shell-overlay-strength-slider')
        && str_contains($layoutCss, '.shell-overlay-strength-value')
        && str_contains($navigationCss, '.avatar-pref-row--range'),
    'central slider styling exists for public and operator preference surfaces'
);

visual_effects_assert(
    !str_contains($layoutCss, 'has-active-overlay'),
    'legacy has-active-overlay path is removed'
);

visual_effects_assert(
    !str_contains($publicHeader, 'ShellOverlayVisualEffects')
        && str_contains($publicHeader, "window['OdareHubOS.ShellOverlay']"),
    'public surface uses the single ShellOverlay controller'
);

visual_effects_assert(
    str_contains($publicHeader, '<main class="container shell-inactive-layer">'),
    'public/admin page content participates in the central affected-layer contract'
);

visual_effects_assert(
    str_contains($publicHeader, "OVERLAY_EFFECT_STRENGTH_STORAGE_KEY = 'shell-overlay-effect-strength'")
        && str_contains($publicHeader, 'DEFAULT_OVERLAY_EFFECT_STRENGTH = 40')
        && str_contains($publicHeader, 'function initOverlayEffectStrengthControls()')
        && str_contains($publicHeader, "controller.setStrength(strength)")
        && str_contains($publicHeader, 'data-overlay-effect-strength')
        && str_contains($publicHeader, 'shellOverlayEffectStrengthTopbar'),
    'public surface exposes persisted central 0-100 overlay strength slider'
);

visual_effects_assert(
    !str_contains($operatorSurface, 'ShellOverlayVisualEffects')
        && str_contains($operatorScript, "window['OdareHubOS.ShellOverlay']"),
    'operator surface uses the single ShellOverlay controller'
);

visual_effects_assert(
    str_contains($operatorScript, "OVERLAY_EFFECT_STRENGTH_STORAGE_KEY = 'shell-overlay-effect-strength'")
        && str_contains($operatorScript, 'DEFAULT_OVERLAY_EFFECT_STRENGTH = 40')
        && str_contains($operatorScript, 'function initOverlayEffectStrengthControls()')
        && str_contains($operatorScript, "controller.setStrength(strength)")
        && str_contains($operatorScript, 'initOverlayEffectStrengthControls();')
        && str_contains($operatorAvatar, 'data-overlay-effect-strength')
        && str_contains($operatorAvatar, 'operatorOverlayEffectStrength'),
    'operator surface exposes persisted central 0-100 overlay strength slider'
);

$adapterPaths = glob(APP_ROOT . '/apps/Shell/Overlay/Compatibility/Adapters/*/*.php') ?: [];
visual_effects_assert(count($adapterPaths) >= 10, 'adapter files discovered');

foreach ($adapterPaths as $adapterPath) {
    $adapter = visual_effects_read($adapterPath);
    $label = str_replace(APP_ROOT . '/', '', $adapterPath);
    visual_effects_assert(str_contains($adapter, 'preset:'), "{$label} selects a Shell-owned definition");
    visual_effects_assert(!str_contains($adapter, 'visualDefaults'), "{$label} does not duplicate definition metadata");
    visual_effects_assert(str_contains($adapter, 'preset:'), "{$label} selects one Shell-owned overlay definition");
    visual_effects_assert(!str_contains($adapter, 'visualEffect:'), "{$label} does not duplicate visual effect literals");
    visual_effects_assert(!str_contains($adapter, 'visualScope:'), "{$label} does not duplicate visual scope literals");
    visual_effects_assert(!str_contains($adapter, 'visualStrength:'), "{$label} does not duplicate visual strength literals");
    visual_effects_assert(!str_contains($adapter, 'shellOverlayEffect'), "{$label} does not write visual data attributes");
    visual_effects_assert(!str_contains($adapter, '--shell-overlay-blur-radius'), "{$label} does not write central CSS variables");
    visual_effects_assert(!str_contains($adapter, 'has-shell-overlay-visual'), "{$label} does not toggle central visual class");
}

echo '[probe] Shell overlay visual effects: ' . $assertions . '/' . $assertions . " assertions passed\n";
