# Procurement — Decisions

### 2026-10-24 — OH-AUTO3-PROC-001 queue filter contract

**Status:** Implemented and verified under bounded read-only authorization

**Decision**

The existing creator-owned operator request projection may accept bounded
read-only filters for request reference, the established request status
vocabulary (`draft`, `approved`, `closed`, `cancelled`), exact `needed_date`,
and inclusive needed-date ranges. No overdue interpretation is introduced.

The query order is fixed: exact authenticated `created_by` ownership, supplied
filters, deterministic `created_at DESC, id DESC` ordering, then the existing
limit. Invalid input fails closed with an empty result and never falls back to
the unrestricted queue. PO linkage and schema-readiness behavior remain
unchanged.

**Evidence**

The database-free filter probe passes **31/31**. Existing queue ownership,
authorization, read-safety, PO-linkage, and mutation boundaries were preserved.
No database, SQL execution, browser, HTTP, activation, deployment, or live
Procurement probe was performed.

**Boundaries**

No approval, cancellation, purchase-order, supplier, receiving, route,
permission, schema, migration, Core, or Auto 1/2 assignment change was made.

## Decision Log

### 2026-10-21 — OH-AUTO2-PROC-004 runtime acceptance

**Status:** Accepted for the bounded read-only queue, qualified by disposable
runtime evidence.

The Procurement operator request queue is accepted after authenticated
loopback HTTP verification against a runner-owned staged source root and
attested runner-owned MySQL database. Exact creator ownership, Procurement
assignment, and `procurement.view` behavior matched the offline contract:
Alice and Bob were confined to their own requests; manage-only and approve-only
identities did not receive the queue; unassigned, display, anonymous,
cross-handle, and blank/space/NULL ownership cases failed closed. Admin
Procurement compatibility remained intact and the queue stayed read-only.

The only accepted corrections are generic Shell integration fixes: the
contributed-focus registry import in `apps/Shell/routes.php` and forwarding of
resolved `permissions` through `OperatorLayerService`. No new permission,
schema, mutation, Core, or Procurement business rule was introduced.

Evidence: `docs/architecture/oh-auto2-proc-004-runtime-acceptance-2026-10-21.md`.
Approval, cancellation, PO creation, supplier, receiving, installed-database
rollback, and production deployment remain excluded.

### 2026-10-21 — OH-AUTO2-PROC-002 operator workflow discovery

**Status:** Accepted and implemented as `OH-AUTO2-PROC-003`.

The existing Procurement request → purchase-order → receipt chain is an
admin-only workflow under `/apps/procurement/*`. The highest-value unfinished
user-facing capability is a bounded operator request review/approval queue under
`/u/{username}` that reuses the existing request state and PO-linkage service
boundaries.

The accepted first stage is creator-owned, read-only request visibility. It
requires Procurement assignment and explicit `procurement.view`. The exact
creator email is passed from `Auth::user()` through both sanctioned creation
paths and is matched exactly by the read-only projection. `procurement.manage`
does not grant approval; `procurement.approve` is reserved for a separate
workflow. Null, blank, legacy, or ambiguous ownership fails closed.

No new ACL field, schema field, approval/cancellation/PO mutation, supplier
mutation, or receiving mutation was added.

Evidence: `docs/architecture/oh-auto2-proc-002-procurement-workflow-discovery-2026-10-21.md`;
focused offline queue probe 20/20; existing supplier, linkage, authorization,
status, transaction, and supplier-creation records remain accepted and were
not reopened.

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

### 2026-10-17 — OH-AUTO2-128 receipt-creation status integrity

**Status:** Accepted — creation uses the existing receipt status vocabulary

New receipt creation accepts only `received`, `posted`, or `cancelled`, using
the same canonical lowercased/trimmed values as `setReceiptStatus()`. Missing or
blank creation input remains `received`, preserving the form default. Valid
internal `posted` creation remains permitted; `cancelled` remains accepted under
the existing lifecycle contract. Unsupported values are rejected before audit
schema initialization, transaction opening, receipt insertion, audit, and PO
recomputation. No historical receipt, quantity rule, line optionality, route,
permission, Core, or transaction policy changed.

Evidence: isolated reflection/source-order proof, 32/32 assertions; no live
database execution is claimed.

### 2026-10-17 — OH-AUTO2-129 direct PO transition integrity

**Status:** Accepted — direct issue/cancel operations own the transaction

Controllers use `setOrderStatusDirect()` for direct issue/cancel requests. It
validates the target status, initializes audit storage before the transaction,
locks the PO first, performs the existing receipt/ordered-quantity checks,
updates the PO, records audit, and commits or rolls back once. Receipt-driven
recomputation continues using transaction-compatible `setOrderStatus()` and
does not open a nested transaction. No new workflow, permission, quantity,
receipt, supplier, linkage, route, Core, or schema policy was introduced.

Evidence: isolated source/instrumentation proof, 31/31 assertions; live MySQL
rollback and concurrency behavior remain unverified.

### 2026-10-17 — OH-AUTO2-130 direct request transition integrity

**Status:** Accepted — direct approval/cancellation operations own the transaction

Controllers use `setRequestStatusDirect()` for direct approval/cancellation
requests. It validates the target status, initializes audit storage before the
transaction, acquires the request row with `FOR UPDATE`, performs the existing
linked-PO cancellation check, updates the request, records audit, and commits
or rolls back once. PO creation retains transaction-compatible
`setRequestStatus()` and does not open a nested transaction or initialize audit
DDL. No lifecycle, permission, route, Core, schema, supplier, or receipt policy
changed.

Evidence: isolated source/instrumentation proof, 38/38 assertions; live MySQL
rollback and concurrency behavior remain unverified.

### 2026-10-17 — OH-AUTO2-131 manual PO creation status integrity

**Status:** Accepted — manual PO creation uses canonical existing statuses

Manual PO creation now defaults missing/blank status to `draft` and delegates
supported nonblank values to the established PO status normalizer:
`draft`, `approved`, `issued`, `partial`, `closed`, and `cancelled`. The
canonical value is used for both insertion and creation audit `new_state`.
Unsupported or malformed values are rejected before audit initialization,
transaction start, or business writes. Approved-request conversion remains
explicitly `draft`; no draft-only creation policy or lifecycle rule was added.

Evidence: isolated reflection/source-order proof, 36/36 assertions; no live
database execution is claimed.

### 2026-10-17 — OH-AUTO2-132 request creation status integrity

**Status:** Accepted — manual request creation uses canonical existing statuses

Manual request creation now defaults missing/blank status to `draft` and
delegates supported nonblank values to the established request status
normalizer: `draft`, `approved`, `closed`, and `cancelled`. The canonical value
is used for both request insertion and creation audit `new_state`. Unsupported
or malformed values are rejected before request-reference generation, audit
access, or business writes. Manufacturing-demand intake remains explicitly
`draft`; no draft-only creation policy or lifecycle rule was added.

Evidence: isolated reflection/source-order proof, 33/33 assertions; no live
database execution is claimed.

### 2026-10-17 — OH-AUTO2-133 request creation transaction integrity

**Status:** Accepted — manual request insertion and creation audit are atomic

Manual request creation initializes the DDL-capable audit storage before
opening its business transaction. Request insertion, inserted-ID retrieval,
creation audit, and the explicit non-OK audit-result check occur inside one
transaction; success commits once and exceptions or audit failure roll back.
The existing request status, quantity, reference, provenance, optional-field,
authorization, CSRF, linkage, and Manufacturing-demand contracts remain
unchanged. Manufacturing-demand intake was not modified.

Evidence: isolated transaction/source proof, 35/35 assertions; simulated
rollback is not live MySQL rollback or concurrency evidence.

### 2026-10-17 — OH-AUTO2-134 supplier eligibility normalization alignment

**Status:** Accepted — selection trims ordinary spaces under the existing rule

The order-entry supplier selector now applies
`LOWER(TRIM(COALESCE(supplier_status, 'active')))` before excluding only
`inactive`, `disabled`, and `blocked`. This aligns ordinary-space handling with
the established PHP server-side validator without introducing an active-only
whitelist. Null status remains eligible through the active fallback; legacy
statuses such as `review` remain eligible; administration retains its
unrestricted historical supplier source. MySQL `TRIM()` behavior for tabs,
newlines, and unusual whitespace remains unclaimed and is documented as a
residual limitation.

Evidence: isolated selection/source proof, 18/18 assertions; server-side
eligibility proof, 22/22 assertions; no live database execution is claimed.

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

### 2026-10-17 — OH-AUTO2-135 supplier-creation transaction integrity correction

**Status:** Accepted under `OH-AUTO2-PROC-001`; live database rollback and
concurrency behavior remain unverified and are not claimed.

The supplier-create path initializes audit storage before opening its business
transaction, inserts the supplier, validates the generated supplier identity,
requires a successful creation audit, commits only after those checks, and
rolls back on failure. The unauthorized `procurement_suppliers_audit`
GET-readiness requirement was removed in favor of the established
`audit_activity_log` schema. Existing controller app authorization, CSRF, route,
and service ownership remain unchanged.

Evidence: restored historical records, source-order review, and repaired
isolated transaction probe **29/29**. The committed predecessor result was
26/28 because its invalid-identity simulation was incomplete. No live database
or concurrency claim is made.
### 2026-10-25 — OH-AUTO3-PROC-PRODUCT-001 request outcome clarity

**Status:** Implemented and verified as a bounded read-only presentation change

The existing creator-owned operator request queue may render the established
request status through localized labels and may show the already-projected
latest purchase-order status when a purchase-order reference exists. Requests
without a linked purchase order remain explicitly labeled as unlinked. Unknown
status values retain an escaped fallback label rather than being treated as a
new workflow state.

This is presentation-only: exact creator ownership, Procurement assignment,
`procurement.view`, read readiness, filters, ordering, limit, PO linkage, and
the absence of operator mutation controls remain unchanged. No approval,
cancellation, purchase-order, supplier, receipt, route, permission, schema,
cross-domain intake, Core, or database behavior changed.

**Evidence:** `probe_operator_request_queue.php` **20/20**;
`probe_operator_request_queue_filters.php` **35/35**; PHP lint and diff
hygiene pass.
