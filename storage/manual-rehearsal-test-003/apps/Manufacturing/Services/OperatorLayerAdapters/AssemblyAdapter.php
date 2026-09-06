<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use App\Core\DB;

/**
 * AssemblyAdapter
 *
 * Data-only adapter for the /u/{operator}/assembly operator view.
 * Queries mfg_assembly_entries directly and returns pure data arrays — no HTML.
 */
final class AssemblyAdapter
{
    /**
     * @param int $days  0 = today only; 7 = last 7 days; 30 = last 30 days
     * @return array{
     *   kpi: array<string,int|float>,
     *   today_entries: array<int,array<string,mixed>>,
     *   in_progress: array<int,array<string,mixed>>,
     *   pending_approval: array<int,array<string,mixed>>,
     *   today: string,
     *   days: int,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(int $days = 0): array
    {
        $defaults = [
            'kpi' => [
                'today_total'      => 0,
                'in_progress'      => 0,
                'completed_today'  => 0,
                'pending_approval' => 0,
                'blocked'          => 0,
                'planned_qty'      => 0.0,
                'completed_qty'    => 0.0,
            ],
            'today_entries'    => [],
            'in_progress'      => [],
            'pending_approval' => [],
            'today'            => date('Y-m-d'),
            'days'             => max(0, $days),
            'empty'            => true,
            'error'            => '',
        ];

        try {
            $today = date('Y-m-d');
            $defaults['today'] = $today;

            if ($days > 0) {
                $dateFilter = "ae.assembly_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
                $dateParam = $days;
            } else {
                $dateFilter = "ae.assembly_date = ?";
                $dateParam = $today;
            }

            $summary = DB::fetchOne(
                "SELECT
                    COUNT(*) AS today_total,
                    COALESCE(SUM(CASE WHEN LOWER(status) = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
                    COALESCE(SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END), 0) AS completed_today,
                    COALESCE(SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END), 0) AS pending_approval,
                    COALESCE(SUM(CASE WHEN LOWER(status) = 'blocked' THEN 1 ELSE 0 END), 0) AS blocked,
                    COALESCE(SUM(planned_qty), 0) AS planned_qty,
                    COALESCE(SUM(completed_qty), 0) AS completed_qty
                 FROM mfg_assembly_entries ae
                 WHERE {$dateFilter}",
                [$dateParam]
            );

            if (is_array($summary)) {
                $defaults['kpi'] = [
                    'today_total'      => (int)($summary['today_total'] ?? 0),
                    'in_progress'      => (int)($summary['in_progress'] ?? 0),
                    'completed_today'  => (int)($summary['completed_today'] ?? 0),
                    'pending_approval' => (int)($summary['pending_approval'] ?? 0),
                    'blocked'          => (int)($summary['blocked'] ?? 0),
                    'planned_qty'      => (float)($summary['planned_qty'] ?? 0),
                    'completed_qty'    => (float)($summary['completed_qty'] ?? 0),
                ];
            }

            $defaults['today_entries'] = DB::fetchAll(
                "SELECT ae.id, p.parts_name AS product_name, p.parts_number,
                        ae.assembly_date, ae.planned_qty, ae.completed_qty,
                        ae.rejected_qty, ae.status, ae.note
                 FROM mfg_assembly_entries ae
                 LEFT JOIN products p ON p.id = ae.product_id
                 WHERE {$dateFilter}
                 ORDER BY ae.assembly_date DESC, ae.status ASC, ae.id DESC
                 LIMIT 100",
                [$dateParam]
            ) ?: [];

            $defaults['in_progress'] = array_values(array_filter(
                $defaults['today_entries'],
                static fn(array $r): bool => strtolower((string)($r['status'] ?? '')) === 'in_progress'
            ));

            $defaults['pending_approval'] = array_values(array_filter(
                $defaults['today_entries'],
                static fn(array $r): bool => strtolower((string)($r['status'] ?? '')) === 'completed'
            ));

            $defaults['empty'] = ($defaults['kpi']['today_total'] === 0);
        } catch (\Throwable $e) {
            $defaults['error'] = $e->getMessage();
            error_log('AssemblyAdapter::getData: ' . $e->getMessage());
        }

        return $defaults;
    }
}
