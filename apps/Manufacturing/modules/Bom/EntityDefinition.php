<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom;

use App\Core\EntityRegistry;

function register_bom_entity(): void
{
    EntityRegistry::register('Bom', [
        'fields' => [
            'id' => ['type' => 'int', 'primary' => true],
            'finished_item_ref' => ['type' => 'int', 'required' => true],
            'version' => ['type' => 'string', 'required' => true],
            'revision' => ['type' => 'int', 'required' => true],
            'status' => ['type' => 'string', 'default' => 'draft'],
            'notes' => ['type' => 'text'],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ],
        'workflow' => [
            'states' => ['draft', 'released', 'superseded', 'archived'],
            'transitions' => [
                'draft' => ['released', 'archived'],
                'released' => ['superseded', 'archived'],
                'superseded' => [],
                'archived' => [],
            ],
            'field' => 'status',
        ],
        'permissions' => [
            'rules' => [
                'can_edit_row' => [[\Apps\Manufacturing\Modules\Bom\BomPolicies::class, 'canEdit']],
            ],
        ],
        'hooks' => [
            'before_create' => [[\Apps\Manufacturing\Modules\Bom\BomHooks::class, 'beforeCreate']],
            'after_create' => [[\Apps\Manufacturing\Modules\Bom\BomHooks::class, 'afterCreate']],
            'before_update' => [[\Apps\Manufacturing\Modules\Bom\BomHooks::class, 'beforeUpdate']],
            'after_update' => [[\Apps\Manufacturing\Modules\Bom\BomHooks::class, 'afterUpdate']],
        ],
    ]);
}