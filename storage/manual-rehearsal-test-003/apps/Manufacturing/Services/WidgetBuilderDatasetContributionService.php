<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

final class WidgetBuilderDatasetContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function contribute(array $request = []): array
    {
        return ['datasets' => self::datasets()];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function datasets(): array
    {
        return [
            [
                'dataset_key' => 'coverage.summary',
                'app_key' => 'manufacturing',
                'module_key' => 'coverage',
                'label_key' => 'ops.widget_builder.dataset.coverage_summary.label',
                'description_key' => 'ops.widget_builder.dataset.coverage_summary.description',
                'allowed_fields' => ['coverage_pct', 'risk_bucket', 'critical_orders', 'window_days'],
                'allowed_filters' => ['window_days', 'risk_bucket'],
                'allowed_aggregations' => ['count', 'sum', 'avg', 'min', 'max'],
                'allowed_runtime_fields' => [
                    'summary.coverage_pct',
                    'summary.open_orders',
                    'summary.demand_qty',
                    'summary.shortage_qty',
                    'summary.critical_orders_count',
                    'summary.low_coverage_orders_count',
                    'summary.fully_covered_orders_count',
                    'summary.window_today_count',
                    'summary.window_3day_count',
                    'summary.window_7day_count',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'summary.coverage_pct', 'subtitle_field' => 'summary.open_orders', 'limit' => 10],
                    'queue' => ['metric_field' => 'summary.critical_orders_count', 'subtitle_field' => 'summary.low_coverage_orders_count', 'limit' => 15],
                    'chart' => ['metric_field' => 'summary.window_7day_count', 'aggregation' => 'count', 'limit' => 14],
                    'table' => ['preview_fields' => ['summary.open_orders', 'summary.demand_qty', 'summary.shortage_qty'], 'limit' => 25],
                ],
            ],
            [
                'dataset_key' => 'qc.status',
                'app_key' => 'manufacturing',
                'module_key' => 'qc',
                'label_key' => 'ops.widget_builder.dataset.qc_status.label',
                'description_key' => 'ops.widget_builder.dataset.qc_status.description',
                'allowed_fields' => ['pending_count', 'failed_count', 'ready_count', 'run_now_count'],
                'allowed_filters' => ['days'],
                'allowed_aggregations' => ['count', 'sum', 'avg'],
                'allowed_runtime_fields' => [
                    'kpi.run_now',
                    'kpi.pending_qc',
                    'kpi.failed_recheck',
                    'kpi.ready_dispatch',
                    'kpi.urgent',
                    'kpi.overdue',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'kpi.run_now', 'subtitle_field' => 'kpi.pending_qc', 'limit' => 10],
                    'queue' => ['metric_field' => 'kpi.failed_recheck', 'subtitle_field' => 'kpi.ready_dispatch', 'limit' => 15],
                    'chart' => ['metric_field' => 'kpi.pending_qc', 'aggregation' => 'count', 'limit' => 14],
                    'table' => ['preview_fields' => ['kpi.run_now', 'kpi.pending_qc', 'kpi.failed_recheck'], 'limit' => 25],
                ],
            ],
            [
                'dataset_key' => 'dispatch.queue',
                'app_key' => 'manufacturing',
                'module_key' => 'dispatch',
                'label_key' => 'ops.widget_builder.dataset.dispatch_queue.label',
                'description_key' => 'ops.widget_builder.dataset.dispatch_queue.description',
                'allowed_fields' => ['queue_count', 'completed_today', 'blocked_count', 'destination'],
                'allowed_filters' => ['destination', 'status'],
                'allowed_aggregations' => ['count', 'sum'],
                'allowed_runtime_fields' => [
                    'kpi.ready_now',
                    'kpi.blocked_hold',
                    'kpi.partial_queue',
                    'kpi.aging_overdue',
                    'kpi.release_candidates',
                    'kpi.urgent',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'kpi.ready_now', 'subtitle_field' => 'kpi.blocked_hold', 'limit' => 10],
                    'queue' => ['metric_field' => 'kpi.ready_now', 'subtitle_field' => 'kpi.partial_queue', 'limit' => 15],
                    'chart' => ['metric_field' => 'kpi.aging_overdue', 'aggregation' => 'count', 'limit' => 14],
                    'table' => ['preview_fields' => ['kpi.ready_now', 'kpi.blocked_hold', 'kpi.partial_queue'], 'limit' => 25],
                ],
            ],
            [
                'dataset_key' => 'machines.workboard',
                'app_key' => 'manufacturing',
                'module_key' => 'machines',
                'label_key' => 'ops.widget_builder.dataset.machines_workboard.label',
                'description_key' => 'ops.widget_builder.dataset.machines_workboard.description',
                'allowed_fields' => ['active_count', 'idle_count', 'blocked_count', 'machine_code'],
                'allowed_filters' => ['status', 'area'],
                'allowed_aggregations' => ['count', 'sum'],
                'allowed_runtime_fields' => [
                    'kpi.run_now',
                    'kpi.next_queue',
                    'kpi.delayed_jobs',
                    'kpi.waiting_qc',
                    'kpi.planned_qty',
                    'kpi.produced_qty',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'kpi.run_now', 'subtitle_field' => 'kpi.next_queue', 'limit' => 10],
                    'queue' => ['metric_field' => 'kpi.delayed_jobs', 'subtitle_field' => 'kpi.waiting_qc', 'limit' => 15],
                    'chart' => ['metric_field' => 'kpi.produced_qty', 'aggregation' => 'sum', 'limit' => 14],
                    'table' => ['preview_fields' => ['kpi.run_now', 'kpi.next_queue', 'kpi.delayed_jobs'], 'limit' => 25],
                ],
            ],
            [
                'dataset_key' => 'assembly.execution',
                'app_key' => 'manufacturing',
                'module_key' => 'assembly',
                'label_key' => 'ops.widget_builder.dataset.assembly_execution.label',
                'description_key' => 'ops.widget_builder.dataset.assembly_execution.description',
                'allowed_fields' => ['setup_queue', 'assembly_queue', 'verification_queue', 'sla_state'],
                'allowed_filters' => ['days', 'sla_state'],
                'allowed_aggregations' => ['count', 'sum'],
                'allowed_runtime_fields' => [
                    'kpi.today_total',
                    'kpi.in_progress',
                    'kpi.completed_today',
                    'kpi.pending_approval',
                    'kpi.blocked',
                    'kpi.planned_qty',
                    'kpi.completed_qty',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'kpi.today_total', 'subtitle_field' => 'kpi.in_progress', 'limit' => 10],
                    'queue' => ['metric_field' => 'kpi.pending_approval', 'subtitle_field' => 'kpi.blocked', 'limit' => 15],
                    'chart' => ['metric_field' => 'kpi.completed_qty', 'aggregation' => 'sum', 'limit' => 14],
                    'table' => ['preview_fields' => ['kpi.today_total', 'kpi.in_progress', 'kpi.pending_approval'], 'limit' => 25],
                ],
            ],
            [
                'dataset_key' => 'materials.pressure',
                'app_key' => 'manufacturing',
                'module_key' => 'materials',
                'label_key' => 'ops.widget_builder.dataset.materials_pressure.label',
                'description_key' => 'ops.widget_builder.dataset.materials_pressure.description',
                'allowed_fields' => ['shortage_count', 'overflow_count', 'delayed_count', 'coverage_days'],
                'allowed_filters' => ['risk_bucket', 'days'],
                'allowed_aggregations' => ['count', 'sum', 'avg', 'min', 'max'],
                'allowed_runtime_fields' => [
                    'kpi.total_materials',
                    'kpi.low_stock_count',
                    'kpi.zero_stock_count',
                    'kpi.open_orders_count',
                    'kpi.critical_shortage',
                ],
                'template_defaults' => [
                    'kpi' => ['metric_field' => 'kpi.low_stock_count', 'subtitle_field' => 'kpi.critical_shortage', 'limit' => 10],
                    'queue' => ['metric_field' => 'kpi.open_orders_count', 'subtitle_field' => 'kpi.zero_stock_count', 'limit' => 15],
                    'chart' => ['metric_field' => 'kpi.total_materials', 'aggregation' => 'count', 'limit' => 14],
                    'table' => ['preview_fields' => ['kpi.low_stock_count', 'kpi.zero_stock_count', 'kpi.open_orders_count'], 'limit' => 25],
                ],
            ],
        ];
    }
}
