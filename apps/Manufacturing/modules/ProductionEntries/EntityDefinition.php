<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries;

use App\Core\EntityRegistry;

function register_production_entry_entity(): void
{
    EntityRegistry::register('ProductionEntry', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'production_date' => ['type' => 'date', 'required' => true],
            'shift' => ['type' => 'string', 'default' => 'Day'],
            'machine_id' => ['type' => 'int', 'required' => true],
            'product_id' => ['type' => 'int', 'required' => true],
            'produced_qty' => ['type' => 'decimal', 'default' => 0],
            'rejected_qty' => ['type' => 'decimal', 'default' => 0],
            'good_qty' => ['type' => 'decimal', 'default' => 0],
            'status' => ['type' => 'string', 'default' => 'Draft'],
            'notes' => ['type' => 'text'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['Draft', 'Open', 'Posted', 'Cancelled'],
            'transitions' => [
                'Draft' => ['Open', 'Cancelled'],
                'Open' => ['Posted', 'Cancelled'],
                'Posted' => [],
                'Cancelled' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Plugins\ProductionEntries\ProductionEntryPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Plugins\ProductionEntries\ProductionEntryHooks::class, 'beforeCreate']],
            'after_create' => [[\Plugins\ProductionEntries\ProductionEntryHooks::class, 'afterCreate']],
            'before_update' => [[\Plugins\ProductionEntries\ProductionEntryHooks::class, 'beforeUpdate']],
            'after_update' => [[\Plugins\ProductionEntries\ProductionEntryHooks::class, 'afterUpdate']],
        ],
    ]);
}
