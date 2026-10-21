# OH-AUTO2-PROC-002 — Procurement Workflow Discovery

**Project:** OdareHub
**Baseline:** `ecf021ba20ee3814730b31f8ea040f0465d26abc`
**Authorization:** Read-only assessment and bounded implementation
**Decision:** **IMPLEMENTED — bounded read-only first stage**

## Executive decision

The highest-value unfinished Procurement user-facing capability is a bounded
**operator Procurement request review/approval queue**: a same-user operator
surface for assigned or otherwise authorized procurement requests, showing
request state and purchase-order linkage and, if explicitly authorized, the
existing approval handoff.

The accepted product decision authorizes a read-only first stage: own requests
only, Procurement assignment plus explicit `procurement.view`, and exact
creator-to-authenticated-user matching. Approval, cancellation, PO creation,
supplier changes, and receiving remain deferred.

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

## Product decision applied

The accepted product/authority decision is:

1. Visibility is creator-owned only; no all-record fallback exists.
2. Access requires Procurement assignment and explicit `procurement.view`.
3. `procurement.manage` does not grant operator approval authority.
4. `procurement.approve` is reserved for a separate workflow.
5. The first slice is read-only queue presentation; mutation is deferred.

## Implemented smallest scope

The smallest safe slice is one Procurement-owned operator capability
declaration and one same-user `/u/{username}` request
queue that consumes a read-only owner projection. It must:

- remain inside `/u/{username}/*`;
- require authenticated self-workspace identity and existing app authority;
- fail closed when assignment or record-scope evidence is absent;
- reuse existing request status and PO-linkage fields;
- avoid direct Shell business logic, new Core fields, schema changes, and
  speculative supplier/receipt behavior; and
- add no mutation; approval/cancellation/PO creation remain separately gated.

The implementation uses the existing operator contribution registry and generic
contributed-focus rendering bridge. No new permission or schema field was
added.

## Ownership proof and verification

- `ProcurementDashboardController::createRequest()` passes
  `Auth::user()['email']` to `ProcurementOverviewService::createRequest()`.
- `ProcurementDashboardController::intakeManufacturingRequest()` passes the
  same canonical authenticated email to
  `intakeFromManufacturingDemand()`.
- Both sanctioned service paths persist that actor through `nullIfBlank()` into
  `procurement_requests.created_by`; no other non-test application source path
  writes this table.
- The operator projection requires a non-empty authenticated email and uses
  exact `created_by = ?` matching. Null, blank, legacy, or ambiguous rows are
  therefore excluded rather than broadened.
- The provider requires active Procurement assignment and exact
  `procurement.view`; manage-only and approve-only contexts do not contribute.
- The SQL projection is bounded to 50 rows and uses only request status,
  quantity, needed/created dates, reference, and latest PO linkage.

Focused offline contract probe: **20/20**. PHP lint passed for all changed PHP
files. No disposable database or authenticated HTTP executor was run in this
slice; those remain the next environment-bound acceptance steps.

## Exclusions and validation boundary

No database mutation, SQL mutation, activation, deployment, or production
operation was performed. Completed supplier acceptance and transaction work was
reused as evidence. Manufacturing dispatch, Material restoration, SBAIO
incident work, and production deployment remain excluded.
