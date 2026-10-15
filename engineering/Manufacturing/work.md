# Manufacturing — Work

## Current Focus

OH-AUTO1-088 machine projection consistency is complete. Continue
 through the same owner-contract pattern for parts, work areas, and process
 areas only as separately bounded milestones.

Future Manufacturing work touching identity, Shared/Foundation, extensions, suite ownership,
or cross-domain authority is subordinate to
`docs/architecture/odarehub-future-architecture-planning-brief.md`.

No Shared/Foundation code, schema, storage, or runtime restructuring is authorized until the
brief's pressure-test workstreams and synthesis complete.

## In Progress

- [x] OH-AUTO1-088 — normalized the owner-shaped runtime scope at the adapter
  boundary, aligned top-level sections with scoped filters, intersected
  part-only machine choices with scoped records, and suppressed unproven
  quantity totals.

- [x] OH-AUTO1-087 — passed authenticated operator identity and effective
  assignment context into `MachinesAdapter`; Manufacturing retains machine,
  part, task-type, queue, filter, section, and KPI scope enforcement. Display
  callers use an explicit read-only display mode and do not receive operator
  identity.

- [ ] Keep Manufacturing identity/extension planning aligned with the canonical future architecture brief.
- [ ] Manufacturing Phase 3 evidence (BOM / product identity / revision) is retained as evidence and migration input only — it must not be used to conclude the final future architecture. See `docs/active/future-architecture-planning-manufacturing-phase3-evidence.md`.

## Next

- [ ] OH-AUTO1-034 — Reconcile updated integration baseline and source inventory.

## Blocked

- Nested invocation inside a caller-owned transaction remains unverified; current callers do not wrap `generate()`.

## Completed

- OH-AUTO1-088 — machine projection consistency and runtime-shaped proof:
  **15/15** assertions. No database, SQL, activation, display-route, schema,
  production, or Core operation.

- OH-AUTO1-019 — Manufacturing operator adapters now return a stable localized diagnostic key instead of raw exception text while retaining existing server-side error logging. See `docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md`.
- OH-AUTO1-020 — Static audit identified unverified target/link atomicity in upstream supply synchronization; no correction made without disposable failure-path evidence. See `docs/architecture/transactional-business-verification-audit-2026-10-14.md`.
- OH-AUTO1-030 — Verified `UpstreamSupplyGenerationService::generate()` through both in-house and third-party branches and added transaction boundaries around target/link pairs.
- OH-AUTO1-032 — Verified concurrent in-house and third-party generation and added duplicate-key recovery using the existing target/link uniqueness contract.

## Evidence

- Manufacturing behavioral probe: 21/21 passed against a fresh independently attested disposable MySQL instance.
- Verified target/link rollback on controlled link failure; no orphan target or link remained.
- Repeat generation updated existing target/link pairs without duplicates.
- Temporary datadir, socket, credentials, and process were removed; installed ServBay remained unchanged.
- Remaining concurrency and update-path limits are recorded in `docs/architecture/transactional-business-verification-audit-2026-10-14.md`.
- Nested transaction composability is not claimed: no existing caller-owned transaction was found, and the generic DB layer has no savepoint/depth abstraction.
- Manufacturing concurrency probe: 11/11 passed; both branches leave one target and one link and retries remain idempotent.
- The OH-AUTO1-030 rollback probe was rerun: 21/21 passed.
- Consolidated regression runner passed all six suites; Manufacturing transaction/concurrency probes passed 21/21 and 11/11.

## OH-AUTO1-047 — Manufacturing runtime DDL safety (2026-10-14)

Status: Static/mock correction complete; no database execution authorized.

`DemandController::debugGenerateUpstreamSupply()` reached
`UpstreamSupplyGenerationService::generate()`, which previously called both
the upstream schema helper and `DemandEngineService::previewRouteDecisions()`;
the latter also called `DemandEngineService::ensureSchema()`. The upstream
tables are owned by `apps/Manufacturing/migrations/005_mfg_upstream_supply_tables.sql`,
and the demand table is owned by `migrations/001_mfg_operational_model.sql`.

Removed the two runtime schema-ensure calls while retaining the explicit helper
for migration/test preparation. Focused static boundary probe: 14/14. PHP
lint and diff hygiene pass. No migration, schema, transaction, idempotency, or
concurrency behavior was changed. Other Manufacturing services with separate
legacy schema-preparation paths remain outside this bounded correction.

## OH-AUTO1-048 — Remaining Manufacturing runtime DDL (2026-10-14)

Status: Static/mock correction complete; no database execution authorized.

Removed runtime schema-ensure calls from `StageTransitionService`'s
`computeForDate()`, `releaseStage()`, and `overrideStageStatus()` paths. The
stage readiness, assembly, and shared demand tables are migration-owned, and
the paths are reachable from Manufacturing bootstrap, queue, dispatch, and
production surfaces.

The same migration-owned helpers were removed from
`DemandExecutionService::productionDemandMapByDate()`, `qcDemandRows()`, and
`assemblyDemandRows()`.

The processing execution generator and DispatchOps preparation paths remain
blocked from correction: their runtime-created tables have no corresponding
repository migrations. Removing those helpers would break supported legacy
installations without an authorized migration contract.

DemandEngine recalculation/adjust/approval/list and AssemblyPlan plan/execution
methods remain as additional runtime-ensure callers for a separately bounded
review because their helpers include legacy compatibility ALTER operations.

Focused remaining-boundary probe: 20/20. OH-AUTO1-047 probe: 14/14. PHP lint,
ownership/Core-lock checks, and diff hygiene pass. No database or SQL execution.
