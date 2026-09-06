<?php
declare(strict_types=1);

return [
    'contract' => 'navigation.v1',
    'owner' => 'manufacturing_studio',
    'items' => [[
        'source_key' => 'studio.generated.manufacturing_studio.assemblyentries_studio',
        'group' => 'Apps',
        'section' => 'Apps',
        'module' => 'assemblyentries_studio',
        'owner' => 'manufacturing_studio',
        'key' => 'manufacturing_studio_assemblyentries_studio',
        'label' => 'Assemblyentries Studio',
        'url' => '/apps/manufacturing-studio/assemblyentries-studio',
        'visible_if' => 'role_platform_admin_or_sysadmin',
        'nav_visible' => true,
        'order' => 10,
        'priority' => 100,
    ]],
];
