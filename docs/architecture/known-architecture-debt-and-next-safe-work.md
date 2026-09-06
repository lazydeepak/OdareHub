# Known Architecture Debt Register And Next Safe Work Map

Status: Active read-only checkpoint map.

Purpose: Record architecture areas that are stable, debt that is explicitly allowed to remain, and future work that is safe without runtime ownership drift.

This document is governance guidance only. It does not authorize runtime behavior changes, route redesign, Core edits, code movement, namespace migration, or sample-app polish.

## Stable / Stop Touching For Now

The following areas are currently stable and should not be changed without a concrete failing architecture gate and owner-scoped approval:

1. Aggregate architecture gate list and order in `scripts/architecture/run_architecture_gates.sh`.
2. Gate runner contract and coverage-index alignment contract in `scripts/architecture/gate-runner-contract.md`.
3. Core lock boundary and approval-only override model in `docs/architecture/CORE-LOCK-POLICY.md`.
4. Route-wrapper boundary laws for operator/display/admin surfaces.
5. Coverage-index alignment enforcement between runner, contract, README, and coverage map.

## Enforced Gates And What They Protect

The aggregate runner currently enforces read-only architecture boundaries for:

1. Core lock scope and explicit-approval visibility.
2. System-app contract baseline and governance metadata.
3. Shell CSS ownership boundary and compatibility-debt warnings.
4. Asset registry and CSS delivery integrity (source truth vs delivery copies).
5. Operator wrapper confinement.
6. Display readonly confinement.
7. Admin canonical route contract and `/me` compatibility-only posture.
8. Studio ownership boundary and readiness diagnostics.
9. Resolved runtime truth boundary.
10. Surface contribution ownership and composition safety.
11. Migration-debt regression prevention for new runtime additions.
12. Capability ownership boundaries for QR/PDF/package semantics.
13. Business app/module ownership baseline.

Reference maps:

- `docs/architecture/architecture-gate-coverage-index.md`
- `scripts/architecture/gate-runner-contract.md`

## Known Debt Allowed To Remain

The following debt is known, documented, and currently allowed to remain as compatibility/integration debt:

1. QR integration and cross-domain coupling warnings where QR currently touches Manufacturing-domain data/workflows.
2. Legacy QR plugin vocabulary and namespace debt (for example `Plugins\QRCode`) as compatibility markers.
3. Existing QR/Timecard selectors in Shell CSS as compatibility debt until approved owner migration.
4. `/me` compatibility alias retained for compatibility; it is not the primary admin architecture target.
5. Legacy compatibility fields retained as transitional carriers, not runtime truth.
6. Studio extraction/readiness remains diagnostics and governance-first; no runtime ownership transfer from diagnostics alone.

## Known Warnings That Should Not Trigger Runtime Edits

Warnings in gate output are inventory signals unless they represent new regressions in active diff scans.

Do not trigger runtime edits solely because of warning-level debt in these categories:

1. Existing QR integration coupling warnings.
2. Legacy QR namespace/plugin naming warnings.
3. Existing Shell CSS compatibility-debt selectors (QR/Timecard).
4. Readiness inventory warnings that do not indicate contract breakage.
5. Existing compatibility alias debt already documented as non-primary.

If warnings are unchanged and gates pass, prefer documentation or diagnostics hardening only.

## Debt That Is Diagnostic-Only For Now

The following debt is currently managed by read-only diagnostics and must not be converted into runtime migration without explicit approved scope:

1. QR ownership migration planning and decoupling sequencing.
2. Legacy namespace migration for QR compatibility vocabulary.
3. Compatibility alias cleanup (`/me` and related bridge surfaces).
4. Transitional field cleanup in resolved/runtime visibility inputs.
5. Studio runtime extraction and ownership realignment.
6. CSS ownership cleanup for legacy compatibility selectors.
7. Public delivery asset drift cleanup where warnings are non-fatal and not introducing new regressions.

## Future Safe Work Candidates

Safe candidates are read-only or contract-documentation improvements that do not move runtime ownership:

1. Expand contract docs with clearer owner/non-owner examples and anti-patterns.
2. Tighten scan-scope self-contract anchors in existing gates.
3. Improve warning phrasing to distinguish inventory debt vs new regressions.
4. Add additional cross-linking between architecture maps and gate contracts.
5. Improve diagnostics portability and deterministic output on macOS shell tools.
6. Add non-runtime lint-style checks for architecture-document consistency.
7. Extend debt register with timestamped checkpoint updates after each architecture slice.

## Unsafe Work Without Explicit Approval

The following is unsafe in this checkpoint and requires explicit owner-approved scope before any change:

1. Editing anything under `/app` (Core lock) without explicit approved Core exception.
2. Moving QR, PDF, or Studio runtime code between owner layers.
3. Renaming compatibility namespaces or runtime identifiers.
4. Removing compatibility aliases such as `/me`.
5. Changing route families or wrapper-confinement behavior.
6. Introducing runtime truth from compatibility fields.
7. Modifying DB schema/migrations as part of architecture checkpoint-only work.
8. Generating or editing public delivery assets as source truth.
9. Sample-app UX/feature polish not tied to a failing architecture law.

## Required Validation Before Any Architecture Commit

Run these commands and record results in the compliance checklist:

```bash
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
git diff --check
git status --short
```

Expected checkpoint outcome:

1. `ARCHITECTURE GATES: PASS`
2. `DEPLOYMENT READINESS: PASS`
3. No diff hygiene errors.
4. File scope limited to docs/diagnostics for read-only architecture slices.

## Source-Of-Truth Reminder

Generated public assets under `public/assets/...` are delivery copies. Owner source truth remains app/module/Shell-owned source files and manifests.

For this reason, asset warnings should direct owner-source or publishing workflow checks, not direct runtime ownership changes.
