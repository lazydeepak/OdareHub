# OH-AUTO1-068 — Database Isolation and Requalification

Date: 2026-10-15
Status: **Implemented and verified for bounded disposable testing**

## Root cause and correction

The historical bootstrap defect remains accurately reproduced: `app_db_config()`
checks `APP_ROOT/storage/db_config.php` before ambient `ERP_DB_*`/`DB_*`
variables. A synthetic temporary APP_ROOT with conflicting file and environment
values resolved to the file identity; no installed configuration or credential
was read.

The remaining non-Core runner defect was that
`run_business_integrity_regression.sh` created an incomplete temporary APP_ROOT
and invoked repository-root probe files. It now:

- stages a complete source root through `IsolatedSourceRootTestRunner`;
- writes the temporary config only inside that staged root;
- executes the staged Manufacturing/Procurement probe path;
- verifies the staged source manifest and temporary config hash after probing;
- uses safe database-name normalization; and
- supports `OH_TEST_ONLY=manufacturing-transaction` for bounded qualification.

The existing configuration guard and execution authorizer remain unchanged and
are invoked before application bootstrap or SQL.

## Independent disposable strategy

The executed instance used Homebrew MySQL 9.6 with:

- fresh temporary datadir;
- unique Unix socket and ephemeral loopback port;
- `--no-defaults` and no installed data/config mounts;
- generated test-only database, user, and password;
- temporary staged APP_ROOT outside the repository;
- authorizer verification of PID, process command, datadir, socket, network
  mode, and server identity; and
- runner-owned bounded teardown.

The existing ServBay MySQL process was observed read-only before and after. It
was not queried, stopped, restarted, or used.

## Executed SQL and regressions

### Fixture smoke

On the disposable instance only:

1. `CREATE DATABASE` for the generated test database.
2. `CREATE USER` and `GRANT` for the generated test-only user.
3. Loaded `scripts/testing/disposable_db_fixture.sql`.
4. Committed one InnoDB parent row.
5. Rolled back a second parent insert.
6. Attempted an invalid child insert and confirmed foreign-key rejection.

Results: database creation, fixture load, commit, rollback, and foreign-key
rejection all passed.

### Existing behavioral suite

`OH_TEST_ONLY=manufacturing-transaction bash
scripts/testing/run_business_integrity_regression.sh` executed the staged
Manufacturing transaction probe. Its migration and transaction assertions
passed **21/21**; the bounded runner reported cleanup and installed-service
preservation.

Static suites in the same run passed: authorizer safety **9/9**, transaction
contract audit **32/32**, and Procurement method boundaries **9/9**.

Procurement concurrency and Manufacturing concurrency were not claimed as
qualified in this assignment. Hospitality database suites were not run because
their broader aggregate/runtime scope requires a separately bounded execution
decision.

## Negative safety evidence

The OH-AUTO1-068 non-DB safety probe passed **11/11**, covering synthetic
configuration precedence, repository APP_ROOT rejection, missing and changed
configuration, contradictory ambient identity, incomplete source root,
unattested process/socket/datadir, and installed datadir rejection. Existing
guard and authorizer probes also passed **63/63** and **9/9**.

## Preservation and teardown

- Temporary config hash unchanged before/after SQL.
- Staged source manifest unchanged before/after SQL.
- Disposable server shut down successfully.
- Disposable socket, datadir, database, user, workspace, and temporary APP_ROOT
  were removed by bounded cleanup.
- Installed ServBay PID 1181 and port 3306 remained present.
- No installed database connection, SQL, reset, cleanup, or credential access
  occurred.
