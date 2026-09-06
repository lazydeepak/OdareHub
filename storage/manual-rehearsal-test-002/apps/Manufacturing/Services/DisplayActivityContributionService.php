<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

final class DisplayActivityContributionService
{
    /**
     * Recent activity feed: assembly completions, dispatches, QC results in the last 2 hours.
     * @return array<int, array<string,mixed>>
     */
    public static function recentActivities(): array
    {
        try {
            $sql = "
                SELECT type, status, ts, qty, product_name, part_number FROM (
                    SELECT 'assembly' AS type,
                           ae.status,
                           ae.updated_at AS ts,
                           ae.completed_qty AS qty,
                           COALESCE(p.parts_name,'') AS product_name,
                           COALESCE(p.parts_number,'') AS part_number
                    FROM mfg_assembly_entries ae
                    LEFT JOIN products p ON ae.product_id = p.id
                    WHERE ae.status IN ('completed','approved')
                      AND ae.updated_at >= NOW() - INTERVAL 2 HOUR
                    UNION ALL
                    SELECT 'dispatch',
                           de.dispatch_status,
                           COALESCE(de.dispatched_at, de.last_transition_at),
                           de.dispatchable_qty,
                           COALESCE(p.parts_name,''),
                           COALESCE(p.parts_number,'')
                    FROM dispatch_entries de
                    LEFT JOIN products p ON de.product_id = p.id
                    WHERE de.dispatch_status IN ('Dispatched','Released')
                      AND COALESCE(de.dispatched_at, de.last_transition_at) >= NOW() - INTERVAL 2 HOUR
                    UNION ALL
                    SELECT 'qc',
                           qe.status,
                           qe.updated_at,
                           qe.checked_qty,
                           COALESCE(p.parts_name,''),
                           COALESCE(p.parts_number,'')
                    FROM qc_entries qe
                    LEFT JOIN products p ON qe.product_id = p.id
                    WHERE qe.status NOT IN ('Open','Draft')
                      AND qe.updated_at >= NOW() - INTERVAL 2 HOUR
                ) sub
                ORDER BY ts DESC
                LIMIT 20
            ";
            return DB::fetchAll($sql);
        } catch (\Throwable $e) {
            error_log('DisplayActivityContributionService::recentActivities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Summary count row for today's daily orders.
     * @return array{count:int,qty:float}
     */
    public static function todayOrderSummary(string $date): array
    {
        try {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c, COALESCE(SUM(ordered_qty),0) AS q FROM daily_orders WHERE order_date = ?',
                [$date]
            );

            return [
                'count' => (int)($row['c'] ?? 0),
                'qty' => (float)($row['q'] ?? 0),
            ];
        } catch (\Throwable $e) {
            error_log('DisplayActivityContributionService::todayOrderSummary: ' . $e->getMessage());
            return ['count' => 0, 'qty' => 0.0];
        }
    }
}
