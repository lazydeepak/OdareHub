<?php
declare(strict_types=1);

/**
 * AdminTools navigation contract.
 *
 * All sub-routes of /admin/system-tools/* are registered here with
 * nav_visible: false. This serves two purposes:
 *
 *  1. Prevents SidebarBuilder::autoDiscoverRouteItems() from auto-generating
 *     sidebar entries for these internal pages (they appear in $declaredByUrl,
 *     so the auto-discover loop skips them).
 *
 *  2. buildItem() returns null for nav_visible:false items, so they are never
 *     rendered in any sidebar section — including catch-all sections.
 *
 * The single top-level entry point (/admin/system-tools) is declared in
 * apps/Platform/navigation.php and renders as the "System Tools" sidebar item.
 * Users navigate to sub-tools via the grouped card workspace, not the sidebar.
 */
return [
    'contract' => 'navigation.v1',
    'owner'    => 'admin_tools',
    'items'    => [
        // ── Platform Operations sub-pages ────────────────────────────────
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.apps_redirect',
            'url'        => '/admin/system-tools/apps',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.routes_redirect',
            'url'        => '/admin/system-tools/routes',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.email_settings',
            'url'        => '/admin/system-tools/email-settings',
            'nav_visible' => false,
        ],
        // ── App Governance sub-pages ────────────────────────────────────
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.export_audit',
            'url'        => '/admin/system-tools/export-audit',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.app_management',
            'url'        => '/admin/system-tools/app-management',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.resilience_map',
            'url'        => '/admin/system-tools/resilience',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.app_management_download',
            'url'        => '/admin/system-tools/app-management/download',
            'nav_visible' => false,
        ],
        // ── Runtime Diagnostics sub-pages ─────────────────────────────────
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.entity_runtime',
            'url'        => '/admin/system-tools/entity-runtime',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.my_work_runtime',
            'url'        => '/admin/system-tools/my-work-runtime',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.stage_inspector',
            'url'        => '/admin/system-tools/stage-inspector',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.runtime_report',
            'url'        => '/admin/system-tools/runtime-report',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.module_health',
            'url'        => '/admin/system-tools/module-health',
            'nav_visible' => false,
        ],
        // ── Data Control sub-pages ────────────────────────────────────────
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control',
            'url'        => '/admin/system-tools/data-control',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control_browse',
            'url'        => '/admin/system-tools/data-control/browse',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control_preview_mutation',
            'url'        => '/admin/system-tools/data-control/preview-mutation',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control_approvals',
            'url'        => '/admin/system-tools/data-control/approvals',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control_approval_detail',
            'url'        => '/admin/system-tools/data-control/approval-detail',
            'nav_visible' => false,
        ],
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.data_control_audit_trail',
            'url'        => '/admin/system-tools/data-control/audit-trail',
            'nav_visible' => false,
        ],
        // ── Misc internal admin pages ─────────────────────────────────────
        [
            'source_key' => 'admin.tools.internal',
            'key'        => 'admin_tools.platform_admin_links',
            'url'        => '/admin/platform-admin-links',
            'nav_visible' => false,
        ],
    ],
];
