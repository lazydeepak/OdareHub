<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'rollback_app',
    'items' => [[
        'source_key' => 'studio.generated.rollback_app.rollback_module',
        'group' => 'Apps',
        'section' => 'Rollback App',
        'module' => 'rollback_module',
        'owner' => 'rollback_app',
        'key' => 'rollback_app_rollback_module',
        'label' => 'Rollback Module',
        'url' => '/apps/rollback-app/rollback-module',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
