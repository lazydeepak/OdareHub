# Batch 21 First GUI Studio Passive CSS Extraction

Status: implementation slice complete.

Authority:

- `docs/migration-cleanup/maps/batch-20-gui-studio-css-js-separation-plan.md`

## Objective

Execute the first no-behavior-change GUI Studio CSS extraction by moving exactly
one passive inline style block from `apps/Studio/Views/gui_studio.php` into a
Studio-owned CSS source asset.

## Candidate And Anchor

- Extraction candidate (from Batch 20): the single inline `<style>` block
  classified as `INLINE_STYLE`, `PASSIVE_STYLE`, `EXTRACT_CANDIDATE`.
- Previous anchor in `gui_studio.php`: opening `<style>` at line ~2925 and
  closing `</style>` at line ~3686.

## Target Asset Path

- Studio-owned CSS source path: `apps/Studio/styles/gui_studio.css`
- Loaded in same page context via:
  `/assets/apps/studio/styles/gui_studio.css`

## What Changed

- Moved the inline style block content as-is into
  `apps/Studio/styles/gui_studio.css`.
- Replaced inline `<style>...</style>` in `apps/Studio/Views/gui_studio.php`
  with:

```html
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
```

## Why This Is No-Behavior-Change

- CSS only; no JavaScript blocks were moved or edited.
- No route, service, controller, or Core changes.
- Selector set, declaration bodies, declaration order, and cascade order were
  preserved by transferring the block content verbatim.
- Stylesheet is loaded from the same Studio page surface and anchor location.

## Batch 21 Validation

- `php -l apps/Studio/Views/gui_studio.php`
- `git diff --check`
- `bash scripts/architecture/check_gui_studio_css_js_separation_plan.sh`
- `bash scripts/architecture/check_gui_studio_view_separation_plan.sh`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`