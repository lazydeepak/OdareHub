# OH-AUTO1-018 — Critical Business Workflow and Data Integrity Audit

Date: 2026-10-14
Status: Bounded audit complete; one independently owned Procurement correction completed.

## Scope and safety boundary

- Preserved all prior OH-AUTO changes, logs, and uncommitted work.
- No Core files, schema, migrations, lifecycle state, deployment artifacts, or production/live
  database writes were changed.
- Existing DB-backed behavioral probes were inspected but not executed because they create tables
  and rows in the installed database. Static and source-level probes were used for this slice.

## Business application readiness matrix

| Application | Observed implemented scope | Maturity assessment | Evidence / limitation |
|---|---|---|---|
| Manufacturing | Production, planning, BOM, stage release/override, QC, assembly, dispatch, materials, coverage | Implemented with functional gaps; broad reference application | `StageTransitionService` has explicit path/release rules and focused BOM contract coverage; broad mutation surface needs workflow-by-workflow testing |
| Procurement | Suppliers, requests, manufacturing-demand intake, purchase orders, order lines, receipts, status transitions | Implemented and behaviorally testable; one corrected service-boundary defect | Existing cancellation, request, supplier, receipt-line, and form probes; DB-backed probes not run against installed DB |
| SBAIO | Attendance/timecards/payroll-era bundle routes and sales behavior | Partially implemented / lifecycle constrained | OH-AUTO1-016/017 verified inactive legacy route suppression; no new workflow correctness claim made here |
| Hospitality | Rooms, guests, reservations, front desk, housekeeping, folio/charges | Implemented foundation with focused behavioral coverage | Existing transition, check-in/out, folio, housekeeping, and operator probes; no live acceptance in this audit |
| Parties | Party service and route/service contract | Partial/shared-domain foundation | `probe_parties_service.php` exists; no evidence of a complete cross-app identity integration contract |
| Platform/Core | Shared infrastructure and authority contracts | Not a business workflow owner | Core remained locked; no Core correction authorized |

## Critical workflow inventory

### Procurement request → purchase order → receipt

- Entry points: Procurement request/order/receipt routes and
  `ProcurementDashboardController`.
- Owner: `apps/Procurement`.
- Service boundary: `ProcurementOverviewService`.
- Tables: `procurement_requests`, `procurement_purchase_orders`,
  `procurement_purchase_order_lines`, `procurement_receipts`, and audit log rows.
- Authorization: route handlers enforce app access and CSRF; status methods are service-level
  workflow guards.
- Validation observed: positive quantities, allowed status values, posted-receipt cancellation
  protection, approved-request conversion, supplier activity check for conversion, and receipt
  line-to-order ownership check.
- Idempotency: approved-request conversion checks an existing PO by `request_id`; manufacturing
  intake checks the source demand reference; unique reference indexes protect generated refs.
- Gap: the prior receipt-line validation patch was placed inside `createOrder()` in committed code,
  where receipt-only input could invalidate order creation. The intended validation already existed
  in `createReceipt()`, so the correction removes the misplaced block only.
- Transaction gap: multi-write creation methods are not wrapped in an explicit transaction. This is
  recorded as a follow-up risk; no speculative transaction redesign was introduced in this slice.

### Hospitality reservation and front-desk workflows

- Entry points and transition services are present, with dedicated concurrency and operator-action
  probes.
- Readiness: strongest existing behavioral evidence among inspected business applications.
- Remaining limitation: probes are DB-backed and were not executed against the installed database in
  this audit.

### Manufacturing stage release

- `StageTransitionService::releaseStage()` computes the current path and releasable quantity before
  one upsert into `mfg_stage_readiness`.
- `overrideStageStatus()` validates stage/override values and writes one readiness upsert.
- No cross-table mutation defect was proven statically; concurrency and actuals consistency remain
  test gaps.

### SBAIO and Parties

- SBAIO lifecycle execution was already audited in OH-AUTO1-017; no new business-state mutation was
  performed here.
- Parties has a service contract probe but no demonstrated shared identity adoption across all
  consuming applications; this is an ownership/integration gap, not a proven data corruption defect.

## Verified defect and correction

### Procurement receipt-line validation placement — corrected

Commit `9596d55` intended to harden `createReceipt()` so a supplied `po_line_id` must exist and
belong to the selected `po_id`. The same patch also inserted that validation into `createOrder()` and
overwrote the newly created order ID with receipt input. As a result, `createOrder()` had a
receipt-only branch that could reject or mis-handle valid order input.

Correction:

- Removed the misplaced receipt-line validation from `createOrder()`.
- Preserved the purchase-order-line insert using the newly created `$poId`.
- Kept receipt-line existence and cross-order validation in `createReceipt()`.
- Added `apps/Procurement/Tests/probe_order_receipt_method_boundaries.php`, a read-only structural
  regression probe that asserts the method separation.

This is an app-owned, minimal correction. No route, schema, authorization, or public contract was
changed.

## Cross-domain ownership risks

- Manufacturing demand intake into Procurement is explicitly marked with source metadata, but no
  universal shared-domain reference contract was found for all product/party relationships.
- Parties is not yet demonstrated as the sole identity authority for Procurement, Manufacturing,
  SBAIO, and Hospitality records.
- Procurement service schema bootstrap creates tables at route entry; migration/schema ownership
  and deployment ordering remain separate production-readiness concerns.
- Multi-write Procurement operations and broader Manufacturing operations lack comprehensive
  transaction/concurrency probes. This is an evidence gap unless a concrete failure is reproduced.
- Disabled-app/module behavior remains governed by OH-AUTO1-016/017 lifecycle findings.

## Verification results

- Procurement method-boundary regression probe: **PASS, 9/9**.
- Existing receipt-line contract probe: **PASS, 8/8**.
- Existing Procurement form contract probe: **PASS, 13/13**.
- PHP lint for corrected service and new probe: **PASS**.
- DB-backed Procurement behavioral probes: **NOT RUN**; they mutate the installed database.
- Live HTTP/browser acceptance: **NOT RUN** by authorization.
- Schema/migration/deployment tests: **NOT RUN** in this focused slice.

## Recommended next three bounded tasks

1. Run the Procurement behavioral suite against an explicitly disposable isolated database, with
   before/after table and schema fingerprints, to verify request cancellation, supplier updates,
   duplicate intake, and order/receipt status transitions.
2. Add a non-mutating transaction-contract audit for multi-write business methods, then authorize
   only app-owned transaction corrections where an isolated failure proves partial writes.
3. Pressure-test shared Parties identity adoption and Manufacturing→Procurement demand references;
   define ownership contracts before adding cross-domain writes or foreign keys.
