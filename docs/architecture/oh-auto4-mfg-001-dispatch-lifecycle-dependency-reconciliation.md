# OH-AUTO4-MFG-001 — Dispatch Lifecycle Dependency Reconciliation

**Date:** 2026-10-30
**Status:** **BLOCKED — no safe implementation authorized**
**Scope:** Manufacturing dispatch lifecycle continuation only

## Result

OH-AUTO4-MFG-001 is unfinished. The repository contains administrative
dispatch lifecycle operations and an operator dispatch-creation surface, but
does not contain an approved operator lifecycle contract. No dispatch lifecycle
operation was implemented in this assignment.

The existing assignment history is authoritative: OH-AUTO2-MFG-001 identified
the product gap, while OH-AUTO2-MFG-002 explicitly blocked operator exposure of
`markReady`, `prepare`, and `complete` pending record scope, legal transitions,
transaction/rollback, schema ownership, audit semantics, and idempotency.

## Current source evidence

- `DispatchOpsService::markReady()`, `prepare()`, and `complete()` call runtime
  `ensureSchema()`, load by opaque dispatch ID, and perform direct updates.
- Those methods do not establish operator record scope, a complete legal
  `draft -> ready -> prepared -> completed` state machine, transaction/rollback,
  or `AuditLogService` success/failure semantics.
- `DispatchWorkflow` and `DispatchEntriesController::transition()` provide
  stronger module-owned transition, transaction, transition-history, ledger,
  and audit components, but their current path is an administrative module
  surface and does not establish `/u/{username}` operator authority.
- Existing operator route admission proves identity, same-user confinement,
  assigned-app/view admission, CSRF, and route access; it does not prove that
  the authenticated operator may mutate a particular dispatch record.
- Dispatch migrations exist for storage and transition-history shape, but their
  presence does not establish the missing cross-boundary authorization contract
  or authorize runtime schema initialization.

## Specific unresolved dependency

The blocking dependency is an owner-approved Manufacturing operator authority
contract that maps an authenticated operator to an exact dispatch-record scope
and legal action set. Until that contract exists, any new operator handler
would either trust a browser-submitted dispatch ID or reuse admin access as
operator authority. Both violate the existing security boundary.

The contract must explicitly define:

1. eligible operator profiles/module authority for each action;
2. record scope based on existing authoritative Manufacturing evidence;
3. legal source/target statuses, including repeated and concurrent requests;
4. migration-owned schema readiness without runtime DDL;
5. atomic mutation and rollback behavior;
6. transition-history and AuditLog event requirements, including audit failure;
7. duplicate/idempotency semantics; and
8. isolated fake/fixture proof before any separately authorized disposable DB
   verification.

## Non-implementation decision

No smallest safe lifecycle operation can be selected from the current evidence.
`markReady`, `prepare`, and `complete` are all blocked. Adding isolated
regression coverage for an unapproved operator contract would create a false
implementation signal, so no new lifecycle probe was added. Existing
administrative/module tests and containment remain unchanged.

No Core/Auth/ACL, Shell, route, schema, migration, database, authorization,
identity, organization, activation, deployment, browser, HTTP, or live-test
operation occurred. The SBAIO D-01–D-04 proposals remain unrelated and are not
treated as approved policy.

## References

- `engineering/Manufacturing/decisions.md`
- `engineering/Manufacturing/work.md`
- `docs/architecture/oh-auto2-mfg-001-manufacturing-operator-workflow-gap-assessment-2026-10-20.md`
- `docs/architecture/oh-auto2-mfg-002-fulfillment-dispatch-operator-contract-2026-10-20.md`
- `docs/architecture/oh-auto4-product-readiness-reconciliation.md`
- `apps/Manufacturing/Services/DispatchOpsService.php`
- `apps/Manufacturing/modules/DispatchEntries/Services/DispatchWorkflow.php`
- `apps/Manufacturing/modules/DispatchEntries/Controllers/DispatchEntriesController.php`
