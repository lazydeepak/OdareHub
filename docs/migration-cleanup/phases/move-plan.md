# Staged Move Plan

Goal: reduce root clutter safely while keeping links and process stability.

## Phase 1: Foundation (done)

- Create cleanup workspace under `docs/migration-cleanup/`.
- Capture root inventory and category map.
- Define per-domain backlog files.

## Phase 2: Studio + Experience docs (done)

- [x] Created target folders:
  - `docs/migration-cleanup/studio/`
  - `docs/migration-cleanup/experience/`
- [x] Moved Studio and Experience markdown files from root.
- [x] Updated cleanup maps and statuses.
- [x] Validated no broken markdown links from the moved set.

## Phase 3: Runtime + Refactor docs (done)

- [x] Created target folders:
  - `docs/migration-cleanup/runtime/`
  - `docs/migration-cleanup/refactor/`
- [x] Moved route hygiene and refactor reports.
- [x] Updated cleanup status maps and backlog.
- [x] Ran architecture and deployment readiness checks.

## Phase 4: Domain + Localization docs (done)

- [x] Created target folders:
  - `docs/migration-cleanup/domain/`
  - `docs/migration-cleanup/localization/`
- [x] Moved domain rollout and localization files.
- [x] Updated cleanup status maps and backlog.

## Phase 5: Root hardening (done)

- [x] Root now keeps a compact documentation surface with migration reports moved under `docs/migration-cleanup/`.
- [x] Added root migration index: `MIGRATION-CLEANUP-INDEX.md`.
- [x] Root canonical docs retained (`README.md`, `AGENTS.md`, `ARCHITECTURE.md`) plus active process files.

## Phase 6: Full-system scattered-folder walkthrough (done)

- [x] Expanded folder walkthrough beyond navigation-only cleanup.
- [x] Covered `docs`, `app`, Shell, Platform, Studio, Manufacturing, SBAIO, Generated apps, plugins, packages, public delivery output, scripts, and storage/runtime references.
- [x] Marked owner boundaries, approval gates, and next safe cleanup actions before any runtime deletion or route changes.

## Batch 1: Docs-only cleanup (done)

Goal: organize safe documentation files before any runtime, navigation,
generated-app, package, public asset, script, or storage cleanup.

Authority:

- `docs/migration-cleanup/README.md`
- `docs/migration-cleanup/maps/folder-walkthrough.md`
- this move plan

Scope:

- Documentation files only.
- Root-level docs or misplaced docs that the walkthrough already marks safe to
  move.
- Documentation links, cleanup indexes, TODO/checklist references, and
  architecture-doc references affected by the moves.

Allowed:

- Move documentation files only.
- Update documentation links and indexes.
- Update script references only when a moved document path is directly
  referenced by a validation or diagnostic script and the update is path-only.

Blocked:

- Runtime PHP behavior changes.
- Core edits.
- App, Shell, Platform, Studio, Manufacturing, SBAIO, or Generated code moves.
- Plugin, package, public, script, or storage moves.
- Generated app deletion or archive.
- Route, navigation, menu, or wrapper behavior changes.

Reference-impact proof required for each moved document:

- old path
- new path
- why the owner/location is correct
- grep/reference impact result
- references updated
- skipped references, if any

Pre-move checks:

- Search for each candidate filename and old path before moving.
- Avoid moving docs referenced by scripts unless the script reference can be
  updated safely as a path-only change.
- Skip ambiguous documents until an owner/location decision is recorded.

Validation:

```bash
git diff --check
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
git status --short
```

Commit message:

```text
docs(cleanup): organize documentation cleanup batch
```

Result:

- moved safe operator documentation into `docs/runtime/operator/`
- skipped realtime and tooling-coupled docs
- proof record: `docs/migration-cleanup/maps/batch-1-docs-cleanup.md`

## Batch 2: Navigation/composition inventory enforcement and labeling (done)

Goal: classify navigation and composition sources before any movement, deletion,
or runtime behavior change.

Scope:

- `app/Navigation/*`
- `app/Core/SidebarBuilder.php`
- `apps/Shell/sidebar.php`
- `apps/Shell/sidebar_sources/*`
- `apps/*/navigation.php`
- `apps/*/modules/*/navigation.php`
- `plugins/*/navigation.php`
- `apps/Generated/*/*/navigation.php`
- DB `menus` fallback/search references

Result:

- added diagnostic inventory labels to
  `scripts/architecture/check_navigation_composition_duplicates.sh`
- added inventory report:
  `docs/migration-cleanup/maps/batch-2-navigation-composition-inventory.md`
- no navigation files moved or deleted
- no Core behavior changed
- DB `menus` usage remains INVESTIGATE

Commit message:

```text
docs(cleanup): add navigation composition inventory diagnostic
```

## Batch 3: Generated apps archive policy and dry-run diagnostic (done)

Goal: classify generated app/module artifacts before any archive, deletion, or
runtime behavior change.

Scope:

- `apps/Generated/*`
- generated manifests, module contracts, routes, navigation, and styles
- `public/assets/apps/{generated_app}/...`
- `storage/appstudio/generated_data/*`
- `storage/appstudio/snapshots/*`
- `storage/appstudio/apps_registry.json`
- optional `core_apps` evidence when local DB access is available

Result:

- added read-only diagnostic:
  `scripts/architecture/check_generated_apps_archive_candidates.sh`
- added policy report:
  `docs/migration-cleanup/maps/batch-3-generated-apps-archive-policy.md`
- no generated apps deleted, archived, or moved
- no runtime behavior changed
- no generated app currently qualifies as DELETE_CANDIDATE or
  ARCHIVE_CANDIDATE

Commit message:

```text
docs(cleanup): add generated apps archive policy diagnostic
```

## Batch 4: Runtime registry and source-of-truth inventory (done)

Goal: classify runtime registries and source-of-truth boundaries before any
generated app archive/delete, public asset cleanup, DB menu cleanup, or route
registry migration.

Scope:

- `core_apps`
- `storage/appstudio/apps_registry.json`
- app/generated manifests
- app/module/plugin/generated navigation
- DB `menus`
- route registry/runtime route loading
- public asset registry and `public/assets/apps`
- Studio generated app lifecycle services
- package install/export registry references

Result:

- added read-only diagnostic:
  `scripts/architecture/check_runtime_registry_source_truth.sh`
- added source-of-truth report:
  `docs/migration-cleanup/maps/batch-4-runtime-registry-source-of-truth.md`
- answered generated archive/delete blocking registry questions
- DB `menus` remains RUNTIME_FALLBACK / INVESTIGATE
- no files moved, deleted, archived, or refactored
- no Core or runtime behavior changed

Commit message:

```text
docs(cleanup): add runtime registry source truth diagnostic
```

## Batch 5: DB menus runtime fallback diagnostic (done)

Goal: classify DB `menus` runtime fallback, search enrichment, and
compatibility writer paths before any navigation/menu cleanup.

Scope:

- all code references to DB `menus`
- SELECT, INSERT, UPDATE, DELETE, TRUNCATE, and ALTER usage
- `menu_key`, `parent_key`, `display_order`, `perm_key`, and source columns
- legacy `menu.php` files and `base_register_menus()` bootstraps
- relationship to `navigation.php` contracts
- relationship to `SidebarBuilder`, SearchService, app lifecycle sync, plugin
  reload, Studio/app generation, and setup/app manager flows

Result:

- added read-only diagnostic:
  `scripts/architecture/check_db_menus_runtime_fallback.sh`
- added fallback/source report:
  `docs/migration-cleanup/maps/batch-5-db-menus-runtime-fallback.md`
- classified DB `menus` as RUNTIME_FALLBACK, SEARCH_ENRICHMENT,
  COMPATIBILITY_TRUTH, LEGACY_SEED, and INVESTIGATE
- confirmed DB `menus` is not safe to delete or demote until parity and writer
  retirement plans exist
- no DB data was mutated
- no Core or runtime behavior changed

Commit message:

```text
docs(cleanup): add DB menus fallback diagnostic
```

## Batch 6: Public assets delivery-output diagnostic (done)

Goal: classify public asset source/output boundaries before any public asset,
generated asset, branding, or compatibility-shim cleanup.

Scope:

- `public/assets/apps/*`
- `public/assets/branding/*`
- root public CSS, JS, service worker, and offline assets
- app/module/generated owner CSS sources
- asset registry and publisher scripts
- references from public layouts/views and app-owned surfaces

Result:

- added read-only diagnostic:
  `scripts/architecture/check_public_assets_delivery_output.sh`
- added delivery-output/source report:
  `docs/migration-cleanup/maps/batch-6-public-assets-delivery-output.md`
- classified `public/assets/apps/...` as DELIVERY_OUTPUT or GENERATED_OUTPUT
- classified shared globals, branding assets, compatibility shims, and operator
  realtime/offline assets separately
- confirmed 24 app-scoped public CSS targets match manifest-declared owner
  source CSS
- no public assets were moved or deleted
- no Core, runtime, or CSS behavior changed

Commit message:

```text
docs(cleanup): add public assets delivery diagnostic
```

## Batch 7: Storage/runtime artifact retention policy diagnostic (done)

Goal: classify storage runtime artifacts, generated-app evidence, package
artifacts, temporary staging areas, and tracked fixtures before any storage,
generated app, public asset, package, restore, or release cleanup.

Scope:

- `storage/appstudio/apps_registry.json`
- `storage/appstudio/generated_data/*`
- `storage/appstudio/snapshots/*`
- `storage/appstudio/applies/*`
- `storage/appstudio/audit/*`
- `storage/appstudio/packages/*`
- `storage/appstudio/publish_decisions/*`
- `storage/tools/*`
- `storage/tmp/*`
- `storage/environment_snapshots/*`
- `storage/release_previews/*`
- `storage/restore_previews/*`
- tracked storage fixtures and operational byproducts

Result:

- added read-only diagnostic:
  `scripts/architecture/check_storage_runtime_retention_policy.sh`
- added retention policy report:
  `docs/migration-cleanup/maps/batch-7-storage-runtime-retention-policy.md`
- classified runtime/customer state, generated data, snapshot evidence, audit
  evidence, package artifacts, temporary artifacts, restore/release previews,
  tool state, and tracked fixtures
- confirmed only 4 storage files are tracked by git while most current storage
  contents are untracked operational artifacts
- blocked generated app storage deletion on registry, code, route/navigation,
  generated data, snapshots, applies, audit, publish decisions, packages, public
  assets, references, and backup/export checks
- no storage files were moved, deleted, archived, or mutated
- no Core or runtime behavior changed

Commit message:

```text
docs(cleanup): add storage runtime retention diagnostic
```

## Batch 8: Studio boundary priority inventory (done)

Goal: classify Studio ownership, runtime-adjacent areas, compatibility bridges,
and extraction candidates before any Studio cleanup or migration.

Scope:

- `apps/Studio/*`
- Platform/Shell Studio bridge references
- generated-app lifecycle dependencies
- Studio view extraction candidates
- Studio service restructuring candidates

Result:

- added read-only diagnostic:
  `scripts/architecture/check_studio_boundary_priority_inventory.sh`
- added boundary inventory report:
  `docs/migration-cleanup/maps/batch-8-studio-boundary-priority-inventory.md`
- classified current Studio folders, runtime-adjacent areas, legacy bridge
  points, and generated-app lifecycle boundaries
- no Studio files were moved, deleted, archived, or mutated
- no runtime behavior changed

Commit message:

```text
docs(cleanup): add Studio boundary priority inventory
```

## Batch 9: Studio runtime-adjacent separation map (done)

Goal: map runtime-adjacent Studio ownership boundaries and compatibility bridge
dependencies before any Studio runtime-adjacent migration.

Scope:

- `apps/Studio/ActionHandlers/*`
- `apps/Studio/Analytics/*`
- `apps/Studio/DataProviders/*`
- `apps/Studio/Repositories/*`
- `apps/Studio/Services/GuiStudioService.php`
- `apps/Studio/Services/StudioRuntimeBindingService.php`
- `apps/Studio/Routes/gui_studio_routes.php`
- Platform/Shell Studio bridge references

Result:

- added read-only diagnostic:
  `scripts/architecture/check_studio_runtime_adjacent_separation_map.sh`
- added runtime-adjacent separation report:
  `docs/migration-cleanup/maps/batch-9-studio-runtime-adjacent-separation-map.md`
- classified runtime-adjacent Studio areas, Platform/Shell bridge edges, and
  owner-boundary sequencing for next split slices
- no Studio, Platform, or Shell files were moved, deleted, archived, or
  behavior-mutated
- no runtime behavior changed

Commit message:

```text
docs(cleanup): add Studio runtime-adjacent separation map
```

## Batch 10: Studio host-link hardening plan (done)

Goal: classify and harden Studio host links and compatibility bridge guards
without changing runtime behavior.

Scope:

- Platform routes and host links to Studio
- Shell admin wrapper Studio exposure
- Shell style/sidebar Studio references
- Base `/ops/design-studio` compatibility surfaces and `/ops/gui-studio` bridge
  absence in Base views
- Studio manifest hooks and compatibility alias metadata
- Studio navigation contribution and guard posture
- installed/enabled guard check inventory

Result:

- added read-only diagnostic:
  `scripts/architecture/check_studio_host_link_hardening.sh`
- added host-link hardening report:
  `docs/migration-cleanup/maps/batch-10-studio-host-link-hardening-plan.md`
- classified host references by risk/owner/guard posture and mapped future
  non-breaking hardening actions
- no files were moved or deleted
- no runtime behavior changed

Commit message:

```text
docs(cleanup): add Studio host link hardening plan
```

## Batch 12: Studio services restructuring plan (done)

Goal: inventory and classify every Studio service before any service movement,
namespace migration, or runtime-adjacent split execution.

Scope:

- `apps/Studio/Services/*`
- service concern classification and target-folder planning
- generated/storage/public-asset touchpoint classification
- safe-move sequencing and do-not-move boundaries

Result:

- added read-only diagnostic:
  `scripts/architecture/check_studio_services_restructuring_plan.sh`
- added restructuring plan report:
  `docs/migration-cleanup/maps/batch-12-studio-services-restructuring-plan.md`
- classified all Studio services into governance/registry/composition/analysis/
  execution/runtime-adjacent bridge categories
- explicitly identified generated/storage writer services and compatibility
  bridge services
- defined first safe future move batch as registry-only service moves
- no files were moved, renamed, or deleted
- no runtime behavior changed

Commit message:

```text
docs(cleanup): add Studio services restructuring plan
```

## Batch 13: Studio Tool Lifecycle Contract (done)

Goal: define Studio Tool lifecycle governance contract and terminology without
runtime behavior changes or Studio feature implementation.

Scope:

- Studio Tool vs Business Module distinction
- tool manifest contract baseline
- instance-level tool enable/disable policy baseline
- role/permission, environment, risk, execution, and visibility gates
- no hidden runtime truth rule

Result:

- added architecture contract:
  `docs/architecture/studio-tool-lifecycle-contract.md`
- added Batch 13 map:
  `docs/migration-cleanup/maps/batch-13-studio-tool-lifecycle-contract.md`
- added read-only diagnostic:
  `scripts/architecture/check_studio_tool_lifecycle_contract.sh`
- updated Studio operating contract baseline with lifecycle-contract reference
- no files were moved
- no Core edits
- no runtime behavior changed
- no Studio features were built

Commit message:

```text
docs(architecture): add Studio tool lifecycle contract
```

## Batch 14: Studio customization tool boundary contract (done)

Goal: define customization-tool ownership and governance boundaries using Batch
13 lifecycle contract authority.

Scope:

- customization tools as Studio Tools (not System Tools)
- System Tools validation/rebuild/repair/audit responsibility
- Shell runtime rendering responsibility for approved resolved contracts
- Platform permission and instance-policy responsibility
- public asset delivery-output boundary
- storage snapshot/audit/history boundary (no hidden design truth)
- future tool contracts for ThemeTool/CssTool/MenuEditor/BrandingTool/FontTool

Result:

- added architecture contract:
  `docs/architecture/studio-customization-tools-contract.md`
- added Batch 14 map:
  `docs/migration-cleanup/maps/batch-14-studio-customization-tools-contract.md`
- added read-only diagnostic:
  `scripts/architecture/check_studio_customization_tools_contract.sh`
- updated lifecycle contract cross-reference to customization-tools contract
- no files were moved
- no Core edits
- no runtime behavior changed
- no Studio features were built

Commit message:

```text
docs(architecture): add Studio customization tools contract
```

## Batch 14: GUI Studio view separation plan (done)

Goal: classify `apps/Studio/Views/gui_studio.php` into safe extraction
boundaries before any partial extraction or runtime-facing view migration.

Scope:

- `apps/Studio/Views/gui_studio.php`
- `apps/Studio/Views/partials/*`
- `apps/Studio/Controllers/*`
- `apps/Studio/Routes/gui_studio_routes.php`
- `apps/Studio/Services/GuiStudioService.php`
- JS/CSS assets used by `gui_studio.php`

Result:

- added Batch 14 GUI map:
  `docs/migration-cleanup/maps/batch-14-gui-studio-view-separation-plan.md`
- added read-only diagnostic:
  `scripts/architecture/check_gui_studio_view_separation_plan.sh`
- classified `gui_studio.php` sections as PAGE_SHELL/LIBRARY_PANEL/
  EDITOR_PANEL/ANALYZE_PANEL/CHANGES_PANEL/APPLY_PANEL/STATUS_PANEL/
  GENERATED_APP_PANEL/INLINE_SCRIPT/INLINE_STYLE/TOOL_SPECIFIC_UI/
  KEEP_COMPAT/EXTRACT_CANDIDATE/DO_NOT_MOVE/INVESTIGATE
- recorded first no-behavior-change extraction slice recommendation for Batch 15
- no files were moved
- no partials were extracted in this batch
- no runtime behavior changed
- no Core edits
- no Studio features were built

Commit message:

```text
docs(cleanup): add GUI Studio view separation plan
```

## Batch 20: GUI Studio CSS JS separation plan (done)

Goal: classify remaining inline CSS/JS ownership and extraction safety in
`apps/Studio/Views/gui_studio.php` before any CSS/JS movement.

Scope:

- `apps/Studio/Views/gui_studio.php`
- inline `<style>` and `<script>` blocks
- `apps/Studio/assets/*`
- `public/assets/apps/studio*` (if present)
- style/script references in Studio routes/controllers/views

Result:

- added Batch 20 map:
  `docs/migration-cleanup/maps/batch-20-gui-studio-css-js-separation-plan.md`
- added read-only diagnostic:
  `scripts/architecture/check_gui_studio_css_js_separation_plan.sh`
- classified style/script areas as INLINE_STYLE/INLINE_SCRIPT/PASSIVE_STYLE/
  BEHAVIOR_SCRIPT/STATEFUL_SCRIPT/DOM_MUTATION/LOCAL_STORAGE/APPLY_FLOW/
  GENERATED_APP_FLOW/EXTRACT_CANDIDATE/DO_NOT_MOVE/INVESTIGATE
- documented inline style/script block counts and first CSS/JS no-behavior-change
  extraction candidates
- no CSS/JS files moved
- no inline scripts extracted
- no runtime behavior changed
- no Core edits
- no Studio features built

Commit message:

```text
docs(cleanup): add GUI Studio CSS JS separation plan
```

## Batch 21: first no-behavior-change GUI Studio CSS extraction (done)

Goal: execute the first passive CSS extraction from `gui_studio.php` under
Batch 20 authority with no runtime behavior change.

Scope:

- `apps/Studio/Views/gui_studio.php`
- `apps/Studio/styles/gui_studio.css`
- Batch map and migration tracker docs

Result:

- extracted exactly one passive inline `<style>` block from
  `apps/Studio/Views/gui_studio.php`
- moved CSS content as-is into Studio-owned source asset:
  `apps/Studio/styles/gui_studio.css`
- preserved page context by linking stylesheet at the same anchor location:
  `/assets/apps/studio/styles/gui_studio.css`
- no JS extraction
- no route/service/controller changes
- no Core edits
- added execution map:
  `docs/migration-cleanup/maps/batch-21-first-gui-studio-passive-css-extraction.md`

Commit message:

```text
refactor(studio): extract first GUI Studio passive CSS
```

## Guardrails

- Keep moves small and category-scoped.
- Do not move core execution files (`composer.json`, `index.php`, scripts).
- Run checks after each phase:
  - `bash scripts/architecture/run_architecture_gates.sh`
  - `bash scripts/system/check_deployment_readiness.sh`
