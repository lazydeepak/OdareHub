# Studio Resource Registry / Resource Explorer Baseline

Status: Studio Readiness Phase baseline.

Purpose: Define a governed resource discovery and classification contract for Studio so future Studio tooling can inspect and prepare edits without taking ownership.

This document is architecture guidance only. It does not authorize Studio UI implementation, runtime behavior changes, route changes, app loading changes, Core edits, or code generation.

## 1. Purpose

Studio needs a governed resource registry model for discovery and inspection.

Purpose rules:

1. Resource discovery exists for inspection and edit-preparation only.
2. Discovery does not transfer ownership to Studio.
3. Registry output is temporary working context for Studio workflows, not runtime truth.
4. Runtime must not consume Studio inventory/draft registry as source of truth.

## 2. Resource Categories Studio May Later Discover

Studio may later discover these owner-owned resource categories:

1. apps
2. modules
3. views/layouts
4. routes
5. nav/menu resources
6. widgets/cards
7. reports
8. diagrams/workflows
9. schema/table/field/migration resources
10. theme tokens
11. CSS scopes/selectors
12. wrapper/components
13. permission/profile resources
14. workspace/landing compositions
15. integration/capability resources
16. package/export/install metadata

## 3. Required Metadata For Each Resource

Each discovered resource should expose these minimal fields:

1. resource_type
2. resource_key
3. owner_type
4. owner_key
5. source_path or source_reference
6. contract_reference
7. editable_by_studio (yes/no)
8. requires_approval (yes/no)
9. risk_level
10. validation_gates
11. preview_supported (yes/no)
12. rollback_supported (yes/no)
13. handover_target

Metadata rules:

1. Contract reference must point to governing architecture/resource contract.
2. Risk level must align with owner boundary and change impact.
3. Validation gates must map to read-only architecture/runtime checks required before apply.

## 4. Ownership Rules

Ownership remains explicit:

1. Source owner owns source resource and runtime meaning.
2. Studio may inspect/load/draft/edit under contract and approval boundaries.
3. Studio must hand approved changes back to owner-owned artifacts.
4. System Tools validate registry and resource health as governance tooling.
5. Runtime consumes approved owner artifacts or resolved contracts, not Studio registry drafts.

## 5. Resource Explorer Behavior

Future Resource Explorer behavior baseline:

1. List resources.
2. Filter by owner/type/risk/status.
3. Inspect governing contract for each resource.
4. Show dependencies and adjacent ownership links.
5. Show editability and approval requirements.
6. Show last modified and handover history in later phases.
7. Never silently mutate resources during discovery.

## 6. Must-Not-Do Rules

Studio registry/explorer must not:

1. perform direct Core editing.
2. behave as a free filesystem editor without owner/contract boundaries.
3. behave as a raw database row editor.
4. create hidden source-of-truth stores.
5. create runtime dependency on Studio inventory/drafts.
6. bypass ACL, migration runner, audit, ownership contracts, or resolved runtime contracts.

## 7. Current Phase Boundary

Current phase boundary:

1. Documentation/preparation only.
2. No implementation in this phase.
3. Future Studio can use this baseline for Resource Explorer planning and governance validation.

## Cross-Contract Alignment

This baseline aligns with:

- Studio operating boundaries: [docs/architecture/studio-operating-contract.md](docs/architecture/studio-operating-contract.md)
- Resolved runtime contract pipeline: [docs/architecture/resolved-runtime-contract-pipeline.md](docs/architecture/resolved-runtime-contract-pipeline.md)
- Surface contribution ownership/composition boundaries: [docs/architecture/surface-contribution-contract.md](docs/architecture/surface-contribution-contract.md)
- Business app/module ownership contracts: [docs/architecture/business-app-module-ownership-contract.md](docs/architecture/business-app-module-ownership-contract.md)

## Validation

Use current architecture gate suite:

- scripts/architecture/run_architecture_gates.sh

This registry baseline is documentation-only in this phase.

Related apply workflow baseline:

- `docs/architecture/studio-change-lifecycle-apply-contract.md`

Related change-record schema baseline:

- `docs/architecture/studio-change-record-schema-baseline.md`
