<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugins\Base\Services\UserSurfaceOverrideService;

require_once __DIR__ . '/../plugins/Base/Services/UserSurfaceOverrideService.php';

final class UserSurfaceOverrideServiceTest extends TestCase
{
    public function testRetirementReadinessAllowsRetirementWhenNoInlineOnlyValuesRemain(): void
    {
        $result = UserSurfaceOverrideService::retirementReadiness([
            'display_surfaces' => ['artifact_rows' => 2, 'inline_only' => 0, 'inline_total' => 2],
            'operator_views' => ['artifact_rows' => 3, 'inline_only' => 0, 'inline_total' => 3],
            'me_dashboard_blocks' => ['artifact_rows' => 1, 'inline_only' => 0, 'inline_total' => 1],
            'me_plugin_cards' => ['artifact_rows' => 1, 'inline_only' => 0, 'inline_total' => 1],
        ]);

        $this->assertSame('user_surface_overrides.retirement_readiness.v1', $result['version']);
        $this->assertTrue($result['can_retire_all']);
        $this->assertTrue($result['fields']['operator_views']['can_retire']);
        $this->assertSame('artifact_coverage_complete', $result['fields']['operator_views']['reason']);
    }

    public function testRetirementReadinessBlocksRetirementWhenInlineOnlyValuesRemain(): void
    {
        $result = UserSurfaceOverrideService::retirementReadiness([
            'display_surfaces' => ['artifact_rows' => 2, 'inline_only' => 0, 'inline_total' => 2],
            'operator_views' => ['artifact_rows' => 1, 'inline_only' => 2, 'inline_total' => 3],
            'me_dashboard_blocks' => ['artifact_rows' => 1, 'inline_only' => 0, 'inline_total' => 1],
            'me_plugin_cards' => ['artifact_rows' => 1, 'inline_only' => 0, 'inline_total' => 1],
        ]);

        $this->assertFalse($result['can_retire_all']);
        $this->assertFalse($result['fields']['operator_views']['can_retire']);
        $this->assertSame('inline_only_values_remain', $result['fields']['operator_views']['reason']);
        $this->assertSame(2, $result['fields']['operator_views']['inline_only']);
    }

    public function testInlineWritePolicyDisablesAllInlineWritesWhenRetirementIsReady(): void
    {
        $policy = UserSurfaceOverrideService::inlineWritePolicy([
            'fields' => [
                'display_surfaces' => ['can_retire' => true],
                'operator_views' => ['can_retire' => true],
                'me_dashboard_blocks' => ['can_retire' => true],
                'me_plugin_cards' => ['can_retire' => true],
            ],
        ]);

        $this->assertFalse($policy['display_surfaces']);
        $this->assertFalse($policy['operator_views']);
        $this->assertFalse($policy['me_dashboard_blocks']);
        $this->assertFalse($policy['me_plugin_cards']);
    }

    public function testInlineWritePolicyKeepsInlineWritesEnabledForBlockedFields(): void
    {
        $policy = UserSurfaceOverrideService::inlineWritePolicy([
            'fields' => [
                'display_surfaces' => ['can_retire' => true],
                'operator_views' => ['can_retire' => false],
                'me_dashboard_blocks' => ['can_retire' => true],
                'me_plugin_cards' => ['can_retire' => false],
            ],
        ]);

        $this->assertFalse($policy['display_surfaces']);
        $this->assertTrue($policy['operator_views']);
        $this->assertFalse($policy['me_dashboard_blocks']);
        $this->assertTrue($policy['me_plugin_cards']);
    }
}
