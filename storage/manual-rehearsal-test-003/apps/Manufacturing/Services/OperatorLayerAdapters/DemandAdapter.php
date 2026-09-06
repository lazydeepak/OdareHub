<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Plugins\Products\Services\PartsMasterSnapshotService;

final class DemandAdapter
{
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function getData(array $query = [], int $userId = 0): array
    {
        $defaults = [
            'summary' => [
                'parts_count' => 0,
                'shortfall_count' => 0,
                'total_shortfall_qty' => 0.0,
                'total_target_qty' => 0.0,
                'total_demand_qty' => 0.0,
                'total_safety_stock_qty' => 0.0,
            ],
            'rows' => [],
            'anchor_date' => date('Y-m-d'),
            'scope_mode' => 'my',
            'empty' => true,
            'error' => '',
        ];

        try {
            $servicePath = APP_ROOT . '/apps/Manufacturing/modules/Products/Services/PartsMasterSnapshotService.php';
            if (!class_exists(PartsMasterSnapshotService::class, false) && is_file($servicePath)) {
                require_once $servicePath;
            }

            if (!class_exists(PartsMasterSnapshotService::class)) {
                throw new \RuntimeException('Demand snapshot service is not available.');
            }

            $anchorDate = self::dateQuery((string)($query['date'] ?? date('Y-m-d')));
            $scope = trim((string)($query['scope'] ?? 'my'));
            $snapshot = PartsMasterSnapshotService::build(
                trim((string)($query['q'] ?? '')),
                '1',
                $anchorDate,
                $userId,
                $scope,
                'coverage_balance_qty',
                'asc'
            );

            $rows = array_values(array_map([self::class, 'shapeRow'], (array)($snapshot['rows'] ?? [])));
            $defaults['rows'] = $rows;
            $defaults['anchor_date'] = (string)($snapshot['anchor_date'] ?? $anchorDate);
            $defaults['scope_mode'] = (string)($snapshot['scope_mode'] ?? 'my');
            $defaults['summary'] = self::summarize($rows);
            $defaults['empty'] = ($rows === []);
        } catch (\Throwable $e) {
            error_log('DemandAdapter::getData: ' . $e->getMessage());
            $defaults['error'] = 'operator.demand.error.load';
        }

        return $defaults;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function shapeRow(array $row): array
    {
        $demandQty = round(max(0.0, (float)($row['target_demand_qty'] ?? 0)), 2);
        $safetyQty = round(max(0.0, (float)($row['safety_stock_qty'] ?? 0)), 2);
        $targetQty = round(max(0.0, (float)($row['target_today_qty'] ?? ($demandQty + $safetyQty))), 2);
        $stockQty = round((float)($row['stock_qty'] ?? 0), 2);
        $balanceQty = round((float)($row['coverage_balance_qty'] ?? ($stockQty - $targetQty)), 2);
        $shortfallQty = round(max(0.0, -$balanceQty), 2);

        return [
            'part_id' => (int)($row['part_id'] ?? 0),
            'part_name' => (string)($row['part_name'] ?? ''),
            'part_number' => (string)($row['part_number'] ?? ''),
            'lead_workdays' => (int)($row['lead_workdays'] ?? 0),
            'target_date' => (string)($row['target_date'] ?? ''),
            'demand_qty' => $demandQty,
            'safety_stock_qty' => $safetyQty,
            'target_qty' => $targetQty,
            'stock_qty' => $stockQty,
            'balance_qty' => $balanceQty,
            'shortfall_qty' => $shortfallQty,
            'tone' => $shortfallQty > 0 ? 'danger' : ($balanceQty <= $safetyQty ? 'warning' : 'success'),
            'is_assigned' => !empty($row['is_assigned']),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int|float>
     */
    private static function summarize(array $rows): array
    {
        $summary = [
            'parts_count' => count($rows),
            'shortfall_count' => 0,
            'total_shortfall_qty' => 0.0,
            'total_target_qty' => 0.0,
            'total_demand_qty' => 0.0,
            'total_safety_stock_qty' => 0.0,
        ];

        foreach ($rows as $row) {
            $shortfall = (float)($row['shortfall_qty'] ?? 0);
            if ($shortfall > 0) {
                $summary['shortfall_count']++;
                $summary['total_shortfall_qty'] += $shortfall;
            }
            $summary['total_target_qty'] += (float)($row['target_qty'] ?? 0);
            $summary['total_demand_qty'] += (float)($row['demand_qty'] ?? 0);
            $summary['total_safety_stock_qty'] += (float)($row['safety_stock_qty'] ?? 0);
        }

        foreach (['total_shortfall_qty', 'total_target_qty', 'total_demand_qty', 'total_safety_stock_qty'] as $key) {
            $summary[$key] = round((float)$summary[$key], 2);
        }

        return $summary;
    }

    private static function dateQuery(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : date('Y-m-d');
    }
}
