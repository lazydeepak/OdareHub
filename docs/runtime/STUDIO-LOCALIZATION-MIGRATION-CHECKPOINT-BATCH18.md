# Studio Localization Migration Checkpoint After Batch 18

Date: 2026-05-24
Branch: main
Checkpoint HEAD: 7b4579e2dfa862564b5f8a07615451620a425f95
Scope: Documentation checkpoint only. No runtime, loader, route, database, permission, or business behavior changes.

## Migration Strategy

Studio localization continues in tiny verified slices:

1. Resolve migrated labels through global ops.gui_studio.* keys first.
2. Fall back to the existing inline Studio dictionaries second.
3. Keep app-scoped Studio language files deferred until a loader contract is approved.

Inline fallback dictionaries remain intentional migration safety, not a source of new runtime truth to expand.

## Completed Batches

Batch 1 through Batch 15 were recorded in the previous checkpoint at 60a61380.

- Batch 1 to Batch 15 summary: completed and verified, total 60 migrated keys.
- Batch 16 (34b08bae): planned/rollback/reversibility labels verified.
- Batch 17 (16cdb465): rollback execution labels verified.
- Batch 18 implementation (facb27d8): rollback execution UI labels migrated.
- Batch 18 corrective close (7b4579e2): completed missed visible callsite migration for rollback_execute_steps and rollback_execute_no_binding.

## Updated Migrated Key Count

- Total migrated global keys through Batch 15: 60
- Batch 16 added: 4
- Batch 17 added: 4
- Batch 18 added: 4
- Batch 18 corrective commit added keys: 0

Updated total migrated global keys through Batch 18: 72.

## Intentionally Touched Across Batches

- app/Locale/en.php
- app/Locale/ja.php
- app/Locale/ne.php
- apps/Studio/Views/gui_studio.php

## Intentionally Not Changed

- Helpers
- Localization loader
- Routes
- DB/migrations
- Permissions
- Business apps/modules

## Validation Pattern

Each implementation batch uses the same narrow validation pattern:

- PHP lint for changed locale/view files.
- ARCHITECTURE_GATE_ALLOW_CORE=1 bash scripts/architecture/run_architecture_gates.sh
- ARCHITECTURE_GATE_ALLOW_CORE=1 bash scripts/system/check_deployment_readiness.sh
- git diff --check
- Authenticated browser smoke checks for:
  - /apps/studio
  - /apps/studio/library
  - /apps/studio/history
  - /apps/studio/library/module?app_key=inventory_app&module_key=parts_master

Docs-only checkpoints use read-only architecture and deployment gates without changing runtime files.

## Known Remaining Debt

- Inline fallback dictionaries remain intentionally.
- Many inline Studio keys remain unmigrated.
- App-scoped Studio language files remain deferred until the loader contract is approved.
- Existing Shell CSS compatibility debt remains documented by architecture gates.
- Existing QR ownership/namespace debt remains documented by architecture gates.

## Recommended Next Candidates

For Batch 19, investigate exact key names and usage first, and stop if any candidate differs.

Recommended investigation-first slice:

- rollback_execute_all_non_reversible
- rollback_execute_non_reversible_warning
- rollback_execute_completed
- rollback_execute_failed

Do not implement Batch 19 from this checkpoint alone. Confirm exact keys and usages in apps/Studio/Views/gui_studio.php before any locale or view edit.

## Stop-Doing Guidance

- Do not migrate large batches.
- Do not remove inline fallbacks yet.
- Do not introduce app-scoped Studio language loader.
- Do not touch Core helpers/loaders for localization cleanup.
