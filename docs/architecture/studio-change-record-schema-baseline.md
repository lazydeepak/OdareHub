# Studio Change Record Schema Baseline

Status: Studio Readiness Phase baseline.

Purpose: Define a canonical metadata/schema contract for Studio change records so future Studio Resource Explorer and Apply workflows use one shared governance model.

This document is architecture guidance only. It does not authorize Studio UI implementation, runtime behavior changes, app loading changes, route changes, Core edits, live apply logic, or migrations.

## 1. Purpose

A Studio change record is a governed Studio work artifact.

Purpose rules:

1. It links task, resource, owner, proposed change, diff, validation, approval, snapshot, apply, rollback, redo, and handover.
2. It is not runtime truth.
3. It must point to owner-owned resources and approved artifacts.
4. Runtime must not consume draft change records as resource truth.

## 2. Canonical Fields

Canonical change-record fields:

1. change_id
2. task_id
3. parent_change_id
4. actor_user_id or actor_username
5. requested_by
6. resource_type
7. resource_key
8. owner_type
9. owner_key
10. source_path or source_reference
11. contract_reference
12. current_state_hash
13. proposed_state_hash
14. diff_reference
15. preview_reference
16. risk_level
17. approval_required
18. approval_status
19. approved_by
20. validation_gates
21. validation_status
22. snapshot_id
23. rollback_strategy
24. redo_strategy
25. apply_status
26. applied_at
27. handover_target
28. handover_status
29. resolved_contract_rebuild_required
30. audit_reference
31. status

Field rules:

1. contract_reference must identify governing ownership/architecture contract.
2. state hashes must be deterministic for traceability.
3. diff and preview references must remain tied to the same change_id lineage.
4. status and apply_status must be auditable state transitions.

## 3. Status Model

Canonical status values:

1. draft
2. analyzed
3. proposed
4. validated
5. awaiting_approval
6. approved
7. rejected
8. applied
9. handover_complete
10. rolled_back
11. superseded
12. failed

Status transition rule:

- Transitions must be monotonic and traceable; invalid jumps require rejection or explicit supersede lineage.

## 4. Risk Model

Canonical risk levels:

1. read_only
2. low
3. medium
4. high
5. critical

Risk policy rule:

- Approval/validation strictness escalates with risk level; critical changes require strongest policy and explicit handling.

## 5. Ownership Rules

Ownership baseline:

1. Change record belongs to Studio workflow domain.
2. Changed artifact belongs to original owner.
3. Runtime must not consume change record as resource truth.
4. System Tools may validate or block change record progress.
5. Core changes require explicit approval and stronger policy.

## 6. Validation Model

Change-record validation model includes:

1. architecture gates
2. owner contract checks
3. resource-specific checks
4. preview checks
5. post-apply checks
6. rollback-readiness checks

Validation rule:

- A change must not advance to apply/handover stages unless required validation evidence is present.

## 7. Snapshot / Rollback / Redo References

Snapshot and recovery baseline:

1. Snapshot is required before apply.
2. Rollback target must be recorded.
3. Redo may execute only from approved change lineage.
4. Every rollback/redo operation must emit audit evidence.

## 8. Handover Model

Handover record should bind:

1. owner target artifact
2. applied diff
3. validation proof
4. approval proof
5. snapshot link
6. resolved-contract rebuild status when applicable

Handover rule:

- After handover completion, owner artifact remains source of truth and Studio record remains governance history.

## 9. Storage Boundary

Storage boundary baseline:

1. Final DB/file storage is not decided in this phase.
2. This is schema/contract baseline only.
3. Future implementation may use database table, JSON records, or append-only log.
4. Runtime must not treat draft records as truth regardless of storage backend.

## 10. Current Phase Boundary

Current phase boundary:

1. Documentation/preparation only.
2. No implementation now.
3. No migration now.
4. No runtime changes.

## Cross-Contract Alignment

This baseline aligns with:

- Studio operating boundaries: [docs/architecture/studio-operating-contract.md](docs/architecture/studio-operating-contract.md)
- Studio change lifecycle/apply contract: [docs/architecture/studio-change-lifecycle-apply-contract.md](docs/architecture/studio-change-lifecycle-apply-contract.md)
- Studio resource registry baseline: [docs/architecture/studio-resource-registry-baseline.md](docs/architecture/studio-resource-registry-baseline.md)
- Resolved runtime contract pipeline: [docs/architecture/resolved-runtime-contract-pipeline.md](docs/architecture/resolved-runtime-contract-pipeline.md)

## Validation

Use current architecture gates:

- scripts/architecture/run_architecture_gates.sh

This change-record schema baseline is documentation-only in this phase.

Related approval/risk/validation baseline:

- `docs/architecture/studio-approval-risk-validation-policy.md`
