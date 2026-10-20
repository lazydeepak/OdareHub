# OH-AUTO2-MFG-002 — Fulfillment Dispatch Operator Contract

**Date:** 2026-10-20
**Baseline:** `5835e25d60b58cc3fd597e8727547181da827cdd`
**Authorization:** Read-only source investigation and documentation only
**Status:** Contract verification complete; operator exposure blocked

## 1. Baseline reconciliation

The local OH-AUTO2-MFG-001 report exists at:

`docs/architecture/oh-auto2-mfg-001-manufacturing-operator-workflow-gap-assessment-2026-10-20.md`

It is present in the local working tree but is untracked, so it is not yet
independently verifiable from GitHub `main`. The local `HEAD` is `5835e25d` and
the working tree contains pre-existing unrelated Procurement logs, a
supplier-probe backup, and PHPUnit cache. Those artifacts were not modified or
removed.

The assignment registry records MFG-001's local assessment disposition. This
report adds MFG-002 as a contract assessment; it does not reserve or authorize
an implementation assignment.

## 2. Execution graph

### Existing administrative graph

```text
/apps/manufacturing/dispatch-ops
  -> Auth::requireRouteAccess('/manufacturing/dispatch-ops', method)
  -> Auth::requireCsrf()
  -> DispatchOpsController::{markReady|prepare|complete}
  -> DispatchOpsService::{markReady|prepare|complete}
  -> DispatchOpsService::ensureSchema()
  -> requireRow(id) + dispatchWorkflowHints()
  -> direct UPDATE dispatch_entries
```

Preparation has a parallel administrative graph:

```text
/apps/manufacturing/dispatch-ops/preparation
  -> route access + CSRF
  -> DispatchOpsController::savePreparation()
  -> DispatchOpsService::savePreparation()
  -> ensureSchema() + order/dispatch reads
  -> direct INSERT/UPDATE dispatch_entries
  -> INSERT dispatch_preparation_logs
```

### Operator graph today

The operator Fulfillment surface has only:

- `POST /u/{username}/preparation/bundle/create`; and
- `POST /u/{username}/dispatch/create`.

There are no operator routes for `markReady`, `prepare`, or `complete`.
`resolveOperatorPostRoute()` authenticates, confines the username, checks the
required app when supplied, and filters the assigned operator view. It does
not establish dispatch-entry record ownership or dispatch-module mutation
authority. The existing dispatch create route also does not call a
Manufacturing-owned dispatch record-scope service.

## 3. Action matrix

| Action | Service entry point | Existing legal behavior | Operator authorization | Transaction/audit | Decision |
|---|---|---|---|---|---|
| Mark ready | `DispatchOpsService::markReady(int $id)` | Rejects completed rows; workflow gate checks stage/releasable quantity; writes `completion_status=ready` | Admin route access only; `requireRow()` selects by ID with no operator scope | Runtime `ensureSchema()`; direct UPDATE; no transaction; no AuditLog call | **BLOCKED** |
| Prepare | `DispatchOpsService::prepare(int $id, array $input)` | Rejects completed rows; validates mode/flow/source enums; writes cases, pallets, flow metadata and `completion_status=prepared` | Admin route access only; no record-level operator scope | Runtime `ensureSchema()`; direct UPDATE; no transaction; no AuditLog call | **BLOCKED** |
| Complete | `DispatchOpsService::complete(int $id)` | Already-completed row returns; otherwise workflow gate checks stage/releasable quantity; writes completed/dispatched fields | Admin route access only; no record-level operator scope | Runtime `ensureSchema()`; direct UPDATE; no transaction; no AuditLog call | **BLOCKED** |
| Save preparation | `DispatchOpsService::savePreparation(array $input)` | Validates order/product/date/quantity and prevents quantity beyond outstanding order quantity; creates or updates dispatch entry and preparation log | Admin preparation route only; no operator route or record scope | Runtime `ensureSchema()`; two related writes without transaction; no AuditLog call | **BLOCKED for operator exposure** |

No action is `READY` or `ALREADY IMPLEMENTED` for operator exposure. The
administrative workflow exists, but that is not equivalent to an authorized
operator contract.

## 4. Required contract findings

### Ownership

Manufacturing owns `DispatchOpsService`, the dispatch routes, and the
Fulfillment operator surface. Shell may remain a generic route/composition
surface; it must not gain dispatch business logic. A future change should
reuse the service authority rather than duplicate lifecycle SQL in
`apps/Shell/routes.php`.

**Status:** Satisfied as an ownership boundary; no implementation needed here.

### Operator role

The existing operator resolver proves login, same-user workspace confinement,
assigned app admission, and allowed operator-view admission. It does not prove
that an operator may mutate dispatch records. The admin route's route access
check and dispatch navigation role (`role_ops_or_dispatch_leader`) cannot be
silently reused as an operator permission.

**Status:** **Blocked.** An explicit mapping to existing assignment/profile and
module authority evidence is required. No new permission should be invented.

### Exact record scope

`requireRow($id)` performs only `SELECT * FROM dispatch_entries WHERE id=?`.
`markReady`, `prepare`, and `complete` then update by ID. Product/date
workflow hints constrain stage readiness and quantity, but they do not prove
that the authenticated operator may act on that dispatch record.

**Status:** **Blocked.** Module access or an arbitrary dispatch ID is not
record-level authorization.

### Status transitions

The service has four completion statuses: `draft`, `ready`, `prepared`, and
`completed`. However:

- `markReady()` rejects only `completed`; it does not require `draft` or
  reject an already `ready`/`prepared` row;
- `prepare()` rejects only `completed`; it can write `prepared` without
  requiring `ready`; and
- `complete()` returns for `completed`, but otherwise does not require
  `prepared` before writing `completed`.

The stage workflow gate is a separate upstream readiness/quantity check, not a
complete dispatch-entry state machine.

**Status:** **Blocked** pending an explicit transition matrix or evidence that
the existing service contract intentionally permits these transitions.

### Transactions and rollback

The three lifecycle methods call `ensureSchema()` and execute one direct
UPDATE, but do not open, commit, or roll back a transaction. `savePreparation()`
performs a dispatch-entry INSERT/UPDATE followed by a preparation-log INSERT
without a transaction. The service also performs runtime CREATE/ALTER checks
for dispatch preparation storage.

**Status:** **Blocked.** Runtime schema mutation and multi-write atomicity must
be resolved before operator exposure. No live database test was run.

### Audit

`DispatchOpsService.php` has no `AuditLogService` import or audit call. The
administrative controller flashes success/failure based on service exceptions,
but does not require audit success before reporting success. Existing
Manufacturing dispatch-entry controllers use the separate AuditLog authority,
which is evidence that dispatch lifecycle audit is an established concern but
not proof that these service methods satisfy it.

**Status:** **Blocked.** Required lifecycle audit events and failure semantics
must be defined/reused before operator success can be reported.

### Presentation and eligibility

The operator view currently exposes dispatch creation and status display only.
It has no lifecycle action controls. Future controls must be generated from a
server-resolved, record-specific eligibility projection; client-submitted IDs
and hidden fields cannot establish eligibility.

**Status:** **Blocked until role, record scope, and transition evidence exist.**

### Idempotency and duplicate submission

`complete()` is idempotent for an already-completed row by returning early.
`markReady()` and `prepare()` are not idempotency contracts: repeated calls
rewrite the row, and `prepare()` can change metadata/status again. Preparation
creation has an existing order/date lookup and may update an existing entry,
but the related log is appended separately. No explicit request key or
concurrency contract was found.

**Status:** **Blocked for operator use** until duplicate-submit and concurrent
transition behavior is explicitly accepted and isolated-proofed.

## 5. Smallest safe implementation boundary if later authorized

Only after the blockers above are resolved, the smallest coherent slice would
be:

1. a Manufacturing-owned dispatch operator authorization/projection seam that
   resolves eligible records and legal actions for the authenticated operator;
2. thin operator POST handlers under the existing `/u/{username}/fulfillment`
   surface that pass only server-resolved opaque action/record evidence;
3. service reuse for the actual lifecycle mutation, with no duplicated SQL or
   transition logic in Shell;
4. existing audit authority invoked before success is reported; and
5. isolated service-double tests covering allowed/denied roles, in/out-of-scope
   records, every legal/illegal transition, repeated submission, mutation
   failure, audit failure, and redirect/action presentation.

This is a future implementation boundary, not authorization to create routes or
modify production code in OH-AUTO2-MFG-002.

## 6. Verification strategy

The next implementation assignment, if approved, should remain offline first:

- fake the assignment/module/profile context and dispatch record provider;
- fake the existing lifecycle service or DB boundary to observe calls without
  SQL;
- assert no action is rendered for denied, malformed, out-of-scope, completed,
  or illegal-transition records;
- assert authorized action forwarding preserves exact record/action identity;
- assert service exceptions and audit failures do not produce success; and
- assert all generated URLs remain same-user `/u/{username}/*` paths.

Disposable database transaction/concurrency verification is a separate,
explicitly authorized step and was not performed here.

## 7. Decision

| Action | Classification | Reason |
|---|---|---|
| `markReady` | **BLOCKED** | No operator record scope, incomplete transition contract, runtime schema ensure, no transaction, no audit |
| `prepare` | **BLOCKED** | Same blockers plus multi-field lifecycle update and no atomicity/audit contract |
| `complete` | **BLOCKED** | No operator record scope, completion does not require prepared state, no transaction/audit |

OH-AUTO2-MFG-002 therefore authorizes no operator dispatch implementation.
The correct next step is contract resolution, not controller or Shell work.
