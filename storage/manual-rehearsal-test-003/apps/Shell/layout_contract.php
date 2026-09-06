<?php
declare(strict_types=1);

return [
    'contract' => 'shell.layout.v1',
    'owner' => 'shell',
    'phase' => 'phase4-view-ownership-separation',
    'status' => 'defined_not_migrated',
    'notes' => [
        'Rendering remains on existing layout engine and public layout files for now.',
        'Shell owns entry-surface layout contract and migration sequencing.',
    ],
    'shared_layout_files' => [
        [
            'key' => 'shell.admin_wrapper_open',
            'path' => 'public/views/layouts/admin-wrapper-open.php',
            'current_runtime_owner' => 'shell',
            'contract_owner' => 'shell',
            'surface_scope' => ['admin_surfaces'],
            'migration_state' => 'active',
            'notes' => 'Canonical entry point for /admin/*, /apps/*, /ops/* pages. Delegates to header.php.',
        ],
        [
            'key' => 'shell.admin_wrapper_close',
            'path' => 'public/views/layouts/admin-wrapper-close.php',
            'current_runtime_owner' => 'shell',
            'contract_owner' => 'shell',
            'surface_scope' => ['admin_surfaces'],
            'migration_state' => 'active',
            'notes' => 'Canonical closing tag for admin pages. Delegates to footer.php.',
        ],
        [
            'key' => 'shell.header',
            'path' => 'public/views/layouts/header.php',
            'current_runtime_owner' => 'core_view_engine',
            'contract_owner' => 'shell',
            'surface_scope' => ['authenticated_shell_surfaces'],
            'migration_state' => 'shared-runtime',
            'notes' => 'Shared layout engine. Do not require directly from admin pages — use admin-wrapper-open.php.',
        ],
        [
            'key' => 'shell.footer',
            'path' => 'public/views/layouts/footer.php',
            'current_runtime_owner' => 'core_view_engine',
            'contract_owner' => 'shell',
            'surface_scope' => ['authenticated_shell_surfaces'],
            'migration_state' => 'shared-runtime',
            'notes' => 'Shared layout engine. Do not require directly from admin pages — use admin-wrapper-close.php.',
        ],
        [
            'key' => 'shell.auth_header',
            'path' => 'public/views/layouts/auth_header.php',
            'current_runtime_owner' => 'core_view_engine',
            'contract_owner' => 'shell',
            'surface_scope' => ['auth_surfaces'],
            'migration_state' => 'shared-runtime',
        ],
        [
            'key' => 'shell.auth_footer',
            'path' => 'public/views/layouts/auth_footer.php',
            'current_runtime_owner' => 'core_view_engine',
            'contract_owner' => 'shell',
            'surface_scope' => ['auth_surfaces'],
            'migration_state' => 'shared-runtime',
        ],
    ],
    'render_wrappers' => [
        [
            'key' => 'shell.my_work.v2',
            'handler' => 'Apps\\Shell\\Composers\\AdminSurfaceComposer::renderHTML',
            'view' => 'shell::admin/home.php',
        ],
    ],
];