<?php
declare(strict_types=1);

namespace Plugins\ProductionQueue\Controllers;

use App\Core\DB;
use App\Core\View;

final class QueueBoardService
{
    public static function render(View $view, string $viewName): void
    {
        $today = date('Y-m-d');
        $date = (string)($_GET['date'] ?? $today);
        $machineId = (int)($_GET['machine_id'] ?? 0);

        $cond = 'pp.plan_date = ?';
        $params = [$date];
        if ($machineId > 0) {
            $cond .= ' AND pp.machine_id = ?';
            $params[] = $machineId;
        }

        $rows = DB::fetchAll(
            "SELECT
                pp.id, pp.plan_date, pp.planned_qty, pp.sequence_no, pp.runtime,
                pp.status, pp.plan_type, pp.notes,
                m.id AS machine_id, m.machine_no, m.machine_name, m.section,
                p.id AS product_id, p.parts_name, p.parts_number, p.model, p.cycle_time,
                COALESCE(pe_agg.produced_qty, 0)  AS produced_qty,
                COALESCE(pe_agg.rejected_qty, 0)  AS rejected_qty,
                COALESCE(pe_agg.good_qty, 0)      AS good_qty,
                COALESCE(pe_agg.entry_count, 0)   AS entry_count
            FROM production_plans pp
            JOIN machines  m ON m.id = pp.machine_id
            JOIN products  p ON p.id = pp.product_id
            LEFT JOIN (
                SELECT machine_id, product_id, production_date,
                       SUM(produced_qty) AS produced_qty,
                       SUM(rejected_qty) AS rejected_qty,
                       SUM(good_qty)     AS good_qty,
                       COUNT(*)          AS entry_count
                FROM production_entries
                WHERE production_date = ?
                GROUP BY machine_id, product_id, production_date
            ) pe_agg ON pe_agg.machine_id = pp.machine_id
                     AND pe_agg.product_id = pp.product_id
                     AND pe_agg.production_date = pp.plan_date
            WHERE {$cond}
            ORDER BY m.machine_no ASC, pp.sequence_no ASC, pp.id ASC",
            array_merge([$date], $params)
        );

        $machines = DB::fetchAll('SELECT id, machine_no, machine_name FROM machines WHERE is_active=1 ORDER BY machine_no ASC');

        $totalPlanned = 0.0;
        $totalProduced = 0.0;
        foreach ($rows as $r) {
            $totalPlanned += (float)$r['planned_qty'];
            $totalProduced += (float)$r['good_qty'];
        }

        $view->render($viewName, [
            'pageTitle' => 'Production Queue',
            'rows' => $rows,
            'machines' => $machines,
            'date' => $date,
            'today' => $today,
            'machine_id' => $machineId,
            'totalPlanned' => $totalPlanned,
            'totalProduced' => $totalProduced,
        ]);
    }
}
