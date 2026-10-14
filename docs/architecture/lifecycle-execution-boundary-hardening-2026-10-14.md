# OH-AUTO1-017 — Lifecycle Execution-Boundary Hardening

Date: 2026-10-14
Status: Bounded investigation complete; no Core correction authorized or required by the verified
non-Core findings.

## Baseline and constraints

- Repository `HEAD`: `42e0ccd8c0f9b695dad2b4869f856e579183ded4`.
- Existing uncommitted OH-AUTO changes, lifecycle logs, and the OH-AUTO1-016 SBAIO correction were
  preserved.
- No Core files, lifecycle state, database rows, schema, migrations, deployment artifacts, or live
  HTTP surfaces were changed.
- No commit or push was performed.

## Lifecycle execution-boundary matrix

| Surface | Source / loader | Authoritative state | Pre-materialization or load filter | Runtime result | Classification |
|---|---|---|---|---|---|
| App entry routes | `AppRuntimeLoader::loadEnabledApps()` | `core_apps.status` | `AppRegistryService::enabledApps()` returns only `enabled` | Disabled app entry is not loaded | Verified enforced |
| App entry failure | `AppRuntimeLoader` entry require | app status plus failure transition | Throwable path marks app `broken` | Entry failure does not continue as enabled | Verified for entry; route rollback not implied |
| Canonical plugin routes | `PluginManager::loadActivePlugins()` | `installed_plugins.status` | Active-name selection, dependency checks, architecture checks | Inactive plugin route files are not loaded | Verified enforced |
| SBAIO legacy fallback | `apps/SBAIO/routes.php` | `installed_plugins.status` when registered | Installed modules are delegated to plugin lifecycle; unregistered local modules retain fallback | Inactive installed module routes absent | **Corrected and verified** |
| SBAIO suite routes | `apps/SBAIO/routes.php` | `core_apps.status` | Parent app entry only | Suite-owned import/export/report routes remain available when SBAIO is enabled | Verified preserved |
| App menus/permissions/hooks/search | `AppRuntimeRegistryService` | `core_apps.status`, hook `is_enabled` | Parent app join and hook flag | Enabled app contributions materialize | Verified app-level enforcement |
| Module attribution in materialized surfaces | `AppRuntimeRegistryService` | `core_app_modules.is_enabled` / plugin status | No universal module-status join; current payloads often lack module identity | Stale module-specific exposure cannot be proven or disproven from current rows | Open Core/platform contract gap |
| Host/operator contributions | Host and operator contribution registries | Parent app status, hook flag, module/plugin status where provider-specific | Fail-closed checks present in reachable contribution services | Disabled app/module contributions are suppressed in verified paths | Verified for inspected paths |
| Legacy Shell widget service | `OperatorLayerWidgetService` | `core_app_modules.is_enabled` | Provider checks module flag, but has fail-open DB exception and no parent-app check | Current runtime call graph does not reach discovery methods | Dormant test debt; not a verified bypass |
| Background execution | No complete lifecycle-owned job registry found | Unresolved | No universal guard demonstrated | Cannot establish safe inactive-job behavior | Unverified / owner escalation |
| Cache invalidation | `AppLifecycleService` registry refresh paths | App/module state transition | No complete cache invalidation contract found | Stale in-process contribution caches remain unproven | Unverified / owner escalation |

## Verified correction

`apps/SBAIO/routes.php` previously iterated every `legacy_bridge_plugins` entry and required its
bootstrap/routes files, even when the module was already registered but inactive. The correction
skips any module recognized by `PluginManager::isInstalled()`, leaving registered lifecycle loading
to `PluginManager::loadActivePlugins()` and preserving fallback behavior only for local-only modules
not present in the installed registry.

This is the smallest non-Core correction and does not introduce a second lifecycle registry.

## Negative verification

Permanent probe:

- `apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php`
- **13/13 passed**.
- Confirms enabled suite routes remain registered.
- Confirms an inactive installed fixture exists.
- Confirms all nine inactive installed SBAIO legacy-module route paths are absent.
- Uses route loading and read-only registry queries only.

The earlier SBAIO route-loader probe also passed with 13 routes and no database writes.

## Plugin partial-route rollback assessment

Disposable fixture behavior:

1. A temporary route file registers `/fixture/partial`.
2. The same file throws immediately afterward.
3. The exception is caught by the fixture harness.
4. `Router::listRoutes()` still contains `/fixture/partial`.

Observed result: `PARTIAL_ROUTE_REMAINS`.

This demonstrates that current route registration is not transactional. The actual plugin loader
wraps neither each bootstrap/route require nor the router in a rollback boundary. Implementing
rollback requires Core router/plugin-loader ownership and an explicit decision about route, view
namespace, event, and boot-hook cleanup. It remains escalated as `LIFECYCLE-003`; no Core change
was made.

## Architecture timeout resolution

Original bounded invocation:

- `bash scripts/architecture/run_architecture_gates.sh`
- Harness timeout: 120 seconds.
- Output appeared to stop during theme-source output because the tool preview was truncated; this
  was not the actual stopping point.

Isolated findings:

- `check_theme_source_integrity.sh`: **PASS**, approximately 0.6 seconds.
- The aggregate reached and completed every gate through
  `check_studio_deletion_family_capability_suite.sh` only after the 120-second ceiling had already
  elapsed.
- The deletion-family suite contains 28 nested gates and completes in approximately 5m30s when
  given a 10-minute bound; it returned **PASS (28/28)**.
- Full aggregate rerun with a 10-minute bound completed in approximately 7m45s and returned
  **ARCHITECTURE GATES: PASS**.

Conclusion: the OH-AUTO1-016 timeout was a slow sequential aggregate scan, dominated by the final
deletion-family suite, not a stuck theme scan or an architecture defect. No timeout script change
was necessary.

## Remaining production blockers

- `SEC-AUTH-001` remains open under Core/security ownership.
- Module-specific materialized-surface filtering lacks a universal identity/state contract.
- Universal module route dispatch enforcement remains unresolved outside the SBAIO correction.
- Plugin partial-route rollback is not transactional and requires Core governance.
- Background-job lifecycle guards and cache invalidation remain unverified.
- Live authenticated HTTP acceptance and deployment/recovery authority contracts remain incomplete.

## Verification results

- Lifecycle execution-boundary probe: **PASS, 13/13**.
- Deletion-family gates: **PASS, 28/28**.
- Full architecture gates: **PASS** with 10-minute bounded execution.
- Business app/module, capability ownership, operator confinement, Studio boundary, and Shell
  rendering gates: **PASS**.
- PHP lint: **PASS** for SBAIO route and lifecycle probe.
- `git diff --check`: **PASS**.
- Live HTTP/database writes/lifecycle transitions: **NOT RUN by authorization**.

## Recommended next assignment

Core/security-owner review of the module lifecycle authority contract, including module identity in
materialized declarations, universal route dispatch enforcement, and transactional plugin route
rollback. No further non-Core workaround should be added until that authority decision is made.
