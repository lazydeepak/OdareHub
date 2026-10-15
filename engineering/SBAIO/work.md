# SBAIO — Work

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
  OH-AUTO2-105 remains open and OH-AUTO2-106 remains deferred.

## Current Focus

OH-AUTO2-105 personnel-data authorization audit is complete for the legacy
`/me` host contribution path.

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
