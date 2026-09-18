# Manufacturing/IPM Bundle Decomposition Audit — Read Only

Date: 2026-09-19
Status: **READ ONLY** — No modifications, moves, edits, schema changes, migrations, commits, pushes, or runtime mutations performed.
Scope: Repository-grounded classification of every app/module/component associated with the legacy Manufacturing/IPM bundle.

---

## Evidence Base (all repository-grounded)

- `apps/Manufacturing/manifest.json` — `id: "manufacturing"`, `app_key: "manufacturing"`, `package_type: "bundle"`, `type: "business"`, 18 modules, `group: "ipm"` for 17 of 18 modules; `MaterialManagement` uses `group: "planning"`.
- All 18 `apps/Manufacturing/modules/*/plugin.json` descriptors — each declares `suite: "manufacturing"`, `owner_app: "manufacturing"`, with `requires` arrays establishing intra-bundle dependency graph.
- `docs/architecture/current-ownership-inventory.md` §5.1 — prior read-only audit closure at commit `6663b4b` (main).
- `docs/architecture/shared-app-extension-readiness.md` — confirms Manufacturing is a Domain App; no Shared App exists today.
- `docs/manufacturing-phase2-route-normalization.md` — canonical routes are Manufacturing-owned; aliases remain Manufacturing-owned.
- `shared/Item/Foundation/Contracts/ItemIdentityContract.php` — independent shared identity contract; references Manufacturing Product via `item_ref`.
- `apps/Manufacturing/modules/Products/migrations/012_add_item_ref.sql` — adds `item_ref INT` to `products` table.
- `apps/Manufacturing/search.php` — declares `ManufacturingSearchProvider`.
- `engineering/Manufacturing/work.md` — states Manufacturing work touching identity/Shared/Foundation/suite-ownership is subordinate to `docs/architecture/odarehub-future-architecture-planning-brief.md`; no Shared/Foundation restructuring authorized.
- `work/shared-parties-foundation/` — unmerged Session B artifact (only `docs/` directory), untouched.

---

## A. Current Inventory

### A.1 Top-Level Bundle

| Item | Location | Manifest Identity | Current Owner | Runtime Status |
|------|----------|-------------------|---------------|----------------|
| Manufacturing bundle | `apps/Manufacturing/` | `manifest.json`: `app_key: "manufacturing"`, `package_type: "bundle"`, `type: "business"` | Manufacturing (Domain App) | Active; 18 modules; 55+ routes; 30+ menus; 8 widgets |

### A.2 Modules (18 total)

| Module | Location | `plugin.json` `group` | `module_type` | Requires (intra-bundle) |
|--------|----------|----------------------|---------------|------------------------|
| Products | `apps/Manufacturing/modules/Products/` | `ipm` | `business_entity` | `Base>=3.0.0` |
| Bom | `apps/Manufacturing/modules/Bom/` | `ipm` | `business_entity` | `Base>=3.0.0`, `Products>=1.0.0` |
| DailyOrders | `apps/Manufacturing/modules/DailyOrders/` | `ipm` | `business_entity` | `Base>=3.0.0`, `Products>=1.0.0` |
| ProductionQueue | `apps/Manufacturing/modules/ProductionQueue/` | `ipm` | `dashboard_only` | `Base>=3.0.0`, `Products`, `Machines`, `ProductionPlans`, `ProductionEntries` |
| Ledger | `apps/Manufacturing/modules/Ledger/` | `ipm` | `business_entity` | `Base>=3.0.0`, `Products>=1.0.0` |
| Machines | `apps/Manufacturing/modules/Machines/` | `ipm` | `business_entity` | `Base>=3.0.0` |
| MaterialManagement | `apps/Manufacturing/modules/MaterialManagement/` | `planning` | `business_entity` | `Base>=3.0.0` |
| PartMachineMap | `apps/Manufacturing/modules/PartMachineMap/` | `ipm` | `business_entity` | `Base>=3.0.0`, `Products`, `Machines` |
| PreOrders | `apps/Manufacturing/modules/PreOrders/` | `ipm` | `business_entity` | `Base>=3.0.0`, `Products>=1.0.0` |
| ProductionEntries | `apps/Manufacturing/modules/ProductionEntries/` | `ipm` | `process_execution` | `Base>=3.0.0`, `Products`, `Machines` |
| ProductionPlans | `apps/Manufacturing/modules/ProductionPlans/` | `ipm` | `planning` | `Base>=3.0.0`, `Products`, `Machines` |
| QCPlans | `apps/Manufacturing/modules/QCPlans/` | `ipm` | `planning` | `Base>=3.0.0`, `Products`, `DailyOrders`, `ProductionEntries` |
| QCEntries | `apps/Manufacturing/modules/QCEntries/` | `ipm` | `process_execution` | `Base>=3.0.0`, `Products`, `QCPlans`, `DailyOrders`, `ProductionPlans` |
| DispatchEntries | `apps/Manufacturing/modules/DispatchEntries/` | `ipm` | `process_execution` | `Base>=3.0.0`, `Products`, `DailyOrders`, `ProductionPlans`, `ProductionEntries`, `QCEntries`, `Workflow` |
| AssemblyPlans | `apps/Manufacturing/modules/AssemblyPlans/` | `ipm` | `planning` | `Base>=3.0.0`, `Products`, `DailyOrders`, `ProductionEntries` |
| AssemblyEntries | `apps/Manufacturing/modules/AssemblyEntries/` | `ipm` | `process_execution` | `Base>=3.0.0`, `Products`, `AssemblyPlans` |
| Coverage | `apps/Manufacturing/modules/Coverage/` | `ipm` | `dashboard_only` | `Base>=3.0.0` |
| Supply | `apps/Manufacturing/modules/Supply/` | `ipm` | `service_only` | `Base>=3.0.0` |
| Workflow | `apps/Manufacturing/modules/Workflow/` | `ipm` | `governance` | `Base>=3.0.0` |

### A.3 Cross-Suite References Found

| Item | Location | Evidence | Direction |
|------|----------|----------|-----------|
| Shared Item Identity Contract | `shared/Item/Foundation/Contracts/ItemIdentityContract.php` | Independent contract; Manufacturing Product references via `item_ref` | Manufacturing → Shared Items (one-way reference) |
| Manufacturing Search Provider | `apps/Manufacturing/search.php` | Declares `ManufacturingSearchProvider` | Manufacturing-owned |
| Platform Manufacturing metrics | `apps/Platform/routes.php:782-804` | `getManufacturingIntelligence()` | Platform reads Manufacturing data |
| Platform Organization branding | `apps/Platform/modules/Organization/Views/branding.php:13-16` | `.ipm-logo` CSS class handling | Platform consumes Manufacturing CSS class name |
| Base GovernanceInboxService | `plugins/Base/Services/GovernanceInboxService.php` | References `Plugins\Workflow\Services\*` | Base → Manufacturing Workflow |
| Platform App Admin scope | `apps/Platform/Tests/` | Manufacturing assigned-app scope tests | Platform admin consumes Manufacturing identity |
| `work/shared-parties-foundation/` | `work/shared-parties-foundation/docs/` | Unmerged Session B artifact; only docs, no code | Unrelated to Manufacturing |

### A.4 Schema/Database Ownership

- All Manufacturing module SQL migrations live under `apps/Manufacturing/modules/*/migrations/` and `apps/Manufacturing/migrations/`.
- `manufacturing_bom` and `manufacturing_bom_line` tables are Manufacturing-owned (Bom module `required_tables`).
- `products` table has Manufacturing-owned columns including `item_ref` (added by `012_add_item_ref.sql`).
- No shared-items SQL table exists; identity store stays verification JSON (`shared/Item/Foundation/`).

### A.5 Routes/Navigation/Search/Permissions

- **Routes**: 55+ canonical routes under `/apps/manufacturing/*`, `/manufacturing/*`, `/dispatch-entries`, `/qc-entries`, etc. All owned by Manufacturing manifest `routes.php`. Compatibility aliases (`/dispatch`, `/qc`, `/production`, `/mfg`, `/ops/*`) remain Manufacturing-owned.
- **Navigation**: 30+ menu items in manifest `menus` array, all under `app.mfg.root`. Permissions like `manufacturing.view`, `manufacturing.manage`, `materials.*`.
- **Search**: `apps/Manufacturing/search.php` declares `ManufacturingSearchProvider`; entries in manifest `runtime_contract.search_entries`.
- **Widgets**: 8 widget definitions in manifest `widgets` array, all `domain: "manufacturing"`, `zone: "manufacturing"`.
- **Hooks**: `host_surface` contributions to `me`, `approval_inbox`, `role_inbox`; `operator_surface` contributions to sidebar, focus_views, data_exchange, breadcrumbs, focus_labels, bottom_actions.

### A.6 Styles/Assets

- `apps/Manufacturing/styles/manufacturing.css` — app-level CSS (`scope: "app"`).
- `apps/Manufacturing/styles/display-floor.css` — display-floor CSS (`scope: "app"`, `.ipm-logo` class).
- Module-level CSS in `modules/*/styles.css` (ProductionEntries, Machines, DispatchEntries, QCPlans, QCEntries, Coverage).

---

## B. Classification Matrix

| # | Item | Classification | Evidence |
|---|------|----------------|----------|
| 1 | Manufacturing bundle (`apps/Manufacturing/`) | **Suite-owned App** | `manifest.json`: `app_key: "manufacturing"`, `type: "business"`, all 18 modules `owner_app: "manufacturing"`. Domain App per `current-ownership-inventory.md`. |
| 2 | All 18 modules (Products, Bom, DailyOrders, etc.) | **Suite-owned App/Module** | Each `plugin.json`: `suite: "manufacturing"`, `owner_app: "manufacturing"`, `group: "ipm"` (or `planning`). Intra-bundle `requires` graph confirms single-owner dependency chain. |
| 3 | Products module `item_ref` column | **Suite Extension** (to Shared Items) | `012_add_item_ref.sql` adds `item_ref INT` referencing `shared/Item/Foundation/`. Manufacturing owns the products table; the `item_ref` is a reference to an independent shared identity contract. |
| 4 | Shared Item Identity Contract (`shared/Item/Foundation/`) | **Shared App** (proven candidate, not Manufacturing-owned) | Independent identity contract with `ItemIdentityContract`, `ItemIdentityService`. Manufacturing references it via `item_ref`, but Shared Items has zero Manufacturing-owned operational truth. Classified as Shared per `shared-app-extension-readiness.md` (Inventory/Items candidate). |
| 5 | `work/shared-parties-foundation/` | **Shared App** (unmerged skeleton, not Manufacturing-related) | Session B artifact; only `docs/` directory; `apps/Parties/` has no `plugin.json`. Zero Manufacturing consumer declared. |
| 6 | Manufacturing Search Provider (`apps/Manufacturing/search.php`) | **Suite-owned App/Module** | Declares `ManufacturingSearchProvider`; business-specific search knowledge stays in Manufacturing. |
| 7 | Platform Manufacturing metrics/routes (`apps/Platform/routes.php:782-804`) | **Suite Extension** (Platform consuming Manufacturing) | Platform reads Manufacturing data via `getManufacturingIntelligence()`; Platform does not own Manufacturing operational truth. |
| 8 | Platform `.ipm-logo` CSS handling (`apps/Platform/modules/Organization/Views/branding.php:13-16`) | **Compatibility / Migration Artifact** | Platform consumes Manufacturing's `.ipm-logo` class name for branding preview. This is a display-side compatibility concern, not operational ownership. |
| 9 | Base `GovernanceInboxService` referencing `Plugins\Workflow\Services\*` | **Compatibility / Migration Artifact** | `plugins/Base/Services/GovernanceInboxService.php` hardcodes `Plugins\Workflow\Services\*` FQCNs. This is legacy compatibility vocabulary per `current-ownership-inventory.md` §10. |
| 10 | Manufacturing compatibility route aliases (`/dispatch`, `/qc`, `/production`, `/mfg`, `/ops/*`) | **Compatibility / Migration Artifact** | Documented as `kind: "alias"`, `compatibility: true` in manifest `runtime_contract.routes`. Owned by Manufacturing but retained for backward compatibility. |
| 11 | `legacy_bridge_plugins` list in manifest | **Compatibility / Migration Artifact** | `manifest.json` line ~661 lists 19 module names as `legacy_bridge_plugins`. Historical metadata; does not create independent plugin directories. |
| 12 | `group: "ipm"` vocabulary | **Compatibility / Migration Artifact** | Derived from `suite: "manufacturing"` (per `PluginManager.php`). Not shared authority. Must not be removed without loader/contract authorization. |
| 13 | `bundle` / `package_type: "bundle"` vocabulary | **Compatibility / Migration Artifact** | Active archive/parser shape; not first-class Suite runtime proof. |
| 14 | `work/shared-parties-foundation/` (unmerged) | **Ownership Unresolved** | Only `docs/` present; no code, no `plugin.json`, no `core_apps` entry. Skeleton only. |
| 15 | `apps/Parties/` | **Shared App** (proven, independent) | `kind: "shared_app"` per `current-ownership-inventory.md`; zero Manufacturing consumer declared; skeleton `routes.php`. Not part of Manufacturing bundle. |
| 16 | Manufacturing `Workflow` module | **Suite-owned App/Module** | `module_type: "governance"`, `suite: "manufacturing"`. Owns workflow policy/registry. Base `GovernanceInboxService` references it via legacy `Plugins\Workflow\Services\*` — compatibility bridge. |
| 17 | Manufacturing `Supply` module | **Suite-owned App/Module** | `module_type: "service_only"`, `target_maturity_level: "L0"`. Service-only within Manufacturing. |
| 18 | Manufacturing `MaterialManagement` module | **Suite-owned App/Module** | `group: "planning"` (only non-ipm group). Business entity within Manufacturing. |

---

## C. Ownership/Location Mismatches

| Item | Issue | Severity |
|------|-------|----------|
| Base `GovernanceInboxService` references `Plugins\Workflow\Services\*` | Physical location is `plugins/Base/`, but references Manufacturing Workflow services by legacy `Plugins\` namespace. This is a compatibility bridge, not a location mismatch per se — the namespace convention is documented as legacy. | Low (documented compatibility) |
| Platform `.ipm-logo` CSS class | Platform Organization branding consumes a Manufacturing-originated CSS class name. Display-side only; no operational truth transferred. | Low (cosmetic dependency) |
| `work/shared-parties-foundation/` | Located at `work/` (not `apps/Shared/`), but is the unmerged Session B artifact for Shared Parties. Does not belong to Manufacturing bundle. | Informational |
| `apps/Generated/manufacturing_app/` | Generated staging artifact beneath `apps/Generated/`. Not a direct app discovery input per `current-ownership-inventory.md`. Should not be treated as an installed/shared app. | Low (generated artifact) |
| `plugins/Base/Services/GovernanceInboxService.php` | References `Plugins\Workflow\Services\*` FQCNs directly. Only one `WorkflowGovernance` class had an `App\Core\WorkflowGovernance` bridge (compatibility only, zero consumers). Hidden load-order dependence; addressed by scoped PSR-4 mapping in engineering/Manufacturing/decisions.md. | Low (documented, scoped fix proposed) |

**No physical location disagrees with declared ownership for Manufacturing bundle components.** All 18 modules are physically inside `apps/Manufacturing/modules/*/` as expected by their `plugin.json` `owner_app: "manufacturing"` declarations.

---

## D. Dependency Blockers

### D.1 Intra-Bundle Dependency Chain (must remain intact)

```
Products → Bom, DailyOrders, PreOrders, PartMachineMap, Ledger, ProductionPlans, ProductionEntries, QCPlans, QCEntries, AssemblyPlans, AssemblyEntries, ProductionQueue, DispatchEntries
Machines → PartMachineMap, ProductionEntries, ProductionPlans, ProductionQueue
DailyOrders → AssemblyPlans, QCPlans, ProductionQueue, DispatchEntries, QCEntries
ProductionPlans → ProductionQueue, DispatchEntries
ProductionEntries → AssemblyPlans, AssemblyEntries, DispatchEntries, QCPlans, ProductionQueue, QCEntries, DispatchEntries
QCPlans → QCEntries
Workflow → DispatchEntries
```

**Any decomposition must preserve this intra-bundle graph.** The `requires` arrays in each `plugin.json` establish hard dependencies. Moving any module out of Manufacturing would break `PluginManager` activation and `AppLocalDiscoveryService` registration.

### D.2 Cross-Suite Dependency Directions

| Direction | Evidence | Blocker |
|-----------|----------|---------|
| Manufacturing → Shared Items | `products.item_ref` INT column (schema-level FK reference); `PartItemRefService` validates locally using `App\Core\DB`; no runtime import of `Shared\Item\Foundation\*`. `ItemIdentityService` uses isolated JSON store; zero imports of Manufacturing code. Shared Items docblocks mention Manufacturing as context only. | One-way schema reference; zero runtime coupling. Manufacturing owns the column and table; Shared Items owns the identity contract. No callback/reverse dependency exists. |
| Platform → Manufacturing | `apps/Platform/routes.php:782-804` reads `getManufacturingIntelligence()` | Platform consumes Manufacturing data for analytics/admin surfaces. Platform does not own Manufacturing operational truth. |
| Base → Manufacturing (Workflow) | `plugins/Base/Services/GovernanceInboxService.php` references `Plugins\Workflow\Services\*` | Legacy compatibility bridge; zero runtime consumers of `App\Core\WorkflowGovernance`. Scoped PSR-4 mapping proposed but not yet committed. |
| Manufacturing → Platform (Branding) | `.ipm-logo` CSS class in `apps/Manufacturing/styles/display-floor.css` consumed by `apps/Platform/modules/Organization/Views/branding.php` | Cosmetic only; display-surface dependency. |

### D.3 What Cannot Safely Be Moved Independently

1. **Any Manufacturing module** — All modules have `owner_app: "manufacturing"` and `suite: "manufacturing"`. Moving any module would invalidate `PluginManager` activation, `AppLocalDiscoveryService` registration, and the `requires` dependency graph.
2. **The `item_ref` schema reference** — Manufacturing `products.item_ref` is an INT column that semantically references Shared Items identity. This is a **one-way schema-level foreign key reference** only. Runtime evidence is unambiguous:
     - `PartItemRefService` (Manufacturing) uses only `App\Core\DB`; it never imports, queries, or calls `Shared\Item\Foundation\*`.
     - `ItemIdentityService` (Shared Items) uses isolated JSON storage (`/tmp/shared-items-store.json`); it never imports, queries, or depends on `Apps\Manufacturing\*` or `Plugins\*`.
     - `shared/Item/Foundation/` has **no `composer.json`** and **no PSR-4 autoload mapping** in `composer.json`; it is not registered as a runtime package.
     - The only Shared Items references to Manufacturing are **docblock comments** in `ItemIdentityContract.php` (`@purpose`, `@return` annotations). Docblock text is documentation, not runtime dependency.
     - **No callback, reverse query, synchronization, or event hook exists in either direction.**
     - Manufacturing owns the `products` table and the `item_ref` column; Shared Items owns the identity contract and its isolated store. Operational truth is fully separated.
     - **There is no bidirectional coupling.** The relationship is a one-way schema reference with zero runtime dependency.
3. **Compatibility route aliases** — `/dispatch`, `/qc`, `/production`, `/mfg`, `/ops/*` are documented as `compatibility: true` in the manifest. Removing them would break existing consumer bookmarks and `PluginManager` route registration.
4. **The `Plugins\{Module}` namespace convention** — Documented as compatibility-only vocabulary (§10). Removing without loader/contract authorization would break `Workflow` service references and widget providers.
5. **Manufacturing CSS `.ipm-logo`** — Consumed by Platform Organization branding. Removing without coordination would break Platform display.

---

## E. Proposed Migration Order — Planning Only

> **Status: Planning only. No implementation authorized.**
> Per `engineering/Manufacturing/work.md`: "No Shared/Foundation code, schema, storage, or runtime restructuring is authorized until the brief's pressure-test workstreams and synthesis complete."

### Phase 0 (Prerequisites — must complete first)
1. User authorization for any decomposition per `docs/architecture/shared-app-extension-readiness.md` promotion rule.
2. Completion of `docs/architecture/odarehub-future-architecture-planning-brief.md` pressure-test workstreams.
3. Cross-domain contracts (owner-neutral entities, per-domain data scoping, per-domain permissions) for any Shared App candidate.

### Phase 1 (Shared Items Foundation — already partially in place)
- `shared/Item/Foundation/` is already an independent shared contract.
- `item_ref` column in `products` already references it.
- **Action**: Formalize Shared Items as a proven Shared App candidate with explicit user approval. No file moves.

### Phase 2 (Shared Parties — if promoted)
- `apps/Parties/` exists as `kind: "shared_app"` with skeleton `routes.php`.
- `work/shared-parties-foundation/` is unmerged Session B artifact.
- **Action**: Merge `work/shared-parties-foundation/` into `apps/Parties/` if user authorizes. Zero Manufacturing consumer.

### Phase 3 (Procurement promotion — if applicable)
- `apps/Procurement/` is `type: "business"` (undecided). Strongest existing Shared App candidate per `shared-app-extension-readiness.md`.
- Requires owner-neutral data contracts, per-domain permission mapping, second committed consumer.
- **Action**: No manufacturing-related decomposition here.

### Phase 4 (Bundle vocabulary cleanup — deferred)
- `group: "ipm"`, `bundle`, `legacy_bridge_plugins`, `Plugins\{Module}` namespace all remain as compatibility vocabulary per `current-ownership-inventory.md` §10.
- **Action**: Deferred until loader/contract authorization. Must not be removed without corresponding authorization.

### Phase 5 (Module-level promotion — requires second consumer)
- Any Manufacturing module promotion to Shared App requires a second independent consuming domain.
- **Action**: Requires architecture authorization + second consumer evidence.

---

## F. Items That Must Remain Untouched

| Item | Reason |
|------|--------|
| All 18 `apps/Manufacturing/modules/*/` directories and their contents | `owner_app: "manufacturing"`, `suite: "manufacturing"` per `plugin.json`. Moving would break `PluginManager`, `AppLocalDiscoveryService`, and `requires` dependency graph. |
| `apps/Manufacturing/manifest.json` | Declares `app_key: "manufacturing"`, `package_type: "bundle"`, all routes, menus, widgets, hooks. Core ownership document. |
| `apps/Manufacturing/routes.php` and `apps/Manufacturing/modules/*/routes.php` | All routes registered by Manufacturing manifest. Compatibility aliases must remain. |
| `apps/Manufacturing/modules/*/migrations/*.sql` | Schema ownership lives with Manufacturing modules. No shared-items SQL table exists. |
| `shared/Item/Foundation/` | Independent shared identity contract. Manufacturing references it via `item_ref`; it does not own Manufacturing operational truth. Must remain untouched as a Shared App candidate. |
| `work/shared-parties-foundation/` | Unmerged Session B artifact. Must remain untouched until user authorizes merge. |
| `apps/Generated/manufacturing_app/` | Generated staging artifact. Not a direct app discovery input. |
| `plugins/Base/Services/GovernanceInboxService.php` | Legacy compatibility bridge. Only one `WorkflowGovernance` class had a bridge (zero consumers). Removing would break existing FQCN references. |
| `apps/Platform/modules/Organization/Views/branding.php` (`.ipm-logo` handling) | Display-side compatibility. Removing would break Platform branding preview. |
| All `group: "ipm"` and `group: "planning"` classifications | Derived vocabulary. Must not be removed without loader/contract authorization. |
| `manifest.json` `legacy_bridge_plugins` list | Historical metadata. Removing without authorization would break legacy plugin resolution expectations. |

---

## Summary

**The Manufacturing/IPM bundle is a single-owner Domain App.** All 18 modules are `owner_app: "manufacturing"` with `suite: "manufacturing"`. No Manufacturing component qualifies as a Shared App or Suite Extension requiring extraction. The only cross-suite interaction is Manufacturing's `products.item_ref` referencing the independent `shared/Item/Foundation/` identity contract — a one-way reference that does not transfer operational truth to Shared Items.

**No physical location disagrees with declared ownership.** All Manufacturing components are properly located under `apps/Manufacturing/`.

**No safe extraction candidate exists.** Any decomposition would require: (1) user authorization, (2) cross-domain contracts, (3) a second independent consumer per the promotion rule, and (4) loader/contract authorization for vocabulary changes.

**The audit confirms the prior closure at `6663b4b`**: Manufacturing bundle remains Manufacturing-owned. All compatibility vocabulary (`group: "ipm"`, `bundle`, `legacy_bridge_plugins`, `Plugins\{Module}`) must not be removed without corresponding authorization.

---

## Verification

All classifications are grounded in repository files inspected:
- `apps/Manufacturing/manifest.json` (full manifest)
- All 18 `apps/Manufacturing/modules/*/plugin.json` (dependency graph)
- `shared/Item/Foundation/Contracts/ItemIdentityContract.php` and `Services/ItemIdentityService.php`
- `apps/Manufacturing/modules/Products/migrations/012_add_item_ref.sql`
- `apps/Manufacturing/search.php`
- `apps/Platform/routes.php` and `apps/Platform/modules/Organization/Views/branding.php`
- `plugins/Base/Services/GovernanceInboxService.php`
- `docs/architecture/current-ownership-inventory.md` §5.1
- `docs/architecture/shared-app-extension-readiness.md`
- `engineering/Manufacturing/work.md`
- `work/shared-parties-foundation/` (unmerged skeleton)
- `docs/manufacturing-phase2-route-normalization.md`

- `composer.json` — confirms **no PSR-4 autoload mapping** for `Shared\Item\Foundation\*`; Shared Items is not a registered runtime package
- `apps/Manufacturing/modules/Products/Services/PartItemRefService.php` — uses only `App\Core\DB`; never imports or calls `Shared\Item\Foundation\*`
- `shared/Item/Foundation/Services/ItemIdentityService.php` — isolated JSON store (`/tmp/shared-items-store.json`); rejects manufacturing-specific fields; **zero imports** of `Apps\Manufacturing\*` or `Plugins\*`
- `shared/Item/Foundation/Contracts/ItemIdentityContract.php` — Manufacturing references appear **only in docblock comments** (documentation, not runtime code)
- `shared/Item/Foundation/` directory listing — 3 files only; no `composer.json`, no manifest, no autoload config

**No modifications were made to any file except this audit artifact.**
