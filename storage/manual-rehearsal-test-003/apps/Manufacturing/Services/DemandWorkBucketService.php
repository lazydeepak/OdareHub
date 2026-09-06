<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use Plugins\Supply\Services\SupplyModel;

final class DemandWorkBucketService
{
    /**
     * @param array<int,array<string,mixed>> $decisionRows
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,float>}
     */
    public static function bucketRows(array $decisionRows): array
    {
        $rows = [];
        $summary = [
            'production_demand_qty' => 0.0,
            'procurement_demand_qty' => 0.0,
            'assembly_demand_qty' => 0.0,
            'qc_demand_qty' => 0.0,
            'processing_demand_qty' => 0.0,
            'dispatch_demand_qty' => 0.0,
        ];

        foreach ($decisionRows as $row) {
            $bucket = self::bucketDecisionRow($row);
            $rows[] = $bucket;
            foreach ($summary as $k => $v) {
                $summary[$k] = round($summary[$k] + (float)($bucket[$k] ?? 0.0), 2);
            }
        }

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,float>}
     */
    public static function previewBuckets(?string $targetDate = null, int $limit = 50, array $productIds = []): array
    {
        $decisionRows = DemandEngineService::previewRouteDecisions($targetDate, $limit, $productIds);
        if (!$decisionRows && $productIds) {
            $decisionRows = self::fallbackPreviewRows($targetDate, $productIds, $limit);
        }
        return self::bucketRows($decisionRows);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function bucketDecisionRow(array $row): array
    {
        $decision = (array)($row['decision'] ?? []);
        $inputs = (array)($row['inputs'] ?? []);

        $netExecutionQty = round(max(0.0, (float)($decision['net_execution_qty'] ?? 0.0)), 2);
        $grossDemandQty = round(max(0.0, (float)($decision['gross_demand_qty'] ?? ($inputs['gross_demand_qty'] ?? 0.0))), 2);
        $dispatchMode = (string)($decision['dispatch_mode'] ?? '');
        $directSupplierDelivery = $dispatchMode === 'third_party_direct_dispatch';

        $productionDemandQty = self::flagQty((bool)($decision['needs_production'] ?? false), $netExecutionQty);
        $procurementDemandQty = self::flagQty((bool)($decision['needs_procurement'] ?? false), $netExecutionQty);
        $assemblyDemandQty = self::flagQty((bool)($decision['needs_assembly'] ?? false), $netExecutionQty);
        $qcDemandQty = self::flagQty((bool)($decision['needs_qc'] ?? false), $netExecutionQty);
        $processingDemandQty = self::flagQty((bool)($decision['needs_processing'] ?? false), $netExecutionQty);

        // Internal dispatch queue demand should exclude direct supplier delivery.
        $dispatchDemandQty = 0.0;
        if ((bool)($decision['needs_dispatch'] ?? false) && !$directSupplierDelivery) {
            $dispatchDemandQty = $grossDemandQty;
        }

        return [
            'demand_date' => (string)($row['demand_date'] ?? ''),
            'product_id' => (int)($row['product_id'] ?? 0),
            'execution_path_label' => (string)($decision['execution_path_label'] ?? ''),
            'route_family' => (string)($decision['route_family'] ?? ''),
            'production_demand_qty' => $productionDemandQty,
            'procurement_demand_qty' => $procurementDemandQty,
            'assembly_demand_qty' => $assemblyDemandQty,
            'qc_demand_qty' => $qcDemandQty,
            'processing_demand_qty' => $processingDemandQty,
            'dispatch_demand_qty' => $dispatchDemandQty,
            'source' => [
                'net_execution_qty' => $netExecutionQty,
                'gross_demand_qty' => $grossDemandQty,
                'effective_supply_qty' => round(max(0.0, (float)($decision['effective_supply_qty'] ?? 0.0)), 2),
                'dispatch_mode' => $dispatchMode,
                'direct_supplier_delivery' => $directSupplierDelivery,
                'needs_production' => (bool)($decision['needs_production'] ?? false),
                'needs_procurement' => (bool)($decision['needs_procurement'] ?? false),
                'needs_assembly' => (bool)($decision['needs_assembly'] ?? false),
                'needs_qc' => (bool)($decision['needs_qc'] ?? false),
                'needs_processing' => (bool)($decision['needs_processing'] ?? false),
                'needs_dispatch' => (bool)($decision['needs_dispatch'] ?? false),
                'internal_execution_required' => (bool)($decision['internal_execution_required'] ?? false),
            ],
        ];
    }

    private static function flagQty(bool $flag, float $qty): float
    {
        return $flag ? round(max(0.0, $qty), 2) : 0.0;
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array<string,mixed>>
     */
    private static function fallbackPreviewRows(?string $targetDate, array $productIds, int $limit): array
    {
        $productIds = array_values(array_filter(array_map(static fn($v): int => (int)$v, $productIds), static fn(int $v): bool => $v > 0));
        if (!$productIds) {
            return [];
        }

        $holders = implode(',', array_fill(0, count($productIds), '?'));
        $rows = DB::fetchAll(
            "SELECT id, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, dispatch_as_is
             FROM products
             WHERE id IN ({$holders})",
            $productIds
        );

        if (!$rows) {
            return [];
        }

        $date = $targetDate !== null && $targetDate !== '' ? $targetDate : date('Y-m-d');
        $limit = max(1, min(200, $limit));
        $out = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $decision = DemandRouteDecisionService::decide(
                [
                    'supply_mode' => (string)($row['supply_mode'] ?? SupplyModel::SUPPLY_IN_HOUSE),
                    'fulfillment_mode' => (string)($row['fulfillment_mode'] ?? 'company_to_destination'),
                    'requires_ipm_qc' => (int)($row['requires_ipm_qc'] ?? 0),
                    'requires_assembly' => (int)($row['requires_assembly'] ?? 0),
                    'dispatch_as_is' => (int)($row['dispatch_as_is'] ?? 0),
                ],
                [
                    'daily_order_qty' => 10.0,
                    'buffer_recovery_need' => 0.0,
                    'available_stock_qty' => 0.0,
                    'planned_stock_qty' => 0.0,
                ]
            );

            $out[] = [
                'product_id' => (int)$row['id'],
                'demand_date' => $date,
                'inputs' => [
                    'pre_orders_qty' => 0.0,
                    'daily_orders_qty' => 10.0,
                    'gross_demand_qty' => 10.0,
                    'available_stock_qty' => 0.0,
                    'planned_stock_qty' => 0.0,
                    'buffer_recovery_need' => 0.0,
                ],
                'decision' => $decision,
                'preview_source' => 'fallback_profile_context',
            ];
        }

        return $out;
    }
}
