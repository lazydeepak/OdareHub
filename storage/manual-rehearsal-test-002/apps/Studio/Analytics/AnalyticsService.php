<?php
declare(strict_types=1);

namespace Apps\Studio\Analytics;

require_once __DIR__ . '/AnalyticsRepository.php';

final class AnalyticsService
{
    private AnalyticsRepository $repo;

    public function __construct(string $appKey)
    {
        $this->repo = new AnalyticsRepository($appKey);
    }

    /**
     * Calculate efficiency score (0-100)
     * Based on: completion rate, overdue %, avg processing time
     * @return int
     */
    public function getEfficiencyScore(): int
    {
        $perf = $this->repo->getPerformanceMetrics();
        $kpi = $this->repo->getKpiMetrics();

        $completionRate = (float)($perf['completion_rate'] ?? 0);
        $totalOrders = (int)($perf['total_orders'] ?? 1);
        $overdueCount = (int)($kpi['overdue_count'] ?? 0);
        $overduePercent = $totalOrders > 0 ? ($overdueCount / $totalOrders) * 100 : 0;

        // Weighted score:
        // - 40% completion rate (higher is better)
        // - 40% inverse overdue % (lower overdue is better)
        // - 20% processing time efficiency (faster is better)
        $avgHours = (float)($kpi['avg_processing_hours'] ?? 0);
        $timeScore = $avgHours > 0 ? min(100, max(0, 100 - ($avgHours / 24) * 10)) : 100;

        $score = (int)(
            ($completionRate * 0.4) +
            ((100 - min(100, $overduePercent)) * 0.4) +
            ($timeScore * 0.2)
        );

        return max(0, min(100, $score));
    }

    /**
     * Get top performing users by completion
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getTopPerformers(int $limit = 5): array
    {
        $workload = $this->repo->getUserWorkload();
        $sorted = [];

        foreach ($workload as $userId => $data) {
            $totalTasks = (int)($data['total_tasks'] ?? 0);
            $completedToday = (int)($data['completed_today'] ?? 0);
            $completionRate = $totalTasks > 0 ? ($completedToday / $totalTasks) * 100 : 0;

            $sorted[$userId] = [
                'user_id' => $userId,
                'completion_rate' => $completionRate,
                'completed_today' => $completedToday,
                'total_tasks' => $totalTasks,
            ];
        }

        // Sort by completion rate
        usort($sorted, function ($a, $b) {
            $rateA = $a['completion_rate'] ?? 0;
            $rateB = $b['completion_rate'] ?? 0;
            return $rateB <=> $rateA;
        });

        return array_slice($sorted, 0, $limit);
    }

    /**
     * Get alerts: high workload users, stale tasks, critical issues
     * @return array<int,array<string,mixed>>
     */
    public function getAlerts(): array
    {
        $alerts = [];
        $workload = $this->repo->getUserWorkload();
        $kpi = $this->repo->getKpiMetrics();

        // Alert 1: High workload (>10 active tasks)
        foreach ($workload as $userId => $data) {
            $activeTasks = (int)($data['active_tasks'] ?? 0);
            if ($activeTasks > 10) {
                $alerts[] = [
                    'type' => 'high_workload',
                    'user_id' => $userId,
                    'severity' => $activeTasks > 20 ? 'critical' : 'warning',
                    'message' => 'High workload: ' . $activeTasks . ' active tasks',
                    'data' => $data,
                ];
            }
        }

        // Alert 2: High overdue rate
        $totalOrders = (int)($kpi['total_orders'] ?? 1);
        $overdueCount = (int)($kpi['overdue_count'] ?? 0);
        $overduePercent = ($overdueCount / $totalOrders) * 100;
        if ($overduePercent > 15) {
            $alerts[] = [
                'type' => 'high_overdue_rate',
                'severity' => $overduePercent > 30 ? 'critical' : 'warning',
                'message' => 'High overdue rate: ' . round($overduePercent, 1) . '% of tasks overdue',
                'data' => ['overdue_count' => $overdueCount, 'total_orders' => $totalOrders],
            ];
        }

        // Alert 3: Many unassigned tasks
        $unassignedCount = $this->repo->getUnassignedTaskCount();
        if ($unassignedCount > 5) {
            $alerts[] = [
                'type' => 'unassigned_tasks',
                'severity' => $unassignedCount > 15 ? 'critical' : 'warning',
                'message' => $unassignedCount . ' tasks awaiting assignment',
                'data' => ['unassigned_count' => $unassignedCount],
            ];
        }

        // Alert 4: Low completion rate
        $perf = $this->repo->getPerformanceMetrics();
        $completionRate = (float)($perf['completion_rate'] ?? 0);
        if ($completionRate < 60) {
            $alerts[] = [
                'type' => 'low_completion_rate',
                'severity' => $completionRate < 40 ? 'critical' : 'warning',
                'message' => 'Low completion rate: ' . round($completionRate, 1) . '%',
                'data' => $perf,
            ];
        }

        return $alerts;
    }

    /**
     * Get trend analysis: compare current period with previous
     * @param int $daysPeriod
     * @return array<string,mixed>
     */
    public function getTrendAnalysis(int $daysPeriod = 7): array
    {
        // Current period completions (last 7 days)
        $currentCompleted = $this->repo->getKpiMetrics()['completed_today'] ?? 0;

        // This is a simplified calculation; in production you'd compare actual periods
        $kpi = $this->repo->getKpiMetrics();
        $perf = $this->repo->getPerformanceMetrics();

        $trend = [
            'period_days' => $daysPeriod,
            'current_completion_rate' => (float)($perf['completion_rate'] ?? 0),
            'current_pending' => (int)($kpi['pending_approval'] ?? 0),
            'current_processing' => (int)($kpi['in_progress'] ?? 0),
            'current_overdue' => (int)($kpi['overdue_count'] ?? 0),
        ];

        return $trend;
    }

    /**
     * Manufacturing intelligence layer
     * @return array<string,mixed>
     */
    public function getManufacturingIntelligence(int $daysPeriod = 7): array
    {
        $qc = $this->repo->getQcMetrics();
        $dispatch = $this->repo->getDispatchMetrics();
        $assembly = $this->repo->getAssemblyMetrics();
        $production = $this->repo->getProductionMetrics();

        $qcFailRate = (float)($qc['fail_rate'] ?? 0);
        $assemblyPending = (int)round((float)($assembly['pending_assembly'] ?? 0));
        $assemblyRejection = (float)($assembly['rejection_rate'] ?? 0);
        $dispatchDelay = (int)round((float)($dispatch['delivery_delay'] ?? 0));
        $dispatchPending = (int)round((float)($dispatch['pending_dispatch'] ?? 0));
        $machineUtilization = (float)($production['machine_utilization'] ?? 0);

        $bottlenecks = [];

        if ($assemblyPending >= 20) {
            $bottlenecks[] = [
                'type' => 'assembly_pending_high',
                'severity' => $assemblyPending >= 35 ? 'critical' : 'warning',
                'message' => 'Assembly pending backlog is high: ' . $assemblyPending,
                'action_url' => '/ops/analytics/assembly/drilldown?type=pending',
            ];
        }

        if ($dispatchDelay >= 5) {
            $bottlenecks[] = [
                'type' => 'dispatch_delay_high',
                'severity' => $dispatchDelay >= 12 ? 'critical' : 'warning',
                'message' => 'Dispatch delay count is high: ' . $dispatchDelay,
                'action_url' => '/ops/analytics/dispatch/drilldown?type=delayed',
            ];
        }

        if ($machineUtilization < 60) {
            $bottlenecks[] = [
                'type' => 'production_low_utilization',
                'severity' => $machineUtilization < 40 ? 'critical' : 'warning',
                'message' => 'Machine utilization is low: ' . round($machineUtilization, 1) . '%',
                'action_url' => '/ops/analytics/production/drilldown?type=low_utilization',
            ];
        }

        if ($qcFailRate >= 10) {
            $bottlenecks[] = [
                'type' => 'qc_fail_rate_high',
                'severity' => $qcFailRate >= 15 ? 'critical' : 'warning',
                'message' => 'QC fail rate is elevated: ' . round($qcFailRate, 1) . '%',
                'action_url' => '/ops/analytics/qc/drilldown?type=fail',
            ];
        }

        $correlations = [];
        if ($qcFailRate >= 10 && $assemblyRejection >= 8) {
            $correlations[] = [
                'type' => 'qc_to_assembly_rejection',
                'severity' => 'warning',
                'message' => 'QC fail rate rise correlates with assembly rejection rise.',
            ];
        }

        if ($assemblyRejection >= 8 && $dispatchDelay >= 5) {
            $correlations[] = [
                'type' => 'assembly_rejection_to_delay',
                'severity' => 'warning',
                'message' => 'Assembly rejection pressure correlates with dispatch delays.',
            ];
        }

        if ($qcFailRate >= 10 && $assemblyRejection >= 8 && $dispatchDelay >= 5) {
            $correlations[] = [
                'type' => 'quality_rejection_delay_chain',
                'severity' => 'critical',
                'message' => 'Detected chain: QC fail rate up -> assembly rejection up -> dispatch delay up.',
            ];
        }

        $predictiveAlerts = [];

        if ($assemblyPending >= 20 || $dispatchPending >= 25) {
            $predictiveAlerts[] = [
                'type' => 'pending_threshold_breach',
                'severity' => ($assemblyPending >= 35 || $dispatchPending >= 40) ? 'critical' : 'warning',
                'message' => 'Pending workload crossed threshold and may create near-term bottlenecks.',
                'action_url' => $assemblyPending >= $dispatchPending
                    ? '/ops/analytics/assembly/drilldown?type=pending'
                    : '/ops/analytics/dispatch/drilldown?type=pending',
            ];
        }

        $passRateTrend = $this->repo->getQcPassRatePerDay($daysPeriod);
        $passRateTrendSignal = $this->detectRisingRiskFromInverseTrend($passRateTrend, 'value', 3.0);
        if ($passRateTrendSignal) {
            $predictiveAlerts[] = [
                'type' => 'qc_fail_rate_rising',
                'severity' => 'warning',
                'message' => 'QC pass rate is trending downward, indicating rising fail-rate risk.',
                'action_url' => '/ops/analytics/qc/drilldown?type=fail',
            ];
        }

        $dispatchDelayTrend = $this->repo->getDispatchDelayPerDay($daysPeriod);
        $delayTrendSignal = $this->detectIncreasingTrend($dispatchDelayTrend, 'value', 1.0);
        if ($delayTrendSignal) {
            $predictiveAlerts[] = [
                'type' => 'dispatch_delay_increasing',
                'severity' => 'warning',
                'message' => 'Dispatch delays are increasing over recent days.',
                'action_url' => '/ops/analytics/dispatch/drilldown?type=delayed',
            ];
        }

        $health = $this->buildHealthSummary($bottlenecks, $correlations, $predictiveAlerts);

        return [
            'bottlenecks' => $bottlenecks,
            'correlations' => $correlations,
            'predictive_alerts' => $predictiveAlerts,
            'health_summary' => $health,
        ];
    }

    /**
     * Recommendation engine: detect + explain + recommend
     * @param array<string,mixed> $intelligence
     * @return array<int,array<string,mixed>>
     */
    public function getRecommendations(array $intelligence): array
    {
        $recommendations = [];

        $bottlenecks = is_array($intelligence['bottlenecks'] ?? null) ? $intelligence['bottlenecks'] : [];
        $predictiveAlerts = is_array($intelligence['predictive_alerts'] ?? null) ? $intelligence['predictive_alerts'] : [];

        foreach ($bottlenecks as $bottleneck) {
            if (!is_array($bottleneck)) {
                continue;
            }

            $type = (string)($bottleneck['type'] ?? '');
            if ($type === 'production_low_utilization') {
                $recommendations[] = [
                    'type' => 'production_optimization',
                    'priority' => 'high',
                    'audience' => 'manager',
                    'message' => 'Reassign jobs to increase utilization and reduce idle machine windows.',
                    'decision_guidance' => 'Move high-volume jobs to underutilized machines first, then rebalance shifts.',
                    'action_url' => '/ops/analytics/production/drilldown?type=low_utilization',
                ];
            } elseif ($type === 'qc_fail_rate_high') {
                $recommendations[] = [
                    'type' => 'quality_stabilization',
                    'priority' => 'high',
                    'audience' => 'operator',
                    'message' => 'Inspect machine calibration and perform focused root-cause checks on failed lots.',
                    'decision_guidance' => 'Start with top failing machine/shift pair and run a short corrective inspection loop.',
                    'action_url' => '/ops/analytics/qc/drilldown?type=fail',
                ];
            } elseif ($type === 'dispatch_delay_high') {
                $recommendations[] = [
                    'type' => 'dispatch_recovery',
                    'priority' => 'medium',
                    'audience' => 'manager',
                    'message' => 'Prioritize delayed queue and dispatch oldest blocked consignments first.',
                    'decision_guidance' => 'Apply FIFO to delayed shipments and reserve immediate slots for SLA breaches.',
                    'action_url' => '/ops/analytics/dispatch/drilldown?type=delayed',
                ];
            } elseif ($type === 'assembly_pending_high') {
                $recommendations[] = [
                    'type' => 'assembly_capacity_boost',
                    'priority' => 'high',
                    'audience' => 'manager',
                    'message' => 'Add temporary assembly capacity and split backlog into short completion batches.',
                    'decision_guidance' => 'Deploy extra operators on bottleneck stations and release work in controlled waves.',
                    'action_url' => '/ops/analytics/assembly/drilldown?type=pending',
                ];
            }
        }

        foreach ($predictiveAlerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }

            $type = (string)($alert['type'] ?? '');
            if ($type === 'pending_threshold_breach') {
                $recommendations[] = [
                    'type' => 'preemptive_backlog_control',
                    'priority' => 'medium',
                    'audience' => 'manager',
                    'message' => 'Preemptively cap queue growth by throttling new releases into overloaded stages.',
                    'decision_guidance' => 'Hold non-urgent releases until pending volume returns below threshold.',
                    'action_url' => (string)($alert['action_url'] ?? '/ops/analytics'),
                ];
            } elseif ($type === 'qc_fail_rate_rising') {
                $recommendations[] = [
                    'type' => 'preventive_quality_check',
                    'priority' => 'medium',
                    'audience' => 'operator',
                    'message' => 'Run preventive quality checks before fail-rate escalation impacts assembly.',
                    'decision_guidance' => 'Sample-check incoming runs for the next shift and isolate abnormal variance.',
                    'action_url' => (string)($alert['action_url'] ?? '/ops/analytics/qc/drilldown?type=fail'),
                ];
            } elseif ($type === 'dispatch_delay_increasing') {
                $recommendations[] = [
                    'type' => 'dispatch_sla_guard',
                    'priority' => 'medium',
                    'audience' => 'manager',
                    'message' => 'Re-sequence dispatch plan to protect SLA-critical deliveries.',
                    'decision_guidance' => 'Promote near-breach routes and postpone low-impact routes temporarily.',
                    'action_url' => (string)($alert['action_url'] ?? '/ops/analytics/dispatch/drilldown?type=delayed'),
                ];
            }
        }

        // Deduplicate recommendations by type while preserving priority order.
        $seen = [];
        $unique = [];
        foreach ($recommendations as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = (string)($item['type'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        usort($unique, function (array $a, array $b): int {
            $priorityWeight = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aw = $priorityWeight[(string)($a['priority'] ?? 'low')] ?? 1;
            $bw = $priorityWeight[(string)($b['priority'] ?? 'low')] ?? 1;
            return $bw <=> $aw;
        });

        return $unique;
    }

    /**
     * @param array<int,array<string,mixed>> $series
     */
    private function detectIncreasingTrend(array $series, string $key, float $minimumDelta): bool
    {
        if (count($series) < 6) {
            return false;
        }

        $prev = array_slice($series, 0, 3);
        $curr = array_slice($series, -3);

        $prevAvg = $this->avgSeries($prev, $key);
        $currAvg = $this->avgSeries($curr, $key);

        return ($currAvg - $prevAvg) >= $minimumDelta;
    }

    /**
     * For pass-rate style signals: falling values imply risk rising.
     * @param array<int,array<string,mixed>> $series
     */
    private function detectRisingRiskFromInverseTrend(array $series, string $key, float $minimumDrop): bool
    {
        if (count($series) < 6) {
            return false;
        }

        $prev = array_slice($series, 0, 3);
        $curr = array_slice($series, -3);

        $prevAvg = $this->avgSeries($prev, $key);
        $currAvg = $this->avgSeries($curr, $key);

        return ($prevAvg - $currAvg) >= $minimumDrop;
    }

    /**
     * @param array<int,array<string,mixed>> $series
     */
    private function avgSeries(array $series, string $key): float
    {
        if (empty($series)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($series as $point) {
            if (!is_array($point)) {
                continue;
            }
            $sum += (float)($point[$key] ?? 0);
        }

        return $sum / count($series);
    }

    /**
     * @param array<int,array<string,mixed>> $bottlenecks
     * @param array<int,array<string,mixed>> $correlations
     * @param array<int,array<string,mixed>> $predictiveAlerts
     * @return array<string,mixed>
     */
    private function buildHealthSummary(array $bottlenecks, array $correlations, array $predictiveAlerts): array
    {
        $score = 100;

        foreach ($bottlenecks as $item) {
            if (!is_array($item)) {
                continue;
            }
            $score -= ((string)($item['severity'] ?? 'warning') === 'critical') ? 18 : 10;
        }

        foreach ($correlations as $item) {
            if (!is_array($item)) {
                continue;
            }
            $score -= ((string)($item['severity'] ?? 'warning') === 'critical') ? 12 : 6;
        }

        foreach ($predictiveAlerts as $item) {
            if (!is_array($item)) {
                continue;
            }
            $score -= ((string)($item['severity'] ?? 'warning') === 'critical') ? 10 : 5;
        }

        $score = max(0, min(100, $score));
        $status = 'good';
        if ($score < 70) {
            $status = 'watch';
        }
        if ($score < 50) {
            $status = 'critical';
        }

        return [
            'score' => $score,
            'status' => $status,
            'bottleneck_count' => count($bottlenecks),
            'correlation_count' => count($correlations),
            'predictive_count' => count($predictiveAlerts),
        ];
    }
}
