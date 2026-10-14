# OH-AUTO1-028 — Disposable MySQL/MariaDB Environment Discovery

Date: 2026-10-14
Status: **Safe disposable environment established and destroyed; fixture-level
verification passed**

## Resource discovery

| Resource | Result |
|---|---|
| Docker CLI | Present, version 29.8.0 |
| Docker daemon | Unavailable; socket `/Users/lazydeepak/.docker/run/docker.sock` did not exist |
| Podman / Colima / Lima / Rancher Desktop | No usable CLI found |
| Homebrew MySQL binaries | Present: MySQL 9.6.0 client/server/admin |
| Homebrew MySQL service | Not running through Homebrew services |
| Existing database service | ServBay MySQL 9.4, PID 1181, port 3306 |
| Existing data directory | `/Applications/ServBay/db/mysql/9.4` |
| Temporary storage | Approximately 5.1 GiB available at discovery; temporary fixture used only a disposable directory |
| Project harness | Present; intentionally non-executing and fail-closed |

The running ServBay instance is an existing installed database service. It was
not started, stopped, restarted, queried, or used for this assignment. The
project `storage/db_config.php` path was observed as a configuration filename
only; its contents and credentials were not read.

## Selected isolation strategy

The safe available strategy was an existing Homebrew MySQL 9.6.0 server binary
launched as a separate disposable instance with:

- a fresh temporary datadir created by `mktemp`;
- `--no-defaults`, preventing inherited MySQL configuration;
- a unique Unix socket inside the temporary directory;
- `--skip-networking`, so no TCP listener or production network path existed;
- explicit Homebrew `--basedir` and temporary `--datadir`;
- a temporary test database and generated non-privileged test user;
- no installed application credentials or installed data mounts.

No software was downloaded or installed.

## Isolation proof

Before SQL execution, the disposable process was verified to:

1. use the fresh temporary datadir;
2. contain no ServBay datadir path in its command line;
3. run with `--skip-networking`;
4. expose only the unique temporary Unix socket;
5. have no TCP or UDP listener;
6. use a temporary database identity and generated test-only credentials;
7. leave the existing ServBay PID 1181 and port 3306 as the separately observed
   installed service.

The instance was initialized with `--initialize-insecure` only inside the fresh
temporary datadir. Root access was used only on that new socket to create the
temporary test user; business tests used the generated non-privileged user.

## Fixture and transaction results

The exact repository fixture was loaded:

`scripts/testing/disposable_db_fixture.sql`

Results:

| Check | Result |
|---|---|
| MySQL engine/version | MySQL 9.6.0 Homebrew |
| Fixture load | PASS |
| InnoDB committed insert | PASS; committed row count `1` |
| InnoDB rollback | PASS; rolled-back row count `0` |
| Foreign-key rejection | PASS; invalid child insert exited nonzero (`1`) |
| Network isolation | PASS; `--skip-networking`, no TCP/UDP listener |
| Installed service preservation | PASS; PID 1181 and port 3306 unchanged |

These results prove fixture-level commit, rollback, and foreign-key behavior in
the disposable instance only. They do not prove Procurement or Manufacturing
workflow atomicity.

## Harness compatibility

The existing safety harness was run with a matching synthetic proof packet for
the disposable identity. It returned the required blocked result:

```text
exit=2
execution_authorized=false
teardown_authorized=false
execution_performed=false
sql_executed=false
installed_database_touched=false
```

The harness remains a proof validator, not an execution authorizer. No safety
guard was weakened. The actual fixture runner was a bounded, temporary shell
invocation using the explicitly isolated socket and was not added to the
repository.

## Cleanup verification

The disposable MySQL process was shut down through its own temporary socket.
The temporary PID, socket, datadir, proof file, logs, database, and test user
were removed with a path-bounded cleanup. Post-cleanup checks confirmed:

- disposable process no longer existed;
- temporary socket and datadir no longer existed;
- ServBay PID 1181 remained alive;
- ServBay port 3306 remained listening;
- no installed database data or application state was modified.

An initial wrapper run stopped after a local post-cleanup assertion was ordered
before its trap cleanup; its trap removed the temporary instance. The subsequent
authoritative run used corrected teardown ordering and passed all checks above.

## Verification commands

```text
php scripts/testing/probe_disposable_db_harness_safety.php
php -l <affected PHP files>
git diff --check
```

The disposable transaction smoke tests were executed in the bounded temporary
runner described above. No aggregate architecture gate or deployment command
was rerun because no repository implementation changed.

## Remaining blockers

- Procurement rollback, duplicate, and concurrency verification still requires
  OH-AUTO1-029 against this or a newly attested disposable instance.
- Manufacturing target/link consistency verification remains OH-AUTO1-030.
- `SEC-AUTH-001` and `LIFECYCLE-003` remain Core-owned blockers.
- Release attestation, installed build identity, migration semantics, and live
  acceptance remain unresolved.

## Recommended next assignment

OH-AUTO1-029 — Execute the bounded Procurement transaction verification against
an independently recreated and attested disposable database identity. The test
must not use the installed ServBay service or application credentials.
