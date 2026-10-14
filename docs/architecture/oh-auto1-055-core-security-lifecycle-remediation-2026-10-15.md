# OH-AUTO1-055 — Core Security and Lifecycle Remediation

Date: 2026-10-15
Status: **Complete; authorized Core corrections verified**

## SEC-AUTH-001 — ACL deny precedence

### Root cause

`AclPolicy::effectiveRolePermissions()` removed denied role permissions from
the effective role map but discarded the fact that they were denied.
`effectivePermissionsForContext()` subsequently merged access-profile,
explicit-user, and assigned-app grants without a retained deny set.

### Correction

`AclPolicy` now retains role denials beside the effective role-permission cache,
applies them after all additive sources, and clears the denial cache through
`invalidateCache()`. Explicit `allow` rows preserve the existing row-order
override behavior by removing a prior denial for that role/permission.

### Evidence

- Historical in-memory reproduction: 10/10 before correction.
- Post-fix regression: 12/12.
- Covered profile, explicit permission, assigned-app, combined grants,
  unrelated permission, default deny, and `tv_display` behavior.
- No database connection or SQL execution.

## LIFECYCLE-003 — Atomic plugin route registration

### Root cause

`Router::get()` and `post()` mutate the live route map immediately, while
`PluginManager::loadActivePlugins()` required plugin bootstrap/routes without a
local failure boundary. A thrown route file therefore left previously added
routes registered.

### Correction

`Router` now exposes a synchronous registration snapshot/commit/rollback
primitive. `PluginManager` wraps each plugin bootstrap/routes operation in that
scope, restores the pre-operation route map on `Throwable`, logs a sanitized
failure diagnostic, and rethrows the original exception. Successful routes and
unrelated/pre-existing routes remain unchanged.

### Evidence

- Historical in-memory reproduction: 16/16 before correction.
- Post-fix regression: 15/15.
- Covered successful GET/POST registration, failure before/after multiple
  routes, preservation of existing and unrelated routes, retry cleanliness,
  and exception propagation.
- No plugin activation, lifecycle mutation, database connection, or SQL.

## Verification

- PHP lint: PASS for all changed Core/probe files.
- Architecture gates with `ARCHITECTURE_GATE_ALLOW_CORE=1`: **49/49 PASS**.
- Deletion-family gates: **28/28 PASS**.
- Business ownership gate: PASS.
- `git diff --check`: PASS.

The Core exception was limited to `AclPolicy.php`, `Router.php`, and
`PluginManager.php`; no unrelated Core paths changed. Deployment, migrations,
database-backed tests, activation, and release apply remain prohibited.
