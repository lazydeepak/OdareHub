<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;
use Apps\Shell\Services\OperatorLayerWidgetService;
use Apps\Manufacturing\Services\OperatorLayerAdapters\CoverageAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\QcAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DispatchAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\AssemblyAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MaterialsAdapter;

/**
 * Operator Realtime KPI Provider
 * 
 * Fetches KPI data from operator layer adapters for WebSocket broadcast.
 * Coordinates with cache layer to minimize adapter calls.
 * 
 * Features:
 * - Per-view KPI extraction (summary, critical, recent, etc.)
 * - Cache-aware fetching (checks live cache first)
 * - Graceful error handling
 * 
 * Usage:
 *   $provider = new OperatorRealtimeKpiProvider();
 *   $data = $provider->getKpiForView('dashboard', 'lazy', ['summary'], $cacheManager);
 */
final class OperatorRealtimeKpiProvider
{
    /**
     * Fetch KPI data for operator view with caching
     * 
     * @param string $view View identifier (dashboard, production, etc.)
     * @param string $username Operator username
     * @param string[] $kpiKeys Which KPI sections to return
     * @param OperatorRealtimeCacheManager $cache Cache manager for stale-while-revalidate
     * @return array KPI data with keys matching requested $kpiKeys
     */
    public function getKpiForView(
        string $view,
        string $username,
        array $kpiKeys,
        OperatorRealtimeCacheManager $cache
    ): array {
        $result = [];

        foreach ($kpiKeys as $key) {
            $cacheKey = "kpi:{$view}:{$username}:{$key}";
            
            // Check cache first (stale-while-revalidate)
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                $result[$key] = $cached;
                continue;
            }

            // Cache miss; fetch fresh data
            try {
                $data = match ($view) {
                    'dashboard' => $this->getKpiDashboard($username, $key),
                    'production' => $this->getKpiProduction($username, $key),
                    'processing' => $this->getKpiProcessing($username, $key),
                    'preparation' => $this->getKpiPreparation($username, $key),
                    'dispatch' => $this->getKpiDispatch($username, $key),
                    'coverage' => $this->getKpiCoverage($username, $key),
                    'qc' => $this->getKpiQc($username, $key),
                    'machines' => $this->getKpiMachines($username, $key),
                    'materials' => $this->getKpiMaterials($username, $key),
                    'assembly' => $this->getKpiAssembly($username, $key),
                    default => null,
                };

                if ($data !== null) {
                    // Store in cache with TTL
                    $cache->set($cacheKey, $data);
                    $result[$key] = $data;
                }
            } catch (Exception $e) {
                // Log error but don't fail the entire update
                error_log("KPI fetch error for {$view}/{$key}: {$e->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Extract dashboard KPI summary
     */
    private function getKpiDashboard(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $coverage = new CoverageAdapter();
            $data = $coverage->getData(['user_id' => DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0]);

            return [
                'open_orders' => (int)($data['summary']['open_orders'] ?? 0),
                'critical' => (int)($data['summary']['critical'] ?? 0),
                'low_coverage' => (int)($data['summary']['low_coverage_count'] ?? 0),
                'coverage_pct' => (float)($data['summary']['avg_coverage'] ?? 0),
                'due_today' => (int)($data['summary']['due_today_count'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract production KPI summary
     */
    private function getKpiProduction(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $coverage = new CoverageAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $coverage->getData(['user_id' => $userId]);

            return [
                'low_coverage' => (int)($data['summary']['low_coverage_count'] ?? 0),
                'shortage_parts' => (int)($data['summary']['shortage_parts'] ?? 0),
                'at_risk_today' => (int)($data['summary']['at_risk_today_count'] ?? 0),
                'overstock_parts' => (int)($data['summary']['overstock_parts'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract processing KPI summary
     */
    private function getKpiProcessing(string $username, string $key): ?array
    {
        // TODO: Implement based on ProcessingAdapter
        return null;
    }

    /**
     * Extract preparation KPI summary
     */
    private function getKpiPreparation(string $username, string $key): ?array
    {
        // TODO: Implement based on PreparationAdapter
        return null;
    }

    /**
     * Extract dispatch KPI summary
     */
    private function getKpiDispatch(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $dispatch = new DispatchAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $dispatch->getData(['user_id' => $userId]);

            return [
                'queued' => (int)($data['summary']['queued_count'] ?? 0),
                'in_progress' => (int)($data['summary']['in_progress_count'] ?? 0),
                'blocked' => (int)($data['summary']['blocked_count'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract coverage KPI summary
     */
    private function getKpiCoverage(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $adapter = new CoverageAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $adapter->getData(['user_id' => $userId]);

            return [
                'open_orders' => (int)($data['summary']['open_orders'] ?? 0),
                'critical' => (int)($data['summary']['critical'] ?? 0),
                'low_coverage_count' => (int)($data['summary']['low_coverage_count'] ?? 0),
                'avg_coverage' => (float)($data['summary']['avg_coverage'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract QC KPI summary
     */
    private function getKpiQc(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $adapter = new QcAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $adapter->getData(['user_id' => $userId]);

            return [
                'pending_qc' => (int)($data['summary']['pending_qc'] ?? 0),
                'failed_qc' => (int)($data['summary']['failed_qc'] ?? 0),
                'passed_qc' => (int)($data['summary']['passed_qc'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract machines KPI summary
     */
    private function getKpiMachines(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $adapter = new MachinesAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $adapter->getData(['user_id' => $userId]);

            return [
                'active_machines' => (int)($data['summary']['active_machines'] ?? 0),
                'idle_machines' => (int)($data['summary']['idle_machines'] ?? 0),
                'maintenance' => (int)($data['summary']['maintenance'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract materials KPI summary
     */
    private function getKpiMaterials(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $adapter = new MaterialsAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $adapter->getData(['user_id' => $userId]);

            return [
                'low_stock' => (int)($data['summary']['low_stock'] ?? 0),
                'critical_stock' => (int)($data['summary']['critical_stock'] ?? 0),
                'overstock' => (int)($data['summary']['overstock'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract assembly KPI summary
     */
    private function getKpiAssembly(string $username, string $key): ?array
    {
        if ($key !== 'summary') {
            return null;
        }

        try {
            $adapter = new AssemblyAdapter();
            $userId = DB::fetchOne('SELECT id FROM users WHERE username = ?', [$username])['id'] ?? 0;
            $data = $adapter->getData(['user_id' => $userId]);

            return [
                'pending' => (int)($data['summary']['pending'] ?? 0),
                'in_progress' => (int)($data['summary']['in_progress'] ?? 0),
                'completed' => (int)($data['summary']['completed'] ?? 0),
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
