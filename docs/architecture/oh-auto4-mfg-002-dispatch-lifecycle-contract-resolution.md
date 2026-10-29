# OH-AUTO4-MFG-002 — Dispatch Lifecycle Contract Resolution

**Date:** 2026-10-30
**Status:** **BLOCKED — precise owner contract dependency remains unresolved**
**Baseline:** `45ccbb4d4fde5f366be730ce1cd09cf3138c1a69`

## Result

OH-AUTO4-MFG-002 is not complete and no dispatch mutation was implemented.
The current source contains several administrative workflow mechanisms, but
they do not compose into an independently safe operator operation. Reusing one
of them would require inventing an operator authority or silently mapping
incompatible lifecycle contracts.

## Execution graph reconciliation

### Fulfillment/admin lifecycle graph

```text
/apps/manufacturing/dispatch-ops
  -> route access + CSRF
  -> DispatchOpsController::{markReady|prepare|complete}
  -> DispatchOpsService::{markReady|prepare|complete}
  -> runtime ensureSchema()
  -> opaque dispatch-entry lookup by id
  -> direct dispatch_entries UPDATE
```

`markReady`, `prepare`, and `complete` do not perform operator record-scope
authorization, do not enforce the complete completion-status sequence, do not
open a transaction, and do not call the established audit authority.

### Module workflow graph

```text
DispatchEntriesController::transition()
  -> WorkflowTransitionEngine validation
  -> WorkflowRegistry dispatch action/permission rules
  -> assignment + machine/part scope check
  -> DispatchWorkflow transition preparation
  -> transaction around EntityStore update + transition-history insert
  -> post-commit AuditLogService / workflow audit calls
```

This is a stronger administrative module path, but it is not an operator
contract. Its dispatch workflow states are `draft`, `submitted`, `approved`,
`hold`, `finalized`, and `cancelled`, while the Fulfillment completion path
uses `draft`, `ready`, `prepared`, and `completed`. No approved mapping exists
between these state machines or their action sets.

### Operator graph

The existing operator routes provide dispatch creation and preparation-bundle
creation only. `resolveOperatorPostRoute()` establishes authenticated
same-user confinement, assigned-app/view admission, and CSRF handling. The
operator dispatch adapter is read/display-oriented and the operator create
path does not establish dispatch-entry record ownership. No operator route
exists for `markReady`, `prepare`, or `complete`.

## Authorization invariants and gap

Existing `WorkflowTransitionEngine::validateAssignmentAndScope()` can verify:

- required app assignment;
- required module access;
- workflow permission keys; and
- optional `machine_ids` / `part_ids` containment.

That evidence is insufficient for MFG-002 because:

1. no owner decision assigns an operator profile to dispatch lifecycle actions;
2. no dispatch-specific record-scope predicate covers the dispatch entry's
   product, order, QC, destination, or upstream release relationship;
3. the generic scope helper permits scope fields to be absent/empty, so it
   cannot be treated as proof of bounded dispatch ownership; and
4. the existing operator route does not invoke this workflow validation for a
   dispatch mutation.

Using module assignment, `role_ops_or_dispatch_leader`, or an opaque submitted
ID as a substitute would fabricate authority and violate the established
operator boundary.

## Transition, transaction, audit, and idempotency invariants

The two existing lifecycle contracts are not interchangeable:

- `DispatchOpsService::markReady()` rejects only completed rows and writes
  `completion_status=ready` directly.
- `DispatchOpsService::prepare()` accepts any non-completed row, writes
  preparation metadata and `completion_status=prepared`, and has no atomic
  audit record.
- `DispatchOpsService::complete()` treats an already-completed row as a
  successful no-op and otherwise writes completed/dispatched fields without
  requiring `prepared`.
- `WorkflowTransitionEngine` has explicit workflow permissions and audit calls,
  but calls schema initialization and performs its update, transition event,
  and audit sequence without one internal transaction. The administrative
  controller's surrounding transaction is not an operator guarantee and does
  not solve the state-machine mismatch.
- `DispatchWorkflow::recordTransition()` and `WorkflowGovernance::recordEvent()`
  are separate history authorities; no accepted rule defines which events are
  mandatory for an operator action or how audit failure affects success.
- Only `complete()` has a documented repeated-call no-op. `markReady()` and
  `prepare()` can rewrite state/metadata, and no request-key or concurrent
  claim contract exists.

## Irreducible dependency

Before any implementation, Manufacturing ownership must approve a single
dispatch operator contract that defines:

1. eligible operator profiles and exact permission keys for each action;
2. the authoritative record-scope predicate and fail-closed behavior for
   missing, empty, malformed, or unrestricted scope evidence;
3. one canonical state machine and its mapping to legacy completion/status and
   workflow columns;
4. migration-owned schema readiness with runtime DDL prohibited;
5. one transaction boundary covering every required row/history/audit write;
6. audit authority, event taxonomy, and audit-failure success semantics; and
7. duplicate, retry, and concurrent-request behavior.

Until those decisions are approved, no operation among `markReady`, `prepare`,
`complete`, module workflow actions, or a new operator wrapper is independently
safe. A new regression probe would encode an unapproved policy, so none was
added.

## Scope and validation outcome

- No application, Core/Auth/ACL, Shell, route, schema, migration, database,
  browser, HTTP, activation, deployment, or live-test change occurred.
- No production database was contacted or mutated.
- Existing unrelated dirty files, logs, caches, backups, and incident evidence
  were preserved.
- Focused source PHP lint, architecture gates, deletion-family checks, and
  deployment-readiness verification remain the applicable validation; no
  DB-backed execution was performed.

## References

- `docs/architecture/oh-auto4-mfg-001-dispatch-lifecycle-dependency-reconciliation.md`
- `docs/architecture/oh-auto2-mfg-002-fulfillment-dispatch-operator-contract-2026-10-20.md`
- `engineering/Manufacturing/decisions.md`
- `engineering/Manufacturing/work.md`
- `apps/Manufacturing/Services/DispatchOpsService.php`
- `apps/Manufacturing/modules/DispatchEntries/Services/DispatchWorkflow.php`
- `apps/Manufacturing/modules/DispatchEntries/Controllers/DispatchEntriesController.php`
- `apps/Manufacturing/modules/Workflow/Services/WorkflowTransitionEngine.php`
- `apps/Manufacturing/modules/Workflow/Services/WorkflowRegistry.php`
- `apps/Manufacturing/Services/ProcessingOperationService.php`
- `apps/Manufacturing/Services/ProductionOperationService.php`
