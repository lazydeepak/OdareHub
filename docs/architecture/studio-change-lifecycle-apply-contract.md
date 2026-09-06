# Studio Change Lifecycle / Apply Contract Baseline

Status: Studio Readiness Phase baseline.

Purpose: Define the governed change lifecycle Studio must follow so future change apply operations are inspectable, reversible, owner-correct, and contract-bound.

This document is architecture guidance only. It does not authorize Studio UI implementation, runtime behavior changes, app loading changes, route changes, Core edits, or live apply logic.

## 1. Purpose

Studio change lifecycle rules:

1. Studio changes must be governed, inspectable, reversible, and owner-correct.
2. Studio prepares and orchestrates approved changes.
3. Approved changes must be applied to owner-owned artifacts.
4. Runtime consumes approved owner artifacts or resolved runtime contracts, not Studio drafts.

## 2. Lifecycle Stages

Standard lifecycle stages:

1. Discover/load resource.
2. Identify owner and governing contract.
3. Analyze current state.
4. Validate constraints.
5. Propose change.
6. Compute diff.
7. Preview.
8. Risk classification.
9. Approval.
10. Snapshot before apply.
11. Apply to owner-owned artifact.
12. Post-apply validation.
13. Rollback/redo support.
14. Handover record.
15. Resolved/runtime contract rebuild when applicable.

Lifecycle invariants:

1. Every apply must reference owner and contract.
2. Every post-apply step must be validated before handover completion.
3. Unapproved drafts must never become runtime truth.

## 3. Change Record Metadata

Each Studio change record should carry:

1. change_id
2. task_id
3. actor
4. resource_type
5. resource_key
6. owner_type
7. owner_key
8. source_path/reference
9. contract_reference
10. risk_level
11. approval_required
12. validation_gates
13. snapshot_id
14. rollback_strategy
15. handover_target
16. status

Metadata rules:

1. contract_reference must resolve to governing owner contract.
2. validation_gates must map to required read-only checks.
3. status transitions must be auditable and monotonic.

## 4. Risk Levels

Risk classes:

1. Read-only analysis.
2. Low-risk content/config change.
3. Medium-risk view/nav/CSS change.
4. High-risk schema/migration/permission change.
5. Critical Core/system-app/lifecycle/security change.

Risk handling rule:

- Approval and validation strictness must increase with risk level.

## 5. Apply Rules

Apply constraints:

1. No direct Core edits unless explicitly approved.
2. No uncontrolled file edits.
3. No row/data editing through schema tools.
4. No bypassing migration runner.
5. No bypassing ACL, audit, or ownership contracts.
6. No hidden source of truth.
7. No runtime consumption of drafts.

## 6. Snapshot / Rollback / Redo Model

Model baseline:

1. Snapshot is required before apply.
2. Rollback must restore owner-owned artifact state where possible.
3. Redo must replay approved change records, not draft guesses.
4. Apply/rollback/redo operations must emit audit trail entries.

Safety rule:

- Missing snapshot or missing rollback strategy blocks apply for risk levels that require reversibility.

## 7. Handover Model

Handover baseline:

1. Studio hands applied artifacts back to resource owner.
2. Handover record links task, diff, snapshot, validation, approval, and owner target.
3. After handover, owner artifact is the source of truth.

Handover completion rule:

- Handover is complete only after post-apply validation gates pass for required scope.

## 8. Relationship With System Tools

Separation of duties:

1. Studio prepares/proposes/applies under governed workflow.
2. System Tools validate gates, rebuild caches, inspect health, and run smoke checks.
3. System Tools do not approve hidden bypasses.
4. System Tools may block apply when required gates fail.

Governance rule:

- Studio and System Tools must remain contract-bound workers, not ownership bypass channels.

## 9. Current Phase Boundary

Current phase boundary:

1. Documentation/preparation only.
2. No implementation in this phase.
3. Future Studio may use this baseline as apply workflow law.

## Cross-Contract Alignment

This baseline aligns with:

- Studio operating boundaries: [docs/architecture/studio-operating-contract.md](docs/architecture/studio-operating-contract.md)
- Studio resource discovery baseline: [docs/architecture/studio-resource-registry-baseline.md](docs/architecture/studio-resource-registry-baseline.md)
- Resolved runtime contract pipeline: [docs/architecture/resolved-runtime-contract-pipeline.md](docs/architecture/resolved-runtime-contract-pipeline.md)
- Business app/module ownership contracts: [docs/architecture/business-app-module-ownership-contract.md](docs/architecture/business-app-module-ownership-contract.md)

## Validation

Use current architecture gate suite:

- scripts/architecture/run_architecture_gates.sh

This change lifecycle baseline is documentation-only in this phase.

Related change-record schema baseline:

- `docs/architecture/studio-change-record-schema-baseline.md`

Related approval/risk/validation baseline:

- `docs/architecture/studio-approval-risk-validation-policy.md`
