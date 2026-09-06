<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'hardening_app',
    'items' => [[
        'source_key' => 'studio.generated.hardening_app.hardening_module',
        'group' => 'Apps',
        'section' => 'Hardening App',
        'module' => 'hardening_module',
        'owner' => 'hardening_app',
        'key' => 'hardening_app_hardening_module',
        'label' => 'Hardening Module',
        'url' => '/apps/hardening-app/hardening-module',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
