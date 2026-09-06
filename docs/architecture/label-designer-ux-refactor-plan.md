# Label Designer UX Refactor Plan

**Status:** Planning-only. No implementation, no PHP changes, no route changes,
no resource model changes, no validation behavior changes, no snapshot behavior changes.

**Date:** 2026-06-12

**Builds on:**
- [Label Designer Workspace Structure Contract](label-designer-workspace-structure-contract.md)
- [Label Designer Operating Contract](label-designer-operating-contract.md)
- [Label Designer UX Architecture Audit](label-designer-ux-architecture-audit.md)
- [Label Resource Contract](label-resource-contract.md)
- [Label Designer Apply/Snapshot Safety Contract](label-designer-apply-snapshot-safety-contract.md)
- [Label Designer Template Apply/Snapshot Safety Contract](label-designer-template-apply-snapshot-safety-contract.md)
- [Label Rule Resource Contract](label-rule-resource-contract.md)
- [Label Designer Phase 1 Runtime Proof Plan](label-designer-phase1-runtime-proof-plan.md)

---

## Part A — Current State Baseline

### Page Size

`apps/Studio/Tools/LabelDesigner/Views/preview.php`: **2,687 lines** across a
single file with no partial views, no import structure, and no layout
inheritance.

### Section Classification (14 sections in a single `<dl>`)

| # | Section | Classification | Lines (approx.) |
|---|---|---|---|
| 1 | Architecture (ownership, paths, Platform, Core) | Reference material | 40 |
| 2 | Future Label Types | Reference material | 20 |
| 3 | Label Resource Contracts | Reference material | 40 |
| 4 | Existing Owner Label Resources (discovery) | Reference material | 60 |
| 5 | Bootstrap DB Discovery Mode | Support — context creation | 100 |
| 6 | Guarded Context Creation (create form + JSON preview + validation) | Primary — context workflow | 130 |
| 7 | Template Creation Preview (create form + JSON preview + validation) | Primary — template workflow | 120 |
| 8 | Ownership Guard | Reference material | 15 |
| 9 | Label Resource Readiness (+ folder creation form) | Maintenance | 140 |
| 10 | Resource Metadata & Migration (analysis + migration preview + migration apply) | Maintenance | 400 |
| 11 | Label Preview Renderer (context/template select + diagnostics + rules + rendered HTML) | Support — shared preview | 190 |
| 12 | Rule Preview Sandbox (ephemeral rule simulation + modified preview) | Support — rule preview | 190 |
| 13 | Rule Creation Preview (create form + JSON preview + diagnostics) | Primary — rule workflow | 220 |
| 14 | Reference Implementation (routes, defaults, field order, layout) | Reference material | 70 |
| — | Inline locale dictionary (EN/JA/NE, ~700 keys) | Data | 700 |
| — | PHP model extraction (~45 model variables) | Bootstrap | 200 |
| — | Inline JS (`toggleRuleEffectTarget`) | Script | 27 |

### POST Handler Count

All 9 POST handlers are registered in `apps/Studio/routes.php` and implemented
in `apps/Studio/Controllers/StudioController.php`:

| # | Route | Handler | Workspace |
|---|---|---|---|
| 1 | `POST /apps/studio/tools/label-designer/context/create` | `labelDesignerCreateContext()` | Contexts |
| 2 | `POST /apps/studio/tools/label-designer/create-folders` | `labelDesignerCreateResourceFolders()` | Maintenance |
| 3 | `POST /apps/studio/tools/label-designer/template/create` | `labelDesignerCreateTemplate()` | Templates |
| 4 | `POST /apps/studio/tools/label-designer/rule/create` | `labelDesignerCreateRule()` | Rules |
| 5 | `POST /apps/studio/tools/label-designer/preview/render` | `labelDesignerRenderPreview()` | Templates / Rules |
| 6 | `POST /apps/studio/tools/label-designer/preview/rule-sandbox` | `labelDesignerRuleSandbox()` | Rules |
| 7 | `POST /apps/studio/tools/label-designer/migration-preview` | `labelDesignerMigrationPreview()` | Maintenance |
| 8 | `POST /apps/studio/tools/label-designer/migration-apply` | `labelDesignerApplyMetadataMigration()` | Maintenance |
| 9 | `GET /apps/studio/tools/label-designer` | `labelDesignerPreview()` (via View) | All (landing page) |

### Diagnostics Surfaces (5+ independent tables)

| Location | Table purpose | Locale key prefix |
|---|---|---|
| Preview Renderer | Validation diagnostics | `preview_diagnostics_*` |
| Rule Sandbox | Rule validation diagnostics | `rule_sandbox_diagnostics_*` |
| Rule Create | Rule creation diagnostics | `rule_create_diagnostics_*` |
| Migration Preview | Migration check diagnostics | inline severity/check/message |
| Migration Apply | Post-apply diagnostics | inline severity/check/message |
| Context Preview | Inline validation status | `contextPreviewValidation` (inline PASS/CHECK_REQUIRED) |
| Template Preview | Inline check list | `templatePreviewValidation` (inline PASS/FAIL) |

Each diagnostics table independently implements matching PASS/WARN/FAIL/ERROR
severity styling, row coloring, and severity color variable references. There
is no shared diagnostics partial.

### Preview Surfaces (2 rendered outputs)

| Surface | Source | Owner |
|---|---|---|
| Label Preview Renderer | `buildResolvedPreview()` → `renderHtmlPreview()` | Templates workspace |
| Rule Sandbox Modified Preview | `modified_html` from rule sandbox result | Rules workspace (duplicate) |

The sandbox preview is a separate HTML string obtained from
`LabelDesignerRuleSandboxService::evaluate()` and rendered in a second
`<div class="label-renderer-preview-container">`. It does not share state
with the primary preview output.

### Maintenance Workflows

| Workflow | Location in page | Destructive |
|---|---|---|
| Resource Readiness table | Between Ownership Guard and Metadata | No |
| Create Missing Folders form | Attached to Readiness table | Yes (creates dirs) |
| Discovery listing | Between Contracts and DB Discovery | No |
| Metadata Analysis | Between Folder Creation and Preview Renderer | No |
| Migration Preview | Inline after Metadata Analysis | No |
| Migration Apply | Inline after Migration Preview (same card) | Yes (writes files) |

### Model Data (45+ variables extracted from `$model`)

Key groups:
- Reference/architecture data (9 vars)
- Discovery/readiness data (8 vars)
- Context creation data (8 vars)
- Template creation data (10 vars)
- Preview renderer data (10 vars)
- Rule sandbox data (2 vars)
- Rule creation data (15 vars)
- Migration data (3 vars)
- Flash/session data (1 var)
- CSRF

---

## Part B — Refactor Goals

### Preserve

- **Context workflow**: Owner/source selection → field selection → context key →
  JSON preview → validation → confirmation → create with snapshot
- **Template workflow**: Context selection → size selection → field selection →
  JSON preview → validation → confirmation → create with snapshot
- **Rule workflow**: Context/template selection → condition + effect config →
  JSON preview → validation → confirmation → create with snapshot
- **Migration workflow**: Metadata analysis → resource selection → migration
  preview → confirmation → migration apply with snapshot + backup
- **Validation workflow**: Pre-create validation with PASS/WARN/FAIL/ERROR
  severity; FAIL/ERROR blocks create. No validation logic changes.
- **Snapshot workflow**: Snapshot-before-write for context, template, rule
  creation and migration apply. No snapshot logic changes.
- **All 9 POST handler contracts**: Request inputs, session keys, response
  format, flash behavior.
- **All 700+ locale keys**: EN/JA/NE dictionary remains intact.
- **Existing boundary gate** (`check_label_designer_boundaries.sh`): Must
  continue to pass with zero changes after each phase.

### Improve

- **Navigation**: Replace flat `<dl>` scroll with tab-based workspace navigation
- **Workflow separation**: Each resource type (context/template/rule) has its
  own workspace; maintenance is a separate workspace
- **Discoverability**: Authoring actions are at the top-level tab, not buried
  between maintenance sections
- **Cognitive load**: Each workspace shows only its relevant content (no
  architecture docs in authoring, no rule forms in context creation)
- **Preview ownership**: One preview engine, one rendered output, shared across
  Templates and Rules workspaces
- **Diagnostics consistency**: One diagnostics table per workspace instead of
  5+ independently styled tables

### Explicitly Prohibit

- **Architecture changes**: No resource model, ownership model, validation
  contract, runtime contract, or snapshot contract changes
- **Route changes**: Canonical route remains `/apps/studio/tools/label-designer`.
  Tab state is a URL query parameter (`?workspace=`), not a route prefix
- **Resource schema changes**: No changes to context/template/rule JSON schemas
- **Runtime changes**: No print, export, QR, or barcode behavior
- **Ownership changes**: Studio remains the workbench owner; apps/modules/plugins
  remain resource owners
- **POST handler URL changes**: All 9 form actions remain at their current paths
- **POST handler behavior changes**: No changes to validation, snapshot,
  confirmation, or write logic
- **Session key changes**: No changes to `$_SESSION['studio_label_designer_preview']`
  or other session data contracts

---

## Part C — Phase 1: Workspace Shell

### Goal

Introduce 4-tab navigation (Contexts, Templates, Rules, Maintenance) as a
pure HTML/CSS wrapper around the existing 14-section `<dl>`. All sections,
forms, and handlers remain in the DOM — only visibility changes.

### Behavior

- **Navigation tab bar** added at the top of the page, below the title/subtitle
- **URL query parameter** `?workspace=contexts|templates|rules|maintenance`
  controls active tab
- **Server-side default**: if `?workspace=` is absent or invalid, default to
  `?workspace=contexts` (the primary authoring workflow)
- **Each section group** wrapped in a `<div>` with CSS class
  `ld-workspace` and data attribute `data-workspace="contexts"` (etc.)
- **CSS**: `.ld-workspace:not(.ld-active) { display: none; }`
- **Active tab visual**: `.ld-tab.ld-active` with distinct styling
- **No JavaScript**: Tab switching is a GET form or anchor link that
  reloads the page with `?workspace=` parameter
- **No section content is moved, removed, or reordered** in this phase

### Section-to-Workspace Mapping (Phase 1 — grouping only, no moves)

| Current section | Wrapped in workspace |
|---|---|
| Architecture | Contexts (temporary — moved in Phase 4) |
| Future Label Types | Contexts (temporary) |
| Resource Contracts | Contexts (temporary) |
| Existing Owner Label Resources (discovery) | Contexts (temporary) |
| Bootstrap DB Discovery Mode | Contexts |
| Guarded Context Creation | Contexts |
| Template Creation Preview | Templates |
| Ownership Guard | Contexts (temporary) |
| Label Resource Readiness + Folder Creation | Maintenance |
| Resource Metadata & Migration | Maintenance |
| Label Preview Renderer | Templates |
| Rule Preview Sandbox | Rules |
| Rule Creation Preview | Rules |
| Reference Implementation | Maintenance (temporary) |
| Studio Home link | All workspaces (always visible in footer) |

### Deliverables

1. **Tab navigation HTML** — `<nav class="ld-tabs">` with 4 `<a>` links,
   each pointing to `?workspace=<name>`. Active tab has class `ld-active`.
2. **CSS** — Tab bar styling + workspace visibility rules. Placed in a
   new `<style>` block in preview.php or extracted to a new asset file
   (`assets/label-designer.css`).
3. **PHP workspace determination** — `$activeWorkspace` variable derived
   from `$_GET['workspace']` with default to `'contexts'`.
4. **Wrapper divs** — Each `<dd>` section gets wrapped in
   `<div class="ld-workspace" data-workspace="..." id="ld-workspace-...">`.
5. **Footer** — Studio Home link wrapped outside all workspace divs so it
   appears on every tab.
6. **Boundary gate update** — `check_label_designer_boundaries.sh` updated to
   verify workspace HTML/CSS patterns exist but NO sections are removed or
   reordered (invariant: all 14 section title locale keys still present).

### Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| Broken tab CSS hides all sections | Low | Server-side fallback: if `$activeWorkspace` is invalid, show all sections (no tab wrapper). CSS uses `display: none` on non-active; removing the CSS shows all content. |
| GET parameter conflicts with existing `?owner=` and `?template_context=` params | Low | The existing GET params control form pre-selection, not visibility. Workspace param is independent. Both can coexist in the URL. |
| Tab links cause form state loss | Low | Each workspace's GET form already uses `<form method="get">` with action `/apps/studio/tools/label-designer`. Tab links are `<a>` tags, not forms. Form state is preserved per workspace (workspace param is added to existing URL). |
| Boundary gate false negative for section presence | Low | Gate checks locale keys, not DOM structure. Wrapping sections in divs does not remove keys. |

### Validation Requirements

1. **Browser**: All 4 tabs render and show/hide correct content
2. **Browser**: Default tab (Contexts) is active on fresh page load
3. **Browser**: `?workspace=invalid` falls through to show all sections (or defaults to Contexts)
4. **Form POST**: All 9 POST handlers work from any tab (forms are still in DOM)
5. **GET pre-selection**: `?owner=Manufacturing` still pre-selects owner dropdown
6. **Flash messages**: Flash still renders after redirect (flash is session-based, not tab-dependent)
7. **Boundary gate**: `bash scripts/architecture/check_label_designer_boundaries.sh` passes
8. **Architecture gates**: `bash scripts/architecture/run_architecture_gates.sh` passes

### Rollback

- **Phase 1 revert**: Remove tab navigation HTML, remove workspace wrapper divs,
  restore CSS, restore `$activeWorkspace` removal. No server-side logic changes
  beyond the PHP variable and wrapper divs.
- **Risk**: Zero. All sections and forms remain unchanged in DOM. Revert is
  purely HTML/CSS.

### Classification

**Low-risk UI reorganization only** — no POST handler, route, model, validation,
snapshot, or session changes. Pure HTML/CSS/PHP template change.

---

## Part D — Phase 2: Preview Consolidation

### Goal

Eliminate the duplicate rendered preview in the Rule Sandbox section. The
sandbox applies rule effects to the session-stored preview output instead of
producing a separate HTML string.

### Current State

- **Preview Renderer** (`preview/render` POST handler): calls
  `LabelDesignerPreviewRendererService::buildResolvedPreview()` →
  `renderHtmlPreview()`, stores result in
  `$_SESSION['studio_label_designer_preview']`
- **Rule Sandbox** (`preview/rule-sandbox` POST handler): The sandbox
  result includes `modified_html` — a full HTML preview string with rule
  effects applied, rendered in a **second** `<div class="label-renderer-preview-container">`
  at line 2353 of preview.php

### Future State

- **Preview Renderer**: Unchanged — still calls `buildResolvedPreview()` →
  `renderHtmlPreview()` and stores HTML in session
- **Rule Sandbox**: Reads the session-stored preview HTML, applies rule
  effects as CSS/injection annotations to the **same** preview element,
  and renders only **one** `<div class="label-renderer-preview-container">`
- **No `modified_html`** in sandbox result. Sandbox returns only diagnostics
  and effect metadata. Effect application happens client-side via CSS class
  toggles or inline style injections on the existing preview DOM

### Implementation Approach

**Option A (server-side, recommended):**
- Sandbox POST handler applies effects to session-stored preview HTML on
  the server side and returns the modified HTML as the single preview output
- Template workspace preview is replaced with sandbox preview when
  sandbox diagnostics exist in session
- Both workspaces display from the **same** session-scoped preview key

**Option B (client-side):**
- Sandbox POST handler returns effect metadata (field to hide, badge text, etc.)
- JS applies effects to the existing preview DOM via class toggles
- No server-side HTML manipulation for rules

### Deliverables

1. Remove `modified_html` from `LabelDesignerRuleSandboxService::evaluate()`
   return value (or keep but stop rendering it in a separate container)
2. Remove the second `label-renderer-preview-container` block from preview.php
   (lines 2351-2356)
3. Update the Rules workspace to render only one preview, sourced from
   the session-stored preview or the sandbox response
4. Add "No preview rendered" message in Rules workspace when session has no
   preview data, with link to Templates workspace

### Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| Preview state synchronization failure | Medium | Session-scoped preview already exists. Only the sandbox read path changes. |
| Sandbox effects lost on template re-render | Low | Same as current behavior — re-rendering preview clears sandbox effects. Session-stored preview persists across tab switches. |
| User enters Rules tab without preview | Low | Show explicit message + link to Templates tab. No data loss — sandbox form handles missing preview gracefully. |

### Validation Requirements

1. **Template workspace**: Preview renders and displays correctly (unchanged)
2. **Rules workspace**: Sandbox effects still apply to preview
3. **Rules workspace**: Only one `label-renderer-preview-container` exists in DOM
4. **Session**: Preview persists across tab switches (Templates → Rules → Templates)
5. **No preview state**: Rules workspace shows "no preview" message when preview
   has not been rendered
6. **Boundary gate**: All existing invariants still pass

### Rollback

- **Revert**: Restore sandbox `modified_html` output and the second preview
  container. Remove session-scoped shared preview read in sandbox handler.
- **Risk**: Low. Only template rendering changes; no data loss.

---

## Part E — Phase 3: Diagnostics Consolidation

### Goal

Replace 5+ independent diagnostics tables with one shared table per workspace.
Each workspace aggregates all relevant diagnostics into a single array and
renders one table with visually separated rowsets.

### Current Diagnostics Locations

| Workspace | Diagnostics | Current location |
|---|---|---|
| Contexts | Context validation (inline PASS/CHECK_REQUIRED) | Inline in context creation card |
| Templates | Template validation (inline PASS/FAIL list) | Inline in template creation card |
| Templates | Preview renderer diagnostics table | In preview renderer card |
| Rules | Sandbox diagnostics table | In rule sandbox card |
| Rules | Rule creation diagnostics table | In rule creation card |
| Maintenance | Migration preview checks table | In migration card |
| Maintenance | Migration apply diagnostics table | In migration apply section |

### Future Diagnostics Model

| Workspace | Diagnostics scope | Table count |
|---|---|---|
| Contexts | Context validation + context create validation | 1 table, 2 rowsets |
| Templates | Template validation + preview validation | 1 table, 2 rowsets |
| Rules | Sandbox validation + rule create validation | 1 table, 2 rowsets |
| Maintenance | Migration preview checks + migration apply diagnostics | 1 table per action (2 distinct tables — preview vs apply are different stages) |

### Implementation Approach

1. **Back-end**: Each workspace's controller handler aggregates diagnostics
   into a single `$workspaceDiagnostics` array. Different validation stages
   are distinguished by a `stage` field or check_key prefix (e.g.,
   `TC-V001` for template create, `TP-V001` for template preview).
2. **View**: Shared diagnostics partial (`_diagnostics_table.php`) that
   accepts an array of diagnostics and renders a single PASS/WARN/FAIL/ERROR
   table. Each workspace calls this partial once.
3. **Stage separation**: A `<thead>` separator row or a column showing the
   stage name distinguishes validation sources within the same table.

### Deliverables

1. Create `_diagnostics_table.php` partial view
2. Update Contexts workspace: aggregate context validation + create validation
   into one table
3. Update Templates workspace: aggregate template validation + preview
   validation into one table
4. Update Rules workspace: aggregate sandbox validation + create validation
   into one table
5. Remove inline diagnostics tables and inline validation lists from
   individual cards
6. Update locale dictionary: remove per-section diagnostics keys, add shared
   diagnostics keys (if needed)

### Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| Loss of stage distinction when merging diagnostics | Medium | Use check_key prefix convention (e.g., CC-V001 for context create, CV-V001 for context validation). Add stage column or section header row. |
| Diagnostics aggregation leaks across POST handlers | Low | Each POST handler returns its own diagnostics. The view aggregates only diagnostics available in the current model. |
| Shared partial introduces rendering regression across all workspaces | Medium | Test each workspace independently. The partial is a simple foreach over [{severity, check_key, message}]. |

### Validation Requirements

1. **Contexts**: Validation checks display correctly (same checks, same severity)
2. **Templates**: Template validation + preview diagnostics display in one table
3. **Rules**: Sandbox + create diagnostics display in one table
4. **Maintenance**: Migration preview + apply diagnostics remain in separate tables
5. **FAIL/ERROR blocking**: Create buttons still block on FAIL/ERROR diagnostics
6. **Stage distinction**: User can tell which validation stage produced each check
7. **Locale keys**: All severity labels (PASS/WARN/FAIL/ERROR) and table headers
   still render correctly
8. **Boundary gate**: All existing invariants still pass

### Rollback

- **Revert**: Restore individual diagnostics tables, remove shared partial,
  restore per-section locale keys. No validation or handler changes.
- **Risk**: Low. All diagnostics data is preserved in model; only rendering
  changes.

---

## Part F — Phase 4: Maintenance Separation (Optional)

### Goal

Physically move maintenance sections out of authoring workspace divs and into
the Maintenance workspace div. Reference material sections are collapsed or
removed from the main content flow.

### Section Movements

| Current section | Current workspace (Phase 1) | Move to |
|---|---|---|
| Architecture | Contexts (temporary) | Collapsed `<details>` at page bottom |
| Future Label Types | Contexts (temporary) | Collapsed `<details>` at page bottom |
| Resource Contracts | Contexts (temporary) | Collapsed `<details>` at page bottom |
| Existing Owner Label Resources (discovery) | Contexts (temporary) | **Remove** — duplicated by Readiness table and DB Discovery |
| Bootstrap DB Discovery Mode | Contexts | Stay in Contexts (belongs to context creation workflow) |
| Ownership Guard | Contexts (temporary) | Collapsed `<details>` at page bottom |
| Reference Implementation | Maintenance (temporary) | Collapsed `<details>` at page bottom |
| Label Resource Readiness | Maintenance | Already in Maintenance |
| Create Missing Folders | Maintenance | Already in Maintenance |
| Resource Metadata & Migration | Maintenance | Already in Maintenance |
| Preview Renderer | Templates | Stay in Templates |
| Rule Sandbox | Rules | Stay in Rules |
| Rule Creation Preview | Rules | Stay in Rules |

### Archive Strategy

Reference material sections (Architecture, Future Types, Contracts, Ownership
Guard, Reference Implementation) are wrapped in a single `<details>` element
at the bottom of the page, **collapsed by default**. The details element is
visible from all workspaces but does not interfere with default content.

### Benefits

- **Authoring workspaces are clean**: No architecture documentation or
  reference data in the default content flow
- **Maintenance is centralized**: All infrastructure scans, folder creation,
  and migration tools are in one workspace
- **Cognitive load reduction**: Contexts workspace shows only context creation;
  Templates workspace shows only template creation; Rules workspace shows
  only rule creation

### Risks

| Risk | Severity | Mitigation |
|---|---|---|
| PHP variable dependency across section boundaries | Medium | Section movement requires careful PHP `<?php` block isolation. Each section block must be self-contained — no variable computed in one section and used in another. |
| Discovery removal removes functionality | Low | Discovery is read-only and duplicates Readiness table and DB Discovery. No workflow depends on it. |
| Architecture docs are less discoverable | Low | Collapsed by default but still present. Link from footer or inline help text. |

### Why Optional

Phase 4 is the highest-effort phase (physical section rearrangement) with the
highest risk of accidentally breaking PHP variable references. Phase 1 alone
already achieves workspace separation via visibility control. Phase 4 is
a "cleanup" phase that can be deferred or skipped without affecting the core
improvement.

Recommended: **Defer until Phase 1-3 are stable in production.** If the
visible-only workspace model proves sufficient, Phase 4 may not be needed.

### Validation Requirements

1. **Browser**: All workspaces render without 500 errors
2. **Browser**: Moved sections are in correct workspace
3. **Browser**: Collapsed reference sections are collapsed by default
4. **Form POST**: All 9 POST handlers still work from their new workspace
5. **GET params**: Existing `?owner=`, `?template_context=`, `?rule_context_id=`
   still pre-select correct values
6. **Boundary gate**: All existing invariants still pass

### Rollback

- **Revert**: Restore original section order and workspace mapping. Phase 1
  workspace divs remain; only content within them changes.
- **Risk**: Medium — section reordering could break PHP scope dependencies.
  Recommended approach: move entire PHP blocks as units, not individual
  HTML fragments.

---

## Part G — File Impact Analysis

### Primary File

| File | Lines | Impact |
|---|---|---|
| `apps/Studio/Tools/LabelDesigner/Views/preview.php` | 2,687 | All 4 phases modify this file |

### Potential Support Files

| File | Impact |
|---|---|
| `apps/Studio/Tools/LabelDesigner/assets/label-designer.css` | **Create** for Phase 1 tab/workspace CSS (or inline `<style>`) |
| `apps/Studio/Tools/LabelDesigner/Views/_diagnostics_table.php` | **Create** for Phase 3 shared diagnostics partial |
| `apps/Studio/Tools/LabelDesigner/Views/_workspace_tabs.php` | **Optionally create** for reusable tab bar |
| `apps/Studio/Controllers/StudioController.php` | **No changes expected** — model data unchanged |
| `apps/Studio/routes.php` | **No changes** |
| `scripts/architecture/check_label_designer_boundaries.sh` | **Minor updates** — workspace HTML pattern invariants |
| `apps/Studio/Tools/LabelDesigner/manifest.php` | **Minor update** — Phase 1 workspace status text |
| `apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuleSandboxService.php` | **Phase 2 only** — remove `modified_html` from return |
| `apps/Studio/Tools/LabelDesigner/lang/en.php` | **No changes** — locale keys stay in preview.php |

### Complexity Estimate

| Phase | Complexity | Blast Radius | Testing Effort |
|---|---|---|---|
| Phase 1: Workspace Shell | Low | preview.php only | Low — visual + form smoke |
| Phase 2: Preview Consolidation | Medium | preview.php + sandbox service | Medium — preview state sync |
| Phase 3: Diagnostics Consolidation | Medium | preview.php + new partial | Medium — diagnostics parity |
| Phase 4: Maintenance Separation | Medium-High | preview.php only | Medium — PHP scope boundaries |

### Highest-Risk Areas

1. **Phase 4 section movement**: PHP blocks that compute variables in one
   section and use them in another. Risk of undefined variable warnings or
   broken output if section order changes.
2. **Phase 2 preview state**: Session-scoped preview must be read correctly
   from the Rules workspace. Risk of stale or missing preview data.
3. **Phase 3 diagnostics aggregation**: Merging diagnostics arrays must
   preserve severity, check_key, and message. Risk of dropped diagnostics
   if aggregation logic is incorrect.
4. **Phase 1 tab default**: Default workspace must not hide critical
   information on first visit. Default to Contexts (primary authoring
   workflow).

---

## Part H — Validation Strategy

### Per-Phase Validation

#### Phase 1: Workspace Shell

- [ ] **Navigation**: All 4 tabs render and are clickable
- [ ] **Default tab**: Contexts tab is active on first visit (no `?workspace=` param)
- [ ] **Tab switch**: Clicking each tab reloads page with correct `?workspace=` param
- [ ] **Content visibility**: Only active workspace content is visible
- [ ] **All sections present**: All 14 sections exist in DOM (inspected via browser tools)
- [ ] **Form POST from any tab**: Submit context/create from Contexts tab → success
- [ ] **Form POST from non-owning tab**: Submit context/create from Templates tab
  (form is in Contexts workspace div, but can tab to Contexts first) → success
- [ ] **GET pre-selection preserved**: `?workspace=contexts&owner=Manufacturing`
  pre-selects Manufacturing owner
- [ ] **Flash messages**: Flash renders after redirect (session-based)
- [ ] **Boundary gate**: `bash scripts/architecture/check_label_designer_boundaries.sh` passes
- [ ] **Architecture gates**: `bash scripts/architecture/run_architecture_gates.sh` passes

#### Phase 2: Preview Consolidation

- [ ] **Preview render**: Templates workspace context+template select → "Render Preview"
  → rendered HTML displayed
- [ ] **Rules preview loads**: Rules workspace shows same rendered preview after
  template workspace rendered it
- [ ] **Sandbox effects apply**: Rule sandbox form → effects applied to single preview
- [ ] **No duplicate preview**: Only one `label-renderer-preview-container` in DOM
- [ ] **No preview state**: Rules workspace shows "No preview" message when no
  preview has been rendered
- [ ] **Preview persists**: Switch Templates → Rules → Templates, preview still renders
- [ ] **Boundary gate passes**

#### Phase 3: Diagnostics Consolidation

- [ ] **Contexts diagnostics**: Context validation checks appear in single table
- [ ] **Templates diagnostics**: Template + preview validation checks in single table
- [ ] **Rules diagnostics**: Sandbox + create validation checks in single table
- [ ] **Stage separation**: Diagnostics from different stages are distinguishable
- [ ] **Severity preserved**: PASS/WARN/FAIL/ERROR colors and labels match current
- [ ] **FAIL/ERROR blocking**: Create buttons still block on FAIL/ERROR diagnostics
- [ ] **Migration diagnostics**: Migration preview + apply tables still separate
- [ ] **Boundary gate passes**

#### Phase 4: Maintenance Separation

- [ ] **No 500 errors**: All workspaces render without PHP errors
- [ ] **Correct sections**: Maintenance workspace shows Readiness, Folder Creation,
  Discovery, Metadata, Migration
- [ ] **Authoring workspaces clean**: No maintenance sections in Contexts/Templates/Rules
- [ ] **Reference material collapsed**: Architecture, contracts, future types in
  `<details>` — collapsed by default
- [ ] **All 9 POST handlers work**: Each handler reachable from correct workspace
- [ ] **GET pre-selection works**: `?owner=`, `?template_context=`, `?rule_context_id=`
  still pre-select correct values
- [ ] **Boundary gate passes**

### Workflow Validation

| Workflow | POST handler | Phase 1 | Phase 2 | Phase 3 | Phase 4 |
|---|---|---|---|---|---|
| Context create | `context/create` | ✅ | ✅ | ✅ | ✅ |
| Template create | `template/create` | ✅ | ✅ | ✅ | ✅ |
| Rule create | `rule/create` | ✅ | ✅ | ✅ | ✅ |
| Preview render | `preview/render` | ✅ | ✅ | ✅ | ✅ |
| Rule sandbox | `preview/rule-sandbox` | ✅ | ✅ | ✅ | ✅ |
| Migration preview | `migration-preview` | ✅ | ✅ | ✅ | ✅ |
| Migration apply | `migration-apply` | ✅ | ✅ | ✅ | ✅ |
| Create folders | `create-folders` | ✅ | ✅ | ✅ | ✅ |

### Snapshot Validation

- [ ] Context create creates snapshot (check `storage/studio-snapshots/label-designer/`)
- [ ] Template create creates snapshot
- [ ] Rule create creates snapshot
- [ ] Migration apply creates snapshot + backup

### Route Validation

- [ ] `GET /apps/studio/tools/label-designer` serves page with tab navigation
- [ ] `GET /apps/studio/tools/label-designer?workspace=contexts` serves Contexts tab
- [ ] `GET /apps/studio/tools/label-designer?workspace=templates` serves Templates tab
- [ ] `GET /apps/studio/tools/label-designer?workspace=rules` serves Rules tab
- [ ] `GET /apps/studio/tools/label-designer?workspace=maintenance` serves Maintenance tab
- [ ] All 9 POST routes unchanged (no URL changes)

---

## Part I — Rollback Strategy

### Independent Rollback

Each phase is independently rollbackable without affecting other phases:

| Phase | Rollback action | Side effects |
|---|---|---|
| Phase 1 | Remove tab HTML, workspace wrapper divs, tab CSS | Phase 2-4 dependencies break (they assume workspace divs exist) |
| Phase 2 | Restore sandbox `modified_html`, restore second preview container | None on Phase 1 (workspace divs unchanged) |
| Phase 3 | Restore individual diagnostics tables, remove shared partial | None on Phase 1-2 |
| Phase 4 | Restore original section order | None on Phase 1-3 |

### Rollback Requirements

- **No resource migrations**: Rollback is code-only. No database, file system,
  or resource changes.
- **No route rollback**: Routes remain unchanged in all phases. Rollback does
  not touch `routes.php`.
- **No schema rollback**: No database or resource schema changes.
- **No data loss**: All session data, snapshot data, and resource files are
  unaffected by rollback.
- **Git revert**: Each phase is a separate commit. Rollback is `git revert <commit>`.

### Phase 1 Rollback Procedure

```bash
# 1. Revert preview.php to remove tab HTML and wrapper divs
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/Views/preview.php

# 2. Revert CSS changes
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/assets/  # if created

# 3. Verify boundary gate
bash scripts/architecture/check_label_designer_boundaries.sh
```

### Phase 2 Rollback Procedure

```bash
# 1. Revert sandbox service changes
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/Services/LabelDesignerRuleSandboxService.php

# 2. Revert preview.php changes (remove shared preview, restore second container)
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/Views/preview.php
```

### Phase 3 Rollback Procedure

```bash
# 1. Remove shared diagnostics partial
rm apps/Studio/Tools/LabelDesigner/Views/_diagnostics_table.php

# 2. Revert preview.php to restore inline diagnostics tables
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/Views/preview.php
```

### Phase 4 Rollback Procedure

```bash
# 1. Revert preview.php to restore original section order
git checkout HEAD~1 -- apps/Studio/Tools/LabelDesigner/Views/preview.php
```

---

## Part J — Success Criteria

### Measurable Outcomes

| Criterion | Current | Target | Measurement |
|---|---|---|---|
| Workspace mixing | 14 sections in one flat `<dl>` | 4 distinct workspaces | Count top-level nav items |
| Navigation hierarchy | Scroll-based (no navigation) | Tab-based (4 tabs) | Tabs visible and functional |
| Maintenance separation | 5+ maintenance sections mixed with authoring | All maintenance in one workspace | Maintenance sections only in Maintenance tab |
| Preview ownership | 2 rendered previews (renderer + sandbox) | 1 rendered preview, shared | Count `label-renderer-preview-container` elements |
| Diagnostics duplication | 5+ independent diagnostics tables | 1 table per workspace (max 4 total) | Count diagnostics `<table>` elements |
| Cognitive load per workspace | All 14 sections visible at once | Contexts: 2 sections, Templates: 2, Rules: 2, Maintenance: 4 | Count visible sections per active tab |
| Reference material in workflow | 5 reference sections in default view | 0 reference sections in default view (collapsed) | Reference sections visible without expansion |
| Locale dictionary size | ~700 keys | ~700 keys (unchanged) | `grep -c "'[a-z_]'"` locale blocks |
| POST handler count | 9 | 9 (unchanged) | `grep -c "post('" routes.php` |
| Route count | 1 GET + 8 POST | 1 GET + 8 POST (unchanged) | Count route registrations |
| Boundary gate invariants | ~694 | ~700+ (adds workspace patterns) | `scripts/architecture/check_label_designer_boundaries.sh` |

### Without Altering Architecture

All success criteria must be achieved without:

- Changing resource ownership
- Changing resource schemas (context/template/rule JSON)
- Changing validation behavior or diagnostics data model
- Changing snapshot behavior
- Changing POST handler URLs or contracts
- Changing PHP service classes (except sandbox `modified_html` removal)
- Changing route registrations
- Changing session key contracts
- Adding new POST handlers
- Adding new GET routes
- Changing the boundary gate's existing invariants (only adding new ones)

---

## Part K — Readiness Classification

### Classification

**A — Phase 1 implementation allowed.**

### Rationale

1. **Phase 1 is pure HTML/CSS/PHP template change.** No service classes,
   routes, POST handlers, validation logic, snapshot logic, or session
   contracts change. The existing page content is wrapped, not modified.

2. **All phases preserve architecture.** The workspace contract explicitly
   prohibits architecture, route, resource, validation, and snapshot changes.
   Each phase is designed to be verifiable against the existing boundary gate.

3. **Independent rollback.** Each phase is a separate commit with a clear
   one-command revert path. No data migration or schema changes required.

4. **Existing audit supports A.** The workspace structure contract classified
   the UX refactor as A. The remaining prerequisite was this plan document.

5. **Boundary gate compatibility.** Phase 1 adds new invariants (workspace
   HTML/CSS patterns) but does not remove or change any existing invariants.

### Prerequisites

- [x] Label Designer Workspace Structure Contract
- [x] Label Designer UX Refactor Plan (this document)
- [ ] Workspace boundary gate update (future `check_label_designer_workspaces.sh`
  or extended boundary gate)

### Remaining Before Phase 1

- **Boundary gate update**: Add workspace HTML/CSS presence invariants to
  `check_label_designer_boundaries.sh` or create a new gate
  `check_label_designer_workspaces.sh`
- **CSS file decision**: Inline `<style>` vs `assets/label-designer.css`
- **Workspace default decision**: `contexts` as default (recommended)

---

## Delivery Summary

| Item | Value |
|---|---|
| Plan path | `docs/architecture/label-designer-ux-refactor-plan.md` |
| Implementation phases | Phase 1 (Workspace Shell) → Phase 2 (Preview Consolidation) → Phase 3 (Diagnostics Consolidation) → Phase 4 (Maintenance Separation, Optional) |
| File impact | Primary: `preview.php`. Support: new CSS file, new diagnostics partial, minor sandbox service change |
| Risk assessment | Phase 1: Low. Phase 2-3: Medium. Phase 4: Medium-High |
| Validation strategy | Per-phase browser + workflow + diagnostics + snapshot + route + boundary gate checks |
| Rollback strategy | Independent per-phase `git revert` with no data migration |
| Readiness classification | **A — Phase 1 implementation allowed** |

---

## Recommended Next Slice

**Label Designer UX Refactor Phase 1 Implementation**

Scope:
1. Add tab navigation HTML to preview.php
2. Add workspace wrapper divs around existing sections
3. Add CSS for tab bar + workspace visibility
4. Add `$activeWorkspace` PHP variable with `?workspace=` param parsing
5. Default to `contexts` workspace
6. Update boundary gate with workspace HTML/CSS invariants
7. Validate: all tabs render, all forms POST, boundary gate passes
