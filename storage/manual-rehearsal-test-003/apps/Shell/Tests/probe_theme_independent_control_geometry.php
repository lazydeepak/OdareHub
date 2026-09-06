<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/StyleRegistryService.php';

use Apps\Shell\Services\StyleRegistryService;

$assertions = 0;
$failures = 0;

function control_geometry_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function control_geometry_read(string $relativePath): string
{
    $content = file_get_contents(APP_ROOT . '/' . $relativePath);
    return is_string($content) ? $content : '';
}

$foundation = control_geometry_read('apps/Shell/Resources/rendering/foundation.css');
$semanticTheme = control_geometry_read('resources/themes/semantic/semantic.css');
$shellTokens = control_geometry_read('apps/Shell/styles/shell-tokens.css');
$shellForms = control_geometry_read('apps/Shell/styles/shell-forms.css');

$foundationTokens = [
    '--render-control-height: 42px',
    '--render-control-font-size: 16px',
    '--render-control-line-height: 1.25',
    '--render-control-radius: 12px',
    '--render-control-padding-block: 10px',
    '--render-control-padding-inline: 12px',
    '--render-choice-size: 18px',
    '--render-checkbox-radius: 4px',
    '--render-radio-radius: 50%',
];
foreach ($foundationTokens as $token) {
    control_geometry_assert(str_contains($foundation, $token), "rendering Foundation owns {$token}");
}

$themeGeometryTokens = [
    '--control-height:',
    '--control-font-size:',
    '--control-line-height:',
    '--control-radius:',
    '--control-padding-block:',
    '--control-padding-inline:',
    '--control-padding-inline-select:',
    '--control-padding-inline-date:',
    '--control-gap:',
    '--control-gap-tight:',
    '--control-field-min:',
    '--control-field-wide-min:',
    '--control-field-compact-min:',
];
foreach ($themeGeometryTokens as $token) {
    control_geometry_assert(!str_contains($semanticTheme, $token), "composed theme does not own {$token}");
}
control_geometry_assert(
    str_contains($semanticTheme, '--control-select-arrow:'),
    'theme retains color-bearing select arrow artwork'
);

control_geometry_assert(
    str_contains($shellTokens, '--control-radius: var(--render-control-radius);')
        && str_contains($shellTokens, '--control-height: var(--render-control-height);'),
    'Shell compatibility aliases resolve control geometry from Foundation'
);
control_geometry_assert(
    str_contains($shellForms, 'input[type="checkbox"]{border-radius:var(--render-checkbox-radius)}')
        && str_contains($shellForms, 'input[type="radio"]{border-radius:var(--render-radio-radius)}'),
    'checkbox and radio shapes consume independent Foundation primitives'
);

$globals = StyleRegistryService::globals();
$orders = [];
$versions = [];
foreach ($globals as $style) {
    $orders[(string)($style['key'] ?? '')] = (int)($style['order'] ?? 0);
    $versions[(string)($style['key'] ?? '')] = (string)($style['version'] ?? '');
}
control_geometry_assert(
    isset($orders['global.rendering-foundation'], $orders['global.theme'])
        && $orders['global.rendering-foundation'] < $orders['global.theme'],
    'runtime loads rendering Foundation before the composed theme'
);
control_geometry_assert(
    isset($versions['global.rendering-foundation'])
        && $versions['global.rendering-foundation'] !== ''
        && $versions['global.rendering-foundation'] !== '1',
    'runtime Foundation link uses the published file modification version'
);

if ($failures > 0) {
    fwrite(STDERR, "[probe] theme-independent control geometry: {$failures} failure(s) / {$assertions} assertions\n");
    exit(1);
}

echo "[probe] theme-independent control geometry: {$assertions}/{$assertions} assertions passed\n";
