<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'inventory_app',
    'items' => [[
        'source_key' => 'studio.generated.inventory_app.stock_entries',
        'group' => 'Apps',
        'section' => 'inventory_app',
        'module' => 'stock_entries',
        'owner' => 'inventory_app',
        'key' => 'inventory_app_stock_entries',
        'label' => 'stock_entries',
        'url' => '/apps/inventory-app/stock-entries',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
