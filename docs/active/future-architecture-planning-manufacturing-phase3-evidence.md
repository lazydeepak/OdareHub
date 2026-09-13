# Manufacturing Phase 3 Evidence — BOM / Product Identity / Revision

> **Status: SUPERSEDED as a planning conclusion. Retained as evidence/migration input only.**
>
> Canonical authority is `docs/architecture/odarehub-future-architecture-planning-brief.md`
> (Domain-Owned Modular Platform with Pressure-Tested Shared Foundations) and its A-D workstream
> briefs. This file may demonstrate that current domain ownership, opaque references, BOM history,
> or contract-based boundaries work well today. It must **not** be used to conclude the final future
> architecture by itself. Current implementation enters only as evidence, migration constraint, and
> compatibility reality — not as target architecture.

Session: OdareHub Future Architecture Planning — Manufacturing Phase 3 Evidence (superseded; evidence-only)
Status: Phase 3 reality evidence. No design, no restructuring, no DB change. Subordinate to canonical brief.

## Evidence gathered (no writes to source/DB)

- BOM module: `apps/Manufacturing/modules/Bom/` exists; `001_bom_contract.sql` defines `manufacturing_bom` / `manufacturing_bom_line`.
- BOM DDL uses opaque `finished_item_ref INT` / `component_item_ref INT`; explicitly NO FK to Shared identity (SQL docblock: "no FK to Shared identity").
- Reference contract cited: `docs/shared-items-foundation/canonical-item-contract.md` (from `fdf47b9` shared foundation).
- Products label resources exist (`contexts/`, `templates/`, `rules/`) with metadata-migration-backup — Manufacturing owns label authoring.
- SQL co-declaration: `apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql` mirrors BOM DDL (idempotent plugin tracking).
- BOM status enum: `draft` / `released` / `superseded` / `archived`; version + revision; unique on `(finished_item_ref, version, revision)`.
- BOM probe (`Tests/probe_bom_contract.php`) runs static contracts always; DB checks skipped when unavailable.
- Recent commits: `12f97b3` item_ref assignment; `b8818e8` BOM module; `b1f939b` versioned BOM/Recipe with item_ref pinning.
- `engineering/Manufacturing/work.md`: updated to In Progress; no prior completed evidence.

## Axis interactions (correction 3 required)

- Axis A (Identity topology): Opaque domain-local references improve isolation but require external reference-resolution if cross-domain equivalence needed. Trade-off: domain autonomy vs. cross-suite identity reconciliation cost.
- Axis B (State ownership): BOM lifecycle (status/version/revision) clearly Manufacturing-owned; item_ref is descriptive, not operational state — correct separation.
- Axis C (Context/scope): BOM applies within Manufacturing; component references may cross to other domains but have no shared-state claim. Trade-off: local scope prevents false global truth.
- Axis E (History): Version/revision/status/supersession supports historical reconstruction without destroying old BOMs — survives audit scenarios B / J / I.
- Axis F (Extension): BOM module owns its schema; extensions must not modify shared identity. Trade-off: module autonomy vs. shared-schema extension friction.

## Migration cost (correction 5 — preliminary classification)

- BOM → shared canonical identity: **staged compatibility required** (not incremental cheap; opaque refs must co-exist with canonical refs during transition; old BOM versions must remain reconstructible).
- BOM → domain-owned with better contracts only: **incremental and cheap** (current state already modular; contract/probe improvements only).
- MaterialManagement / Products label expansion: **incremental and cheap**.

## Decision requiring user (before Phase 4 synthesis or any restructuring)

Should Manufacturing BOM remain on **opaque item_ref** (domain-local identity with reference links only) — preserving current isolation — or is the planning framework intended to test whether it should become **canonical shared identity with domain-owned state kept separate**?

This determines whether Section 17 gate questions 1 (shared identity) and 4 (authority division) are answered "yes for BOM identity" or "no — BOM identity stays domain-local."

## Hard invariants preserved (from document + AGENTS.md)

- No `/app` changes; Core locked.
- No DB mutation; no SQL execution.
- No Manufacturing service/route creation or deletion.
- No Shared/Foundation restructuring started.
- No commitment/push/deploy.
- No AI/autonomous implementation; planning only.
- Unrelated sessions (Shared Foundation `fdf47b9`, Hospitality operator, Studio tools) untouched.

## Next step (user confirmation required)

Confirm BOM identity choice (opaque-ref vs shared-canonical-test) and target workstream (A identity / B domain-ext / C integrity / D composition) so Phase 3 evidence can feed Section 17 gate with correct assumptions.
