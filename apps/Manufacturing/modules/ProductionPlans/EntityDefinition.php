<?php
declare(strict_types=1);

namespace Plugins\ProductionPlans;

use App\Core\EntityRegistry;

function register_production_plan_entity(): void
{
    EntityRegistry::register('ProductionPlan', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'plan_date' => ['type' => 'date', 'required' => true],
            'machine_id' => ['type' => 'int', 'required' => true],
            'product_id' => ['type' => 'int', 'required' => true],
            'planned_qty' => ['type' => 'decimal', 'default' => 0],
            'sequence_no' => ['type' => 'int', 'default' => 1],
            'runtime' => ['type' => 'decimal'],
            'status' => ['type' => 'string', 'default' => 'Planned'],
            'plan_type' => ['type' => 'string', 'default' => 'Manual'],
            'reference_doctype' => ['type' => 'string'],
            'reference_name' => ['type' => 'string'],
            'coverage_pct' => ['type' => 'decimal', 'default' => 0],
            'shortage_qty' => ['type' => 'decimal', 'default' => 0],
            'auto_created' => ['type' => 'int', 'default' => 0],
            'notes' => ['type' => 'text'],
            'added_by' => ['type' => 'string'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['Draft', 'Planned', 'InProgress', 'Completed', 'Closed', 'Cancelled'],
            'transitions' => [
                'Draft' => ['Planned', 'Cancelled'],
                'Planned' => ['InProgress', 'Cancelled'],
                'InProgress' => ['Completed', 'Cancelled'],
                'Completed' => ['Closed', 'Cancelled'],
                'Closed' => [],
                'Cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Plugins\ProductionPlans\ProductionPlanPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Plugins\ProductionPlans\ProductionPlanHooks::class, 'beforeCreate']],
            'after_create' => [[\Plugins\ProductionPlans\ProductionPlanHooks::class, 'afterCreate']],
            'before_update' => [[\Plugins\ProductionPlans\ProductionPlanHooks::class, 'beforeUpdate']],
            'after_update' => [[\Plugins\ProductionPlans\ProductionPlanHooks::class, 'afterUpdate']],
        ],
    ]);
}
