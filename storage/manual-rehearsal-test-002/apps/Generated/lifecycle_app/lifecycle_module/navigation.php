<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'lifecycle_app',
    'items' => [[
        'source_key' => 'studio.generated.lifecycle_app.lifecycle_module',
        'group' => 'Apps',
        'section' => 'Lifecycle App',
        'module' => 'lifecycle_module',
        'owner' => 'lifecycle_app',
        'key' => 'lifecycle_app_lifecycle_module',
        'label' => 'Lifecycle Module',
        'url' => '/apps/lifecycle-app/lifecycle-module',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
