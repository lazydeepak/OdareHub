<?php
declare(strict_types=1);

namespace Plugins\MaterialManagement\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\DemandEngineService;

final class MaterialManagementService
{
    /**
     * @var array<string,array<int,string>>
     */
    private static array $tableColumns = [];

    /**
     * @var array<int,array<string,mixed>>|null
     */
    private static ?array $coverageModelCache = null;

    /**
     * @var array<int,array<string,mixed>>|null
     */
    private static ?array $planningRowsCache = null;

    /**
     * @var array<int,array<string,mixed>>|null
     */
    private static ?array $demandSummaryCache = null;

    /**
     * @var array<string,string>
     */
    private const MOVEMENT_ALIASES = [
        'opening' => 'opening',
        'receipt' => 'receipt',
        'return' => 'return',
        'issue_to_production' => 'issue_to_production',
        'adjustment_plus' => 'adjustment_plus',
        'adjustment_minus' => 'adjustment_minus',
        'reservation' => 'reservation',
        'reservation_release' => 'reservation_release',
        'adjust' => 'adjustment_plus',
        'in' => 'receipt',
        'out' => 'adjustment_minus',
        'issue' => 'issue_to_production',
        'reserve' => 'reservation',
        'release' => 'reservation_release',
    ];

    /**
     * @var array<int,string>
     */
    private const ORDER_STATUSES = ['planned', 'ordered', 'partial', 'received', 'delayed', 'cancelled'];

    /**
     * @var array<int,string>
     */
    private const RECEIPT_ITEM_CLASSES = [
        'material',
        'resin_jairo_material',
        'consumable',
        'third_party_part',
        'functional_part',
        'assembly_component',
        'other',
    ];

    private const DEMAND_HORIZON_DAYS = 30;

    /**
     * @var array<string,float>
     */
    private const COVERAGE_THRESHOLDS = [
        'critical_pct' => 80.0,
        'low_pct' => 100.0,
        'high_pct' => 150.0,
        'near_full_pct' => 85.0,
        'overflow_attention_pct' => 25.0,
    ];

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function planningBuckets(): array
    {
        $today = date('Y-m-d');

        return [
            ['key' => 'today', 'label' => 'Today', 'start_date' => $today, 'end_date' => $today, 'days' => 0],
            ['key' => 'next_7', 'label' => 'Next 7 Days', 'start_date' => $today, 'end_date' => date('Y-m-d', strtotime('+7 days')), 'days' => 7],
            ['key' => 'next_14', 'label' => 'Next 14 Days', 'start_date' => $today, 'end_date' => date('Y-m-d', strtotime('+14 days')), 'days' => 14],
            ['key' => 'next_30', 'label' => 'Next 30 Days', 'start_date' => $today, 'end_date' => date('Y-m-d', strtotime('+30 days')), 'days' => 30],
        ];
    }

    public static function summary(): array
    {
        $coverageRows = self::coverageRows();
        $orderRows = self::orderRows();
        $statusCounts = [
            'Critical' => 0,
            'Low' => 0,
            'Balanced' => 0,
            'High' => 0,
            'Overflow Risk' => 0,
        ];
        $lateIncomingMaterials = [];
        $actionNow = 0;
        $monitor = 0;
        $delayInbound = 0;

        foreach ($coverageRows as $row) {
            $status = (string)($row['coverage_status'] ?? 'Balanced');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            if (!empty($row['has_delayed_inbound']) || !empty($row['late_incoming_risk'])) {
                $materialId = (int)($row['material_id'] ?? 0);
                if ($materialId > 0) {
                    $lateIncomingMaterials[$materialId] = true;
                }
            }
            $action = (string)($row['recommended_action'] ?? '');
            if ($action === 'Buy / Plan Now') {
                $actionNow++;
            } elseif ($action === 'Monitor') {
                $monitor++;
            } elseif ($action === 'Delay Inbound') {
                $delayInbound++;
            }
        }

        foreach ($orderRows as $row) {
            if (empty($row['has_delayed_inbound']) && empty($row['late_incoming_risk'])) {
                continue;
            }
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $lateIncomingMaterials[$materialId] = true;
            }
        }

        return [
            'materials_count' => count(self::materials()),
            'critical_count' => $statusCounts['Critical'],
            'low_count' => $statusCounts['Low'],
            'balanced_count' => $statusCounts['Balanced'],
            'high_count' => $statusCounts['High'],
            'overflow_count' => $statusCounts['Overflow Risk'],
            'incoming_delay_count' => count($lateIncomingMaterials),
            'action_now_count' => $actionNow,
            'monitor_count' => $monitor,
            'delay_inbound_count' => $delayInbound,
            'shortage_risk_count' => $statusCounts['Critical'] + $statusCounts['Low'],
            'overstock_risk_count' => $statusCounts['High'],
            'storage_overflow_count' => $statusCounts['Overflow Risk'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function materials(): array
    {
        MaterialSchemaService::ensureSchema();
        return DB::fetchAll(
            "SELECT
                m.*,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS vendor_name,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS supplier_name,
                COALESCE(m.minimum_stock_qty, m.safety_stock_qty, 0) AS minimum_stock_qty,
                COALESCE(NULLIF(m.reorder_point_qty, 0), m.safety_stock_qty, m.minimum_stock_qty, 0) AS reorder_point_qty,
                COALESCE(NULLIF(m.maximum_stock_qty, 0), m.max_storage_qty, 0) AS maximum_stock_qty,
                COALESCE(m.storage_capacity_qty, NULLIF(m.max_storage_qty, 0)) AS storage_capacity_qty,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_cost,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_unit_cost
             FROM materials m
             ORDER BY m.is_active DESC, m.material_name ASC, m.id ASC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function materialOptions(): array
    {
        return DB::fetchAll(
            "SELECT
                id,
                COALESCE(NULLIF(material_number, ''), material_code) AS material_number,
                COALESCE(NULLIF(material_number, ''), material_code) AS material_code,
                material_name,
                material_type,
                material_subtype,
                storage_location,
                COALESCE(NULLIF(uom, ''), unit, 'kg') AS uom,
                COALESCE(NULLIF(uom, ''), unit, 'kg') AS unit,
                COALESCE(pack_size, 0) AS pack_size,
                COALESCE(standard_cost, standard_unit_cost, 0) AS standard_cost,
                COALESCE(standard_cost, standard_unit_cost, 0) AS standard_unit_cost
             FROM materials
             WHERE is_active = 1
             ORDER BY material_name ASC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function productOptions(): array
    {
        try {
            return DB::fetchAll("SELECT id, parts_name, parts_number FROM products ORDER BY parts_name ASC");
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function mappingRows(): array
    {
        MaterialSchemaService::ensureSchema();

        try {
            return DB::fetchAll(
                "SELECT
                    pm.*,
                    COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS qty_per_part,
                    COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS usage_qty,
                    COALESCE(NULLIF(pm.uom, ''), pm.usage_unit, m.uom, m.unit, 'kg') AS uom,
                    COALESCE(NULLIF(pm.uom, ''), pm.usage_unit, m.uom, m.unit, 'kg') AS usage_unit,
                    p.parts_name,
                    p.parts_number,
                    COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                    COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                    m.material_name,
                    COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit
                 FROM part_material_map pm
                 INNER JOIN materials m ON m.id = pm.material_id
                 LEFT JOIN products p ON p.id = pm.product_id
                 ORDER BY p.parts_name ASC, pm.sequence_no ASC, m.material_name ASC, pm.effective_from ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function stockRows(): array
    {
        $rows = self::baseStockRows();
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
        });
        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function coverageRows(): array
    {
        return self::materialCoverageModelRows();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function planningRows(): array
    {
        if (self::$planningRowsCache !== null) {
            return self::$planningRowsCache;
        }

        MaterialSchemaService::ensureSchema();

        $coverageRows = self::materialCoverageModelRows();
        $stockMap = [];
        foreach (self::baseStockRows() as $row) {
            $stockMap[(int)$row['id']] = $row;
        }

        $capacityMap = self::capacitySummaryMap();
        $incomingByMaterial = self::incomingOrdersByMaterial(self::rawOrderRows());
        $demandByMaterial = self::demandLinesByMaterial();
        $planLinesByMaterial = self::productionPlanLinesByMaterial();
        $buckets = self::planningBuckets();

        $rows = [];
        foreach ($coverageRows as $coverageRow) {
            $materialId = (int)($coverageRow['material_id'] ?? 0);
            $stock = $stockMap[$materialId] ?? [];
            $demandLines = $demandByMaterial[$materialId] ?? [];
            $incomingLines = $incomingByMaterial[$materialId] ?? [];
            $capacity = $capacityMap[$materialId] ?? ['capacity_qty' => (float)($coverageRow['max_storage_qty'] ?? 0), 'slot_count' => 0];

            $openingOnHand = (float)($stock['on_hand_qty'] ?? 0);
            $openingReserved = max(0.0, (float)($stock['reserved_qty'] ?? 0));
            $openingAvailable = (float)($stock['available_qty'] ?? ($openingOnHand - $openingReserved));
            $safetyStock = max(0.0, (float)($coverageRow['safety_stock_qty'] ?? 0));
            $materialMax = max(0.0, (float)($coverageRow['max_storage_qty'] ?? 0));
            $capacityQty = max(0.0, (float)($capacity['capacity_qty'] ?? 0));
            $effectiveCapacity = $capacityQty > 0 ? $capacityQty : $materialMax;

            $bucketSummaries = [];
            $confirmedIncomingCumulative = 0.0;
            $plannedIncomingCumulative = 0.0;
            $demandCumulative = 0.0;

            foreach ($buckets as $bucket) {
                $bucketEnd = (string)$bucket['end_date'];
                $demandInBucket = self::sumLinesUntil($demandLines, $bucketEnd, 'required_qty');
                $confirmedIncoming = self::sumIncomingUntil($incomingLines, $bucketEnd, true);
                $plannedIncoming = self::sumIncomingUntil($incomingLines, $bucketEnd, false);

                $demandCumulative = $demandInBucket;
                $confirmedIncomingCumulative = $confirmedIncoming;
                $plannedIncomingCumulative = $plannedIncoming;

                $projectedAvailable = $openingAvailable + $confirmedIncomingCumulative - $demandCumulative;
                $projectedPhysicalConfirmed = $openingOnHand + $confirmedIncomingCumulative - $demandCumulative;
                $projectedPhysicalPlanned = $openingOnHand + $confirmedIncomingCumulative + $plannedIncomingCumulative - $demandCumulative;
                $bufferRecoveryNeed = max(0.0, $safetyStock - $projectedAvailable);
                $netNeed = max(0.0, $bufferRecoveryNeed);
                $status = self::classifyCoverageStatus($projectedAvailable, $safetyStock, $effectiveCapacity, $projectedPhysicalConfirmed, $projectedPhysicalPlanned);

                $bucketSummaries[(string)$bucket['key']] = [
                    'label' => (string)$bucket['label'],
                    'end_date' => $bucketEnd,
                    'demand_qty' => round($demandCumulative, 2),
                    'confirmed_incoming_qty' => round($confirmedIncomingCumulative, 2),
                    'planned_incoming_qty' => round($plannedIncomingCumulative, 2),
                    'projected_available_qty' => round($projectedAvailable, 2),
                    'projected_physical_qty' => round($projectedPhysicalConfirmed, 2),
                    'planned_physical_qty' => round($projectedPhysicalPlanned, 2),
                    'net_need_qty' => round($netNeed, 2),
                    'status' => $status,
                ];
            }

            $trace = self::planTrace($openingAvailable, $openingOnHand, $demandLines, $incomingLines, $effectiveCapacity, $safetyStock);
            $overallStatus = (string)($coverageRow['coverage_status'] ?? self::worstStatus(array_map(static fn (array $bucket): string => (string)($bucket['status'] ?? 'Balanced'), $bucketSummaries), (string)($trace['status'] ?? 'Balanced')));
            $hasDelayedInbound = !empty($coverageRow['has_delayed_inbound']) || !empty($trace['has_delayed_inbound']) || !empty($trace['late_incoming_risk']);
            $recommendedAction = (string)($coverageRow['recommended_action'] ?? self::recommendedAction($overallStatus, (float)($trace['net_need_qty'] ?? 0), $hasDelayedInbound));

            $rows[] = [
                'material_id' => $materialId,
                'material_number' => (string)($coverageRow['material_number'] ?? $coverageRow['material_code'] ?? ''),
                'material_code' => (string)($coverageRow['material_code'] ?? ''),
                'material_name' => (string)($coverageRow['material_name'] ?? ''),
                'material_type' => (string)($coverageRow['material_type'] ?? ''),
                'unit' => (string)($coverageRow['unit'] ?? ''),
                'supplier_name' => (string)($coverageRow['supplier_name'] ?? ''),
                'lead_time_days' => (int)($coverageRow['lead_time_days'] ?? 0),
                'safety_stock_qty' => round($safetyStock, 2),
                'effective_storage_capacity_qty' => round($effectiveCapacity, 2),
                'storage_slot_count' => (int)($capacity['slot_count'] ?? 0),
                'storage_location' => (string)($coverageRow['storage_location'] ?? ''),
                'on_hand_qty' => round($openingOnHand, 2),
                'reserved_qty' => round($openingReserved, 2),
                'effective_available_qty' => round((float)($coverageRow['current_available_qty'] ?? $openingAvailable), 2),
                'confirmed_incoming_30_qty' => round((float)($bucketSummaries['next_30']['confirmed_incoming_qty'] ?? 0), 2),
                'planned_incoming_30_qty' => round((float)($bucketSummaries['next_30']['planned_incoming_qty'] ?? 0), 2),
                'demand_30_qty' => round((float)($coverageRow['scrap_adjusted_demand_qty'] ?? ($bucketSummaries['next_30']['demand_qty'] ?? 0)), 2),
                'projected_available_30_qty' => round((float)($bucketSummaries['next_30']['projected_available_qty'] ?? 0), 2),
                'net_need_30_qty' => round((float)($coverageRow['shortage_qty'] ?? $trace['net_need_qty'] ?? 0), 2),
                'gross_demand_qty' => round((float)($coverageRow['gross_demand_qty'] ?? 0), 2),
                'scrap_adjusted_demand_qty' => round((float)($coverageRow['scrap_adjusted_demand_qty'] ?? 0), 2),
                'inbound_qty' => round((float)($coverageRow['inbound_qty'] ?? 0), 2),
                'net_available_qty' => round((float)($coverageRow['net_available_qty'] ?? 0), 2),
                'shortage_qty' => round((float)($coverageRow['shortage_qty'] ?? 0), 2),
                'surplus_qty' => round((float)($coverageRow['surplus_qty'] ?? 0), 2),
                'coverage_pct' => round((float)($coverageRow['coverage_pct'] ?? 0), 2),
                'number_of_products_using_material' => (int)($coverageRow['number_of_products_using_material'] ?? 0),
                'top_contributing_products' => (array)($coverageRow['top_contributing_products'] ?? []),
                'coverage_driver_summary' => (string)($coverageRow['coverage_driver_summary'] ?? ''),
                'coverage_status' => $overallStatus,
                'has_delayed_inbound' => $hasDelayedInbound,
                'late_incoming_risk' => $hasDelayedInbound,
                'inbound_delivery_status' => $hasDelayedInbound ? 'Delayed Inbound' : 'On Schedule',
                'delayed_inbound_reason' => (string)($coverageRow['delayed_inbound_reason'] ?? $trace['delayed_inbound_reason'] ?? ''),
                'delayed_inbound_note' => (string)($coverageRow['delayed_inbound_note'] ?? $trace['delayed_inbound_note'] ?? ''),
                'delayed_inbound_order_ids' => (array)($coverageRow['delayed_inbound_order_ids'] ?? $trace['delayed_inbound_order_ids'] ?? []),
                'recommended_action' => $recommendedAction,
                'bucket_summaries' => $bucketSummaries,
                'affected_plans' => (array)($trace['affected_plans'] ?? []),
                'related_plan_lines' => array_slice($planLinesByMaterial[$materialId] ?? [], 0, 12),
                'trace_shortage_date' => (string)($coverageRow['earliest_demand_date'] ?? $trace['first_shortage_date'] ?? ''),
                'trace_overflow_date' => (string)($trace['first_overflow_date'] ?? ''),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $priority = ['Critical' => 0, 'Overflow Risk' => 1, 'Low' => 2, 'High' => 3, 'Balanced' => 4];
            $aStatus = !empty($a['has_delayed_inbound']) ? -1 : ($priority[(string)($a['coverage_status'] ?? 'Balanced')] ?? 9);
            $bStatus = !empty($b['has_delayed_inbound']) ? -1 : ($priority[(string)($b['coverage_status'] ?? 'Balanced')] ?? 9);
            if ($aStatus !== $bStatus) {
                return $aStatus <=> $bStatus;
            }
            return strcmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
        });

        self::$planningRowsCache = $rows;
        return self::$planningRowsCache;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function orderRows(): array
    {
        MaterialSchemaService::ensureSchema();

        $planningByMaterial = [];
        $delayedInboundOrderIds = [];
        foreach (self::planningRows() as $planningRow) {
            $planningByMaterial[(int)($planningRow['material_id'] ?? 0)] = $planningRow;
            foreach ((array)($planningRow['delayed_inbound_order_ids'] ?? []) as $orderId) {
                $delayedInboundOrderIds[(int)$orderId] = true;
            }
        }

        $rows = self::rawOrderRows();

        foreach ($rows as &$row) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            $remainingQty = self::remainingOrderQty($row);
            $hasDelayedInbound = !empty($delayedInboundOrderIds[(int)($row['id'] ?? 0)]);
            $planning = $planningByMaterial[(int)($row['material_id'] ?? 0)] ?? [];

            $row['remaining_qty'] = round($remainingQty, 2);
            $row['confirmed_for_coverage'] = self::isConfirmedOrderStatus($status);
            $row['has_delayed_inbound'] = $hasDelayedInbound;
            $row['late_incoming_risk'] = $hasDelayedInbound;
            $row['inbound_delivery_status'] = $hasDelayedInbound ? 'Delayed Inbound' : 'On Schedule';
            $row['status_label'] = self::orderStatusLabel($status);
            $row['delayed_inbound_reason'] = $hasDelayedInbound
                ? self::delayedInboundReasonForOrder($status, (string)($planning['delayed_inbound_reason'] ?? ''))
                : '';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function capacityRows(): array
    {
        $rows = [];
        $planningMap = [];
        foreach (self::planningRows() as $row) {
            $planningMap[(int)$row['material_id']] = $row;
        }

        foreach (self::capacitySummaryRawRows() as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            $plan = $planningMap[$materialId] ?? [];
            $projectedPhysical = (float)(($plan['bucket_summaries']['next_30']['planned_physical_qty'] ?? $row['current_qty']) ?? 0);
            $maxCapacity = (float)($row['max_capacity_qty'] ?? 0);

            $row['projected_qty_30'] = round($projectedPhysical, 2);
            $row['occupancy_pct'] = $maxCapacity > 0 ? round((((float)($row['current_qty'] ?? 0)) / $maxCapacity) * 100, 2) : 0.0;
            $row['projected_occupancy_pct'] = $maxCapacity > 0 ? round(($projectedPhysical / $maxCapacity) * 100, 2) : 0.0;
            $row['storage_status'] = self::classifyStorageStatus($projectedPhysical, $maxCapacity);
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            return ((float)($b['projected_occupancy_pct'] ?? 0)) <=> ((float)($a['projected_occupancy_pct'] ?? 0));
        });

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function costRows(): array
    {
        MaterialSchemaService::ensureSchema();

        return DB::fetchAll(
            "SELECT
                m.id,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                m.material_name,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_cost,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_unit_cost,
                COALESCE(stock.on_hand_qty, 0) AS on_hand_qty,
                ROUND(COALESCE(stock.on_hand_qty, 0) * COALESCE(m.standard_cost, m.standard_unit_cost, 0), 2) AS stock_value,
                COALESCE(open_orders.open_qty, 0) AS open_order_qty,
                ROUND(COALESCE(open_orders.open_qty, 0) * COALESCE(m.standard_cost, m.standard_unit_cost, 0), 2) AS exposure_value
             FROM materials m
             LEFT JOIN (
                SELECT material_id, SUM(qty_delta) AS on_hand_qty
                FROM material_ledger
                GROUP BY material_id
             ) stock ON stock.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(GREATEST(planned_qty - received_qty, 0)) AS open_qty
                FROM material_orders
                WHERE LOWER(status) IN ('planned', 'ordered', 'partial', 'delayed')
                GROUP BY material_id
             ) open_orders ON open_orders.material_id = m.id
             ORDER BY exposure_value DESC, stock_value DESC, m.material_name ASC"
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function materialDetail(int $materialId): ?array
    {
        MaterialSchemaService::ensureSchema();

        $material = DB::fetchOne(
            "SELECT
                m.*,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS vendor_name,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS supplier_name,
                COALESCE(m.minimum_stock_qty, m.safety_stock_qty, 0) AS minimum_stock_qty,
                COALESCE(NULLIF(m.reorder_point_qty, 0), m.safety_stock_qty, m.minimum_stock_qty, 0) AS reorder_point_qty,
                COALESCE(NULLIF(m.maximum_stock_qty, 0), m.max_storage_qty, 0) AS maximum_stock_qty,
                COALESCE(m.storage_capacity_qty, NULLIF(m.max_storage_qty, 0)) AS storage_capacity_qty,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_cost,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_unit_cost
             FROM materials m
             WHERE m.id = ? LIMIT 1",
            [$materialId]
        );
        if ($material === null) {
            return null;
        }

        $stock = null;
        foreach (self::stockRows() as $row) {
            if ((int)($row['id'] ?? 0) === $materialId) {
                $stock = $row;
                break;
            }
        }

        $coverage = null;
        foreach (self::coverageRows() as $row) {
            if ((int)($row['material_id'] ?? 0) === $materialId) {
                $coverage = $row;
                break;
            }
        }

        $planning = null;
        foreach (self::planningRows() as $row) {
            if ((int)($row['material_id'] ?? 0) === $materialId) {
                $planning = $row;
                break;
            }
        }

        $orders = array_values(array_filter(
            self::orderRows(),
            static fn (array $row): bool => (int)($row['material_id'] ?? 0) === $materialId
        ));

        $capacity = array_values(array_filter(
            self::capacityRows(),
            static fn (array $row): bool => (int)($row['material_id'] ?? 0) === $materialId
        ));

        $mappings = array_values(array_filter(
            self::mappingRows(),
            static fn (array $row): bool => (int)($row['material_id'] ?? 0) === $materialId
        ));

        $reservations = DB::fetchAll(
            "SELECT *
             FROM material_reservations
             WHERE material_id = ?
             ORDER BY COALESCE(required_by_date, '9999-12-31') ASC, id DESC",
            [$materialId]
        );

        $recentLedger = DB::fetchAll(
            "SELECT id, movement_type, qty_delta, reserved_delta, ledger_reference, reference_type, reference_id, notes, created_at
             FROM material_ledger
             WHERE material_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 12",
            [$materialId]
        );

        return [
            'material' => $material,
            'stock' => $stock,
            'coverage' => $coverage,
            'planning' => $planning,
            'orders' => $orders,
            'capacity' => $capacity,
            'mappings' => $mappings,
            'reservations' => $reservations,
            'recent_ledger' => $recentLedger,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function partStatusRowsForProduction(string $date, int $machineId = 0): array
    {
        MaterialSchemaService::ensureSchema();

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        $context = MaterialAccessService::context();
        $scope = (array)($context['scope'] ?? []);
        $scopeMachineIds = array_values(array_filter(array_map('intval', (array)($scope['machine_ids'] ?? [])), static fn (int $id): bool => $id > 0));
        $scopePartIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn (int $id): bool => $id > 0));

        $where = ['pp.plan_date = ?'];
        $params = [$date];
        if ($machineId > 0) {
            $where[] = 'pp.machine_id = ?';
            $params[] = $machineId;
        }
        if ($scopeMachineIds !== []) {
            $ph = implode(',', array_fill(0, count($scopeMachineIds), '?'));
            $where[] = "pp.machine_id IN ({$ph})";
            foreach ($scopeMachineIds as $scopeMachineId) {
                $params[] = $scopeMachineId;
            }
        }
        if ($scopePartIds !== []) {
            $ph = implode(',', array_fill(0, count($scopePartIds), '?'));
            $where[] = "pp.product_id IN ({$ph})";
            foreach ($scopePartIds as $scopePartId) {
                $params[] = $scopePartId;
            }
        }

        $rows = DB::fetchAll(
            "SELECT DISTINCT
                pp.product_id,
                pp.machine_id,
                p.parts_name,
                p.parts_number,
                m.machine_no,
                m.machine_name
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             INNER JOIN machines m ON m.id = pp.machine_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.machine_no ASC, p.parts_name ASC, p.id ASC",
            $params
        );

        $planningMap = [];
        foreach (self::planningRows() as $planningRow) {
            $planningMap[(int)($planningRow['material_id'] ?? 0)] = $planningRow;
        }

        $mappingRows = self::mappingRows();
        $materialIndex = [];
        foreach ($mappingRows as $mappingRow) {
            $materialIndex[(int)($mappingRow['product_id'] ?? 0)][] = $mappingRow;
        }

        $statusWeight = static function (string $status): int {
            return match ($status) {
                'Critical' => 4,
                'Overflow Risk' => 3,
                'Low' => 2,
                'High' => 1,
                default => 0,
            };
        };

        $partRows = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $linkedMaterials = $materialIndex[$productId] ?? [];
            $partStatus = 'Balanced';
            $delayed = false;
            $materials = [];
            $topMaterialId = 0;
            $topMaterialName = '';
            foreach ($linkedMaterials as $linkedMaterial) {
                $materialId = (int)($linkedMaterial['material_id'] ?? 0);
                $planning = $planningMap[$materialId] ?? null;
                if (!is_array($planning)) {
                    continue;
                }
                $coverageStatus = (string)($planning['coverage_status'] ?? 'Balanced');
                $lateIncoming = !empty($planning['has_delayed_inbound']) || !empty($planning['late_incoming_risk']);
                if (
                    $statusWeight($coverageStatus) > $statusWeight($partStatus)
                    || ($statusWeight($coverageStatus) === $statusWeight($partStatus) && $lateIncoming && !$delayed)
                ) {
                    $partStatus = $coverageStatus;
                    $topMaterialId = $materialId;
                    $topMaterialName = (string)($planning['material_name'] ?? $linkedMaterial['material_name'] ?? '-');
                }
                if ($lateIncoming) {
                    $delayed = true;
                    if ($topMaterialId === 0) {
                        $topMaterialId = $materialId;
                        $topMaterialName = (string)($planning['material_name'] ?? $linkedMaterial['material_name'] ?? '-');
                    }
                }
                $materials[] = [
                    'material_id' => $materialId,
                    'material_name' => (string)($planning['material_name'] ?? $linkedMaterial['material_name'] ?? '-'),
                    'coverage_status' => $coverageStatus,
                    'has_delayed_inbound' => $lateIncoming,
                    'late_incoming_risk' => $lateIncoming,
                    'detail_url' => '/apps/manufacturing/materials/detail?id=' . $materialId,
                ];
            }

            $materialReady = $partStatus === 'Balanced' || $partStatus === 'High';
            if ($delayed) {
                $materialReady = false;
            }

            $partRows[] = [
                'product_id' => $productId,
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_no' => (string)($row['machine_no'] ?? '-'),
                'machine_name' => (string)($row['machine_name'] ?? '-'),
                'parts_name' => (string)($row['parts_name'] ?? '-'),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'material_ready' => $materialReady,
                'coverage_status' => $delayed ? 'Delivery Delayed' : $partStatus,
                'top_material_id' => $topMaterialId,
                'top_material_name' => $topMaterialName,
                'materials' => $materials,
            ];
        }

        return $partRows;
    }

    public static function createMaterial(array $input): void
    {
        MaterialSchemaService::ensureSchema();

        $code = trim((string)($input['material_number'] ?? $input['material_code'] ?? ''));
        $name = trim((string)($input['material_name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \RuntimeException('Material number and material name are required.');
        }

        $leadTimeDays = max(0, (int)($input['lead_time_days'] ?? 7));
        $minimumStockQty = self::nonNegativeFloat($input['minimum_stock_qty'] ?? $input['safety_stock_qty'] ?? 0, 'Minimum stock');
        $reorderPointQty = self::nonNegativeFloat($input['reorder_point_qty'] ?? $minimumStockQty, 'Reorder point quantity');
        $maximumStockQty = self::nonNegativeFloat($input['maximum_stock_qty'] ?? $input['max_storage_qty'] ?? 0, 'Maximum stock quantity');
        $storageCapacityQty = self::nullableNonNegativeFloat($input['storage_capacity_qty'] ?? $input['max_storage_qty'] ?? null, 'Storage capacity quantity');
        $standardCost = self::nullableNonNegativeFloat($input['standard_cost'] ?? $input['standard_unit_cost'] ?? null, 'Standard cost');
        $minOrderQty = self::nonNegativeFloat($input['min_order_qty'] ?? 0, 'Minimum order quantity');
        $orderLotSize = self::nonNegativeFloat($input['order_lot_size'] ?? 0, 'Order lot size');
        $uom = trim((string)($input['uom'] ?? $input['unit'] ?? 'kg')) ?: 'kg';
        $vendorName = trim((string)($input['vendor_name'] ?? $input['supplier_name'] ?? ''));

        DB::query(
            "INSERT INTO materials
                (material_number, material_name, material_type, material_subtype, vendor_id, vendor_name, uom, pack_size, material_grade, color, lead_time_days, minimum_stock_qty, reorder_point_qty, maximum_stock_qty, storage_capacity_qty, standard_cost, currency, material_code, unit, supplier_name, supplier_ref, safety_stock_qty, max_storage_qty, storage_location, standard_unit_cost, reorder_policy, min_order_qty, order_lot_size, is_active, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $code,
                $name,
                trim((string)($input['material_type'] ?? '材料')) ?: '材料',
                trim((string)($input['material_subtype'] ?? '')) ?: null,
                isset($input['vendor_id']) && (int)$input['vendor_id'] > 0 ? (int)$input['vendor_id'] : null,
                $vendorName !== '' ? $vendorName : null,
                $uom,
                self::nullableNonNegativeFloat($input['pack_size'] ?? null, 'Pack size'),
                trim((string)($input['material_grade'] ?? '')) ?: null,
                trim((string)($input['color'] ?? '')) ?: null,
                $leadTimeDays,
                $minimumStockQty,
                $reorderPointQty,
                $maximumStockQty,
                $storageCapacityQty,
                $standardCost,
                trim((string)($input['currency'] ?? 'USD')) ?: 'USD',
                $code,
                $uom,
                $vendorName !== '' ? $vendorName : null,
                trim((string)($input['supplier_ref'] ?? '')) ?: null,
                $minimumStockQty,
                $maximumStockQty,
                trim((string)($input['storage_location'] ?? '')),
                $standardCost ?? 0.0,
                trim((string)($input['reorder_policy'] ?? '')) ?: null,
                $minOrderQty,
                $orderLotSize,
                isset($input['is_active']) ? 1 : 0,
                trim((string)($input['notes'] ?? '')),
            ]
        );
    }

    public static function createMapping(array $input): void
    {
        MaterialSchemaService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        $materialId = (int)($input['material_id'] ?? 0);
        if ($productId <= 0 || $materialId <= 0) {
            throw new \RuntimeException('Part and material are required.');
        }
        if (!self::productExists($productId)) {
            throw new \RuntimeException('Selected part was not found.');
        }
        if (!self::materialExists($materialId)) {
            throw new \RuntimeException('Selected material was not found.');
        }

        $qtyPerPart = (float)($input['qty_per_part'] ?? $input['usage_qty'] ?? 0);
        if ($qtyPerPart <= 0) {
            throw new \RuntimeException('Quantity per part must be greater than zero.');
        }

        $scrapPct = self::nonNegativeFloat($input['scrap_pct'] ?? 0, 'Scrap percentage');
        $effectiveFrom = self::normalizeNullableDate($input['effective_from'] ?? null);
        $effectiveTo = self::normalizeNullableDate($input['effective_to'] ?? null);
        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveFrom > $effectiveTo) {
            throw new \RuntimeException('Effective-to date must be on or after effective-from date.');
        }

        $existing = DB::fetchOne(
            "SELECT id
             FROM part_material_map
             WHERE product_id = ?
               AND material_id = ?
             LIMIT 1",
            [$productId, $materialId]
        );
        if ($existing !== null) {
            throw new \RuntimeException('A part-material mapping for this product and material already exists.');
        }

        DB::query(
            "INSERT INTO part_material_map (product_id, material_id, qty_per_part, uom, usage_qty, usage_unit, scrap_pct, is_primary, notes, effective_from, effective_to, sequence_no, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $productId,
                $materialId,
                $qtyPerPart,
                trim((string)($input['uom'] ?? $input['usage_unit'] ?? '')) ?: 'kg',
                $qtyPerPart,
                trim((string)($input['uom'] ?? $input['usage_unit'] ?? '')) ?: 'kg',
                $scrapPct,
                (int)((string)($input['is_primary'] ?? '1') === '0' ? 0 : 1),
                trim((string)($input['notes'] ?? '')) ?: null,
                $effectiveFrom,
                $effectiveTo,
                max(1, (int)($input['sequence_no'] ?? 1)),
                isset($input['is_active']) ? 1 : 1,
            ]
        );
    }

    public static function postLedgerAdjustment(array $input, int $userId): void
    {
        MaterialSchemaService::ensureSchema();

        $materialId = (int)($input['material_id'] ?? 0);
        if ($materialId <= 0) {
            throw new \RuntimeException('Material is required.');
        }
        if (!self::materialExists($materialId)) {
            throw new \RuntimeException('Selected material was not found.');
        }

        $movement = self::normalizeMovementType((string)($input['movement_type'] ?? 'adjust'));
        $note = trim((string)($input['notes'] ?? ''));
        $referenceType = trim((string)($input['reference_type'] ?? 'manual')) ?: 'manual';
        $referenceId = ($input['reference_id'] ?? '') !== '' ? (int)$input['reference_id'] : null;
        $ledgerReference = trim((string)($input['ledger_reference'] ?? ($input['reservation_reference'] ?? ''))) ?: null;
        $qtyInput = (float)($input['qty_delta'] ?? 0);
        $reservedInput = (float)($input['reserved_delta'] ?? 0);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $snapshot = self::currentStockSnapshot($materialId);
            $reservedQty = abs($reservedInput > 0 ? $reservedInput : $qtyInput);

            if ($movement === 'reservation') {
                if ($reservedQty <= 0) {
                    throw new \RuntimeException('Reservation quantity must be greater than zero.');
                }
                if ($snapshot['available_qty'] + 0.0001 < $reservedQty) {
                    throw new \RuntimeException('Reservation exceeds currently available stock.');
                }

                self::createReservationRecord($materialId, $reservedQty, $input, $userId);
                self::insertLedgerRow([
                    'material_id' => $materialId,
                    'movement_type' => $movement,
                    'qty_delta' => 0.0,
                    'reserved_delta' => $reservedQty,
                    'ledger_reference' => $ledgerReference,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $note,
                    'updated_by' => $userId,
                ]);
            } elseif ($movement === 'reservation_release') {
                if ($reservedQty <= 0) {
                    throw new \RuntimeException('Release quantity must be greater than zero.');
                }
                $released = self::releaseReservationQty($materialId, $reservedQty, $input, $userId);
                if ($released <= 0) {
                    throw new \RuntimeException('No active reservation matched this release request.');
                }

                self::insertLedgerRow([
                    'material_id' => $materialId,
                    'movement_type' => $movement,
                    'qty_delta' => 0.0,
                    'reserved_delta' => -1 * $released,
                    'ledger_reference' => $ledgerReference,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $note,
                    'updated_by' => $userId,
                ]);
            } else {
                $qtyDelta = self::normalizeQtyDeltaForMovement($movement, $qtyInput);
                if (abs($qtyDelta) <= 0.0001) {
                    throw new \RuntimeException('Quantity change must be greater than zero.');
                }
                if (($snapshot['on_hand_qty'] + $qtyDelta) < -0.0001) {
                    throw new \RuntimeException('Ledger entry would drive stock below zero.');
                }

                self::insertLedgerRow([
                    'material_id' => $materialId,
                    'movement_type' => $movement,
                    'qty_delta' => $qtyDelta,
                    'reserved_delta' => 0.0,
                    'ledger_reference' => $ledgerReference,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $note,
                    'updated_by' => $userId,
                ]);

                if ($movement === 'issue_to_production' && $referenceId !== null) {
                    self::fulfillReservationsForSource($materialId, abs($qtyDelta), $referenceType, $referenceId);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function recordReceipt(array $input, int $userId): void
    {
        MaterialSchemaService::ensureSchema();

        $materialId = (int)($input['material_id'] ?? 0);
        if ($materialId <= 0) {
            throw new \RuntimeException('Material is required.');
        }
        if (!self::materialExists($materialId)) {
            throw new \RuntimeException('Selected material was not found.');
        }
        $materialProfile = self::materialMeasurementProfileForId($materialId);
        $materialUnit = (string)($materialProfile['unit'] ?? 'kg');
        $materialPackSize = (float)($materialProfile['pack_size'] ?? 0);

        $receivedQty = abs((float)($input['received_qty'] ?? $input['qty_delta'] ?? 0));
        if ($receivedQty <= 0.0001) {
            throw new \RuntimeException('Received quantity must be greater than zero.');
        }

        $receivedDate = self::normalizeNullableDate($input['received_date'] ?? null) ?? date('Y-m-d');
        $orderId = ($input['order_id'] ?? '') !== '' ? (int)$input['order_id'] : null;
        $supplierName = trim((string)($input['supplier_name'] ?? ''));
        $orderReference = '';
        $referenceType = 'material_receipt';
        $referenceId = null;

        $referenceNo = trim((string)($input['reference_no'] ?? ''));
        $deliveryNote = trim((string)($input['delivery_note'] ?? ''));
        $poReference = trim((string)($input['po_reference'] ?? ''));
        $lotBatch = trim((string)($input['lot_batch'] ?? ''));
        $storageSlot = trim((string)($input['storage_slot'] ?? ''));
        $userNote = trim((string)($input['notes'] ?? ''));
        $receivedItemClass = self::normalizeReceiptItemClass((string)($input['received_item_class'] ?? 'material'));
        $receivedItemDetail = trim((string)($input['received_item_detail'] ?? ''));
        $thirdPartyProducer = trim((string)($input['third_party_producer'] ?? ''));
        $receivedUnit = self::normalizeMeasurementUnit((string)($input['received_unit'] ?? ''), $materialUnit);
        $receivedConversionRate = self::normalizeReceiptConversionRate(
            $input['received_conversion_rate'] ?? null,
            $receivedUnit,
            $materialUnit,
            $materialPackSize
        );
        $convertedReceivedQty = round($receivedQty * $receivedConversionRate, 4);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            if ($orderId !== null) {
                $order = DB::fetchOne(
                    "SELECT id, material_id, order_reference, supplier_name, planned_qty, received_qty, status
                     FROM material_orders
                     WHERE id = ?
                     LIMIT 1",
                    [$orderId]
                );

                if ($order === null) {
                    throw new \RuntimeException('Selected material order was not found.');
                }
                if ((int)($order['material_id'] ?? 0) !== $materialId) {
                    throw new \RuntimeException('Selected order does not belong to the selected material.');
                }

                $status = strtolower(trim((string)($order['status'] ?? 'planned')));
                if ($status === 'cancelled') {
                    throw new \RuntimeException('Cancelled orders cannot receive stock.');
                }

                $plannedQty = (float)($order['planned_qty'] ?? 0);
                $existingReceived = (float)($order['received_qty'] ?? 0);
                $newReceived = $existingReceived + $convertedReceivedQty;
                if ($newReceived > ($plannedQty + 0.0001)) {
                    throw new \RuntimeException('Receipt exceeds the remaining quantity for this order.');
                }

                $nextStatus = $newReceived + 0.0001 >= $plannedQty ? 'received' : 'partial';
                $nextSupplier = $supplierName !== '' ? $supplierName : trim((string)($order['supplier_name'] ?? ''));

                DB::query(
                    "UPDATE material_orders
                     SET received_qty = ?, status = ?, supplier_name = ?
                     WHERE id = ?",
                    [$newReceived, $nextStatus, $nextSupplier, $orderId]
                );

                $orderReference = trim((string)($order['order_reference'] ?? ''));
                $referenceType = 'material_order';
                $referenceId = $orderId;
                if ($poReference === '' && $orderReference !== '') {
                    $poReference = $orderReference;
                }
                if ($supplierName === '') {
                    $supplierName = trim((string)($order['supplier_name'] ?? ''));
                }
            }

            $ledgerReference = $referenceNo !== ''
                ? $referenceNo
                : ($deliveryNote !== ''
                    ? $deliveryNote
                    : ($poReference !== ''
                        ? $poReference
                        : ($orderReference !== '' ? $orderReference : ('receipt:' . $receivedDate))));

            $notes = [
                'Material receipt recorded on ' . $receivedDate,
                'Received Qty: ' . number_format($receivedQty, 2, '.', '') . ' ' . $receivedUnit,
            ];
            if (abs($receivedConversionRate - 1.0) > 0.0001) {
                $notes[] = 'Conversion: 1 ' . $receivedUnit . ' = ' . number_format($receivedConversionRate, 4, '.', '') . ' ' . $materialUnit;
                $notes[] = 'Posted Qty (Base Unit): ' . number_format($convertedReceivedQty, 2, '.', '') . ' ' . $materialUnit;
            }
            if ($receivedUnit !== $materialUnit) {
                $notes[] = 'Material Base Unit: ' . $materialUnit;
            }
            if ($supplierName !== '') {
                $notes[] = 'Supplier: ' . $supplierName;
            }
            if ($poReference !== '') {
                $notes[] = 'PO Ref: ' . $poReference;
            }
            if ($deliveryNote !== '') {
                $notes[] = 'Delivery Note: ' . $deliveryNote;
            }
            $notes[] = 'Received Item Class: ' . self::receiptItemClassLabel($receivedItemClass);
            if ($receivedItemDetail !== '') {
                $notes[] = 'Received Item Detail: ' . $receivedItemDetail;
            }
            if ($thirdPartyProducer !== '') {
                $notes[] = 'Third-Party Producer: ' . $thirdPartyProducer;
            }
            if ($lotBatch !== '') {
                $notes[] = 'Lot/Batch: ' . $lotBatch;
            }
            if ($storageSlot !== '') {
                $notes[] = 'Storage Slot: ' . $storageSlot;
            }
            if ($userNote !== '') {
                $notes[] = 'Notes: ' . $userNote;
            }

            self::insertLedgerRow([
                'material_id' => $materialId,
                'movement_type' => 'receipt',
                'qty_delta' => $convertedReceivedQty,
                'reserved_delta' => 0.0,
                'ledger_reference' => $ledgerReference,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => implode("\n", $notes),
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function createOrder(array $input): void
    {
        MaterialSchemaService::ensureSchema();

        $materialId = (int)($input['material_id'] ?? 0);
        if ($materialId <= 0) {
            throw new \RuntimeException('Material is required.');
        }
        if (!self::materialExists($materialId)) {
            throw new \RuntimeException('Selected material was not found.');
        }

        $plannedQty = (float)($input['planned_qty'] ?? 0);
        if ($plannedQty <= 0) {
            throw new \RuntimeException('Planned quantity must be greater than zero.');
        }
        $receivedQty = self::nonNegativeFloat($input['received_qty'] ?? 0, 'Received quantity');
        if ($receivedQty > $plannedQty) {
            throw new \RuntimeException('Received quantity cannot exceed planned quantity.');
        }

        $status = strtolower(trim((string)($input['status'] ?? 'planned')) ?: 'planned');
        if (!in_array($status, self::ORDER_STATUSES, true)) {
            throw new \RuntimeException('Order status is not supported.');
        }
        if ($status === 'received' && $receivedQty < $plannedQty) {
            throw new \RuntimeException('Use partial status until the full quantity is received.');
        }
        if (in_array($status, ['planned', 'ordered', 'delayed', 'cancelled'], true) && $receivedQty > 0) {
            throw new \RuntimeException('Received quantity requires partial or received status.');
        }
        if ($status === 'partial' && $receivedQty <= 0) {
            throw new \RuntimeException('Partial orders must include received quantity.');
        }

        $expectedDeliveryDate = self::normalizeNullableDate($input['expected_delivery_date'] ?? null) ?? date('Y-m-d');
        $unitCost = self::nonNegativeFloat($input['unit_cost'] ?? 0, 'Unit cost');
        $orderedAt = in_array($status, ['ordered', 'partial', 'received', 'delayed'], true) ? date('Y-m-d H:i:s') : null;
        $confirmedAt = in_array($status, ['ordered', 'partial', 'received', 'delayed'], true) ? date('Y-m-d H:i:s') : null;
        $cancelledAt = $status === 'cancelled' ? date('Y-m-d H:i:s') : null;

        $db = DB::conn();
        $db->begin_transaction();
        try {
            DB::query(
                "INSERT INTO material_orders (material_id, order_reference, supplier_name, supplier_ref, planned_qty, expected_delivery_date, received_qty, status, unit_cost, ordered_at, confirmed_at, cancelled_at, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $materialId,
                    trim((string)($input['order_reference'] ?? '')) ?: null,
                    trim((string)($input['supplier_name'] ?? '')),
                    trim((string)($input['supplier_ref'] ?? '')) ?: null,
                    $plannedQty,
                    $expectedDeliveryDate,
                    $receivedQty,
                    $status,
                    $unitCost,
                    $orderedAt,
                    $confirmedAt,
                    $cancelledAt,
                    trim((string)($input['notes'] ?? '')),
                ]
            );

            $orderId = (int)$db->insert_id;
            if ($receivedQty > 0) {
                self::insertLedgerRow([
                    'material_id' => $materialId,
                    'movement_type' => 'receipt',
                    'qty_delta' => $receivedQty,
                    'reserved_delta' => 0.0,
                    'ledger_reference' => trim((string)($input['order_reference'] ?? '')) ?: ('material_order:' . $orderId),
                    'reference_type' => 'material_order',
                    'reference_id' => $orderId,
                    'notes' => 'Receipt posted from material order intake.',
                    'updated_by' => null,
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function createCapacity(array $input): void
    {
        MaterialSchemaService::ensureSchema();

        $location = trim((string)($input['location_code'] ?? ''));
        if ($location === '') {
            throw new \RuntimeException('Location code is required.');
        }
        $materialId = ($input['material_id'] ?? '') !== '' ? (int)$input['material_id'] : null;
        if ($materialId !== null && !self::materialExists($materialId)) {
            throw new \RuntimeException('Selected material was not found.');
        }
        $capacityQty = self::nonNegativeFloat($input['max_capacity_qty'] ?? 0, 'Max capacity quantity');
        if ($capacityQty <= 0) {
            throw new \RuntimeException('Max capacity quantity must be greater than zero.');
        }

        DB::query(
            "INSERT INTO material_capacity (material_id, location_code, slot_label, max_capacity_qty, is_active, notes)
             VALUES (?,?,?,?,?,?)",
            [
                $materialId,
                $location,
                trim((string)($input['slot_label'] ?? '')) ?: null,
                $capacityQty,
                isset($input['is_active']) ? 1 : 1,
                trim((string)($input['notes'] ?? '')),
            ]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function rawOrderRows(): array
    {
        return DB::fetchAll(
            "SELECT
                o.*,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                m.material_name,
                m.material_type,
                m.material_subtype,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit
             FROM material_orders o
             INNER JOIN materials m ON m.id = o.material_id
             ORDER BY
                CASE LOWER(o.status)
                    WHEN 'delayed' THEN 0
                    WHEN 'partial' THEN 1
                    WHEN 'ordered' THEN 2
                    WHEN 'planned' THEN 3
                    WHEN 'received' THEN 4
                    ELSE 5
                END,
                o.expected_delivery_date ASC, o.id DESC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function baseStockRows(): array
    {
        MaterialSchemaService::ensureSchema();

        return DB::fetchAll(
            "SELECT
                m.id,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                m.material_name,
                m.material_type,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom,
                COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS unit,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS vendor_name,
                COALESCE(NULLIF(m.vendor_name, ''), m.supplier_name, '') AS supplier_name,
                m.lead_time_days,
                COALESCE(m.minimum_stock_qty, m.safety_stock_qty, 0) AS minimum_stock_qty,
                COALESCE(m.minimum_stock_qty, m.safety_stock_qty, 0) AS safety_stock_qty,
                COALESCE(NULLIF(m.maximum_stock_qty, 0), m.max_storage_qty, 0) AS maximum_stock_qty,
                COALESCE(NULLIF(m.maximum_stock_qty, 0), m.max_storage_qty, 0) AS max_storage_qty,
                COALESCE(m.storage_capacity_qty, NULLIF(m.max_storage_qty, 0)) AS storage_capacity_qty,
                m.storage_location,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_cost,
                COALESCE(m.standard_cost, m.standard_unit_cost, 0) AS standard_unit_cost,
                m.is_active,
                COALESCE(ledger.on_hand_qty, 0) AS on_hand_qty,
                COALESCE(reservations.reserved_qty, 0) AS reserved_qty,
                COALESCE(incoming.confirmed_incoming_qty, 0) AS incoming_qty,
                COALESCE(issued.issued_qty, 0) AS issued_qty,
                COALESCE(adjustments.adjustment_qty, 0) AS adjustment_qty,
                (COALESCE(ledger.on_hand_qty, 0) - COALESCE(reservations.reserved_qty, 0)) AS available_qty
             FROM materials m
             LEFT JOIN (
                SELECT material_id, SUM(qty_delta) AS on_hand_qty
                FROM material_ledger
                GROUP BY material_id
             ) ledger ON ledger.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved_qty
                FROM material_reservations
                WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
                GROUP BY material_id
             ) reservations ON reservations.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(GREATEST(planned_qty - received_qty, 0)) AS confirmed_incoming_qty
                FROM material_orders
                WHERE LOWER(status) IN ('ordered', 'partial', 'delayed')
                GROUP BY material_id
             ) incoming ON incoming.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(ABS(qty_delta)) AS issued_qty
                FROM material_ledger
                WHERE LOWER(movement_type) = 'issue_to_production'
                GROUP BY material_id
             ) issued ON issued.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(qty_delta) AS adjustment_qty
                FROM material_ledger
                WHERE LOWER(movement_type) IN ('adjustment_plus', 'adjustment_minus')
                GROUP BY material_id
             ) adjustments ON adjustments.material_id = m.id
             ORDER BY m.is_active DESC, m.material_name ASC"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function capacitySummaryRawRows(): array
    {
        MaterialSchemaService::ensureSchema();

        return DB::fetchAll(
            "SELECT
                c.material_id,
                c.location_code,
                MAX(c.slot_label) AS slot_label,
                COALESCE(MAX(c.max_capacity_qty), 0) AS max_capacity_qty,
                MAX(c.notes) AS notes,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_number,
                COALESCE(NULLIF(m.material_number, ''), m.material_code) AS material_code,
                m.material_name,
                m.storage_location,
                COALESCE(stock.on_hand_qty, 0) AS current_qty
             FROM material_capacity c
             LEFT JOIN materials m ON m.id = c.material_id
             LEFT JOIN (
                SELECT material_id, SUM(qty_delta) AS on_hand_qty
                FROM material_ledger
                GROUP BY material_id
             ) stock ON stock.material_id = c.material_id
             WHERE COALESCE(c.is_active, 1) = 1
             GROUP BY c.material_id, c.location_code, material_number, material_code, m.material_name, m.storage_location, stock.on_hand_qty"
        );
    }

    /**
     * @return array<int,array{capacity_qty:float,slot_count:int}>
     */
    private static function capacitySummaryMap(): array
    {
        $map = [];
        foreach (self::capacitySummaryRawRows() as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            if (!isset($map[$materialId])) {
                $map[$materialId] = ['capacity_qty' => 0.0, 'slot_count' => 0];
            }
            $map[$materialId]['capacity_qty'] += (float)($row['max_capacity_qty'] ?? 0);
            $map[$materialId]['slot_count']++;
        }
        return $map;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function materialCoverageModelRows(): array
    {
        if (self::$coverageModelCache !== null) {
            return self::$coverageModelCache;
        }

        MaterialSchemaService::ensureSchema();

        $materials = self::materials();
        $stockMap = [];
        foreach (self::baseStockRows() as $row) {
            $stockMap[(int)($row['id'] ?? 0)] = $row;
        }

        $capacityMap = self::capacitySummaryMap();
        $incomingByMaterial = self::incomingOrdersByMaterial(self::rawOrderRows());
        $demandSummaryByMaterial = self::demandSummaryByMaterial();
        $rows = [];

        foreach ($materials as $material) {
            $materialId = (int)($material['id'] ?? 0);
            $stock = $stockMap[$materialId] ?? [];
            $capacity = $capacityMap[$materialId] ?? ['capacity_qty' => (float)($material['max_storage_qty'] ?? 0), 'slot_count' => 0];
            $demand = $demandSummaryByMaterial[$materialId] ?? self::emptyDemandSummary($materialId);
            $incomingLines = $incomingByMaterial[$materialId] ?? [];

            $onHandQty = round((float)($stock['on_hand_qty'] ?? 0), 2);
            $reservedQty = round(max(0.0, (float)($stock['reserved_qty'] ?? 0)), 2);
            $currentAvailableQty = round((float)($stock['available_qty'] ?? ($onHandQty - $reservedQty)), 2);
            $confirmedInboundQty = round(self::sumIncomingUntil($incomingLines, date('Y-m-d', strtotime('+' . self::DEMAND_HORIZON_DAYS . ' days')), true), 2);
            $plannedInboundQty = round(self::sumIncomingUntil($incomingLines, date('Y-m-d', strtotime('+' . self::DEMAND_HORIZON_DAYS . ' days')), false), 2);
            $inboundQty = round($confirmedInboundQty + $plannedInboundQty, 2);
            $netAvailableQty = round($onHandQty + $inboundQty - $reservedQty, 2);
            $grossDemandQty = round((float)($demand['gross_demand_qty'] ?? 0), 2);
            $scrapAdjustedDemandQty = round((float)($demand['scrap_adjusted_demand_qty'] ?? 0), 2);
            $shortageQty = round(max(0.0, $scrapAdjustedDemandQty - $netAvailableQty), 2);
            $surplusQty = round(max(0.0, $netAvailableQty - $scrapAdjustedDemandQty), 2);
            $coveragePct = self::coveragePct($netAvailableQty, $scrapAdjustedDemandQty);
            $storageCapacityQty = round(max(0.0, (float)($capacity['capacity_qty'] ?? ($material['storage_capacity_qty'] ?? $material['max_storage_qty'] ?? 0))), 2);
            $projectedPhysicalQty = round($onHandQty + $inboundQty, 2);
            $coverageStatus = self::normalizedCoverageStatus($coveragePct, $shortageQty, $projectedPhysicalQty, $storageCapacityQty);

            $delayMeta = self::delayedInboundMeta(
                $incomingLines,
                (string)($demand['earliest_demand_date'] ?? ''),
                $shortageQty > 0.0001
            );
            $recommendedAction = self::recommendedAction($coverageStatus, $shortageQty, $delayMeta['has_delayed_inbound']);

            $rows[] = [
                'material_id' => $materialId,
                'material_number' => (string)($material['material_number'] ?? $material['material_code'] ?? ''),
                'material_code' => (string)($material['material_code'] ?? $material['material_number'] ?? ''),
                'material_name' => (string)($material['material_name'] ?? ''),
                'material_type' => (string)($material['material_type'] ?? ''),
                'unit' => (string)($material['unit'] ?? ''),
                'uom' => (string)($material['uom'] ?? $material['unit'] ?? ''),
                'supplier_name' => (string)($material['supplier_name'] ?? ''),
                'lead_time_days' => (int)($material['lead_time_days'] ?? 0),
                'storage_location' => (string)($material['storage_location'] ?? ''),
                'safety_stock_qty' => round((float)($material['minimum_stock_qty'] ?? $material['safety_stock_qty'] ?? 0), 2),
                'max_storage_qty' => round((float)($material['storage_capacity_qty'] ?? $material['max_storage_qty'] ?? 0), 2),
                'storage_capacity_qty' => $storageCapacityQty,
                'gross_demand_qty' => $grossDemandQty,
                'scrap_adjusted_demand_qty' => $scrapAdjustedDemandQty,
                'number_of_products_using_material' => (int)($demand['number_of_products_using_material'] ?? 0),
                'top_contributing_products' => (array)($demand['top_contributing_products'] ?? []),
                'coverage_driver_summary' => (string)($demand['coverage_driver_summary'] ?? ''),
                'demand_driver_lines' => (array)($demand['demand_driver_lines'] ?? []),
                'earliest_demand_date' => (string)($demand['earliest_demand_date'] ?? ''),
                'on_hand_qty' => $onHandQty,
                'reserved_qty' => $reservedQty,
                'current_available_qty' => $currentAvailableQty,
                'confirmed_incoming_30_qty' => $confirmedInboundQty,
                'planned_incoming_30_qty' => $plannedInboundQty,
                'inbound_qty' => $inboundQty,
                'net_available_qty' => $netAvailableQty,
                'effective_available_qty' => $currentAvailableQty,
                'projected_available_qty' => $netAvailableQty,
                'shortage_qty' => $shortageQty,
                'surplus_qty' => $surplusQty,
                'coverage_pct' => $coveragePct,
                'coverage_status' => $coverageStatus,
                'demand_qty' => $scrapAdjustedDemandQty,
                'net_need_qty' => $shortageQty,
                'has_delayed_inbound' => $delayMeta['has_delayed_inbound'],
                'late_incoming_risk' => $delayMeta['has_delayed_inbound'],
                'inbound_delivery_status' => $delayMeta['has_delayed_inbound'] ? 'Delayed Inbound' : 'On Schedule',
                'delayed_inbound_reason' => (string)($delayMeta['delayed_inbound_reason'] ?? ''),
                'delayed_inbound_note' => (string)($delayMeta['delayed_inbound_note'] ?? ''),
                'delayed_inbound_order_ids' => (array)($delayMeta['delayed_inbound_order_ids'] ?? []),
                'recommended_action' => $recommendedAction,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $priority = ['Critical' => 0, 'Overflow Risk' => 1, 'Low' => 2, 'High' => 3, 'Balanced' => 4];
            $left = $priority[(string)($a['coverage_status'] ?? 'Balanced')] ?? 9;
            $right = $priority[(string)($b['coverage_status'] ?? 'Balanced')] ?? 9;
            if ($left !== $right) {
                return $left <=> $right;
            }

            return strcmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
        });

        self::$coverageModelCache = $rows;
        return self::$coverageModelCache;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function demandSummaryByMaterial(): array
    {
        if (self::$demandSummaryCache !== null) {
            return self::$demandSummaryCache;
        }

        $rows = self::openMaterialDemandRows();
        $summary = [];

        foreach ($rows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            $productId = (int)($row['product_id'] ?? 0);
            if ($materialId <= 0 || $productId <= 0) {
                continue;
            }

            $effectiveDemandQty = round(max(0.0, (float)($row['effective_demand_qty'] ?? 0)), 2);
            $qtyPerPart = round(max(0.0, (float)($row['qty_per_part'] ?? 0)), 4);
            $scrapPct = max(0.0, (float)($row['scrap_pct'] ?? 0));
            $grossDemandQty = round($effectiveDemandQty * $qtyPerPart, 4);
            $scrapAdjustedDemandQty = round($grossDemandQty * (1 + ($scrapPct / 100)), 4);
            if ($scrapAdjustedDemandQty <= 0.0001) {
                continue;
            }

            if (!isset($summary[$materialId])) {
                $summary[$materialId] = self::emptyDemandSummary($materialId);
            }

            $summary[$materialId]['gross_demand_qty'] += $grossDemandQty;
            $summary[$materialId]['scrap_adjusted_demand_qty'] += $scrapAdjustedDemandQty;
            $summary[$materialId]['products'][$productId] = true;

            $earliestDemandDate = (string)($summary[$materialId]['earliest_demand_date'] ?? '');
            $demandDate = (string)($row['demand_date'] ?? '');
            if ($demandDate !== '' && ($earliestDemandDate === '' || $demandDate < $earliestDemandDate)) {
                $summary[$materialId]['earliest_demand_date'] = $demandDate;
            }

            if (!isset($summary[$materialId]['drivers'][$productId])) {
                $summary[$materialId]['drivers'][$productId] = [
                    'product_id' => $productId,
                    'part_name' => (string)($row['parts_name'] ?? ''),
                    'part_number' => (string)($row['parts_number'] ?? ''),
                    'effective_demand_qty' => 0.0,
                    'gross_demand_qty' => 0.0,
                    'scrap_adjusted_demand_qty' => 0.0,
                ];
            }

            $summary[$materialId]['drivers'][$productId]['effective_demand_qty'] += $effectiveDemandQty;
            $summary[$materialId]['drivers'][$productId]['gross_demand_qty'] += $grossDemandQty;
            $summary[$materialId]['drivers'][$productId]['scrap_adjusted_demand_qty'] += $scrapAdjustedDemandQty;
            $summary[$materialId]['demand_driver_lines'][] = [
                'demand_id' => (int)($row['demand_id'] ?? 0),
                'plan_id' => 0,
                'plan_date' => $demandDate,
                'product_id' => $productId,
                'part_name' => (string)($row['parts_name'] ?? ''),
                'part_number' => (string)($row['parts_number'] ?? ''),
                'planned_qty' => round($effectiveDemandQty, 2),
                'required_qty' => round($scrapAdjustedDemandQty, 2),
                'gross_demand_qty' => round($grossDemandQty, 2),
                'scrap_pct' => $scrapPct,
            ];
        }

        foreach ($summary as $materialId => $row) {
            $drivers = array_values((array)($row['drivers'] ?? []));
            usort($drivers, static function (array $a, array $b): int {
                return ((float)($b['scrap_adjusted_demand_qty'] ?? 0)) <=> ((float)($a['scrap_adjusted_demand_qty'] ?? 0));
            });

            $topContributors = array_slice(array_map(static function (array $driver): array {
                return [
                    'product_id' => (int)($driver['product_id'] ?? 0),
                    'part_name' => (string)($driver['part_name'] ?? ''),
                    'part_number' => (string)($driver['part_number'] ?? ''),
                    'effective_demand_qty' => round((float)($driver['effective_demand_qty'] ?? 0), 2),
                    'gross_demand_qty' => round((float)($driver['gross_demand_qty'] ?? 0), 2),
                    'scrap_adjusted_demand_qty' => round((float)($driver['scrap_adjusted_demand_qty'] ?? 0), 2),
                ];
            }, $drivers), 0, 3);

            $summary[$materialId]['gross_demand_qty'] = round((float)$summary[$materialId]['gross_demand_qty'], 2);
            $summary[$materialId]['scrap_adjusted_demand_qty'] = round((float)$summary[$materialId]['scrap_adjusted_demand_qty'], 2);
            $summary[$materialId]['number_of_products_using_material'] = count((array)($summary[$materialId]['products'] ?? []));
            $summary[$materialId]['top_contributing_products'] = $topContributors;
            $summary[$materialId]['coverage_driver_summary'] = self::coverageDriverSummary($topContributors);
            unset($summary[$materialId]['drivers'], $summary[$materialId]['products']);
        }

        self::$demandSummaryCache = $summary;
        return self::$demandSummaryCache;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function openMaterialDemandRows(): array
    {
        if (!self::tableExists('part_material_map') || !self::tableExists('products')) {
            return [];
        }

        $today = date('Y-m-d');
        $windowEnd = date('Y-m-d', strtotime('+' . self::DEMAND_HORIZON_DAYS . ' days'));
        DemandEngineService::ensureSchema();

        if (self::tableExists('mfg_part_demands')) {
            $existingCount = (int)(DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM mfg_part_demands
                 WHERE demand_type = 'production'
                   AND demand_date >= ?
                   AND demand_date <= ?",
                [$today, $windowEnd]
            )['c'] ?? 0);

            if ($existingCount === 0) {
                DemandEngineService::recalculateDemands([], $today, $windowEnd);
            }
        }

        return DB::fetchAll(
            "SELECT
                d.id AS demand_id,
                d.product_id,
                d.demand_date,
                COALESCE(d.approved_qty, d.adjusted_qty, d.system_qty, 0) AS effective_demand_qty,
                p.parts_name,
                p.parts_number,
                pm.material_id,
                COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS qty_per_part,
                COALESCE(pm.scrap_pct, 0) AS scrap_pct
             FROM mfg_part_demands d
             INNER JOIN part_material_map pm ON pm.product_id = d.product_id
             LEFT JOIN products p ON p.id = d.product_id
             WHERE d.demand_type = 'production'
               AND d.demand_date >= ?
               AND d.demand_date <= ?
               AND COALESCE(d.approved_qty, d.adjusted_qty, d.system_qty, 0) > 0
               AND COALESCE(pm.is_active, 1) = 1
               AND (pm.effective_from IS NULL OR pm.effective_from <= d.demand_date)
               AND (pm.effective_to IS NULL OR pm.effective_to >= d.demand_date)
             ORDER BY d.demand_date ASC, d.product_id ASC, pm.sequence_no ASC",
            [$today, $windowEnd]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function emptyDemandSummary(int $materialId): array
    {
        return [
            'material_id' => $materialId,
            'gross_demand_qty' => 0.0,
            'scrap_adjusted_demand_qty' => 0.0,
            'number_of_products_using_material' => 0,
            'top_contributing_products' => [],
            'coverage_driver_summary' => '',
            'demand_driver_lines' => [],
            'earliest_demand_date' => '',
            'products' => [],
            'drivers' => [],
        ];
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function incomingOrdersByMaterial(array $orderRows): array
    {
        $rows = [];
        foreach ($orderRows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            $remaining = self::remainingOrderQty($row);
            if ($remaining <= 0.0001) {
                continue;
            }
            $status = strtolower(trim((string)($row['status'] ?? '')));
            $confirmed = self::isConfirmedOrderStatus($status);
            $rows[$materialId][] = [
                'order_id' => (int)($row['id'] ?? 0),
                'order_reference' => trim((string)($row['order_reference'] ?? '')),
                'expected_delivery_date' => trim((string)($row['expected_delivery_date'] ?? '')),
                'remaining_qty' => $remaining,
                'confirmed' => $confirmed,
                'status' => $status,
                'explicit_delay' => self::isExplicitDelayedOrderStatus($status),
            ];
        }

        foreach ($rows as &$materialRows) {
            usort($materialRows, static function (array $a, array $b): int {
                return strcmp((string)$a['expected_delivery_date'], (string)$b['expected_delivery_date']);
            });
        }
        unset($materialRows);

        return $rows;
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function demandLinesByMaterial(): array
    {
        if (!$thisRows = self::openMaterialDemandRows()) {
            return [];
        }

        $lines = [];
        foreach ($thisRows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }

            $plannedQty = (float)($row['effective_demand_qty'] ?? 0);
            $usageQty = (float)($row['qty_per_part'] ?? $row['usage_qty'] ?? 0);
            $scrapPct = max(0.0, (float)($row['scrap_pct'] ?? 0));
            $requiredQty = $plannedQty * $usageQty * (1 + ($scrapPct / 100));

            if ($requiredQty <= 0.0001) {
                continue;
            }

            $lines[$materialId][] = [
                'plan_id' => (int)($row['demand_id'] ?? 0),
                'plan_date' => (string)($row['demand_date'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'part_name' => (string)($row['parts_name'] ?? ''),
                'part_number' => (string)($row['parts_number'] ?? ''),
                'planned_qty' => $plannedQty,
                'usage_qty' => $usageQty,
                'scrap_pct' => $scrapPct,
                'required_qty' => $requiredQty,
                'approval_status' => 'approved',
                'status' => 'open_demand',
            ];
        }

        foreach ($lines as &$materialLines) {
            usort($materialLines, static function (array $a, array $b): int {
                $cmp = strcmp((string)$a['plan_date'], (string)$b['plan_date']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int)$a['plan_id']) <=> ((int)$b['plan_id']);
            });
        }
        unset($materialLines);

        return $lines;
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function productionPlanLinesByMaterial(): array
    {
        $rows = self::productionPlanDemandRows();
        if ($rows === []) {
            return [];
        }

        $lines = [];
        foreach ($rows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }

            $plannedQty = (float)($row['planned_qty'] ?? 0);
            $usageQty = (float)($row['qty_per_part'] ?? $row['usage_qty'] ?? 0);
            $scrapPct = max(0.0, (float)($row['scrap_pct'] ?? 0));
            $requiredQty = round($plannedQty * $usageQty * (1 + ($scrapPct / 100)), 2);
            if ($requiredQty <= 0.0001) {
                continue;
            }

            $lines[$materialId][] = [
                'plan_id' => (int)($row['plan_id'] ?? 0),
                'plan_date' => (string)($row['plan_date'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'part_name' => (string)($row['parts_name'] ?? ''),
                'part_number' => (string)($row['parts_number'] ?? ''),
                'planned_qty' => round($plannedQty, 2),
                'required_qty' => $requiredQty,
            ];
        }

        return $lines;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function productionPlanDemandRows(): array
    {
        if (!self::tableExists('production_plans') || !self::tableExists('part_material_map')) {
            return [];
        }

        $today = date('Y-m-d');
        $windowEnd = date('Y-m-d', strtotime('+30 days'));
        $hasApproval = self::hasColumn('production_plans', 'approval_status');

        $approvalExpr = $hasApproval ? 'COALESCE(pp.approval_status, \'\') AS approval_status,' : "'' AS approval_status,";
        $approvalFilter = $hasApproval
            ? "AND LOWER(COALESCE(pp.approval_status, 'draft')) = 'approved'"
            : '';

        return DB::fetchAll(
            "SELECT
                pp.id AS plan_id,
                pp.plan_date,
                pp.product_id,
                pp.planned_qty,
                pp.status,
                {$approvalExpr}
                pm.material_id,
                COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS qty_per_part,
                COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS usage_qty,
                pm.scrap_pct,
                p.parts_name,
                p.parts_number
             FROM production_plans pp
             INNER JOIN part_material_map pm ON pm.product_id = pp.product_id
             LEFT JOIN products p ON p.id = pp.product_id
             WHERE pp.plan_date >= ?
               AND pp.plan_date <= ?
               AND LOWER(COALESCE(pp.status, 'planned')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
               {$approvalFilter}
               AND COALESCE(pm.is_active, 1) = 1
               AND (pm.effective_from IS NULL OR pm.effective_from <= pp.plan_date)
               AND (pm.effective_to IS NULL OR pm.effective_to >= pp.plan_date)
             ORDER BY pp.plan_date ASC, pp.id ASC, pm.sequence_no ASC",
            [$today, $windowEnd]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     */
    private static function sumLinesUntil(array $lines, string $endDate, string $qtyKey): float
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            $lineDate = trim((string)($line['plan_date'] ?? ''));
            if ($lineDate !== '' && $lineDate <= $endDate) {
                $sum += (float)($line[$qtyKey] ?? 0);
            }
        }
        return $sum;
    }

    /**
     * @param array<int,array<string,mixed>> $lines
     */
    private static function sumIncomingUntil(array $lines, string $endDate, bool $confirmedOnly): float
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            $date = trim((string)($line['expected_delivery_date'] ?? ''));
            if ($date === '' || $date > $endDate) {
                continue;
            }
            $confirmed = (bool)($line['confirmed'] ?? false);
            if ($confirmedOnly && !$confirmed) {
                continue;
            }
            if (!$confirmedOnly && $confirmed) {
                continue;
            }
            $sum += (float)($line['remaining_qty'] ?? 0);
        }
        return $sum;
    }

    /**
     * @param array<int,array<string,mixed>> $demandLines
     * @param array<int,array<string,mixed>> $incomingLines
     * @return array<string,mixed>
     */
    private static function planTrace(float $openingAvailable, float $openingOnHand, array $demandLines, array $incomingLines, float $capacity, float $safetyStock): array
    {
        $events = [];
        foreach ($incomingLines as $incoming) {
            $date = trim((string)($incoming['expected_delivery_date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $events[$date]['incoming'][] = $incoming;
        }
        foreach ($demandLines as $demand) {
            $date = trim((string)($demand['plan_date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $events[$date]['demand'][] = $demand;
        }

        ksort($events);

        $available = $openingAvailable;
        $physical = $openingOnHand;
        $affectedPlans = [];
        $firstShortageDate = '';
        $firstOverflowDate = '';
        $hasDelayedInbound = false;
        $latestNetNeed = 0.0;
        $delayedInboundReasons = [];
        $delayedInboundOrderIds = [];

        foreach ($events as $date => $event) {
            foreach ((array)($event['incoming'] ?? []) as $incoming) {
                if ((bool)($incoming['confirmed'] ?? false)) {
                    $qty = (float)($incoming['remaining_qty'] ?? 0);
                    $available += $qty;
                    $physical += $qty;
                }
            }

            foreach ((array)($event['demand'] ?? []) as $demand) {
                $required = (float)($demand['required_qty'] ?? 0);
                $available -= $required;
                $physical -= $required;

                if ($available < 0 && $firstShortageDate === '') {
                    $firstShortageDate = $date;
                }

                if ($available < 0) {
                    $affectedPlans[] = [
                        'plan_id' => (int)($demand['plan_id'] ?? 0),
                        'plan_date' => $date,
                        'part_name' => (string)($demand['part_name'] ?? ''),
                        'part_number' => (string)($demand['part_number'] ?? ''),
                        'required_qty' => round($required, 2),
                        'shortage_after_allocation' => round(abs($available), 2),
                    ];
                }

                $bufferNeed = max(0.0, $safetyStock - $available);
                $latestNetNeed = max($latestNetNeed, $bufferNeed);
            }

            $plannedInbound = 0.0;
            foreach ((array)($event['incoming'] ?? []) as $incoming) {
                if (!(bool)($incoming['confirmed'] ?? false)) {
                    $plannedInbound += (float)($incoming['remaining_qty'] ?? 0);
                }
            }

            if ($capacity > 0 && ($physical + $plannedInbound) > $capacity && $firstOverflowDate === '') {
                $firstOverflowDate = $date;
            }
        }

        foreach ($incomingLines as $incoming) {
            if (!empty($incoming['explicit_delay'])) {
                $hasDelayedInbound = true;
                $delayedInboundReasons['explicit_delay'] = true;
                $orderId = (int)($incoming['order_id'] ?? 0);
                if ($orderId > 0) {
                    $delayedInboundOrderIds[] = $orderId;
                }
            }
        }

        if ($firstShortageDate !== '') {
            foreach ($incomingLines as $incoming) {
                if (!(bool)($incoming['confirmed'] ?? false)) {
                    continue;
                }
                $incomingDate = trim((string)($incoming['expected_delivery_date'] ?? ''));
                if ($incomingDate !== '' && $incomingDate > $firstShortageDate) {
                    $hasDelayedInbound = true;
                    $delayedInboundReasons['post_shortage_arrival'] = true;
                    $orderId = (int)($incoming['order_id'] ?? 0);
                    if ($orderId > 0) {
                        $delayedInboundOrderIds[] = $orderId;
                    }
                }
            }
        }

        $delayedInboundOrderIds = array_values(array_unique(array_filter(array_map('intval', $delayedInboundOrderIds), static fn (int $id): bool => $id > 0)));
        $delayedInboundReason = '';
        $delayedInboundNote = '';
        if (!empty($delayedInboundReasons['explicit_delay']) && !empty($delayedInboundReasons['post_shortage_arrival'])) {
            $delayedInboundReason = 'Explicit delayed status and post-shortage arrival';
            $delayedInboundNote = 'Confirmed inbound is already marked delayed and still lands after the projected shortage date.';
        } elseif (!empty($delayedInboundReasons['explicit_delay'])) {
            $delayedInboundReason = 'Explicit delayed inbound status';
            $delayedInboundNote = 'Open inbound is explicitly marked delayed.';
        } elseif (!empty($delayedInboundReasons['post_shortage_arrival'])) {
            $delayedInboundReason = 'Confirmed inbound arrives after shortage date';
            $delayedInboundNote = 'Confirmed inbound lands after the projected shortage date.';
        }

        $status = self::classifyCoverageStatus($available, $safetyStock, $capacity, $physical, $physical, $firstOverflowDate !== '');

        return [
            'status' => $status,
            'first_shortage_date' => $firstShortageDate,
            'first_overflow_date' => $firstOverflowDate,
            'has_delayed_inbound' => $hasDelayedInbound,
            'late_incoming_risk' => $hasDelayedInbound,
            'delayed_inbound_reason' => $delayedInboundReason,
            'delayed_inbound_note' => $delayedInboundNote,
            'delayed_inbound_order_ids' => $delayedInboundOrderIds,
            'net_need_qty' => round($latestNetNeed, 2),
            'affected_plans' => array_slice($affectedPlans, 0, 5),
        ];
    }

    private static function coveragePct(float $netAvailableQty, float $demandQty): float
    {
        if ($demandQty <= 0.0001) {
            return 100.0;
        }

        return round(max(0.0, min(999.0, ($netAvailableQty / $demandQty) * 100)), 2);
    }

    private static function normalizedCoverageStatus(float $coveragePct, float $shortageQty, float $projectedPhysicalQty, float $storageCapacityQty): string
    {
        if ($storageCapacityQty > 0 && $projectedPhysicalQty > $storageCapacityQty && $coveragePct >= self::COVERAGE_THRESHOLDS['overflow_attention_pct']) {
            return 'Overflow Risk';
        }
        if ($shortageQty > 0 && $coveragePct < self::COVERAGE_THRESHOLDS['critical_pct']) {
            return 'Critical';
        }
        if ($shortageQty > 0 || $coveragePct < self::COVERAGE_THRESHOLDS['low_pct']) {
            return 'Low';
        }
        if ($storageCapacityQty > 0 && $projectedPhysicalQty > $storageCapacityQty) {
            return 'Overflow Risk';
        }
        if ($storageCapacityQty > 0 && $projectedPhysicalQty >= ($storageCapacityQty * self::COVERAGE_THRESHOLDS['near_full_pct'] / 100)) {
            return 'High';
        }
        if ($coveragePct >= self::COVERAGE_THRESHOLDS['high_pct']) {
            return 'High';
        }

        return 'Balanced';
    }

    /**
     * @param array<int,array<string,mixed>> $topContributors
     */
    private static function coverageDriverSummary(array $topContributors): string
    {
        if ($topContributors === []) {
            return 'No open part demand is currently consuming this material.';
        }

        $parts = array_map(static function (array $row): string {
            $label = trim((string)($row['part_name'] ?? ''));
            $partNumber = trim((string)($row['part_number'] ?? ''));
            $qty = round((float)($row['scrap_adjusted_demand_qty'] ?? 0), 2);
            if ($partNumber !== '') {
                $label .= ' (' . $partNumber . ')';
            }
            return trim($label) . ' ' . $qty;
        }, $topContributors);

        return implode(' · ', $parts);
    }

    /**
     * @param array<int,array<string,mixed>> $incomingLines
     * @return array<string,mixed>
     */
    private static function delayedInboundMeta(array $incomingLines, string $earliestDemandDate, bool $hasShortage): array
    {
        $hasExplicitDelay = false;
        $postShortageArrival = false;
        $orderIds = [];

        foreach ($incomingLines as $incoming) {
            if (!empty($incoming['explicit_delay'])) {
                $hasExplicitDelay = true;
            }
            $orderId = (int)($incoming['order_id'] ?? 0);
            if ($orderId > 0 && (!empty($incoming['explicit_delay']) || !empty($incoming['confirmed']))) {
                $orderIds[] = $orderId;
            }
            if ($hasShortage && $earliestDemandDate !== '' && !empty($incoming['confirmed'])) {
                $incomingDate = trim((string)($incoming['expected_delivery_date'] ?? ''));
                if ($incomingDate !== '' && $incomingDate > $earliestDemandDate) {
                    $postShortageArrival = true;
                }
            }
        }

        $hasDelayedInbound = $hasExplicitDelay || $postShortageArrival;
        $reason = '';
        $note = '';
        if ($hasExplicitDelay && $postShortageArrival) {
            $reason = 'Explicit delayed status and post-shortage arrival';
            $note = 'Open inbound is already marked delayed and confirmed replenishment lands after the earliest demand date.';
        } elseif ($hasExplicitDelay) {
            $reason = 'Explicit delayed inbound status';
            $note = 'Open inbound order is explicitly marked delayed.';
        } elseif ($postShortageArrival) {
            $reason = 'Confirmed inbound arrives after demand date';
            $note = 'Confirmed replenishment lands after the earliest open demand date.';
        }

        return [
            'has_delayed_inbound' => $hasDelayedInbound,
            'delayed_inbound_reason' => $reason,
            'delayed_inbound_note' => $note,
            'delayed_inbound_order_ids' => array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0))),
        ];
    }

    private static function classifyCoverageStatus(float $projectedAvailable, float $safetyStock, float $capacity, float $projectedPhysicalConfirmed, float $projectedPhysicalPlanned, bool $forceOverflow = false): string
    {
        if ($forceOverflow || ($capacity > 0 && max($projectedPhysicalConfirmed, $projectedPhysicalPlanned) > $capacity)) {
            return 'Overflow Risk';
        }
        if ($projectedAvailable < 0) {
            return 'Critical';
        }
        if ($projectedAvailable < $safetyStock) {
            return 'Low';
        }
        if ($capacity > 0 && $projectedPhysicalPlanned >= ($capacity * 0.85)) {
            return 'High';
        }
        if ($safetyStock > 0 && $projectedAvailable > ($safetyStock * 2.5)) {
            return 'High';
        }
        return 'Balanced';
    }

    private static function classifyStorageStatus(float $projectedQty, float $capacity): string
    {
        if ($capacity <= 0) {
            return 'No Limit';
        }
        if ($projectedQty > $capacity) {
            return 'Overflow Risk';
        }
        if ($projectedQty >= ($capacity * 0.85)) {
            return 'High';
        }
        return 'Balanced';
    }

    private static function normalizeMovementType(string $movementType): string
    {
        $normalized = strtolower(trim($movementType));
        if ($normalized === '') {
            $normalized = 'adjust';
        }

        if (!isset(self::MOVEMENT_ALIASES[$normalized])) {
            throw new \RuntimeException('Unsupported ledger movement type.');
        }

        return self::MOVEMENT_ALIASES[$normalized];
    }

    private static function normalizeReceiptItemClass(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, self::RECEIPT_ITEM_CLASSES, true)) {
            return $normalized;
        }

        return 'material';
    }

    private static function receiptItemClassLabel(string $value): string
    {
        return match ($value) {
            'resin_jairo_material' => 'Resin / Jairo / Material',
            'consumable' => 'Consumables delivered',
            'third_party_part' => 'Parts delivered by third-party producers',
            'functional_part' => 'Functional parts delivered',
            'assembly_component' => 'Assembly components',
            'other' => 'Other received item',
            default => 'Material',
        };
    }

    private static function normalizeQtyDeltaForMovement(string $movementType, float $qtyInput): float
    {
        $qty = abs($qtyInput);

        return match ($movementType) {
            'opening', 'receipt', 'return', 'adjustment_plus' => $qty,
            'issue_to_production', 'adjustment_minus' => -1 * $qty,
            default => 0.0,
        };
    }

    /**
     * @return array{on_hand_qty:float,reserved_qty:float,available_qty:float}
     */
    private static function currentStockSnapshot(int $materialId): array
    {
        $row = DB::fetchOne(
            "SELECT
                COALESCE(ledger.on_hand_qty, 0) AS on_hand_qty,
                COALESCE(reservations.reserved_qty, 0) AS reserved_qty
             FROM materials m
             LEFT JOIN (
                SELECT material_id, SUM(qty_delta) AS on_hand_qty
                FROM material_ledger
                WHERE material_id = ?
                GROUP BY material_id
             ) ledger ON ledger.material_id = m.id
             LEFT JOIN (
                SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved_qty
                FROM material_reservations
                WHERE material_id = ?
                  AND LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
                GROUP BY material_id
             ) reservations ON reservations.material_id = m.id
             WHERE m.id = ?
             LIMIT 1",
            [$materialId, $materialId, $materialId]
        );

        $onHandQty = (float)($row['on_hand_qty'] ?? 0);
        $reservedQty = (float)($row['reserved_qty'] ?? 0);

        return [
            'on_hand_qty' => $onHandQty,
            'reserved_qty' => $reservedQty,
            'available_qty' => $onHandQty - $reservedQty,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function insertLedgerRow(array $payload): void
    {
        DB::query(
            "INSERT INTO material_ledger (material_id, movement_type, qty_delta, reserved_delta, ledger_reference, reference_type, reference_id, notes, updated_by)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [
                (int)$payload['material_id'],
                (string)$payload['movement_type'],
                (float)($payload['qty_delta'] ?? 0),
                (float)($payload['reserved_delta'] ?? 0),
                $payload['ledger_reference'] ?? null,
                $payload['reference_type'] ?? null,
                $payload['reference_id'] ?? null,
                trim((string)($payload['notes'] ?? '')),
                $payload['updated_by'] ?? null,
            ]
        );
    }

    private static function createReservationRecord(int $materialId, float $reservedQty, array $input, int $userId): void
    {
        DB::query(
            "INSERT INTO material_reservations
                (material_id, reservation_reference, demand_source_type, demand_source_id, reserved_qty, fulfilled_qty, required_by_date, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $materialId,
                trim((string)($input['reservation_reference'] ?? '')) ?: trim((string)($input['ledger_reference'] ?? '')) ?: null,
                trim((string)($input['reference_type'] ?? 'manual')) ?: 'manual',
                ($input['reference_id'] ?? '') !== '' ? (int)$input['reference_id'] : null,
                $reservedQty,
                0.0,
                self::normalizeNullableDate($input['required_by_date'] ?? null),
                'active',
                trim((string)($input['notes'] ?? '')),
                $userId,
            ]
        );
    }

    private static function releaseReservationQty(int $materialId, float $releaseQty, array $input, int $userId): float
    {
        $referenceType = trim((string)($input['reference_type'] ?? ''));
        $referenceId = ($input['reference_id'] ?? '') !== '' ? (int)$input['reference_id'] : null;
        $reservationReference = trim((string)($input['reservation_reference'] ?? ''));

        $sql = "SELECT *
                FROM material_reservations
                WHERE material_id = ?
                  AND LOWER(COALESCE(status, 'active')) IN ('active', 'partial')";
        $params = [$materialId];

        if ($reservationReference !== '') {
            $sql .= " AND reservation_reference = ?";
            $params[] = $reservationReference;
        }
        if ($referenceType !== '' && $referenceId !== null) {
            $sql .= " AND demand_source_type = ? AND demand_source_id = ?";
            $params[] = $referenceType;
            $params[] = $referenceId;
        }

        $sql .= " ORDER BY COALESCE(required_by_date, '9999-12-31') ASC, id ASC";
        $rows = DB::fetchAll($sql, $params);

        $remaining = $releaseQty;
        $released = 0.0;
        foreach ($rows as $row) {
            if ($remaining <= 0.0001) {
                break;
            }
            $outstanding = max(0.0, (float)($row['reserved_qty'] ?? 0) - (float)($row['fulfilled_qty'] ?? 0));
            if ($outstanding <= 0.0001) {
                continue;
            }

            $apply = min($remaining, $outstanding);
            $newReserved = max(0.0, (float)($row['reserved_qty'] ?? 0) - $apply);
            $newStatus = $newReserved <= ((float)($row['fulfilled_qty'] ?? 0) + 0.0001) ? 'released' : 'partial';

            DB::query(
                "UPDATE material_reservations
                 SET reserved_qty = ?, status = ?, released_by = ?, released_at = CASE WHEN ? = 'released' THEN NOW() ELSE released_at END
                 WHERE id = ?",
                [$newReserved, $newStatus, $userId, $newStatus, (int)$row['id']]
            );

            $remaining -= $apply;
            $released += $apply;
        }

        return round($released, 4);
    }

    private static function fulfillReservationsForSource(int $materialId, float $issueQty, string $referenceType, int $referenceId): void
    {
        $rows = DB::fetchAll(
            "SELECT *
             FROM material_reservations
             WHERE material_id = ?
               AND demand_source_type = ?
               AND demand_source_id = ?
               AND LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
             ORDER BY COALESCE(required_by_date, '9999-12-31') ASC, id ASC",
            [$materialId, $referenceType, $referenceId]
        );

        $remaining = $issueQty;
        foreach ($rows as $row) {
            if ($remaining <= 0.0001) {
                break;
            }
            $outstanding = max(0.0, (float)($row['reserved_qty'] ?? 0) - (float)($row['fulfilled_qty'] ?? 0));
            if ($outstanding <= 0.0001) {
                continue;
            }

            $apply = min($remaining, $outstanding);
            $newFulfilled = (float)($row['fulfilled_qty'] ?? 0) + $apply;
            $newStatus = $newFulfilled + 0.0001 >= (float)($row['reserved_qty'] ?? 0) ? 'fulfilled' : 'partial';

            DB::query(
                "UPDATE material_reservations
                 SET fulfilled_qty = ?, status = ?, released_at = CASE WHEN ? = 'fulfilled' THEN NOW() ELSE released_at END
                 WHERE id = ?",
                [$newFulfilled, $newStatus, $newStatus, (int)$row['id']]
            );

            $remaining -= $apply;
        }
    }

    private static function nonNegativeFloat(mixed $value, string $label): float
    {
        $float = (float)$value;
        if ($float < 0) {
            throw new \RuntimeException($label . ' cannot be negative.');
        }
        return $float;
    }

    private static function nullableNonNegativeFloat(mixed $value, string $label): ?float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        return self::nonNegativeFloat($value, $label);
    }

    private static function normalizeNullableDate(mixed $value): ?string
    {
        $date = trim((string)$value);
        if ($date === '') {
            return null;
        }

        $normalized = date('Y-m-d', strtotime($date));
        if ($normalized === false || $normalized === '1970-01-01' && $date !== '1970-01-01') {
            throw new \RuntimeException('Invalid date supplied.');
        }

        return $normalized;
    }

    private static function materialExists(int $materialId): bool
    {
        return DB::fetchOne("SELECT id FROM materials WHERE id = ? LIMIT 1", [$materialId]) !== null;
    }

    /**
     * @return array{unit:string,pack_size:float}
     */
    private static function materialMeasurementProfileForId(int $materialId): array
    {
        $row = DB::fetchOne(
            "SELECT
                COALESCE(NULLIF(uom, ''), NULLIF(unit, ''), 'kg') AS unit,
                COALESCE(pack_size, 0) AS pack_size
             FROM materials
             WHERE id = ?
             LIMIT 1",
            [$materialId]
        );

        return [
            'unit' => self::normalizeMeasurementUnit((string)($row['unit'] ?? 'kg'), 'kg'),
            'pack_size' => max(0.0, (float)($row['pack_size'] ?? 0)),
        ];
    }

    private static function normalizeReceiptConversionRate(mixed $value, string $receivedUnit, string $materialUnit, float $materialPackSize): float
    {
        if (strcasecmp($receivedUnit, $materialUnit) === 0) {
            return 1.0;
        }

        $raw = trim((string)$value);
        if ($raw === '' || (float)$raw <= 0) {
            if ($receivedUnit === 'pack' && $materialPackSize > 0) {
                return $materialPackSize;
            }
            throw new \RuntimeException('Conversion rate is required when received unit differs from material base unit.');
        }

        $rate = (float)$raw;
        if ($rate <= 0) {
            throw new \RuntimeException('Conversion rate must be greater than zero.');
        }

        return $rate;
    }

    private static function normalizeMeasurementUnit(string $value, string $fallback = 'kg'): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            $normalized = strtolower(trim($fallback));
        }
        if ($normalized === '') {
            $normalized = 'kg';
        }

        $aliases = [
            'kilogram' => 'kg',
            'kilograms' => 'kg',
            'kgs' => 'kg',
            'numbers' => 'pcs',
            'number' => 'pcs',
            'piece' => 'pcs',
            'pieces' => 'pcs',
            'pc' => 'pcs',
            'pcs' => 'pcs',
            'packs' => 'pack',
            'package' => 'pack',
            'packages' => 'pack',
            'pk' => 'pack',
            'set' => 'set',
            'sets' => 'set',
            'box' => 'box',
            'boxes' => 'box',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        $sanitized = preg_replace('/[^a-z0-9._\/-]/', '', $normalized) ?? '';
        if ($sanitized === '') {
            return 'kg';
        }

        return substr($sanitized, 0, 20);
    }

    private static function productExists(int $productId): bool
    {
        if (!self::tableExists('products')) {
            return false;
        }

        return DB::fetchOne("SELECT id FROM products WHERE id = ? LIMIT 1", [$productId]) !== null;
    }

    /**
     * @param array<int,string> $statuses
     */
    private static function worstStatus(array $statuses, string $fallback = 'Balanced'): string
    {
        $priority = ['Critical' => 0, 'Overflow Risk' => 1, 'Low' => 2, 'High' => 3, 'Balanced' => 4];
        $best = $fallback;
        $bestPriority = $priority[$fallback] ?? 9;

        foreach ($statuses as $status) {
            $current = $priority[$status] ?? 9;
            if ($current < $bestPriority) {
                $best = $status;
                $bestPriority = $current;
            }
        }

        return $best;
    }

    private static function recommendedAction(string $status, float $netNeedQty, bool $lateIncomingRisk): string
    {
        if ($status === 'Overflow Risk' || $status === 'High') {
            return 'Delay Inbound';
        }
        if ($status === 'Critical' || $lateIncomingRisk || $netNeedQty > 0.0001) {
            return 'Buy / Plan Now';
        }
        if ($status === 'Low') {
            return 'Monitor';
        }
        return 'No Action Needed';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function remainingOrderQty(array $row): float
    {
        return max(0.0, (float)($row['planned_qty'] ?? 0) - (float)($row['received_qty'] ?? 0));
    }

    private static function isConfirmedOrderStatus(string $status): bool
    {
        return in_array($status, ['ordered', 'partial', 'delayed'], true);
    }

    private static function isExplicitDelayedOrderStatus(string $status): bool
    {
        return $status === 'delayed';
    }

    private static function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'delayed' => 'Delayed Inbound',
            'partial' => 'Partial',
            'ordered' => 'Ordered',
            'planned' => 'Planned',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($status),
        };
    }

    private static function delayedInboundReasonForOrder(string $status, string $materialReason): string
    {
        if (self::isExplicitDelayedOrderStatus($status)) {
            return 'Open inbound is explicitly marked delayed.';
        }

        return $materialReason !== '' ? $materialReason : 'Confirmed inbound lands after the projected shortage date.';
    }

    private static function tableExists(string $table): bool
    {
        try {
            $safeTable = DB::conn()->real_escape_string($table);
            return DB::fetchOne("SHOW TABLES LIKE '{$safeTable}'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int,string>
     */
    private static function columnsForTable(string $table): array
    {
        if (isset(self::$tableColumns[$table])) {
            return self::$tableColumns[$table];
        }

        try {
            $safeTable = DB::conn()->real_escape_string($table);
            $rows = DB::fetchAll("SHOW COLUMNS FROM `{$safeTable}`");
        } catch (\Throwable $e) {
            self::$tableColumns[$table] = [];
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = strtolower(trim((string)($row['Field'] ?? '')));
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        self::$tableColumns[$table] = $columns;
        return $columns;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), self::columnsForTable($table), true);
    }
}
