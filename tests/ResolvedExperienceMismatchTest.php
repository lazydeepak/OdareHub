<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugins\Base\Services\ResolvedExperienceConsumerService;
use Plugins\Base\Services\ResolvedExperienceDiagnosticsService;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../plugins/Base/Services/ResolvedExperienceConsumerService.php';

/**
 * Phase 5 regression coverage for route/nav/content mismatches between the
 * Resolved Experience pipeline and the runtime catalogs. Each test pins down
 * an invariant that the cutover must preserve so future refactors get a fast
 * failure rather than a silent runtime drift.
 */
final class ResolvedExperienceMismatchTest extends TestCase
{
    public function testOperatorCatalogRoutesAreOperatorScoped(): void
    {
        $catalog = ResolvedExperienceDiagnosticsService::capabilityCatalog('operator');
        $this->assertNotEmpty($catalog);
        foreach ($catalog as $capability) {
            $route = (string)($capability['route'] ?? '');
            $this->assertStringStartsWith('/u/{user}/', $route, "operator route should be /u/{user}/* — got {$route}");
            $this->assertSame('operator', (string)($capability['surface'] ?? ''));
            $this->assertSame('view', (string)($capability['kind'] ?? ''));
            $this->assertSame('operator_views', (string)($capability['override_field'] ?? ''));
        }
    }

    public function testDisplayCatalogRoutesAreDisplayScoped(): void
    {
        $catalog = ResolvedExperienceDiagnosticsService::capabilityCatalog('display');
        $this->assertNotEmpty($catalog);
        foreach ($catalog as $capability) {
            $route = (string)($capability['route'] ?? '');
            $this->assertStringStartsWith('/displays/', $route, "display route should be /displays/* — got {$route}");
            $this->assertSame('display', (string)($capability['surface'] ?? ''));
            $this->assertSame('panel', (string)($capability['kind'] ?? ''));
            $this->assertSame('display_surfaces', (string)($capability['override_field'] ?? ''));
        }
    }

    public function testAdminCatalogRoutesAreAdminOrOpsScoped(): void
    {
        $catalog = ResolvedExperienceDiagnosticsService::capabilityCatalog('admin');
        $this->assertNotEmpty($catalog);
        foreach ($catalog as $capability) {
            $route = (string)($capability['route'] ?? '');
            $this->assertMatchesRegularExpression(
                '#^/(admin|ops|me)(/|$)#',
                $route,
                "admin route should live under /admin, /ops, or /me — got {$route}"
            );
            $this->assertSame('admin', (string)($capability['surface'] ?? ''));
            $this->assertContains((string)($capability['kind'] ?? ''), ['dashboard_block', 'quick_link_card']);
        }
    }

    public function testConsumerOperatorOutputIsSubsetOfCatalog(): void
    {
        $row = [
            'id' => 200,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing,sbaio',
            'operator_views' => 'production,qc,sbaio,parts-detail',
        ];
        $catalogTokens = self::catalogTokens('operator');
        $views = ResolvedExperienceConsumerService::operatorViews($row);
        foreach ($views as $view) {
            $this->assertContains(
                $view,
                $catalogTokens,
                "operator view {$view} is not present in catalog — consumer/catalog drift"
            );
        }
    }

    public function testConsumerDisplayOutputIsSubsetOfCatalog(): void
    {
        $row = [
            'id' => 201,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'display_surfaces' => 'overview,qc,activity',
        ];
        $catalogTokens = self::catalogTokens('display');
        $panels = ResolvedExperienceConsumerService::displayPanels($row);
        foreach ($panels as $panel) {
            $this->assertContains($panel, $catalogTokens);
        }
    }

    public function testOperatorViewWithoutAssignedAppIsHidden(): void
    {
        $row = [
            'id' => 202,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'sbaio',
            'operator_views' => 'production,qc,sbaio',
        ];
        $views = ResolvedExperienceConsumerService::operatorViews($row);
        $this->assertNotContains('production', $views, 'manufacturing view leaked through ACL when manufacturing not assigned');
        $this->assertNotContains('qc', $views);
        $this->assertContains('sbaio', $views);
        $this->assertContains('dashboard', $views);
        $this->assertContains('account', $views);
    }

    public function testOperatorViewBlockedByModuleVisibility(): void
    {
        $row = [
            'id' => 203,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => 'production',
            'operator_views' => 'production,qc',
        ];
        $views = ResolvedExperienceConsumerService::operatorViews($row);
        $this->assertContains('production', $views);
        $this->assertNotContains('qc', $views, 'qc view should be hidden when module_visibility excludes qc');
    }

    public function testDisplayPanelHiddenWhenModuleNotVisible(): void
    {
        $row = [
            'id' => 204,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'module_visibility' => 'dispatch',
            'display_surfaces' => '',
        ];
        $panels = ResolvedExperienceConsumerService::displayPanels($row);
        $this->assertContains('overview', $panels, 'overview should always be visible (no module gate)');
        $this->assertContains('dispatch', $panels);
        $this->assertNotContains('qc', $panels, 'qc panel should be hidden when module_visibility excludes qc');
    }

    public function testOperatorAlwaysAllowSurvivesRestrictiveOverride(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 205,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'operator_views' => 'production',
        ]);
        $this->assertContains('dashboard', $views);
        $this->assertContains('account', $views);
        $this->assertContains('production', $views);
    }

    public function testExplicitNoneHidesAllDisplayPanels(): void
    {
        $panels = ResolvedExperienceConsumerService::displayPanels([
            'id' => 206,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'display_surfaces' => '__none',
        ]);
        $this->assertSame([], $panels);
    }

    public function testTvDisplayCannotAccessOperatorSurface(): void
    {
        $views = ResolvedExperienceConsumerService::operatorViews([
            'id' => 207,
            'authority_role' => 'tv_display',
            'dashboard_type' => 'display',
            'assigned_apps' => 'manufacturing',
            'operator_views' => 'production',
        ]);
        $this->assertNotContains('production', $views, 'tv_display must be jailed away from operator surface');
    }

    public function testAdminSurfaceBlockedForNonAdminAuthority(): void
    {
        $blocks = ResolvedExperienceConsumerService::adminDashboardBlocks([
            'id' => 208,
            'authority_role' => 'app_user',
            'dashboard_type' => 'operator',
            'assigned_apps' => 'manufacturing',
            'me_dashboard_blocks' => 'operational_summary,primary_work_widgets',
        ]);
        $this->assertSame([], $blocks, 'admin blocks should be empty for non-admin authority');
    }

    /**
     * @return array<int,string>
     */
    private static function catalogTokens(string $surface): array
    {
        $tokens = [];
        foreach (ResolvedExperienceDiagnosticsService::capabilityCatalog($surface) as $capability) {
            $token = (string)($capability['token'] ?? '');
            if ($token !== '') {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
}
