<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyEntries;

use App\Core\EntityRegistry;

function register_assembly_entry_entity(): void
{
    EntityRegistry::register('AssemblyEntry', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'assembly_plan_id' => ['type' => 'int'],
            'product_id' => ['type' => 'int', 'required' => true],
            'source_type' => ['type' => 'string'],
            'source_id' => ['type' => 'int'],
            'assembly_date' => ['type' => 'date', 'required' => true],
            'planned_qty' => ['type' => 'decimal', 'default' => 0],
            'completed_qty' => ['type' => 'decimal', 'default' => 0],
            'rejected_qty' => ['type' => 'decimal', 'default' => 0],
            'status' => ['type' => 'string', 'default' => 'draft'],
            'note' => ['type' => 'text'],
            'completed_by' => ['type' => 'string'],
            'approved_by' => ['type' => 'string'],
            'approved_at' => ['type' => 'datetime'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['draft', 'in_progress', 'completed', 'approved', 'blocked', 'cancelled'],
            'transitions' => [
                'draft' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed', 'blocked', 'cancelled'],
                'completed' => ['approved', 'cancelled'],
                'approved' => [],
                'blocked' => ['in_progress', 'cancelled'],
                'cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryHooks::class, 'beforeCreate']],
            'after_create' => [[\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryHooks::class, 'afterCreate']],
            'before_update' => [[\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryHooks::class, 'beforeUpdate']],
            'after_update' => [[\Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryHooks::class, 'afterUpdate']],
        ],
    ]);
}
