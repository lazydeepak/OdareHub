# Procurement — Decisions

## Decision Log

### 2026-10-16 — OH-AUTO2-119 common supplier eligibility rule

**Status:** Accepted — apply the existing lifecycle rule consistently

`createOrderFromApprovedRequest()` already established that a selected supplier
must exist and must not have `supplier_status` of `inactive`, `disabled`, or
`blocked`. The manual purchase-order form requires a supplier, while the
service/database contract intentionally permits a null supplier for the
existing automatic/omitted path. The same validation is therefore applied to
manual creation without rejecting null. Invalid suppliers are rejected before
schema initialization, transaction opening, purchase-order insertion,
linked-request closure, or audit creation. No historical order is revalidated,
and no new supplier lifecycle status or authorization model is introduced.

The read-before-write supplier check retains the existing conversion behavior;
concurrent supplier-status changes between validation and insertion remain a
future database-backed policy/transaction concern and were not redesigned here.

Evidence: isolated supplier policy and source-order probe, 22/22 assertions;
existing route and transaction contracts remain unchanged.

### 2026-10-16 — OH-AUTO2-120 selection-source separation

**Status:** Accepted — eligible source for order entry, unrestricted source for administration

Supplier selection for manual purchase orders and approved-request conversion
must use a query-level eligible source filtering the existing ineligible
statuses before ordering and limiting. Supplier administration must continue to
use the unrestricted source so inactive suppliers can be reviewed or
reactivated. This is a selection usability boundary only; submission-time
`assertSupplierEligible()` remains authoritative for status changes after page
rendering. Historical order queries and supplier lifecycle data are unchanged.

Evidence: isolated selection/source/rendering probe, 16/16 assertions.

### 2026-10-16 — OH-AUTO2-121 manual request-link integrity

**Status:** Accepted — reuse approved-request conversion boundary

Manual purchase orders may remain standalone when `request_id` is omitted. When
linked to a request, the existing conversion contract governs: the request must
exist, be `approved`, and have no existing purchase order. Manual creation now
locks and validates that request inside its existing transaction before PO,
line, request-status, or audit mutation. The manual selector uses a separate
approved-and-unlinked source; historical request listings remain unchanged.
`setRequestStatus()` was not changed globally, and no schema constraint or new
status was introduced.

Evidence: isolated linkage/transaction/source probe, 26/26 assertions. Database
concurrency execution remains outside this assignment.

### 2026-10-16 — OH-AUTO2-122 controller app-access defense in depth

**Status:** Accepted — require existing Procurement app access at every write controller

All Procurement POST routes already require `procurement` app access, but nine
controller methods did not repeat that authority when invoked directly. The
same existing `Auth::requireAppAccess('procurement')` guard used by the three
OH-AUTO1-051 methods is now present before CSRF, input processing, and service
dispatch in all 12 write controllers. This adds no permission semantics,
service-level ACL, route, or workflow change.

Evidence: expanded static route/controller probe, 112/112 assertions.

### 2026-10-16 — OH-AUTO2-123 Procurement GET read boundary

**Status:** Accepted — fail closed without schema initialization

Procurement GET routes must not call `ProcurementOverviewService::ensureSchema()`
or transitively initialize audit storage. The app-owned read path now checks
required Procurement table/column metadata using read-only information-schema
queries before composing page data. Missing or incompatible essential storage
renders an explicit unavailable state rather than verified-zero business data.
Audit storage is optional: when unavailable, activity history is shown as
unavailable and no audit query or schema creation occurs. Existing
`ensureSchema()` calls remain in POST/mutation paths, and Core's
`AuditLogService` is unchanged.

Evidence: isolated read-safety probe and focused Procurement regressions; no
database-backed execution is claimed.

### 2026-10-16 — OH-AUTO2-124 GET dependency completeness

**Status:** Accepted — readiness mirrors dependent query contracts

Procurement readiness now covers all columns used by essential GET SELECTs,
including selected fields, status filters, ordering, and supplier/request/PO
joins. Audit readiness covers every field used by `recentTransitions()` and
`latestTransitionMap()`, including `app_key`, state/note fields, and actor
fields. Optional Manufacturing-demand readiness covers both demand columns and
the request-link join columns; when unavailable, only that intake section is
shown unavailable and no dependent SELECT runs. No schema definition,
provisioning path, Core code, or database behavior was changed.

Evidence: injected metadata-catalog probe, 74/74 assertions; live database
compatibility remains unverified.

### 2026-10-16 — OH-AUTO2-125 CSRF-before-provisioning boundary

**Status:** Accepted — reject CSRF before route schema provisioning

Every Procurement POST route now preserves app access and session boot, then
calls the existing Core `Auth::requireCsrf()` before
`ProcurementOverviewService::ensureSchema()`. Controller-level CSRF checks,
controller dispatch, route registration, and authorized provisioning remain
unchanged. No token mechanism, permission model, or business service changed.

Evidence: route/controller structural proof, 160/160 assertions. No live
database or application execution is claimed.

### 2026-10-16 — OH-AUTO2-126 receipt PO identity

**Status:** Accepted — a new receipt requires an existing purchase order

`createReceipt()` must reject absent, non-positive, malformed, or nonexistent
purchase-order identities before receipt insertion. Valid identities are
locked with the existing `FOR UPDATE` query inside the transaction and the
same validated ID is used for insertion and posted-status recomputation.
`po_line_id` remains optional; when supplied, its existing existence and
cross-order checks remain authoritative. Quantity, receipt status, audit, and
rollback policies are unchanged. No historical receipt is changed and no
receipt over-quantity policy is introduced.

Evidence: injected-reader/source-order proof, 18/18 assertions; live database
and database-level concurrency behavior remain unverified.

### 2026-10-16 — OH-AUTO2-127 receipt posting transaction integrity

**Status:** Accepted — receipt posting is one PO-serialized transaction

Receipt posting now begins one Procurement transaction, establishes the linked
PO, locks the PO before the receipt row, revalidates receipt identity and PO
ownership under lock, updates the receipt, records its audit, recomputes linked
PO status, and commits once. Any downstream failure rolls back the operation.
Existing status vocabulary, no-change behavior, receipt creation, quantity
policy, line optionality, and authorization remain unchanged. No nested
transaction mechanism or Core change was introduced.

Evidence: isolated source/instrumentation proof, 24/24 assertions; live
database failure and concurrency behavior remain unverified.

### 2026-10-17 — OH-AUTO2-127R receipt posting transaction reconciliation

**Status:** Accepted for bounded correction — DDL and snapshot hazards removed

Because `AuditLogService::logEvent()` re-enters DDL-capable
`ensureSchema()`, `setReceiptStatus()` now explicitly initializes audit
storage before beginning its business transaction. The preliminary receipt
identity read also occurs before transaction start. After begin, the first
database operation is the PO locking read; receipt identity and ownership are
then revalidated with a receipt-row lock before any mutation. No Core change,
global isolation change, nested transaction, or lifecycle-policy change was
introduced.

Evidence: source/call-graph and isolated instrumentation proof, 29/29
assertions. Real database rollback and concurrency behavior remain unverified.

### YYYY-MM-DD — Decision title
**Status:** Proposed / Accepted / Superseded

**Context**
Why the decision was needed.

**Decision**
What was decided.

**Consequences**
What this enables, constrains, or requires next.

**Evidence**
Relevant validation, source references, or implementation links.
