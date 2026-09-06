# Batch 9 Studio Runtime-Adjacent Separation Map

Status: executed as read-only diagnostic and documentation only.

No files were moved, deleted, archived, or mutated. Runtime behavior and Core
behavior were not changed.

## Architecture Basis

- Studio remains a governed builder system app, not owner runtime truth.
- Runtime-adjacent Studio code must be separated by ownership contract before
  migration or refactor slices.
- Compatibility bridges may remain while separation contracts are stabilized.
- Legacy /ops bridges stay transitional until contract parity and handoff proof
  are complete.

## Diagnostic Script

- script: scripts/architecture/check_studio_runtime_adjacent_separation_map.sh
- mode: read-only diagnostic
- current result: pass

## Separation Objective

This batch maps runtime-adjacent Studio edges so future slices can split by
owner boundary without behavior regressions.

## Direct Answers

1. Which Studio areas are runtime-adjacent and not final Studio ownership?
   - Action handlers bound to order lifecycle paths.
   - Analytics package bound to operations drilldown URLs and manufacturing
     intelligence terms.
   - Data providers and repositories carrying business-order and parts semantics.
   - Route glue in gui_studio_routes still mixing broad workflow behavior.

2. Which app currently owns compatibility entry bridges?
   - Platform owns /ops/gui-studio redirect and legacy /ops/design-studio
     surfaces in apps/Platform/routes.php.

3. Which app currently owns admin wrapper-level Studio discovery?
   - Shell owns conditional Studio sidebar entry wiring through
     AdminLayerWrapperComposer.

4. Which services remain runtime-adjacent even when Studio is optional?
   - GuiStudioService and StudioRuntimeBindingService lifecycle glue.
   - Governance and analysis services that read/write generated artifacts and
     evidence stores.

5. Which artifact targets are explicitly in-scope for governed Studio writes?
   - apps/Generated/* owner artifacts.
   - storage/appstudio/* provenance and lifecycle evidence.
   - public/assets/apps/* delivery output through governed publish flow.

6. What must stay unchanged during separation mapping?
   - No route behavior changes.
   - No bridge removals.
   - No file moves.
   - No generated-app lifecycle mutations.

7. Which boundaries are next safe split candidates after this map?
   - Analytics and business-facing provider/repository directories.
   - Order-specific action handlers.
   - Oversized route/service composition hubs.

8. What proof is required before moving any runtime-adjacent area?
   - Owner contract assignment.
   - Compatibility bridge parity checks.
   - Read-only diagnostics and architecture gates passing.

## Runtime-Adjacent Separation Matrix

| Area | Current location | Current role | Separation target | Owner boundary | Next action |
|---|---|---|---|---|---|
| Order action handlers | apps/Studio/ActionHandlers | Order submit/update/transition lifecycle helpers | Split to owner app module service surface | Business app owner | Keep in place and classify each handler to owner capability map |
| Analytics | apps/Studio/Analytics | Operations/manufacturing KPI and drilldown composition | Move business analytics logic to owner app | Business app owner | Keep in place and map route and data dependencies |
| Data providers | apps/Studio/DataProviders | Order/parts data access abstraction | Move provider contracts to owner modules | Business app owner | Keep in place and classify input/output contracts |
| Repositories | apps/Studio/Repositories | Business CRUD and schema bridge helpers | Move business repositories to owner app modules | Business app owner | Keep in place and document writer/reader boundaries |
| Runtime binding | apps/Studio/Services/StudioRuntimeBindingService.php | Binds runtime resources during Studio workflows | Split into execution-only Studio layer plus owner adapters | Shared boundary (Studio execution + owner runtime) | Keep behavior fixed and produce split design |
| Monolith workflow | apps/Studio/Services/GuiStudioService.php | Mixed generation, validation, lifecycle, and registry operations | Split by execution/governance/analysis sub-services | Studio owner | Keep behavior fixed and stage service decomposition |
| Route mega file | apps/Studio/Routes/gui_studio_routes.php | Mixed route registration and workflow orchestration | Reduce to route registration + delegated handlers | Studio owner | Keep routes unchanged and classify handler domains |

## Platform And Shell Runtime-Adjacent Bridges

| Bridge edge | File | Classification | Owner | Policy for next slices |
|---|---|---|---|---|
| /ops/gui-studio compatibility redirect | apps/Platform/routes.php | Compatibility entry bridge | Platform | Keep redirect while Studio optional discovery remains |
| studio_register_gui_studio_routes bridge load | apps/Platform/routes.php | Transitional route bridge | Platform + Studio | Keep conditional load; avoid ownership expansion |
| /ops/design-studio legacy routes | apps/Platform/routes.php | Legacy render-owning bridge | Platform + Base legacy | Keep gated fallback until explicit retirement slice |
| Admin sidebar Studio links | apps/Shell/Services/AdminLayerWrapperComposer.php | Conditional discovery coupling | Shell + Studio | Keep conditional checks; no unconditional Studio dependency |
| Generated manifest fallback in style registry | apps/Shell/Services/StyleRegistryService.php | Runtime delivery compatibility bridge | Shell + generated apps | Keep fallback until generated registry contract is narrowed |

## Runtime Contract Guards

- Studio optional contract remains mandatory: enabled-status checks and fail-safe
  loading paths must stay intact.
- Compatibility bridge behavior remains unchanged in this batch.
- Runtime-adjacent areas are mapped only; no migration or removal occurs.
- Generated-app and storage evidence boundaries remain governed and unchanged.

## Separation Sequencing After Batch 9

1. Produce owner-mapped split specs for ActionHandlers, Analytics,
   DataProviders, and Repositories.
2. Define service decomposition boundaries for GuiStudioService and
   StudioRuntimeBindingService without changing runtime behavior.
3. Define route handler extraction plan for gui_studio_routes.php while keeping
   canonical route behavior intact.
4. Execute minimal migration slices only after diagnostics and gates prove
   owner-boundary parity.

## Priority Outcome

1. Runtime-adjacent edges are explicitly mapped with owner boundaries.
2. Compatibility bridges are retained and classified, not expanded.
3. Next slices are constrained to contract-first decomposition.
4. Cleanup/migration remains blocked until owner split proofs are complete.
