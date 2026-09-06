# Phase 3: Core / App / Plugin Contract Hardening

Date: 2026-04-06

> Historical implementation record.
> Use this document for migration context and diagnostics history.
> Current authoritative governance policy remains in `AGENTS.md` and `docs/architecture/*`.

## 1) Core/App/Plugin Contract Model

This phase introduces a runtime-contract model that is explicit, app-owned, and lifecycle-bound.

### App Contract (manifest-level)

Each app manifest is normalized into:

- `runtime_contract.owner_app`
- `runtime_contract.routes[]`
  - `path`
  - `feature_key`
  - `kind` (`canonical|alias`)
  - `canonical_target` (for aliases)
  - `compatibility`
  - `deprecated`
  - `nav_visible`
  - `search_visible`
  - `lifecycle_bound`
  - `role_visibility[]`
- `runtime_contract.navigation_items[]`
- `runtime_contract.widgets[]`
- `runtime_contract.charts[]`
- `runtime_contract.dashboards[]`
- `runtime_contract.search_entries[]`
- `runtime_contract.notifications[]`
- `runtime_contract.resolvers[]`
- `runtime_contract.permissions[]`
- `runtime_contract.migrations`
- `runtime_contract.purge`
- `runtime_contract.module_contracts[]`
- `runtime_contract.plugin_modules[]`

### Plugin/Module Contract

For app-owned plugin/module metadata:

- `owner_app`
- `feature_key`
- `runtime_surfaces[]`
- `dependencies[]`
- `kind` (`canonical|compatibility`)
- `nav_visible`
- `search_visible`
- `role_scoped`

### Localization Readiness Signal

The contract validator now emits `contract_warnings` when explicitly declared UI entries use hardcoded labels without `label_key`.

## 2) Updated Files / Enforcement Points

### Manifest normalization + enforcement

- `app/Services/AppManifestService.php`
  - builds normalized `runtime_contract`
  - derives route canonical/alias metadata from manifest routes
  - normalizes plugin/module contracts
  - emits `contract_warnings`
  - provides single `surfaceHooksFromManifest()` for runtime registration

### Install/discovery registration hardened

- `app/Services/AppInstallService.php`
  - registers all runtime surfaces through `surfaceHooksFromManifest()`
- `app/Services/AppLocalDiscoveryService.php`
  - same hook registration path
  - respects app enabled status for all hook inserts
  - logs manifest contract warnings via platform logger

### Route registry metadata hardened

- `app/Core/RouteRuntimeAuthority.php`
  - diagnostics now include `route_contracts`
  - summary now includes contract counters:
    - total
    - loaded
    - alias count
    - compatibility count
    - deprecated count

### Surface metadata retrieval hardened

- `app/Services/AppRuntimeRegistryService.php`
  - generalized app-surface retrieval by hook type
  - new APIs: `enabledCharts()`, `enabledDashboards()`, `enabledSearchEntries()`

### Search ownership hardened

- `app/Core/SearchService.php`
  - chart catalog now merges app-owned `chart` hooks first
  - core fallback remains for resilience

### Manufacturing app contract (first app)

- `apps/Manufacturing/manifest.json`
  - explicit `runtime_contract` added for routes/aliases/charts/dashboards/search entries/resolvers/notifications/module+plugin contracts

### Localization keys added

- `app/Locale/en.php`
- `app/Locale/ja.php`
  - added keys used by declared contract labels (assembly leader, demand workspace, chart labels)

## 3) Examples of Added/Normalized Metadata

### Route metadata (from contract)

- `/manufacturing/production-dashboard`
  - `kind=canonical`
  - `feature_key=production_dashboard`
  - `nav_visible=true`
  - `search_visible=true`
  - `lifecycle_bound=true`
- `/ops/production-dashboard`
  - `kind=alias`
  - `canonical_target=/manufacturing/production-dashboard`
  - `compatibility=true`

### Plugin/module metadata

- `Machines`
  - `owner_app=manufacturing`
  - `feature_key=production_dashboard`
  - `runtime_surfaces=[/manufacturing/production-dashboard, /manufacturing/production-workboard]`
  - `kind=canonical`

### Chart/search/dashboard metadata

- `throughput_trend` chart entry declared by app contract with canonical URL and localization key
- dashboard/search metadata declared under runtime contract instead of implicit core-only assumptions

## 4) Remaining Architecture Leaks (Not Fully Covered Yet)

1. Manufacturing route handlers still call Base/plugin controllers for some surfaces.
2. Legacy bridge plugin loading remains transitional (`apps/Manufacturing/routes.php`).
3. Notifications/workflow semantics are still largely table-level and not yet fully provider-driven.
4. Navigation is still split across `navigation.php` and manifest menus; contract metadata exists, but rendering still uses existing navigation pipelines.
5. Contract warnings are currently non-blocking; they guide behavior but do not fail install/discovery yet.

## 5) Recommended Next Enforcement Steps

1. Add a strict-mode toggle (`ERP_APP_CONTRACT_STRICT=1`) to fail install/discovery on contract violations.
2. Add App Manager contract diagnostics panel:
   - canonical vs alias breakdown
   - deprecated alias list
   - localization warnings
   - unresolved canonical targets
3. Move app chart/search/dashboard rendering fully to contract-driven providers and retire hardcoded fallbacks.
4. Add route-level ACL contract fields and verify ACL consistency in diagnostics.
5. Introduce contract tests for each app:
   - route contract validity
   - lifecycle-bound surface cleanup on disable/uninstall/purge
   - localization key existence checks for declared contract labels.

## Validation Snapshot

- Lifecycle matrix after Phase 3 changes: `174 PASS | 0 FAIL | 5 SKIP`.
- Route contract diagnostics snapshot (manufacturing boot):
  - `contract_route_total=45`
  - `contract_route_alias=14`
  - `contract_route_compatibility=14`
  - `contract_route_deprecated=4`
