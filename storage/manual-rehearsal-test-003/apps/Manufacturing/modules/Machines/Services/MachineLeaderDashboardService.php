<?php
declare(strict_types=1);

namespace Plugins\Machines\Services;

use App\Core\DB;
use App\Core\HandoffEngine;

final class MachineLeaderDashboardService
{
    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function build(array $input, ?array $user = null): array
    {
        $today = date('Y-m-d');
        $date = self::normalizeDate((string)($input['date'] ?? $today), $today);
        $machineId = (int)($input['machine_id'] ?? 0);
        $section = trim((string)($input['section'] ?? ''));

        $roleSlug = strtolower(trim((string)($user['role'] ?? '')));
        $email = strtolower(trim((string)($user['email'] ?? '')));

        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $scope = (array)($ctx['scope'] ?? []);
        $scopeMachineIds = array_values(array_filter(array_map('intval', (array)($scope['machine_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $scopePartIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $taskTypes = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), (array)($scope['task_types'] ?? [])), static fn(string $v): bool => $v !== ''));
        $allowProductionTasks = empty($taskTypes)
            || in_array('production', $taskTypes, true)
            || in_array('production_execution', $taskTypes, true)
            || in_array('machine', $taskTypes, true);

        if (!$allowProductionTasks) {
            $scopeMachineIds = [-1];
            $scopePartIds = [-1];
        }

        $scopedMachines = self::fetchScopedMachines($date, $machineId, $section, $email, $scopeMachineIds);
        $machineIds = array_values(array_map(static fn(array $m): int => (int)$m['id'], $scopedMachines));

        $allMachines = DB::fetchAll(
            'SELECT id, machine_no, machine_name, section FROM machines WHERE is_active=1 ORDER BY machine_no ASC LIMIT 1000'
        );
        $sections = self::extractSections($allMachines);

        $rows = [];
        if (!empty($machineIds)) {
            $rows = self::fetchPlanRows($machineIds, $date, $scopePartIds);
        }

        $groupedByMachine = [];
        foreach ($rows as $row) {
            $mid = (int)($row['machine_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            if (!isset($groupedByMachine[$mid])) {
                $groupedByMachine[$mid] = [];
            }
            $groupedByMachine[$mid][] = self::decoratePlanRow($row, $date);
        }

        $runNow = [];
        $nextQueue = [];
        $delayedJobs = [];
        $waitingQc = [];
        $riskJobs = [];

        $todayPlannedQty = 0.0;
        $todayProducedQty = 0.0;

        foreach ($groupedByMachine as $mid => $machineRows) {
            usort($machineRows, static function(array $a, array $b): int {
                $dateCmp = strcmp((string)$a['plan_date'], (string)$b['plan_date']);
                if ($dateCmp !== 0) {
                    return $dateCmp;
                }
                $seqCmp = ((int)$a['sequence_no']) <=> ((int)$b['sequence_no']);
                if ($seqCmp !== 0) {
                    return $seqCmp;
                }
                return ((int)$a['id']) <=> ((int)$b['id']);
            });

            foreach ($machineRows as $row) {
                if ((string)$row['plan_date'] === $date) {
                    $todayPlannedQty += (float)$row['planned_qty'];
                    $todayProducedQty += (float)$row['good_qty'];
                }
                if ((bool)$row['is_delayed']) {
                    $delayedJobs[] = $row;
                }
                if ((float)$row['pending_qc_qty'] > 0.0) {
                    $waitingQc[] = $row;
                }
                if ((string)$row['risk_level'] !== 'low' && (float)$row['remaining_qty'] > 0.0) {
                    $riskJobs[] = $row;
                }
            }

            $active = self::pickActivePlan($machineRows, $date);
            if ($active !== null) {
                $runNow[] = $active;
            }

            $machineQueue = self::pickNextQueue($machineRows, $date, (int)($active['id'] ?? 0));
            foreach ($machineQueue as $qRow) {
                $nextQueue[] = $qRow;
            }
        }

        usort($runNow, static fn(array $a, array $b): int => strcmp((string)$a['machine_no'], (string)$b['machine_no']));
        usort($nextQueue, static function(array $a, array $b): int {
            $dateCmp = strcmp((string)$a['plan_date'], (string)$b['plan_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            $machineCmp = strcmp((string)$a['machine_no'], (string)$b['machine_no']);
            if ($machineCmp !== 0) {
                return $machineCmp;
            }
            return ((int)$a['sequence_no']) <=> ((int)$b['sequence_no']);
        });
        usort($delayedJobs, static function(array $a, array $b): int {
            $dateCmp = strcmp((string)$a['plan_date'], (string)$b['plan_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return strcmp((string)$a['machine_no'], (string)$b['machine_no']);
        });
        usort($waitingQc, static fn(array $a, array $b): int => ((float)$b['pending_qc_qty']) <=> ((float)$a['pending_qc_qty']));
        usort($riskJobs, static function(array $a, array $b): int {
            $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
            $ar = $rank[(string)$a['risk_level']] ?? 0;
            $br = $rank[(string)$b['risk_level']] ?? 0;
            if ($ar !== $br) {
                return $br <=> $ar;
            }
            return ((float)$b['demand_shortage_qty']) <=> ((float)$a['demand_shortage_qty']);
        });

        $todayProgressPct = $todayPlannedQty > 0
            ? round(min(100.0, ($todayProducedQty / $todayPlannedQty) * 100.0), 2)
            : 0.0;

        $kpi = [
            'assigned_machines' => count($scopedMachines),
            'run_now_jobs' => count($runNow),
            'next_queue_jobs' => count($nextQueue),
            'delayed_jobs' => count($delayedJobs),
            'waiting_qc_jobs' => count($waitingQc),
            'risk_jobs' => count($riskJobs),
            'today_planned_qty' => round($todayPlannedQty, 2),
            'today_produced_qty' => round($todayProducedQty, 2),
            'today_progress_pct' => $todayProgressPct,
        ];

        return [
            'today' => $today,
            'date' => $date,
            'role_slug' => $roleSlug,
            'scope' => [
                'machine_id' => $machineId,
                'section' => $section,
                'assignee_mode' => $machineId > 0 ? 'machine_filter' : ($section !== '' ? 'section_filter' : 'operational_scope'),
                'scope_machine_ids' => $scopeMachineIds,
                'scope_part_ids' => $scopePartIds,
            ],
            'filters' => [
                'machines' => $allMachines,
                'sections' => $sections,
            ],
            'assigned_machines' => $scopedMachines,
            'run_now' => $runNow,
            'next_queue' => array_slice($nextQueue, 0, 20),
            'delayed_jobs' => array_slice($delayedJobs, 0, 20),
            'waiting_qc' => array_slice($waitingQc, 0, 20),
            'risk_jobs' => array_slice($riskJobs, 0, 20),
            'kpi' => $kpi,
            'ownership_board' => HandoffEngine::workboardForOwner('Machine Leader'),
        ];
    }

    private static function normalizeDate(string $date, string $fallback): string
    {
        if ($date === '') {
            return $fallback;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return $fallback;
        }
        return $date;
    }

    /**
     * @param array<int,array<string,mixed>> $machines
     * @return array<int,string>
     */
    private static function extractSections(array $machines): array
    {
        $sections = [];
        foreach ($machines as $m) {
            $section = trim((string)($m['section'] ?? ''));
            if ($section !== '') {
                $sections[$section] = true;
            }
        }
        $result = array_keys($sections);
        sort($result);
        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchScopedMachines(string $date, int $machineId, string $section, string $email, array $allowedMachineIds = []): array
    {
        $params = [$date, $date];
        $sql = "SELECT
                    m.id,
                    m.machine_no,
                    m.machine_name,
                    m.section,
                    m.status,
                    COALESCE(mp.today_jobs, 0) AS today_jobs,
                    COALESCE(mp.open_jobs, 0) AS open_jobs,
                    COALESCE(mp.overdue_jobs, 0) AS overdue_jobs,
                    CASE
                        WHEN ? <> '' AND LOWER(COALESCE(m.notes, '')) LIKE CONCAT('%', ?, '%') THEN 1
                        ELSE 0
                    END AS note_match
                FROM machines m
                LEFT JOIN (
                    SELECT
                        machine_id,
                        SUM(CASE WHEN plan_date = ? AND LOWER(COALESCE(status, '')) IN ('planned', 'in progress') THEN 1 ELSE 0 END) AS today_jobs,
                        SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('planned', 'in progress') THEN 1 ELSE 0 END) AS open_jobs,
                        SUM(CASE WHEN plan_date < ? AND LOWER(COALESCE(status, '')) IN ('planned', 'in progress') THEN 1 ELSE 0 END) AS overdue_jobs
                    FROM production_plans
                    GROUP BY machine_id
                ) mp ON mp.machine_id = m.id
                WHERE m.is_active = 1";

        array_unshift($params, $email, $email);

        if ($machineId > 0) {
            $sql .= ' AND m.id = ?';
            $params[] = $machineId;
        }
        if ($section !== '') {
            $sql .= ' AND m.section = ?';
            $params[] = $section;
        }
        if (!empty($allowedMachineIds)) {
            $placeholders = implode(',', array_fill(0, count($allowedMachineIds), '?'));
            $sql .= " AND m.id IN ({$placeholders})";
            foreach ($allowedMachineIds as $allowedMachineId) {
                $params[] = (int)$allowedMachineId;
            }
        }

        $sql .= ' ORDER BY note_match DESC, COALESCE(mp.open_jobs,0) DESC, m.machine_no ASC LIMIT 200';
        $rows = DB::fetchAll($sql, $params);

        if ($machineId > 0 || $section !== '') {
            return $rows;
        }

        $scoped = [];
        foreach ($rows as $row) {
            $openJobs = (int)($row['open_jobs'] ?? 0);
            $overdueJobs = (int)($row['overdue_jobs'] ?? 0);
            $noteMatch = (int)($row['note_match'] ?? 0);
            if ($noteMatch === 1 || $openJobs > 0 || $overdueJobs > 0) {
                $scoped[] = $row;
            }
        }

        if (!empty($scoped)) {
            return $scoped;
        }

        return array_slice($rows, 0, 6);
    }

    /**
     * @param array<int,int> $machineIds
     * @return array<int,array<string,mixed>>
     */
    private static function fetchPlanRows(array $machineIds, string $date, array $allowedPartIds = []): array
    {
        if (empty($machineIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($machineIds), '?'));
        $windowStart = date('Y-m-d', strtotime($date . ' -7 day'));
        $windowEnd = date('Y-m-d', strtotime($date . ' +7 day'));

        $params = [$date, $windowStart, $windowEnd];
        foreach ($machineIds as $mid) {
            $params[] = $mid;
        }

        $partSql = '';
        if (!empty($allowedPartIds)) {
            $partPlaceholders = implode(',', array_fill(0, count($allowedPartIds), '?'));
            $partSql = " AND pp.product_id IN ({$partPlaceholders})";
            foreach ($allowedPartIds as $partId) {
                $params[] = (int)$partId;
            }
        }

        return DB::fetchAll(
            "SELECT
                pp.id,
                pp.plan_date,
                pp.sequence_no,
                pp.status,
                pp.planned_qty,
                pp.machine_id,
                pp.product_id,
                m.machine_no,
                m.machine_name,
                m.section,
                p.parts_name,
                p.parts_number,
                p.model,
                COALESCE(pe_agg.produced_qty, 0) AS produced_qty,
                COALESCE(pe_agg.rejected_qty, 0) AS rejected_qty,
                COALESCE(pe_agg.good_qty, 0) AS good_qty,
                COALESCE(qc_agg.checked_qty, 0) AS qc_checked_qty,
                COALESCE(qc_agg.pass_qty, 0) AS qc_pass_qty,
                COALESCE(qc_agg.fail_qty, 0) AS qc_fail_qty,
                COALESCE(do_agg.low_orders, 0) AS low_orders,
                COALESCE(do_agg.partial_orders, 0) AS partial_orders,
                COALESCE(do_agg.shortage_qty, 0) AS demand_shortage_qty,
                COALESCE(do_agg.min_coverage_pct, 100) AS min_coverage_pct
            FROM production_plans pp
            INNER JOIN machines m ON m.id = pp.machine_id
            INNER JOIN products p ON p.id = pp.product_id
            LEFT JOIN (
                SELECT
                    machine_id,
                    product_id,
                    production_date,
                    SUM(produced_qty) AS produced_qty,
                    SUM(rejected_qty) AS rejected_qty,
                    SUM(good_qty) AS good_qty
                FROM production_entries
                GROUP BY machine_id, product_id, production_date
            ) pe_agg
                ON pe_agg.machine_id = pp.machine_id
               AND pe_agg.product_id = pp.product_id
               AND pe_agg.production_date = pp.plan_date
            LEFT JOIN (
                SELECT
                    production_plan_id,
                    SUM(checked_qty) AS checked_qty,
                    SUM(pass_qty) AS pass_qty,
                    SUM(fail_qty) AS fail_qty
                FROM qc_entries
                GROUP BY production_plan_id
            ) qc_agg ON qc_agg.production_plan_id = pp.id
            LEFT JOIN (
                SELECT
                    product_id,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                              AND LOWER(COALESCE(coverage_status, '')) = 'low' THEN 1 ELSE 0 END) AS low_orders,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                              AND LOWER(COALESCE(coverage_status, '')) = 'partial' THEN 1 ELSE 0 END) AS partial_orders,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                             THEN COALESCE(shortage_qty, 0) ELSE 0 END) AS shortage_qty,
                    MIN(CASE WHEN LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                             THEN COALESCE(coverage_pct, 100) END) AS min_coverage_pct
                FROM daily_orders
                GROUP BY product_id
            ) do_agg ON do_agg.product_id = pp.product_id
            WHERE pp.plan_date BETWEEN ? AND ?
              AND LOWER(COALESCE(pp.status, '')) NOT IN ('completed', 'closed', 'cancelled', 'canceled')
              AND pp.machine_id IN ({$placeholders})
                            {$partSql}
            ORDER BY pp.plan_date ASC, pp.sequence_no ASC, pp.id ASC",
            array_merge([$windowStart, $windowEnd], array_slice($params, 3))
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function decoratePlanRow(array $row, string $date): array
    {
        $planned = (float)($row['planned_qty'] ?? 0);
        $goodQty = (float)($row['good_qty'] ?? 0);
        $remaining = max(0.0, $planned - $goodQty);
        $progressPct = $planned > 0 ? round(min(100.0, ($goodQty / $planned) * 100.0), 2) : 0.0;

        $qcChecked = (float)($row['qc_checked_qty'] ?? 0);
        $pendingQc = max(0.0, $goodQty - $qcChecked);

        $minCoverage = (float)($row['min_coverage_pct'] ?? 100.0);
        $lowOrders = (int)($row['low_orders'] ?? 0);
        $partialOrders = (int)($row['partial_orders'] ?? 0);
        $shortageQty = (float)($row['demand_shortage_qty'] ?? 0);

        $riskLevel = 'low';
        if ($lowOrders > 0 || $minCoverage < 35.0 || $shortageQty > 0.0) {
            $riskLevel = 'high';
        } elseif ($partialOrders > 0 || $minCoverage < 80.0) {
            $riskLevel = 'medium';
        }

        $planDate = (string)($row['plan_date'] ?? '');
        $isDelayed = $planDate !== '' && $planDate < $date && $remaining > 0.0;

        $row['remaining_qty'] = round($remaining, 2);
        $row['progress_pct'] = $progressPct;
        $row['pending_qc_qty'] = round($pendingQc, 2);
        $row['risk_level'] = $riskLevel;
        $row['is_delayed'] = $isDelayed;
        $row['can_run_now'] = $remaining > 0.0 && $planDate <= $date;

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $machineRows
     * @return array<string,mixed>|null
     */
    private static function pickActivePlan(array $machineRows, string $date): ?array
    {
        foreach ($machineRows as $row) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ((bool)$row['can_run_now'] && $status === 'in progress') {
                return $row;
            }
        }
        foreach ($machineRows as $row) {
            if ((bool)$row['can_run_now'] && (float)$row['good_qty'] > 0.0) {
                return $row;
            }
        }
        foreach ($machineRows as $row) {
            if ((bool)$row['can_run_now']) {
                return $row;
            }
        }
        foreach ($machineRows as $row) {
            if ((string)$row['plan_date'] >= $date && (float)$row['remaining_qty'] > 0.0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $machineRows
     * @return array<int,array<string,mixed>>
     */
    private static function pickNextQueue(array $machineRows, string $date, int $activeId): array
    {
        $queue = [];
        foreach ($machineRows as $row) {
            if ((int)($row['id'] ?? 0) === $activeId) {
                continue;
            }
            if ((string)$row['plan_date'] < $date) {
                continue;
            }
            if ((float)$row['remaining_qty'] <= 0.0) {
                continue;
            }
            $queue[] = $row;
            if (count($queue) >= 2) {
                break;
            }
        }
        return $queue;
    }
}
