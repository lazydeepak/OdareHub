# OH-AUTO1-048 — Remaining Manufacturing Runtime DDL Boundaries

Date: 2026-10-14
Status: **Bounded static correction complete; no database execution**

## Inventory and prioritization

The remaining Manufacturing service inventory was searched for `ensureSchema()`
and embedded `CREATE TABLE` / `ALTER TABLE` statements. The highest-impact
reachable path with complete migration ownership was the Stage Transition
family:

```text
Manufacturing bootstrap / queue / dispatch / production controllers
  -> StageTransitionService::computeForDate()
  -> StageTransitionService::ensureSchema()
  -> CREATE TABLE mfg_stage_readiness
  -> ALTER TABLE mfg_stage_readiness
```

The same helper was also invoked by `releaseStage()` and
`overrideStageStatus()`. `mfg_stage_readiness` is owned by
`apps/Manufacturing/migrations/002_mfg_stage_transitions.sql`; its assembly and
shared demand dependencies are owned by migrations
`003_mfg_assembly_execution.sql` and `001_mfg_operational_model.sql`.

The same migration-owned schema helpers were also invoked by
`DemandExecutionService::productionDemandMapByDate()`,
`qcDemandRows()`, and `assemblyDemandRows()`.

## Correction

Removed runtime schema preparation calls from:

- `StageTransitionService::computeForDate()`
- `StageTransitionService::releaseStage()`
- `StageTransitionService::overrideStageStatus()`
- `DemandExecutionService::productionDemandMapByDate()`
- `DemandExecutionService::qcDemandRows()`
- `DemandExecutionService::assemblyDemandRows()`

The public `StageTransitionService::ensureSchema()` helper remains available
for explicit migration/test preparation and retains the existing DDL. No
migration or schema file was changed.

## Compatibility blockers retained

`DemandWorkExecutionGeneratorService::generate()` remains unchanged because it
invokes DDL for `mfg_order_processing_entries` and
`mfg_work_execution_links`, but no corresponding repository migration exists.
Removing that fallback would break installations whose schema is supplied only
by the helper.

`DispatchOpsService` also remains unchanged because its preparation logs and
dispatch bundle tables have no repository migration ownership. These are
documented blockers, not silently removed compatibility behavior.

Additional runtime callers remain outside the two-correction limit: the
`DemandEngineService` recalculation/adjust/approval/list methods and
`AssemblyPlanService` plan/execution methods still delegate to their explicit
schema helpers. Their table ownership is partly migration-backed, but the
helpers also contain legacy compatibility ALTER operations; they require a
separate bounded review rather than speculative removal here.

## Static/mock verification

- Remaining-boundary probe: **20/20**
- OH-AUTO1-047 upstream boundary probe: **14/14**
- PHP lint: changed service and probes pass
- Migration ownership and reachable callers confirmed statically
- No database connection, SQL execution, module activation, or application
  runtime request was performed

## Scope

- No Core changes
- No schema or migration modifications
- No route changes
- Stage transition transaction/data behavior preserved
- Database-backed tests remain suspended
