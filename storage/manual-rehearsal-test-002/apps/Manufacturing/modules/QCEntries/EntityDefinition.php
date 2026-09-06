<?php
declare(strict_types=1);

namespace Plugins\QCEntries;

use App\Core\EntityRegistry;

function register_qc_entry_entity(): void
{
    EntityRegistry::register('QCEntry', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'qc_plan_id' => ['type' => 'int'],
            'daily_order_id' => ['type' => 'int'],
            'production_plan_id' => ['type' => 'int'],
            'product_id' => ['type' => 'int', 'required' => true],
            'qc_type' => ['type' => 'string', 'default' => 'Final'],
            'checked_qty' => ['type' => 'decimal', 'default' => 0],
            'pass_qty' => ['type' => 'decimal', 'default' => 0],
            'fail_qty' => ['type' => 'decimal', 'default' => 0],
            'status' => ['type' => 'string', 'default' => 'Draft'],
            'remarks' => ['type' => 'text'],
            'approval_status' => ['type' => 'string'],
            'workflow_state' => ['type' => 'string'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['Draft', 'Open', 'Submitted', 'Approved', 'Rejected', 'Cancelled'],
            'transitions' => [
                'Draft' => ['Open', 'Cancelled'],
                'Open' => ['Submitted', 'Cancelled'],
                'Submitted' => ['Approved', 'Rejected', 'Cancelled'],
                'Approved' => [],
                'Rejected' => ['Open', 'Cancelled'],
                'Cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Plugins\QCEntries\QCEntryPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Plugins\QCEntries\QCEntryHooks::class, 'beforeCreate']],
            'after_create' => [[\Plugins\QCEntries\QCEntryHooks::class, 'afterCreate']],
            'before_update' => [[\Plugins\QCEntries\QCEntryHooks::class, 'beforeUpdate']],
            'after_update' => [[\Plugins\QCEntries\QCEntryHooks::class, 'afterUpdate']],
        ],
    ]);
}
