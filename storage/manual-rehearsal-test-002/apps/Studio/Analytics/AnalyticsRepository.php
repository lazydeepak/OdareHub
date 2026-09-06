<?php
declare(strict_types=1);

namespace Apps\Studio\Analytics;

use App\Core\DB;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;

require_once APP_ROOT . '/app/Core/DB.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';

final class AnalyticsRepository
{
    private string $appKey;

    private string $ordersTable;

    private string $auditTable;

    public function __construct(string $appKey)
    {
        $this->appKey = StudioSchemaGovernanceService::sanitizeAppKey($appKey);
        $this->ordersTable = StudioSchemaGovernanceService::ordersTable($this->appKey);
        $this->auditTable = StudioSchemaGovernanceService::auditTable($this->appKey);
    }

    /**
     * Get KPI metrics: total orders, completed today, overdue count, avg processing time
     * @return array<string,mixed>
     */
    public function getKpiMetrics(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $today = gmdate('Y-m-d');

        // Total orders
        $totalResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " WHERE status = 'active' AND state != 'deleted'"
        );
        $totalOrders = (int)($totalResult['total'] ?? 0);

        // Completed today
        $completedResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state = 'completed' AND DATE(updated_at) = '" . str_replace("'", "''", $today) . "'"
        );
        $completedToday = (int)($completedResult['total'] ?? 0);

        // Overdue count
        $overdueResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state != 'completed' AND due_at IS NOT NULL AND due_at < UTC_TIMESTAMP()"
        );
        $overdueCount = (int)($overdueResult['total'] ?? 0);

        // Average processing time (approved → completed, in hours)
        $avgResult = DB::fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, approved_at, completed_at)) as avg_hours
             FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state = 'completed' AND approved_at IS NOT NULL AND completed_at IS NOT NULL"
        );
        $avgProcessingTime = (float)($avgResult['avg_hours'] ?? 0);

        // In progress count
        $inProgressResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state = 'processing'"
        );
        $inProgress = (int)($inProgressResult['total'] ?? 0);

        // Pending approval
        $pendingResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state = 'draft'"
        );
        $pending = (int)($pendingResult['total'] ?? 0);

        // Average turnaround time (created → completed, in days)
        $turnaroundResult = DB::fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(DAY, created_at, completed_at)) as avg_days
             FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state = 'completed' AND completed_at IS NOT NULL"
        );
        $avgTurnaroundDays = (float)($turnaroundResult['avg_days'] ?? 0);

        return [
            'total_orders' => $totalOrders,
            'completed_today' => $completedToday,
            'overdue_count' => $overdueCount,
            'in_progress' => $inProgress,
            'pending_approval' => $pending,
            'avg_processing_hours' => round($avgProcessingTime, 2),
            'avg_turnaround_days' => round($avgTurnaroundDays, 2),
        ];
    }

    /**
     * Get performance metrics: state transition times, completion rates
     * @return array<string,mixed>
     */
    public function getPerformanceMetrics(): array
    {
        // State transition times (average time spent in each state)
        $stateTimesResult = DB::fetchAll(
            'SELECT 
                CASE 
                    WHEN payload_json LIKE \'%"to_state":"draft"%\' THEN "draft"
                    WHEN payload_json LIKE \'%"to_state":"approved"%\' THEN "approved"
                    WHEN payload_json LIKE \'%"to_state":"processing"%\' THEN "processing"
                    WHEN payload_json LIKE \'%"to_state":"completed"%\' THEN "completed"
                    ELSE "unknown"
                END as state,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(HOUR, created_at, created_at)) as avg_duration
            FROM ' . $this->auditTable . " 
            WHERE action = 'workflow_transition' AND entity = 'orders'
            GROUP BY state
            ORDER BY count DESC"
        );

        $stateCounts = [];
        if (is_array($stateTimesResult)) {
            foreach ($stateTimesResult as $row) {
                if (is_array($row)) {
                    $state = (string)($row['state'] ?? 'unknown');
                    $stateCounts[$state] = [
                        'transitions' => (int)($row['count'] ?? 0),
                    ];
                }
            }
        }

        // Completion rate (completed / total)
        $completedResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " WHERE status = 'active' AND state = 'completed'"
        );
        $completed = (int)($completedResult['total'] ?? 0);

        $totalResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " WHERE status = 'active' AND state != 'deleted'"
        );
        $total = (int)($totalResult['total'] ?? 0);

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        // Average approval time (draft → approved)
        $approvalResult = DB::fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_hours
             FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND approved_at IS NOT NULL"
        );
        $avgApprovalHours = (float)($approvalResult['avg_hours'] ?? 0);

        return [
            'completion_rate' => $completionRate,
            'completed_total' => $completed,
            'total_orders' => $total,
            'avg_approval_hours' => round($avgApprovalHours, 2),
            'state_transitions' => $stateCounts,
        ];
    }

    /**
     * Get user workload: tasks per user, overdue per user, active tasks per user
     * @return array<int,array<string,mixed>>
     */
    public function getUserWorkload(): array
    {
        $workload = DB::fetchAll(
            'SELECT 
                o.assigned_to,
                COUNT(*) as total_tasks,
                SUM(CASE WHEN o.state = \'processing\' THEN 1 ELSE 0 END) as active_tasks,
                SUM(CASE WHEN o.state != \'completed\' AND o.due_at IS NOT NULL AND o.due_at < UTC_TIMESTAMP() THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(CASE WHEN o.state = \'completed\' AND DATE(o.updated_at) = DATE(UTC_TIMESTAMP()) THEN 1 ELSE 0 END) as completed_today,
                MAX(o.created_at) as last_activity
            FROM ' . $this->ordersTable . " o
            WHERE o.status = 'active' AND o.assigned_to IS NOT NULL AND o.assigned_to > 0
            GROUP BY o.assigned_to
            ORDER BY o.assigned_to ASC"
        );

        $result = [];
        if (is_array($workload)) {
            foreach ($workload as $row) {
                if (is_array($row)) {
                    $userId = (int)($row['assigned_to'] ?? 0);
                    if ($userId > 0) {
                        $result[$userId] = [
                            'user_id' => $userId,
                            'total_tasks' => (int)($row['total_tasks'] ?? 0),
                            'active_tasks' => (int)($row['active_tasks'] ?? 0),
                            'overdue_tasks' => (int)($row['overdue_tasks'] ?? 0),
                            'completed_today' => (int)($row['completed_today'] ?? 0),
                            'last_activity' => (string)($row['last_activity'] ?? ''),
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get unassigned task count
     * @return int
     */
    public function getUnassignedTaskCount(): int
    {
        $result = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state != 'deleted' AND (assigned_to IS NULL OR assigned_to <= 0)"
        );
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get priority distribution
     * @return array<string,int>
     */
    public function getPriorityDistribution(): array
    {
        $result = DB::fetchAll(
            'SELECT priority, COUNT(*) as count FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state != 'deleted'
             GROUP BY priority
             ORDER BY priority ASC"
        );

        $distribution = ['high' => 0, 'medium' => 0, 'low' => 0];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $priority = strtolower(trim((string)($row['priority'] ?? 'medium')));
                    if (in_array($priority, ['high', 'medium', 'low'], true)) {
                        $distribution[$priority] = (int)($row['count'] ?? 0);
                    }
                }
            }
        }

        return $distribution;
    }

    /**
     * Get state distribution
     * @return array<string,int>
     */
    public function getStateDistribution(): array
    {
        $result = DB::fetchAll(
            'SELECT state, COUNT(*) as count FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state != 'deleted'
             GROUP BY state
             ORDER BY state ASC"
        );

        $distribution = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $state = strtolower(trim((string)($row['state'] ?? 'draft')));
                    $distribution[$state] = (int)($row['count'] ?? 0);
                }
            }
        }

        return $distribution;
    }

    /**
     * Get overdue orders details for dashboard
     * @return array<int,array<string,mixed>>
     */
    public function getOverdueOrders(): array
    {
        $result = DB::fetchAll(
            'SELECT id, order_no, customer, priority, state, assigned_to, due_at, created_at
             FROM ' . $this->ordersTable . " 
             WHERE status = 'active' AND state != 'deleted' AND state != 'completed' 
             AND due_at IS NOT NULL AND due_at < UTC_TIMESTAMP()
             ORDER BY due_at ASC
             LIMIT 20"
        );

        $orders = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $orders[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'order_no' => (string)($row['order_no'] ?? ''),
                        'customer' => (string)($row['customer'] ?? ''),
                        'priority' => strtolower(trim((string)($row['priority'] ?? 'medium'))),
                        'state' => strtolower(trim((string)($row['state'] ?? 'draft'))),
                        'assigned_to' => (int)($row['assigned_to'] ?? 0),
                        'due_at' => (string)($row['due_at'] ?? ''),
                        'created_at' => (string)($row['created_at'] ?? ''),
                    ];
                }
            }
        }

        return $orders;
    }

    /**
     * Get daily trend data: orders created per day for last N days
     * @param int $days
     * @return array<int,array<string,mixed>>
     */
    public function getDailyTrends(int $days = 7): array
    {
        $result = DB::fetchAll(
            'SELECT 
                DATE(created_at) as day,
                COUNT(*) as count
            FROM ' . $this->ordersTable . " 
            WHERE status = 'active' AND state != 'deleted' AND created_at >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC",
            [$days]
        );

        $trend = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $trend[] = [
                        'day' => (string)($row['day'] ?? ''),
                        'count' => (int)($row['count'] ?? 0),
                    ];
                }
            }
        }

        // Fill missing days with 0
        $filled = $this->fillMissingDays($trend, $days);
        return $filled;
    }

    /**
     * Get daily completion trend: orders completed per day for last N days
     * @param int $days
     * @return array<int,array<string,mixed>>
     */
    public function getCompletionTrend(int $days = 7): array
    {
        $result = DB::fetchAll(
            'SELECT 
                DATE(completed_at) as day,
                COUNT(*) as count
            FROM ' . $this->ordersTable . " 
            WHERE status = 'active' AND state = 'completed' AND completed_at IS NOT NULL 
            AND completed_at >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
            GROUP BY DATE(completed_at)
            ORDER BY day ASC",
            [$days]
        );

        $trend = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $trend[] = [
                        'day' => (string)($row['day'] ?? ''),
                        'count' => (int)($row['count'] ?? 0),
                    ];
                }
            }
        }

        // Fill missing days with 0
        $filled = $this->fillMissingDays($trend, $days);
        return $filled;
    }

    /**
     * Get daily overdue trend: orders that became overdue per day for last N days
     * @param int $days
     * @return array<int,array<string,mixed>>
     */
    public function getOverdueTrend(int $days = 7): array
    {
        $result = DB::fetchAll(
            'SELECT 
                DATE(due_at) as day,
                COUNT(*) as count
            FROM ' . $this->ordersTable . " 
            WHERE status = 'active' AND state != 'deleted' AND state != 'completed' 
            AND due_at IS NOT NULL AND due_at < UTC_TIMESTAMP()
            AND due_at >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
            GROUP BY DATE(due_at)
            ORDER BY day ASC",
            [$days]
        );

        $trend = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $trend[] = [
                        'day' => (string)($row['day'] ?? ''),
                        'count' => (int)($row['count'] ?? 0),
                    ];
                }
            }
        }

        // Fill missing days with 0
        $filled = $this->fillMissingDays($trend, $days);
        return $filled;
    }

    /**
     * Fill missing days in trend data with count 0
     * @param array<int,array<string,mixed>> $trend
     * @param int $days
     * @return array<int,array<string,mixed>>
     */
    private function fillMissingDays(array $trend, int $days): array
    {
        $now = time();
        $filled = [];
        $trendByDay = [];

        // Build map of days with data
        foreach ($trend as $item) {
            if (is_array($item)) {
                $trendByDay[(string)($item['day'] ?? '')] = (int)($item['count'] ?? 0);
            }
        }

        // Generate all days in range and fill gaps
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayTs = $now - ($i * 86400);
            $day = gmdate('Y-m-d', $dayTs);
            $filled[] = [
                'day' => $day,
                'count' => (int)($trendByDay[$day] ?? 0),
            ];
        }

        return $filled;
    }

    /**
     * Get detailed orders for drill-down: filtered by type and optional date range
     * @param string $type (overdue|completed|processing|draft|all)
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public function getDrilldownOrders(string $type, int $limit = 50, int $offset = 0): array
    {
        $safeType = in_array($type, ['overdue', 'completed', 'processing', 'draft', 'all'], true) ? $type : 'all';

        $whereClause = "WHERE status = 'active' AND state != 'deleted'";
        if ($safeType === 'overdue') {
            $whereClause .= " AND state != 'completed' AND due_at IS NOT NULL AND due_at < UTC_TIMESTAMP()";
        } elseif ($safeType === 'completed') {
            $whereClause .= " AND state = 'completed'";
        } elseif ($safeType === 'processing') {
            $whereClause .= " AND state = 'processing'";
        } elseif ($safeType === 'draft') {
            $whereClause .= " AND state = 'draft'";
        }

        $result = DB::fetchAll(
            'SELECT id, order_no, customer, qty, priority, state, assigned_to, due_at, created_at, completed_at
            FROM ' . $this->ordersTable . " 
            $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?",
            [$limit, $offset]
        );

        $orders = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                if (is_array($row)) {
                    $orders[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'order_no' => (string)($row['order_no'] ?? ''),
                        'customer' => (string)($row['customer'] ?? ''),
                        'qty' => (float)($row['qty'] ?? 0),
                        'priority' => strtolower(trim((string)($row['priority'] ?? 'medium'))),
                        'state' => strtolower(trim((string)($row['state'] ?? 'draft'))),
                        'assigned_to' => (int)($row['assigned_to'] ?? 0),
                        'due_at' => (string)($row['due_at'] ?? ''),
                        'created_at' => (string)($row['created_at'] ?? ''),
                        'completed_at' => (string)($row['completed_at'] ?? ''),
                    ];
                }
            }
        }

        return $orders;
    }

    /**
     * Get count of drill-down orders (for pagination)
     * @param string $type
     * @return int
     */
    public function getDrilldownOrderCount(string $type): int
    {
        $safeType = in_array($type, ['overdue', 'completed', 'processing', 'draft', 'all'], true) ? $type : 'all';

        $whereClause = "WHERE status = 'active' AND state != 'deleted'";
        if ($safeType === 'overdue') {
            $whereClause .= " AND state != 'completed' AND due_at IS NOT NULL AND due_at < UTC_TIMESTAMP()";
        } elseif ($safeType === 'completed') {
            $whereClause .= " AND state = 'completed'";
        } elseif ($safeType === 'processing') {
            $whereClause .= " AND state = 'processing'";
        } elseif ($safeType === 'draft') {
            $whereClause .= " AND state = 'draft'";
        }

        $result = DB::fetchOne(
            'SELECT COUNT(*) as total FROM ' . $this->ordersTable . " $whereClause"
        );

        return (int)($result['total'] ?? 0);
    }

    // ===== MANUFACTURING MODULES =====

    /**
     * Get QC metrics: pass rate, fail rate, rework count
     * @return array<string,mixed>
     */
    public function getQcMetrics(): array
    {
        // Total checked
        $totalResult = DB::fetchOne(
            'SELECT SUM(checked_qty) as total FROM qc_entries WHERE status != \'Cancelled\''
        );
        $totalChecked = (float)($totalResult['total'] ?? 0);

        // Total passed
        $passResult = DB::fetchOne(
            'SELECT SUM(pass_qty) as total FROM qc_entries WHERE status != \'Cancelled\''
        );
        $totalPass = (float)($passResult['total'] ?? 0);

        // Total failed
        $failResult = DB::fetchOne(
            'SELECT SUM(fail_qty) as total FROM qc_entries WHERE status != \'Cancelled\''
        );
        $totalFail = (float)($failResult['total'] ?? 0);

        // Rework count (failed items that need rework)
        $reworkResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM qc_entries WHERE status = \'Rework\' OR status = \'Failed\''
        );
        $reworkCount = (int)($reworkResult['total'] ?? 0);

        // Pass rate
        $passRate = $totalChecked > 0 ? round(($totalPass / $totalChecked) * 100, 2) : 0;

        // Fail rate
        $failRate = $totalChecked > 0 ? round(($totalFail / $totalChecked) * 100, 2) : 0;

        return [
            'total_checked' => round($totalChecked, 2),
            'total_pass' => round($totalPass, 2),
            'total_fail' => round($totalFail, 2),
            'pass_rate' => $passRate,
            'fail_rate' => $failRate,
            'rework_count' => $reworkCount,
        ];
    }

    /**
     * Get dispatch metrics: dispatched today, pending, delivery delay
     * @return array<string,mixed>
     */
    public function getDispatchMetrics(): array
    {
        $today = gmdate('Y-m-d');

        // Dispatched today
        $dispatchedResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM dispatch_entries 
             WHERE dispatch_status = \'Dispatched\' AND DATE(dispatched_at) = ?',
            [$today]
        );
        $dispatchedToday = (int)($dispatchedResult['total'] ?? 0);

        // Pending dispatch
        $pendingResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM dispatch_entries 
             WHERE dispatch_status IN (\'Ready\', \'Prepared\') AND DATE(dispatch_date) <= ?',
            [$today]
        );
        $pendingDispatch = (int)($pendingResult['total'] ?? 0);

        // Delivery delay (dispatched but not delivered beyond SLA)
        $delayResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM dispatch_entries 
             WHERE dispatch_status = \'Dispatched\' AND dispatched_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)'
        );
        $deliveryDelay = (int)($delayResult['total'] ?? 0);

        // Total dispatchable quantity pending
        $qtyResult = DB::fetchOne(
            'SELECT SUM(dispatchable_qty) as total FROM dispatch_entries 
             WHERE dispatch_status IN (\'Ready\', \'Prepared\')'
        );
        $pendingQuantity = (float)($qtyResult['total'] ?? 0);

        return [
            'dispatched_today' => $dispatchedToday,
            'pending_dispatch' => $pendingDispatch,
            'delivery_delay' => $deliveryDelay,
            'pending_quantity' => round($pendingQuantity, 2),
        ];
    }

    /**
     * Get assembly metrics: completed, pending, avg time
     * @return array<string,mixed>
     */
    public function getAssemblyMetrics(): array
    {
        $today = gmdate('Y-m-d');

        // Assembly completed today
        $completedResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM mfg_assembly_entries 
             WHERE status = \'completed\' AND DATE(updated_at) = ?',
            [$today]
        );
        $completedToday = (int)($completedResult['total'] ?? 0);

        // Assembly pending (in_progress, draft)
        $pendingResult = DB::fetchOne(
            'SELECT COUNT(*) as total FROM mfg_assembly_entries 
             WHERE status IN (\'draft\', \'in_progress\')'
        );
        $pendingAssembly = (int)($pendingResult['total'] ?? 0);

        // Average assembly time (from created to completed, in hours)
        $avgTimeResult = DB::fetchOne(
            'SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours 
             FROM mfg_assembly_entries WHERE status = \'completed\' AND completed_qty > 0'
        );
        $avgAssemblyTime = (float)($avgTimeResult['avg_hours'] ?? 0);

        // Total completed quantity
        $qtyResult = DB::fetchOne(
            'SELECT SUM(completed_qty) as total FROM mfg_assembly_entries 
             WHERE status = \'completed\''
        );
        $totalCompleted = (float)($qtyResult['total'] ?? 0);

        // Rejection rate
        $totalPlannedResult = DB::fetchOne(
            'SELECT SUM(planned_qty) as total FROM mfg_assembly_entries'
        );
        $totalPlanned = (float)($totalPlannedResult['total'] ?? 0);

        $totalRejectedResult = DB::fetchOne(
            'SELECT SUM(rejected_qty) as total FROM mfg_assembly_entries'
        );
        $totalRejected = (float)($totalRejectedResult['total'] ?? 0);

        $rejectionRate = $totalPlanned > 0 ? round(($totalRejected / $totalPlanned) * 100, 2) : 0;

        return [
            'completed_today' => $completedToday,
            'pending_assembly' => $pendingAssembly,
            'avg_assembly_hours' => round($avgAssemblyTime, 2),
            'total_completed_qty' => round($totalCompleted, 2),
            'rejection_rate' => $rejectionRate,
        ];
    }

    /**
     * Get production metrics: output, machine utilization, downtime
     * @return array<string,mixed>
     */
    public function getProductionMetrics(): array
    {
        $today = gmdate('Y-m-d');

        // Production output today
        $todayResult = DB::fetchOne(
            'SELECT SUM(produced_qty) as total FROM production_entries WHERE DATE(production_date) = ?',
            [$today]
        );
        $productionToday = (float)($todayResult['total'] ?? 0);

        // Total machines
        $machineCountResult = DB::fetchOne(
            'SELECT COUNT(DISTINCT machine_id) as total FROM production_entries'
        );
        $totalMachines = (int)($machineCountResult['total'] ?? 0);

        // Active machines (with production today)
        $activeMachineResult = DB::fetchOne(
            'SELECT COUNT(DISTINCT machine_id) as total FROM production_entries 
             WHERE DATE(production_date) = ? AND produced_qty > 0',
            [$today]
        );
        $activeMachines = (int)($activeMachineResult['total'] ?? 0);

        // Machine utilization percentage
        $machineUtilization = $totalMachines > 0 ? round(($activeMachines / $totalMachines) * 100, 1) : 0;

        // Good output vs rejected (quality metric)
        $goodQtyResult = DB::fetchOne(
            'SELECT SUM(good_qty) as total FROM production_entries WHERE DATE(production_date) = ?',
            [$today]
        );
        $goodQty = (float)($goodQtyResult['total'] ?? 0);

        $rejectedQtyResult = DB::fetchOne(
            'SELECT SUM(rejected_qty) as total FROM production_entries WHERE DATE(production_date) = ?',
            [$today]
        );
        $rejectedQty = (float)($rejectedQtyResult['total'] ?? 0);

        // Overall equipment effectiveness (OEE approximation)
        $oeeResult = DB::fetchOne(
            'SELECT 
                SUM(good_qty) as good,
                SUM(produced_qty) as produced,
                COUNT(DISTINCT DATE(production_date)) as production_days
             FROM production_entries 
             WHERE DATE(production_date) >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL 7 DAY)'
        );
        $totalGood = (float)($oeeResult['good'] ?? 0);
        $totalProduced = (float)($oeeResult['produced'] ?? 0);
        $oee = $totalProduced > 0 ? round(($totalGood / $totalProduced) * 100, 2) : 0;

        return [
            'production_today' => round($productionToday, 2),
            'total_machines' => $totalMachines,
            'active_machines' => $activeMachines,
            'machine_utilization' => $machineUtilization,
            'good_qty' => round($goodQty, 2),
            'rejected_qty' => round($rejectedQty, 2),
            'overall_equipment_effectiveness' => $oee,
        ];
    }

    /**
     * Get QC pass rate trend per day
     * @return array<int,array<string,mixed>>
     */
    public function getQcPassRatePerDay(int $days = 7): array
    {
        $rows = DB::fetchAll(
            "SELECT
                DATE(created_at) as day,
                CASE
                    WHEN SUM(checked_qty) > 0 THEN ROUND((SUM(pass_qty) / SUM(checked_qty)) * 100, 2)
                    ELSE 0
                END as value
            FROM qc_entries
            WHERE status != 'Cancelled'
              AND created_at >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC",
            [$days]
        );

        $trend = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $trend[] = [
                'day' => (string)($row['day'] ?? ''),
                'value' => (float)($row['value'] ?? 0),
            ];
        }

        return $this->fillMissingValueDays($trend, $days);
    }

    /**
     * Get production output trend per day
     * @return array<int,array<string,mixed>>
     */
    public function getProductionOutputPerDay(int $days = 7): array
    {
        $rows = DB::fetchAll(
            'SELECT
                production_date as day,
                ROUND(SUM(produced_qty), 2) as value
             FROM production_entries
             WHERE production_date >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
             GROUP BY production_date
             ORDER BY day ASC',
            [$days]
        );

        $trend = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $trend[] = [
                'day' => (string)($row['day'] ?? ''),
                'value' => (float)($row['value'] ?? 0),
            ];
        }

        return $this->fillMissingValueDays($trend, $days);
    }

    /**
     * Get dispatch delay trend per day
     * @return array<int,array<string,mixed>>
     */
    public function getDispatchDelayPerDay(int $days = 7): array
    {
        $rows = DB::fetchAll(
            "SELECT
                DATE(dispatch_date) as day,
                COUNT(*) as value
             FROM dispatch_entries
             WHERE dispatch_date >= DATE_SUB(DATE(UTC_TIMESTAMP()), INTERVAL ? DAY)
               AND (
                    (dispatch_status IN ('Ready', 'Prepared', 'Blocked') AND dispatch_date < UTC_DATE())
                    OR
                    (dispatch_status = 'Dispatched' AND dispatched_at IS NOT NULL AND DATE(dispatched_at) > dispatch_date)
               )
             GROUP BY DATE(dispatch_date)
             ORDER BY day ASC",
            [$days]
        );

        $trend = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $trend[] = [
                'day' => (string)($row['day'] ?? ''),
                'value' => (float)($row['value'] ?? 0),
            ];
        }

        return $this->fillMissingValueDays($trend, $days);
    }

    /**
     * Fill missing days in value trend data with value 0
     * @param array<int,array<string,mixed>> $trend
     * @return array<int,array<string,mixed>>
     */
    private function fillMissingValueDays(array $trend, int $days): array
    {
        $now = time();
        $filled = [];
        $trendByDay = [];

        foreach ($trend as $item) {
            if (is_array($item)) {
                $trendByDay[(string)($item['day'] ?? '')] = (float)($item['value'] ?? 0);
            }
        }

        for ($i = $days - 1; $i >= 0; $i--) {
            $dayTs = $now - ($i * 86400);
            $day = gmdate('Y-m-d', $dayTs);
            $filled[] = [
                'day' => $day,
                'value' => (float)($trendByDay[$day] ?? 0),
            ];
        }

        return $filled;
    }

    /**
     * Get QC drilldown entries with optional type filtering
     * @param string $type Filter: 'open', 'closed', 'draft', 'submitted', 'approved', 'fail', 'rework', or 'all'
     * @param int $limit Records per page
     * @param int $offset Pagination offset
     * @return array<int,array<string,mixed>>
     */
    public function getQcDrilldown(string $type, int $limit = 50, int $offset = 0): array
    {
        $whereClause = "WHERE status != 'Cancelled'";
        $params = [];

        if ($type === 'open') {
            $whereClause .= " AND status = 'Open'";
        } elseif ($type === 'closed') {
            $whereClause .= " AND status = 'Closed'";
        } elseif ($type === 'draft') {
            $whereClause .= " AND approval_status = 'Draft'";
        } elseif ($type === 'submitted') {
            $whereClause .= " AND approval_status = 'Submitted'";
        } elseif ($type === 'approved') {
            $whereClause .= " AND approval_status = 'Approved'";
        } elseif ($type === 'fail') {
            $whereClause .= " AND (fail_qty > 0 OR LOWER(status) = ? OR LOWER(workflow_state) = ?)";
            $params[] = 'rework';
            $params[] = 'rework';
        } elseif ($type === 'rework') {
            $whereClause .= " AND (LOWER(status) = ? OR LOWER(workflow_state) = ?)";
            $params[] = 'rework';
            $params[] = 'rework';
        }

        $query = "SELECT 
            id, qc_type, checked_qty, pass_qty, fail_qty, status, 
            approval_status, created_at, updated_at
        FROM qc_entries 
        $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";

        $rows = DB::fetchAll($query, array_merge($params, [$limit, $offset]));
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = [
                'id' => (int)($row['id'] ?? 0),
                'qc_type' => (string)($row['qc_type'] ?? ''),
                'checked_qty' => (float)($row['checked_qty'] ?? 0),
                'pass_qty' => (float)($row['pass_qty'] ?? 0),
                'fail_qty' => (float)($row['fail_qty'] ?? 0),
                'status' => strtolower((string)($row['status'] ?? '')),
                'approval_status' => strtolower((string)($row['approval_status'] ?? '')),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Get QC drilldown total count for pagination
     */
    public function getQcDrilldownCount(string $type): int
    {
        $whereClause = "WHERE status != 'Cancelled'";
        $params = [];

        if ($type === 'open') {
            $whereClause .= " AND status = 'Open'";
        } elseif ($type === 'closed') {
            $whereClause .= " AND status = 'Closed'";
        } elseif ($type === 'draft') {
            $whereClause .= " AND approval_status = 'Draft'";
        } elseif ($type === 'submitted') {
            $whereClause .= " AND approval_status = 'Submitted'";
        } elseif ($type === 'approved') {
            $whereClause .= " AND approval_status = 'Approved'";
        } elseif ($type === 'fail') {
            $whereClause .= " AND (fail_qty > 0 OR LOWER(status) = ? OR LOWER(workflow_state) = ?)";
            $params[] = 'rework';
            $params[] = 'rework';
        } elseif ($type === 'rework') {
            $whereClause .= " AND (LOWER(status) = ? OR LOWER(workflow_state) = ?)";
            $params[] = 'rework';
            $params[] = 'rework';
        }

        $result = DB::fetchOne("SELECT COUNT(*) as total FROM qc_entries $whereClause", $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get Dispatch drilldown entries with optional type filtering
     * @param string $type Filter: 'pending', 'delayed', 'ready', 'dispatched', 'blocked', 'completed', or 'all'
     */
    public function getDispatchDrilldown(string $type, int $limit = 50, int $offset = 0): array
    {
        $whereClause = 'WHERE 1=1';
        $params = [];

        if ($type === 'pending' || $type === 'ready') {
            $whereClause .= " AND dispatch_status IN ('Ready', 'Prepared')";
        } elseif ($type === 'delayed') {
            $whereClause .= " AND ((dispatch_status IN ('Ready', 'Prepared', 'Blocked') AND dispatch_date < UTC_DATE()) OR (dispatch_status = 'Dispatched' AND dispatched_at IS NOT NULL AND DATE(dispatched_at) > dispatch_date))";
        } elseif ($type === 'dispatched') {
            $whereClause .= " AND dispatch_status = 'Dispatched'";
        } elseif ($type === 'blocked') {
            $whereClause .= " AND dispatch_status = 'Blocked'";
        } elseif ($type === 'completed') {
            $whereClause .= " AND dispatch_status = 'Completed'";
        }

        $query = "SELECT 
            id, dispatch_date, dispatchable_qty, destination, dispatch_status, 
            dispatch_type, cases_count, pallets_count, dispatched_at, created_at
        FROM dispatch_entries 
        $whereClause
        ORDER BY dispatch_date DESC, created_at DESC
        LIMIT ? OFFSET ?";

        $rows = DB::fetchAll($query, array_merge($params, [$limit, $offset]));
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = [
                'id' => (int)($row['id'] ?? 0),
                'dispatch_date' => (string)($row['dispatch_date'] ?? ''),
                'dispatchable_qty' => (float)($row['dispatchable_qty'] ?? 0),
                'destination' => (string)($row['destination'] ?? ''),
                'dispatch_status' => strtolower((string)($row['dispatch_status'] ?? '')),
                'dispatch_type' => (string)($row['dispatch_type'] ?? ''),
                'cases_count' => (int)($row['cases_count'] ?? 0),
                'pallets_count' => (int)($row['pallets_count'] ?? 0),
                'dispatched_at' => (string)($row['dispatched_at'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Get Dispatch drilldown total count
     */
    public function getDispatchDrilldownCount(string $type): int
    {
        $whereClause = 'WHERE 1=1';
        $params = [];

        if ($type === 'pending' || $type === 'ready') {
            $whereClause .= " AND dispatch_status IN ('Ready', 'Prepared')";
        } elseif ($type === 'delayed') {
            $whereClause .= " AND ((dispatch_status IN ('Ready', 'Prepared', 'Blocked') AND dispatch_date < UTC_DATE()) OR (dispatch_status = 'Dispatched' AND dispatched_at IS NOT NULL AND DATE(dispatched_at) > dispatch_date))";
        } elseif ($type === 'dispatched') {
            $whereClause .= " AND dispatch_status = 'Dispatched'";
        } elseif ($type === 'blocked') {
            $whereClause .= " AND dispatch_status = 'Blocked'";
        } elseif ($type === 'completed') {
            $whereClause .= " AND dispatch_status = 'Completed'";
        }

        $result = DB::fetchOne("SELECT COUNT(*) as total FROM dispatch_entries $whereClause", $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get Assembly drilldown entries with optional type filtering
     * @param string $type Filter: 'pending', 'high_rejection', 'draft', 'in_progress', 'completed', 'approved', 'blocked', or 'all'
     */
    public function getAssemblyDrilldown(string $type, int $limit = 50, int $offset = 0): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($type === 'pending') {
            $whereClause .= " AND status IN ('draft', 'in_progress')";
        } elseif ($type === 'high_rejection') {
            $whereClause .= " AND planned_qty > 0 AND (rejected_qty / planned_qty) >= 0.10";
        } elseif ($type === 'draft') {
            $whereClause .= " AND status = 'draft'";
        } elseif ($type === 'in_progress') {
            $whereClause .= " AND status = 'in_progress'";
        } elseif ($type === 'completed') {
            $whereClause .= " AND status = 'completed'";
        } elseif ($type === 'approved') {
            $whereClause .= " AND status = 'approved'";
        } elseif ($type === 'blocked') {
            $whereClause .= " AND status = 'blocked'";
        }

        $query = "SELECT 
            id, assembly_date, planned_qty, completed_qty, rejected_qty, status, 
            completed_by, approved_by, approved_at, created_at, updated_at
        FROM mfg_assembly_entries 
        $whereClause
        ORDER BY assembly_date DESC, created_at DESC
        LIMIT ? OFFSET ?";

        $rows = DB::fetchAll($query, array_merge($params, [$limit, $offset]));
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = [
                'id' => (int)($row['id'] ?? 0),
                'assembly_date' => (string)($row['assembly_date'] ?? ''),
                'planned_qty' => (float)($row['planned_qty'] ?? 0),
                'completed_qty' => (float)($row['completed_qty'] ?? 0),
                'rejected_qty' => (float)($row['rejected_qty'] ?? 0),
                'status' => strtolower((string)($row['status'] ?? '')),
                'completed_by' => (string)($row['completed_by'] ?? ''),
                'approved_by' => (string)($row['approved_by'] ?? ''),
                'approved_at' => (string)($row['approved_at'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Get Assembly drilldown total count
     */
    public function getAssemblyDrilldownCount(string $type): int
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if ($type === 'pending') {
            $whereClause .= " AND status IN ('draft', 'in_progress')";
        } elseif ($type === 'high_rejection') {
            $whereClause .= " AND planned_qty > 0 AND (rejected_qty / planned_qty) >= 0.10";
        } elseif ($type === 'draft') {
            $whereClause .= " AND status = 'draft'";
        } elseif ($type === 'in_progress') {
            $whereClause .= " AND status = 'in_progress'";
        } elseif ($type === 'completed') {
            $whereClause .= " AND status = 'completed'";
        } elseif ($type === 'approved') {
            $whereClause .= " AND status = 'approved'";
        } elseif ($type === 'blocked') {
            $whereClause .= " AND status = 'blocked'";
        }

        $result = DB::fetchOne("SELECT COUNT(*) as total FROM mfg_assembly_entries $whereClause", $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Get Production drilldown entries with optional type filtering
     * @param string $type Filter: 'day', 'night', 'morning', 'low_utilization', 'high_rejection', or 'all'
     */
    public function getProductionDrilldown(string $type, int $limit = 50, int $offset = 0): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (in_array($type, ['day', 'night', 'morning'], true)) {
            $whereClause .= ' AND LOWER(p.shift) = ?';
            $params[] = strtolower($type);
        } elseif ($type === 'low_utilization') {
            $whereClause .= ' AND p.produced_qty < (COALESCE((SELECT AVG(p2.produced_qty) FROM production_entries p2 WHERE p2.machine_id = p.machine_id AND p2.production_date >= DATE_SUB(UTC_DATE(), INTERVAL 14 DAY)), 0) * 0.60)';
        } elseif ($type === 'high_rejection') {
            $whereClause .= ' AND p.produced_qty > 0 AND (p.rejected_qty / p.produced_qty) >= 0.10';
        }

        $query = 'SELECT 
            p.id, p.production_date, p.shift, p.machine_id, p.produced_qty, p.good_qty, p.rejected_qty, p.created_at
        FROM production_entries p
        $whereClause
        ORDER BY p.production_date DESC, p.shift DESC, p.created_at DESC
        LIMIT ? OFFSET ?';

        $rows = DB::fetchAll($query, array_merge($params, [$limit, $offset]));
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = [
                'id' => (int)($row['id'] ?? 0),
                'production_date' => (string)($row['production_date'] ?? ''),
                'shift' => strtolower((string)($row['shift'] ?? '')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'produced_qty' => (float)($row['produced_qty'] ?? 0),
                'good_qty' => (float)($row['good_qty'] ?? 0),
                'rejected_qty' => (float)($row['rejected_qty'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Get Production drilldown total count
     */
    public function getProductionDrilldownCount(string $type): int
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (in_array($type, ['day', 'night', 'morning'], true)) {
            $whereClause .= ' AND LOWER(p.shift) = ?';
            $params[] = strtolower($type);
        } elseif ($type === 'low_utilization') {
            $whereClause .= ' AND p.produced_qty < (COALESCE((SELECT AVG(p2.produced_qty) FROM production_entries p2 WHERE p2.machine_id = p.machine_id AND p2.production_date >= DATE_SUB(UTC_DATE(), INTERVAL 14 DAY)), 0) * 0.60)';
        } elseif ($type === 'high_rejection') {
            $whereClause .= ' AND p.produced_qty > 0 AND (p.rejected_qty / p.produced_qty) >= 0.10';
        }

        $result = DB::fetchOne("SELECT COUNT(*) as total FROM production_entries p $whereClause", $params);
        return (int)($result['total'] ?? 0);
    }
}
