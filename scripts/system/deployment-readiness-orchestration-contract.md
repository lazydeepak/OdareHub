# Deployment Readiness Orchestration Contract

`bash scripts/system/check_deployment_readiness.sh` is the preferred validation entrypoint for fresh clones, local development, shared hosting shells, and CI-like environments.

Deployment readiness is a governed validation orchestrator, not a runtime owner. It must not bypass Core, ACL, owner contracts, Studio approval/snapshot rules, migrations, or audit policy. Its generated CSS delivery remains a reproducible output, not source truth.

It must keep these stages in the readiness chain, in this order:

```bash
bash scripts/system/check_system_tools_inventory.sh
bash scripts/system/check_backfill_utility_aging.sh
php -l scripts/assets/publish_registered_css.php
php -l scripts/assets/compile_first_boot_css.php
php scripts/assets/publish_registered_css.php --apply
php scripts/assets/compile_first_boot_css.php --apply
bash scripts/architecture/run_architecture_gates.sh
git diff --check
git diff --quiet -- public/assets/apps
```

The order is intentional:

1. System Tool inventory validates the tool registry, shell/PHP conventions, documentation, and baseline contracts before any other readiness work.
2. Backfill utility aging confirms one-off backfill scripts remain outside the active System Tools path.
3. Both CSS publisher scripts are syntax checked before either publisher executes.
4. Registered app CSS assets are published from owner sources.
5. First-boot CSS assets are published from Shell/Platform owner sources before architecture validation.
6. Architecture gates run after generated delivery assets are current.
7. Diff hygiene runs after architecture gates to catch whitespace/path-level patch problems.
8. Generated asset cleanliness runs last to confirm readiness did not leave tracked public asset drift.

The readiness script contains a self-checking orchestration contract before executing the stages. If a required stage is removed, renamed, or reordered without an intentional contract update, readiness must fail before partial validation runs.

Purpose:

- Keep one preferred validation command.
- Prevent silent removal or reordering of required validation stages.
- Keep generated CSS delivery reproducible.
- Keep architecture gates in the standard readiness path.
- Avoid executable-bit dependency by invoking shell scripts through `bash`.

Expected final result:

```text
DEPLOYMENT READINESS: PASS
```
