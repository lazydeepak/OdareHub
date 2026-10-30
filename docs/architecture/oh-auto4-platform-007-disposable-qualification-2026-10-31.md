# OH-AUTO4-PLATFORM-007 — Disposable Organization Membership Qualification

**Date:** 2026-10-31
**Status:** Qualified in a runner-owned disposable database
**Repository baseline:** `d5b1bb18feb5a62af2428f9ee2eaaa72257fb9`

## Environment

The qualification used a fresh local Homebrew MySQL **9.6.0** instance. The
repository's configured `storage/db_config.php` was not read or used.

| Evidence | Result |
|---|---|
| Server binary | `/opt/homebrew/opt/mysql/bin/mysqld` |
| Source root | Runner-staged outside the repository, inside the temporary resource root |
| Datadir | Fresh temporary datadir, destroyed after the run |
| Socket | Unique temporary Unix socket, destroyed after the run |
| Network | `127.0.0.1` only, port `57947`, no wildcard listener |
| Server defaults | `--no-defaults` |
| MySQL X Plugin | Disabled with `--mysqlx=0` |
| Credentials | Generated test-only database/user/password; no repository credentials |
| Cleanup owner | `IsolatedSourceRootTestRunner` plus path-bounded temporary-resource cleanup |

The disposable database name, user, password, PID, socket, datadir, and staged
source-root paths were generated for this run and are not retained.

## Attestation

The existing `DisposableDbExecutionAuthorizer` independently verified **24/24**
checks before application bootstrap and repository execution, including:

- temporary resource-root containment;
- datadir and socket containment;
- rejection of installed application paths;
- live non-root process ownership;
- MySQL process identity;
- `--no-defaults` and loopback-only command-line configuration;
- exact process datadir/socket identity;
- no wildcard listener;
- server-reported datadir, socket, network mode, and port identity.

The staged source root was created by `IsolatedSourceRootTestRunner` outside the
repository. Its `storage/db_config.php` contained only the generated disposable
endpoint identity and credential. The entry-point guard revalidated the config
fingerprint before bootstrap and registered shutdown verification.

## Commands

The run used the existing local tooling and guarded entry point:

```text
mysqld --no-defaults --basedir=/opt/homebrew/opt/mysql \
  --datadir=<temporary-resource-root>/mysql-data \
  --socket=<temporary-resource-root>/mysql.sock \
  --pid-file=<temporary-resource-root>/mysql.pid \
  --bind-address=127.0.0.1 --port=57947 --mysqlx=0 \
  --skip-name-resolve

mysql --no-defaults --protocol=SOCKET --socket=<temporary-resource-root>/mysql.sock
  # create generated database/user and grant only that database

mysql --no-defaults --protocol=SOCKET \
  --socket=<temporary-resource-root>/mysql.sock < tools/restore/live-schema.sql

php apps/Platform/Tests/probe_organization_membership_repository.php
```

The probe was run only after attestation and schema loading. It uses the
existing `006_create_user_organization_memberships.sql` migration and the
existing `OrganizationMembershipRepository`; no source implementation was
changed.

## Results

The repository probe completed **19 passed, 0 failed**:

- membership migration creates the table;
- active membership creation returns a persisted ID;
- duplicate active membership is rejected;
- inactive historical membership is retained;
- organization evidence resolves through the pure fail-closed resolver;
- evidence remains bound to the exact `users.id`;
- inactive organization evidence fails closed;
- multiple organization memberships require explicit selection;
- revocation preserves the membership row;
- inactive membership can be explicitly activated;
- unknown organizations are rejected by referential integrity;
- concurrent same-pair creation yields one success and one conflict;
- active replacement leaves exactly one active row;
- invalid replacement is rejected without changing prior active state.

## Schema and cleanup effects

The existing `tools/restore/live-schema.sql` was loaded into the new disposable
database only. The membership migration created `org_user_memberships` there.
All probe users, organizations, memberships, and migration state existed only in
that temporary database. The probe's cleanup completed, the MySQL process was
stopped, and the temporary datadir, socket, staged source root, generated config,
credentials, and database were removed. No installed, development, production,
or incident database was contacted or modified.

## Boundaries preserved

No Core/Auth/ACL files, global authorization, Staff binding, Procurement,
Manufacturing, SBAIO, Leave, Timecard, deployment, or production runtime path
was changed or activated. Existing historical evidence was not treated as a
substitute for this fresh attestation.
