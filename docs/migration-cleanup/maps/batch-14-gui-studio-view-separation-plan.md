# Batch 14 GUI Studio View Separation Plan

Status: planning and read-only guard only.

No files were moved.
No partials were extracted in this batch.
No runtime behavior was changed.
No Core files were edited.
No Studio features were built.

Authority:

- Batch 8 Studio boundary priority inventory
- Batch 9 Studio runtime-adjacent separation map
- Batch 10 Studio host-link hardening plan
- Batch 12 Studio services restructuring plan
- Batch 13 Studio Tool Lifecycle Contract
- Batch 14 Studio customization tool boundary contract

## Objective

Reduce extraction risk in `apps/Studio/Views/gui_studio.php` by classifying all
major view sections and defining a no-behavior-change first extraction slice for
Batch 15.

## Scope Inspected

- `apps/Studio/Views/gui_studio.php`
- `apps/Studio/Views/partials/*` (existing extracted fragments)
- `apps/Studio/Controllers/StudioController.php`
- `apps/Studio/Routes/gui_studio_routes.php`
- `apps/Studio/Services/GuiStudioService.php`
- JS/CSS assets currently required by `gui_studio.php`

## Anchor Summary

- Main shell starts at `3688` (`<div class="studio-shell" ...>`)
- Existing partial includes at `3694`, `3733`, `3734`, `9343`, `9344`, `9346`, `9347`, `14814`
- Primary tabs at `3728` (`tab-edit`), `4100` (`tab-analyze`), `4372` (`tab-changes`), `4639` (`tab-apply`)
- Inline style block starts at `2925` (`<style>`)
- Inline script blocks start at `4778`, `7516`, `9349`
- Large stateful JS initializer starts at `4804` (`function initStudioPage()`)
- Legacy compatibility branch starts at `7552` (`$showLegacyStudioWorkbench`)

## Section Classification Matrix

| Classification | Approximate Location / Anchor | Responsibility | Dependencies | Extraction Risk | Proposed Target Partial | Safe For First Extraction | Validation Needed |
|---|---|---|---|---|---|---|---|
| PAGE_SHELL | `3688` (`studio-shell`), `3696` (`studio-main`), `3697` (`studio-header`) | Top-level page framing, status bar, tab controls, mobile mode wrapper | `StudioController` payload values, translation keys, shell-level classes | Medium | `Views/pages/gui_studio.php` + `Views/layouts/studio_shell.php` | No | `php -l`, visual parity, tab-switch smoke checks |
| LIBRARY_PANEL | `3694` (`partials/library_explorer.php`) | Resource library/sidebar navigation and discovery | `globalLibraryTree`, `GuiStudioService::resolve*` helpers, library JS | Medium | `Views/partials/gui_studio/library_panel.php` | Later | library search/filter/load click paths, no route changes |
| EDITOR_PANEL | `3728` (`tab-edit`), includes at `3733` and `3734` | Structured editor/workbench and route/nav proposal forms | loaded-resource partials, tool navigation partial, controller payloads | High | `Views/partials/gui_studio/editor_panel.php` | No | form submit parity, edit/create/upgrade mode behavior checks |
| ANALYZE_PANEL | `4100` (`tab-analyze`) | Pre-apply analysis/preview and diagnostics render | analysis result arrays, server-provided status/result data | Medium | `Views/partials/gui_studio/analyze_panel.php` | Not first | analyze-only flow and read-only rendering diff checks |
| CHANGES_PANEL | `4372` (`tab-changes`) | Change-intelligence tables and diff readiness UI | change-intelligence row JS, result payload, diff metadata | Medium | `Views/partials/gui_studio/changes_panel.php` | Not first | filter/sort/row display parity checks |
| APPLY_PANEL | `4639` (`tab-apply`) and `9343` (`partials/apply_center.php`) | Approval/apply/rollback execution surfaces | apply routes, CSRF fields, preconditions, rollback IDs, service outputs | Very high | `Views/partials/gui_studio/apply_panel.php` | No | POST route parity, apply/rollback safety checks |
| STATUS_PANEL | `3697` header chips and governance status sub-panels (`workflow_status.php`, `mode_panel.php`) | Workflow/mode/readiness status visibility | translation bundles, workflow/mode state inputs, governance partials | Low | `Views/partials/gui_studio/status_panel.php` | Yes (candidate) | static markup parity and status chip rendering checks |
| GENERATED_APP_PANEL | Legacy branch under `7552` and deep lifecycle/apply/generation sections through `14814` | Generated app lifecycle controls and compatibility UI | `GuiStudioService`, route handlers, generated/storage/public assets | Very high | `Views/partials/gui_studio/generated_app_panel.php` | No | end-to-end create/analyze/apply snapshot parity checks |
| INLINE_STYLE | `2925` (`<style>` block) | View-scoped styling and responsive overrides | global theme tokens, partial class names, legacy selectors | Medium | `apps/Studio/assets/css/gui-studio-page.css` (future) | No (in this batch) | CSS ownership gate + visual diff on desktop/mobile |
| INLINE_SCRIPT | Script include wrappers at `4778..4802`, `7516..7544`; inline blocks at `9349..14813` | Client-side orchestration, tab flow, form gating, diff filters, legacy visibility | required JS assets, DOM contracts, localStorage keys, route assumptions | Very high | `apps/Studio/assets/js/gui-studio-page.js` split later | No | JS behavior parity, console error scan, key flow smoke tests |
| TOOL_SPECIFIC_UI | `3734` (`editor_workbench_shell.php`), tool navigation and preview/governance zone partials | Tool catalog cards, tool preview, workbench context, governance lane | tool group payload, localized labels, preview helpers | Medium | `Views/partials/gui_studio/tool_cards.php` + `status_panel.php` | Later | tool-card click preview parity |
| KEEP_COMPAT | `7552` (`$showLegacyStudioWorkbench`) plus inert/content-visibility scripts (`9347`, `14814`) | Compatibility bridge for legacy Studio workbench path | legacy DOM IDs, inert toggles, periodic visibility synchronization | High | keep in compatibility partials until migration debt retires | No | legacy query-param path check (`legacy_studio=1`) |
| EXTRACT_CANDIDATE | Header/static status helper blocks around `3697..3727` and passive status/read-only helper fragments | Low-state markup with minimal logic coupling | translation keys and existing CSS classes only | Low | `Views/partials/gui_studio/header.php` and `status_panel.php` | Yes (first slice) | markup diff + screenshot parity |
| DO_NOT_MOVE | Apply/rollback forms, generated lifecycle mutation controls, legacy compatibility controls | Mutation surfaces and compatibility contracts | route/controller/service coupling and CSRF/action semantics | Very high | keep in `gui_studio.php` until boundaries are isolated | No | route and mutation regression checks |
| INVESTIGATE | Duplicate helper functions and mixed old/new JS patterns in long inline script regions (`~12000+`) | Candidate technical debt requiring pre-extraction normalization | mixed state models, repeated DOM utilities, hidden coupling | High | normalize JS utility layer before extraction | No | JS unit/behavior probes before split |

## Dependency Notes (Current Coupling)

1. Controller coupling: `StudioController::index()` builds broad payloads used by
   multiple mixed panels in one template render pass.
2. Route coupling: `gui_studio_routes.php` handles analysis/apply/rollback flows
   that map directly to form actions embedded in the same monolithic view.
3. Service coupling: `GuiStudioService` data/result shape is consumed by both new
   and legacy panel sections in one file.
4. JS coupling: inline scripts and embedded assets assume in-file DOM IDs/classes
   spanning multiple panels and compatibility branches.

## Existing Extracted Fragments (Do Not Re-Extract In Batch 14)

- `partials/library_explorer.php`
- `partials/loaded_resource_workbench.php`
- `partials/editor_workbench_shell.php`
- `partials/apply_center.php`
- `partials/rollback_panel.php`
- `partials/governance_diagnostics_panel.php`
- `partials/legacy_wrapper_inert_script.php`
- `partials/legacy_content_visibility_script.php`

## Specifically Answered

1. What responsibilities are mixed inside gui_studio.php?

- Page shell/layout, library explorer, structured editor, analyze/changes/apply
  panels, generated app lifecycle controls, governance/readiness display, legacy
  compatibility rendering, and large inline JS/CSS are all mixed in one template.

2. Which sections are safe first extraction candidates?

- Passive header/status/helper blocks with low coupling (classification:
  `EXTRACT_CANDIDATE`, `STATUS_PANEL`) are safest for first extraction.
- Existing already-extracted partials should be preserved as-is in this batch.

3. Which sections must stay until service/controller boundaries are clearer?

- `APPLY_PANEL`, `GENERATED_APP_PANEL`, `DO_NOT_MOVE`, and `KEEP_COMPAT` sections
  must stay due to route/service/CSRF/mutation and compatibility coupling.

4. Are there inline styles/scripts that should become Studio assets later?

- Yes. The inline `<style>` block and long inline JS blocks should be migrated to
  `apps/Studio/assets/*` in later batches after DOM/API boundaries are stabilized.

5. What is the first no-behavior-change extraction slice?

- Extract one passive, read-only fragment only: header/status helper markup
  (`header.php` and/or status-only block) without moving any forms, route actions,
  mutation controls, or stateful JS logic.

## Batch 15 First Slice Recommendation

- Candidate: extract a static header/status fragment from the top shell region
  (`3697..3727`) into `Views/partials/gui_studio/header.php`.
- Constraints:
  - no logic changes
  - no route changes
  - no service/controller changes
  - no JS behavior changes

## Validation For This Batch

- `git diff --check`
- `bash scripts/architecture/check_gui_studio_view_separation_plan.sh`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`

## Batch 18: Passive Partial Extraction Integrity Diagnostic

Status: diagnostic extension only.

No files were moved.
No new partials were extracted.
No runtime behavior was changed.
No Core files were edited.

Extended `scripts/architecture/check_gui_studio_view_separation_plan.sh` to
verify transition integrity for passive extracted fragments:

- `apps/Studio/Views/partials/gui_studio/header.php` exists.
- `apps/Studio/Views/partials/gui_studio/mobile_mode_tabs.php` exists.
- `apps/Studio/Views/partials/gui_studio/editor_intro.php` exists.
- `apps/Studio/Views/gui_studio.php` includes all three partials.
- extracted partials remain passive view fragments and do not include route/
  controller/service mutation indicators.
- `apps/Studio/Views/gui_studio.php` still owns main page shell wrappers during
  transition (`studio-shell`, `studio-main`, `studio-content`).

Batch 18 purpose is guard-strengthening for Batch 15-17 extractions, not new
decomposition work.
