# OH-AUTO3-MFG-SCOPE-001 — Production Operator Authorization Contract

**Status:** Implemented under accepted bounded authorization
**Owner:** Manufacturing / Production Plans
**Date:** 2026-10-25

## 1. Decision Summary

The active Production operator surface must not continue to use Shell-owned
direct reads and opaque-ID status writes as its authority boundary. Manufacturing
must own the Production operator projection and mutation authorization; Shell
may retain route and presentation compatibility while delegating business
decisions to Manufacturing.

The accepted SCOPE-002 implementation delegates Production authority to
Manufacturing without changing schema, database configuration, Core, roles, or
permissions. The exact existing `production` module token and assignment
profiles are used; no new authority was introduced.

## 2. Active Runtime Evidence

### 2.1 Operator read path

The active `/u/{username}/production` path is declared in
`apps/Shell/routes.php` and renders through `OperatorLayerService` and
`OperatorSurfaceComposer::buildProductionFocusData()`.

The active composer directly queries:

- `daily_orders` for risk summaries and coverage rows;
- `production_plans` for the operator queue;
- `products` and `machines` for form options.

The queue query at `apps/Shell/Composers/OperatorSurfaceComposer.php:1573-1589`
has no user part/machine predicates before its `LIMIT 30`. The coverage queries
at approximately lines 1527-1571 likewise have no operator scope predicate.

### 2.2 Operator mutation path

`apps/Shell/routes.php:885-913` handles
`POST /u/production/plan/status` and currently:

1. checks operator route/session and CSRF;
2. validates the integer `plan_id` and two target strings;
3. executes `UPDATE production_plans SET status = ? WHERE id = ? LIMIT 1`.

It does not call a Manufacturing operator authorization method, load the target
record for scope verification, update the corresponding approval state, invoke
the ProductionPlan entity service, or establish owner-level audit evidence.

### 2.3 Existing Manufacturing service boundary

`apps/Manufacturing/Services/ProductionOperationService::build()` already
accepts a user and applies `machine_ids` and `part_ids` predicates in
`productionRows()` before result delivery. This is useful read-query logic,
but it is an administrative operation projection, not an operator authority:

- `scopeForUser()` converts absent or malformed scope into an empty list;
- it does not verify Manufacturing assignment or module authority;
- it does not distinguish read-only/display users from mutation-capable users;
- it has no Production record mutation authorization method.

Therefore the method may be reused behind a stricter operator boundary, but it
must not be treated as sufficient authorization unchanged.

### 2.4 Existing entity/workflow evidence

`apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php` declares the
workflow:

```text
Draft      -> Planned, Cancelled
Planned    -> InProgress, Cancelled
InProgress -> Completed, Cancelled
Completed  -> Closed, Cancelled
Closed     -> none
Cancelled  -> none
```

`ProductionPlanService::transitionStatus()` updates both `status` and the
derived `approval_status`, and routes through `EntityStore` and its hooks.
`ProductionPlanHooks` records before/after lifecycle evidence for the entity
context. `ProductionPlanPolicies::canEdit()` only rejects Completed, Closed,
and Cancelled rows; it does not prove operator record scope.

The active Shell route instead accepts `In Progress` and `Completed` strings
and writes only `status`. The observed `pending` and `in_progress` aliases in
the view are presentation/runtime compatibility values, not yet a formally
approved operator transition grammar.

## 3. Authorization Contract

### 3.1 Read admission

Manufacturing-owned operator projection must require, in this order:

1. authenticated positive user identity;
2. active Manufacturing app assignment and enabled app policy;
3. authorized Production module/capability evidence from the existing access
   policy; the exact token must be confirmed by Manufacturing;
4. non-display surface/account evidence;
5. canonical, validated scope evidence from the existing assignment context.

Missing, malformed, contradictory, or unavailable evidence returns an
unavailable/empty projection. It must never become an unrestricted Production
query.

Read-only operators may receive records when their existing view authority
allows the Production surface, but they must receive `can_mutate = false`.
Display users receive no operator Production projection or actions.

### 3.2 Record selection

Every Production read must apply scope before ordering and limiting:

- `production_plans`: `product_id` and `machine_id` scope where each authority
  exists;
- `daily_orders` coverage: `product_id` scope before grouping/limit;
- `products`: only products within the effective part scope;
- `machines`: only machines within the effective machine scope.

The existing denied sentinel (`[-1]`) and malformed/mixed restriction rules
must remain fail-closed. No presentation label, dashboard type, URL, or
operator-subnav choice may grant record access.

The meaning of an explicitly empty scope must be resolved from the existing
assignment contract before implementation. The Production contract must not
silently copy the administrative `[] = unrestricted` behavior.

### 3.3 Mutation eligibility

The owner authorization method must receive the authenticated user, operation,
record ID, requested target status, and any required reason/note. It must:

1. resolve and validate the same operator authority and scope as the read path;
2. reject read-only/display/missing-profile users;
3. load the ProductionPlan by ID with an owner scope predicate, not by ID alone;
4. reject missing, stale, out-of-scope, terminal, or locked records;
5. validate the current-to-target transition against the canonical workflow;
6. return a bounded decision object without writing.

Authorization must run before any mutation or schema initialization. The Shell
route must not make a second, weaker decision after the Manufacturing decision.

### 3.4 Deny behavior

For missing, stale, malformed, or unauthorized IDs:

- no database mutation occurs;
- no record details are disclosed;
- a generic localized failure is stored in the existing operator flash path;
- redirect normalization remains same-user `/u/{username}/*` confined;
- repeated submission remains denied rather than retried automatically.

## 4. Status Transition Contract

The following transitions are directly evidenced by the entity definition and
are the only candidate operator transitions:

| Current canonical state | Candidate target | Evidence | Decision |
|---|---|---|---|
| `Planned` | `InProgress` | Entity workflow; current UI uses `In Progress` | Confirm canonical storage/display alias |
| `InProgress` | `Completed` | Entity workflow; current UI uses `Completed` | Confirm operator may finalize |
| `Draft` | any operator target | Entity workflow | Not authorized by current operator UI |
| `Completed`, `Closed`, `Cancelled` | any target | Policy/entity terminal behavior | Deny |

`Pending` and `In Progress`/`in_progress` remain observed compatibility strings,
but are not silently treated as canonical mutation states. They remain visible
as historical data while operator actions are emitted only for exact canonical
`Planned` and `InProgress` rows and canonical targets.

## 5. CSRF, Audit, and Persistence Requirements

- Existing Shell CSRF validation remains mandatory.
- Manufacturing authorization must be evaluated after route identity and
  before the owner mutation call.
- Mutation must use the existing ProductionPlan entity/service path or another
  already-authoritative Manufacturing path that preserves workflow, approval
  status, hooks, and audit behavior.
- Direct Shell `UPDATE production_plans` must be removed from the active path.
- No new audit table, schema field, or audit framework is authorized.
- If existing EntityStore audit evidence cannot be proven for the operator
  context, implementation remains blocked pending an owner decision; do not
  create a parallel audit trail.

## 6. Reuse Assessment

### Reusable

- `ProductionOperationService::productionRows()` scope predicate shape;
- existing `UserAssignmentContext` and access-policy resolution;
- Processing operator scope normalization and read-only/mutation evidence as a
  pattern, without duplicating it blindly;
- `ProductionPlanService::find()`, `transitionStatus()`, entity workflow, and
  existing hooks once a valid `EntityContext` is established;
- existing Shell CSRF and redirect-confinement helpers.

### Not sufficient as-is

- `ProductionOperationService::scopeForUser()` — no operator admission and
  fail-open empty-scope behavior;
- `ProductionPlanPolicies::canEdit()` — no record scope or operator identity;
- `resolveOperatorPostRoute()` — app/view admission is not record authority;
- current `production_plans` direct update — bypasses owner workflow semantics;
- preserving `production_date` alone — active Production projection does not
  consume that query key.

## 7. Smallest Safe Implementation Sequence

1. **Owner decision:** confirm Production module authority token, empty-scope
   semantics, mutation profiles, canonical status aliases, and whether the
   existing EntityStore path is the required audit/workflow authority.
2. **Manufacturing authorized projection:** add a Production operator adapter
   or owner service method that validates authority and applies scope before
   limits. Return a stable pure-data shape for the existing Shell view.
3. **Manufacturing mutation authorization:** add a decision method that checks
   identity, assignment, module authority, scope, record state, lock state, and
   canonical transition without writing.
4. **Owner mutation delegation:** route the authorized decision through the
   existing ProductionPlan service/entity workflow. Keep CSRF and redirect
   normalization in Shell; remove direct Shell business SQL.
5. **Shell consumption:** replace direct Production queries with the
   Manufacturing projection while preserving existing markup, route paths,
   flash behavior, and localized labels.
6. **Focused offline proof:** run the required positive and negative matrix
   before any live acceptance or deployment consideration.

## 8. Required Regression Matrix

The implementation probe must prove, without a database connection:

- assigned Production operator receives only in-scope products/machines/plans;
- out-of-scope product and machine rows are absent before the result limit;
- denied sentinel, malformed, mixed, missing, and contradictory scope fail closed;
- module-denied, unassigned, unauthenticated, read-only, and display users are
  denied appropriately;
- authorized worker/leader mutation decisions are distinct from read access;
- stale, missing, terminal, locked, and out-of-scope IDs never reach mutation;
- only approved canonical transitions are accepted;
- `approval_status`/entity-service delegation and audit hooks remain in path;
- CSRF and same-user redirect confinement remain present;
- Shell no longer contains Production business SQL or direct status updates;
- existing Processing, Machines, Parts, architecture, deletion, and readiness
  baselines remain green.

## 9. Explicit Non-Goals

This contract does not authorize:

- new roles, permissions, scope fields, schema, migrations, or audit tables;
- Production execution-progress capture;
- Dispatch lifecycle work;
- Material aggregate restoration;
- Shared Items promotion;
- new operator routes or duplicate module pages;
- a generic cross-domain authorization framework;
- Core, Studio, database, activation, deployment, or live HTTP changes.

## 10. Implementation Evidence

The accepted bounded correction now:

- resolves the existing Manufacturing app/module authority and assignment
  context before Production reads;
- applies existing machine and part scope predicates before grouping, ordering,
  or limits;
- returns an empty projection for denied, malformed, missing, or sentinel scope;
- suppresses create/status actions for read-only and historical noncanonical
  status rows;
- authorizes plan IDs with the same scope before canonical transition checks;
- delegates status changes and plan creation to `ProductionPlanService`, keeping
  EntityStore workflow, approval synchronization, and hooks in the path;
- preserves Shell CSRF and same-user redirect confinement while removing direct
  Shell Production SQL mutations.

The database-free authorization probe passes **14/14**. Existing Processing,
Machine, Parts, work/process, architecture, deletion-family, and deployment
readiness checks remain required delivery validation.

## 11. Disposition

**IMPLEMENTED AND VERIFIED OFFLINE.** Live database, authenticated HTTP,
browser, activation, deployment, and production acceptance remain outside this
slice and are not claimed.
