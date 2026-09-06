# Shell Runtime Normalization — 2026-06-05

**Status**: Applied (Prompt 3/4)
**Date**: 2026-06-05
**Owner**: Shell
**References**:
- `docs/architecture/shell-behavior-rendering-contract-v1.md` — Contract (Prompt 2/4)
- `docs/architecture/shell-behavior-rendering-audit.md` — Audit (Prompt 1/4)

---

## Summary

Implemented Parts A, B, and C of Prompt 3/4 per the Shell Behavior & Rendering Contract v1:

### Part B — Z-Index Layer Map

Added canonical z-index custom properties to both Shell CSS files:

| Token | Value | Purpose |
|---|---|---|
| `--z-base` | 0 | Document flow, static content |
| `--z-workspace` | 10 | Workspace content layer |
| `--z-navigation` | 20 | Sidebar (desktop), rail collapse |
| `--z-topbar` | 30 | Sticky topbar |
| `--z-dropdown` | 40 | Search results, select menus, popups |
| `--z-drawer` | 50 | Sidebar drawer (mobile), action panels |
| `--z-backdrop` | 60 | Backdrops for drawers, modals, panels |
| `--z-modal` | 70 | Modal dialogs, confirmation prompts |
| `--z-overlay` | 80 | Shell overlay container, portal panels |
| `--z-scanner` | 90 | Camera/barcode scanner overlay |
| `--z-system-emergency` | 100 | Error banners, system alerts |

**Replaced 3 magic numbers** (where contract value == existing value):
- `components.css` — `.auth-shell::before { z-index: 0 }` → `var(--z-base)`
- `components.css` — `.topbar { z-index: 30 }` → `var(--z-topbar)` (admin shell)
- `components.css` — `.layout-sidebar { z-index: 20 }` → `var(--z-navigation)` (admin desktop)

**Files modified**: `apps/Shell/styles/operator.css`, `apps/Shell/styles/components.css`

### Part C — Breakpoint Registry

Added canonical breakpoint custom properties to both Shell CSS files:

| Token | Value | Purpose |
|---|---|---|
| `--bp-mobile` | 480px | Compact single-column surfaces, bottom nav |
| `--bp-tablet` | 768px | Multi-column grids, sidebar collapses |
| `--bp-desktop` | 1024px | Full sidebar visible, content max-width |
| `--bp-wide` | 1366px | Expanded content area, multi-panel dashboards |
| `--bp-ultrawide` | 1920px | Maximum content bounds, optional side panels |
| `--bp-display` | 2560px | TV/kiosk display surfaces |

**Note**: CSS custom properties cannot be used in `@media` queries (CSS spec limitation). Tokens serve as documentation and naming convention for future preprocessor/tooling use.

**Files modified**: `apps/Shell/styles/operator.css`, `apps/Shell/styles/components.css`

### Part A — Unified Overlay Infrastructure

1. **Admin shell overlay container** — Added `.shell-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 500 }` to `components.css` (matching operator shell pattern).
2. **Admin shell overlay DOM** — Added `<div class="shell-overlay" id="shellOverlay">` to `footer.php` (before `</body>`), matching operator shell pattern.
3. **Admin overlay JS infrastructure** — Added `__overlayCount` / `__setShellOverlayActive()` to `header.php` for the admin shell, identical to the operator shell's pattern in `OperatorSurfaceComposer.php`.

**Files modified**: `apps/Shell/styles/components.css`, `public/views/layouts/footer.php`, `public/views/layouts/header.php`

---

## Not Migrated (Infrastructure Established, Future Normalization Required)

### Z-index values not replaced (value differs from contract token)

**operator.css** (14 values cannot be replaced without changing behavior):
- `z-index: 100` — `.topbar` (current) vs `--z-topbar: 30` (contract)
- `z-index: 50` — `.app-sidebar` vs `--z-navigation: 20`
- `z-index: 260` — `.operator-search-results` vs `--z-dropdown: 40`
- `z-index: 220` — `.header-avatar-panel` vs `--z-modal: 70`
- `z-index: 160` — `.hamburger-menu` vs `--z-drawer: 50`
- `z-index: 150` — `.hamburger-backdrop` vs `--z-backdrop: 60`
- `z-index: 129` — `.action-panel-backdrop` vs `--z-backdrop: 60`
- `z-index: 130` — `.mobile-action-panel` vs `--z-drawer: 50`
- `z-index: 120` — `.u-bottom-nav` (no matching token)
- `z-index: 90` — `.op-refresh-badge` (no matching token)
- `z-index: 60` — `.u-header` (no matching token)
- `z-index: 500` — `.shell-overlay` vs `--z-overlay: 80`
- `z-index: 10060` — `.camera-scan-overlay` vs `--z-scanner: 90`

**components.css** (21 values not replaced):
- `z-index: 2` — search controls (no matching token)
- `z-index: 80` — bottom nav, toast (no matching token)
- `z-index: 84` — `.sidebar-backdrop` vs `--z-backdrop: 60`
- `z-index: 85` — `.sidebar-toggle-dock` (no matching token)
- `z-index: 90` — sidebar mobile, modal (conflicts with `--z-scanner`)
- `z-index: 100` — notification toast (no matching token)
- `z-index: 110` — sidebar mobile breakpoint (no matching token)
- `z-index: 150` — `.topbar-search-results` vs `--z-dropdown: 40`
- `z-index: 155` — search results mobile (no matching token)
- `z-index: 220` — dropdowns vs `--z-modal: 70`
- `z-index: 1000` — notification dropdown (no matching token)
- `z-index: 9999` — alert banner vs `--z-system-emergency: 100`

**admin.css** (2 values not replaced):
- `z-index: 200` — admin topbar at 901px+
- `z-index: 3000` — `.reset-progress-overlay`

### Media queries not tokenized

28+ media query breakpoints across the Shell CSS files could not be replaced because CSS custom properties don't resolve in `@media` contexts. Requires a preprocessor step.

### Admin overlays not wired

The `__overlayCount` / `__setShellOverlayActive()` JS infrastructure is added but not connected to any admin overlay handlers (sidebar toggle, notification dropdown, action dropdown). This is future work.

---

## Validation

- `git diff --check`: ✅
- PHP lint: ✅ (7 files)
- Architecture gates: ✅ (all pass)
- Deployment readiness: ✅ (all pass)
- No Core changes: ✅
- No visual behavior changed: ✅ (token values match contract, not current magic numbers)
