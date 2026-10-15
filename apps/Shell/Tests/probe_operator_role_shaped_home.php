<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Composers/OperatorRoleHomeComposer.php';
require_once APP_ROOT . '/apps/Shell/Services/OperatorLayerWidgetService.php';

use Apps\Shell\Composers\OperatorRoleHomeComposer;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$resolvedLeaderContext = ['app_roles' => ['manufacturing' => 'production_leader']];
$leaderContext = array_merge($resolvedLeaderContext, [
    'authenticated_user_id' => 7,
    'operator_username' => 'leader',
    'active_assigned_apps' => ['manufacturing'],
    'operator_route_access_resolver' => static fn(string $path): bool => $path === '/u/leader/coverage',
]);
$assert(OperatorRoleHomeComposer::resolveInteractionProfile($leaderContext) === 'leader', 'leader profile comes from explicit dashboard assignment authority');
$assert(OperatorRoleHomeComposer::resolveInteractionProfile(['interaction_profile' => 'worker']) === 'worker', 'operator assignment keeps worker profile');
$assert(OperatorRoleHomeComposer::resolveInteractionProfile(['interaction_profile' => 'unknown_label']) === 'worker', 'unknown labels do not elevate interaction profile');
$assert(OperatorRoleHomeComposer::resolveInteractionProfile(['interaction_profile' => 'read_only']) === 'read_only', 'explicit resolved read-only profile takes precedence');
$assert(OperatorRoleHomeComposer::resolveInteractionProfile(['access_profiles' => ['readonly_observer']]) === 'read_only', 'read-only access profile shapes a read-only home');
$assert(OperatorRoleHomeComposer::fromAssignmentContext(['operator_home_eligible' => false, 'app_roles' => ['manufacturing' => 'display'], 'access_profiles' => ['readonly_observer']]) === 'worker', 'display assignment does not activate the operator role home');
$assert(OperatorRoleHomeComposer::fromAssignmentContext(['operator_home_eligible' => false, 'app_roles' => ['platform' => 'platform_admin']]) === 'worker', 'admin assignment does not activate the operator role home');

$regions = [
    'monitoring_sections' => [
        [
            'widget_key' => 'allowed_watch',
            'widget_type' => 'watchlist',
            'placement_zone' => 'monitoring',
            'interaction_profiles' => ['leader', 'read_only'],
            'priority' => 2,
            'title' => 'Allowed review',
            'url' => '/u/leader/coverage',
        ],
        [
            'widget_key' => 'hidden_watch',
            'widget_type' => 'watchlist',
            'placement_zone' => 'monitoring',
            'interaction_profiles' => ['worker'],
            'priority' => 1,
            'title' => 'Wrong profile',
        ],
    ],
    'summary_cards' => [[
        'widget_key' => 'denied_summary',
        'widget_type' => 'informative',
        'interaction_profiles' => ['leader'],
        'priority' => 3,
        'title' => 'Denied route summary',
        'url' => '/u/leader/denied',
    ]],
    'header_actions' => [[
        'widget_key' => 'read_only_action',
        'widget_type' => 'action',
        'view_kind' => 'form',
        'interaction_profiles' => ['read_only'],
        'title' => 'Mutation action',
    ]],
];
$leader = OperatorRoleHomeComposer::compose($leaderContext, $regions);
$assert(array_keys($leader['regions']) === ['summary_cards', 'monitoring_sections'], 'leader retains only authorized owner regions in deterministic region order');
$assert(count($leader['regions']['monitoring_sections']) === 1 && $leader['regions']['monitoring_sections'][0]['widget_key'] === 'allowed_watch', 'leader receives owner monitoring content matching interaction profile');
$assert(($leader['regions']['summary_cards'][0]['url'] ?? '') === '', 'route-inaccessible owner link is removed while content remains');

$readOnly = OperatorRoleHomeComposer::compose([
    'interaction_profile' => 'read_only',
    'operator_username' => 'leader',
    'operator_route_access_resolver' => static fn(string $path): bool => true,
], $regions);
$assert($readOnly['read_only'] === true, 'read-only composition carries an explicit read-only flag');
$assert(!isset($readOnly['regions']['header_actions']), 'read-only composition suppresses action/form contributions');
$assert(count($readOnly['regions']['monitoring_sections']) === 1, 'read-only composition retains informational monitoring content');

$crossUser = OperatorRoleHomeComposer::compose($leaderContext, ['monitoring_sections' => [[
    'widget_key' => 'cross_user',
    'widget_type' => 'watchlist',
    'interaction_profiles' => ['leader'],
    'title' => 'Cross-user link',
    'url' => '/u/another-user/coverage',
]]]);
$assert(($crossUser['regions']['monitoring_sections'][0]['url'] ?? '') === '', 'owner contribution cannot escape the current operator identity');

final class OperatorRoleHomeProbeRenderer
{
    public function tr(string $key, string $fallback = '', array $params = []): string
    {
        return $fallback !== '' ? $fallback : $key;
    }

    /** @param array<string,mixed> $data */
    public function render(array $data): string
    {
        ob_start();
        include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php';
        return (string)ob_get_clean();
    }
}

$renderer = new OperatorRoleHomeProbeRenderer();
$leaderHtml = $renderer->render([
    'dashboard_title' => 'Overview',
    'operator_role_home_enabled' => true,
    'operator_role_home_mode' => 'leader',
    'operator_role_home_title' => 'Leadership view',
    'operator_role_regions' => ['monitoring_sections' => [['widget_key' => 'review', 'title' => 'Review first', 'interaction_profiles' => ['leader']]]],
    'worker_work' => ['available' => true, 'current' => [], 'upcoming' => [], 'attention' => []],
]);
$assert(str_contains($leaderHtml, 'Review first') && !str_contains($leaderHtml, 'operator-worker-work-title'), 'leader renders review content without inheriting worker assignments');
$workerHtml = $renderer->render([
    'dashboard_title' => 'Overview',
    'operator_role_home_enabled' => false,
    'operator_role_regions' => [],
    'worker_work' => ['available' => true, 'current' => [], 'upcoming' => [], 'attention' => []],
]);
$assert(!str_contains($workerHtml, 'operator-role-home'), 'worker keeps the existing role-neutral home composition');
$leaderEmptyHtml = $renderer->render([
    'dashboard_title' => 'Overview',
    'operator_role_home_enabled' => false,
    'operator_role_home_mode' => 'leader',
    'operator_role_regions' => [],
    'worker_work' => ['available' => true, 'current' => [], 'upcoming' => [], 'attention' => []],
]);
$assert(!str_contains($leaderEmptyHtml, 'operator-worker-work-title'), 'leader never falls back to worker assignments when owner content is empty');

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
