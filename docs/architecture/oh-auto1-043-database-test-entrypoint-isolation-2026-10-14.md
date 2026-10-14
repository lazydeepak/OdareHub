# OH-AUTO1-043 — Database Test Entry-Point Isolation Enforcement

Date: 2026-10-14
Status: **Static enforcement complete; database-backed execution suspended**

## Entry-point inventory

Static source inspection found 25 DB-capable entry points:

- Hospitality: 19
- Procurement: 4
- Manufacturing: 2

The inventory included direct `DB::conn()`, `DB::query()`, and `mysqli`
entry points, migration-backed probes, operator probes with database fixtures,
and the Hospitality aggregate runner. The Procurement transaction-contract
audit was excluded because it only scans source text and does not execute DB
calls.

The earlier 24-entry-point count omitted
`apps/Hospitality/Tests/probe_reservation_create_concurrency.php`. That probe
was introduced by OH-AUTO1-041, already had the specialized configuration and
execution-authorizer guard, and was present in the governed path listing; the
discrepancy was a count omission, not a new OH-AUTO1-044 entry point.

## Single safety contract

`DisposableDbTestEntryPointGuard` now runs before application autoload/bootstrap
for all legacy direct-bootstrap probes. It reuses:

- `DisposableDbTestConfigurationGuard` for exact isolated config identity;
- `DisposableDbExecutionAuthorizer` for process/socket/datadir ownership and
  server identity.

The effective `APP_ROOT` must be a complete isolated source root outside the
repository. The guard also registers a shutdown recheck of the configuration
fingerprint. Missing evidence, repository-root execution, contradictory
environment values, incomplete source roots, or attestation failure aborts
before application bootstrap.

The two existing Procurement/Manufacturing concurrency families and the
Hospitality reservation concurrency probe retain their specialized pre-bootstrap
configuration guard and live authorizer sequence.

## Migrated entry points

The following legacy paths were updated without removing behavioral assertions:

- Procurement: cancellation, request cancellation, supplier update
- Hospitality: registration, migrations, modules, routes/navigation, rooms/guests,
  reservations lifecycle, folio, housekeeping, front-desk void readiness,
  installation lifecycle, operator contribution, housekeeping action, check-in,
  check-out, add-charge, cancel, no-show, and transition concurrency
- Hospitality aggregate runner

Legacy probes are intentionally not made runnable from the repository checkout.
They require a complete isolated source-root runner, which is a separate
execution-contract task.

## Static verification

- Entry-point/bootstrap-order regression: **63/63** across 25 entry points
- Disposable execution-authorizer safety: **9/9**
- Disposable harness safety: **14/14**, `database_connected=false`,
  `sql_executed=false`
- PHP lint: all 25 DB-capable entry points and guard/probe files
- Shell syntax: pass
- Core lock gate: pass
- `git diff --check`: pass

Direct invocation checks for legacy Procurement and Hospitality probes rejected
missing governed evidence before autoloading. No database connection or SQL was
performed for OH-AUTO1-043.

## Remaining restrictions

- No disposable or installed database test is authorized by this report.
- The installed-database restoration uncertainty from OH-AUTO1-041 remains
  open.
- A future runner must provision a complete isolated source root, attest its
  database process/socket/datadir, and prove config identity before launching
  any probe.
- Only after a separate planner decision may controlled disposable regression
  requalification begin.
