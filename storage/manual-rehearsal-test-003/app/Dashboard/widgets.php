<?php
declare(strict_types=1);

$shellWidgetsPath = APP_ROOT . '/apps/Shell/dashboard_widgets.php';
if (is_file($shellWidgetsPath)) {
    $shellWidgets = require $shellWidgetsPath;
    if (is_array($shellWidgets)) {
        return $shellWidgets;
    }
}

/**
 * Home Dashboard v2 widget definitions.
 *
 * Schema:
 * - key
 * - label_key
 * - module_key
 * - domain
 * - owner_plugin
 * - zone
 * - priority
 * - visible_if
 * - status
 * - is_placeholder
 * - url
 */
return [
    'core_widgets' => [
        [
            'key' => 'core.my_work',
            'label_key' => 'nav.my_work',
            'module_key' => 'platform',
            'domain' => 'platform',
            'owner_plugin' => 'Base',
            'zone' => 'platform',
            'priority' => 10,
            'visible_if' => 'role_ops',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/',
            'description_key' => 'dashboard.core_my_work_desc',
        ],
        [
            'key' => 'core.admin_system_links',
            'label_key' => 'nav.admin_system_links',
            'module_key' => 'admin_tools',
            'domain' => 'admin',
            'owner_plugin' => 'Base',
            'zone' => 'platform',
            'priority' => 15,
            'visible_if' => 'role_platform_admin_or_sysadmin',
            'status' => 'active',
            'is_placeholder' => false,
            'description_key' => 'dashboard.admin_system_desc',
        ],
        [
            'key' => 'core.alerts',
            'label_key' => 'nav.alerts',
            'module_key' => 'platform',
            'domain' => 'platform',
            'owner_plugin' => 'Base',
            'zone' => 'platform',
            'priority' => 20,
            'visible_if' => 'logged_in',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/ops/notifications',
            'description_key' => 'dashboard.core_alerts_desc',
        ],
        [
            'key' => 'core.approval_inbox',
            'label_key' => 'nav.approval_inbox',
            'module_key' => 'platform',
            'domain' => 'platform',
            'owner_plugin' => 'Base',
            'zone' => 'platform',
            'priority' => 25,
            'visible_if' => 'role_governance',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/ops/approval-inbox',
            'description_key' => 'dashboard.core_approval_desc',
        ],
        [
            'key' => 'core.admin_tools',
            'label_key' => 'dashboard.admin_tools',
            'module_key' => 'admin_tools',
            'domain' => 'admin',
            'owner_plugin' => 'AdminTools',
            'zone' => 'platform',
            'priority' => 30,
            'visible_if' => 'admin_tools',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/admin/apps',
            'description_key' => 'dashboard.admin_tools_subtitle',
        ],
        [
            'key' => 'core.routes',
            'label_key' => 'common.routes',
            'module_key' => 'admin_tools',
            'domain' => 'admin',
            'owner_plugin' => 'AdminTools',
            'zone' => 'platform',
            'priority' => 40,
            'visible_if' => 'admin_tools',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/admin/routes',
            'description_key' => 'dashboard.core_routes_desc',
        ],
        [
            'key' => 'core.base_builder',
            'label_key' => 'common.base',
            'module_key' => 'admin_tools',
            'domain' => 'admin',
            'owner_plugin' => 'Base',
            'zone' => 'platform',
            'priority' => 50,
            'visible_if' => 'can_access_base',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/admin/base',
            'description_key' => 'dashboard.core_base_desc',
        ],
        [
            'key' => 'core.admin_system_links',
            'label_key' => 'dashboard.admin_system_links',
            'module_key' => 'admin_tools',
            'domain' => 'admin',
            'owner_plugin' => 'AdminTools',
            'zone' => 'platform',
            'priority' => 60,
            'visible_if' => 'role_platform_admin_or_sysadmin',
            'status' => 'active',
            'is_placeholder' => false,
            'url' => '/admin',
            'description_key' => 'dashboard.admin_system_links_desc',
        ],
    ],

    'extension_placeholders' => [
        [
            'key' => 'ext.hr',
            'label_key' => 'module.hr',
            'module_key' => 'hr',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 10,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
        [
            'key' => 'ext.accounting',
            'label_key' => 'module.accounting',
            'module_key' => 'accounting',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 20,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
        [
            'key' => 'ext.crm',
            'label_key' => 'module.crm',
            'module_key' => 'crm',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 30,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
        [
            'key' => 'ext.procurement',
            'label_key' => 'module.procurement',
            'module_key' => 'procurement',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 40,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
        [
            'key' => 'ext.maintenance',
            'label_key' => 'module.maintenance',
            'module_key' => 'maintenance',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 50,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
        [
            'key' => 'ext.reports_bi',
            'label_key' => 'module.reports_bi',
            'module_key' => 'reports_bi',
            'domain' => 'extension',
            'owner_plugin' => null,
            'zone' => 'extensions',
            'priority' => 60,
            'visible_if' => 'admin_tools',
            'status' => 'planned',
            'is_placeholder' => true,
            'url' => null,
        ],
    ],
];
