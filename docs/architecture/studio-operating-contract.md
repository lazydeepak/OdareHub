# Studio Operating Contract Baseline

Status: Studio Readiness Phase baseline.

Purpose: Define Studio operating boundaries before feature implementation so Studio remains a governed worker/tool and does not become runtime ownership truth.

This document is architecture guidance only. It does not authorize Studio UI implementation, runtime behavior changes, route redesign, Core edits, or app/module generation.

Related extension:

- Studio tool lifecycle contract baseline: `docs/architecture/studio-tool-lifecycle-contract.md`

## 1. Studio Identity

Studio identity rules:

1. Studio is a governed worker/tool.
2. Studio is not an owner of business/runtime resources.
3. Studio edits owner-owned resources under governance constraints.
4. Studio owns orchestration artifacts only:
   - tasks
   - drafts
   - diffs
   - previews
   - snapshots
   - approvals
   - undo/redo records
   - handover records

## 2. Resource Types Studio May Later Work With

Studio may later work with owner-owned resource types:

1. app resources
2. module resources
3. view/layout resources
4. route/nav/menu resources
5. widget/card resources
6. report resources
7. diagram/workflow resources
8. schema/table/field/migration resources
9. theme-token resources
10. CSS scope/selector resources
11. wrapper/component resources
12. permission/profile resources
13. landing/workspace composition resources
14. integration/capability resources
15. package/export/install metadata

Resource ownership rule:

- Studio may edit these resources through governance workflow, but ownership and runtime meaning remain with the owner layer (app/module/system owner as defined by contracts).

## 3. Standard Studio Workflow

Studio workflow baseline:

1. Load resource.
2. Identify owner and contract scope.
3. Analyze current resource state.
4. Validate contract and boundary constraints.
5. Propose change.
6. Produce diff.
7. Render preview.
8. Collect approval.
9. Apply approved change to owner-owned artifact.
10. Record snapshot.
11. Support rollback/redo through governance records.
12. Emit handover record.

Workflow requirements:

- Every apply step must map to owner artifacts.
- Every approved change must keep provenance from input to output.
- Unapproved drafts must not become runtime truth.

## 4. Studio Must-Not-Own Rules

Studio must not:

1. own runtime truth.
2. directly edit Core as part of normal Studio operation.
3. create hidden source-of-truth stores for runtime composition.
4. own live business logic semantics.
5. perform direct row/data editing as part of Schema Tool behavior.
6. bypass migration runner, audit, ACL, ownership contracts, or resolved runtime contracts.

## 5. Relationship With System Tools

Role separation:

1. Studio prepares and edits owner resources through governed workflows.
2. System Tools validate, repair, rebuild, and check health.
3. System Tools may verify Studio output artifacts and contract compliance.
4. Runtime consumes approved owner artifacts (and derived resolved contracts), not Studio drafts.

Governance rule:

- Studio and System Tools are complementary workers with different authority; neither may bypass ownership, policy, or runtime truth contracts.

## 6. Current Phase Boundary

Current phase boundary:

1. This slice is preparation only.
2. No Studio feature implementation is included.
3. No runtime behavior changes are included.

## Cross-Contract Alignment

This baseline aligns with:

- Charter law and Studio model: [docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md](docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md)
- Surface contribution ownership/composition boundaries: [docs/architecture/surface-contribution-contract.md](docs/architecture/surface-contribution-contract.md)
- Resolved runtime compiler-resolver boundaries: [docs/architecture/resolved-runtime-contract-pipeline.md](docs/architecture/resolved-runtime-contract-pipeline.md)
- Business app/module ownership contracts: [docs/architecture/business-app-module-ownership-contract.md](docs/architecture/business-app-module-ownership-contract.md)

## Validation

Use current architecture gates:

- scripts/architecture/run_architecture_gates.sh

This Studio readiness baseline is documentation-only in this phase.

Related resource discovery baseline:

- `docs/architecture/studio-resource-registry-baseline.md`

Related change lifecycle baseline:

- `docs/architecture/studio-change-lifecycle-apply-contract.md`

Related change-record schema baseline:

- `docs/architecture/studio-change-record-schema-baseline.md`

Related tool lifecycle baseline:

- `docs/architecture/studio-tool-lifecycle-contract.md`
