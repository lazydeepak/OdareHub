<?php
declare(strict_types=1);

namespace Apps\Studio\Analytics;

require_once __DIR__ . '/AnalyticsRepository.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';

final class AnalyticsProvider
{
    /**
     * Fetch and decorate analytics data
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function fetch(array $context): array
    {
        $appKey = (string)($context['app_key'] ?? 'studio_sales');
        $days = (int)($context['days'] ?? 7);

        try {
            $repo = new AnalyticsRepository($appKey);

            $kpi = $repo->getKpiMetrics();
            $performance = $repo->getPerformanceMetrics();
            $workload = $repo->getUserWorkload();
            $unassignedCount = $repo->getUnassignedTaskCount();
            $priorityDist = $repo->getPriorityDistribution();
            $stateDist = $repo->getStateDistribution();
            $overdueOrders = $repo->getOverdueOrders();
            $dailyTrends = $repo->getDailyTrends($days);
            $completionTrend = $repo->getCompletionTrend($days);
            $overdueTrend = $repo->getOverdueTrend($days);
            $qcPassRateTrend = $repo->getQcPassRatePerDay($days);
            $productionOutputTrend = $repo->getProductionOutputPerDay($days);
            $dispatchDelayTrend = $repo->getDispatchDelayPerDay($days);

            return [
                'kpi' => $this->decorateKpi($kpi),
                'performance' => $this->decoratePerformance($performance),
                'workload' => $this->decorateWorkload($workload),
                'unassigned_count' => $unassignedCount,
                'priority_distribution' => $priorityDist,
                'state_distribution' => $stateDist,
                'overdue_orders' => $overdueOrders,
                'trend_orders' => $this->decorateTrend($dailyTrends),
                'trend_completed' => $this->decorateTrend($completionTrend),
                'trend_overdue' => $this->decorateTrend($overdueTrend),
                'trend_qc_pass_rate' => $this->decorateValueTrend($qcPassRateTrend, 1, '%', true),
                'trend_production_output' => $this->decorateValueTrend($productionOutputTrend, 0, ''),
                'trend_dispatch_delay' => $this->decorateValueTrend($dispatchDelayTrend, 0, ''),
            ];
        } catch (\Throwable) {
            return [
                'kpi' => [
                    'total_orders' => 0,
                    'completed_today' => 0,
                    'overdue_count' => 0,
                    'in_progress' => 0,
                    'pending_approval' => 0,
                    'avg_processing_hours' => 0,
                    'avg_turnaround_days' => 0,
                ],
                'performance' => [
                    'completion_rate' => 0,
                    'completed_total' => 0,
                    'total_orders' => 0,
                    'avg_approval_hours' => 0,
                    'state_transitions' => [],
                ],
                'workload' => [],
                'unassigned_count' => 0,
                'priority_distribution' => ['high' => 0, 'medium' => 0, 'low' => 0],
                'state_distribution' => [],
                'overdue_orders' => [],
                'trend_orders' => [],
                'trend_completed' => [],
                'trend_overdue' => [],
                'trend_qc_pass_rate' => [],
                'trend_production_output' => [],
                'trend_dispatch_delay' => [],
            ];
        }
    }

    /**
     * Decorate KPI metrics with formatting
     * @param array<string,mixed> $kpi
     * @return array<string,mixed>
     */
    private function decorateKpi(array $kpi): array
    {
        return [
            'total_orders' => (int)($kpi['total_orders'] ?? 0),
            'completed_today' => (int)($kpi['completed_today'] ?? 0),
            'overdue_count' => (int)($kpi['overdue_count'] ?? 0),
            'in_progress' => (int)($kpi['in_progress'] ?? 0),
            'pending_approval' => (int)($kpi['pending_approval'] ?? 0),
            'avg_processing_hours' => (float)($kpi['avg_processing_hours'] ?? 0),
            'avg_turnaround_days' => (float)($kpi['avg_turnaround_days'] ?? 0),
            'avg_processing_hours_text' => round((float)($kpi['avg_processing_hours'] ?? 0), 1) . ' hrs',
            'avg_turnaround_days_text' => round((float)($kpi['avg_turnaround_days'] ?? 0), 1) . ' days',
        ];
    }

    /**
     * Decorate performance metrics with formatting
     * @param array<string,mixed> $performance
     * @return array<string,mixed>
     */
    private function decoratePerformance(array $performance): array
    {
        $completionRate = (float)($performance['completion_rate'] ?? 0);
        $completionClass = $completionRate >= 85 ? 'status-good' : ($completionRate >= 70 ? 'status-warn' : 'status-danger');

        return [
            'completion_rate' => round($completionRate, 1),
            'completion_rate_text' => round($completionRate, 1) . '%',
            'completion_rate_class' => $completionClass,
            'completed_total' => (int)($performance['completed_total'] ?? 0),
            'total_orders' => (int)($performance['total_orders'] ?? 0),
            'avg_approval_hours' => (float)($performance['avg_approval_hours'] ?? 0),
            'avg_approval_hours_text' => round((float)($performance['avg_approval_hours'] ?? 0), 1) . ' hrs',
            'state_transitions' => is_array($performance['state_transitions'] ?? null) ? $performance['state_transitions'] : [],
        ];
    }

    /**
     * Decorate workload data with user names and status colors
     * @param array<int,array<string,mixed>> $workload
     * @return array<int,array<string,mixed>>
     */
    private function decorateWorkload(array $workload): array
    {
        $decorated = [];
        foreach ($workload as $userId => $data) {
            if (!is_array($data)) {
                continue;
            }

            $totalTasks = (int)($data['total_tasks'] ?? 0);
            $activeTasks = (int)($data['active_tasks'] ?? 0);
            $overdueTasks = (int)($data['overdue_tasks'] ?? 0);

            $workloadPercent = $totalTasks > 0 ? round(($activeTasks / $totalTasks) * 100, 1) : 0;
            $workloadClass = $workloadPercent >= 75 ? 'workload-high' : ($workloadPercent >= 50 ? 'workload-medium' : 'workload-low');

            $overdueClass = $overdueTasks > 0 ? 'overdue-alert' : 'overdue-ok';

            $decorated[$userId] = [
                'user_id' => (int)$userId,
                'total_tasks' => $totalTasks,
                'active_tasks' => $activeTasks,
                'overdue_tasks' => $overdueTasks,
                'completed_today' => (int)($data['completed_today'] ?? 0),
                'last_activity' => (string)($data['last_activity'] ?? ''),
                'workload_percent' => $workloadPercent,
                'workload_class' => $workloadClass,
                'overdue_class' => $overdueClass,
            ];
        }

        return $decorated;
    }

    /**
     * Decorate trend data with bar heights and formatting
     * @param array<int,array<string,mixed>> $trend
     * @return array<int,array<string,mixed>>
     */
    private function decorateTrend(array $trend): array
    {
        if (empty($trend)) {
            return [];
        }

        // Find max count for bar height calculation
        $maxCount = 0;
        foreach ($trend as $item) {
            if (is_array($item)) {
                $count = (int)($item['count'] ?? 0);
                if ($count > $maxCount) {
                    $maxCount = $count;
                }
            }
        }

        $decorated = [];
        foreach ($trend as $item) {
            if (!is_array($item)) {
                continue;
            }

            $count = (int)($item['count'] ?? 0);
            $dayStr = (string)($item['day'] ?? '');
            $barHeight = $maxCount > 0 ? round(($count / $maxCount) * 100, 1) : 0;

            $decorated[] = [
                'day' => $dayStr,
                'count' => $count,
                'bar_height' => $barHeight,
                'day_short' => $this->formatDayShort($dayStr),
            ];
        }

        return $decorated;
    }

    /**
     * Decorate value-based trend data
     * @param array<int,array<string,mixed>> $trend
     * @return array<int,array<string,mixed>>
     */
    private function decorateValueTrend(array $trend, int $precision = 1, string $suffix = '', bool $fixedScale100 = false): array
    {
        if (empty($trend)) {
            return [];
        }

        $maxValue = 0.0;
        foreach ($trend as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = (float)($item['value'] ?? 0);
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        $decorated = [];
        foreach ($trend as $item) {
            if (!is_array($item)) {
                continue;
            }

            $value = (float)($item['value'] ?? 0);
            if ($fixedScale100) {
                $barHeight = max(0.0, min(100.0, $value));
            } else {
                $barHeight = $maxValue > 0 ? round(($value / $maxValue) * 100, 1) : 0;
            }

            $rounded = round($value, $precision);
            $decorated[] = [
                'day' => (string)($item['day'] ?? ''),
                'value' => $rounded,
                'value_text' => (string)$rounded . $suffix,
                'bar_height' => $barHeight,
                'day_short' => $this->formatDayShort((string)($item['day'] ?? '')),
            ];
        }

        return $decorated;
    }

    /**
     * Format day as short format (Mon, Tue, etc.)
     * @param string $dayStr (Y-m-d format)
     * @return string
     */
    private function formatDayShort(string $dayStr): string
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        try {
            $ts = strtotime($dayStr);
            if ($ts === false) {
                return substr($dayStr, 5); // Return m-d
            }
            return $days[(int)gmdate('w', $ts)] . ' ' . gmdate('m-d', $ts);
        } catch (\Throwable) {
            return substr($dayStr, 5);
        }
    }

    /**
     * Fetch and decorate drill-down orders
     * @param string $type
     * @param int $limit
     * @param int $offset
     * @param string $appKey
     * @return array<string,mixed>
     */
    public function fetchDrilldown(string $type, int $limit = 50, int $offset = 0, string $appKey = 'studio_sales'): array
    {
        try {
            $repo = new AnalyticsRepository($appKey);
            $orders = $repo->getDrilldownOrders($type, $limit, $offset);
            $totalCount = $repo->getDrilldownOrderCount($type);

            return [
                'type' => $type,
                'orders' => $this->decorateDrilldownOrders($orders),
                'total_count' => $totalCount,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalCount,
            ];
        } catch (\Throwable) {
            return [
                'type' => $type,
                'orders' => [],
                'total_count' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => false,
            ];
        }
    }

    /**
     * Decorate drill-down orders with formatting and colors
     * @param array<int,array<string,mixed>> $orders
     * @return array<int,array<string,mixed>>
     */
    private function decorateDrilldownOrders(array $orders): array
    {
        $decorated = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $state = strtolower((string)($order['state'] ?? 'draft'));
            $priority = strtolower((string)($order['priority'] ?? 'medium'));

            $stateClass = match ($state) {
                'completed' => 'state-completed',
                'processing' => 'state-processing',
                'approved' => 'state-approved',
                'draft' => 'state-draft',
                default => 'state-unknown',
            };

            $priorityClass = match ($priority) {
                'high' => 'priority-high',
                'medium' => 'priority-medium',
                'low' => 'priority-low',
                default => 'priority-medium',
            };

            $decorated[] = [
                'id' => (int)($order['id'] ?? 0),
                'order_no' => (string)($order['order_no'] ?? ''),
                'customer' => (string)($order['customer'] ?? ''),
                'qty' => (float)($order['qty'] ?? 0),
                'priority' => $priority,
                'priority_class' => $priorityClass,
                'state' => $state,
                'state_class' => $stateClass,
                'assigned_to' => (int)($order['assigned_to'] ?? 0),
                'due_at' => (string)($order['due_at'] ?? ''),
                'created_at' => (string)($order['created_at'] ?? ''),
                'completed_at' => (string)($order['completed_at'] ?? ''),
            ];
        }

        return $decorated;
    }

    // ===== MANUFACTURING DECORATORS =====

    /**
     * Fetch and decorate QC metrics
     * @param AnalyticsRepository $repo
     * @return array<string,mixed>
     */
    public function fetchQcMetrics(AnalyticsRepository $repo): array
    {
        try {
            $qc = $repo->getQcMetrics();
            return $this->decorateQc($qc);
        } catch (\Throwable) {
            return [
                'total_checked' => 0,
                'total_pass' => 0,
                'total_fail' => 0,
                'pass_rate' => 0,
                'fail_rate' => 0,
                'rework_count' => 0,
                'pass_rate_text' => '0%',
                'fail_rate_text' => '0%',
                'pass_rate_class' => 'status-danger',
                'fail_rate_class' => 'status-good',
            ];
        }
    }

    /**
     * Decorate QC metrics with formatting and colors
     * @param array<string,mixed> $qc
     * @return array<string,mixed>
     */
    private function decorateQc(array $qc): array
    {
        $passRate = (float)($qc['pass_rate'] ?? 0);
        $failRate = (float)($qc['fail_rate'] ?? 0);

        // Pass rate: green >= 95, yellow >= 85, red < 85
        $passRateClass = $passRate >= 95 ? 'status-good' : ($passRate >= 85 ? 'status-warn' : 'status-danger');

        // Fail rate: green < 5, yellow < 15, red >= 15
        $failRateClass = $failRate < 5 ? 'status-good' : ($failRate < 15 ? 'status-warn' : 'status-danger');

        return [
            'total_checked' => (float)($qc['total_checked'] ?? 0),
            'total_pass' => (float)($qc['total_pass'] ?? 0),
            'total_fail' => (float)($qc['total_fail'] ?? 0),
            'pass_rate' => round($passRate, 1),
            'pass_rate_text' => round($passRate, 1) . '%',
            'pass_rate_class' => $passRateClass,
            'fail_rate' => round($failRate, 1),
            'fail_rate_text' => round($failRate, 1) . '%',
            'fail_rate_class' => $failRateClass,
            'rework_count' => (int)($qc['rework_count'] ?? 0),
        ];
    }

    /**
     * Fetch and decorate dispatch metrics
     * @param AnalyticsRepository $repo
     * @return array<string,mixed>
     */
    public function fetchDispatchMetrics(AnalyticsRepository $repo): array
    {
        try {
            $dispatch = $repo->getDispatchMetrics();
            return $this->decorateDispatch($dispatch);
        } catch (\Throwable) {
            return [
                'dispatched_today' => 0,
                'pending_dispatch' => 0,
                'delivery_delay' => 0,
                'pending_quantity' => 0,
                'pending_dispatch_class' => 'status-warn',
                'delivery_delay_class' => 'status-good',
            ];
        }
    }

    /**
     * Decorate dispatch metrics with formatting and colors
     * @param array<string,mixed> $dispatch
     * @return array<string,mixed>
     */
    private function decorateDispatch(array $dispatch): array
    {
        $pendingDispatch = (int)($dispatch['pending_dispatch'] ?? 0);
        $deliveryDelay = (int)($dispatch['delivery_delay'] ?? 0);

        // Pending dispatch: green < 10, yellow < 25, red >= 25
        $pendingClass = $pendingDispatch < 10 ? 'status-good' : ($pendingDispatch < 25 ? 'status-warn' : 'status-danger');

        // Delivery delay: green = 0, yellow 1-5, red > 5
        $delayClass = $deliveryDelay === 0 ? 'status-good' : ($deliveryDelay <= 5 ? 'status-warn' : 'status-danger');

        return [
            'dispatched_today' => (int)($dispatch['dispatched_today'] ?? 0),
            'pending_dispatch' => $pendingDispatch,
            'pending_dispatch_class' => $pendingClass,
            'delivery_delay' => $deliveryDelay,
            'delivery_delay_class' => $delayClass,
            'pending_quantity' => (float)($dispatch['pending_quantity'] ?? 0),
        ];
    }

    /**
     * Fetch and decorate assembly metrics
     * @param AnalyticsRepository $repo
     * @return array<string,mixed>
     */
    public function fetchAssemblyMetrics(AnalyticsRepository $repo): array
    {
        try {
            $assembly = $repo->getAssemblyMetrics();
            return $this->decorateAssembly($assembly);
        } catch (\Throwable) {
            return [
                'completed_today' => 0,
                'pending_assembly' => 0,
                'avg_assembly_hours' => 0,
                'total_completed_qty' => 0,
                'rejection_rate' => 0,
                'avg_assembly_hours_text' => '0 hrs',
                'rejection_rate_text' => '0%',
                'pending_assembly_class' => 'status-warn',
                'rejection_rate_class' => 'status-good',
            ];
        }
    }

    /**
     * Decorate assembly metrics with formatting and colors
     * @param array<string,mixed> $assembly
     * @return array<string,mixed>
     */
    private function decorateAssembly(array $assembly): array
    {
        $rejectionRate = (float)($assembly['rejection_rate'] ?? 0);
        $pendingAssembly = (int)($assembly['pending_assembly'] ?? 0);

        // Rejection rate: green < 5, yellow < 10, red >= 10
        $rejectionClass = $rejectionRate < 5 ? 'status-good' : ($rejectionRate < 10 ? 'status-warn' : 'status-danger');

        // Pending assembly: green < 10, yellow < 25, red >= 25
        $pendingClass = $pendingAssembly < 10 ? 'status-good' : ($pendingAssembly < 25 ? 'status-warn' : 'status-danger');

        return [
            'completed_today' => (int)($assembly['completed_today'] ?? 0),
            'pending_assembly' => $pendingAssembly,
            'pending_assembly_class' => $pendingClass,
            'avg_assembly_hours' => round((float)($assembly['avg_assembly_hours'] ?? 0), 1),
            'avg_assembly_hours_text' => round((float)($assembly['avg_assembly_hours'] ?? 0), 1) . ' hrs',
            'total_completed_qty' => (float)($assembly['total_completed_qty'] ?? 0),
            'rejection_rate' => round($rejectionRate, 1),
            'rejection_rate_text' => round($rejectionRate, 1) . '%',
            'rejection_rate_class' => $rejectionClass,
        ];
    }

    /**
     * Fetch and decorate production metrics
     * @param AnalyticsRepository $repo
     * @return array<string,mixed>
     */
    public function fetchProductionMetrics(AnalyticsRepository $repo): array
    {
        try {
            $production = $repo->getProductionMetrics();
            return $this->decorateProduction($production);
        } catch (\Throwable) {
            return [
                'production_today' => 0,
                'total_machines' => 0,
                'active_machines' => 0,
                'machine_utilization' => 0,
                'good_qty' => 0,
                'rejected_qty' => 0,
                'overall_equipment_effectiveness' => 0,
                'machine_utilization_text' => '0%',
                'oee_text' => '0%',
                'utilization_class' => 'status-warn',
                'oee_class' => 'status-warn',
            ];
        }
    }

    /**
     * Decorate production metrics with formatting and colors
     * @param array<string,mixed> $production
     * @return array<string,mixed>
     */
    private function decorateProduction(array $production): array
    {
        $utilization = (float)($production['machine_utilization'] ?? 0);
        $oee = (float)($production['overall_equipment_effectiveness'] ?? 0);

        // Utilization: green >= 80, yellow >= 60, red < 60
        $utilizationClass = $utilization >= 80 ? 'status-good' : ($utilization >= 60 ? 'status-warn' : 'status-danger');

        // OEE: green >= 85, yellow >= 70, red < 70
        $oeeClass = $oee >= 85 ? 'status-good' : ($oee >= 70 ? 'status-warn' : 'status-danger');

        return [
            'production_today' => (float)($production['production_today'] ?? 0),
            'total_machines' => (int)($production['total_machines'] ?? 0),
            'active_machines' => (int)($production['active_machines'] ?? 0),
            'machine_utilization' => round($utilization, 1),
            'machine_utilization_text' => round($utilization, 1) . '%',
            'utilization_class' => $utilizationClass,
            'good_qty' => (float)($production['good_qty'] ?? 0),
            'rejected_qty' => (float)($production['rejected_qty'] ?? 0),
            'overall_equipment_effectiveness' => round($oee, 1),
            'oee_text' => round($oee, 1) . '%',
            'oee_class' => $oeeClass,
        ];
    }

    /**
     * Fetch and decorate QC drilldown data
     */
    public function fetchQcDrilldown(
        string $type,
        int $limit = 50,
        int $offset = 0,
        string $appKey = 'studio_sales'
    ): array {
        try {
            $repo = new AnalyticsRepository($appKey);
            $entries = $repo->getQcDrilldown($type, $limit, $offset);
            $total = $repo->getQcDrilldownCount($type);

            return [
                'type' => $type,
                'entries' => $this->decorateQcDrilldown($entries),
                'total_count' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ];
        } catch (\Throwable) {
            return [
                'type' => $type,
                'entries' => [],
                'total_count' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => false,
            ];
        }
    }

    /**
     * Decorate QC drilldown entries with formatting
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function decorateQcDrilldown(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $passQty = (float)($entry['pass_qty'] ?? 0);
            $failQty = (float)($entry['fail_qty'] ?? 0);
            $checkedQty = (float)($entry['checked_qty'] ?? 0);
            $passRate = $checkedQty > 0 ? round(($passQty / $checkedQty) * 100, 1) : 0;

            // Status color: open=warning, closed=success.
            $statusClass = $entry['status'] === 'closed' ? 'status-good' : 'status-warn';

            // Approval color: draft/submitted=warning, approved=success, rejected=danger.
            $approvalClass = match ($entry['approval_status']) {
                'approved' => 'status-good',
                'rejected' => 'status-danger',
                default => 'status-warn',
            };

            $result[] = [
                'id' => (int)($entry['id'] ?? 0),
                'qc_type' => (string)($entry['qc_type'] ?? ''),
                'checked_qty' => round($checkedQty, 2),
                'pass_qty' => round($passQty, 2),
                'fail_qty' => round($failQty, 2),
                'pass_rate' => $passRate,
                'pass_rate_text' => $passRate . '%',
                'status' => (string)($entry['status'] ?? ''),
                'status_class' => $statusClass,
                'approval_status' => (string)($entry['approval_status'] ?? ''),
                'approval_class' => $approvalClass,
                'created_at' => (string)($entry['created_at'] ?? ''),
                'updated_at' => (string)($entry['updated_at'] ?? ''),
            ];
        }
        return $result;
    }

    /**
     * Fetch and decorate Dispatch drilldown data
     */
    public function fetchDispatchDrilldown(
        string $type,
        int $limit = 50,
        int $offset = 0,
        string $appKey = 'studio_sales'
    ): array {
        try {
            $repo = new AnalyticsRepository($appKey);
            $entries = $repo->getDispatchDrilldown($type, $limit, $offset);
            $total = $repo->getDispatchDrilldownCount($type);

            return [
                'type' => $type,
                'entries' => $this->decorateDispatchDrilldown($entries),
                'total_count' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ];
        } catch (\Throwable) {
            return [
                'type' => $type,
                'entries' => [],
                'total_count' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => false,
            ];
        }
    }

    /**
     * Decorate Dispatch drilldown entries
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function decorateDispatchDrilldown(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            // Status color: Ready/Prepared=yellow, Dispatched=good, Blocked=danger
            $statusClass = match ($entry['dispatch_status']) {
                'dispatched' => 'status-good',
                'blocked' => 'status-danger',
                default => 'status-warn',
            };

            $result[] = [
                'id' => (int)($entry['id'] ?? 0),
                'dispatch_date' => (string)($entry['dispatch_date'] ?? ''),
                'dispatchable_qty' => round((float)($entry['dispatchable_qty'] ?? 0), 2),
                'destination' => (string)($entry['destination'] ?? ''),
                'dispatch_status' => (string)($entry['dispatch_status'] ?? ''),
                'status_class' => $statusClass,
                'dispatch_type' => (string)($entry['dispatch_type'] ?? ''),
                'cases_count' => (int)($entry['cases_count'] ?? 0),
                'pallets_count' => (int)($entry['pallets_count'] ?? 0),
                'dispatched_at' => (string)($entry['dispatched_at'] ?? ''),
                'created_at' => (string)($entry['created_at'] ?? ''),
            ];
        }
        return $result;
    }

    /**
     * Fetch and decorate Assembly drilldown data
     */
    public function fetchAssemblyDrilldown(
        string $type,
        int $limit = 50,
        int $offset = 0,
        string $appKey = 'studio_sales'
    ): array {
        try {
            $repo = new AnalyticsRepository($appKey);
            $entries = $repo->getAssemblyDrilldown($type, $limit, $offset);
            $total = $repo->getAssemblyDrilldownCount($type);

            return [
                'type' => $type,
                'entries' => $this->decorateAssemblyDrilldown($entries),
                'total_count' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ];
        } catch (\Throwable) {
            return [
                'type' => $type,
                'entries' => [],
                'total_count' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => false,
            ];
        }
    }

    /**
     * Decorate Assembly drilldown entries
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function decorateAssemblyDrilldown(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $plannedQty = (float)($entry['planned_qty'] ?? 0);
            $completedQty = (float)($entry['completed_qty'] ?? 0);
            $rejectedQty = (float)($entry['rejected_qty'] ?? 0);
            $rejectionRate = $plannedQty > 0 ? round(($rejectedQty / $plannedQty) * 100, 1) : 0;

            // Status color
            $statusClass = match ($entry['status']) {
                'completed' => 'status-good',
                'approved' => 'status-good',
                'blocked' => 'status-danger',
                'cancelled' => 'status-danger',
                'in_progress' => 'status-warn',
                default => 'status-muted',
            };

            $result[] = [
                'id' => (int)($entry['id'] ?? 0),
                'assembly_date' => (string)($entry['assembly_date'] ?? ''),
                'planned_qty' => round($plannedQty, 2),
                'completed_qty' => round($completedQty, 2),
                'rejected_qty' => round($rejectedQty, 2),
                'rejection_rate' => $rejectionRate,
                'rejection_rate_text' => $rejectionRate . '%',
                'status' => (string)($entry['status'] ?? ''),
                'status_class' => $statusClass,
                'completed_by' => (string)($entry['completed_by'] ?? ''),
                'approved_by' => (string)($entry['approved_by'] ?? ''),
                'created_at' => (string)($entry['created_at'] ?? ''),
                'updated_at' => (string)($entry['updated_at'] ?? ''),
            ];
        }
        return $result;
    }

    /**
     * Fetch and decorate Production drilldown data
     */
    public function fetchProductionDrilldown(
        string $type,
        int $limit = 50,
        int $offset = 0,
        string $appKey = 'studio_sales'
    ): array {
        try {
            $repo = new AnalyticsRepository($appKey);
            $entries = $repo->getProductionDrilldown($type, $limit, $offset);
            $total = $repo->getProductionDrilldownCount($type);

            return [
                'type' => $type,
                'entries' => $this->decorateProductionDrilldown($entries),
                'total_count' => $total,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total,
            ];
        } catch (\Throwable) {
            return [
                'type' => $type,
                'entries' => [],
                'total_count' => 0,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => false,
            ];
        }
    }

    /**
     * Decorate Production drilldown entries
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function decorateProductionDrilldown(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $producedQty = (float)($entry['produced_qty'] ?? 0);
            $goodQty = (float)($entry['good_qty'] ?? 0);
            $rejectedQty = (float)($entry['rejected_qty'] ?? 0);
            $qualityRate = $producedQty > 0 ? round(($goodQty / $producedQty) * 100, 1) : 0;

            // Quality color: var(--color-success-text)            $qualityClass = $qualityRate >= 95 ? 'status-good' : ($qualityRate >= 85 ? 'status-warn' : 'status-danger');

            $result[] = [
                'id' => (int)($entry['id'] ?? 0),
                'production_date' => (string)($entry['production_date'] ?? ''),
                'shift' => (string)($entry['shift'] ?? ''),
                'machine_id' => (int)($entry['machine_id'] ?? 0),
                'produced_qty' => round($producedQty, 2),
                'good_qty' => round($goodQty, 2),
                'rejected_qty' => round($rejectedQty, 2),
                'quality_rate' => $qualityRate,
                'quality_rate_text' => $qualityRate . '%',
                'quality_class' => $qualityClass,
                'created_at' => (string)($entry['created_at'] ?? ''),
            ];
        }
        return $result;
    }
}
