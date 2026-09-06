# Full-System Migration Cleanup Walkthrough

Repository-wide migration cleanup map for the scattered folders that still carry
legacy runtime references, generated artifacts, compatibility routes, and
cleanup reports.

This is a walkthrough and sequencing document only. It does not authorize direct
runtime deletion, Core edits, or compatibility-route removal.

## Scope

Requested areas covered:

- `docs`
- `app`
- `apps/Shell`
- `apps/Platform`
- `apps/Studio`
- `apps/Manufacturing`
- `apps/SBAIO`
- `apps/Generated`
- `plugins`
- `packages`
- `public`
- `scripts`
- `storage/runtime` references

Note: `storage/runtime` is not present as a folder in this checkout. Runtime
artifact references are spread through `storage/appstudio`, `storage/tools`,
`storage/tmp`, snapshot, release, restore, export, and package folders.

## Architecture Basis

- Broken law being controlled: no hidden duplicate source of truth.
- Owner of this map: documentation/process governance under
  `docs/migration-cleanup`.
- Why this is not sample-app polish: the scan covers platform contracts,
  compatibility surfaces, generated assets, lifecycle tooling, and storage
  artifacts across the whole system.
- Validation that proves this pass: diff-only documentation changes plus
  architecture and deployment readiness gates.

## Folder Metrics

Format: `folder | directories | files | markdown_files | migration_debt_files`

- `docs | 17 | 129 | 128 | 101`
- `app | 18 | 134 | 1 | 43`
- `apps/Shell | 11 | 78 | 1 | 24`
- `apps/Platform | 22 | 87 | 1 | 32`
- `apps/Studio | 32 | 97 | 1 | 14`
- `apps/Manufacturing | 123 | 485 | 2 | 50`
- `apps/SBAIO | 62 | 146 | 2 | 49`
- `apps/Generated | 46 | 92 | 0 | 0`
- `plugins | 23 | 124 | 2 | 44`
- `packages | 7 | 8 | 1 | 0`
- `public | 52 | 89 | 0 | 13`
- `scripts | 6 | 44 | 9 | 25`
- `storage/runtime | missing | missing | missing | n/a`
- `storage | 33 | 168 | 1 | 0`

`migration_debt_files` counts files matching legacy or migration-control terms
such as `SidebarBuilder`, `ShellCompositionService`, `/me`, `/ops`,
`ResolvedExperience`, `user_surface_overrides`, `user_dashboard_assignments`,
`legacy`, `migration`, `cleanup`, `TODO`, or `FIXME`.

## Cross-Cutting Clusters

### Legacy Wrapper And Route Vocabulary

Observed in:

- `docs/access-control-view-architecture.md`
- `docs/runtime/operator/operator-layer-architecture.md`
- `docs/unified-operational-landing-strategy.md`
- `apps/Shell/Services/WorkspaceWrapperRegistry.php`
- `apps/Shell/routes.php`
- `apps/Platform/navigation.php`
- `apps/Manufacturing/manifest.json`
- `plugins/Base/Controllers/RoleDashboardsController.php`
- `plugins/Base/Views/ops/*`
- `public/views/layouts/*`

Cleanup rule:

- Do not remove `/ops` or `/me` references as a text cleanup.
- Classify each reference as one of:
  - canonical admin route under `/admin/{username}`;
  - admin-wrapper governance route under `/ops/*`;
  - legacy alias retained for compatibility;
  - stale documentation that can be updated safely.

Next safe action:

- Create a focused stale-doc pass for documents that still describe `/me` as a
  primary workspace.
- Keep runtime route changes behind the admin route and migration-debt gates.

### Experience Composition And ACL Migration Debt

Observed in:

- `docs/experience-composition-architecture-plan.md`
- `docs/access-control-view-architecture.md`
- `plugins/Base/Controllers/RoleDashboardsController.php`
- `plugins/Base/Services/ResolvedExperienceConsumerService.php`
- `plugins/Base/Services/UserSurfaceOverrideService.php`
- `plugins/Base/migrations/004_dashboard_assignment_model.sql`
- `plugins/Base/migrations/009_user_surface_overrides.sql`
- `apps/Shell/Composers/OperatorSurfaceComposer.php`
- `apps/Shell/Services/OperatorLayerSidebarService.php`

Cleanup rule:

- ACL fields such as `me_dashboard_blocks`, `me_plugin_cards`,
  `display_surfaces`, `operator_views`, and `workspace_profile_key` are
  transitional carriers, not a new source of composition truth.
- Do not add more ACL presentation fields.
- Prefer read-only diagnostics and parity checks before renderer rewrites.

Next safe action:

- Add or extend documentation that marks each remaining ACL presentation field
  as compatibility debt with a target resolved-experience owner.

### Studio Extraction And Bridge Debt

Observed in:

- `apps/Studio/Routes/gui_studio_routes.php`
- `apps/Studio/Services/GuiStudioService.php`
- `apps/Studio/Services/StudioExperienceGovernanceService.php`
- `apps/Studio/Views/gui_studio.php`
- `apps/Studio/manifest.json`
- `plugins/Base/Views/ops/design_studio.php`
- `plugins/Base/Views/ops/design_studio_edit.php`
- `docs/runtime/STUDIO-GUI-STUDIO-EXTRACTION-PLAN.md`
- `docs/runtime/STUDIO-TOOL-SEPARATION-PLAN.md`

Cleanup rule:

- New Studio behavior belongs under `apps/Studio` and `/apps/studio/...`.
- Existing `/ops/design-studio*` and `/ops/gui-studio` surfaces are
  compatibility bridges until extracted or redirected by an approved Studio
  boundary change.
- Platform and Base must not absorb new Studio builder/editor logic.

Next safe action:

- Build an extraction matrix of each `/ops` Studio bridge, its `apps/Studio`
  equivalent, and whether it is entry-only, render-owning, or mutating.

### Generated Apps And Generated Data

Observed in:

- `apps/Generated/*`
- `apps/Studio/Services/GuiStudioService.php`
- `apps/Studio/Services/StudioGovernanceService.php`
- `storage/appstudio/generated_data/*`
- `storage/appstudio/snapshots/*`
- `public/index.php`
- `public/router.php`
- `scripts/architecture/check_asset_registry_integrity.sh`

Cleanup rule:

- `apps/Generated` has no text hits for legacy route terms, but it is coupled to
  Studio apply/publish flows and generated data storage.
- Do not delete `apps/Generated/tmp` or sample generated apps without an archive
  policy and dry-run audit.

Next safe action:

- Continue from `folder-pass-apps-generated.md`: define active, inactive,
  archival, and temporary artifact classes before any move or deletion.

### Public Assets And Generated Delivery Output

Observed in:

- `public/index.php`
- `public/router.php`
- `public/assets/apps/*`
- `public/views/layouts/header.php`
- `public/views/layouts/admin-wrapper-open.php`
- `public/views/layouts/sidebar.php`

Cleanup rule:

- `public/assets/apps/...` is delivery output, not source truth.
- Do not hand-edit generated CSS or view output.
- Shell-owned public layout references must preserve wrapper confinement until
  route contracts are changed through gates.

Next safe action:

- If a CSS cleanup is needed, update owner CSS first and publish registered CSS
  through the asset script.

### Storage And Runtime Artifact References

Observed in:

- `storage/appstudio/applies`
- `storage/appstudio/audit`
- `storage/appstudio/generated_data`
- `storage/appstudio/packages`
- `storage/appstudio/publish_decisions`
- `storage/appstudio/snapshots`
- `storage/environment_snapshots`
- `storage/release_previews`
- `storage/restore_previews`
- `storage/tmp`
- `storage/tools/gui_studio`

Cleanup rule:

- Treat storage as runtime/customer artifact territory unless a documented
  retention policy says otherwise.
- Snapshot, package, restore, and audit folders may be evidence for generated
  artifacts; they are not ordinary source clutter.

Next safe action:

- Create a storage retention policy map before deleting runtime artifacts.
- Explicitly distinguish tracked fixture-like artifacts from local operational
  byproducts.

## Per-Folder Walkthrough

### `docs`

Current shape:

- Strong architecture, contract, runtime, identity, and migration-cleanup
  subfolders exist.
- Root-level docs still include operator, access-control, route, Studio, and
  app-boundary history.

Cleanup stance:

- Safe for documentation-only cleanup.
- Keep canonical charter and experience plan stable.
- Move root docs in topic batches only when links are updated in the same diff.

Next pass:

- Split stale historical docs from active contract docs.
- Update docs that still call `/me` the primary operator workspace.

### `app`

Current shape:

- Core lock applies to `app/Core`.
- Legacy navigation still exists in `app/Navigation`.
- `app/Core/SidebarBuilder.php` remains frozen legacy composition debt.
- Lifecycle services reference packages, snapshots, restore previews, and setup
  flows.

Cleanup stance:

- No edits without explicit Core approval when the change touches `app/Core`.
- `app/Navigation` cleanup is only one part of this pass and should not be
  treated as the whole migration.

Next pass:

- Document which `app/Navigation` sources are runtime truth, compatibility
  truth, or dead inventory before code changes.
- Keep Core runtime primitives locked unless a gate proves a Core-owned fix.

### `apps/Shell`

Current shape:

- Owns wrappers, operator/admin/display orchestration, and runtime composition.
- Contains `OperatorSurfaceComposer`, `AdminSurfaceComposer`, wrapper registry,
  display/operator views, and Shell CSS.
- Shell CSS and public asset delivery need ownership discipline.

Cleanup stance:

- Shell may fix wrapper/composition boundaries.
- Shell must not absorb business app page styling or module capability meaning.

Next pass:

- Audit Shell route and composer references to `/me`, `/ops`, `ResolvedExperience`,
  and generated URLs.
- Prefer diagnostics and parity checks before changing render selection.

### `apps/Platform`

Current shape:

- Owns governance/admin capabilities.
- Emits many `/ops/*` governance routes.
- Contains analytics views that link to `/ops/analytics*`.

Cleanup stance:

- `/ops/*` can be valid admin-wrapper territory.
- Do not recategorize governance routes as operator surfaces.

Next pass:

- Separate canonical governance `/ops` links from migration aliases.
- Review hardcoded fallback labels in touched UI only when making Platform UI
  changes.

### `apps/Studio`

Current shape:

- Owns Studio tooling, governance, generated app apply/publish, and GUI Studio
  service logic.
- Manifest still references `/ops/gui-studio`.
- Services write to `apps/Generated` and `storage/appstudio`.

Cleanup stance:

- Studio extraction work belongs here.
- Do not add new Studio logic to Platform, Base, Core, or unrelated `/ops`
  surfaces.

Next pass:

- Map every legacy `/ops` Studio bridge to a target `/apps/studio/...` route.
- Keep create vs upgrade and Analyze / Changes / Apply separation intact.

### `apps/Manufacturing`

Current shape:

- Reference business app with module-owned capabilities.
- Contains compatibility routes and manifest `/ops/*` entries.
- Some routes redirect to `/me`, which is compatibility debt and must be
  treated carefully.

Cleanup stance:

- Manufacturing cleanup is valid only when it fixes platform contract boundary
  drift, not sample-app polish.
- Operator-facing needs should go through `/u/{username}/*` adapters/views.

Next pass:

- Classify each Manufacturing `/ops` route as admin governance, legacy
  compatibility, or operator candidate needing `/u/*` treatment.

### `apps/SBAIO`

Current shape:

- Reference business app with workbook, payroll, attendance, staff, schedule,
  and timecard migration history.
- Contains module AGENTS guidance for host-surface contributions and legacy
  `/me` composition.

Cleanup stance:

- Do not polish SBAIO business screens unless the issue proves an app/module
  ownership or composition boundary bug.

Next pass:

- Keep workbook migration docs and module ownership docs aligned.
- Avoid turning `/me`, `/admin/*`, `/u/*`, or `/displays/*` into duplicate SBAIO
  workflow implementations.

### `apps/Generated`

Current shape:

- Generated app folders include `hardening_app`, `inventory_app`,
  `lifecycle_app`, `manufacturing_app`, `manufacturing_studio`, `rollback_app`,
  `runtime`, `sample_app`, and `tmp`.
- No direct legacy term hits in the folder scan, but generated providers depend
  on `storage/appstudio/generated_data`.

Cleanup stance:

- Generated folders are live tooling artifacts until an archive policy proves
  otherwise.

Next pass:

- Build an active/inactive matrix from manifests, route registration, data
  files, snapshots, and app registry evidence.

### `plugins`

Current shape:

- Base and AdminTools carry most ACL/dashboard assignment, `/ops`, and
  compatibility UI debt.
- Base contains `ResolvedExperience` services and transitional
  `user_dashboard_assignments` handling.

Cleanup stance:

- Base compatibility debt may remain only as documented migration debt.
- Do not add new active usage of frozen legacy Shell/Base/Core composition
  artifacts.

Next pass:

- Split Base compatibility surfaces into keep, bridge, extract, and retire
  candidates.
- Any ACL shape change must follow the experience composition plan.

### `packages`

Current shape:

- Low file count, mostly lifecycle artifact folders.
- No direct migration-debt text hits in this scan.

Cleanup stance:

- Treat as lifecycle mechanism territory.
- Do not delete package folders without checking install/export/import flows and
  storage package references.

Next pass:

- Add a package retention and quarantine note only after lifecycle services are
  checked.

### `public`

Current shape:

- Front controller and router participate in `/u`, `/admin`, displays, apps,
  and generated routing.
- Public layouts still include compatibility comments for `/me` and `/ops`.
- Public assets include generated delivery output.

Cleanup stance:

- Public route preprocessing is runtime-sensitive.
- Asset cleanup must flow from owner CSS and registered publishing.

Next pass:

- Treat public route changes as architecture-gated runtime changes.
- Keep public asset pruning separate from source CSS cleanup.

### `scripts`

Current shape:

- Architecture gates, system deployment readiness checks, runtime repro scripts,
  and asset publishing are already grouped.
- Migration-debt gate scripts are the preferred safety net for cleanup work.

Cleanup stance:

- Scripts are System Tools foundation and should remain read-only where gates
  claim read-only behavior.

Next pass:

- Add focused dry-run audit scripts only after a cleanup policy exists.
- Do not make gates mutate runtime state.

### `storage` And `storage/runtime` References

Current shape:

- `storage/runtime` is absent.
- Runtime-like state exists under `storage/appstudio`, environment snapshots,
  release previews, restore previews, exports, package artifacts, logs, and temp
  folders.

Cleanup stance:

- Storage cleanup requires retention policy and artifact ownership first.
- Studio-generated data and snapshots may be needed for provenance and rollback.

Next pass:

- Inventory tracked vs local-only storage artifacts.
- Define retention classes: audit/provenance, generated data, package/export,
  restore/release preview, temp, and local logs.

## Recommended Cleanup Sequence

1. Documentation truth pass: update stale `/me` and old operator wording in
   docs without changing runtime behavior.
2. Studio bridge matrix: map legacy `/ops` Studio surfaces to `apps/Studio`
   ownership and target routes.
3. ACL/resolved-experience debt map: identify each transitional field, current
   consumer, target owner, and retirement condition.
4. Generated artifact policy: define archive and dry-run audit criteria for
   `apps/Generated` plus `storage/appstudio/generated_data`.
5. Shell wrapper parity pass: audit Shell composer and wrapper decisions against
   `/admin`, `/u`, and `/displays` rules.
6. Platform/Base compatibility split: classify `/ops` governance routes,
   bridges, and stale aliases.
7. Reference-app route classification: review Manufacturing and SBAIO only for
   platform boundary cleanup.
8. Public asset/source split: make sure asset changes originate from owner CSS.
9. Storage retention policy: define before deletion.
10. Only then consider code cleanup slices, each with owner AGENTS files,
    architecture-law statement, and gates.

## Blockers And Approval Gates

- Any `app/Core/*` change requires explicit Core approval.
- Runtime route changes must pass admin route, operator confinement, display
  readonly, and migration-debt gates.
- Studio runtime dependency changes must pass Studio boundary gates.
- Generated artifact deletion requires archive policy and dry-run audit.
- Storage deletion requires retention policy and provenance review.

## Validation Commands

Preferred validation for cleanup documentation and future cleanup slices:

```bash
git diff --check
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
git status --short
```
