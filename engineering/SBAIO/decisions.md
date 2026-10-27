# SBAIO — Decisions
### 2026-10-27 — OH-AUTO3-SBAIO-CSV-DELIVERY-INTEGRITY-001 CSV delivery integrity

**Decision: Record CSV export success only after the complete output stream has
been written and closed successfully.**

The previous SBAIO CSV path recorded `completed` immediately after opening
`php://output`, before the BOM, headers, rows, and stream close. The path now
checks BOM, header-row, data-row, and close results; output failures record a
failed export and return `false`. Success history is recorded only after close
success. `ExportHistoryService` and its historical record contract remain
unchanged.

This corrects audit chronology and delivery failure handling only. CSV formula
safety, projections, admission, personnel containment, authorization, schema,
Core/Auth/ACL, and database behavior remain unchanged.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-FORMULA-SAFETY-001 CSV formula safety

**Decision: Neutralize spreadsheet formula-like text at the centralized
authorized CSV serialization boundary.**

`ModuleReportRegistryService::streamCsvForReport()` now preserves numeric
scalars and numeric strings exactly, retains existing boolean/null/structured
serialization, and prefixes nonnumeric text beginning with `=`, `+`, `-`, or
`@` (including leading-whitespace variants) with a literal apostrophe before
`fputcsv()`. Report projections, admission, personnel containment, and export
authorization remain unchanged.

This is output encoding safety, not authorization expansion or personnel
visibility restoration. No database, schema, Core/Auth/ACL, or business
workflow behavior changed.

### 2026-10-27 — OH-AUTO3-SBAIO-PAYROLL-PARITY-CONTAINMENT-001 Payroll parity containment

**Decision: Fail closed for Payroll parity diagnostics until an owner-approved personnel/payroll record-level visibility contract exists.**

The existing `/apps/sbaio/payroll/parity` route previously invoked
`PayrollParityValidationService::report(80)` after only SBAIO app access. The
service reads salary-monthly rates, payroll records/runs, staff rows, fixed
payroll profiles, and emits employee and financial comparison fields.
`isPersonnelParityAvailable()` now rejects the route after existing active
report admission and before session/controller/service/SQL execution.

No permission, identity binding, organization scope, supervisor scope, schema,
payroll business logic, or historical record behavior was added or changed.
The remaining owner decisions are personnel identity binding, organization and
supervisor scope, and record-level visibility for reads, exports, and mutations.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-MUTATION-CONTAINMENT-001 mutation containment

**Decision: Fail closed for all nine personnel/payroll POST mutation routes until an owner-approved record-level mutation contract exists.**

The existing SBAIO app access and active-module admission remain the first
checks. `isPersonnelMutationAvailable()` rejects Staff, Payroll, Leave,
Attendance, Timecards, and Schedules mutation routes before session/controller
setup, CSRF dispatch, service calls, SQL, or transactions. No new permission,
identity binding, organization scope, supervisor scope, schema rule, or
business-logic redesign was introduced.

Remaining reachable read entry points are documented separately: Payroll
`/parity` remains a GET parity-validation surface, and the six `/export`
landing pages remain metadata-only pages whose CSV actions are already blocked
by the prior export containment. These were not conflated with mutation
containment.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-INDEX-CONTAINMENT-001 module index containment

**Decision: Fail closed for six personnel-related module index GET surfaces until an owner-approved record-level visibility contract exists.**

The existing SBAIO app access and active-module admission remain the first
checks. `isPersonnelIndexAvailable()` then rejects the Attendance, Leave,
Payroll, Schedules, Staff, and Timecards index descriptors before their
controllers or services can query personnel/payroll data. This is containment,
not a new permission, identity binding, organization scope, supervisor scope,
schema rule, or workflow redesign.

The corresponding POST workflows remain separately unresolved. Their routes
retain existing app-access and CSRF checks, but no established contract proves
authenticated-user-to-Staff binding, organization/supervisor scope, or
record-level mutation authority for the affected personnel records. No POST
behavior was changed in this slice.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-HTML-REPORT-CONTAINMENT-001 HTML report containment

**Decision: Fail closed for the six personnel/payroll HTML report descriptors until an owner-approved record-level visibility contract exists.**

The existing SBAIO app access and active-report admission are retained as the
first checks, but `findActiveHtmlReport()` returns no descriptor for
Attendance, Leave, Payroll, Schedules, Staff, or Timecards. Their `/report`
routes therefore stop before rendering, and their report-view fallback also
fails closed before report SQL. This adds no permission, identity binding,
organization scope, supervisor scope, schema, or record policy.

The corresponding module index/dashboard surfaces remain separately exposed
under existing SBAIO app access and are not silently treated as resolved by
this report-only containment. The audit remains an owner decision for those
surfaces and for personnel/financial record visibility generally.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-EXPORT-CONTAINMENT-001 personnel/payroll export containment

**Decision: Fail closed for personnel/payroll CSV reports until an owner-approved record-level export contract exists.**

The existing `app.sbaio.access` plus active-module admission does not establish
Staff identity binding, organization scope, supervisor scope, or record-level
personnel/financial export visibility. The following report keys are therefore
blocked centrally before source lookup/query: `sbaio.attendance.overview`,
`sbaio.leave.overview`, `sbaio.payroll.overview`,
`sbaio.schedules.overview`, `sbaio.staff.overview`, and
`sbaio.timecards.overview`.

This is a fail-closed export containment decision, not a new permission or
record-level policy. Task, Notice, Customers, Expenses, and Sales retain their
existing authorized CSV behavior and explicit projections. No stored records,
schema, lifecycle, identity, organization, Core/Auth/ACL, or route behavior
was changed.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-ADMISSION-001 unresolved personnel/financial visibility

**Status: Unresolved — no new policy inferred.**

Staff, Payroll, Leave, Attendance, and Timecards exports contain personnel or
financial fields, but the repository proves only the existing SBAIO app/report
admission (`app.sbaio.access` plus active module lifecycle). It does not prove
authenticated-user-to-Staff binding, organization scope, supervisor scope, or
record-level export visibility for those fields. The CSV change therefore
preserves the established export field contract while making it explicit; it
does not claim that the fields are newly authorized or add a filtering policy.
An owner-approved personnel/financial export visibility decision remains
required before narrowing, expanding, or introducing record-level filtering.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-ADMISSION-001 CSV admission and projection safety

**Decision: Centralize existing active-report admission in the CSV stream boundary and require explicit projections for every SBAIO CSV source.**

All eleven module reports already declare the existing `app.sbaio.access`
permission and `active_only` lifecycle. Their routes retain their existing
authorization gates, including the established administrator checks for
Customers and Sales. `streamCsvForReport()` now rejects inactive/unadmitted
reports before source lookup or streaming, without adding permissions or
record-level policy.

Every CSV source now declares schema-aligned columns from its install/update
contract. The generic unrestricted projection and schema-discovery fallback
were removed. Existing Task and Notice exclusions of `related_staff_id`, and
the established Notice exclusion of `body`, remain unchanged. No schema,
lifecycle, organization, Staff identity, Core/Auth/ACL, database, deployment,
or activation behavior changed.

### 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-EXPORT-PRIVACY-001 Notice export privacy

**Decision: Do not export Notice `body` from the existing CSV route without an established record-level visibility contract.**

The Notice exact-read and report contracts already exclude `body`, while the
CSV route is gated only by existing SBAIO app access. No independent
authorization evidence establishes body export. The descriptor therefore
retains the existing Notice metadata fields and omits `body` and
`related_staff_id`. No export permission, route, schema, lifecycle, or
authorization policy was added; Notice creation and stored historical data are
unchanged.

### 2026-10-26 — OH-AUTO3-SBAIO-STAFF-READ-PROJECTION-001 read projection containment

**Decision: Remove Staff linkage from Task/Notice read and CSV projections where no established authorized consumer contract exists.**

Task search and exact retrieval selected `related_staff_id`, but the existing
controller/view/report consumers did not read it and no record-level Staff
authorization contract exists. Notice list, exact, and report projections were
already Staff-free. Task search/exact SQL now uses explicit Staff-free columns.
The generic CSV engine retains its `SELECT *` fallback for other reports, while
Task and Notice sources explicitly preserve every existing non-Staff column
(including Notice `body`) and omit only `related_staff_id`. Historical schema,
stored associations, INSERT behavior, routes, permissions, and authorization
boundaries are unchanged.

### 2026-10-26 — OH-AUTO3-SBAIO-STAFF-WRITE-001 Staff-association write containment

**Decision: Reject positive Staff associations at Task and Notice creation until an owner-approved record-level Staff authorization contract exists.**

Selector containment alone did not close the write boundary: a caller could
still submit a numeric `related_staff_id` directly. Both service validators now
accept only unassigned input (absent, `null`, empty, or zero) for this surface;
positive normalized IDs fail with `related_staff_id_unavailable` before any DB
connection or INSERT. Malformed values retain `related_staff_id_invalid`.
The nullable field, INSERT shape, historical rows, and ordinary unassigned
creation remain unchanged. This does not infer or create Staff authority.


### 2026-10-26 — OH-AUTO3-SBAIO-STAFF-SELECTOR-001 containment

**Decision: Suppress unproven Staff names/codes from the Task and Notice administrator creation surfaces until a record-level Staff authorization contract exists.**

The existing `activeStaff()` queries selected Staff IDs, names, and employee
codes after only the SBAIO app-access gate. Because the repository has no
authoritative authenticated-user-to-Staff binding or organization/supervisor
scope, that selector data flow is classified as potential personnel-data
exposure. The controllers no longer call `activeStaff()` and the views no
longer render selectors; a localized unavailable state preserves an
unassigned-only creation path. Existing positive numeric `related_staff_id`
validation and persistence remain unchanged for separately authorized callers.
No authorization policy or identity mapping is inferred.

### 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-REPORT-001 report ownership

**Decision: Assign existing Notice report data preparation to `NoticesService` while retaining route authorization and registry admission in the route.**

`NoticesService::reportData()` owns the unchanged four-read SQL contract and
default payload. `NoticesController::report()` passes the admitted descriptor
and prepared data to the presentation view. The view performs no database or
registry operations and retains the existing report projection, which excludes
Notice body and Staff linkage. No generic reporting framework or new metric was
introduced.

### 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-CONTINUITY-001 exact-ID continuity

**Decision: Reuse the established Task transaction/identity pattern and carry the verified Notice ID through the existing administrator surface.**

Notice creation begins a same-connection transaction, verifies the positive
generated identity, commits only afterward, and rolls back all failure paths.
The existing create redirect carries `notice_id`; exact retrieval uses a
parameterized `WHERE id = ? LIMIT 1` query after existing app authorization.
The safe record projection intentionally excludes `body` and
`related_staff_id` because no established record-level visibility evidence
supports exposing those fields. No route, permission, lifecycle, or schema
behavior is added.

### 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-INTEGRITY-001 Notice input contract

**Decision: Validate Notice creation fields at the NoticesService boundary and preserve the existing route, schema, checkbox, and INSERT contracts.**

Title/body/type/period/level inputs must be supplied as strings and respect
the existing schema limits; title remains required and blank optional values
retain their current defaults. `related_staff_id` accepts only positive
signed-INT-compatible decimal IDs, with blank/zero sentinels remaining NULL.
`requires_ack` and `is_active` accept only exact scalar `0`/`1` values (or
absent checkbox fields), preventing truthiness-based flag coercion. No new
acknowledgment workflow, lifecycle, Staff policy, or authorization rule was
added.

### 2026-10-26 — OH-AUTO3-SBAIO-TASKS-DUE-PARITY-001 report/queue due parity

**Decision: Make the Task report's `due_7d` aggregate use the existing actionable queue's upcoming predicate, while retaining the report's separate snapshot ordering.**

The report now counts only non-null due dates from inclusive `CURDATE()`
through seven days and excludes the already-established `done`, `completed`,
and `closed` statuses, matching the queue without adding lifecycle semantics.
The report's `recent` list remains an ascending due-date snapshot with its
existing 25-row limit; it is not the actionable queue and therefore retains a
separate presentation order.

### 2026-10-26 — OH-AUTO3-SBAIO-TASKS-QUEUE-001 bounded discovery contract

**Decision: Extend the existing Task queue with a read-only exact-filter search and forward keyset pagination, without changing Task lifecycle or authorization semantics.**

The search supports `overdue`, `upcoming`, `historical`, and `all` modes,
exact status/priority/bucket filters, inclusive due-date bounds, a 50-row
cap, and opaque cursors bound to the normalized filter fingerprint. Results
order by `due_date DESC, id DESC`; overdue/upcoming use only the already
established `done`, `completed`, and `closed` terminal values. Existing SBAIO
app access remains the authorization boundary and no Staff field is used for
ownership inference.

### 2026-10-26 — OH-AUTO3-SBAIO-TASKS-CONTINUITY-001 transactional identity and exact retrieval

**Decision: Adopt the existing Sales/Customers/Expenses same-connection transaction and exact-ID continuity pattern for Tasks on the existing SBAIO route.**

Task creation returns only the positive generated ID after insert identity
verification and commit; insert, identity, commit, and rollback failures cannot
produce success. The existing Task page receives `task_id` through the create
redirect and reads only `WHERE id = ? LIMIT 1` after the unchanged SBAIO app
access gate. No latest-row, title, Staff identity, lifecycle, or permission
inference is permitted.

### 2026-10-26 — OH-AUTO3-SBAIO-TASKS-REPORT-001 report ownership

**Decision: Keep the existing Task report SQL and output contract in `TasksService::reportData()` and make the presentation view data-only.**

The route continues to require SBAIO app access and admit the active
`sbaio.tasks.overview` registry descriptor before controller rendering. The
service owns the same four reads, predicates, aggregation, ordering, limits,
and zero-value fallback payload; no metric, lifecycle, permission, route,
schema, Staff, or cross-domain policy was added.

### 2026-10-26 — OH-AUTO3-SBAIO-TASKS-INTEGRITY-001 Task input contract

**Decision: Validate and normalize all existing Task creation fields at the SBAIO TasksService boundary without adding Staff authorization, identity binding, lifecycle, route, or schema policy.**

Task title, owner, type, bucket, status, and priority are scalar strings with
existing schema limits; title remains required, blank optional fields retain
their defaults, and existing arbitrary status/priority values remain valid.
Related and due dates must be real `Y-m-d` calendar dates. `related_staff_id`
accepts only a positive signed-INT-compatible decimal ID; blank/zero sentinels
remain `NULL`, while malformed, negative, fractional, scientific, boolean,
array/object, and overflow values fail before SQL. No Staff relationship to
authenticated users is inferred.

### 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-RETRIEVAL-001 exact retrieval and bounded search

**Decision: Keep Expense retrieval inside the existing administrator Expense GET surface and use the established Sales keyset-search contract without adding routes, fuzzy matching, or identity inference.**

Exact retrieval accepts only a positive decimal database ID and uses a
parameterized `WHERE id = ? LIMIT 1` read. Historical search accepts exact
reference/status values and inclusive calendar-date bounds, orders by
`expense_date DESC, id DESC`, fetches one overflow row, caps pages at 50, and
binds opaque cursors to the normalized filters. Cursor continuation explicitly
handles NULL dates; malformed or filter-changed cursors fail closed before SQL.
Existing SBAIO app authorization remains the route boundary.

### 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-DURABILITY-001 transaction and identity

**Decision: Adopt the established Sales/Customers same-connection transaction boundary for Expense creation and return only the verified positive generated ID after commit.**

Expense creation begins a transaction on `DB::conn()`, performs the existing
parameterized INSERT through `DB::query()`, validates the connection's
`insert_id` as a positive integer, commits, and returns that ID. Insert,
identity, commit, or rollback failure cannot produce success; no latest-row,
reference-based, or second-connection identity lookup is used. The existing
route receives an ID-bearing localized confirmation and `expense_id` query
context without adding a route or detail workflow.

### 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-INPUT-002 complete input contract

**Decision: Extend the existing Expense service validation boundary to all create fields while preserving the existing insert shape, optional defaults, arbitrary status values, route, CSRF, and app-access behavior.**

`expense_ref`, `category_name`, `expense_status`, and `expense_date` now reject
unsupported supplied types before normalization. Reference/category/status
lengths match the existing schema (`80/120/40`), reference remains required,
blank category/status/date retain existing null/default behavior, and dates
must be real `Y-m-d` calendar dates. The already-established exact decimal
amount contract remains unchanged. No approval lifecycle, permission, route,
schema, Core/Auth/ACL, organization, Staff, Leave, or Timecard behavior changes.

### 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-INTEGRITY-001 monetary input contract

**Decision: Validate Expense amounts as decimal strings before persistence and preserve the existing Expense route, insert shape, status/date behavior, and authorization boundaries.**

The accepted monetary grammar is the existing Sales contract aligned to
`DECIMAL(12,2)`: up to 10 integral digits and 1–2 fractional digits, optional
sign, and leading-decimal notation such as `.5`; values are normalized to a
decimal string without binary floating-point conversion. Missing, malformed,
scientific, special, overflow, comma-formatted, and non-string values fail
closed before SQL. No approval lifecycle, permission, route, schema,
Core/Auth/ACL, organization, Staff, Leave, or Timecard behavior changes.

### 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-SEARCH-001 bounded historical discovery

**Decision: Extend `CustomersService` and the existing administrator Customers page with exact-filter forward keyset search; do not add a route, generic search framework, fuzzy matching, or unfiltered historical browse.**

Search accepts exact `customer_name`, `email`, `phone`, and `status` values,
plus inclusive `created_at` calendar-date bounds. At least one filter is
required. Each scalar/date/range and cursor is validated before database
access. The cursor carries version, the last `created_at` (including NULL),
positive ID, and a SHA-256 normalized-filter fingerprint. Results preserve
`created_at DESC, id DESC`, fetch one overflow row, and expose at most 50
records. Existing administrator authorization remains before search; no
permission, lifecycle, schema, mutation, cross-domain, Core/Auth/ACL,
organization, Staff, Leave, or Timecard behavior changes.

### 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-REPORT-001 report read ownership

**Decision: Keep Customers report data reads in `CustomersService`, with the existing route retaining administrator authorization and active report-registry admission before controller/view rendering.**

`CustomersService::reportData()` preserves the existing summary aggregate,
status grouping, and recent-customer queries exactly, including their
predicates, ordering, limit, and default-on-error payload. The controller
passes the admitted report descriptor and prepared payload to the view. The
view no longer imports or calls `DB` or `ModuleReportRegistryService`; no new
report capability, metric, route, permission, schema, or generic reporting
framework is introduced.

### 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-CONTINUITY-001 exact identity contract

**Decision: Extend the existing administrator Customers page with a read-only exact-ID view and make creation return the verified generated identity; do not add a route or infer records from customer fields/order.**

`CustomersService::create()` uses the existing `DB::conn()` transaction
boundary, performs the existing parameterized insert, validates a strictly
positive same-connection `insert_id`, and commits only after identity success.
Insert, identity, and commit failures roll back and cannot produce success.
`findById()` accepts only a positive integer ID and performs one parameterized
`WHERE id = ? LIMIT 1` read. The existing Customers administrator guard runs
before exact retrieval and creation. No lifecycle, permission, route, schema,
Core/Auth/ACL, or cross-domain behavior changes.

### 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-INPUT-001 strict input boundary

**Decision: Accept only string values for supplied Customer create fields before normalization; preserve missing/`null` defaults and reject all other supplied types.**

`CustomersService::validateCreateInput()` now checks the raw values for
`customer_name`, `contact_name`, `email`, `phone`, and `status` before calling
`trim()`. Missing or `null` optional values remain blank and status still
defaults to `active`; valid strings retain existing trimming, length, email,
and arbitrary-status behavior. Arrays, objects, booleans, integers, floats,
resources, and other unsupported values return `input_type_invalid` and cannot
reach the create INSERT boundary. Existing administrator access, CSRF, routes,
schema, and persistence shape are unchanged.

### 2026-10-26 — OH-AUTO3-SBAIO-SALES-CONTINUITY-001 exact-ID retrieval

**Decision: Extend the existing administrator Sales page with a read-only exact-ID view; do not add a new route or infer records from references/order.**

`SalesService::findById()` strictly accepts a positive integer ID and performs
one parameterized `WHERE id = ? LIMIT 1` read. The existing Sales administrator
guard runs before this read. The create redirect carries the verified generated
ID as `sale_id`, and the page emits a direct link to that same authoritative
surface. Duplicate references, pagination, missing records, malformed IDs, and
database-read failures cannot substitute another record or disclose data.

### 2026-10-26 — OH-AUTO3-SBAIO-SALES-INTEGRITY-001 bounded transaction contract

**Decision: Keep the Sales insert and same-connection generated-ID verification inside one existing `mysqli` transaction boundary.**

The service uses `DB::conn()->begin_transaction()`, performs the existing
parameterized insert, verifies the connection-local positive identity, and
calls `commit()` only after verification. Insert, identity, and commit failures
attempt `rollback()` and propagate failure; no success ID or redirect is
returned. The existing `sbaio_sales` schema is verified as InnoDB, so this
bounded service transaction has transactional storage support without a Core
or generic transaction-abstraction change.

### 2026-10-26 — OH-AUTO3-SBAIO-SALES-CREATE-001 exact identity contract

**Decision: Return the database-generated identity from the existing Sales create service and use it in the existing success redirect.**

After the parameterized insert succeeds, Sales reads `DB::conn()->insert_id`
from the same current connection. Only a strictly positive integer identity
(including a valid positive integer string representation) is accepted;
missing, malformed, zero, negative, or overflowing identity evidence throws and
cannot produce a success redirect. No latest-row query, sale-reference lookup,
uniqueness assumption, route, permission, schema, or lifecycle change is
introduced.


### 2026-10-26 — OH-AUTO3-SBAIO-SALES-PAGING-001 forward keyset contract

**Decision: Use a Sales-owned opaque forward keyset cursor; do not add a generic pagination framework or route.**

The cursor records version, the last displayed `sale_date` (including NULL),
the last displayed positive `id`, and a SHA-256 fingerprint of normalized
`sale_ref`, status, and inclusive date filters. It is decoded and validated
before database access. A changed filter set, malformed structure, invalid
calendar date, invalid ID, or invalid fingerprint fails closed without a read.

The next-page predicate preserves the existing order: non-NULL cursors use
`sale_date < cursor_date OR sale_date IS NULL OR (sale_date = cursor_date AND
id < cursor_id)`; NULL cursors use `sale_date IS NULL AND id < cursor_id`.
Values remain parameterized and each page fetches at most 51 rows to display
50. Existing authorization, filters, reports, exports, routes, schema,
mutations, and lifecycle semantics remain unchanged.


### 2026-10-26 — OH-AUTO3-SBAIO-SALES-SEARCH-001 bounded retrieval contract

**Decision: Extend the existing Sales-owned read service rather than introduce a generic search framework or new route.**

Search accepts exact `sale_ref`, one of the established `draft`, `open`,
`pending`, `won`, `completed`, or `closed` statuses, and inclusive `sale_from`
/ `sale_to` calendar dates. Each supplied value is validated before any DB read;
the service binds values as parameters and uses one overflow row (`LIMIT 51`)
to expose a bounded 50-row result plus `truncated` state. Ordering is fixed to
`sale_date DESC, id DESC`.

The existing Sales route continues to apply administrator authorization before
the controller invokes search. No permission, lifecycle, schema, mutation,
report, export, organization, or cross-domain authorization contract changes.


### 2026-10-26 — OH-AUTO3-SBAIO-SALES-REPORT-001 report read ownership

**Decision: Keep Sales report query ownership in `SalesService`, with the route/controller supplying a prepared payload to the view.**

The existing report route continues to enforce the administrator guard and
active `sbaio.sales.overview` registry admission before invoking the controller
render handoff. `SalesService::reportData()` retains the four existing reads:
the 90-day aggregate, 90-day status grouping, 90-day customer ranking limited
to 15 rows, and recent sales ordered by date/id limited to 25 rows. Its
default-on-error result matches the former view behavior.

The report view is presentation-only and no longer imports or calls DB or
report-registry services. This is a Sales-owned extraction, not a generic
reporting framework; no widget type, placement zone, interaction profile,
admin/operator/display contribution, or canonical route changed.


### 2026-10-16 — OH-AUTO2-105S DB-backed probe admission

**Status:** Accepted — reuse the existing disposable entry-point guard

Every SBAIO DB-backed behavioral probe must establish explicit disposable
repository, isolated source-root, configuration, and process/socket/datadir
attestation before loading the application autoloader or DB layer. Missing,
ambiguous, repository-root, installed-like, or otherwise unverified evidence
fails closed. The guard does not redirect a probe to another database.

The existing disposable orchestrator owns the authorized `OH_SBAIO` execution
branch. Static and configuration probes remain the verification path when no
disposable database is authorized. No Core capability, database cleanup, or
incident remediation is implied by this decision.

## Decision Log

### 2026-10-16 — OH-AUTO2-108 SBAIO operational provider boundary

**Status:** Accepted — retain suppression

SBAIO personnel, attendance, timecard, payroll, leave, and watchlist host
contributions remain suppressed. Existing app/module access and route
authority do not independently establish record-level personnel authorization;
no new visibility rule or authorization field may be created as a migration
shortcut.

### 2026-10-16 — OH-AUTO2-105 SBAIO `/me` personnel-data boundary

**Status:** Accepted — suppress until record-level authority exists

Attendance exceptions, staff names/codes, payroll runs, timecards, and leave
requests are not exposed through SBAIO `/me` summary or watchlist providers.
The existing app/module and route contracts do not independently prove
record-level personnel authorization for this host surface. The provider
therefore returns no sensitive collection before module widgets execute.
Existing canonical `/apps/sbaio/...` routes remain unchanged; host navigation
continues to be subject to the existing route-access filter. A future
record-level contract is required before restoring useful personnel widgets.

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
