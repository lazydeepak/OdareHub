# OH-AUTO1-045 — Isolated Test Execution Boundary Reconciliation

Date: 2026-10-14
Status: **READY for separately authorized disposable requalification; no
database execution performed**

## Entry-point discrepancy

The previous OH-AUTO1-043 report stated 24 entry points, while the source
authority and runner enumerate 25. The exact omitted path was:

`apps/Hospitality/Tests/probe_reservation_create_concurrency.php`

It was introduced during OH-AUTO1-041, is database-capable, and already had
the specialized `DisposableDbTestConfigurationGuard` plus
`DisposableDbExecutionAuthorizer` sequence. It was present in the governed
manifest but omitted from the earlier arithmetic count. No new database entry
point was created by OH-AUTO1-044/045, and no guard correction was required.

## Snapshot contents

The actual preparation manifest contained **5,271 files** after excluding
vendor test/documentation/example/tooling trees discovered during this audit.
The contents were:

| Root | Files | Approximate bytes |
|---|---:|---:|
| `app/` | 132 | 3.25 MB |
| `apps/` | 1,769 | 16.00 MB |
| `platform/` | 66 | 0.50 MB |
| `plugins/` | 128 | 1.84 MB |
| Composer metadata | 2 | 0.15 MB |
| `vendor/` production/dependency tree | 3,174 | 37.88 MB |

The 5,271 files are required by the current source-root contract: all
application/platform/plugin source, all 25 guarded probes, Composer metadata,
and installed dependency packages needed by the generated Composer autoloader.
The runner excludes repository storage, `storage/db_config.php`, `.env` files,
logs, database data, credential/key paths, symlinks, and vendor test/docs/
examples/tooling directories. The manifest records every staged file with a
SHA-256 digest.

## Dependency and path assessment

- Staged Composer autoload loaded successfully from the isolated root without
  loading application bootstrap.
- Composer autoload metadata contained no repository, `/Users/`, or temporary
  absolute path references.
- All staged file hashes matched the manifest.
- Source paths are copied only after realpath containment checks.
- Nested symlink fixtures are rejected; the repository currently contains no
  symlinks in the staged source/dependency roots.
- No installed configuration or runtime storage was staged.
- The isolated root contains an empty `storage/` directory but no database
  config; a future authorized runner must add an independently attested,
  disposable config there before entry-point authorization.

## Authority chain

The verified intended sequence is:

```text
isolated source preparation
  -> isolated config creation and configuration guard
  -> disposable process/socket/datadir attestation
  -> guarded entry point
  -> application bootstrap
  -> test execution
  -> configuration and owned-resource teardown checks
```

Preparation itself creates no process/socket/datadir authority and does not
invoke application bootstrap. Direct entry-point invocation without the later
authority evidence remains fail-closed.

## Verification

- Isolated-source synthetic regression: **17/17**
- Actual source preparation: **PASS**, 5,271 files
- Isolated runner-owned cleanup: **PASS**
- Staged Composer autoload-only check: **PASS**
- Configuration/entry-point regression: **63/63**
- Disposable authorizer safety: **9/9**
- Harness safety: **14/14**, no connection or SQL
- PHP lint: **PASS**
- Shell syntax: **PASS**
- Core lock: **PASS**
- `git diff --check`: **PASS**
- Integration inventory: **126/126 paths**, 123 candidate paths and 3 preserved
  excluded runtime/generated artifacts

No MySQL process, database connection, SQL, installed database access, or
database-capable application bootstrap was performed.

## Recommendation

**READY for a separately authorized OH-AUTO1-045/046 disposable database
requalification decision, but not self-authorized.** The next authorization
should begin with one harmless isolated test, require a staged disposable
`storage/db_config.php`, independently attested process/socket/datadir
identity, and retain the existing fail-closed entry-point and teardown checks.
