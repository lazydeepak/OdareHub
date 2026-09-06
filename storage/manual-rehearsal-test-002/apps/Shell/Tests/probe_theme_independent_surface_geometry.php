<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$assertions = 0;
$failures = 0;

function surface_geometry_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function surface_geometry_read(string $relativePath): string
{
    $content = file_get_contents(APP_ROOT . '/' . $relativePath);
    return is_string($content) ? $content : '';
}

$foundation = surface_geometry_read('apps/Shell/Resources/rendering/foundation.css');
$shellTokens = surface_geometry_read('apps/Shell/styles/shell-tokens.css');
$semanticTheme = surface_geometry_read('resources/themes/semantic/semantic.css');
$adminCss = surface_geometry_read('apps/Shell/styles/shell-admin.css');

$foundationTokens = [
    '--render-space-1: 6px',
    '--render-space-5: 24px',
    '--render-radius-sm: 10px',
    '--render-radius-md: 14px',
    '--render-radius-lg: 18px',
    '--render-radius-panel: 20px',
    '--render-radius-pill: 999px',
    '--render-card-radius: var(--render-radius-md)',
    '--render-card-padding: 16px',
    '--render-icon-chip-size: 32px',
    '--render-icon-chip-radius: var(--render-radius-sm)',
];
foreach ($foundationTokens as $token) {
    surface_geometry_assert(str_contains($foundation, $token), "rendering Foundation owns {$token}");
}

$compatibilityAliases = [
    '--space-1: var(--render-space-1)',
    '--space-5: var(--render-space-5)',
    '--radius-sm: var(--render-radius-sm)',
    '--radius-md: var(--render-radius-md)',
    '--radius-lg: var(--render-radius-lg)',
    '--radius-panel: var(--render-radius-panel)',
    '--radius-pill: var(--render-radius-pill)',
    '--radius: var(--render-radius-md)',
    '--card-radius: var(--render-card-radius)',
    '--icon-chip-size: var(--render-icon-chip-size)',
    '--icon-chip-radius: var(--render-icon-chip-radius)',
    '--safe-area-bottom: var(--sys-safe-area-bottom, 0px)',
];
foreach ($compatibilityAliases as $alias) {
    surface_geometry_assert(str_contains($shellTokens, $alias), "Shell resolves {$alias}");
}

$appearanceAliases = [
    '--border: var(--style-border-soft)',
    '--surface: var(--style-content-bg)',
    '--surface-2: var(--style-subtle-bg)',
    '--surface-3: var(--style-subtle-bg-hover)',
];
foreach ($appearanceAliases as $alias) {
    surface_geometry_assert(str_contains($shellTokens, $alias), "Shell defines required admin appearance alias {$alias}");
}

foreach (['--card-radius:', '--icon-chip-size:', '--icon-chip-radius:'] as $token) {
    surface_geometry_assert(!str_contains($semanticTheme, $token), "composed semantic theme does not own {$token}");
}

foreach (['var(--radius)', 'var(--border)', 'var(--surface)', 'var(--surface-2)', 'var(--surface-3)'] as $usage) {
    surface_geometry_assert(str_contains($adminCss, $usage), "probe covers live admin dependency {$usage}");
}

if ($failures > 0) {
    fwrite(STDERR, "[probe] theme-independent surface geometry: {$failures} failure(s) / {$assertions} assertions\n");
    exit(1);
}

echo "[probe] theme-independent surface geometry: {$assertions}/{$assertions} assertions passed\n";
