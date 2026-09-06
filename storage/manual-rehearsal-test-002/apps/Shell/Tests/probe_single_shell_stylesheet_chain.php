<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/StyleRegistryService.php';

use Apps\Shell\Services\StyleRegistryService;

$assertions = 0;
$failures = 0;

function shell_chain_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

$manifest = json_decode((string)file_get_contents(APP_ROOT . '/apps/Shell/manifest.json'), true);
$styles = is_array($manifest) ? (array)($manifest['styles'] ?? []) : [];
$aggregate = null;
foreach ($styles as $style) {
    if (is_array($style) && ($style['key'] ?? '') === 'shell.app') {
        $aggregate = $style;
        break;
    }
}

shell_chain_assert(is_array($aggregate), 'Shell aggregate remains declared for publishing and source tooling');
shell_chain_assert(($aggregate['runtime'] ?? null) === false, 'Shell aggregate is explicitly excluded from runtime');
shell_chain_assert(is_file(APP_ROOT . '/apps/Shell/styles/shell.css'), 'Shell source import map remains present');

$preview = StyleRegistryService::previewChain('admin');
$keys = array_map(static fn (array $entry): string => (string)($entry['key'] ?? ''), $preview);
$urls = array_map(static fn (array $entry): string => (string)($entry['url'] ?? ''), $preview);

shell_chain_assert(!in_array('shell.app', $keys, true), 'preview chain excludes the aggregate stylesheet');
shell_chain_assert(!in_array('/assets/apps/shell/styles/shell.css', $urls, true), 'preview chain has no aggregate URL');
shell_chain_assert(in_array('global.rendering-foundation', $keys, true), 'preview chain includes rendering Foundation');
shell_chain_assert(in_array('global.theme', $keys, true), 'preview chain includes composed theme');
shell_chain_assert(in_array('shell.tokens', $keys, true), 'preview chain includes explicit Shell tokens');
shell_chain_assert(in_array('shell.admin', $keys, true), 'admin preview includes its surface stylesheet');
shell_chain_assert(!in_array('shell.operator', $keys, true), 'admin preview excludes operator-only stylesheet');
shell_chain_assert(count($urls) === count(array_unique($urls)), 'preview chain contains no duplicate stylesheet URL');

foreach ($preview as $entry) {
    shell_chain_assert(
        (string)($entry['version'] ?? '') !== '' && (string)($entry['version'] ?? '') !== '1',
        'preview stylesheet has a resolved file version: ' . (string)($entry['key'] ?? 'unknown')
    );
}

$specialEffects = (string)file_get_contents(
    APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php'
);
$liveEditor = (string)file_get_contents(
    APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Services/CssLiveEditorPreviewFeedService.php'
);
shell_chain_assert(!str_contains($specialEffects, '/styles/shell.css'), 'Special Effects preview has no aggregate stylesheet link');
shell_chain_assert(!str_contains($liveEditor, '/styles/shell.css'), 'CSS Live Editor preview has no aggregate stylesheet link');

if ($failures > 0) {
    fwrite(STDERR, "[probe] single Shell stylesheet chain: {$failures} failure(s) / {$assertions} assertions\n");
    exit(1);
}

echo "[probe] single Shell stylesheet chain: {$assertions}/{$assertions} assertions passed\n";
