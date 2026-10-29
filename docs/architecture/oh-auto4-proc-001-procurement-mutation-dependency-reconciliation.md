# OH-AUTO4-PROC-001 — Procurement Mutation Dependency Reconciliation

**Date:** 2026-10-30
**Status:** **BLOCKED — exact tenant and record-authority contract is absent**
**Baseline:** `35e4782fdcc72ee457a25ec891f666621f13a849`

## Result

OH-AUTO4-PROC-001 is not already completed and no new Procurement mutation was
implemented. The repository contains mature, transaction-tested administrative
mutations, but none currently satisfies the requested combination of
record-level authorization, tenant isolation, lifecycle, audit, rollback, and
duplicate-request protection.

Implementing an operation now would require inventing organization authority
or treating app access as record ownership. Both are explicitly outside the
available Procurement contracts.

## Strongest existing candidate

The closest operation is approved-request to purchase-order conversion:

```text
POST /apps/procurement/orders/create-from-request
  -> Procurement app access + CSRF
  -> ProcurementDashboardController::createOrderFromRequest()
  -> ProcurementOverviewService::createOrderFromApprovedRequest()
  -> audit schema readiness
  -> transaction
  -> lock procurement_requests row FOR UPDATE
  -> check existing PO by request_id
  -> require request_status = approved
  -> insert PO and PO line
  -> close request
  -> record creation audit
  -> commit / rollback
```

This path has valuable existing guarantees:

- request-row serialization prevents concurrent duplicate conversion;
- an existing PO returns `already_exists` rather than inserting another;
- request state and quantity are checked before insertion;
- supplier eligibility is validated before transaction setup;
- PO, line, request closure, and audit are in one transaction; and
- focused source and disposable concurrency evidence exists in the historical
  Procurement records, including the **10/10** concurrency probe.

Those guarantees do not establish who is authorized to convert the request or
which organization owns it.

## Authorization and tenant evidence

The current mutation routes and controllers establish:

- `Auth::requireAppAccess('procurement')`; and
- `Auth::requireCsrf(...)` before controller/service dispatch.

The service intentionally does not duplicate route authorization. It receives
an actor email for audit and persistence, but the conversion query selects the
request by opaque `id`; it does not constrain the request by authenticated
actor, organization, membership, or tenant.

The Procurement schema has `created_by` fields and unique reference keys, but
no organization/tenant identity column on requests, purchase orders, suppliers,
or receipts. `created_by` is not an organization boundary and cannot be
reinterpreted as one.

The existing operator contract is explicitly read-only and creator-owned. It
proves exact `created_by` visibility for own requests, not approval, PO
creation, supplier, or receiving mutation authority. Its accepted decision
reserves `procurement.approve` for a separate workflow.

The `ProcurementIntakeDescriptor` contains `organization_scope` and
`record_scope` only as future evidence fields. Its validator explicitly states
that it does not claim actor, organization, or source-record authorization; the
descriptor is not persisted, no unique replay key is stored, and no runtime
intake path is wired to it.

## Candidate disposition

| Operation | Existing safety evidence | Irreducible gap |
|---|---|---|
| Supplier create/update | Atomic audit transaction for create; app access/CSRF | No supplier organization ownership or duplicate business-key contract |
| Request create | Atomic insert/audit transaction | No tenant binding or request idempotency contract |
| Request approve/cancel | Row lock, lifecycle checks, audit, rollback | No approver role/record scope/tenant predicate beyond app access |
| PO create/issue/cancel | Transactions and status validation | No tenant/record authority; manual creation permits broad caller input |
| Approved request → PO | Best duplicate/concurrency behavior; 10/10 evidence | No tenant isolation or authorized approver mapping |
| Receipt create/post | PO/line ownership and PO serialization | No receiving identity/tenant scope or duplicate receipt request contract |

No candidate can be selected without policy invention.

## Exact dependency requiring owner decision

Procurement ownership must approve, before implementation:

1. the authorized actor profiles and exact existing permission mapping for one
   mutation;
2. the authoritative organization/tenant context and its source of truth;
3. the record-level predicate connecting that context to the selected request,
   PO, supplier, or receipt;
4. fail-closed behavior for absent, blank, malformed, ambiguous, or conflicting
   organization and ownership evidence;
5. the canonical lifecycle transition and invalid-transition behavior;
6. the migration-owned persistence shape needed to carry tenant identity, if
   required;
7. the transaction boundary covering all business, audit, and related-row
   writes; and
8. the durable duplicate/retry/concurrency key and conflict response.

Until those decisions exist, adding a route, permission, organization field,
ownership predicate, migration, or mutation probe would either fabricate policy
or encode an unapproved contract. The existing deny-by-default operator
boundary must remain unchanged.

## Scope and validation boundary

- No Procurement, Core/Auth/ACL, Shell, route, schema, migration, or runtime
  code changed.
- No database connection, SQL mutation, browser, HTTP, activation, deployment,
  or production operation occurred.
- Existing unrelated working-tree modifications, logs, caches, backups, PDFs,
  and incident evidence were preserved.
- Existing Procurement probes were reused as evidence; no new probe was added
  because it would assert an unapproved authorization policy.

## References

- `engineering/Procurement/work.md`
- `engineering/Procurement/decisions.md`
- `docs/architecture/oh-auto2-proc-002-procurement-workflow-discovery-2026-10-21.md`
- `docs/architecture/oh-auto3-001-product-readiness-functional-gap-audit-2026-10-22.md`
- `apps/Procurement/routes.php`
- `apps/Procurement/Controllers/ProcurementDashboardController.php`
- `apps/Procurement/Services/ProcurementOverviewService.php`
- `apps/Procurement/Services/ProcurementIntakeDescriptor.php`
- `apps/Procurement/Services/ProcurementIntakeDescriptorValidator.php`
- `apps/Procurement/Tests/probe_procurement_concurrency.php`
