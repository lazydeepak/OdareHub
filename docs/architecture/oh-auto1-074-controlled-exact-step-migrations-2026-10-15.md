# OH-AUTO1-074 — Controlled Exact-Step Migrations

Date: 2026-10-15
Status: **Implemented and verified in an attested disposable database**
Scope: **REL-003 exact-step execution only; no release authority or apply enablement**

## Result

`AppMigrationService::executePlannedStep()` now executes one explicitly selected
SQL migration through the existing `AppSchemaSyncService`. Existing
`runMigrations()` behavior and interface are unchanged.

The new path requires an opaque `ReleaseMigrationPlanEvidence` object produced
by `ReleaseMigrationPlanValidator`. The validator checks an immutable ordered
plan, exact step identity, SHA-256 checksums, unique identities, and dependency
ordering. This is structural evidence only; it is not trusted release
authority, administrator approval, update eligibility, or installation
authority.

## Execution boundary

- Registered application owners are required through `core_apps`.
- Only an owner-relative `.sql` basename is accepted.
- Directory and file realpaths are confined; symlink paths and traversal fail
  closed.
- SQL bytes are hashed before any database execution.
- An identical applied ledger row is skipped without SQL.
- Applied checksum mismatches, failed rows, ambiguous rows, missing
  dependencies, and prior-plan contradictions block execution.
- Only the selected SQL is passed to `AppSchemaSyncService::runAdditiveSql()`.
- Applied ledger evidence is inserted only after SQL succeeds.
- SQL failure records `failed` evidence and returns
  `migration_recovery_required`; no rollback success is claimed and no retry is
  attempted.
- Ledger persistence failure after SQL returns
  `migration_state_ambiguous`.

No second migration table or SQL executor was introduced.

## Disposable A/B/A proof

The governed fixture executes:

```text
owner-a:001_a.sql -> owner-b:001_b.sql -> owner-a:002_a.sql
```

The proof verifies:

- A001 does not execute neighboring A002.
- B001 executes after A001 while crossing owners.
- A002 executes only after B001.
- Repeating an identical applied step is idempotent.
- Caller checksum/position contradictions are rejected.
- Failing SQL preserves a failed ledger row and blocks retry.
- Missing dependencies block execution.
- Unrecognized owners are rejected.

## Isolation evidence

Executed with `OH_TEST_ONLY=exact-step-migrations` through the existing
OH-AUTO1-068 runner:

- configuration guard: PASS
- live disposable MySQL authorizer: PASS (24 checks)
- exact-step assertions: **13/13**
- isolated source root and temporary configuration fingerprints unchanged
- installed MySQL service preserved
- disposable socket removed and runner workspace cleaned
- no installed database, production SQL, activation, identity update, release
  apply, or deployment occurred

Static source-root regression: **17/17**. Existing migration compatibility,
installed compatibility, and migration-governance fixtures: **16/16**,
**16/16**, and **18/18**.

## Remaining boundary

The validator does not authenticate a release coordinator or administrator
approval. Trusted release coordination, migration recovery policy, health
evidence, and isolated signed installation/update/recovery proof remain
separate prerequisites.
