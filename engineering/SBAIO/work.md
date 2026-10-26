# SBAIO — Work
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
