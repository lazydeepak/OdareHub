# Studio JavaScript Extraction Checkpoint

Date: 2026-05-24
Status: Docs-only checkpoint before apply/governance/rollback JavaScript extraction.
Scope: Studio-owned runtime JavaScript boundary planning only. No code refactor, no behavior change.

## Purpose

This checkpoint records the current JavaScript extraction state for `apps/Studio/Views/gui_studio.php` and defines the risk boundary before any apply, governance, snapshot, rollback, preflight, or publish-gate JavaScript is moved.

Localization remains paused. Backend wiring remains paused. This document does not authorize extraction by itself.

## Change Intelligence Row-Renderer Group Complete (Docs-Only)

Checkpoint date: 2026-05-24
Status: completed for pure row-render helpers only. No runtime behavior change.

### Completed Helper File

- `apps/Studio/assets/js/change-intelligence-table-rows.js`

### Completed Helpers

- `window.gsRenderImpactAnalysisRows` (`47135a2e`)
- `window.gsRenderMigrationPlanRows` (`98402190`)
- `window.gsRenderSimulationPreviewRows` (`d2213836`)
- `window.gsRenderChangeSummaryRows` (`79153433`)

### Parent-Owned No-Move Logic (Must Remain In `apps/Studio/Views/gui_studio.php`)

- High-impact detection.
- Destructive migration detection.
- Broken-view detection.
- Warning visibility.
- Required field toggles.
- Rollback snapshot notice.
- Breaking-change banner.
- Change counters including `#gs-change-high`.
- Compile required flags.
- Submit guards.
- Backend payload assumptions.
- Fetch/load/apply/publish/preflight/rollback paths.
- Confirmations and alerts.
- Result/preflight/publish-gate rendering.

### Validation Baseline Used

- `php -l` on Studio view when extraction slices were implemented.
- `node --check` on updated Studio JS assets.
- `bash scripts/architecture/run_architecture_gates.sh`.
- `bash scripts/system/check_deployment_readiness.sh`.
- `git diff --check`.
- Authenticated browser checks on:
  - `/apps/studio`
  - `/apps/studio/library`
  - `/apps/studio/history`
  - `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

### Next Decision Gate

- Option 1: pause JS extraction and move to CSS extraction planning.
- Option 2: run a new read-only audit for remaining display-only helpers.
- Guardrail: do not touch action/POST/rollback execution JavaScript without an explicit route/payload audit first.

## Completed JavaScript Extraction Chain

The following Studio-owned JavaScript slices have already been extracted from `apps/Studio/Views/gui_studio.php` into `apps/Studio/assets/js/`:

| Slice | File | Commit | Risk Class | Notes |
|---|---|---:|---|---|
| Tool preview | `tool-preview.js` | `d825e393` | Low | Preview card selection and `studio-tool-preview-selected` event flow. |
| Loaded-resource context bridge | `loaded-resource-context.js` | `f50bc310` | Low | Workbench context sync from selected tool and loaded identity event. |
| Library Explorer | `library-explorer.js` | `e797b33f` | Low | Library filter/search/detail/load affordance behavior. |
| Workflow/Mode panels | `workflow-mode.js` | `eb246a76` | Low | Workflow status and mode display sync. |
| Edit workbench state | `edit-workbench-state.js` | `54b79d0f` | Low | Edit badges and workbench loaded-state display sync. |
| Loaded Resource Identity | `loaded-resource-identity.js` | `a52c7ce3` | Low | Loaded identity panel text, source-path row visibility, and loaded identity event dispatch. |

These completed slices were intentionally limited to UI preview/display/sync behavior and preserved existing selector, event, and parent updater contracts.

## Remaining Inline JavaScript Responsibilities

The remaining JavaScript in `apps/Studio/Views/gui_studio.php` still includes higher-coupling behavior that must not be moved without a finer audit:

- Apply form handling.
- Approval and governance readiness behavior.
- Snapshot handling and snapshot-linked display state.
- Rollback preview and rollback execution handling.
- Result rendering, preflight result handling, and publish-gate behavior.
- Legacy compatibility behavior still inline in the parent view.
- Shared state, dictionaries, event wiring, fetch calls, and request payload construction used by the apply/governance/rollback flow.

## Risk Classification

### Low Risk Already Completed

The extracted files listed above are low risk because they are mostly display synchronization, read-only preview behavior, or event bridging. They do not change backend routes, request payloads, form submission semantics, destructive actions, or governance decisions.

### Medium Risk Remaining Display-Sync Logic

Any remaining display-only helper that reads existing state and updates text, visibility, or inert status may be medium risk if it can be isolated from fetch/action execution. This includes possible snapshot display helpers or rollback preview display helpers only when they do not build or submit requests.

Medium-risk work still requires a one-slice extraction, exact selector preservation, and browser parity checks.

### High Risk Apply/Governance/Rollback Behavior

Apply, governance, rollback, preflight, publish-gate, and result-handling JavaScript is high risk because it may include action execution, route contracts, POST payloads, confirmation prompts, destructive or governance-significant behavior, approval readiness, snapshot assumptions, or rollback execution state.

High-risk behavior must not be extracted until its route, payload, DOM contract, and parity validation are explicitly documented.

## Boundary Rules For Next Extraction

Any future apply/governance/rollback JavaScript extraction must follow these rules:

- Extract one behavior slice at a time.
- Do not change backend behavior.
- Do not change destructive action behavior.
- Preserve all form IDs, button IDs, event handlers, request payloads, confirmation behavior, alert/dialog behavior, and result rendering.
- Preserve existing routes and controller contracts.
- Preserve existing validation and authenticated browser checks.
- Keep localization paused.
- Keep backend wiring paused.
- Do not touch Core, Shell, routes, controllers, DB/migrations, permissions, business apps/modules, CSS, or unrelated JavaScript.
- Do not combine display-only helpers with fetch/action execution in the same extraction.

## Recommended Extraction Order

Recommended order, subject to a read-only audit before each implementation slice:

1. Apply form UI gating / mode state only, if separable.
2. Snapshot display-only helper, if separable.
3. Rollback preview display-only helper, if separable.
4. Rollback execution handler only after explicit audit.
5. Publish-gate/preflight result handling last.

This order intentionally keeps destructive or governance-significant action handlers behind display-only extraction candidates.

## Stop Conditions

Stop and split the plan before implementation if any of the following are true:

- A JavaScript block mixes DOM display updates and fetch/action execution.
- A JavaScript block touches apply or rollback POST behavior and the exact route/payload contract has not been documented.
- A JavaScript block changes confirmation, alert, dialog, or result rendering semantics.
- A JavaScript block depends on backend behavior that is not already wired or stable.
- Browser validation cannot confirm parity after a slice.
- The extraction would require editing routes, controllers, permissions, DB/migrations, localization, Core, Shell, business apps/modules, CSS, or unrelated JS.

If browser validation cannot confirm parity, revert the slice rather than layering additional fixes on top.

## Required Route And Payload Audit Before High-Risk Movement

Before moving any apply/governance/rollback action handler, document:

- Owning form or button selector.
- Bound event type and current event ordering.
- Route URL and HTTP method.
- CSRF source and payload fields.
- Success and failure response assumptions.
- Confirmation or alert text source.
- Result rendering targets.
- Snapshot, rollback, preflight, and publish-gate state inputs.
- Existing browser path that proves parity.

## Validation Baseline

Every future extraction slice must preserve the current validation baseline:

- `php -l apps/Studio/Views/gui_studio.php`
- `node --check` for any new or changed Studio JS asset.
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- `git status --short`
- Authenticated browser check for:
  - `/apps/studio`
  - `/apps/studio/library`
  - `/apps/studio/history`
  - `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

Browser parity must include no raw localization keys, no PHP fatal/warning, no console/page fatal, and no regression in already-extracted tool preview, loaded-resource bridge, library, workflow/mode, edit workbench state, or loaded identity behavior.

## Recommended Next Implementation Slice

Do not extract apply/governance/rollback JavaScript next.

The recommended next slice is a read-only audit of apply/governance/rollback JavaScript boundaries in `apps/Studio/Views/gui_studio.php`. The audit should identify the smallest exact sub-slice, classify whether it is display-only or action-executing, and document selector/route/payload contracts before any code movement.

Preferred first candidate after audit: apply form UI gating / mode state only, but only if it can be proven separable from request submission, route payload construction, result rendering, and destructive/governed action execution.

## Apply / Governance / Rollback JS Boundary Audit

Audit date: 2026-05-24
Current head commit: `8bf77141`
Audit mode: strict read-only. No JavaScript extraction, no behavior change.

### Current Remaining JS Responsibility Map

| Area | Line range / anchors | Selectors / events / functions | Type | Risk | Safe next? | Stop conditions |
|---|---|---|---|---|---|---|
| Apply tab form and gate markup | `4553-4675`; `#gs-apply-form`, `#btn-apply`, `#gs-apply-confirmation`, `#gs-apply-gate-errors`, `#gs-rollback-snapshot-notice` | Form defaults to `/apps/studio/approval-preview`; apply button uses `formaction="/apps/studio/apply-snapshot"`; hidden payload fields include `csrf`, `studio_mode`, `se_previous_bundle`, `app_manifest`, `module_manifest`, `view_definition`, `navigation_definition` | Mixed markup/server payload | High | No | Do not move with JS. Stop if extraction requires changing form IDs, hidden fields, `formaction`, CSRF, or server-rendered runtime errors. |
| Apply form client-side gate | `7394-7441`; anchor `const applyForm = document.getElementById('gs-apply-form')` | `submit` listener; checks `event.submitter.id === 'btn-apply'`; reads `data-analyze-ok`, `#gs-apply-reason`, `#gs-apply-confirmation`, `#gs-change-high`, `input[name="risk_acknowledged"]`; writes `#gs-apply-gate-errors`; calls `event.preventDefault()` and `switchTab('apply')` only on local gate failure | Display/pre-submit guard | Medium | Yes, with care | Stop if the slice changes submit routing, allows blocked submit, changes error text escaping/rendering, changes `event.submitter` semantics, or couples to POST/fetch behavior. |
| Apply result rendering | `partials/apply_center.php:1-164`, included from `9278`; anchors `#gs-apply-snapshot`, rollback forms in apply result | Server-rendered apply status, preconditions, transaction steps, post-publish verification, rollback binding; rollback forms POST to `/apps/studio/rollback-execute` and `/apps/studio/rollback` | Server-rendered result/action forms | High | No | Stop if implementation would touch apply result rendering, rollback form actions, `apply_id`, `compile_id`, or post-publish verification assumptions. |
| Governance/approval readiness | `8968-9073`; anchors `#gs-approval-gate`, `#gs-approval-decision`, `#gs-approval-reason` | Server-rendered readiness chips, approval form, `risk_acknowledged`, `migration_override`, `formaction` buttons for `/apps/studio/snapshot-preview`, `/apps/studio/execution-preview`, `/apps/studio/rollback-preview`, `/apps/studio/publish-gate` | Mixed display/action form | High | No | Stop if a block mixes readiness display with any form submit target or approval payload fields. |
| Governance diagnostics / preflight / publish gate | `8283-8348`, `partials/governance_diagnostics_panel.php`; anchors `/apps/studio/preflight`, `#gs-governance-panel` | Preflight submit button from structured-editor form; server-rendered preflight checks, publish gate checks/errors, rollback plan, publish decision, focused plan, checkpoints, TODO gates | Mixed server-rendered diagnostics/action entry | High | No | Stop if extraction touches preflight or publish gate submit targets, gate token assumptions, result rendering, or diagnostics payload names. |
| Snapshot display | `9076-9161`; anchor `#gs-snapshot-preview` | Server-rendered snapshot status, snapshot ID/hash/integrity/artifact rows | Display-only but server-rendered | Medium | Not before PHP/markup audit | Stop if extraction would require moving PHP-derived `$snapshot`, `$approvalValidation`, `$snapshotSummary`, `$snapshotIntegrity`, or artifact row rendering. |
| Execution preview display | `9164-9239`; anchor `#gs-execution-preview` | Server-rendered execution status, can-execute chip, execution result rows | Display-only but server-rendered | Medium | Not before PHP/markup audit | Stop if extraction would touch `can_execute`, execution result fields, or status derivation from `$snapshot`. |
| Rollback preview | `9032` button; `partials/rollback_panel.php:1-53`; anchors `/apps/studio/rollback-preview`, rollback summary/artifacts | Submit target for rollback preview plus server-rendered rollback plan summary and artifact rows | Mixed action entry/server display | High | No | Stop if extraction changes selected rollback source, rollback preview route, artifact field names, or reversible/non-reversible display. |
| Rollback execution | `partials/apply_center.php:146-161`, `partials/rollback_panel.php:55-152`; anchors `/apps/studio/rollback-execute`, `/apps/studio/rollback`, `#gs-rollback-execution` | POST forms with `apply_id` or `compile_id`; server-rendered rollback block reasons, non-reversible warning, execution status, rollback steps | Action/destructive guardrail | High | No | Do not move without explicit route/payload contract. Stop if confirmation behavior, CSRF, `apply_id`, `compile_id`, block reasons, or non-reversible guardrails are not documented. |
| Change/intelligence display sync affecting apply readiness | `9939-10188`; anchors `renderSimulationPreview`, `renderImpactAnalysis`, `renderMigrationPlan`, `renderChangeSummary`, `#gs-rollback-snapshot-notice` | Updates simulation/impact/migration/change summary tables; toggles required flags and rollback notice based on high/breaking/destructive state | Mixed display/form requirement sync | Medium-High | No for this phase | Stop if extraction would combine display table rendering with required-field semantics or rollback snapshot notice behavior. Split into a separate non-governance display-sync audit first. |
| Content-aware governance visibility | `13399-13500`; anchor `#gs-approval-meta-section` | Hides approval metadata when table edit mode is direct DB/table-edit context | Display-only visibility | Medium | Not as apply/governance slice | Stop if extraction would couple this to apply flow. Keep with broader content-aware visibility until that legacy block is addressed. |
| Legacy compatibility JS | `9284-14860` remaining parent block plus `legacy_content_visibility_script.php` include at `14863` | Structured editor state, local drafts, visual builder, DB row editor fetches, library load fetches, import/export alerts/confirms, content visibility compatibility | Mixed legacy runtime | High | No | Leave inline until smaller non-governance boundaries are documented. Stop if a proposed slice crosses structured editor, library loading, DB editor fetch, or import/export action behavior. |

### Risk Matrix

| Risk | Blocks | Reason |
|---|---|---|
| Low | None newly identified in apply/governance/rollback scope | Remaining low-risk display sync has already been extracted or is not part of apply/governance/rollback. |
| Medium | Apply form client-side gate (`7394-7441`); snapshot display (`9076-9161`); execution preview display (`9164-9239`); content-aware approval metadata visibility (`13399-13500`) | These are mostly display/pre-submit behavior, but still depend on parent functions, server-rendered state, or required-field semantics. |
| Medium-High | Change/intelligence display sync (`9939-10188`) | It renders tables and also changes required fields plus rollback snapshot notice visibility. It must be split before movement. |
| High | Apply form markup/payload (`4553-4675`), approval/governance form (`8968-9073`), preflight/publish diagnostics and submit entries (`8283-8348`, `governance_diagnostics_panel.php`), rollback preview/execution (`rollback_panel.php`, `apply_center.php` rollback forms), legacy mixed JS (`9284-14860`) | These areas include POST routes, payload assumptions, destructive guardrails, server-rendered result contracts, or mixed legacy action/display behavior. |

### Safest Candidate Sub-Slice

The safest next extraction candidate is the apply form client-side gate only:

- Source range: `apps/Studio/Views/gui_studio.php:7394-7441`.
- Candidate file name for a future implementation slice: `apps/Studio/assets/js/apply-form-gate.js`.
- Scope: bind `#gs-apply-form` submit handler and preserve only current local gate behavior for `#btn-apply`.
- Preserved inputs/selectors: `#gs-apply-form`, `#btn-apply`, `#gs-apply-reason`, `#gs-apply-confirmation`, `#gs-apply-gate-errors`, `#gs-change-high`, `input[name="risk_acknowledged"]`.
- Preserved data attributes: `data-analyze-ok`, `data-err-title`, `data-err-analyze`, `data-err-confirm`, `data-err-reason`.
- Preserved behavior: non-apply submits pass through untouched; apply submit is locally blocked only when analyze, confirmation, reason, or breaking-risk acknowledgment checks fail; errors render into `#gs-apply-gate-errors`; blocked submits call `switchTab('apply')`.

This candidate is still medium risk because it is a submit interceptor. It must not move hidden inputs, form actions, server route contracts, apply result rendering, or rollback behavior.

### Explicit No-Move Areas

Do not move these in the next implementation slice:

- `#gs-apply-form` markup, hidden inputs, `action`, or `formaction` values.
- `/apps/studio/apply-snapshot` POST behavior.
- `/apps/studio/approval-preview`, `/apps/studio/snapshot-preview`, `/apps/studio/execution-preview`, `/apps/studio/rollback-preview`, `/apps/studio/publish-gate` form targets.
- `partials/apply_center.php` rollback forms to `/apps/studio/rollback-execute` and `/apps/studio/rollback`.
- `partials/rollback_panel.php` rollback execution rendering and block reason handling.
- Server-rendered apply, approval, snapshot, execution, preflight, publish-gate, and rollback result sections.
- Change/intelligence renderers that also toggle required fields or rollback snapshot notice.
- Legacy structured editor, visual builder, DB row editor, import/export, and library fetch behavior.

### Required Validation / Browser Checks For Future Apply Gate Slice

For a future apply-form-gate extraction, run:

- `php -l apps/Studio/Views/gui_studio.php`
- `node --check apps/Studio/assets/js/apply-form-gate.js`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- `git status --short`

Authenticated browser validation must cover:

- `/apps/studio`
- `/apps/studio/library`
- `/apps/studio/history`
- `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

Browser parity must additionally verify:

- Non-apply submit from `#gs-apply-form` still reaches preview approval unchanged.
- `#btn-apply` submit is blocked when analyze is not complete.
- `#btn-apply` submit is blocked when `#gs-apply-confirmation` is unchecked.
- `#btn-apply` submit is blocked when `#gs-apply-reason` is empty.
- Breaking-change/high-risk state still requires `input[name="risk_acknowledged"]`.
- `#gs-apply-gate-errors` renders the same escaped error list.
- Existing apply, approval, snapshot, rollback, preflight, publish-gate, loaded-resource, library, workflow/mode, edit workbench, and tool-preview behaviors show no console/page fatal and no raw localization keys.

## Change Intelligence / Governance Display-Sync JS Boundary Audit

Audit date: 2026-05-24
Current head commit: `f9ce7404`
Audit mode: strict read-only. No JavaScript extraction, no behavior change.

### Remaining JS Responsibility Map

| Area | Line range / anchors | Selectors / functions / events | Classification | Risk | Safe next? | Stop conditions |
|---|---|---|---|---|---|---|
| Change-intelligence DOM handles and labels | `9324-9420`; anchors `gs_compile_reason`, `gs_compile_risk_ack`, `gs_migration_override`, `gs_impact_confirmation`, `gs_simulation_override`, `gs-change-*` | Reads/writes compile, migration, impact, simulation, and change summary nodes; PHP-localized label constants feed renderers | Mixed display/form-state dependency | Medium | No by itself | Stop if a slice must move PHP-localized values, required-field handles, or shared state variables without also preserving all renderer call sites. |
| Client-side change intelligence computation | `9584-9900`; anchors `computeStructuredDiff`, `buildMigrationPlan`, `buildDependencyGraph`, `computeImpact`, `simulateFutureState` | Computes `diff`, `migration_plan`, `impact_analysis`, and `simulation_preview` from editor bundles and current structured state | Backend-payload-like computed state | Medium-High | No | Stop if extraction would alter risk/severity calculation, route/nav impact assumptions, generated migration strategy, or simulated broken-view warnings. |
| Simulation preview display-sync | `9902-9953`; anchor `renderSimulationPreview` | Updates `#gs-simulation-preview-body`, `#gs-simulation-warning`, `#gs_simulation_override`, `#gs_simulation_override_reason` | Mixed display/form-state | Medium-High | No as a first slice | Stop if extraction would combine table rendering with `required` semantics or simulation override gating. A future split must isolate pure row rendering first. |
| Impact analysis display-sync | `9955-10005`; anchor `renderImpactAnalysis` | Updates `#gs-impact-analysis-body`, `#gs-impact-warning`, `#gs_impact_confirmation`, `#gs_impact_acknowledged` | Mixed display/form-state | Medium-High | No as a first slice | Stop if high-impact detection or required confirmation/acknowledgment flags would move without a dedicated parity fixture. |
| Migration plan display-sync | `10007-10056`; anchor `renderMigrationPlan` | Updates `#gs-migration-plan-body`, `#gs-migration-warning`, `#gs_migration_override`, `#gs_migration_override_reason` | Mixed display/form-state | Medium-High | No as a first slice | Stop if destructive migration detection or override/reason required flags would move with table rendering. |
| Change summary and rollback notice sync | `10058-10151`; anchor `renderChangeSummary` | Updates `#gs-change-total`, `#gs-change-high`, `#gs-change-medium`, `#gs-change-low`, `#gs-change-summary-body`, `#gs-breaking-changes-banner`, `#gs-rollback-snapshot-notice`, `#gs-change-risk-note`, `#gs_compile_reason`, `#gs_compile_risk_ack` | Mixed display/apply-adjacent form-state | High | No | Stop if extraction touches rollback notice visibility, compile reason/risk acknowledgment requirements, or `#gs-change-high` because `apply-form-gate.js` reads that value. |
| Studio state commit dispatcher | `13930-13965`; anchor `commitStudioState` / `window.gsCommitStudioState` | Calls `updateHiddenStates`, all four change-intelligence renderers, visual builder refresh, content-aware visibility, outline/guide renderers, and dispatches `studio-state-committed` | Mixed state dispatcher | High | No | Stop if a slice changes renderer order, custom event timing, `window.gsStudioState`, hidden input updates, or visual-builder/content-outline refresh order. |
| Structured editor submit sync | `14334-14341`; anchor `form.addEventListener('submit')` | Calls `buildInternalJson`; blocks submit on builder binding errors; refreshes visual builder warning | Mixed display/action submit guard | High | No | Stop if extraction touches submit prevention, `buildInternalJson`, binding-error state, or form action routing. |
| Library/import load action path feeding change intelligence | `14695-14820`; anchors `data-load-module`, `data-load-library-node`, `loadBundleIntoEditor`, `window.fetch`, `window.alert` | Fetches backend bundles, loads editor state, triggers downstream `commitStudioState`, updates localStorage usage/recent state, displays alerts | Mixed fetch/action/backend payload | High | No | Stop if extraction touches fetch URLs, payload assumptions, alerts, localStorage recents, or load-to-editor behavior. |
| Content-aware governance visibility | `13460-13463`; duplicate compatibility anchor in `partials/legacy_content_visibility_script.php:194` | Hides `#gs-approval-meta-section` when table edit mode is active/direct DB; duplicated in legacy compatibility script | Display-only but duplicated/legacy-coupled | Medium | Maybe later, after duplicate-source audit | Stop if a slice leaves two competing visibility owners or changes direct DB/table-edit governance visibility. |
| Diff preview filters | `9270-9288`; anchors `#gs-diff-preview`, `data-diff-filter`, `data-diff-row` | Filters already-rendered diff rows by risk/ownership/drift select values | Display-only | Low-Medium | Possible but not change-intelligence/governance first choice | Stop if extraction is scoped to governance/change-intelligence. This is a separate low-risk diff-filter slice and should not be mixed with required-field governance. |
| Governance approval/readiness form | `8952-8998`; anchors `#gs-approval-gate`, `#gs-approval-decision`, `#gs-approval-reason`, `/apps/studio/snapshot-preview`, `/apps/studio/execution-preview`, `/apps/studio/rollback-preview`, `/apps/studio/publish-gate` | Server-rendered readiness labels and form submit targets | Mixed display/action form | High | No | Stop if extraction touches form actions, CSRF, approval/migration payloads, readiness chips, or disabled apply state. |
| Snapshot/execution/diff readiness result display | `9070-9238`; anchors `#gs-execution-preview`, snapshot artifacts, execution rows, diff readiness | Server-rendered PHP result display from `$snapshot`, `$execution`, `$diffReadiness` | Backend-payload dependent display | Medium-High | No for JS extraction | Stop if movement would require translating PHP-rendered result sections into client renderers or changing server payload contracts. |
| Preflight/publish-gate diagnostics | `apps/Studio/Views/partials/governance_diagnostics_panel.php:1-180`; anchor `#gs-governance-panel` | Server-rendered preflight/lint/gate checks, errors, rollback plan, publish decision, focused plan, checkpoints | Backend-payload dependent display | High | No | Stop if extraction touches `result` payload keys, publish decision rendering, rollback plan rendering, or preflight/publish-gate form targets. |
| Apply result and rollback binding display/actions | `apps/Studio/Views/partials/apply_center.php:1-164` | Server-rendered apply status, transaction steps, post-publish verification, rollback binding, rollback forms to `/apps/studio/rollback-execute` and `/apps/studio/rollback` | Mixed backend display/action forms | High | No | Stop if extraction touches apply result fields, post-publish verification, rollback binding, `apply_id`, `compile_id`, CSRF, or rollback POST forms. |
| Rollback preview/execution display | `apps/Studio/Views/partials/rollback_panel.php:1-152`; anchors `rollback_preview`, `#gs-rollback-execution` | Server-rendered rollback summary/artifacts, rollback execution status, block reasons, non-reversible warnings, step results | Backend-payload dependent display/action guardrail | High | No | Stop if extraction touches rollback block reasons, non-reversible warnings, status classes, steps, or execution payload assumptions. |
| Apply form gate duplicate check | `4704`, `4598-4672`, `apps/Studio/assets/js/apply-form-gate.js` | `#gs-apply-form` markup remains; JS gate is loaded once through inline require; no remaining inline `#gs-apply-form` submit gate found | Extracted, no duplicate gate | Low | Already done | Stop if a future slice reintroduces another apply submit listener or changes `#gs-change-high`, because the extracted gate depends on it. |

### Risk Matrix

| Risk | Blocks | Reason |
|---|---|---|
| Low | Apply-form-gate duplicate check | Extraction is complete and no duplicate inline submit gate remains. |
| Low-Medium | Diff preview filters (`9270-9288`) | Display-only row filtering, but it is not the change-intelligence/governance display-sync target and should be a separate slice. |
| Medium | Content-aware governance visibility (`13460-13463` plus legacy duplicate) | Display-only intent, but duplicate compatibility ownership must be resolved before moving. |
| Medium-High | Client-side change-intelligence computations (`9584-9900`), simulation/impact/migration renderers (`9902-10056`), snapshot/execution server result displays (`9070-9238`) | These are display-related but carry risk, required-field, or backend-payload semantics. |
| High | `renderChangeSummary` (`10058-10151`), `commitStudioState` (`13930-13965`), structured-editor submit guard (`14334-14341`), library/import fetch loaders (`14695-14820`), approval/governance forms (`8952-8998`), preflight/publish diagnostics, apply result, rollback preview/execution | These areas touch apply-adjacent state, submit ordering, fetch/action behavior, backend result payloads, destructive guardrails, or route/payload contracts. |

### Safest Next Extraction Candidate

No change-intelligence / governance display-sync slice is safe to extract next without additional splitting.

The only low-risk candidate found in the nearby remaining inline JavaScript is the diff preview row filter (`apps/Studio/Views/gui_studio.php:9270-9288`). It is display-only and uses `#gs-diff-preview`, `[data-diff-filter]`, and `[data-diff-row]`, but it is not part of change-intelligence required-field/governance display-sync. Treat it as a separate future slice only if the prompt explicitly targets diff filtering.

For change-intelligence itself, the recommended next implementation preparation is a read-only micro-audit of pure table-row rendering inside:

- `renderSimulationPreview`
- `renderImpactAnalysis`
- `renderMigrationPlan`
- `renderChangeSummary`

That micro-audit must identify whether a helper can render table rows without moving any `required` flag, warning visibility, rollback notice, `#gs-change-high`, submit guard, fetch, route, backend result, or governance action behavior.

### Explicit No-Move Areas

Do not move these in the next implementation slice:

- `#gs-change-high`, `#gs-rollback-snapshot-notice`, `#gs-change-risk-note`, `#gs_compile_reason`, or `#gs_compile_risk_ack` updates.
- `#gs_simulation_override`, `#gs_simulation_override_reason`, `#gs_impact_confirmation`, `#gs_impact_acknowledged`, `#gs_migration_override`, or `#gs_migration_override_reason` required-flag logic.
- `commitStudioState`, `window.gsStudioState`, `window.gsCommitStudioState`, `studio-state-committed`, or renderer call ordering.
- `form.addEventListener('submit')` on `#gs-structured-editor-form`.
- `loadBundleIntoEditor`, `data-load-module`, `data-load-library-node`, library/import fetches, alerts, or localStorage usage/recent state.
- Approval, snapshot, execution, rollback preview, publish-gate, preflight, apply result, rollback binding, and rollback execution result sections.
- Any form actions, `formaction` values, routes, CSRF inputs, hidden payload fields, backend payload keys, confirmation/alert behavior, or destructive rollback guardrails.

### Required Validation / Browser Checks For Any Future Slice

For any future implementation slice in this area, run:

- `php -l apps/Studio/Views/gui_studio.php`
- `node --check` for every new or changed Studio JS asset
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- `git status --short`

Authenticated browser validation must cover:

- `/apps/studio`
- `/apps/studio/library`
- `/apps/studio/history`
- `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

Browser parity must additionally verify:

- Change summary counts and rows remain identical for a high-risk diff fixture.
- `#gs-change-high` still drives the extracted apply form gate.
- Breaking-change banner and `#gs-rollback-snapshot-notice` visibility remain unchanged.
- Compile reason and risk acknowledgment required states remain unchanged.
- Simulation, impact, and migration warning visibility and required flags remain unchanged.
- Approval/snapshot/execution/rollback/preflight/publish/apply result rendering remains server-driven and unchanged.
- No apply, rollback, preflight, publish, fetch, route, backend payload, confirmation, alert, or result-rendering behavior changes.
- Already-extracted tool preview, loaded-resource context, library explorer, workflow/mode, edit workbench state, loaded-resource identity, and apply-form gate JS still load and behave.

## Pure Table-Row Rendering Micro-Audit

Audit date: 2026-05-24
Current head commit: `d0c4f47e`
Audit mode: strict read-only. No JavaScript extraction, no behavior change.

This audit inspected only the pure table-row rendering surface inside the four remaining mixed change-intelligence renderers in `apps/Studio/Views/gui_studio.php`.

### Renderer-By-Renderer Classification

| Renderer | Line range / anchors | Table selectors | Pure-rendering candidate | Mixed logic that blocks whole-renderer extraction | Risk | Tiny helper safe later? |
|---|---|---|---|---|---|---|
| `renderSimulationPreview` | `9902-9953`; anchors `views_after`, `broken_views`, `removed_fields`, `new_fields` | `#gs-simulation-preview-body` | Empty row rendering (`9910-9913`) and single summary row creation/replacement (`9914-9940`) can be isolated as `renderSimulationPreviewRows` or `renderSimulationPreviewTableRows`. It would need data arrays, `simulationReasonLabels`, and `emptySimulationLabel` passed in. | `hasBrokenViews` computation drives `#gs-simulation-warning`, `#gs_simulation_override.required`, and `#gs_simulation_override_reason.required` (`9943-9951`). Those are governance/submit-precondition state and must remain in the parent renderer. | Medium | Yes, only if the helper touches the table body and returns no gating state. |
| `renderImpactAnalysis` | `9955-10005`; anchors `impactRows`, `severity`, `affects` | `#gs-impact-analysis-body` | Empty row rendering (`9962-9965`) and row/chip creation for change, field/path, affects, and severity (`9966-9993`) can be isolated as `renderImpactAnalysisRows`. It would need `impactLabels`, `impactCategoryLabels`, and `emptyImpactLabel` passed in. | `hasHighImpact` detection drives `#gs-impact-warning`, `#gs_impact_confirmation.required`, and `#gs_impact_acknowledged.required` (`9957-9960`, `9996-10003`). Required flags are governance/apply precondition state and must not move with table rendering. | Medium | Yes. This is the safest first pure-row helper because its table rows are clearly separable from the required-flag block. |
| `renderMigrationPlan` | `10007-10056`; anchors `plan`, `strategy`, `risk` | `#gs-migration-plan-body` | Empty row rendering (`10013-10016`) and row/chip creation for action, field, strategy, and risk (`10017-10044`) can be isolated as `renderMigrationPlanRows`. It would need `migrationStrategyLabels`, `impactLabels`, and `emptyMigrationLabel` passed in. | `hasDestructive` detection drives `#gs-migration-warning`, `#gs_migration_override.required`, and `#gs_migration_override_reason.required` (`10009-10010`, `10047-10054`). Destructive migration gating must remain in the parent renderer. | Medium | Yes, after impact rows or together with it if the implementation stays row-only. |
| `renderChangeSummary` | `10058-10151`; anchors `summary`, `changes`, `stringifyValue` | `#gs-change-summary-body` | `stringifyValue` (`10059-10069`), empty row rendering (`10079-10082`), and change row/chip creation (`10083-10122`) can be isolated as `renderChangeSummaryRows`. It would need category labels, impact/severity labels, and `emptyChangesLabel` passed in. | Summary counter updates include `#gs-change-high` (`10072-10076`), which feeds `apply-form-gate.js`. `requiresEscalation` and `hasBreakingChanges` drive `#gs-breaking-changes-banner`, `#gs-rollback-snapshot-notice`, `#gs-change-risk-note`, `#gs_compile_reason.required`, and `#gs_compile_risk_ack.required` (`10125-10149`). Those apply/governance states must remain in the parent renderer. | Medium-High | Yes, but not first. It is safe only if the helper excludes summary counters, `#gs-change-high`, rollback notice, breaking banner, compile required flags, and escalation detection. |

### Candidate Helper Names

The following helper names are acceptable for a future implementation slice if the helper only mutates the table body passed to it:

- `renderImpactAnalysisRows`
- `renderMigrationPlanRows`
- `renderSimulationPreviewRows`
- `renderChangeSummaryRows`

An alternative shared helper such as `renderEmptyTableRow` may be extracted with them, but it must be purely presentational and must not know about governance, apply, rollback, preflight, publish, routes, payloads, or required fields.

### Explicit No-Move List

Do not move these in a pure table-row helper slice:

- `#gs-simulation-warning`, `#gs_simulation_override.required`, and `#gs_simulation_override_reason.required`.
- `#gs-impact-warning`, `#gs_impact_confirmation.required`, and `#gs_impact_acknowledged.required`.
- `#gs-migration-warning`, `#gs_migration_override.required`, and `#gs_migration_override_reason.required`.
- `#gs-change-total`, `#gs-change-high`, `#gs-change-medium`, and `#gs-change-low` counter updates.
- `#gs-breaking-changes-banner`, `#gs-rollback-snapshot-notice`, `#gs-change-risk-note`, `#gs_compile_reason.required`, and `#gs_compile_risk_ack.required`.
- `hasBrokenViews`, `hasHighImpact`, `hasDestructive`, `requiresEscalation`, and `hasBreakingChanges` behavior if any downstream warning, required-flag, rollback, or apply-gate state depends on it.
- `commitStudioState`, renderer call ordering, `studio-state-committed`, hidden state updates, submit guards, fetch/load paths, apply/publish/preflight/rollback routes, backend result payloads, confirmations, alerts, and server-rendered result sections.

### Recommended Next Implementation Slice

Safe later slice: extract only `renderImpactAnalysisRows` into a Studio-owned JS file, with the parent `renderImpactAnalysis` still owning:

- `hasHighImpact` detection
- `#gs-impact-warning` visibility
- `#gs_impact_confirmation.required`
- `#gs_impact_acknowledged.required`
- renderer call ordering through `commitStudioState`

Candidate future file name: `apps/Studio/assets/js/change-intelligence-table-rows.js`.

Recommended prompt scope for the next implementation:

Move only pure `#gs-impact-analysis-body` table row rendering from `renderImpactAnalysis` into `change-intelligence-table-rows.js`. Do not move warning visibility, required flags, risk detection, apply/governance/rollback/preflight/publish behavior, fetch/load behavior, `#gs-change-high`, or renderer ordering.

Stop instead of extracting if the implementation cannot keep the parent renderer responsible for all warning/required/governance state.
