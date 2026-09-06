# CSS Token Editor Source-Mode Migration Audit (2026-06-05)

## Scope
Prompt 1 migration for Studio CSS Token Editor from runtime-artifact editing semantics to source-layer editing semantics.

## Old Behavior
- UI copy and warnings described direct writes to theme.css.
- Frontend selector refresh polled runtime CSS text and parsed blocks client-side.
- Save/verify payloads did not include source file metadata.
- Service flow was partially aligned and needed end-to-end wiring.

## New Behavior
- CTE now operates as source-mode editor for selectors discovered from resources/themes/**.
- Frontend refresh uses read-only source snapshot endpoint:
  - GET /apps/studio/tools/css-token-editor/source-snapshot
- Save payload carries source identity metadata:
  - source_id
  - source_path
- Service save flow:
  1. Validate source_path under resources/themes/**
  2. Read source file
  3. Apply token changes to selected selector block
  4. Create source-scoped backup in storage/css_token_editor_backups/
  5. Compile runtime artifact via scripts/assets/compile_theme_sources.php --apply --json
  6. Verify runtime artifact includes changed token values
- Verify flow now reads from source file using source_path.

## Files Changed
- apps/Studio/Tools/CssTokenEditor/Services/CssTokenEditorSaveService.php
- apps/Studio/Controllers/StudioController.php
- apps/Studio/routes.php
- apps/Studio/Tools/CssTokenEditor/Views/preview.php
- apps/Studio/Tools/CssTokenEditor/assets/css_token_editor.js
- apps/Studio/Tools/CssTokenEditor/lang/en.php
- apps/Studio/Tools/CssTokenEditor/lang/ja.php
- apps/Studio/Tools/CssTokenEditor/lang/ne.php
- apps/Studio/Tools/CssTokenEditor/manifest.php
- scripts/architecture/check_cte_safety.sh
- docs/architecture/css-token-editor-safety-checkpoint.md

## UX/Contract Changes
- Summary panel now shows selected source file and source layer (Foundation/Semantic/Variant).
- Legacy copy "Writes to theme.css" replaced with source-write + compile semantics.
- Fetch warning reframed to source snapshot failure semantics.

## Validation Evidence
- PHP lint: PASS (all touched PHP files)
- JS syntax: PASS (css_token_editor.js)
- git diff --check: PASS
- Theme compile: PASS (unchanged=true, ok=true)
- CTE safety gate: PASS (13 invariants)
- Full architecture gates: PASS (arch_exit=0)
- Deployment readiness: PASS (deploy_exit=0)

## Remaining Debt / Follow-up
- Legacy theme.css-specific error keys remain in locale packs for backward compatibility, though source-mode keys now drive active flows.
- This slice does not redesign CTE visual layout; it focuses on ownership, payload, and save/compile integrity.
