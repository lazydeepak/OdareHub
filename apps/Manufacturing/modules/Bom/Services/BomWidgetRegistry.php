<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom\Services;

use Apps\Manufacturing\Modules\Bom\BomService;
use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class BomWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        $items = [];

        if ($region === 'summary_cards') {
            $issues = self::activeIssues();
            $items[] = [
                'widget_key' => 'bom_integrity',
                'view_kind' => 'kpi',
                'widget_type' => $issues > 0 ? 'alert' : 'informative',
                'placement_zone' => 'supporting_visibility',
                'interaction_profiles' => ['leader', 'admin', 'read_only'],
                'surface_key' => 'me',
                'supports_empty_state' => true,
                'supports_clickthrough' => true,
                'title' => t('nav.bom'),
                'value' => $issues,
                'meta' => t('bom.widget.integrity_meta'),
                'url' => '/apps/manufacturing/bom',
                'tone' => $issues > 0 ? 'warn' : 'success',
                'priority' => 16,
                'weight' => 16,
            ];
        }

        return array_merge($items, ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'bom',
            'title' => t('bom.title'),
            'url' => '/apps/manufacturing/bom',
            'table' => 'manufacturing_bom',
            'priority' => 28,
            'include_summary' => false,
        ]));
    }

    private static function activeIssues(): int
    {
        try {
            if (!BomService::tableExists('manufacturing_bom')) {
                return 0;
            }
            return count(BomService::integrityIssues());
        } catch (\Throwable $e) {
            return 0;
        }
    }
}