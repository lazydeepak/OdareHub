<?php
declare(strict_types=1);

namespace Plugins\PreOrders\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class PreOrderWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'pre_orders',
            'title' => 'Pre Orders',
            'url' => '/pre-orders',
            'report_url' => '/pre-orders/report',
            'export_url' => '/pre-orders/export',
            'table' => 'pre_orders',
            'priority' => 34,
            'include_summary' => true,
        ]);
    }
}
