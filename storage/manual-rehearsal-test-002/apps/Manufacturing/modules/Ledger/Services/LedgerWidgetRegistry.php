<?php
declare(strict_types=1);

namespace Plugins\Ledger\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class LedgerWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'ledger',
            'title' => 'Ledger',
            'url' => '/ledger',
            'report_url' => '/ledger/report',
            'export_url' => '/ledger/export',
            'table' => 'stock_ledger_entries',
            'priority' => 35,
            'include_summary' => true,
        ]);
    }
}
