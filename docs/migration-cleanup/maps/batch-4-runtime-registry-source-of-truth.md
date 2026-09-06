# Batch 4 Runtime Registry Source-Of-Truth Inventory

Status: executed as read-only diagnostic and documentation only.

No files were moved, deleted, archived, or refactored. No runtime behavior was
changed. Core behavior remains untouched.

## Architecture Basis

- Core is locked.
- Shell composes runtime surfaces.
- Apps, modules, plugins, and generated artifacts contribute metadata.
- Studio may create/generated/apply artifacts through governance, but Studio
  working state is not runtime truth for owner-owned business capabilities.
- Public assets are delivery output, not source truth.
- Generated app archive/delete decisions are blocked until registry truth is
  clear.

## Diagnostic Script

- script: `scripts/architecture/check_runtime_registry_source_truth.sh`
- mode: read-only diagnostic
- current result: pass

## Direct Answers

1. Is `core_apps` authoritative for system/business apps only?
   - Current answer: yes for installed system/business app lifecycle state in
     this checkout.
   - Evidence: generated app keys are not registered in `core_apps`; first-class
     installed app keys are system/business/framework apps such as Shell,
     Platform, Studio, Manufacturing, SBAIO, and Procurement.
   - Label: SOURCE_OF_TRUTH for installed app lifecycle; not sufficient for
     generated Studio app archive/delete decisions.

2. Is `storage/appstudio/apps_registry.json` authoritative for generated Studio apps?
   - Current answer: yes for Studio-generated app enablement/publish metadata.
   - Evidence: generated apps such as `inventory_app`, `manufacturing_app`,
     `manufacturing_studio`, `sample_app`, and `lifecycle_app` are enabled
     there while absent from `core_apps`.
   - Label: SOURCE_OF_TRUTH for Studio-generated app lifecycle evidence.

3. Is DB `menus` still source truth or only fallback/search/runtime enrichment?
   - Current answer: INVESTIGATE.
   - Evidence: `app/Core/SidebarBuilder.php` reads `menus` as a runtime fallback,
     `app/Core/SearchService.php` reads it for search, and lifecycle code may
     delete app-sourced menu rows.
   - Label: RUNTIME_FALLBACK until a deeper migration confirms whether any
     remaining path treats it as canonical source truth.

4. Are public assets delivery output or source truth?
   - Current answer: delivery output/cache.
   - Evidence: registered CSS publisher copies owner CSS into
     `public/assets/apps/...`, and asset docs explicitly state public assets are
     not source truth.
   - Label: CACHE_OR_OUTPUT.

5. Which registry must be checked before generated app archive/delete?
   - Required checks: `storage/appstudio/apps_registry.json`, generated
     manifests/modules/routes/navigation, `storage/appstudio/generated_data`,
     `storage/appstudio/snapshots`, public assets, route/runtime loaders,
     scripts, docs, and `core_apps`.
   - Practical decision: `storage/appstudio/apps_registry.json` is the primary
     generated-app lifecycle registry; `core_apps` is still checked to avoid
     deleting anything that became first-class installed app state.

## Source Inventory

| Source | Current role | Owner | Label | Readers | Writers | Cleanup risk | Allowed future action | Blocked future action |
|---|---|---|---|---|---|---|---|---|
| DB table `core_apps` | installed system/business/framework app lifecycle registry | Core/App lifecycle governance | SOURCE_OF_TRUTH | app services, Platform/Base governance, deployment readiness, ACL/profile services | app install/lifecycle services | high | read-only diagnostics, lifecycle-governed updates | direct cleanup deletion; treating as generated-app truth by itself |
| `storage/appstudio/apps_registry.json` | Studio-generated app registry | Studio governance | SOURCE_OF_TRUTH | Studio generated app loaders, generated archive diagnostic | Studio apply/publish flows | high | read-only diagnostics; governed Studio lifecycle writes | deleting generated apps without checking it |
| `apps/*/manifest.json` | owner app manifest declarations | owning app | SOURCE_OF_TRUTH | app discovery, CSS publisher, system app gates, lifecycle services | app owner / governed lifecycle | high | owner-approved manifest updates | moving ownership into Shell/Core |
| `apps/Generated/*/manifest.json` | generated app manifest declarations | Studio-generated artifact owner | COMPATIBILITY_TRUTH | generated loaders, CSS publisher, diagnostics | Studio apply/publish flows | high | preserve; classify through archive policy | delete/archive without generated registry and storage checks |
| `apps/*/navigation.php` | app navigation contribution | owning app | SOURCE_OF_TRUTH | SidebarBuilder compatibility path, route authority, navigation diagnostics | app owner | medium | owner-owned contribution edits | Shell/Core consolidation |
| `apps/*/modules/*/navigation.php` | module navigation contribution | owning app/module | SOURCE_OF_TRUTH | SidebarBuilder compatibility path, route authority, navigation diagnostics | module owner | medium | module-owned contribution edits | moving into ACL/Shell as presentation truth |
| `plugins/*/navigation.php` | plugin navigation contribution | owning plugin | SOURCE_OF_TRUTH | SidebarBuilder compatibility path, navigation diagnostics | plugin owner | medium | plugin-owned contribution edits | cross-owner business links |
| DB table `menus` | runtime fallback/search/enrichment input | legacy/Core compatibility | RUNTIME_FALLBACK / INVESTIGATE | SidebarBuilder, SearchService, Base bootstrap | lifecycle/bootstrap/app runtime registry code | high | read-only investigation and migration plan | deletion or truth reassignment before classification |
| route files `apps/*/routes.php`, module `routes.php`, plugin `routes.php` | route registration | owner app/module/plugin | SOURCE_OF_TRUTH | public router/runtime route authority | owner app/module/plugin | high | owner-scoped route edits | route behavior changes in cleanup batch |
| `public/assets/apps/...` | published CSS delivery output | generated from owner CSS | CACHE_OR_OUTPUT | browser/runtime asset loading, asset integrity gate | registered CSS publisher | medium | regenerate from owner CSS | hand-editing as source truth |
| Studio generated lifecycle services | generated apply/publish/registry workflow | Studio | SOURCE_OF_TRUTH for generated lifecycle workflow | Studio controllers/services/diagnostics | Studio governed operations | high | read-only diagnostics; governed Studio changes | moving Studio ownership into Platform/Base/Core |
| package install/export registries/artifacts | lifecycle transport metadata | Packages / Studio package flows | COMPATIBILITY_TRUTH / CACHE_OR_OUTPUT | package manager, Studio package services | package/export/install flows | medium | retention policy and package diagnostics | treating package as feature owner |

## Registry Coupling Notes

- `core_apps` should be checked before app deletion, but generated Studio apps
  may be absent there and still active through `storage/appstudio/apps_registry.json`.
- `storage/appstudio/apps_registry.json` must be checked before generated app
  archive/delete because it records enabled generated app state and publish
  metadata.
- Generated app manifests, routes, navigation files, provider data paths,
  snapshots, and public assets are all coupling evidence.
- `public/assets/apps/...` must be regenerated from owner source CSS, never
  edited as source truth.
- DB `menus` remains the largest unresolved runtime source-of-truth question.

## Future Safe Work

1. Investigate DB `menus` usage separately and decide whether it is still
   source truth, compatibility fallback, or search-only enrichment.
2. Keep generated app archive/delete blocked on `storage/appstudio/apps_registry.json`
   and storage provenance checks.
3. Keep public asset cleanup tied to owner CSS and the registered publisher.
4. Keep package artifacts governed as lifecycle transport, not feature ownership.
5. Do not promote generated apps into `core_apps` or remove them from
   `storage/appstudio/apps_registry.json` in cleanup batches without a Studio
   lifecycle decision.
