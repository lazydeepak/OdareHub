<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('t')) {
    function t(string $key): string
    {
        return $key;
    }
}

$viewPath = APP_ROOT . '/plugins/Base/Views/ops/me.php';
if (!is_file($viewPath)) {
    fwrite(STDERR, "view_missing: {$viewPath}\n");
    exit(1);
}

$modes = ['worker', 'leader', 'read_only', 'display'];

$hostRegions = [
    'header_actions' => [
        [
            'widget_key' => 'open_prod',
            'widget_type' => 'action',
            'placement_zone' => 'operator_actions',
            'interaction_profiles' => ['worker', 'leader', 'admin'],
            'label' => 'Open Production',
            'url' => '/apps/manufacturing/production-operation',
            'weight' => 10,
        ],
    ],
    'summary_cards' => [
        [
            'widget_key' => 'dispatch_blocked',
            'widget_type' => 'alert',
            'placement_zone' => 'primary_work',
            'interaction_profiles' => ['worker', 'leader', 'read_only'],
            'title' => 'Dispatch Blocked',
            'value' => 3,
            'meta' => 'Blocked items requiring release review.',
            'url' => '/apps/manufacturing/dispatch-ops',
            'weight' => 20,
        ],
        [
            'widget_key' => 'coverage_risk',
            'widget_type' => 'informative',
            'placement_zone' => 'dashboard_summary',
            'interaction_profiles' => ['worker', 'leader', 'read_only', 'display'],
            'title' => 'Coverage Risk',
            'value' => 8,
            'meta' => 'Low coverage orders in current horizon.',
            'url' => '/apps/manufacturing/coverage',
            'weight' => 21,
        ],
    ],
    'quick_links' => [
        [
            'widget_key' => 'orders_link',
            'widget_type' => 'reference',
            'placement_zone' => 'supporting_visibility',
            'interaction_profiles' => ['worker', 'leader', 'read_only'],
            'label' => 'Orders',
            'url' => '/apps/manufacturing/daily-orders',
            'weight' => 30,
        ],
    ],
    'monitoring_sections' => [
        [
            'widget_key' => 'risk_watch',
            'widget_type' => 'watchlist',
            'placement_zone' => 'monitoring',
            'interaction_profiles' => ['leader', 'read_only', 'display'],
            'title' => 'Risk Watchlist',
            'kind' => 'table',
            'columns' => ['Item', 'Action'],
            'rows' => [['cells' => ['Order #10'], 'url' => '/apps/manufacturing/daily-orders/360?id=10', 'url_label' => 'Open']],
            'empty_message' => 'No risk items.',
            'weight' => 40,
        ],
        [
            'widget_key' => 'display_wall_kpi',
            'widget_type' => 'display',
            'placement_zone' => 'display_wall',
            'interaction_profiles' => ['display'],
            'title' => 'Display Wall KPI',
            'kind' => 'chart',
            'rows' => [['label' => 'Today Output', 'value' => '120']],
            'empty_message' => 'No display data.',
            'weight' => 45,
        ],
    ],
];

$results = [];

foreach ($modes as $mode) {
    $pageTitle = 'Home';
    $user_name = 'Verifier';
    $user_role = 'Verifier';
    $authority_role = 'app_user';
    $dashboard_type = 'operator';
    $kpi = ['total' => 4, 'needs_action' => 1, 'awaiting_approval' => 1, 'blocked' => 1, 'overdue_escalated' => 1];
    $sections = ['needs_action' => [], 'awaiting_approval' => [], 'overdue_escalated' => [], 'blocked_followup' => []];
    $grouped_sections = ['primary_work' => [], 'cross_functional_work' => [], 'visibility_monitoring' => []];
    $all_items = [];
    $has_approvals = false;
    $has_overdue = false;
    $has_blocked = false;
    $role_links = [];
    $approval_modules = [];
    $notification_summary = [];
    $recent_notifications = [];
    $unread_notifications = 0;
    $cross_functional_access = [];
    $primary_work_area = 'production';
    $cross_work_areas = [];
    $home_assigned_apps = ['manufacturing'];
    $home_launcher_apps = ['manufacturing'];
    $home_all_assigned_apps = ['manufacturing'];
    $home_dominant_app = 'manufacturing';
    $home_suite_role_templates = [];
    $home_module_permission_templates = [];
    $home_access_profiles = [];
    $home_duty_codes = [];
    $home_scope_snapshot = [];
    $home_experience_mode = $mode;
    $home_incomplete_mapping = false;
    $home_admin_warning = '';
    $admin_dashboard_panels = [];
    $host_regions = $hostRegions;
    $me_dashboard_blocks = [];
    $me_plugin_cards = [];
    $home_module_visibility = ['demands', 'coverage', 'materials', 'dispatch', 'production', 'qc'];
    $platform_admin_tools_links = [];

    ob_start();
    include $viewPath;
    $html = (string)ob_get_clean();

    $results[$mode] = [
        'operator_actions_visible' => str_contains($html, 'Open Production'),
        'primary_alert_visible' => str_contains($html, 'Dispatch Blocked'),
        'monitoring_watchlist_visible' => str_contains($html, 'Risk Watchlist'),
        'display_wall_visible' => str_contains($html, 'Display Wall KPI'),
        'type_badge_alert_visible' => str_contains($html, 'ALERT'),
        'type_badge_reference_visible' => str_contains($html, 'REFERENCE'),
    ];
}

$expectations = [
    'worker' => ['operator_actions_visible' => false, 'primary_alert_visible' => true, 'monitoring_watchlist_visible' => false, 'display_wall_visible' => false],
    'leader' => ['operator_actions_visible' => true, 'primary_alert_visible' => true, 'monitoring_watchlist_visible' => true, 'display_wall_visible' => false],
    'read_only' => ['operator_actions_visible' => false, 'primary_alert_visible' => true, 'monitoring_watchlist_visible' => true, 'display_wall_visible' => false],
    'display' => ['operator_actions_visible' => false, 'primary_alert_visible' => false, 'monitoring_watchlist_visible' => true, 'display_wall_visible' => true],
];

$failed = false;
foreach ($expectations as $mode => $checks) {
    foreach ($checks as $field => $expected) {
        $actual = (bool)($results[$mode][$field] ?? false);
        if ($actual !== $expected) {
            $failed = true;
            echo "FAIL {$mode} {$field}: expected=" . ($expected ? '1' : '0') . ' actual=' . ($actual ? '1' : '0') . "\n";
        }
    }
}

if ($failed) {
    exit(1);
}

echo "PASS profile render checks\n";
foreach ($results as $mode => $data) {
    echo $mode . ': ' . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
}
