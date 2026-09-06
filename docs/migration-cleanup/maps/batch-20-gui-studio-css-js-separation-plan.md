# Batch 20 GUI Studio CSS JS Separation Plan

Status: planning and read-only guard only.

No CSS or JS files were moved in this batch.
No inline scripts were extracted in this batch.
No runtime behavior was changed.
No Core files were edited.
No Studio features were built.

Authority:

- Batch 14 GUI Studio view separation plan
- Batch 15 first passive partial extraction
- Batch 16 second passive partial extraction
- Batch 17 third passive partial extraction
- Batch 18 passive-partial integrity diagnostic
- Batch 19 fourth passive partial extraction

## Objective

Map remaining inline CSS/JS ownership in `apps/Studio/Views/gui_studio.php`
before any CSS/JS extraction so behavior-critical logic is not moved early.

## Scope Inspected

- `apps/Studio/Views/gui_studio.php`
- inline `<style>` and `<script>` blocks in `gui_studio.php`
- `apps/Studio/assets/*`
- `public/assets/apps/studio*` (if present)
- style/script references in Studio routes/controllers/views

## Current Inventory

- Inline style blocks remaining: 0
- Inline script blocks remaining: 13
- Studio source asset folder currently present: `apps/Studio/assets/js/`
- Studio source CSS asset extracted in Batch 21: `apps/Studio/styles/gui_studio.css`
- Studio source JS assets discovered:
  - `apps/Studio/assets/js/tool-preview.js`
  - `apps/Studio/assets/js/workflow-mode.js`
  - `apps/Studio/assets/js/edit-workbench-state.js`
  - `apps/Studio/assets/js/loaded-resource-identity.js`
  - `apps/Studio/assets/js/apply-form-gate.js`
  - `apps/Studio/assets/js/change-intelligence-table-rows.js`
  - `apps/Studio/assets/js/library-explorer.js`
  - `apps/Studio/assets/js/loaded-resource-context.js`
- public/assets/apps/studio* present: no

## Style/Script Area Classification

| Area | Approximate Anchor | Classification | Reasoning | Future Move Safety |
|---|---|---|---|---|
| Main inline style block | Extracted in Batch 21 to `apps/Studio/styles/gui_studio.css`; linked from `gui_studio.php` (`/assets/apps/studio/styles/gui_studio.css`) | INLINE_STYLE, PASSIVE_STYLE, EXTRACT_CANDIDATE | Pure presentation CSS moved as-is from inline block to Studio-owned stylesheet source | Completed as no-behavior-change CSS extraction |
| JS include wrapper: tool preview | `gui_studio.php:4722-4724` | INLINE_SCRIPT, BEHAVIOR_SCRIPT | Wrapper delegates behavior to `tool-preview.js` | Keep until script include strategy is standardized |
| JS include wrapper: workflow mode | `gui_studio.php:4726-4728` | INLINE_SCRIPT, BEHAVIOR_SCRIPT | Wrapper delegates to `workflow-mode.js` | Keep for now |
| JS include wrapper: edit workbench state | `gui_studio.php:4730-4732` | INLINE_SCRIPT, STATEFUL_SCRIPT, DOM_MUTATION | Script drives editor state synchronization | DO_NOT_MOVE until state contract guard exists |
| JS include wrapper: loaded identity | `gui_studio.php:4734-4736` | INLINE_SCRIPT, STATEFUL_SCRIPT, DOM_MUTATION | Ties loaded resource identity to UI badges | DO_NOT_MOVE until parity probes exist |
| JS include wrapper: apply form gate | `gui_studio.php:4738-4740` | INLINE_SCRIPT, BEHAVIOR_SCRIPT, APPLY_FLOW | Governs apply action readiness | DO_NOT_MOVE |
| JS include wrapper: change intelligence rows | `gui_studio.php:4742-4744` | INLINE_SCRIPT, BEHAVIOR_SCRIPT, DOM_MUTATION | Renders/updates change table rows | DO_NOT_MOVE until table parity tests |
| Large page orchestrator block (`initStudioPage`) | `gui_studio.php:4746-7459` | INLINE_SCRIPT, STATEFUL_SCRIPT, DOM_MUTATION, LOCAL_STORAGE, APPLY_FLOW, GENERATED_APP_FLOW, DO_NOT_MOVE | Owns tab switching, library interactions, editor state, localStorage drafts/templates, flow wiring | Not safe for early extraction |
| JS include wrapper: library explorer | `gui_studio.php:7460-7462` | INLINE_SCRIPT, BEHAVIOR_SCRIPT, STATEFUL_SCRIPT | Initializes library explorer behavior | Keep until orchestrator split strategy |
| Library explorer ready-init block | `gui_studio.php:7463-7477` | INLINE_SCRIPT, BEHAVIOR_SCRIPT, DOM_MUTATION | Bootstraps `gsInitLibraryExplorer` on DOM ready | INVESTIGATE for later merge into asset |
| Loaded context defaults object block | `gui_studio.php:7478-7484` | INLINE_SCRIPT, PASSIVE_STYLE, EXTRACT_CANDIDATE | Defines static config defaults (no event listeners, no mutations by itself) | First no-behavior-change JS extraction candidate |
| JS include wrapper + init: loaded resource context | `gui_studio.php:7485-7494` | INLINE_SCRIPT, STATEFUL_SCRIPT, DOM_MUTATION | Initializes context-sync behavior against live DOM state | DO_NOT_MOVE for now |
| Legacy/runtime mixed mega-block after legacy sections | `gui_studio.php:9293+` | INLINE_SCRIPT, STATEFUL_SCRIPT, DOM_MUTATION, LOCAL_STORAGE, APPLY_FLOW, GENERATED_APP_FLOW, DO_NOT_MOVE, INVESTIGATE | Contains diff filters, editor/runtime wiring, apply/migration/simulation state hooks, visual builder logic | Must remain until behavior contract decomposition |

## Reference Surface Notes

- `apps/Studio/Routes/gui_studio_routes.php` and `apps/Studio/Controllers/StudioController.php`
  both render `studio::gui_studio.php` as the behavior owner surface.
- No dedicated Studio runtime-delivery CSS/JS under `public/assets/apps/studio*`
  is currently present.
- Current JS source of truth is `apps/Studio/assets/js/*`, but orchestration still
  depends on inline wrapper and inline stateful blocks in `gui_studio.php`.

## Specifically Answered

1. How many inline style/script blocks remain in gui_studio.php?

- Inline style blocks remaining: 1
- Inline script blocks remaining: 13

2. Which blocks are passive styling and safe future extraction candidates?

- The single `<style>` block (`gui_studio.php:2925`) is the primary passive
  styling candidate once asset wiring parity guard is ready.
- The small defaults script block (`gui_studio.php:7478-7484`) is a passive JS
  config candidate.

3. Which scripts control runtime/editor behavior and must not move yet?

- `initStudioPage` (`gui_studio.php:4746-7459`), the loaded-resource-context
  init block (`7485-7494`), and the late mega-block (`9293+`) control editor and
  runtime behavior and must not move yet.

4. Which scripts touch apply/generated-app/stateful flows?

- Apply flow: `apply-form-gate.js` wrapper block, `initStudioPage`, and late
  mega-block (`9293+`).
- Generated-app/stateful flow: `initStudioPage` and late mega-block (`9293+`).

5. What should be the first no-behavior-change CSS/JS extraction candidate?

- First JS candidate: extract only the static `window.gsLoadedResourceContextDefaults`
  block (`7478-7484`) into a dedicated Studio asset include, with zero logic
  changes.
- First CSS candidate: move the inline style block as-is into a Studio-owned CSS
  source file, with strict selector parity checks.

6. What guard should protect extracted Studio CSS/JS assets?

- Add a read-only guard that verifies:
  - extracted files exist under Studio-owned asset source paths;
  - `gui_studio.php` includes them in the same order;
  - no stateful/apply/generated-flow blocks are moved without explicit parity
    anchor updates;
  - shell ownership remains in `gui_studio.php` during transition;
  - inline block counts only decrease when mapped extraction entries are added to
    the plan.

## Batch 20 Validation

- `git diff --check`
- `bash scripts/architecture/check_gui_studio_css_js_separation_plan.sh`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`