# Shell Behavior & Rendering Contract v1

**Status**: Active architecture contract (governed, no implementation authorized by this document)
**Date**: 2026-06-05
**Owner**: Shell + Tooling/System Tools governance
**Supersedes**: Ad-hoc shell layout patterns in operator.css, components.css, admin.css
**References**:
- `docs/architecture/shell-behavior-rendering-audit.md` — Prompt 1/4 audit findings
- `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` — Charter laws
- `docs/architecture/theme-source-compilation-migration.md` — Theme boundary
- `docs/architecture/surface-contribution-contract.md` — Composition safety

---

## 1. Purpose

This contract defines the Shell as the **owner of universal system rendering behavior** — viewport management, layout frame, navigation surface, topbar, footer, overlays, scroll lock, z-index application, and responsive mechanics — as a separate concern from Theme Architecture (which owns token values only).

The Shell is not a business surface. It provides the generic runtime wrapper into which Apps, Modules, and Plugins contribute business content. This contract codifies the rendering rules that both the Operator Shell (`.app-shell`) and Admin Shell (`.layout-shell`) must converge toward, resolving the gap of two independent shell models identified in the Prompt 1/4 audit.

This contract does not authorize implementation. Implementation follows in Prompts 3/4 and 4/4 under strict migration phases.

---

## 2. Ownership Boundaries

### Shell Owns

1. **Viewport** — `100dvh` sizing, safe-area integration, overscroll behavior, body-level scroll containment.
2. **Layout frame** — The grid/flex container that structures the page (`.app-shell` / `.layout-shell`).
3. **Navigation surface** — Sidebar, hamburger drawer, submenu rendering, navigation collapse/peek.
4. **Topbar** — Height, sticky positioning, search elements, notification triggers, avatar/action triggers, company branding slot.
5. **Footer** — Fixed/flow placement, layer-switching links, version display.
6. **Overlays** — The shell overlay container, backdrop governance, portal-rendered panels (avatar, action, notification).
7. **Scroll lock** — Single mechanism, overlay count integration, body-class governance.
8. **Z-index map** — Named layer reservation system; all z-index values come from Shell-owned custom properties.
9. **Responsive breakpoints** — The canonical breakpoint token set consumed by all surfaces.
10. **Safe area** — Token-based safe-area application pattern across all wrapper surfaces.
11. **Blur activation** — Workspace blur when overlay is active; `backdrop-filter` governance.
12. **Print/export surface** — `@media print` rules for hiding shell chrome.

### Theme Owns

1. **Token values only** — Colors, spacing, radius, shadow, blur intensity, font stacks, timing.
2. **Theme variant overrides** — Light/dark mode, liquid-glass vs paper style tokens.

### Apps/Modules Own

1. **Business content surfaces** — The workspace area within the layout frame.
2. **Business logic and data rendering** — Cards, tables, forms, charts, KPI strips.
3. **Owner-scoped CSS** — Business selectors in app/module stylesheets.
4. **Widget/card declarations** — Contribution metadata consumed by Shell composition.

### Studio Owns (Future)

1. **Governed editing of approved rendering sockets** — Sidebar width, widget arrangement, topbar customization (future phase, not v1).

### Core Owns

1. **Nothing in this contract.** Core owns platform law and primitives only. Core must never own presentation behavior, layout rules, shell chrome, rendering mechanics, or responsive breakpoints.

---

## 3. Surface Model

### Canonical Surfaces (top-to-bottom viewport order)

```
┌─────────────────────────────────────────┐
│  Scan / Camera Surface (overlay)        │ ← z-index: scanner
├─────────────────────────────────────────┤
│  Overlay Surface                        │ ← z-index: overlay
│  ┌───────────────────────────────────┐  │
│  │ Avatar / Action / Notif panels    │  │
│  └───────────────────────────────────┘  │
├─────────────────────────────────────────┤
│  Topbar Surface (sticky)                │ ← z-index: topbar
│  ┌──────┬────────────────┬───────────┐  │
│  │Menu  │ Search/Brand   │ Avatar/   │  │
│  │Toggle│                │ Actions   │  │
│  └──────┴────────────────┴───────────┘  │
├────────────────┬────────────────────────┤
│ Navigation     │ Workspace Surface      │
│ Surface        │ (scrollable, owns      │
│ (sidebar /     │  business content)      │
│  drawer)       │                        │
│                │                        │
├────────────────┴────────────────────────┤
│  Footer Surface                         │
└─────────────────────────────────────────┘
│  Print/Export Surface (@media print)    │ ← hides topbar, sidebar, footer
```

### Surface Definitions

| Surface | Role | Position | Scroll | Z-layer |
|---|---|---|---|---|
| **Viewport** | Root container (`body`) | Static | `overflow: hidden` | base |
| **Shell Frame** | `.app-shell` / `.layout-shell` | Grid container | Grid row overflow | base |
| **Topbar Surface** | Branding, search, triggers | `position: sticky; top: 0` | Never scrolls | topbar |
| **Navigation Surface** | Sidebar (desktop) / Drawer (mobile) | Grid column / fixed drawer | `overflow-y: auto` | navigation |
| **Workspace Surface** | Business content area | Grid column / flex child | `overflow-y: auto` | workspace |
| **Overlay Surface** | Portal panels (avatar, action, notif) | `position: fixed; inset: 0; pointer-events: none` | Locked when active | overlay |
| **Footer Surface** | Version, layer links | Grid row / flow | Static | base |
| **Print/Export Surface** | Print-only chrome hide | `@media print` | N/A | N/A |
| **Scan/Camera Surface** | Barcode/QR scanner | `position: fixed; inset: 0` | Locked | scanner |

---

## 4. Rendering Method

Fluid-first rendering is the approved approach:

1. **Fluid first** — Layout containers use `%`, `fr`, `min-content`, `max-content`, `auto`. No fixed-px-first layout.

2. **Fractional grids** — Sidebar/workspace split uses `grid-template-columns: var(--sidebar-width) minmax(0, 1fr)`. Workspace always has `minmax(0, 1fr)` to prevent overflow.

3. **`clamp()` for spacing/density** — Padding, gap, and margin values use `clamp()`:
   ```
   --space-xs: clamp(2px, 0.25vw, 4px);
   --space-sm: clamp(4px, 0.5vw, 8px);
   --space-md: clamp(8px, 1vw, 16px);
   --space-lg: clamp(16px, 1.5vw, 24px);
   ```
   Values reference theme tokens, not hardcoded px.

4. **`minmax()` / `auto-fit` / `auto-fill`** — Card grids, KPI strips, and dashboard layouts use intrinsic sizing.
   ```
   grid-template-columns: repeat(auto-fill, minmax(var(--card-min), 1fr));
   ```

5. **Container-aware behavior** — Dashboard widgets, card grids, and panel layouts use `@container` queries for context-aware resizing, not viewport media queries.

6. **Bounded max-width** — Workspace content `max-width` is permitted only where readability requires it (e.g., `.content { max-width: var(--content-max); }`). Default is `max-width: none` in the grid column.

7. **No fixed-pixel-first layout** — Hardcoded px values for layout dimensions (topbar height `58px`, sidebar width `272px`/`280px`/`320px`, content padding `18px`) are replaced by token references:
   ```
   --topbar-height: var(--space-topbar);
   --sidebar-width: var(--space-sidebar);
   ```

8. **Safe area integration** — All viewport-edge surfaces use `var(--safe-area-*)` tokens consistently:
   ```
   padding: var(--safe-area-top) var(--safe-area-right) var(--safe-area-bottom) var(--safe-area-left);
   ```

---

## 5. Breakpoint Contract

### Canonical Breakpoint Names and Tokens

| Token | Name | Value | Purpose |
|---|---|---|---|
| `--bp-mobile` | mobile | `480px` | Compact single-column surfaces, bottom nav |
| `--bp-tablet` | tablet | `768px` | Multi-column grids appear, sidebar collapses |
| `--bp-desktop` | desktop | `1024px` | Full sidebar visible, content max-width |
| `--bp-wide` | wide | `1366px` | Expanded content area, multi-panel dashboards |
| `--bp-ultrawide` | ultrawide | `1920px` | Maximum content bounds, optional side panels |
| `--bp-display` | display/tv | `2560px` | TV/kiosk display surfaces |

### Usage Rules

1. **No direct `px` values in `@media` rules** — All breakpoints reference tokens:
   ```
   @media (max-width: var(--bp-mobile)) { ... }
   ```

2. **`min-width` preferred** — Desktop-first media queries with `min-width` breakpoints. `max-width` permitted only for mobile-only overrides.

3. **Each breakpoint has one semantic purpose** — No overlapping ranges. The current `900px` vs `901px` ambiguity is resolved:
   - `--bp-tablet` (768px): sidebar collapse, bottom nav appear
   - `--bp-desktop` (1024px): sidebar expanded, full grid

4. **Container queries for component-level responsiveness** — Viewport media queries are reserved for shell-level layout changes (sidebar, topbar, bottom nav). Component-level responsiveness (widgets, cards, KPI strips) uses `@container`.

5. **Consistent `prefers-reduced-motion`** — All transitions and animations respect `@media (prefers-reduced-motion: reduce)`.

---

## 6. Z-Index Contract

### Named Layers (Lowest to Highest)

| Layer | Token | Value | Purpose |
|---|---|---|---|
| base | `--z-base` | `0` | Document flow, static content |
| workspace | `--z-workspace` | `10` | Workspace content layer |
| navigation | `--z-navigation` | `20` | Sidebar (desktop), rail collapse |
| topbar | `--z-topbar` | `30` | Sticky topbar |
| dropdown | `--z-dropdown` | `40` | Search results, select menus, popups |
| drawer | `--z-drawer` | `50` | Sidebar drawer (mobile), action panels |
| backdrop | `--z-backdrop` | `60` | Backdrops for drawers, modals, panels |
| modal | `--z-modal` | `70` | Modal dialogs, confirmation prompts |
| overlay | `--z-overlay` | `80` | Shell overlay container, portal panels |
| scanner | `--z-scanner` | `90` | Camera/barcode scanner overlay |
| system-emergency | `--z-system-emergency` | `100` | Error banners, system alerts |

### Rules

1. **All z-index values must use named layer tokens** — No raw numeric z-index values outside the Shell-owned layer map. Exceptions require explicit approval and a reserved layer slot.

2. **Layer values are separated by gaps of 10** — Allows sub-layering within each named layer (`calc(var(--z-modal) + 1)` for modal inner elements).

3. **Sidebar z-index is unified** — Desktop sidebar uses `var(--z-navigation)` (20). Mobile drawer uses `var(--z-drawer)` (50). No sidebar uses conflicting z-index values.

4. **Modal and sidebar do not share a layer** — Modal is `var(--z-modal)` (70), sidebar drawer is `var(--z-drawer)` (50). The current conflict at `z-index: 90` is resolved.

5. **Scanner layer (90) is the highest interactive layer** — System emergency (100) is reserved for non-interactive alerts that must always be visible.

6. **`--z-overlay` governs all portal-rendered panels** — Avatar panel, action panel, notification dropdowns render within the shell overlay container at `var(--z-overlay)`.

---

## 7. Overlay Contract

### Overlay Types

| Type | Layer | Backdrop | Blur | Scroll Lock | Escape | Outside-Click | Focus | Owner |
|---|---|---|---|---|---|---|---|---|
| **Dropdown** | `--z-dropdown` (40) | None | None | No | Escape closes | Click outside closes | Focus trap optional | Shell |
| **Popover** | `--z-dropdown` (40) | None | None | No | Escape closes | Click outside closes | Focus trap optional | Shell |
| **Drawer** | `--z-drawer` (50) | `--z-backdrop` (60), `pointer-events: auto` | Workspace only | Yes | Escape closes | Backdrop click closes | Focus trap on open | Shell |
| **Modal** | `--z-modal` (70) | `--z-backdrop` (60), `pointer-events: auto` | Workspace only | Yes | Escape closes | Backdrop may close (configurable) | Focus trap required | Shell |
| **Scanner** | `--z-scanner` (90) | Full-screen overlay | Workspace only | Yes | Escape closes | N/A (full-screen) | Focus on scanner UI | Shell |
| **Action Panel** | `--z-drawer` (50) | `--z-backdrop` (60), `pointer-events: auto` | Workspace only | Yes | Escape closes | Backdrop click closes | Focus on panel | Shell |

### Rules

1. **Single shell overlay container** — One `.shell-overlay` per shell (operator + admin). All portal-rendered panels live inside it.

2. **`pointer-events: none` on the overlay container** — Clicks pass through to surfaces beneath. Individual panels set `pointer-events: auto`.

3. **Only one overlay active at a time** — The `__overlayCount` counter (or equivalent mutex) prevents concurrent overlay states. Opening a drawer closes any open panel and vice versa.

4. **Backdrop click closes** — All backdrops (drawer, modal, action panel) close their associated surface on click.

5. **Escape key closes** — All overlays close on `Escape` keydown. Admin shell must implement this (currently absent).

6. **No `backdrop-filter` on backdrops** — All backdrop blur targets the workspace layer via `body.has-active-overlay .app-shell` / `body.has-active-overlay .layout-main`. Backdrops remain visually clean.

---

## 8. Blur Contract

### Rules

1. **Workspace may blur** — When an overlay is active (`body.has-active-overlay`), the workspace surface (`.app-shell` / `.layout-main`) should receive `filter: blur(var(--blur-overlay))`.

2. **Topbar must remain sharp** — The topbar is the primary navigation/action trigger zone. It must never blur.

3. **Navigation surface must remain sharp** — The sidebar/drawer must never blur. Users navigate through it.

4. **Active overlay panel must remain sharp** — The panel content (avatar, action, notif, modal) must be fully readable. `backdrop-filter` on the panel itself is prohibited.

5. **Backdrop must not blur its own panel** — If a backdrop uses `backdrop-filter`, it applies to the content behind the backdrop only, not to the panel it contains.

6. **Blur intensity comes from theme token** — `var(--blur-overlay)` is a Theme-owned token value. Shell selects when to apply blur; Theme controls how much.

7. **Blur activation is Shell behavior** — The class toggle (`body.has-active-overlay`) and overlay count integration are Shell-owned rendering mechanics. Apps must not independently toggle blur.

---

## 9. Header / Sidebar / Footer Contract

### Desktop Behavior (≥1024px)

| Surface | Layout | Position | Scroll |
|---|---|---|---|
| Topbar | `position: sticky; top: 0` | Always visible | Never scrolls |
| Sidebar | Grid column, `position: relative` | Pushed by grid | `overflow-y: auto` within shell frame |
| Footer | Grid row or document flow | Below workspace | Scrolls with page |
| Workspace | `minmax(0, 1fr)` grid column | Fills remaining space | `overflow-y: auto` |

### Mobile Behavior (<1024px)

| Surface | Layout | Position | Scroll |
|---|---|---|---|
| Topbar | `position: sticky; top: 0` | Always visible | Never scrolls |
| Sidebar | Hamburger drawer | `position: fixed; left: 0; top: var(--topbar-height)` | `overflow-y: auto` within drawer |
| Footer | Bottom of document flow | Below workspace | Scrolls with workspace |
| Bottom Nav | `position: fixed; bottom: 0` | Fixed overlay | Never scrolls |

### Submenu Behavior

1. Submenus within the navigation surface are **Shell-owned rendering** — Expand/collapse is Shell behavior, not app behavior.
2. Submenu state persists within the Shell navigation model. Apps declare hierarchy; Shell renders it.
3. Submenu collapse/expand must be accessible (keyboard, `aria-expanded`).
4. Hover-based peek on collapsed sidebar must have a keyboard alternative.

### Sticky/Fixed Rules

1. **Topbar is always sticky** (`position: sticky; top: 0`). Never `position: fixed` — sticky respects safe areas and stacking context.
2. **Sidebar is never `position: fixed` at desktop** — Grid column or `position: relative`. `position: fixed` is permitted only for mobile drawer mode.
3. **Bottom nav is always `position: fixed`** at mobile breakpoints.
4. **Footer is never fixed** — Document flow below workspace. Fixed footer risks content occlusion on short viewports.

### Scroll Rules

1. **Body-level scroll is locked** (`body { overflow: hidden; }`). All scrolling happens within shell child containers.
2. **Workspace scrolls independently** (`.app-shell workspace-area` / `.layout-main { overflow-y: auto }`).
3. **Sidebar scrolls independently** when content exceeds viewport.
4. **`overscroll-behavior: contain`** on sidebar and workspace to prevent scroll chaining.
5. **Scroll position is preserved** on sidebar collapse/expand.

### Footer Placement Rules

1. Footer renders at the natural end of the workspace content.
2. Footer must not overlap with bottom nav (mobile).
3. Footer contains layer-switching links (Operator ↔ Admin) and version display.
4. Footer links respect wrapper confinement — operator footer links stay in `/u/*`, admin footer links stay in `/admin/*`.

---

## 10. Density / Mode Contract

### Rendering Profiles (Future)

The Shell defines these rendering profiles as future concerns (no implementation in v1). They describe the shape and density of Shell chrome, not visual theme.

| Profile | Topbar Height | Sidebar Width | Padding Scale | Bottom Nav | Use Case |
|---|---|---|---|---|---|
| **admin** | Standard | Full/expanded | Normal | Hidden | Admin governance, management |
| **operator** | Standard | Collapsible (grid) | Normal | Visible (mobile) | Floor work, production |
| **compact** | Reduced | Narrow/collapsed | Compact | Hidden | Power users, dense data work |
| **comfortable** | Expanded | Full | Relaxed | Hidden | Review, presentation, accessibility |
| **tv/display** | Minimal/hidden | Hidden | Normal | Hidden | Kiosk, TV monitors, read-only dashboards |
| **kiosk/mobile** | Minimal | Hidden | Normal | Visible | Handheld, dedicated device |

### Rules

1. Density profiles affect **Shell chrome dimensions only** — topbar height, sidebar width, padding scale, bottom nav visibility. They do not affect theme tokens, font sizes, or business content layout.
2. Density profile is a **Shell rendering concern**. Apps provide content; Shell applies density.
3. Profile selection is governed by route family (`/u/*`, `/admin/*`, `/displays/*`) and may be overridden by user preference (future).

---

## 11. Universal Component Boundary

### Current State

The following component patterns exist in `components.css` under Shell ownership but are conceptually shared UI primitives used by apps:

- **Cards** (`.card`, `.card-header`, `.card-body`) — Generic content containers with header/footer slots.
- **Dashboard tiles** (`.tile-card`) — Linkable dashboard blocks with icon + label + badge.
- **Tables** (`.table-wrap`, table element styling) — Generic table chrome, responsive card fallback.
- **Buttons** (`.btn`, `.btn-primary`, etc.) — Universal button system.
- **KPI indicators** (`.kpi-value`, `.kpi-label`) — Numeric metric display.
- **Forms** (`.form-group`, `.form-input`, select styling) — Generic form controls.
- **Filters** (`.filter-bar`, `.filter-chip`) — Generic filter UI.
- **Badges/pills** (`.badge`, `.pill`) — Status and count indicators.
- **Notifications** (`.notif-dot`, `.notif-dropdown`) — Alert indicators.
- **Modals** (modal backdrop + dialog) — Generic dialog system.
- **Search** (`.topbar-search-*`) — Topbar search component.
- **Grid utilities** (`.grid-*`, `.dash-grid`) — Responsive grid helpers.

### Boundary Decision

These patterns represent a **Shell-owned Universal Component Catalog** — shared rendering primitives that any app/module may consume. They are not app-owned because they provide generic chrome/UX patterns, not business semantics. They are not Theme-owned because they define structure, not values.

This contract declares these as Shell-owned components. Their visual properties (colors, spacing, radius) come from Theme tokens. Their structure, behavior, and responsive rules are Shell-owned.

### Future Universal Component Contract

If the component catalog grows beyond ~20 primitives, a standalone **Universal Component Contract** should be created with explicit ownership, API stability guarantees, theme token consumption audit, and accessibility baseline.

---

## 12. Migration Plan

### Phase 1 — Layer Tokens and Z-Index Map (Prompt 3/4 candidate)

**Scope**: CSS custom properties for z-index layers, canonical breakpoints, density tokens.

1. Add `--z-base` through `--z-system-emergency` custom properties to Shell CSS (on `:root` / `body`).
2. Migrate all hardcoded z-index values in operator.css and components.css to `var(--z-*)` references.
3. Add `--bp-mobile` through `--bp-display` custom properties.
4. Replace all `@media (max-width: Npx)` rules with token references.
5. Resolve the 900px/901px sidebar breakpoint ambiguity to a single token.

**Validation**: All z-index values reference tokens. No raw numeric z-index added. Architecture gates pass.

### Phase 2 — Overlay State and Scroll Lock Unification (Prompt 3/4 candidate)

**Scope**: Single shell overlay, unified overlay counter, single scroll-lock mechanism.

1. Add `.shell-overlay` to admin shell (currently operator-only).
2. Unify `__overlayCount` / scroll-lock into a shared Shell service or JS module.
3. Replace two independent scroll-lock implementations with one.
4. Add Escape-key handler to admin shell overlays.
5. Ensure all overlays (dropdown, popover, drawer, modal, scanner, action panel) route through the single overlay container.

**Validation**: Only one scroll-lock mechanism. Admin shell has overlay container. All overlays close on Escape.

### Phase 3 — Sidebar and Topbar Normalization (Prompt 4/4 candidate)

**Scope**: Unified sidebar width token, topbar height token, consistent position behavior.

1. Add `--sidebar-width` and `--topbar-height` custom properties consumed by both shells.
2. Normalize operator (272px) and admin (280px/320px) sidebar widths to a single token with two profiles (expanded/collapsed).
3. Topbar height via `--topbar-height` — eliminate hardcoded `58px` and `calc()` fallbacks.
4. Ensure desktop sidebar is never `position: fixed` in either shell.
5. Ensure mobile sidebar drawer uses `var(--z-drawer)` consistently.

**Validation**: Both shells reference the same sidebar-width and topbar-height tokens. No `position: fixed` sidebar at desktop in either shell.

### Phase 4 — Fluid Layout Utilities (Prompt 4/4 candidate)

**Scope**: `clamp()` based spacing tokens, container query adoption, safe-area standardization.

1. Add `--space-{xs,sm,md,lg,xl}` tokens using `clamp()`.
2. Replace hardcoded `18px`, `12px`, `16px`, `22px`, `24px` padding values with token references.
3. Convert dashboard grids from viewport media queries to container queries.
4. Standardize `var(--safe-area-*)` usage across all wrapper surfaces.
5. Add `overscroll-behavior: contain` to sidebar and workspace.

**Validation**: No hardcoded px spacing values in Shell layout CSS. Dashboard grids use `@container`.

### Phase 5 — Guardrails (Post-Migration)

**Scope**: Architecture gates, lint rules, onboarding documentation.

1. Add Shell rendering gate to the architecture gate suite:
   `scripts/architecture/check_shell_behavior_contract.sh`
2. Gate checks:
   - No raw numeric z-index values in Shell CSS.
   - No hardcoded px breakpoint values in `@media` rules.
   - No `@media (max-width|min-width)` in app CSS (must use `@container` for component responsiveness).
   - Shell CSS does not contain app business selectors (already covered by `check_shell_css_ownership.sh`).
   - Desktop sidebar is not `position: fixed`.
3. Add CSS lint rule: `declaration-property-value-disallowed-list` for `z-index` with raw numbers over 10.

---

## 13. Non-Goals

This contract explicitly does not authorize:

1. **No theme redesign** — Theme values, token names, variant structure, and compilation pipeline remain unchanged. Theme is value-owner; Shell is behavior-owner.

2. **No app dashboard redesign** — App-specific layouts, cards, KPI strips, dashboards, and business content surfaces are app-owned. This contract governs the wrapper, not the content.

3. **No Studio adaptation yet** — Studio customization of rendering sockets is a future phase. Studio's governed editing workflow does not extend to Shell rendering behavior in v1.

4. **No component visual redesign** — The Universal Component Catalog (cards, tables, buttons, KPI indicators) is structurally owned by Shell but visually driven by Theme tokens. This contract does not redesign their appearance, only their ownership classification.

5. **No new rendering features** — This contract codifies existing behavior patterns and resolves inconsistencies. It does not add new layout types, animation systems, or interactive behaviors.

6. **No Core changes** — Core remains locked. No Core files are modified by this contract or its implementation phases.

7. **No runtime behavior changes from this document alone** — This is a governance contract. Implementation requires explicit prompts (3/4 and 4/4) with validation gates.

---

## Validation

This contract is an architecture governance document. It is validated by:

- Internal consistency review with the Prompt 1/4 audit findings.
- Cross-reference with Charter v1 ownership laws.
- Cross-reference with Theme Architecture V1 boundary separation.
- `git diff --check` — No whitespace errors.
- Architecture gates — All 29 gates pass (no runtime behavior changed).
- Deployment readiness — All checks pass.

Implementation phases (Prompts 3/4, 4/4) will require additional validation: CSS lint, browser smoke tests, and Playwright overlay/scroll/z-index checks.
