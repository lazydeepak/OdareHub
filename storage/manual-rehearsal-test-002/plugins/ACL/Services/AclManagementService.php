<?php
declare(strict_types=1);

namespace Plugins\ACL\Services;

use App\Core\AclPolicy;
use App\Core\Auth;
use App\Core\DB;

final class AclManagementService
{
    // ----------------------------------------------------------------
    // Request-level bulk permission data cache
    // Eliminates N+1 queries on matrix/roleDetail pages.
    // ----------------------------------------------------------------

    /** @var array<string,array<string,bool>>|null  role → permKey → true */
    private static ?array $customAllowCache = null;

    /** @var array<string,array<string,string>>|null  role → permKey → 'deny'|'allow' */
    private static ?array $overrideCacheMap = null;

    /**
     * Bulk-load role_permissions and acl_role_permission_overrides once per request.
     * Subsequent calls within the same request are free (array lookups).
     */
    private static function prewarmPermissionCache(): void
    {
        if (self::$customAllowCache !== null) {
            return;
        }
        self::$customAllowCache = [];
        self::$overrideCacheMap = [];

        foreach (self::safeFetchAll('SELECT role, perm_key FROM role_permissions') as $row) {
            $r = AclPolicy::normalizeRole((string)($row['role'] ?? ''));
            $p = trim((string)($row['perm_key'] ?? ''));
            if ($r !== '' && $p !== '') {
                self::$customAllowCache[$r][$p] = true;
            }
        }
        foreach (self::safeFetchAll('SELECT role, perm_key, state FROM acl_role_permission_overrides') as $row) {
            $r = AclPolicy::normalizeRole((string)($row['role'] ?? ''));
            $p = trim((string)($row['perm_key'] ?? ''));
            $s = strtolower(trim((string)($row['state'] ?? '')));
            if ($r !== '' && $p !== '' && $s !== '') {
                self::$overrideCacheMap[$r][$p] = $s;
            }
        }
    }

    /**
     * Invalidate the request-level bulk cache after a DB mutation.
     */
    private static function invalidatePermissionCache(): void
    {
        self::$customAllowCache = null;
        self::$overrideCacheMap = null;
    }

    /**
     * @return array<int,string>
     */
    public static function roleKeys(): array
    {
        return AclPolicy::roleKeys();
    }

    /**
     * @return array<string,string>
     */
    public static function permissionRegistry(): array
    {
        return AclPolicy::permissionRegistry();
    }

    /**
     * @return array<string,mixed>
     */
    public static function buildOverview(): array
    {
        self::ensureSchema();

        $roles = self::roleKeys();
        $permissions = self::permissionRegistry();

        $customAllowRows = self::safeFetchAll('SELECT role, perm_key FROM role_permissions');
        $denyRows = self::safeFetchAll("SELECT role, perm_key FROM acl_role_permission_overrides WHERE state='deny'");
        $auditRows = self::safeFetchAll('SELECT * FROM acl_audit_log ORDER BY id DESC LIMIT 20');

        $customAllowCount = 0;
        $defaults = AclPolicy::defaultRoleMatrix();
        foreach ($customAllowRows as $row) {
            $role = AclPolicy::normalizeRole((string)($row['role'] ?? ''));
            $perm = trim((string)($row['perm_key'] ?? ''));
            if ($role === '' || $perm === '') {
                continue;
            }
            $isDefault = in_array($perm, (array)($defaults[$role] ?? []), true);
            if (!$isDefault) {
                $customAllowCount++;
            }
        }

        $rolesWithOverrides = [];
        foreach ($customAllowRows as $row) {
            $role = AclPolicy::normalizeRole((string)($row['role'] ?? ''));
            $perm = trim((string)($row['perm_key'] ?? ''));
            $isDefault = in_array($perm, (array)($defaults[$role] ?? []), true);
            if ($role !== '' && !$isDefault) {
                $rolesWithOverrides[$role] = true;
            }
        }
        foreach ($denyRows as $row) {
            $role = AclPolicy::normalizeRole((string)($row['role'] ?? ''));
            if ($role !== '') {
                $rolesWithOverrides[$role] = true;
            }
        }

        $sensitive = [];
        foreach (array_keys($permissions) as $permKey) {
            if (AclPolicy::isSensitivePermission($permKey)) {
                $sensitive[] = $permKey;
            }
        }

        $recentChanges = [];
        foreach ($auditRows as $row) {
            $recentChanges[] = [
                'actor_email' => (string)($row['actor_email'] ?? ''),
                'actor_role' => (string)($row['actor_role'] ?? ''),
                'target_role' => (string)($row['target_role'] ?? ''),
                'perm_key' => (string)($row['perm_key'] ?? ''),
                'previous_state' => (string)($row['previous_state'] ?? ''),
                'new_state' => (string)($row['new_state'] ?? ''),
                'reason_text' => (string)($row['reason_text'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        return [
            'total_roles' => count($roles),
            'total_permissions' => count($permissions),
            'roles_with_overrides' => count($rolesWithOverrides),
            'custom_allow_count' => $customAllowCount,
            'custom_deny_count' => count($denyRows),
            'sensitive_permissions' => $sensitive,
            'recent_changes' => $recentChanges,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function permissionCatalog(): array
    {
        $registry = self::permissionRegistry();
        $rows = [];

        foreach ($registry as $permKey => $description) {
            $rows[] = [
                'perm_key' => (string)$permKey,
                'description' => (string)$description,
                'group' => self::permissionGroup((string)$permKey),
                'sensitive' => AclPolicy::isSensitivePermission((string)$permKey),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $groupCmp = strcmp((string)($left['group'] ?? ''), (string)($right['group'] ?? ''));
            if ($groupCmp !== 0) {
                return $groupCmp;
            }
            return strcmp((string)($left['perm_key'] ?? ''), (string)($right['perm_key'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public static function matrixData(string $groupFilter = '', string $search = ''): array
    {
        $roles = self::roleKeys();
        $permissions = self::permissionRegistry();

        $groupFilter = trim($groupFilter);
        $search = strtolower(trim($search));

        $matrixRows = [];
        foreach ($permissions as $permKey => $description) {
            $group = self::permissionGroup((string)$permKey);
            if ($groupFilter !== '' && $group !== $groupFilter) {
                continue;
            }

            $haystack = strtolower((string)$permKey . ' ' . (string)$description . ' ' . strtolower($group));
            if ($search !== '' && !str_contains($haystack, $search)) {
                continue;
            }

            $cells = [];
            foreach ($roles as $role) {
                $cells[$role] = self::permissionStateForRole($role, (string)$permKey);
            }

            $matrixRows[] = [
                'perm_key' => (string)$permKey,
                'description' => (string)$description,
                'group' => $group,
                'sensitive' => AclPolicy::isSensitivePermission((string)$permKey),
                'cells' => $cells,
            ];
        }

        return [
            'roles' => $roles,
            'rows' => $matrixRows,
            'groups' => self::permissionGroups(),
            'group_filter' => $groupFilter,
            'search' => $search,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function roleDetail(string $role): array
    {
        $roleKey = AclPolicy::normalizeRole($role);
        if ($roleKey === '' || $roleKey === 'anonymous') {
            $roleKey = 'admin';
        }

        $permissions = self::permissionRegistry();
        $rows = [];

        foreach ($permissions as $permKey => $description) {
            $state = self::permissionStateForRole($roleKey, (string)$permKey);
            $rows[] = [
                'perm_key' => (string)$permKey,
                'description' => (string)$description,
                'group' => self::permissionGroup((string)$permKey),
                'sensitive' => AclPolicy::isSensitivePermission((string)$permKey),
                'state' => $state,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $groupCmp = strcmp((string)($left['group'] ?? ''), (string)($right['group'] ?? ''));
            if ($groupCmp !== 0) {
                return $groupCmp;
            }
            return strcmp((string)($left['perm_key'] ?? ''), (string)($right['perm_key'] ?? ''));
        });

        $summary = [
            'effective_allowed' => 0,
            'default_allowed' => 0,
            'custom_allowed' => 0,
            'custom_denied' => 0,
            'sensitive_allowed' => 0,
        ];
        $allowedByGroup = [];
        $highRiskAllowed = [];

        foreach ($rows as $row) {
            $state = (array)($row['state'] ?? []);
            if (!empty($state['effective'])) {
                $summary['effective_allowed']++;
                $group = (string)($row['group'] ?? 'extensions');
                $allowedByGroup[$group][] = (string)($row['perm_key'] ?? '');
                if (!empty($row['sensitive'])) {
                    $summary['sensitive_allowed']++;
                    $highRiskAllowed[] = (string)($row['perm_key'] ?? '');
                }
            }
            if (!empty($state['default_allow'])) {
                $summary['default_allowed']++;
            }
            if (($state['source'] ?? '') === 'custom_allow') {
                $summary['custom_allowed']++;
            }
            if (($state['source'] ?? '') === 'custom_deny') {
                $summary['custom_denied']++;
            }
        }

        return [
            'role' => $roleKey,
            'rows' => $rows,
            'summary' => $summary,
            'allowed_by_group' => $allowedByGroup,
            'high_risk_allowed' => $highRiskAllowed,
        ];
    }

    /**
     * @param array<string,mixed>|null $actor
     * @return array{ok:bool,message:string}
     */
    public static function applyPermissionChange(string $action, string $role, string $permissionKey, ?array $actor = null, string $reason = ''): array
    {
        self::ensureSchema();

        $action = strtolower(trim($action));
        $roleKey = AclPolicy::normalizeRole($role);
        $permKey = trim($permissionKey);

        if (!in_array($action, ['grant', 'revoke', 'reset'], true)) {
            return ['ok' => false, 'message' => 'Unsupported ACL action.'];
        }
        if ($roleKey === '' || $roleKey === 'anonymous') {
            return ['ok' => false, 'message' => 'Invalid role.'];
        }
        if ($permKey === '' || !array_key_exists($permKey, self::permissionRegistry())) {
            return ['ok' => false, 'message' => 'Unknown permission key.'];
        }

        $before      = self::permissionStateForRole($roleKey, $permKey);
        $beforeState = (string)($before['state'] ?? 'denied');
        $afterState  = $beforeState;

        // Wrap the entire mutation in a transaction so that if the safety guard
        // fails, the DB is rolled back atomically — no manual restorePriorState needed.
        $db = DB::conn();
        $db->begin_transaction();

        try {
            if ($action === 'grant') {
                self::clearOverrideRow($roleKey, $permKey);
                if (empty($before['default_allow'])) {
                    DB::query('INSERT IGNORE INTO role_permissions (role, perm_key) VALUES (?, ?)', [$roleKey, $permKey]);
                }
            } elseif ($action === 'revoke') {
                DB::query('DELETE FROM role_permissions WHERE role=? AND perm_key=? LIMIT 1', [$roleKey, $permKey]);
                if (!empty($before['default_allow'])) {
                    DB::query(
                        "INSERT INTO acl_role_permission_overrides (role, perm_key, state, reason_text, updated_by)
                         VALUES (?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE state=VALUES(state), reason_text=VALUES(reason_text), updated_by=VALUES(updated_by), updated_at=NOW()",
                        [$roleKey, $permKey, 'deny', $reason !== '' ? $reason : null, (int)($actor['id'] ?? 0)]
                    );
                } else {
                    self::clearOverrideRow($roleKey, $permKey);
                }
            } else {
                DB::query('DELETE FROM role_permissions WHERE role=? AND perm_key=? LIMIT 1', [$roleKey, $permKey]);
                self::clearOverrideRow($roleKey, $permKey);
            }

            // Invalidate caches so the safety guard reads the *new* state from DB
            // (within the same uncommitted transaction, InnoDB shows own writes).
            AclPolicy::invalidateCache();
            self::invalidatePermissionCache();

            $after      = self::permissionStateForRole($roleKey, $permKey);
            $afterState = (string)($after['state'] ?? 'denied');

            $guardMessage = self::validateCriticalSafety($actor, $roleKey, $permKey);
            if ($guardMessage !== null) {
                // Safety guard failed — roll back the entire mutation atomically.
                $db->rollback();
                AclPolicy::invalidateCache();
                self::invalidatePermissionCache();
                return ['ok' => false, 'message' => $guardMessage];
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            AclPolicy::invalidateCache();
            self::invalidatePermissionCache();
            return ['ok' => false, 'message' => 'ACL update failed: ' . $e->getMessage()];
        }

        self::writeAudit($actor, $roleKey, $permKey, $beforeState, $afterState, $reason);
        return ['ok' => true, 'message' => 'ACL permission updated successfully.'];
    }

    /**
     * @return array<string,mixed>
     */
    public static function permissionStateForRole(string $role, string $permissionKey): array
    {
        self::ensureSchema();

        $roleKey = AclPolicy::normalizeRole($role);
        $permKey = trim($permissionKey);

        $defaultAllow = in_array($permKey, (array)(AclPolicy::defaultRoleMatrix()[$roleKey] ?? []), true);

        // Use the bulk-preloaded cache to avoid N+1 queries on matrix/roleDetail pages.
        // Falls back gracefully to per-query if cache is somehow unavailable.
        self::prewarmPermissionCache();
        $customAllow   = isset(self::$customAllowCache[$roleKey][$permKey]);
        $overrideState = self::$overrideCacheMap[$roleKey][$permKey] ?? '';
        $customDeny    = ($overrideState === 'deny');

        $effective = AclPolicy::can($permKey, ['role' => $roleKey]);

        $source = 'default_deny';
        if ($customDeny) {
            $source = 'custom_deny';
        } elseif ($customAllow && !$defaultAllow) {
            $source = 'custom_allow';
        } elseif ($defaultAllow) {
            $source = 'default_allow';
        }

        $state = $effective ? 'allowed' : 'denied';

        return [
            'role' => $roleKey,
            'permission' => $permKey,
            'state' => $state,
            'effective' => $effective,
            'default_allow' => $defaultAllow,
            'custom_allow' => $customAllow,
            'custom_deny' => $customDeny,
            'source' => $source,
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function permissionGroups(): array
    {
        return [
            'platform' => 'Platform / Ops',
            'products' => 'Products / Master Data',
            'planning' => 'Production Plans',
            'qc' => 'QC',
            'dispatch' => 'Dispatch',
            'inventory' => 'Inventory / Ledger',
            'governance' => 'Governance / Approval',
            'admin_tools' => 'Admin Tools',
            'acl_system' => 'ACL / System Control',
            'extensions' => 'Future Extensions',
        ];
    }

    private static function permissionGroup(string $permissionKey): string
    {
        if (str_starts_with($permissionKey, 'ops.')) {
            return 'platform';
        }
        if (str_starts_with($permissionKey, 'products.') || str_starts_with($permissionKey, 'machines.') || str_starts_with($permissionKey, 'part_machine_map.')) {
            return 'products';
        }
        if (str_starts_with($permissionKey, 'workflow.production_plan') || str_starts_with($permissionKey, 'daily_orders.') || str_starts_with($permissionKey, 'pre_orders.')) {
            return 'planning';
        }
        if (str_starts_with($permissionKey, 'workflow.qc_entry') || str_starts_with($permissionKey, 'qc_entries.') || str_starts_with($permissionKey, 'qc_plans.')) {
            return 'qc';
        }
        if (str_starts_with($permissionKey, 'workflow.dispatch_entry') || str_starts_with($permissionKey, 'dispatch_entries.')) {
            return 'dispatch';
        }
        if (str_starts_with($permissionKey, 'ledger.') || str_starts_with($permissionKey, 'inventory.')) {
            return 'inventory';
        }
        if (str_starts_with($permissionKey, 'workflow.')) {
            return 'governance';
        }
        if (str_starts_with($permissionKey, 'admin.') || str_starts_with($permissionKey, 'admin_tools.') || str_starts_with($permissionKey, 'base.') || str_starts_with($permissionKey, 'plugins.') || str_starts_with($permissionKey, 'routes.')) {
            return 'admin_tools';
        }
        if (str_starts_with($permissionKey, 'acl.') || str_starts_with($permissionKey, 'developer.')) {
            return 'acl_system';
        }

        return 'extensions';
    }

    private static function ensureSchema(): void
    {
        // Trigger AclPolicy table sync + cache initialization path.
        AclPolicy::roleKeys();
    }

    private static function clearOverrideRow(string $role, string $perm): void
    {
        DB::query('DELETE FROM acl_role_permission_overrides WHERE role=? AND perm_key=? LIMIT 1', [$role, $perm]);
    }

    /**
     * @param array<string,mixed>|null $actor
     */
    private static function validateCriticalSafety(?array $actor, string $targetRole, string $permissionKey): ?string
    {
        if (!AclPolicy::isSensitivePermission($permissionKey)) {
            return null;
        }

        $criticalControlPermissions = ['acl.manage', 'admin.tools.access', 'admin_tools.manage'];
        if (!in_array($permissionKey, $criticalControlPermissions, true)) {
            return null;
        }

        $guardRoles = ['platform_admin', 'admin', 'it_admin'];
        $holders = 0;
        foreach ($guardRoles as $role) {
            if (AclPolicy::can($permissionKey, ['role' => $role])) {
                $holders++;
            }
        }

        if ($holders <= 0) {
            return 'Blocked: this change would remove all recovery-capable admin roles for a critical permission.';
        }

        $actorRole = AclPolicy::normalizeRole((string)($actor['role'] ?? ''));
        if ($actorRole !== '' && $actorRole === $targetRole && in_array($permissionKey, ['acl.manage', 'admin.tools.access'], true)) {
            if (!AclPolicy::can($permissionKey, ['role' => $actorRole])) {
                return 'Blocked: this change would remove your current role access to critical ACL/Admin control.';
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $priorState
     */
    private static function restorePriorState(string $role, string $permissionKey, array $priorState): void
    {
        DB::query('DELETE FROM role_permissions WHERE role=? AND perm_key=? LIMIT 1', [$role, $permissionKey]);
        DB::query('DELETE FROM acl_role_permission_overrides WHERE role=? AND perm_key=? LIMIT 1', [$role, $permissionKey]);

        if (!empty($priorState['custom_allow']) && empty($priorState['default_allow'])) {
            DB::query('INSERT IGNORE INTO role_permissions (role, perm_key) VALUES (?, ?)', [$role, $permissionKey]);
        }
        if (!empty($priorState['custom_deny'])) {
            DB::query(
                "INSERT INTO acl_role_permission_overrides (role, perm_key, state) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE state=VALUES(state), updated_at=NOW()",
                [$role, $permissionKey, 'deny']
            );
        }
    }

    /**
     * @param array<string,mixed>|null $actor
     */
    private static function writeAudit(?array $actor, string $targetRole, string $permKey, string $previousState, string $newState, string $reason): void
    {
        $actorId    = (int)($actor['id'] ?? 0) ?: null;
        $actorEmail = null;
        $actorRole  = null;

        // Resolve actor identity from the DB rather than trusting session-supplied strings.
        // This prevents a tampered or stale session from poisoning the audit trail.
        if ($actorId !== null && $actorId > 0) {
            try {
                $actorRow = DB::fetchOne('SELECT email, role FROM users WHERE id = ? LIMIT 1', [$actorId]);
                if (is_array($actorRow)) {
                    $actorEmail = trim((string)($actorRow['email'] ?? '')) ?: null;
                    $actorRole  = AclPolicy::normalizeRole((string)($actorRow['role'] ?? '')) ?: null;
                }
            } catch (\Throwable $e) {
                // Audit DB lookup failed — fall back to session strings rather than skipping the log.
                $actorEmail = trim((string)($actor['email'] ?? '')) ?: null;
                $actorRole  = AclPolicy::normalizeRole((string)($actor['role'] ?? '')) ?: null;
            }
        }

        DB::query(
            'INSERT INTO acl_audit_log (actor_user_id, actor_email, actor_role, target_role, perm_key, previous_state, new_state, reason_text) VALUES (?,?,?,?,?,?,?,?)',
            [
                $actorId,
                $actorEmail,
                $actorRole,
                $targetRole,
                $permKey,
                $previousState,
                $newState,
                trim($reason) !== '' ? trim($reason) : null,
            ]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function safeFetchAll(string $sql, array $params = []): array
    {
        try {
            return DB::fetchAll($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function safeFetchOne(string $sql, array $params = []): ?array
    {
        try {
            return DB::fetchOne($sql, $params);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Require ACL management access permission.
     * Checks 'acl.manage' permission and exits if not authorized.
     *
     * @param string|null $intendedUrl Optional URL to remember for redirect
     * @return void Exits if access denied, otherwise continues
     */
    public static function requireManagementAccess(?string $intendedUrl = null): void
    {
        if (function_exists('acl_require')) {
            acl_require('acl.manage', $intendedUrl);
            return;
        }

        // Fallback if acl_require not available
        if (function_exists('base_require_admin_tools_access')) {
            base_require_admin_tools_access();
            return;
        }

        // Final fallback
        Auth::requireAdmin();
    }
}
