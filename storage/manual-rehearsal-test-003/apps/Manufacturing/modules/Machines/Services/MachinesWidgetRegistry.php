<?php
declare(strict_types=1);

namespace Plugins\Machines\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class MachinesWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'machines',
            'title' => 'Machines',
            'url' => '/machines',
            'report_url' => '/machines/report',
            'export_url' => '/machines/export',
            'table' => 'machines',
            'priority' => 23,
            'include_summary' => true,
        ]);
    }
}
