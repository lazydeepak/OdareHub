<?php
declare(strict_types=1);

namespace Apps\Shell\Services\OperatorLayerAdapters;

use Apps\Shell\Services\NotificationsService;

/**
 * Notifications Adapter
 * 
 * Pure data adapter for operator alerts widget.
 * 
 * Returns aggregated alerts from:
 * - QC failures and urgent items
 * - Dispatch blockers and overdue orders  
 * - Coverage critical shortages
 * - Material critical stock
 * 
 * Contract:
 * - alerts: array of alert objects with type, severity, title, count, link
 * - total_critical: int count of critical alerts
 * - total_warning: int count of warning alerts
 * - total_info: int count of informational alerts
 * - empty: bool (true if no alerts)
 * - error: string or null (error message if fetch failed)
 */
final class NotificationsAdapter
{
    /**
     * Get alert data for operator alerts widget
     * 
     * @return array{alerts: array, total_critical: int, total_warning: int, total_info: int, empty: bool, error: string|null}
     */
    public static function getData(): array
    {
        try {
            $data = NotificationsService::getAlerts();
            
            // Combine all alerts in priority order (critical first)
            $alerts = [];
            
            // Add critical alerts
            if (!empty($data['critical'])) {
                $alerts = array_merge($alerts, $data['critical']);
            }
            
            // Add warning alerts
            if (!empty($data['warning'])) {
                $alerts = array_merge($alerts, $data['warning']);
            }
            
            // Add info alerts
            if (!empty($data['info'])) {
                $alerts = array_merge($alerts, $data['info']);
            }
            
            $totalAlerts = count($alerts);
            
            return [
                'alerts' => $alerts,
                'total_critical' => $data['total_critical'] ?? 0,
                'total_warning' => $data['total_warning'] ?? 0,
                'total_info' => $data['total_info'] ?? 0,
                'total_all' => $totalAlerts,
                'empty' => $totalAlerts === 0,
                'error' => $data['error'] ?? null,
            ];
            
        } catch (\Throwable $e) {
            return [
                'alerts' => [],
                'total_critical' => 0,
                'total_warning' => 0,
                'total_info' => 0,
                'total_all' => 0,
                'empty' => true,
                'error' => $e->getMessage(),
            ];
        }
    }
}
