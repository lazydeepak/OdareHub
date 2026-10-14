# OH-AUTO1-022 — Disposable Database Test Infrastructure

Date: 2026-10-14
Status: **Safety harness complete; real database provisioning blocked**

## Isolation assessment

The repository uses MySQL/MariaDB-compatible `mysqli` with InnoDB tables. The
workstation has the Docker CLI and MySQL client, but the Docker daemon is not
reachable at:

```text
unix:///Users/lazydeepak/.docker/run/docker.sock
```

No MySQL/MariaDB test container or independent test instance was available. No
database connection was attempted. No installed database credentials were
read or used, and no production/shared storage was mounted.

## Safe implementation delivered

### `scripts/testing/disposable_db_harness.php`

Non-executing, fail-closed proof validator. It rejects or blocks when:

- test identity variables are missing;
- host is not an allowlisted loopback host;
- database name resembles the installed/production database;
- user is a privileged or non-test identity;
- isolation token, provisioner, or credential source is invalid;
- proof JSON is missing, mismatched, or asserts production access/storage;
- the known fixture is missing or contains destructive DDL.

It explicitly reports:

```json
{
  "execution_performed": false,
  "sql_executed": false,
  "installed_database_touched": false
}
```

### `scripts/testing/disposable_db_fixture.sql`

Minimal InnoDB parent/child schema with a foreign key and unique markers. It is
a contract only and was not executed.

### `scripts/testing/README.md`

Documents required proof variables, proof JSON, accepted provisioning models,
and the future execution requirements.

## Verification results

- PHP lint: **PASS**.
- Missing-proof safety run: **BLOCKED**, expected exit code `2`.
- Synthetic proof-only run: **MOCK_PROOF_VALIDATED_BUT_EXECUTION_BLOCKED**.
- Synthetic run executed SQL: **No**.
- Fixture destructive-DDL check: **PASS**.
- `git diff --check`: **PASS**.
- Core files changed: **No**.
- Database connections/mutations: **None**.
- Docker daemon: **Unavailable**.

The synthetic proof validates parser/guard logic only and never authorizes a
future runner. It does not prove engine
version, connection isolation, transaction commit, rollback, constraints, or
teardown.

## Required provisioning before real transactional verification

1. Start an ephemeral MySQL/MariaDB container or separately approved local test
   instance with InnoDB enabled.
2. Use a unique database and test-only non-root user.
3. Place the database and test runner on an internal-only network with no
   production network or storage access.
4. Produce proof JSON matching the harness identity, credential-source, and
   teardown assertions.
5. Run the harness and confirm that synthetic proof remains blocked; a separate
   independently attested runner must own any execution authorization.
6. Add a separate noninteractive runner that loads the fixture and proves:
   successful commit, controlled rollback, foreign-key rejection, connection
   isolation, and teardown limited to harness-owned resources.

## Recommended next assignment

Provision the isolated engine outside the repository or start an approved local
container runtime, then execute real fixture verification before any
Procurement or Manufacturing business workflow is tested. Do not reuse
`storage/db_config.php`, `erp_local`, or any shared database server identity.
