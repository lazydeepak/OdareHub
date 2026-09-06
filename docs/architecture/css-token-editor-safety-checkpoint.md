# CSS Token Editor Safety Checkpoint

**Status**: Source-mode editing hardened — compile/publish flow active.
**Base commit**: `a468c325`
**Date**: 2026-05-31

> CSS Token Editor has passed the dangerous foundation stage. The three critical blank-input bugs are fixed, save authority stays server-side, and invariants are guarded by architecture gate `check_cte_safety.sh`.

## 1. Bug History (Fixed in `a468c325`)

| Bug | Root Cause | Fix | File:Line |
|---|---|---|---|
| Blank inputs after page load | `updateTokenStatus()` overwrote `tokenValues[name]` with stale DOM value on every `input` event, erasing correctly hydrated values | Removed `tokenValues[name] = input.value` from `updateTokenStatus()` — event listener already handles per-token write | `css_token_editor.js:254` (removed) |
| Source refresh mismatch | Runtime artifact polling could drift from source-layer ownership model | Source snapshot URL supplied from server template via `data-cte-source-snapshot-url`; JS refreshes selector data from source snapshot endpoint | `preview.php`, `css_token_editor.js` |
| Save fails with "selector_required" | `selectorField.value` was empty placeholder `""` on initial load; save handler read blank key | Added `selectorField.value = firstKey` in initial load block | `css_token_editor.js:642-644` |
| Save crashes on large CSS with `@supports` | PHP PCRE JIT stack exhaustion on 725-line CSS with nested braces in `@supports` blocks | Added `(*NO_JIT)` to `CssTokenEditorSaveService::parseSelectors()` regex | `CssTokenEditorSaveService.php:225` |

## 2. Critical Invariants (Must Never Regress)

These invariants are enforced by `scripts/architecture/check_cte_safety.sh`:

1. **Source snapshot URL must come from server attribute, not hardcoded path.**
   - JS: `SOURCE_SNAPSHOT_URL = workspace.getAttribute('data-cte-source-snapshot-url') ...`
   - PHP: `data-cte-source-snapshot-url` is emitted by `preview.php`
   - DO NOT hardcode source refresh URLs in JS.

2. **`updateTokenStatus()` must not write to `tokenValues`.**
   - The `input` event listener at line 518 (`tokenValues[tokenName] = target.value`) is the sole writer.
   - DO NOT add `tokenValues[name] = input.value` to `updateTokenStatus()`.

3. **`selectorField.value` must be initialized on page load.**
   - Line 642-644: `selectorField.value = firstKey` in the initial load block.
   - DO NOT remove this assignment.

4. **`(*NO_JIT)` must remain in save service regex.**
   - Line 225: `$pattern = '/(*NO_JIT)(?P<selector>...)...'`
   - DO NOT remove `(*NO_JIT)` even if it seems unnecessary for small CSS files.

5. **Save authority is server-side only.**
   - The save handler submits a POST form to `/apps/studio/tools/customization-studio/design-system/tokens/save`.
   - The server validates token existence, value safety, writes backup, applies diff, and writes file.
   - DO NOT add client-side file write, AJAX PUT/PATCH save without server round-trip, or `localStorage`/`sessionStorage` as save mechanism.
   - DO NOT bypass server validation.

6. **Browser smoke checklist (10 steps) must pass after any JS change.**
   - Embedded at the top of `css_token_editor.js` (lines 3–17).
   - Must be run manually or via Playwright automation before committing JS changes.

## 3. Behavior Contract

CSS Token Editor is intentionally two tools in one page:

### Simple Mode

- Answers: “What part of the UI do I want to change?”
- Shows intent-first labels such as page background, card/panel background, main text, secondary text, accent/primary color, borders, controls, roundness, spacing density, and status colors.
- Hides raw CSS mechanics unless they are needed to understand the change.
- Does not require the user to understand `:root`, selector blocks, `data-theme`, `data-color-style`, `var()` chains, inheritance paths, or duplicate aliases.
- Never shows blank editable fields for inherited or unavailable values.
- Uses Live Preview as the primary confidence tool.
- Uses pre-save safety checks to protect readability and contrast.

### Developer Mode

- Answers: “Which exact CSS token and selector block am I editing?”
- Shows selector metadata, raw token names, defined values, inherited/resolved values, source selectors, `var()` chains, category labels, editable vs inherited state, and validation state.
- May expose exact text inputs and diagnostic details that Simple Mode hides.
- May allow “Save anyway” when a severe warning is present, but only with explicit confirmation.

### Theme And Preview Isolation

- Avatar/account theme controls the real application theme.
- The CSS block browser discovers enabled source files from `resources/themes/theme-manifest.json` and eligible theme CSS files under `resources/themes/**`.
- Browser groups and labels come from source file identity; explicitly disabled manifest sources must not appear.
- The CSS Token Editor “Theme CSS Block” selector changes only the block being edited.
- Selecting a block must not change the live app theme.
- The selected block must restyle only the isolated Live Preview area.
- It must not restyle Studio chrome, sidebar, topbar, workspace, or editor controls.

### Pre-Save Visibility Safety

Before Save, the editor must check visibility and contrast at minimum for:

- main text vs page/card background
- muted text vs background
- button text vs button background
- status text vs success/warning/danger/info backgrounds
- border visibility where it matters
- focus ring visibility
- active/selected state distinguishability
- glass/transparent surfaces where text can become unreadable

Save behavior:

- No issue: save normally.
- Warning issue: show a warning and allow confirmation.
- Severe issue: protect Simple Mode users by blocking save or requiring strong confirmation.
- Developer Mode may allow “Save anyway” with an explicit warning.

Current runtime behavior:

- Save-time visibility analysis is enforced in the editor UI and server-side save service.
- Severe issues block Simple Mode saves.
- Developer Mode may save anyway only after explicit confirmation and an override flag.
- The analysis covers the current selected block plus inherited cascade values available to the editor.

## 4. Architecture Boundaries Preserved

| Boundary | Status |
|---|---|
| CTE is a Studio tool under `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/` | ✅ Confirmed |
| Theme browser discovery follows `resources/themes/**` source files | ✅ Confirmed |
| Server-side save with backup | ✅ Confirmed |
| No Core changes for CTE functionality | ✅ Confirmed |
| Save bypasses Shell/Platform composition | ✅ Confirmed |
| Runtime delivery remains compiled `public/assets/theme.css`; source of truth is `resources/themes/**` | ✅ Confirmed |
| No new database tables or migrations for CTE | ✅ Confirmed |

## 4. Intentionally Postponed

1. **Multi-file bulk editing** — The browser scans and groups `resources/themes/**`, and save writes selected source file only. Cross-file bulk edits are not enabled.
2. **Bulk token editing** — No "edit all" or "find-replace across selectors."
3. **Undo/redo** — No in-session undo stack.
4. **Visual preview** — The current preview renders raw CSS text, not a live-styled iframe or rendered page.
5. **Token import/export** — No JSON/CSV import or export of token values.
6. **Token dependency graph** — No visualization of which tokens reference others via `var()`.
7. **Auto-complete / suggestion** — No CSS value autocomplete or color picker.
8. **Role-based permissions** — No per-role read/write restrictions on CTE access.
9. **Multi-user conflict detection** — No awareness of concurrent edits.
10. **Scheduled/reversible saves** — No staged saves, review-before-apply, or automatic rollback.

## 5. Key Files

| File | Role |
|---|---|
| `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.js` | Frontend logic: fetch, parse, render, diff, save |
| `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/assets/css_token_editor.css` | Frontend styling |
| `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Views/preview.php` | Server template: source snapshot URL + i18n embedding |
| `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/Services/CssTokenEditorSaveService.php` | Server-side save: parse, validate, backup, source write, compile, runtime verify |
| `apps/Studio/Controllers/StudioController.php` | Route handler: read selectors, dispatch save |
| `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/lang/{en,ja,ne}.php` | Localization |
| `resources/themes/theme-manifest.json` | Enabled/disabled source discovery policy for the browser |
| `resources/themes/**/*.css` | Theme browser source files |
| `public/assets/theme.css` | Runtime compiled artifact (delivery only) |
| `storage/css_token_editor_backups/` | Auto-generated backup directory on each save |

## 6. Smoke Test Checklist

After any JS change, run the 10-step browser smoke test embedded at the top of `css_token_editor.js`:

1. Open `/apps/studio/tools/customization-studio/design-system/tokens`
2. Select Foundation / Global Defaults (`:root::1`)
3. Confirm token values load from source snapshot endpoint
4. Change one harmless token and save
5. Confirm save success and source backup path appears
6. Confirm compiled `/assets/theme.css` reflects the saved token value
7. Confirm summary shows source file and source layer (Foundation/Semantic/Variant)
8. Confirm legacy copy `Writes to theme.css` is not visible
9. Confirm `semantic.semantic` options are not present in theme selectors
10. Confirm source snapshot warning appears if source snapshot endpoint is unreachable

## 7. Usability Features (as of `a468c325`)

- Search highlighting (`.is-search-match` class on matching rows)
- Group change badges (`.is-changed` count badge)
- "Changed only" filter pill
- Collapsible diff panel (auto-hide when clean)
- Save button auto-disable (disabled + `.is-disabled` when `changedCount === 0`)
- Auto-expand groups with modified tokens
- Localization: en/ja/ne
