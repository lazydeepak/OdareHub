<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartsWorkspaceService.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorPartDetailContributionService.php';

use Apps\Manufacturing\Services\OperatorPartsWorkspaceService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$rows = [
    ['id' => 10, 'parts_number' => 'PX-10', 'parts_name' => 'Part X'],
    ['id' => 20, 'parts_number' => 'PY-20', 'parts_name' => 'Part Y'],
];

$partX = ['part_ids' => [10]];
$visible = OperatorPartsWorkspaceService::projectAuthorizedRows($rows, $partX);
$assert(count($visible) === 1 && (int)$visible[0]['id'] === 10, 'part list projection excludes unauthorized product');
$assert(OperatorPartsWorkspaceService::isPartAuthorized(10, $partX), 'authorized part detail remains selectable');
$assert(!OperatorPartsWorkspaceService::isPartAuthorized(20, $partX), 'unauthorized direct detail ID is rejected');
$assert(count(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => [20]])) === 1, 'detail/list scope changes consistently');

$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => ['12junk']]) === null, 'malformed part restriction fails closed');
$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => [0]]) === null, 'zero part restriction fails closed');
$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => [-2]]) === null, 'negative part restriction fails closed');
$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => [-1]]) === [], 'denied part sentinel returns no products');
$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, ['part_ids' => []]) === $rows, 'explicit empty scope preserves authorized unrestricted list');
$assert(OperatorPartsWorkspaceService::projectAuthorizedRows($rows, []) === null, 'missing part scope fails closed');

$detailService = file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorPartDetailContributionService.php');
$assert(is_string($detailService) && strpos($detailService, 'resolveAuthorizedPartId') !== false, 'detail resolves through authorized owner boundary');
$assert(is_string($detailService) && strpos($detailService, 'Part360Service::buildReadOnly($productId)') !== false, 'read-only Part360 is called only after authorization resolution');
$assert(is_string($detailService) && strpos($detailService, 'ensureSchema') === false, 'operator detail adapter does not initialize schema');
$assert(is_string($detailService) && strpos($detailService, 'DB::') === false, 'operator detail adapter does not query before authorization');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";
