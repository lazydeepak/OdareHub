# OH-AUTO1-035 — Core Security and Lifecycle Remediation Decision Package

Date: 2026-10-14
Status: **Implementation-ready proposal; Core modification not authorized**

This document converts the two reproduced Core defects into narrowly bounded
implementation packages. It is not authorization to modify `/app`, activate a
plugin, change lifecycle state, stage, commit, deploy, or release.

## 1. Verification boundary

The relevant read-only reproductions pass against the current implementation:

- `php scripts/security/probe_acl_deny_precedence.php` — **10/10**;
- `php scripts/testing/probe_plugin_route_registration_atomicity.php` — **16/16**;
- `php -l` on `app/Core/AclPolicy.php`, `PluginManager.php`, and `Router.php`;
- Core-lock gate — **PASS**;
- no database, live plugin activation, or Core mutation.

## 2. SEC-AUTH-001 — ACL deny precedence

### Verified defect and severity

`AclPolicy::effectiveRolePermissions()` removes a denied permission from the
role map, but `AclPolicy::effectivePermissionsForContext()` subsequently adds
permissions from all of these sources without consulting a retained deny set:

1. authority-role permissions;
2. legacy-role permissions;
3. `accessProfileMatrix()`;
4. `$ctx['permissions']` explicit user grants;
5. `APP_PERMISSION_GRANTS` for assigned apps.

The reproduction proves that an empty role baseline can regain permissions from
an access profile, explicit permission, or assigned app. Severity is **high**:
the documented custom-deny contract can be bypassed on any authorization path
using `AclPolicy::can()`.

### Exact affected Core methods

- `app/Core/AclPolicy.php::effectiveRolePermissions()` — construct and cache
  role grants plus denied permissions;
- `app/Core/AclPolicy.php::effectivePermissionsForContext()` — merge all grant
  sources and enforce the retained deny set at the final boundary;
- `app/Core/AclPolicy.php::invalidateCache()` — clear any deny cache added by
  the correction;
- `app/Core/AclPolicy.php::can()` — no algorithm change is required, but its
  existing documented deny contract must remain true.

`UserDashboardAssignmentService::resolveUserContext()` and
`accessProfilePermissionMatrix()` remain context/grant providers. They are not
appropriate owners for a global deny rule.

### Minimal proposed correction

1. Add a private process-local deny-set cache paired with
   `$effectiveRolePermissions`, or return an internal role grant/deny pair from
   the private role composition stage.
2. While reading `acl_role_permission_overrides`, record every normalized
   `deny` permission in the role's deny set. Role baseline removal remains.
3. Build the context grant union exactly as today, including profiles, explicit
   user permissions, and assigned-app grants.
4. Remove every permission in the applicable deny set immediately before
   returning `$granted`.
5. Clear both grant and deny caches in `invalidateCache()`.

No app-specific exception, permission redesign, profile change, assigned-app
change, or experience-composition change is proposed.

### Required regression acceptance

The future Core/security acceptance suite must fail on the current code and pass
after the authorized correction:

1. role baseline allows a permission;
2. role override deny removes it;
3. deny plus access-profile grant remains denied;
4. deny plus explicit user grant remains denied;
5. deny plus assigned-app grant remains denied;
6. deny plus all grant sources remains denied;
7. a role containing both allow and deny has a deterministic, security-approved
   result—recommended default is deny-wins;
8. `tv_display` remains non-interactive;
9. `invalidateCache()` exposes changed override decisions;
10. unrelated roles and permissions retain existing results.

The existing reproduction remains separate and must continue to document the
pre-fix failure; it must not be weakened into an acceptance test.

### Compatibility and rollback

The correction intentionally removes access previously reintroduced after an
explicit deny. Existing accounts with conflicting grant/deny sources may lose
effective permissions, which is the intended security consequence and requires
security-owner review plus an account-impact report. Rollback is a whole-file
Core revert only after confirming whether the original bypass would be restored;
no app workaround is acceptable.

## 3. LIFECYCLE-003 — Plugin route registration atomicity

### Verified defect and severity

`PluginManager::loadActivePlugins()` directly requires plugin `bootstrap.php`
and the declared route file. `Router::get()` and `Router::post()` mutate the
live route map immediately. If bootstrap or routes throw after an earlier route
registration, the earlier route remains callable. `Router` has no removal,
snapshot, or registration-scope API.

The loader also truncates the shared `menus` table before plugin loading, adds a
view namespace before bootstrap/routes, and has no local failure-state
transition. Severity is **high** for failed-plugin isolation, but route rollback
alone does not make bootstrap, menu, namespace, event, global-function, or
static-cache side effects atomic.

### Exact affected Core methods

- `app/Core/Router.php::get()` and `::post()` — route mutation boundary;
- `app/Core/Router.php::listRoutes()` — current inspection-only route map;
- `app/Core/PluginManager.php::loadActivePlugins()` — per-plugin bootstrap and
  route loading boundary;
- the authoritative lifecycle service, if a failed/broken status transition is
  approved. No specific lifecycle writer should be selected without owner
  approval.

### Minimal proposed correction

1. Add a generic per-plugin registration scope to `Router`, not a second route
   registry. The scope must track each GET/POST key first touched and its prior
   callable, including replacement of an existing route.
2. Expose narrowly scoped `begin`, `commit`, and `rollback` operations, or one
   equivalent immutable scope object. These operations must be generic and
   independent of plugin names.
3. In `loadActivePlugins()`, begin the scope immediately before requiring the
   plugin bootstrap and route entry; commit only after both return normally.
4. On `Throwable`, roll back only keys touched by that plugin, preserve unrelated
   routes, emit a sanitized diagnostic, and rethrow to preserve the current
   bootstrap failure visibility unless a lifecycle owner approves continue-on-
   failure behavior.
5. Do not silently mark a plugin broken in this patch. A failed/broken lifecycle
   transition requires the authoritative lifecycle owner and a separate contract.

For duplicate declarations within one scope, the acceptance contract must make
the policy explicit. The lowest-compatibility-risk first correction is to retain
last-write-wins while recording and testing it as an explicit policy; rejecting
duplicates is a separate behavior change requiring approval.

### Required regression acceptance

The future Core/lifecycle acceptance suite must fail on the current code and pass
after authorized remediation:

1. successful GET and POST bootstrap/routes commit all routes;
2. failure after route N leaves no route introduced or replaced by that plugin;
3. unrelated pre-existing routes retain equivalent handlers;
4. retry starts from a clean route state and cannot resurrect stale entries;
5. disabled and dependency-blocked plugins register no executable route;
6. duplicate GET/POST declarations follow the explicitly approved policy;
7. bootstrap and route exceptions remain observable with sanitized diagnostics;
8. lifecycle status behavior is tested separately if a status transition is
   authorized;
9. route rollback does not claim rollback of menus, namespaces, hooks, event
   subscriptions, globals, or static caches without separate evidence;
10. no live database or real plugin activation is required for the acceptance
    fixture suite.

The existing 16/16 reproduction remains a pre-fix defect reproduction and must
remain separate from the future acceptance suite.

### Compatibility and rollback

Successful plugin route behavior should remain unchanged. Failed plugins will no
longer leave callable routes, which is an intentional security/lifecycle
correction. Router scope state must be exception-safe and must not leak between
plugins. Rollback is a whole Core correction revert after verifying that stale
routes are not required by any supported failure path.

## 4. Required architectural approvals

Before implementation, obtain explicit approval for:

1. Core ACL deny-wins semantics when role allow and deny rows conflict;
2. Core file changes to `AclPolicy.php`, `Router.php`, and `PluginManager.php`;
3. whether plugin failure is rethrown or converted into a continue-with-error
   lifecycle state;
4. whether duplicate route declarations remain last-write-wins or become
   rejected;
5. whether bootstrap side effects are outside the route atomicity contract or
   require a separate staging/compensation design;
6. the Core-owned acceptance-test location and test-fixture policy.

## 5. Production-release implications

Until both corrections are implemented and accepted—or an explicit Core/security
risk decision is recorded—production readiness remains **blocked**. Application,
Shell, SBAIO, Manufacturing, Procurement, or plugin-level workarounds must not
claim to resolve either defect. No release, activation, deployment, or source
integration decision should treat the existing reproduction probes as proof that
the defects are fixed.

## 6. Future correction file set

Minimum expected Core files:

- `app/Core/AclPolicy.php`;
- `app/Core/Router.php`;
- `app/Core/PluginManager.php`.

Expected future acceptance tests should be added under an explicitly approved
Core test location, likely alongside the existing Core test/probe conventions.
The current reproduction probes remain under `scripts/security/` and
`scripts/testing/` and should not be overwritten.

## 7. Next independently executable assignment

Proceed to OH-AUTO1-036 production-readiness gate reconciliation or obtain an
explicit Core authorization decision. Do not repeat these reproductions unless a
future authorized correction changes the affected methods.
