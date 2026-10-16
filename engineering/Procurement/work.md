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

## In Progress

- [x] OH-AUTO1-018 — Corrected misplaced receipt-line validation in `ProcurementOverviewService::createOrder()` and added `probe_order_receipt_method_boundaries.php`.
- [x] OH-AUTO1-020 — Completed non-mutating transaction-contract audit; disposable DB execution is blocked because an isolated target and credentials were not proven.
- [x] OH-AUTO1-029 — Verified Procurement transactions against an independently attested disposable MySQL instance and added app-owned rollback boundaries.
- [x] OH-AUTO1-031 — Verified concurrent conversion and receipt idempotency against a fresh independently attested disposable MySQL instance; added request/PO row-lock boundaries.

## Next

- [ ] OH-AUTO1-034 — Reconcile updated integration baseline and source inventory.

## Blocked

- Receipt over-quantity policy and concurrency behavior for downstream audit/status paths remain unverified.

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
- First concurrency run reproduced duplicate conversion and stale receipt-status defects; corrected rerun passed.
- Consolidated regression runner: all 6 suites passed; Procurement concurrency 10/10 and Manufacturing transaction/concurrency 21/21 + 11/11.
- Each live suite used a separate authorized disposable instance; no fixture state was shared across suites.
- Temporary MySQL datadir/socket/credentials were removed; installed ServBay PID 1181 and port 3306 remained unchanged.
- Remaining evidence and limits are recorded in `docs/architecture/transactional-business-verification-audit-2026-10-14.md`.

## OH-AUTO1-051 — Procurement write authorization integrity (2026-10-14)

Status: Completed with a bounded Procurement-owned correction; no database
execution authorized.

Audited purchase-order creation, approved-request conversion, and receipt
creation. Their routes enforce `procurement` app access and CSRF, while the
three controller methods previously relied on route-only app authorization.
That allowed direct controller invocation to bypass the app-access check.

Correction: `createOrder()`, `createOrderFromRequest()`, and `createReceipt()`
now re-check `Auth::requireAppAccess('procurement')` before CSRF and service
dispatch. Existing service ownership checks remain intact: approved-request
conversion validates active suppliers, approved request state, request-row
locking, and existing PO ownership; receipt creation validates PO and line
identity and line-to-order ownership.

Static write-authorization probe: 40/40. No new `procurement.manage` or
`procurement.approve` semantics were invented because no operation mapping
contract exists for those manifest permissions. Database-backed probes remain
suspended.

Focused validation also passed the method-boundary, receipt-line, and form
probes (9/9, 8/8, 13/13). The broader transaction-contract probe reports one
pre-existing Manufacturing schema-ensure expectation failure after OH-AUTO1-047
removed that runtime DDL; all Procurement transaction assertions pass.

Residual contract question: manual `createOrder()` accepts a caller-supplied
supplier identifier without the active-supplier validation already present in
approved-request conversion. No documented manual-order ownership rule was
found, so this remains escalated rather than changed speculatively.
