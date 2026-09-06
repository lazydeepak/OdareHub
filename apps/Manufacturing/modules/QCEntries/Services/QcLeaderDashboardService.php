<?php
declare(strict_types=1);

namespace Plugins\QCEntries\Services;

use App\Core\DB;
use App\Core\HandoffEngine;

final class QcLeaderDashboardService
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
        $priority = trim((string)($input['priority'] ?? ''));
        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $scope = (array)($ctx['scope'] ?? []);
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $taskTypes = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), (array)($scope['task_types'] ?? [])), static fn(string $v): bool => $v !== ''));
        $allowQcTasks = empty($taskTypes)
            || in_array('qc', $taskTypes, true)
            || in_array('qc_check', $taskTypes, true)
            || in_array('quality', $taskTypes, true);

        if (!$allowQcTasks) {
            $partIds = [-1];
        }

        $runNow = self::fetchRunNow($date, $priority, $partIds);
        $pendingQc = self::fetchPendingQc($date, $partIds);
        $failedQueue = self::fetchFailedQueue($date, $partIds);
        $readyDispatch = self::fetchReadyForDispatch($partIds);
        $backlog = self::fetchBacklogByPriority($date, $partIds);

        $urgentCount = 0;
        foreach ($runNow as $row) {
            if ((bool)($row['is_overdue'] ?? false)) {
                $urgentCount++;
            }
        }
        foreach ($failedQueue as $row) {
            if ((float)($row['fail_qty'] ?? 0) > 0 || strtolower((string)($row['status'] ?? '')) === 'recheck') {
                $urgentCount++;
            }
        }

        $sla = self::buildSlaIndicators($date, $runNow, $pendingQc, $failedQueue);

        $kpi = [
            'run_now' => count($runNow),
            'pending_qc' => count($pendingQc),
            'failed_recheck' => count($failedQueue),
            'ready_dispatch' => count($readyDispatch),
            'urgent' => $urgentCount,
            'overdue' => (int)($sla['overdue_plans'] ?? 0),
        ];

        return [
            'today' => $today,
            'date' => $date,
            'priority' => $priority,
            'filters' => [
                'priorities' => self::priorityOptions(),
            ],
            'kpi' => $kpi,
            'run_now' => $runNow,
            'pending_qc' => $pendingQc,
            'failed_recheck' => $failedQueue,
            'ready_dispatch' => $readyDispatch,
            'backlog_by_priority' => $backlog,
            'sla' => $sla,
            'role_slug' => strtolower(trim((string)($user['role'] ?? ''))),
            'scope' => $scope,
            'ownership_board' => HandoffEngine::workboardForOwner('QC Leader'),
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
     * @return array<int,string>
     */
    private static function priorityOptions(): array
    {
        return ['Critical', 'High', 'Normal', 'Low'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchRunNow(string $date, string $priority, array $partIds): array
    {
        $params = [$date, $date];
        $prioritySql = '';
        if ($priority !== '') {
            $prioritySql = ' AND q.priority = ?';
            $params[] = $priority;
        }
        $partScopeSql = self::partScopeSql('q.product_id', $partIds, $params);

        $rows = DB::fetchAll(
            "SELECT
                q.id,
                q.plan_date,
                q.required_date,
                q.priority,
                q.status,
                q.product_id,
                q.daily_order_id,
                q.production_entry_id,
                q.planned_qty,
                p.parts_name,
                p.parts_number,
                COALESCE(qc.checked_qty, 0) AS checked_qty,
                COALESCE(qc.pass_qty, 0) AS pass_qty,
                COALESCE(qc.fail_qty, 0) AS fail_qty,
                COALESCE(qc.open_entry_count, 0) AS open_entry_count,
                qc.open_entry_id,
                COALESCE(d.coverage_pct, 100) AS coverage_pct,
                COALESCE(d.shortage_qty, 0) AS shortage_qty,
                CASE
                    WHEN COALESCE(q.required_date, q.plan_date) < ? THEN 1
                    ELSE 0
                END AS is_overdue
            FROM qc_plans q
            INNER JOIN products p ON p.id = q.product_id
            LEFT JOIN daily_orders d ON d.id = q.daily_order_id
            LEFT JOIN (
                SELECT
                    qc_plan_id,
                    SUM(checked_qty) AS checked_qty,
                    SUM(pass_qty) AS pass_qty,
                    SUM(fail_qty) AS fail_qty,
                    SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'open' THEN 1 ELSE 0 END) AS open_entry_count,
                    MIN(CASE WHEN LOWER(COALESCE(status, '')) = 'open' THEN id ELSE NULL END) AS open_entry_id
                FROM qc_entries
                GROUP BY qc_plan_id
            ) qc ON qc.qc_plan_id = q.id
            WHERE LOWER(COALESCE(q.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
              AND q.plan_date <= ?
                            {$partScopeSql}
              {$prioritySql}
            ORDER BY
                is_overdue DESC,
                CASE LOWER(COALESCE(q.priority, 'normal'))
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END ASC,
                COALESCE(q.required_date, q.plan_date) ASC,
                q.id ASC",
            $params
        );

        foreach ($rows as &$row) {
            $planned = (float)($row['planned_qty'] ?? 0);
            $checked = (float)($row['checked_qty'] ?? 0);
            $remaining = max(0.0, $planned - $checked);
            $row['remaining_qty'] = round($remaining, 2);
            $row['is_urgent'] = (int)($row['is_overdue'] ?? 0) === 1 || strtolower((string)($row['priority'] ?? '')) === 'critical';
        }
        unset($row);

        return array_values(array_filter($rows, static function(array $r): bool {
            return (float)($r['remaining_qty'] ?? 0) > 0 || (int)($r['open_entry_count'] ?? 0) > 0;
        }));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchPendingQc(string $date, array $partIds): array
    {
        $params = [$date];
        $partScopeSql = self::partScopeSql('pe.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                pe.id AS production_entry_id,
                pe.production_date,
                pe.product_id,
                pe.good_qty,
                p.parts_name,
                p.parts_number,
                COALESCE(qe.checked_qty, 0) AS checked_qty,
                GREATEST(pe.good_qty - COALESCE(qe.checked_qty, 0), 0) AS pending_qty,
                COALESCE(d.coverage_pct, 100) AS coverage_pct,
                COALESCE(d.shortage_qty, 0) AS shortage_qty,
                CASE
                    WHEN pe.production_date < ? THEN 1
                    ELSE 0
                END AS is_overdue
            FROM production_entries pe
            INNER JOIN products p ON p.id = pe.product_id
            LEFT JOIN (
                SELECT product_id, DATE(created_at) AS qc_date, SUM(checked_qty) AS checked_qty
                FROM qc_entries
                GROUP BY product_id, DATE(created_at)
            ) qe ON qe.product_id = pe.product_id
               AND qe.qc_date = pe.production_date
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(coverage_pct, 100)) AS coverage_pct, SUM(COALESCE(shortage_qty, 0)) AS shortage_qty
                FROM daily_orders
                WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                GROUP BY product_id
            ) d ON d.product_id = pe.product_id
            WHERE pe.good_qty > COALESCE(qe.checked_qty, 0)
                            {$partScopeSql}
            ORDER BY is_overdue DESC, pe.production_date ASC, pe.id ASC
            LIMIT 200",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchFailedQueue(string $date, array $partIds): array
    {
        $params = [$date];
        $partScopeSql = self::partScopeSql('q.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                q.id,
                q.product_id,
                q.qc_plan_id,
                q.daily_order_id,
                q.checked_qty,
                q.pass_qty,
                q.fail_qty,
                q.status,
                q.remarks,
                q.updated_at,
                p.parts_name,
                p.parts_number,
                COALESCE(d.coverage_pct, 100) AS coverage_pct,
                COALESCE(d.shortage_qty, 0) AS shortage_qty,
                CASE
                    WHEN DATE(COALESCE(q.updated_at, q.created_at)) < ? THEN 1
                    ELSE 0
                END AS stale
            FROM qc_entries q
            INNER JOIN products p ON p.id = q.product_id
            LEFT JOIN daily_orders d ON d.id = q.daily_order_id
                WHERE (q.fail_qty > 0
                    OR LOWER(COALESCE(q.status, '')) IN ('recheck', 'failed', 'hold'))
                            {$partScopeSql}
            ORDER BY stale DESC, q.fail_qty DESC, q.id DESC
            LIMIT 200",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchBacklogByPriority(string $date, array $partIds): array
    {
        $params = [$date];
        $partScopeSql = self::partScopeSql('q.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                q.priority,
                COUNT(*) AS open_plans,
                SUM(GREATEST(q.planned_qty - COALESCE(qc.checked_qty, 0), 0)) AS pending_qty,
                SUM(CASE WHEN COALESCE(q.required_date, q.plan_date) < ? THEN 1 ELSE 0 END) AS overdue_plans
            FROM qc_plans q
            LEFT JOIN (
                SELECT qc_plan_id, SUM(checked_qty) AS checked_qty
                FROM qc_entries
                GROUP BY qc_plan_id
            ) qc ON qc.qc_plan_id = q.id
            WHERE LOWER(COALESCE(q.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                            {$partScopeSql}
            GROUP BY q.priority
            ORDER BY
                CASE LOWER(COALESCE(q.priority, 'normal'))
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END ASC",
            $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchReadyForDispatch(array $partIds): array
    {
        $params = [];
        $partScopeSql = self::partScopeSql('q.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                q.id AS qc_entry_id,
                q.product_id,
                q.daily_order_id,
                p.parts_name,
                p.parts_number,
                q.pass_qty,
                COALESCE(disp.dispatched_qty, 0) AS dispatched_qty,
                GREATEST(q.pass_qty - COALESCE(disp.dispatched_qty, 0), 0) AS ready_qty,
                q.status,
                q.updated_at
            FROM qc_entries q
            INNER JOIN products p ON p.id = q.product_id
            LEFT JOIN (
                SELECT qc_entry_id, SUM(dispatchable_qty) AS dispatched_qty
                FROM dispatch_entries
                GROUP BY qc_entry_id
            ) disp ON disp.qc_entry_id = q.id
            WHERE q.pass_qty > COALESCE(disp.dispatched_qty, 0)
              AND LOWER(COALESCE(q.status, '')) NOT IN ('cancelled', 'canceled')
              {$partScopeSql}
            ORDER BY ready_qty DESC, q.id DESC
            LIMIT 200",
            $params
        );
    }

    private static function partScopeSql(string $column, array $partIds, array &$params): string
    {
        if (empty($partIds)) {
            return '';
        }

        $placeholders = implode(',', array_fill(0, count($partIds), '?'));
        foreach ($partIds as $partId) {
            $params[] = (int)$partId;
        }
        return " AND {$column} IN ({$placeholders})";
    }

    /**
     * @param array<int,array<string,mixed>> $runNow
     * @param array<int,array<string,mixed>> $pendingQc
     * @param array<int,array<string,mixed>> $failedQueue
     * @return array<string,mixed>
     */
    private static function buildSlaIndicators(string $date, array $runNow, array $pendingQc, array $failedQueue): array
    {
        $overduePlans = 0;
        foreach ($runNow as $row) {
            if ((int)($row['is_overdue'] ?? 0) === 1) {
                $overduePlans++;
            }
        }

        $pendingOverdue = 0;
        foreach ($pendingQc as $row) {
            if ((int)($row['is_overdue'] ?? 0) === 1) {
                $pendingOverdue++;
            }
        }

        $staleFailures = 0;
        foreach ($failedQueue as $row) {
            if ((int)($row['stale'] ?? 0) === 1) {
                $staleFailures++;
            }
        }

        return [
            'date' => $date,
            'overdue_plans' => $overduePlans,
            'pending_overdue' => $pendingOverdue,
            'stale_failures' => $staleFailures,
        ];
    }
}
