# OH-AUTO1-054 — Non-Mutating Deployment Readiness Verification

Date: 2026-10-15
Status: **Complete**

## Side-effect inventory

The former default readiness path executed:

- `publish_registered_css.php --apply`, which copies owner CSS into
  `public/assets/apps/...` and may create delivery directories;
- `compile_first_boot_css.php --apply`, which writes first-boot assets and may
  compile `public/assets/theme.css`.

The default path now executes only `--verify` for both tools. It reads source,
manifest, and published targets; it does not write, create, delete, publish,
compile, modify manifests, regenerate caches, install, migrate, or activate
anything. Explicit publication remains available through
`check_deployment_readiness.sh --apply`.

## Verification contract

- Registered CSS `--verify` fails for missing or stale public targets and for
  structurally invalid owner CSS.
- First-boot CSS `--verify` fails for missing or stale generated targets and
  invalid source CSS.
- Current source/target fingerprints pass without mutation.
- Verify mode is mutually exclusive with `--apply`.

## Regression evidence

`scripts/assets/tests/probe_css_publication_readiness.php` uses copied tools
and a disposable fixture root. It records **10/10** passes covering current,
missing, stale, and invalid CSS cases for both publisher families, plus an
unchanged-fixture assertion after verification.

## Readiness evidence

- Default deployment readiness: **PASS (read-only verify mode)**
- Architecture gates: **49/49 PASS**
- Deletion-family gates: **28/28 PASS**
- Working tree before/after readiness: unchanged except the three preserved
  excluded artifacts.
- No database connection, SQL, migration, activation, deployment, or Core
  modification occurred.

The explicit `--apply` workflow was not executed in this assignment.
