<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries\Services;

use App\Core\DB;
use App\Core\HandoffEngine;

final class DispatchLeaderDashboardService
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
        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $scope = (array)($ctx['scope'] ?? []);
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $taskTypes = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), (array)($scope['task_types'] ?? [])), static fn(string $v): bool => $v !== ''));
        $allowDispatchTasks = empty($taskTypes)
            || in_array('dispatch', $taskTypes, true)
            || in_array('dispatch_execution', $taskTypes, true)
            || in_array('logistics', $taskTypes, true);

        if (!$allowDispatchTasks) {
            $partIds = [-1];
        }

        $readyNow = self::fetchReadyNow($date, $partIds);
        $blockedHold = self::fetchBlockedHold($date, $partIds);
        $partialQueue = self::fetchPartialQueue($partIds);
        $agingOverdue = self::fetchAgingOverdue($date, $partIds);
        $backlog = self::fetchBacklogByDueDate($date, $partIds);
        $releaseCandidates = self::fetchTodayReleaseCandidates($date, $partIds);

        $urgentCount = count($blockedHold) + count($agingOverdue);

        $kpi = [
            'ready_now' => count($readyNow),
            'blocked_hold' => count($blockedHold),
            'partial_queue' => count($partialQueue),
            'aging_overdue' => count($agingOverdue),
            'release_candidates' => count($releaseCandidates),
            'urgent' => $urgentCount,
        ];

        return [
            'today' => $today,
            'date' => $date,
            'kpi' => $kpi,
            'ready_now' => $readyNow,
            'blocked_hold' => $blockedHold,
            'partial_queue' => $partialQueue,
            'aging_overdue' => $agingOverdue,
            'backlog_by_due' => $backlog,
            'release_candidates' => $releaseCandidates,
            'role_slug' => strtolower(trim((string)($user['role'] ?? ''))),
            'scope' => $scope,
            'ownership_board' => HandoffEngine::workboardForOwner('Dispatch Leader'),
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
     * @return array<int,array<string,mixed>>
     */
    private static function fetchReadyNow(string $date, array $partIds): array
    {
        $params = [$date];
        $partScopeSql = self::partScopeSql('d.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                d.id,
                d.dispatch_date,
                d.product_id,
                d.daily_order_id,
                d.qc_entry_id,
                d.dispatchable_qty,
                d.destination,
                d.dispatch_type,
                d.dispatch_status,
                d.approval_status,
                d.locked_at,
                p.parts_name,
                p.parts_number,
                COALESCE(o.qty, 0) AS order_qty,
                COALESCE(o.shortage_qty, 0) AS order_shortage_qty,
                COALESCE(st.stock_qty, 0) AS stock_qty,
                CASE
                    WHEN d.dispatch_date < ? THEN 1
                    ELSE 0
                END AS is_overdue
            FROM dispatch_entries d
            INNER JOIN products p ON p.id = d.product_id
            LEFT JOIN daily_orders o ON o.id = d.daily_order_id
            LEFT JOIN (
                SELECT product_id, COALESCE(SUM(qty_delta), 0) AS stock_qty
                FROM stock_ledger_entries
                GROUP BY product_id
            ) st ON st.product_id = d.product_id
            WHERE LOWER(COALESCE(d.dispatch_status, '')) = 'ready'
                            {$partScopeSql}
            ORDER BY is_overdue DESC, d.dispatch_date ASC, d.id ASC
            LIMIT 250",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchBlockedHold(string $date, array $partIds): array
    {
        $params = [$date];
        $partScopeSql = self::partScopeSql('d.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                d.id,
                d.dispatch_date,
                d.product_id,
                d.daily_order_id,
                d.dispatchable_qty,
                d.dispatch_status,
                d.destination,
                d.remarks,
                p.parts_name,
                p.parts_number,
                COALESCE(o.coverage_pct, 100) AS coverage_pct,
                COALESCE(o.shortage_qty, 0) AS shortage_qty,
                COALESCE(o.dispatch_deadline, NULL) AS dispatch_deadline,
                CASE
                    WHEN DATE(COALESCE(o.dispatch_deadline, d.dispatch_date)) < ? THEN 1
                    ELSE 0
                END AS is_overdue,
                CASE
                    WHEN COALESCE(o.shortage_qty, 0) > 0 THEN 'Stock risk'
                    WHEN COALESCE(o.coverage_pct, 100) < 100 THEN 'Coverage gap'
                    ELSE 'Manual hold'
                END AS blocker_hint
            FROM dispatch_entries d
            INNER JOIN products p ON p.id = d.product_id
            LEFT JOIN daily_orders o ON o.id = d.daily_order_id
            WHERE LOWER(COALESCE(d.dispatch_status, '')) IN ('hold', 'blocked')
                            {$partScopeSql}
            ORDER BY is_overdue DESC, d.dispatch_date ASC, d.id ASC
            LIMIT 250",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchPartialQueue(array $partIds): array
    {
        $params = [];
        $partScopeSql = self::partScopeSql('o.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                o.id AS daily_order_id,
                o.product_id,
                o.required_date,
                o.dispatch_deadline,
                o.qty AS demand_qty,
                p.parts_name,
                p.parts_number,
                COALESCE(disp.dispatched_qty, 0) AS dispatched_qty,
                GREATEST(o.qty - COALESCE(disp.dispatched_qty, 0), 0) AS balance_qty,
                COALESCE(o.coverage_pct, 100) AS coverage_pct,
                COALESCE(o.shortage_qty, 0) AS shortage_qty
            FROM daily_orders o
            INNER JOIN products p ON p.id = o.product_id
            LEFT JOIN (
                SELECT daily_order_id,
                       SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered') THEN dispatchable_qty ELSE 0 END) AS dispatched_qty
                FROM dispatch_entries
                GROUP BY daily_order_id
            ) disp ON disp.daily_order_id = o.id
            WHERE LOWER(COALESCE(o.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
              AND COALESCE(disp.dispatched_qty, 0) > 0
              AND COALESCE(disp.dispatched_qty, 0) < COALESCE(o.qty, 0)
                            {$partScopeSql}
            ORDER BY COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, CURDATE()), ' 23:59:59')) ASC, o.id ASC
                        LIMIT 250",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchAgingOverdue(string $date, array $partIds): array
    {
        $params = [$date, $date, $date];
        $partScopeSql = self::partScopeSql('d.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                d.id,
                d.dispatch_date,
                d.product_id,
                d.daily_order_id,
                d.dispatchable_qty,
                d.dispatch_status,
                p.parts_name,
                p.parts_number,
                COALESCE(o.dispatch_deadline, NULL) AS dispatch_deadline,
                COALESCE(o.required_date, NULL) AS required_date,
                CASE
                    WHEN d.dispatch_date < ? THEN 1
                    WHEN DATE(COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, ?), ' 23:59:59'))) < ? THEN 1
                    ELSE 0
                END AS overdue
            FROM dispatch_entries d
            INNER JOIN products p ON p.id = d.product_id
            LEFT JOIN daily_orders o ON o.id = d.daily_order_id
            WHERE LOWER(COALESCE(d.dispatch_status, '')) IN ('ready', 'hold', 'blocked')
                            {$partScopeSql}
            HAVING overdue = 1
            ORDER BY COALESCE(dispatch_deadline, CONCAT(COALESCE(required_date, dispatch_date), ' 23:59:59')) ASC, d.id ASC
            LIMIT 250",
                        $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchBacklogByDueDate(string $date, array $partIds): array
    {
        $params = [$date, $date, $date, $date, $date, $date];
        $partScopeSql = self::partScopeSql('o.product_id', $partIds, $params);
        return DB::fetchAll(
            "SELECT
                due_bucket,
                COUNT(*) AS orders,
                SUM(outstanding_qty) AS outstanding_qty,
                SUM(CASE WHEN COALESCE(shortage_qty, 0) > 0 THEN 1 ELSE 0 END) AS stock_risk_orders
            FROM (
                SELECT
                    o.id,
                    o.shortage_qty,
                    GREATEST(COALESCE(o.qty, 0) - COALESCE(disp.dispatched_qty, 0), 0) AS outstanding_qty,
                    CASE
                        WHEN DATE(COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, ?), ' 23:59:59'))) < ? THEN 'Overdue'
                        WHEN DATE(COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, ?), ' 23:59:59'))) = ? THEN 'Due Today'
                        WHEN DATE(COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, ?), ' 23:59:59'))) <= DATE_ADD(?, INTERVAL 2 DAY) THEN 'Next 48h'
                        ELSE 'Later'
                    END AS due_bucket
                FROM daily_orders o
                LEFT JOIN (
                    SELECT daily_order_id,
                           SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered') THEN dispatchable_qty ELSE 0 END) AS dispatched_qty
                    FROM dispatch_entries
                    GROUP BY daily_order_id
                ) disp ON disp.daily_order_id = o.id
                WHERE LOWER(COALESCE(o.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                                    {$partScopeSql}
            ) x
            WHERE outstanding_qty > 0
            GROUP BY due_bucket
            ORDER BY
                CASE due_bucket
                    WHEN 'Overdue' THEN 1
                    WHEN 'Due Today' THEN 2
                    WHEN 'Next 48h' THEN 3
                    ELSE 4
                END ASC",
            $params
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchTodayReleaseCandidates(string $date, array $partIds): array
    {
        $params = [$date];
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
                GREATEST(q.pass_qty - COALESCE(disp.dispatched_qty, 0), 0) AS releasable_qty,
                COALESCE(o.shortage_qty, 0) AS shortage_qty,
                COALESCE(st.stock_qty, 0) AS stock_qty
            FROM qc_entries q
            INNER JOIN products p ON p.id = q.product_id
            LEFT JOIN (
                  SELECT qc_entry_id,
                      SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered') THEN dispatchable_qty ELSE 0 END) AS dispatched_qty
                FROM dispatch_entries
                GROUP BY qc_entry_id
            ) disp ON disp.qc_entry_id = q.id
            LEFT JOIN daily_orders o ON o.id = q.daily_order_id
            LEFT JOIN (
                SELECT product_id, COALESCE(SUM(qty_delta), 0) AS stock_qty
                FROM stock_ledger_entries
                GROUP BY product_id
            ) st ON st.product_id = q.product_id
            WHERE LOWER(COALESCE(q.status, '')) NOT IN ('cancelled', 'canceled')
              AND DATE(COALESCE(q.updated_at, q.created_at)) <= ?
              AND q.pass_qty > COALESCE(disp.dispatched_qty, 0)
              {$partScopeSql}
            ORDER BY releasable_qty DESC, q.id DESC
            LIMIT 250",
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
}
