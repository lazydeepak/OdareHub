<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'manufacturing_app',
    'items' => [[
        'source_key' => 'studio.generated.manufacturing_app.production_plan',
        'group' => 'Apps',
        'section' => 'manufacturing_app',
        'module' => 'production_plan',
        'owner' => 'manufacturing_app',
        'key' => 'manufacturing_app_production_plan',
        'label' => 'production_plan',
        'url' => '/apps/manufacturing-app/production-plan',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
