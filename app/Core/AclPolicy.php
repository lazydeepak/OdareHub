<?php
declare(strict_types=1);

namespace App\Core;

use Plugins\Base\Services\UserDashboardAssignmentService;

final class AclPolicy
{
    private static bool $registrySynced = false;

    /**
     * @var array<string,array<string,bool>>|null
     */
    private static ?array $effectiveRolePermissions = null;

    /**
     * @var array<int,string>|null
     */
    private static ?array $roleKeysCache = null;


    /**
     * Full permission set granted to users who have the corresponding app in assigned_apps.
     * This is the simplified app-grant model: app access = full normal module access.
     *
     * @var array<string,array<int,string>>
     */
    private const APP_PERMISSION_GRANTS = [
        'manufacturing' => [
            'ops.my_work.view',
            'ops.handoff.view',
            'ops.handoff.assign_owner',
            'ops.handoff.escalate',
            'ops.cockpit.view',
            'ops.approval_inbox.view',
            'ops.audit_explorer.view',
            'ops.notifications.view',
            'ops.notifications.manage',
            'daily_orders.index.view',
            'daily_orders.360.view',
            'products.360.view',
            'machines.leader.view',
            'qc_entries.leader.view',
            'dispatch_entries.leader.view',
            'dispatch_entries.quick_status',
            'dispatch_entries.transition',
            'workflow.production_plan.submit',
            'workflow.production_plan.approve',
            'workflow.production_plan.reject',
            'workflow.production_plan.reopen',
            'workflow.production_plan.hold',
            'workflow.production_plan.resume',
            'workflow.production_plan.finalize',
            'workflow.production_plan.cancel',
            'workflow.production_plan.override_lock',
            'workflow.assembly_plan.submit',
            'workflow.assembly_plan.approve',
            'workflow.assembly_plan.reject',
            'workflow.assembly_plan.reopen',
            'workflow.assembly_plan.hold',
            'workflow.assembly_plan.resume',
            'workflow.assembly_plan.finalize',
            'workflow.assembly_plan.cancel',
            'workflow.qc_entry.submit',
            'workflow.qc_entry.approve',
            'workflow.qc_entry.reject',
            'workflow.qc_entry.reopen',
            'workflow.qc_entry.finalize',
            'workflow.qc_entry.cancel',
            'workflow.qc_entry.override_lock',
            'workflow.dispatch_entry.submit',
            'workflow.dispatch_entry.approve',
            'workflow.dispatch_entry.reject',
            'workflow.dispatch_entry.reopen',
            'workflow.dispatch_entry.hold',
            'workflow.dispatch_entry.resume',
            'workflow.dispatch_entry.finalize',
            'workflow.dispatch_entry.cancel',
            'workflow.dispatch_entry.handoff',
            'workflow.dispatch_entry.override_lock',
        ],
    ];

    /**
     * Runtime app-grant permission map used by access checks.
     * Kept as a public read API so diagnostics can stay in sync with runtime behavior.
     *
     * @return array<string,array<int,string>>
     */
    public static function appPermissionGrants(): array
    {
        return self::APP_PERMISSION_GRANTS;
    }

    /**
     * @return array<string,string>
     */
    public static function permissionRegistry(): array
    {
        $registry = [
            'admin.strict' => 'Legacy strict admin gate for core CRUD routes.',
            'admin.tools.access' => 'Access Apps Manager and system administration tools.',
            'admin_tools.manage' => 'Manage admin tools workspace and privileged operations.',
            'admin.base.builder' => 'Access Base schema builder and low-level model tools.',
            'base.manage' => 'Manage Base module structure and schema operations.',
            'routes.manage' => 'Inspect and manage route-level tooling.',
            'plugins.update' => 'Install, update, activate, and deactivate plugins/packages.',
            'acl.manage' => 'View and edit ACL role-permission assignments.',
            'developer.tools.access' => 'Access developer-only utilities in non-production environments.',
            'organization.view' => 'View the Organization master-data module and company defaults.',
            'organization.manage' => 'Create and update company, branch, fiscal, and branding settings in Organization.',

            'ops.my_work.view' => 'View My Work inbox.',
            'ops.handoff.view' => 'View cross-role handoff board.',
            'ops.handoff.assign_owner' => 'Assign owner on cross-role handoff board.',
            'ops.handoff.escalate' => 'Escalate item on cross-role handoff board.',
            'ops.cockpit.view' => 'View Supervisor/GM cockpit.',
            'ops.approval_inbox.view' => 'View governance approval inbox.',
            'ops.audit_explorer.view' => 'View cross-entity audit explorer.',
            'ops.notifications.view' => 'View notifications list.',
            'ops.notifications.manage' => 'Mark notifications read/dismiss.',

            'daily_orders.index.view' => 'Access Daily Orders list page.',
            'daily_orders.360.view' => 'Open Daily Orders 360 view.',
            'products.360.view' => 'Open Product 360 view.',

            'machines.leader.view' => 'Access production dashboard.',
            'qc_entries.leader.view' => 'Access QC dashboard.',
            'dispatch_entries.leader.view' => 'Access dispatch dashboard.',
            'dispatch_entries.quick_status' => 'Update dispatch quick status.',
            'dispatch_entries.transition' => 'Run dispatch leader transitions.',

            'workflow.production_plan.submit' => 'Submit production plan for approval.',
            'workflow.production_plan.approve' => 'Approve/reject production plan.',
            'workflow.production_plan.reject' => 'Reject production plan submission.',
            'workflow.production_plan.reopen' => 'Reopen production plan approval.',
            'workflow.production_plan.hold' => 'Place production plan on hold.',
            'workflow.production_plan.resume' => 'Resume held production plan.',
            'workflow.production_plan.finalize' => 'Finalize production plan governance flow.',
            'workflow.production_plan.cancel' => 'Cancel production plan workflow.',
            'workflow.production_plan.override_lock' => 'Override lock on production plan.',

            'workflow.assembly_plan.submit' => 'Submit assembly plan for approval.',
            'workflow.assembly_plan.approve' => 'Approve assembly plan workflow.',
            'workflow.assembly_plan.reject' => 'Reject assembly plan submission.',
            'workflow.assembly_plan.reopen' => 'Reopen assembly plan workflow.',
            'workflow.assembly_plan.hold' => 'Place assembly plan on hold.',
            'workflow.assembly_plan.resume' => 'Resume held assembly plan.',
            'workflow.assembly_plan.finalize' => 'Finalize assembly plan workflow.',
            'workflow.assembly_plan.cancel' => 'Cancel assembly plan workflow.',

            'workflow.qc_entry.submit' => 'Submit QC entry for approval.',
            'workflow.qc_entry.approve' => 'Approve/reject QC entry.',
            'workflow.qc_entry.reject' => 'Reject QC entry submission.',
            'workflow.qc_entry.reopen' => 'Reopen QC entry approval.',
            'workflow.qc_entry.finalize' => 'Finalize QC entry governance flow.',
            'workflow.qc_entry.cancel' => 'Cancel QC entry workflow.',
            'workflow.qc_entry.override_lock' => 'Override lock on QC entry.',

            'workflow.dispatch_entry.submit' => 'Submit dispatch entry for approval.',
            'workflow.dispatch_entry.approve' => 'Approve/reject dispatch entry.',
            'workflow.dispatch_entry.reject' => 'Reject dispatch entry submission.',
            'workflow.dispatch_entry.reopen' => 'Reopen dispatch entry approval.',
            'workflow.dispatch_entry.hold' => 'Place dispatch entry on hold.',
            'workflow.dispatch_entry.resume' => 'Resume held dispatch entry.',
            'workflow.dispatch_entry.finalize' => 'Finalize dispatch entry governance flow.',
            'workflow.dispatch_entry.cancel' => 'Cancel dispatch entry workflow.',
            'workflow.dispatch_entry.handoff' => 'Execute dispatch handoff transition.',
            'workflow.dispatch_entry.override_lock' => 'Override lock on dispatch entry.',
        ];

        try {
            $rows = DB::fetchAll(
                "SELECT p.permission_key, COALESCE(NULLIF(TRIM(p.description), ''), 'App permission') AS description
                 FROM core_app_permissions p
                 INNER JOIN core_apps a ON a.app_key = p.app_key
                 WHERE a.status = 'enabled'
                 ORDER BY p.permission_key ASC"
            );

            foreach ($rows as $row) {
                $perm = trim((string)($row['permission_key'] ?? ''));
                if ($perm === '') {
                    continue;
                }
                $registry[$perm] = (string)($row['description'] ?? 'App permission');
            }
        } catch (\Throwable $e) {
            // App permission tables may not exist yet.
        }

        return $registry;
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function defaultRoleMatrix(): array
    {
        $allPermissions = array_keys(self::permissionRegistry());

        return [
            'platform_admin' => $allPermissions,
            'app_admin' => [
                'auth.logged_in',
                'ops.my_work.view',
                'ops.audit_explorer.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
                'daily_orders.index.view',
            ],
            'app_user' => [
                'auth.logged_in',
                'ops.my_work.view',
                'ops.notifications.view',
                'ops.notifications.manage',
            ],
            'admin' => $allPermissions,
            'it_admin' => $allPermissions,
            'sys_admin' => [
                'admin.tools.access',
                'admin_tools.manage',
                'admin.base.builder',
                'base.manage',
                'routes.manage',
                'plugins.update',
                'acl.manage',
                'developer.tools.access',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'system_developer' => [
                'admin.base.builder',
                'developer.tools.access',
                'ops.notifications.view',
            ],
            'developer' => [
                'admin.base.builder',
                'developer.tools.access',
                'ops.notifications.view',
            ],
            'manager' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.cockpit.view',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'supervisor' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.cockpit.view',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'gm' => [
                'ops.my_work.view',
                'ops.cockpit.view',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
                'workflow.production_plan.submit',
                'workflow.production_plan.approve',
                'workflow.production_plan.reject',
                'workflow.production_plan.reopen',
                'workflow.production_plan.hold',
                'workflow.production_plan.resume',
                'workflow.production_plan.finalize',
                'workflow.production_plan.cancel',
                'workflow.production_plan.override_lock',
                'workflow.assembly_plan.submit',
                'workflow.assembly_plan.approve',
                'workflow.assembly_plan.reject',
                'workflow.assembly_plan.reopen',
                'workflow.assembly_plan.hold',
                'workflow.assembly_plan.resume',
                'workflow.assembly_plan.finalize',
                'workflow.assembly_plan.cancel',
                'workflow.qc_entry.submit',
                'workflow.qc_entry.approve',
                'workflow.qc_entry.reject',
                'workflow.qc_entry.reopen',
                'workflow.qc_entry.finalize',
                'workflow.qc_entry.cancel',
                'workflow.qc_entry.override_lock',
                'workflow.dispatch_entry.submit',
                'workflow.dispatch_entry.approve',
                'workflow.dispatch_entry.reject',
                'workflow.dispatch_entry.reopen',
                'workflow.dispatch_entry.hold',
                'workflow.dispatch_entry.resume',
                'workflow.dispatch_entry.finalize',
                'workflow.dispatch_entry.cancel',
                'workflow.dispatch_entry.handoff',
                'workflow.dispatch_entry.override_lock',
            ],
            'planning_leader' => [
                'ops.my_work.view',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'workflow.production_plan.submit',
                'workflow.production_plan.approve',
                'workflow.production_plan.reject',
                'workflow.production_plan.reopen',
                'workflow.assembly_plan.submit',
                'workflow.assembly_plan.approve',
                'workflow.assembly_plan.reject',
                'workflow.assembly_plan.reopen',
            ],
            'machine_leader' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
                'machines.leader.view',
                'workflow.production_plan.submit',
            ],
            'qc_leader' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
                'qc_entries.leader.view',
                'workflow.qc_entry.submit',
                'workflow.qc_entry.approve',
                'workflow.qc_entry.reject',
                'workflow.qc_entry.reopen',
            ],
            'dispatch_leader' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
                'dispatch_entries.leader.view',
                'dispatch_entries.quick_status',
                'dispatch_entries.transition',
                'workflow.dispatch_entry.submit',
                'workflow.dispatch_entry.approve',
                'workflow.dispatch_entry.reject',
                'workflow.dispatch_entry.reopen',
                'workflow.dispatch_entry.hold',
                'workflow.dispatch_entry.resume',
                'workflow.dispatch_entry.finalize',
            ],
        ];
    }

    public static function roleSlug(?array $user = null): string
    {
        $raw = trim((string)($user['role'] ?? ''));
        return self::normalizeRole($raw);
    }

    public static function normalizeRole(string $role): string
    {
        $raw = strtolower(trim($role));
        if ($raw === '') {
            return 'anonymous';
        }

        $flat = preg_replace('/[^a-z0-9]+/', '', $raw) ?? '';
        return match ($flat) {
            'admin' => 'admin',
            'platformadmin' => 'platform_admin',
            'sysadmin', 'systemadmin', 'systemadministrator' => 'sys_admin',
            'systemdeveloper' => 'system_developer',
            'developer' => 'developer',
            'manager' => 'manager',
            'supervisor' => 'supervisor',
            'gm', 'generalmanager', 'generalmgr' => 'gm',
            'planningleader', 'planleader' => 'planning_leader',
            'machineleader', 'machinelead' => 'machine_leader',
            'qcleader' => 'qc_leader',
            'dispatchleader' => 'dispatch_leader',
            default => str_replace(' ', '_', trim($raw)),
        };
    }

    public static function can(string $permissionKey, ?array $user = null): bool
    {
        $permission = trim($permissionKey);
        if ($permission === '') {
            return false;
        }

        if ($permission === 'auth.logged_in') {
            return self::roleSlug($user) !== 'anonymous';
        }

        if (class_exists(UserDashboardAssignmentService::class) && is_array($user)) {
            $ctx = UserDashboardAssignmentService::resolveUserContext($user);
            // Do NOT short-circuit for platform_admin here — fall through so that
            // custom_deny overrides applied via the ACL matrix take runtime effect.
            $granted = self::effectivePermissionsForContext($ctx, $user);
            return isset($granted[$permission]);
        }

        $role = self::roleSlug($user);
        // Do NOT short-circuit for platform_admin/admin/it_admin here — effectiveRolePermissions()
        // already grants all permissions from defaultRoleMatrix() AND applies custom_deny
        // overrides, so deny changes made in the ACL matrix are correctly honoured.
        $effective = self::effectiveRolePermissions();
        return isset($effective[$role][$permission]);
    }

    /**
     * @param array<int,string> $permissionKeys
     */
    public static function canAny(array $permissionKeys, ?array $user = null): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if (self::can((string)$permissionKey, $user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,string> $permissionKeys
     */
    public static function canAll(array $permissionKeys, ?array $user = null): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if (!self::can((string)$permissionKey, $user)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $visibilityContext
     */
    public static function allowsVisibilityRule(string $rule, array $visibilityContext): bool
    {
        $normalizedRule = trim($rule);
        $roleRaw = (string)($visibilityContext['role'] ?? '');
        $user = [
            'role' => $roleRaw,
            'authority_role' => (string)($visibilityContext['authority_role'] ?? ''),
        ];

        return match ($normalizedRule) {
            'always', '' => true,
            'never' => false,
            'logged_in' => (bool)($visibilityContext['logged_in'] ?? false) || self::can('auth.logged_in', $user),
            'admin_strict' => self::can('admin.strict', $user),
            'admin_tools' => (bool)($visibilityContext['has_admin_tools_access'] ?? false) || self::can('admin.tools.access', $user),
            'can_access_base' => (bool)($visibilityContext['can_access_base'] ?? false) || self::canAny(['admin.base.builder', 'admin.tools.access'], $user),
            'developer_tools' => (bool)($visibilityContext['developer_tools'] ?? false) || self::can('developer.tools.access', $user),
            'role_platform_admin_or_sysadmin' => ((string)($visibilityContext['authority_role'] ?? '') === 'platform_admin') || in_array(self::roleSlug($user), ['platform_admin', 'sys_admin'], true),
            'role_supervisor' => ((string)($visibilityContext['authority_role'] ?? '') !== 'app_user')
                && ((bool)($visibilityContext['has_supervisor_access'] ?? false) || self::can('ops.cockpit.view', $user)),
            'role_governance' => ((string)($visibilityContext['authority_role'] ?? '') !== 'app_user')
                && self::can('ops.approval_inbox.view', $user),
            'organization_access' => self::canAny(['organization.view', 'organization.manage'], $user),
            'ops_audit_explorer' => ((string)($visibilityContext['authority_role'] ?? '') === 'platform_admin')
                && self::can('ops.audit_explorer.view', $user),
            'role_machine_leader' => self::can('machines.leader.view', $user),
            'role_qc_leader' => self::can('qc_entries.leader.view', $user),
            'role_dispatch_leader' => self::can('dispatch_entries.leader.view', $user),
            'role_ops_or_dispatch_leader' => self::canAny(['ops.my_work.view', 'dispatch_entries.leader.view'], $user),
            'role_ops', 'admin_or_ops' => self::can('ops.my_work.view', $user),
            'admin_or_dispatch' => self::can('dispatch_entries.leader.view', $user),
            'admin_or_qc' => self::can('qc_entries.leader.view', $user),
            'admin_or_base' => self::canAny(['admin.strict', 'admin.base.builder'], $user),
            default => false,
        };
    }

    /**
     * @return array<string,mixed>
     */
    public static function report(?array $user = null): array
    {
        $effective = self::effectiveRolePermissions();
        $role = self::roleSlug($user ?? Auth::user() ?? []);
        $granted = array_keys($effective[$role] ?? []);
        sort($granted);

        return [
            'role' => $role,
            'granted_permissions' => $granted,
            'registry' => self::permissionRegistry(),
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function roleKeys(): array
    {
        // Cache: this method is called on every ACL matrix page render and can
        // trigger a full-table DISTINCT scan on large users tables. Cache the
        // result for the duration of the request; invalidateCache() resets it.
        if (self::$roleKeysCache !== null) {
            return self::$roleKeysCache;
        }

        self::syncRegistry();

        $roles = array_fill_keys(array_keys(self::defaultRoleMatrix()), true);

        foreach (self::safeFetchRolesFromTable('role_permissions') as $role) {
            $roles[$role] = true;
        }
        foreach (self::safeFetchRolesFromTable('acl_role_permission_overrides') as $role) {
            $roles[$role] = true;
        }

        try {
            $rows = DB::fetchAll('SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role <> ""');
            foreach ($rows as $row) {
                $role = self::normalizeRole((string)($row['role'] ?? ''));
                if ($role !== '' && $role !== 'anonymous') {
                    $roles[$role] = true;
                }
            }
        } catch (\Throwable $e) {
            // users table role discovery is optional.
        }

        $keys = array_keys($roles);
        sort($keys);
        self::$roleKeysCache = $keys;
        return $keys;
    }

    public static function isSensitivePermission(string $permissionKey): bool
    {
        $permission = trim($permissionKey);
        if ($permission === '') {
            return false;
        }

        $explicit = [
            'acl.manage',
            'admin.tools.access',
            'admin_tools.manage',
            'plugins.update',
            'routes.manage',
            'base.manage',
            'admin.base.builder',
            'admin.strict',
            'workflow.dispatch_entry.override_lock',
            'workflow.qc_entry.override_lock',
            'workflow.production_plan.override_lock',
        ];

        if (in_array($permission, $explicit, true)) {
            return true;
        }

        return str_starts_with($permission, 'acl.')
            || str_starts_with($permission, 'admin.')
            || str_starts_with($permission, 'admin_tools.')
            || str_starts_with($permission, 'plugins.')
            || str_starts_with($permission, 'routes.')
            || str_starts_with($permission, 'base.')
            || str_contains($permission, '.override_lock');
    }

    public static function invalidateCache(): void
    {
        self::$effectiveRolePermissions = null;
        self::$roleKeysCache = null;
    }

    /**
     * @return array<string,array<string,bool>>
     */
    private static function effectiveRolePermissions(): array
    {
        if (is_array(self::$effectiveRolePermissions)) {
            return self::$effectiveRolePermissions;
        }

        self::syncRegistry();

        $map = [];
        foreach (self::defaultRoleMatrix() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $map[$role][$permission] = true;
            }
        }

        try {
            $rows = DB::fetchAll('SELECT role, perm_key FROM role_permissions');
            foreach ($rows as $row) {
                $role = self::normalizeRole((string)($row['role'] ?? ''));
                $permission = trim((string)($row['perm_key'] ?? ''));
                if ($role === '' || $role === 'anonymous' || $permission === '') {
                    continue;
                }
                $map[$role][$permission] = true;
            }
        } catch (\Throwable $e) {
            // Database role overrides are optional; keep defaults when unavailable.
        }

        try {
            $rows = DB::fetchAll('SELECT role, perm_key, state FROM acl_role_permission_overrides');
            foreach ($rows as $row) {
                $role = self::normalizeRole((string)($row['role'] ?? ''));
                $permission = trim((string)($row['perm_key'] ?? ''));
                $state = strtolower(trim((string)($row['state'] ?? '')));
                if ($role === '' || $role === 'anonymous' || $permission === '' || $state === '') {
                    continue;
                }

                if ($state === 'deny') {
                    unset($map[$role][$permission]);
                    continue;
                }

                if ($state === 'allow') {
                    $map[$role][$permission] = true;
                }
            }
        } catch (\Throwable $e) {
            // Override table is optional until migrations run.
        }

        self::$effectiveRolePermissions = $map;
        return $map;
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array<string,bool>
     */
    private static function effectivePermissionsForContext(array $ctx, ?array $user = null): array
    {
        $effective = self::effectiveRolePermissions();
        $granted = [];

        $authorityRole = trim((string)($ctx['authority_role'] ?? ''));
        // tv_display users are read-only kiosk accounts — they hold no interactive permissions
        // regardless of their assigned_apps, access_profiles, or permissions column values.
        // Those fields are data-source hints for the display panels, not access grants.
        if ($authorityRole === 'tv_display') {
            return [];
        }

        if ($authorityRole !== '') {
            foreach (array_keys($effective[$authorityRole] ?? []) as $permission) {
                $granted[$permission] = true;
            }
        }

        $legacyRole = self::roleSlug($user ?? ['role' => (string)($ctx['legacy_role'] ?? '')]);
        foreach (array_keys($effective[$legacyRole] ?? []) as $permission) {
            $granted[$permission] = true;
        }

        foreach (self::accessProfileMatrix() as $profile => $permissions) {
            if (!in_array($profile, (array)($ctx['access_profiles'] ?? []), true)) {
                continue;
            }
            foreach ($permissions as $permission) {
                $granted[$permission] = true;
            }
        }

        foreach ((array)($ctx['permissions'] ?? []) as $permission) {
            $key = trim((string)$permission);
            if ($key !== '') {
                $granted[$key] = true;
            }
        }


        // Simplified app-grant model: if user has an app assigned, grant all normal
        // permissions for that app without requiring specific role packs.
        // tv_display authority_role: assigned_apps is a data-source hint for the kiosk
        // display panels, not an interactive access grant — skip APP_PERMISSION_GRANTS.
        if ($authorityRole !== 'tv_display') {
            $assignedApps = array_values((array)($ctx['assigned_apps'] ?? []));
            foreach (self::APP_PERMISSION_GRANTS as $app => $appPerms) {
                if (in_array($app, $assignedApps, true)) {
                    foreach ($appPerms as $perm) {
                        $granted[$perm] = true;
                    }
                }
            }
        }

        return $granted;
    }

    /**
     * @return array<string,array<int,string>>
     */
    private static function accessProfileMatrix(): array
    {
        if (class_exists(UserDashboardAssignmentService::class)) {
            try {
                $matrix = UserDashboardAssignmentService::accessProfilePermissionMatrix();
                if (!empty($matrix)) {
                    return $matrix;
                }
            } catch (\Throwable $e) {
                // Fall back to legacy static matrix.
            }
        }

        return [
            'platform_administration' => array_keys(self::permissionRegistry()),
            'app_administration' => [
                'ops.my_work.view',
                'ops.notifications.view',
                'ops.notifications.manage',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'manufacturing_planning' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
                'machines.leader.view',
                'workflow.production_plan.submit',
                'workflow.assembly_plan.submit',
            ],
            'assembly_operations' => [
                'ops.my_work.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
                'workflow.assembly_plan.submit',
                'workflow.assembly_plan.approve',
                'workflow.assembly_plan.reject',
                'workflow.assembly_plan.reopen',
            ],
            'qc_operations' => [
                'ops.my_work.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
                'qc_entries.leader.view',
                'workflow.qc_entry.submit',
                'workflow.qc_entry.approve',
                'workflow.qc_entry.reject',
                'workflow.qc_entry.reopen',
            ],
            'dispatch_operations' => [
                'ops.my_work.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
                'dispatch_entries.leader.view',
                'dispatch_entries.quick_status',
                'dispatch_entries.transition',
                'workflow.dispatch_entry.submit',
                'workflow.dispatch_entry.approve',
                'workflow.dispatch_entry.reject',
                'workflow.dispatch_entry.reopen',
                'workflow.dispatch_entry.hold',
                'workflow.dispatch_entry.resume',
                'workflow.dispatch_entry.finalize',
                'workflow.dispatch_entry.cancel',
                'workflow.dispatch_entry.handoff',
            ],
            'operational_supervision' => [
                'ops.my_work.view',
                'ops.handoff.view',
                'ops.handoff.assign_owner',
                'ops.handoff.escalate',
                'ops.cockpit.view',
                'ops.approval_inbox.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'operator_workboard' => [
                'ops.my_work.view',
                'ops.notifications.view',
            ],
            'readonly_observer' => [
                'ops.my_work.view',
                'ops.notifications.view',
                'products.360.view',
                'daily_orders.360.view',
            ],
            'accounting_approval' => [
                'ops.my_work.view',
                'ops.notifications.view',
            ],
        ];
    }

    private static function syncRegistry(): void
    {
        if (self::$registrySynced) {
            return;
        }
        self::$registrySynced = true;

        try {
            DB::query("CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                perm_key VARCHAR(150) NOT NULL UNIQUE,
                description VARCHAR(255) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            DB::query("CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(50) NOT NULL,
                perm_key VARCHAR(150) NOT NULL,
                UNIQUE KEY uniq_role_perm (role, perm_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            DB::query("CREATE TABLE IF NOT EXISTS acl_role_permission_overrides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(50) NOT NULL,
                perm_key VARCHAR(150) NOT NULL,
                state ENUM('allow','deny') NOT NULL,
                reason_text VARCHAR(255) NULL,
                updated_by INT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_acl_override (role, perm_key),
                KEY idx_acl_override_role (role),
                KEY idx_acl_override_perm (perm_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            DB::query("CREATE TABLE IF NOT EXISTS acl_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NULL,
                actor_email VARCHAR(190) NULL,
                actor_role VARCHAR(80) NULL,
                target_role VARCHAR(80) NOT NULL,
                perm_key VARCHAR(150) NOT NULL,
                previous_state VARCHAR(40) NOT NULL,
                new_state VARCHAR(40) NOT NULL,
                reason_text VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_acl_audit_created (created_at),
                KEY idx_acl_audit_role (target_role),
                KEY idx_acl_audit_perm (perm_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Lazy upsert: skip the ~80-row loop when the persisted count already
            // matches the registry. This avoids redundant writes on every warm worker
            // boot and reduces per-request DDL overhead in production.
            $registry = self::permissionRegistry();
            $expected = count($registry);
            $existing = 0;
            try {
                $countRow = DB::fetchOne('SELECT COUNT(*) AS n FROM permissions');
                $existing = (int)($countRow['n'] ?? 0);
            } catch (\Throwable $e) {
                // Table may not yet exist — fall through to upsert.
            }
            if ($existing !== $expected) {
                foreach ($registry as $permKey => $description) {
                    DB::query(
                        'INSERT INTO permissions (perm_key, description) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE description=VALUES(description)',
                        [$permKey, $description]
                    );
                }
            }
        } catch (\Throwable $e) {
            // ACL table sync should not block request flow.
        }
    }

    /**
     * @return array<int,string>
     */
    private static function safeFetchRolesFromTable(string $tableName): array
    {
        try {
            $rows = DB::fetchAll('SELECT DISTINCT role FROM ' . $tableName . ' WHERE role IS NOT NULL AND role <> ""');
        } catch (\Throwable $e) {
            return [];
        }

        $roles = [];
        foreach ($rows as $row) {
            $role = self::normalizeRole((string)($row['role'] ?? ''));
            if ($role !== '' && $role !== 'anonymous') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }
}
