<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

final class AdminRouteRegistryService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function groupedRegistry(bool $materialCanAccessAny = false): array
    {
        $groups = [
            [
                'group_key' => 'core_execution',
                'group_label_key' => 'mfg.portal.registry.group.core_execution',
                'audience_label_key' => 'mfg.portal.registry.audience.all_admin',
                'items' => [
                    ['path' => '/apps/manufacturing', 'label_key' => 'mfg.portal.registry.route.portal'],
                    ['path' => '/apps/manufacturing/production-operation', 'label_key' => 'mfg.portal.registry.route.production_operation'],
                    ['path' => '/apps/manufacturing/processing-operation', 'label_key' => 'mfg.portal.registry.route.processing_operation'],
                    ['path' => '/apps/manufacturing/production-queue', 'label_key' => 'mfg.portal.registry.route.production_queue'],
                    ['path' => '/apps/manufacturing/stage-board', 'label_key' => 'mfg.portal.registry.route.stage_board'],
                    ['path' => '/apps/manufacturing/demand-dashboard', 'label_key' => 'mfg.portal.registry.route.demand_dashboard'],
                    ['path' => '/apps/manufacturing/handoffs', 'label_key' => 'mfg.portal.registry.route.handoffs'],
                ],
            ],
            [
                'group_key' => 'demand_order_plan',
                'group_label_key' => 'mfg.portal.registry.group.demand_order_plan',
                'audience_label_key' => 'mfg.portal.registry.audience.ops_and_planning',
                'items' => [
                    ['path' => '/apps/manufacturing/demands', 'label_key' => 'mfg.portal.registry.route.demands'],
                    ['path' => '/apps/manufacturing/daily-orders', 'label_key' => 'mfg.portal.registry.route.daily_orders'],
                    ['path' => '/apps/manufacturing/daily-orders/add', 'label_key' => 'mfg.portal.registry.route.daily_orders_add'],
                    ['path' => '/apps/manufacturing/production-plans', 'label_key' => 'mfg.portal.registry.route.production_plans'],
                    ['path' => '/apps/manufacturing/production-plans/add', 'label_key' => 'mfg.portal.registry.route.production_plans_add'],
                    ['path' => '/apps/manufacturing/production-plans/queue', 'label_key' => 'mfg.portal.registry.route.production_plans_queue'],
                    ['path' => '/apps/manufacturing/coverage', 'label_key' => 'mfg.portal.registry.route.coverage'],
                ],
            ],
            [
                'group_key' => 'quality_assembly_dispatch',
                'group_label_key' => 'mfg.portal.registry.group.quality_assembly_dispatch',
                'audience_label_key' => 'mfg.portal.registry.audience.execution_admin',
                'items' => [
                    ['path' => '/apps/manufacturing/qc-queue', 'label_key' => 'mfg.portal.registry.route.qc_queue'],
                    ['path' => '/apps/manufacturing/assembly-queue', 'label_key' => 'mfg.portal.registry.route.assembly_queue'],
                    ['path' => '/apps/manufacturing/assembly-plans', 'label_key' => 'mfg.portal.registry.route.assembly_plans'],
                    ['path' => '/apps/manufacturing/dispatch-ops', 'label_key' => 'mfg.portal.registry.route.dispatch_ops'],
                    ['path' => '/apps/manufacturing/dispatch-ops/preparation', 'label_key' => 'mfg.portal.registry.route.dispatch_preparation'],
                ],
            ],
            [
                'group_key' => 'master_data',
                'group_label_key' => 'mfg.portal.registry.group.master_data',
                'audience_label_key' => 'mfg.portal.registry.audience.domain_admin',
                'items' => [
                    ['path' => '/apps/manufacturing/products', 'label_key' => 'mfg.portal.registry.route.products'],
                    ['path' => '/apps/manufacturing/products/add', 'label_key' => 'mfg.portal.registry.route.products_add'],
                    ['path' => '/apps/manufacturing/products/import', 'label_key' => 'mfg.portal.registry.route.products_import'],
                    ['path' => '/apps/manufacturing/materials', 'label_key' => 'mfg.portal.registry.route.materials'],
                    ['path' => '/apps/manufacturing/materials/master', 'label_key' => 'mfg.portal.registry.route.materials_master'],
                    ['path' => '/apps/manufacturing/materials/stock', 'label_key' => 'mfg.portal.registry.route.materials_stock'],
                    ['path' => '/apps/manufacturing/materials/orders', 'label_key' => 'mfg.portal.registry.route.materials_orders'],
                    ['path' => '/apps/manufacturing/materials/receipt', 'label_key' => 'mfg.portal.registry.route.materials_receipt'],
                ],
            ],
            [
                'group_key' => 'operational_workboards',
                'group_label_key' => 'mfg.portal.registry.group.operational_workboards',
                'audience_label_key' => 'mfg.portal.registry.audience.execution_admin',
                'items' => [
                    ['path' => '/apps/manufacturing/production-workboard', 'label_key' => 'mfg.portal.registry.route.production_workboard'],
                    ['path' => '/apps/manufacturing/qc-workboard', 'label_key' => 'mfg.portal.registry.route.qc_workboard'],
                    ['path' => '/apps/manufacturing/assembly-workboard', 'label_key' => 'mfg.portal.registry.route.assembly_workboard'],
                    ['path' => '/apps/manufacturing/dispatch-workboard', 'label_key' => 'mfg.portal.registry.route.dispatch_workboard'],
                    ['path' => '/apps/manufacturing/production-dashboard', 'label_key' => 'mfg.portal.registry.route.production_dashboard'],
                    ['path' => '/apps/manufacturing/qc-dashboard', 'label_key' => 'mfg.portal.registry.route.qc_dashboard'],
                    ['path' => '/apps/manufacturing/assembly-dashboard', 'label_key' => 'mfg.portal.registry.route.assembly_dashboard'],
                    ['path' => '/apps/manufacturing/dispatch-dashboard', 'label_key' => 'mfg.portal.registry.route.dispatch_dashboard'],
                ],
            ],
            [
                'group_key' => 'governance_data_ops',
                'group_label_key' => 'mfg.portal.registry.group.governance_data_ops',
                'audience_label_key' => 'mfg.portal.registry.audience.platform_admin',
                'items' => [
                    ['path' => '/apps/manufacturing/imports', 'label_key' => 'mfg.portal.registry.route.imports'],
                    ['path' => '/apps/manufacturing/exports', 'label_key' => 'mfg.portal.registry.route.exports'],
                    ['path' => '/apps/manufacturing/restores', 'label_key' => 'mfg.portal.registry.route.restores'],
                ],
            ],
        ];

        if (!$materialCanAccessAny) {
            $groups = array_map(static function (array $group): array {
                if ((string)($group['group_key'] ?? '') !== 'master_data') {
                    return $group;
                }

                $group['items'] = array_values(array_filter(
                    (array)($group['items'] ?? []),
                    static fn(array $item): bool => !str_starts_with((string)($item['path'] ?? ''), '/apps/manufacturing/materials')
                ));

                return $group;
            }, $groups);
        }

        return $groups;
    }
}
