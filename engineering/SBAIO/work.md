# SBAIO — Work

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
