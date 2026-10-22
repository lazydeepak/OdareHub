<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartsWorkspaceService.php';

use Apps\Manufacturing\Services\OperatorPartsWorkspaceService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
};

$rows = [];
for ($id = 1; $id <= 505; $id++) {
    $rows[] = [
        'id' => $id,
        'parts_name' => $id === 504 ? 'Scoped Widget' : 'Other Part ' . $id,
        'parts_number' => $id === 504 ? 'SW-504' : 'OP-' . $id,
        'model' => $id === 505 ? 'Model-Z' : 'Model-' . $id,
    ];
}

$scope = ['part_ids' => [504, 505]];
$unrestricted = ['part_ids' => []];
$denied = ['part_ids' => [-1]];

$filtered = OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'scoped'], $scope, $rows);
$assert(count($filtered ?? []) === 1 && (int)$filtered[0]['id'] === 504, 'authorized name search returns only scoped match');

$number = OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'SW-504'], $scope, $rows);
$assert(count($number ?? []) === 1 && (int)$number[0]['id'] === 504, 'identifier search matches part number');

$model = OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'model-z'], $scope, $rows);
$assert(count($model ?? []) === 1 && (int)$model[0]['id'] === 505, 'name search is case-insensitive and matches model');

$empty = OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'does-not-exist'], $scope, $rows);
$assert($empty === [], 'non-matching search returns an honest empty result');

$scopeBeforeLimit = OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'sw-504'], $scope, $rows);
$assert(count($scopeBeforeLimit ?? []) === 1 && (int)$scopeBeforeLimit[0]['id'] === 504, 'authorized match is found despite unrelated rows before the limit');

$all = OperatorPartsWorkspaceService::projectFilteredRows([], $unrestricted, $rows);
$assert(count($all ?? []) === 500, 'empty search preserves the bounded result limit');

$assert(OperatorPartsWorkspaceService::projectFilteredRows(['q' => ['504']], $scope, $rows) === null, 'array search fails closed');
$assert(OperatorPartsWorkspaceService::projectFilteredRows(['q' => '   '], $scope, $rows) === null, 'whitespace-only search fails closed');
$assert(OperatorPartsWorkspaceService::projectFilteredRows(['q' => str_repeat('x', 81)], $scope, $rows) === null, 'oversized search fails closed');
$assert(OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'scoped'], $denied, $rows) === [], 'denied scope never returns search results');
$assert(OperatorPartsWorkspaceService::projectFilteredRows(['q' => 'other part 1'], $scope, $rows) === [], 'out-of-scope rows cannot be disclosed by search');

$service = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorPartsWorkspaceService.php');
$view = (string)file_get_contents(APP_ROOT . '/apps/Shell/Views/operator/parts.php');
$routes = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
$assert(str_contains($service, 'LIMIT 500'), 'runtime list remains bounded');
$assert(str_contains($service, 'partRestrictionSql'), 'runtime list retains server-side scope predicate');
$assert(str_contains($service, 'ESCAPE'), 'runtime search uses escaped parameterized LIKE matching');
$assert(str_contains($view, 'common.search'), 'Parts view renders localized search control');
$assert(!str_contains($view, 'method="post"'), 'Parts search view adds no mutation form');
$assert(substr_count($routes, "\$router->get('/u/parts'") === 1, 'Parts route remains a single existing GET route');
$assert(!str_contains($routes, "\$router->post('/u/parts"), 'Parts search adds no POST route');

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
