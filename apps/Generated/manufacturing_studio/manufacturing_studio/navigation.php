<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'manufacturing_studio',
    'items' => [[
        'source_key' => 'studio.generated.manufacturing_studio.manufacturing_studio',
        'group' => 'Apps',
        'section' => 'Apps',
        'module' => 'manufacturing_studio',
        'owner' => 'manufacturing_studio',
        'key' => 'manufacturing_studio_manufacturing_studio',
        'label' => 'Manufacturing Studio',
        'url' => '/apps/manufacturing-studio/manufacturing-studio',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
