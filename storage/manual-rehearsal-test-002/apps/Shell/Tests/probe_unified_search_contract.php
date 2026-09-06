<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require APP_ROOT . '/vendor/autoload.php';

use Platform\Search\AuthorizedSearchIndexService;
use App\Core\RouteRuntimeAuthority;
use App\Core\SearchService;
use Apps\Manufacturing\Services\Search\ManufacturingSearchProvider;
use Apps\Platform\Services\Search\PlatformArtifactSearchProvider;
use Platform\Search\SearchProviderRegistry;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
};

$user = ['id' => 7, 'username' => 'demo'];
$candidates = [
    [
        'kind' => 'route',
        'route' => '/u/demo/dashboard',
        'label' => 'Workspace dashboard',
        'text' => 'dashboard home workspace',
    ],
    [
        'kind' => 'route',
        'route' => '/u/demo/dashboard',
        'label' => 'Workspace dashboard',
        'text' => 'duplicate dashboard',
    ],
    [
        'kind' => 'entity',
        'entity_type' => 'part',
        'entity_id' => '42',
        'route' => '/u/demo/parts/detail?part_id=42',
        'label' => 'Widget A',
        'text' => 'Widget A WA-42 M1 42',
        'meta' => 'WA-42 | M1',
    ],
    [
        'kind' => 'route',
        'route' => '/admin/access-control',
        'label' => 'Outside operator scope',
        'text' => 'outside forbidden',
    ],
    [
        'kind' => 'route',
        'route' => 'https://example.com/u/demo/dashboard',
        'label' => 'External lookalike route',
        'text' => 'external lookalike',
    ],
];

$scope = AuthorizedSearchIndexService::publish(
    $user,
    'operator',
    $candidates,
    ['path_prefix' => '/u/demo']
);

$assert(preg_match('/^[a-f0-9]{36}$/', $scope) === 1, 'publishing returns an opaque search scope');

$allowedPaths = [];
$result = AuthorizedSearchIndexService::search(
    'widget',
    $user,
    $scope,
    static function (string $path) use (&$allowedPaths): bool {
        $allowedPaths[] = $path;
        return true;
    }
);
$items = (array)(($result['groups'][0] ?? [])['items'] ?? []);
$assert(($result['success'] ?? false) === true, 'authorized search returns the common success contract');
$assert(count($items) === 1, 'entity candidate is matched once');
$assert((string)($items[0]['url'] ?? '') === '/u/demo/parts/detail?part_id=42', 'result preserves the authorized operator route');
$assert((string)($items[0]['entity_type'] ?? '') === 'part', 'result preserves entity metadata');
$assert(in_array('/u/demo/parts/detail', $allowedPaths, true), 'route authorization runs before delivery');
$assert(!in_array('/admin/access-control', $allowedPaths, true), 'out-of-prefix candidate never reaches route authorization');

$duplicateResult = AuthorizedSearchIndexService::search('dashboard', $user, $scope, static fn(string $path): bool => true);
$duplicateItems = (array)(($duplicateResult['groups'][0] ?? [])['items'] ?? []);
$assert(count($duplicateItems) === 1, 'duplicate candidates are removed before searching');

$outsideResult = AuthorizedSearchIndexService::search('outside', $user, $scope, static fn(string $path): bool => true);
$assert((array)($outsideResult['groups'] ?? []) === [], 'candidate outside the published path prefix is never indexed');
$externalResult = AuthorizedSearchIndexService::search('external', $user, $scope, static fn(string $path): bool => true);
$assert((array)($externalResult['groups'] ?? []) === [], 'external lookalike route is never indexed');

$deniedResult = AuthorizedSearchIndexService::search('widget', $user, $scope, static fn(string $path): bool => false);
$assert((array)($deniedResult['groups'] ?? []) === [], 'route authorization can deny an indexed candidate');

$otherUserResult = AuthorizedSearchIndexService::search('widget', ['id' => 8], $scope, static fn(string $path): bool => true);
$assert((array)($otherUserResult['groups'] ?? []) === [], 'search scope cannot be reused by another user');

$shortResult = AuthorizedSearchIndexService::search('w', $user, $scope, static fn(string $path): bool => true);
$assert((array)($shortResult['groups'] ?? []) === [], 'queries shorter than two characters return no results');

$source = file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorInteractionScriptComposer.php') ?: '';
$controller = file_get_contents(APP_ROOT . '/app/AppManager/Controllers/SearchController.php') ?: '';
$assert(str_contains($source, "'/api/search?q='"), 'Operator search calls the canonical search endpoint');
$assert(!str_contains($source, 'buildOperatorLiveSearchCandidates'), 'Operator DOM candidate crawler is removed');
$assert(!str_contains($source, 'liberalSearchScore'), 'duplicate client-side search scorer is removed');
$assert(str_contains($controller, "'candidate_scope' => \$candidateScope"), 'canonical controller delegates the authorized candidate scope');

$searchReflection = new ReflectionClass(SearchService::class);
$columnCache = $searchReflection->getProperty('tableColumnsCache');
$columnCache->setValue(null, ['production_entries' => ['product_id', 'part_id']]);
$scopeBuilder = $searchReflection->getMethod('buildScopeClause');
[$scopeSql, $scopeParams] = $scopeBuilder->invoke(
    null,
    'production_entries',
    ['product_id' => 'part_ids', 'part_id' => 'part_ids'],
    ['scope' => ['part_ids' => [42, 43]]],
    'pe'
);
$assert(str_contains($scopeSql, 'pe.`product_id` IN (?,?)'), 'part scope uses the owned product_id schema column');
$assert(!str_contains($scopeSql, 'pe.`part_id`'), 'scope aliases do not apply the same part scope twice');
$assert($scopeParams === [42, 43], 'scope alias resolution preserves bound parameters');

$winnerSelector = $searchReflection->getMethod('shouldReplaceResultCandidate');
$existingWinner = ['precedence' => 100, 'fingerprint' => 'bbb'];
$assert($winnerSelector->invoke(null, 200, 'zzz', $existingWinner) === true, 'higher-precedence duplicate wins independent of provider order');
$assert($winnerSelector->invoke(null, 100, 'aaa', $existingWinner) === true, 'equal-precedence duplicate uses a deterministic fingerprint tie-break');
$assert($winnerSelector->invoke(null, 50, 'aaa', $existingWinner) === false, 'lower-precedence duplicate cannot replace the canonical winner');
$groupSorter = $searchReflection->getMethod('sortGroupsByPriority');
$sortedGroups = $groupSorter->invoke(null, [
    ['type' => 'workflow_actions'],
    ['type' => 'pages'],
    ['type' => 'charts'],
], ['pages' => 60, 'charts' => 90, 'workflow_actions' => 110]);
$assert(array_column($sortedGroups, 'type') === ['pages', 'charts', 'workflow_actions'], 'provider priorities preserve canonical group order across provider load order');

SearchProviderRegistry::clear();
$providers = SearchProviderRegistry::providers();
$providersByClass = [];
foreach ($providers as $provider) {
    $providersByClass[$provider::class] = $provider;
}
$assert(count($providers) >= 2, 'owner search provider registry discovers data and artifact providers');
$assert(isset($providersByClass[ManufacturingSearchProvider::class]), 'Manufacturing search provider is owner-declared');
$assert(isset($providersByClass[PlatformArtifactSearchProvider::class]), 'Platform artifact provider is owner-declared');
$assert($providersByClass[ManufacturingSearchProvider::class]->groupLabels()['parts'] === 'Parts', 'provider owns its result group labels');
$assert($providersByClass[PlatformArtifactSearchProvider::class]->groupPriorities()['log']['pages'] === 1, 'artifact provider owns intent-specific group ordering');

RouteRuntimeAuthority::seed([
    'GET' => ['/ops/runtime-only' => static fn() => null],
    'POST' => ['/ops/runtime-submit' => static fn() => null],
]);
$artifactReflection = new ReflectionClass(PlatformArtifactSearchProvider::class);
$runtimeEndpoints = $artifactReflection->getMethod('routeEndpointsFromRuntime')->invoke(null);
$assert(in_array(['method' => 'GET', 'path' => '/ops/runtime-only'], $runtimeEndpoints, true), 'artifact discovery consumes the final runtime GET catalog');
$assert(in_array(['method' => 'POST', 'path' => '/ops/runtime-submit'], $runtimeEndpoints, true), 'artifact discovery consumes the final runtime POST catalog');

$coreSource = file_get_contents(APP_ROOT . '/app/Core/SearchService.php') ?: '';
$providerSource = file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/Search/ManufacturingSearchProvider.php') ?: '';
$artifactProviderSource = file_get_contents(APP_ROOT . '/apps/Platform/Services/Search/PlatformArtifactSearchProvider.php') ?: '';
$assert(!str_contains($coreSource, 'private static function searchMachines'), 'Core no longer implements Manufacturing machine search');
$assert(!str_contains($coreSource, 'FROM production_entries'), 'Core no longer contains Manufacturing entity SQL');
$assert(!str_contains($coreSource, 'searchUiArtifacts'), 'Core no longer implements UI or navigation discovery');
$assert(!str_contains($coreSource, 'routeEndpointsFromFiles'), 'Core no longer crawls route source files');
$assert(!str_contains($coreSource, "'menu_items'"), 'Core contains no artifact group catalog knowledge');
$assert(str_contains($coreSource, '$authorizedByIdentity'), 'Core applies deterministic cross-provider deduplication');
$assert(str_contains($coreSource, '$pathAuthorizationCache'), 'Core applies cached result-level route authorization');
$assert(str_contains($providerSource, 'FROM production_entries'), 'Manufacturing owner contains its entity search SQL');
$assert(str_contains($providerSource, "'product_id' => 'part_ids'"), 'owner provider preserves product scope confinement');
$assert(str_contains($providerSource, 'searchWorkflowActions'), 'Manufacturing owns workflow-action search semantics');
$assert(str_contains($providerSource, "'throughput_trend'"), 'Manufacturing owns its fallback chart catalog');
$assert(str_contains($artifactProviderSource, 'RouteRuntimeAuthority::loadedRoutes'), 'artifact provider uses authoritative runtime routes');
$assert(!str_contains($artifactProviderSource, 'preg_match_all'), 'artifact provider does not parse route PHP with regex');
$assert(!str_contains(strtolower($artifactProviderSource), 'manufacturing'), 'Platform artifact provider contains no Manufacturing knowledge');

echo '[probe] Unified search contract: ' . $assertions . '/' . $assertions . " assertions passed\n";
