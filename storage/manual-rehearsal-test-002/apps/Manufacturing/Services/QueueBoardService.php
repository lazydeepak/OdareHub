<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use App\Core\View;
use Apps\Manufacturing\Services\StageTransitionService;

final class QueueBoardService
{
    public static function render(View $view, string $viewName = 'manufacturing::production_queue/index.php'): void
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
                p.supply_mode, p.requires_ipm_qc, p.requires_assembly, p.dispatch_as_is, p.fulfillment_mode, p.activity_type,
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

        $demandMap = DemandExecutionService::productionDemandMapByDate($date);
        $stageReadinessMap = StageTransitionService::computeForDate($date);

        $totalPlanned  = 0.0;
        $totalProduced = 0.0;
        $totalEffectiveDemand  = 0.0;
        $totalApprovedDemand   = 0.0;
        $totalRemainingDemand  = 0.0;
        $blockedCount          = 0;
        $stageReadyForQC       = 0;
        $stageReadyForAssembly = 0;
        $stageReadyForPackaging = 0;
        $stageReadyForDispatch = 0;
        foreach ($rows as $r) {
            $totalPlanned  += (float)$r['planned_qty'];
            $totalProduced += (float)$r['good_qty'];

            $d = $demandMap[(int)$r['product_id']] ?? null;
            if ($d) {
                $totalEffectiveDemand += (float)($d['effective_qty'] ?? 0.0);
                $totalApprovedDemand  += (float)($d['approved_qty'] ?? 0.0);
                $totalRemainingDemand += (float)($d['remaining_qty'] ?? 0.0);
                if (!empty($d['blocked'])) {
                    $blockedCount++;
                }
            }

            $sr = $stageReadinessMap[(int)$r['product_id']] ?? null;
            if ($sr) {
                foreach ((array)($sr['can_release_to'] ?? []) as $rel) {
                    if ($rel === 'assembly')  { $stageReadyForAssembly++; }
                    elseif ($rel === 'qc')        { $stageReadyForQC++; }
                    elseif ($rel === 'packaging') { $stageReadyForPackaging++; }
                    elseif ($rel === 'dispatch') { $stageReadyForDispatch++; }
                }
            }
        }

        foreach ($rows as &$row) {
            $d = $demandMap[(int)$row['product_id']] ?? null;
            $row['demand_execution'] = $d ?? [
                'system_qty'       => 0.0,
                'adjusted_qty'     => null,
                'approved_qty'     => null,
                'effective_qty'    => 0.0,
                'remaining_qty'    => 0.0,
                'status'           => 'calculated',
                'source_pre_qty'   => 0.0,
                'source_daily_qty' => 0.0,
                'blocked'          => false,
                'blocked_reasons'  => ['No demand row for selected date'],
            ];
            $row['stage_readiness'] = $stageReadinessMap[(int)$row['product_id']] ?? null;
        }
        unset($row);

        $view->render($viewName, [
            'pageTitle' => 'Production Queue',
            'rows' => $rows,
            'machines' => $machines,
            'date' => $date,
            'today' => $today,
            'machine_id' => $machineId,
            'totalPlanned' => $totalPlanned,
            'totalProduced' => $totalProduced,
            'totalEffectiveDemand'  => $totalEffectiveDemand,
            'totalApprovedDemand'   => $totalApprovedDemand,
            'totalRemainingDemand'  => $totalRemainingDemand,
            'blockedCount'          => $blockedCount,
            'stageReadyForQC'        => $stageReadyForQC,
            'stageReadyForAssembly'  => $stageReadyForAssembly,
            'stageReadyForPackaging' => $stageReadyForPackaging,
            'stageReadyForDispatch'  => $stageReadyForDispatch,
        ]);
    }
}
