<?php
declare(strict_types=1);

/**
 * OH-AUTO2-100 focused proof.
 *
 * The preflight reader is instrumented with in-memory rows and rejects every
 * non-SELECT operation. Restricted My Work is then built without a database
 * connection or schema bootstrap authority. The unrestricted case is asserted
 * only at the preflight boundary; this probe does not claim the full
 * unrestricted request is non-mutating.
 */
const APP_ROOT = __DIR__ . '/../../..';

require_once APP_ROOT . '/plugins/Base/Services/UserDashboardAssignmentService.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/MyWorkContributionService.php';

use Apps\Manufacturing\Services\MyWorkContributionService;
use Plugins\Base\Services\UserDashboardAssignmentService;

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$user = [
    'id' => 41,
    'role' => 'operator',
    'authority_role' => 'app_user',
    'email' => 'worker@example.test',
];

$reader = static function (string $sql, array $params, string $kind) use (&$assertions): array {
    if (stripos(ltrim($sql), 'select ') !== 0) {
        throw new RuntimeException('preflight attempted a non-read operation');
    }
    if ($kind === 'one') {
        return [[
            'id' => 41,
            'assignment_user_id' => 41,
            'role' => 'operator',
            'authority_role' => 'app_user',
            'role_tier' => 'editor',
            'default_app' => 'manufacturing',
        ]];
    }
    return [['machine_id' => 7, 'part_id' => null, 'task_type' => null,
        'department_code' => '', 'branch_code' => '', 'ownership_role' => '']];
};

$restricted = UserDashboardAssignmentService::readOnlyPreflight($user, $reader);
$assert(($restricted['state'] ?? '') === 'restricted', 'restricted scope is identified before legacy resolution');
$assert(($restricted['context']['scope']['machine_ids'] ?? []) === [7], 'existing machine scope evidence is preserved');
$assert(($restricted['context']['_scope_preflight_state'] ?? '') === 'restricted', 'restricted state is carried into context');

// No DB connection is available to this probe. A schema initializer would
// therefore throw; a successful contained result proves it was not reached.
$contained = MyWorkContributionService::buildRestricted($user, [], $restricted['context']);
$assert(($contained['scope_containment']['records_visible'] ?? true) === false, 'restricted result is unavailable, not an empty verified queue');
$assert(($contained['all_items'] ?? null) === [], 'restricted result contains no queue records');
$assert(($contained['notification_summary'] ?? null) === [], 'restricted result contains no notification summary');
$assert(($contained['recent_notifications'] ?? null) === [], 'restricted result contains no notifications');
$assert(($contained['role_links'] ?? null) === [], 'restricted result contains no role links');
$assert(($contained['scope_containment']['state'] ?? '') === 'restricted_unavailable', 'restricted state remains visible to Shell');
$normalEntryContained = MyWorkContributionService::build(
    array_merge($user, ['_scope_preflight_context' => $restricted['context']]),
    []
);
$assert(($normalEntryContained['scope_containment']['records_visible'] ?? true) === false, 'normal contribution entry honors server preflight context');

$deniedReader = static function (string $sql, array $params, string $kind) use ($reader): array {
    if ($kind === 'one') {
        return $reader($sql, $params, $kind);
    }
    return [['machine_id' => -1, 'part_id' => null, 'task_type' => null,
        'department_code' => '', 'branch_code' => '', 'ownership_role' => '']];
};
$denied = UserDashboardAssignmentService::readOnlyPreflight($user, $deniedReader);
$assert(($denied['state'] ?? '') === 'restricted', 'denied sentinel fails closed as restricted');

$malformedReader = static function (string $sql, array $params, string $kind) use ($reader): array {
    if ($kind === 'one') {
        return $reader($sql, $params, $kind);
    }
    return [['machine_id' => 'not-an-id', 'part_id' => null, 'task_type' => null,
        'department_code' => '', 'branch_code' => '', 'ownership_role' => '']];
};
$malformed = UserDashboardAssignmentService::readOnlyPreflight($user, $malformedReader);
$assert(($malformed['state'] ?? '') === 'unavailable', 'malformed scope evidence fails closed');
$malformedResult = MyWorkContributionService::buildRestricted($user, [], $malformed['context']);
$assert(($malformedResult['scope_containment']['records_visible'] ?? true) === false, 'malformed scope cannot reach queue collection');

$missingEvidenceReader = static function (string $sql, array $params, string $kind) use ($reader): array {
    if ($kind === 'one') {
        return $reader($sql, $params, $kind);
    }
    throw new RuntimeException('user_operational_scopes unavailable');
};
$missing = UserDashboardAssignmentService::readOnlyPreflight($user, $missingEvidenceReader);
$assert(($missing['state'] ?? '') === 'unavailable', 'missing scope table/evidence fails closed');

$emptyReader = static function (string $sql, array $params, string $kind) use ($reader): array {
    if ($kind === 'one') {
        return $reader($sql, $params, $kind);
    }
    return [];
};
$empty = UserDashboardAssignmentService::readOnlyPreflight($user, $emptyReader);
$assert(($empty['state'] ?? '') === 'unrestricted', 'explicit valid empty scope remains unrestricted at preflight');
$assert(($empty['context']['scope'] ?? []) === [
    'machine_ids' => [], 'part_ids' => [], 'task_types' => [],
    'department_code' => '', 'branch_code' => '', 'ownership_role' => '',
], 'empty scope retains canonical shape');

$missingAssignmentReader = static function (string $sql, array $params, string $kind): array {
    if ($kind === 'one') {
        return [[
            'id' => 41,
            'assignment_user_id' => null,
            'role' => 'operator',
            'authority_role' => 'app_user',
            'role_tier' => 'editor',
            'default_app' => 'manufacturing',
        ]];
    }
    return [];
};
$missingAssignment = UserDashboardAssignmentService::readOnlyPreflight($user, $missingAssignmentReader);
$assert(($missingAssignment['state'] ?? '') === 'unavailable', 'missing assignment evidence fails closed');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "PASS {$assertions}/{$assertions}\n");
