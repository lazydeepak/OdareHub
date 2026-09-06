# Localization Studio v2 Edit/Apply Architecture Contract

**Status**: Planning/design only — no implementation.
**Date**: 2026-06-01
**Based on**: Localization Studio v1 closed at `c31366a1`

> This document defines the architecture contract for a **future governed localization
> edit/apply workflow**. It is design only. No code, no routes, no UI, no locale file
> writes, no runtime changes are authorized by this document. A separate implementation
> plan and approval is required before any v2 code is written.

---

## 1. Purpose and Boundary

### Principle

Localization Studio v2 is a **governed edit/apply workflow** that extends v1's read-only
inspection with controlled mutation of owner-owned locale resources. Studio remains a
governed worker — it may edit and apply on behalf of owners, but it must never become
the source of translation truth.

### Ownership Invariant

- Apps/modules/plugins/Core **retain ownership** of their locale files.
- Studio may **propose, validate, and apply** changes, but ownership does not transfer.
- Studio must not host, cache, or serve locale files as runtime sources.
- Shell/runtime must **never consume Studio draft state** — drafts are inspection/planning
  artifacts only.

### Read Boundary

- Runtime translation loading is **unchanged**. The system loads locale files exactly
  as it does today.
- Runtime must have no knowledge of Studio drafts, proposals, change records, or snapshots.
- Studio's apply action writes to the owner's existing locale file path — the same file
  that runtime already reads.

---

## 2. Draft Translation Resource Model

This defines what a future draft translation record should contain. Implementation is
deferred — the storage mechanism (DB table, file, or otherwise) will be decided in the
implementation phase after this contract is approved.

### Minimum Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | identifier | yes | Unique draft identifier |
| `owner_key` | string | yes | e.g. `manufacturing.dispatch` or `sbaio.timecards` |
| `owner_type` | enum | yes | `app`, `module`, `plugin`, `core` |
| `locale` | string | yes | ISO locale code: `en`, `ja`, `ne` |
| `key` | string | yes | Full locale key, e.g. `manufacturing.dispatch.list.title` |
| `source_value` | string | yes | Current value from locale file (read-only at proposal time) |
| `proposed_value` | string | no | The editor's proposed new value |
| `proposal_source` | enum | yes | `manual`, `imported`, `ai_assisted` (future) |
| `status` | enum | yes | `draft`, `reviewed`, `approved`, `rejected`, `applied`, `rolled_back` |
| `author_id` | identifier | yes | Who created the proposal |
| `reviewer_id` | identifier | no | Who reviewed it |
| `approver_id` | identifier | no | Who approved apply |
| `validation_status` | object | yes | Per-validation-category pass/fail/warning |
| `snapshot_id` | identifier | no | Ref to snapshot taken before apply |
| `change_record_id` | identifier | no | Ref to persisted change record |
| `risk_level` | enum | yes | `none`, `low`, `medium`, `high`, `critical` |
| `created_at` | timestamp | yes | Proposal creation |
| `updated_at` | timestamp | yes | Last modification |

### Constraints

- A draft must reference exactly one key-locale pair.
- Multiple drafts may exist for the same key-locale (different editors, different proposals).
- A draft must not be applied if the `source_value` has changed since proposal creation
  (stale proposal detection).
- Drafts are **Studio-local**. They are not consumed by runtime. They are not persisted
  to owner-owned locale file paths.

---

## 3. Governed Workflow

The future lifecycle, in order:

```
[1] Discover/Load
    → Use v1 discovery service to load current locale state
    → Select owner, locale, key from v1 inspection data

[2] Create Draft Proposal
    → Editor creates proposed value for a single key-locale
    → Proposal recorded as draft status

[3] Validate Proposal
    → Run all validation categories on the proposed change
    → Report pass/fail/warning per category
    → Block apply if any critical validation fails

[4] Show Diff
    → Display before/after for the affected key
    → Display before/after for the affected locale file
    → Highlight affected keys and any secondary impacts

[5] Preview Affected Locale File
    → Render a simulated version of the locale file with the proposal applied
    → No runtime loading — preview is in-memory/display only

[6] Approve
    → Authorized user marks proposal as approved
    → Approval may require different user than author
    → Approval may be conditional on risk level

[7] Snapshot
    → Backup the current owner locale file before any write
    → Snapshot is managed by Studio for rollback purposes
    → Snapshot is owned by Studio as a governance artifact

[8] Apply
    → Write the proposed value to the owner's locale file
    → Write is atomic per file
    → Apply must create a change record after successful write

[9] Run Diagnostics
    → Re-run v1 diagnostics on the modified locale file
    → Report any new warnings or errors introduced by the change

[10] Handover Record
    → Persist a handover record stating the change has been applied
    → Record includes apply timestamp, snapshot ref, validation results

[11] Rollback (if needed)
    → Restore locale file from snapshot
    → Run validation diagnostics post-restore
    → Mark change record as rolled_back
```

---

## 4. Apply Rules

Apply must satisfy **all** of the following conditions before execution:

| Rule | Check | Blocking |
|---|---|---|
| Owner boundary | Target owner path matches proposal `owner_key` | Yes |
| Locale support | Locale code is `en`, `ja`, or `ne` (from supported list) | Yes |
| Key naming | Key follows owner convention (`{owner}.{module}.{view}.{key}`) | Yes |
| Target path | Target file path is within owner-owned directory | Yes |
| Proposal validation | All critical validations pass | Yes |
| Approval | Proposal status is `approved` | Yes |
| Approver authorization | Approver has per-owner write authority | Yes |
| Snapshot exists | Snapshot of target file was created before apply | Yes |
| Diagnostics pre-apply | v1 diagnostics pass on current file | Warning, not blocking |
| Stale proposal check | `source_value` matches current file value | Yes |

### Additional Constraints

- Apply must never write to Core (`app/lang/`) without an explicit, separately
  documented approval flow.
- Apply must not create locale files for owners that have none.
- Apply must not change the locale file's PHP structure — only key-value pairs
  may be modified.
- Apply must not add keys that don't exist in the English locale file (for non-English
  locales) unless explicitly approved in the proposal.
- Batch apply (multiple keys at once) must validate every key individually and
  fail atomically if any single key fails validation.

---

## 5. Validation Model

Validation categories for proposed changes. These are the same categories defined
in the v1 resource diagnostic, extended with proposal-specific checks.

### Categories

| Category | Scope | Applies to |
|---|---|---|
| Parse safety | Proposed value must not break PHP syntax of the target file | Every proposal |
| Locale support | Locale code must be in the supported set | Every proposal |
| Key naming | Key must follow owner naming convention | Every proposal |
| Owner/path boundary | Target file must be within owner-owned directory | Every proposal |
| Missing/extra key parity | Adding a key to non-en locale without en equivalent is a warning | Non-en proposals |
| Dangerous values | XSS patterns, superglobals, exec functions in proposed value | Every proposal |
| Placeholder mismatch | Proposed value placeholder tokens mismatch template placeholders | Future v2/v3 optional |
| Orphan-key detection | Proposed new key has no usage in owner source code | Future optional |
| Translation quality | Semantic accuracy of proposed translation | **Explicitly out of scope** |

### Classification

| Severity | Behavior |
|---|---|
| Pass | Proposal is valid |
| Warning (non-blocking) | Proposal is accepted but flagged for review |
| Fail (blocking) | Proposal cannot proceed to apply |

---

## 6. Diff/Preview Model

Studio must preview changes without mutating runtime or loading draft state.

### Diff Display Requirements

- Before/after value for the single proposed key
- Before/after for the affected locale file (syntax-highlighted)
- List of all affected keys in the proposal (for batch)
- Affected owners and locales
- Validation result summary per category
- Risk indicators (none/low/medium/high/critical)
- Stale proposal warning if source value has changed

### Preview Constraints

- Preview must be rendered from in-memory data — no temp files, no cache writes
- Preview must not trigger any locale loading in runtime
- Preview must not modify any file, DB, or registry
- Preview must display a `DRAFT - NOT LIVE` watermark or header

### Risk Indicators

| Condition | Risk Level |
|---|---|
| Single key, low-impact value change | `low` |
| Batch update (>5 keys) | `medium` |
| Core locale (`app/lang/`) involved | `critical` |
| New key added to non-English locale | `medium` |
| Key value contains HTML or URLs | `high` |
| Proposal involves maintenance/readme-only keys | `none` |

---

## 7. Snapshot and Rollback Model

### Snapshot Requirements

- A snapshot must be created **before** every apply action.
- Snapshot captures the **current state** of the locale file being modified.
- Snapshot is managed by Studio — stored in a Studio-governed area, not in the
  owner's locale file path.
- Snapshot must reference: file path, owner, locale, timestamp, and pre-apply hash.
- Snapshot is read-only after creation — never modified.

### Rollback Requirements

- Rollback restores a locale file from a snapshot.
- Rollback must be authorized — same authority level as apply.
- Rollback must verify the target file path still belongs to the original owner
  (no ownership migration since snapshot).
- Rollback must create a **new snapshot** before restoring (to preserve current state).
- Rollback must run diagnostics after restore to confirm file integrity.
- Rollback must mark the associated change record as `rolled_back`.
- Rollback must not consume or reference any Studio draft state.
- Rollback must never write to paths outside the snapshot's recorded owner boundary.

### Snapshot/Rollback Constraints

- Snapshots are Studio governance artifacts — not runtime sources.
- Snapshots must not be readable by runtime locale loading.
- Snapshots must have a retention policy (minimum: until superseded by next snapshot
  for same file, or explicit archival).
- Rollback is a separate governed action, not an automatic undo.

---

## 8. Audit/Change Record Model

### Change Record Fields

| Field | Required | Notes |
|---|---|---|
| `change_record_id` | yes | Unique identifier |
| `owner_key` | yes | e.g. `manufacturing.dispatch` |
| `locale` | yes | Affected locale |
| `keys_changed` | yes | Array of key-value pairs before and after |
| `proposal_ids` | yes | References to source proposal drafts |
| `author_id` | yes | Who initiated the change |
| `approver_id` | yes | Who approved apply |
| `validation_results` | yes | Per-category validation outcome |
| `snapshot_id` | yes | Snapshot taken before apply |
| `applied_at` | yes | Timestamp of apply |
| `rollback_id` | no | If rolled back, reference to rollback record |
| `risk_level` | yes | Computed risk at time of apply |
| `handover_status` | yes | `pending`, `completed`, `failed` |
| `resolved_contract_rebuild_required` | yes | Whether a resolved runtime contract rebuild is needed |

### Relationship to Existing Studio Docs

This model extends the existing Studio change lifecycle contracts:

- **`studio-change-lifecycle-apply-contract.md`**: The lifecycle stages (analyze, diff,
  preview, approval, apply, snapshot, rollback) defined there apply directly to
  Localization Studio v2.
- **`studio-change-record-schema-baseline.md`**: The change record schema baseline
  defines shared fields (`risk_level`, `approval_required`, `approval_status`,
  `approved_by`, `validation_gates`, `validation_status`, `snapshot_id`,
  `rollback_strategy`, `handover_status`, `resolved_contract_rebuild_required`).
  Localization Studio v2 change records must conform to this shared schema.
- **`studio-approval-risk-validation-policy.md`**: The approval/risk validation policy
  applies to localization changes — risk level determines approval requirements.
- **`studio-resource-registry-baseline.md`**: The resource registry defines how Studio
  discovers and references owner resources. Locale files follow the same owner-resource
  pattern.

---

## 9. Permission/Authority Model

### Future Roles

| Role | Scope | Authority |
|---|---|---|
| Viewer (default) | All owners | Read-only: inspect locale files, view drafts |
| Translator/Editor | Assigned owners | Create and edit draft proposals |
| Reviewer | Assigned owners | Review and comment on drafts |
| Approver | Assigned owners | Approve or reject proposals for apply |
| Platform Admin | System-wide | Override apply authority, manage rollback |
| System Admin | System-wide | All permissions including Core locale changes |

### Authority Boundaries

- Approver must be different from author (no self-approval) for `medium` risk and above.
- Per-owner apply authority is the default — a translator for Manufacturing cannot
  write to SBAIO locale files.
- Cross-owner apply requires explicit platform admin authorization.
- Core (`app/lang/`) apply requires system admin authorization and a separate
  approval gate.
- No role may apply without an approved proposal and a valid snapshot.
- No role may bypass validation.

---

## 10. Manual vs Imported vs Future AI-Assisted Translation

### Manual Translation (v2.3+)

- First supported proposal source.
- Editor types proposed value directly.
- Must pass same validation pipeline as all other sources.
- No auto-apply.

### Imported Translation (future v2.x, design only)

- Accept TSV/CSV files with key-value pairs.
- Import creates draft proposals for each row.
- Each imported proposal must be individually validated.
- Import does not bypass approval or apply gates.
- Import source file must be discarded after proposal creation — no persistent
  import file storage.

### Future AI-Assisted Translation (not part of initial v2)

- Separate mode, not included in initial v2 implementation.
- AI output is a **suggestion** only — creates draft proposals in `draft` status.
- AI output must **never auto-apply**.
- AI output must pass the same validation/diff/approval/apply lifecycle as manual.
- AI source must be recorded in `proposal_source` field.
- AI suggestions must be clearly labeled as AI-generated in the UI.

---

## 11. Runtime Boundary

### Invariant

**Runtime localization loading is unchanged.**

- All locale files remain at their owner-owned paths.
- Studio does not move, copy, or replace locale files.
- Studio does not generate merged locale bundles.
- Studio does not intercept or override translation loading.
- Owner locale files are the sole source of translation truth at runtime.
- Studio drafts, proposals, change records, and snapshots are governance artifacts
  — not runtime sources.
- A runtime refresh/rebuild step (if needed after locale file modification) is a
  separate governed action, not automatic.

### What Must Never Happen

- Runtime must never load a Studio draft as a translation source.
- Runtime must never check a Studio database table for override translations.
- Runtime must never reference a Studio-managed cache as a locale source.
- Runtime must never fall back to a Studio-managed file path for missing keys.

---

## 12. Implementation Sequencing

These are safe future implementation phases. **Do not implement them** until a
separate implementation plan is approved. Each phase must be independently validated
by architecture gates before the next phase begins.

### v2.1 — Contract + Diagnostics (planning only)
- **This document**. No code.
- Augment v1 resource diagnostic with proposal-specific validation rules (optional).
- Augment boundary diagnostic with draft prohibitions (optional).

### v2.2 — Draft Model Placeholder
- Define the draft data structure in code (class/interface only).
- No storage, no UI, no persistence.
- All create/read operations are in-memory mocks.
- Gate: no DB writes, no file writes, no localStorage.

### v2.3 — Manual Draft UI (disabled/read-only preview)
- Render a draft editor surface.
- Input fields are **disabled by default** — read-only mock.
- Show proposed value as pre-filled from inspection data.
- No save button. No submit. No apply.
- Gate: no form submission, no POST, no AJAX.

### v2.4 — Validation + Diff Preview
- Run validation rules against in-memory proposals.
- Render diff view (before/after).
- All validation results displayed but no mutation.
- Gate: no file writes, no approval state changes, no apply.

### v2.5 — Approval Model
- Define approval storage (DB table or file-based).
- Implement approval workflow (create/review/approve/reject).
- Approval action does not write locale files — only changes proposal status.
- Gate: approval must not have write side effects on locale files.

### v2.6 — Apply/Snapshot/Rollback
- Only after v2.1–v2.5 are independently validated and approved.
- Implement snapshot (backup before write).
- Implement apply (write to owner locale file).
- Implement rollback (restore from snapshot).
- Every write must pass all apply rules (section 4).
- Gate: atomic per-file writes, snapshot before every apply, diagnostics post-apply.

---

## 13. Forbidden Shortcuts

| Shortcut | Reason |
|---|---|
| Direct file writes from UI | Bypasses validation, diff, approval, snapshot |
| Save/apply without diff | Cannot verify what changed |
| Apply without snapshot | Cannot roll back |
| Apply without approval | Governance bypass |
| Runtime loading from Studio drafts | Studio is not runtime truth |
| Hidden generated locale files | Ownership violation, runtime confusion |
| DB/cache side effects without contract | Unmanaged state, drift from source truth |
| Cross-owner writes | Ownership boundary violation |
| AI auto-apply | No human oversight, risk of bad translations |
| Translation generation inside v2 contract | AI is a separate future mode, not part of initial v2 |
| Write to Core without explicit approval | Core Lock Rule |
| Self-approval for medium+ risk changes | No separation of duties |
| Bypass validation for urgent changes | All changes must pass validation |
| Store Studio state in owner locale file paths | Ownership violation, runtime confusion |
| Persist drafts as runtime locale overrides | Drafts are governance artifacts, not runtime sources |

---

## 14. Contract Dependencies

This contract depends on and extends the following existing architecture documents:

| Document | Relationship |
|---|---|
| `docs/architecture/localization-studio-v1-foundation.md` | Defines ownership model, locale discovery, naming conventions, validation rules |
| `docs/architecture/localization-studio-v1-checkpoint.md` | Documents v1 completion state that v2 extends |
| `docs/architecture/studio-change-lifecycle-apply-contract.md` | Defines the analyze/diff/preview/approve/apply/rollback lifecycle stages |
| `docs/architecture/studio-change-record-schema-baseline.md` | Defines shared change record schema fields |
| `docs/architecture/studio-approval-risk-validation-policy.md` | Defines approval requirements by risk level |
| `docs/architecture/studio-resource-registry-baseline.md` | Defines owner resource discovery and reference model |
| `scripts/architecture/check_localization_studio_boundaries.sh` | Enforces v1 boundary invariants; v2 will extend with draft/apply invariants |

---

## 15. Key Architecture Laws

1. **Studio is a governed worker** — it may edit and apply on behalf of owners,
   but never own translation truth.
2. **Locale ownership follows view ownership** — apps/modules/plugins/Core own
   their locale files; Studio may propose and apply changes but ownership does
   not transfer.
3. **Runtime must never depend on Studio** — runtime reads only owner-owned locale
   files, never Studio drafts, proposals, change records, or snapshots.
4. **Snapshot before apply** — every write must have a recoverable backup.
5. **Validation before apply** — every proposal must pass all blocking validations.
6. **Approval before apply** — every write must have explicit authorization.
7. **Core locale is locked** — writing to `app/lang/` requires explicit separate
   approval.
8. **Proposal sources are equal before governance** — manual, imported, and
   AI-assisted proposals all pass the same validation/diff/approval/apply lifecycle.
9. **No AI auto-apply** — AI suggestions are proposals, not applied state.
10. **No self-approval for medium+ risk** — separation of duties above low risk.

---

## 16. References

- Localization Studio v1 foundation: `docs/architecture/localization-studio-v1-foundation.md`
- Localization Studio v1 checkpoint: `docs/architecture/localization-studio-v1-checkpoint.md`
- Studio change lifecycle/apply contract: `docs/architecture/studio-change-lifecycle-apply-contract.md`
- Studio change record schema baseline: `docs/architecture/studio-change-record-schema-baseline.md`
- Studio approval/risk/validation policy: `docs/architecture/studio-approval-risk-validation-policy.md`
- Studio resource registry baseline: `docs/architecture/studio-resource-registry-baseline.md`
- Localization resource validation: `docs/architecture/localization-studio-resource-validation.md`
- Boundary diagnostic: `scripts/architecture/check_localization_studio_boundaries.sh`
- Resource diagnostic: `scripts/architecture/check_localization_resource_diagnostics.sh`
