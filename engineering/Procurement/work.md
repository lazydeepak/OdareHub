> Status: classification evidence insufficient (metadata reconciliation 2026-09-13); no promotion; workspace has no completed/evidence records; preserved.
# Procurement — Work

## Current Focus

State the current verified objective.

## 2026-10-16 — OH-AUTO2-119 supplier eligibility integrity

- [x] Confirmed the existing supplier lifecycle contract: `active` is eligible;
  `inactive`, `disabled`, and `blocked` are rejected by approved-request
  conversion. The manual order form requires a supplier, while the existing
  service contract preserves nullable/automatic supplier behavior.
- [x] Centralized that existing rule in `assertSupplierEligible()` and applied
  it to manual order creation before schema access, transaction opening, PO
  insertion, linked-request closure, or audit creation. Approved-request
  conversion uses the same rule.
- [x] Isolated fake-reader and source-order proof passes **22/22**; no database,
  SQL, browser, activation, or deployment operation was used.
- [x] Existing route app-access/CSRF authority and transaction boundaries remain
  unchanged. Historical orders are untouched.

## 2026-10-16 — OH-AUTO2-120 eligible supplier selection

- [x] Added `eligibleSupplierOptions()` with the established
  `inactive`/`disabled`/`blocked` filter applied before supplier ordering and
  the existing result limit.
- [x] Requests and purchase-order forms now use the eligible source; dashboard
  supplier administration retains unrestricted `supplierOptions()` so inactive
  suppliers remain selectable for review/reactivation.
- [x] Preserved the required manual supplier selector, approved-request Auto
  supplier option, historical order loading, and server-side
  `assertSupplierEligible()` authority.
- [x] Isolated selection/source/rendering contract proof passes **16/16**; no
  database, SQL, browser, activation, or deployment operation was used.

## 2026-10-16 — OH-AUTO2-121 manual request-link integrity

- [x] Confirmed the existing approved-request conversion contract: linked
  requests must be approved, must exist, and may have only one purchase order;
  conversion already locks the request row before checking linkage.
- [x] Applied the same validation and row-lock boundary inside manual order
  creation. Standalone orders with no request retain their existing behavior.
- [x] Added `eligibleRequestOptions()` for the manual order form only; request
  history listings remain unrestricted and the empty standalone option remains.
- [x] Isolated linkage/transaction/source proof passes **26/26**; no database,
  SQL, browser, activation, or deployment operation was used.

## 2026-10-16 — OH-AUTO2-122 controller write-authorization closure

- [x] Confirmed all 12 registered Procurement POST routes already enforce app
  access, while nine controller entry points lacked the same direct guard.
- [x] Added `Auth::requireAppAccess('procurement')` before CSRF, input handling,
  and service dispatch in the nine missing methods. The three OH-AUTO1-051
  protected methods remain unchanged.
- [x] Expanded the write-authorization probe to all 12 controllers/routes;
  **112/112** assertions pass. No service permission policy was duplicated.

## 2026-10-16 — OH-AUTO2-123 non-mutating Procurement GET pages

- [x] Confirmed all four Procurement GET routes called
  `ProcurementOverviewService::ensureSchema()`. Dashboard activity and list
  transition enrichment also reached `AuditLogService::ensureSchema()`.
- [x] Removed schema initialization from the four GET route closures and made
  the Procurement read path inspect required table/column metadata only.
  Missing or incompatible essential storage now fails closed with an explicit
  unavailable state; optional audit storage is reported separately.
- [x] Preserved `ensureSchema()` in POST/mutation routes and business write
  paths. No Core or AuditLogService change was made.
- [x] Isolated GET read-safety proof passes **61/61**; requested linkage,
  supplier, authorization, form, receipt, and transaction regressions pass.
  No database, SQL, browser, activation, or deployment operation was used.

## 2026-10-16 — OH-AUTO2-124 GET readiness contract completeness

- [x] Audited the GET dependency matrix: Procurement supplier, request, PO,
  PO-line, and receipt reads; request/PO/supplier joins; status filters; ID
  ordering; complete audit activity fields and app filter; and optional
  Manufacturing-demand/request join fields.
- [x] Extended metadata readiness to validate the demonstrated selected,
  joined, filtered, ordered, and grouped columns. Incomplete audit storage is
  unavailable rather than treated as empty history.
- [x] Optional Manufacturing-demand storage now has separate readiness and
  unavailable presentation; Procurement pages are not blocked by that source.
- [x] Isolated readiness contract proof passes **74/74**; no database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-16 — OH-AUTO2-125 CSRF-before-provisioning integrity

- [x] Confirmed all 12 Procurement POST routes previously ran app access,
  session boot, schema initialization, then controller-level CSRF validation.
- [x] Added the existing `Auth::requireCsrf()` call after session boot and
  before `ProcurementOverviewService::ensureSchema()` on every POST route.
  Controller-level CSRF checks remain unchanged as defense in depth.
- [x] Expanded route/controller authorization proof passes **160/160**;
  invalid-token rejection is structural/instrumented, with no database,
  schema, browser, activation, or deployment operation.

## 2026-10-16 — OH-AUTO2-126 receipt purchase-order identity integrity

- [x] Confirmed `createReceipt()` previously normalized missing, zero,
  negative, and malformed `po_id` values to `null`, conditionally skipped the
  purchase-order lookup, and could proceed to receipt insertion with a null
  identity. The receipt form itself requires a PO; `po_line_id` remains
  intentionally optional.
- [x] Added strict receipt-only PO identity normalization before audit/schema
  setup and made the existing purchase-order `FOR UPDATE` lookup unconditional
  for valid identities. The validated exact PO ID is used for insertion and
  posted-status recomputation.
- [x] Added isolated injectable-reader and source-order proof: **18/18**.
  Existing receipt-line, transaction, authorization, GET-readiness, supplier,
  linkage, and form regressions remain passing. No database, SQL, browser,
  activation, or deployment operation was used.

## 2026-10-16 — OH-AUTO2-127 receipt posting transaction integrity

- [x] Confirmed `setReceiptStatus()` previously updated the receipt, recorded
  audit, and recomputed PO status without one transaction or a PO lock.
- [x] Added one explicit commit/rollback boundary. Posting now establishes the
  receipt's PO, locks the PO first, re-locks and revalidates the receipt, then
  performs update, audit, recomputation, and one commit.
- [x] Isolated transaction/order proof passes **24/24**, including simulated
  update, audit, and recomputation failures rolling back. No database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-127R receipt posting reconciliation

- [x] Confirmed the prior implementation left audit DDL reachable through
  `AuditLogService::logEvent()` inside the transaction and performed the first
  receipt identity read after transaction start.
- [x] Moved explicit audit initialization before transaction start and moved
  preliminary receipt-to-PO discovery outside the transaction. The transaction
  now begins with PO `FOR UPDATE`, then receipt-row `FOR UPDATE` revalidation,
  update, audit, recomputation, and one commit/rollback boundary.
- [x] Strengthened the source/call-graph and instrumentation proof to **29/29**;
  no database, SQL, browser, activation, or deployment operation was used.

## In Progress

- [x] OH-AUTO2-128 — validated and canonicalized receipt status during creation
  before audit/schema setup, transaction opening, or receipt insertion; preserved
  the existing `received`/`posted`/`cancelled` contract and verified in isolation.

- [x] OH-AUTO2-129 — direct PO issue/cancel transitions now use a bounded
  Procurement transaction without changing receipt recomputation ownership.

- [x] OH-AUTO2-130 — direct request approval/cancellation transitions now use
  a bounded Procurement transaction without changing PO-conversion ownership.

- [x] OH-AUTO2-131 — manual PO creation now validates and canonicalizes status
  before audit setup, transaction opening, and insertion while preserving the
  existing six-status vocabulary and draft default.

- [x] OH-AUTO2-132 — manual request creation now validates and canonicalizes
  status before reference generation, audit access, and insertion while
  preserving the existing four-status vocabulary and draft default.

- [x] OH-AUTO2-133 — manual request insertion and creation audit now share one
  explicit transaction with audit initialization before transaction start and
  rollback on exceptions or non-OK audit results.

- [x] OH-AUTO2-134 — supplier selection now trims ordinary spaces before the
  established exclusion-based inactive/disabled/blocked filter, preserving
  null and legacy-status eligibility.

## Next

- [ ] OH-AUTO1-034 — Reconcile updated integration baseline and source inventory.

## Completed

- `createOrderFromApprovedRequest()`, `createOrder()`, and `createReceipt()` now use explicit commit/rollback boundaries.
- The prior `createOrder()` partial-write defect was reproduced and corrected; no Core or schema change was required.
- `createOrderFromApprovedRequest()` now locks the request row before its existing-PO check.
- `createReceipt()` now locks the purchase-order row through insertion and posted-status recomputation.

## Evidence

- Read-only method-boundary probe: 9/9 passed.
- Existing receipt-line contract probe: 8/8 passed.
- Existing form contract probe: 13/13 passed.
- Static transaction-contract audit: 30/30 passed.
- Disposable authorizer safety probe: 9/9 passed.
- Isolated Procurement behavioral verification: 15/15 passed.
- Isolated concurrency/idempotency verification: 10/10 passed.

Each category includes direct source validation and the instrument runner's detailed results.