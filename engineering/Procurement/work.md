# Procurement — Work

## 2026-10-24 — OH-AUTO3-PROC-001 own-request queue filters

- [x] Added bounded read-only filters for request reference, established request
  status, exact `needed_date`, and inclusive `needed_from`/`needed_to` ranges.
- [x] Preserved exact authenticated `created_by` ownership as the first query
  predicate, followed by filters, deterministic ordering, and the existing
  bounded result limit. Latest linked PO projection remains unchanged.
- [x] Invalid, array-shaped, unsupported, control-character, oversized,
  impossible-date, mixed exact/range, and reversed-range inputs fail closed
  without returning the full queue. No overdue rule was invented.
- [x] Added localized EN/JA/NE GET controls and same-user reset navigation;
  the operator surface remains read-only with no mutation controls.
- [x] Added `apps/Procurement/Tests/probe_operator_request_queue_filters.php`;
  database-free coverage passes **31/31**. No database-backed Procurement
  probes were run.

## 2026-10-21 — OH-AUTO2-PROC-004 authenticated runtime acceptance

**Status: ACCEPTED — bounded disposable-runtime acceptance complete.**

The creator-owned read-only operator request queue was exercised through the
real authenticated loopback HTTP path from a runner-owned staged source root
and runner-owned MySQL database. Alice and Bob each saw only their exact
creator-owned request; Alice saw the approved status, quantity, needed date,
and linked purchase order. Cross-handle access, manage-only, approve-only,
unassigned, display, anonymous, and blank/space/NULL ownership cases all
failed closed as required. Existing admin Procurement pages remained HTTP 200,
and the operator queue rendered no mutation controls.

The first runtime pass exposed two generic Shell integration defects: a
missing `OperatorSurfaceContributionRegistry` import in `apps/Shell/routes.php`
and missing `permissions` forwarding in
`apps/Shell/Services/OperatorLayerService.php`. Both were corrected minimally
and the final staged matrix passed. No Procurement business logic, permission,
schema, Core, or mutation behavior changed.

Evidence: `docs/architecture/oh-auto2-proc-004-runtime-acceptance-2026-10-21.md`.
Offline queue probe remains **20/20**. Final disposable dump SHA-256 is
`9198c0494003cf11622b355e3decc6025ac1a37aef11cecb3cf4f423b1ab36d6`; the
attestation JSON SHA-256 is
`6e0e8b437daed196fd48fc7df8bf4c65b6047d02869df5f2a1f83e714203e678`.
Production access was not attempted; approval, cancellation, PO creation,
supplier, receiving, and installed-database rollback remain deferred.

## 2026-10-21 — OH-AUTO2-PROC-002 Procurement Workflow Discovery

**Status: IMPLEMENTED — bounded read-only first stage.**

The accepted product decision selected one concrete unfinished capability: a
Procurement operator request review/approval queue under `/u/{username}`. The
existing request → purchase-order → receipt workflow is implemented through
admin-only `/apps/procurement/*` routes and the existing service boundary, but
Procurement has no operator capability declaration, operator route/view,
record-scope contract, or defined `procurement.manage`/`procurement.approve`
operation mapping.

The accepted first stage is own requests only, requiring Procurement
assignment plus explicit `procurement.view`. Exact creator ownership is
verified from both sanctioned creation paths: both pass the canonical
authenticated email and persist it to `created_by`. Null/blank/ambiguous rows
fail closed. Approval, cancellation, PO creation, supplier mutation, and
receiving remain deferred.

Implementation files: `apps/Procurement/Services/OperatorSurfaceContributionService.php`,
`apps/Procurement/Services/ProcurementOverviewService.php`,
`apps/Procurement/Views/operator/requests.php`, Procurement manifest/locales,
and the generic contributed-focus bridge in Shell. Focused offline probe:
20/20; PHP lint and diff hygiene pass. No database mutation or production
operation occurred.

Evidence: `docs/architecture/oh-auto2-proc-002-procurement-workflow-discovery-2026-10-21.md`.
The completed OH-AUTO2-PROC-001 supplier acceptance and OH-AUTO2-119–135
integrity records were reused; no completed audit was repeated.

## 2026-10-20 — Preserved OH-AUTO1-034 baseline reconciliation

Status: NO CHANGE REQUIRED — NO NEW CONFIRMED DEFECT

Evidence reviewed:
- apps/Shell/Composers/OperatorWorkerWorkComposer.php (resolveDestination, bindTaskSelector, isSafeOperatorUrl)
- apps/Shell/Tests/probe_operator_worker_dashboard.php (26 assertions)
- apps/Shell/Composers/OperatorNavigationComposer.php (navigation links)

Findings:
- Navigation authorization controls work correctly (intentional design, not defects)
- resolveDestination() produces safe same-user relative operator routes and suppresses unauthorized destinations
- bindTaskSelector() canonicalizes task selectors to the assigned task identity
- All 26 probe assertions pass: current/upcoming/attention lanes, identity binding, authorization, route access, read-only suppression
- No reproducible usability defect demonstrated by source evidence

Disposition:
No change to navigation safeguards is authorized without demonstrated failure evidence.
Status: OH-AUTO1-034 remains BASELINE RECONCILED — NO NEW CONFIRMED DEFECT.
Recommendation: Select next concrete backlog item (e.g., Manufacturing Processing workspace or verified feature gap).
Preserved: OH-AUTO2-135 IMPLEMENTED — ACCEPTANCE PENDING; OH-AUTO1-034 BASELINE RECONCILED.
No Core changes, no SQL, no deployment, no destructive Git operations performed.

## Restored historical Procurement work record through 2026-10-17

### Current Focus

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

## 2026-10-17 — OH-AUTO2-134 supplier eligibility normalization alignment

- [x] Confirmed the selector used `LOWER(COALESCE(...))` while
  `assertSupplierEligible()` uses PHP `strtolower(trim(...))`; ordinary-space
  padded excluded statuses could therefore appear in selection and fail on
  submission.
- [x] Applied the smallest SQL correction:
  `LOWER(TRIM(COALESCE(...)))`, preserving null→active fallback, the
  exclusion-based policy, ordering before limit, and unrestricted supplier
  administration.
- [x] Preserved legacy statuses such as `review` and `pending`, server-side
  eligibility authority, supplier creation/update behavior, request/PO
  linkage, and authorization/CSRF boundaries. MySQL `TRIM()` equivalence for
  tabs, newlines, or unusual whitespace is not claimed and remains an explicit
  residual limitation.
- [x] Focused selection proof passes **18/18**; supplier eligibility remains
  **22/22**. No database, SQL, browser, activation, or deployment operation
  was used.

## 2026-10-17 — OH-AUTO2-133 request creation transaction integrity

- [x] Confirmed the prior sequence inserted the request and then called the
  audit helper without a shared explicit transaction. `logEvent()` can invoke
  DDL-capable audit initialization and can return a non-OK result as well as
  throwing on database failure.
- [x] Added audit initialization before `begin_transaction()`, then one
  request insert, inserted-ID retrieval, required creation audit, explicit
  non-OK audit-result rejection, one commit, and rollback on every failure.
- [x] Preserved OH-AUTO2-132 status normalization, canonical insert/audit
  state, quantity/reference/provenance/optional fields, controller authority,
  direct transitions, linkage, and unchanged Manufacturing-demand intake.
- [x] Isolated atomicity proof passes **35/35**; no database, SQL, browser,
  activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-132 request creation status integrity

- [x] Confirmed the form submits `draft`, missing/blank service input defaults
  to `draft`, and Manufacturing-demand intake explicitly creates `draft`
  requests. The prior manual path inserted a separately cast raw status and
  audited the independent raw input without vocabulary validation.
- [x] Reused the established request normalizer through a creation-specific
  default wrapper. The canonical status now feeds both request insertion and
  creation audit `new_state`; invalid values are rejected before request
  reference generation, audit access, or business writes.
- [x] Preserved positive quantity validation, optional fields, provenance,
  direct request transitions, internal request closure, approved-request PO
  conversion, request linkage, authorization, CSRF, and Manufacturing intake.
- [x] Isolated creation-status proof passes **33/33**; no database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-131 manual PO creation status integrity

- [x] Confirmed the form submits `draft`, missing/blank service input defaults
  to `draft`, and approved-request conversion explicitly creates `draft` POs.
  The prior manual path inserted a trimmed raw value and audited a separate raw
  value without vocabulary validation.
- [x] Reused the established PO normalizer through a creation-specific default
  wrapper. The canonical status now feeds both PO insertion and audit
  `new_state`; invalid values are rejected before audit setup or transaction.
- [x] Preserved direct PO transitions, internal receipt recomputation, request
  linkage, supplier eligibility, authorization, CSRF, and transaction behavior.
- [x] Isolated creation-status proof passes **36/36**; no database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-130 direct request transition integrity

- [x] Confirmed the caller graph: approval/cancellation controllers use the
  direct transition entry point; manual and approved-request PO creation retain
  internal `setRequestStatus()` closure inside their existing transactions.
- [x] Added the direct boundary: status validation and audit initialization
  before transaction start, request-row `FOR UPDATE`, existing cancellation
  linkage check, request update, audit, and one commit/rollback. The internal
  update opens no transaction and performs no audit DDL setup.
- [x] Preserved request statuses, approval/cancellation behavior, same-state
  audit handling, request linkage, supplier eligibility, authorization, CSRF,
  receipt, and GET readiness behavior.
- [x] Isolated direct-request proof passes **38/38**; no database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-129 direct PO transition integrity

- [x] Confirmed the caller graph: issue/cancel controllers use the direct
  transition entry point; receipt creation and posting retain internal
  `setOrderStatus()` recomputation inside their existing transactions.
- [x] Added the narrowly scoped direct boundary: audit initialization before
  transaction start, PO-first `FOR UPDATE`, status-dependent checks, PO update,
  audit, and one commit/rollback. The internal update opens no transaction and
  performs no audit DDL setup.
- [x] Preserved existing issue quantity, posted-receipt cancellation, status,
  same-state audit, authorization, CSRF, receipt, supplier, linkage, and GET
  readiness behavior.
- [x] Isolated direct-transition proof passes **31/31**; no database, SQL,
  browser, activation, or deployment operation was used.

## 2026-10-17 — OH-AUTO2-128 receipt-creation status integrity

- [x] Confirmed the existing receipt status vocabulary is `received`, `posted`,
  and `cancelled`; the form defaults to `received`, while internal creation
  paths already use `posted`.
- [x] Added one shared normalizer used by creation and status transitions.
  Creation defaults missing/blank input to `received`; supported values are
  lowercased and trimmed; unsupported values are rejected before audit schema
  initialization, transaction start, or insertion.
- [x] The canonical status now feeds receipt insertion, audit `new_state`, and
  posted-status recomputation. Optional receipt lines, PO identity, supplier,
  linkage, authorization, CSRF, and transaction boundaries remain unchanged.
- [x] Isolated status/source proof passes **32/32**; no database, SQL, browser,
  activation, or deployment operation was used.

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

## OH-AUTO2-135 — Supplier-creation transaction integrity correction

Status: ACCEPTED under `OH-AUTO2-PROC-001`; live database rollback and
concurrency behavior remain unverified and are not claimed.

- Removed the unauthorized `procurement_suppliers_audit` GET-readiness
  requirement; the established audit schema is `audit_activity_log`.
- Added inserted-supplier identity validation before audit recording and commit.
- Preserved the single transaction boundary, required audit result, rollback,
  existing controller authorization, CSRF, route, and service contract.
- The repaired working-tree focused probe passes **29/29** in an isolated,
  non-database run, including invalid inserted-ID rollback behavior. The
  committed predecessor recorded 26/28 because its invalid-identity fixture
  did not model inserted-ID extraction; the production implementation was not
  changed in this review.

Acceptance record: historical work and decision entries removed by `1107ca0`
were restored from `1107ca0^`; no unrelated Procurement working-tree changes,
logs, backup, or PHPUnit cache were overwritten.
## 2026-10-25 — OH-AUTO3-PROC-PRODUCT-001 request outcome clarity

**Status: COMPLETE — bounded read-only operator presentation slice.**

- [x] Selected the existing creator-owned operator request queue because its
  request status and latest linked purchase-order status are already returned
  by the authorized read projection.
- [x] Render those authoritative outcomes with localized labels and explicit
  linked/unlinked purchase-order state; no mutation, permission, route, schema,
  or cross-domain intake behavior is in scope.
- [x] Add focused positive/negative presentation regression coverage and run
  Procurement plus repository validation before integration.
