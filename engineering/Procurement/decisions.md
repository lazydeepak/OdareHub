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
