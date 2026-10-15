<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Manufacturing/Services/MyWorkContributionService.php';

use Apps\Manufacturing\Services\MyWorkContributionService;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
};

$emptyScope = [
    'machine_ids' => [],
    'part_ids' => [],
    'task_types' => [],
    'department_code' => '',
    'branch_code' => '',
    'ownership_role' => '',
];

$assert(
    MyWorkContributionService::scopeContainmentState($emptyScope) === 'unrestricted',
    'explicit empty scope retains existing unrestricted owner meaning'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['machine_ids' => [7]])) === 'restricted_unavailable',
    'machine restriction is contained before the unscoped queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['part_ids' => [11]])) === 'restricted_unavailable',
    'part restriction is contained before the unscoped queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['task_types' => ['assembly']])) === 'restricted_unavailable',
    'task-type restriction is contained before the unscoped queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['department_code' => 'D1'])) === 'restricted_unavailable',
    'department restriction is not treated as enforced by the queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['branch_code' => 'B1'])) === 'restricted_unavailable',
    'branch restriction is not treated as enforced by the queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['ownership_role' => 'leader'])) === 'restricted_unavailable',
    'ownership restriction is not treated as enforced by the queue'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['machine_ids' => [-1]])) === 'denied_unavailable',
    'denied machine sentinel fails closed'
);
$assert(
    MyWorkContributionService::scopeContainmentState(array_replace($emptyScope, ['part_ids' => ['bad']])) === 'malformed_unavailable',
    'malformed part scope fails closed'
);
$assert(
    MyWorkContributionService::scopeContainmentState(['machine_ids' => [], 'part_ids' => []]) === 'malformed_unavailable',
    'missing scope dimensions fail closed'
);

$contained = MyWorkContributionService::containedWorkspace(
    ['id' => 5, 'full_name' => 'Scoped Worker', 'role' => 'operator'],
    [
        'authority_role' => 'app_user',
        'active_assigned_apps' => ['manufacturing'],
        'cross_functional_access' => ['assembly' => 'view'],
    ],
    'restricted_unavailable'
);

$assert(($contained['scope_containment']['records_visible'] ?? true) === false, 'contained result exposes no records');
$assert(($contained['scope_containment']['state'] ?? '') === 'restricted_unavailable', 'contained state is explicit');
$assert(($contained['kpi']['total'] ?? -1) === 0, 'contained result exposes no total count');
$assert(($contained['all_items'] ?? null) === [], 'contained result exposes no queue items');
$assert(($contained['sections']['needs_action'] ?? null) === [], 'contained result exposes no needs-action records');
$assert(($contained['sections']['awaiting_approval'] ?? null) === [], 'contained result exposes no approval records');
$assert(($contained['role_links'] ?? null) === [], 'contained result exposes no derived role links');
$assert(($contained['active_assigned_apps'] ?? []) === ['manufacturing'], 'contained result preserves harmless identity context');

$source = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/MyWorkContributionService.php');
$scopePos = strpos($source, '$scopeContainmentState = self::scopeContainmentState($scope);');
$handoffPos = strpos($source, 'self::collectHandoffItems(');
$operationalPos = strpos($source, 'self::collectOperationalQueue(');
$assert($scopePos !== false && $handoffPos !== false && $scopePos < $handoffPos, 'containment precedes handoff collection');
$assert($scopePos !== false && $operationalPos !== false && $scopePos < $operationalPos, 'containment precedes operational queue collection');
$containedPos = strpos($source, 'public static function containedWorkspace(');
$containedEnd = $containedPos === false ? false : strpos($source, "\n    }", $containedPos);
$containedSource = ($containedPos === false || $containedEnd === false)
    ? ''
    : substr($source, $containedPos, $containedEnd - $containedPos);
$assert(!str_contains($containedSource, 'ensureSchema();'), 'contained result path introduces no schema initializer');

echo "Restricted-scope My Work containment: {$passed}/" . ($passed + $failed) . " passed\n";
exit($failed === 0 ? 0 : 1);
