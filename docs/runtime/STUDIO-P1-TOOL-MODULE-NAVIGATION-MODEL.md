# Studio P1.5 Tool / Module Navigation Model

## Date

- 2026-05-23

## Status

- Planning only (docs-only).
- No runtime UI implementation in this slice.

## Purpose

Define Studio's internal tool navigation model so users can clearly distinguish:

1. Studio tools (orchestration/work surfaces inside Studio)
2. Business app/module resources (owner-owned runtime artifacts)

This model exists to prevent ownership confusion before any P1.5 runtime labels/navigation are introduced.

## Ownership And Boundary Rules

1. Studio is a governed worker/tool.
2. Studio does not become runtime owner of business modules, views, widgets, reports, or schema.
3. Owner app/module remains source-of-truth for business/runtime meaning.
4. Shell composes runtime experiences; Studio does not grant permissions or rewrite ownership boundaries.
5. Apply is governed handover back to owners, not Studio ownership conversion.

---

## Studio Internal Tool Modules

### 1) Resource Explorer / Library

- Purpose:
  - Find, search, filter, inspect, and load resources into Studio context.
- What it owns:
  - Discovery/view-state only (search/filter/sort/selection state).
- What it must not own:
  - Resource business meaning, runtime behavior, or ownership metadata truth.
- Allowed resources:
  - App/module/view/navigation/widget/report/schema references and manifests for inspection/load.
- Future UI entry point:
  - Explore > Resource Explorer.
- Safe first implementation:
  - Non-functional navigation label and descriptive copy only.
- Hard limits:
  - No mutation by Explorer itself.

### 2) App Builder

- Purpose:
  - Create/inspect app-level contracts and app manifests.
- What it owns:
  - Studio draft/edit session state for app contract authoring.
- What it must not own:
  - Runtime app ownership after apply/handover.
- Allowed resources:
  - App manifest drafts, route contract descriptors, app metadata forms.
- Future UI entry point:
  - Build > App Builder.
- Safe first implementation:
  - Label/group placement and owner-boundary help text.
- Hard limits:
  - No direct runtime app mutation in this planning phase.

### 3) Module Builder

- Purpose:
  - Create/inspect/edit module-owned resources in a governed Studio flow.
- What it owns:
  - Draft session state for module contract edits.
- What it must not own:
  - Business module runtime ownership or module meaning.
- Allowed resources:
  - Module manifests, module settings, module contract payloads.
- Future UI entry point:
  - Build > Module Builder.
- Safe first implementation:
  - Non-functional label chips and contextual ownership note.
- Hard limits:
  - No runtime ownership reassignment.

### 4) View / Layout Builder

- Purpose:
  - Inspect/edit view and layout resources under owner contracts.
- What it owns:
  - Studio draft layout state and view editing session metadata.
- What it must not own:
  - Final runtime ownership of view semantics.
- Allowed resources:
  - View definitions, layout structures, component binding drafts.
- Future UI entry point:
  - Build > View / Layout Builder.
- Safe first implementation:
  - Navigation label and read-only guidance text.
- Hard limits:
  - No direct owner bypass; no hidden apply path.

### 5) Navigation / Menu Tool

- Purpose:
  - Inspect/edit navigation contributions safely.
- What it owns:
  - Draft navigation contribution edits in Studio context.
- What it must not own:
  - Shell composition ownership or business navigation meaning.
- Allowed resources:
  - Nav/menu contribution artifacts and route link references.
- Future UI entry point:
  - Build > Navigation / Menu Tool.
- Safe first implementation:
  - Label and explanatory copy: Shell composes, app/module owns meaning.
- Hard limits:
  - No Shell ownership shift.

### 6) Widget / Card Builder

- Purpose:
  - Inspect/edit cards/widgets under owner artifacts.
- What it owns:
  - Studio widget draft state only.
- What it must not own:
  - Runtime widget ownership or dashboard meaning.
- Allowed resources:
  - Widget/card definitions, composition metadata, placement drafts.
- Future UI entry point:
  - Build > Widget / Card Builder.
- Safe first implementation:
  - Visual label and ownership-boundary text.
- Hard limits:
  - No runtime ownership transfer.

### 7) Report Builder

- Purpose:
  - Inspect/edit report artifacts in governed workflow.
- What it owns:
  - Draft report config state during Studio session.
- What it must not own:
  - Business report semantic ownership.
- Allowed resources:
  - Report definitions, template/config drafts, output descriptors.
- Future UI entry point:
  - Build > Report Builder.
- Safe first implementation:
  - Non-functional nav label and explanatory note.
- Hard limits:
  - No direct runtime publication from tool label alone.

### 8) Data Model / DB Schema Tool

- Purpose:
  - Plan schema/migration changes only.
- What it owns:
  - Schema planning drafts and migration-plan previews.
- What it must not own:
  - Row/data editing authority or runtime schema truth.
- Allowed resources:
  - Schema definitions, migration plans, impact simulations.
- Future UI entry point:
  - Build > Data Model / DB Schema Tool.
- Safe first implementation:
  - Label with explicit "planning only" text.
- Hard limits:
  - No row/data editing; must obey owner and migration rules.

### 9) Validation / Preview Center

- Purpose:
  - Run/read validation and preview outputs.
- What it owns:
  - Validation result view-state and preview snapshots in Studio context.
- What it must not own:
  - Direct runtime mutation authority.
- Allowed resources:
  - Analyze output, diff preview, simulation previews, diagnostics.
- Future UI entry point:
  - Validate > Validation / Preview Center.
- Safe first implementation:
  - Non-functional nav grouping and state-copy only.
- Hard limits:
  - Read-only result surfaces; no mutation actions.

### 10) Change History / Snapshots

- Purpose:
  - Surface Studio-owned work history and snapshot references.
- What it owns:
  - Studio work logs/references and historical view context.
- What it must not own:
  - Runtime source-of-truth for business resource ownership.
- Allowed resources:
  - Compile/apply history references, snapshot metadata, rollback references.
- Future UI entry point:
  - History > Change History / Snapshots.
- Safe first implementation:
  - Group label and scope explanation only.
- Hard limits:
  - History references must not be treated as runtime ownership truth.

### 11) Approval / Apply Center

- Purpose:
  - Govern approval/apply workflow before handover to owners.
- What it owns:
  - Approval/apply process state inside Studio governance flow.
- What it must not own:
  - Post-apply business runtime ownership.
- Allowed resources:
  - Approval gates, apply readiness metadata, apply records.
- Future UI entry point:
  - Govern > Approval / Apply Center.
- Safe first implementation:
  - Non-functional nav grouping + lifecycle explanation text.
- Hard limits:
  - No policy bypass, no new apply semantics in this planning scope.

---

## Recommended Studio Navigation Groups

1. Explore
2. Build
3. Validate
4. Govern
5. History

## Suggested Sidebar/Menu Labels

### Explore

- Resource Explorer / Library

### Build

- App Builder
- Module Builder
- View / Layout Builder
- Navigation / Menu Tool
- Widget / Card Builder
- Report Builder
- Data Model / DB Schema Tool (Planning)

### Validate

- Validation / Preview Center

### Govern

- Approval / Apply Center

### History

- Change History / Snapshots

---

## User Mental Model

1. Choose tool first.
2. Load resource second.
3. Inspect/edit safely third.
4. Validate/diff/approve/apply last.

Plain-language framing:

- "Tool" answers: what kind of work am I doing?
- "Resource" answers: what owner-owned artifact am I working on?
- "Governed lifecycle" answers: when can changes safely move to owners?

---

## First Runtime Implementation Recommendation (Future P1.5 Runtime Slice)

Implement only non-functional navigation grouping/labels first:

1. Add grouped tool labels in Studio sidebar/header (Explore/Build/Validate/Govern/History).
2. Add brief ownership-helper text near tool groups.
3. Do not introduce new tool behavior, routes, or mutations.
4. Keep current tabs/buttons behavior unchanged.

This first runtime step is clarity-only and should not alter workflow semantics.

---

## Acceptance Criteria For Future P1.5 Runtime Implementation

1. Users can clearly distinguish Studio tools from business modules.
2. No business module is presented as Studio-owned.
3. Navigation labels avoid implying Studio runtime ownership.
4. Shell/Core/business apps remain unmodified for this first runtime clarity step.
5. No new route contracts, DB schema changes, or permission changes are introduced.

---

## Out Of Scope In This Docs Slice

1. Runtime UI implementation.
2. Tool route creation.
3. Behavior changes in apply/approval/snapshot/rollback.
4. Any ownership model changes.
5. Any Core or business app/module runtime modifications.
