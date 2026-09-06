<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Plugins\QCEntries\Services\QcLeaderDashboardService;

/**
 * QcAdapter
 *
 * Data-only adapter for the /u/{operator}/qc operator view.
 * Delegates to QcLeaderDashboardService and returns pure data arrays — no HTML.
 */
final class QcAdapter
{
    /**
     * @param int $days  0 = due today; 7 = due within last 7 days; 30 = last 30 days
     * @return array{
     *   kpi: array<string,int>,
     *   run_now: array<int,array<string,mixed>>,
     *   pending_qc: array<int,array<string,mixed>>,
     *   failed_recheck: array<int,array<string,mixed>>,
     *   ready_dispatch: array<int,array<string,mixed>>,
     *   sla: array<string,mixed>,
     *   today: string,
     *   days: int,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(int $days = 0): array
    {
        $defaults = [
            'kpi' => [
                'run_now'        => 0,
                'pending_qc'     => 0,
                'failed_recheck' => 0,
                'ready_dispatch' => 0,
                'urgent'         => 0,
                'overdue'        => 0,
            ],
            'run_now'        => [],
            'pending_qc'     => [],
            'failed_recheck' => [],
            'ready_dispatch' => [],
            'sla'            => [],
            'today'          => date('Y-m-d'),
            'days'           => max(0, $days),
            'empty'          => true,
            'error'          => '',
        ];

        try {
            // When days > 0, use a start date so the queue shows plans from that lookback window.
            $refDate = $days > 0
                ? date('Y-m-d', strtotime("-{$days} days"))
                : date('Y-m-d');

            $result = QcLeaderDashboardService::build(['date' => $refDate], null);
            $kpi = (array)($result['kpi'] ?? []);
            $defaults['kpi']           = [
                'run_now'        => (int)($kpi['run_now'] ?? 0),
                'pending_qc'     => (int)($kpi['pending_qc'] ?? 0),
                'failed_recheck' => (int)($kpi['failed_recheck'] ?? 0),
                'ready_dispatch' => (int)($kpi['ready_dispatch'] ?? 0),
                'urgent'         => (int)($kpi['urgent'] ?? 0),
                'overdue'        => (int)($kpi['overdue'] ?? 0),
            ];
            $defaults['run_now']        = (array)($result['run_now'] ?? []);
            $defaults['pending_qc']     = (array)($result['pending_qc'] ?? []);
            $defaults['failed_recheck'] = (array)($result['failed_recheck'] ?? []);
            $defaults['ready_dispatch'] = (array)($result['ready_dispatch'] ?? []);
            $defaults['sla']            = (array)($result['sla'] ?? []);
            $defaults['today']          = (string)($result['today'] ?? date('Y-m-d'));
            $total = $defaults['kpi']['run_now'] + $defaults['kpi']['pending_qc'] + $defaults['kpi']['failed_recheck'];
            $defaults['empty'] = ($total === 0);
        } catch (\Throwable $e) {
            $defaults['error'] = $e->getMessage();
            error_log('QcAdapter::getData: ' . $e->getMessage());
        }

        return $defaults;
    }
}
