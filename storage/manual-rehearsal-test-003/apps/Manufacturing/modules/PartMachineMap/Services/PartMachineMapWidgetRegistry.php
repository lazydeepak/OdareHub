<?php
declare(strict_types=1);

namespace Plugins\PartMachineMap\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class PartMachineMapWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'part_machine_map',
            'title' => 'Part-Machine Map',
            'url' => '/part-machine-map',
            'report_url' => '/part-machine-map/report',
            'export_url' => '/part-machine-map/export',
            'table' => 'part_machine_map',
            'priority' => 33,
            'include_summary' => true,
        ]);
    }
}
