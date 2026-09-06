<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugins\Base\Services\ResolvedExperienceConsumerService;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../plugins/Base/Services/ResolvedExperienceConsumerService.php';

final class ResolvedExperienceConsumerServiceTest extends TestCase
{
    public function testDisplayPanelsEmptyOverrideReturnsFullCatalog(): void
    {
        $panels = ResolvedExperienceConsumerService::displayPanels([
            'id' => 100,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'display_surfaces' => '',
        ]);

        sort($panels);
        $this->assertSame(['activity', 'dispatch', 'machines', 'overview', 'qc'], $panels);
    }

    public function testDisplayPanelsCsvFiltersToSelection(): void
    {
        $panels = ResolvedExperienceConsumerService::displayPanels([
            'id' => 101,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'display_surfaces' => 'overview,qc',
        ]);

        sort($panels);
        $this->assertSame(['overview', 'qc'], $panels);
    }

    public function testDisplayPanelsExplicitNoneHidesAll(): void
    {
        $panels = ResolvedExperienceConsumerService::displayPanels([
            'id' => 102,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'display_surfaces' => '__none',
        ]);

        $this->assertSame([], $panels);
    }

    public function testOperatorViewsEmptyOverridePreservesLegacyMinimalSet(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 103,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'operator_views' => '',
        ]);

        $this->assertSame(['dashboard', 'account'], $views);
    }

    public function testOperatorViewsAppliesAliasesAndAlwaysAllow(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 104,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'operator_views' => 'parts-detail,dispatch-adapter,alerts',
        ]);

        sort($views);
        $this->assertContains('parts', $views);
        $this->assertContains('dispatch', $views);
        $this->assertContains('notifications', $views);
        $this->assertContains('dashboard', $views);
        $this->assertContains('account', $views);
    }

    public function testOperatorViewsRespectsExplicitOverride(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 105,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'operator_views' => 'dashboard,production,qc',
        ]);

        sort($views);
        $this->assertSame(['account', 'dashboard', 'production', 'qc'], $views);
    }

    public function testOperatorViewsEvaluateOperatorSurfaceForPrivilegedRows(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 1051,
            'authority_role' => 'platform_admin',
            'dashboard_type' => 'platform_admin',
            'assigned_apps' => 'manufacturing,platform',
            'module_visibility' => 'production,qc',
            'operator_views' => 'dashboard,production,qc',
        ]);

        sort($views);
        $this->assertSame(['account', 'dashboard', 'production', 'qc'], $views);
    }

    public function testOperatorViewsDeriveCanonicalModulesForPrivilegedRows(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 1052,
            'authority_role' => 'platform_admin',
            'dashboard_type' => 'platform_admin',
            'assigned_apps' => 'manufacturing,platform',
            'module_visibility' => 'admin,assembly,coverage,demands,dispatch,ops,production,qc',
            'operator_views' => 'dashboard,demand,orders,parts,machines,processing,preparation,materials',
        ]);

        sort($views);
        $this->assertSame(['account', 'dashboard', 'demand', 'machines', 'materials', 'orders', 'parts', 'preparation', 'processing'], $views);
    }

    public function testAdminBlocksAndCardsMatchOverride(): void
    {
        $row = [
            'id' => 106,
            'authority_role' => 'platform_admin',
            'dashboard_type' => 'admin',
            'assigned_apps' => 'manufacturing,platform',
            'me_dashboard_blocks' => 'admin_dashboard_panels,platform_admin_tools',
            'me_plugin_cards' => 'platform_admin_dashboard,route_diagnostics',
        ];

        $blocks = ResolvedExperienceConsumerService::adminDashboardBlocks($row);
        $cards = ResolvedExperienceConsumerService::adminPluginCards($row);
        sort($blocks);
        sort($cards);

        $this->assertSame(['admin_dashboard_panels', 'platform_admin_tools'], $blocks);
        $this->assertSame(['platform_admin_dashboard', 'route_diagnostics'], $cards);
    }

    public function testAdminBlocksPrependAdminToolsForPlatformAdminWhenAbsent(): void
    {
        $row = [
            'id' => 107,
            'authority_role' => 'platform_admin',
            'dashboard_type' => 'admin',
            'assigned_apps' => 'manufacturing,platform',
            'me_dashboard_blocks' => 'operational_summary,primary_work_widgets',
        ];

        $blocks = ResolvedExperienceConsumerService::adminDashboardBlocks($row);

        $this->assertSame('admin_dashboard_panels', $blocks[0]);
        $this->assertSame('platform_admin_tools', $blocks[1]);
        $this->assertContains('operational_summary', $blocks);
        $this->assertContains('primary_work_widgets', $blocks);
    }

    public function testAdminBlocksStripPlatformOnlyForAppAdmin(): void
    {
        $row = [
            'id' => 108,
            'authority_role' => 'app_admin',
            'dashboard_type' => 'admin',
            'assigned_apps' => 'manufacturing',
            'me_dashboard_blocks' => 'admin_dashboard_panels,platform_admin_tools,operational_summary',
        ];

        $blocks = ResolvedExperienceConsumerService::adminDashboardBlocks($row);

        $this->assertNotContains('platform_admin_tools', $blocks);
        $this->assertContains('admin_dashboard_panels', $blocks);
        $this->assertContains('operational_summary', $blocks);
    }

    public function testAdminCardsStripPlatformOnlyForAppAdmin(): void
    {
        $row = [
            'id' => 109,
            'authority_role' => 'app_admin',
            'dashboard_type' => 'admin',
            'assigned_apps' => 'manufacturing',
            'me_plugin_cards' => 'platform_admin_dashboard,approval_inbox,notifications',
        ];

        $cards = ResolvedExperienceConsumerService::adminPluginCards($row);

        $this->assertNotContains('platform_admin_dashboard', $cards);
        $this->assertContains('approval_inbox', $cards);
        $this->assertContains('notifications', $cards);
    }
}
