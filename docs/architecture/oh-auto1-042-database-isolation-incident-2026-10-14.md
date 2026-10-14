# OH-AUTO1-042 — Database Isolation Breach and Test Bootstrap Hardening

Date: 2026-10-14
Status: **Incident scope documented; fail-closed guard added; database-backed
testing remains suspended**

## Root cause

`app_db_config()` in `app/Core/helpers.php` checks
`APP_ROOT/storage/db_config.php` before reading `ERP_DB_*`/`DB_*` environment
variables. `app/Core/DB.php` calls that resolver when its static connection is
first created.

The OH-AUTO1-041 temporary baseline script set disposable environment values
but used the repository `APP_ROOT`. The repository's installed
`storage/db_config.php` therefore won, silently selecting the installed
configuration. This is a test-bootstrap boundary defect, not a Hospitality
business-rule defect.

The Procurement and Manufacturing concurrency probes already received a
temporary `APP_ROOT` from their runner, but did not independently validate that
the config file exactly matched the attested endpoint. The Hospitality probe
had a broad “config exists” refusal rather than a shared identity check.

## Evidence-supported incident scope

The preliminary baseline command was a temporary PHP script that loaded the
repository autoloader and Hospitality services, then called:

- `GuestsService::create()` — one `INSERT` into `hosp_guests`;
- `RoomsService::create()` — one `INSERT` into `hosp_rooms`;
- `ReservationsService::create()` — one `INSERT` into `hosp_reservations`;
- cleanup deletes for reservations by the newly returned guest ID, the newly
  returned room ID, and the newly returned guest ID.

The selected source was the repository `storage/db_config.php`, whose observed
non-secret identity was loopback host `127.0.0.1`, database `os.susankhya.com`,
and user `root`. No password or other credential is recorded here.

The script output reported successful fixture creation and successful cleanup.
There was no independent before/after snapshot, transaction audit, database
dump, or server audit record. Therefore the following cannot be certified:

- that the installed database was identical to its original state afterward;
- whether auto-increment counters, binary logs, audit logs, or timestamps were
  affected;
- whether any external observer saw the transient rows.

The source evidence supports that no schema DDL, migration, or broad-table
delete was issued by this baseline path. It does **not** support a clean
restoration claim. The installed database must be treated as potentially
affected until an independently authorized owner performs its own review.

The later OH-AUTO1-041 concurrency, rollback, and regression runs used an
independently attested temporary instance after the repository config was
isolated. Those runs are not used to erase or downgrade this incident.

## Bounded correction

Added `scripts/testing/DisposableDbTestConfigurationGuard.php`. It performs no
database connection or SQL execution and requires, before the application DB
layer loads:

- a temporary `APP_ROOT` inside the attested resource root and outside the
  repository;
- an `APP_ROOT/storage/db_config.php` file;
- exact loopback host, port, database name, and test-only user identity;
- a non-empty test credential;
- rejection of production-like names, privileged users, repository config paths,
  missing config, and contradictory values;
- a SHA-256 snapshot and revalidation before protected probe work.

`run_business_integrity_regression.sh` now validates configuration identity and
the live process attestation before creating the disposable database/user, and
checks the temporary config fingerprint after each probe. Procurement,
Manufacturing, and Hospitality concurrency probes use the same guard.

The older Hospitality aggregate suite and Procurement behavioral probes still
use repository-root bootstrap conventions and were not executed in this
assignment. They are treated as blocked database harnesses, not as evidence of
safe execution; they require the same isolated-runner migration before any
future database-backed invocation.

## No-database regression evidence

`scripts/testing/probe_disposable_db_test_configuration_guard.php` uses only
temporary PHP config fixtures and passed **14/14** checks:

- valid identity accepted;
- contradictory ambient database target rejected;
- missing config rejected;
- contradictory port rejected;
- production-like database name rejected;
- privileged user rejected;
- post-authorization target change rejected;
- repository-installed config target rejected.

PHP lint and shell syntax checks pass. No database connection or SQL execution
was performed for OH-AUTO1-042.

## Remaining risks and resume requirements

- The installed-database incident has unresolved restoration uncertainty.
- Database-backed testing remains suspended.
- The shared guard and all probe callers require review before any rerun.
- The older direct-bootstrap Hospitality/Procurement probes require migration
  to the corrected isolated runner before they can resume.
- A separately authorized database owner must assess the installed instance;
  this worker must not reconnect or attempt cleanup.
- Future execution must use the corrected runner, pass explicit database name
  and user identity, complete configuration and process attestation before any
  SQL DDL/DML, and retain pre/post identity evidence.
- No Core change is authorized or required; the resolver defect is contained at
  the non-Core test-bootstrap boundary.
