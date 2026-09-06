# Studio Approval / Risk / Validation Policy Baseline

Status: Studio Readiness Phase baseline.

Purpose: Define policy rules for risk classification, approval requirements, and validation evidence before future Studio apply workflows are allowed to proceed.

This document is architecture guidance only. It does not authorize Studio UI implementation, runtime behavior changes, route/app-loading changes, Core edits, live apply logic, or migrations.

## 1. Purpose

Studio policy goals:

1. Studio changes must be approved according to risk.
2. Validation evidence is required before apply.
3. System Tools can block apply when required gates fail.
4. Approval does not bypass ownership, ACL, audit, migration rules, or resolved runtime contracts.

## 2. Risk Levels And Meaning

Canonical risk levels:

1. read_only
2. low
3. medium
4. high
5. critical

## 3. Example Risk Mapping

Example mapping guidance:

1. read_only: inspect/analyze only.
2. low: copy/text/config metadata changes.
3. medium: view/layout/nav/CSS/widget changes.
4. high: schema/migration/permission/workflow changes.
5. critical: Core/system-app lifecycle/security/destructive changes.

Mapping rule:

- If a change touches multiple categories, highest applicable risk level wins.

## 4. Approval Requirements

Baseline approval policy:

1. read_only:
   - no apply approval path needed because no mutation is allowed.
2. low:
   - owner-level approval required.
3. medium:
   - owner-level approval plus platform/admin review when cross-surface impact exists.
4. high:
   - owner-level approval plus platform/admin approval required.
   - self-approval should be disallowed where possible.
5. critical:
   - explicit platform/security authority approval required.
   - explicit Core approval required for any Core-touching change.
   - self-approval must be disallowed.

Approval invariants:

1. Approval never transfers ownership from owner layer to Studio.
2. Approval cannot bypass policy or validation requirements.

## 5. Required Validation By Risk Level

Validation baseline by risk:

1. read_only:
   - inspection consistency checks only.
2. low:
   - architecture gates
   - owner contract checks
   - resource-specific checks
   - preview checks where applicable
3. medium:
   - all low checks
   - rollback-readiness checks
   - post-apply smoke checks
4. high:
   - all medium checks
   - migration dry-run for schema/migration changes
   - dependency and scope impact checks
5. critical:
   - all high checks
   - strongest policy/audit/security review set
   - explicit Core/system-app governance checks when relevant

Validation rule:

- Apply cannot proceed without required validation evidence for the assigned risk level.

## 6. System Tools Blocking Rules

System Tools may block apply when any required condition fails, including:

1. failed architecture or required validation gates.
2. missing owner resolution.
3. missing snapshot or rollback strategy for high/critical changes.
4. unresolved dependency.
5. unauthorized actor.
6. dirty or unknown resource state.

Blocking rule:

- A block is a governance stop, not a suggestion; apply remains disallowed until conditions are resolved.

## 7. Approval Evidence

Approval evidence should include:

1. approver
2. timestamp
3. risk accepted
4. validation result
5. diff reviewed
6. preview reviewed
7. snapshot confirmed
8. rollback strategy confirmed

Evidence rule:

- Missing required evidence means approval is incomplete.

## 8. Relationship To Change Record

This policy maps directly to change-record fields in the Studio change record schema:

1. approval_status
2. approved_by
3. validation_gates
4. validation_status
5. risk_level
6. snapshot_id
7. rollback_strategy
8. handover_status

Policy rule:

- Approval/validation decisions must be reflected in the change record before apply/handover progression.

## 9. Current Phase Boundary

Current phase boundary:

1. documentation/preparation only.
2. no implementation now.
3. no enforcement code now.
4. future Studio may use this policy before apply.

## Cross-Contract Alignment

This baseline aligns with:

- Studio change record schema: [docs/architecture/studio-change-record-schema-baseline.md](docs/architecture/studio-change-record-schema-baseline.md)
- Studio change lifecycle/apply baseline: [docs/architecture/studio-change-lifecycle-apply-contract.md](docs/architecture/studio-change-lifecycle-apply-contract.md)
- Studio operating contract: [docs/architecture/studio-operating-contract.md](docs/architecture/studio-operating-contract.md)
- Architecture gate suite: [scripts/architecture/run_architecture_gates.sh](scripts/architecture/run_architecture_gates.sh)

## Validation

Use current architecture gates:

- scripts/architecture/run_architecture_gates.sh

This approval/risk/validation policy baseline is documentation-only in this phase.
