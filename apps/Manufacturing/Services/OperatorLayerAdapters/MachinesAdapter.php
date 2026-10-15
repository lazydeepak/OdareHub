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
    public static function getData(?array $user = null, string $surface = 'operator'): array
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
            if ($surface !== 'display' && !self::operatorContextIsAuthorized($user)) {
                $defaults['error'] = 'operator.common.data_unavailable';
                return $defaults;
            }

            $result = MachineLeaderDashboardService::build(
                ['date' => date('Y-m-d'), 'surface' => $surface],
                $surface === 'display' ? null : $user
            );
            if ($surface !== 'display') {
                $result = self::projectScopedData($result, (array)($result['scope'] ?? []));
            }
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
                'planned_qty'  => (float)($result['kpi']['today_planned_qty'] ?? $result['today_planned_qty'] ?? 0),
                'produced_qty' => (float)($result['kpi']['today_produced_qty'] ?? $result['today_produced_qty'] ?? 0),
            ];
            $total = $defaults['kpi']['run_now'] + $defaults['kpi']['next_queue'] + $defaults['kpi']['delayed_jobs'];
            $defaults['empty'] = ($total === 0);
        } catch (\Throwable $e) {
            error_log('MachinesAdapter::getData: ' . $e->getMessage());
            $defaults['error'] = 'operator.common.data_unavailable';
        }

        return $defaults;
    }

    /**
     * Operator access requires the resolved Platform context, active app
     * assignment, module access, and an explicit scope object. An empty scope
     * object retains the existing contract's authorized-unrestricted meaning;
     * missing scope evidence does not.
     *
     * @param array<string,mixed>|null $user
     */
    public static function operatorContextIsAuthorized(?array $user): bool
    {
        if (!is_array($user) || (int)($user['id'] ?? 0) <= 0) {
            return false;
        }
        try {
            if (!function_exists('platform_user_context_contract') || !function_exists('platform_user_access_policy_contract')) {
                return false;
            }
            $context = platform_user_context_contract()->resolveUserContext($user);
            if (!is_array($context) || !array_key_exists('scope', $context) || !is_array($context['scope'])) {
                return false;
            }
            $assignedApps = array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                (array)($context['active_assigned_apps'] ?? [])
            );
            if (!in_array('manufacturing', $assignedApps, true)) {
                return false;
            }

            $policy = platform_user_access_policy_contract();
            return $policy->isAppEnabled('manufacturing')
                && $policy->canAccessModule($user, 'machines');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Pure post-projection guard used by the runtime and isolated fixtures.
     * The Manufacturing service performs the SQL scope; this second boundary
     * keeps rows, assigned machines, filters, and count KPIs aligned if a
     * provider returns an over-broad projection.
     *
     * @param array<string,mixed> $result
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    public static function projectScopedData(array $result, array $scope): array
    {
        $machineIds = array_values(array_filter(array_map('intval', (array)($scope['machine_ids'] ?? [])), static fn(int $id): bool => $id > 0));
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $id): bool => $id > 0));
        $filterRows = static function (array $rows) use ($machineIds, $partIds): array {
            return array_values(array_filter($rows, static function ($row) use ($machineIds, $partIds): bool {
                if (!is_array($row)) {
                    return false;
                }
                if ($machineIds !== [] && !in_array((int)($row['machine_id'] ?? 0), $machineIds, true)) {
                    return false;
                }
                $partId = (int)($row['product_id'] ?? $row['part_id'] ?? 0);
                return $partIds === [] || in_array($partId, $partIds, true);
            }));
        };

        foreach (['run_now', 'next_queue', 'delayed_jobs', 'waiting_qc', 'risk_jobs'] as $key) {
            $result[$key] = $filterRows((array)($result[$key] ?? []));
        }
        $machines = array_values(array_filter((array)($result['assigned_machines'] ?? []), static function ($machine) use ($machineIds): bool {
            return is_array($machine) && ($machineIds === [] || in_array((int)($machine['id'] ?? 0), $machineIds, true));
        }));
        $result['assigned_machines'] = $machines;

        $filters = is_array($result['filters'] ?? null) ? $result['filters'] : [];
        if (isset($filters['machines']) && is_array($filters['machines']) && $machineIds !== []) {
            $filters['machines'] = array_values(array_filter($filters['machines'], static fn($machine): bool => is_array($machine) && in_array((int)($machine['id'] ?? 0), $machineIds, true)));
        }
        $filters['sections'] = array_values(array_unique(array_filter(array_map(
            static fn($machine): string => is_array($machine) ? trim((string)($machine['section'] ?? '')) : '',
            $filters['machines'] ?? $machines
        ), static fn(string $section): bool => $section !== '')));
        sort($filters['sections']);
        $result['filters'] = $filters;

        $kpi = is_array($result['kpi'] ?? null) ? $result['kpi'] : [];
        $kpi['assigned_machines'] = count($machines);
        $kpi['run_now_jobs'] = count((array)$result['run_now']);
        $kpi['next_queue_jobs'] = count((array)$result['next_queue']);
        $kpi['delayed_jobs'] = count((array)$result['delayed_jobs']);
        $kpi['waiting_qc_jobs'] = count((array)$result['waiting_qc']);
        $kpi['risk_jobs'] = count((array)$result['risk_jobs']);
        $result['kpi'] = $kpi;
        return $result;
    }
}
