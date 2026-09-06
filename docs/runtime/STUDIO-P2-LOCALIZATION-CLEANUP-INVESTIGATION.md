# STUDIO P2.2 Localization Cleanup Investigation (Docs-Only Checkpoint)

Date: 2026-05-23
Status: Investigation complete (read-only)
Scope: Documentation checkpoint only. No implementation changes.

## 1) Current Localization Model

Studio currently uses a mixed localization model:

- Global translation keys in `app/Locale/*.php`, including many `ops.gui_studio.*` entries.
- Inline dictionaries inside Studio views:
  - `apps/Studio/Views/gui_studio.php`
  - `apps/Studio/Views/gui_studio_history.php`
- Some route/controller title handling in Studio route/controller surfaces.

This means Studio text is not yet sourced from one canonical location.

## 2) Evidence That Active Translation Path Is Global

Read-only investigation confirmed active helper translation behavior resolves through global locale files:

- `app/Core/helpers.php` contains the primary language resolution and translation helper flow (`current_lang`, locale loading, `t`, `__`).
- Runtime helper path resolves locale files under `app/Locale/<lang>.php`.
- Existing controller usage already relies on global keys (for example Studio title usage through `t('ops.gui_studio.page_title')`).

Conclusion: active translation loading is globally rooted, not app-scoped for Studio.

## 3) Studio Mixed Localization Usage

Observed mixed usage across Studio surfaces:

- Global key usage exists for `ops.gui_studio.*` in `app/Locale/en.php` and corresponding locale files.
- Inline dictionaries remain in:
  - `apps/Studio/Views/gui_studio.php`
  - `apps/Studio/Views/gui_studio_history.php`
- Route/controller title behavior is partially key-based and partially local handling depending on surface.

This is functional today but increases drift risk.

## 4) Why App-Scoped Lang Files Are Not Safe Yet

Do not move Studio labels to `apps/Studio/lang` at this stage.

Reasoning:

- Active helper/render translation path is global (`app/Locale`) and does not currently provide a stable, approved app-scoped loader contract for Studio.
- Introducing app-scoped language files now would create parallel loading behavior and potential key resolution divergence.
- Partial migrations would likely produce fallback ambiguity and inconsistent UI language outcomes across Studio surfaces.

## 5) Session `lang` vs `locale` Inconsistency Risk

Investigation observed a session key mismatch risk across the codebase:

- Helper translation path expects `$_SESSION['lang']`.
- Some surfaces read `$_SESSION['locale']`.

Without a single enforced contract, localization behavior can differ by entry path or rendering surface.

## 6) Decision (Recorded)

Decision for P2.2 checkpoint:

- Keep in-view dictionaries for now.
- Do not move Studio labels to `apps/Studio/lang` yet.
- Treat this as a risk-control hold until loader ownership and language session contract are explicitly approved.

## 7) Recommended Future Migration Path (Safe Sequence)

Use a phased approach:

1. Inventory all Studio user-facing strings and map each to canonical keys.
2. Normalize Studio labels to global `ops.gui_studio.*` keys first.
3. Remove duplicated inline strings gradually with controlled fallbacks.
4. Only after architecture approval, evaluate whether Studio-owned lang files should be introduced under an explicit loader contract.

## 8) Unsafe Work (Do Not Do In P2.2 Cleanup)

- No new parallel localization loader.
- No Core/helper path changes for this cleanup slice.
- No partial migration without explicit fallback rules and validation matrix.

## 9) Validation Commands For Future Localization Slices

Run these before and after any future localization implementation slice:

```bash
/opt/homebrew/bin/php -l apps/Studio/Views/gui_studio.php
/opt/homebrew/bin/php -l apps/Studio/Views/gui_studio_history.php
/opt/homebrew/bin/php -l apps/Studio/Controllers/StudioController.php
/opt/homebrew/bin/php -l apps/Studio/Routes/gui_studio_routes.php
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
git diff --check
git status --short
```

## Non-Goals Confirmed For This Checkpoint

- No runtime code changes.
- No `/app` locale file edits.
- No helper/loader modifications.
- No route changes.
- No DB/migration changes.
- No permission changes.
- No business app/module behavior changes.
