<?php
declare(strict_types=1);

namespace App\Services;

final class TileActionResolverService
{
    /**
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array<string,mixed>>
     */
    public static function annotateMyWorkSummaryCards(array $cards): array
    {
        return self::annotateMany('me.summary_card', $cards);
    }

    /**
     * @param array<int,array<string,mixed>> $links
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function annotateDemandQuickLinks(array $links, array $context = []): array
    {
        return self::annotateMany('demand.quick_link', $links, $context);
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array<string,mixed>>
     */
    public static function annotateRoleDashboardCards(string $dashboardType, array $cards): array
    {
        return self::annotateMany('role_dashboard.card', $cards, ['dashboard_type' => $dashboardType]);
    }

    /**
     * @param array<int,array<string,mixed>> $tiles
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function annotateMany(string $surface, array $tiles, array $context = []): array
    {
        $definitions = self::definitionsFor($surface, $context);
        $annotated = [];

        foreach ($tiles as $tile) {
            if (!is_array($tile)) {
                continue;
            }
            $annotated[] = self::annotateTile($surface, $tile, $definitions);
        }

        return $annotated;
    }

    /**
     * @param array<string,mixed> $tile
     * @param array<string,array<string,mixed>> $definitions
     * @return array<string,mixed>
     */
    private static function annotateTile(string $surface, array $tile, array $definitions): array
    {
        $key = trim((string)($tile['key'] ?? ''));
        if ($key === '') {
            return $tile;
        }

        $definition = $definitions[$key] ?? null;
        if (!is_array($definition)) {
            return $tile;
        }

        $tile['tile_category'] = (string)($definition['category'] ?? ($tile['tile_category'] ?? ''));

        if (!empty($tile['tile_action']) || !empty($tile['mini_action'])) {
            return $tile;
        }

        $action = self::buildAction($tile, $definition);
        if ($action === null) {
            return $tile;
        }

        $tile['tile_action'] = $action;
        if ($surface === 'role_dashboard.card') {
            $tile['mini_action'] = $action;
        }

        return $tile;
    }

    /**
     * @param array<string,mixed> $tile
     * @param array<string,mixed> $definition
     * @return array<string,string>|null
     */
    private static function buildAction(array $tile, array $definition): ?array
    {
        $url = trim((string)($definition['url'] ?? ($tile['url'] ?? '')));
        if ($url === '') {
            return null;
        }

        $visibility = (string)($definition['visibility'] ?? 'always');
        if ($visibility === 'never') {
            return null;
        }

        $hasSignal = self::signalValue($tile) > (float)($definition['threshold'] ?? 0);
        if ($visibility === 'signal_only' && !$hasSignal) {
            return null;
        }

        $labelKey = $hasSignal
            ? (string)($definition['active_label'] ?? 'common.open')
            : (string)($definition['idle_label'] ?? ($definition['active_label'] ?? 'common.open'));

        if ($labelKey === '') {
            return null;
        }

        return [
            'label' => t($labelKey),
            'url' => $url,
        ];
    }

    /**
     * @param array<string,mixed> $tile
     */
    private static function signalValue(array $tile): float
    {
        $value = $tile['action_metric'] ?? $tile['value'] ?? $tile['count'] ?? 0;
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9.\-]/', '', $value);
            return is_string($normalized) && $normalized !== '' && is_numeric($normalized)
                ? (float)$normalized
                : 0.0;
        }

        return 0.0;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,array<string,mixed>>
     */
    private static function definitionsFor(string $surface, array $context = []): array
    {
        if ($surface === 'me.summary_card') {
            return [
                'coverage_dashboard' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.investigate',
                    'idle_label' => 'common.open',
                ],
                'high_risk_orders' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.investigate',
                    'idle_label' => 'common.open',
                ],
                'approval_inbox' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'idle_label' => 'common.open',
                ],
                'dispatch_ops' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.resolve',
                    'idle_label' => 'common.open',
                ],
                'missing_punches' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.investigate',
                    'idle_label' => 'common.open',
                ],
                'draft_timecards' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'idle_label' => 'common.open',
                ],
                'draft_payroll' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'idle_label' => 'common.open',
                ],
                'pending_leave' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'idle_label' => 'common.open',
                ],
            ];
        }

        if ($surface === 'demand.quick_link') {
            return [
                'daily_orders' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                ],
                'coverage_dashboard' => [
                    'category' => 'informational summary',
                    'visibility' => 'never',
                ],
                'production_plans' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                ],
                'demand_workspace' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                ],
                'stage_board' => [
                    'category' => 'passive watch/monitoring',
                    'visibility' => 'never',
                ],
                'qc_queue' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                ],
                'order_processing' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.resolve',
                ],
                'dispatch_ops' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.resolve',
                ],
                'manufacturing_portal' => [
                    'category' => 'informational summary',
                    'visibility' => 'never',
                ],
                'production_queue' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                ],
                'production_dashboard' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.review',
                ],
                'assembly_queue' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                ],
                'assembly_dashboard' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.review',
                ],
                'qc_dashboard' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.review',
                ],
                'dispatch_dashboard' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.review',
                ],
            ];
        }

        if ($surface !== 'role_dashboard.card') {
            return [];
        }

        $dashboardType = (string)($context['dashboard_type'] ?? '');
        return match ($dashboardType) {
            'production_leader', 'my_work' => [
                'assigned_queue_rows' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'url' => '/manufacturing/production-queue',
                ],
                'production_delays' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.investigate',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/production-queue',
                ],
                'plan_production_gap' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/production-queue',
                ],
                'shortage_affected_orders' => [
                    'category' => 'data/list drill-down',
                    'active_label' => 'common.review',
                    'visibility' => 'signal_only',
                    'url' => '/daily-orders',
                ],
            ],
            'assembly_leader' => [
                'assembly_demand_workload' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'url' => '/manufacturing/assembly-queue',
                ],
                'blocked_upstream_production' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.investigate',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/stage-board',
                ],
                'pending_qc_handoff' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/qc-queue',
                ],
                'pending_assembly_qty' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/assembly-queue',
                ],
            ],
            'qc_leader' => [
                'qc_required_workload' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'url' => '/manufacturing/qc-queue',
                ],
                'failed_qty' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'visibility' => 'signal_only',
                    'url' => '/qc-entries',
                ],
                'pending_qc_checks' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'url' => '/manufacturing/qc-queue',
                ],
                'qc_blocked_exceptions' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.investigate',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/stage-board',
                ],
            ],
            'dispatch_leader' => [
                'ready_to_pack' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.open_work',
                    'url' => '/manufacturing/dispatch-ops',
                ],
                'packed_pending_complete' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.review',
                    'visibility' => 'signal_only',
                    'url' => '/dispatch-entries',
                ],
                'cases_pallet_updates_pending' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.resolve',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/dispatch-ops',
                ],
                'blocked_dispatches' => [
                    'category' => 'workflow/actionable',
                    'active_label' => 'common.resolve',
                    'visibility' => 'signal_only',
                    'url' => '/manufacturing/dispatch-ops/preparation',
                ],
            ],
            default => [],
        };
    }
}
