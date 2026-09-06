<?php
declare(strict_types=1);

/**
 * Module Registry — Module Architecture v1
 *
 * Defines every major domain module in the platform with:
 *   key           — unique module identifier
 *   label_key     — locale key for display label
 *   domain        — high-level domain: platform | manufacturing | extension | admin | developer
 *   dashboard_zone— homepage ownership zone: platform | manufacturing | extensions
 *   type          — module type: core | business_app | extension | admin | developer
 *   owner_plugin  — which plugin(s) own this module (string or null for platform-provided)
 *   entry_url     — canonical landing URL for this module (null = no dedicated entry point yet)
 *   visible_if    — visibility rule matching SidebarBuilder::isVisible() rules
 *   order         — sort order within domain
 *   icon          — optional icon identifier (reserved for future use)
 *   status        — active | planned | placeholder
 *   is_placeholder— true if the module has no live route yet
 *   nav_groups    — sidebar group keys that belong to this module
 *   description   — internal documentation string (not shown to users)
 */
return [

    // =========================================================================
    // DOMAIN: platform
    // The reusable ERP platform shell — auth, shell, alerts, roles, admin
    // =========================================================================

    'platform' => [
        'key'           => 'platform',
        'label_key'     => 'module.platform',
        'domain'        => 'platform',
        'dashboard_zone'=> 'platform',
        'type'          => 'core',
        'owner_plugin'  => 'Base',
        'entry_url'     => '/',
        'visible_if'    => 'logged_in',
        'order'         => 10,
        'icon'          => '',
        'status'        => 'active',
        'is_placeholder'=> false,
        'nav_groups'    => [
            'platform_operations_work',
            'platform_operations_review',
            'platform_operations_investigate',
            'platform_operations_manage',
        ],
        'description'   => 'ERP platform shell: my work, alerts, supervisor access, language, session.',
    ],

    // =========================================================================
    // DOMAIN: manufacturing
    // Manufacturing / IPM business application
    // =========================================================================

    'manufacturing_ipm' => [
        'key'           => 'manufacturing_ipm',
        'label_key'     => 'module.manufacturing_ipm',
        'domain'        => 'manufacturing',
        'dashboard_zone'=> 'manufacturing',
        'type'          => 'business_app',
        'owner_plugin'  => 'Products,Machines,PartMachineMap,DailyOrders,PreOrders,ProductionPlans,ProductionEntries,QCPlans,QCEntries,DispatchEntries,Ledger,ProductionQueue',
        'entry_url'     => '/manufacturing/coverage',
        'visible_if'    => 'role_ops',
        'order'         => 20,
        'icon'          => '',
        'status'        => 'active',
        'is_placeholder'=> false,
        'nav_groups'    => [
            'manufacturing_workboards',
            'manufacturing_execution',
            'manufacturing_reference',
        ],
        'description'   => 'Full Manufacturing/IPM app: master data, planning, execution, QC, dispatch, inventory, analysis.',
    ],

    'qr_platform' => [
        'key'           => 'qr_platform',
        'label_key'     => 'module.qr_platform',
        'domain'        => 'platform',
        'dashboard_zone'=> 'platform',
        'type'          => 'core',
        'owner_plugin'  => 'QRCode',
        'entry_url'     => '/qr/stock',
        'visible_if'    => 'logged_in',
        'order'         => 22,
        'icon'          => '',
        'status'        => 'active',
        'is_placeholder'=> false,
        'nav_groups'    => [
            'platform_operations_manage',
        ],
        'description'   => 'Shared QR generation, scan resolution, and scan-driven utility workflows.',
    ],

    // =========================================================================
    // DOMAIN: admin
    // System administration tools
    // =========================================================================

    'admin_tools' => [
        'key'           => 'admin_tools',
        'label_key'     => 'module.admin_tools',
        'domain'        => 'admin',
        'dashboard_zone'=> 'platform',
        'type'          => 'admin',
        'owner_plugin'  => 'AdminTools',
        'entry_url'     => '/admin/apps',
        'visible_if'    => 'admin_tools',
        'order'         => 30,
        'icon'          => '',
        'status'        => 'active',
        'is_placeholder'=> false,
        'nav_groups'    => ['admin_platform_system'],
        'description'   => 'Admin panel: plugin/app management, route inspector, base configuration.',
    ],

    // =========================================================================
    // DOMAIN: developer
    // Developer utilities and diagnostic tools
    // =========================================================================

    'developer_tools' => [
        'key'           => 'developer_tools',
        'label_key'     => 'module.developer_tools',
        'domain'        => 'developer',
        'dashboard_zone'=> 'platform',
        'type'          => 'developer',
        'owner_plugin'  => 'Base',
        'entry_url'     => '/admin/routes',
        'visible_if'    => 'developer_tools',
        'order'         => 40,
        'icon'          => '',
        'status'        => 'active',
        'is_placeholder'=> false,
        'nav_groups'    => ['developer_utilities'],
        'description'   => 'Developer-only utilities and diagnostics.',
    ],

    // =========================================================================
    // DOMAIN: extension (planned / placeholder modules)
    // Future optional apps that can be added to the platform.
    // status: planned | placeholder means no routes exist yet.
    // =========================================================================

    'hr' => [
        'key'           => 'hr',
        'label_key'     => 'module.hr',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 50,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_hr'],
        'description'   => 'Human Resources (planned). Employee records, attendance, payroll.',
    ],

    'accounting' => [
        'key'           => 'accounting',
        'label_key'     => 'module.accounting',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 60,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_accounting'],
        'description'   => 'Accounting (planned). General ledger, invoicing, financial reports.',
    ],

    'crm' => [
        'key'           => 'crm',
        'label_key'     => 'module.crm',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 70,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_crm'],
        'description'   => 'CRM (planned). Customer management, pipeline, quotes.',
    ],

    'procurement' => [
        'key'           => 'procurement',
        'label_key'     => 'module.procurement',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 80,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_procurement'],
        'description'   => 'Procurement (planned). Supplier management, purchase orders, receiving.',
    ],

    'maintenance' => [
        'key'           => 'maintenance',
        'label_key'     => 'module.maintenance',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 90,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_maintenance'],
        'description'   => 'Maintenance (planned). Equipment maintenance schedules and logs.',
    ],

    'reports_bi' => [
        'key'           => 'reports_bi',
        'label_key'     => 'module.reports_bi',
        'domain'        => 'extension',
        'dashboard_zone'=> 'extensions',
        'type'          => 'extension',
        'owner_plugin'  => null,
        'entry_url'     => null,
        'visible_if'    => 'never',
        'order'         => 100,
        'icon'          => '',
        'status'        => 'planned',
        'is_placeholder'=> true,
        'nav_groups'    => ['extensions_reports_bi'],
        'description'   => 'Reports / BI (planned). Cross-module analytics, dashboards, exports.',
    ],

];
