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
