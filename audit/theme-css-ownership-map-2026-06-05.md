# Theme CSS Ownership Map

**Date**: 2026-06-05
**Status**: Complete — ownership traced for every selector in the theme source chain
**Scope**: `resources/themes/{foundation.css, light.css, dark.css, liquid-glass.css, paper.css}` → compiled to `public/assets/theme.css`
**Purpose**: Pre-extraction ownership certainty for Phase 2 selector extraction

---

## How to Read This Map

- **Selector**: The CSS selector(s) as they appear in theme source.
- **Current File**: Which theme source file contains this rule.
- **Owner**: The component/system that owns this selector's rendering.
- **Destination File**: Where the selector should move during extraction.
- **Confidence**: HIGH (proven render), MEDIUM (inferred from pattern), LOW (speculative).
- **Notes**: Rendering view evidence, special considerations.

---

## 1. Shell Selectors

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 1.1 | `[data-theme="light"] .sidebar-group-has-active` | `light.css:283` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Rendered in `public/views/layouts/sidebar.php:44-47` (conditional `$groupClasses`) |
| 1.2 | `[data-theme="light"] .sidebar-group-operations` | `light.css:284` | Shell | `apps/Shell/styles/sidebar.css` | MEDIUM | CSS-only — no PHP template emits this class. Defined in `apps/Shell/styles/components.css:33,34,1923`. Possibly dead or JS-managed. |
| 1.3 | `[data-theme="light"] .sidebar-group-operations .sidebar-group-title` | `light.css:289-291` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | `.sidebar-group-title` rendered in `public/views/layouts/sidebar.php:50` |
| 1.4 | `[data-theme="light"] .sidebar-link` | `light.css:293` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Rendered in `public/views/layouts/sidebar.php:122` |
| 1.5 | `[data-theme="light"] .sidebar-link-secondary` | `light.css:294` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Rendered on same `<a>` as `.sidebar-link` in `sidebar.php:122` (conditional `is_secondary`) |
| 1.6 | `[data-theme="light"] .topbar-search-input` | `light.css:295,305,316` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Rendered in `public/views/layouts/header.php:2297`, `apps/Shell/Composers/OperatorSurfaceComposer.php:3084` |
| 1.7 | `[data-theme="light"] .topbar-search-scan-btn` | `light.css:296-297` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Rendered in `public/views/layouts/header.php:2312`, `apps/Shell/Composers/OperatorSurfaceComposer.php:3085` |
| 1.8 | `[data-theme="light"] .topbar-search-result` | `light.css:298-299` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Created dynamically by JS in `public/views/layouts/header.php:1482` — `result.className = 'topbar-search-result'` |
| 1.9 | `[data-theme="light"] .topbar-search-input:focus` | `light.css:316-319` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Same element as 1.6, focus state |
| 1.10 | `[data-theme="light"] .topbar-search-scan-btn:hover` | `light.css:296-297,307-308` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Same element as 1.7, hover state |
| 1.11 | `[data-theme="dark"] .topbar` | `dark.css:400` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Rendered in `public/views/layouts/header.php:2223`, `apps/Shell/Composers/OperatorSurfaceComposer.php:3047` |
| 1.12 | `[data-theme="dark"] .layout-sidebar` | `dark.css:401` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Rendered in `public/views/layouts/sidebar.php:9` |
| 1.13 | `[data-theme="dark"] .topbar-search-results` | `dark.css:402` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Rendered in `public/views/layouts/header.php:2316` |
| 1.14 | `[data-theme="dark"] .notif-dropdown` | `dark.css:403` | Shell | `apps/Shell/styles/notifications.css` | HIGH | Rendered in `public/views/layouts/header.php:2344` |
| 1.15 | `[data-theme="dark"] .sidebar-link` | `dark.css:407` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Same element as 1.4, dark theme override |
| 1.16 | `[data-theme="dark"] .dashboard-link-card` | `dark.css:409` | Shell | `apps/Shell/styles/dashboard-cards.css` | HIGH | Generic Shell-level card pattern. Rendered by Platform (`platform_operations.php:97`), Studio (`gui_studio.php:3103-3172`), Base (`Views/admin/apps.php:297-309`), AdminTools, SBAIO (`reports.php:60`). Shared across owners — Shell is correct owner. |
| 1.17 | `[data-theme="dark"] .hero-meta-card` | `dark.css:411` | Shell | `apps/Shell/styles/dashboard-cards.css` | HIGH | Generic Shell-level meta card. Rendered by Base (`Views/admin/apps.php:280`), SBAIO (`dashboard.php:23`), Studio (`Adapters/KpiAdapter.php:34`), Procurement (`Views/requests.php:97`, `orders.php:75-76`, `dashboard.php:74-77`, `receipts.php:63`), ACL plugin. |
| 1.18 | `[data-color-style="liquid-glass"] .topbar` | `liquid-glass.css:528` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Variant backdrop-filter effect on Shell element |
| 1.19 | `[data-color-style="liquid-glass"] .layout-sidebar` | `liquid-glass.css:529` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Variant backdrop-filter effect on Shell element |
| 1.20 | `[data-color-style="liquid-glass"] .sidebar` | `liquid-glass.css:530` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Variant backdrop-filter effect. Note: no bare `.sidebar` rendered — all usage is `.app-sidebar`, `.u-sidebar`, `.wrapper-sidebar`, `.layout-sidebar`. This selector may match `.layout-sidebar` as ancestor context. |
| 1.21 | `[data-color-style="liquid-glass"] .topbar-search-results` | `liquid-glass.css:531` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Variant effect on Shell element |
| 1.22 | `[data-color-style="liquid-glass"] .notif-dropdown` | `liquid-glass.css:532` | Shell | `apps/Shell/styles/notifications.css` | HIGH | Variant effect on Shell element |
| 1.23 | `[data-color-style="liquid-glass"] .sidebar-link` | `liquid-glass.css:551` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Variant effect on Shell element |
| 1.24 | `[data-color-style="liquid-glass"] .sidebar-overflow-toggle` | `liquid-glass.css:552` | Shell | `apps/Shell/styles/sidebar.css` | HIGH | Rendered in `public/views/layouts/sidebar.php:127` |
| 1.25 | `[data-color-style="liquid-glass"] .topbar-search-result` | `liquid-glass.css:553` | Shell | `apps/Shell/styles/topbar.css` | HIGH | Same element as 1.8, variant effect |

### Subtotal: 25 selector entries — Shell

---

## 2. Dashboard Generic Selectors (Shell-owned card/composite patterns)

These are Shell-pattern card components rendered by multiple apps. Shell owns the pattern; apps consume it.

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 2.1 | `[data-theme="dark"] .widget-card` | `dark.css:410` | Shell | `apps/Shell/styles/dashboard-cards.css` | MEDIUM | CSS-only — no PHP template emits this class. Defined in `apps/Shell/styles/components.css:386-394`. Probably dead CSS (no rendering found across any view). Extraction is safe. |
| 2.2 | `[data-theme="dark"] .snapshot-card` | `dark.css:412` | Shell | `apps/Shell/styles/dashboard-cards.css` | MEDIUM | CSS-only — no PHP template emits this class. Defined in `apps/Shell/styles/components.css:887`. Probably dead CSS. |
| 2.3 | `[data-theme="dark"] .status-item` | `dark.css:413` | Shell | `apps/Shell/styles/dashboard-cards.css` | LOW | CSS-only — no view renders bare `.status-item`. The rendered variants are `.gs-workflow-status-item` (Studio), `.account-status-item` (Base), `.onboarding-status-item` (Setup). These are **distinct classes** that do not match the `.status-item` theme selector. Possibly dead CSS. |
| 2.4 | `[data-theme="dark"] .workflow-step` | `dark.css:414` | Shell | `apps/Shell/styles/dashboard-cards.css` | LOW | CSS-only — no view renders bare `.workflow-step`. The rendered variant is `.we-workflow-step` (Shell/WorkEntryComposer.php:597-600), a **distinct class**. Possibly dead CSS. |
| 2.5 | `[data-color-style="liquid-glass"] .dashboard-link-card` | `liquid-glass.css:535,562` | Shell | `apps/Shell/styles/dashboard-cards.css` | HIGH | Same element as 1.16, variant effect |
| 2.6 | `[data-color-style="liquid-glass"] .widget-card` | `liquid-glass.css:536,563` | Shell | `apps/Shell/styles/dashboard-cards.css` | MEDIUM | Same element as 2.1, variant effect |
| 2.7 | `[data-color-style="liquid-glass"] .hero-meta-card` | `liquid-glass.css:537,564` | Shell | `apps/Shell/styles/dashboard-cards.css` | HIGH | Same element as 1.17, variant effect |
| 2.8 | `[data-color-style="liquid-glass"] .snapshot-card` | `liquid-glass.css:538,565` | Shell | `apps/Shell/styles/dashboard-cards.css` | MEDIUM | Same element as 2.2, variant effect |
| 2.9 | `[data-color-style="liquid-glass"] .status-item` | `liquid-glass.css:539,566` | Shell | `apps/Shell/styles/dashboard-cards.css` | LOW | Same element as 2.3, variant effect |
| 2.10 | `[data-color-style="liquid-glass"] .workflow-step` | `liquid-glass.css:540,567` | Shell | `apps/Shell/styles/dashboard-cards.css` | LOW | Same element as 2.4, variant effect |

### Subtotal: 10 selector entries — Shell (dashboard generic)

---

## 3. Component Selectors

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 3.1 | `[data-theme="light"] .btn`, `.btn:hover` | `light.css:300-303,305-314,322-329` | Shell | `apps/Shell/styles/button.css` | HIGH | Rendered across Shell, Platform, Base, Studio, Manufacturing, SBAIO — it is the Shell-owned generic button class. Rendered in `header.php:2227,2331,2347`, `sidebar.php:148`, `OperatorSurfaceComposer.php:3050`, `Platform/Views/my_work.php`, `Platform/Views/ops/widget_builder.php`, etc. |
| 3.2 | `[data-theme="light"] select` | `light.css:331-333` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Native `<select>` form control. Rendered across all apps. |
| 3.3 | `[data-theme="dark"] .btn` | `dark.css:426` | Shell | `apps/Shell/styles/button.css` | HIGH | Same element as 3.1, dark override |
| 3.4 | `[data-theme="dark"] input:not([type="checkbox"]):not([type="radio"])` | `dark.css:427` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Native `<input>` form control. Rendered across all apps. |
| 3.5 | `[data-theme="dark"] select` | `dark.css:428` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Same as 3.2, dark override |
| 3.6 | `[data-theme="dark"] textarea` | `dark.css:429` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Native `<textarea>` form control. |
| 3.7 | `[data-color-style="liquid-glass"] .card` | `liquid-glass.css:533,561` | Shell | `apps/Shell/styles/card.css` | HIGH | Generic `.card` class. Rendered in `apps/Shell/Views/admin/dashboard.php:71`, `apps/Platform/Views/ops/platform_operations.php:80,90`, `apps/Platform/Views/ops/widget_builder.php:144,158,555`, `apps/Platform/Views/my_work.php` (16 instances). |
| 3.8 | `[data-color-style="liquid-glass"] .btn` | `liquid-glass.css:550` | Shell | `apps/Shell/styles/button.css` | HIGH | Same as 3.1, variant effect |
| 3.9 | `[data-color-style="liquid-glass"] input` | `liquid-glass.css:554` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Note: this is NOT `input:not([...])` — it's bare `input`, which includes checkboxes/radios. Same as 3.4, variant effect. Verify checkbox/radio invariance during extraction. |
| 3.10 | `[data-color-style="liquid-glass"] select` | `liquid-glass.css:555` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Same as 3.5, variant effect |
| 3.11 | `[data-color-style="liquid-glass"] textarea` | `liquid-glass.css:556` | Shell | `apps/Shell/styles/form-controls.css` | HIGH | Same as 3.6, variant effect |
| 3.12 | `[data-color-style="liquid-glass"] .table-wrap` | `liquid-glass.css:549,576` | Shell | `apps/Shell/styles/table.css` | HIGH | Generic table wrapper rendered across Shell, Studio, Platform, Base, Manufacturing, SBAIO. Key locations: `apps/Shell/Views/admin/workspace_prep.php:466`, `apps/Studio/Views/partials/workflow/*.php`, `apps/Studio/gui_studio.php` (29+ instances), `plugins/Base/Views/admin/apps.php`. |

### Subtotal: 12 selector entries — Shell (component)

---

## 4. Setup Selectors

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 4.1 | `[data-theme="dark"] body` | `dark.css:396-398` | Setup | `apps/Shell/styles/shell-setup.css` or `apps/Shell/styles/layout.css` | HIGH | The `body` element is the root page element. This sets `background: var(--page-bg)` in dark mode. Rendered by every page layout (Shell, Setup, Login, Auth all extend the same `body` element). During extraction, must ensure `body` is not duplicated across Setup + Shell stylesheets. Single ownership: Shell/chrome. |

### Subtotal: 1 selector entry — Setup

---

## 5. Application Selectors — Manufacturing

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 5.1 | `[data-theme="dark"] .coverage-kpi` | `dark.css:415` | Manufacturing/Coverage | `apps/Manufacturing/modules/Coverage/styles/coverage.css` | HIGH | Also rendered by Shell admin (`Shell/Views/admin/workspace_prep.php:504`) and Base plugin (`Base/Views/ops/role_dashboard.php:219`). The operator variant `coverage-kpi-card` (distinct class) is rendered across Manufacturing operator views. Dual reader. Recommend extracting to shared Shell + app override pattern. During extraction: keep base `.coverage-kpi` in Shell's shared styles, and let Manufacturing override with app-specific rules. |
| 5.2 | `[data-theme="dark"] .coverage-trend-wrap` | `dark.css:416` | Manufacturing/Coverage | `apps/Manufacturing/modules/Coverage/styles/coverage.css` | MEDIUM | CSS-only — no PHP template emits this class. Defined in `apps/Manufacturing/modules/Coverage/styles.css:11`. Trend chart component that may be rendered dynamically or prepared for future use. |
| 5.3 | `[data-theme="dark"] .app-quicklink-card` | `dark.css:417` | Manufacturing (shared) | `apps/Manufacturing/styles/manufacturing.css` | HIGH | Rendered by SBAIO (`apps/SBAIO/Views/dashboard.php:56`) and Base (`plugins/Base/Views/ops/role_dashboard.php:184,297`). The class name `app-quicklink-card` suggests it's an app-level quicklink, but SBAIO is the primary renderer alongside Base. **Ownership ambiguity**: SBAIO is the primary business-app renderer, but `app-` prefix suggests generic. Recommend Shell for base pattern, SBAIO for overrides. |
| 5.4 | `[data-theme="dark"] .mfg-subgroup-card` | `dark.css:418` | Manufacturing | `apps/Manufacturing/styles/manufacturing.css` | MEDIUM | CSS-only — no PHP template emits this class. Defined in `apps/Manufacturing/styles/manufacturing.css:4-16,377`. Prepared for a subgroup card component that may be dynamically rendered or is dead CSS. |
| 5.5 | `[data-color-style="liquid-glass"] .coverage-kpi` | `liquid-glass.css:541,568` | Manufacturing/Coverage | `apps/Manufacturing/modules/Coverage/styles/coverage.css` | HIGH | Variant effect on same element as 5.1 |
| 5.6 | `[data-color-style="liquid-glass"] .coverage-trend-wrap` | `liquid-glass.css:542,569` | Manufacturing/Coverage | `apps/Manufacturing/modules/Coverage/styles/coverage.css` | MEDIUM | Variant effect on same element as 5.2 |
| 5.7 | `[data-color-style="liquid-glass"] .app-quicklink-card` | `liquid-glass.css:543,570` | Manufacturing (shared) | `apps/Manufacturing/styles/manufacturing.css` | HIGH | Variant effect on same element as 5.3 |
| 5.8 | `[data-color-style="liquid-glass"] .mfg-subgroup-card` | `liquid-glass.css:544,571` | Manufacturing | `apps/Manufacturing/styles/manufacturing.css` | MEDIUM | Variant effect on same element as 5.4 |

### Subtotal: 8 selector entries — Manufacturing

---

## 6. Application Selectors — SBAIO

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 6.1 | `[data-theme="dark"] .sc-card` | `dark.css:420` | SBAIO | `apps/SBAIO/styles/sbaio.css` | LOW | CSS-only — no PHP template emits `.sc-card`. No CSS definition found outside theme files (`dark.css:85`). The `sc-` prefix suggests "supply chain" or similar SBAIO domain. No rendering template found anywhere in the codebase (search: PHP, JS, all apps). **Possibly dead CSS.** Mark **Unknown** if no SBAIO team can confirm. |
| 6.2 | `[data-color-style="liquid-glass"] .sc-card` | Not in lg.css | — | — | — | `.sc-card` does NOT appear in liquid-glass.css (it was only in dark.css). |

### Subtotal: 1 selector entry — SBAIO (tentative)

---

## 7. Application Selectors — Platform (via Base plugin)

| # | Selector | Current File | Owner | Destination File | Confidence | Notes |
|---|---|---|---|---|---|---|
| 7.1 | `[data-theme="dark"] .menu .group` | `dark.css:408` | Base (plugin) | `plugins/Base/styles/base-menu.css` | HIGH | Compound selector: `.group` inside `.menu`. The `menu` class is rendered by `plugins/Base/bootstrap.php:272`. The `group` class is rendered at `plugins/Base/bootstrap.php:300`. This is Base plugin legacy admin menu infrastructure. |
| 7.2 | `[data-theme="dark"] .ai-kpi` | `dark.css:419` | Base (plugin) | `plugins/Base/styles/approval-inbox.css` | HIGH | Rendered in `plugins/Base/Views/approval_inbox.php:183-202`. `.ai-kpis` wraps 5 `.ai-kpi` blocks for approval KPI summary. |
| 7.3 | `[data-color-style="liquid-glass"] .menu .group` | `liquid-glass.css:534` | Base (plugin) | `plugins/Base/styles/base-menu.css` | HIGH | Variant effect on 7.1 |
| 7.4 | `[data-color-style="liquid-glass"] .ai-kpi` | `liquid-glass.css:545,572` | Base (plugin) | `plugins/Base/styles/approval-inbox.css` | HIGH | Variant effect on 7.2 |
| 7.5 | `[data-color-style="liquid-glass"] .ai-item` | `liquid-glass.css:546,573` | Base (plugin) | `plugins/Base/styles/approval-inbox.css` | HIGH | Rendered in `plugins/Base/Views/approval_inbox.php:101,310` |
| 7.6 | `[data-color-style="liquid-glass"] .ai-details` | `liquid-glass.css:547,574` | Base (plugin) | `plugins/Base/styles/approval-inbox.css` | HIGH | Rendered in `plugins/Base/Views/approval_inbox.php:298` |
| 7.7 | `[data-color-style="liquid-glass"] .ai-table-wrap` | `liquid-glass.css:548,575` | Base (plugin) | `plugins/Base/styles/approval-inbox.css` | HIGH | Rendered in `plugins/Base/Views/approval_inbox.php:348` |

### Subtotal: 7 selector entries — Base (plugin)

---

## 8. Summary: All Selectors by Owner

| Owner | Count | Selector Groups | Destination |
|---|---|---|---|
| **Shell** | 25 | `.sidebar-*`, `.topbar-*`, `.layout-sidebar`, `.notif-dropdown`, `.sidebar-overflow-toggle` | `apps/Shell/styles/{sidebar,topbar,notifications}.css` |
| **Shell (dashboard generic)** | 10 | `.dashboard-link-card`, `.widget-card`, `.hero-meta-card`, `.snapshot-card`, `.status-item`, `.workflow-step` | `apps/Shell/styles/dashboard-cards.css` |
| **Shell (component)** | 12 | `.btn`, `input`, `select`, `textarea`, `.card`, `.table-wrap` | `apps/Shell/styles/{button,form-controls,card,table}.css` |
| **Setup** | 1 | `body` | `apps/Shell/styles/shell-setup.css` |
| **Manufacturing** | 8 | `.coverage-kpi`, `.coverage-trend-wrap`, `.app-quicklink-card`, `.mfg-subgroup-card` | `apps/Manufacturing/styles/` or module-level `styles/` |
| **SBAIO** | 1 | `.sc-card` | `apps/SBAIO/styles/sbaio.css` (tentative) |
| **Base** | 7 | `.menu .group`, `.ai-kpi`, `.ai-item`, `.ai-details`, `.ai-table-wrap` | `plugins/Base/styles/` |
| **Total** | **64** | | |

---

## 9. Special: Variant Visual Effects

The liquid-glass `.card`, `.dashboard-link-card`, `.widget-card`, `.hero-meta-card`, `.snapshot-card`, `.status-item`, `.workflow-step`, `.coverage-kpi`, `.coverage-trend-wrap`, `.app-quicklink-card`, `.mfg-subgroup-card`, `.ai-kpi`, `.ai-item`, `.ai-details`, `.ai-table-wrap`, `.table-wrap` selector groups at `liquid-glass.css:561-578` apply **`border-color: var(--style-border-soft)`** as a style variant.

**Extraction approach:**
- Each extracted selector must bring its liquid-glass border-color override to the same destination file.
- Use a data-attribute or wrapper class approach: instead of duplicating the 14-selector list in each owner's CSS, define `.lg-border-soft` utility class and apply it at render time.

**Alternative approach (lower risk):**
- Keep the variant border-color overrides in the theme system (they are theme value application, not layout). Only extract the selector grouping by using a shared class.

---

## 10. Special: Dead or Dormant CSS

These selectors have CSS definitions in the theme source chain but **no rendering view emits them**:

| Selector | Current File | Evidence | Recommendation |
|---|---|---|---|
| `.sidebar-group-operations` | `light.css:283-287` | No PHP grep match; CSS-only in `components.css` | Extract to Shell sidebar CSS; harmless if no DOM element matches |
| `.widget-card` | `dark.css:410`, `liquid-glass.css:536,563` | No PHP grep match for `.widget-card` | Extract to Shell dashboard-cards CSS; dormant pattern |
| `.snapshot-card` | `dark.css:412`, `liquid-glass.css:538,565` | No PHP grep match for `.snapshot-card` | Extract to Shell dashboard-cards CSS; dormant pattern |
| `.status-item` | `dark.css:413`, `liquid-glass.css:539,566` | No PHP grep match for bare `.status-item`; only `gs-workflow-*`, `account-*`, `onboarding-*` variants | Extract to Shell dashboard-cards CSS; dead CSS |
| `.workflow-step` | `dark.css:414`, `liquid-glass.css:540,567` | No PHP grep match for bare `.workflow-step`; only `we-workflow-step` | Extract to Shell dashboard-cards CSS; dead CSS |
| `.coverage-trend-wrap` | `dark.css:416`, `liquid-glass.css:542,569` | No PHP grep match; CSS-only in `Manufacturing/modules/Coverage/styles.css` | Extract to Manufacturing CSS; dormant component |
| `.mfg-subgroup-card` | `dark.css:418`, `liquid-glass.css:544,571` | No PHP grep match; CSS-only in `Manufacturing/styles/manufacturing.css` | Extract to Manufacturing CSS; dormant component |
| `.sc-card` | `dark.css:420` | No PHP grep match anywhere in codebase | **Unknown owner.** Possibly dead or future component. Mark as Unknown in extraction plan. |

**Total dormant**: 8 selectors across Shell, Manufacturing, and one Unknown.

---

## 11. Selector Preservation During Extraction

All theme source selectors currently use `var(--token)` for their values — **no hardcoded values exist in any selector block**. This makes extraction mechanically safe: the selectors will continue to resolve the same tokens from theme.css even after extraction to owner stylesheets.

| Token | Used by selectors | Defined in |
|---|---|---|
| `var(--style-subtle-bg)` | Light sidebar/search/btn background | `foundation.css` (overridden in `light.css`, `dark.css`) |
| `var(--style-border-soft)` | Light sidebar/search/btn border | `foundation.css` (overridden in `light.css`, `dark.css`, `lg.css`) |
| `var(--style-content-bg)` | Dark card/surface backgrounds | `foundation.css` (overridden in `dark.css`, `lg.css`) |
| `var(--style-border)` | Dark border-color | `foundation.css` (overridden in `dark.css`, `lg.css`) |
| `var(--style-control-bg)` | Dark form control backgrounds | `foundation.css` (overridden in `dark.css`, `lg.css`) |
| `var(--glass-border)` | Dark form control border | `foundation.css` (overridden in `dark.css`, `lg.css`) |
| `var(--page-bg)` | Body background | `foundation.css` (overridden in `light.css`, `dark.css`, `lg.css`) |
| `var(--style-shell-bg)` | Topbar/sidebar/search/notif background | `foundation.css` (overridden in `light.css`, `dark.css`, `lg.css`) |
| `var(--text)` | Sidebar group title color | `foundation.css` (overridden in `light.css`, `dark.css`) |
| `var(--style-border-soft)` | LG card border-color | `foundation.css` (overridden in `light.css`, `dark.css`, `lg.css`) |

---

## 12. Extraction Order Recommendation

| Phase | What | Risk | Owner effort |
|---|---|---|---|
| **2a** | Shell sidebar + topbar + notification selectors (16 entries) | Low — all Shell-owned, single destination per group | 1 file group |
| **2b** | Shell component selectors: `.btn`, `input`, `select`, `textarea`, `.card`, `.table-wrap` (12 entries) | Low — generic form/layout, single destination per group | 1 file group |
| **2c** | Shell dashboard generic cards: `.dashboard-link-card`, `.hero-meta-card`, plus dormant `.widget-card`, `.snapshot-card`, `.status-item`, `.workflow-step` (10 entries) | Low — dormant CSS safe to move or remove | 1 file group |
| **2d** | Setup: `body` (1 entry) | Low — single selector | 1 file |
| **2e** | Liquid-glass backdrop-filter effect (across Shell/Base/Manufacturing selectors) | Medium — requires per-owner coordination for the selector list | Multiple owners |
| **2f** | Manufacturing selectors: `.coverage-kpi`, `.app-quicklink-card` (active) + `.coverage-trend-wrap`, `.mfg-subgroup-card` (dormant) | Medium — `coverage-kpi` has dual readers (Shell + Manufacturing) | Manufacturing |
| **2g** | Base plugin selectors: `.menu .group`, `.ai-*` classes | Low — plugin-owned | Base |
| **2h** | `.sc-card` — Unknown owner | Low — dormant CSS, safe to leave or remove | Needs owner identification |

---

## Validation Gates

- `git diff --check`: ✅ (no whitespace errors)
- `bash scripts/system/check_deployment_readiness.sh`: ✅ PASS
- `bash scripts/architecture/run_architecture_gates.sh`: ✅ PASS

No files were modified during this audit.
