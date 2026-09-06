<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../plugins/Base/Services/HostSurfaceRegistryService.php';

use PHPUnit\Framework\TestCase;
use Plugins\Base\Services\HostSurfaceRegistryService;

final class HostSurfaceRegistryServiceTaxonomyTest extends TestCase
{
    public function testNormalizeTaxonomyInfersActionDefaultsFromHeaderRegion(): void
    {
        $item = [
            'label' => 'Open Production',
            'url' => '/apps/manufacturing/production-operation',
        ];

        $normalized = $this->normalizeTaxonomy($item, 'me', 'header_actions', 'manufacturing');

        $this->assertSame('action', $normalized['widget_type']);
        $this->assertSame('operator_actions', $normalized['placement_zone']);
        $this->assertSame(['worker', 'leader', 'admin'], $normalized['interaction_profiles']);
        $this->assertTrue($normalized['supports_clickthrough']);
        $this->assertSame('manufacturing', $normalized['app_key']);
    }

    public function testNormalizeTaxonomyInfersWatchlistFromMonitoringKind(): void
    {
        $item = [
            'title' => 'Risk Watchlist',
            'kind' => 'watchlist',
            'rows' => [],
        ];

        $normalized = $this->normalizeTaxonomy($item, 'me', 'monitoring_sections', 'manufacturing');

        $this->assertSame('watchlist', $normalized['widget_type']);
        $this->assertSame('monitoring', $normalized['placement_zone']);
        $this->assertSame(['worker', 'leader', 'admin', 'read_only'], $normalized['interaction_profiles']);
    }

    public function testDisplayPlacementDefaultsInteractionProfileToDisplayOnly(): void
    {
        $item = [
            'widget_type' => 'display',
            'title' => 'Display Wall',
        ];

        $normalized = $this->normalizeTaxonomy($item, 'me', 'monitoring_sections', 'manufacturing');

        $this->assertSame('display_wall', $normalized['placement_zone']);
        $this->assertSame(['display'], $normalized['interaction_profiles']);
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function normalizeTaxonomy(array $item, string $surface, string $region, string $appKey): array
    {
        $invoker = \Closure::bind(
            static function (array $rawItem, string $rawSurface, string $rawRegion, string $rawAppKey): array {
                return HostSurfaceRegistryService::normalizeTaxonomy($rawItem, $rawSurface, $rawRegion, $rawAppKey);
            },
            null,
            HostSurfaceRegistryService::class
        );

        return $invoker($item, $surface, $region, $appKey);
    }
}
