<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'sample_app',
    'items' => [[
        'source_key' => 'studio.generated.sample_app.sample_module',
        'group' => 'Apps',
        'section' => 'Sample App',
        'module' => 'sample_module',
        'owner' => 'sample_app',
        'key' => 'sample_app_sample_module',
        'label' => 'Sample Module',
        'url' => '/apps/sample-app/sample-module',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
