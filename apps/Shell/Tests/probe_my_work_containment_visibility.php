<?php
declare(strict_types=1);

/**
 * Focused OH-AUTO1-098 proof. This probe exercises the committed
 * Manufacturing containment result and captures the actual Shell partial
 * output for contained and unrestricted states. It uses no DB/bootstrap.
 */
const APP_ROOT = __DIR__ . '/../../..';

require_once APP_ROOT . '/apps/Manufacturing/Services/MyWorkContributionService.php';

use Apps\Manufacturing\Services\MyWorkContributionService;

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
};

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('t')) {
    function t(string $key): string
    {
        return [
            'admin.my_work.scope_containment.title' => 'Work information unavailable',
            'admin.my_work.scope_containment.unavailable' => 'Scope-safe work information is temporarily unavailable. No work count or operational record is being shown.',
        ][$key] ?? $key;
    }
}

$baseScope = [
    'machine_ids' => [7],
    'part_ids' => [],
    'task_types' => [],
    'department_code' => '',
    'branch_code' => '',
    'ownership_role' => '',
];
$contained = MyWorkContributionService::containedWorkspace(
    ['display_name' => 'Restricted User'],
    ['active_assigned_apps' => ['manufacturing'], 'authority_role' => 'app_user'],
    'restricted_unavailable'
);

$assert(($contained['scope_containment']['records_visible'] ?? true) === false, 'contained result denies records');
$assert(($contained['scope_containment']['state'] ?? '') === 'restricted_unavailable', 'contained state is preserved');
$assert(($contained['all_items'] ?? null) === [], 'contained result has no queue items');
$assert(($contained['role_links'] ?? null) === [], 'contained result has no role links');
$assert(($contained['recent_notifications'] ?? null) === [], 'contained result has no notification rows');
$assert(($contained['unread_notifications'] ?? null) === 0, 'contained result has no notification count');
$assert(MyWorkContributionService::scopeContainmentState($baseScope) === 'restricted_unavailable', 'restricted scope remains contained');
$assert(MyWorkContributionService::scopeContainmentState(array_replace($baseScope, [
    'machine_ids' => [],
])) === 'unrestricted', 'explicit empty scope remains unrestricted');

$render = static function (array $scope): string {
    $scope_containment = $scope;
    ob_start();
    require APP_ROOT . '/apps/Shell/Views/admin/scope_containment.php';
    return (string)ob_get_clean();
};

$containedHtml = $render($contained['scope_containment']);
$unrestrictedHtml = $render([]);
$assert(str_contains($containedHtml, 'Work information unavailable'), 'contained output has explicit unavailable title');
$assert(str_contains(strtolower($containedHtml), 'scope-safe work information is temporarily unavailable'), 'contained output explains unsupported safe source');
$assert(!str_contains($containedHtml, '0'), 'contained output does not present a verified zero count');
$assert($unrestrictedHtml === '', 'unrestricted output preserves ordinary presentation');

$source = (string)file_get_contents(APP_ROOT . '/apps/Shell/Composers/AdminSurfaceComposer.php');
$viewSource = (string)file_get_contents(APP_ROOT . '/apps/Shell/Views/admin/dashboard.php');
$assert(str_contains($source, "'scope_containment'"), 'composer forwards containment state');
$assert(str_contains($viewSource, 'scope_containment.php'), 'admin home includes containment presentation');

if ($failures !== []) {
    fwrite(STDERR, "OH-AUTO1-098 containment visibility: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "OH-AUTO1-098 containment visibility: {$assertions}/{$assertions} PASS\n");
