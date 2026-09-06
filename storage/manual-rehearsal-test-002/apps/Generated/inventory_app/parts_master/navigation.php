<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'inventory_app',
    'items' => [[
        'source_key' => 'studio.generated.inventory_app.parts_master',
        'group' => 'Apps',
        'section' => 'inventory_app',
        'module' => 'parts_master',
        'owner' => 'inventory_app',
        'key' => 'inventory_app_parts_master',
        'label' => 'parts_master',
        'url' => '/apps/inventory-app/parts-master',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
