<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyPlans;

use App\Core\EntityRegistry;

function register_assembly_plan_entity(): void
{
    EntityRegistry::register('AssemblyPlan', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'product_id' => ['type' => 'int', 'required' => true],
            'demand_date' => ['type' => 'date', 'required' => true],
            'demand_type' => ['type' => 'string'],
            'system_qty' => ['type' => 'decimal', 'default' => 0],
            'adjusted_qty' => ['type' => 'decimal'],
            'approved_qty' => ['type' => 'decimal'],
            'status' => ['type' => 'string', 'default' => 'calculated'],
            'adjustment_note' => ['type' => 'text'],
            'source_summary_json' => ['type' => 'text'],
            'workflow_state' => ['type' => 'string'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['calculated', 'adjusted', 'approved', 'released', 'completed', 'cancelled'],
            'transitions' => [
                'calculated' => ['adjusted', 'cancelled'],
                'adjusted' => ['approved', 'cancelled'],
                'approved' => ['released', 'cancelled'],
                'released' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanHooks::class, 'beforeCreate']],
            'after_create' => [[\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanHooks::class, 'afterCreate']],
            'before_update' => [[\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanHooks::class, 'beforeUpdate']],
            'after_update' => [[\Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanHooks::class, 'afterUpdate']],
        ],
    ]);
}
