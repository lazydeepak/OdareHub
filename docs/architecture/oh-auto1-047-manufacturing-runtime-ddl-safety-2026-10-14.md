# OH-AUTO1-047 — Manufacturing Runtime DDL Safety

Date: 2026-10-14
Status: **Static/mock correction complete; no database execution**

## Runtime DDL path

`DemandController::debugGenerateUpstreamSupply()` calls
`UpstreamSupplyGenerationService::generate()`.

Before this correction, the path was:

```text
generate()
  -> UpstreamSupplyGenerationService::ensureSchema()
  -> DemandEngineService::ensureSchema()
  -> CREATE TABLE x3
  -> ALTER TABLE compatibility checks
  -> DemandWorkBucketService::previewBuckets()
  -> DemandEngineService::previewRouteDecisions()
  -> DemandEngineService::ensureSchema()
```

The three upstream tables were created by
`apps/Manufacturing/migrations/005_mfg_upstream_supply_tables.sql`. The demand
table used by the route-decision contract is owned by
`apps/Manufacturing/migrations/001_mfg_operational_model.sql`. Product and
dispatch columns are owned by their Manufacturing module migrations, including
Products migration `006_part_execution_planning_fields.sql` and DispatchEntries
migration `004_dispatch_execution_columns.sql`.

## Correction

Removed only the two schema-ensure calls from the runtime read/generation path:

- `UpstreamSupplyGenerationService::generate()` no longer calls
  `self::ensureSchema()`.
- `DemandEngineService::previewRouteDecisions()` no longer calls
  `self::ensureSchema()`.

`UpstreamSupplyGenerationService::ensureSchema()` remains available as an
explicit preparation helper for migration/test setup. It is documented as
non-runtime and retains the existing table definitions. No schema or migration
file was changed.

## Compatibility implication

Supported installations must have the relevant migrations applied before
upstream generation. A stale or incompletely migrated installation will now
fail at its normal schema/read contract rather than silently issuing DDL inside
the business transaction. This is intentional and prevents implicit commits.

Other Manufacturing services still contain separate legacy schema-preparation
paths; they were not broadened or changed by this bounded assignment.

## Static/mock verification

- Runtime DDL boundary probe: **14/14**
- PHP lint: changed services and focused probe pass
- Migration ownership: all upstream tables and demand table confirmed
- Controller caller and generation chain retained
- No `CREATE TABLE` or `ALTER TABLE` remains in the runtime generation/read
  method bodies
- No database connection, SQL execution, module activation, or application
  runtime request was performed

## Scope

- No Core changes
- No schema or migration modifications
- No route changes
- Existing transaction, idempotency, duplicate-key recovery, and concurrency
  logic preserved
- Existing database-backed behavioral probes were not run
