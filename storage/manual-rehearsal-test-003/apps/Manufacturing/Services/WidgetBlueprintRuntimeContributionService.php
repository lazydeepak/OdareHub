<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use Apps\Manufacturing\Services\OperatorLayerAdapters\AssemblyAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\CoverageAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DispatchAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MaterialsAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\QcAdapter;

final class WidgetBlueprintRuntimeContributionService
{
    /**
     * @return array<string,mixed>|null
     */
    public static function loadDataset(string $datasetKey): ?array
    {
        try {
            return match ($datasetKey) {
                'coverage.summary' => CoverageAdapter::getData(),
                'qc.status' => QcAdapter::getData(0),
                'dispatch.queue' => DispatchAdapter::getData(),
                'machines.workboard' => MachinesAdapter::getData(),
                'assembly.execution' => AssemblyAdapter::getData(0),
                'materials.pressure' => MaterialsAdapter::getData(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int,string>
     */
    public static function preferredMetricCandidates(string $datasetKey): array
    {
        return match ($datasetKey) {
            'coverage.summary' => ['summary.coverage_pct', 'summary.open_orders'],
            'qc.status' => ['kpi.pending_qc', 'kpi.run_now'],
            'dispatch.queue' => ['kpi.ready_now', 'kpi.blocked_hold'],
            'machines.workboard' => ['kpi.run_now', 'kpi.next_queue'],
            'assembly.execution' => ['kpi.in_progress', 'kpi.today_total'],
            'materials.pressure' => ['kpi.low_stock_count', 'kpi.critical_shortage'],
            default => [],
        };
    }
}
