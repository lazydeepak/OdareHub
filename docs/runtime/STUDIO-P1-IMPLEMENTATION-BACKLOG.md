# Studio P1 Implementation Backlog

## Date

- 2026-05-23

## Scope

- Documentation and implementation planning only.
- No runtime code changes in this slice.
- No route/DB/migration/Core/business-app behavior changes in this slice.

## Baseline Inputs

- Studio UX planning checkpoint: `docs/runtime/STUDIO-UX-PLANNING-REPORT.md` (commit `79445829`).
- Studio P0 clarity slice: commit `42f797fb`.
- Studio operating boundaries and governance baselines:
  - `docs/architecture/studio-operating-contract.md`
  - `docs/architecture/studio-resource-registry-baseline.md`
  - `docs/architecture/studio-change-lifecycle-apply-contract.md`
  - `docs/architecture/studio-change-record-schema-baseline.md`
  - `docs/architecture/studio-approval-risk-validation-policy.md`

## Architecture Guardrails For All P1 Tickets

1. Studio remains a governed worker/tool, not runtime ownership truth.
2. No Core edits unless explicitly approved in a separate task.
3. No business app behavior changes through Studio clarity work.
4. No route contract changes unless explicitly required and approved.
5. No direct data-row editing behavior.
6. No bypass of Analyze -> Changes -> Approval -> Apply governance flow.

---

## P1.1 Library / Editor Separation

### 1. Purpose

- Make role boundaries explicit: Library is for finding/loading resources, Editor is for inspecting/editing loaded resources.

### 2. User Problem Solved

- Reduces confusion from mixed navigation/action affordances and duplicated loading controls.

### 3. Architecture Boundary Protected

- Protects Studio orchestration boundaries by clarifying UX layers without changing resource ownership or runtime meaning.

### 4. Allowed Files / Scope

- `apps/Studio/Views/gui_studio.php`
- Optional Studio-owned docs only.

### 5. Hard Limits

- No new runtime data source.
- No route behavior changes.
- No apply semantics changes.
- No owner transfer implications.

### 6. Acceptance Criteria

1. Library and Editor section purposes are visibly distinct and labeled.
2. Library actions focus on discover/load/inspect.
3. Editor actions focus on loaded-resource inspection/edit preparation.
4. No regression in existing load path behavior.

### 7. Validation Commands

1. `bash scripts/architecture/run_architecture_gates.sh`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `git diff --check`
4. `git status --short`

### 8. Risk Level

- Low

### 9. Suggested Commit Message

- `chore(studio): clarify library and editor responsibilities`

---

## P1.2 Loaded Resource Identity Panel

### 1. Purpose

- Provide a dedicated identity panel for currently loaded resource context.

### 2. User Problem Solved

- Removes ambiguity about what is loaded and where it belongs.

### 3. Architecture Boundary Protected

- Uses existing context metadata only; does not create new ownership truth or runtime source-of-truth paths.

### 4. Allowed Files / Scope

- `apps/Studio/Views/gui_studio.php`
- Studio-owned helper/view fragments if already under `apps/Studio/*`.

### 5. Hard Limits

- Read-only display first.
- No new persistence.
- No backend ownership remapping.
- If Source Path is unavailable in current payload, show fallback text and defer backend enrichment.

### 6. Acceptance Criteria

1. Panel shows fields when available: Owner App, Module, Resource Type, Resource Key, Mode, Source Path.
2. Missing fields degrade gracefully as `Unavailable` (or localized equivalent).
3. Panel is non-interactive (display-only) in first slice.

### 7. Validation Commands

1. `bash scripts/architecture/run_architecture_gates.sh`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `git diff --check`
4. `git status --short`

### 8. Risk Level

- Low

### 9. Suggested Commit Message

- `chore(studio): add loaded resource identity panel`

---

## P1.3 Create vs Edit vs Upgrade Mode Selector

### 1. Purpose

- Make mode intent explicit with visual selector clarity.

### 2. User Problem Solved

- Reduces confusion about whether actions are creating new resources or editing/upgrading existing ones.

### 3. Architecture Boundary Protected

- Preserves Studio as governed worker by limiting scope to mode signaling and UI guidance, not apply behavior.

### 4. Allowed Files / Scope

- `apps/Studio/Views/gui_studio.php`

### 5. Hard Limits

- Visual selector behavior first only.
- Do not change actual apply execution semantics.
- Do not introduce new route contracts.

### 6. Acceptance Criteria

1. Selector clearly presents Create / Edit / Upgrade states.
2. Mode-specific hints update contextual guidance text.
3. Existing mode data handling remains backward compatible.
4. No apply pipeline behavior regression.

### 7. Validation Commands

1. `bash scripts/architecture/run_architecture_gates.sh`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `git diff --check`
4. `git status --short`

### 8. Risk Level

- Medium

### 9. Suggested Commit Message

- `chore(studio): improve mode selector clarity for create edit upgrade`

---

## P1.4 Validation / Diff / Apply Visibility

### 1. Purpose

- Make Analyze, Changes/Diff, Preview, Approval, and Apply states easier to understand at a glance.

### 2. User Problem Solved

- Reduces uncertainty about current lifecycle stage and readiness to proceed.

### 3. Architecture Boundary Protected

- Keeps governance pipeline intact; only clarity and visibility changes are allowed.

### 4. Allowed Files / Scope

- `apps/Studio/Views/gui_studio.php`

### 5. Hard Limits

- No logic changes to approval/apply outcomes.
- No policy bypass.
- No new mutation path.

### 6. Acceptance Criteria

1. Lifecycle stage indicators are visible and comprehensible.
2. State messaging distinguishes blocked/pending/ready clearly.
3. Risk/approval requirements are shown in plain language.
4. Existing governance checks continue unchanged.

### 7. Validation Commands

1. `bash scripts/architecture/run_architecture_gates.sh`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `git diff --check`
4. `git status --short`

### 8. Risk Level

- Low to Medium

### 9. Suggested Commit Message

- `chore(studio): improve lifecycle visibility for analyze diff approval apply`

---

## P1.5 Studio Tool/Module Navigation Model

### 1. Purpose

- Define Studio internal tool-module map to improve wayfinding and future decomposition.

### 2. User Problem Solved

- Prevents users from confusing Studio tools with business modules.

### 3. Architecture Boundary Protected

- Explicitly keeps Studio tools as orchestration surfaces; business/runtime ownership remains with owner apps/modules.

### 4. Allowed Files / Scope

- Docs-first:
  - `docs/runtime/STUDIO-P1-IMPLEMENTATION-BACKLOG.md`
  - `docs/runtime/STUDIO-P1-TOOL-MODULE-NAVIGATION-MODEL.md`
  - Optional follow-up Studio docs under `docs/runtime/*` or `docs/architecture/*` if approved.
- UI mapping later may use `apps/Studio/Views/gui_studio.php` with labels only.

### 5. Hard Limits

- Do not introduce runtime dependencies from business apps to Studio tools.
- Do not represent Studio tool labels as business module ownership truth.
- Do not alter ACL/permission contracts in this ticket.

### 6. Acceptance Criteria

1. Studio tool map is documented and visible as Studio-internal tooling taxonomy.
2. Tool list includes:
   - Resource Explorer
   - App Builder
   - Module Builder
   - View/Layout Builder
   - Navigation/Menu Tool
   - Widget/Card Builder
   - Report Builder
   - Data Model / DB Schema Tool
   - Validation/Preview Center
   - Change History / Snapshots
   - Approval / Apply Center
3. Documentation explicitly states: these are Studio tools, not business modules.

### 7. Validation Commands

1. `bash scripts/architecture/run_architecture_gates.sh`
2. `bash scripts/system/check_deployment_readiness.sh`
3. `git diff --check`
4. `git status --short`

### 8. Risk Level

- Low

### 9. Suggested Commit Message

- `docs(studio): define studio internal tool navigation model`

---

## Recommended Implementation Order

1. P1.1 Library / Editor separation
2. P1.2 Loaded Resource identity panel
3. P1.4 Validation / Diff / Apply visibility
4. P1.3 Create vs Edit vs Upgrade mode selector
5. P1.5 Studio tool/module navigation model

## Why This Order

1. P1.1 reduces immediate cognitive load before deeper paneling work.
2. P1.2 establishes consistent context identity for subsequent flow clarity.
3. P1.4 clarifies lifecycle state before expanding mode semantics.
4. P1.3 then refines mode intent with lower ambiguity.
5. P1.5 lands as structural taxonomy after practical UX layers are stabilized.

## Out Of Scope For This Backlog Slice

- Any implementation of P1 tickets.
- Any runtime behavior mutation.
- Any ownership transfer or contract changes.
