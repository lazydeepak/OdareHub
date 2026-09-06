<?php
declare(strict_types=1);

$shellSidebarPath = APP_ROOT . '/apps/Shell/sidebar.php';
if (is_file($shellSidebarPath)) {
    $shellSidebar = require $shellSidebarPath;
    if (is_array($shellSidebar)) {
        return $shellSidebar;
    }
}

/**
 * Sidebar shell config.
 *
 * This file intentionally keeps only stable shell/taxonomy metadata:
 * - sections
 * - groups
 * - sorting/open behavior
 * - registry source bindings
 *
 * Concrete navigation links are registered from dynamic sources loaded by
 * SidebarBuilder (see app/Navigation/sidebar_sources.php).
 */
return [
    'sections' => [
        'operations' => [
            'label' => 'Operations',
            'order' => 10,
        ],
        'apps' => [
            'label' => 'Apps',
            'order' => 20,
        ],
        'admin' => [
            'label' => 'Admin / System',
            'order' => 30,
        ],
    ],
    'groups' => [
        [
            'key'       => 'platform_operations_work',
            'section'   => 'operations',
            'domain'    => 'platform',
            'type'      => 'core',
            'label'     => 'Work',
            'order'     => 10,
            'auto_open' => true,
            'source_keys' => ['core.operations.work'],
            'items'     => [],
        ],
        [
            'key'       => 'platform_operations_review',
            'section'   => 'operations',
            'domain'    => 'platform',
            'type'      => 'core',
            'label'     => 'Approve / Review',
            'order'     => 20,
            'source_keys' => ['core.operations.review'],
            'items'     => [],
        ],
        [
            'key'       => 'platform_operations_tracking',
            'section'   => 'operations',
            'domain'    => 'platform',
            'type'      => 'core',
            'label'     => 'Track / Coordinate',
            'order'     => 30,
            'source_keys' => ['core.operations.tracking'],
            'items'     => [],
        ],
        [
            'key'       => 'manufacturing_workboards',
            'section'   => 'apps',
            'domain'    => 'manufacturing',
            'type'      => 'business_app',
            'label'     => 'Manufacturing',
            'order'     => 10,
            'auto_open' => true,
            'source_keys' => ['apps.manufacturing.workboards'],
            'items'     => [],
        ],
        [
            'key'       => 'manufacturing_execution',
            'section'   => 'apps',
            'domain'    => 'manufacturing',
            'type'      => 'business_app',
            'label'     => 'Demand & Orders',
            'order'     => 20,
            'source_keys' => ['apps.manufacturing.demand_orders'],
            'items'     => [],
        ],
        [
            'key'       => 'manufacturing_execution_queues',
            'section'   => 'apps',
            'domain'    => 'manufacturing',
            'type'      => 'business_app',
            'label'     => 'Work Queues',
            'order'     => 30,
            'source_keys' => ['apps.manufacturing.queues'],
            'items'     => [],
        ],
        [
            'key'       => 'manufacturing_reference',
            'section'   => 'apps',
            'domain'    => 'manufacturing',
            'type'      => 'business_app',
            'label'     => 'Reference / Planning',
            'order'     => 40,
            'source_keys' => ['apps.manufacturing.reference'],
            'items'     => [],
        ],
        [
            'key'       => 'sbaio_apps',
            'section'   => 'apps',
            'domain'    => 'sbaio',
            'type'      => 'business_app',
            'label_key' => 'nav.sbaio_apps',
            'order'     => 50,
            'source_keys' => ['apps.sbaio.dashboard', 'apps.sbaio.modules', 'apps.sbaio.reports'],
            'items'     => [],
        ],
        // Removed: admin_platform_system (platform admin links moved to runtime widget)
        // Removed: Architecture Health (moved to runtime verification if needed)
    ],
];
