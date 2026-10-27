# SBAIO — Work
### 2026-10-27 — OH-AUTO3-SBAIO-BULK-IO-CONTAINMENT-001 suite bulk-I/O containment

**Status: Complete — offline/static verification only; no exports, imports, restores, package operations, production filesystem mutations, installed database, or DB-backed probe.**

- [x] Inventoried all eleven suite-level routes: three index GETs, two import
  POSTs, two export POSTs, three restore POSTs, and one backup-download GET.
- [x] Added centralized fail-closed admission after existing SBAIO app access
  and before session boot, CSRF, controller, SQL, filesystem, package, or
  restore dispatch.
- [x] Confirmed underlying bypass-capable operations: export file/package
  creation, import upload and transactions, restore package/data application,
  and backup `readfile()`.
- [x] Bulk-I/O containment probe passed **58/58**. No shared service or
  history service was modified.
- [x] Remaining owner decisions are suite-level actor authority, identity and
  organization scope, record/package visibility, and explicit bulk-I/O
  approval for import/export/restore/download.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-DELIVERY-INTEGRITY-001 CSV delivery integrity

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced the baseline chronology: CSV success was recorded after
  `php://output` opened but before BOM, headers, rows, and close completed.
- [x] Success history now records only after successful BOM/header/row writes
  and successful stream close.
- [x] BOM, header `fputcsv()`, row `fputcsv()`, stream-open, and stream-close
  failures now fail the export and record failure history.
- [x] Delivery-integrity probe passed **14/14**; formula safety **30/30**;
  CSV admission/projection **244/244**; personnel export containment **23/23**.
- [x] `ExportHistoryService` was not changed; existing historical success and
  failure record contracts remain preserved.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-FORMULA-SAFETY-001 CSV formula safety

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced the prior CSV behavior: formula-like text was passed to
  `fputcsv()` unchanged and emitted as a formula candidate.
- [x] Added centralized cell serialization at the existing CSV boundary;
  textual `=`, `+`, `-`, and `@` triggers receive a literal apostrophe while
  numeric values remain numeric-compatible.
- [x] Formula safety probe passed **30/30**, including exact before/after CSV
  lines, quoted cells, numeric strings/scalars, booleans, nulls, and JSON
  structured values.
- [x] Existing CSV admission/projection **244/244**, personnel export
  containment **23/23**, route inventory **56/56**, mutation **74/74**, index
  **57/57**, and HTML report **50/50** probes remain green.
- [x] Explicit `fputcsv()` escape argument preserves the existing escape
  behavior and removes the PHP deprecation without changing export contracts.

### 2026-10-27 — OH-AUTO3-SBAIO-PAYROLL-PARITY-CONTAINMENT-001 Payroll parity containment

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Contained `/apps/sbaio/payroll/parity` with centralized
  `isPersonnelParityAvailable()` admission before session boot and parity
  controller/service execution.
- [x] Before containment, `PayrollParityValidationService::report(80)` read
  salary-monthly rates, payroll records/runs, staff rows, fixed payroll
  profiles, and emitted employee/salary/financial comparison data.
- [x] Parity and complete route inventory probe passed **56/56**. Admission
  performs only the existing `installed_plugins` query and no payroll/staff
  source SQL.
- [x] Inventory confirms all six personnel modules retain index, HTML report,
  CSV export, and mutation containment; Payroll parity is now contained too.
- [x] Remaining authorization decisions are Staff identity binding,
  organization/supervisor scope, and record-level personnel/payroll visibility.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-MUTATION-CONTAINMENT-001 mutation containment

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Contained all nine personnel/payroll POST endpoints: Staff create;
  Payroll run creation; Leave create and holiday; Attendance save; Timecard
  generation and approval; Schedule template and assignment.
- [x] Mutation gates run after existing app access but before session/controller
  setup, CSRF dispatch, service invocation, SQL, transaction, or commit paths.
- [x] Mutation containment probe passed **74/74**. Each blocked endpoint uses
  only the existing `installed_plugins` admission query and no source-table
  SQL before dispatch.
- [x] Remaining reachable entry points: Payroll `/parity` remains a GET
  parity-validation surface; six `/export` pages remain metadata-only landing
  pages, while CSV downloads remain fail-closed. No mutation is reachable
  through those pages.
- [x] No controller, service, POST workflow, schema, permission, identity, or
  business-record behavior was redesigned.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-INDEX-CONTAINMENT-001 module index containment

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Added centralized `isPersonnelIndexAvailable()` admission before the
  six module index controllers execute.
- [x] GET index surfaces now fail closed for Attendance, Leave, Payroll,
  Schedules, Staff, and Timecards; Customers remains available.
- [x] Index containment probe passed **57/57**. Each blocked route performs
  only the existing `installed_plugins` admission query and no sensitive source
  table query before controller execution.
- [x] Audited affected workflows and separately documented POST risks. Staff
  create, Payroll run creation, Leave creation/holiday creation, Attendance
  save, Timecard generation/approval, and Schedule template/assignment POST
  routes retain existing app-access and CSRF checks but lack proven
  record-level Staff identity, organization, or supervisor authority.
- [x] No POST, controller, service, schema, permission, identity, or workflow
  behavior was changed.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-HTML-REPORT-CONTAINMENT-001 HTML report containment

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Added centralized `findActiveHtmlReport()` containment after active
  report admission and before six sensitive report views can receive a report
  descriptor or issue source SQL.
- [x] Contained `/apps/sbaio/{attendance,leave,payroll,schedules,staff,timecards}/report`.
- [x] Changed the six report-view fallbacks to the same fail-closed resolver,
  preventing direct view inclusion from re-opening the report SQL path.
- [x] HTML containment probe passed **50/50**; blocked keys perform only the
  installed-module admission query and never query their source tables.
- [x] Audited index/dashboard paths: Staff, Payroll, Leave, Attendance,
  Schedules, and Timecards still query/render personnel, payroll, attendance,
  leave, schedule, or staff-selector data under existing SBAIO app access.
  Those surfaces remain unresolved owner decisions and were not changed in
  this report-only slice.

### 2026-10-27 — OH-AUTO3-SBAIO-PERSONNEL-EXPORT-CONTAINMENT-001 personnel/payroll export containment

**Status: Complete — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Blocked six report keys lacking established personnel/financial
  record-level export authority: Attendance, Leave, Payroll, Schedules, Staff,
  and Timecards.
- [x] Containment occurs after existing active-report admission but before
  source lookup and SQL; no new permission or identity policy was added.
- [x] Preserved Task, Notice, Customers, Expenses, and Sales export behavior
  and explicit projections.
- [x] Personnel export containment probe passed **23/23**. Existing CSV
  admission/projection probe remains **244/244**.
- [x] Remaining owner decisions concern personnel/financial visibility,
  Staff identity binding, organization scope, and supervisor scope.

### 2026-10-27 — OH-AUTO3-SBAIO-CSV-ADMISSION-001 unresolved personnel/financial visibility

**Status: Deferred owner decision — no runtime change.**

The explicit Staff, Payroll, Leave, Attendance, and Timecards projections are
schema-bound preservation of the existing export contract, not a new
record-level authorization grant. Existing evidence does not establish Staff
identity binding, organization/supervisor scope, or personnel/financial export
visibility beyond SBAIO app/report admission. Future narrowing or filtering
requires a separate owner-approved decision; no policy was invented here.

## 2026-10-27 — OH-AUTO3-SBAIO-CSV-ADMISSION-001 CSV admission and projection audit

**Status: Complete — offline/static verification only; no installed database or DB-backed SBAIO probe.**

- [x] Admit CSV exports through the existing active-report registry before any
  source lookup, query, or stream.
- [x] Replace every unrestricted SBAIO CSV source with an explicit,
  schema-aligned projection while preserving established fields and prior
  Task/Notice privacy exclusions.
- [x] Add database-free coverage for all 11 report sources, routes, manifest
  permissions, admission ordering, and projection safety.
- [x] CSV admission/projection probe passed **244/244**. Focused Notice,
  Task, Staff, Expenses, Customers, and Sales regressions passed; architecture,
  deletion **28/28**, deployment readiness, PHP lint, and diff hygiene passed.
- [x] No installed database or DB-backed SBAIO probe ran; unrelated working-tree
  artifacts remain untouched.

## 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-EXPORT-PRIVACY-001 Notice export privacy

**Status: Contained — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reconciled the prior export projection against Notice privacy evidence.
  Exact retrieval and report presentation explicitly exclude `body`; the export
  route has only the existing SBAIO app-access gate and no separate
  record-level body visibility contract.
- [x] Classified Notice `body` export as unauthorized/unproven rather than
  inventing an export permission or broadening application authorization.
- [x] Notice CSV now retains authorized metadata (`id`, `title`, type, period,
  acknowledgment, level, active state, and timestamps) while excluding both
  `body` and `related_staff_id`. Notice persistence and lifecycle behavior are
  unchanged.
- [x] Privacy probe passed **6/6**; the combined Staff read-projection probe
  passed **8/8**. No installed database or DB-backed SBAIO probe ran.

## 2026-10-26 — OH-AUTO3-SBAIO-STAFF-READ-PROJECTION-001 read projection containment

**Status: Contained — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Inventory found Task `search()` and `findById()` selected
  `related_staff_id`, although the Task controller and view never consumed it.
  Notice `recent()`, `findById()`, reports, and views were already Staff-free.
- [x] Confirmed `activeStaff()` remains an uncalled legacy helper and is not a
  read projection consumer; no new Staff access authority exists.
- [x] Confirmed generic CSV export used `SELECT *`, which exposed historical
  Task/Notice Staff IDs. No authorized export dependency on those IDs was found.
- [x] Replaced only the unnecessary Task read fields and Task/Notice export
  projections; preserve all other columns and historical database storage.
- [x] Focused projection probe passed **8/8**. Task continuity **34/34**,
  queue **31/31**, report **16/16**, input integrity **76/76**; Notice
  continuity **37/37**, report **18/18**, input integrity **142/142**;
  Staff containment **32/32**. No installed database or DB-backed probe ran.

## 2026-10-26 — OH-AUTO3-SBAIO-STAFF-WRITE-001 Staff-association write containment

**Status: Contained — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Verified the remaining write path after selector containment: raw
  browser-supplied positive `related_staff_id` values were still accepted by
  both service validators and passed into the existing INSERT parameter list.
- [x] Task and Notice creation now reject normalized positive Staff IDs with
  `related_staff_id_unavailable`, before `DB::conn()`/INSERT. Malformed values
  remain `related_staff_id_invalid`; absent, `null`, empty, and zero sentinels
  remain valid unassigned input and persist as `NULL`.
- [x] Historical rows are untouched; the nullable schema and existing INSERT
  shape remain unchanged. No Staff authorization policy, identity mapping,
  permission, route, schema, lifecycle, or Core/Auth/ACL behavior was added.
- [x] Task integrity passed **76/76**, Notice integrity **142/142**, and the
  Staff containment probe passed **32/32**. Positive-association rejection and
  unassigned INSERT behavior were verified with database doubles only.

## 2026-10-26 — OH-AUTO3-SBAIO-STAFF-SELECTOR-001 selector containment

**Status: Contained — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Verified the prior data flow: both Task and Notice controllers called
  `activeStaff()`, whose existing query selected `id`, `full_name`, and
  `employee_code` from active or NULL-status `sbaio_staff` rows; both views
  rendered those values in `related_staff_id` selectors.
- [x] Classified the exposure as **proven potential personnel-data exposure**:
  existing SBAIO app access was the only gate, while no authoritative
  users-to-Staff binding, organization scope, supervisor scope, or record-level
  Staff authorization policy exists.
- [x] Removed both Staff-row data flows and selectors. The forms now show a
  localized unavailable state and submit no Staff identifier, so the existing
  validator preserves the unassigned NULL sentinel.
- [x] Preserved direct numeric `related_staff_id` validation and persistence;
  no Staff authorization policy, identity mapping, permission, route, schema,
  lifecycle, or Core/Auth/ACL behavior was added.
- [x] Containment probe passed **28/28**; Task input integrity **72/72**,
  continuity **34/34**, Notice integrity **136/136**, and continuity **37/37**
  passed. Existing Staff query methods remain available only as uncalled legacy
  service methods pending an owner-approved authorization contract.

## 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-REPORT-001 report ownership

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Moved the existing four Notice report reads and default payload into
  `NoticesService::reportData()` without changing metrics, predicates,
  ordering, limits, or failure behavior.
- [x] The existing authorized route still admits the active registry report;
  `NoticesController::report()` now supplies the owner-prepared payload.
- [x] `Views/report.php` now performs presentation only: zero database and
  registry operations. Existing report privacy remains intact; body and Staff
  linkage are not selected or rendered.
- [x] Report ownership probe passed **18/18**; Notice continuity **37/37**,
  integrity **136/136**, Task queue **30/30**, continuity **34/34**, Task
  integrity **72/72**, report parity **16/16**, Expenses **91/91**,
  Customers **195/195**, and Sales **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-CONTINUITY-001 exact-ID continuity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Notice creation now uses the current connection transaction, verifies a
  positive generated identity before commit, and rolls back insert, identity,
  and commit failures.
- [x] The existing `/apps/sbaio/notices` surface carries `notice_id` through
  the create redirect and performs strict bounded exact-ID retrieval.
- [x] Exact retrieval exposes only ID, title, type, target period, level,
  acknowledgment flag, active flag, and creation timestamp. Body and Staff
  linkage remain intentionally omitted because no established record-level
  visibility contract supports them.
- [x] Continuity probe passed **37/37**; Notice integrity **136/136**, Task
  queue **30/30**, continuity **34/34**, Task integrity **72/72**, report
  parity **16/16**, Expenses **91/91**, Customers **195/195**, and Sales
  **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-NOTICES-INTEGRITY-001 Notice input integrity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced Notice creation's silent string/integer coercion for text,
  acknowledgment/active flags, and `related_staff_id`.
- [x] Added NoticesService-owned scalar, schema-length, required-title,
  positive Staff-ID, and exact binary-flag validation while preserving the
  existing INSERT shape and checkbox semantics (`1` or absent).
- [x] Malformed supplied arrays, objects, booleans, numbers, malformed Staff
  IDs, and non-`0/1` flags fail before `DB::query`; blank/zero Staff sentinels
  remain unassigned.
- [x] Notice integrity probe passed **136/136**; Task queue **30/30**,
  continuity **34/34**, Task integrity **72/72**, report parity **16/16**,
  Expenses **91/91**, Customers **195/195**, and Sales **225/225** remain
  passing.

## 2026-10-26 — OH-AUTO3-SBAIO-TASKS-DUE-PARITY-001 report/queue due parity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the discrepancy: the Task report `due_7d` aggregate counted
  terminal tasks, while the actionable queue `upcoming` mode excluded the
  existing `done`, `completed`, and `closed` statuses.
- [x] Aligned only the report `due_7d` predicate with the existing queue:
  non-null `due_date` inclusive from `CURDATE()` through seven days, excluding
  those established terminal values. The report `overdue` predicate already
  matched.
- [x] Preserved the report's ascending recent snapshot as an intentional
  presentation distinction from the actionable queue's `due_date DESC,
  id DESC` order.
- [x] Report probe now passes **16/16**; queue **30/30**, continuity **34/34**,
  Task integrity **72/72**, Expenses **91/91**, Customers **195/195**, and
  Sales **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-TASKS-QUEUE-001 bounded Task discovery

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Added bounded exact-filter Task discovery on the existing queue surface:
  `overdue`, `upcoming`, `historical`, and `all` queue modes, exact status,
  priority, bucket, and inclusive due-date filters.
- [x] Results use a hard 50-row page cap, deterministic `due_date DESC,
  id DESC` ordering, opaque filter-bound cursors, strict keyset continuation,
  and explicit NULL-date handling.
- [x] Overdue means due before `CURDATE()` with existing terminal statuses
  excluded; upcoming means `CURDATE()` through seven days with the same
  established terminal-status exclusion. No status mutation or new state was
  added.
- [x] Queue probe passed **30/30**; continuity **34/34**, Task integrity
  **72/72**, report ownership **15/15**, Expenses **91/91**, Customers
  **195/195**, and Sales **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-TASKS-CONTINUITY-001 Task creation durability and exact retrieval

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Task creation now uses a same-connection transaction, validates the
  positive generated ID before commit, and rolls back insert, identity, or
  commit failures.
- [x] The existing Task POST redirects with the exact `task_id`; the existing
  administrator Task page reads that ID through a bounded parameterized
  `WHERE id = ? LIMIT 1` path.
- [x] Malformed, overflow, negative, fractional, scientific, array, and
  boolean IDs fail closed before reads; no latest-row or title inference was
  added. Existing SBAIO app authorization remains unchanged.
- [x] Continuity probe passed **34/34**; Task integrity **72/72**, Expenses
  **91/91**, Customers **195/195**, and Sales **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-TASKS-REPORT-001 report ownership

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the existing Task report view performed four direct reads and
  imported both `DB` and the report registry.
- [x] Moved the unchanged summary, status, priority, and recent-task SQL into
  `TasksService::reportData()` with the existing default-on-read-failure
  payload preserved.
- [x] Route authorization and active report-registry admission remain before
  controller delegation; the controller now supplies the admitted descriptor
  and prepared report data to the view.
- [x] Report ownership probe passed **15/15**; Task integrity **71/71**,
  Expenses **91/91**, Customers **195/195**, and Sales **225/225** remain
  passing.

## 2026-10-26 — OH-AUTO3-SBAIO-TASKS-INTEGRITY-001 Task input integrity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced Task creation's unsafe `(string)` and `(int)` coercion of
  text/date fields and `related_staff_id` before the existing INSERT.
- [x] Added one TasksService-owned contract for scalar types, schema-aligned
  lengths, required title, real calendar dates, and preserved blank defaults
  plus arbitrary existing status/priority values.
- [x] Related Staff IDs now normalize only positive signed-INT-compatible
  decimal strings/integers; blank and zero sentinels remain unassigned. No
  Staff/authenticated-user relationship or authorization policy was inferred.
- [x] Controller validates raw POST input before service dispatch; malformed
  payloads perform zero SQL. Task probe passed **71/71**; Expenses **91/91**,
  Customers **195/195**, and Sales **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-RETRIEVAL-001 exact retrieval and search

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the pre-change Expense page exposed only the recent `id DESC`
  list; it had no exact-ID reader or bounded historical search.
- [x] Added exact positive-ID retrieval through `WHERE id = ? LIMIT 1`, with
  malformed IDs rejected before reads and existing SBAIO app authorization
  retained before controller access.
- [x] Added exact reference/status/date-range search with deterministic
  `expense_date DESC, id DESC` keyset pagination, opaque filter-bound cursors,
  explicit NULL-date continuation, and a hard 50-row page cap.
- [x] Existing creation confirmation, statuses, monetary precision,
  reporting/export routes, and mutation boundaries remain unchanged.
- [x] Expense retrieval probe passed **91/91**; Customers **195/195** and Sales
  **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-DURABILITY-001 transactional identity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the existing `DB::conn()`/`mysqli` convention used by Sales and
  Customers is available without changing Core: begin, insert, verify positive
  generated identity, commit, and rollback on failure.
- [x] Expense creation now returns the same-connection generated record ID only
  after commit. Missing identity, insert failure, commit failure, and rollback
  failure cannot report success.
- [x] Existing success redirect now carries `expense_id` and uses localized
  ID-bearing confirmation; no route, schema, authorization, or approval
  behavior changed.
- [x] Durability probe passed **78/78**; Customers **195/195** and Sales
  **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-INPUT-002 complete input integrity

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the prior monetary fix still left reference, category, status,
  and date fields subject to `(string)` coercion before SQL.
- [x] Added one service-owned Expense create contract covering scalar types,
  `VARCHAR` bounds, required reference, optional blank defaults, arbitrary
  existing statuses, and strict calendar dates.
- [x] Controller now validates the complete raw POST payload and passes only
  normalized values to the existing service INSERT. Malformed non-monetary
  values fail before SQL; no route or authorization boundary changed.
- [x] Expanded Expense probe passed **62/62**; Customers **195/195** and Sales
  **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-EXPENSES-INTEGRITY-001 monetary validation

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced the Expense defect: `ExpensesService::create()` converted
  arbitrary input through `is_numeric()` and float coercion, silently turning
  malformed values into `0.0` and allowing non-schema monetary formats.
- [x] Added exact decimal-string validation aligned to the existing
  `DECIMAL(12,2)` schema: up to 10 integral digits and 2 fractional digits,
  with normalized string persistence and no float conversion.
- [x] Invalid, missing, overflow, scientific, special, comma-formatted, and
  non-string amounts now fail before the INSERT. Existing app-access, CSRF,
  expense reference, status, date, route, and schema contracts remain intact.
- [x] Expense monetary probe passed **29/29**; Customers **195/195** and Sales
  **225/225** remain passing.

## 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-SEARCH-001 bounded historical discovery

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the Customers surface had only the bounded recent list and
  exact-ID retrieval; no historical search capability existed.
- [x] Added Customers-owned exact filters for name, email, phone, status, and
  inclusive creation-date bounds. Empty filters, malformed scalars/dates, and
  reversed ranges fail before any database read.
- [x] Added opaque forward keyset cursors bound to normalized filters, with
  deterministic `created_at DESC, id DESC` ordering, explicit NULL-date
  continuation, and a bounded 50-row page plus overflow row.
- [x] Integrated search into the existing administrator Customers page without
  adding a route or changing creation, exact-ID, reporting, export, or Sales
  behavior. Customers passed **193/193** and Sales **225/225**.

## 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-REPORT-001 report service ownership

**Status: In progress — offline verification only; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the Customers report view performed the summary, status, and
  recent-record reads directly and could also resolve report metadata itself.
- [x] Moved the existing three report reads, SQL predicates, ordering, limits,
  and default-on-error payload into `CustomersService::reportData()`.
- [x] The admitted report route now hands metadata and prepared data through
  `CustomersController`; the presentation view performs zero database and
  registry operations.
- [x] Customers regression passed **160/160** and Sales **225/225**.

## 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-CONTINUITY-001 exact-ID continuity

**Status: Complete for offline verification; no installed database or DB-backed SBAIO probe.**

- [x] Confirmed the prior Customer create flow returned no generated identity,
  had no transaction/rollback boundary, and redirected without an exact-record
  selector or view.
- [x] Customer creation now verifies the same-connection positive generated ID,
  commits only after identity verification, and rolls back on insert, identity,
  or commit failure.
- [x] Existing administrator Customers surface now performs strict positive-ID
  retrieval through `WHERE id = ? LIMIT 1`; create success carries `customer_id`
  and renders a direct authoritative-record link.
- [x] Customers regression passed **151/151** and Sales **225/225**. Changed
  files passed PHP lint and diff hygiene; architecture, deletion-family, and
  deployment-readiness validation completed separately. No installed database
  or DB-backed SBAIO probe was executed.

## 2026-10-26 — OH-AUTO3-SBAIO-CUSTOMERS-INPUT-001 strict create-input types

**Status: Complete for offline verification; no installed database or DB-backed SBAIO probe.**

- [x] Reproduced the baseline array coercion: an array-shaped customer name
  emitted an `Array to string conversion` warning and became the accepted value
  `Array`.
- [x] Added a pre-normalization string-only type boundary for customer name,
  contact name, email, phone, and status. Missing/`null` values retain existing
  empty/default behavior; booleans, numbers, arrays, objects, and other supplied
  non-string types fail closed.
- [x] Added database-free service and controller regressions proving malformed
  payloads do not reach `CustomersService::create()` or `sbaio_customers` INSERT.
- [x] Customers probe passed **121/121**; Sales regression passed **225/225**.

## 2026-10-26 — OH-AUTO3-SBAIO-SALES-CONTINUITY-001 exact-ID continuity

**Status: Complete for offline verification; database-backed SBAIO probes remain environment-bound.**

- [x] Confirmed the prior ID-bearing confirmation had no authoritative exact-ID
  retrieval or direct record link; list/search paths could be affected by
  duplicate references and pagination.
- [x] Added bounded read-only `SalesService::findById()` and integrated it into
  the existing administrator Sales page via strict positive `sale_id` input.
- [x] Existing create success now links to `/apps/sbaio/sales?sale_id={id}`;
  authorization precedes the exact read, and missing/malformed/unavailable
  records fail closed without disclosure or fallback inference.
- [x] Sales regression passed **225/225** and Customers **68/68**; changed-file
  PHP lint and diff hygiene passed. No installed database or DB-backed SBAIO
  probe was executed.

## 2026-10-26 — OH-AUTO3-SBAIO-SALES-INTEGRITY-001 transactional identity verification

**Status: Complete for offline verification; database-backed SBAIO probes remain environment-bound.**

- [x] Confirmed the pre-change defect: Sales inserted before identity
  verification, with no transaction or rollback, while `sbaio_sales` is an
  existing InnoDB table.
- [x] Bounded Sales creation now begins a transaction, inserts, verifies the
  same connection's positive generated identity, commits only after identity
  success, and rolls back on insert, identity, or commit failure.
- [x] Sales regression passed **175/175**, including transaction ordering,
  rollback, commit failure, invalid/overflow identity, and no-false-success
  coverage. No installed database or DB-backed SBAIO probe was executed.

## 2026-10-26 — OH-AUTO3-SBAIO-SALES-CREATE-001 exact created identity

**Status: Complete for offline verification; database-backed SBAIO probes remain environment-bound.**

- [x] Confirmed the prior Sales create flow returned no identity and redirected
  with a generic confirmation.
- [x] Sales creation now returns the current connection's positive generated
  `insert_id`; invalid identity evidence and insert failures fail closed before
  any success redirect.
- [x] The existing post-create redirect now carries a localized ID-bearing
  confirmation, with EN/JA/NE coverage. Existing administrator, CSRF,
  validation, search, pagination, report, export, route, schema, and lifecycle
  behavior remains unchanged.
- [x] Sales regression passed **163/163**; changed-file PHP lint and diff
  hygiene passed. No installed database or DB-backed SBAIO probe was executed.


## 2026-10-26 — OH-AUTO3-SBAIO-SALES-PAGING-001 keyset pagination

**Status: Complete for offline verification; database-backed SBAIO probes remain environment-bound.**

- [x] Confirmed the integrated Sales search exposed only the first 50 matching
  rows; its overflow flag did not provide access to later records.
- [x] Added Sales-owned forward keyset pagination over the existing
  `sale_date DESC, id DESC` order. Opaque cursors bind the last date/id and a
  normalized-filter fingerprint; NULL dates use an explicit final-segment
  predicate.
- [x] Preserved exact reference, status, and inclusive date filters across
  pages, strict cursor validation before reads, parameterized SQL, `LIMIT 51`,
  and the existing administrator boundary.
- [x] Sales regression passed **146/146**; changed-file PHP lint and diff
  hygiene passed. Architecture, deletion-family, and deployment-readiness
  verification remain part of final integration validation.
- [x] No installed database, DB-backed SBAIO probe, deployment, activation, or
  unrelated artifact operation occurred.


## 2026-10-26 — OH-AUTO3-SBAIO-SALES-SEARCH-001 bounded Sales search

**Status: Complete for offline verification; database-backed SBAIO probes remain environment-bound.**

- [x] Confirmed the pre-change Sales administration listing was limited to
  `SalesService::recent()` and the latest 25 records; no equivalent historical
  search existed.
- [x] Added Sales-owned exact reference, established-status, and inclusive
  sale-date range search with strict validation, parameterized predicates,
  deterministic `sale_date DESC, id DESC` ordering, a 50-row display bound,
  and truncation indication.
- [x] Preserved existing administrator authorization, create behavior,
  reporting, exports, lifecycle statuses, routes, and schema boundaries.
- [x] Focused Sales regression passed **130/130**; Customers regression passed
  **68/68**; changed-file PHP lint, architecture gates, deletion-family gates
  **28/28**, and `git diff --check` passed.
- [x] No installed database, DB-backed SBAIO probe, deployment, activation, or
  unrelated artifact operation occurred.


## 2026-10-26 — OH-AUTO3-SBAIO-SALES-REPORT-001 report read extraction

**Status: Implemented and verified offline; database-backed Sales probes remain environment-bound.**

- [x] Moved the Sales report's summary, status distribution, customer ranking,
  and recent-sales reads into `SalesService::reportData()` without changing
  predicates, aggregation, ordering, limits, or default-on-error behavior.
- [x] Existing administrator authorization and active report admission remain
  in the existing report route before the controller prepares data; no route,
  permission, report capability, schema, mutation, or export change was added.
- [x] `Views/report.php` now consumes only the admitted report descriptor and
  prepared payload; it contains no database or registry operation.
- [x] Sales admin workflow probe passed **76/76**; neighboring Customers
  regression passed **68/68**; PHP lint, architecture gates, deletion-family
  gates **28/28**, and diff hygiene passed.
- [ ] Database-backed SBAIO probes remain blocked by the disposable-entry guard;
  no installed database, deployment, activation, or production operation was performed.


## 2026-10-16 — OH-AUTO2-105B authorized assessment prerequisite result

- [x] Verified target identity non-destructively from the existing configuration
  and incident record: `127.0.0.1:3306 / os.susankhya.com`; the configured
  application identity is `root` and was not used for an assessment.
- [x] Checked the preserved `storage/manual-recovery-test-001` recovery-point
  payloads without database access: database and filesystem payloads exist,
  recorded SHA-256/size evidence matches, gzip integrity passes, and ZIP
  integrity passes. This is evidence of payload integrity only, not independent
  proof of complete scope, transactional consistency, or restorable target
  state.
- [!] Assessment is **BLOCKED before connection**: no genuine least-privilege
  read-only database account with independently verified privileges is
  available. The recovery metadata also lacks the independent completeness /
  consistency / restore evidence required by the recovery contract.
- [x] No application-root credential, database connection, SQL, cleanup,
  restoration, deletion, schema operation, migration, or preparation was
  performed. Lifecycle logs, PHPUnit cache, and incident evidence remain
  preserved.

## 2026-10-16 — OH-AUTO2-105R unauthorized database probe incident

- [!] The committed OH-AUTO2-105 record's “no installed database” statement is
  superseded by this incident reconciliation; the original record is retained
  unchanged below as historical evidence.
- [x] Read-only reconstruction identified the accidentally executed
  `apps/SBAIO/Tests/probe_behavioral_sales_status.php` as DB-backed. Its direct
  bootstrap uses the repository configuration, which identifies the local
  development target `127.0.0.1 / os.susankhya.com` (credentials not repeated
  here).
- [x] Source inspection confirms the probe can create `sbaio_sales`, delete
  `VALID-%` rows, insert `VALID-OPEN-A` and `VALID-OMITTED-B`, and query those
  rows. The available evidence does not establish exact completion, prior
  table state, transaction/rollback state, or whether the target was empty.
- [x] No SQL, cleanup, migration, repair, restart, or DB-backed rerun was
  performed during OH-AUTO2-105R. Repository logs and the untracked PHPUnit
  cache were preserved.
- [!] Final classification: **IMPACT UNDETERMINED**. Cleanup remains
  unauthorized pending an independently approved target assessment and
  restoration decision.

## 2026-10-16 — OH-AUTO2-105S DB-backed probe execution safeguard

- [x] Added the existing `DisposableDbTestEntryPointGuard` before autoload and
  DB bootstrap in `probe_behavioral_sales_status.php`.
- [x] Added SBAIO to the isolated source-root entry-point inventory and the
  read-only static guard probe; the governed inventory now contains **29** DB
  entry points, including the SBAIO lifecycle probe and the related guarded
  Manufacturing BOM entry point.
- [x] Added an explicit `OH_SBAIO` branch and opt-in selector to the existing
  disposable regression orchestrator. Ordinary direct invocation without
  attested disposable evidence fails before application bootstrap.
- [x] Static/configuration-only proof passes **65/65**; isolated source-root
  proof passes **17/17**. No DB-backed probe was executed.
- [!] The affected database remains unassessed and unchanged by this work;
  OH-AUTO2-105 remains open. OH-AUTO2-106 proceeded independently as a
  non-database provider-governance design audit; it did not reopen 105B.

## Current Focus

OH-AUTO2-105 personnel-data authorization audit is complete for the legacy
`/me` host contribution path.

## 2026-10-16 — OH-AUTO2-108 operational provider migration inventory

- [x] Reconfirmed that SBAIO summary and monitoring callbacks remain empty or
  suppressed before sensitive module collection.
- [x] No personnel, attendance, timecard, payroll, leave, or watchlist
  contribution was admitted; existing record-level authorization remains
  unproven.
- [x] No SBAIO source, route, database, or authorization change was made.

## In Progress

- [x] Audit attendance, timecard, payroll, and leave host widgets for existing
  record-level authorization evidence.
- [x] Suppress sensitive summaries/watchlists where that evidence is absent;
  preserve route-authorized navigation only.

## Next

- [ ] Record the next confirmed task here.

## Blocked

- None recorded.

## Completed

- OH-AUTO2-105: SBAIO personnel/payroll summaries and watchlists are suppressed
  before module provider collection. No new authorization model was added.

## Evidence

- Manufacturing host-provider proof: **12/12**; SBAIO suppressed-summary
  assertions included. No installed database or browser operation.

The final sentence above is the original OH-AUTO2-105 record and is retained
as historical evidence. OH-AUTO2-105R corrects it: the DB-backed SBAIO probe
was accidentally executed against the configured local development target,
and impact remains undetermined.
