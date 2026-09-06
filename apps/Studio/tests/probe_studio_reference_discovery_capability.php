<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__, 3);
$fixtureRoot = sys_get_temp_dir() . '/studio-reference-discovery-' . bin2hex(random_bytes(4));
mkdir($fixtureRoot, 0777, true);
define('APP_ROOT', $fixtureRoot);

require_once $repoRoot . '/apps/Studio/Services/StudioReferenceDiscoveryService.php';
require_once $repoRoot . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureReferenceDiscoveryService.php';

use Apps\Studio\Services\StudioReferenceDiscoveryService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureReferenceDiscoveryService;

$passed = 0;
$failed = 0;

function srd_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
}

function srd_file(string $path, string $content): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($path, $content);
}

function srd_remove(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            srd_remove($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

function srd_needles(array $patterns): array
{
    return array_values(array_map(static fn(array $pattern): string => (string)($pattern['needle'] ?? ''), $patterns));
}

srd_file($fixtureRoot . '/apps/Shell/Composers/OperatorSurfaceComposer.php', <<<'TXT'
<?php
namespace Apps\Shell\Composers;
use Apps\Shell\Style\ThemeTokens;
$path = 'apps/Shell/Style/theme.php';
TXT);
srd_file($fixtureRoot . '/apps/Platform/Style/PlatformStyle.php', <<<'TXT'
<?php
use Apps\Shell\Style\ThemeTokens;
$path = 'apps/Shell/Style/theme.php';
TXT);
srd_file($fixtureRoot . '/apps/Studio/tests/probe_reference.php', "apps/Shell/Style\n");
srd_file($fixtureRoot . '/engineering/Shell/work.md', "apps/Shell/Style -> apps/Shell/DesignSystem\n");
srd_file($fixtureRoot . '/docs/architecture/shell.md', "apps/Shell/Style ownership note\n");
srd_file($fixtureRoot . '/vendor/ignored.php', "apps/Shell/Style\n");
srd_file($fixtureRoot . '/storage/cache/ignored.php', "apps/Shell/Style\n");
srd_file($fixtureRoot . '/apps/Shell/Resources/lang/en.php', "return ['path' => 'apps/Shell/Style'];\n");

$patterns = StudioReferenceDiscoveryService::patterns('apps/Shell/Style', 'apps/Shell/DesignSystem', 'Shell');
$needles = srd_needles($patterns);
srd_assert(in_array('apps/Shell/Style', $needles, true), 'patterns include exact path');
srd_assert(in_array('Shell/Style', $needles, true), 'patterns include owner path');
srd_assert(in_array('/Shell/Style/', $needles, true), 'patterns include owner path segment');
srd_assert(in_array('namespace Apps\\Shell\\Style', $needles, true), 'patterns include namespace declaration');
srd_assert(in_array('use Apps\\Shell\\Style', $needles, true), 'patterns include import path');
srd_assert(in_array('Apps\\\\Shell\\\\Style', $needles, true), 'patterns include escaped namespace path');
srd_assert(!in_array('Style', $needles, true), 'patterns exclude generic basename for folder rename');
srd_assert(!in_array('Shell', $needles, true), 'patterns exclude generic owner key');

srd_assert(
    StudioReferenceDiscoveryService::proposedReplacement('exact path', 'apps/Shell/Style', 'apps/Shell/Style', 'apps/Shell/DesignSystem') === 'apps/Shell/DesignSystem',
    'exact path replacement is deterministic'
);
srd_assert(
    StudioReferenceDiscoveryService::proposedReplacement('namespace declaration', 'namespace Apps\\Shell\\Style', 'apps/Shell/Style', 'apps/Shell/DesignSystem') === 'namespace Apps\\Shell\\DesignSystem',
    'namespace declaration replacement is deterministic'
);
srd_assert(
    StudioReferenceDiscoveryService::proposedReplacement('basename', 'Style', 'apps/Shell/Style', 'apps/Shell/DesignSystem') === '',
    'unsupported replacement remains empty'
);

srd_assert(
    StudioReferenceDiscoveryService::shouldPreferReference(
        ['match_type' => 'namespace declaration', 'matched_pattern' => 'namespace Apps\\Shell\\Style'],
        ['match_type' => 'escaped namespace path', 'matched_pattern' => 'Apps\\\\Shell\\\\Style']
    ),
    'dedupe prefers namespace declaration'
);
srd_assert(
    !StudioReferenceDiscoveryService::shouldPreferReference(
        ['match_type' => 'owner path', 'matched_pattern' => 'Shell/Style'],
        ['match_type' => 'namespace declaration', 'matched_pattern' => 'namespace Apps\\Shell\\Style']
    ),
    'dedupe does not downgrade specific namespace match'
);

$direct = StudioReferenceDiscoveryService::discover([[
    'request_id' => 'shell-style',
    'operation_id' => 'op-shell-style',
    'operation_type' => 'folder_rename',
    'owner_key' => 'Shell',
    'source_path' => 'apps/Shell/Style',
    'target_path' => 'apps/Shell/DesignSystem',
    'reference_key' => 'Style -> DesignSystem',
    'include_path_prefixes' => ['apps/Shell/'],
]], $fixtureRoot);

srd_assert(($direct['status'] ?? '') === 'ok', 'direct discovery returns ok');
srd_assert((int)($direct['summary']['request_count'] ?? 0) === 1, 'direct discovery counts request');
srd_assert((int)($direct['search_scope']['scanned_files'] ?? 0) === 6, 'excluded vendor and cache files are not scanned');
$directItem = $direct['items'][0] ?? [];
$directRefs = is_array($directItem['references'] ?? null) ? $directItem['references'] : [];
srd_assert($directRefs !== [], 'direct discovery returns references');
srd_assert((int)($directItem['reference_count'] ?? 0) === count($directRefs), 'reference count matches evidence');
srd_assert((string)($directItem['operation_id'] ?? '') === 'op-shell-style', 'operation provenance is retained');
srd_assert((string)($directItem['confidence'] ?? '') === 'high', 'folder rename evidence is high confidence');
srd_assert(count(array_filter($directRefs, static fn(array $reference): bool => str_starts_with((string)($reference['file_path'] ?? ''), 'apps/Platform/'))) === 0, 'include-prefix prevents cross-owner false positives');
srd_assert(count(array_filter($directRefs, static fn(array $reference): bool => (string)($reference['relevance'] ?? '') === StudioReferenceDiscoveryService::RELEVANCE_RUNTIME_BLOCKING)) > 0, 'runtime reference is classified');
srd_assert(count(array_filter($directRefs, static fn(array $reference): bool => (string)($reference['proposed_replacement'] ?? '') !== '')) > 0, 'replacement evidence is returned');

$allScope = StudioReferenceDiscoveryService::discover([[
    'source_path' => 'apps/Shell/Style',
    'target_path' => 'apps/Shell/DesignSystem',
]], $fixtureRoot);
$allRefs = $allScope['items'][0]['references'] ?? [];
srd_assert(count(array_filter($allRefs, static fn(array $reference): bool => str_starts_with((string)($reference['file_path'] ?? ''), 'apps/Platform/'))) > 0, 'unscoped discovery can find cross-owner references');
srd_assert(count(array_filter($allRefs, static fn(array $reference): bool => (string)($reference['relevance'] ?? '') === StudioReferenceDiscoveryService::RELEVANCE_STUDIO_TOOLING)) > 0, 'Studio test reference is classified as tooling');
srd_assert(count(array_filter($allRefs, static fn(array $reference): bool => (string)($reference['relevance'] ?? '') === StudioReferenceDiscoveryService::RELEVANCE_ENGINEERING_WORKSPACE)) > 0, 'engineering reference is classified');
srd_assert(count(array_filter($allRefs, static fn(array $reference): bool => (string)($reference['relevance'] ?? '') === StudioReferenceDiscoveryService::RELEVANCE_DOCUMENTATION_HISTORY)) > 0, 'documentation reference is classified');
srd_assert(count(array_filter($allRefs, static fn(array $reference): bool => (string)($reference['relevance'] ?? '') === StudioReferenceDiscoveryService::RELEVANCE_OWNER_METADATA)) > 0, 'locale reference is classified as owner metadata');

$invalid = StudioReferenceDiscoveryService::discover([], $fixtureRoot . '/missing');
srd_assert(($invalid['status'] ?? '') === 'error', 'invalid root returns controlled error');
srd_assert((string)($invalid['diagnostics'][0]['code'] ?? '') === 'REFERENCE_ROOT_UNAVAILABLE', 'invalid root returns diagnostic code');

$migrationPlan = [
    'operations' => [
        [
            'operation_id' => 'op-shell-style',
            'operation_type' => 'folder_rename',
            'blocker_status' => 'BLOCKED_BY_REFERENCE_DISCOVERY',
            'source_path' => 'apps/Shell/Style',
            'target_path' => 'apps/Shell/DesignSystem',
        ],
        [
            'operation_id' => 'ignored',
            'operation_type' => 'copy',
            'blocker_status' => 'READY',
            'source_path' => 'apps/Shell/foo.php',
            'target_path' => 'apps/Shell/bar.php',
        ],
    ],
];
$adapted = OwnerStructureReferenceDiscoveryService::discover(['owner_key' => 'Shell'], [], $migrationPlan);
srd_assert((int)($adapted['summary']['total_blockers'] ?? 0) === 1, 'adapter selects only reference-blocked operations');
srd_assert(count($adapted['items'] ?? []) === 1, 'adapter preserves one item');
$adaptedItem = $adapted['items'][0] ?? [];
srd_assert((string)($adaptedItem['operation_id'] ?? '') === 'op-shell-style', 'adapter preserves operation id');
srd_assert((string)($adaptedItem['group'] ?? '') === 'needs_review', 'runtime references remain needs_review');
srd_assert((string)($adaptedItem['safe_to_promote'] ?? '') === 'no', 'runtime references block promotion');
srd_assert((string)($adaptedItem['migration_readiness_state'] ?? '') === 'blocked_runtime_references', 'adapter preserves blocked runtime state');
srd_assert((int)($adaptedItem['relevance_summary']['RUNTIME_BLOCKING'] ?? 0) > 0, 'adapter preserves relevance summary');
srd_assert((int)($adaptedItem['category_summary']['runtime'] ?? 0) > 0, 'adapter preserves category summary');
srd_assert((string)($adapted['search_scope']['root'] ?? '') === '.', 'adapter preserves legacy root display');
srd_assert((int)($adapted['search_scope']['scanned_files'] ?? 0) === 6, 'adapter exposes canonical scan scope');

$discoveryItem = new ReflectionMethod(OwnerStructureReferenceDiscoveryService::class, 'discoveryItem');
if (PHP_VERSION_ID < 80100) {
    $discoveryItem->setAccessible(true);
}
$docsOnly = $discoveryItem->invoke(null, ['operation_id' => 'docs'], 'a', 'b', 'a -> b', [[
    'file_path' => 'docs/a.md',
    'confidence' => 'high',
    'relevance' => StudioReferenceDiscoveryService::RELEVANCE_DOCUMENTATION_HISTORY,
    'category' => StudioReferenceDiscoveryService::CATEGORY_DOCS,
]]);
srd_assert((string)($docsOnly['safe_to_promote'] ?? '') === 'yes', 'docs-only evidence remains promotable');
srd_assert((string)($docsOnly['group'] ?? '') === 'ready_to_promote', 'docs-only evidence remains ready_to_promote');
srd_assert((string)($docsOnly['migration_readiness_state'] ?? '') === 'ready_for_manual_rename', 'docs-only evidence keeps manual rename readiness');

$tooling = $discoveryItem->invoke(null, ['operation_id' => 'tooling'], 'a', 'b', 'a -> b', [[
    'file_path' => 'apps/Studio/tests/probe.php',
    'confidence' => 'high',
    'relevance' => StudioReferenceDiscoveryService::RELEVANCE_STUDIO_TOOLING,
    'category' => StudioReferenceDiscoveryService::CATEGORY_TOOLING,
]]);
srd_assert((string)($tooling['safe_to_promote'] ?? '') === 'no', 'tooling evidence still requires review');
srd_assert((string)($tooling['migration_readiness_state'] ?? '') === 'needs_tooling_review', 'tooling evidence preserves readiness state');

srd_remove($fixtureRoot);
echo "[probe] Studio reference discovery capability: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
