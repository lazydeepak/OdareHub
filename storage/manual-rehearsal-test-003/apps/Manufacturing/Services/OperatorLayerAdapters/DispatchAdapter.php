<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Plugins\DispatchEntries\Services\DispatchLeaderDashboardService;

/**
 * DispatchAdapter
 *
 * Data-only adapter for the /u/{operator}/dispatch view.
 * Delegates to DispatchLeaderDashboardService and returns pure data arrays — no HTML.
 */
final class DispatchAdapter
{
    /**
     * @return array{
     *   kpi: array<string,int>,
     *   ready_now: array<int,array<string,mixed>>,
     *   blocked_hold: array<int,array<string,mixed>>,
     *   partial_queue: array<int,array<string,mixed>>,
     *   aging_overdue: array<int,array<string,mixed>>,
     *   release_candidates: array<int,array<string,mixed>>,
     *   today: string,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(): array
    {
        $defaults = [
            'kpi' => [
                'ready_now'          => 0,
                'blocked_hold'       => 0,
                'partial_queue'      => 0,
                'aging_overdue'      => 0,
                'release_candidates' => 0,
                'urgent'             => 0,
            ],
            'ready_now'          => [],
            'blocked_hold'       => [],
            'partial_queue'      => [],
            'aging_overdue'      => [],
            'release_candidates' => [],
            'today'              => date('Y-m-d'),
            'empty'              => true,
            'error'              => '',
        ];

        try {
            $result = DispatchLeaderDashboardService::build(['date' => date('Y-m-d')], null);
            $kpi = (array)($result['kpi'] ?? []);
            $defaults['kpi'] = [
                'ready_now'          => (int)($kpi['ready_now'] ?? 0),
                'blocked_hold'       => (int)($kpi['blocked_hold'] ?? 0),
                'partial_queue'      => (int)($kpi['partial_queue'] ?? 0),
                'aging_overdue'      => (int)($kpi['aging_overdue'] ?? 0),
                'release_candidates' => (int)($kpi['release_candidates'] ?? 0),
                'urgent'             => (int)($kpi['urgent'] ?? 0),
            ];
            $defaults['ready_now']          = (array)($result['ready_now'] ?? []);
            $defaults['blocked_hold']       = (array)($result['blocked_hold'] ?? []);
            $defaults['partial_queue']      = (array)($result['partial_queue'] ?? []);
            $defaults['aging_overdue']      = (array)($result['aging_overdue'] ?? []);
            $defaults['release_candidates'] = (array)($result['release_candidates'] ?? []);
            $defaults['today']              = (string)($result['today'] ?? date('Y-m-d'));
            $total = $defaults['kpi']['ready_now'] + $defaults['kpi']['blocked_hold'] + $defaults['kpi']['partial_queue'];
            $defaults['empty'] = ($total === 0);
        } catch (\Throwable $e) {
            $defaults['error'] = $e->getMessage();
            error_log('DispatchAdapter::getData: ' . $e->getMessage());
        }

        return $defaults;
    }
}
