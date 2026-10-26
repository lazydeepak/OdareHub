# SBAIO — Decisions
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
