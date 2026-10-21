# OH-AUTO2-PROC-002 — Procurement Workflow Discovery

**Project:** OdareHub
**Baseline:** `ecf021ba20ee3814730b31f8ea040f0465d26abc`
**Authorization:** Read-only assessment and documentation
**Decision:** **NEEDS DECISION**

## Executive decision

The highest-value unfinished Procurement user-facing capability is a bounded
**operator Procurement request review/approval queue**: a same-user operator
surface for assigned or otherwise authorized procurement requests, showing
request state and purchase-order linkage and, if explicitly authorized, the
existing approval handoff.

This cannot be assigned for implementation under the current evidence. The
repository has complete admin request/order/receipt workflows, but no
Procurement operator capability declaration, `/u/*` route, operator view,
record-scope contract, or defined mapping for `procurement.manage` versus
`procurement.approve`. Implementing the queue or approval action now would
invent product and authorization policy.

## Evidence reviewed

| Evidence | Finding |
|---|---|
| `apps/Procurement/routes.php:14-148` | Four GET pages and twelve POST mutation routes all live under `/apps/procurement/*`; no `/u/*` Procurement route exists. |
| `apps/Procurement/Controllers/ProcurementDashboardController.php:11-304` | Existing entry points cover suppliers, requests, purchase orders, and receipts through admin controllers. They use app access and CSRF, but do not define operator identity or record scope. |
| `apps/Procurement/Views/{dashboard,requests,orders,receipts}.php` | Existing forms expose supplier creation/edit, request creation/approval, PO creation/issue/cancel, receipt creation/posting, and Manufacturing-demand intake. These are admin views, not operator views. |
| `apps/Procurement/Services/ProcurementOverviewService.php:514-1250` | Existing service authority includes supplier, request, PO, and receipt creation/status transitions. No operator projection or assignment-aware request queue exists. |
| `apps/Procurement/manifest.json:12-15,42-88` | App permissions and admin routes are declared, but no operator capability catalog or operation-to-permission mapping is declared. |
| `engineering/Procurement/work.md` and `decisions.md` | Supplier eligibility, selection, linkage, authorization, GET safety, CSRF ordering, status, transaction, and supplier-creation integrity work is recorded complete. No operator workflow is recorded as implemented. |
| `docs/architecture/oh-auto2-106-host-provider-capability-governance-audit-2026-10-16.md:186` | No current Procurement host/operator provider hook was found. |
| `docs/architecture/business-workflow-data-integrity-audit-2026-10-14.md:27-120` | The request → PO → receipt chain is implemented and tested with documented limits; it does not establish operator exposure policy. |

## Existing workflow trace

The implemented admin chain is concrete and reusable:

1. Request creation: `POST /apps/procurement/requests/create`.
2. Request approval/cancellation: `/requests/approve` and `/requests/cancel`.
3. PO creation: manual `/orders/create` or approved-request
   `/orders/create-from-request`.
4. PO issue/cancellation: `/orders/issue` and `/orders/cancel`.
5. Receipt creation/posting: `/receipts/create` and `/receipts/post`.
6. Supplier creation/edit: `/suppliers/create` and `/suppliers/edit`.

The prior Procurement sequence verified the service boundaries and preserved
these contracts. It did not create a missing operator surface, and this
assessment does not repeat those completed audits.

## Candidate comparison

| Candidate | User value | Readiness | Disposition |
|---|---:|---:|---|
| Operator request review/approval queue | High | Medium | **Selected; needs authority/product decision** |
| Operator supplier acceptance/maintenance | Medium | Low | Supplier lifecycle ownership and operator edit authority are undefined. |
| Operator receipt posting | High | Low | Requires receiving identity, PO/line scope, quantity policy, and approval semantics not present in the operator contract. |

The selected candidate is the smallest useful bridge into purchasing
operations: it starts from the existing request lifecycle and can later hand
off to the existing PO conversion boundary without duplicating service logic.

## Required decision before implementation

The product/authority owner must decide:

1. Which operator role or assigned-app condition may see Procurement requests.
2. Whether visibility is all Procurement requests, creator-owned requests,
   supplier/request scope, or another existing scope—not a new ACL field.
3. Whether operators may only review status, may approve/cancel, or may also
   create the PO from an approved request.
4. Whether `procurement.approve` is the intended approval authority and how it
   relates to the existing `procurement.manage` app access.
5. Whether the first slice is read-only queue presentation or includes a
   mutation; any mutation would require the existing CSRF, transaction, audit,
   and isolated verification contracts.

## Conditional smallest implementation scope

After those decisions, the smallest safe slice would be one Procurement-owned
operator capability declaration and one same-user `/u/{username}` request
queue that consumes a read-only owner projection. It must:

- remain inside `/u/{username}/*`;
- require authenticated self-workspace identity and existing app authority;
- fail closed when assignment or record-scope evidence is absent;
- reuse existing request status and PO-linkage fields;
- avoid direct Shell business logic, new Core fields, schema changes, and
  speculative supplier/receipt behavior; and
- add mutation only after an explicit operation/permission decision and
  isolated behavioral proof plan.

No source implementation is authorized by this assessment.

## Exclusions and validation boundary

No database, SQL, browser, HTTP, activation, deployment, or production
operation was performed. Completed supplier acceptance and transaction work was
reused as evidence. Manufacturing dispatch, Material restoration, SBAIO
incident work, and production deployment remain excluded.
