# Shell Behavior & Rendering Architecture Audit

**Status**: Classification B — Architecture revealed, contracting phase authorized
**Date**: 2026-06-05
**Auditor**: Automated architecture audit (Prompt 1/4 — read-only)

---

## 1. Executive Summary

This audit reveals **two distinct Shell models** operating under different layout strategies, z-index systems, sidebar behaviors, and overlay mechanisms. The Operator Shell and Admin Shell converge to CSS Grid at desktop breakpoints but diverge on mobile. Critical gaps exist in sidebar ownership (3 sidebar models), overlay governance (3+ overlay mechanisms), z-index range fragmentation (1–100060 across 24 distinct values), and breakpoint centralization (26+ unique widths). No architecture rule violations were found — the current state is functional but lacks a unified Shell Behavior & Rendering Contract.

**Classification B**: Architecture gaps identified; Prompt 2/4 (contract writing) is authorized.

---

## 2. Shell Layout Model — Two Shells

### Shell A: Operator Shell (`operator.css`)
| Property | Desktop | Mobile |
|---|---|---|
| Container | `.app-shell` (CSS Grid) | `.app-shell` (1-col grid) |
| Grid cols | `var(--sidebar-width) minmax(0, 1fr)` (272px default) | Single column |
| Body | `display: flex; flex-direction: column; height: 100vh` | Same |
| Topbar | `position: sticky; top: 0; z-index: 100` | Same |
| Sidebar | Grid column (pushed), NOT positioned | Hamburger drawer `position: fixed; z-index: 160` |
| Shell DOM | `body > .topbar + .app-shell` | Same |
| Routing | `/u/{username}/*` | Same |

### Shell B: Admin Shell (`components.css`)
| Property | Desktop (≥901px) | Mobile (<901px) |
|---|---|---|
| Container | `.layout-shell` (CSS Grid) | `.layout-shell` (Flex) |
| Grid cols | `var(--me-sidebar-expanded-width) minmax(0, 1fr)` (280px) | N/A (flex) |
| Body | N/A (shell manages own height) | Same |
| Topbar | `position: sticky; top: 0; z-index: 30` | Same |
| Sidebar | `position: relative; z-index: 20` in grid | `position: fixed; z-index: 90; transform(-110%)` |
| Shell DOM | `body > .layout-shell` | Same |
| Routing | Everything NOT `/u/*` or `/displays/*` | Same |

**Key insight**: Both shells converge to CSS Grid at desktop, but use different sidebar widths (272px vs 280px), different z-index systems, and different DOM structures. The admin shell has a richer responsive grid with collapse/peek animations.

---

## 3. Sidebar Models — Three Distinct Behaviors

Three sidebar implementations coexist:

### Sidebar Model 1: Operator Desktop (Grid-Column)
- Selector: `.app-shell .sidebar` (implied as grid child, no explicit position)
- Width: `var(--sidebar-width)` = 272px
- Position: Grid column — no `position` property, pushed by grid
- Scroll: Managed by grid row overflow (`.app-shell { overflow: hidden; }`)
- Breakpoint behavior: Becomes hamburger drawer on mobile

### Sidebar Model 2: Operator Mobile (Hamburger Drawer)
- Selector: `.hamburger-menu`
- Width: 280px
- Position: `position: fixed; left: 0; top: 64px; max-height: calc(100vh - 64px)`
- Z-index: 160
- Backdrop: `.hamburger-backdrop` at z-index: 150
- Transition: `transform: translateX(-100%) → translateX(0)`
- DOM: Separate from `.app-shell` grid (rendered beside it)

### Sidebar Model 3: Admin Shell (Dual-Mode)
- Selector: `.layout-sidebar`
- Width: `min(320px, calc(100vw - 58px))` on mobile; auto on desktop
- Desktop (≥901px): `position: relative; z-index: 20; height: 100%`
- Mobile (<901px): `position: fixed; z-index: 90; transform: translateX(-110%)`
- Backdrop: `.sidebar-backdrop` at z-index: 84 with `backdrop-filter: blur(3px)`
- Has collapse/peek behavior via `.sidebar-collapsed`/`.sidebar-peek` classes
- Transition: `transform .24s ease` on mobile; `grid-template-columns .28s` on desktop

---

## 4. Z-Index Landscape — Fragmented

**Total distinct z-index values: 24** (range: 0 to 100060)

### Operator Shell (`operator.css`) — Hardcoded values

| Value | Element(s) |
|---|---|
| 50 | Various inner elements |
| 60 | Various elements |
| 90 | Bottom nav elements |
| 100 | Topbar (sticky) |
| 120 | Mobile nav items |
| 129 | Action panel backdrop |
| 130 | Mobile action panel |
| 150 | Hamburger backdrop |
| 160 | Hamburger menu |
| 220 | Search wrap container |
| 260 | Search elements |
| 500 | Shell overlay container |
| 10060 | Camera scanner overlay |
| 1 (x5) | Icon layers, inner elements |

### Admin/Components Shell (`components.css`) — Hardcoded values

| Value | Element(s) |
|---|---|
| 0 | Image placeholders |
| 1 | Search icons, table th sticky, tile actions |
| 2 | Search controls |
| 20 | Sidebar (desktop/≥901px) |
| 30 | Topbar sticky, notification badge |
| 80 | Bottom nav, notification toast stack |
| 84 | Sidebar backdrop |
| 85 | Sidebar toggle dock |
| 90 | Sidebar (mobile), modal, mobile action panel |
| 100 | Notification toast |
| 110 | Sidebar mobile breakpoint |
| 150 | Search results dropdown |
| 1000 | Notification dropdown (desktop) |
| 9999 | Utility alert banner |

**Gaps identified**:
- No custom z-index properties (the `--z-*` variables from operator.css appear to have been removed; all values are hardcoded)
- Sidebar z-index differs across models: 160 (operator hamburger), 90 (admin sidebar mobile), 20 (admin sidebar desktop)
- Modal/dialog at z-index 90 conflicts with sidebar at 90 on admin mobile
- Camera scanner at 10060 is an outlier — no defined governance for extreme values
- No documented z-index stack hierarchy or reservation system

---

## 5. Overlay and Panel Systems — Three Mechanisms

### Mechanism 1: Shell Overlay (Operator Only)
- `operator.css:382` — `.shell-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 500; }`
- Purpose: Shared container for avatar panel, action panel, and future portal-rendered popups
- Uses `__overlayCount` JS counter for mutual exclusion
- Both `.topbar` (z-index: 100) and sidebar/hamburger (z-index: 160) sit below this
- `pointer-events: none` allows click-through to elements beneath

### Mechanism 2: Hamburger Drawer (Operator Mobile)
- Backdrop: `.hamburger-backdrop` at z-index: 150
- Drawer: `.hamburger-menu` at z-index: 160
- Outside-click handler for close
- Separate from shell overlay

### Mechanism 3: Local Panel/Dropdown (Both Shells)
- Search results: z-index: 150 (both shells)
- Notifications: z-index: 100 (toast), z-index: 1000 (dropdown desktop), z-index: 150 (mobile)
- Modals/dialogs: z-index: 90 with backdrop at 80
- Notification badge: z-index: 30
- Bottom nav: z-index: 80
- Mobile action panels: z-index: 90 or 130 (two implementations)

**Gaps**:
- Three overlay systems with no shared governance
- No overlay registry (z-index conflicts between sidebar=90 and modal=90 on admin mobile)
- Camera scanner overlay (z-index: 10060) exists outside all overlay systems
- Admin shell lacks a shell overlay equivalent — dropdowns manage their own z-index

---

## 6. Breakpoints and Responsive Contract

**26+ unique media query breakpoints** across 3 CSS files, with no centralized breakpoint map:

| Breakpoint | Usage |
|---|---|
| 420px | Compact table cards, stat rows |
| 480px | Single-column stat grids |
| 560px | Table cell padding |
| 640px | Coverage chart single col, dashboard grids |
| 700px | Layout adjustments |
| 720px | Topbar search KBD hide |
| 760px | Dashboard top grid, activity logs |
| 767.98px | Notification dropdown positioning |
| 768px | Bottom nav show, chart resize |
| 799px | Production focus form |
| 800px | Coverage KPI strip |
| 820px | Header wrap layout |
| 860px | Notification dropdown fixed position |
| 861px | Coverage KPI revert |
| 900px | Operator sidebar → hamburger |
| 901px | Admin sidebar position:relative → fixed |
| 920px | Dashboard container, coverage |
| 980px | Machine grids |
| 1024px | Left nav, content area |
| 1100px | Topbar search |
| 1200px | KPI columns, quick grid |
| 1280–1599.98px | Layout content |
| 1400px | Stat helpers |
| 1440px | Container max-width |
| 1600px | Layout content + container |
| 1920px | Layout content |

### Container Queries (6 total)
- `admin-dashboard` container: 3 breakpoints (760px, 767px, 920px, 1160px)
- All in `admin.css` — operator shell uses no container queries

**Gaps**:
- 26 breakpoints with no documented rationale or tier system
- Overlapping ranges (900px sidebar collapse vs 901px sidebar model switch)
- Operator uses `max-width` only; admin uses `min-width` for desktop-first
- No size tokens for breakpoints
- Container queries limited to admin dashboard only

---

## 7. Position and Scroll Behavior

### `position: fixed` Elements (18 identified)

| Element | Shell | Z-index | Scroll Impact |
|---|---|---|---|
| Camera scanner | Operator | 10060 | Page content non-interactive |
| Shell overlay | Operator | 500 | pointer-events: none |
| Hamburger menu | Operator | 160 | Chat UI, max-height constrained |
| Hamburger backdrop | Operator | 150 | pointer-events: none |
| Action panel backdrop | Operator | 129 | pointer-events: none |
| Mobile action panel | Operator | 130 | Fixed at bottom |
| Avatar panel | Operator | N/A | Fixed within shell overlay |
| Topbar refresh badge | Operator | N/A | Fixed position |
| Topbar (admin) | Admin | 30 | position: sticky |
| Sidebar (admin mobile) | Admin | 90 | Fixed drawer |
| Sidebar backdrop | Admin | 84 | backdrop-filter: blur |
| Notification dropdown | Admin | 1000 (desktop) / 150 (mobile) | Fixed |
| Topbar overflow menu | Admin | N/A | Fixed (mobile) |
| Sidebar toggle dock | Admin | 85 | Fixed bottom-left |
| Admin bottom nav | Admin | 80 | Fixed bottom |
| Operator bottom nav | Admin | 80 | Fixed bottom |
| Mobile action panel (admin) | Admin | 90 | Fixed bottom |
| Utility alert banner | Admin | 9999 | Fixed top |

### Scroll Lock Implementations (Two)

1. **Operator composer** (`OperatorSurfaceComposer.php`): JS-based `overflow: hidden` on `mainContent` + `body` + `__overlayCount` counter. Toggles `has-active-overlay` class.

2. **Components CSS** (`.scroll-lock`): `overflow: hidden` on body. Used by hamburger menu via class toggle.

**Gaps**:
- Two independent scroll-lock mechanisms may conflict when both fire
- Admin shell lacks a centralized scroll-lock mechanism
- 18 fixed elements create complex stacking and nesting risks
- No `overscroll-behavior` control on shell containers

---

## 8. Viewport Assumptions and Layout Values

### Viewport Height References
- `100vh` — Body (operator), `.layout-shell` (admin)
- `100dvh` — Body (operator), `.layout-sidebar` (admin mobile)
- `calc(100vh - 64px)` — Hamburger menu max-height
- `calc(100vh - var(--topbar-height) - var(--safe-area-top) - 16px)` — Search results
- `calc(100dvh - var(--topbar-height))` — Notif dropdown

### Hardcoded px Values
| Value | Usage |
|---|---|
| `58px` | Topbar height calc fallback |
| `64px` | Hamburger menu top offset |
| `68px` | Sidebar collapsed width (operator) |
| `72px` | Sidebar collapsed width (admin) |
| `272px` | Sidebar expanded width (operator) |
| `280px` | Sidebar expanded width (admin), hamburger width |
| `320px` | Sidebar max-width (admin base) |
| `44px` | Min touch target (buttons) |
| `10px` | Gap, padding multiples |
| `12px`, `16px`, `18px`, `22px`, `24px` | Layout padding values |
| `44px` x 2 | Action-groups-button dimensions |

### Safe Area Usage
- `var(--safe-area-top)`, `var(--safe-area-right)`, `var(--safe-area-bottom)`, `var(--safe-area-left)` — Applied to padding on topbar, sidebar, content, and bottom nav
- `env(safe-area-inset-bottom, 0px)` — Used in mobile padding (operator)

**Gaps**:
- No logical spacing or density tokens — all values are hardcoded px
- Topbar height derived from `var(--topbar-height) + var(--safe-area-top)` with no baseline in tokens
- Sidebar widths differ between shells (272px vs 280px) with no documented rationale

---

## 9. Gaps and Recommendations

### Critical Gaps (Must Resolve Before Contract)

| ID | Gap | Shell(s) | Risk |
|---|---|---|---|
| G-01 | No unified Shell Behavior & Rendering Contract | Both | High |
| G-02 | 3 sidebar models with different z-index, layout, and breakpoint behaviors | Both | High |
| G-03 | 24 raw z-index values, no custom properties, no documented reservation system | Both | High |
| G-04 | 26+ breakpoints with no centralized map or size tokens | Both | Medium |
| G-05 | 3 overlay mechanisms with no shared governance | Operator | Medium |

### Moderate Gaps (Should Document in Contract)

| ID | Gap | Shell(s) | Risk |
|---|---|---|---|
| G-06 | Sidebar widths differ (272px operator vs 280px/320px admin) | Both | Low |
| G-07 | Two independent scroll-lock implementations | Operator | Low |
| G-08 | Camera scanner z-index (10060) is ungoverned outlier | Operator | Low |
| G-09 | Modal z-index (90) conflicts with sidebar z-index (90) on admin mobile | Admin | Low |
| G-10 | No `overscroll-behavior` governance on shell containers | Both | Low |
| G-11 | Admin shell lacks a shell overlay equivalent | Admin | Low |

### Minor Gaps (Document for Future)

| ID | Gap | Shell(s) |
|---|---|---|
| G-12 | All layout values are hardcoded px — no density tokens | Both |
| G-13 | Container queries only exist for admin dashboard | Admin |
| G-14 | 18 fixed-position elements across both shells | Both |
| G-15 | No anchor positioning (CSS `anchor()` not used) | Both |
| G-16 | Hover-based peek on collapsed sidebar not accessible | Admin |

### Recommended Contract Sections (Prompt 2/4)

1. **Shell model unification** — Single grid contract for both shells at desktop with explicit sidebar width token, unified z-index wallet, shared overlay contract
2. **Z-index wallet** — Reserved ranges with custom properties: `--z-sidebar`, `--z-dropdown`, `--z-overlay`, `--z-modal`, `--z-toast`, `--z-camera`, with `--z-max` governor
3. **Overlay contract** — Shared overlay registry, mutual exclusion guarantees, `pointer-events: none` contract, backdrop governance
4. **Breakpoint tokens** — Centralized size tokens: `--bp-mobile`, `--bp-tablet`, `--bp-desktop`, `--bp-wide`
5. **Sidebar contract** — Unified width token, single scroll model, mobile drawer governance, collapse/peek accessibility
6. **Scroll lock contract** — Single mechanism, overlay count integration, body class governance, `overscroll-behavior` rules
7. **Safe area contract** — Standard application pattern across all wrapper surfaces

---

## Audit Methodology

- **CSS analysis**: Full scan of `operator.css` (2156 lines), `components.css` (~3904 lines), `admin.css` (907 lines), `shell.css` (139 lines), `setup.css` (134 lines)
- **Template analysis**: `header.php`, `sidebar.php`, `footer.php`, `layout-footer.php`, wrapper views, display views
- **Composer analysis**: `AdminSurfaceComposer.php`, `OperatorSurfaceComposer.php`, `OperatorLayerWrapperComposer.php`
- **Architecture doc cross-reference**: Charter v1, Surface Contribution Contract, Theme Architecture V1, Style Customization Chain Checkpoint
- **Subagent tasks**: 3 parallel exploration agents (layout/PHP, CSS analysis, component CSS deep scan)

All validation passes: `git diff --check` ✅, architecture gates (24/24) ✅, deployment readiness ✅
