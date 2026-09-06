<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';

use PHPUnit\Framework\TestCase;

final class MyWorkViewWidgetPlacementTest extends TestCase
{
    private string $viewPath;

    protected function setUp(): void
    {
        $this->viewPath = __DIR__ . '/../plugins/Base/Views/ops/me.php';
    }

    public function testReadOnlyModeHidesOperatorActionsButShowsMonitoringAndPrimaryWidgets(): void
    {
        $html = $this->renderForMode('read_only');

        $this->assertStringNotContainsString('Open Production', $html);
        $this->assertStringContainsString('Risk Watchlist', $html);
    }

    public function testDisplayModeShowsDisplayWallWidgets(): void
    {
        $html = $this->renderForMode('display');

        $this->assertStringContainsString('Display Wall KPI', $html);
        $this->assertStringContainsString('DISPLAY', $html);
    }

    public function testLinkOnlyWidgetsAreSkippedFromBlanketRenderer(): void
    {
        $html = $this->renderForMode('worker', [
            'header_actions' => [[
                'widget_key' => 'manufacturing_portal',
                'widget_type' => 'action',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only', 'display'],
                'label' => 'Manufacturing Portal',
                'url' => '/apps/manufacturing',
            ]],
            'summary_cards' => [[
                'widget_key' => 'high_risk_orders',
                'widget_type' => 'alert',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only', 'display'],
                'title' => 'High Risk Orders',
                'value' => 10,
                'url' => '/apps/manufacturing/demands',
            ]],
            'quick_links' => [[
                'widget_key' => 'demand_workspace',
                'widget_type' => 'reference',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only', 'display'],
                'label' => 'Demand Workspace',
                'url' => '/apps/manufacturing/demands',
            ]],
            'monitoring_sections' => [],
        ]);

        $this->assertStringNotContainsString('Manufacturing Portal', $html);
        $this->assertStringNotContainsString('High Risk Orders', $html);
        $this->assertStringNotContainsString('Demand Workspace', $html);
        $this->assertStringNotContainsString('href="/apps/manufacturing"', $html);
    }

    public function testExperienceLayoutBlocksControlRenderedHomeSections(): void
    {
        $html = $this->renderForMode('admin', null, [
            'me_dashboard_blocks' => ['monitoring_widgets'],
            'host_regions' => [
                'header_actions' => [],
                'summary_cards' => [],
                'quick_links' => [],
                'monitoring_sections' => [[
                    'widget_key' => 'risk_watch',
                    'widget_type' => 'watchlist',
                    'placement_zone' => 'monitoring',
                    'interaction_profiles' => ['admin'],
                    'title' => 'Risk Watchlist',
                    'kind' => 'table',
                    'columns' => ['Item'],
                    'rows' => [['cells' => ['Order #10']]],
                ]],
            ],
        ]);

        $this->assertStringContainsString('me-dashboard-activity-card', $html);
        $this->assertStringContainsString('User Activity', $html);
        $this->assertStringNotContainsString('me-dashboard-hero', $html);
        $this->assertStringNotContainsString('me-dashboard-cockpit', $html);
    }

    public function testExperienceLayoutPluginCardsControlQuickLinks(): void
    {
        $html = $this->renderForMode('admin', null, [
            'me_dashboard_blocks' => ['plugin_dashboards_charts'],
            'me_plugin_cards' => ['allowed_link'],
            'host_regions' => [
                'header_actions' => [],
                'summary_cards' => [[
                    'widget_key' => 'structured_summary',
                    'widget_type' => 'alert',
                    'interaction_profiles' => ['admin'],
                    'title' => 'Structured Summary',
                    'value' => 1,
                    'rows' => [['cells' => ['Structured Row']]],
                ]],
                'quick_links' => [
                    [
                        'widget_key' => 'allowed_link',
                        'widget_type' => 'reference',
                        'interaction_profiles' => ['admin'],
                        'label' => 'Allowed Link',
                        'url' => '/apps/manufacturing/allowed',
                    ],
                    [
                        'widget_key' => 'hidden_link',
                        'widget_type' => 'reference',
                        'interaction_profiles' => ['admin'],
                        'label' => 'Hidden Link',
                        'url' => '/apps/manufacturing/hidden',
                    ],
                ],
                'monitoring_sections' => [],
            ],
        ]);

        $this->assertStringContainsString('Allowed Link', $html);
        $this->assertStringNotContainsString('Hidden Link', $html);
        $this->assertStringNotContainsString('/apps/manufacturing/hidden', $html);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function renderForMode(string $mode, ?array $hostRegionsOverride = null, array $overrides = []): string
    {
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
        $defaultHostRegions = [
            'header_actions' => [[
                'widget_key' => 'open_prod',
                'widget_type' => 'action',
                'placement_zone' => 'operator_actions',
                'interaction_profiles' => ['worker', 'leader', 'admin'],
                'label' => 'Open Production',
                'url' => '/apps/manufacturing/production-operation',
            ]],
            'summary_cards' => [[
                'widget_key' => 'dispatch_blocked',
                'widget_type' => 'alert',
                'placement_zone' => 'primary_work',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'title' => 'Dispatch Blocked',
                'value' => 3,
                'meta' => 'Blocked items requiring release review.',
                'url' => '/apps/manufacturing/dispatch-ops',
            ]],
            'quick_links' => [],
            'monitoring_sections' => [
                [
                    'widget_key' => 'risk_watch',
                    'widget_type' => 'watchlist',
                    'placement_zone' => 'monitoring',
                    'interaction_profiles' => ['leader', 'read_only', 'display'],
                    'title' => 'Risk Watchlist',
                    'kind' => 'table',
                    'columns' => ['Item'],
                    'rows' => [['cells' => ['Order #10']]],
                ],
                [
                    'widget_key' => 'display_wall_kpi',
                    'widget_type' => 'display',
                    'placement_zone' => 'display_wall',
                    'interaction_profiles' => ['display'],
                    'title' => 'Display Wall KPI',
                    'kind' => 'chart',
                    'rows' => [['label' => 'Today Output', 'value' => '120']],
                ],
            ],
        ];
        $host_regions = is_array($overrides['host_regions'] ?? null)
            ? (array)$overrides['host_regions']
            : (is_array($hostRegionsOverride) ? $hostRegionsOverride : $defaultHostRegions);
        $me_dashboard_blocks = [];
        $me_plugin_cards = [];
        $me_dashboard_blocks_explicit_none = false;
        $me_plugin_cards_explicit_none = false;
        $home_module_visibility = ['demands', 'coverage', 'materials', 'dispatch', 'production', 'qc'];
        $platform_admin_tools_links = [];

        foreach ($overrides as $overrideKey => $overrideValue) {
            if ($overrideKey === 'host_regions') {
                continue;
            }
            ${$overrideKey} = $overrideValue;
        }

        $bufferLevel = ob_get_level();
        ob_start();
        try {
            include $this->viewPath;
            return (string)ob_get_clean();
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
    }
}
