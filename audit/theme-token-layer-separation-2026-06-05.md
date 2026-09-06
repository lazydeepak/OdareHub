# Theme Token Layer Separation — Prompt 4

**Date**: 2026-06-05
**Phase**: 3 (Source Theme Hardening) — Prompt 4: Foundation vs Semantic Token Separation

## Scope

Separate context-neutral foundation primitives from intent-facing semantic tokens that were previously interleaved in `resources/themes/foundation.css`.

## Classification

Every token in the original `foundation.css` (161 tokens total) was classified as **foundation primitive** or **semantic**:

### Foundation Primitives (29 tokens, stay in `foundation.css`)

Context-neutral design values describing what a value IS, not what it means:

| Category | Tokens |
|---|---|
| Blur primitives | `--glass-blur`, `--glass-blur-shell`, `--glass-blur-card`, `--glass-blur-popover`, `--glass-blur-control` |
| Specular primitives | `--glass-specular-top`, `--glass-specular-bottom` |
| Motion/easing | `--transition` |
| Elevation | `--depth`, `--depth-lg` |
| Layout | `--topbar-height` |
| Color scheme | `--select-scheme` |
| Spacing scale | `--space-1` through `--space-5` |
| Safe area | `--safe-area-top`, `--safe-area-right`, `--safe-area-bottom`, `--safe-area-left` |
| Radius scale | `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-pill` |
| Focus/outline | `--focus-ring` |
| Typography | `--font-sans`, `--type-body-size`, `--type-control-size` |

### Semantic Tokens (132 tokens, moved to `semantic/semantic.css`)

Intent-facing tokens describing UI-layer meaning:

| Category | Tokens |
|---|---|
| Core surface/color | `--bg`, `--panel`, `--card`, `--text`, `--muted`, `--line`, `--accent`, `--style-bg` |
| Status colors | `--color-success-{bg,text,border}`, `--color-danger-{bg,text,border}`, `--color-warning-{bg,text,border}` |
| Glass surfaces | `--glass`, `--glass-strong`, `--glass-border`, `--glass-shadow`, `--glass-highlights`, `--glass-card-bg`, `--glass-card-bg-hover` |
| Page surfaces | `--page-bg`, `--topbar-bg`, `--sidebar-bg`, `--search-panel-bg` |
| Card surfaces | `--card-surface`, `--card-border-base`, `--card-edge-fallback` |
| Notification | `--notif-bell-badge-{bg,text}`, `--notif-divider`, `--notif-item-{border,bg,hover-border,hover-bg}`, `--notif-chip-{critical,warning,action,approval,info}-{color,border,bg}` |
| Scan | `--scan-overlay-bg`, `--scan-panel-{bg,border,shadow}`, `--scan-video-{wrap-bg,bg}` |
| Control sizing | `--control-height`, `--control-font-size`, `--control-line-height`, `--control-radius`, `--control-padding-{block,inline,inline-select,inline-date}`, `--control-gap`, `--control-gap-tight`, `--control-field-{min,wide-min,compact-min}` |
| Style surfaces | `--style-{shell-bg,shell-raised-bg,content-bg,content-bg-hover,subtle-bg,subtle-bg-hover,control-bg,control-bg-hover,control-bg-focus,button-bg,button-bg-hover,button-shadow,button-shadow-hover,border,border-soft,border-strong,active-bg,active-border,table-bg,table-head-bg,table-row-hover,card-shadow,surface-shadow}` |
| Control select | `--control-select-arrow` |
| Tone/status | `--tone-{info,success,warning,danger,neutral}-{text,border,bg}`, `--tone-{info,success,warning,danger}` |
| Color aliases | `--color-{background,surface,card,text,text-muted,border,accent}` |
| Card/chrome/icon | `--card-radius`, `--card-background`, `--card-border`, `--chrome-background`, `--chrome-raised-background`, `--chrome-border`, `--icon-chip-size`, `--icon-chip-radius`, `--icon-chip-background`, `--icon-chip-border` |

## Files Created

- `resources/themes/semantic/semantic.css` — 132 semantic tokens, preserving original declaration order from `foundation.css`

## Files Modified

| File | Change |
|---|---|
| `resources/themes/foundation.css` | Reduced from 161 to 29 tokens (primitives only) |
| `resources/themes/theme-manifest.json` | Added `semantic` source entry between `foundation` and `light` |

## Validation

| Check | Result |
|---|---|
| Token value parity (all 161 tokens vs git HEAD) | ✅ 0 mismatches |
| Token count: original | 161 |
| Token count: foundation (post) | 29 |
| Token count: semantic (new) | 132 |
| Combined count | 161 ✅ |
| No component selectors in theme sources | ✅ |
| `git diff --check` | ✅ |
| PHP lint (all changed files) | ✅ |
| Manifest JSON parse | ✅ |
| Compiler apply | ✅ (26706 bytes) |
| Deployment readiness | ✅ (all gates PASS) |
| Architecture gates (177 invariants) | ✅ (PASS) |
| Browser smoke (admin/studio/mfg) | ✅ 11/11 PASS |

## Strict Constraints Compliance

| Constraint | Status |
|---|---|
| No token renamed | ✅ |
| No token value changed | ✅ |
| No new theme concepts created | ✅ |
| No visual design modified | ✅ |
| No selectors reintroduced into theme sources | ✅ |
| ThemePreferenceService unchanged | ✅ |
| Studio tooling unchanged | ✅ |
| Compiler unchanged (manifest update only) | ✅ |
