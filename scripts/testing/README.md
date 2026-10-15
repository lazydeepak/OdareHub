# Disposable Database Test Harness

`disposable_db_harness.php` is the OH-AUTO1-022 safety gate for future database
behavioral probes. The current implementation is intentionally non-executing:
it validates an externally produced isolation proof packet and never connects to
MySQL/MariaDB or executes SQL.

## Executing probe boundary

Executable Procurement, Manufacturing, and Hospitality probes must pass
through `DisposableDbTestConfigurationGuard` before loading the application DB
layer. The guard is pure configuration validation: it does not connect or run
SQL. It requires an isolated temporary `APP_ROOT/storage/db_config.php` whose
host, port, database name, and test-only user exactly match the independently
attested endpoint. The repository's installed `storage/db_config.php` and
production-like database identities are rejected.

`run_business_integrity_regression.sh` validates the configuration identity and
the live process attestation before creating the disposable database/user. It
also fingerprints the temporary config before and after each probe. A missing,
contradictory, or changed identity fails closed.

The static regression is:

```text
php scripts/testing/probe_disposable_db_test_configuration_guard.php
```

This command creates only temporary PHP configuration fixtures; it does not
load the application DB class, connect to MySQL, or execute SQL.

## Required future proof packet

The provisioner must create a JSON file and provide these environment variables:

```text
OH_TEST_DB_PROOF_FILE=/path/to/proof.json
OH_TEST_DB_HOST=127.0.0.1
OH_TEST_DB_PORT=<ephemeral-port>
OH_TEST_DB_NAME=<unique-non-production-name>
OH_TEST_DB_USER=<test-only-user>
OH_TEST_DB_ISOLATION_TOKEN=<hex-proof-token>
OH_TEST_DB_PROVISIONER=docker-internal-network|ephemeral-vm|approved-local-test-instance
OH_TEST_DB_CREDENTIAL_SOURCE=container-secret|ephemeral-provisioner-secret|approved-secret-store
OH_TEST_DB_ATTESTATION_MODE=mock
OH_TEST_DB_RESOURCE_ID=oh-auto-022-<unique-resource-id>
```

The proof JSON must identify the same host/database and assert:

```json
{
  "disposable": true,
  "installed_database_access": false,
  "production_network_access": false,
  "production_storage_mount": false,
  "teardown_owned_only": true,
  "owned_by_harness": true,
  "host": "127.0.0.1",
  "database_name": "unique_test_name",
  "credential_source": "container-secret",
  "resource_id": "oh-auto-022-unique-resource-id"
}
```

Run the non-executing gate with:

```sh
php scripts/testing/disposable_db_harness.php
```

Exit `2` always means no execution authorization was issued. Even a structurally
valid synthetic proof returns `MOCK_PROOF_VALIDATED_BUT_EXECUTION_BLOCKED` and
cannot authorize a connection, fixture load, SQL mutation, or teardown. There
is intentionally no success exit from this validator.

## Required provisioning properties

- MySQL/MariaDB with InnoDB and a test-only user.
- Ephemeral container/VM or separately approved local test instance.
- No production mounts and no production network access.
- Unique disposable database/resource identity and teardown ownership.
- The fixture at `disposable_db_fixture.sql` must be loaded only after the gate passes.
- A later independently attested runner must prove commit, rollback, foreign-key rejection, connection
  isolation, and teardown before business probes are authorized.

The current workstation has Docker CLI but no reachable Docker daemon. For the
approved OH-AUTO1-068 qualification, the bounded
`run_business_integrity_regression.sh` runner uses the independently attested
Homebrew MySQL local-instance path when explicitly invoked; it stages a source
root, validates the configuration and process identity before SQL, and removes
only runner-owned disposable resources.

## Supplementary local authorizer

`DisposableDbExecutionAuthorizer.php` is a narrow, explicit runtime attestation
for a separately provisioned local MySQL process. It does not replace or weaken
`disposable_db_harness.php`. It accepts no environment-based proof and only
authorizes a caller-supplied PID/socket/datadir tuple after independently
checking the live process command, current-user ownership, temporary-path
containment, socket ownership, network isolation or loopback binding, and
server-reported datadir/socket/port identity.

Its safety regression probe is:

```sh
php scripts/testing/probe_disposable_db_execution_authorizer.php
```

Application probes must invoke the authorizer immediately before connecting;
they must never read the installed application DB configuration or use ServBay
credentials.
