# Label Designer Workspace Structure Contract

**Status:** Contract-only. No implementation, no PHP changes, no route changes,
no runtime changes, no resource model changes.

**Date:** 2026-06-12

**Builds on:**
- [Label Designer UX Architecture Audit](label-designer-ux-architecture-audit.md)
- [Label Designer Operating Contract](label-designer-operating-contract.md)
- [Label Resource Contract](label-resource-contract.md)
- [Label Designer Template Apply Snapshot Safety Contract](label-designer-template-apply-snapshot-safety-contract.md)
- [Label Designer Apply Snapshot Safety Contract](label-designer-apply-snapshot-safety-contract.md)
- [Label Rule Resource Contract](label-rule-resource-contract.md)
- [Label Validation Contract](label-validation-contract.md)
- [Label Runtime Contract](label-runtime-contract.md)

---

## Part A — Purpose

The Label Designer page has grown to 2,687 lines as a single flat `<dl>`
with 14 sections, 9 POST handlers, 5+ independent diagnostics tables, and
2 rendered preview outputs. Sections of fundamentally different intent
(documentation, diagnostic scan, authoring wizard, destructive migration)
are mixed in the same scroll surface with no progressive disclosure.

Workspace separation exists to:

- **Partition by resource ownership.** Contexts, templates, and rules are
  separate resource types with separate lifecycle, validation, and creation
  workflows. Each workspace owns one resource type.
- **Reduce cognitive load per workspace.** A user authoring a context does
  not need to see rule sandbox forms or metadata migration tables. A user
  running maintenance does not need to see architecture documentation.
- **Prevent accidental writes.** Destructive actions (folder creation,
  metadata migration apply) currently appear inline alongside preview forms
  and diagnostic tables. Workspace separation creates a deliberate navigational
  boundary before write actions.
- **Eliminate preview duplication.** The current page renders a label preview
  in the Preview Renderer section and a second modified preview in the Rule
  Sandbox section. A shared preview model prevents divergent states.
- **Eliminate diagnostic fragmentation.** Each section independently
  implements PASS/WARN/FAIL/ERROR tables with near-identical HTML. A shared
  diagnostics model per workspace removes duplication.

### What workspace separation must preserve

- **Context resources.** Owner-owned JSON files at `{OwnerRoot}/Resources/labels/contexts/`.
  Same create workflow, validation, snapshot-before-write contract.
- **Template resources.** Owner-owned JSON files at `{OwnerRoot}/Resources/labels/templates/`.
  Same field selection, context binding, size selection workflow.
- **Rule resources.** Owner-owned JSON files at `{OwnerRoot}/Resources/labels/rules/`.
  Same guarded create-only flow with FAIL/ERROR blocking.
- **Validation model.** 14+ checks per resource type with PASS/WARN/FAIL/ERROR severity.
  No validation logic changes.
- **Ownership model.** Apps/modules/plugins own label resources. Studio owns
  only the builder/editor workbench. No ownership transfer.
- **Snapshot model.** Snapshot-before-write for context, template, and rule
  creation. Migration apply also creates snapshots. No snapshot logic changes.

### What workspace separation must not do

- **Simplify architecture.** The resource model, ownership model, validation
  contract, and runtime contract remain exactly as defined in existing contracts.
- **Create a business-user mode.** All workspaces are technical-user surfaces.
  No role-based simplification, no wizard-like abstraction, no "easy mode."
- **Change routes.** Canonical route remains `/apps/studio/tools/label-designer`.
  No new route prefixes, no route parameter changes.
- **Change resource locations.** Context/template/rule paths remain unchanged.
- **Change the POST handler contract.** Each create action still requires
  snapshot, validation, confirmation, and owner-boundary enforcement.

---

## Part B — Workspace Classification

Using the UX Architecture Audit as source of truth, each current section
is classified into one of four categories:

### Primary Workflows

Authoring actions a user arrives to perform. Each maps to one resource type.

| Section | Resource type | Classification rationale |
|---|---|---|
| Context Creation Preview | Context | User defines a label's business purpose, fields, and data source boundary. |
| Template Creation Preview | Template | User composes a label layout from a context, chooses fields and size. |
| Label Preview Renderer | Context + Template + Rule | User visually verifies the composed label before or after creation. |
| Rule Creation Preview | Rule | User attaches conditional presentation logic to a label template. |

### Support Workflows

Enable or inform primary workflows without creating resources.

| Section | Classification rationale |
|---|---|
| Bootstrap DB Discovery Mode | Provides candidate data sources and columns for context field selection. Support for context creation. |
| Rule Preview Sandbox | Ephemeral in-memory rule simulation. Support for rule creation. |
| Label Resource Readiness (diagnostic part) | Shows folder structure completeness per owner. Informs whether contexts/templates/rules can be created. |

### Maintenance Workflows

Infrastructure and data management. Not part of daily authoring.

| Section | Classification rationale |
|---|---|
| Resource Metadata & Migration (analysis) | Audits legacy resources missing explicit `owner_key`. |
| Resource Metadata & Migration (preview) | Shows per-resource impact of adding `owner_key`. |
| Resource Metadata & Migration (apply) | Writes `owner_key` to a resource file with snapshot/backup. Destructive. |
| Create Missing Folders | One-time folder scaffolding per owner. Infrastructure action with lifecycle guard. |

### Reference Material

Documentation that should never appear in an authoring or maintenance workflow.

| Section | Classification rationale |
|---|---|
| Architecture (ownership, paths, Platform, Core) | Static architecture documentation. Not a workflow. |
| Future Label Types | Aspirational list. Not actionable. |
| Label Resource Contracts | Links to contract documents. Reference only. |
| Existing Owner Label Resources (discovery) | Raw filesystem dump. Duplicates information in Readiness and DB Discovery. |
| Ownership Guard | Restated architecture principle. |
| Reference Implementation (routes, defaults, field order, layout) | Manufacturing QR-specific reference data. Not general Label Designer functionality. |

---

## Part C — Navigation Model

### Canonical Navigation

```
Label Designer
├── Contexts
├── Templates
├── Rules
└── Maintenance
```

### Rationale

**Resource-centric navigation aligns with ownership.**

Label Designer's architecture is organized around three resource types:
contexts, templates, and rules. Each is owned by the same owner, stored
under the same `{OwnerRoot}/Resources/labels/` tree, validated by the same
validation contract family, and created through the same guarded create flow.
A resource-centric nav model maps directly to this architecture.

Each workspace owns exactly one resource type:
- **Contexts** — context resource lifecycle (create, preview, validate)
- **Templates** — template resource lifecycle (create, preview, validate)
- **Rules** — rule resource lifecycle (sandbox, create, validate)
- **Maintenance** — everything else (readiness, folder creation, discovery,
  metadata analysis, migration)

### Rejected Navigation Models

**Compose tab.** Rejected because "compose" implies a multi-resource assembly
workflow (select context → create template → attach rules) that does not match
the architecture. Contexts and templates are independent resources with
independent lifecycles. A compose tab would re-mix the three resource types
that workspace separation is designed to isolate.

**Preview tab.** Rejected because preview is a shared service consumed by the
Templates and Rules workspaces, not a standalone workspace. Making Preview a
top-level tab would require the user to navigate away from their authoring
context to see output. Preview belongs inside Templates and Rules.

**Admin tab.** Rejected because it implies user administration or role
management. The Maintenance workspace contains infrastructure actions (folder
creation, migration) that are part of the Label Designer tool's scope, not
system administration. "Maintenance" more accurately describes the intent.

### Navigation Behavior

- Each tab replaces the workspace content entirely. No section stacking within
  a tab.
- Active tab is persisted via URL query parameter (e.g., `?workspace=contexts`).
  No client-side tab state.
- Tab switch is a full-page navigation. No dynamic tab switching to avoid
  hidden form state and stale preview state.
- The Maintenance tab is visually distinct (e.g., secondary styling or position)
  to indicate it is not an authoring workflow.

---

## Part D — Context Workspace

### Scope

The Contexts workspace contains every action and display related to label
context resources. Nothing else.

### Must Contain

- **Owner/DB source selection.** Select owner → choose bootstrap candidate
  source → preview available columns.
- **Field selection.** Checkbox-based field selection from the candidate source.
- **Context key input.** Explicit `context_key` text input for the JSON file name.
- **JSON preview.** Read-only rendered JSON of the context resource that would
  be created.
- **Validation output.** Diagnostics table showing PASS/WARN/FAIL/ERROR for
  context-specific validation checks.
- **Create action.** POST form with confirmation checkbox, snapshot-before-write
  notice, and submit button (disabled on FAIL/ERROR).
- **Flash/result display.** Success or error flash after create, with link back
  to Contexts workspace.

### Must Not Contain

- Template creation forms or JSON preview
- Rule sandbox or rule creation forms
- Resource readiness tables
- Metadata analysis or migration tools
- Architecture documentation
- Reference implementation data
- Rule diagnostics

### Expected Layout

```
[Contexts] [Templates] [Rules] [Maintenance]

Context Workspace
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 1 — Select Owner & Source
  [Owner dropdown] [Source dropdown]

Step 2 — Select Fields
  [field:checkbox] [field:checkbox] [field:checkbox] ...

Step 3 — Configure Context Key
  [context_key text input]

Step 4 — Preview & Validate
  [JSON preview block]
  [Validation diagnostics table — collapsible]

Step 5 — Create
  [Confirmation checkbox] [Create button — disabled if FAIL/ERROR]
```

---

## Part E — Template Workspace

### Scope

The Templates workspace contains every action and display related to label
template resources. It consumes the shared preview service for visual
verification but does not own the preview engine.

### Must Contain

- **Context selection.** Dropdown of existing context resources filtered by
  compatible owner.
- **Size selection.** Dropdown of label sizes.
- **Field arrangement.** Checkbox or reorderable field list from the selected
  context.
- **JSON preview.** Read-only rendered JSON of the template resource.
- **Validation output.** Diagnostics table for template-specific checks.
- **Preview integration.** Rendered visual preview using the shared preview
  engine, shown after validation passes.
- **Create action.** POST form with confirmation checkbox, snapshot-before-write
  notice, and submit button.
- **Flash/result display.** Success or error flash after create.

### Must Not Contain

- Context creation forms
- Rule creation forms or sandbox
- Migration tools
- Architecture documentation
- Reference implementation data
- Independent preview engine (must use shared service)

### Expected Layout

```
[Contexts] [Templates] [Rules] [Maintenance]

Template Workspace
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Step 1 — Select Context
  [Context dropdown] shows owner :: context_key

Step 2 — Select Size
  [Size dropdown]

Step 3 — Select Fields
  [field:checkbox] [field:checkbox] ... from selected context

Step 4 — Preview & Validate
  [JSON preview block]
  [Validation diagnostics table — collapsible]

Step 5 — Visual Preview
  [Rendered label preview — from shared preview engine]
  [Rule match summary — inline annotations on preview]

Step 6 — Create
  [Confirmation checkbox] [Create button — disabled if FAIL/ERROR]
```

---

## Part F — Rules Workspace

### Scope

The Rules workspace contains the rule preview sandbox (ephemeral prototype)
and the guarded rule creation workflow. It consumes the shared preview engine
but does not render a second preview.

### Must Contain

- **Context/template selection.** Dropdowns to select which context and template
  the rule will apply to.
- **Rule sandbox.** Temporary in-memory rule form: condition field, operator,
  comparison value, effect type, effect target, effect value.
- **Sandbox output.** Rule validation diagnostics, condition evaluation status,
  effect application status.
- **Sandbox preview integration.** Effects applied to the existing shared
  preview output. No separate rendered HTML preview block.
- **Rule creation workflow.** Full create form with rule key, condition,
  effect, enabled toggle.
- **Rule JSON preview.** Read-only JSON of the rule resource.
- **Rule creation validation.** Diagnostics table with FAIL/ERROR blocking.
- **Create action.** POST form with confirmation checkbox, snapshot-before-write
  notice, submit button.

### Must Not Contain

- Context creation forms or field selection
- Template creation forms or size/field selection
- A second rendered preview (must annotate the existing shared preview)
- Migration tools
- Reference implementation data

### Rule-Preview Relationship

The rules workspace does not own a preview renderer. It receives the rendered
preview output from the shared preview engine (owned by Preview Model — see
Part H) and applies rule effects as CSS/injections to that output. The user
sees:

1. Rendered preview from shared engine (context + template combination)
2. Rule sandbox form below the preview
3. After sandbox evaluation: the same preview with effect annotations applied
   (badges, hidden fields, warnings, style token overrides)

This prevents the current problem of two independent `<div>` elements
containing two different previews of the same label.

### Expected Layout

```
[Contexts] [Templates] [Rules] [Maintenance]

Rules Workspace
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section A — Rule Sandbox (Ephemeral)
  Step 1: Select Context & Template
  Step 2: Configure condition (field, operator, value)
  Step 3: Configure effect (type, target, value)
  Step 4: Evaluate → see effect annotations on preview below
  [Shared preview with effect annotations]
  [Sandbox diagnostics]

  ---

Section B — Rule Creation (Persistent)
  Step 1: Select Context & Template
  Step 2: Rule key input
  Step 3: Condition configuration
  Step 4: Effect configuration
  Step 5: Enabled toggle
  Step 6: Preview & Validate
    [Rule JSON preview]
    [Rule creation diagnostics — collapsible, FAIL/ERROR blocking]
  Step 7: Create
    [Confirmation checkbox] [Create button]
```

---

## Part G — Maintenance Workspace

### Scope

The Maintenance workspace contains all infrastructure scans, diagnostic
displays, and destructive write actions. No authoring workflows live here.

### Must Contain

- **Resource Readiness.** Table of all owners with folder-existence status
  per owner (labels/, contexts/, templates/, rules/). Summary counts.
- **Create Missing Folders.** Owner selector (lifecycle owners only), confirmation
  checkbox, submit button.
- **Discovery Scan.** Read-only listing of existing resource files found on
  filesystem, organized by owner.
- **Metadata Analysis.** Summary counts (contexts/templates, metadata-first vs
  legacy fallback, coherent vs incoherent).
- **Migration Preview.** Per-resource selector, preview of `owner_key` addition,
  validation checks table.
- **Migration Apply.** Preview-required guard, confirmation checkbox, snapshot/
  backup display, post-apply diagnostics.

### Destructive-Action Boundaries

| Action | Boundary | Guard |
|---|---|---|
| Create Missing Folders | `POST /apps/studio/tools/label-designer/create-folders` | Lifecycle owner check + confirmation checkbox. Creates empty directories only. |
| Migration Apply | `POST /apps/studio/tools/label-designer/migration-apply` | Preview required before apply. Confirmation checkbox. Snapshot + backup created before write. |

Both destructive actions must be visually separated from non-destructive
scans and previews. Recommended: a `<hr>` or section heading, or a secondary
page within the Maintenance workspace.

### Explicitly Removed from Authoring Workspaces

- Readiness table (currently next to template creation)
- Discovery listing (currently at top of page, before any workflow)
- Metadata analysis (currently between folder creation and preview renderer)
- Migration preview + apply (currently between metadata analysis and preview)
- Folder creation form (currently attached to readiness table)

### Expected Layout

```
[Contexts] [Templates] [Rules] [Maintenance]

Maintenance Workspace
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section A — Infrastructure Status
  [Resource Readiness table] — full scrollable table
  [Create Missing Folders] — only if incomplete owners exist
  [Discovery Scan] — read-only file listing

  ---

Section B — Metadata & Migration
  [Metadata Analysis summary] — counts and status per owner
  [Legacy resources list] — resources missing owner_key

  ---

Section C — Migration (Destructive)
  [Migration Preview] — select resource, see diff
  [Migration Apply] — only after preview, with confirmation
  [Post-apply diagnostics]
```

---

## Part H — Shared Preview Model

### Ownership

The preview engine is owned by the Templates workspace. The Templates workspace
provides the context/template selection form and displays the rendered output.
The Rules workspace receives the rendered output from the Templates workspace
and applies rule effects to it.

### Single Engine Rule

There is exactly one preview engine. Its canonical implementation is:

- **Service:** `LabelDesignerPreviewRendererService::buildResolvedPreview()`
- **Output type:** `ResolvedLabelPreview` value object
- **Render method:** `LabelDesignerPreviewRendererService::renderHtmlPreview()`
- **POST handler:** `StudioController::labelDesignerRenderPreview()`

### Sharing Contract

| Consumer | Receives | May modify | Must not |
|---|---|---|---|
| Templates workspace | Full rendered HTML + resolved model | — | Modify the preview engine output. Store preview ID for create action. |
| Rules workspace | Rendered HTML from Templates workspace | Apply CSS/injection effects to the HTML | Re-render the preview. Create a second `ResolvedLabelPreview`. Store independent preview state. |

### State Synchronization

- The Templates workspace stores the last rendered context_id, template_id,
  and HTML preview in session scope (current behavior).
- The Rules workspace reads the same session keys instead of storing its own
  copies via hidden POST fields.
- If no preview exists when the Rules workspace is entered, the Rules workspace
  shows a message: "Render a preview in the Templates workspace first."
- Changing context/template in the Rules workspace triggers a redirect to the
  Templates workspace with the new parameters pre-selected.

### Must Prevent

- **Duplicate preview renderers.** No second `renderHtmlPreview()` call from
  the Rules workspace or any other workspace.
- **Divergent preview states.** If both Templates and Rules could hold
  independent preview state, the user could see different output in each tab.
  Session-scoped shared state prevents this.
- **Hidden field synchronization.** The current sandbox stores
  `preview_context_id` and `preview_template_id` as hidden POST fields that
  can become stale. The shared session state eliminates this pattern.

---

## Part I — Shared Diagnostics Model

### Ownership

Each workspace owns its diagnostics display. There is no cross-workspace
diagnostics panel.

### Consolidation Principle

Each workspace has exactly one diagnostics table, not one per sub-action.

| Workspace | Diagnostics scope | Renders |
|---|---|---|
| Contexts | Context validation + context creation validation | One table, two rowsets separated by `<hr>` or section heading |
| Templates | Template validation | One table |
| Rules | Sandbox validation + Rule creation validation | One table, two rowsets separated visually |
| Maintenance | Migration preview checks + Migration post-apply diagnostics | Two tables (preview vs apply are different stages) |

### Table Structure

```
Severity  |  Check  |  Message
───────── | ─────── | ────────────────────
PASS      | V-001   | Context key is valid
WARN      | V-002   | No template references this context
FAIL      | V-003   | Owner not found
ERROR     | V-004   | Path traversal detected
```

Each workspace may choose to expand or collapse the table by default:

| Workspace | Default state | Rationale |
|---|---|---|
| Contexts | Expanded | User needs to see validation before create |
| Templates | Expanded | User needs to see validation before create |
| Rules | Collapsed (sandbox) / Expanded (create) | Sandbox is scratch; create needs validation visibility |
| Maintenance | Expanded | Migration preview is intentionally visible before destructive action |

### Elimination of Duplicate Tables

The current page has diagnostics tables in at least 5 locations:
- Preview Renderer (`preview_diagnostics_title`)
- Rule Sandbox (`rule_sandbox_diagnostics_title`)
- Rule Create (`rule_create_diagnostics_title`)
- Migration Preview (inline checks)
- Migration Apply (inline checks)

Under the workspace model:
- Contexts workspace → 1 table
- Templates workspace → 1 table
- Rules workspace → 1 table (sandbox + create rowset)
- Maintenance workspace → 1 table per action (preview + apply are distinct,
  but both belong to the Maintenance workspace)

This reduces the number of diagnostics tables from 5+ to 4, each in a
different workspace with no duplication of the PASS/WARN/FAIL/ERROR rendering
pattern within a workspace.

---

## Part J — Documentation & Reference Material

### What moves out of default workflow

| Current section | New location |
|---|---|
| Architecture (ownership, paths, Platform, Core) | Collapsible details element at the bottom of the Label Designer page. Default state: collapsed. |
| Future Label Types | Collapsible details element, same section as architecture. |
| Resource Contracts | Link to contract docs in a small "Contracts" footer. Not inline content. |
| Ownership Guard | Remove duplicate. The workspace model's resource-centric nav already communicates ownership boundaries. |
| Existing Owner Label Resources (discovery) | Remove entirely. Duplicated by Maintenance workspace Readiness table and DB Discovery Mode. |
| Reference Implementation (routes, defaults, field order, layout) | Move to a single "Reference" collapsible section in the Maintenance workspace. Only visible when expanded. |

### Archive Strategy

Documentation and reference material should be:

1. **Collapsed by default** in a `<details>` element.
2. **Not persisted** across page navigations. Each page load starts collapsed.
3. **Linked** to the actual architecture contract documents in
   `docs/architecture/` rather than duplicated inline.

### What stays visible

- Navigation tabs (always visible)
- Active workspace content
- Flash messages (transient, displayed on redirect)

---

## Part K — Migration Strategy

### Guiding Principles

- No functionality changes in any phase
- No route changes
- No resource model changes
- No changes to POST handler logic or validation
- Each phase must be independently verifiable via existing boundary gates
- Each phase must leave the page in a working state

### Phase 1 — Workspace Shell

**Goal:** Wrap the existing 14-section page in a tab navigation shell without
moving any content.

**Changes:**
1. Add tab navigation HTML (Contexts, Templates, Rules, Maintenance).
2. Add URL query parameter `?workspace=` to select active tab.
3. Wrap each existing section group in a `<div>` that is hidden when its
   tab is not active.
4. All existing sections remain in the DOM. Only visibility changes.
5. No sections are removed, reordered, or modified.

**What does not change:**
- PHP model keys (all existing model data is still sent to the view)
- POST handler URLs
- Form structures
- Diagnostics tables (still duplicated within their sections)

**Risk:** Low. Tab navigation is pure HTML/CSS with no server-side changes.

**Verification:** Existing boundary gate passes unchanged. All 9 POST handlers
still reachable. Flash messages still render.

---

### Phase 2 — Preview Consolidation

**Goal:** Eliminate the duplicate rendered preview in the Rule Sandbox section.

**Changes:**
1. Store the last rendered preview HTML in session scope (already partially
   implemented via `$_SESSION['studio_label_designer_preview']`).
2. Remove the sandbox's independent `renderHtmlPreview()` call.
3. Sandbox effects now apply to the session-stored preview HTML instead of
   a fresh render.
4. Remove the second `label-renderer-preview-container` from the sandbox output.

**What does not change:**
- Template preview renderer behavior
- Rule sandbox effect evaluation logic
- Diagnostics tables (consolidated in Phase 3)
- POST handler contracts

**Risk:** Medium. Preview state synchronization requires careful session key
management. Mitigation: session-scoped storage already exists; only the sandbox
read path changes.

**Verification:** Template preview still renders. Sandbox effects still apply
to the same preview. Boundary gate remains unchanged.

---

### Phase 3 — Diagnostics Consolidation

**Goal:** Replace independent diagnostics tables with one shared table per
workspace.

**Changes:**
1. Each workspace aggregates its diagnostics into a single array before
   rendering.
2. Remove the `<table>` HTML from individual sections.
3. Render one `<table>` per workspace with rowsets for different validation
   stages.
4. Template preview diagnostics, sandbox diagnostics, and rule creation
   diagnostics each contribute to their workspace's single table.

**What does not change:**
- Diagnostics data model (severity, check_key, message structure)
- Validation service methods
- PASS/WARN/FAIL/ERROR severity values
- Render-blocking behavior (FAIL/ERROR still blocks create)

**Risk:** Medium. Aggregation requires back-end changes to combine diagnostic
arrays. Diagnostics from different validation stages must remain distinguishable
(by check_key prefix or stage label).

**Verification:** All validation checks still emit correct severity. Create
buttons still block on FAIL/ERROR. Diagnostics display correctly in the single
table.

---

### Phase 4 — Section Relocation (Optional)

**Goal:** Physically move section HTML from current positions to their
workspace assignments.

**Changes:**
1. Move Resource Readiness to Maintenance workspace div.
2. Move DB Discovery to Contexts workspace div.
3. Move Metadata & Migration to Maintenance workspace div.
4. Move Reference Implementation to collapsible footer.
5. Remove or collapse documentation sections.

**What does not change:**
- Any server-side logic
- POST handler URLs
- Model data keys

**Risk:** Low-Medium. Template edits only. Risk of accidentally breaking
PHP variable references if section boundaries are not clear.

**Verification:** All 9 POST handlers still render correct output. Boundary
gate passes. No 500 errors on any workspace.

---

## Part L — Risks

### 1. Preview Coupling (Phase 2)

**Risk:** The Rules workspace depends on a preview rendered by the Templates
workspace. If the user enters the Rules workspace without first rendering a
preview, no preview is available.

**Severity:** Medium

**Mitigation:**
- Show a clear message: "No preview available. Render a preview in the
  Templates workspace first."
- Link directly to the Templates workspace with the current context/template
  pre-selected.
- Session-scoped preview persists across tab switches, so the user only needs
  to render once per session (or until context/template changes).

### 2. Diagnostics Consolidation (Phase 3)

**Risk:** Merging multiple diagnostics arrays into one table could lose the
distinction between validation stages (e.g., sandbox PASS vs creation FAIL).

**Severity:** Medium

**Mitigation:**
- Use a stage prefix or a `stage` column in the diagnostics table.
- Example: `RS-V001` (sandbox validation) vs `RC-V001` (creation validation).
- Visually separate rowsets with a section header row within the table.

### 3. Migration Workflow Relocation (Phase 4)

**Risk:** Moving migration preview and apply from their current inline position
(next to the main content) to a separate Maintenance tab could make the
migration feature harder to discover.

**Severity:** Low

**Mitigation:**
- The current inline position is already hard to discover (buried between
  folder creation and preview renderer).
- A dedicated Maintenance tab with a clear "Migration" heading is more
  discoverable than the current scroll position.
- Migration is not a daily workflow. Some discoverability loss is acceptable
  to reduce cognitive load in authoring workspaces.

### 4. User Retraining

**Risk:** Existing users accustomed to the flat scrollable page may be confused
by tab navigation.

**Severity:** Low

**Mitigation:**
- The tab structure mirrors the resource ownership model. Users already think
  in terms of contexts, templates, and rules from the architecture contracts.
- Tab labels match resource type names, not abstract workflow labels.
- Progressive disclosure reduces, not increases, the information density per
  view.

### 5. Hidden State Dependencies

**Risk:** Session-scoped preview state could become stale if the user modifies
a context or template after rendering the preview.

**Severity:** Low

**Mitigation:**
- This is the current behavior. The preview is a snapshot at render time.
- Workspace separation does not change this. The preview state contract remains
  the same.
- Future enhancement (not in scope): invalidate preview when context or
  template is modified.

### 6. Tab State URL Persistence

**Risk:** If tab state is persisted in the URL via query parameter, bookmarking
or sharing a URL could send someone to a workspace with stale data.

**Severity:** Low

**Mitigation:**
- Tab state is visual only. Each workspace performs fresh data discovery on
  page load (same as current behavior).
- No cached or preloaded data per workspace.

---

## Part M — Readiness Classification

**A — UX refactor planning allowed.**

### Why A

1. **No architecture conflicts.** The workspace model maps directly to the
   existing three-resource ownership model (contexts, templates, rules). No
   resource contract requires modification.
2. **No route changes.** The canonical route `/apps/studio/tools/label-designer`
   remains unchanged. Tab state is a query parameter, not a route prefix.
3. **No POST handler changes.** All 9 existing handlers continue to accept
   the same inputs and return the same outputs.
4. **No validation model changes.** Diagnostics structure, severity levels,
   and render-blocking behavior remain identical.
5. **No resource model changes.** Context, template, and rule resource shapes
   remain as defined in existing contracts.
6. **Phase 1 is pure HTML/CSS.** Tab navigation adds zero server-side risk.
7. **Phase 2-3 have clear rollback.** Session-scoped preview already exists.
   Diagnostics aggregation can be reverted by restoring the individual tables.
8. **Documentation sections are already non-functional.** Moving them to
   collapsed state removes zero functionality.

### Remaining Prerequisites

- Label Designer UX Refactor Plan (implementation sequence, file-level changes,
  test strategy)
- Workspace boundary gate update (future `check_label_designer_workspaces.sh`
  or equivalent)

---

## Delivery Summary

| Item | Value |
|---|---|
| Contract path | `docs/architecture/label-designer-workspace-structure-contract.md` |
| Workspace model | 4 workspaces: Contexts, Templates, Rules, Maintenance |
| Navigation hierarchy | Resource-centric: Contexts → Templates → Rules → Maintenance (secondary) |
| Preview model | Single preview engine owned by Templates workspace; Rules workspace applies effects to shared output |
| Diagnostics model | One diagnostics table per workspace, not per sub-action; rowsets distinguish stages |
| Migration strategy | Phase 1 (Workspace Shell) → Phase 2 (Preview Consolidation) → Phase 3 (Diagnostics Consolidation) → Phase 4 (Section Relocation, optional) |
| Readiness classification | **A — UX refactor planning allowed** |
| Recommended next slice | Label Designer UX Refactor Plan |
