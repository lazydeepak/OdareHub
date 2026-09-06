# Studio Tool Separation Plan (Docs-Only)

Date: 2026-05-24
Status: Planning only. No runtime implementation in this slice.
Scope: Studio tool-surface separation from `apps/Studio/Views/gui_studio.php`, with Nav Composer as first real separated tool surface.

## Purpose

Define a safe, governed separation model so `apps/Studio/Views/gui_studio.php` becomes a thin shell/composition page over time, while each Studio tool gets an explicit owner surface.

This document does not authorize code movement by itself.

## 1) Current Problem

- `apps/Studio/Views/gui_studio.php` still carries too many mixed responsibilities.
- Tool cards are present in UI, but most tools are not real runtime surfaces yet.
- Navigation/Menu tool is currently planned/disabled and has no route/href.
- Link-candidate provider/consumer contract for Nav Composer is missing.

## 2) Target Studio Tool Model

Proposed Studio-owned tool boundaries:

- `apps/Studio/Tools/ResourceExplorer`
- `apps/Studio/Tools/NavComposer`
- `apps/Studio/Tools/ViewComposer`
- `apps/Studio/Tools/ModuleBuilder`
- `apps/Studio/Tools/AppBuilder`
- `apps/Studio/Tools/ValidationPreview`
- `apps/Studio/Tools/ApplyGovernance`
- `apps/Studio/Tools/HistorySnapshots`

## 3) Tool Ownership Boundary

Each tool may own:

- its own route surface under `/apps/studio/tools/...`
- its own view partials
- its own JS/CSS when needed
- its own read-only adapters
- its own governed apply flow in later phases

Each tool must not own:

- business app routes
- business navigation truth
- Core/Shell behavior
- app/module source resources
- hidden duplicate source of truth

## 4) Nav Composer Target

Nav Composer is the first real separated Studio tool surface.

First target behavior:

- read-only candidate preview only
- no mutation/apply in first implementation
- consumes owner-owned route/nav candidates
- later writes only via governed owner-owned artifact updates

## 5) Separation Strategy For `gui_studio.php`

- Keep `apps/Studio/Views/gui_studio.php` as the main Studio shell/composition page.
- Tool card for Navigation/Menu should point to a real Nav Composer route only after read-only shell exists.
- Do not move all tools at once.
- Do not split PHP randomly by file size only.
- Move by explicit owner boundaries and validated contracts.
- Recommended order: Nav Composer first, then Resource Explorer, then View Composer.

## 6) Provider/Consumer Contract Requirement

Before any Nav Composer implementation:

- define shared link-candidate contract keys and semantics
- define source-of-truth owner for every candidate field
- define filtering rules and diagnostics
- define explicit no-mutation scope for phase-1 Nav Composer

Contract details are documented in:

- `docs/runtime/STUDIO-NAV-COMPOSER-LINK-CONTRACT-AUDIT.md`

## 7) Implementation Phases

- Phase 0: docs-only plan.
- Phase 1: route/tool shell for Nav Composer (read-only surface only).
- Phase 2: read-only candidate provider adapter.
- Phase 3: candidate preview UI.
- Phase 4: diagnostics and mismatch display.
- Phase 5: governed apply planning only.
- Phase 6: mutation/apply only after explicit contract approval.

## 8) Guardrails

- Studio remains a governed worker/tool.
- Apps/modules remain source-of-truth for route/nav/link declarations.
- Studio may discover/present candidates but must not become business link owner.
- No Core/Shell ownership changes.
- No fake links.
- No hardcoded sample business links.
- Localization remains paused.
- Backend wiring remains governed.
- CSS extraction remains paused.

## 9) Validation Plan (For Future Implementation Slices)

- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- Browser checks:
  - `/apps/studio`
  - `/apps/studio/tools/nav-composer` (when created)
  - `/apps/studio/library`
  - `/apps/studio/history`
- verify no fake links
- verify no Core/Shell/business app ownership changes

## 10) Recommended First Implementation Slice

After this docs checkpoint, implement only:

- Nav Composer read-only route shell under `/apps/studio/tools/nav-composer`
- no apply/save buttons
- no route mutation
- no `navigation.php` mutation
- no DB writes
- use owner-owned providers only
