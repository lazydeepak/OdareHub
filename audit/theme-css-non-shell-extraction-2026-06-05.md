# Theme CSS Non-Shell Selector Extraction — Prompt 3

**Date**: 2026-06-05
**Phase**: 2 (Selector Extraction) — Prompt 3: Remaining non-Shell selectors

## Scope

Extract all remaining component selectors from `resources/themes/dark.css` and `resources/themes/liquid-glass.css` that are owned by non-Shell apps/modules (Manufacturing, Base plugin), and remove dead CSS.

## Source Files Before

| File | Lines | Non-token content |
|---|---|---|
| `resources/themes/dark.css` | 98 | `.menu .group`, `.coverage-kpi`, `.coverage-trend-wrap`, `.mfg-subgroup-card`, `.sc-card`, `.ai-*` (dark block) |
| `resources/themes/liquid-glass.css` | 139 | Selector block 1: backdrop-filter (21 selectors); Selector block 2: border-color (18 selectors) |

## Extraction Decisions

| Selector(s) | Owner | Destination CSS | Rationale |
|---|---|---|---|
| `.coverage-kpi`, `.coverage-trend-wrap` | Manufacturing/Coverage | `apps/Manufacturing/modules/Coverage/styles.css` | Coverage module-specific components |
| `.mfg-subgroup-card` | Manufacturing | `apps/Manufacturing/styles/manufacturing.css` | Manufacturing-level component |
| `.menu .group`, `.ai-kpi`, `.ai-item`, `.ai-details`, `.ai-table-wrap` | Base plugin → Shell | `apps/Shell/styles/shell.css` | Base plugin has no CSS loading mechanism; these are shared Shell UI primitives with base definitions in `shell.css` / `components.css` |
| `.sc-card` | **Dead CSS** | Removed | Zero PHP template matches anywhere in codebase |
| `.app-quicklink-card` | Shell (kept) | Stayed in Shell CSS | Rendered by SBAIO and Base; class is part of Shell's glass-card group alongside `.dashboard-link-card`, `.widget-card`, etc. |
| `.topbar`, `.layout-sidebar`, `.sidebar`, `.card`, `.dashboard-link-card`, `.widget-card`, `.hero-meta-card`, `.snapshot-card`, `.status-item`, `.workflow-step`, `.table-wrap`, `.btn`, `.sidebar-link`, `.sidebar-overflow-toggle`, `.topbar-search-result`, `input`, `select`, `textarea` | Shell | Pre-existing in `shell.css` (Prompt 1/2) | Shell structural/shared UI |

### Verdict on `.app-quicklink-card`

- Rendered in: `apps/SBAIO/Views/dashboard.php:56`, `plugins/Base/Views/ops/role_dashboard.php:184,297`
- Base definition in: `apps/Shell/styles/components.css:445-452` (glass card group)
- No `mfg-`, `sbaio-`, or platform prefix
- ✅ Retained in Shell CSS as shared component class

## Files Modified

| File | Change |
|---|---|
| `resources/themes/dark.css` | Removed dark selector block (`.menu .group`, `.coverage-kpi`, `.coverage-trend-wrap`, `.mfg-subgroup-card`, `.sc-card`, `.ai-*`); now tokens-only (59 lines) |
| `resources/themes/liquid-glass.css` | Removed backdrop-filter and border-color selector blocks; now tokens-only (92 lines) |
| `apps/Shell/styles/shell.css` | Added `.menu .group`, `.ai-*` to existing dark backdrop-filter, liquid-glass backdrop-filter, and liquid-glass border-color blocks |
| `apps/Manufacturing/styles/manufacturing.css` | Added `.mfg-subgroup-card` dark + liquid-glass theme variants |
| `apps/Manufacturing/modules/Coverage/styles.css` | Added `.coverage-kpi`, `.coverage-trend-wrap` dark + liquid-glass theme variants |

## Validation

| Check | Result |
|---|---|
| `git diff --check` | ✅ |
| PHP lint (all 5 PHP/CSS files) | ✅ |
| `public/assets/theme.css` selectors | **0** across all 9 extracted patterns |
| CSS publish | ✅ (shell.css, manufacturing.css, Coverage/styles.css) |
| `compile_theme_sources.php --apply` | ✅ (26523 bytes, applied=true) |
| Deployment readiness | ✅ (all gates PASS) |
| Architecture gates (177 invariants) | ✅ (PASS) |

## Residual Warnings

None. All 5 theme source files are now tokens-only.

## Source Files After

| File | Lines | Content |
|---|---|---|
| `resources/themes/foundation.css` | 2660 | Tokens only |
| `resources/themes/light.css` | 102 | Tokens only |
| `resources/themes/dark.css` | 59 | Tokens only |
| `resources/themes/liquid-glass.css` | 92 | Tokens only |
| `resources/themes/paper.css` | 68 | Tokens only |
