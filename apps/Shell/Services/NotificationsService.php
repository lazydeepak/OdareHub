<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;
use Plugins\QCEntries\Services\QcLeaderDashboardService;
use Plugins\DispatchEntries\Services\DispatchLeaderDashboardService;

/**
 * Notifications Service
 * 
 * Aggregates critical alerts from all modules for operator dashboard.
 * Orchestrates QC, Dispatch, Coverage, and Material alerts into unified alert feed.
 * 
 * Data sources (all already available):
 * - QC failures/urgent: QcLeaderDashboardService
 * - Dispatch blockers/overdue: DispatchLeaderDashboardService
 * - Coverage critical: Direct DB query
 * - Material critical stock: Direct DB query
 */
final class NotificationsService
{
    private string $today;

    public function __construct()
    {
        $this->today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    }

    /**
     * Get all critical and warning alerts for operator
     * 
     * Returns three alert levels:
     * - critical: blocking issues requiring immediate action
     * - warning: risky items requiring attention
     * - info: monitoring/status information
     * 
     * @return array{critical: array, warning: array, info: array, total_critical: int, total_warning: int, total_info: int}
     */
    public static function getAlerts(): array
    {
        $service = new self();
        
        $critical = [];
        $warning = [];
        $info = [];

        try {
            // QC critical alerts: failed/recheck and urgent items
            $qcAlerts = $service->getQcAlerts();
            $critical = array_merge($critical, $qcAlerts['critical']);
            $warning = array_merge($warning, $qcAlerts['warning']);
            
            // Dispatch critical alerts: blocked items and aging orders
            $dispatchAlerts = $service->getDispatchAlerts();
            $critical = array_merge($critical, $dispatchAlerts['critical']);
            $warning = array_merge($warning, $dispatchAlerts['warning']);
            
            // Coverage warning: critical shortage risks
            $coverageAlerts = $service->getCoverageAlerts();
            $warning = array_merge($warning, $coverageAlerts);
            
            // Material warning: critical stock levels
            $materialAlerts = $service->getMaterialAlerts();
            $warning = array_merge($warning, $materialAlerts);
            
        } catch (\Throwable $e) {
            return [
                'critical' => [],
                'warning' => [],
                'info' => [],
                'total_critical' => 0,
                'total_warning' => 0,
                'total_info' => 0,
                'error' => $e->getMessage(),
            ];
        }

        // Sort by timestamp (newest first)
        usort($critical, fn($a, $b) => strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01'));
        usort($warning, fn($a, $b) => strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01'));
        usort($info, fn($a, $b) => strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01'));

        // Limit to top alerts per category
        $critical = array_slice($critical, 0, 10);
        $warning = array_slice($warning, 0, 10);
        $info = array_slice($info, 0, 10);

        return [
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'total_critical' => count($critical),
            'total_warning' => count($warning),
            'total_info' => count($info),
        ];
    }

    /**
     * Get QC failure and urgent alerts
     * 
     * @return array{critical: array, warning: array}
     */
    private function getQcAlerts(): array
    {
        $critical = [];
        $warning = [];

        try {
            $data = QcLeaderDashboardService::build(['date' => $this->today], null);
            
            // Critical: Failed/recheck items (need immediate action)
            if (!empty($data['kpi']['failed_recheck'] ?? 0)) {
                $count = (int)($data['kpi']['failed_recheck'] ?? 0);
                $critical[] = [
                    'type' => 'qc_failed',
                    'severity' => 'critical',
                    'title' => $count . ' QC Failures',
                    'description' => 'Items failed QC and need rework',
                    'count' => $count,
                    'link' => '/u/{operator}/qc',
                    'icon' => '❌',
                    'timestamp' => $this->today,
                ];
            }
            
            // Critical: Run now items (need immediate attention)
            if (!empty($data['kpi']['run_now'] ?? 0)) {
                $count = (int)($data['kpi']['run_now'] ?? 0);
                $critical[] = [
                    'type' => 'qc_run_now',
                    'severity' => 'critical',
                    'title' => 'QC: ' . $count . ' Run Now',
                    'description' => 'QC checks required immediately',
                    'count' => $count,
                    'link' => '/u/{operator}/qc',
                    'icon' => '⚠️',
                    'timestamp' => $this->today,
                ];
            }
            
            // Warning: Pending QC (monitor)
            if (!empty($data['kpi']['pending_qc'] ?? 0)) {
                $count = (int)($data['kpi']['pending_qc'] ?? 0);
                if ($count > 0) {
                    $warning[] = [
                        'type' => 'qc_pending',
                        'severity' => 'warning',
                        'title' => $count . ' Pending QC',
                        'description' => 'Output awaiting quality check',
                        'count' => $count,
                        'link' => '/u/{operator}/qc',
                        'icon' => '📋',
                        'timestamp' => $this->today,
                    ];
                }
            }
            
            // Warning: Overdue items
            if (!empty($data['kpi']['overdue'] ?? 0)) {
                $count = (int)($data['kpi']['overdue'] ?? 0);
                if ($count > 0) {
                    $warning[] = [
                        'type' => 'qc_overdue',
                        'severity' => 'warning',
                        'title' => $count . ' QC Overdue',
                        'description' => 'Quality checks past due date',
                        'count' => $count,
                        'link' => '/u/{operator}/qc',
                        'icon' => '⏰',
                        'timestamp' => $this->today,
                    ];
                }
            }
            
        } catch (\Throwable $e) {
            // Silent fail - QC service not available
        }

        return compact('critical', 'warning');
    }

    /**
     * Get dispatch blockers and aging order alerts
     * 
     * @return array{critical: array, warning: array}
     */
    private function getDispatchAlerts(): array
    {
        $critical = [];
        $warning = [];

        try {
            $data = DispatchLeaderDashboardService::build(['date' => $this->today], null);
            
            // Critical: Blocked/hold items (blocking dispatch)
            if (!empty($data['kpi']['blocked_hold'] ?? 0)) {
                $count = (int)($data['kpi']['blocked_hold'] ?? 0);
                $critical[] = [
                    'type' => 'dispatch_blocked',
                    'severity' => 'critical',
                    'title' => $count . ' Dispatch Blocked',
                    'description' => 'Orders blocked from dispatch',
                    'count' => $count,
                    'link' => '/u/{operator}/dispatch',
                    'icon' => '🚫',
                    'timestamp' => $this->today,
                ];
            }
            
            // Critical: Aging/overdue items (past SLA)
            if (!empty($data['kpi']['aging_overdue'] ?? 0)) {
                $count = (int)($data['kpi']['aging_overdue'] ?? 0);
                $critical[] = [
                    'type' => 'dispatch_overdue',
                    'severity' => 'critical',
                    'title' => $count . ' Orders Overdue',
                    'description' => 'Dispatch orders past due date',
                    'count' => $count,
                    'link' => '/u/{operator}/dispatch',
                    'icon' => '🚚',
                    'timestamp' => $this->today,
                ];
            }
            
            // Warning: Ready but not dispatched
            if (!empty($data['kpi']['ready_now'] ?? 0)) {
                $count = (int)($data['kpi']['ready_now'] ?? 0);
                if ($count > 20) { // Only warn if significant backlog
                    $warning[] = [
                        'type' => 'dispatch_ready',
                        'severity' => 'warning',
                        'title' => $count . ' Ready for Dispatch',
                        'description' => 'Large queue of orders ready to ship',
                        'count' => $count,
                        'link' => '/u/{operator}/dispatch',
                        'icon' => '📦',
                        'timestamp' => $this->today,
                    ];
                }
            }
            
        } catch (\Throwable $e) {
            // Silent fail - Dispatch service not available
        }

        return compact('critical', 'warning');
    }

    /**
     * Get coverage critical shortage alerts via DB query
     * 
     * @return array
     */
    private function getCoverageAlerts(): array
    {
        $alerts = [];

        try {
            // Query critical coverage items directly
            $db = new DB();
            
            $criticalCount = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM daily_orders do
                 WHERE DATE(do.order_date) = ? AND do.coverage_ratio < 0.25
                 LIMIT 1",
                [$this->today]
            );
            
            if ($criticalCount && (int)($criticalCount['cnt'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'coverage_critical',
                    'severity' => 'warning',
                    'title' => (int)$criticalCount['cnt'] . ' Critical Coverage',
                    'description' => 'Orders at severe supply risk',
                    'count' => (int)$criticalCount['cnt'],
                    'link' => '/u/{operator}/coverage',
                    'icon' => '📊',
                    'timestamp' => $this->today,
                ];
            }
            
        } catch (\Throwable $e) {
            // Silent fail - Coverage data not available
        }

        return $alerts;
    }

    /**
     * Get material critical stock level alerts via DB query
     * 
     * @return array
     */
    private function getMaterialAlerts(): array
    {
        $alerts = [];

        try {
            $db = new DB();
            
            // Query for critical stock items
            $criticalCount = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM materials 
                 WHERE status='active' AND (stock_level <= (min_stock * 0.25) OR stock_level <= 0)
                 LIMIT 1"
            );
            
            if ($criticalCount && (int)($criticalCount['cnt'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'material_critical',
                    'severity' => 'warning',
                    'title' => (int)$criticalCount['cnt'] . ' Materials Critical',
                    'description' => 'Stock levels critically low',
                    'count' => (int)$criticalCount['cnt'],
                    'link' => '/u/{operator}/materials',
                    'icon' => '📦',
                    'timestamp' => $this->today,
                ];
            }
            
        } catch (\Throwable $e) {
            // Silent fail - Materials service not available
        }

        return $alerts;
    }

    /**
     * Get alert count summary (for badge display)
     * 
     * @return array{critical: int, warning: int, info: int}
     */
    public static function getAlertCounts(): array
    {
        $data = self::getAlerts();
        return [
            'critical' => $data['total_critical'] ?? 0,
            'warning' => $data['total_warning'] ?? 0,
            'info' => $data['total_info'] ?? 0,
        ];
    }
}
