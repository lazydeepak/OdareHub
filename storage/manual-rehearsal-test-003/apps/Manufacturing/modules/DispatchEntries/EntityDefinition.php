<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries;

use App\Core\EntityRegistry;

function register_dispatch_entry_entity(): void
{
    EntityRegistry::register('DispatchEntry', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'dispatch_date' => ['type' => 'date', 'required' => true],
            'daily_order_id' => ['type' => 'int'],
            'production_plan_id' => ['type' => 'int'],
            'production_entry_id' => ['type' => 'int'],
            'qc_entry_id' => ['type' => 'int'],
            'product_id' => ['type' => 'int', 'required' => true],
            'dispatchable_qty' => ['type' => 'decimal', 'default' => 0],
            'destination' => ['type' => 'string'],
            'dispatch_type' => ['type' => 'string', 'default' => 'Regular'],
            'dispatch_status' => ['type' => 'string', 'default' => 'Draft'],
            'remarks' => ['type' => 'text'],
            'status_reason' => ['type' => 'string'],
            'status_note' => ['type' => 'text'],
            'approval_status' => ['type' => 'string'],
            'workflow_state' => ['type' => 'string'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['Draft', 'Ready', 'Partial', 'Hold', 'Blocked', 'Dispatched', 'Cancelled'],
            'transitions' => [
                'Draft' => ['Ready', 'Cancelled'],
                'Ready' => ['Partial', 'Hold', 'Blocked', 'Dispatched', 'Cancelled'],
                'Partial' => ['Ready', 'Hold', 'Blocked', 'Dispatched', 'Cancelled'],
                'Hold' => ['Ready', 'Partial', 'Cancelled'],
                'Blocked' => ['Ready', 'Partial', 'Cancelled'],
                'Dispatched' => ['Ready', 'Cancelled'],
                'Cancelled' => [],
            ],
            'field' => 'dispatch_status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Plugins\DispatchEntries\DispatchEntryPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Plugins\DispatchEntries\DispatchEntryHooks::class, 'beforeCreate']],
            'after_create' => [[\Plugins\DispatchEntries\DispatchEntryHooks::class, 'afterCreate']],
            'before_update' => [[\Plugins\DispatchEntries\DispatchEntryHooks::class, 'beforeUpdate']],
            'after_update' => [[\Plugins\DispatchEntries\DispatchEntryHooks::class, 'afterUpdate']],
        ],
    ]);
}
