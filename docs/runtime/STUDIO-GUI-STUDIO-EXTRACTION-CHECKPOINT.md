# Studio GUI Extraction Checkpoint

Date: 2026-05-24
Status: Checkpoint after the latest markup and compatibility-script extraction cluster.
Scope: apps/Studio/Views/gui_studio.php composition-focused extraction with no backend behavior change.

## Completed Extraction Chain

- 41333c84: extract tool navigation partial
- 12e8f52a: extract loaded resource workbench partial
- 67904834: extract workbench zone partials
- a9391fca: extract library/workflow/mode partials
- cf11193d: extract apply/rollback partials
- 76bc778a: extract governance diagnostics panel
- ae0c7c6c: extract legacy wrapper inert script
- d291d4de: extract legacy content visibility script

## Current Partial Composition Inventory

Parent composition file:
- apps/Studio/Views/gui_studio.php

Extracted partials currently composed by parent and/or nested partials:
- apps/Studio/Views/partials/tool_navigation.php
- apps/Studio/Views/partials/loaded_resource_workbench.php
- apps/Studio/Views/partials/editor_workbench_shell.php
- apps/Studio/Views/partials/validation_preview_zone.php
- apps/Studio/Views/partials/governance_apply_zone.php
- apps/Studio/Views/partials/library_explorer.php
- apps/Studio/Views/partials/workflow_status.php
- apps/Studio/Views/partials/mode_panel.php
- apps/Studio/Views/partials/apply_center.php
- apps/Studio/Views/partials/rollback_panel.php
- apps/Studio/Views/partials/governance_diagnostics_panel.php
- apps/Studio/Views/partials/legacy_wrapper_inert_script.php
- apps/Studio/Views/partials/legacy_content_visibility_script.php

## Remaining Inline Responsibilities In Parent

Read-only classification for next planning stage:

- Main page shell and structural wrappers around composed partials.
- Remaining inline dictionaries and labels used by Studio runtime scripts.
- Remaining inline PHP preparation/output blocks (including result and preflight-linked data wiring where not yet extracted).
- Remaining inline JS controller blocks (large, multi-responsibility runtime scripts).
- Legacy compatibility container envelope wrapping legacy studio content.

## Preserved Runtime Contracts

The following runtime contracts were preserved during extraction (no intended behavior drift):

- Existing selectors and data-attributes consumed by Studio JS.
- Existing form ids, panel ids, tool trigger hooks, and loaded-context hooks.
- Existing workflow/mode/apply/rollback display behavior.
- Existing compatibility inert behavior for legacy container.
- Existing content-aware visibility behavior for create intent/view kind/table edit mode logic.

## Next Extraction Risk Map

Known risk hotspots for future slices:

- Large inline JS controller blocks are tightly coupled through shared state and global window variables.
- Mixed PHP+JS dictionary regions can break easily if extraction boundaries are broad.
- Loaded-resource context logic has many downstream dependencies across panels.
- Any CSS movement before JS stabilization can introduce unintended UI regressions.

## Recommended Near-Term Order (Docs + Low-Risk)

1. Keep next step read-only: classify remaining parent responsibilities in finer boundaries.
2. Extract only small, isolated compatibility/helper blocks with strict selector parity.
3. Produce JS extraction plan document before moving any additional JS blocks.
4. Start JS extraction with the safest single-purpose block first (tool preview), then validate route-by-route.

## Validation Baseline At This Checkpoint

- PHP lint passed across parent and extracted partial files in prior slices.
- Architecture gates passed in prior slices.
- Deployment readiness checks passed in prior slices.
- Browser checks passed on key Studio routes in prior slices.

## Non-Goals For This Checkpoint

- No backend behavior changes.
- No Core/Shell ownership changes.
- No route contract changes.
- No Studio tool-module runtime ownership rewrite.

## Remaining gui_studio.php Responsibility Audit

Audit date: 2026-05-24
Current head commit: 4d87ef70
Audit mode: strict read-only classification (no refactor, no behavior change)

### Extracted Partials Already Completed (Summary)

- tool_navigation.php
- loaded_resource_workbench.php
- editor_workbench_shell.php
- validation_preview_zone.php
- governance_apply_zone.php
- library_explorer.php
- workflow_status.php
- mode_panel.php
- apply_center.php
- rollback_panel.php
- governance_diagnostics_panel.php
- legacy_wrapper_inert_script.php
- legacy_content_visibility_script.php

### 1) Page Composition Shell

Evidence anchors in parent file:

- Inline style block remains in parent: `<style>` around lines 2900-3595.
- Parent shell wrapper remains inline: `.studio-shell`, mobile tabs, main header, tab rail, toasts, edit/analyze/changes/apply container wrappers.
- Parent includes remain orchestrated inline:
	- library_explorer include around line 3603
	- loaded_resource_workbench include around line 3642
	- editor_workbench_shell include around line 3643
	- apply_center include around line 9914
	- rollback_panel include around line 9915
	- governance_diagnostics_panel include around line 9917
	- legacy_wrapper_inert_script include around line 9918
	- legacy_content_visibility_script include around line 15499

Classification:

- Parent is still the composition orchestrator for shell + include order + cross-panel wrappers.
- Remaining structural wrappers are valid near-term extraction candidates only if they do not alter selector reachability or event-target ancestry.

### 2) Inline CSS

Evidence anchors:

- One major inline style block remains (2900-3595), covering Studio shell, tool cards, workbench zones, mobile mode behavior, library detail sheet/backdrop, and legacy layout support.

Ownership/risk assessment:

- CSS appears Studio-owned by selector naming and context.
- Move risk is medium-high now because JS behavior and responsive interactions still rely on exact class/DOM combinations.
- Recommended to defer CSS movement until JS extraction stabilizes.

### 3) Inline Localization/Fallback Dictionaries

Evidence anchors:

- Large inline `$gs` dictionary remains in parent (multi-language key map used throughout runtime scripts and template output).

Classification:

- Presence confirmed; localization migration remains intentionally paused.
- Do not migrate keys or localization wiring in this phase.

### 4) PHP Data Preparation / Result Handling

Evidence areas:

- Top-of-file normalization and defaulting of request/result inputs and library payloads.
- Tool-catalog and preview key preparation.
- Apply-tab local preparation block remains inline (around 4560+), including compile/analyze/approval/publish-gate and runtime gate error derivations.
- Legacy area still includes result-driven rendering for lifecycle, validation, compile graph, approval/snapshot/execution/diff readiness, and governance-linked outputs.

Classification:

- Parent still owns substantial PHP preparation and output branching.
- Future extraction candidates exist, but must preserve variable scope and conditional boundaries exactly.

### 5) Remaining Markup

Still-inline markup classes of responsibility:

- Main Studio shell/header/tab wrappers and notices.
- Surface exposure panel and route/nav link proposal panels in edit tab.
- Apply tab gate form wrapper and metadata capture section around included apply/rollback blocks.
- Large legacy compatibility container `.legacy-studio` with multiple legacy cards/forms/tables still inline.

Future partial candidacy:

- Safe candidates: small isolated helper panels with minimal inline PHP branching.
- Higher-risk candidates: legacy cards tightly coupled to large script block state and PHP conditional rendering.

### 6) JavaScript Blocks

Current inline script map in parent:

- Script A (4687-8022): primary Studio controller bootstrap (`initStudioPage`) with tab/mode switching, library interactions, mobile behavior, and apply form gating.
- Script B (8024-8123): workbench context sync bridge reacting to tool selection and loaded-resource events.
- Script C (9920-15497): large structured-editor and governance runtime logic (diff filters, builder state, migration/impact/simulation behaviors, library load/apply behavior, and extensive event wiring).
- Compatibility script includes extracted:
	- legacy_wrapper_inert_script.php
	- legacy_content_visibility_script.php

Classification:

- JS is still the highest coupling area; extraction should be single-purpose and incremental.

### 7) Risk Classification

Safe to extract next:

- Small markup-only helper panels in parent shell where selector/event contracts are local and obvious.

Needs docs-only JS plan first:

- Any movement from Script A, Script B, or Script C.
- Especially loaded-resource and governance paths due cross-panel coupling.

Should remain until backend contracts stabilize:

- Result-driven legacy execution/approval/rollback rendering segments tied to governance/apply payload structures.
- Mixed PHP+JS dictionary-dependent regions where extraction could silently break key lookups or runtime defaults.

### Recommended Next Extraction/Refactor Order

1. Keep parent read-only for one more step and produce explicit JS extraction map by responsibility (tool preview, loaded context, library explorer, workflow/mode, apply/governance/rollback).
2. Extract the safest single JS responsibility first: tool-preview behavior only.
3. Validate parity on all key Studio routes after each JS slice.
4. Extract loaded-resource context JS only after tool-preview extraction passes parity checks.
5. Defer inline CSS relocation until post-JS stabilization.

### Must Not Be Touched Yet

- Localization migration/key restructuring.
- Backend behavior wiring changes.
- Core/Shell/routes/controllers/DB/permissions/business-app ownership boundaries.
- Broad JS or CSS movement across multiple concerns in one slice.

### Recommended Next Implementation Slice

- Docs-first JS extraction plan update, then a single-scope implementation slice for tool-preview JS extraction only.

### JS Boundary Map (Docs-First, Evidence Anchors)

This subsection refines extraction boundaries for the first JS move without changing runtime code.

#### Target: Tool Preview JS (first extraction candidate)

Primary anchors in parent file:

- Tool preview bootstrap entry in Script A: around lines 4906+ (`bindStudioToolPreviewPanel`).
- Selector contract source: `[data-gs-tool-preview-trigger]` card collection.
- Preview sink selectors:
	- `[data-gs-tool-preview-name]`
	- `[data-gs-tool-preview-group]`
	- `[data-gs-tool-preview-status]`
	- `[data-gs-tool-preview-purpose]`
	- `[data-gs-tool-preview-works-on]`
	- `[data-gs-tool-preview-must-not-own]`
	- `[data-gs-tool-preview-first-safe]`
	- `[data-gs-tool-preview-backend]`
- Event handoff contract: emits `studio-tool-preview-selected` CustomEvent with `{ card, toolId }` payload.
- Default-card policy: prefers `data-tool-id="resource_explorer"`, then first card fallback.
- Accessibility/UX behavior contract: `is-preview-active` class, `aria-current`, click/focus/keydown (`Enter` / `Space`) handlers.

#### Coupling To Keep Stable During First JS Move

- Script B depends on the emitted `studio-tool-preview-selected` event for workbench-context sync.
- Script B fallback lookup expects active card contract:
	- `.gs-studio-tool-card.is-preview-active[data-gs-tool-preview-trigger]`
- CSS state styling relies on active-card and trigger selectors.

#### Do-Not-Break Contract Checklist For Tool Preview Extraction

- Preserve selector names and data attributes exactly.
- Preserve event name and payload shape exactly.
- Preserve default selection behavior and fallback order.
- Preserve keyboard activation semantics and `aria-current` toggling.
- Preserve active-card class contract (`is-preview-active`).

#### Non-Target For First JS Extraction

- Loaded-resource identity synchronization logic in Script B.
- Structured editor / governance runtime logic in Script C.
- Any inline dictionary or localization key organization.
- Any CSS relocation.

#### Proposed File Target (Next Implementation Slice)

- `apps/Studio/assets/js/tool-preview.js` (single-scope extraction target)

This map is advisory for the next code slice and intentionally keeps localization and backend wiring paused.
