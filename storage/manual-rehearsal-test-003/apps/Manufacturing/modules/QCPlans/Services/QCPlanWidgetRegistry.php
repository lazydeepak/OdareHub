<?php
declare(strict_types=1);

namespace Plugins\QCPlans\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class QCPlanWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'qc_plans',
            'title' => 'QC Plans',
            'url' => '/qc-plans',
            'report_url' => '/qc-plans/report',
            'export_url' => '/qc-plans/export',
            'table' => 'qc_plans',
            'priority' => 29,
            'include_summary' => true,
        ]);
    }
}
