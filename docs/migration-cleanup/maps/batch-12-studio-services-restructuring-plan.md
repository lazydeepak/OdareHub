# Batch 12 Studio Services Restructuring Plan

Status: planning and read-only diagnostic only.

No files were moved, renamed, or deleted.
No classes were renamed.
No runtime behavior was changed.
No Core files were edited.
No Studio feature expansion was implemented.

Authority:

- Batch 8: `docs/migration-cleanup/maps/batch-8-studio-boundary-priority-inventory.md`
- Batch 9: `docs/migration-cleanup/maps/batch-9-studio-runtime-adjacent-separation-map.md`
- Batch 10: `docs/migration-cleanup/maps/batch-10-studio-host-link-hardening-plan.md`
- Batch 11: optional host-link hardening implementation (`fix(studio): harden optional host links`)

## Architecture Basis

- Studio services must be decomposed by ownership concern before any physical move.
- Runtime-adjacent and compatibility bridge services must stay in place until contract split parity is proven.
- Generated artifact writers and storage evidence writers remain governed boundaries.
- This batch is classification-only for safe sequencing.

## Diagnostic Script

- script: `scripts/architecture/check_studio_services_restructuring_plan.sh`
- mode: read-only diagnostic
- current result: pass (expected after this batch)

## Service Inventory And Classification

Legend for `Classification tags`:

- `GOVERNANCE`
- `REGISTRY`
- `COMPOSITION`
- `ANALYSIS`
- `EXECUTION`
- `GENERATION`
- `PUBLISHING`
- `SNAPSHOT`
- `VALIDATION`
- `RUNTIME_ADJACENT`
- `COMPAT_BRIDGE`
- `INVESTIGATE`
- `DO_NOT_MOVE`

| Service | Current file | Current responsibility | Classification tags | Target folder (future) | Dependencies (obvious) | Runtime risk | Safe to move later | Namespace/autoload must change if moved | Tests/gates must be updated if moved |
|---|---|---|---|---|---|---|---|---|---|
| AppStudioRegistryService | `apps/Studio/Services/AppStudioRegistryService.php` | Builds Studio library tree/index for apps/plugins/generated and route-authority summaries | `REGISTRY`, `ANALYSIS` | `apps/Studio/Services/Registry/AppStudioRegistryService.php` | `App\\Core\\RouteRuntimeAuthority`, filesystem scans under `apps/` and `plugins/` | Medium | Yes (after consumers are redirected) | Yes | Yes (`check_studio_services_restructuring_plan.sh`, any path-based diagnostics) |
| GuiStudioService | `apps/Studio/Services/GuiStudioService.php` | Monolithic compile/apply/rollback orchestration; generated artifact construction; snapshot/package/registry persistence | `EXECUTION`, `GENERATION`, `PUBLISHING`, `SNAPSHOT`, `VALIDATION`, `RUNTIME_ADJACENT`, `DO_NOT_MOVE` | `apps/Studio/Services/Execution/GuiStudioService.php` (after decomposition, not before) | `StudioGovernanceService`, generated manifests/routes/nav/styles, storage/appstudio evidence files, package flows | High | No (must be split first) | Yes | Yes (architecture gates + deployment readiness + service diagnostics) |
| HostSurfaceContributionService | `apps/Studio/Services/HostSurfaceContributionService.php` | Contributes Studio host-surface quick links/actions to host surfaces | `COMPOSITION`, `COMPAT_BRIDGE` | `apps/Studio/Services/Composition/HostSurfaceContributionService.php` | Host surface contribution request context, localization helper `t()` | Low | Yes | Yes | Yes (host-link diagnostics) |
| StudioDataContractService | `apps/Studio/Services/StudioDataContractService.php` | Extracts/validates fields, bindings, data sources for imported or generated bundles | `ANALYSIS`, `VALIDATION` | `apps/Studio/Services/Analysis/StudioDataContractService.php` | View/module manifest structures | Low | Yes | Yes | Low (mostly unit/contract checks if present) |
| StudioDependencyGraphService | `apps/Studio/Services/StudioDependencyGraphService.php` | Builds dependency graph between fields/views/dashboards/workflows | `ANALYSIS` | `apps/Studio/Services/Analysis/StudioDependencyGraphService.php` | Data contract payload from Studio bundle pipeline | Low | Yes | Yes | Low |
| StudioExperienceGovernanceService | `apps/Studio/Services/StudioExperienceGovernanceService.php` | Analyze/changes/apply-plan governance gate for workspace profile and user override proposals | `GOVERNANCE`, `VALIDATION`, `EXECUTION`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Governance/StudioExperienceGovernanceService.php` | ResolvedExperience payloads, persister callbacks | Medium | Later (after apply contract split proof) | Yes | Yes (experience and architecture checks) |
| StudioGovernanceService | `apps/Studio/Services/StudioGovernanceService.php` | G1-G4 governance policy, preflight guardrails, publish decisions, audit snapshots | `GOVERNANCE`, `VALIDATION`, `PUBLISHING`, `SNAPSHOT`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Governance/StudioGovernanceService.php` | `GuiStudioService` constants/workflow, `storage/appstudio/audit`, `storage/appstudio/publish_decisions` | Medium-High | Later (after governance write-path adapters exist) | Yes | Yes (governance, architecture, deployment checks) |
| StudioGovernedToolRegistryService | `apps/Studio/Services/StudioGovernedToolRegistryService.php` | Discovers tool manifests from `apps/Studio/Tools/*/manifest.php` and normalizes them | `REGISTRY` | `apps/Studio/Services/Registry/StudioGovernedToolRegistryService.php` | Studio tool manifests on disk | Low | Yes | Yes | Low |
| StudioNavCandidateProviderService | `apps/Studio/Services/StudioNavCandidateProviderService.php` | Builds nav-link candidates from route authority diagnostics and route contracts | `ANALYSIS`, `COMPOSITION`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Composition/StudioNavCandidateProviderService.php` | `App\\Core\\RouteRuntimeAuthority` diagnostics | Medium | Later (after route-authority coupling is isolated) | Yes | Yes (nav/route diagnostics) |
| StudioNavLinkingService | `apps/Studio/Services/StudioNavLinkingService.php` | Analyze/apply nav URL linking writes for generated navigation contracts | `COMPOSITION`, `EXECUTION`, `GENERATION`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Execution/StudioNavLinkingService.php` | Generated `navigation.php` files in `apps/Generated`, plan fingerprint gates | High | Later (after generated-write facade extraction) | Yes | Yes (generated-artifact and architecture diagnostics) |
| StudioNotificationService | `apps/Studio/Services/StudioNotificationService.php` | Studio notification table lifecycle and user notification CRUD | `EXECUTION`, `INVESTIGATE`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Execution/StudioNotificationService.php` | `App\\Core\\DB`, `users` table, `studio_notifications` table | Medium | Investigate first (table ownership and lifecycle policy) | Yes | Yes (DB/runtime checks where applicable) |
| StudioResourceTypeRegistryService | `apps/Studio/Services/StudioResourceTypeRegistryService.php` | Static resource type catalog and owner inference by path | `REGISTRY` | `apps/Studio/Services/Registry/StudioResourceTypeRegistryService.php` | Library node metadata/path conventions | Low | Yes | Yes | Low |
| StudioRouteLinkingService | `apps/Studio/Services/StudioRouteLinkingService.php` | Analyze/apply route_path linking writes for generated view/module manifests | `COMPOSITION`, `EXECUTION`, `GENERATION`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Execution/StudioRouteLinkingService.php` | Generated `manifest.json` and `module.json` in `apps/Generated`, fingerprint gating | High | Later (after generated-write facade extraction) | Yes | Yes (generated-artifact and architecture diagnostics) |
| StudioRuntimeBindingService | `apps/Studio/Services/StudioRuntimeBindingService.php` | Runtime binding of adapters/providers/action handlers for Studio-rendered flows | `EXECUTION`, `RUNTIME_ADJACENT`, `COMPAT_BRIDGE`, `DO_NOT_MOVE` | `apps/Studio/Services/Runtime/StudioRuntimeBindingService.php` (only after owner split) | Studio ActionHandlers, Adapters, DataProviders | High | No (until ActionHandlers/Adapters/DataProviders separation is completed) | Yes | Yes (runtime-adjacent diagnostics and behavior checks) |
| StudioToolCatalogService | `apps/Studio/Services/StudioToolCatalogService.php` | Static Studio workbench tool metadata and grouping | `REGISTRY`, `COMPOSITION` | `apps/Studio/Services/Registry/StudioToolCatalogService.php` | Translation keys and Studio route links | Low | Yes | Yes | Low |
| StudioViewIntrospectionService | `apps/Studio/Services/StudioViewIntrospectionService.php` | Imports existing/generated nodes into Studio bundle model and reconstructs layout models | `ANALYSIS`, `RUNTIME_ADJACENT` | `apps/Studio/Services/Analysis/StudioViewIntrospectionService.php` | `GuiStudioService` template/generated-module helpers; route/nav extraction | Medium | Later (after GuiStudioService decomposition) | Yes | Yes (import/runtime diagnostic checks) |

## Write-Path And Bridge Summary

### Generated Artifacts (`apps/Generated`)

- Direct/explicit generated artifact writers or mutators:
  - `GuiStudioService`
  - `StudioRouteLinkingService`
  - `StudioNavLinkingService`
- Generated tree readers/indexers:
  - `AppStudioRegistryService`
  - `StudioViewIntrospectionService`

### Studio Evidence Storage (`storage/appstudio`)

- Direct writers/readers:
  - `GuiStudioService` (`applies`, `snapshots`, `history.json`, `packages`, `registry.json`, lock/signing artifacts)
  - `StudioGovernanceService` (`audit`, `publish_decisions`)

### Public Delivery Assets (`public/assets/apps`)

- No direct writer in `apps/Studio/Services/*` currently.
- Current service boundary is indirect: `GuiStudioService` writes generated module/app styles under `apps/Generated/.../styles.css`, and Shell/public asset publishing remains a separate pipeline owner.

### Compatibility Bridge Services

- `HostSurfaceContributionService` (host-surface contribution links)
- `StudioRuntimeBindingService` (transitional runtime binding across handlers/providers/adapters)
- `StudioNavCandidateProviderService` (route contract compatibility diagnostics)

## Direct Answers

1. Which services are safe future move candidates?
   - `StudioResourceTypeRegistryService`
   - `StudioGovernedToolRegistryService`
   - `StudioToolCatalogService`
   - `StudioDataContractService`
   - `StudioDependencyGraphService`
   - `HostSurfaceContributionService`
   - `AppStudioRegistryService` (safe after route-authority dependency references are redirected)

2. Which services are runtime-adjacent and must stay until separated?
   - `GuiStudioService`
   - `StudioRuntimeBindingService`
   - `StudioRouteLinkingService`
   - `StudioNavLinkingService`
   - `StudioExperienceGovernanceService`
   - `StudioGovernanceService`
   - `StudioViewIntrospectionService`
   - `StudioNavCandidateProviderService`
   - `StudioNotificationService` (pending ownership decision)

3. Which services write to `apps/Generated`?
   - `GuiStudioService`
   - `StudioRouteLinkingService`
   - `StudioNavLinkingService`

4. Which services write to `storage/appstudio`?
   - `GuiStudioService`
   - `StudioGovernanceService`

5. Which services touch `public/assets/apps`?
   - None directly in `apps/Studio/Services/*`.
   - Indirect upstream source impact: `GuiStudioService` prepares generated style artifacts that later feed publish pipelines.

6. Which services are compatibility bridges?
   - `HostSurfaceContributionService`
   - `StudioRuntimeBindingService`
   - `StudioNavCandidateProviderService`

7. What should be the first actual service move batch?
   - Batch 12-A (lowest risk, no behavior change): move only pure registry/metadata services first.
   - Proposed initial move set:
     - `StudioResourceTypeRegistryService`
     - `StudioGovernedToolRegistryService`
     - `StudioToolCatalogService`
   - Why first: no direct generated writes, no storage writes, minimal runtime coupling, clean rollback profile.
   - Precondition before execution: class-loading bridge strategy and diagnostic/gate path updates prepared in the same slice.

## Sequencing Recommendation After Batch 12 Plan

1. Batch 12-A: Registry-only move (pure metadata/manifest readers).
2. Batch 12-B: Analysis-only move (`StudioDataContractService`, `StudioDependencyGraphService`).
3. Batch 12-C: Composition bridge move (`HostSurfaceContributionService`) if host-link diagnostics remain green.
4. Batch 12-D onward: runtime-adjacent split design for `GuiStudioService` and `StudioRuntimeBindingService` before any physical move.

## Priority Outcome

1. Every Studio service file under `apps/Studio/Services/*` is inventoried and classified.
2. Runtime-adjacent and do-not-move services are explicitly blocked from early movement.
3. Generated/storage writer services are identified for guarded sequencing.
4. First safe move batch is constrained to low-risk registry services only.
