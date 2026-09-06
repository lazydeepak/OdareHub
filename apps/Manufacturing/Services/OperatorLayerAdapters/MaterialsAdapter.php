<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use App\Core\DB;

/**
 * MaterialsAdapter
 *
 * Data-only adapter for the /u/{operator}/materials operator view.
 * Queries materials and related tables directly — no HTML.
 */
final class MaterialsAdapter
{
    /**
     * @return array{
     *   kpi: array<string,int|float>,
     *   low_stock: array<int,array<string,mixed>>,
     *   open_orders: array<int,array<string,mixed>>,
     *   critical_shortage: array<int,array<string,mixed>>,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(): array
    {
        $defaults = [
            'kpi' => [
                'total_materials'   => 0,
                'low_stock_count'   => 0,
                'zero_stock_count'  => 0,
                'open_orders_count' => 0,
                'critical_shortage' => 0,
            ],
            'low_stock'        => [],
            'open_orders'      => [],
            'critical_shortage' => [],
            'empty'            => true,
            'error'            => '',
        ];

        try {
            // Summary KPIs — join materials with ledger balance
            $summary = DB::fetchOne(
                "SELECT
                    COUNT(DISTINCT m.id) AS total_materials,
                    COALESCE(SUM(CASE WHEN COALESCE(l.on_hand_qty, 0) <= COALESCE(m.reorder_point_qty, 0)
                                          AND m.reorder_point_qty IS NOT NULL
                                     THEN 1 ELSE 0 END), 0) AS low_stock_count,
                    COALESCE(SUM(CASE WHEN COALESCE(l.on_hand_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS zero_stock_count
                 FROM materials m
                 LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand_qty
                     FROM material_ledger
                     GROUP BY material_id
                 ) l ON l.material_id = m.id"
            );

            $openOrdersCount = DB::fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM material_orders
                 WHERE GREATEST(planned_qty - received_qty, 0) > 0"
            );

            if (is_array($summary)) {
                $defaults['kpi']['total_materials']  = (int)($summary['total_materials'] ?? 0);
                $defaults['kpi']['low_stock_count']  = (int)($summary['low_stock_count'] ?? 0);
                $defaults['kpi']['zero_stock_count'] = (int)($summary['zero_stock_count'] ?? 0);
            }
            if (is_array($openOrdersCount)) {
                $defaults['kpi']['open_orders_count'] = (int)($openOrdersCount['cnt'] ?? 0);
            }

            // Low-stock materials (on_hand <= reorder_point)
            $defaults['low_stock'] = DB::fetchAll(
                "SELECT m.id, m.material_code, m.material_name, m.unit,
                        m.reorder_point_qty, COALESCE(l.on_hand_qty, 0) AS on_hand_qty
                 FROM materials m
                 LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand_qty
                     FROM material_ledger
                     GROUP BY material_id
                 ) l ON l.material_id = m.id
                 WHERE m.reorder_point_qty IS NOT NULL
                   AND COALESCE(l.on_hand_qty, 0) <= m.reorder_point_qty
                 ORDER BY on_hand_qty ASC, m.material_name ASC
                 LIMIT 30"
            ) ?: [];

            // Open purchase/material orders
            $defaults['open_orders'] = DB::fetchAll(
                "SELECT mo.id, mo.order_reference AS order_ref, m.material_name, m.unit,
                        mo.planned_qty, mo.received_qty,
                        GREATEST(mo.planned_qty - mo.received_qty, 0) AS outstanding_qty,
                        mo.expected_delivery_date AS expected_date
                 FROM material_orders mo
                 LEFT JOIN materials m ON m.id = mo.material_id
                 WHERE GREATEST(mo.planned_qty - mo.received_qty, 0) > 0
                 ORDER BY mo.expected_delivery_date ASC, mo.id ASC
                 LIMIT 30"
            ) ?: [];

            // Critical shortage: zero stock with open demand (in material_reservations)
            $defaults['critical_shortage'] = DB::fetchAll(
                "SELECT m.id, m.material_code, m.material_name, m.unit,
                        COALESCE(l.on_hand_qty, 0) AS on_hand_qty,
                        COALESCE(r.reserved_qty, 0) AS reserved_qty,
                        COALESCE(l.on_hand_qty, 0) - COALESCE(r.reserved_qty, 0) AS net_qty
                 FROM materials m
                 LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand_qty
                     FROM material_ledger GROUP BY material_id
                 ) l ON l.material_id = m.id
                 LEFT JOIN (
                     SELECT material_id, SUM(reserved_qty) AS reserved_qty
                     FROM material_reservations
                     WHERE LOWER(COALESCE(status,'')) NOT IN ('released','cancelled')
                     GROUP BY material_id
                 ) r ON r.material_id = m.id
                 HAVING net_qty < 0
                 ORDER BY net_qty ASC
                 LIMIT 20"
            ) ?: [];

            $defaults['kpi']['critical_shortage'] = count($defaults['critical_shortage']);
            $defaults['empty'] = ($defaults['kpi']['total_materials'] === 0);
        } catch (\Throwable $e) {
            $defaults['error'] = $e->getMessage();
            error_log('MaterialsAdapter::getData: ' . $e->getMessage());
        }

        return $defaults;
    }
}
