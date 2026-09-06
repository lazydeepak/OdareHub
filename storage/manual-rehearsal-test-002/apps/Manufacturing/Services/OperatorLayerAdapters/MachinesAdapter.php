<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Plugins\Machines\Services\MachineLeaderDashboardService;

/**
 * MachinesAdapter
 *
 * Data-only adapter for the /u/{operator}/machines operator view.
 * Delegates to MachineLeaderDashboardService and returns pure data arrays — no HTML.
 */
final class MachinesAdapter
{
    /**
     * @return array{
     *   kpi: array<string,int|float>,
     *   run_now: array<int,array<string,mixed>>,
     *   next_queue: array<int,array<string,mixed>>,
     *   delayed_jobs: array<int,array<string,mixed>>,
     *   waiting_qc: array<int,array<string,mixed>>,
     *   sections: array<int,string>,
     *   today: string,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(): array
    {
        $defaults = [
            'kpi' => [
                'run_now'       => 0,
                'next_queue'    => 0,
                'delayed_jobs'  => 0,
                'waiting_qc'    => 0,
                'planned_qty'   => 0.0,
                'produced_qty'  => 0.0,
            ],
            'run_now'      => [],
            'next_queue'   => [],
            'delayed_jobs' => [],
            'waiting_qc'   => [],
            'sections'     => [],
            'today'        => date('Y-m-d'),
            'empty'        => true,
            'error'        => '',
        ];

        try {
            $result = MachineLeaderDashboardService::build(['date' => date('Y-m-d')], null);
            $defaults['run_now']      = (array)($result['run_now'] ?? []);
            $defaults['next_queue']   = (array)($result['next_queue'] ?? []);
            $defaults['delayed_jobs'] = (array)($result['delayed_jobs'] ?? []);
            $defaults['waiting_qc']   = (array)($result['waiting_qc'] ?? []);
            $defaults['sections']     = (array)($result['sections'] ?? []);
            $defaults['today']        = (string)($result['today'] ?? date('Y-m-d'));
            $defaults['kpi'] = [
                'run_now'      => count($defaults['run_now']),
                'next_queue'   => count($defaults['next_queue']),
                'delayed_jobs' => count($defaults['delayed_jobs']),
                'waiting_qc'   => count($defaults['waiting_qc']),
                'planned_qty'  => (float)($result['today_planned_qty'] ?? 0),
                'produced_qty' => (float)($result['today_produced_qty'] ?? 0),
            ];
            $total = $defaults['kpi']['run_now'] + $defaults['kpi']['next_queue'] + $defaults['kpi']['delayed_jobs'];
            $defaults['empty'] = ($total === 0);
        } catch (\Throwable $e) {
            $defaults['error'] = $e->getMessage();
            error_log('MachinesAdapter::getData: ' . $e->getMessage());
        }

        return $defaults;
    }
}
