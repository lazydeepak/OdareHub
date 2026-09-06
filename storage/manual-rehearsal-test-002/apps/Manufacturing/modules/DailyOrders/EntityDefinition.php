<?php
declare(strict_types=1);

namespace Plugins\DailyOrders;

use App\Core\EntityRegistry;

function register_daily_orders_entity(): void
{
    EntityRegistry::register('DailyOrder', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'order_date' => ['type' => 'date', 'required' => true],
            'required_date' => ['type' => 'date'],
            'customer_name' => ['type' => 'string', 'required' => true],
            'product_id' => ['type' => 'int', 'required' => true],
            'qty' => ['type' => 'decimal', 'default' => 0],
            'dispatch_deadline' => ['type' => 'datetime'],
            'coverage_pct' => ['type' => 'decimal', 'default' => 0],
            'coverage_status' => ['type' => 'string', 'default' => 'Low'],
            'shortage_qty' => ['type' => 'decimal', 'default' => 0],
            'planned_supply_qty' => ['type' => 'decimal', 'default' => 0],
            'status' => ['type' => 'string', 'default' => 'Open'],
            'notes' => ['type' => 'text'],
            'usable_stock_qty' => ['type' => 'decimal', 'default' => 0],
            'qc_pass_qty' => ['type' => 'decimal', 'default' => 0],
            'dispatched_qty' => ['type' => 'decimal', 'default' => 0],
            'usable_supply_qty' => ['type' => 'decimal', 'default' => 0],
            'open_demand_qty' => ['type' => 'decimal', 'default' => 0],
            'forecast_pressure_qty' => ['type' => 'decimal', 'default' => 0],
            'coverage_last_recalculated_at' => ['type' => 'datetime'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['Open', 'InProgress', 'Fulfilled', 'Cancelled'],
            'transitions' => [
                'Open' => ['InProgress', 'Cancelled'],
                'InProgress' => ['Fulfilled', 'Cancelled'],
                'Fulfilled' => [],
                'Cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Plugins\DailyOrders\DailyOrderPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Plugins\DailyOrders\DailyOrderHooks::class, 'beforeCreate']],
            'after_create' => [[\Plugins\DailyOrders\DailyOrderHooks::class, 'afterCreate']],
            'before_update' => [[\Plugins\DailyOrders\DailyOrderHooks::class, 'beforeUpdate']],
            'after_update' => [[\Plugins\DailyOrders\DailyOrderHooks::class, 'afterUpdate']],
        ],
    ]);
}
