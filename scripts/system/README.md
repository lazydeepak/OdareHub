# System Scripts

System scripts orchestrate operational validation and maintenance tasks. System Tools are governed maintenance/validation/diagnostic workers, not owners.

Rules:

- Do not edit Core, app source, manifests, routes, permissions, database rows, or runtime business logic from these scripts unless a separate approved maintenance task explicitly allows it.
- System Tools must not bypass Core, ACL, ownership boundaries, approvals, snapshots, migrations, or audit policy.
- System Tools must not become phpMyAdmin-style direct DB editors or uncontrolled admin shortcuts.
- Runtime must not consume temporary System Tool working state as source of truth.
- Studio authors and edits through governed workflows; System Tools validate, maintain, diagnose, publish reproducible delivery assets, and orchestrate readiness.
- Prefer calling existing focused tools instead of duplicating their checks.
- Generated delivery artifacts may be prepared when the source of truth remains owner-owned and reproducible.
- New repeatable System Tool entry points must be registered in `scripts/system/tools.registry.json`.
- Every registered System Tool path must also be documented in this README.
- The registry shape is closed; do not add new registry fields without updating the inventory validator and this README in the same change.

## Deployment Readiness

Run from the repository root:

```bash
bash scripts/system/check_deployment_readiness.sh
```

Portable local helper for Windows or environments without `bash`:

```bash
php scripts/system/check_deployment_readiness_portable.php
```

Use `bash ...` rather than direct execution so the command works even when the checkout does not preserve executable file mode.

The PHP helper delegates to the authoritative shell orchestrator when `bash` is available. When `bash` is unavailable, it runs the PHP/git subset needed for local setup portability and reports shell-only diagnostics as skipped.

The readiness script performs the standard fresh-clone/server validation sequence:

```bash
bash scripts/system/check_system_tools_inventory.sh
bash scripts/system/check_backfill_utility_aging.sh
php -l scripts/assets/publish_registered_css.php
php scripts/assets/publish_registered_css.php --apply
bash scripts/architecture/run_architecture_gates.sh
git diff --check
git diff --quiet -- public/assets/apps
```

Expected final result:

```text
DEPLOYMENT READINESS: PASS
```

This script may publish generated CSS delivery files under `public/assets/apps/...`; those files are ignored by Git and remain derived from app/module-owned source CSS.

The final generated asset cleanliness check verifies readiness did not leave tracked changes under:

```text
public/assets/apps
```

If generated public app assets change during readiness, the command fails and prints the changed filenames. This protects reproducible delivery assets from silent drift.

Companion notes:

- `scripts/system/deployment-readiness-portability.md`
- `scripts/system/deployment-readiness-orchestration-contract.md`

## Recovery Point Tool

Run preflight from the repository root:

```bash
php scripts/system/recovery_point.php preflight
```

Create and verify operations require an explicit operator-provided target directory:

```bash
php scripts/system/recovery_point.php create --target-dir=/path/to/recovery-point --reason=manual
php scripts/system/recovery_point.php verify --target-dir=/path/to/recovery-point
php scripts/system/recovery_point.php rehearse --target-dir=/path/to/recovery-point --isolated-dir=/path/to/empty-rehearsal-dir --rehearsal-db=susankhya_rehearsal
php scripts/system/recovery_point.php build-channel --channel-dir=storage/update-channels/local
php scripts/system/recovery_point.php preview-channel --channel-dir=storage/update-channels/local --recovery-dir=/path/to/recovery-point
php scripts/system/recovery_point.php prepare-apply --channel-dir=storage/update-channels/local --recovery-dir=/path/to/recovery-point --staging-dir=/path/to/empty-apply-staging-dir
```

The recovery tool is a governed recovery-point and local release readiness orchestrator. It must not create production recovery storage implicitly, replace controlled update apply, become a direct database editor, or bypass the backup/restore recovery contract. `prepare-apply` extracts the approved release package only into an explicit empty staging directory and records an apply plan; it does not mutate the live installation.

## Tool Registry

Structured System Tool discovery is recorded in:

```text
scripts/system/tools.registry.json
```

The registry is checked by:

```bash
bash scripts/system/check_system_tools_inventory.sh
```

The registry records only these top-level fields:

```text
schema
owner
source_of_truth
tools
```

Each tool entry records only these fields:

```text
key
path
kind
mode
preferred_entrypoint
description
```

Unexpected fields fail validation. This keeps the registry from becoming a hidden source of truth.

Keep the registry and this README aligned when a new repeatable system-level command becomes an approved entry point.

The inventory check enforces:

- registry schema, owner, source-of-truth, allowed kinds, and allowed modes
- no unexpected top-level registry fields
- no unexpected per-tool registry fields
- lowercase `snake_case` tool keys
- unique tool keys and unique tool paths
- registered tool paths ending only in `.sh` or `.php`
- locked baseline contracts for the current primary tools
- exactly one preferred entrypoint: `deployment_readiness`
- registered tool file existence
- registered tool syntax checks
- registered shell tool conventions
- registered PHP tool conventions
- Bash 3/macOS portability for registered shell tools
- registry drift detection for `scripts/system/*.sh`
- README documentation drift detection for every registered tool path
- readiness portability and orchestration companion-note checks

## Registered Tool Conventions

Registry keys must use lowercase `snake_case`:

```text
deployment_readiness
system_tools_inventory
```

Registered tool paths must be unique and must point to `.sh` or `.php` files under one of these approved tool roots:

```text
scripts/system/
scripts/architecture/
scripts/assets/
```

Registered shell tools must start with:

```bash
#!/bin/bash
set -euo pipefail
```

Registered shell tools must stay compatible with macOS default Bash 3. Avoid Bash 4-only features such as:

```text
mapfile
readarray
declare -A
declare -n
```

Registered PHP tools must start with:

```php
<?php
declare(strict_types=1);
```

## Backfill Utility Aging

Root-level scripts matching this pattern are treated as task-specific utilities, not active System Tools:

```text
scripts/backfill_*.php
```

The aging check discovers these scripts dynamically and verifies they are not part of deployment readiness, not listed as active System Tools in this README, and not registered in the System Tools registry.

## Active Tool Baseline

Current primary tool entry points:

- `scripts/system/check_deployment_readiness.sh`
- `scripts/system/check_deployment_readiness_portable.php`
- `scripts/system/check_system_tools_inventory.sh`
- `scripts/system/check_backfill_utility_aging.sh`
- `scripts/architecture/run_architecture_gates.sh`
- `scripts/assets/publish_registered_css.php`
- `scripts/assets/compile_first_boot_css.php`

## Baseline Tool Contract Lock

The current primary tools have locked registry contracts. Changing any `path`, `kind`, `mode`, or `preferred_entrypoint` value below requires updating the inventory validator and this README in the same change.

| Key | Path | Kind | Mode | Preferred |
|---|---|---|---|---|
| `deployment_readiness` | `scripts/system/check_deployment_readiness.sh` | `orchestrator` | `validation` | `true` |
| `deployment_readiness_portable` | `scripts/system/check_deployment_readiness_portable.php` | `orchestrator` | `validation` | `false` |
| `system_tools_inventory` | `scripts/system/check_system_tools_inventory.sh` | `diagnostic` | `read_only` | `false` |
| `backfill_utility_aging` | `scripts/system/check_backfill_utility_aging.sh` | `diagnostic` | `read_only` | `false` |
| `recovery_point` | `scripts/system/recovery_point.php` | `orchestrator` | `guarded_apply` | `false` |
| `architecture_gates` | `scripts/architecture/run_architecture_gates.sh` | `diagnostic_orchestrator` | `read_only` | `false` |
| `registered_css_publisher` | `scripts/assets/publish_registered_css.php` | `asset_publisher` | `guarded_apply` | `false` |
| `first_boot_css_compiler` | `scripts/assets/compile_first_boot_css.php` | `asset_publisher` | `guarded_apply` | `false` |

## Checkpoint

This milestone is complete and validated. Future work should continue with read-only diagnostics before adding new maintenance actions.
