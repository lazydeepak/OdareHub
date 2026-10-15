<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/OperatorPartDetailActionAuthority.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartsWorkspaceService.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartDetailContributionService.php';

use Apps\Manufacturing\Services\OperatorPartsWorkspaceService;
use Apps\Shell\Services\OperatorPartDetailActionAuthority;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$rows = [
    ['id' => 10, 'parts_name' => 'Part X', 'parts_number' => 'PX-10', 'model' => 'Alpha', 'is_active' => 1],
    ['id' => 20, 'parts_name' => 'Part Y', 'parts_number' => 'PY-20', 'model' => 'Beta', 'is_active' => 1],
];
$scopeX = ['part_ids' => [10]];
$scopeY = ['part_ids' => [20]];
$scopeDenied = ['part_ids' => [-1]];

$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10], $scopeX, $rows) === 10, 'scoped operator selects authorized Part X');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 20], $scopeX, $rows) === 0, 'direct request for unauthorized Part Y is unavailable');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['q' => 'Part Y'], $scopeX, $rows) === 0, 'search cannot select unauthorized Part Y');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_number' => 'PY-20'], $scopeX, $rows) === 0, 'part-number lookup cannot select unauthorized Part Y');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10, 'product_id' => 20], $scopeX, $rows) === 0, 'contradictory direct selectors fail closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => ['10']], $scopeX, $rows) === 0, 'array direct selector fails closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['q' => 'Part X', 'part_q' => 'Part Y'], $scopeX, $rows) === 0, 'contradictory search selectors fail closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_number' => 'PX-10', 'q' => 'Part X'], $scopeX, $rows) === 0, 'part-number and search selectors fail closed as ambiguous');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10, 'q' => 'Part X'], $scopeX, $rows) === 0, 'mixed direct and search selectors fail closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10], ['part_ids' => ['bad']], $rows) === 0, 'malformed scope fails closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10], $scopeDenied, $rows) === 0, 'denied scope fails closed');
$assert(OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['q' => 'Part X'], ['part_ids' => []], $rows) === 10, 'authorized unrestricted scope preserves selection');

$called = 0;
$fakeBuilder = static function (int $id) use (&$called): array {
    $called++;
    return ['product' => ['id' => $id, 'parts_name' => 'Part X', 'parts_number' => 'PX-10']];
};
$unauthorizedSelection = OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 20], $scopeX, $rows);
if ($unauthorizedSelection > 0) {
    $fakeBuilder($unauthorizedSelection);
}
$assert($unauthorizedSelection === 0 && $called === 0, 'unauthorized selection stops before fake Part360 builder');
$authorizedSelection = OperatorPartsWorkspaceService::resolveAuthorizedPartIdFromRows(['part_id' => 10], $scopeX, $rows);
$authorizedPayload = $authorizedSelection > 0 ? $fakeBuilder($authorizedSelection) : [];
$assert($authorizedSelection === 10 && (int)($authorizedPayload['product']['id'] ?? 0) === 10 && $called === 1, 'authorized selection preserves identity for fake Part360 builder');

$actions = [
    ['key' => 'plan', 'url' => '/u/worker/production?prefill_product_id=10'],
    ['key' => 'orders', 'url' => '/u/worker/orders?q=PX-10'],
    ['key' => 'coverage', 'url' => '/u/worker/coverage'],
    ['key' => 'dispatch', 'url' => '/u/worker/dispatch?product_id=10'],
    ['key' => 'denied', 'url' => '/u/worker/production'],
    ['key' => 'cross-user', 'url' => '/u/other/coverage'],
];
$filtered = OperatorPartDetailActionAuthority::filter(
    $actions,
    'worker',
    static fn(string $url): bool => $url !== '/u/worker/production'
        && !str_contains($url, '/dispatch')
        && !str_contains($url, 'prefill_product_id')
);
$filteredKeys = array_values(array_map(static fn(array $action): string => (string)($action['key'] ?? ''), $filtered));
$assert($filteredKeys === ['orders', 'coverage'], 'route authority suppresses inaccessible detail actions');
$assert(!str_contains(implode(' ', array_map(static fn(array $action): string => (string)$action['url'], $filtered)), '/u/other/'), 'cross-user detail action is absent');
$assert(OperatorPartDetailActionAuthority::filter($actions, 'worker', static fn(string $url): bool => true, true) === [], 'read-only interaction profile has no detail actions');
$assert(OperatorPartDetailActionAuthority::filter($actions, 'worker', static fn(string $url): bool => true)[0]['url'] === '/u/worker/production?prefill_product_id=10', 'authorized same-user detail action remains functional');

$part360 = file_get_contents(APP_ROOT . '/apps/Manufacturing/modules/Products/Services/Part360Service.php');
$materialSchema = file_get_contents(APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialSchemaService.php');
$engineeringSchema = file_get_contents(APP_ROOT . '/apps/Manufacturing/modules/Products/Services/PartEngineeringSchemaService.php');
$assert(is_string($part360) && substr_count($part360, 'ensureSchema()') >= 2, 'Part360 authorized build still invokes two schema initializers');
$assert(is_string($materialSchema) && str_contains($materialSchema, 'CREATE TABLE IF NOT EXISTS'), 'Material schema initializer is mutation-capable');
$assert(is_string($engineeringSchema) && str_contains($engineeringSchema, 'CREATE TABLE IF NOT EXISTS'), 'Part engineering schema initializer is mutation-capable');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}
echo "RESULT: {$passed}/{$passed} assertions passed\n";
