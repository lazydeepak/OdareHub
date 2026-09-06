<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugins\Base\Services\ResolvedExperienceDiagnosticsService;
use Plugins\Base\Services\UserDashboardAssignmentService;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../plugins/Base/Services/ResolvedExperienceDiagnosticsService.php';
require_once __DIR__ . '/../plugins/Base/Services/SuitePermissionTemplateService.php';
require_once __DIR__ . '/../plugins/Base/Services/UserDashboardAssignmentService.php';

final class UserDashboardAssignmentServiceTest extends TestCase
{
    public function testPlatformAdminCoreQuickLinksAreExposed(): void
    {
        $links = UserDashboardAssignmentService::meCoreQuickLinks([
            'authority_role' => 'platform_admin',
        ]);

        $keys = array_map(
            static fn(array $link): string => (string)($link['key'] ?? ''),
            $links
        );

        $this->assertSame([
            'platform_admin_dashboard',
            'access_control_board',
            'user_control_board',
            'display_manager',
            'admin_tools',
            'route_diagnostics',
            'navigation_tree',
            'platform_setup',
        ], $keys);
    }

    public function testNonPlatformAdminsDoNotReceiveCoreQuickLinks(): void
    {
        $this->assertSame([], UserDashboardAssignmentService::meCoreQuickLinks([
            'authority_role' => 'app_user',
        ]));
    }

    public function testPluginCardCatalogIncludesPlatformCoreCards(): void
    {
        $catalog = UserDashboardAssignmentService::mePluginCardCatalog();

        $this->assertArrayHasKey('platform_admin_dashboard', $catalog);
        $this->assertArrayHasKey('platform_setup', $catalog);
        $this->assertArrayHasKey('access_control_board', $catalog);
        $this->assertArrayHasKey('user_dashboard', $catalog);
        $this->assertArrayHasKey('admin_tools', $catalog);
        $this->assertArrayHasKey('route_diagnostics', $catalog);
    }

    public function testMeDashboardBlockCatalogIncludesPlatformAdminToolsBlock(): void
    {
        $catalog = UserDashboardAssignmentService::meDashboardBlockCatalog();

        $this->assertArrayHasKey('platform_admin_tools', $catalog);
        $this->assertSame('Platform Admin Tools', $catalog['platform_admin_tools']);
        $this->assertSame('Operational Summary', $catalog['operational_summary']);
    }

    public function testPlatformAdminBlockPolicyMovesToolsBlockToTopOfBodySequence(): void
    {
        $method = new ReflectionMethod(UserDashboardAssignmentService::class, 'normalizeMeDashboardBlocksForAccountType');

        $blocks = UserDashboardAssignmentService::resolveMeDashboardBlocks(
            'top_navigation_module_launcher,global_controls,search_alerts,operational_summary,primary_work_widgets,monitoring_widgets,detailed_work_tables,plugin_dashboards_charts'
        );

        $normalized = $method->invoke(null, $blocks, 'platform_admin');

        $this->assertSame([
            'admin_dashboard_panels',
            'platform_admin_tools',
            'top_navigation_module_launcher',
            'global_controls',
            'search_alerts',
            'operational_summary',
            'primary_work_widgets',
            'monitoring_widgets',
            'detailed_work_tables',
            'plugin_dashboards_charts',
        ], $normalized);
    }

    public function testNonPlatformAdminBlockPolicyDoesNotAddToolsBlock(): void
    {
        $method = new ReflectionMethod(UserDashboardAssignmentService::class, 'normalizeMeDashboardBlocksForAccountType');

        $blocks = UserDashboardAssignmentService::resolveMeDashboardBlocks(
            'operational_summary,primary_work_widgets,detailed_work_tables'
        );

        $normalized = $method->invoke(null, $blocks, 'app_user');

        $this->assertSame([
            'operational_summary',
            'primary_work_widgets',
            'detailed_work_tables',
        ], $normalized);
    }

    public function testUserDashboardRouteIsClassifiedAsPlatformSurface(): void
    {
        $appMethod = new ReflectionMethod(UserDashboardAssignmentService::class, 'appForPath');
        $platformMethod = new ReflectionMethod(UserDashboardAssignmentService::class, 'isPlatformOnlyPath');

        $this->assertSame('platform', $appMethod->invoke(null, '/ops/user-dashboard'));
        $this->assertTrue($platformMethod->invoke(null, '/ops/user-dashboard'));
    }

    public function testNonPlatformUsersDoNotInheritPlatformPluginCardsByDefault(): void
    {
        $method = new ReflectionMethod(UserDashboardAssignmentService::class, 'normalizeMePluginCardsForContext');

        $cards = UserDashboardAssignmentService::resolveMePluginCards('');
        $normalized = $method->invoke(null, $cards, 'app_user', 'production_leader', ['manufacturing']);

        $this->assertNotContains('platform_admin_dashboard', $normalized);
        $this->assertNotContains('platform_setup', $normalized);
        $this->assertNotContains('access_control_board', $normalized);
        $this->assertNotContains('admin_tools', $normalized);
        $this->assertContains('approval_inbox', $normalized);
        $this->assertContains('notifications', $normalized);
    }

    public function testPlatformPluginCardsStillReachPlatformAdmins(): void
    {
        $method = new ReflectionMethod(UserDashboardAssignmentService::class, 'normalizeMePluginCardsForContext');

        $cards = UserDashboardAssignmentService::resolveMePluginCards('');
        $normalized = $method->invoke(null, $cards, 'platform_admin', 'platform_admin', ['platform', 'manufacturing']);

        $this->assertContains('platform_admin_dashboard', $normalized);
        $this->assertContains('platform_setup', $normalized);
        $this->assertContains('access_control_board', $normalized);
        $this->assertContains('admin_tools', $normalized);
    }

    public function testExplicitNoneExperienceLayoutTokenDisablesBlocksAndCards(): void
    {
        $this->assertSame([], UserDashboardAssignmentService::resolveMeDashboardBlocks('__none'));
        $this->assertSame([], UserDashboardAssignmentService::resolveMePluginCards('__none'));
    }

    public function testAssignmentUiConfigPublishesModeledSurfaceDefinitions(): void
    {
        $config = UserDashboardAssignmentService::assignmentUiConfig();
        $definitions = (array)($config['surfaceDefinitions'] ?? []);

        $this->assertArrayHasKey('production_queue', $definitions);
        $this->assertArrayHasKey('qc_queue', $definitions);
        $this->assertArrayHasKey('dispatch_ops', $definitions);
        $this->assertArrayHasKey('coverage_dashboard', $definitions);
        $this->assertArrayHasKey('material_stock', $definitions);
    }

    public function testResolvedExperienceDiagnosticsDetectOperatorOverrideHiddenView(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 7,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'operator_views' => 'dashboard,production',
            'workspace_profile_key' => 'manufacturing_operator',
        ], [
            'profile_key' => 'manufacturing_operator',
            'name' => 'Manufacturing Operator',
        ]);

        $this->assertSame('operator', $diagnostics['surface']);
        $items = array_column((array)$diagnostics['items'], null, 'key');

        $this->assertTrue($items['operator.view.account']['visible']);
        $this->assertTrue($items['operator.view.production']['visible']);
        $this->assertFalse($items['operator.view.qc']['visible']);
        $this->assertSame('user_override_hidden', $items['operator.view.qc']['hidden_reason']);
        $this->assertSame([], $diagnostics['runtime_parity']['missing_in_diagnostics']);
        $this->assertSame([], $diagnostics['runtime_parity']['extra_in_diagnostics']);
    }

    public function testResolvedExperienceDiagnosticsDetectDisplayPanelSelection(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 8,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'display_surfaces' => 'overview,qc',
            'workspace_profile_key' => 'tv_display_floor',
        ], [
            'profile_key' => 'tv_display_floor',
            'name' => 'TV Display Floor',
        ]);

        $this->assertSame('display', $diagnostics['surface']);
        $items = array_column((array)$diagnostics['items'], null, 'key');

        $this->assertTrue($items['display.panel.overview']['visible']);
        $this->assertTrue($items['display.panel.qc']['visible']);
        $this->assertFalse($items['display.panel.dispatch']['visible']);
        $this->assertSame('user_override_hidden', $items['display.panel.dispatch']['hidden_reason']);
    }

    public function testResolvedExperienceDiagnosticsReportsStaleOperatorOverrideTokens(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 9,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'operator_views' => 'dashboard,parts-detail,ghost-view',
            'workspace_profile_key' => 'manufacturing_operator',
        ]);

        $stale = (array)$diagnostics['stale_references'];

        $this->assertSame(1, $diagnostics['summary']['stale_reference_count']);
        $this->assertSame('user_override', $stale[0]['source']);
        $this->assertSame('operator_views', $stale[0]['field']);
        $this->assertSame('ghost-view', $stale[0]['token']);
        $this->assertSame('ghost-view', $stale[0]['normalized_token']);
        $this->assertContains('parts', $diagnostics['runtime_parity']['runtime_expected_tokens']);
    }

    public function testResolvedExperienceDiagnosticsReportsStaleDisplayOverrideTokens(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 10,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'display_surfaces' => 'overview,ghost-panel',
            'workspace_profile_key' => 'tv_display_floor',
        ]);

        $stale = (array)$diagnostics['stale_references'];

        $this->assertSame(1, $diagnostics['summary']['stale_reference_count']);
        $this->assertSame('display_surfaces', $stale[0]['field']);
        $this->assertSame('ghost-panel', $stale[0]['token']);
    }

    public function testResolvedExperienceDiagnosticsReportsStaleWorkspaceProfileNavTokens(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 11,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'operator_views' => '',
            'workspace_profile_key' => 'custom_operator',
        ], [
            'profile_key' => 'custom_operator',
            'name' => 'Custom Operator',
            'nav_sections' => [
                [
                    'title' => 'Work',
                    'items' => [
                        ['key' => 'production', 'route' => '/u/{user}/production'],
                        ['key' => 'ghost-zone', 'route' => '/u/{user}/ghost-zone'],
                    ],
                ],
            ],
        ]);

        $stale = (array)$diagnostics['stale_references'];

        $this->assertSame(1, $diagnostics['summary']['stale_reference_count']);
        $this->assertSame('workspace_profile', $stale[0]['source']);
        $this->assertSame('nav_sections', $stale[0]['field']);
        $this->assertSame('ghost-zone', $stale[0]['token']);
    }

    public function testResolveForProfileBuildsProfileOnlyDiagnostics(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolveForProfile([
            'profile_key' => 'manufacturing_operator',
            'name' => 'Manufacturing Operator',
            'authority_role' => 'app_user',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => json_encode([
                'production_entries' => 'work',
                'qc_entries' => 'none',
            ]),
        ]);

        $this->assertSame('profile_only', $diagnostics['mode']);
        $this->assertSame('operator', $diagnostics['surface']);
        $this->assertSame('manufacturing_operator', $diagnostics['workspace_profile']['profile_key']);
        $this->assertSame(0, $diagnostics['target_user_id']);
        $this->assertSame(0, $diagnostics['summary']['user_override_hidden_count']);
    }

    public function testResolveForProfileFlagsStaleAssignedAppToken(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolveForProfile([
            'profile_key' => 'tv_display_floor',
            'name' => 'TV Display Floor',
            'authority_role' => 'tv_display',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => null,
        ]);

        $this->assertSame('display', $diagnostics['surface']);
        $this->assertSame('profile_only', $diagnostics['mode']);
        $this->assertGreaterThanOrEqual(0, $diagnostics['summary']['stale_reference_count']);
    }

    public function testRuntimeParityDisplayDefaultsToFullPanelSet(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 30,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'display_surfaces' => '',
        ]);

        $this->assertSame([], $diagnostics['runtime_parity']['missing_in_diagnostics']);
        $this->assertSame([], $diagnostics['runtime_parity']['extra_in_diagnostics']);
        $visible = self::visibleTokens($diagnostics, 'display', 'panel');
        sort($visible);
        $this->assertSame(['activity', 'dispatch', 'machines', 'overview', 'qc'], $visible);
    }

    public function testRuntimeParityOperatorAppliesAliasAndAlwaysAllowTokens(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 31,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => '',
            'operator_views' => 'parts-detail,dispatch-adapter,alerts',
        ]);

        $this->assertSame([], $diagnostics['runtime_parity']['missing_in_diagnostics']);
        $this->assertSame([], $diagnostics['runtime_parity']['extra_in_diagnostics']);
        $visible = self::visibleTokens($diagnostics, 'operator', 'view');
        $this->assertContains('parts', $visible);
        $this->assertContains('dispatch', $visible);
        $this->assertContains('notifications', $visible);
        $this->assertContains('dashboard', $visible);
        $this->assertContains('account', $visible);
    }

    public function testRuntimeParityAdminBlocksAndCardsMatchOverrideSelection(): void
    {
        $diagnostics = ResolvedExperienceDiagnosticsService::resolve([
            'id' => 32,
            'authority_role' => 'platform_admin',
            'dashboard_type' => 'admin',
            'assigned_apps' => 'manufacturing,platform',
            'module_visibility' => '',
            'me_dashboard_blocks' => 'admin_dashboard_panels,platform_admin_tools',
            'me_plugin_cards' => 'platform_admin_dashboard,route_diagnostics',
        ]);

        $this->assertSame([], $diagnostics['runtime_parity']['missing_in_diagnostics']);
        $this->assertSame([], $diagnostics['runtime_parity']['extra_in_diagnostics']);
        $blocks = self::visibleTokens($diagnostics, 'admin', 'dashboard_block');
        $cards = self::visibleTokens($diagnostics, 'admin', 'quick_link_card');
        sort($blocks);
        sort($cards);
        $this->assertSame(['admin_dashboard_panels', 'platform_admin_tools'], $blocks);
        $this->assertSame(['platform_admin_dashboard', 'route_diagnostics'], $cards);
    }

    /**
     * @param array<string,mixed> $diagnostics
     * @return array<int,string>
     */
    private static function visibleTokens(array $diagnostics, string $surface, string $kind): array
    {
        $out = [];
        foreach ((array)($diagnostics['items'] ?? []) as $item) {
            if (($item['surface'] ?? '') === $surface
                && ($item['kind'] ?? '') === $kind
                && !empty($item['visible'])
            ) {
                $key = (string)($item['key'] ?? '');
                $token = '';
                if (($pos = strrpos($key, '.')) !== false) {
                    $token = substr($key, $pos + 1);
                }
                if ($token !== '') {
                    $out[] = $token;
                }
            }
        }
        return $out;
    }
}
