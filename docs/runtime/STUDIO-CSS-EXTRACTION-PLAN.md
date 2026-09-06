# Studio CSS Extraction Plan (Docs-Only)

Date: 2026-05-24
Status: Planning only. No CSS movement in this change.
Scope: Studio-owned inline CSS planning for `apps/Studio/Views/gui_studio.php`.

## Purpose

This plan defines a safe, no-behavior-change path to extract remaining Studio-owned inline CSS from `apps/Studio/Views/gui_studio.php`.

This is a documentation checkpoint only.
No CSS is moved in this slice.
No runtime logic is changed in this slice.
No route, controller, DB, permission, Shell, or Core change is included.

## 1. Current Problem

- `apps/Studio/Views/gui_studio.php` still contains a large inline Studio-owned CSS block.
- Markup extraction and major JS extraction slices are already completed.
- CSS extraction should now be planned as an independent phase before any movement.

## 2. Ownership Rule

- CSS in scope is Studio-owned only.
- Do not move Studio page CSS into Shell CSS, Core CSS, or global platform CSS in this phase.
- Do not create new shared platform theme tokens in this phase.
- Preserve existing token usage exactly as it is today.

## 3. Candidate Target Options

### Option A (Preferred First)

- Target: `apps/Studio/Views/partials/studio_styles.php`
- Why: safest first step because it preserves inline loading behavior and avoids asset pipeline changes.
- Risk profile: lowest for behavior parity.

### Option B (Later)

- Target: `apps/Studio/assets/css/gui-studio.css`
- Why: cleaner long-term ownership shape after stability is proven.
- Condition: only after verifying Studio-owned asset loading pattern can remain local without Shell/Core changes.

## 4. Recommended First Extraction

- Extract only the inline `<style>` block from `apps/Studio/Views/gui_studio.php` into:
  - `apps/Studio/Views/partials/studio_styles.php`
- Include the partial from `apps/Studio/Views/gui_studio.php` at the exact same render location.
- Keep all selectors unchanged.
- Keep token references unchanged.
- Keep rendering order unchanged.
- No visual redesign.

## 5. Preserve Selector Contracts

Preserve all existing selector contracts exactly, including:

- all `.gs-*` classes
- `.studio-*` classes owned by Studio page
- `.library-*` classes used by Studio library
- `.legacy-studio` compatibility selectors
- any selectors currently touched by Studio JS assets

## 6. No-Move Rules

- no Shell CSS movement
- no Core CSS movement
- no app-wide CSS movement
- no business app CSS movement
- no token redesign
- no selector rename
- no layout redesign
- no JS behavior change

## 7. Validation Plan (For Future Implementation Slice)

When implementing the extraction (not in this docs slice), run:

- `php -l apps/Studio/Views/gui_studio.php`
- `php -l apps/Studio/Views/partials/studio_styles.php`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- `git diff --check`
- Browser visual smoke (authenticated):
  - `/apps/studio`
  - `/apps/studio/library`
  - `/apps/studio/history`
  - `/apps/studio/library/module?app_key=inventory_app&module_key=parts_master`

Parity requirements:

- no visual break
- no console fatal
- no raw locale keys

## 8. Future Later Phase

After the style-partial extraction is stable and parity is confirmed, consider moving to a real CSS asset:

- `apps/Studio/assets/css/gui-studio.css`

Only proceed if the Studio-owned asset loading path remains local and does not require Shell/Core changes.

## Guardrails

- Localization remains paused.
- Backend wiring remains paused.
- Do not move governance/apply/rollback JS in this CSS plan phase.
- Do not alter runtime behavior.

## Recommended Next Implementation Prompt

"Implement Option A from docs/runtime/STUDIO-CSS-EXTRACTION-PLAN.md by extracting the inline style block from apps/Studio/Views/gui_studio.php into apps/Studio/Views/partials/studio_styles.php and include it at the same location. Preserve all selectors/tokens/order. No behavior change. Run php lint, architecture gates, deployment readiness, git diff --check, and browser visual smoke on the four Studio routes."
