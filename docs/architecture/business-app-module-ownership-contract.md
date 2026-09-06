# Business App And Module Ownership Contract

Status: Minimal contract baseline for reference business apps and modules.

Purpose: Make business app and module ownership explicit so Manufacturing, SBAIO, Payroll, and future business apps remain reference implementations for Susankhya OS platform contracts instead of ad-hoc sample code.

This document does not restructure folders, rename routes, migrate modules, or change runtime behavior.

## Charter Laws Protected

This baseline protects Charter v1 laws:

- Apps own business logic.
- Apps contribute; Shell composes.
- Runtime consumes resolved/compiled contracts.
- Studio edits but does not own runtime truth.
- System Tools maintain and validate but do not bypass governance.
- No hidden duplicate source of truth.

## Business App Declaration

A Business App is a top-level installable domain capability. It owns business runtime meaning and contributes declared capabilities to Shell, Platform governance, and composed user surfaces.

Minimum app manifest or documented contract fields:

```json
{
  "app_key": "manufacturing",
  "type": "business",
  "owner": "app",
  "entry": "routes.php",
  "modules": [],
  "routes": [],
  "permissions": [],
  "dependencies": [],
  "native_modules": [],
  "legacy_bridge_plugins": [],
  "ownership_contract": {
    "version": "business-app-contract.v1",
    "owns": [
      "business_routes",
      "business_views",
      "business_services",
      "app_styles",
      "app_assets",
      "module_registry",
      "permission_contributions",
      "navigation_contributions",
      "widget_contributions",
      "report_contributions"
    ],
    "allowed_shell_surfaces": [
      "admin",
      "operator",
      "display",
      "legacy_me"
    ],
    "must_not_own": [
      "core_primitives",
      "shell_runtime_behavior",
      "shell_wrapper_chrome",
      "acl_authorization_truth",
      "workspace_profile_policy_truth",
      "studio_runtime_truth",
      "system_tool_bypasses",
      "unrelated_app_business_logic"
    ]
  }
}
```

Existing manifests may express this baseline through current fields before the optional nested `ownership_contract` is added. The nested contract is the target explicit shape, not a migration requirement for this slice.

Business Apps should declare or document:

- App key: stable runtime identity such as `manufacturing` or `sbaio`.
- App type: `business`.
- Owner: the app owns domain meaning for its routes, views, services, styles, assets, reports, and app-level orchestration.
- Modules owned: module keys listed by manifest or app/module registry.
- Routes owned: canonical business routes under `/apps/{app}`.
- Views owned: app-level cross-module views plus module views delegated to owning modules.
- CSS/assets owned: app styles and assets under the app path; module-specific styling under the module path.
- Contributions: nav, sidebar, widgets, host-surface regions, reports, exports, operator/display data contributions, and capability catalogs.
- Permissions contributed: app and module permission keys required by capabilities.
- Allowed Shell surfaces: only declared contribution points for admin, operator, display, or documented legacy compatibility surfaces.
- Dependencies: platform, plugin, package, app, or module dependencies needed for the app to run.
- Must-not-own boundaries: Core primitives, Shell runtime behavior, Shell chrome, ACL authorization truth, Workspace Profile policy truth, Studio runtime truth, System Tool bypasses, and unrelated business domains.

## Module Declaration

A Module is an app-owned internal feature. It may own a single business entity, process, planning surface, dashboard, service capability, governance capability, or integration capability.

Minimum module manifest or documented contract fields:

```json
{
  "package_type": "module",
  "owner_app": "manufacturing",
  "module_key": "AssemblyEntries",
  "module_type": "process_execution",
  "target_maturity_level": "L3",
  "declared_capabilities": [
    "routes",
    "views",
    "forms",
    "schema",
    "migrations",
    "controllers",
    "services",
    "permissions",
    "menus",
    "widgets",
    "reports",
    "exports",
    "dependencies",
    "lifecycle_hooks",
    "localization"
  ],
  "lifecycle_contract": {
    "routes": "active_only",
    "menus": "active_only",
    "widgets": "active_only",
    "reports": "active_only",
    "dashboards": "active_only",
    "schema": "installed_or_active"
  }
}
```

Modules should declare or document:

- Module key: stable module identity in the owner app.
- Owner app: the app that owns the module's business meaning.
- Routes/views/services owned: focused module surfaces, controllers, handlers, services, templates, forms, reports, and exports.
- Data/schema ownership: migrations, required tables, entity definitions, and schema contracts when the module controls data.
- Contribution points: menus, widgets, dashboards, operator/display/admin contributions, reports, exports, lifecycle hooks, and localization.
- CSS scope: module-specific styles under the module path when the styling is not app-wide.
- Permission scope: permission keys required for module routes, actions, reports, exports, and contributions.
- Integration points: required and optional dependencies, adapters, app-level orchestration hooks, platform engines, and cross-module contracts.

## Apps Contribute, Shell Composes

Business Apps and Modules declare capability catalogs and contribution providers. Shell may compose those contributions into route-driven surfaces, but Shell does not become the owner of business meaning.

Canonical composition pipeline:

```text
App/Module-owned capability catalog
  -> ACL authorization filter
  -> Workspace Profile role-experience shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

Composition rules:

- Apps/modules own the capability, route, widget, panel, adapter, permission requirement, data meaning, and business action semantics.
- ACL owns server-side allow/deny only.
- Workspace Profile may shape presentation but must not expose denied capabilities.
- Shell owns wrapper chrome, route-driven composition, and generic rendering surfaces.
- Display surfaces are readonly and actionless.
- Operator surfaces remain confined to `/u/{username}/*`.
- Admin and app routes remain admin-wrapper territory unless the route prefix contract says otherwise.

## Boundaries Business Apps Must Not Own

Business Apps and Modules must not own:

- Core primitives or `/app` runtime law.
- Shell runtime behavior, wrapper selection, global navigation mechanics, or generic chrome.
- ACL authorization truth beyond contributing permission requirements.
- Workspace Profile policy truth beyond declaring contribution metadata consumed by policy.
- Studio runtime truth, drafts, diffs, previews, or apply workflows.
- System Tool bypasses, repair authority, or governance exception paths.
- Package lifecycle transport semantics.
- Plugin cross-cutting extension mechanics.
- Unrelated app domains or duplicate compatibility surfaces.

## Reference App Interpretation

Manufacturing, SBAIO, and Payroll are reference Business Apps. Their existing code can be used to identify platform contract needs, but changes to them should be treated as contract-boundary work unless the user explicitly requests app feature work.

Reference app work is architecture work when it:

- documents app/module ownership,
- adds read-only diagnostics,
- clarifies contribution or lifecycle contracts,
- prevents Shell/Core/Studio/ACL ownership drift, or
- proves platform boundaries with gates.

Reference app work is sample-app polish when it:

- improves feature UX without a contract boundary issue,
- adds business logic,
- moves module code for tidiness,
- duplicates routes or dashboards,
- changes runtime behavior without a platform boundary need, or
- treats a reference implementation as the product.

## Shared App Contract (Governance, No New Runtime Type)

A Shared App is a normal business Domain App that canonically owns a reusable business domain consumed by multiple Apps/product compositions. It is not a new runtime App type — `manifest type` remains `business`, loader/registry behavior is unchanged, and no `suites/` runtime is introduced.

When an App declares governance metadata `shared_contract`, the following holds for this slice:

```json
{
  "shared_contract": {
    "kind": "shared_app",
    "consumers": ["hospitality", "sbaio"]
  }
}
```

Rules:

- `kind` must be exactly `shared_app` when present.
- `consumers` must be a non-empty array of unique App keys; the Shared App must not list itself.
- Consumer keys should refer to known Apps where mechanically reasonable.
- No manifest may introduce `type: shared_app` as a runtime type; `type` stays `business` (or existing `framework/system` for non-business apps).
- **Data scope is not an App property.** A Shared App may contain entities with different scopes (e.g., canonical Party identity may be database/instance-wide, while a Party↔Company relationship may be company-specific). Scope belongs to entity/domain contracts, schemas, and query boundaries — not to the Shared App classification. Therefore `scoped_by_company`, `company_scoped`, `branch_scoped`, and `tenant_scope` are **rejected** at App level in this slice.
- Governance metadata does not change runtime behavior in this slice; it documents canonical ownership and intentional reuse via `consumers`.

No `apps/Parties` (or other Shared App) is created in this slice.

Reconciles: `docs/architecture/shared-app-extension-readiness.md`, `docs/architecture/susankhya-productization-roadmap.md`, `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`.

Slice 2 correction note: The previous `scoped_by_company: true` App-level boolean incorrectly bound entire App to company scope and conflated tenant (database/instance, via `storage/db_config.php`, `app/Core/DB.php`) with Company (organizational structure `org_companies` inside one DB). It is removed. Future Parties work will define scope per-entity (identity vs relationship), not per-App.

## App Extension Contract (Metadata on Ordinary Module)

An App Extension is a normal module owned by one App (`apps/<Owner>/modules/<Module>/plugin.json`) that contributes integration or surfaces to another canonical App without becoming the owner of the target domain.

Representation:

- Under `apps/<Owner>/modules/<Module>/` only. There is no `apps/<Owner>/extensions/` directory and no extension loader.
- Minimal metadata:

```json
{
  "ownership_type": "app_extension",
  "extension_of": "parties",
  "extension_key": "hospitality.parties",
  "required_target_version": ">=0.1.0"
}
```

Rules:

- `ownership_type: app_extension` requires `extension_of` (canonical target App key, non-empty, differs from `owner_app`).
- `extension_key` must be present, stable, and globally unique among extensions.
- Target App must exist in the repository when a real extension is introduced.
- Do not introduce a second field `target_app`; `extension_of` is the single target declaration — `target_app` is forbidden.
- `required_target_version`, if present, uses simple semver constraint syntax (e.g., `>=0.1.0`, `^1.0`, `~1.2`, `1.0.0`); invalid syntax is a gate failure.
- Dependency direction is Owner Extension → Target App; Target App must not depend back merely to satisfy the contract.
- Ordinary modules without `ownership_type` are unaffected.
- An extension does not become canonical owner of target data; no cross-app table writes except via target's service API.

Deferred (documentation only until runtime slice proves need): richer `contribution`, `data_boundary`, `lifecycle`, permission-mapping objects. Company/branch data-scope is an entity/domain-contract property (e.g., canonical identity vs relationship), not an App-level flag, and belongs with the Parties identity/relationship schema investigation — not this contract slice. No `company_id`/`branch_id`/`tenant_id` App-level flags are allowed here.

## Validation

Use the aggregate architecture gates:

```bash
scripts/architecture/run_architecture_gates.sh
```

The business app/module baseline gate is:

```bash
scripts/architecture/check_business_app_module_contracts.sh
```

The gate is read-only. It checks that reference Business Apps and their modules expose minimal ownership metadata. It must not mutate manifests, runtime state, routes, permissions, migrations, or generated artifacts.

Current diagnostics validate stable contract shape only:

- Reference Business App scan scope must remain explicit so Manufacturing and SBAIO coverage cannot silently narrow.
- App manifests must declare app identity, business type, route entrypoint, modules, routes, permissions, dependencies, native modules, and legacy bridge modules where applicable.
- App route declarations must use explicit absolute route paths and include at least one canonical `/apps/{app}` route.
- App module declarations must include stable module keys and names.
- Permission declarations must be explicit string keys and remain authorization inputs rather than presentation truth.
- Module manifests must identify `package_type`, `owner_app`, `module_key`, `module_type`, target maturity, declared capabilities, and lifecycle contract.
- Module type, maturity, lifecycle policy, and declared capability vocabulary must stay inside the documented contract set.
- Report declarations must include owner, scope, permission, lifecycle, and view metadata when reports are declared.
- The diagnostic may report counts and warnings, but it must not infer runtime completeness or replace module health checks.

Related contract baseline:

- `docs/architecture/surface-contribution-contract.md`
