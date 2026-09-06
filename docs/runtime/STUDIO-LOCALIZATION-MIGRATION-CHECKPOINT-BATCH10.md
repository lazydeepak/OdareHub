# Studio Localization Migration Checkpoint After Batch 10

Date: 2026-05-24
Branch: `main`
Checkpoint HEAD: `62119d1c2ae8df9c9f872101d6586e962f6303fa`
Scope: Documentation checkpoint only. No runtime, loader, route, database, permission, or business behavior changes.

## Migration Strategy

Studio localization is moving in tiny verified slices:

1. Resolve migrated labels through global `ops.gui_studio.*` keys first.
2. Fall back to the existing inline Studio dictionaries second.
3. Keep app-scoped Studio language files deferred until a loader contract is approved.

Inline fallback dictionaries remain intentional migration safety, not duplicate runtime truth to expand.

## Completed Batches

| Batch | Commit | Key Group | Keys | Verification |
|---|---|---|---:|---|
| Inventory | `34255c3b` | Docs-only Studio localization inventory | 0 | Complete |
| 1 | `c550cf9f` | View fallback wiring for global-first lookup | 0 | PASS |
| 1b | `27d10c02` | Studio tools, loaded identity title, workflow/mode panel titles, clear loaded context | 7 | PASS via Batch 1c |
| 2 | `0966a55a` | Loaded identity labels | 5 | Verified |
| 3 | `60e3037b` | Loaded context/type labels | 3 | Verified |
| 4 | `fa7f7c98` | Loaded source labels | 3 | Verified |
| 5 | `fce7edb0` | Loaded helper/read-only/unknown labels | 3 | Verified |
| 6 | `296df09c` | Workflow Status stage labels | 5 | Verified |
| 7 | `8510c58f` | Workflow Status helper and inactive messages | 4 | Verified |
| 8 | `ac09ce0d` | Workflow Status inactive/read-only messages | 4 | Verified |
| 9 | `98cc9a75` | Mode panel and workflow inactive labels | 4 | Verified |
| 10 | `62119d1c` | Remaining Mode panel labels | 4 | Verified |

Total migrated global keys so far: **42**.

## Intentionally Touched Across Batches

- `app/Locale/en.php`
- `app/Locale/ja.php`
- `app/Locale/ne.php`
- `apps/Studio/Views/gui_studio.php`

`apps/Studio/Views/gui_studio_history.php` was touched only for Batch 1 fallback wiring, not for Batch 2 through Batch 10 label migrations.

## Intentionally Not Changed

- Helpers
- Localization loader
- Routes
- DB/migrations
- Permissions
- Business apps/modules

## Validation Pattern

Each implementation batch used the same narrow validation pattern:

- PHP lint for changed locale/view files.
- `ARCHITECTURE_GATE_ALLOW_CORE=1 bash scripts/architecture/run_architecture_gates.sh`
- `ARCHITECTURE_GATE_ALLOW_CORE=1 bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- Authenticated browser smoke checks for:
  - `/apps/studio`
  - `/apps/studio/library`
  - `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

Docs-only checkpoints use the normal read-only validation gates without the Core override.

## Known Remaining Debt

- Inline fallback dictionaries remain intentionally.
- Many inline Studio keys remain unmigrated.
- App-scoped Studio language files remain deferred until the loader contract is approved.
- Existing Shell CSS compatibility debt remains documented by architecture gates.
- Existing QR ownership/namespace debt remains documented by architecture gates.

## Recommended Next Candidates

Batch 11 should investigate exact inline key names first and stop if any candidate differs.

Possible tiny Batch 11 candidates:

- `mode_state_not_active`
- `mode_state_requires_governance`
- `mode_state_context_available`

Do not implement Batch 11 from this checkpoint alone. Confirm exact keys and usages in `apps/Studio/Views/gui_studio.php` before any locale or view edit.

## Stop-Doing Guidance

- Do not migrate large batches.
- Do not remove inline fallbacks yet.
- Do not introduce an app-scoped Studio language loader.
- Do not touch Core helpers/loaders for localization cleanup.
- Do not migrate more labels during checkpoint or verification tasks.
