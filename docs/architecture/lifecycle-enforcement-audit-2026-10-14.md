# OH-AUTO1-016 — Application and Module Lifecycle Enforcement Audit

Date: 2026-10-14
Status: Audit complete; production lifecycle readiness remains blocked by Core/platform-owned gaps.

## Scope and constraints

This was a read-only audit with disposable/in-memory or isolated route-loader probes. No database
rows, lifecycle states, schema, migrations, deployment artifacts, or live HTTP surfaces were
mutated. Core files were not changed. Existing OH-AUTO changes and runtime logs were preserved.

## Authoritative lifecycle model

### Applications

- `core_apps.status` is authoritative for installable app runtime loading.
- `uploaded`, `installed`, `disabled`, `broken`, `upgrade_pending`, and `uninstalled` are not
  loaded by `AppRuntimeLoader`; only `enabled` rows are returned by
  `AppRegistryService::enabledApps()`.
- `AppLocalDiscoveryService` discovers local manifests and refreshes app-owned runtime artifacts;
  discovery does not activate an app.
- `AppRuntimeRegistryService` materializes menus, permissions, hooks, widgets, charts, dashboards,
  and search entries only when the parent app is enabled.

### Modules and plugins

- Canonical module manifests live under `apps/*/modules/*/plugin.json`.
- `PluginManager::scan()` is repository discovery, not activation.
- `installed_plugins.status = active` is the plugin/module runtime authority for legacy and
  canonical plugin loading.
- `PluginManager::loadActivePlugins()` rejects inactive modules, missing dependencies, architecture
  errors, and strict-mode warnings before loading bootstrap/routes.
- `core_app_modules.is_enabled` is the app-module contribution flag materialized from the parent
  app status. It is not currently a universal route-dispatch guard.

## Enforcement matrix

| Surface | Expected lifecycle rule | Evidence | Result |
|---|---|---|---|
| App HTTP routes | Only enabled apps load entry files | `AppRegistryService::enabledApps()` + `AppRuntimeLoader::loadEnabledApps()` | Pass |
| Broken app entry | Failed entry is marked broken and is not treated as enabled | `AppRuntimeLoader` catches entry failure and calls `setStatus(..., broken, ...)` | Pass for app entry; partial route rollback remains unverified |
| Plugin/module routes | Only active modules load through plugin lifecycle | `PluginManager::loadActivePlugins()` and dependency checks | Pass for plugin path |
| SBAIO legacy fallback routes | Inactive installed modules must not be reloaded by app fallback | `apps/SBAIO/routes.php` previously loaded every listed module path | **Fixed outside Core** |
| Menus/permissions/hooks/search | Parent app must be enabled | `AppRuntimeRegistryService` queries join `core_apps` with `status='enabled'` | Pass at app boundary |
| Module menu/hook surfaces | Inactive module declarations must be suppressed | Current materialization has no module-status join/filter | **Blocked Core/platform contract** |
| Host surfaces | Disabled parent app and disabled hook are excluded | `HostSurfaceRegistryService` filters app enabled + hook enabled | Pass |
| Operator app contributions | Disabled parent app excluded before provider loading | `OperatorSurfaceContributionRegistry::loadAppManifest()` filters enabled/active | Pass |
| Legacy operator widgets | Disabled module/app must not contribute; failures must fail closed | `OperatorLayerWidgetService` only checks module flag, assumes active on DB failure, and does not check parent app | **Transitional debt; runtime use not found** |
| Background jobs | Disabled component must not execute | No complete lifecycle-owned job registry/guard was found | **Unverified** |
| Missing dependencies | Activation/load must reject missing or inactive dependencies | `PluginManager::runtimeDependencyErrors()` and `enable()` | Pass for plugin activation path |
| Disabled dependencies | Dependent module activation is blocked | `PluginManager::enable()` requires dependency status `active` | Pass for plugin activation path |
| Cache invalidation | Lifecycle cache refresh must be proven | No isolated cache invalidation contract was found | **Unverified** |

## Verified defect and correction

### SBAIO legacy fallback reloaded inactive installed modules

`apps/SBAIO/routes.php` iterated `legacy_bridge_plugins`, resolved each module path, and required
its `bootstrap.php` and `routes.php` without checking `installed_plugins.status`. This bypassed the
active-only module contract when the parent SBAIO app was enabled. The current installation has
inactive installed modules including Customers, Tasks, Sales, Expenses, Leave, Notices, Timecards,
Schedules, and Payroll.

The fallback now skips modules already registered in the plugin lifecycle. This preserves the
fallback only for local-only modules not yet registered, matching the established Manufacturing
bundle fallback pattern.

Changed file:

- `apps/SBAIO/routes.php`

## Core/platform escalations

### LIFECYCLE-001 — Module-owned materialized surfaces ignore module status

`AppRuntimeRegistryService::syncMenus()`, `syncPermissions()`, `enabledSurfacesByType()`, and
`emitBootHooksForApp()` filter parent app status and hook enablement but do not join/filter
`core_app_modules.is_enabled` or `installed_plugins.status`. Read-only inspection confirms that
SBAIO app-owned menu/route declarations are enabled while several legacy module rows are inactive,
but those hook payloads do not carry module identity. Therefore stale module-specific
materialization is a **plausible exposure and attribution gap, not a proven module-specific
execution bypass** in this installation. This requires a Core/platform lifecycle contract decision
and is not changed here.

### LIFECYCLE-002 — Module route ownership is not uniformly enforced

Hospitality and SBAIO app route files register module feature routes from the enabled app path,
while module manifests declare `routes: active_only`. There is no generic module route guard at
the dispatch boundary. Manufacturing has a mixture of explicit legacy-module guards and app-level
routes. A generic fix would require Core/route authority or an explicit app-owned route-guard
contract; no speculative cross-app implementation was added.

### LIFECYCLE-003 — Broken plugin route partial-load rollback is reproduced

`PluginManager::loadActivePlugins()` does not wrap each plugin bootstrap/routes require in a local
rollback boundary. The in-memory OH-AUTO1-024 probe reproduces a route remaining after a thrown
registration step, while preserving unrelated fixture routes. End-to-end plugin activation remains
unrun because database/lifecycle mutation is prohibited. Core owner review is required before
remediation.

## Read-only validation

- PHPUnit activation contract: **2 tests / 7 assertions passed**.
- Module health diagnostic: ran read-only; reports existing capability/maturity gaps, not lifecycle
  state changes.
- SBAIO isolated route-loader probe after correction: inactive installed module routes absent; route
  loader completed with 13 suite-level routes and no database writes.
- Business app/module contract gate: **PASS**.
- Capability ownership gate: **PASS** (existing QR namespace warnings remain documented debt).
- Operator confinement gate: **PASS**.
- Studio boundary gate: **PASS**.
- Shell rendering contract gate: **PASS**.
- PHP lint: touched SBAIO route and audited lifecycle files pass.
- `git diff --check`: clean for this slice.
- Live browser/HTTP acceptance: not run by authorization.

## Remaining unverified behavior

- Background-job dispatch and queue worker lifecycle guards.
- Cache invalidation after app/module state transitions.
- Runtime HTTP rejection of a disabled module route in an authenticated installation.
- Partial route cleanup after a thrown plugin bootstrap/route file.
- Dependency behavior for app bundles beyond the verified plugin activation path.

## Production-readiness conclusion

Application-level enablement is enforced consistently for the main app loader and contribution
registries. Plugin activation/dependency enforcement is also present. The audit is **not lifecycle
production-ready** because module-level materialized surfaces and module-owned route dispatch lack
one universal active-state authority. LIFECYCLE-001 through LIFECYCLE-003 remain escalated; no Core
or authorization semantics were changed.
