<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\AclPolicy;
use App\Core\DB;
use App\Services\InviteLifecycleService;

final class UserDashboardAssignmentService
{
    private const ALL_ENABLED_APPS_TOKEN = '__all_enabled__';

    /** @var null|array<string,bool> */
    private static ?array $enabledAppCache = null;
    /** @var null|array<string,array<int,string>> */
    private static ?array $landingOptionsUiCache = null;

    private const APP_USER_DASHBOARD_TYPES = [
        'my_work',
        'operator',
        'production_leader',
        'assembly_leader',
        'qc_leader',
        'dispatch_leader',
    ];

    private const ADMIN_DASHBOARD_TYPES = [
        'platform_admin',
        'app_admin',
    ];

    private const VALID_ACCOUNT_TYPES = [
        'platform_admin',
        'app_admin',
        'app_user',
        'tv_display',
    ];

    private const ACCOUNT_CLASSES = [
        'platform_operations' => 'Platform Operations',
        'platform_security' => 'Platform Security',
        'app_administration' => 'App Administration',
        'app_user' => 'App User',
        'tv_display' => 'TV / Display',
    ];

    private const APP_OPTIONS = ['platform', 'manufacturing', 'accounting', 'hr', 'inventory', 'sales', 'sbaio'];

    /**
     * Canonical platform baseline visibility policy.
     *
     * Principle:
     *   - Operational truth is broadly visible  → View for all authenticated app users
     *   - Operational control is role-gated     → elevated by profile / authority only
     *   - Managerial / admin functions          → default None unless explicitly granted
     *
     * This is the single reusable source consumed by Access, Access2, Visibility,
     * /me composition, and the runtime module-gating layer.
     */
    private const BASELINE_VISIBILITY_POLICY = [
        // Modules that default to at least View for all authenticated app users.
        // Applies to any user whose active assigned apps include the owning app.
        'baseline_view_modules' => [
            'demands',    // Orders
            'coverage',   // Plans / coverage
            'production', // Production status
            'assembly',   // Assembly status
            'qc',         // QC status
            'dispatch',   // Dispatch status
            'materials',  // Parts & Material Stock (view only — no management authority)
        ],

        // Read-only permission tokens granted to all authenticated app_user accounts.
        // View-only — no write, approve, or manage authority included.
        'baseline_view_permissions' => [
            'ops.my_work.view',
            'daily_orders.360.view',
            'products.360.view',
            'materials.stock.view',
            'materials.coverage.view',
        ],

        // Read-only route tokens for key operational truth pages.
        // These tokens support route-level visibility and do not grant write authority.
        'baseline_view_table_access' => [
            'products',
            'daily_orders',
            'production_plans',
            'dispatch_entries',
        ],

        // Modules that remain None unless explicitly elevated by authority or profile.
        'management_restricted_modules' => [
            'admin',  // Governance / Admin tools
            'ops',    // Platform Ops / Control surfaces (procurement, planning control)
        ],

        // Permission tokens that are NEVER part of the baseline.
        // These require explicit profile or authority elevation.
        'management_restricted_permissions' => [
            'materials.orders.manage',
            'materials.planning.manage',
            'materials.stock.adjust',
            'materials.capacity.manage',
            'materials.cost.manage',
            'daily_orders.index.view',
            'ops.cockpit.view',
            'ops.approval_inbox.view',
            'acl.manage',
            'admin.tools.access',
            'materials.admin',
            'materials.master.manage',
        ],

        // Business-facing labels for UI display.
        'surface_labels' => [
            'demands'    => 'Orders',
            'coverage'   => 'Plans',
            'production' => 'Production',
            'assembly'   => 'Assembly',
            'qc'         => 'QC',
            'dispatch'   => 'Dispatch Status',
            'materials'  => 'Parts & Material Stock',
            'admin'      => 'Governance / Admin Tools',
            'ops'        => 'Procurement / Planning Control',
        ],
    ];

    private const APP_ROLE_PACKS = [
        'manufacturing' => [
            'production_operations' => 'Production Worker Execution',
            'assembly_operations' => 'Assembly Worker Execution',
            'qc_operations' => 'QC Worker Execution',
            'dispatch_operations' => 'Dispatch Worker Execution',
            'production_coordination' => 'Production Coordination',
            'assembly_coordination' => 'Assembly Coordination',
            'qc_coordination' => 'QC Coordination',
            'dispatch_coordination' => 'Dispatch Coordination',
            'manufacturing_planning' => 'Manufacturing Planning',
            'readonly_observer' => 'Readonly Observer',
            'manufacturing_reporting' => 'Manufacturing Reporting',
            'manufacturing_approval' => 'Manufacturing Approval',
            'materials_readonly' => 'Material Stock Readonly',
        ],
        'sbaio' => [],
    ];

    private const ACCOUNT_CLASS_BASELINES = [
        'platform_operations' => [
            'assigned_apps' => self::ALL_ENABLED_APPS_TOKEN,
            'permissions' => 'ops.my_work.view,ops.notifications.view,ops.notifications.manage,ops.approval_inbox.view,admin.tools.access,acl.manage',
            'view_access' => 'governance_board,platform_health,security_posture',
            'table_access' => 'approval_inbox,route_registry,acl_overrides',
            'chart_access' => 'ops_pressure,schema_sync,security_alerts',
            'duty_codes' => 'approve_governance,user_assignment,acl_review',
            'module_visibility' => 'admin,ops,production,assembly,qc,dispatch,coverage,demands',
            'task_types' => 'approval,security',
            'notification_surfaces' => 'ops,governance,security',
            'home_widgets' => 'home_governance,home_platform_health,home_security',
        ],
        'platform_security' => [
            'assigned_apps' => self::ALL_ENABLED_APPS_TOKEN,
            'permissions' => 'ops.my_work.view,ops.notifications.view,admin.tools.access,acl.manage',
            'view_access' => 'security_posture,platform_health',
            'table_access' => 'route_registry,acl_overrides',
            'chart_access' => 'security_alerts,schema_sync',
            'duty_codes' => 'acl_review',
            'module_visibility' => 'admin,ops',
            'task_types' => 'security',
            'notification_surfaces' => 'security',
            'home_widgets' => 'home_security,home_platform_health',
        ],
        'app_administration' => [
            'assigned_apps' => '',
            'permissions' => 'ops.my_work.view,ops.notifications.view,ops.notifications.manage,ops.approval_inbox.view',
            'view_access' => 'governance_board',
            'table_access' => 'approval_inbox',
            'chart_access' => 'ops_pressure',
            'duty_codes' => 'user_assignment,approve_governance',
            'module_visibility' => 'ops,production,assembly,qc,dispatch,coverage,demands',
            'task_types' => 'approval',
            'notification_surfaces' => 'ops,governance',
            'home_widgets' => 'home_app_admin,home_approvals',
        ],
        'app_user' => [
            'assigned_apps' => '',
            'permissions' => 'ops.my_work.view',
            'view_access' => '',
            'table_access' => '',
            'chart_access' => '',
            'duty_codes' => '',
            'module_visibility' => '',
            'task_types' => '',
            'notification_surfaces' => 'ops',
            'home_widgets' => 'home_my_work',
        ],
    ];

    private const OPERATIONAL_ROLES = [
        'Platform Operations',
        'Platform Security',
        'App Administration',
        'Production',
        'Assembly',
        'QC',
        'Dispatch',
        'Readonly Observer',
        'Manufacturing Planner',
        'Accounting Followup',
        'HR Processing',
    ];

    private const OPERATIONAL_ROLE_ALIASES = [
        'admin' => 'platformoperations',
        'platform_admin' => 'platformoperations',
        'sysadmin' => 'platformsecurity',
        'systemadmin' => 'platformsecurity',
        'systemadministrator' => 'platformsecurity',
        'accountadmin' => 'appadministration',
        'appadmin' => 'appadministration',
        'machineleader' => 'productionleader',
        'operator' => 'productionworker',
        'productionoperator' => 'productionworker',
        'assemblyoperator' => 'assemblyworker',
        'qcoperator' => 'qcworker',
        'dispatchoperator' => 'dispatchworker',
        'viewer' => 'readonlyobserver',
        'supervisor' => 'manufacturingplanner',
        'manager' => 'manufacturingplanner',
    ];

    private const ROLE_DEFAULTS = [
        'platformoperations' => ['authority_role' => 'platform_admin', 'dashboard_type' => 'platform_admin', 'default_app' => 'platform', 'default_landing_page' => '/', 'role_tier' => 'admin', 'assigned_apps' => self::ALL_ENABLED_APPS_TOKEN, 'access_profiles' => 'platform_administration', 'permissions' => 'admin.tools.access,acl.manage'],
        'platformsecurity' => ['authority_role' => 'platform_admin', 'dashboard_type' => 'platform_admin', 'default_app' => 'platform', 'default_landing_page' => '/', 'role_tier' => 'admin', 'assigned_apps' => self::ALL_ENABLED_APPS_TOKEN, 'access_profiles' => 'platform_administration', 'permissions' => 'admin.tools.access,acl.manage'],
        'appadministration' => ['authority_role' => 'app_admin', 'dashboard_type' => 'app_admin', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'manufacturing_admin', 'permissions' => 'ops.my_work.view'],
        'productionworker' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'production_operations', 'permissions' => 'ops.my_work.view'],
        'assemblyworker' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'assembly_operations', 'permissions' => 'ops.my_work.view'],
        'qcworker' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'qc_operations', 'permissions' => 'ops.my_work.view'],
        'dispatchworker' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'dispatch_operations', 'permissions' => 'ops.my_work.view'],
        'productionleader' => ['authority_role' => 'app_user', 'dashboard_type' => 'production_leader', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'production_coordination', 'permissions' => 'machines.leader.view'],
        'assemblyleader' => ['authority_role' => 'app_user', 'dashboard_type' => 'assembly_leader', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'assembly_coordination', 'permissions' => 'ops.my_work.view'],
        'qcleader' => ['authority_role' => 'app_user', 'dashboard_type' => 'qc_leader', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'qc_coordination', 'permissions' => 'qc_entries.leader.view'],
        'dispatchleader' => ['authority_role' => 'app_user', 'dashboard_type' => 'dispatch_leader', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'dispatch_coordination', 'permissions' => 'dispatch_entries.leader.view'],
        'readonlyobserver' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'readonly_observer', 'permissions' => 'ops.my_work.view'],
        'manufacturingplanner' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'manufacturing', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'manufacturing', 'access_profiles' => 'manufacturing_planning', 'permissions' => 'ops.cockpit.view'],
        'sbaio_operator' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'sbaio', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'sbaio', 'access_profiles' => 'sbaio_operations', 'permissions' => 'ops.my_work.view'],
        'accountingfollowup' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'accounting', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'accounting', 'access_profiles' => 'accounting_approval', 'permissions' => 'ops.my_work.view'],
        'hrprocessing' => ['authority_role' => 'app_user', 'dashboard_type' => 'my_work', 'default_app' => 'hr', 'default_landing_page' => '/', 'role_tier' => 'editor', 'assigned_apps' => 'hr', 'access_profiles' => 'operator_workboard', 'permissions' => 'ops.my_work.view'],
    ];

    // Canonical identity mapping is profile + account type. Permission packs are capabilities only.
    private const PROFILE_ACCOUNT_CANONICAL = [
        'platformoperations|platform_admin' => [
            'dashboard_type' => 'platform_admin',
            'default_app' => 'platform',
            'default_landing_page' => '/',
            'alternate_landing_pages' => ['/admin'],
            'recommended_access_profiles' => ['platform_administration'],
            'baseline_module_visibility' => ['admin', 'ops', 'production', 'assembly', 'qc', 'dispatch', 'coverage', 'demands'],
        ],
        'platformsecurity|platform_admin' => [
            'dashboard_type' => 'platform_admin',
            'default_app' => 'platform',
            'default_landing_page' => '/',
            'alternate_landing_pages' => ['/admin'],
            'recommended_access_profiles' => ['platform_administration'],
            'baseline_module_visibility' => ['admin', 'ops'],
        ],
        'appadministration|app_admin' => [
            'dashboard_type' => 'app_admin',
            'default_app' => '',  // resolved at runtime from user's primary assigned business app
            'default_landing_page' => '/',
            'alternate_landing_pages' => ['/admin'],
            'recommended_access_profiles' => ['manufacturing_admin', 'manufacturing_reporting', 'manufacturing_approval'],
            'baseline_module_visibility' => ['ops', 'production', 'assembly', 'qc', 'dispatch', 'coverage', 'demands', 'admin'],
        ],
        'production|app_user' => [
            'dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['production_operations'],
            'baseline_module_visibility' => ['production'],
        ],
        'assembly|app_user' => [
            'dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['assembly_operations'],
            'baseline_module_visibility' => ['assembly'],
        ],
        'qc|app_user' => [
            'dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['qc_operations'],
            'baseline_module_visibility' => ['qc'],
        ],
        'dispatch|app_user' => [
            'dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['dispatch_operations'],
            'baseline_module_visibility' => ['dispatch'],
        ],
        'readonlyobserver|app_user' => [
            'dashboard_type' => 'operator',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['readonly_observer'],
            'baseline_module_visibility' => ['production', 'coverage'],
        ],
        'manufacturingplanner|app_user' => [
            'dashboard_type' => 'production_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'alternate_landing_pages' => ['/apps/manufacturing/production-dashboard'],
            'recommended_access_profiles' => ['manufacturing_planning'],
            'baseline_module_visibility' => ['production', 'demands', 'coverage'],
        ],
        'sbaio_operator|app_user' => [
            'dashboard_type' => 'my_work',
            'default_app' => 'sbaio',
            'default_landing_page' => '/',
            'alternate_landing_pages' => [],
            'recommended_access_profiles' => ['sbaio_operations'],
            'baseline_module_visibility' => [],
        ],
        'tvdisplay|tv_display' => [
            'dashboard_type' => 'display',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/displays/user',
            'alternate_landing_pages' => ['/displays/device', '/'],
            'recommended_access_profiles' => ['readonly_observer'],
            'baseline_module_visibility' => ['production', 'assembly', 'qc', 'dispatch'],
        ],
    ];

    private const DASHBOARD_OPTIONS_BY_ACCOUNT = [
        'platform_admin' => ['platform_admin'],
        'app_admin' => ['app_admin'],
        'app_user' => ['operator', 'my_work', 'production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'],
        'tv_display' => ['display'],
    ];

    private const LANDING_OPTIONS_BY_DASHBOARD = [
        'platform_admin' => ['/', '/admin'],
        'app_admin' => ['/', '/admin'],
        'production_leader' => ['/', '/apps/manufacturing/production-dashboard'],
        'assembly_leader' => ['/', '/apps/manufacturing/assembly-dashboard'],
        'qc_leader' => ['/', '/apps/manufacturing/qc-dashboard'],
        'dispatch_leader' => ['/', '/apps/manufacturing/dispatch-dashboard'],
        'operator' => ['/'],
        'my_work' => ['/'],
        'display' => ['/displays/user', '/displays/device', '/'],
    ];

    private const DEPENDENT_AUTO_FIELDS = [
        'dashboard_mode',
        'default_app_mode',
        'landing_mode',
        'access_profiles_mode',
        'module_visibility_mode',
    ];

    private const CROSS_PERMISSION_LEVELS = ['view', 'work', 'approve', 'manage'];

    private const SURFACE_CLASS_OPERATIONAL = 'operational';
    private const SURFACE_CLASS_SUPERVISORY = 'supervisory';
    private const SURFACE_CLASS_GOVERNANCE = 'governance_system';

    private const ALLOWED_SURFACE_CLASSES_BY_ACCOUNT = [
        'app_user' => [self::SURFACE_CLASS_OPERATIONAL],
        'app_admin' => [self::SURFACE_CLASS_OPERATIONAL, self::SURFACE_CLASS_SUPERVISORY],
        'platform_admin' => [self::SURFACE_CLASS_OPERATIONAL, self::SURFACE_CLASS_SUPERVISORY, self::SURFACE_CLASS_GOVERNANCE],
    ];

    private const SUPERVISORY_PERMISSION_TOKENS = [
        'ops.approval_inbox.view',
        'ops.cockpit.view',
        'ops.notifications.manage',
    ];

    private const GOVERNANCE_PERMISSION_TOKENS = [
        'ops.audit_explorer.view',
        'acl.manage',
        'admin.tools.access',
        'workflow.dispatch.override',
    ];

    private const SUPERVISORY_DUTY_CODES = [
        'approve_governance',
    ];

    private const GOVERNANCE_DUTY_CODES = [
        'user_assignment',
        'acl_review',
    ];

    private const SUPERVISORY_VIEW_TOKENS = [];
    private const GOVERNANCE_VIEW_TOKENS = [
        'governance_board',
        'platform_health',
        'security_posture',
    ];

    private const SUPERVISORY_TABLE_TOKENS = [
        'approval_inbox',
    ];
    private const GOVERNANCE_TABLE_TOKENS = [
        'route_registry',
        'acl_overrides',
    ];

    private const SUPERVISORY_CHART_TOKENS = [
        'ops_pressure',
    ];
    private const GOVERNANCE_CHART_TOKENS = [
        'schema_sync',
        'security_alerts',
    ];

    private const SUPERVISORY_MODULE_TOKENS = [
        'ops',
    ];
    private const GOVERNANCE_MODULE_TOKENS = [
        'admin',
    ];

    // Cross-functional bundles extend capabilities without changing primary identity.
    private const CROSS_FUNCTIONAL_BUNDLES = [
        'cross_order_access' => [
            'label' => 'Order / Planning',
            'levels' => [
                'view' => [
                    'permissions' => ['ops.my_work.view', 'daily_orders.360.view', 'products.360.view'],
                    'view_access' => ['prod_backlog'],
                    'table_access' => ['production_plans'],
                    'chart_access' => ['plan_vs_actual'],
                    'module_visibility' => ['demands', 'coverage', 'production'],
                    'duty_codes' => ['prod_plan_execute'],
                    'access_profiles' => ['manufacturing_planning'],
                ],
                'work' => [
                    'permissions' => ['workflow.production_plan.submit', 'workflow.assembly_plan.submit'],
                    'table_access' => ['production_queue'],
                ],
                'approve' => [
                    'permissions' => ['ops.approval_inbox.view', 'workflow.production_plan.approve', 'workflow.production_plan.reject', 'workflow.production_plan.reopen', 'workflow.assembly_plan.approve', 'workflow.assembly_plan.reject', 'workflow.assembly_plan.reopen'],
                    'duty_codes' => ['approve_governance'],
                ],
                'manage' => [
                    'permissions' => ['ops.cockpit.view', 'ops.notifications.manage'],
                    'table_access' => ['approval_inbox'],
                    'chart_access' => ['ops_pressure'],
                ],
            ],
        ],
        'cross_production_access' => [
            'label' => 'Production',
            'levels' => [
                'view' => [
                    'permissions' => ['ops.my_work.view', 'machines.leader.view'],
                    'view_access' => ['prod_kpi', 'prod_backlog'],
                    'table_access' => ['production_queue', 'production_plans'],
                    'chart_access' => ['throughput_trend'],
                    'module_visibility' => ['production'],
                    'access_profiles' => ['production_operations'],
                ],
                'work' => [
                    'permissions' => ['workflow.production_plan.submit'],
                    'duty_codes' => ['prod_release'],
                ],
                'approve' => [
                    'permissions' => ['workflow.production_plan.approve', 'workflow.production_plan.reject', 'workflow.production_plan.reopen', 'workflow.production_plan.finalize'],
                ],
                'manage' => [
                    'permissions' => ['workflow.production_plan.cancel', 'workflow.production_plan.hold', 'workflow.production_plan.resume'],
                    'chart_access' => ['plan_vs_actual'],
                ],
            ],
        ],
        'cross_assembly_access' => [
            'label' => 'Assembly',
            'levels' => [
                'view' => [
                    'permissions' => ['ops.my_work.view'],
                    'view_access' => ['assembly_queue_view'],
                    'table_access' => ['assembly_queue', 'assembly_plans'],
                    'chart_access' => ['assembly_output'],
                    'module_visibility' => ['assembly'],
                    'access_profiles' => ['assembly_operations'],
                ],
                'work' => [
                    'permissions' => ['workflow.assembly_plan.submit'],
                    'duty_codes' => ['assembly_coordination'],
                ],
                'approve' => [
                    'permissions' => ['workflow.assembly_plan.approve', 'workflow.assembly_plan.reject', 'workflow.assembly_plan.reopen', 'workflow.assembly_plan.finalize'],
                ],
                'manage' => [
                    'permissions' => ['workflow.assembly_plan.cancel', 'workflow.assembly_plan.hold', 'workflow.assembly_plan.resume'],
                ],
            ],
        ],
        'cross_qc_access' => [
            'label' => 'QC',
            'levels' => [
                'view' => [
                    'permissions' => ['ops.my_work.view', 'qc_entries.leader.view'],
                    'view_access' => ['qc_release_board'],
                    'table_access' => ['qc_entries', 'qc_plans'],
                    'chart_access' => ['qc_pass_rate'],
                    'module_visibility' => ['qc'],
                    'access_profiles' => ['qc_operations'],
                ],
                'work' => [
                    'permissions' => ['workflow.qc_entry.submit', 'workflow.qc_entry.reopen'],
                    'duty_codes' => ['qc_release'],
                ],
                'approve' => [
                    'permissions' => ['workflow.qc_entry.approve', 'workflow.qc_entry.reject', 'workflow.qc_entry.finalize'],
                ],
                'manage' => [
                    'permissions' => ['workflow.qc_entry.cancel'],
                ],
            ],
        ],
        'cross_dispatch_access' => [
            'label' => 'Dispatch',
            'levels' => [
                'view' => [
                    'permissions' => ['ops.my_work.view', 'dispatch_entries.leader.view'],
                    'view_access' => ['dispatch_board'],
                    'table_access' => ['dispatch_entries'],
                    'chart_access' => ['dispatch_volume'],
                    'module_visibility' => ['dispatch'],
                    'access_profiles' => ['dispatch_operations', 'dispatch_preparation'],
                ],
                'work' => [
                    'permissions' => ['dispatch_entries.quick_status', 'dispatch_entries.transition', 'workflow.dispatch_entry.submit', 'workflow.dispatch_entry.reopen'],
                    'duty_codes' => ['dispatch_release'],
                ],
                'approve' => [
                    'permissions' => ['workflow.dispatch_entry.approve', 'workflow.dispatch_entry.reject', 'workflow.dispatch_entry.handoff', 'workflow.dispatch_entry.finalize'],
                ],
                'manage' => [
                    'permissions' => ['workflow.dispatch_entry.cancel', 'workflow.dispatch_entry.hold', 'workflow.dispatch_entry.resume'],
                ],
            ],
        ],
    ];

    private const CROSS_FUNCTIONAL_PRESETS = [
        'production_qc_view' => ['label' => 'Production + QC View', 'grants' => ['cross_qc_access:view']],
        'production_dispatch_work' => ['label' => 'Production + Dispatch Work', 'grants' => ['cross_dispatch_access:work']],
        'assembly_qc_work' => ['label' => 'Assembly + QC Work', 'grants' => ['cross_qc_access:work']],
        'qc_production_view' => ['label' => 'QC + Production View', 'grants' => ['cross_production_access:view']],
        'dispatch_order_view' => ['label' => 'Dispatch + Order View', 'grants' => ['cross_order_access:view']],
        'planner_full_view' => ['label' => 'Planner + Production/QC/Dispatch View', 'grants' => ['cross_production_access:view', 'cross_qc_access:view', 'cross_dispatch_access:view']],
    ];

    private const ACCESS_PROFILE_REGISTRY = [
        'manufacturing_planning' => [
            'label' => 'Manufacturing Planning',
            'description' => 'Demand, coverage, and production planning operations.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'production_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'products.360.view', 'daily_orders.360.view', 'workflow.production_plan.submit', 'workflow.assembly_plan.submit'],
            'view_access' => ['prod_kpi', 'prod_backlog'],
            'table_access' => ['production_queue', 'production_plans'],
            'chart_access' => ['throughput_trend', 'plan_vs_actual'],
            'duty_codes' => ['prod_plan_execute'],
            'module_visibility' => ['production', 'demands', 'coverage'],
            'scope_hints' => ['task_types' => ['production', 'queue_release']],
        ],
        'production_operations' => [
            'label' => 'Production Worker Execution',
            'description' => 'Worker-first production queue execution without leader-only controls.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view'],
            'view_access' => [],
            'table_access' => ['production_queue'],
            'chart_access' => ['throughput_trend'],
            'duty_codes' => ['prod_plan_execute'],
            'module_visibility' => ['production'],
            'scope_hints' => ['task_types' => ['production']],
        ],
        'production_coordination' => [
            'label' => 'Production Coordination',
            'description' => 'Leader coordination for production queues, machine oversight, and stage flow.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'production_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'machines.leader.view', 'workflow.production_plan.submit'],
            'view_access' => ['prod_kpi', 'prod_backlog'],
            'table_access' => ['production_queue', 'production_plans'],
            'chart_access' => ['throughput_trend', 'plan_vs_actual'],
            'duty_codes' => ['prod_plan_execute', 'prod_release'],
            'module_visibility' => ['production', 'demands', 'coverage'],
            'scope_hints' => ['task_types' => ['production', 'queue_release']],
        ],
        'assembly_operations' => [
            'label' => 'Assembly Worker Execution',
            'description' => 'Assembly work execution without coordinator-only exception handling.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'workflow.assembly_plan.submit'],
            'view_access' => ['assembly_queue_view'],
            'table_access' => ['assembly_queue'],
            'chart_access' => [],
            'duty_codes' => [],
            'module_visibility' => ['assembly'],
            'scope_hints' => ['task_types' => ['assembly']],
        ],
        'assembly_coordination' => [
            'label' => 'Assembly Coordination',
            'description' => 'Assembly queue coordination, planning visibility, and handoff oversight.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'assembly_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'workflow.assembly_plan.submit'],
            'view_access' => ['assembly_queue_view'],
            'table_access' => ['assembly_queue', 'assembly_plans'],
            'chart_access' => ['assembly_output'],
            'duty_codes' => ['assembly_coordination'],
            'module_visibility' => ['assembly', 'demands', 'coverage'],
            'scope_hints' => ['task_types' => ['assembly']],
        ],
        'qc_operations' => [
            'label' => 'QC Worker Execution',
            'description' => 'Worker-first QC processing without leader approval or reopen authority.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'workflow.qc_entry.submit'],
            'view_access' => [],
            'table_access' => ['qc_entries'],
            'chart_access' => [],
            'duty_codes' => [],
            'module_visibility' => ['qc'],
            'scope_hints' => ['task_types' => ['qc_check']],
        ],
        'qc_coordination' => [
            'label' => 'QC Coordination',
            'description' => 'Leader QC release flow with queue oversight and quality approval actions.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'qc_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'qc_entries.leader.view', 'workflow.qc_entry.submit', 'workflow.qc_entry.approve', 'workflow.qc_entry.reject', 'workflow.qc_entry.reopen'],
            'view_access' => ['qc_release_board'],
            'table_access' => ['qc_entries', 'qc_plans'],
            'chart_access' => ['qc_pass_rate'],
            'duty_codes' => ['qc_release'],
            'module_visibility' => ['qc', 'coverage'],
            'scope_hints' => ['task_types' => ['qc_check']],
        ],
        'dispatch_operations' => [
            'label' => 'Dispatch Worker Execution',
            'description' => 'Worker dispatch execution with posting and status updates only.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'dispatch_entries.quick_status', 'dispatch_entries.transition', 'workflow.dispatch_entry.submit'],
            'view_access' => [],
            'table_access' => ['dispatch_entries'],
            'chart_access' => [],
            'duty_codes' => [],
            'module_visibility' => ['dispatch'],
            'scope_hints' => ['task_types' => ['dispatch', 'shipment']],
        ],
        'dispatch_coordination' => [
            'label' => 'Dispatch Coordination',
            'description' => 'Leader dispatch control with approvals, handoff, and outbound exception management.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'dispatch_leader',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'dispatch_entries.leader.view', 'dispatch_entries.quick_status', 'dispatch_entries.transition', 'workflow.dispatch_entry.submit', 'workflow.dispatch_entry.approve', 'workflow.dispatch_entry.reject', 'workflow.dispatch_entry.reopen', 'workflow.dispatch_entry.hold', 'workflow.dispatch_entry.resume', 'workflow.dispatch_entry.finalize', 'workflow.dispatch_entry.cancel', 'workflow.dispatch_entry.handoff'],
            'view_access' => ['dispatch_board'],
            'table_access' => ['dispatch_entries'],
            'chart_access' => ['dispatch_volume'],
            'duty_codes' => ['dispatch_release'],
            'module_visibility' => ['dispatch', 'coverage'],
            'scope_hints' => ['task_types' => ['dispatch', 'shipment']],
        ],
        'dispatch_preparation' => [
            'label' => 'Dispatch Preparation',
            'description' => 'Packaging and dispatch pre-check workflow.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view'],
            'view_access' => ['dispatch_board'],
            'table_access' => ['dispatch_entries'],
            'chart_access' => ['dispatch_volume'],
            'duty_codes' => ['dispatch_release'],
            'module_visibility' => ['dispatch'],
            'scope_hints' => ['task_types' => ['dispatch']],
        ],
        'readonly_observer' => [
            'label' => 'Readonly Observer',
            'description' => 'Read-only visibility into manufacturing operations.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view'],
            'view_access' => ['prod_backlog', 'dispatch_board'],
            'table_access' => ['production_queue', 'qc_entries', 'dispatch_entries'],
            'chart_access' => ['throughput_trend', 'qc_pass_rate', 'dispatch_volume'],
            'duty_codes' => ['read_only'],
            'module_visibility' => ['production', 'qc', 'dispatch', 'coverage'],
            'scope_hints' => [],
        ],
        'materials_readonly' => [
            'label' => 'Material Stock Readonly',
            'description' => 'Readonly material stock and coverage visibility without planning, orders, capacity, or cost access.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'materials.stock.view', 'materials.coverage.view'],
            'view_access' => [],
            'table_access' => [],
            'chart_access' => [],
            'duty_codes' => ['read_only'],
            'module_visibility' => ['materials'],
            'scope_hints' => [],
        ],
        'manufacturing_admin' => [
            'label' => 'Manufacturing Admin',
            'description' => 'App-level manufacturing administration and control.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'app_admin',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/admin',
            'permissions' => ['ops.my_work.view', 'ops.notifications.view', 'ops.notifications.manage', 'ops.approval_inbox.view', 'daily_orders.index.view', 'materials.admin', 'materials.master.manage', 'materials.planning.manage', 'materials.orders.manage', 'materials.stock.adjust', 'materials.capacity.manage', 'materials.cost.manage', 'materials.stock.view', 'materials.coverage.view'],
            'view_access' => ['governance_board', 'platform_health'],
            'table_access' => ['approval_inbox', 'route_registry'],
            'chart_access' => ['ops_pressure'],
            'duty_codes' => ['approve_governance'],
            'module_visibility' => ['production', 'assembly', 'qc', 'dispatch', 'demands', 'coverage', 'materials', 'ops'],
            'scope_hints' => [],
        ],
        'manufacturing_reporting' => [
            'label' => 'Manufacturing Reporting',
            'description' => 'KPI/reporting visibility across manufacturing stages.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'products.360.view', 'daily_orders.360.view', 'daily_orders.index.view'],
            'view_access' => ['prod_kpi', 'dispatch_board', 'qc_release_board'],
            'table_access' => ['production_plans', 'qc_entries', 'dispatch_entries'],
            'chart_access' => ['throughput_trend', 'qc_pass_rate', 'dispatch_volume'],
            'duty_codes' => [],
            'module_visibility' => ['production', 'qc', 'dispatch', 'coverage', 'demands'],
            'scope_hints' => [],
        ],
        'manufacturing_approval' => [
            'label' => 'Manufacturing Approval',
            'description' => 'Approval inbox and governance workflow authority.',
            'app' => 'manufacturing',
            'default_dashboard_type' => 'my_work',
            'default_app' => 'manufacturing',
            'default_landing_page' => '/',
            'permissions' => ['ops.my_work.view', 'ops.approval_inbox.view', 'workflow.production_plan.approve', 'workflow.production_plan.reject', 'workflow.production_plan.reopen', 'workflow.production_plan.hold', 'workflow.production_plan.resume', 'workflow.production_plan.finalize', 'workflow.production_plan.cancel', 'workflow.assembly_plan.approve', 'workflow.assembly_plan.reject', 'workflow.assembly_plan.reopen', 'workflow.assembly_plan.hold', 'workflow.assembly_plan.resume', 'workflow.assembly_plan.finalize', 'workflow.assembly_plan.cancel', 'workflow.qc_entry.approve', 'workflow.qc_entry.reject', 'workflow.qc_entry.reopen', 'workflow.qc_entry.finalize', 'workflow.qc_entry.cancel', 'workflow.dispatch_entry.approve', 'workflow.dispatch_entry.reject', 'workflow.dispatch_entry.reopen', 'workflow.dispatch_entry.hold', 'workflow.dispatch_entry.resume', 'workflow.dispatch_entry.finalize', 'workflow.dispatch_entry.cancel', 'workflow.dispatch_entry.handoff'],
            'view_access' => ['governance_board'],
            'table_access' => ['approval_inbox'],
            'chart_access' => ['ops_pressure'],
            'duty_codes' => ['approve_governance'],
            'module_visibility' => ['ops', 'production', 'qc', 'dispatch'],
            'scope_hints' => [],
        ],
    ];

    private const MODULE_SYNONYMS = [
        'sbaio_staff' => 'sbaio_staff',
        'staff' => 'sbaio_staff',
        'sbaio_attendance' => 'sbaio_attendance',
        'attendance' => 'sbaio_attendance',
        'sbaio_schedules' => 'sbaio_schedules',
        'schedules' => 'sbaio_schedules',
        'sbaio_leave' => 'sbaio_leave',
        'leave' => 'sbaio_leave',
        'sbaio_timecards' => 'sbaio_timecards',
        'timecards' => 'sbaio_timecards',
        'sbaio_payroll' => 'sbaio_payroll',
        'payroll' => 'sbaio_payroll',
        'sbaio_customers' => 'sbaio_customers',
        'customers' => 'sbaio_customers',
        'sbaio_tasks' => 'sbaio_tasks',
        'tasks' => 'sbaio_tasks',
        'sbaio_sales' => 'sbaio_sales',
        'sales' => 'sbaio_sales',
        'sbaio_expenses' => 'sbaio_expenses',
        'expenses' => 'sbaio_expenses',
        'sbaio_notices' => 'sbaio_notices',
        'notices' => 'sbaio_notices',
        'production' => 'production',
        'mfg.production' => 'production',
        'production_plan' => 'production',
        'production_plans' => 'production',
        'qc' => 'qc',
        'mfg.qc' => 'qc',
        'qc_entry' => 'qc',
        'qc_entries' => 'qc',
        'assembly' => 'assembly',
        'mfg.assembly' => 'assembly',
        'assembly_plan' => 'assembly',
        'assembly_plans' => 'assembly',
        'dispatch' => 'dispatch',
        'mfg.dispatch' => 'dispatch',
        'dispatch_entry' => 'dispatch',
        'dispatch_entries' => 'dispatch',
        'demands' => 'demands',
        'coverage' => 'coverage',
        'materials' => 'materials',
        'admin' => 'admin',
        'ops' => 'ops',
    ];

    private const PATH_MODULE_RULES = [
        ['prefix' => '/apps/sbaio/staff', 'module' => 'sbaio_staff'],
        ['prefix' => '/apps/sbaio/attendance', 'module' => 'sbaio_attendance'],
        ['prefix' => '/apps/sbaio/schedules', 'module' => 'sbaio_schedules'],
        ['prefix' => '/apps/sbaio/leave', 'module' => 'sbaio_leave'],
        ['prefix' => '/apps/sbaio/timecards', 'module' => 'sbaio_timecards'],
        ['prefix' => '/apps/sbaio/payroll', 'module' => 'sbaio_payroll'],
        ['prefix' => '/apps/sbaio/customers', 'module' => 'sbaio_customers'],
        ['prefix' => '/apps/sbaio/tasks', 'module' => 'sbaio_tasks'],
        ['prefix' => '/apps/sbaio/sales', 'module' => 'sbaio_sales'],
        ['prefix' => '/apps/sbaio/expenses', 'module' => 'sbaio_expenses'],
        ['prefix' => '/apps/sbaio/notices', 'module' => 'sbaio_notices'],
        ['prefix' => '/apps/manufacturing/production-plans', 'module' => 'production'],
        ['prefix' => '/apps/manufacturing/daily-orders', 'module' => 'demands'],
        ['prefix' => '/apps/manufacturing/products', 'module' => 'demands'],
        ['prefix' => '/apps/manufacturing/production-dashboard', 'module' => 'production'],
        ['prefix' => '/apps/manufacturing/assembly-dashboard', 'module' => 'assembly'],
        ['prefix' => '/apps/manufacturing/qc-dashboard', 'module' => 'qc'],
        ['prefix' => '/apps/manufacturing/dispatch-dashboard', 'module' => 'dispatch'],
        ['prefix' => '/production', 'module' => 'production'],
        ['prefix' => '/manufacturing/production-queue', 'module' => 'production'],
        ['prefix' => '/production-plans', 'module' => 'production'],
        ['prefix' => '/daily-orders', 'module' => 'demands'],
        ['prefix' => '/apps/manufacturing/materials', 'module' => 'materials'],
        ['prefix' => '/dispatch-entries', 'module' => 'dispatch'],
        ['prefix' => '/qc-entries', 'module' => 'qc'],
        ['prefix' => '/qc-plans', 'module' => 'qc'],
        ['prefix' => '/ops/access-control', 'module' => 'ops'],
        ['prefix' => '/ops/user-dashboard', 'module' => 'ops'],
        ['prefix' => '/ops/dashboard-assignments', 'module' => 'ops'],
        ['prefix' => '/ops/navigation-tree', 'module' => 'ops'],
        ['prefix' => '/ops/organization', 'module' => 'ops'],
        ['prefix' => '/ops/audit-log', 'module' => 'ops'],
    ];

    private const PATH_TOKEN_RULES = [
        ['prefix' => '/ops/access-control', 'view' => 'user_control_board', 'table' => 'user_dashboard_assignments'],
        ['prefix' => '/ops/user-dashboard', 'view' => 'user_control_board', 'table' => 'user_dashboard_assignments'],
        ['prefix' => '/ops/dashboard-assignments', 'view' => 'user_control_board', 'table' => 'user_dashboard_assignments'],
        ['prefix' => '/ops/navigation-tree', 'view' => 'platform_health', 'table' => 'route_registry'],
        ['prefix' => '/apps/manufacturing/production-dashboard', 'view' => 'prod_kpi', 'table' => 'production_queue', 'chart' => 'throughput_trend'],
        ['prefix' => '/apps/manufacturing/assembly-dashboard', 'view' => 'assembly_queue_view', 'table' => 'assembly_queue', 'chart' => 'assembly_output'],
        ['prefix' => '/apps/manufacturing/qc-dashboard', 'view' => 'qc_release_board', 'table' => 'qc_entries', 'chart' => 'qc_pass_rate'],
        ['prefix' => '/apps/manufacturing/dispatch-dashboard', 'view' => 'dispatch_board', 'table' => 'dispatch_entries', 'chart' => 'dispatch_volume'],
        ['prefix' => '/manufacturing/production-queue', 'table' => 'production_queue'],
        ['prefix' => '/apps/manufacturing/production-plans', 'table' => 'production_plans'],
        ['prefix' => '/production-plans', 'table' => 'production_plans'],
        ['prefix' => '/apps/manufacturing/daily-orders', 'table' => 'daily_orders'],
        ['prefix' => '/apps/manufacturing/products', 'table' => 'products'],
        ['prefix' => '/qc-entries', 'table' => 'qc_entries'],
        ['prefix' => '/qc-plans', 'table' => 'qc_plans'],
        ['prefix' => '/dispatch-entries', 'table' => 'dispatch_entries'],
    ];

    private const SOURCE_PRIORITY = [
        'account_class' => 1,
        'assigned_app' => 2,
        'role_pack' => 3,
        'cross_bundle' => 4,
        'advanced_override' => 5,
        'manual' => 6,
    ];

    private const ME_DASHBOARD_BLOCKS = [
        'admin_dashboard_panels' => 'admin_dashboard_panels',
        'top_navigation_module_launcher' => 'top_navigation_module_launcher',
        'global_controls' => 'global_controls',
        'search_alerts' => 'search_alerts',
        'operational_summary' => 'operational_summary',
        'primary_work_widgets' => 'primary_work_widgets',
        'monitoring_widgets' => 'monitoring_widgets',
        'detailed_work_tables' => 'detailed_work_tables',
        'platform_admin_tools' => 'platform_admin_tools',
        'plugin_dashboards_charts' => 'plugin_dashboards_charts',
    ];

    private const ME_PLUGIN_CARDS = [
        'approval_inbox' => 'approval_inbox',
        'notifications' => 'notifications',
        'cross_role_handoff' => 'cross_role_handoff',
        'platform_setup' => 'platform_setup',
        'access_control_board' => 'access_control_board',
        'user_control_board' => 'user_control_board',
        'user_dashboard' => 'user_dashboard',
        'admin_tools' => 'admin_tools',
        'route_diagnostics' => 'route_diagnostics',
    ];

    private const PLATFORM_ONLY_ME_PLUGIN_CARDS = [
        'platform_setup',
        'access_control_board',
        'user_control_board',
        'user_dashboard',
        'admin_tools',
        'route_diagnostics',
    ];

    private const EXPERIENCE_LAYOUT_NONE = '__none';

    private const APP_ADMIN_ONLY_ACCESS_PROFILES = [
        'manufacturing_admin',
        'manufacturing_approval',
        'manufacturing_suite_admin',
    ];

    private const LEADER_ACCESS_PROFILES = [
        'manufacturing_planning',
        'production_coordination',
        'assembly_coordination',
        'qc_coordination',
        'dispatch_coordination',
        'manufacturing_planning_control',
    ];

    private const APP_ADMIN_ONLY_PERMISSION_TOKENS = [
        'ops.notifications.manage',
        'ops.audit_explorer.view',
        'daily_orders.index.view',
        'materials.admin',
        'materials.master.manage',
        'materials.planning.manage',
        'materials.orders.manage',
        'materials.stock.adjust',
        'materials.capacity.manage',
        'materials.cost.manage',
    ];

    private const LEADER_ONLY_PERMISSION_TOKENS = [
        'ops.handoff.assign_owner',
        'ops.handoff.escalate',
        'ops.cockpit.view',
        'ops.approval_inbox.view',
        'machines.leader.view',
        'qc_entries.leader.view',
        'dispatch_entries.leader.view',
        'workflow.production_plan.submit',
        'workflow.production_plan.approve',
        'workflow.production_plan.reject',
        'workflow.production_plan.reopen',
        'workflow.production_plan.hold',
        'workflow.production_plan.resume',
        'workflow.production_plan.finalize',
        'workflow.production_plan.cancel',
        'workflow.production_plan.override_lock',
        'workflow.assembly_plan.approve',
        'workflow.assembly_plan.reject',
        'workflow.assembly_plan.reopen',
        'workflow.assembly_plan.hold',
        'workflow.assembly_plan.resume',
        'workflow.assembly_plan.finalize',
        'workflow.assembly_plan.cancel',
        'workflow.qc_entry.approve',
        'workflow.qc_entry.reject',
        'workflow.qc_entry.reopen',
        'workflow.qc_entry.finalize',
        'workflow.qc_entry.cancel',
        'workflow.qc_entry.override_lock',
        'workflow.dispatch_entry.approve',
        'workflow.dispatch_entry.reject',
        'workflow.dispatch_entry.reopen',
        'workflow.dispatch_entry.hold',
        'workflow.dispatch_entry.resume',
        'workflow.dispatch_entry.finalize',
        'workflow.dispatch_entry.cancel',
        'workflow.dispatch_entry.handoff',
        'workflow.dispatch_entry.override_lock',
    ];

    private const APP_USER_ASSIGNED_APP_BASELINE_PERMISSIONS = [
        'ops.my_work.view',
        'ops.handoff.view',
        'ops.notifications.view',
    ];

    /**
     * First-class modeled surfaces used to seed the Access Control Board while
     * the full registry is still transitioning away from legacy token overlap.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function surfaceDefinitionCatalog(): array
    {
        return [
            'manufacturing_portal' => [
                'label' => 'Manufacturing Workspace',
                'token_type' => 'plugin_card',
                'token_key' => 'manufacturing_portal',
                'view_suite' => 'cards',
                'interaction_profiles' => ['worker', 'leader', 'admin', 'read_only'],
                'access_authorities' => ['worker', 'leader', 'app_admin', 'observer'],
                'governance_scope' => 'app',
                'permission_profiles' => ['production_operations', 'assembly_operations', 'qc_operations', 'dispatch_operations', 'production_coordination', 'assembly_coordination', 'qc_coordination', 'dispatch_coordination', 'readonly_observer', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 10,
            ],
            'production_queue' => [
                'label' => 'Production Queue',
                'token_type' => 'table',
                'token_key' => 'production_queue',
                'view_suite' => 'table',
                'interaction_profiles' => ['worker', 'leader'],
                'access_authorities' => ['worker', 'leader', 'app_admin'],
                'governance_scope' => 'module',
                'permission_profiles' => ['production_operations', 'production_coordination', 'manufacturing_planning', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 20,
            ],
            'qc_queue' => [
                'label' => 'QC Queue',
                'token_type' => 'table',
                'token_key' => 'qc_entries',
                'view_suite' => 'table',
                'interaction_profiles' => ['worker', 'leader'],
                'access_authorities' => ['worker', 'leader', 'app_admin'],
                'governance_scope' => 'module',
                'permission_profiles' => ['qc_operations', 'qc_coordination', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 30,
            ],
            'dispatch_ops' => [
                'label' => 'Dispatch Ops',
                'token_type' => 'table',
                'token_key' => 'dispatch_entries',
                'view_suite' => 'table',
                'interaction_profiles' => ['worker', 'leader'],
                'access_authorities' => ['worker', 'leader', 'app_admin'],
                'governance_scope' => 'module',
                'permission_profiles' => ['dispatch_operations', 'dispatch_coordination', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 40,
            ],
            'stage_board' => [
                'label' => 'Stage Board',
                'token_type' => 'view',
                'token_key' => 'stage_board',
                'view_suite' => 'board',
                'interaction_profiles' => ['leader', 'admin', 'display'],
                'access_authorities' => ['leader', 'app_admin', 'observer'],
                'governance_scope' => 'shared',
                'permission_profiles' => ['manufacturing_planning', 'production_coordination', 'assembly_coordination', 'qc_coordination', 'dispatch_coordination', 'readonly_observer', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 45,
            ],
            'coverage_dashboard' => [
                'label' => 'Coverage Analytics',
                'token_type' => 'plugin_card',
                'token_key' => 'coverage',
                'view_suite' => 'board',
                'interaction_profiles' => ['leader', 'read_only'],
                'access_authorities' => ['leader', 'observer', 'app_admin'],
                'governance_scope' => 'module',
                'permission_profiles' => ['manufacturing_planning', 'readonly_observer', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 50,
            ],
            'material_stock' => [
                'label' => 'Material Stock',
                'token_type' => 'module',
                'token_key' => 'materials',
                'view_suite' => 'table',
                'interaction_profiles' => ['read_only', 'admin'],
                'access_authorities' => ['observer', 'app_admin'],
                'governance_scope' => 'module',
                'permission_profiles' => ['materials_readonly', 'manufacturing_material_visibility', 'manufacturing_materials_readonly_module', 'manufacturing_admin'],
                'primary' => true,
                'priority' => 60,
            ],
        ];
    }

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        // Backward-compatible user attributes.
        self::addUserColumnIfMissing('role_tier', "ALTER TABLE users ADD COLUMN role_tier VARCHAR(20) NULL AFTER role");
        self::addUserColumnIfMissing('authority_role', "ALTER TABLE users ADD COLUMN authority_role VARCHAR(40) NULL AFTER role_tier");
        self::addUserColumnIfMissing('display_name', "ALTER TABLE users ADD COLUMN display_name VARCHAR(190) NULL AFTER email");
        self::addUserColumnIfMissing('username', "ALTER TABLE users ADD COLUMN username VARCHAR(120) NULL AFTER display_name");
        self::addUserColumnIfMissing('department', "ALTER TABLE users ADD COLUMN department VARCHAR(120) NULL AFTER username");
        self::addUserColumnIfMissing('account_status', "ALTER TABLE users ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER authority_role");
        self::addUserColumnIfMissing('verification_status', "ALTER TABLE users ADD COLUMN verification_status VARCHAR(40) NOT NULL DEFAULT 'ready' AFTER account_status");
        self::addUserColumnIfMissing('security_status', "ALTER TABLE users ADD COLUMN security_status VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER verification_status");

        InviteLifecycleService::ensureSchema();

        DB::query(
            "CREATE TABLE IF NOT EXISTS user_dashboard_assignments (
                user_id INT PRIMARY KEY,
                dashboard_type VARCHAR(80) NOT NULL,
                authority_role VARCHAR(40) NULL,
                default_app VARCHAR(120) NULL,
                default_landing_page VARCHAR(255) NULL,
                assigned_apps TEXT NULL,
                access_profiles TEXT NULL,
                permissions TEXT NULL,
                view_access TEXT NULL,
                table_access TEXT NULL,
                chart_access TEXT NULL,
                module_visibility TEXT NULL,
                updated_by VARCHAR(190) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user_dashboard_type (dashboard_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        self::addAssignmentColumnIfMissing('authority_role', "ALTER TABLE user_dashboard_assignments ADD COLUMN authority_role VARCHAR(40) NULL AFTER dashboard_type");
        self::addAssignmentColumnIfMissing('assigned_apps', "ALTER TABLE user_dashboard_assignments ADD COLUMN assigned_apps TEXT NULL AFTER default_landing_page");
        self::addAssignmentColumnIfMissing('access_profiles', "ALTER TABLE user_dashboard_assignments ADD COLUMN access_profiles TEXT NULL AFTER assigned_apps");
        self::addAssignmentColumnIfMissing('permissions', "ALTER TABLE user_dashboard_assignments ADD COLUMN permissions TEXT NULL AFTER access_profiles");
        self::addAssignmentColumnIfMissing('view_access', "ALTER TABLE user_dashboard_assignments ADD COLUMN view_access TEXT NULL AFTER permissions");
        self::addAssignmentColumnIfMissing('table_access', "ALTER TABLE user_dashboard_assignments ADD COLUMN table_access TEXT NULL AFTER view_access");
        self::addAssignmentColumnIfMissing('chart_access', "ALTER TABLE user_dashboard_assignments ADD COLUMN chart_access TEXT NULL AFTER table_access");
        self::addAssignmentColumnIfMissing('module_visibility', "ALTER TABLE user_dashboard_assignments ADD COLUMN module_visibility TEXT NULL AFTER chart_access");
        self::addAssignmentColumnIfMissing('dashboard_mode', "ALTER TABLE user_dashboard_assignments ADD COLUMN dashboard_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER chart_access");
        self::addAssignmentColumnIfMissing('default_app_mode', "ALTER TABLE user_dashboard_assignments ADD COLUMN default_app_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER dashboard_mode");
        self::addAssignmentColumnIfMissing('landing_mode', "ALTER TABLE user_dashboard_assignments ADD COLUMN landing_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER default_app_mode");
        self::addAssignmentColumnIfMissing('access_profiles_mode', "ALTER TABLE user_dashboard_assignments ADD COLUMN access_profiles_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER landing_mode");
        self::addAssignmentColumnIfMissing('module_visibility_mode', "ALTER TABLE user_dashboard_assignments ADD COLUMN module_visibility_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER access_profiles_mode");
        self::addAssignmentColumnIfMissing('account_class', "ALTER TABLE user_dashboard_assignments ADD COLUMN account_class VARCHAR(40) NULL AFTER module_visibility_mode");
        self::addAssignmentColumnIfMissing('selected_role_packs', "ALTER TABLE user_dashboard_assignments ADD COLUMN selected_role_packs TEXT NULL AFTER account_class");
        self::addAssignmentColumnIfMissing('cross_functional_access', "ALTER TABLE user_dashboard_assignments ADD COLUMN cross_functional_access TEXT NULL AFTER module_visibility_mode");
        self::addAssignmentColumnIfMissing('me_dashboard_blocks', "ALTER TABLE user_dashboard_assignments ADD COLUMN me_dashboard_blocks TEXT NULL AFTER cross_functional_access");
        self::addAssignmentColumnIfMissing('me_plugin_cards', "ALTER TABLE user_dashboard_assignments ADD COLUMN me_plugin_cards TEXT NULL AFTER me_dashboard_blocks");
        self::addAssignmentColumnIfMissing('display_surfaces', "ALTER TABLE user_dashboard_assignments ADD COLUMN display_surfaces TEXT NULL AFTER me_plugin_cards");
        self::addAssignmentColumnIfMissing('operator_views', "ALTER TABLE user_dashboard_assignments ADD COLUMN operator_views TEXT NULL AFTER display_surfaces");
        self::addAssignmentColumnIfMissing('workspace_profile_key', "ALTER TABLE user_dashboard_assignments ADD COLUMN workspace_profile_key VARCHAR(100) NULL AFTER me_plugin_cards");

        DB::query(
            "UPDATE user_dashboard_assignments uda
                LEFT JOIN users u ON u.id = uda.user_id
               SET uda.authority_role = COALESCE(NULLIF(TRIM(uda.authority_role), ''), NULLIF(TRIM(u.authority_role), ''), 'app_user')
             WHERE COALESCE(NULLIF(TRIM(uda.authority_role), ''), '') = ''",
            []
        );
        DB::query(
            "UPDATE user_dashboard_assignments uda
                LEFT JOIN (
                    SELECT user_id, GROUP_CONCAT(module_key ORDER BY module_key SEPARATOR ',') AS module_visibility
                      FROM user_module_visibility
                     WHERE is_enabled = 1
                     GROUP BY user_id
                ) umv ON umv.user_id = uda.user_id
               SET uda.module_visibility = COALESCE(NULLIF(TRIM(uda.module_visibility), ''), umv.module_visibility)
             WHERE COALESCE(NULLIF(TRIM(uda.module_visibility), ''), '') = ''",
            []
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS user_operational_scopes (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                machine_id INT NULL,
                part_id INT NULL,
                task_type VARCHAR(80) NULL,
                department_code VARCHAR(80) NULL,
                branch_code VARCHAR(80) NULL,
                ownership_role VARCHAR(80) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                updated_by VARCHAR(190) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_scope_user_id (user_id),
                KEY idx_scope_machine_id (machine_id),
                KEY idx_scope_part_id (part_id),
                KEY idx_scope_task_type (task_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS user_module_visibility (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                module_key VARCHAR(120) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                updated_by VARCHAR(190) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_module (user_id, module_key),
                KEY idx_user_module_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS user_role_duties (
                user_id INT PRIMARY KEY,
                duty_codes TEXT NULL,
                duty_notes TEXT NULL,
                updated_by VARCHAR(190) NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS workspace_profiles (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                profile_key VARCHAR(100) NOT NULL,
                name VARCHAR(190) NOT NULL,
                description TEXT NULL,
                app_key VARCHAR(120) NULL,
                owner_type VARCHAR(20) NOT NULL DEFAULT 'app',
                owner_key VARCHAR(120) NULL,
                authority_role VARCHAR(40) NOT NULL DEFAULT 'app_user',
                governance_scope VARCHAR(40) NOT NULL DEFAULT 'app',
                landing_route VARCHAR(255) NULL,
                default_app VARCHAR(120) NULL,
                assigned_apps TEXT NULL,
                access_profiles TEXT NULL,
                widget_discovery VARCHAR(16) NOT NULL DEFAULT 'auto',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                nav_sections LONGTEXT NULL,
                quick_actions LONGTEXT NULL,
                module_visibility LONGTEXT NULL,
                permissions LONGTEXT NULL,
                dashboard_blocks LONGTEXT NULL,
                created_by VARCHAR(190) NULL,
                updated_by VARCHAR(190) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_workspace_profiles_key (profile_key),
                KEY idx_workspace_profiles_app (app_key),
                KEY idx_workspace_profiles_owner (owner_type, owner_key),
                KEY idx_workspace_profiles_authority (authority_role),
                KEY idx_workspace_profiles_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );
        self::addWorkspaceProfileColumnIfMissing('owner_type', "ALTER TABLE workspace_profiles ADD COLUMN owner_type VARCHAR(20) NOT NULL DEFAULT 'app' AFTER app_key");
        self::addWorkspaceProfileColumnIfMissing('owner_key', "ALTER TABLE workspace_profiles ADD COLUMN owner_key VARCHAR(120) NULL AFTER owner_type");

        DB::query(
            "CREATE TABLE IF NOT EXISTS user_app_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                app_key VARCHAR(120) NOT NULL,
                operational_role VARCHAR(120) NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 1,
                updated_by VARCHAR(190) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_app (user_id, app_key),
                KEY idx_user_app_roles_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );

        $done = true;

        // Seed built-in system workspace profiles (idempotent — INSERT IGNORE).
        self::seedSystemWorkspaceProfiles();
    }

    /**
     * Idempotent bootstrap of system workspace profiles.
     * Called once per process from ensureSchema(). INSERT IGNORE guarantees no double-insert.
     * is_system=1 profiles are read-only in the catalog UI.
     */
    private static function seedSystemWorkspaceProfiles(): void
    {
        $systemProfileKeys = [
            'platform_admin_governance',
            'general_admin',
            'general_operator',
            'general_display_tv',
        ];

        $profiles = [
            [
                'profile_key'     => 'platform_admin_governance',
                'name'            => 'Platform Admin Governance',
                'description'     => 'System baseline profile for platform administrators.',
                'app_key'         => 'platform',
                'authority_role'  => 'platform_admin',
                'governance_scope'=> 'platform',
                'landing_route'   => '/',
                'default_app'     => 'platform',
                'assigned_apps'   => self::ALL_ENABLED_APPS_TOKEN,
                'access_profiles' => 'platform_administration',
                'widget_discovery'=> 'auto',
                'is_system'       => 1,
            ],
            [
                'profile_key'     => 'general_admin',
                'name'            => 'General Admin',
                'description'     => 'System baseline profile for app-level administrators (non-platform).',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'app_admin',
                'governance_scope'=> 'app',
                'landing_route'   => '/',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing,sbaio',
                'access_profiles' => 'manufacturing_admin',
                'widget_discovery'=> 'auto',
                'is_system'       => 1,
            ],
            [
                'profile_key'     => 'general_operator',
                'name'            => 'General Operator',
                'description'     => 'System baseline profile for operators with broad app work access.',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'app_user',
                'governance_scope'=> 'app',
                'landing_route'   => '/u/{user}/dashboard',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing,sbaio',
                'access_profiles' => 'production_operations',
                'widget_discovery'=> 'auto',
                'is_system'       => 1,
            ],
            [
                'profile_key'     => 'general_display_tv',
                'name'            => 'General Display/TV',
                'description'     => 'System baseline profile for readonly display users.',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'tv_display',
                'governance_scope'=> 'shared',
                'landing_route'   => '/displays/user',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing',
                'access_profiles' => 'readonly_observer',
                'widget_discovery'=> 'auto',
                'is_system'       => 1,
            ],
            // Editable starter profiles (non-system templates)
            [
                'profile_key'     => 'manufacturing_operator',
                'name'            => 'Manufacturing Operator',
                'description'     => 'Editable starter profile for manufacturing operators.',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'app_user',
                'governance_scope'=> 'app',
                'landing_route'   => '/u/{user}/dashboard',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing',
                'access_profiles' => 'production_operations',
                'widget_discovery'=> 'auto',
                'is_system'       => 0,
            ],
            [
                'profile_key'     => 'sbaio_operator',
                'name'            => 'SBAIO Operator',
                'description'     => 'Editable starter profile for SBAIO operators.',
                'app_key'         => 'sbaio',
                'authority_role'  => 'app_user',
                'governance_scope'=> 'app',
                'landing_route'   => '/u/{user}/dashboard',
                'default_app'     => 'sbaio',
                'assigned_apps'   => 'sbaio',
                'access_profiles' => 'sbaio_operations',
                'widget_discovery'=> 'auto',
                'is_system'       => 0,
            ],
            [
                'profile_key'     => 'platform_operator',
                'name'            => 'Platform Operator',
                'description'     => 'Editable starter profile for platform app users.',
                'app_key'         => 'platform',
                'authority_role'  => 'app_user',
                'governance_scope'=> 'platform',
                'landing_route'   => '/u/{user}/dashboard',
                'default_app'     => 'platform',
                'assigned_apps'   => 'platform',
                'access_profiles' => 'operator_workboard',
                'widget_discovery'=> 'auto',
                'is_system'       => 0,
            ],
            [
                'profile_key'     => 'tv_display_floor',
                'name'            => 'TV Display Floor',
                'description'     => 'Editable starter profile for floor displays.',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'tv_display',
                'governance_scope'=> 'shared',
                'landing_route'   => '/displays/user',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing',
                'access_profiles' => 'readonly_observer',
                'widget_discovery'=> 'auto',
                'is_system'       => 0,
            ],
            [
                'profile_key'     => 'app_admin_manufacturing',
                'name'            => 'Manufacturing App Admin',
                'description'     => 'Editable starter profile for manufacturing app administrators.',
                'app_key'         => 'manufacturing',
                'authority_role'  => 'app_admin',
                'governance_scope'=> 'app',
                'landing_route'   => '/',
                'default_app'     => 'manufacturing',
                'assigned_apps'   => 'manufacturing',
                'access_profiles' => 'manufacturing_admin',
                'widget_discovery'=> 'auto',
                'is_system'       => 0,
            ],
        ];

        foreach ($profiles as $p) {
            try {
                DB::query(
                    "INSERT IGNORE INTO workspace_profiles
                        (profile_key, name, description, app_key, authority_role, governance_scope, landing_route, default_app, assigned_apps, access_profiles, widget_discovery, is_active, is_system, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'system')",
                    [
                        $p['profile_key'],
                        $p['name'],
                        $p['description'],
                        $p['app_key'],
                        $p['authority_role'],
                        $p['governance_scope'],
                        $p['landing_route'],
                        $p['default_app'],
                        $p['assigned_apps'],
                        $p['access_profiles'],
                        $p['widget_discovery'],
                        (int)($p['is_system'] ?? 0),
                    ]
                );

                // Keep existing custom values intact, but backfill missing fields for older installs.
                $isSystem = in_array((string)$p['profile_key'], $systemProfileKeys, true) ? 1 : (int)($p['is_system'] ?? 0);
                DB::query(
                    "UPDATE workspace_profiles
                        SET authority_role = CASE WHEN COALESCE(NULLIF(TRIM(authority_role), ''), '') = '' THEN ? ELSE authority_role END,
                            app_key = CASE WHEN COALESCE(NULLIF(TRIM(app_key), ''), '') = '' THEN ? ELSE app_key END,
                            landing_route = CASE WHEN COALESCE(NULLIF(TRIM(landing_route), ''), '') = '' THEN ? ELSE landing_route END,
                            default_app = CASE WHEN COALESCE(NULLIF(TRIM(default_app), ''), '') = '' THEN ? ELSE default_app END,
                            assigned_apps = CASE WHEN COALESCE(NULLIF(TRIM(assigned_apps), ''), '') = '' THEN ? ELSE assigned_apps END,
                            access_profiles = CASE WHEN COALESCE(NULLIF(TRIM(access_profiles), ''), '') = '' THEN ? ELSE access_profiles END,
                                                        is_system = ?,
                            updated_by = 'system',
                            updated_at = NOW()
                      WHERE profile_key = ?",
                    [
                        $p['authority_role'],
                        $p['app_key'],
                        $p['landing_route'],
                        $p['default_app'],
                        $p['assigned_apps'],
                        $p['access_profiles'],
                        $isSystem,
                        $p['profile_key'],
                    ]
                );
            } catch (\Throwable) {
                // Non-fatal: table may not exist yet on fresh installs; skip gracefully.
            }
        }

        try {
            DB::query(
                "UPDATE workspace_profiles
                    SET assigned_apps = ?,
                        updated_by = 'system',
                        updated_at = NOW()
                  WHERE profile_key = 'platform_admin_governance'
                    AND is_system = 1",
                [self::ALL_ENABLED_APPS_TOKEN]
            );
        } catch (\Throwable) {
            // Non-fatal.
        }

        // Policy guard: only the canonical four remain system profiles; all others stay editable.
        try {
            $in = implode(',', array_fill(0, count($systemProfileKeys), '?'));
            DB::query(
                "UPDATE workspace_profiles
                    SET is_system = CASE WHEN profile_key IN ({$in}) THEN 1 ELSE 0 END,
                        updated_by = 'system',
                        updated_at = NOW()",
                $systemProfileKeys
            );
        } catch (\Throwable) {
            // Non-fatal.
        }

        self::backfillWorkspaceProfileOwnership();
        self::backfillWorkspaceProfilesForAccessAutofill();
    }

    /**
     * @return array<int,string>
     */
    public static function canonicalSystemWorkspaceProfileKeys(): array
    {
        return [
            'platform_admin_governance',
            'general_admin',
            'general_operator',
            'general_display_tv',
        ];
    }

    /**
     * @return array{owner_type:string,owner_key:string}
     */
    public static function inferWorkspaceProfileOwnership(
        string $profileKey,
        string $authorityRole,
        string $govScope,
        string $appKey,
        string $defaultApp,
        ?string $moduleVisibilityJson = null,
        bool $isSystem = false
    ): array {
        $profileKey = strtolower(trim($profileKey));
        $authorityRole = strtolower(trim($authorityRole));
        $govScope = strtolower(trim($govScope));
        $appKey = strtolower(trim($appKey));
        $defaultApp = strtolower(trim($defaultApp));

        if ($isSystem || in_array($profileKey, self::canonicalSystemWorkspaceProfileKeys(), true)) {
            return ['owner_type' => 'acl', 'owner_key' => 'base_acl'];
        }

        if ($govScope === 'module') {
            $ownerKey = '';
            if (is_string($moduleVisibilityJson) && trim($moduleVisibilityJson) !== '') {
                $decoded = json_decode($moduleVisibilityJson, true);
                if (is_array($decoded)) {
                    $ownerKey = self::firstWorkspaceProfileModuleKey($decoded);
                }
            }
            if ($ownerKey === '') {
                $ownerKey = $appKey !== '' ? $appKey : ($defaultApp !== '' ? $defaultApp : ($profileKey !== '' ? $profileKey : 'module'));
            }
            return ['owner_type' => 'module', 'owner_key' => $ownerKey];
        }

        $ownerKey = $appKey !== '' ? $appKey : ($defaultApp !== '' ? $defaultApp : ($authorityRole !== '' ? $authorityRole : 'shared'));
        return ['owner_type' => 'app', 'owner_key' => $ownerKey];
    }

    /**
     * @param mixed $decoded
     */
    private static function firstWorkspaceProfileModuleKey($decoded): string
    {
        if (!is_array($decoded)) {
            return '';
        }

        foreach ($decoded as $key => $value) {
            $normalizedKey = strtolower(trim((string)$key));
            if ($normalizedKey !== '' && !in_array($normalizedKey, ['none', 'view', 'work', 'approve', 'manage'], true)) {
                return $normalizedKey;
            }
            $nested = self::firstWorkspaceProfileModuleKey($value);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    private static function backfillWorkspaceProfileOwnership(): void
    {
        try {
            $rows = DB::fetchAll(
                "SELECT id, profile_key, authority_role, governance_scope, app_key, default_app, module_visibility, is_system
                   FROM workspace_profiles"
            );
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $profileId = (int)($row['id'] ?? 0);
            if ($profileId <= 0) {
                continue;
            }

            $ownership = self::inferWorkspaceProfileOwnership(
                (string)($row['profile_key'] ?? ''),
                (string)($row['authority_role'] ?? ''),
                (string)($row['governance_scope'] ?? ''),
                (string)($row['app_key'] ?? ''),
                (string)($row['default_app'] ?? ''),
                is_string($row['module_visibility'] ?? null) ? (string)$row['module_visibility'] : null,
                ((int)($row['is_system'] ?? 0)) === 1
            );

            DB::query(
                'UPDATE workspace_profiles
                    SET owner_type = ?, owner_key = ?, updated_by = ?, updated_at = NOW()
                  WHERE id = ?',
                [$ownership['owner_type'], $ownership['owner_key'], 'system', $profileId]
            );
        }
    }

    private static function backfillWorkspaceProfilesForAccessAutofill(): void
    {
        try {
            $rows = DB::fetchAll(
                "SELECT id, profile_key, authority_role, app_key, default_app, assigned_apps, access_profiles, landing_route
                   FROM workspace_profiles"
            );
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $authorityRole = strtolower(trim((string)($row['authority_role'] ?? '')));
            if ($authorityRole === '') {
                $authorityRole = 'app_user';
            }

            $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
            $defaultApp = strtolower(trim((string)($row['default_app'] ?? '')));
            if ($defaultApp === '') {
                if ($appKey !== '') {
                    $defaultApp = $appKey;
                } elseif ($authorityRole === 'platform_admin') {
                    $defaultApp = 'platform';
                } elseif ($authorityRole === 'tv_display') {
                    $defaultApp = 'manufacturing';
                } else {
                    $defaultApp = 'manufacturing';
                }
            }

            $assignedApps = strtolower(trim((string)($row['assigned_apps'] ?? '')));
            if ($assignedApps === '') {
                $assignedApps = $defaultApp;
            }

            $accessProfiles = strtolower(trim((string)($row['access_profiles'] ?? '')));
            if ($accessProfiles === '') {
                $accessProfiles = self::defaultAccessProfilesForWorkspaceProfile($authorityRole, $defaultApp, (string)($row['profile_key'] ?? ''));
            }

            $landingRoute = trim((string)($row['landing_route'] ?? ''));
            if ($landingRoute === '') {
                $landingRoute = $authorityRole === 'tv_display' ? '/displays/user' : '/u/{user}/dashboard';
            }

            DB::query(
                'UPDATE workspace_profiles
                    SET authority_role = ?,
                        default_app = ?,
                        assigned_apps = ?,
                        access_profiles = ?,
                        landing_route = ?,
                        updated_by = ?,
                        updated_at = NOW()
                  WHERE id = ?',
                [$authorityRole, $defaultApp, $assignedApps, $accessProfiles, $landingRoute, 'system', $id]
            );
        }
    }

    private static function defaultAccessProfilesForWorkspaceProfile(string $authorityRole, string $defaultApp, string $profileKey): string
    {
        $authority = strtolower(trim($authorityRole));
        $app = strtolower(trim($defaultApp));
        $key = strtolower(trim($profileKey));

        if ($authority === 'platform_admin') {
            return 'platform_administration';
        }
        if ($authority === 'app_admin') {
            return 'manufacturing_admin';
        }
        if ($authority === 'tv_display') {
            return 'readonly_observer';
        }
        if (str_contains($key, 'readonly') || str_contains($key, 'display')) {
            return 'readonly_observer';
        }

        return match ($app) {
            'sbaio' => 'sbaio_operations',
            'accounting' => 'accounting_approval',
            'hr' => 'operator_workboard',
            'manufacturing' => 'production_operations',
            default => 'operator_workboard',
        };
    }

    public static function resolveUserContext(?array $user): array
    {
        self::ensureSchema();

        $legacyRole = strtolower(trim((string)($user['role'] ?? '')));
        $userId = (int)($user['id'] ?? 0);

        $row = $userId > 0
            ? DB::fetchOne(
                "SELECT u.id,
                        u.role,
                    COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                        COALESCE(NULLIF(TRIM(u.role_tier), ''), '') AS role_tier,
                        a.dashboard_type,
                        a.default_app,
                        a.default_landing_page,
                    COALESCE(a.assigned_apps, '') AS assigned_apps,
                    COALESCE(a.access_profiles, '') AS access_profiles,
                    COALESCE(a.permissions, '') AS permissions,
                        COALESCE(a.view_access, '') AS view_access,
                        COALESCE(a.table_access, '') AS table_access,
                        COALESCE(a.chart_access, '') AS chart_access,
                        COALESCE(a.account_class, '') AS account_class,
                        COALESCE(a.selected_role_packs, '') AS selected_role_packs,
                        COALESCE(a.cross_functional_access, '') AS cross_functional_access,
                        COALESCE(a.me_dashboard_blocks, '') AS me_dashboard_blocks,
                        COALESCE(a.me_plugin_cards, '') AS me_plugin_cards,
                        COALESCE(a.operator_views, '') AS operator_views,
                        COALESCE(d.duty_codes, '') AS duty_codes,
                        COALESCE(d.duty_notes, '') AS duty_notes
                 FROM users u
                 LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
                 LEFT JOIN user_role_duties d ON d.user_id = u.id
                 WHERE u.id=? LIMIT 1",
                [$userId]
            )
            : null;

        // Fetch per-app operational roles from user_app_roles (new model).
        $appRoles = [];
        if ($userId > 0) {
            try {
                $appRoleRows = DB::fetchAll(
                    'SELECT app_key, operational_role FROM user_app_roles WHERE user_id = ? ORDER BY is_primary DESC, updated_at DESC',
                    [$userId]
                );
            } catch (\Throwable $e) {
                $appRoleRows = [];
            }
            foreach ($appRoleRows as $_ar) {
                $appRoles[(string)$_ar['app_key']] = (string)$_ar['operational_role'];
            }
        }

        $authorityRole = self::normalizeAccountType(
            (string)($row['authority_role'] ?? ''),
            (string)($row['role'] ?? ($user['role'] ?? '')),
            (string)($row['role_tier'] ?? ''),
            (string)($row['default_app'] ?? '')
        );
        $defaults = self::suggestedDefaultsForRole((string)($row['role'] ?? ($user['role'] ?? '')), $authorityRole);
        $tier = self::legacyTierForAccountType($authorityRole);
        // Prefer operational_role from user_app_roles for the primary app over the legacy dashboard_type column.
        $primaryAppKey = self::resolveDefaultApp((string)($row['default_app'] ?? ''), (string)($defaults['default_app'] ?? ''));
        $rawDashboardType = (string)($row['dashboard_type'] ?? '');
        if ($rawDashboardType === '' && $primaryAppKey !== '' && isset($appRoles[$primaryAppKey])) {
            $rawDashboardType = $appRoles[$primaryAppKey];
        }
        $dashboardType = self::normalizeDashboardType($rawDashboardType, $authorityRole, $legacyRole);
        $defaultApp = self::resolveDefaultApp((string)($row['default_app'] ?? ''), (string)($defaults['default_app'] ?? ''));
        $accountClass = self::normalizeAccountClass(
            (string)($row['account_class'] ?? ''),
            $authorityRole,
            (string)($row['role'] ?? ($user['role'] ?? ''))
        );
        $selectedRolePacks = self::resolveRolePackKeys((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? ($defaults['access_profiles'] ?? ''))));
        $profileKeys = !empty($selectedRolePacks)
            ? $selectedRolePacks
            : self::resolveAccessProfileKeys((string)($row['access_profiles'] ?? ($defaults['access_profiles'] ?? '')));
        $profileKeys = self::normalizeAccessProfileKeysForContext($profileKeys, $authorityRole, $dashboardType);
        $accountClassDefaults = self::composeAccountClassDefaults($accountClass);
        $profileDefaults = self::composeAccessProfileDefaults($profileKeys);
        $crossAccess = self::parseCrossFunctionalAccess((string)($row['cross_functional_access'] ?? ''));
        $crossDefaults = self::composeCrossFunctionalDefaults($crossAccess);
        $crossProfileKeys = self::resolveAccessProfileKeys((string)($crossDefaults['access_profiles'] ?? ''));
        $effectiveProfiles = self::normalizeAccessProfileKeysForContext(array_values(array_unique(array_merge($profileKeys, $crossProfileKeys))), $authorityRole, $dashboardType);
        $templateSelections = SuitePermissionTemplateService::splitTemplateKeys($effectiveProfiles);
        $assignedApps = self::resolveAssignedApps(
            self::mergeTokenCsv(self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['assigned_apps'] ?? ''), (string)($profileDefaults['assigned_apps'] ?? '')), (string)($crossDefaults['assigned_apps'] ?? '')), (string)($row['assigned_apps'] ?? '')),
            $defaultApp,
            (string)($defaults['assigned_apps'] ?? '')
        );
        $activeAssignedApps = array_values(array_filter($assignedApps, static fn(string $app): bool => self::isAppEnabled($app)));
        $assignedAppDefaults = self::normalizeAssignedAppDefaultsForContext(
            self::composeAssignedAppsDefaults($assignedApps),
            $authorityRole,
            $dashboardType
        );
        // Baseline visibility policy floor: all authenticated non-platform-admin users receive
        // at-minimum View for operational truth surfaces (Orders, Plans, Production/Assembly/QC
        // status, Dispatch Status, Parts & Material Stock). This is additive — never removes
        // modules already granted by higher authorities. filterInactiveAppModules() ensures that
        // baseline modules for unassigned apps are automatically excluded.
        $baselineModuleCsv = $authorityRole !== 'platform_admin'
            ? implode(',', self::BASELINE_VISIBILITY_POLICY['baseline_view_modules'])
            : '';
        $baselinePermissionCsv = $authorityRole !== 'platform_admin'
            ? implode(',', self::BASELINE_VISIBILITY_POLICY['baseline_view_permissions'])
            : '';
        $baselineTableCsv = $authorityRole !== 'platform_admin'
            ? implode(',', self::BASELINE_VISIBILITY_POLICY['baseline_view_table_access'])
            : '';
        $moduleVisibility = self::filterInactiveAppModules(
            self::csvTokens(self::mergeTokenCsv(
                self::mergeTokenCsv(
                    self::mergeTokenCsv((string)($profileDefaults['module_visibility'] ?? ''), (string)($crossDefaults['module_visibility'] ?? '')),
                    implode(',', $userId > 0 ? self::moduleVisibilityForUser($userId) : [])
                ),
                $baselineModuleCsv
            )),
            $activeAssignedApps
        );
        $defaultApp = self::effectiveDefaultApp($defaultApp, $activeAssignedApps);
        $effectiveViewAccess = self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['view_access'] ?? ''), (string)($assignedAppDefaults['view_access'] ?? '')), self::mergeTokenCsv((string)($profileDefaults['view_access'] ?? ''), (string)($crossDefaults['view_access'] ?? ''))), (string)($row['view_access'] ?? '')));
        $effectiveTableAccess = self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(
                self::mergeTokenCsv(
                    self::mergeTokenCsv((string)($accountClassDefaults['table_access'] ?? ''), (string)($assignedAppDefaults['table_access'] ?? '')),
                    self::mergeTokenCsv((string)($profileDefaults['table_access'] ?? ''), (string)($crossDefaults['table_access'] ?? ''))
                ),
                (string)($row['table_access'] ?? '')
            ),
            $baselineTableCsv
        ));
        $effectiveChartAccess = self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['chart_access'] ?? ''), (string)($assignedAppDefaults['chart_access'] ?? '')), self::mergeTokenCsv((string)($profileDefaults['chart_access'] ?? ''), (string)($crossDefaults['chart_access'] ?? ''))), (string)($row['chart_access'] ?? '')));
        $effectivePermissions = self::normalizePermissionTokensForContext(
            self::csvTokens(self::mergeTokenCsv(
                self::mergeTokenCsv(
                    self::mergeTokenCsv(
                        self::mergeTokenCsv((string)($accountClassDefaults['permissions'] ?? ''), (string)($assignedAppDefaults['permissions'] ?? '')),
                        self::mergeTokenCsv((string)($profileDefaults['permissions'] ?? ''), (string)($crossDefaults['permissions'] ?? ''))
                    ),
                    (string)($row['permissions'] ?? ($defaults['permissions'] ?? ''))
                ),
                $baselinePermissionCsv
            )),
            $authorityRole,
            $dashboardType
        );
        $effectiveDutyCodes = self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['duty_codes'] ?? ''), (string)($assignedAppDefaults['duty_codes'] ?? '')), self::mergeTokenCsv((string)($profileDefaults['duty_codes'] ?? ''), (string)($crossDefaults['duty_codes'] ?? ''))), (string)($row['duty_codes'] ?? '')));
        $rawMeDashboardBlocks = (string)($row['me_dashboard_blocks'] ?? '');
        $rawMePluginCards = (string)($row['me_plugin_cards'] ?? '');

        return [
            'authority_role' => $authorityRole,
            'view_access' => $effectiveViewAccess,
            'table_access' => $effectiveTableAccess,
            'chart_access' => $effectiveChartAccess,
            'access_profiles' => $effectiveProfiles,
            'suite_role_templates' => (array)($templateSelections['suite_role_templates'] ?? []),
            'module_permission_templates' => (array)($templateSelections['module_permission_templates'] ?? []),
            'suite_role_template_labels' => SuitePermissionTemplateService::labels((array)($templateSelections['suite_role_templates'] ?? [])),
            'module_permission_template_labels' => SuitePermissionTemplateService::labels((array)($templateSelections['module_permission_templates'] ?? [])),
            'permissions' => $effectivePermissions,
            'cross_functional_access' => $crossAccess,
            'assigned_apps' => $assignedApps,
            'active_assigned_apps' => $activeAssignedApps,
            'account_class' => $accountClass,
            'selected_role_packs' => $profileKeys,
            'duty_codes' => $effectiveDutyCodes,
            'duty_notes' => (string)($row['duty_notes'] ?? ''),
            'me_dashboard_blocks' => self::normalizeMeDashboardBlocksForAccountType(
                self::resolveMeDashboardBlocks($rawMeDashboardBlocks),
                $authorityRole
            ),
            'me_plugin_cards' => self::normalizeMePluginCardsForContext(
                self::resolveMePluginCards($rawMePluginCards),
                $authorityRole,
                $dashboardType,
                $activeAssignedApps
            ),
            'me_dashboard_blocks_explicit_none' => self::hasExplicitExperienceLayoutNone($rawMeDashboardBlocks),
            'me_plugin_cards_explicit_none' => self::hasExplicitExperienceLayoutNone($rawMePluginCards),
            'user_id' => $userId,
            'operational_role' => self::normalizeOperationalRole((string)($row['role'] ?? ($user['role'] ?? ''))),
            'legacy_role' => (string)($row['role'] ?? ($user['role'] ?? '')),
            'role_tier' => $tier,
            'dashboard_type' => $dashboardType,
            'app_roles' => $appRoles,
            'default_app' => $defaultApp,
            'default_landing_page' => (string)($row['default_landing_page'] ?? ''),
            'scopes' => $userId > 0 ? self::scopeForUser($userId) : self::emptyScope(),
            'scope' => $userId > 0 ? self::scopeForUser($userId) : self::emptyScope(),
            'module_visibility' => $moduleVisibility,
            'workspace_profile' => self::resolveWorkspaceProfile($authorityRole, $defaultApp, (int)($user['id'] ?? 0)),
        ];
    }

    /**
     * Look up the best-matching workspace_profile for a given authority_role + app_key.
     * Prefers exact app_key match over wildcard (NULL/empty). Returns null if none found.
     * When $userId > 0, honours any user-specific pin in user_dashboard_assignments.workspace_profile_key.
     *
     * @return array{profile_key:string,name:string,landing_route:string,nav_sections:mixed,quick_actions:mixed,widget_discovery:string,is_pinned:bool}|null
     */
    private static function resolveWorkspaceProfile(string $authorityRole, string $appKey, int $userId = 0): ?array
    {
        if ($authorityRole === '' || in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            return null;
        }

        try {
            // Check for a user-specific pin first.
            if ($userId > 0) {
                $pinRow = DB::fetchOne(
                    "SELECT workspace_profile_key FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1",
                    [$userId]
                );
                $pinnedKey = trim((string)($pinRow['workspace_profile_key'] ?? ''));
                if ($pinnedKey !== '') {
                    $pinnedProfile = DB::fetchOne(
                        "SELECT profile_key, name, landing_route, nav_sections, quick_actions, widget_discovery
                         FROM workspace_profiles
                         WHERE profile_key = ? AND is_active = 1 LIMIT 1",
                        [$pinnedKey]
                    );
                    if (is_array($pinnedProfile) && $pinnedProfile !== []) {
                        return [
                            'profile_key'      => (string)($pinnedProfile['profile_key'] ?? ''),
                            'name'             => (string)($pinnedProfile['name'] ?? ''),
                            'landing_route'    => (string)($pinnedProfile['landing_route'] ?? ''),
                            'nav_sections'     => isset($pinnedProfile['nav_sections']) && is_string($pinnedProfile['nav_sections']) && $pinnedProfile['nav_sections'] !== ''
                                ? json_decode($pinnedProfile['nav_sections'], true)
                                : null,
                            'quick_actions'    => isset($pinnedProfile['quick_actions']) && is_string($pinnedProfile['quick_actions']) && $pinnedProfile['quick_actions'] !== ''
                                ? json_decode($pinnedProfile['quick_actions'], true)
                                : null,
                            'widget_discovery' => (string)($pinnedProfile['widget_discovery'] ?? 'auto'),
                            'is_pinned'        => true,
                        ];
                    }
                }
            }

            $wpRow = DB::fetchOne(
                "SELECT profile_key, name, landing_route, nav_sections, quick_actions, widget_discovery
                 FROM workspace_profiles
                 WHERE authority_role = ?
                   AND is_active = 1
                   AND (app_key IS NULL OR app_key = '' OR app_key = ?)
                 ORDER BY (app_key = ? AND app_key != '') DESC, id ASC
                 LIMIT 1",
                [$authorityRole, $appKey, $appKey]
            );
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($wpRow) || $wpRow === []) {
            return null;
        }

        return [
            'profile_key'      => (string)($wpRow['profile_key'] ?? ''),
            'name'             => (string)($wpRow['name'] ?? ''),
            'landing_route'    => (string)($wpRow['landing_route'] ?? ''),
            'nav_sections'     => isset($wpRow['nav_sections']) && is_string($wpRow['nav_sections']) && $wpRow['nav_sections'] !== ''
                ? json_decode($wpRow['nav_sections'], true)
                : null,
            'quick_actions'    => isset($wpRow['quick_actions']) && is_string($wpRow['quick_actions']) && $wpRow['quick_actions'] !== ''
                ? json_decode($wpRow['quick_actions'], true)
                : null,
            'widget_discovery' => (string)($wpRow['widget_discovery'] ?? 'auto'),
            'is_pinned'        => false,
        ];
    }

    public static function routeForDashboardType(string $dashboardType): string
    {
        return '/';
    }

    /**
     * Unified daily-home policy by account type.
     * App users always enter through My Work; admin accounts land on control dashboards.
     *
     * @param array<string,mixed> $context
     */
    public static function primaryLandingForContext(array $context): string
    {
        return '/';
    }

    /**
     * Safe post-login landing: ordered fallbacks validated against assignment policy.
     *
     * @param array<string,mixed>|null $user
     */
    public static function safePostLoginLanding(?array $user): string
    {
        $ctx = self::resolveUserContext($user);
        $intended = \App\Core\Auth::consumeIntendedUrl('/');
        $candidates = [
            $intended,
            trim((string)($ctx['default_landing_page'] ?? '')) ?: '/',
            '/',
        ];

        // Add first active assigned app home
        foreach ((array)($ctx['active_assigned_apps'] ?? []) as $app) {
            $appHome = match (strtolower(trim((string)$app))) {
                'manufacturing' => '/apps/manufacturing',
                'platform' => '/admin',
                default => "/apps/{$app}",
            };
            $candidates[] = $appHome;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') continue;

            $decision = self::routeAccessDecision($user, $candidate);
            if ($decision['allowed']) {
                return $candidate;
            }
        }

        return '/';
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public static function primaryLandingForUser(?array $user): string
    {
        return self::safePostLoginLanding($user);
    }

    public static function dashboardTypeForUser(?array $user): string
    {
        $ctx = self::resolveUserContext($user);
        return (string)($ctx['dashboard_type'] ?? 'production_leader');
    }

    /**
     * @return array{allowed:bool,reason:string}
     */
    public static function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
    {
        self::ensureSchema();

        if (!$user || (int)($user['id'] ?? 0) <= 0) {
            // Unauthenticated callers must not be silently allowed.
            // Authentication is enforced upstream (acl_require / Auth::isLoggedIn),
            // but returning true here would be a silent fail-open if that check is ever skipped.
            return ['allowed' => false, 'reason' => 'unauthenticated'];
        }

        $path = trim($path);
        if ($path === '') {
            $path = '/';
        }

        // Always allow core ops routes for logged-in users (safe post-login landing)
        if (in_array($path, ['/', '/me'], true)) {
            $ctx = self::resolveUserContext($user);
            $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
            if ($authorityRole !== 'platform_admin') {
                return ['allowed' => true, 'reason' => 'post_login_safe'];
            }
        }

        $ctx = self::resolveUserContext($user);
        $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
        $dashboardType = (string)($ctx['dashboard_type'] ?? 'production_leader');
        $dashboardRoute = self::routeForDashboardType($dashboardType);

        if (in_array($path, ['/ops/dashboard', '/me', '/'], true) || str_starts_with($path, '/ops/notifications')) {
            return ['allowed' => true, 'reason' => 'core_ops_route'];
        }

        if (self::isSupervisoryOnlyPath($path) && $authorityRole === 'app_user') {
            return ['allowed' => false, 'reason' => 'supervisory_only'];
        }

        // App Admin may access their own /admin/{username} dashboard and sub-paths.
        $isAdminPath = $path === '/admin' || str_starts_with($path, '/admin/');
        if (self::isGovernanceOnlyPath($path) && $authorityRole !== 'platform_admin' && !($isAdminPath && $authorityRole === 'app_admin')) {
            return ['allowed' => false, 'reason' => 'governance_only'];
        }

        $targetDashboardType = self::dashboardTypeForPath($path);
        if ($targetDashboardType !== '' && !self::canAccessDashboardTypeForContext($ctx, $targetDashboardType, $dashboardRoute, $path)) {
            return ['allowed' => false, 'reason' => 'dashboard_not_assigned'];
        }

        $appKey = self::appForPath($path);
        if ($appKey !== '' && !self::canAccessAssignedApp($ctx, $appKey)) {
            return ['allowed' => false, 'reason' => 'app_not_assigned'];
        }

        if (self::isQcEntryExecutionPath($path, $method) && self::canAccessQcEntryExecutionForContext($ctx)) {
            return ['allowed' => true, 'reason' => 'qc_entry_execution_grant'];
        }

        if ($authorityRole !== 'platform_admin' && self::isPlatformOnlyPath($path) && !($isAdminPath && $authorityRole === 'app_admin')) {
            return ['allowed' => false, 'reason' => 'platform_only'];
        }

        $module = self::moduleForPath($path);
        $tokenRule = self::tokensForPath($path);

        // Keep app assignment as the broad entry grant, but enforce modeled module
        // and surface token gates whenever a route is explicitly mapped.
        if ($appKey !== '' && self::canAccessAssignedApp($ctx, $appKey) && $module === '' && $tokenRule === []) {
            return ['allowed' => true, 'reason' => 'app_grant'];
        }

        if ($module !== '' && !self::canAccessModule($user, $module)) {
            return ['allowed' => false, 'reason' => 'module_not_allowed'];
        }

        if ($authorityRole !== 'platform_admin') {
            $viewTokens = array_values((array)($ctx['view_access'] ?? []));
            $tableTokens = array_values((array)($ctx['table_access'] ?? []));
            $chartTokens = array_values((array)($ctx['chart_access'] ?? []));

            if (!empty($tokenRule['view']) && !empty($viewTokens) && !in_array((string)$tokenRule['view'], $viewTokens, true)) {
                return ['allowed' => false, 'reason' => 'view_not_allowed'];
            }
            if (!empty($tokenRule['table']) && !empty($tableTokens) && !in_array((string)$tokenRule['table'], $tableTokens, true)) {
                return ['allowed' => false, 'reason' => 'table_not_allowed'];
            }
            if (!empty($tokenRule['chart']) && !empty($chartTokens) && !in_array((string)$tokenRule['chart'], $chartTokens, true)) {
                return ['allowed' => false, 'reason' => 'chart_not_allowed'];
            }
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,string> $columnToScopeKey
     * @return array{sql:string,params:array<int,int|string>}
     */
    public static function scopeFiltersForTable(array $scope, string $table, array $columnToScopeKey): array
    {
        self::ensureSchema();

        $sql = '';
        $params = [];
        foreach ($columnToScopeKey as $column => $scopeKey) {
            if (!self::columnExists($table, $column)) {
                continue;
            }

            if (in_array($scopeKey, ['machine_ids', 'part_ids'], true)) {
                $values = array_values(array_filter(array_map('intval', (array)($scope[$scopeKey] ?? [])), static fn(int $v): bool => $v > 0));
                if (!$values) {
                    continue;
                }
                $holders = implode(',', array_fill(0, count($values), '?'));
                $sql .= " AND {$column} IN ({$holders})";
                foreach ($values as $v) {
                    $params[] = $v;
                }
                continue;
            }

            if (in_array($scopeKey, ['department_code', 'branch_code'], true)) {
                $value = trim((string)($scope[$scopeKey] ?? ''));
                if ($value === '') {
                    continue;
                }
                $sql .= " AND {$column} = ?";
                $params[] = $value;
            }
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array<int,string>
     */
    public static function enabledModulesForUser(?array $user): array
    {
        $ctx = self::resolveUserContext($user);
        $mods = array_values(array_filter(array_map([self::class, 'normalizeModuleKey'], (array)($ctx['module_visibility'] ?? []))));
        $uniq = [];
        foreach ($mods as $m) {
            $uniq[$m] = $m;
        }
        return array_values($uniq);
    }

    public static function hasModuleRestrictions(?array $user): bool
    {
        return count(self::enabledModulesForUser($user)) > 0;
    }

    public static function canAccessModule(?array $user, string $moduleKey): bool
    {
        $moduleKey = self::normalizeModuleKey($moduleKey);
        if ($moduleKey === '') {
            return true;
        }

        $enabled = self::enabledModulesForUser($user);
        if (empty($enabled)) {
            return true;
        }

        return in_array($moduleKey, $enabled, true);
    }

    /**
     * @param array<int,string> $modules
     * @return array<int,string>
     */
    public static function filterModulesForUser(?array $user, array $modules): array
    {
        if (!self::hasModuleRestrictions($user)) {
            return $modules;
        }

        $out = [];
        foreach ($modules as $module) {
            if (self::canAccessModule($user, (string)$module)) {
                $out[] = (string)$module;
            }
        }
        return $out;
    }

    public static function isAppEnabled(string $appKey): bool
    {
        $appKey = strtolower(trim($appKey));
        if ($appKey === '') {
            return false;
        }

        $map = self::enabledAppMap();
        return (bool)($map[$appKey] ?? false);
    }

    /**
     * Returns the canonical baseline visibility policy for consumption by UI layers
     * (Access, Access2, Visibility, /me composition, and runtime gating).
     *
     * This is the platform-level source of truth for default access behavior.
     *
     * @return array<string,mixed>
     */
    public static function baselineVisibilityPolicy(): array
    {
        return [
            // Surfaces that default to View for all authenticated app users.
            'baseline_view' => [
                ['module' => 'demands',    'label' => 'Orders',                 'level' => 'view', 'url' => '/apps/manufacturing/daily-orders'],
                ['module' => 'coverage',   'label' => 'Plans',                  'level' => 'view', 'url' => '/apps/manufacturing/production-plans'],
                ['module' => 'production', 'label' => 'Production',             'level' => 'view', 'url' => '/apps/manufacturing/production-dashboard'],
                ['module' => 'assembly',   'label' => 'Assembly',               'level' => 'view', 'url' => '/apps/manufacturing/assembly-dashboard'],
                ['module' => 'qc',         'label' => 'QC',                     'level' => 'view', 'url' => '/apps/manufacturing/qc-dashboard'],
                ['module' => 'dispatch',   'label' => 'Dispatch Status',        'level' => 'view', 'url' => '/dispatch-entries'],
                ['module' => 'materials',  'label' => 'Parts & Material Stock', 'level' => 'view', 'url' => '/apps/manufacturing/materials'],
            ],
            // Surfaces that default to None — require explicit authority/profile elevation.
            'management_restricted' => [
                ['key' => 'material_orders',  'label' => 'Material Orders',                'level' => 'none', 'note' => 'Requires explicit elevation'],
                ['key' => 'procurement',      'label' => 'Procurement / Planning Control', 'level' => 'none', 'note' => 'Requires explicit elevation'],
                ['key' => 'stock_adjustment', 'label' => 'Stock Adjustment',               'level' => 'none', 'note' => 'Requires explicit elevation'],
                ['key' => 'capacity_cost',    'label' => 'Cost / Capacity',                'level' => 'none', 'note' => 'Requires explicit elevation'],
                ['key' => 'accounting',       'label' => 'Accounting',                     'level' => 'none', 'note' => 'Requires explicit elevation'],
                ['key' => 'governance',       'label' => 'Governance / Admin Tools',       'level' => 'none', 'note' => 'Requires explicit elevation'],
            ],
            // Execution surfaces that move above View only when role/profile grants it.
            'execution_elevated' => [
                ['module' => 'production', 'label' => 'Production (Work / Approve / Manage)', 'level' => 'elevated_by_role'],
                ['module' => 'assembly',   'label' => 'Assembly (Work / Approve / Manage)',   'level' => 'elevated_by_role'],
                ['module' => 'qc',         'label' => 'QC (Work / Approve / Manage)',         'level' => 'elevated_by_role'],
                ['module' => 'dispatch',   'label' => 'Dispatch (Work / Approve / Manage)',   'level' => 'elevated_by_role'],
            ],
            'policy_note' => 'Operational truth is broadly visible (View for all). '
                . 'Operational control is role-gated. '
                . 'Stock and status visibility (View) does not grant management authority.',
        ];
    }

    public static function listAssignmentRows(array $filters = [], bool $includeDiagnostics = false): array
    {
        self::ensureSchema();

        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(LOWER(u.email) LIKE ? OR LOWER(COALESCE(u.display_name, '')) LIKE ? OR LOWER(COALESCE(u.username, '')) LIKE ?)";
            $needle = '%' . strtolower($search) . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        $accountClassFilter = strtolower(trim((string)($filters['account_class'] ?? '')));
        if ($accountClassFilter !== '') {
            $where[] = 'LOWER(COALESCE(NULLIF(TRIM(a.account_class), ""), "")) = ?';
            $params[] = $accountClassFilter;
        }

        $assignedAppFilter = strtolower(trim((string)($filters['assigned_app'] ?? '')));
        if ($assignedAppFilter !== '') {
            $where[] = 'LOWER(COALESCE(a.assigned_apps, "")) LIKE ?';
            $params[] = '%' . $assignedAppFilter . '%';
        }

        $rolePackFilter = strtolower(trim((string)($filters['role_pack'] ?? '')));
        if ($rolePackFilter !== '') {
            $where[] = '(LOWER(COALESCE(a.selected_role_packs, "")) LIKE ? OR LOWER(COALESCE(a.access_profiles, "")) LIKE ?)';
            $params[] = '%' . $rolePackFilter . '%';
            $params[] = '%' . $rolePackFilter . '%';
        }

        $workspaceProfileFilter = strtolower(trim((string)($filters['workspace_profile'] ?? '')));
        if ($workspaceProfileFilter !== '') {
            $where[] = 'LOWER(COALESCE(a.workspace_profile_key, "")) = ?';
            $params[] = $workspaceProfileFilter;
        }

        $status = self::normalizeAccountStatus((string)($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $where[] = "LOWER(COALESCE(NULLIF(TRIM(u.account_status), ''), 'active')) = ?";
            $params[] = $status;
        }

        $userId = (int)($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'u.id = ?';
            $params[] = $userId;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $rows = DB::fetchAll(
            "SELECT u.id,
                    u.email,
                    COALESCE(u.display_name, '') AS display_name,
                    COALESCE(u.username, '') AS username,
                    COALESCE(u.department, '') AS department,
                    COALESCE(u.created_at, '') AS created_at,
                    COALESCE(u.updated_at, '') AS updated_at,
                    u.role,
                    u.role AS operational_role,
                    COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                    COALESCE(NULLIF(TRIM(u.role_tier), ''), '') AS role_tier,
                    COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') AS account_status,
                    COALESCE(NULLIF(TRIM(u.verification_status), ''), 'ready') AS verification_status,
                    COALESCE(NULLIF(TRIM(u.security_status), ''), 'standard') AS security_status,
                    COALESCE(u.twofa_enabled, 0) AS twofa_enabled,
                    COALESCE(DATE_FORMAT(u.password_changed_at, '%Y-%m-%d %H:%i:%s'), '') AS password_changed_at,
                    COALESCE(a.dashboard_type, '') AS dashboard_type,
                    COALESCE(a.default_app, '') AS default_app,
                    COALESCE(a.default_landing_page, '') AS default_landing_page,
                    COALESCE(a.assigned_apps, '') AS assigned_apps,
                    COALESCE(a.access_profiles, '') AS access_profiles,
                    COALESCE(a.permissions, '') AS permissions,
                    COALESCE(a.view_access, '') AS view_access,
                    COALESCE(a.table_access, '') AS table_access,
                    COALESCE(a.chart_access, '') AS chart_access,
                    COALESCE(a.account_class, '') AS account_class,
                    COALESCE(a.selected_role_packs, '') AS selected_role_packs,
                    COALESCE(a.cross_functional_access, '') AS cross_functional_access,
                    COALESCE(a.me_dashboard_blocks, '') AS me_dashboard_blocks,
                    COALESCE(a.me_plugin_cards, '') AS me_plugin_cards,
                    COALESCE(a.display_surfaces, '') AS display_surfaces,
                    COALESCE(a.operator_views, '') AS operator_views,
                    COALESCE(a.workspace_profile_key, '') AS workspace_profile_key,
                    COALESCE(NULLIF(TRIM(a.dashboard_mode), ''), 'auto') AS dashboard_mode,
                    COALESCE(NULLIF(TRIM(a.default_app_mode), ''), 'auto') AS default_app_mode,
                    COALESCE(NULLIF(TRIM(a.landing_mode), ''), 'auto') AS landing_mode,
                    COALESCE(NULLIF(TRIM(a.access_profiles_mode), ''), 'auto') AS access_profiles_mode,
                    COALESCE(NULLIF(TRIM(a.module_visibility_mode), ''), 'auto') AS module_visibility_mode,
                    COALESCE(d.duty_codes, '') AS duty_codes,
                    COALESCE(d.duty_notes, '') AS duty_notes
             FROM users u
             LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
             LEFT JOIN user_role_duties d ON d.user_id = u.id
             {$whereSql}
             ORDER BY u.email ASC",
            $params
        );

        foreach ($rows as &$row) {
            $rawPermissionCsv = (string)($row['permissions'] ?? '');
            $rawViewCsv = (string)($row['view_access'] ?? '');
            $rawTableCsv = (string)($row['table_access'] ?? '');
            $rawChartCsv = (string)($row['chart_access'] ?? '');
            $rawDutyCsv = (string)($row['duty_codes'] ?? '');
            $legacyRole = strtolower(trim((string)($row['role'] ?? '')));
            $row['authority_role'] = self::normalizeAccountType(
                (string)($row['authority_role'] ?? ''),
                (string)($row['role'] ?? ''),
                (string)($row['role_tier'] ?? ''),
                (string)($row['default_app'] ?? '')
            );
            $defaults = self::suggestedDefaultsForRole((string)($row['role'] ?? ''), (string)$row['authority_role']);
            $row['role_tier'] = self::legacyTierForAccountType((string)$row['authority_role']);
            $row['dashboard_type'] = self::normalizeDashboardType((string)$row['dashboard_type'], (string)$row['authority_role'], $legacyRole);
            $row['operational_role'] = self::normalizeOperationalRole((string)($row['role'] ?? ''));
            $row['default_app'] = self::resolveDefaultApp((string)($row['default_app'] ?? ''), (string)($defaults['default_app'] ?? ''));
            $scope = self::scopeForUser((int)$row['id']);
            $profileKeys = self::resolveAccessProfileKeys((string)($row['selected_role_packs'] ?? ($row['access_profiles'] ?? ($defaults['access_profiles'] ?? ''))));
            $profileKeys = self::normalizeAccessProfileKeysForContext($profileKeys, (string)$row['authority_role'], (string)$row['dashboard_type']);
            $profileDefaults = self::composeAccessProfileDefaults($profileKeys);
            $crossAccess = self::parseCrossFunctionalAccess((string)($row['cross_functional_access'] ?? ''));
            $crossDefaults = self::composeCrossFunctionalDefaults($crossAccess);
            $crossProfileKeys = self::resolveAccessProfileKeys((string)($crossDefaults['access_profiles'] ?? ''));
            $effectiveProfiles = self::normalizeAccessProfileKeysForContext(array_values(array_unique(array_merge($profileKeys, $crossProfileKeys))), (string)$row['authority_role'], (string)$row['dashboard_type']);
            $templateSelections = SuitePermissionTemplateService::splitTemplateKeys($effectiveProfiles);
            $row['scope_machine_ids'] = implode(',', (array)$scope['machine_ids']);
            $row['scope_part_ids'] = implode(',', (array)$scope['part_ids']);
            $row['scope_task_types'] = implode(',', (array)$scope['task_types']);
            $row['scope_department_code'] = (string)($scope['department_code'] ?? '');
            $row['scope_branch_code'] = (string)($scope['branch_code'] ?? '');
            $row['scope_ownership_role'] = (string)($scope['ownership_role'] ?? '');
            $row['account_class'] = self::normalizeAccountClass((string)($row['account_class'] ?? ''), (string)$row['authority_role'], (string)($row['role'] ?? ''));
            $row['view_access'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['view_access'] ?? ''), (string)($crossDefaults['view_access'] ?? '')), (string)($row['view_access'] ?? ''))));
            $row['table_access'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['table_access'] ?? ''), (string)($crossDefaults['table_access'] ?? '')), (string)($row['table_access'] ?? ''))));
            $row['chart_access'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['chart_access'] ?? ''), (string)($crossDefaults['chart_access'] ?? '')), (string)($row['chart_access'] ?? ''))));
            $row['duty_codes'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['duty_codes'] ?? ''), (string)($crossDefaults['duty_codes'] ?? '')), (string)($row['duty_codes'] ?? ''))));
            $row['duty_notes'] = (string)($row['duty_notes'] ?? '');
            $row['module_visibility'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['module_visibility'] ?? ''), (string)($crossDefaults['module_visibility'] ?? '')), implode(',', self::moduleVisibilityForUser((int)$row['id'])))));
            $row['assigned_apps'] = implode(',', self::resolveAssignedApps(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['assigned_apps'] ?? ''), (string)($crossDefaults['assigned_apps'] ?? '')), (string)($row['assigned_apps'] ?? '')), (string)$row['default_app'], (string)($defaults['assigned_apps'] ?? '')));
            $row['access_profiles'] = implode(',', $effectiveProfiles);
            $row['selected_role_packs'] = implode(',', $profileKeys);
            $row['suite_role_templates'] = implode(',', array_map('strval', array_column((array)($templateSelections['suite_role_templates'] ?? []), 'key')));
            $row['module_permission_templates'] = implode(',', array_map('strval', array_column((array)($templateSelections['module_permission_templates'] ?? []), 'key')));
            $row['suite_role_template_labels'] = SuitePermissionTemplateService::labels((array)($templateSelections['suite_role_templates'] ?? []));
            $row['module_permission_template_labels'] = SuitePermissionTemplateService::labels((array)($templateSelections['module_permission_templates'] ?? []));
            $row['permissions'] = implode(',', self::csvTokens(self::mergeTokenCsv(self::mergeTokenCsv((string)($profileDefaults['permissions'] ?? ''), (string)($crossDefaults['permissions'] ?? '')), (string)($row['permissions'] ?? ($defaults['permissions'] ?? '')))));
            $row['account_status'] = self::normalizeAccountStatus((string)($row['account_status'] ?? 'active')) ?: 'active';
            $row['verification_status'] = self::normalizeVerificationStatus((string)($row['verification_status'] ?? 'ready'));
            $row['cross_functional_access'] = self::serializeCrossFunctionalAccess(
                $crossAccess
            );
            $rawMeDashboardBlocks = (string)($row['me_dashboard_blocks'] ?? '');
            $rawMePluginCards = (string)($row['me_plugin_cards'] ?? '');
            $row['me_dashboard_blocks'] = self::hasExplicitExperienceLayoutNone($rawMeDashboardBlocks)
                ? self::EXPERIENCE_LAYOUT_NONE
                : implode(',', self::normalizeMeDashboardBlocksForAccountType(
                    self::resolveMeDashboardBlocks($rawMeDashboardBlocks),
                    (string)$row['authority_role']
                ));
            $row['me_plugin_cards'] = self::hasExplicitExperienceLayoutNone($rawMePluginCards)
                ? self::EXPERIENCE_LAYOUT_NONE
                : implode(',', self::normalizeMePluginCardsForContext(
                    self::resolveMePluginCards($rawMePluginCards),
                    (string)$row['authority_role'],
                    (string)($row['dashboard_type'] ?? 'my_work'),
                    self::csvTokens((string)($row['assigned_apps'] ?? ''))
                ));
            $row['security_status'] = self::normalizeSecurityStatus(
                (string)($row['security_status'] ?? 'standard'),
                !empty($row['twofa_enabled']),
                $row['verification_status']
            );
            $row['dashboard_mode'] = self::normalizeMode((string)($row['dashboard_mode'] ?? 'auto'));
            $row['default_app_mode'] = self::normalizeMode((string)($row['default_app_mode'] ?? 'auto'));
            $row['landing_mode'] = self::normalizeMode((string)($row['landing_mode'] ?? 'auto'));
            $row['access_profiles_mode'] = self::normalizeMode((string)($row['access_profiles_mode'] ?? 'auto'));
            $row['module_visibility_mode'] = self::normalizeMode((string)($row['module_visibility_mode'] ?? 'auto'));
            if ($row['landing_mode'] === 'auto') {
                $row['default_landing_page'] = '/';
            } elseif (trim((string)$row['default_landing_page']) === '') {
                $row['default_landing_page'] = '/';
            }

            if ($includeDiagnostics) {
                $audit = self::buildEffectivePermissionAudit([
                    'id' => (int)($row['id'] ?? 0),
                    'email' => (string)($row['email'] ?? ''),
                    'role' => (string)($row['role'] ?? ''),
                    'dashboard_type' => (string)($row['dashboard_type'] ?? ''),
                    'authority_role' => (string)($row['authority_role'] ?? ''),
                    'default_app' => (string)($row['default_app'] ?? ''),
                    'account_class' => (string)($row['account_class'] ?? ''),
                    'assigned_apps' => (string)($row['assigned_apps'] ?? ''),
                    'selected_role_packs' => (string)($row['selected_role_packs'] ?? ''),
                    'access_profiles' => (string)($row['access_profiles'] ?? ''),
                    'cross_functional_access' => (string)($row['cross_functional_access'] ?? ''),
                    'dashboard_mode' => (string)($row['dashboard_mode'] ?? 'auto'),
                    'default_app_mode' => (string)($row['default_app_mode'] ?? 'auto'),
                    'landing_mode' => (string)($row['landing_mode'] ?? 'auto'),
                    'access_profiles_mode' => (string)($row['access_profiles_mode'] ?? 'auto'),
                    'module_visibility_mode' => (string)($row['module_visibility_mode'] ?? 'auto'),
                    'permissions' => $rawPermissionCsv,
                    'view_access' => $rawViewCsv,
                    'table_access' => $rawTableCsv,
                    'chart_access' => $rawChartCsv,
                    'duty_codes' => $rawDutyCsv,
                ]);

                $row['permissions'] = implode(',', (array)($audit['effective']['permissions'] ?? []));
                $row['view_access'] = implode(',', (array)($audit['effective']['view_access'] ?? []));
                $row['table_access'] = implode(',', (array)($audit['effective']['table_access'] ?? []));
                $row['chart_access'] = implode(',', (array)($audit['effective']['chart_access'] ?? []));
                $row['duty_codes'] = implode(',', (array)($audit['effective']['duty_codes'] ?? []));
                $row['module_visibility'] = implode(',', (array)($audit['effective']['module_visibility'] ?? []));
                $row['effective_permission_audit'] = $audit;
            } else {
                $row['effective_permission_audit'] = [];
            }

            $analysis = self::analyzeRoleConsistency([
                'role' => (string)($row['role'] ?? ''),
                'authority_role' => (string)($row['authority_role'] ?? ''),
                'role_tier' => (string)($row['role_tier'] ?? ''),
                'dashboard_type' => (string)($row['dashboard_type'] ?? ''),
                'default_app' => (string)($row['default_app'] ?? ''),
                'default_landing_page' => (string)($row['default_landing_page'] ?? ''),
                'dashboard_mode' => (string)($row['dashboard_mode'] ?? 'auto'),
                'default_app_mode' => (string)($row['default_app_mode'] ?? 'auto'),
                'landing_mode' => (string)($row['landing_mode'] ?? 'auto'),
                'cross_functional_access' => (string)($row['cross_functional_access'] ?? ''),
                'account_class' => (string)($row['account_class'] ?? ''),
                'assigned_apps' => (string)($row['assigned_apps'] ?? ''),
                'selected_role_packs' => (string)($row['selected_role_packs'] ?? ''),
                'access_profiles' => (string)($row['access_profiles'] ?? ''),
            ]);
            $row['mapping_warning'] = !empty($analysis['warnings']) ? implode(' | ', (array)$analysis['warnings']) : '';
            $row['has_mapping_warning'] = !empty($analysis['warnings']) ? 1 : 0;
            $row['mapping_labels'] = implode('|', (array)($analysis['labels'] ?? []));
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function buildEffectivePermissionAudit(array $row): array
    {
        $role = (string)($row['role'] ?? '');
        $authorityRole = self::normalizeAccountType(
            (string)($row['authority_role'] ?? ''),
            $role,
            '',
            (string)($row['default_app'] ?? '')
        );
        $defaults = self::suggestedDefaultsForRole($role, $authorityRole);
        $dashboardType = self::normalizeDashboardType((string)($row['dashboard_type'] ?? ''), $authorityRole, $role);
        $defaultApp = self::resolveDefaultApp((string)($row['default_app'] ?? ''), (string)($defaults['default_app'] ?? ''));
        $accountClass = self::normalizeAccountClass((string)($row['account_class'] ?? ''), $authorityRole, $role);
        $accountClassDefaults = self::composeAccountClassDefaults($accountClass);

        $primaryCanonical = self::canonicalMappingForProfileAccount($role, $authorityRole);
        $primaryRolePackKeys = self::resolveAccessProfileKeys(implode(',', (array)($primaryCanonical['recommended_access_profiles'] ?? [])));

        $profileKeys = self::resolveRolePackKeys((string)($row['selected_role_packs'] ?? ($row['access_profiles'] ?? ($defaults['access_profiles'] ?? ''))));
        $profileDefaults = self::composeAccessProfileDefaults($profileKeys);

        $crossAccess = self::parseCrossFunctionalAccess((string)($row['cross_functional_access'] ?? ''));
        $crossDefaults = self::composeCrossFunctionalDefaults($crossAccess);

        $assignedApps = self::resolveAssignedApps(
            self::mergeTokenCsv(
                self::mergeTokenCsv(
                    self::mergeTokenCsv((string)($accountClassDefaults['assigned_apps'] ?? ''), (string)($profileDefaults['assigned_apps'] ?? '')),
                    (string)($crossDefaults['assigned_apps'] ?? '')
                ),
                (string)($row['assigned_apps'] ?? '')
            ),
            $defaultApp,
            (string)($defaults['assigned_apps'] ?? '')
        );
        $assignedAppsDefaults = self::normalizeAssignedAppDefaultsForContext(
            self::composeAssignedAppsDefaults($assignedApps),
            $authorityRole,
            $dashboardType
        );

        $moduleUserCsv = implode(',', self::moduleVisibilityForUser((int)($row['id'] ?? 0)));

        $basePermissions = self::normalizePermissionTokensForContext(self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['permissions'] ?? ''), (string)($assignedAppsDefaults['permissions'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['permissions'] ?? ''), (string)($crossDefaults['permissions'] ?? ''))
        )), 'permission', $authorityRole), $authorityRole, $dashboardType);
        $baseViews = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['view_access'] ?? ''), (string)($assignedAppsDefaults['view_access'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['view_access'] ?? ''), (string)($crossDefaults['view_access'] ?? ''))
        )), 'view', $authorityRole);
        $baseTables = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['table_access'] ?? ''), (string)($assignedAppsDefaults['table_access'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['table_access'] ?? ''), (string)($crossDefaults['table_access'] ?? ''))
        )), 'table', $authorityRole);
        $baseCharts = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['chart_access'] ?? ''), (string)($assignedAppsDefaults['chart_access'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['chart_access'] ?? ''), (string)($crossDefaults['chart_access'] ?? ''))
        )), 'chart', $authorityRole);
        $baseDutyCodes = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['duty_codes'] ?? ''), (string)($assignedAppsDefaults['duty_codes'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['duty_codes'] ?? ''), (string)($crossDefaults['duty_codes'] ?? ''))
        )), 'duty_code', $authorityRole);
        $baseModules = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['module_visibility'] ?? ''), (string)($assignedAppsDefaults['module_visibility'] ?? '')),
            self::mergeTokenCsv((string)($profileDefaults['module_visibility'] ?? ''), (string)($crossDefaults['module_visibility'] ?? ''))
        )), 'module', $authorityRole);

        $manualPermissionExtras = self::tokensDifference(self::csvTokens((string)($row['permissions'] ?? '')), $basePermissions);
        $manualViewExtras = self::tokensDifference(self::csvTokens((string)($row['view_access'] ?? '')), $baseViews);
        $manualTableExtras = self::tokensDifference(self::csvTokens((string)($row['table_access'] ?? '')), $baseTables);
        $manualChartExtras = self::tokensDifference(self::csvTokens((string)($row['chart_access'] ?? '')), $baseCharts);
        $manualDutyExtras = self::tokensDifference(self::csvTokens((string)($row['duty_codes'] ?? '')), $baseDutyCodes);
        $manualModuleExtras = self::tokensDifference(self::csvTokens($moduleUserCsv), $baseModules);

        $effectivePermissions = self::normalizePermissionTokensForContext(
            self::csvTokens(self::mergeTokenCsv(implode(',', $basePermissions), (string)($row['permissions'] ?? ($defaults['permissions'] ?? '')))),
            $authorityRole,
            $dashboardType
        );
        $effectiveViews = self::csvTokens(self::mergeTokenCsv(implode(',', $baseViews), (string)($row['view_access'] ?? '')));
        $effectiveTables = self::csvTokens(self::mergeTokenCsv(implode(',', $baseTables), (string)($row['table_access'] ?? '')));
        $effectiveCharts = self::csvTokens(self::mergeTokenCsv(implode(',', $baseCharts), (string)($row['chart_access'] ?? '')));
        $effectiveDutyCodes = self::csvTokens(self::mergeTokenCsv(implode(',', $baseDutyCodes), (string)($row['duty_codes'] ?? '')));
        $effectiveModules = self::csvTokens(self::mergeTokenCsv(implode(',', $baseModules), $moduleUserCsv));

        $primaryProfileDefaults = self::composeAccessProfileDefaults($primaryRolePackKeys);
        $primaryBaselinePermissions = self::normalizePermissionTokensForContext(self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv((string)($accountClassDefaults['permissions'] ?? ''), (string)($assignedAppsDefaults['permissions'] ?? '')),
            (string)($primaryProfileDefaults['permissions'] ?? '')
        )), 'permission', $authorityRole), $authorityRole, $dashboardType);

        $permissionSources = [];
        $viewSources = [];
        $tableSources = [];
        $chartSources = [];
        $dutySources = [];
        $moduleSources = [];

        self::appendSourceTokens($permissionSources, self::csvTokens((string)($accountClassDefaults['permissions'] ?? '')), 'account_class', $accountClass);
        self::appendSourceTokens($viewSources, self::csvTokens((string)($accountClassDefaults['view_access'] ?? '')), 'account_class', $accountClass);
        self::appendSourceTokens($tableSources, self::csvTokens((string)($accountClassDefaults['table_access'] ?? '')), 'account_class', $accountClass);
        self::appendSourceTokens($chartSources, self::csvTokens((string)($accountClassDefaults['chart_access'] ?? '')), 'account_class', $accountClass);
        self::appendSourceTokens($dutySources, self::csvTokens((string)($accountClassDefaults['duty_codes'] ?? '')), 'account_class', $accountClass);
        self::appendSourceTokens($moduleSources, self::csvTokens((string)($accountClassDefaults['module_visibility'] ?? '')), 'account_class', $accountClass);

        foreach ($assignedApps as $appKey) {
            $appDefaults = self::normalizeAssignedAppDefaultsForContext(
                self::composeAssignedAppsDefaults([$appKey]),
                $authorityRole,
                $dashboardType
            );
            self::appendSourceTokens($permissionSources, self::csvTokens((string)($appDefaults['permissions'] ?? '')), 'assigned_app', $appKey);
            self::appendSourceTokens($viewSources, self::csvTokens((string)($appDefaults['view_access'] ?? '')), 'assigned_app', $appKey);
            self::appendSourceTokens($tableSources, self::csvTokens((string)($appDefaults['table_access'] ?? '')), 'assigned_app', $appKey);
            self::appendSourceTokens($chartSources, self::csvTokens((string)($appDefaults['chart_access'] ?? '')), 'assigned_app', $appKey);
            self::appendSourceTokens($dutySources, self::csvTokens((string)($appDefaults['duty_codes'] ?? '')), 'assigned_app', $appKey);
            self::appendSourceTokens($moduleSources, self::csvTokens((string)($appDefaults['module_visibility'] ?? '')), 'assigned_app', $appKey);
        }

        foreach ($profileKeys as $profileKey) {
            $profileCfg = self::accessProfileRegistry()[$profileKey] ?? null;
            if (!is_array($profileCfg)) {
                continue;
            }
            self::appendSourceTokens($permissionSources, self::normalizeTokenArray((array)($profileCfg['permissions'] ?? [])), 'role_pack', $profileKey);
            self::appendSourceTokens($viewSources, self::normalizeTokenArray((array)($profileCfg['view_access'] ?? [])), 'role_pack', $profileKey);
            self::appendSourceTokens($tableSources, self::normalizeTokenArray((array)($profileCfg['table_access'] ?? [])), 'role_pack', $profileKey);
            self::appendSourceTokens($chartSources, self::normalizeTokenArray((array)($profileCfg['chart_access'] ?? [])), 'role_pack', $profileKey);
            self::appendSourceTokens($dutySources, self::normalizeTokenArray((array)($profileCfg['duty_codes'] ?? [])), 'role_pack', $profileKey);
            self::appendSourceTokens($moduleSources, self::normalizeTokenArray((array)($profileCfg['module_visibility'] ?? [])), 'role_pack', $profileKey);
        }

        foreach ($crossAccess as $bundleKey => $level) {
            $bundleCfg = self::CROSS_FUNCTIONAL_BUNDLES[$bundleKey] ?? null;
            if (!is_array($bundleCfg)) {
                continue;
            }
            $rank = array_search($level, self::CROSS_PERMISSION_LEVELS, true);
            if ($rank === false) {
                $rank = 0;
            }
            foreach (self::CROSS_PERMISSION_LEVELS as $idx => $candidateLevel) {
                if ($idx > $rank) {
                    break;
                }
                $caps = (array)($bundleCfg['levels'][$candidateLevel] ?? []);
                $sourceName = $bundleKey . ':' . $candidateLevel;
                self::appendSourceTokens($permissionSources, self::normalizeTokenArray((array)($caps['permissions'] ?? [])), 'cross_bundle', $sourceName);
                self::appendSourceTokens($viewSources, self::normalizeTokenArray((array)($caps['view_access'] ?? [])), 'cross_bundle', $sourceName);
                self::appendSourceTokens($tableSources, self::normalizeTokenArray((array)($caps['table_access'] ?? [])), 'cross_bundle', $sourceName);
                self::appendSourceTokens($chartSources, self::normalizeTokenArray((array)($caps['chart_access'] ?? [])), 'cross_bundle', $sourceName);
                self::appendSourceTokens($dutySources, self::normalizeTokenArray((array)($caps['duty_codes'] ?? [])), 'cross_bundle', $sourceName);
                self::appendSourceTokens($moduleSources, self::normalizeTokenArray((array)($caps['module_visibility'] ?? [])), 'cross_bundle', $sourceName);
            }
        }

        self::appendSourceTokens($permissionSources, $manualPermissionExtras, 'manual', 'manual override');
        self::appendSourceTokens($viewSources, $manualViewExtras, 'manual', 'manual override');
        self::appendSourceTokens($tableSources, $manualTableExtras, 'manual', 'manual override');
        self::appendSourceTokens($chartSources, $manualChartExtras, 'manual', 'manual override');
        self::appendSourceTokens($dutySources, $manualDutyExtras, 'manual', 'manual override');
        self::appendSourceTokens($moduleSources, $manualModuleExtras, 'manual', 'manual override');

        $effective = [
            'permissions' => $effectivePermissions,
            'view_access' => $effectiveViews,
            'table_access' => $effectiveTables,
            'chart_access' => $effectiveCharts,
            'duty_codes' => $effectiveDutyCodes,
            'module_visibility' => $effectiveModules,
            'cross_functional_access' => $crossAccess,
        ];

        $permissionTrace = self::buildPermissionTraceAudit($effectivePermissions, $permissionSources, $primaryBaselinePermissions, $authorityRole);
        $areaAudit = self::buildAreaAccessAudit($effective, $permissionSources, $primaryBaselinePermissions, $authorityRole);
        $visibilityAudit = self::buildVisibilityAudit($effective, $viewSources, $tableSources, $chartSources, $dutySources, $moduleSources);

        $manualOverrides = [];
        if (self::normalizeMode((string)($row['dashboard_mode'] ?? 'auto')) === 'manual') {
            $manualOverrides[] = 'Dashboard';
        }
        if (self::normalizeMode((string)($row['default_app_mode'] ?? 'auto')) === 'manual') {
            $manualOverrides[] = 'Default App';
        }
        if (self::normalizeMode((string)($row['landing_mode'] ?? 'auto')) === 'manual') {
            $manualOverrides[] = 'Landing';
        }
        if (self::normalizeMode((string)($row['access_profiles_mode'] ?? 'auto')) === 'manual') {
            $manualOverrides[] = 'Permission Packs';
        }
        if (self::normalizeMode((string)($row['module_visibility_mode'] ?? 'auto')) === 'manual') {
            $manualOverrides[] = 'Module Visibility';
        }
        if (!empty($manualPermissionExtras)) {
            $manualOverrides[] = 'Permissions';
        }
        if (!empty($manualViewExtras)) {
            $manualOverrides[] = 'Views';
        }
        if (!empty($manualTableExtras)) {
            $manualOverrides[] = 'Tables';
        }
        if (!empty($manualChartExtras)) {
            $manualOverrides[] = 'Charts';
        }
        if (!empty($manualDutyExtras)) {
            $manualOverrides[] = 'Duty Codes';
        }

        $elevatedTokens = [];
        foreach ($permissionTrace as $traceRow) {
            if (empty($traceRow['granted'])) {
                continue;
            }
            $flags = array_map('strtolower', (array)($traceRow['flags'] ?? []));
            if (in_array('elevated', $flags, true) || in_array('admin-level', $flags, true)) {
                $elevatedTokens[] = (string)($traceRow['token'] ?? '');
            }
        }

        $hasAdminLike = $authorityRole !== 'app_user';
        foreach ($permissionTrace as $traceRow) {
            $flags = array_map('strtolower', (array)($traceRow['flags'] ?? []));
            if (in_array('admin-level', $flags, true) && !empty($traceRow['granted'])) {
                $hasAdminLike = true;
                break;
            }
        }

        $shapeTags = [];
        $additionalPacks = array_values(array_diff($profileKeys, $primaryRolePackKeys));
        if (empty($additionalPacks) && empty($crossAccess) && empty($manualOverrides) && empty($elevatedTokens)) {
            $shapeTags[] = 'Baseline';
        }
        if (!empty($crossAccess)) {
            $shapeTags[] = 'Cross-functional';
        }
        if (!empty($manualOverrides) || !empty($additionalPacks)) {
            $shapeTags[] = 'Custom';
        }
        if (!empty($elevatedTokens)) {
            $shapeTags[] = 'Elevated';
        }
        if ($hasAdminLike) {
            $shapeTags[] = 'Admin-like';
        }
        if (str_contains(strtolower((string)($row['email'] ?? '')), 'test')) {
            $shapeTags[] = 'Test/Super User';
        }

        $crossSummary = [];
        foreach ($crossAccess as $bundleKey => $level) {
            $label = (string)((self::CROSS_FUNCTIONAL_BUNDLES[$bundleKey]['label'] ?? $bundleKey));
            $crossSummary[] = $label . ' (' . ucfirst((string)$level) . ')';
        }

        return [
            'merge_order' => 'account class -> apps -> role packs -> cross bundles -> advanced overrides',
            'effective' => $effective,
            'baseline_deviation' => [
                'account_class' => $accountClass,
                'authority_role' => $authorityRole,
                'operational_role' => self::normalizeOperationalRole($role),
                'dashboard_type' => self::normalizeDashboardType((string)($row['dashboard_type'] ?? ''), $authorityRole, self::roleSlug($role)),
                'default_app' => $defaultApp,
                'default_landing' => '/',
                'baseline_packs' => array_values($primaryRolePackKeys),
                'selected_packs' => array_values($profileKeys),
                'additional_packs' => $additionalPacks,
                'cross_functional' => $crossSummary,
                'manual_overrides' => array_values(array_unique($manualOverrides)),
                'elevated_tokens' => array_slice(array_values(array_unique($elevatedTokens)), 0, 8),
                'shape_tags' => array_values(array_unique($shapeTags)),
            ],
            'areas' => $areaAudit,
            'permission_trace' => $permissionTrace,
            'visibility_trace' => $visibilityAudit,
        ];
    }

    /**
     * @param array<int,string> $effectivePermissions
     * @param array<string,array<int,array<string,string>>> $permissionSources
     * @param array<int,string> $primaryBaselinePermissions
     * @return array<int,array<string,mixed>>
     */
    private static function buildPermissionTraceAudit(array $effectivePermissions, array $permissionSources, array $primaryBaselinePermissions, string $authorityRole): array
    {
        $catalog = [];
        foreach (self::ACCOUNT_CLASS_BASELINES as $cfg) {
            foreach (self::csvTokens((string)($cfg['permissions'] ?? '')) as $token) {
                $catalog[$token] = $token;
            }
        }
        foreach (self::accessProfileRegistry() as $cfg) {
            foreach (self::normalizeTokenArray((array)($cfg['permissions'] ?? [])) as $token) {
                $catalog[$token] = $token;
            }
        }
        foreach (self::CROSS_FUNCTIONAL_BUNDLES as $bundleCfg) {
            foreach ((array)($bundleCfg['levels'] ?? []) as $caps) {
                foreach (self::normalizeTokenArray((array)($caps['permissions'] ?? [])) as $token) {
                    $catalog[$token] = $token;
                }
            }
        }
        foreach ($effectivePermissions as $token) {
            $catalog[$token] = $token;
        }

        $effectiveSet = array_fill_keys($effectivePermissions, true);
        $baselineSet = array_fill_keys($primaryBaselinePermissions, true);
        ksort($catalog);

        $rows = [];
        foreach (array_values($catalog) as $token) {
            $granted = isset($effectiveSet[$token]);
            $sources = $granted ? (array)($permissionSources[$token] ?? []) : [];
            $primarySource = self::primarySource($sources);
            $flags = self::classifyFlags($token, $sources, isset($baselineSet[$token]), $authorityRole);
            $rows[] = [
                'token' => $token,
                'granted' => $granted,
                'source_type' => (string)($primarySource['type'] ?? '-'),
                'source_name' => (string)($primarySource['name'] ?? '-'),
                'authority' => self::authorityLevelForToken($token),
                'sources' => self::sourceLabels($sources),
                'flags' => $flags,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $effective
     * @param array<string,array<int,array<string,string>>> $permissionSources
     * @param array<int,string> $primaryBaselinePermissions
     * @return array<int,array<string,mixed>>
     */
    private static function buildAreaAccessAudit(array $effective, array $permissionSources, array $primaryBaselinePermissions, string $authorityRole): array
    {
        $permissionSet = array_fill_keys((array)($effective['permissions'] ?? []), true);
        $baselineSet = array_fill_keys($primaryBaselinePermissions, true);
        $crossGrants = (array)($effective['cross_functional_access'] ?? []);

        $areas = [
            'Order / Planning' => [
                'view' => ['daily_orders.360.view', 'products.360.view'],
                'work' => ['workflow.production_plan.submit', 'workflow.assembly_plan.submit'],
                'approve' => ['workflow.production_plan.approve', 'workflow.assembly_plan.approve'],
                'manage' => ['ops.cockpit.view'],
            ],
            'Production' => [
                'view' => ['machines.leader.view'],
                'work' => ['workflow.production_plan.submit'],
                'approve' => ['workflow.production_plan.approve'],
                'manage' => ['workflow.production_plan.cancel', 'workflow.production_plan.hold', 'workflow.production_plan.resume'],
            ],
            'Assembly' => [
                'view' => ['workflow.assembly_plan.submit'],
                'work' => ['workflow.assembly_plan.submit'],
                'approve' => ['workflow.assembly_plan.approve'],
                'manage' => ['workflow.assembly_plan.cancel', 'workflow.assembly_plan.hold', 'workflow.assembly_plan.resume'],
            ],
            'QC' => [
                'view' => ['qc_entries.leader.view'],
                'work' => ['workflow.qc_entry.submit'],
                'approve' => ['workflow.qc_entry.approve'],
                'manage' => ['workflow.qc_entry.cancel'],
            ],
            'Dispatch' => [
                'view' => ['dispatch_entries.leader.view'],
                'work' => ['workflow.dispatch_entry.submit', 'dispatch_entries.quick_status', 'dispatch_entries.transition'],
                'approve' => ['workflow.dispatch_entry.approve', 'workflow.dispatch_entry.handoff'],
                'manage' => ['workflow.dispatch_entry.cancel', 'workflow.dispatch_entry.hold', 'workflow.dispatch_entry.resume'],
            ],
            'Ops' => [
                'view' => ['ops.my_work.view'],
                'work' => ['ops.my_work.view'],
                'approve' => ['ops.approval_inbox.view'],
                'manage' => ['ops.notifications.manage'],
            ],
            'Admin' => [
                'view' => ['admin.tools.access'],
                'work' => ['acl.manage'],
                'approve' => ['acl.manage'],
                'manage' => ['admin.tools.access', 'acl.manage'],
            ],
            'Reporting' => [
                'view' => ['products.360.view', 'daily_orders.360.view'],
                'work' => [],
                'approve' => [],
                'manage' => ['ops.cockpit.view'],
            ],
            'Notifications' => [
                'view' => ['ops.notifications.view'],
                'work' => [],
                'approve' => [],
                'manage' => ['ops.notifications.manage'],
            ],
            'Approvals' => [
                'view' => ['ops.approval_inbox.view'],
                'work' => [],
                'approve' => ['workflow.production_plan.approve', 'workflow.assembly_plan.approve', 'workflow.qc_entry.approve', 'workflow.dispatch_entry.approve'],
                'manage' => ['workflow.production_plan.finalize', 'workflow.assembly_plan.finalize', 'workflow.qc_entry.finalize', 'workflow.dispatch_entry.finalize'],
            ],
        ];

        $rows = [];
        foreach ($areas as $areaName => $rules) {
            $sourceTokens = [];
            $cells = [];
            foreach (['view', 'work', 'approve', 'manage'] as $action) {
                $granted = false;
                foreach ((array)($rules[$action] ?? []) as $token) {
                    $tk = strtolower(trim((string)$token));
                    if ($tk === '' || !isset($permissionSet[$tk])) {
                        continue;
                    }
                    $granted = true;
                    $sourceTokens[] = $tk;
                }
                $cells[$action] = $granted;
            }

            $sources = [];
            foreach (array_values(array_unique($sourceTokens)) as $token) {
                foreach ((array)($permissionSources[$token] ?? []) as $src) {
                    $sources[] = $src;
                }
            }

            $flags = [];
            foreach (array_values(array_unique($sourceTokens)) as $token) {
                $tokenFlags = self::classifyFlags($token, (array)($permissionSources[$token] ?? []), isset($baselineSet[$token]), $authorityRole);
                foreach ($tokenFlags as $flag) {
                    $flags[$flag] = $flag;
                }
            }
            if (!empty($crossGrants)) {
                $areaKey = strtolower(str_replace([' / ', ' '], ['_', '_'], $areaName));
                foreach ($crossGrants as $bundle => $_level) {
                    if (str_contains((string)$bundle, $areaKey === 'order_planning' ? 'order' : $areaKey)) {
                        $flags['Cross-functional'] = 'Cross-functional';
                    }
                }
            }

            $rows[] = [
                'area' => $areaName,
                'view' => (bool)$cells['view'],
                'work' => (bool)$cells['work'],
                'approve' => (bool)$cells['approve'],
                'manage' => (bool)$cells['manage'],
                'source' => implode(', ', self::sourceLabels($sources)),
                'flags' => array_values($flags),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $effective
     * @param array<string,array<int,array<string,string>>> $viewSources
     * @param array<string,array<int,array<string,string>>> $tableSources
     * @param array<string,array<int,array<string,string>>> $chartSources
     * @param array<string,array<int,array<string,string>>> $dutySources
     * @param array<string,array<int,array<string,string>>> $moduleSources
     * @return array<int,array<string,mixed>>
     */
    private static function buildVisibilityAudit(array $effective, array $viewSources, array $tableSources, array $chartSources, array $dutySources, array $moduleSources): array
    {
        $rows = [];

        $surfaceMap = [
            'Module' => ['tokens' => (array)($effective['module_visibility'] ?? []), 'sources' => $moduleSources],
            'View' => ['tokens' => (array)($effective['view_access'] ?? []), 'sources' => $viewSources],
            'Table' => ['tokens' => (array)($effective['table_access'] ?? []), 'sources' => $tableSources],
            'Chart' => ['tokens' => (array)($effective['chart_access'] ?? []), 'sources' => $chartSources],
            'Duty Code' => ['tokens' => (array)($effective['duty_codes'] ?? []), 'sources' => $dutySources],
        ];

        foreach ($surfaceMap as $surfaceType => $cfg) {
            $grantedSet = array_fill_keys((array)($cfg['tokens'] ?? []), true);
            $sourceTokens = [];
            foreach ((array)($cfg['sources'] ?? []) as $token => $_list) {
                $sourceTokens[(string)$token] = (string)$token;
            }
            foreach ((array)($cfg['tokens'] ?? []) as $token) {
                $sourceTokens[(string)$token] = (string)$token;
            }
            ksort($sourceTokens);
            foreach (array_values($sourceTokens) as $token) {
                $sources = (array)(($cfg['sources'] ?? [])[$token] ?? []);
                $rows[] = [
                    'surface_type' => $surfaceType,
                    'token' => $token,
                    'granted' => isset($grantedSet[$token]),
                    'source' => implode(', ', self::sourceLabels($sources)),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string,array<int,array<string,string>>> $sourceMap
     * @param array<int,string> $tokens
     */
    private static function appendSourceTokens(array &$sourceMap, array $tokens, string $sourceType, string $sourceName): void
    {
        foreach ($tokens as $token) {
            $tk = strtolower(trim((string)$token));
            if ($tk === '') {
                continue;
            }
            if (!isset($sourceMap[$tk])) {
                $sourceMap[$tk] = [];
            }
            $dedupeKey = $sourceType . '|' . $sourceName;
            $seen = false;
            foreach ($sourceMap[$tk] as $entry) {
                if (((string)($entry['type'] ?? '')) . '|' . ((string)($entry['name'] ?? '')) === $dedupeKey) {
                    $seen = true;
                    break;
                }
            }
            if ($seen) {
                continue;
            }
            $sourceMap[$tk][] = ['type' => $sourceType, 'name' => $sourceName];
        }
    }

    /**
     * @param array<int,array<string,string>> $sources
     * @return array<string,string>
     */
    private static function primarySource(array $sources): array
    {
        if (empty($sources)) {
            return [];
        }
        usort($sources, static function (array $a, array $b): int {
            $aType = (string)($a['type'] ?? '');
            $bType = (string)($b['type'] ?? '');
            $aRank = self::SOURCE_PRIORITY[$aType] ?? 0;
            $bRank = self::SOURCE_PRIORITY[$bType] ?? 0;
            if ($aRank === $bRank) {
                return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            }
            return $bRank <=> $aRank;
        });
        return $sources[0] ?? [];
    }

    /**
     * @param array<int,array<string,string>> $sources
     * @return array<int,string>
     */
    private static function sourceLabels(array $sources): array
    {
        $labels = [];
        foreach ($sources as $src) {
            $type = (string)($src['type'] ?? '');
            $name = (string)($src['name'] ?? '');
            if ($type === '' || $name === '') {
                continue;
            }
            $prefix = match ($type) {
                'assigned_app' => 'assigned_app',
                'role_pack' => 'role_pack',
                'account_class' => 'account_class',
                'manual', 'advanced_override' => 'manual_override',
                'cross_bundle' => 'cross_bundle',
                default => $type,
            };
            $labels[$type . ':' . $name] = $prefix . ':' . $name;
        }
        return array_values($labels);
    }

    private static function normalizeOperatorViewsCsv(string $csv): ?string
    {
        $allowed = array_flip([
            'dashboard', 'work-entry', 'data-exchange', 'critical', 'recent', 'tasks',
            'production', 'demand', 'orders', 'parts', 'coverage', 'machines',
            'processing', 'assembly', 'qc',
            'fulfillment', 'preparation', 'dispatch',
            'materials', 'handoff', 'account', 'notifications', 'messages', 'preferences',
            'sbaio',
        ]);
        $aliases = [
            'parts-detail' => 'parts',
            'dispatch-detail' => 'dispatch',
            'dispatch-adapter' => 'dispatch',
            'alerts' => 'notifications',
        ];
        $out = [];
        foreach (self::csvTokens($csv) as $token) {
            $token = $aliases[$token] ?? $token;
            if (isset($allowed[$token])) {
                $out[$token] = $token;
            }
        }
        $out['dashboard'] = 'dashboard';
        $out['account'] = 'account';
        return self::nullIfEmpty(implode(',', array_values($out)));
    }

    /**
     * @param array<int,array<string,string>> $sources
     * @return array<int,string>
     */
    private static function classifyFlags(string $token, array $sources, bool $inBaseline, string $authorityRole): array
    {
        $flags = [];
        foreach ($sources as $src) {
            $type = (string)($src['type'] ?? '');
            if ($type === 'cross_bundle') {
                $flags['Cross-functional'] = 'Cross-functional';
            }
            if ($type === 'manual' || $type === 'advanced_override') {
                $flags['Manual override'] = 'Manual override';
            }
        }
        if (!$inBaseline && $authorityRole === 'app_user') {
            $flags['Elevated'] = 'Elevated';
        }
        if (
            str_starts_with($token, 'admin.')
            || $token === 'acl.manage'
            || str_contains($token, '.cancel')
            || str_contains($token, '.finalize')
            || str_contains($token, '.hold')
            || str_contains($token, '.resume')
        ) {
            $flags['Admin-level'] = 'Admin-level';
        }
        return array_values($flags);
    }

    private static function authorityLevelForToken(string $token): string
    {
        if (
            str_starts_with($token, 'admin.')
            || $token === 'acl.manage'
            || str_contains($token, '.cancel')
            || str_contains($token, '.finalize')
        ) {
            return 'High';
        }
        if (str_contains($token, '.approve') || str_contains($token, '.reject') || str_contains($token, '.handoff')) {
            return 'Elevated';
        }
        if (str_contains($token, '.submit') || str_contains($token, '.manage')) {
            return 'Standard';
        }
        return 'View';
    }

    /**
     * @return array<int,string>
     */
    private static function allowedSurfaceClassesForAccountType(string $authorityRole): array
    {
        return self::ALLOWED_SURFACE_CLASSES_BY_ACCOUNT[$authorityRole]
            ?? self::ALLOWED_SURFACE_CLASSES_BY_ACCOUNT['app_user'];
    }

    private static function surfaceClassForToken(string $surfaceType, string $token): string
    {
        $tk = strtolower(trim($token));
        if ($tk === '') {
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'permission') {
            if (in_array($tk, self::GOVERNANCE_PERMISSION_TOKENS, true) || str_starts_with($tk, 'admin.')) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (
                str_contains($tk, '.assign_owner')
                || str_contains($tk, '.escalate')
                || str_contains($tk, '.approve')
                || str_contains($tk, '.reject')
                || str_contains($tk, '.finalize')
                || str_contains($tk, '.cancel')
                || str_contains($tk, '.hold')
                || str_contains($tk, '.resume')
                || str_contains($tk, '.override_lock')
                || str_contains($tk, '.handoff')
            ) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            if (in_array($tk, self::SUPERVISORY_PERMISSION_TOKENS, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'duty_code') {
            if (in_array($tk, self::GOVERNANCE_DUTY_CODES, true)) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (in_array($tk, self::SUPERVISORY_DUTY_CODES, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'view') {
            if (in_array($tk, self::GOVERNANCE_VIEW_TOKENS, true)) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (in_array($tk, self::SUPERVISORY_VIEW_TOKENS, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'table') {
            if (in_array($tk, self::GOVERNANCE_TABLE_TOKENS, true)) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (in_array($tk, self::SUPERVISORY_TABLE_TOKENS, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'chart') {
            if (in_array($tk, self::GOVERNANCE_CHART_TOKENS, true)) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (in_array($tk, self::SUPERVISORY_CHART_TOKENS, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        if ($surfaceType === 'module') {
            if (in_array($tk, self::GOVERNANCE_MODULE_TOKENS, true)) {
                return self::SURFACE_CLASS_GOVERNANCE;
            }
            if (in_array($tk, self::SUPERVISORY_MODULE_TOKENS, true)) {
                return self::SURFACE_CLASS_SUPERVISORY;
            }
            return self::SURFACE_CLASS_OPERATIONAL;
        }

        return self::SURFACE_CLASS_OPERATIONAL;
    }

    /**
     * @param array<int,string> $tokens
     * @return array<int,string>
     */
    private static function filterGeneratedTokensForAccountType(array $tokens, string $surfaceType, string $authorityRole): array
    {
        $allowedClasses = array_fill_keys(self::allowedSurfaceClassesForAccountType($authorityRole), true);
        $filtered = [];
        foreach ($tokens as $token) {
            $tk = strtolower(trim((string)$token));
            if ($tk === '') {
                continue;
            }
            $class = self::surfaceClassForToken($surfaceType, $tk);
            if (isset($allowedClasses[$class])) {
                $filtered[$tk] = $tk;
            }
        }
        return array_values($filtered);
    }

    /**
     * @param array<int,string> $left
     * @param array<int,string> $right
     * @return array<int,string>
     */
    private static function tokensDifference(array $left, array $right): array
    {
        $rightSet = array_fill_keys($right, true);
        $out = [];
        foreach ($left as $token) {
            if (!isset($rightSet[$token])) {
                $out[$token] = $token;
            }
        }
        return array_values($out);
    }

    /**
     * @param array<int,mixed> $tokens
     * @return array<int,string>
     */
    private static function normalizeTokenArray(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $token) {
            $v = strtolower(trim((string)$token));
            if ($v === '') {
                continue;
            }
            $out[$v] = $v;
        }
        return array_values($out);
    }

    public static function listLifecycleUsers(array $filters = []): array
    {
        self::ensureSchema();

        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(LOWER(u.email) LIKE ? OR LOWER(COALESCE(u.display_name, '')) LIKE ? OR LOWER(COALESCE(u.username, '')) LIKE ?)";
            $needle = '%' . strtolower($search) . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        $accountClassFilter = strtolower(trim((string)($filters['account_class'] ?? '')));
        if ($accountClassFilter !== '') {
            $where[] = 'LOWER(COALESCE(NULLIF(TRIM(a.account_class), ""), "")) = ?';
            $params[] = $accountClassFilter;
        }

        $assignedAppFilter = strtolower(trim((string)($filters['assigned_app'] ?? '')));
        if ($assignedAppFilter !== '') {
            $where[] = 'LOWER(COALESCE(a.assigned_apps, "")) LIKE ?';
            $params[] = '%' . $assignedAppFilter . '%';
        }

        $rolePackFilter = strtolower(trim((string)($filters['role_pack'] ?? '')));
        if ($rolePackFilter !== '') {
            $where[] = '(LOWER(COALESCE(a.selected_role_packs, "")) LIKE ? OR LOWER(COALESCE(a.access_profiles, "")) LIKE ?)';
            $params[] = '%' . $rolePackFilter . '%';
            $params[] = '%' . $rolePackFilter . '%';
        }

        $status = self::normalizeAccountStatus((string)($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $where[] = "LOWER(COALESCE(NULLIF(TRIM(u.account_status), ''), 'active')) = ?";
            $params[] = $status;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $rows = DB::fetchAll(
            "SELECT u.id,
                    u.email,
                    COALESCE(u.display_name, '') AS display_name,
                    COALESCE(u.username, '') AS username,
                    COALESCE(u.department, '') AS department,
                    u.role,
                    u.role AS operational_role,
                    COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                    COALESCE(NULLIF(TRIM(u.role_tier), ''), '') AS role_tier,
                    COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') AS account_status,
                    COALESCE(NULLIF(TRIM(u.verification_status), ''), 'ready') AS verification_status,
                    COALESCE(NULLIF(TRIM(u.security_status), ''), 'standard') AS security_status,
                    COALESCE(a.dashboard_type, '') AS dashboard_type,
                    COALESCE(a.default_app, '') AS default_app,
                    u.created_at
             FROM users u
             LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
             {$whereSql}
             ORDER BY u.email ASC",
            $params
        );

        foreach ($rows as &$row) {
            $row['authority_role'] = self::normalizeAccountType(
                (string)($row['authority_role'] ?? ''),
                (string)($row['role'] ?? ''),
                (string)($row['role_tier'] ?? ''),
                (string)($row['default_app'] ?? '')
            );
            $row['operational_role'] = self::normalizeOperationalRole((string)($row['operational_role'] ?? ($row['role'] ?? '')));
            $row['verification_status'] = self::normalizeVerificationStatus((string)($row['verification_status'] ?? 'ready'));
            $row['security_status'] = self::normalizeSecurityStatus((string)($row['security_status'] ?? 'standard'));
        }
        unset($row);

        return $rows;
    }

    public static function createUserFromInput(array $input, ?array $actor): array
    {
        self::ensureSchema();

        $email = strtolower(trim((string)($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $exists = DB::fetchOne('SELECT id FROM users WHERE email=? LIMIT 1', [$email]);
        if ($exists) {
            throw new \RuntimeException('Email already exists.');
        }

        $authorityRole = self::normalizeAccountType(
            (string)($input['authority_role'] ?? ''),
            (string)($input['operational_role'] ?? ($input['role'] ?? '')),
            (string)($input['role_tier'] ?? ''),
            (string)($input['default_app'] ?? '')
        );
        $role = self::resolveProvisioningRole($input, $authorityRole);
        $accountClass = self::normalizeAccountClass((string)($input['account_class'] ?? ''), $authorityRole, $role);
        $authorityRole = self::accountTypeForAccountClass($accountClass, $authorityRole);
        $roleTier = self::legacyTierForAccountType($authorityRole);
        $status = self::normalizeAccountStatus((string)($input['account_status'] ?? 'active'));
        if ($status === '') {
            $status = 'active';
        }

        $displayName = self::sanitizeOptionalText((string)($input['display_name'] ?? ''), 190);
        $username = self::sanitizeOptionalText((string)($input['username'] ?? ''), 120);
        $department = self::sanitizeOptionalText((string)($input['department'] ?? ''), 120);
        $actorId = (int)($actor['id'] ?? 0);
        $actorId = (int)($actor['id'] ?? 0);
        $actorId = (int)($actor['id'] ?? 0);
        $actorLabel = self::actorLabel($actor);

        $sendSetupLink = !empty($input['send_setup_link']);
        $requirePasswordSetup = $sendSetupLink || !empty($input['require_password_setup']);
        $temporaryPassword = self::generateTemporaryPassword();
        $passwordHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $verificationStatus = $sendSetupLink ? 'invite_pending' : ($requirePasswordSetup ? 'setup_pending' : 'ready');
        $securityStatus = $requirePasswordSetup ? 'password_setup_pending' : 'standard';
        if ($requirePasswordSetup && $status !== 'disabled') {
            $status = 'pending';
        }

        DB::query(
            'INSERT INTO users (email, display_name, username, department, password_hash, role, role_tier, authority_role, account_status, verification_status, security_status, twofa_enabled, twofa_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)',
            [
                $email,
                self::nullIfEmpty($displayName),
                self::nullIfEmpty($username),
                self::nullIfEmpty($department),
                $passwordHash,
                $role,
                $roleTier,
                $authorityRole,
                $status,
                $verificationStatus,
                $securityStatus,
            ]
        );

        $created = DB::fetchOne('SELECT id FROM users WHERE email=? LIMIT 1', [$email]);
        $userId = (int)($created['id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException('User creation failed.');
        }

        $savePayload = $input;
        $savePayload['user_id'] = $userId;
        $savePayload['account_class'] = $accountClass;
        $savePayload['authority_role'] = $authorityRole;
        if (trim((string)($savePayload['operational_role'] ?? '')) === '') {
            $savePayload['operational_role'] = $role;
        }
        self::saveFromInput($savePayload, $actor);

        $setupLink = '';
        $inviteSent = false;
        $inviteError = '';
        if ($sendSetupLink) {
            try {
                $inviteService = new InviteLifecycleService();
                $invite = $inviteService->issueSetupLinkForUser($userId, true);
                $setupLink = (string)($invite['setup_url'] ?? '');
                $inviteSent = true;
            } catch (\Throwable $e) {
                $inviteError = $e->getMessage();
            }
        }

        return [
            'id' => $userId,
            'email' => $email,
            'status' => $status,
            'verification_status' => $verificationStatus,
            'security_status' => $securityStatus,
            'setup_link' => $setupLink,
            'invite_sent' => $inviteSent,
            'invite_error' => $inviteError,
        ];
    }

    public static function updateBasicUserFromInput(array $input, ?array $actor): void
    {
        self::ensureSchema();

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        $current = DB::fetchOne('SELECT id, email, role, authority_role, account_status FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!$current) {
            throw new \RuntimeException('User not found.');
        }

        $email = strtolower(trim((string)($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $dup = DB::fetchOne('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1', [$email, $userId]);
        if ($dup) {
            throw new \RuntimeException('Email already exists.');
        }

        $status = self::normalizeAccountStatus((string)($input['account_status'] ?? 'active'));
        if ($status === '') {
            $status = 'active';
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId > 0 && $actorId === $userId && $status !== 'active') {
            throw new \RuntimeException('Cannot deactivate your own account.');
        }

        self::assertLastPlatformAdminActiveGuard($userId, (string)($current['authority_role'] ?? ''), $status);

        $displayName = self::sanitizeOptionalText((string)($input['display_name'] ?? ''), 190);
        $username = self::sanitizeOptionalText((string)($input['username'] ?? ''), 120);
        $department = self::sanitizeOptionalText((string)($input['department'] ?? ''), 120);

        DB::query(
            'UPDATE users SET email=?, display_name=?, username=?, department=?, account_status=? WHERE id=? LIMIT 1',
            [
                $email,
                self::nullIfEmpty($displayName),
                self::nullIfEmpty($username),
                self::nullIfEmpty($department),
                $status,
                $userId,
            ]
        );
    }

    public static function setUserStatusFromInput(array $input, ?array $actor): string
    {
        self::ensureSchema();

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        $nextStatus = self::normalizeAccountStatus((string)($input['account_status'] ?? ''));
        if (!in_array($nextStatus, ['active', 'disabled', 'pending'], true)) {
            throw new \InvalidArgumentException('Invalid account status.');
        }

        $current = DB::fetchOne('SELECT id, role, authority_role FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!$current) {
            throw new \RuntimeException('User not found.');
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId > 0 && $actorId === $userId && $nextStatus !== 'active') {
            throw new \RuntimeException('Cannot deactivate your own account.');
        }

        self::assertLastPlatformAdminActiveGuard($userId, (string)($current['authority_role'] ?? ''), $nextStatus);

        DB::query('UPDATE users SET account_status=? WHERE id=? LIMIT 1', [$nextStatus, $userId]);
        return $nextStatus;
    }

    public static function saveFromInput(array $input, ?array $actor): void
    {
        self::ensureSchema();

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        $user = DB::fetchOne('SELECT id, role, authority_role FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorLabel = self::actorLabel($actor);
        $workspaceProfileProvided = array_key_exists('workspace_profile_key', $input);
        $workspaceProfileKey = strtolower(trim((string)($input['workspace_profile_key'] ?? '')));
        if ($workspaceProfileProvided && $workspaceProfileKey !== '' && !preg_match('/^[a-z0-9_]+$/', $workspaceProfileKey)) {
            throw new \InvalidArgumentException('Invalid workspace profile key format.');
        }
        if ($workspaceProfileProvided && $workspaceProfileKey !== '') {
            $profileRow = DB::fetchOne(
                "SELECT profile_key FROM workspace_profiles WHERE profile_key = ? AND is_active = 1 LIMIT 1",
                [$workspaceProfileKey]
            );
            if (!is_array($profileRow) || $profileRow === []) {
                throw new \InvalidArgumentException('Workspace profile not found or is inactive.');
            }
        }

        $accountTypeRaw = self::normalizeAccountType(
            (string)($input['authority_role'] ?? ''),
            (string)($input['operational_role'] ?? ($input['legacy_role'] ?? (string)($user['role'] ?? ''))),
            (string)($input['role_tier'] ?? ''),
            (string)($input['default_app'] ?? '')
        );

        // FIX: Make authority_role (from top Primary Access select) authoritative
        // Auto-sync account_class to match when authority_role is explicitly provided
        $accountTypeExplicit = (string)($input['authority_role'] ?? '');
        $accountTypeWasExplicit = $accountTypeExplicit !== '' && in_array($accountTypeExplicit, self::VALID_ACCOUNT_TYPES, true);
        $provisioningRole = self::resolveProvisioningRole($input, $accountTypeRaw, (string)($user['role'] ?? ''));

        if ($accountTypeWasExplicit) {
            // User explicitly chose via top Primary Access select - make it authoritative
            $authorityRole = $accountTypeRaw;
            // Auto-sync account_class to match the authority_role
            $accountClass = self::accountClassForAccountType($authorityRole);
        } else {
            // authority_role was not explicitly provided - derive from account_class or role (legacy path)
            $accountClass = self::normalizeAccountClass((string)($input['account_class'] ?? ''), $accountTypeRaw, $provisioningRole);
            $authorityRole = self::accountTypeForAccountClass($accountClass, $accountTypeRaw);
        }

        $assignableRole = self::resolveProvisioningRole($input, $authorityRole, (string)($user['role'] ?? ''));
        $targetRoleSlug = self::roleSlug($assignableRole);

        $defaults = self::suggestedDefaultsForRole($assignableRole, $authorityRole);
        $canonical = self::canonicalMappingForProfileAccount($assignableRole, $authorityRole);
        $accountClassDefaults = self::composeAccountClassDefaults($accountClass);

        $dashboardMode = self::normalizeMode((string)($input['dashboard_mode'] ?? 'auto'));
        $defaultAppMode = self::normalizeMode((string)($input['default_app_mode'] ?? 'auto'));
        $landingMode = self::normalizeMode((string)($input['landing_mode'] ?? 'auto'));
        $accessProfilesMode = self::normalizeMode((string)($input['access_profiles_mode'] ?? 'auto'));
        $moduleVisibilityMode = self::normalizeMode((string)($input['module_visibility_mode'] ?? 'auto'));

        $requestedDashboard = strtolower(trim((string)($input['dashboard_type'] ?? '')));
        if ($dashboardMode === 'auto' && $canonical !== null) {
            $requestedDashboard = strtolower(trim((string)($canonical['dashboard_type'] ?? '')));
        }
        if ($requestedDashboard === '') {
            $requestedDashboard = strtolower(trim((string)($defaults['dashboard_type'] ?? 'my_work')));
        }
        $provisionalDashboardType = self::normalizeDashboardType($requestedDashboard, $authorityRole, $targetRoleSlug);
        if ($provisionalDashboardType === '') {
            $provisionalDashboardType = self::normalizeDashboardType((string)($defaults['dashboard_type'] ?? 'my_work'), $authorityRole, $targetRoleSlug);
        }

        $templateSelectionCsv = self::mergeTokenCsv(
            (string)($input['selected_role_packs'] ?? ''),
            self::mergeTokenCsv((string)($input['suite_role_templates'] ?? ''), (string)($input['module_permission_templates'] ?? ''))
        );
        $selectedRolePackKeys = self::resolveRolePackKeys($templateSelectionCsv);
        $profileKeys = [];
        if ($accessProfilesMode === 'auto' && $canonical !== null) {
            $profileKeys = self::resolveAccessProfileKeys(implode(',', (array)($canonical['recommended_access_profiles'] ?? [])));
        }
        if (!empty($selectedRolePackKeys)) {
            $profileKeys = $selectedRolePackKeys;
        }
        if (empty($profileKeys)) {
            $profileKeys = self::resolveAccessProfileKeys((string)($input['access_profiles'] ?? ($defaults['access_profiles'] ?? '')));
        }
        $profileKeys = self::normalizeAccessProfileKeysForContext($profileKeys, $authorityRole, $provisionalDashboardType);
        $profileDefaults = self::composeAccessProfileDefaults($profileKeys);
        $crossAccess = self::parseCrossFunctionalAccess((string)($input['cross_functional_access'] ?? ''));
        $crossDefaults = self::composeCrossFunctionalDefaults($crossAccess);

        $roleTier = self::legacyTierForAccountType($authorityRole);
        if ($requestedDashboard === '') {
            $requestedDashboard = strtolower(trim((string)($profileDefaults['default_dashboard_type'] ?? ($defaults['dashboard_type'] ?? ''))));
        }
        $dashboardType = $dashboardMode === 'auto'
            ? self::normalizeDashboardType($requestedDashboard, $authorityRole, $targetRoleSlug)
            : $requestedDashboard;
        if ($dashboardType === '') {
            $dashboardType = self::normalizeDashboardType((string)($defaults['dashboard_type'] ?? 'my_work'), $authorityRole, $targetRoleSlug);
        }

        $defaultApp = self::nullIfEmpty((string)($input['default_app'] ?? ''));
        if ($defaultAppMode === 'auto' && $canonical !== null) {
            $defaultApp = self::nullIfEmpty((string)($canonical['default_app'] ?? ''));
        }
        if ($defaultApp === null) {
            $defaultApp = self::nullIfEmpty((string)($profileDefaults['default_app'] ?? ($defaults['default_app'] ?? '')));
        }
        // Platform-admin governance can carry cross-app assignments; keep default app stable.
        // Coerce unsupported manual picks back to platform to avoid false save failures.
        if ($authorityRole === 'platform_admin' && $defaultApp !== null) {
            $normalizedDefaultApp = strtolower(trim($defaultApp));
            if (!in_array($normalizedDefaultApp, ['platform', 'erp_core'], true)) {
                $defaultApp = 'platform';
            }
        }

        $defaultLanding = self::nullIfEmpty((string)($input['default_landing_page'] ?? ''));
        if ($landingMode === 'auto') {
            $defaultLanding = '/';
        }
        if ($defaultLanding === null) {
            $defaultLanding = self::routeForDashboardType($dashboardType);
        }

        if (!self::isDashboardCompatibleWithAccount($dashboardType, $authorityRole)) {
            throw new \InvalidArgumentException('Invalid combination: selected dashboard is incompatible with account type.');
        }
        if (!self::isLandingCompatibleWithDashboard((string)($defaultLanding ?? ''), $dashboardType)) {
            throw new \InvalidArgumentException('Route mismatch: selected landing route is incompatible with dashboard.');
        }
        if (!self::isDefaultAppCompatible((string)($defaultApp ?? ''), $authorityRole, $canonical)) {
            $defaultAppLabel = self::appDisplayLabel((string)($defaultApp ?? ''));
            $authorityLabel = match ($authorityRole) {
                'platform_admin' => 'Platform Admin',
                'app_admin' => 'App Admin',
                default => 'App User',
            };

            $profileLabel = 'default profile model';
            if (!empty($profileKeys)) {
                $registry = self::accessProfileRegistry();
                $firstProfile = strtolower(trim((string)$profileKeys[0]));
                if ($firstProfile !== '' && isset($registry[$firstProfile])) {
                    $profileLabel = (string)($registry[$firstProfile]['label'] ?? $firstProfile);
                } elseif ($firstProfile !== '') {
                    $profileLabel = $firstProfile;
                }
            }

            $requiredDefault = strtolower(trim((string)($canonical['default_app'] ?? '')));
            if ($requiredDefault !== '') {
                $requiredLabel = self::appDisplayLabel($requiredDefault);
                throw new \InvalidArgumentException(
                    'Cannot save: profile `' . $profileLabel . '` requires default app `' . $requiredLabel . '`, but current default app is `' . $defaultAppLabel . '` for access authority `' . $authorityLabel . '`.'
                );
            }

            throw new \InvalidArgumentException(
                'Cannot save: default app `' . $defaultAppLabel . '` is incompatible with profile `' . $profileLabel . '` for access authority `' . $authorityLabel . '`.'
            );
        }

        // Merge order (deterministic): account class baseline -> assigned apps -> selected role packs -> cross grants -> manual overrides.
        $assignedAppList = self::resolveAssignedApps(
            self::mergeTokenCsv(
                self::mergeTokenCsv(
                    self::mergeTokenCsv((string)($accountClassDefaults['assigned_apps'] ?? ''), (string)($profileDefaults['assigned_apps'] ?? '')),
                    (string)($crossDefaults['assigned_apps'] ?? '')
                ),
                (string)($input['assigned_apps'] ?? '')
            ),
            (string)($defaultApp ?? ''),
            (string)($defaults['assigned_apps'] ?? '')
        );
        if ($authorityRole === 'platform_admin') {
            $assignedAppList = self::resolveAssignedApps(self::ALL_ENABLED_APPS_TOKEN, (string)($defaultApp ?? ''), '');
        }
        $assignedApps = implode(',', $assignedAppList);
        if ($authorityRole !== 'platform_admin' && empty($assignedAppList)) {
            throw new \InvalidArgumentException('Select at least one assigned app for this user.');
        }
        $assignedAppsDefaults = self::normalizeAssignedAppDefaultsForContext(
            self::composeAssignedAppsDefaults($assignedAppList),
            $authorityRole,
            $dashboardType
        );
        $effectiveProfileKeys = self::normalizeAccessProfileKeysForContext(array_values(array_unique(array_merge($profileKeys, self::resolveAccessProfileKeys((string)($crossDefaults['access_profiles'] ?? ''))))), $authorityRole, $dashboardType);
        $accessProfiles = implode(',', $effectiveProfileKeys);
        $rawMeDashboardBlocks = (string)($input['me_dashboard_blocks'] ?? '');
        $rawMePluginCards = (string)($input['me_plugin_cards'] ?? '');
        $meDashboardBlocks = self::hasExplicitExperienceLayoutNone($rawMeDashboardBlocks)
            ? self::EXPERIENCE_LAYOUT_NONE
            : self::nullIfEmpty(implode(',', self::normalizeMeDashboardBlocksForAccountType(
                self::resolveMeDashboardBlocks($rawMeDashboardBlocks),
                $authorityRole
            )));
        $mePluginCards = self::hasExplicitExperienceLayoutNone($rawMePluginCards)
            ? self::EXPERIENCE_LAYOUT_NONE
            : self::nullIfEmpty(implode(',', self::normalizeMePluginCardsForContext(
                self::resolveMePluginCards($rawMePluginCards),
                $authorityRole,
                $dashboardType,
                $assignedAppList
            )));
        $inlineWritePolicy = UserSurfaceOverrideService::inlineWritePolicy();
        $inlineMeDashboardBlocks = ($inlineWritePolicy['me_dashboard_blocks'] ?? true) ? $meDashboardBlocks : null;
        $inlineMePluginCards = ($inlineWritePolicy['me_plugin_cards'] ?? true) ? $mePluginCards : null;
        $basePermissionTokens = self::normalizePermissionTokensForContext(self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['permissions'] ?? ''), (string)($assignedAppsDefaults['permissions'] ?? '')), (string)($profileDefaults['permissions'] ?? '')),
            (string)($crossDefaults['permissions'] ?? '')
        )), 'permission', $authorityRole), $authorityRole, $dashboardType);
        $permissions = implode(',', self::normalizePermissionTokensForContext(
            self::csvTokens(self::mergeTokenCsv(implode(',', $basePermissionTokens), (string)($input['permissions'] ?? ($defaults['permissions'] ?? '')))),
            $authorityRole,
            $dashboardType
        ));

        $baseViewTokens = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['view_access'] ?? ''), (string)($assignedAppsDefaults['view_access'] ?? '')), (string)($profileDefaults['view_access'] ?? '')),
            (string)($crossDefaults['view_access'] ?? '')
        )), 'view', $authorityRole);
        $effectiveViewAccess = self::nullIfEmpty(implode(',', self::csvTokens(self::mergeTokenCsv(implode(',', $baseViewTokens), (string)($input['view_access'] ?? '')))));

        $baseTableTokens = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['table_access'] ?? ''), (string)($assignedAppsDefaults['table_access'] ?? '')), (string)($profileDefaults['table_access'] ?? '')),
            (string)($crossDefaults['table_access'] ?? '')
        )), 'table', $authorityRole);
        $effectiveTableAccess = self::nullIfEmpty(implode(',', self::csvTokens(self::mergeTokenCsv(implode(',', $baseTableTokens), (string)($input['table_access'] ?? '')))));

        $baseChartTokens = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['chart_access'] ?? ''), (string)($assignedAppsDefaults['chart_access'] ?? '')), (string)($profileDefaults['chart_access'] ?? '')),
            (string)($crossDefaults['chart_access'] ?? '')
        )), 'chart', $authorityRole);
        $effectiveChartAccess = self::nullIfEmpty(implode(',', self::csvTokens(self::mergeTokenCsv(implode(',', $baseChartTokens), (string)($input['chart_access'] ?? '')))));

        $baseDutyCodeTokens = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['duty_codes'] ?? ''), (string)($assignedAppsDefaults['duty_codes'] ?? '')), (string)($profileDefaults['duty_codes'] ?? '')),
            (string)($crossDefaults['duty_codes'] ?? '')
        )), 'duty_code', $authorityRole);
        $effectiveDutyCodes = self::nullIfEmpty(implode(',', self::csvTokens(self::mergeTokenCsv(implode(',', $baseDutyCodeTokens), (string)($input['duty_codes'] ?? '')))));

        self::assertPlatformAdminDemotionAllowed((int)$user['id'], (string)($user['authority_role'] ?? ''), $authorityRole, $actorId);
        DB::query('UPDATE users SET role_tier=?, authority_role=?, role=? WHERE id=?', [$roleTier, $authorityRole, $assignableRole, $userId]);

        DB::query(
                "INSERT INTO user_dashboard_assignments (user_id, dashboard_type, authority_role, default_app, default_landing_page, assigned_apps, access_profiles, permissions, view_access, table_access, chart_access, module_visibility, dashboard_mode, default_app_mode, landing_mode, access_profiles_mode, module_visibility_mode, account_class, selected_role_packs, cross_functional_access, me_dashboard_blocks, me_plugin_cards, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                dashboard_type=VALUES(dashboard_type),
                authority_role=VALUES(authority_role),
                default_app=VALUES(default_app),
                default_landing_page=VALUES(default_landing_page),
                assigned_apps=VALUES(assigned_apps),
                access_profiles=VALUES(access_profiles),
                permissions=VALUES(permissions),
                view_access=VALUES(view_access),
                table_access=VALUES(table_access),
                chart_access=VALUES(chart_access),
                module_visibility=VALUES(module_visibility),
                dashboard_mode=VALUES(dashboard_mode),
                default_app_mode=VALUES(default_app_mode),
                landing_mode=VALUES(landing_mode),
                access_profiles_mode=VALUES(access_profiles_mode),
                module_visibility_mode=VALUES(module_visibility_mode),
                account_class=VALUES(account_class),
                selected_role_packs=VALUES(selected_role_packs),
                cross_functional_access=VALUES(cross_functional_access),
                me_dashboard_blocks=VALUES(me_dashboard_blocks),
                me_plugin_cards=VALUES(me_plugin_cards),
                updated_by=VALUES(updated_by),
                updated_at=NOW()",
            [
                $userId,
                $dashboardType,
                $authorityRole,
                $defaultApp,
                $defaultLanding,
                self::nullIfEmpty($assignedApps),
                self::nullIfEmpty($accessProfiles),
                self::nullIfEmpty($permissions),
                $effectiveViewAccess,
                $effectiveTableAccess,
                $effectiveChartAccess,
                null,
                $dashboardMode,
                $defaultAppMode,
                $landingMode,
                $accessProfilesMode,
                $moduleVisibilityMode,
                $accountClass,
                self::nullIfEmpty(implode(',', $profileKeys)),
                self::nullIfEmpty(self::serializeCrossFunctionalAccess($crossAccess)),
                $inlineMeDashboardBlocks,
                $inlineMePluginCards,
                $actorLabel,
            ]
        );

        UserSurfaceOverrideService::saveField($userId, 'me_dashboard_blocks', $meDashboardBlocks);
        UserSurfaceOverrideService::saveField($userId, 'me_plugin_cards', $mePluginCards);

        if (array_key_exists('display_surfaces', $input)) {
            $displaySurfacesValue = self::nullIfEmpty(implode(',', self::csvTokens((string)$input['display_surfaces'])));
            if (($inlineWritePolicy['display_surfaces'] ?? true) === true) {
                DB::query(
                    'UPDATE user_dashboard_assignments SET display_surfaces = ?, updated_by = ?, updated_at = NOW() WHERE user_id = ?',
                    [
                        $displaySurfacesValue,
                        $actorLabel,
                        $userId,
                    ]
                );
            }
            UserSurfaceOverrideService::saveField($userId, 'display_surfaces', $displaySurfacesValue);
        }
        if (array_key_exists('operator_views', $input)) {
            $operatorViewsValue = self::normalizeOperatorViewsCsv((string)$input['operator_views']);
            if (($inlineWritePolicy['operator_views'] ?? true) === true) {
                DB::query(
                    'UPDATE user_dashboard_assignments SET operator_views = ?, updated_by = ?, updated_at = NOW() WHERE user_id = ?',
                    [
                        $operatorViewsValue,
                        $actorLabel,
                        $userId,
                    ]
                );
            }
            UserSurfaceOverrideService::saveField($userId, 'operator_views', $operatorViewsValue);
        }

        if ($workspaceProfileProvided) {
            DB::query(
                'UPDATE user_dashboard_assignments
                    SET workspace_profile_key = ?,
                        updated_by = ?,
                        updated_at = NOW()
                  WHERE user_id = ?',
                [$workspaceProfileKey !== '' ? $workspaceProfileKey : null, $actorLabel, $userId]
            );
        }

        DB::query('DELETE FROM user_operational_scopes WHERE user_id=?', [$userId]);
        $scopeInput = $input;
        if (trim((string)($scopeInput['scope_task_types'] ?? '')) === '') {
            $scopeInput['scope_task_types'] = self::mergeTokenCsv(
                (string)($accountClassDefaults['task_types'] ?? ''),
                (string)($profileDefaults['task_types'] ?? '')
            );
        }
        self::insertScopeRows($userId, $scopeInput, $actorLabel);

        DB::query(
            "INSERT INTO user_role_duties (user_id, duty_codes, duty_notes, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                duty_codes=VALUES(duty_codes),
                duty_notes=VALUES(duty_notes),
                updated_by=VALUES(updated_by),
                updated_at=NOW()",
            [
                $userId,
                $effectiveDutyCodes,
                self::nullIfEmpty(self::sanitizeDutyNotes((string)($input['duty_notes'] ?? ''))),
                $actorLabel,
            ]
        );

        // Sync primary app operational role to user_app_roles (new model).
        // admin-level authority roles don't have operational roles.
        if ($defaultApp !== null && $defaultApp !== '' && !in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            DB::query(
                'INSERT INTO user_app_roles (user_id, app_key, operational_role, is_primary) VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE operational_role = VALUES(operational_role), is_primary = 1, updated_at = NOW()',
                [$userId, $defaultApp, $dashboardType]
            );
        }

        DB::query('DELETE FROM user_module_visibility WHERE user_id=?', [$userId]);
        $moduleSeed = ($moduleVisibilityMode === 'auto' && $canonical !== null)
            ? implode(',', (array)($canonical['baseline_module_visibility'] ?? []))
            : (string)($input['module_visibility'] ?? '');
        $moduleSeed = implode(',', self::filterGeneratedTokensForAccountType(self::csvTokens($moduleSeed), 'module', $authorityRole));
        $baseModuleKeys = self::filterGeneratedTokensForAccountType(self::csvTokens(self::mergeTokenCsv(
            self::mergeTokenCsv(self::mergeTokenCsv((string)($accountClassDefaults['module_visibility'] ?? ''), (string)($assignedAppsDefaults['module_visibility'] ?? '')), (string)($profileDefaults['module_visibility'] ?? '')),
            (string)($crossDefaults['module_visibility'] ?? '')
        )), 'module', $authorityRole);
        $moduleKeys = self::csvTokens(self::mergeTokenCsv(implode(',', $baseModuleKeys), $moduleSeed));
        foreach ($moduleKeys as $moduleKey) {
            DB::query(
                'INSERT INTO user_module_visibility (user_id, module_key, is_enabled, updated_by) VALUES (?, ?, 1, ?)',
                [$userId, $moduleKey, $actorLabel]
            );
        }
        DB::query(
            'UPDATE user_dashboard_assignments SET module_visibility = ?, updated_by = ?, updated_at = NOW() WHERE user_id = ?',
            [self::nullIfEmpty(implode(',', $moduleKeys)), $actorLabel, $userId]
        );
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     */
    public static function saveExperienceLayoutFromInput(array $input, array $actor): void
    {
        self::ensureSchema();

        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Missing user id.');
        }

        $user = DB::fetchOne('SELECT id, role, authority_role FROM users WHERE id=? LIMIT 1', [$userId]);
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        $rows = self::listAssignmentRows(['user_id' => $userId], true);
        $row = $rows[0] ?? null;
        if (!is_array($row)) {
            throw new \RuntimeException('User assignment not found.');
        }

        $authorityRole = self::normalizeAccountType(
            (string)($row['authority_role'] ?? ($user['authority_role'] ?? '')),
            (string)($row['operational_role'] ?? ($row['role'] ?? ($user['role'] ?? ''))),
            (string)($row['role_tier'] ?? ''),
            (string)($row['default_app'] ?? '')
        );
        $dashboardType = self::normalizeDashboardType(
            (string)($row['dashboard_type'] ?? ''),
            $authorityRole,
            strtolower(trim((string)($row['role'] ?? ($user['role'] ?? ''))))
        );
        $assignedApps = self::resolveAssignedApps(
            (string)($row['assigned_apps'] ?? ''),
            (string)($row['default_app'] ?? ''),
            ''
        );

        $rawMeDashboardBlocks = (string)($input['me_dashboard_blocks'] ?? '');
        $rawMePluginCards = (string)($input['me_plugin_cards'] ?? '');
        $meDashboardBlocks = self::hasExplicitExperienceLayoutNone($rawMeDashboardBlocks)
            ? self::EXPERIENCE_LAYOUT_NONE
            : self::nullIfEmpty(implode(',', self::normalizeMeDashboardBlocksForAccountType(
                self::resolveMeDashboardBlocks($rawMeDashboardBlocks),
                $authorityRole
            )));
        $mePluginCards = self::hasExplicitExperienceLayoutNone($rawMePluginCards)
            ? self::EXPERIENCE_LAYOUT_NONE
            : self::nullIfEmpty(implode(',', self::normalizeMePluginCardsForContext(
                self::resolveMePluginCards($rawMePluginCards),
                $authorityRole,
                $dashboardType,
                $assignedApps
            )));
        $inlineWritePolicy = UserSurfaceOverrideService::inlineWritePolicy();
        $inlineMeDashboardBlocks = ($inlineWritePolicy['me_dashboard_blocks'] ?? true) ? $meDashboardBlocks : null;
        $inlineMePluginCards = ($inlineWritePolicy['me_plugin_cards'] ?? true) ? $mePluginCards : null;

        if (($authorityRole === 'tv_display' || $dashboardType === 'display') && array_key_exists('display_surfaces', $input)) {
            $displaySurfacesValue = self::nullIfEmpty(implode(',', self::csvTokens((string)$input['display_surfaces'])));
            if (($inlineWritePolicy['display_surfaces'] ?? true) === true) {
                DB::query(
                    "INSERT INTO user_dashboard_assignments (user_id, dashboard_type, authority_role, default_app, default_landing_page, display_surfaces, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        authority_role=VALUES(authority_role),
                        display_surfaces=VALUES(display_surfaces),
                        updated_by=VALUES(updated_by),
                        updated_at=NOW()",
                    [
                        $userId,
                        (string)($row['dashboard_type'] ?? 'display'),
                        $authorityRole,
                        self::nullIfEmpty((string)($row['default_app'] ?? '')),
                        self::nullIfEmpty((string)($row['default_landing_page'] ?? '/displays')),
                        $displaySurfacesValue,
                        self::actorLabel($actor),
                    ]
                );
            }
            UserSurfaceOverrideService::saveField($userId, 'display_surfaces', $displaySurfacesValue);
            return;
        }

        if ($authorityRole === 'app_user' && array_key_exists('operator_views', $input)) {
            $operatorViewsValue = self::normalizeOperatorViewsCsv((string)$input['operator_views']);
            if (($inlineWritePolicy['operator_views'] ?? true) === true) {
                DB::query(
                    "INSERT INTO user_dashboard_assignments (user_id, dashboard_type, authority_role, default_app, default_landing_page, operator_views, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        authority_role=VALUES(authority_role),
                        operator_views=VALUES(operator_views),
                        updated_by=VALUES(updated_by),
                        updated_at=NOW()",
                    [
                        $userId,
                        (string)($row['dashboard_type'] ?? 'operator'),
                        $authorityRole,
                        self::nullIfEmpty((string)($row['default_app'] ?? '')),
                        self::nullIfEmpty((string)($row['default_landing_page'] ?? '/')),
                        $operatorViewsValue,
                        self::actorLabel($actor),
                    ]
                );
            }
            UserSurfaceOverrideService::saveField($userId, 'operator_views', $operatorViewsValue);
            return;
        }

        DB::query(
            "INSERT INTO user_dashboard_assignments (user_id, dashboard_type, authority_role, default_app, default_landing_page, me_dashboard_blocks, me_plugin_cards, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                authority_role=VALUES(authority_role),
                me_dashboard_blocks=VALUES(me_dashboard_blocks),
                me_plugin_cards=VALUES(me_plugin_cards),
                updated_by=VALUES(updated_by),
                updated_at=NOW()",
            [
                $userId,
                (string)($row['dashboard_type'] ?? 'my_work'),
                $authorityRole,
                self::nullIfEmpty((string)($row['default_app'] ?? '')),
                self::nullIfEmpty((string)($row['default_landing_page'] ?? '/')),
                $inlineMeDashboardBlocks,
                $inlineMePluginCards,
                self::actorLabel($actor),
            ]
        );
        UserSurfaceOverrideService::saveField($userId, 'me_dashboard_blocks', $meDashboardBlocks);
        UserSurfaceOverrideService::saveField($userId, 'me_plugin_cards', $mePluginCards);
    }

    public static function roleDefaultsForUi(): array
    {
        $map = [];
        foreach (self::PROFILE_ACCOUNT_CANONICAL as $key => $cfg) {
            [$profileSlug, $authorityRole] = array_pad(explode('|', $key, 2), 2, '');
            if ($profileSlug === '' || $authorityRole === '') {
                continue;
            }
            if (!isset($map[$profileSlug])) {
                $map[$profileSlug] = [];
            }
            $alternateLandingPages = array_values(array_unique(array_map(
                static fn($route): string => self::normalizeLandingRouteForUi((string)$route),
                (array)($cfg['alternate_landing_pages'] ?? [])
            )));

            $map[$profileSlug][$authorityRole] = [
                'dashboard_type' => (string)($cfg['dashboard_type'] ?? ''),
                'default_app' => (string)($cfg['default_app'] ?? ''),
                'default_landing_page' => (string)($cfg['default_landing_page'] ?? ''),
                'alternate_landing_pages' => $alternateLandingPages,
                'recommended_access_profiles' => array_values((array)($cfg['recommended_access_profiles'] ?? [])),
                'baseline_module_visibility' => array_values((array)($cfg['baseline_module_visibility'] ?? [])),
            ];
        }
        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    public static function assignmentUiConfig(): array
    {
        $appRegistry = self::governanceAppRegistry();
        $moduleRegistry = self::governanceModuleRegistry($appRegistry);
        $crossBundles = [];
        foreach (self::CROSS_FUNCTIONAL_BUNDLES as $key => $cfg) {
            $crossBundles[$key] = [
                'label' => (string)($cfg['label'] ?? $key),
                'levels' => self::CROSS_PERMISSION_LEVELS,
            ];
        }
        $mergedRolePacks = self::APP_ROLE_PACKS;
        foreach (SuitePermissionTemplateService::rolePacksByApp() as $appKey => $packs) {
            $mergedRolePacks[$appKey] = array_merge($mergedRolePacks[$appKey] ?? [], $packs);
        }
        return [
            'canonical' => self::roleDefaultsForUi(),
            'dashboardsByAccount' => self::DASHBOARD_OPTIONS_BY_ACCOUNT,
            'landingByDashboard' => self::landingOptionsByDashboardForUi(),
            'autoModeFields' => self::DEPENDENT_AUTO_FIELDS,
            'accessAuthorities' => [
                'platform_admin' => 'Platform Admin',
                'app_admin' => 'App Admin',
                'app_user' => 'App User',
                'tv_display' => 'TV / Display',
            ],
            'interactionProfiles' => [
                'worker' => 'Worker',
                'leader' => 'Leader',
                'admin' => 'Admin',
                'read_only' => 'Read-only',
                'display' => 'TV / Display',
            ],
            'operationalFocuses' => self::governanceOperationalFocusRegistry(),
            'appFocusesByKey' => self::appFocusesByKey(),
            'appRolesByKey' => self::appOperationalRolesByKey(),
            'accountClasses' => self::ACCOUNT_CLASSES,
            'appOptions' => self::APP_OPTIONS,
            'appRegistry' => $appRegistry,
            'allEnabledAppsToken' => self::ALL_ENABLED_APPS_TOKEN,
            'enabledAppKeys' => array_values(array_map(static fn(array $row): string => (string)($row['key'] ?? ''), $appRegistry)),
            'moduleRegistry' => $moduleRegistry,
            'appAdminOnlyAccessProfiles' => self::APP_ADMIN_ONLY_ACCESS_PROFILES,
            'leaderAccessProfiles' => self::LEADER_ACCESS_PROFILES,
            'rolePacksByApp' => $mergedRolePacks,
            'suiteRoleTemplates' => SuitePermissionTemplateService::suiteRoleTemplates(),
            'modulePermissionTemplates' => SuitePermissionTemplateService::modulePermissionTemplates(),
            'defaultRoleBundles' => SuitePermissionTemplateService::defaultRoleBundles(),
            'dashboardLabels' => [
                'production_leader' => 'Production Control',
                'assembly_leader' => 'Assembly Control',
                'qc_leader' => 'Quality Control',
                'dispatch_leader' => 'Dispatch Control',
            ],
            'crossFunctionalBundles' => $crossBundles,
            'crossFunctionalPresets' => self::CROSS_FUNCTIONAL_PRESETS,
            'crossPermissionLevels' => self::CROSS_PERMISSION_LEVELS,
            'meDashboardBlocks' => self::meDashboardBlockCatalog(),
            'mePluginCards' => self::mePluginCardCatalog(),
            'surfaceDefinitions' => self::surfaceDefinitionCatalog(),
            'baselineVisibilityPolicy' => self::baselineVisibilityPolicy(),
        ];
    }

    /**
     * @return array<int,array{key:string,label:string,enabled:bool,status:string}>
     */
    private static function governanceAppRegistry(): array
    {
        $enabledMap = self::enabledAppMap();
        $labels = [];
        foreach (array_keys($enabledMap) as $appKey) {
            $canonicalKey = self::canonicalAppKey((string)$appKey);
            if ($canonicalKey === '') {
                continue;
            }
            $labels[$canonicalKey] = self::appDisplayLabel($canonicalKey);
        }

        $statusByKey = [];
        $appTypeByKey = [];
        try {
            $rows = DB::fetchAll('SELECT app_key, status, app_type FROM core_apps', []);
            foreach ($rows as $row) {
                $key = self::canonicalAppKey((string)($row['app_key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $status = strtolower(trim((string)($row['status'] ?? 'enabled')));
                $statusByKey[$key] = $status;
                $appTypeByKey[$key] = strtolower(trim((string)($row['app_type'] ?? '')));
                if ($status === 'enabled' && !isset($labels[$key])) {
                    $labels[$key] = self::appDisplayLabel($key);
                }
            }
        } catch (\Throwable $e) {
            // Fall back to static app options when registry table is unavailable.
        }

        if ($labels === []) {
            $labels['platform'] = self::appDisplayLabel('platform');
        }

        $out = [];
        foreach ($labels as $key => $label) {
            $status = (string)($statusByKey[$key] ?? 'enabled');
            $out[] = [
                'key'      => (string)$key,
                'label'    => (string)$label,
                'enabled'  => true,
                'status'   => $status,
                'app_type' => (string)($appTypeByKey[$key] ?? ''),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $out;
    }

    /**
     * @return array<int,string>
     */
    private static function allEnabledGovernanceAppKeys(): array
    {
        $keys = [];
        foreach (self::governanceAppRegistry() as $app) {
            $key = self::canonicalAppKey((string)($app['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $appType = strtolower(trim((string)($app['app_type'] ?? '')));
            if ($appType === 'framework' && $key !== 'platform') {
                continue;
            }
            $keys[$key] = $key;
        }

        return array_values($keys);
    }

    private static function appDisplayLabel(string $appKey): string
    {
        $key = strtolower(trim($appKey));
        return match ($key) {
            'platform', 'erp_core' => 'Platform',
            'sbaio' => 'SBAIO',
            'hr' => 'HR',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    /**
     * Merge operational focus values from all enabled apps.
     *
     * Each app can define its own operational focus values via the
     * operationalFocusValues() static method in its HostSurfaceContributionService.
     *
     * @return array<string,string> Merged map of focus key to localized label
     */
    private static function governanceOperationalFocusRegistry(): array
    {
        $merged = [];
        $appRegistry = self::governanceAppRegistry();

        foreach ($appRegistry as $appEntry) {
            $appKey = self::canonicalAppKey((string)($appEntry['key'] ?? ''));
            if ($appKey === '' || $appKey === 'platform') {
                // Skip platform app - it doesn't provide OF values
                continue;
            }

            $serviceClass = self::appHostSurfaceServiceClass($appKey);
            if ($serviceClass === '' || !class_exists($serviceClass)) {
                continue;
            }

            try {
                if (method_exists($serviceClass, 'operationalFocusValues')) {
                    $appFocuses = call_user_func([$serviceClass, 'operationalFocusValues']);
                    if (is_array($appFocuses)) {
                        foreach ($appFocuses as $focusKey => $focusLabel) {
                            $key = strtolower(trim((string)$focusKey));
                            if ($key !== '' && !isset($merged[$key])) {
                                $merged[$key] = $focusLabel;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall back gracefully if app service is unavailable
            }
        }

        // Return merged values, or defaults if empty
        return $merged !== [] ? $merged : [
            'production' => 'Production',
            'assembly' => 'Assembly',
            'qc' => 'QC',
            'dispatch' => 'Dispatch',
            'planner' => 'Planner',
            'office_ops' => 'Office Ops',
        ];
    }

    /**
     * Get the HostSurfaceContributionService class name for an app.
     *
     * @param string $appKey Canonical app key
     * @return string Fully qualified class name or empty string if not found
     */
    private static function appHostSurfaceServiceClass(string $appKey): string
    {
        $appKey = strtolower(trim($appKey));
        return match ($appKey) {
            'manufacturing' => 'Apps\\Manufacturing\\Services\\HostSurfaceContributionService',
            'sbaio' => 'Apps\\SBAIO\\Services\\HostSurfaceContributionService',
            'procurement' => 'Apps\\Procurement\\Services\\HostSurfaceContributionService',
            default => '',
        };
    }

    /**
     * Build a mapping of app keys to their supported operational focus values.
     *
     * Used by the governance UI to dynamically filter OF options based on assigned apps.
     *
     * @return array<string,array<string,string>> Map of appKey => (focusKey => focusLabel)
     */
    private static function appFocusesByKey(): array
    {
        $out = [];
        $appRegistry = self::governanceAppRegistry();

        foreach ($appRegistry as $appEntry) {
            $appKey = self::canonicalAppKey((string)($appEntry['key'] ?? ''));
            if ($appKey === '' || $appKey === 'platform') {
                continue;
            }

            $serviceClass = self::appHostSurfaceServiceClass($appKey);
            if ($serviceClass === '' || !class_exists($serviceClass)) {
                continue;
            }

            try {
                if (method_exists($serviceClass, 'operationalFocusValues')) {
                    $appFocuses = call_user_func([$serviceClass, 'operationalFocusValues']);
                    if (is_array($appFocuses)) {
                        $out[$appKey] = [];
                        foreach ($appFocuses as $focusKey => $focusLabel) {
                            $key = strtolower(trim((string)$focusKey));
                            if ($key !== '') {
                                $out[$appKey][$key] = $focusLabel;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall back gracefully if app service is unavailable
            }
        }

        return $out;
    }

    /**
     * Build a mapping of app keys to their supported per-app operational roles.
     *
     * Apps may advertise values through HostSurfaceContributionService::operationalRoleValues().
     * Unknown apps expose no roles until the app advertises its own list.
     *
     * @return array<string,array<string,string>>
     */
    public static function appOperationalRolesByKey(): array
    {
        $out = [];
        $appRegistry = self::governanceAppRegistry();

        foreach ($appRegistry as $appEntry) {
            $appKey = self::canonicalAppKey((string)($appEntry['key'] ?? ''));
            if ($appKey === '') {
                continue;
            }

            $roles = [];
            $serviceClass = self::appHostSurfaceServiceClass($appKey);
            if ($serviceClass !== '' && class_exists($serviceClass) && method_exists($serviceClass, 'operationalRoleValues')) {
                try {
                    $advertisedRoles = call_user_func([$serviceClass, 'operationalRoleValues']);
                    if (is_array($advertisedRoles)) {
                        foreach ($advertisedRoles as $roleKey => $roleLabel) {
                            $key = strtolower(trim((string)$roleKey));
                            if ($key !== '') {
                                $roles[$key] = trim((string)$roleLabel) !== ''
                                    ? (string)$roleLabel
                                    : self::humanizeToken($key);
                            }
                        }
                    }
                } catch (\Throwable) {
                    $roles = [];
                }
            }

            $out[$appKey] = $roles;
        }

        return $out;
    }

    /**
     * @param array<int,array{key:string,label:string,enabled:bool,status:string}> $appRegistry
     * @return array<int,array{key:string,label:string,app:string,levels:array<int,string>}>
     */
    private static function governanceModuleRegistry(array $appRegistry): array
    {
        $appSet = [];
        foreach ($appRegistry as $entry) {
            $appKey = self::canonicalAppKey((string)($entry['key'] ?? ''));
            if ($appKey !== '') {
                $appSet[$appKey] = $appKey;
            }
        }

        $moduleSet = [];
        foreach (self::MODULE_SYNONYMS as $moduleKey) {
            $normalized = self::normalizeModuleKey((string)$moduleKey);
            if ($normalized !== '') {
                $moduleSet[$normalized] = $normalized;
            }
        }

        foreach (self::ACCOUNT_CLASS_BASELINES as $cfg) {
            foreach (self::csvTokens((string)($cfg['module_visibility'] ?? '')) as $moduleKey) {
                $normalized = self::normalizeModuleKey($moduleKey);
                if ($normalized !== '') {
                    $moduleSet[$normalized] = $normalized;
                }
            }
        }

        foreach (self::accessProfileRegistry() as $cfg) {
            foreach (self::normalizeTokenArray((array)($cfg['module_visibility'] ?? [])) as $moduleKey) {
                $normalized = self::normalizeModuleKey($moduleKey);
                if ($normalized !== '') {
                    $moduleSet[$normalized] = $normalized;
                }
            }
        }

        foreach (self::surfaceDefinitionCatalog() as $surfaceDef) {
            $type = strtolower(trim((string)($surfaceDef['token_type'] ?? '')));
            if ($type !== 'module') {
                continue;
            }
            $normalized = self::normalizeModuleKey((string)($surfaceDef['token_key'] ?? ''));
            if ($normalized !== '') {
                $moduleSet[$normalized] = $normalized;
            }
        }

        $levels = ['none', 'view', 'work', 'approve', 'manage'];
        $out = [];
        foreach (array_values($moduleSet) as $moduleKey) {
            $ownerApp = self::ownerAppForModule($moduleKey);
            if ($ownerApp === '' || !isset($appSet[$ownerApp])) {
                $ownerApp = 'platform';
            }
            $out[] = [
                'key' => $moduleKey,
                'label' => ucwords(str_replace('_', ' ', $moduleKey)),
                'app' => $ownerApp,
                'levels' => $levels,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $byApp = strcmp((string)($a['app'] ?? ''), (string)($b['app'] ?? ''));
            if ($byApp !== 0) {
                return $byApp;
            }
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return $out;
    }

    /**
     * @return array<string,string>
     */
    public static function meDashboardBlockCatalog(): array
    {
        $catalog = [];

        foreach (self::ME_DASHBOARD_BLOCKS as $key) {
            $labelMap = [
                'admin_dashboard_panels' => 'Admin Dashboard Panels',
                'top_navigation_module_launcher' => 'Top Navigation / Module Launcher',
                'global_controls' => 'Workspace Actions',
                'search_alerts' => 'Search + Alerts',
                'operational_summary' => 'Operational Summary',
                'primary_work_widgets' => 'Primary Work Widgets',
                'monitoring_widgets' => 'Monitoring Widgets',
                'detailed_work_tables' => 'Action Queue Tables',
                'platform_admin_tools' => 'Platform Admin Tools',
                'plugin_dashboards_charts' => 'App Quick Links',
            ];
            $fallback = $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key));
            $catalog[$key] = self::tr("dashboard.blocks.{$key}", $fallback);
        }

        // Manufacturing-specific blocks
        if (class_exists(\Apps\Manufacturing\Services\ManufacturingDashboardBlockService::class)) {
            $catalog = array_replace($catalog, \Apps\Manufacturing\Services\ManufacturingDashboardBlockService::dashboardBlockCatalog());
        }

        return $catalog;
    }

    /**
     * @return array<string,string>
     */
    public static function mePluginCardCatalog(): array
    {
        $catalog = [];

        foreach (self::ME_PLUGIN_CARDS as $key) {
            $labelMap = [
                'approval_inbox' => 'Approval Inbox',
                'notifications' => 'Notifications',
                'cross_role_handoff' => 'Cross-Role Handoff',
                'platform_setup' => 'Setup',
                'access_control_board' => 'Access Control Board',
                'user_control_board' => 'User Control Board',
                'user_dashboard' => 'User Dashboard (Compatibility)',
                'admin_tools' => 'Admin Tools',
                'route_diagnostics' => 'Routes / Diagnostics',
            ];
            $fallback = $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key));
            $catalog[$key] = self::tr("dashboard.cards.{$key}", $fallback);
        }

        return $catalog;
    }

    /**
     * @return array<string,array<int,string>>
     */
    private static function landingOptionsByDashboardForUi(): array
    {
        if (self::$landingOptionsUiCache !== null) {
            return self::$landingOptionsUiCache;
        }

        $map = [];

        foreach (self::LANDING_OPTIONS_BY_DASHBOARD as $dashboardType => $routes) {
            $dashboardKey = strtolower(trim((string)$dashboardType));
            if ($dashboardKey === '') {
                continue;
            }

            if (!isset($map[$dashboardKey])) {
                $map[$dashboardKey] = [];
            }

            foreach ((array)$routes as $route) {
                $normalizedRoute = self::sanitizeLandingRouteForUi((string)$route);
                if ($normalizedRoute === null) {
                    continue;
                }
                $map[$dashboardKey][$normalizedRoute] = $normalizedRoute;
            }
        }

        foreach (self::PROFILE_ACCOUNT_CANONICAL as $cfg) {
            if (!is_array($cfg)) {
                continue;
            }

            $dashboardKey = strtolower(trim((string)($cfg['dashboard_type'] ?? '')));
            if ($dashboardKey === '') {
                continue;
            }

            if (!isset($map[$dashboardKey])) {
                $map[$dashboardKey] = [];
            }

            $candidateRoutes = array_merge(
                [(string)($cfg['default_landing_page'] ?? '')],
                array_map('strval', (array)($cfg['alternate_landing_pages'] ?? []))
            );

            foreach ($candidateRoutes as $route) {
                $normalizedRoute = self::sanitizeLandingRouteForUi($route);
                if ($normalizedRoute === null) {
                    continue;
                }
                $map[$dashboardKey][$normalizedRoute] = $normalizedRoute;
            }
        }

        // DB-driven extension: active workspace profile routes are folded in by authority.
        try {
            $rows = DB::fetchAll(
                "SELECT authority_role, landing_route
                 FROM workspace_profiles
                 WHERE is_active = 1
                   AND COALESCE(NULLIF(TRIM(landing_route), ''), '') <> ''",
                []
            );
            foreach ($rows as $row) {
                $authorityRole = strtolower(trim((string)($row['authority_role'] ?? '')));
                if (!in_array($authorityRole, self::VALID_ACCOUNT_TYPES, true)) {
                    continue;
                }

                $route = self::sanitizeLandingRouteForUi((string)($row['landing_route'] ?? ''));
                if ($route === null) {
                    continue;
                }

                foreach (self::allowedDashboardsForAccount($authorityRole) as $dashboardType) {
                    $dashboardKey = strtolower(trim((string)$dashboardType));
                    if ($dashboardKey === '') {
                        continue;
                    }
                    if (!isset($map[$dashboardKey])) {
                        $map[$dashboardKey] = [];
                    }
                    $map[$dashboardKey][$route] = $route;
                }
            }
        } catch (\Throwable) {
            // Non-fatal during bootstrap / migration windows.
        }

        $out = [];
        foreach ($map as $dashboardKey => $routeSet) {
            $routes = array_values($routeSet);
            if ($routes === []) {
                $routes = ['/'];
            }
            $out[$dashboardKey] = $routes;
        }

        self::$landingOptionsUiCache = $out;
        return self::$landingOptionsUiCache;
    }

    private static function sanitizeLandingRouteForUi(string $route): ?string
    {
        $normalizedRoute = self::normalizeLandingRouteForUi($route);
        if ($normalizedRoute === '') {
            return null;
        }

        if (!str_starts_with($normalizedRoute, '/')) {
            return null;
        }
        if (str_contains($normalizedRoute, '://') || str_contains($normalizedRoute, '..')) {
            return null;
        }
        if (str_contains($normalizedRoute, '{') || str_contains($normalizedRoute, '}')) {
            return null;
        }
        if (preg_match('/\s/', $normalizedRoute) === 1) {
            return null;
        }

        if ($normalizedRoute !== '/' && !preg_match('#^/(ops|apps|u|admin|displays)(/|$)#', $normalizedRoute)) {
            return null;
        }

        return $normalizedRoute;
    }

    private static function normalizeLandingRouteForUi(string $route): string
    {
        $trimmed = trim($route);
        return match ($trimmed) {
            '/ops/production-dashboard', '/ops/production-leader-dashboard' => '/apps/manufacturing/production-dashboard',
            '/ops/assembly-dashboard', '/ops/assembly-leader-dashboard' => '/apps/manufacturing/assembly-dashboard',
            '/ops/qc-dashboard', '/ops/qc-leader-dashboard' => '/apps/manufacturing/qc-dashboard',
            '/ops/dispatch-dashboard', '/ops/dispatch-leader-dashboard' => '/apps/manufacturing/dispatch-dashboard',
            default => $trimmed,
        };
    }

    /**
     * Core quick links exposed on /me for platform admins.
     *
     * @param array<string,mixed> $ctx
     * @return array<int,array<string,mixed>>
     */
    public static function meCoreQuickLinks(array $ctx): array
    {
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
        if ($authorityRole !== 'platform_admin') {
            return [];
        }

        return [
            [
                'key' => 'unified_admin',
                'label' => self::tr('admin.launcher.title', 'Admin overview'),
                'url' => '/admin',
                'description' => self::tr('admin.launcher.description', 'Open the role-aware unified admin home.'),
                'weight' => 1,
            ],
            [
                'key' => 'access_control_board',
                'label' => self::tr('ops.platform_admin_tools.link.access_control_board', 'Access Control Board'),
                'url' => '/ops/access-control',
                'description' => self::tr('ops.platform_admin_tools.desc.access_control_board', 'Manage account type, permissions, and visibility rules.'),
                'weight' => 2,
            ],
            [
                'key' => 'user_control_board',
                'label' => self::tr('ops.platform_admin_tools.link.user_control_board', 'User Control Board'),
                'url' => '/ops/user-control',
                'description' => self::tr('ops.platform_admin_tools.desc.user_control_board', 'Review lifecycle, verification, recovery, and user-level operational actions.'),
                'weight' => 3,
            ],
            [
                'key' => 'display_manager',
                'label' => self::tr('ops.platform_admin_tools.link.display_manager', 'Display Manager'),
                'url' => '/ops/display-manager',
                'description' => self::tr('ops.platform_admin_tools.desc.display_manager', 'Assign display device IDs and open readonly display links.'),
                'weight' => 4,
            ],
            [
                'key' => 'admin_tools',
                'label' => self::tr('ops.platform_admin_tools.link.admin_tools', 'Admin Tools'),
                'url' => '/admin/apps',
                'description' => self::tr('ops.platform_admin_tools.desc.admin_tools', 'Manage apps, packages, and runtime surfaces.'),
                'weight' => 5,
            ],
            [
                'key' => 'route_diagnostics',
                'label' => self::tr('ops.platform_admin_tools.link.routes', 'Routes'),
                'url' => '/admin/routes',
                'description' => self::tr('ops.platform_admin_tools.desc.routes', 'Inspect routes, logs, and platform diagnostics.'),
                'weight' => 6,
            ],
            [
                'key' => 'navigation_tree',
                'label' => self::tr('ops.platform_admin_tools.link.navigation_tree', 'Navigation Tree'),
                'url' => '/ops/navigation-tree',
                'description' => self::tr('ops.platform_admin_tools.desc.navigation_tree', 'Inspect route hierarchy and navigation structure.'),
                'weight' => 7,
            ],
            [
                'key' => 'platform_setup',
                'label' => self::tr('ops.platform_admin_tools.link.setup', 'Setup'),
                'url' => '/admin/setup',
                'description' => self::tr('ops.platform_admin_tools.desc.setup', 'Core, suite, module, environment, and release workflows.'),
                'weight' => 8,
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function resolveMeDashboardBlocks(string $csv): array
    {
        if (self::hasExplicitExperienceLayoutNone($csv)) {
            return [];
        }

        $requested = self::csvTokens($csv);
        $allowed = array_combine(array_keys(self::ME_DASHBOARD_BLOCKS), array_keys(self::ME_DASHBOARD_BLOCKS));

        if ($requested === []) {
            return $allowed;
        }

        $selected = [];
        foreach ($requested as $key) {
            if (isset(self::ME_DASHBOARD_BLOCKS[$key])) {
                $selected[$key] = $key;
            }
        }

        if ($selected !== []) {
            return $selected;
        }

        return $allowed;
    }

    /**
     * @param array<int,string> $blocks
     * @return array<int,string>
     */
    public static function normalizeMeDashboardBlocksForAccountType(array $blocks, string $authorityRole): array
    {
        $normalized = [];
        foreach ($blocks as $key) {
            if (isset(self::ME_DASHBOARD_BLOCKS[$key])) {
                $normalized[$key] = $key;
            }
        }

        $authorityRole = strtolower(trim($authorityRole));
        if (!in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            unset($normalized['admin_dashboard_panels']);
        }

        if ($authorityRole !== 'platform_admin') {
            unset($normalized['platform_admin_tools']);
            unset($normalized['admin_dashboard_panels']);
            if ($authorityRole === 'app_admin') {
                $ordered = ['admin_dashboard_panels' => 'admin_dashboard_panels'] + $normalized;
                return array_values($ordered);
            }
            return array_values($normalized);
        }

        unset($normalized['admin_dashboard_panels']);
        unset($normalized['platform_admin_tools']);
        $ordered = [
            'admin_dashboard_panels' => 'admin_dashboard_panels',
            'platform_admin_tools' => 'platform_admin_tools',
        ] + $normalized;
        return array_values($ordered);
    }

    /**
     * @return array<string,string>
     */
    public static function resolveMePluginCards(string $csv): array
    {
        if (self::hasExplicitExperienceLayoutNone($csv)) {
            return [];
        }

        $requested = self::csvTokens($csv);
        $allowed = array_combine(array_keys(self::ME_PLUGIN_CARDS), array_keys(self::ME_PLUGIN_CARDS));

        if ($requested === []) {
            return $allowed;
        }

        $selected = [];
        foreach ($requested as $key) {
            if (isset(self::ME_PLUGIN_CARDS[$key])) {
                $selected[$key] = $key;
            }
        }

        if ($selected !== []) {
            return $selected;
        }

        return $allowed;
    }

    /**
     * @param array<int,string> $cards
     * @param array<int,string> $assignedApps
     * @return array<int,string>
     */
    public static function normalizeMePluginCardsForContext(array $cards, string $authorityRole, string $dashboardType, array $assignedApps): array
    {
        $normalized = [];
        foreach ($cards as $key) {
            if (isset(self::ME_PLUGIN_CARDS[$key])) {
                $normalized[$key] = $key;
            }
        }

        $authorityRole = strtolower(trim($authorityRole));
        $dashboardType = strtolower(trim($dashboardType));
        $assignedApps = array_values(array_unique(array_filter(array_map(
            static fn(string $app): string => strtolower(trim($app)),
            $assignedApps
        ), static fn(string $app): bool => $app !== '')));

        $allowLeaderOps = in_array($dashboardType, ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'], true);
        $allowed = [];
        foreach (array_keys($normalized) as $key) {
            if (in_array($key, self::PLATFORM_ONLY_ME_PLUGIN_CARDS, true) && $authorityRole !== 'platform_admin') {
                continue;
            }

            if ($key === 'cross_role_handoff' && !in_array($authorityRole, ['platform_admin', 'app_admin'], true) && !$allowLeaderOps) {
                continue;
            }

            $cardApp = self::appForMePluginCard($key);
            if ($cardApp === 'platform' && $authorityRole !== 'platform_admin') {
                continue;
            }
            if ($cardApp !== '' && $cardApp !== 'shared' && $authorityRole !== 'platform_admin' && !in_array($cardApp, $assignedApps, true)) {
                continue;
            }

            $allowed[$key] = $key;
        }

        if ($allowed !== []) {
            return array_values($allowed);
        }

        $fallback = [];
        foreach (array_keys(self::ME_PLUGIN_CARDS) as $key) {
            if (in_array($key, self::PLATFORM_ONLY_ME_PLUGIN_CARDS, true) && $authorityRole !== 'platform_admin') {
                continue;
            }
            if ($key === 'cross_role_handoff' && !in_array($authorityRole, ['platform_admin', 'app_admin'], true) && !$allowLeaderOps) {
                continue;
            }

            $cardApp = self::appForMePluginCard($key);
            if ($cardApp === 'platform' && $authorityRole !== 'platform_admin') {
                continue;
            }
            if ($cardApp !== '' && $cardApp !== 'shared' && $authorityRole !== 'platform_admin' && !in_array($cardApp, $assignedApps, true)) {
                continue;
            }
            $fallback[$key] = $key;
        }

        return array_values($fallback);
    }

    public static function meDashboardBlockGroupKey(string $key): string
    {
        return match (strtolower(trim($key))) {
            'admin_dashboard_panels', 'platform_admin_tools' => 'admin',
            'top_navigation_module_launcher', 'global_controls', 'search_alerts' => 'workspace',
            'operational_summary', 'primary_work_widgets', 'monitoring_widgets', 'detailed_work_tables' => 'work',
            'plugin_dashboards_charts' => 'apps',
            default => 'apps',
        };
    }

    public static function mePluginCardGroupKey(string $key): string
    {
        $app = self::appForMePluginCard($key);
        return $app !== '' ? $app : 'shared';
    }

    /**
     * @return array<string,string>
     */
    public static function meExperienceGroupLabels(): array
    {
        return [
            'admin' => 'Admin',
            'workspace' => 'Workspace',
            'work' => 'Work',
            'apps' => 'Apps',
            'platform' => 'Platform',
            'manufacturing' => 'Manufacturing',
            'shared' => 'Shared',
        ];
    }

    /**
     * Filter the dashboard block catalog down to entries the target user is eligible to see.
     *
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    public static function eligibleMeDashboardBlocksForRow(array $row): array
    {
        $catalog = self::meDashboardBlockCatalog();
        $authorityRole = strtolower(trim((string)($row['authority_role'] ?? '')));
        $filtered = [];
        foreach ($catalog as $key => $label) {
            $k = (string)$key;
            if ($k === 'admin_dashboard_panels' && !in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
                continue;
            }
            if ($k === 'platform_admin_tools' && $authorityRole !== 'platform_admin') {
                continue;
            }
            $filtered[$k] = (string)$label;
        }
        return $filtered;
    }

    /**
     * Filter the plugin card catalog down to entries the target user is eligible to see.
     *
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    public static function eligibleMePluginCardsForRow(array $row): array
    {
        $catalog = self::mePluginCardCatalog();
        $authorityRole = strtolower(trim((string)($row['authority_role'] ?? '')));
        $dashboardType = strtolower(trim((string)($row['dashboard_type'] ?? '')));
        $assignedApps = array_values(array_unique(array_filter(array_map(
            static fn(string $app): string => strtolower(trim($app)),
            preg_split('/\s*,\s*/', (string)($row['assigned_apps'] ?? '')) ?: []
        ), static fn(string $app): bool => $app !== '')));
        $allowLeaderOps = in_array($dashboardType, ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'], true);

        $filtered = [];
        foreach ($catalog as $key => $label) {
            $k = (string)$key;
            if (in_array($k, self::PLATFORM_ONLY_ME_PLUGIN_CARDS, true) && $authorityRole !== 'platform_admin') {
                continue;
            }
            if ($k === 'cross_role_handoff' && !in_array($authorityRole, ['platform_admin', 'app_admin'], true) && !$allowLeaderOps) {
                continue;
            }
            $cardApp = self::appForMePluginCard($k);
            if ($cardApp === 'platform' && $authorityRole !== 'platform_admin') {
                continue;
            }
            if ($cardApp !== '' && $cardApp !== 'shared' && $authorityRole !== 'platform_admin' && !in_array($cardApp, $assignedApps, true)) {
                continue;
            }
            $filtered[$k] = (string)$label;
        }
        return $filtered;
    }

    private static function appForMePluginCard(string $cardKey): string
    {
        return match (strtolower(trim($cardKey))) {
            'manufacturing_portal',
            'daily_orders',
            'production_plans',
            'dispatch_entries',
            'machine_workboard',
            'qc_workboard',
            'dispatch_ops',
            'cross_role_handoff',
            'coverage',
            'materials',
            'demands' => 'manufacturing',
            'platform_setup',
            'access_control_board',
            'user_dashboard',
            'admin_tools',
            'route_diagnostics' => 'platform',
            'approval_inbox',
            'notifications' => 'shared',
            default => '',
        };
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function accessProfileRegistry(): array
    {
        return array_merge(self::ACCESS_PROFILE_REGISTRY, SuitePermissionTemplateService::accessProfileTemplates());
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function accessProfilePermissionMatrix(): array
    {
        $matrix = [];
        foreach (self::accessProfileRegistry() as $key => $profile) {
            $matrix[(string)$key] = array_values((array)($profile['permissions'] ?? []));
        }
        return $matrix;
    }

    public static function normalizeExistingAssignments(?array $actor): array
    {
        self::ensureSchema();

        $rows = DB::fetchAll(
            "SELECT u.id,
                    u.email,
                    u.role,
                    COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                    COALESCE(NULLIF(TRIM(u.role_tier), ''), '') AS role_tier,
                    COALESCE(NULLIF(TRIM(a.dashboard_type), ''), '') AS dashboard_type,
                    COALESCE(NULLIF(TRIM(a.default_app), ''), '') AS default_app,
                    COALESCE(NULLIF(TRIM(a.assigned_apps), ''), '') AS assigned_apps,
                    COALESCE(NULLIF(TRIM(a.access_profiles), ''), '') AS access_profiles,
                    COALESCE(NULLIF(TRIM(a.permissions), ''), '') AS permissions,
                    COALESCE(NULLIF(TRIM(a.default_landing_page), ''), '') AS default_landing_page
             FROM users u
             LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
             ORDER BY u.id ASC"
        );

        $actorLabel = self::actorLabel($actor);
        $corrected = [];

        foreach ($rows as $row) {
            $userId = (int)($row['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $authorityRole = self::normalizeAccountType(
                (string)($row['authority_role'] ?? ''),
                (string)($row['role'] ?? ''),
                (string)($row['role_tier'] ?? ''),
                (string)($row['default_app'] ?? '')
            );
            $defaults = self::suggestedDefaultsForRole((string)($row['role'] ?? ''), $authorityRole);
            $canonical = self::canonicalMappingForProfileAccount((string)($row['role'] ?? ''), $authorityRole);
            $analysis = self::analyzeRoleConsistency($row);
            $requiresRepair = !empty($analysis['hard_mismatch']);
            if (!$requiresRepair) {
                continue;
            }
            $roleTier = self::legacyTierForAccountType($authorityRole);
            $dashboardType = self::normalizeDashboardType((string)($defaults['dashboard_type'] ?? ''), $authorityRole, self::roleSlug((string)($row['role'] ?? '')));
            $defaultApp = self::nullIfEmpty((string)($defaults['default_app'] ?? ''));

            $existingLanding = trim((string)($row['default_landing_page'] ?? ''));
            $defaultLanding = self::nullIfEmpty((string)($defaults['default_landing_page'] ?? self::routeForDashboardType($dashboardType)));
            if ($existingLanding !== '' && self::isLandingCompatibleWithDashboard($existingLanding, $dashboardType)) {
                $defaultLanding = $existingLanding;
            }
            $assignedApps = self::nullIfEmpty(implode(',', self::resolveAssignedApps((string)($row['assigned_apps'] ?? ''), (string)($defaultApp ?? ''), (string)($defaults['assigned_apps'] ?? ''))));
            $recommendedProfiles = $canonical !== null
                ? self::resolveAccessProfileKeys(implode(',', (array)($canonical['recommended_access_profiles'] ?? [])))
                : self::csvTokens((string)($defaults['access_profiles'] ?? ''));
            $accessProfiles = self::nullIfEmpty(implode(',', $recommendedProfiles));
            $permissions = self::nullIfEmpty(implode(',', self::csvTokens((string)($row['permissions'] ?? ($defaults['permissions'] ?? '')))));

            DB::query('UPDATE users SET role_tier=?, authority_role=? WHERE id=? LIMIT 1', [$roleTier, $authorityRole, $userId]);
            DB::query(
                     "INSERT INTO user_dashboard_assignments (user_id, dashboard_type, authority_role, default_app, default_landing_page, assigned_apps, access_profiles, permissions, dashboard_mode, default_app_mode, landing_mode, access_profiles_mode, module_visibility_mode, cross_functional_access, updated_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'auto', 'auto', 'auto', 'auto', 'auto', NULL, ?)
                 ON DUPLICATE KEY UPDATE
                    dashboard_type=VALUES(dashboard_type),
                          authority_role=VALUES(authority_role),
                    default_app=VALUES(default_app),
                    default_landing_page=VALUES(default_landing_page),
                    assigned_apps=VALUES(assigned_apps),
                    access_profiles=VALUES(access_profiles),
                    permissions=VALUES(permissions),
                    dashboard_mode=VALUES(dashboard_mode),
                    default_app_mode=VALUES(default_app_mode),
                    landing_mode=VALUES(landing_mode),
                    access_profiles_mode=VALUES(access_profiles_mode),
                    module_visibility_mode=VALUES(module_visibility_mode),
                    updated_by=VALUES(updated_by),
                    updated_at=NOW()",
                [$userId, $dashboardType, $authorityRole, $defaultApp, $defaultLanding, $assignedApps, $accessProfiles, $permissions, $actorLabel]
            );

            $corrected[] = [
                'id' => $userId,
                'email' => (string)($row['email'] ?? ''),
                'role' => (string)($row['role'] ?? ''),
                'dashboard_type' => $dashboardType,
                'default_landing_page' => (string)($defaultLanding ?? ''),
                'default_app' => (string)($defaultApp ?? ''),
                'was_test_seed' => ((int)($row['is_test_seed'] ?? 0)) === 1 ? 1 : 0,
            ];
        }

        return $corrected;
    }

    private static function scopeForUser(int $userId): array
    {
        $rows = DB::fetchAll('SELECT * FROM user_operational_scopes WHERE user_id=? AND is_active=1', [$userId]);
        if (!$rows) {
            return self::emptyScope();
        }

        $scope = self::emptyScope();
        foreach ($rows as $row) {
            $machineId = (int)($row['machine_id'] ?? 0);
            $partId = (int)($row['part_id'] ?? 0);
            $taskType = trim((string)($row['task_type'] ?? ''));
            if ($machineId > 0) {
                $scope['machine_ids'][$machineId] = $machineId;
            }
            if ($partId > 0) {
                $scope['part_ids'][$partId] = $partId;
            }
            if ($taskType !== '') {
                $scope['task_types'][$taskType] = $taskType;
            }

            if ($scope['department_code'] === '' && trim((string)($row['department_code'] ?? '')) !== '') {
                $scope['department_code'] = trim((string)$row['department_code']);
            }
            if ($scope['branch_code'] === '' && trim((string)($row['branch_code'] ?? '')) !== '') {
                $scope['branch_code'] = trim((string)$row['branch_code']);
            }
            if ($scope['ownership_role'] === '' && trim((string)($row['ownership_role'] ?? '')) !== '') {
                $scope['ownership_role'] = trim((string)$row['ownership_role']);
            }
        }

        $scope['machine_ids'] = array_values($scope['machine_ids']);
        $scope['part_ids'] = array_values($scope['part_ids']);
        $scope['task_types'] = array_values($scope['task_types']);
        return $scope;
    }

    private static function moduleVisibilityForUser(int $userId): array
    {
        $rows = DB::fetchAll('SELECT module_key FROM user_module_visibility WHERE user_id=? AND is_enabled=1 ORDER BY module_key ASC', [$userId]);
        $keys = [];
        foreach ($rows as $row) {
            $module = trim((string)($row['module_key'] ?? ''));
            if ($module !== '') {
                $keys[] = $module;
            }
        }
        return $keys;
    }

    private static function legacyTierForAccountType(string $authorityRole): string
    {
        return $authorityRole === 'platform_admin' ? 'admin' : 'editor';
    }

    private static function normalizeAccountType(string $authorityRole, string $legacyRole, string $legacyTier = '', string $defaultApp = ''): string
    {
        $normalized = strtolower(trim($authorityRole));
        if (in_array($normalized, self::VALID_ACCOUNT_TYPES, true)) {
            return $normalized;
        }

        $roleSlug = self::roleSlug($legacyRole);
        $tier = strtolower(trim($legacyTier));
        $app = strtolower(trim($defaultApp));

        if (in_array($roleSlug, ['admin', 'platform_admin', 'sysadmin', 'systemadmin', 'systemadministrator'], true)) {
            return 'platform_admin';
        }

        if ($roleSlug === 'accountadmin' || $roleSlug === 'appadmin') {
            return 'app_admin';
        }

        if ($tier === 'admin' && $app !== '' && $app !== 'platform' && $app !== 'erp_core') {
            return 'app_admin';
        }

        if (str_contains($roleSlug, 'admin') && !in_array($roleSlug, ['admin', 'platform_admin', 'sysadmin', 'systemadmin', 'systemadministrator'], true)) {
            return 'app_admin';
        }

        return 'app_user';
    }

    private static function normalizeAccountClass(string $accountClass, string $authorityRole, string $role = ''): string
    {
        $key = strtolower(trim($accountClass));
        if (isset(self::ACCOUNT_CLASSES[$key])) {
            return $key;
        }

        $roleSlug = self::roleSlug($role);
        if (in_array($roleSlug, ['platformoperations', 'admin', 'platform_admin'], true)) {
            return 'platform_operations';
        }
        if (in_array($roleSlug, ['platformsecurity', 'sysadmin', 'systemadmin', 'systemadministrator'], true)) {
            return 'platform_security';
        }
        if (in_array($roleSlug, ['appadministration', 'accountadmin', 'appadmin'], true)) {
            return 'app_administration';
        }

        return match (strtolower(trim($authorityRole))) {
            'platform_admin' => 'platform_operations',
            'app_admin' => 'app_administration',
            default => 'app_user',
        };
    }

    private static function accountTypeForAccountClass(string $accountClass, string $fallback): string
    {
        return match ($accountClass) {
            'platform_operations', 'platform_security' => 'platform_admin',
            'app_administration' => 'app_admin',
            'tv_display' => 'tv_display',
            'app_user' => 'app_user',
            default => $fallback,
        };
    }

    /**
     * Maps authority_role back to a canonical account_class.
     * Inverse operation for accountTypeForAccountClass().
     */
    private static function accountClassForAccountType(string $authorityRole): string
    {
        return match (strtolower(trim($authorityRole))) {
            'platform_admin' => 'platform_operations',
            'app_admin' => 'app_administration',
            'tv_display' => 'tv_display',
            default => 'app_user',
        };
    }

    /**
     * @return array<string,string>
     */
    private static function composeAccountClassDefaults(string $accountClass): array
    {
        return self::ACCOUNT_CLASS_BASELINES[$accountClass] ?? (self::ACCOUNT_CLASS_BASELINES['app_user'] ?? []);
    }

    /**
     * @param array<int,string> $apps
     * @return array<string,string>
     */
    private static function composeAssignedAppsDefaults(array $apps): array
    {
        $bundle = [
            'permissions' => '',
            'view_access' => '',
            'table_access' => '',
            'chart_access' => '',
            'duty_codes' => '',
            'module_visibility' => '',
        ];

        $appSet = [];
        foreach ($apps as $app) {
            $k = strtolower(trim($app));
            if ($k !== '') {
                $appSet[$k] = $k;
            }
        }

        // Keep diagnostics aligned with runtime app-grant policy.
        $appPermissionGrants = AclPolicy::appPermissionGrants();
        foreach ($appSet as $appKey) {
            $grants = (array)($appPermissionGrants[$appKey] ?? []);
            if ($grants === []) {
                continue;
            }
            $bundle['permissions'] = self::mergeTokenCsv($bundle['permissions'], implode(',', $grants));
        }

        if (isset($appSet['manufacturing'])) {
            // App assignment alone should not widen a user's operational workspace.
            // Surface visibility comes from modeled access profiles, not broad app-level fallbacks.
            $bundle['permissions'] = self::mergeTokenCsv($bundle['permissions'], 'ops.my_work.view');
        }
        if (isset($appSet['platform'])) {
            $bundle['module_visibility'] = self::mergeTokenCsv($bundle['module_visibility'], 'ops');
            $bundle['permissions'] = self::mergeTokenCsv($bundle['permissions'], 'ops.my_work.view');
        }

        return $bundle;
    }

    /**
     * @param array<string,string> $bundle
     * @return array<string,string>
     */
    private static function normalizeAssignedAppDefaultsForContext(array $bundle, string $authorityRole, string $dashboardType): array
    {
        $authorityRole = strtolower(trim($authorityRole));
        if ($authorityRole !== 'app_user') {
            return $bundle;
        }

        $permissions = self::normalizePermissionTokensForContext(
            self::csvTokens((string)($bundle['permissions'] ?? '')),
            $authorityRole,
            $dashboardType
        );

        if ($authorityRole === 'app_user') {
            $permissions = array_values(array_intersect(
                self::APP_USER_ASSIGNED_APP_BASELINE_PERMISSIONS,
                $permissions
            ));
        }

        $bundle['permissions'] = implode(',', $permissions);

        return $bundle;
    }

    /**
     * @return array<int,string>
     */
    private static function resolveRolePackKeys(string $csv): array
    {
        return self::resolveAccessProfileKeys($csv);
    }

    private static function normalizeDashboardType(string $dashboardType, string $authorityRole, string $legacyRole): string
    {
        $type = strtolower(trim($dashboardType));
        $roleSlug = self::roleSlug($legacyRole);

        if ($authorityRole === 'platform_admin') {
            if (in_array($type, ['platform_admin', 'admin', 'itadmin', 'sysadmin'], true)) {
                return 'platform_admin';
            }
            return 'platform_admin';
        }

        if ($authorityRole === 'app_admin') {
            if (in_array($type, ['app_admin', 'accountadmin'], true)) {
                return 'app_admin';
            }
            return 'app_admin';
        }

        if ($authorityRole === 'tv_display') {
            return 'display';
        }

        if (in_array($type, self::APP_USER_DASHBOARD_TYPES, true)) {
            return $type;
        }

        return match ($roleSlug) {
            'operator', 'viewer' => 'my_work',
            'assemblyleader' => 'assembly_leader',
            'qcleader' => 'qc_leader',
            'dispatchleader' => 'dispatch_leader',
            default => 'my_work',
        };
    }

    private static function emptyScope(): array
    {
        return [
            'machine_ids' => [],
            'part_ids' => [],
            'task_types' => [],
            'department_code' => '',
            'branch_code' => '',
            'ownership_role' => '',
        ];
    }

    private static function addUserColumnIfMissing(string $column, string $ddl): void
    {
        $safeColumn = DB::conn()->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
        if ($exists) {
            return;
        }
        DB::query($ddl, []);
    }

    private static function addAssignmentColumnIfMissing(string $column, string $ddl): void
    {
        $safeColumn = DB::conn()->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM user_dashboard_assignments LIKE '{$safeColumn}'");
        if ($exists) {
            return;
        }
        DB::query($ddl, []);
    }

    private static function addWorkspaceProfileColumnIfMissing(string $column, string $ddl): void
    {
        $safeColumn = DB::conn()->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM workspace_profiles LIKE '{$safeColumn}'");
        if ($exists) {
            return;
        }
        DB::query($ddl, []);
    }

    private static function insertScopeRows(int $userId, array $input, string $actor): void
    {
        $machineIds = self::csvInts((string)($input['scope_machine_ids'] ?? ''));
        $partIds = self::csvInts((string)($input['scope_part_ids'] ?? ''));
        $taskTypes = self::csvTokens((string)($input['scope_task_types'] ?? ''));
        $departmentCode = trim((string)($input['scope_department_code'] ?? ''));
        $branchCode = trim((string)($input['scope_branch_code'] ?? ''));
        $ownershipRole = trim((string)($input['scope_ownership_role'] ?? ''));

        foreach ($machineIds as $machineId) {
            DB::query(
                'INSERT INTO user_operational_scopes (user_id, machine_id, updated_by) VALUES (?, ?, ?)',
                [$userId, $machineId, $actor]
            );
        }
        foreach ($partIds as $partId) {
            DB::query(
                'INSERT INTO user_operational_scopes (user_id, part_id, updated_by) VALUES (?, ?, ?)',
                [$userId, $partId, $actor]
            );
        }
        foreach ($taskTypes as $taskType) {
            DB::query(
                'INSERT INTO user_operational_scopes (user_id, task_type, updated_by) VALUES (?, ?, ?)',
                [$userId, $taskType, $actor]
            );
        }

        if ($departmentCode !== '' || $branchCode !== '' || $ownershipRole !== '') {
            DB::query(
                'INSERT INTO user_operational_scopes (user_id, department_code, branch_code, ownership_role, updated_by) VALUES (?, ?, ?, ?, ?)',
                [
                    $userId,
                    self::nullIfEmpty($departmentCode),
                    self::nullIfEmpty($branchCode),
                    self::nullIfEmpty($ownershipRole),
                    $actor,
                ]
            );
        }
    }

    private static function csvInts(string $csv): array
    {
        $parts = preg_split('/\s*,\s*/', trim($csv)) ?: [];
        $ints = [];
        foreach ($parts as $part) {
            $v = (int)$part;
            if ($v > 0) {
                $ints[$v] = $v;
            }
        }
        return array_values($ints);
    }

    private static function csvTokens(string $csv): array
    {
        $parts = preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = trim($part);
            if ($token === '' || !preg_match('/^[a-z0-9_.:-]+$/', $token)) {
                continue;
            }
            $tokens[$token] = $token;
        }
        return array_values($tokens);
    }

    private static function hasExplicitExperienceLayoutNone(string $csv): bool
    {
        return in_array(self::EXPERIENCE_LAYOUT_NONE, self::csvTokens($csv), true);
    }

    /**
     * @return array<int,string>
     */
    private static function resolveAccessProfileKeys(string $csv): array
    {
        $registry = self::accessProfileRegistry();
        $tokens = self::csvTokens($csv);
        $resolved = [];
        foreach ($tokens as $token) {
            if (isset($registry[$token])) {
                $resolved[$token] = $token;
            }
        }
        return array_values($resolved);
    }

    /**
     * @param array<int,string> $profileKeys
     * @return array<int,string>
     */
    private static function normalizeAccessProfileKeysForContext(array $profileKeys, string $authorityRole, string $dashboardType): array
    {
        $normalized = [];
        $authorityRole = strtolower(trim($authorityRole));
        $dashboardType = strtolower(trim($dashboardType));
        $isWorkerWorkspace = $authorityRole === 'app_user' && in_array($dashboardType, ['my_work', 'operator'], true);

        foreach ($profileKeys as $key) {
            $profileKey = strtolower(trim($key));
            if ($profileKey === '') {
                continue;
            }

            if ($authorityRole !== 'app_admin' && $authorityRole !== 'platform_admin' && in_array($profileKey, self::APP_ADMIN_ONLY_ACCESS_PROFILES, true)) {
                continue;
            }

            if ($isWorkerWorkspace && in_array($profileKey, self::LEADER_ACCESS_PROFILES, true)) {
                continue;
            }

            $normalized[$profileKey] = $profileKey;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int,string> $permissionTokens
     * @return array<int,string>
     */
    private static function normalizePermissionTokensForContext(array $permissionTokens, string $authorityRole, string $dashboardType): array
    {
        $normalized = [];
        $authorityRole = strtolower(trim($authorityRole));
        $dashboardType = strtolower(trim($dashboardType));
        $isWorkerWorkspace = $authorityRole === 'app_user' && in_array($dashboardType, ['my_work', 'operator'], true);

        foreach ($permissionTokens as $token) {
            $permission = strtolower(trim($token));
            if ($permission === '') {
                continue;
            }

            if ($authorityRole !== 'app_admin' && $authorityRole !== 'platform_admin' && in_array($permission, self::APP_ADMIN_ONLY_PERMISSION_TOKENS, true)) {
                continue;
            }

            if ($isWorkerWorkspace && in_array($permission, self::LEADER_ONLY_PERMISSION_TOKENS, true)) {
                continue;
            }

            $normalized[$permission] = $permission;
        }

        return array_values($normalized);
    }

    /**
     * @param array<int,string> $profileKeys
     * @return array<string,string>
     */
    private static function composeAccessProfileDefaults(array $profileKeys): array
    {
        $bundle = [
            'assigned_apps' => '',
            'permissions' => '',
            'view_access' => '',
            'table_access' => '',
            'chart_access' => '',
            'duty_codes' => '',
            'module_visibility' => '',
            'task_types' => '',
            'notification_surfaces' => '',
            'home_widgets' => '',
            'default_dashboard_type' => '',
            'default_app' => '',
            'default_landing_page' => '',
        ];

        $assignedApps = [];
        $permissions = [];
        $viewAccess = [];
        $tableAccess = [];
        $chartAccess = [];
        $dutyCodes = [];
        $moduleVisibility = [];
        $taskTypes = [];

        foreach ($profileKeys as $key) {
            $profile = self::accessProfileRegistry()[$key] ?? null;
            if (!is_array($profile)) {
                continue;
            }

            $app = strtolower(trim((string)($profile['app'] ?? '')));
            if ($app !== '') {
                $assignedApps[$app] = $app;
            }

            foreach ((array)($profile['permissions'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $permissions[$v] = $v;
                }
            }
            foreach ((array)($profile['view_access'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $viewAccess[$v] = $v;
                }
            }
            foreach ((array)($profile['table_access'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $tableAccess[$v] = $v;
                }
            }
            foreach ((array)($profile['chart_access'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $chartAccess[$v] = $v;
                }
            }
            foreach ((array)($profile['duty_codes'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $dutyCodes[$v] = $v;
                }
            }
            foreach ((array)($profile['module_visibility'] ?? []) as $token) {
                $v = self::normalizeModuleKey((string)$token);
                if ($v !== '') {
                    $moduleVisibility[$v] = $v;
                }
            }
            foreach ((array)(($profile['scope_hints'] ?? [])['task_types'] ?? []) as $token) {
                $v = strtolower(trim((string)$token));
                if ($v !== '') {
                    $taskTypes[$v] = $v;
                }
            }

            if ($bundle['default_dashboard_type'] === '' && trim((string)($profile['default_dashboard_type'] ?? '')) !== '') {
                $bundle['default_dashboard_type'] = strtolower(trim((string)$profile['default_dashboard_type']));
            }
            if ($bundle['default_app'] === '' && trim((string)($profile['default_app'] ?? '')) !== '') {
                $bundle['default_app'] = strtolower(trim((string)$profile['default_app']));
            }
            if ($bundle['default_landing_page'] === '' && trim((string)($profile['default_landing_page'] ?? '')) !== '') {
                $bundle['default_landing_page'] = trim((string)$profile['default_landing_page']);
            }
        }

        $bundle['assigned_apps'] = implode(',', array_values($assignedApps));
        $bundle['permissions'] = implode(',', array_values($permissions));
        $bundle['view_access'] = implode(',', array_values($viewAccess));
        $bundle['table_access'] = implode(',', array_values($tableAccess));
        $bundle['chart_access'] = implode(',', array_values($chartAccess));
        $bundle['duty_codes'] = implode(',', array_values($dutyCodes));
        $bundle['module_visibility'] = implode(',', array_values($moduleVisibility));
        $bundle['task_types'] = implode(',', array_values($taskTypes));

        return $bundle;
    }

    /**
     * @return array<string,string> bundle => level
     */
    private static function parseCrossFunctionalAccess(string $csv): array
    {
        $pairs = preg_split('/\s*,\s*/', trim($csv)) ?: [];
        $out = [];
        foreach ($pairs as $pair) {
            $entry = strtolower(trim($pair));
            if ($entry === '') {
                continue;
            }
            $parts = explode(':', $entry, 2);
            $bundle = trim((string)($parts[0] ?? ''));
            $level = trim((string)($parts[1] ?? 'view'));
            if (!isset(self::CROSS_FUNCTIONAL_BUNDLES[$bundle])) {
                continue;
            }
            if (!in_array($level, self::CROSS_PERMISSION_LEVELS, true)) {
                $level = 'view';
            }
            $out[$bundle] = $level;
        }
        ksort($out);
        return $out;
    }

    /**
     * @param array<string,string> $grants
     */
    private static function serializeCrossFunctionalAccess(array $grants): string
    {
        $pairs = [];
        foreach ($grants as $bundle => $level) {
            $bundleKey = strtolower(trim((string)$bundle));
            $levelKey = strtolower(trim((string)$level));
            if (!isset(self::CROSS_FUNCTIONAL_BUNDLES[$bundleKey])) {
                continue;
            }
            if (!in_array($levelKey, self::CROSS_PERMISSION_LEVELS, true)) {
                $levelKey = 'view';
            }
            $pairs[] = $bundleKey . ':' . $levelKey;
        }
        sort($pairs);
        return implode(',', $pairs);
    }

    /**
     * @param array<string,string> $grants bundle => level
     * @return array<string,string>
     */
    private static function composeCrossFunctionalDefaults(array $grants): array
    {
        $bundle = [
            'assigned_apps' => '',
            'permissions' => '',
            'view_access' => '',
            'table_access' => '',
            'chart_access' => '',
            'duty_codes' => '',
            'module_visibility' => '',
            'access_profiles' => '',
        ];

        $assignedApps = [];
        $permissions = [];
        $viewAccess = [];
        $tableAccess = [];
        $chartAccess = [];
        $dutyCodes = [];
        $moduleVisibility = [];
        $accessProfiles = [];

        foreach ($grants as $bundleKey => $level) {
            $cfg = self::CROSS_FUNCTIONAL_BUNDLES[$bundleKey] ?? null;
            if (!is_array($cfg)) {
                continue;
            }
            $targetLevel = strtolower(trim((string)$level));
            if (!in_array($targetLevel, self::CROSS_PERMISSION_LEVELS, true)) {
                $targetLevel = 'view';
            }
            $targetRank = array_search($targetLevel, self::CROSS_PERMISSION_LEVELS, true);
            if ($targetRank === false) {
                $targetRank = 0;
            }

            foreach (self::CROSS_PERMISSION_LEVELS as $idx => $candidateLevel) {
                if ($idx > $targetRank) {
                    break;
                }
                $caps = (array)($cfg['levels'][$candidateLevel] ?? []);
                foreach ((array)($caps['permissions'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $permissions[$k] = $k;
                    }
                }
                foreach ((array)($caps['view_access'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $viewAccess[$k] = $k;
                    }
                }
                foreach ((array)($caps['table_access'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $tableAccess[$k] = $k;
                    }
                }
                foreach ((array)($caps['chart_access'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $chartAccess[$k] = $k;
                    }
                }
                foreach ((array)($caps['duty_codes'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $dutyCodes[$k] = $k;
                    }
                }
                foreach ((array)($caps['module_visibility'] ?? []) as $v) {
                    $k = self::normalizeModuleKey((string)$v);
                    if ($k !== '') {
                        $moduleVisibility[$k] = $k;
                    }
                }
                foreach ((array)($caps['access_profiles'] ?? []) as $v) {
                    $k = strtolower(trim((string)$v));
                    if ($k !== '') {
                        $accessProfiles[$k] = $k;
                    }
                }
                $assignedApps['manufacturing'] = 'manufacturing';
            }
        }

        $bundle['assigned_apps'] = implode(',', array_values($assignedApps));
        $bundle['permissions'] = implode(',', array_values($permissions));
        $bundle['view_access'] = implode(',', array_values($viewAccess));
        $bundle['table_access'] = implode(',', array_values($tableAccess));
        $bundle['chart_access'] = implode(',', array_values($chartAccess));
        $bundle['duty_codes'] = implode(',', array_values($dutyCodes));
        $bundle['module_visibility'] = implode(',', array_values($moduleVisibility));
        $bundle['access_profiles'] = implode(',', array_values($accessProfiles));

        return $bundle;
    }

    private static function mergeTokenCsv(string $profileCsv, string $explicitCsv): string
    {
        $merged = [];
        foreach (self::csvTokens($profileCsv) as $token) {
            $merged[$token] = $token;
        }
        foreach (self::csvTokens($explicitCsv) as $token) {
            $merged[$token] = $token;
        }
        return implode(',', array_values($merged));
    }

    private static function resolveDefaultApp(string $defaultApp, string $fallback = ''): string
    {
        $candidate = self::canonicalAppKey($defaultApp);
        if ($candidate !== '') {
            return $candidate;
        }

        $fallback = self::canonicalAppKey($fallback);
        return $fallback !== '' ? $fallback : '';
    }

    /**
     * @return array<int,string>
     */
    private static function resolveAssignedApps(string $assignedApps, string $defaultApp = '', string $fallback = ''): array
    {
        $apps = self::csvTokens($assignedApps);
        if (empty($apps)) {
            $apps = self::csvTokens($fallback);
        }

        if (in_array(self::ALL_ENABLED_APPS_TOKEN, $apps, true)) {
            $apps = array_values(array_unique(array_merge(
                array_filter($apps, static fn(string $app): bool => $app !== self::ALL_ENABLED_APPS_TOKEN),
                self::allEnabledGovernanceAppKeys()
            )));
        }

        $default = self::resolveDefaultApp($defaultApp, $fallback);
        if ($default !== '' && !in_array($default, $apps, true)) {
            $apps[] = $default;
        }

        $normalized = [];
        foreach ($apps as $app) {
            $key = self::canonicalAppKey($app);
            if ($key === '') {
                continue;
            }
            $normalized[$key] = $key;
        }

        return array_values($normalized);
    }

    private static function normalizeModuleKey(string $module): string
    {
        $v = strtolower(trim($module));
        if ($v === '') {
            return '';
        }
        return self::MODULE_SYNONYMS[$v] ?? $v;
    }

    /**
     * @return array{view?:string,table?:string,chart?:string}
     */
    private static function tokensForPath(string $path): array
    {
        foreach (self::PATH_TOKEN_RULES as $rule) {
            $prefix = (string)($rule['prefix'] ?? '');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $out = [];
                foreach (['view', 'table', 'chart'] as $kind) {
                    $token = trim((string)($rule[$kind] ?? ''));
                    if ($token !== '') {
                        $out[$kind] = $token;
                    }
                }
                return $out;
            }
        }
        return [];
    }

    private static function moduleForPath(string $path): string
    {
        if (str_starts_with($path, '/apps/manufacturing/materials')) {
            return 'materials';
        }

        if (str_starts_with($path, '/manufacturing/')) {
            if (str_starts_with($path, '/manufacturing/coverage')) {
                return 'coverage';
            }
            if (str_starts_with($path, '/manufacturing/dispatch')) {
                return 'dispatch';
            }
            if (str_starts_with($path, '/manufacturing/assembly')) {
                return 'assembly';
            }
            if (str_starts_with($path, '/manufacturing/qc')) {
                return 'qc';
            }
            if (str_starts_with($path, '/manufacturing/stage-board')) {
                return 'production';
            }
            return 'production';
        }

        foreach (self::PATH_MODULE_RULES as $rule) {
            $prefix = (string)($rule['prefix'] ?? '');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return self::normalizeModuleKey((string)($rule['module'] ?? ''));
            }
        }
        return '';
    }

    private static function appForPath(string $path): string
    {
        if ($path === '/' || str_starts_with($path, '/admin/') || $path === '/admin' || str_starts_with($path, '/ops/access-control') || str_starts_with($path, '/ops/user-dashboard') || str_starts_with($path, '/ops/dashboard-assignments') || str_starts_with($path, '/ops/navigation-tree') || str_starts_with($path, '/ops/organization') || str_starts_with($path, '/ops/display-manager')) {
            return 'platform';
        }

        if (str_starts_with($path, '/apps/')) {
            $parts = explode('/', trim($path, '/'));
            return strtolower(trim((string)($parts[1] ?? '')));
        }

        if (
            str_starts_with($path, '/manufacturing/')
            || str_starts_with($path, '/production')
            || str_starts_with($path, '/daily-orders')
            || str_starts_with($path, '/pre-orders')
            || str_starts_with($path, '/machines')
            || str_starts_with($path, '/products')
            || str_starts_with($path, '/part-machine-map')
            || str_starts_with($path, '/qc-')
            || str_starts_with($path, '/dispatch-entries')
        ) {
            return 'manufacturing';
        }

        return '';
    }

    private static function dashboardTypeForPath(string $path): string
    {
        return match ($path) {
            '/apps/manufacturing/production-dashboard' => 'production_leader',
            '/apps/manufacturing/assembly-dashboard' => 'assembly_leader',
            '/apps/manufacturing/qc-dashboard' => 'qc_leader',
            '/apps/manufacturing/dispatch-dashboard' => 'dispatch_leader',
            default => '',
        };
    }

    private static function canAccessDashboardTypeForContext(array $ctx, string $targetType, string $dashboardRoute, string $path): bool
    {
        $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        if ($authorityRole === 'app_admin') {
            return $targetType === 'app_admin';
        }

        return $targetType === (string)($ctx['dashboard_type'] ?? 'production_leader');
    }

    private static function canAccessAssignedApp(array $ctx, string $appKey): bool
    {
        $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        $normalizedApp = strtolower(trim($appKey));
        if ($normalizedApp === '' || $normalizedApp === 'shared') {
            return true;
        }

        if ($normalizedApp === 'platform' || $normalizedApp === 'erp_core') {
            return in_array($authorityRole, ['platform_admin', 'app_admin'], true)
                && self::isAppEnabled($normalizedApp);
        }

        return self::isAppEnabled($normalizedApp)
            && in_array($normalizedApp, array_values((array)($ctx['active_assigned_apps'] ?? [])), true);
    }

    /**
     * @param array<int,string> $activeAssignedApps
     * @return array<int,string>
     */
    private static function filterInactiveAppModules(array $modules, array $activeAssignedApps): array
    {
        $activeSet = array_fill_keys($activeAssignedApps, true);
        $filtered = [];
        foreach ($modules as $module) {
            $moduleKey = self::normalizeModuleKey((string)$module);
            if ($moduleKey === '') {
                continue;
            }
            $ownerApp = self::ownerAppForModule($moduleKey);
            if ($ownerApp !== '' && !isset($activeSet[$ownerApp])) {
                continue;
            }
            $filtered[$moduleKey] = $moduleKey;
        }
        return array_values($filtered);
    }

    /**
     * @param array<int,string> $activeAssignedApps
     */
    private static function effectiveDefaultApp(string $defaultApp, array $activeAssignedApps): string
    {
        $defaultApp = strtolower(trim($defaultApp));
        if ($defaultApp !== '' && in_array($defaultApp, $activeAssignedApps, true)) {
            return $defaultApp;
        }
        if ($activeAssignedApps !== []) {
            return (string)$activeAssignedApps[0];
        }
        if (self::isAppEnabled('platform')) {
            return 'platform';
        }
        return '';
    }

    private static function ownerAppForModule(string $moduleKey): string
    {
        return match ($moduleKey) {
            'production', 'assembly', 'qc', 'dispatch', 'demands', 'coverage', 'materials' => 'manufacturing',
            'admin', 'ops' => 'platform',
            default => '',
        };
    }

    private static function canonicalAppKey(string $app): string
    {
        $normalized = strtolower(trim($app));
        if ($normalized === 'erp_core') {
            return 'platform';
        }

        return $normalized;
    }

    /**
     * @return array<string,bool>
     */
    private static function enabledAppMap(): array
    {
        if (self::$enabledAppCache !== null) {
            return self::$enabledAppCache;
        }

        $map = [];
        try {
            $rows = DB::fetchAll("SELECT app_key FROM core_apps WHERE status='enabled'");
            foreach ($rows as $row) {
                $key = strtolower(trim((string)($row['app_key'] ?? '')));
                if ($key !== '') {
                    $map[$key] = true;
                }
            }
        } catch (\Throwable $e) {
            // Keep host boot-safe if app registry is unavailable.
        }

        self::$enabledAppCache = $map;
        return $map;
    }

    private static function isPlatformOnlyPath(string $path): bool
    {
        return $path === '/'
            || str_starts_with($path, '/admin/')
            || $path === '/admin'
            || str_starts_with($path, '/ops/access-control')
            || str_starts_with($path, '/ops/user-dashboard')
            || str_starts_with($path, '/ops/dashboard-assignments')
            || str_starts_with($path, '/ops/navigation-tree')
                || str_starts_with($path, '/ops/display-manager')
            || str_starts_with($path, '/ops/audit-log')
            || str_starts_with($path, '/ops/audit-explorer');
    }

    private static function isSupervisoryOnlyPath(string $path): bool
    {
        return str_starts_with($path, '/ops/approval-inbox')
            || str_starts_with($path, '/ops/handoff-board')
            || str_starts_with($path, '/apps/manufacturing/handoffs');
    }

    private static function isGovernanceOnlyPath(string $path): bool
    {
        return self::isPlatformOnlyPath($path);
    }

    private static function isDashboardRoute(string $path): bool
    {
        return in_array($path, [
            '/apps/manufacturing/production-dashboard',
            '/apps/manufacturing/assembly-dashboard',
            '/apps/manufacturing/qc-dashboard',
            '/apps/manufacturing/dispatch-dashboard',
        ], true);
    }

    private static function isQcEntryExecutionPath(string $path, string $method): bool
    {
        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === 'GET') {
            return in_array($path, ['/qc-entries/add', '/qc-entries/edit'], true);
        }

        if ($normalizedMethod === 'POST') {
            return in_array($path, ['/qc-entries/add', '/qc-entries/edit', '/qc-entries/start-draft'], true);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $ctx
     */
    private static function canAccessQcEntryExecutionForContext(array $ctx): bool
    {
        if (!self::canAccessAssignedApp($ctx, 'manufacturing')) {
            return false;
        }

        $permissions = array_values(array_map(
            static fn(string $token): string => strtolower(trim($token)),
            (array)($ctx['permissions'] ?? [])
        ));

        foreach (['qc_entries.leader.view', 'workflow.qc_entry.submit', 'workflow.qc_entry.approve', 'workflow.qc_entry.reopen'] as $token) {
            if (in_array($token, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private static function actorLabel(?array $actor): string
    {
        $email = trim((string)($actor['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'system';
    }

    private static function sanitizeAssignableRole(string $role): string
    {
        return self::normalizeOperationalRole($role);
    }

    private static function resolveProvisioningRole(array $input, string $authorityRole, string $fallbackRole = ''): string
    {
        $explicitRole = trim((string)($input['operational_role'] ?? ($input['legacy_role'] ?? ($input['role'] ?? ''))));
        if ($explicitRole !== '') {
            return self::sanitizeAssignableRole($explicitRole);
        }

        $dashboardType = strtolower(trim((string)($input['dashboard_type'] ?? '')));
        $defaultApp = strtolower(trim((string)($input['default_app'] ?? '')));

        if ($authorityRole === 'platform_admin') {
            return 'Platform Operations';
        }
        if ($authorityRole === 'app_admin') {
            return 'Application Administrator';
        }

        return match ($dashboardType) {
            'production_leader' => 'Production',
            'assembly_leader' => 'Assembly',
            'qc_leader' => 'QC',
            'dispatch_leader' => 'Dispatch',
            default => match ($defaultApp) {
                'accounting' => 'Accounting Followup',
                'hr' => 'HR Processing',
                default => trim($fallbackRole) !== '' ? self::sanitizeAssignableRole($fallbackRole) : 'General User',
            },
        };
    }

    private static function assertPlatformAdminDemotionAllowed(int $userId, string $currentAccountType, string $targetAccountType, int $actorId = 0): void
    {
        if ($currentAccountType !== 'platform_admin' || $targetAccountType === 'platform_admin') {
            return;
        }

        if ($actorId > 0 && $actorId === $userId) {
            throw new \RuntimeException('Cannot demote your own platform admin account. Ask another platform admin to change your access level.');
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c
             FROM users
             WHERE id <> ?
               AND COALESCE(NULLIF(TRIM(authority_role), ''), '') = 'platform_admin'",
            [$userId]
        );
        $otherPlatformAdmins = (int)($row['c'] ?? 0);
        if ($otherPlatformAdmins <= 0) {
            throw new \RuntimeException('Cannot demote the only platform admin account. Create or promote another platform admin first.');
        }
    }

    private static function assertLastPlatformAdminActiveGuard(int $userId, string $currentAccountType, string $targetStatus): void
    {
        if ($targetStatus === 'active' || $currentAccountType !== 'platform_admin') {
            return;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c
             FROM users
             WHERE id <> ?
               AND LOWER(COALESCE(NULLIF(TRIM(account_status), ''), 'active')) = 'active'
               AND COALESCE(NULLIF(TRIM(authority_role), ''), '') = 'platform_admin'",
            [$userId]
        );

        $otherActivePlatformAdmins = (int)($row['c'] ?? 0);
        if ($otherActivePlatformAdmins <= 0) {
            throw new \RuntimeException('Cannot deactivate the last active platform admin account.');
        }
    }

    private static function normalizeOperationalRole(string $profile): string
    {
        $raw = preg_replace('/[^a-zA-Z0-9 _-]/', '', trim($profile)) ?? '';
        if ($raw === '') {
            return 'Production Leader';
        }

        $slug = self::roleSlug($raw);
        $canonicalSlug = self::OPERATIONAL_ROLE_ALIASES[$slug] ?? $slug;
        foreach (self::OPERATIONAL_ROLES as $allowed) {
            if (self::roleSlug($allowed) === $canonicalSlug) {
                return $allowed;
            }
        }

        return ucwords(strtolower($raw));
    }

    private static function operationalProfileSlug(string $profile): string
    {
        $slug = self::roleSlug($profile);
        return self::OPERATIONAL_ROLE_ALIASES[$slug] ?? $slug;
    }

    /**
     * @return array<int,string>
     */
    public static function assignableRoles(): array
    {
        return self::OPERATIONAL_ROLES;
    }

    /**
     * @return array<int,string>
     */
    public static function operationalProfiles(): array
    {
        return self::OPERATIONAL_ROLES;
    }

    private static function nullIfEmpty(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private static function sanitizeDutyNotes(string $notes): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return '';
        }
        return substr($notes, 0, 2000);
    }

    private static function normalizeAccountStatus(string $status): string
    {
        $s = strtolower(trim($status));
        return match ($s) {
            'active', 'disabled', 'pending', 'all' => $s,
            default => '',
        };
    }

    private static function normalizeVerificationStatus(string $status): string
    {
        $value = strtolower(trim($status));
        return match ($value) {
            'invite_pending', 'setup_pending', 'ready' => $value,
            default => 'ready',
        };
    }

    private static function normalizeSecurityStatus(string $status, bool $twoFactorEnabled = false, string $verificationStatus = 'ready'): string
    {
        $value = strtolower(trim($status));
        if ($twoFactorEnabled) {
            return '2fa_enabled';
        }

        if (in_array($value, ['password_setup_pending', 'standard', '2fa_enabled', 'security_attention'], true)) {
            return $value;
        }

        if (in_array(strtolower(trim($verificationStatus)), ['invite_pending', 'setup_pending'], true)) {
            return 'password_setup_pending';
        }

        return 'standard';
    }

    private static function sanitizeOptionalText(string $v, int $maxLen): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        $v = preg_replace('/\s+/', ' ', $v) ?? '';
        return substr($v, 0, $maxLen);
    }

    private static function generateTemporaryPassword(): string
    {
        return 'Tmp-' . substr(bin2hex(random_bytes(8)), 0, 16);
    }

    private static function suggestedDefaultsForRole(string $role, ?string $authorityRole = null): array
    {
        $slug = self::operationalProfileSlug($role);
        $fallback = self::ROLE_DEFAULTS[$slug] ?? [
            'authority_role' => 'app_user',
            'dashboard_type' => 'my_work',
            'default_app' => '',
            'default_landing_page' => '/',
            'assigned_apps' => '',
            'access_profiles' => '',
            'permissions' => 'ops.my_work.view',
            'role_tier' => 'editor',
        ];

        $targetAccount = strtolower(trim((string)($authorityRole ?? (string)($fallback['authority_role'] ?? 'app_user'))));
        $canonical = self::canonicalMappingForProfileAccount($role, $targetAccount);
        if ($canonical === null) {
            return $fallback;
        }

        $recommendedProfiles = self::resolveAccessProfileKeys(implode(',', (array)($canonical['recommended_access_profiles'] ?? [])));

        return [
            'authority_role' => $targetAccount,
            'dashboard_type' => (string)($canonical['dashboard_type'] ?? (string)($fallback['dashboard_type'] ?? 'my_work')),
            'default_app' => (string)($canonical['default_app'] ?? (string)($fallback['default_app'] ?? '')),
            'default_landing_page' => (string)($canonical['default_landing_page'] ?? (string)($fallback['default_landing_page'] ?? '/')),
            'role_tier' => self::legacyTierForAccountType($targetAccount),
            'assigned_apps' => (string)($canonical['default_app'] ?? (string)($fallback['assigned_apps'] ?? '')),
            'access_profiles' => implode(',', $recommendedProfiles),
            'permissions' => (string)($fallback['permissions'] ?? 'ops.my_work.view'),
            'baseline_module_visibility' => implode(',', (array)($canonical['baseline_module_visibility'] ?? [])),
            'alternate_landing_pages' => (array)($canonical['alternate_landing_pages'] ?? []),
        ];
    }

    private static function normalizeMode(string $mode): string
    {
        return strtolower(trim($mode)) === 'manual' ? 'manual' : 'auto';
    }

    private static function canonicalMappingForProfileAccount(string $profile, string $authorityRole): ?array
    {
        $slug = self::operationalProfileSlug($profile);
        $key = $slug . '|' . strtolower(trim($authorityRole));
        $cfg = self::PROFILE_ACCOUNT_CANONICAL[$key] ?? null;
        return is_array($cfg) ? $cfg : null;
    }

    private static function allowedDashboardsForAccount(string $authorityRole): array
    {
        return self::DASHBOARD_OPTIONS_BY_ACCOUNT[$authorityRole] ?? self::DASHBOARD_OPTIONS_BY_ACCOUNT['app_user'];
    }

    private static function allowedLandingRoutesForDashboard(string $dashboardType): array
    {
        $key = strtolower(trim($dashboardType));
        $map = self::landingOptionsByDashboardForUi();
        return $map[$key] ?? ['/'];
    }

    private static function isDashboardCompatibleWithAccount(string $dashboardType, string $authorityRole): bool
    {
        return in_array(strtolower(trim($dashboardType)), self::allowedDashboardsForAccount($authorityRole), true);
    }

    private static function isLandingCompatibleWithDashboard(string $landing, string $dashboardType): bool
    {
        $trimmed = trim($landing);
        if ($trimmed === '') {
            return true;
        }
        if ($trimmed === '/') {
            return true;
        }
        return in_array($trimmed, self::allowedLandingRoutesForDashboard($dashboardType), true);
    }

    private static function isDefaultAppCompatible(string $defaultApp, string $authorityRole, ?array $canonical): bool
    {
        $app = strtolower(trim($defaultApp));
        if ($app === '') {
            return true;
        }

        if ($authorityRole === 'platform_admin') {
            return in_array($app, ['platform', 'erp_core'], true);
        }

        if ($authorityRole === 'app_admin') {
            return true;
        }

        if ($canonical !== null && trim((string)($canonical['default_app'] ?? '')) !== '') {
            return $app === strtolower(trim((string)$canonical['default_app']));
        }

        return true;
    }

    private static function analyzeRoleConsistency(array $row): array
    {
        $role = (string)($row['role'] ?? '');
        $authorityRole = self::normalizeAccountType(
            (string)($row['authority_role'] ?? ''),
            $role,
            (string)($row['role_tier'] ?? ''),
            (string)($row['default_app'] ?? '')
        );
        $dashboard = strtolower(trim((string)($row['dashboard_type'] ?? '')));
        $landing = trim((string)($row['default_landing_page'] ?? ''));
        $app = strtolower(trim((string)($row['default_app'] ?? '')));
        $dashboardMode = self::normalizeMode((string)($row['dashboard_mode'] ?? 'auto'));
        $landingMode = self::normalizeMode((string)($row['landing_mode'] ?? 'auto'));
        $appMode = self::normalizeMode((string)($row['default_app_mode'] ?? 'auto'));
        $accountClass = self::normalizeAccountClass((string)($row['account_class'] ?? ''), $authorityRole, $role);
        $assignedApps = self::resolveAssignedApps((string)($row['assigned_apps'] ?? ''), $app, '');
        $selectedRolePacks = self::resolveRolePackKeys((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? '')));

        $warnings = [];
        $labels = [];
        $hard = false;

        if (!isset(self::ACCOUNT_CLASSES[$accountClass])) {
            $warnings[] = 'Needs normalization';
            $labels[] = 'Needs normalization';
            $hard = true;
        }

        if ($accountClass === 'app_user' && empty($assignedApps)) {
            $warnings[] = 'Needs apps';
            $labels[] = 'Needs apps';
            $hard = true;
        }

        if ($accountClass === 'app_user' && empty($selectedRolePacks)) {
            $warnings[] = 'Needs roles';
            $labels[] = 'Needs roles';
            $hard = true;
        }

        if (!in_array($authorityRole, self::VALID_ACCOUNT_TYPES, true)) {
            $warnings[] = 'Needs normalization';
            $labels[] = 'Needs normalization';
            $hard = true;
        }

        if (($accountClass === 'app_user' && $authorityRole !== 'app_user')
            || ($accountClass === 'app_administration' && $authorityRole !== 'app_admin')
            || (in_array($accountClass, ['platform_operations', 'platform_security'], true) && $authorityRole !== 'platform_admin')) {
            $warnings[] = 'Needs normalization';
            $labels[] = 'Needs normalization';
            $hard = true;
        }

        if ($landingMode === 'manual') {
            if (!self::isDashboardCompatibleWithAccount($dashboard, $authorityRole) || !self::isLandingCompatibleWithDashboard($landing, $dashboard)) {
                $warnings[] = 'Needs normalization';
                $labels[] = 'Needs normalization';
                $hard = true;
            }
        }

        if (!self::isDefaultAppCompatible($app, $authorityRole, null)) {
            $warnings[] = 'Needs normalization';
            $labels[] = 'Needs normalization';
            $hard = true;
        }

        if ($hard) {
            return [
                'warnings' => array_values(array_unique($warnings)),
                'hard_mismatch' => true,
                'labels' => array_values(array_unique($labels)),
            ];
        }

        $labels[] = 'Ready';

        if ($dashboardMode === 'manual' || $landingMode === 'manual' || $appMode === 'manual') {
            $labels[] = 'Manual override active';
        }

        return [
            'warnings' => [],
            'hard_mismatch' => false,
            'labels' => array_values(array_unique($labels)),
        ];
    }

    private static function roleSlug(string $role): string
    {
        $flat = strtolower(trim($role));
        return preg_replace('/[^a-z0-9]+/', '', $flat) ?? '';
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $safeTable = DB::conn()->real_escape_string($table);
            $safeColumn = DB::conn()->real_escape_string($column);
            $row = DB::fetchOne("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        if ($params === []) {
            return $fallback;
        }

        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }

        return strtr($fallback, $replace);
    }
}
