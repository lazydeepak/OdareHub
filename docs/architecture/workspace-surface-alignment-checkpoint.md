# Workspace Surface Alignment Checkpoint

## 1. Surface Terminology Standard

The Shell UI is composed of four distinct surfaces:

| Surface | Purpose |
|---|---|
| **Topbar Surface** | App-level chrome: branding, search, notifications, avatar, scan |
| **Navigation Surface** | Wayfinding: sidebar, hamburger drawer, bottom nav, action panels |
| **Workspace Surface** | Primary content area: dashboard, views, forms, tables |
| **Overlay Surface** | Floating popups: avatar panel, action tray, dialogs |

## 2. DOM Ownership Mapping

| Surface | Canonical class | Compatibility alias | Notes |
|---|---|---|---|
| **Topbar Surface** | `.topbar` | — | Sticky header at z-index 100 |
| **Navigation Surface** | `.app-sidebar` | — | Desktop contextual sidebar (z-index 50, inside `.app-shell`) |
| | `.hamburger-menu` | — | Mobile drawer (z-index 160, outside `.app-shell`) |
| | `.hamburger-backdrop` | — | Mobile drawer scrim (z-index 150) |
| | `.u-bottom-nav` | — | Mobile bottom bar (z-index 120) |
| | `.action-panel-backdrop` | — | Action tray scrim (z-index 129) |
| | `.mobile-action-panel` | — | Action tray panel (z-index 130) |
| **Workspace Surface** | `.workspace-surface` | `.main-content` | Scrollable content area (z-index 1, inside `.app-shell`) |
| **Overlay Surface** | `.shell-overlay` | — | Fixed container at z-index 500 for portal popups |
| | (within overlay) `.avatar-backdrop` | — | Visual scrim (no blur) |
| | (within overlay) `.header-avatar-panel` | — | Interactive panel |

## 3. Overlay Activation Contract

When an **Overlay Surface** component is active (via `body.has-active-overlay`):

- **Blur** Shell layers marked `.shell-inactive-layer`
- **Blur** inactive Topbar, Navigation, and Workspace layers
- **Do not blur** the Overlay Surface
- **Do not blur** the active overlay panel or drawer itself

The blur is controlled by shared Shell state (`__overlayCount` counter) to prevent race conditions when overlays open/close concurrently.

## 4. Implementation Checkpoint

### Before (blurred entire app-shell, including sidebar):

```css
body.has-active-overlay .app-shell { filter: blur(3px); }
```

### Interim (blur shrunk to main-content, but name ambiguous):

```css
body.has-active-overlay .main-content { filter: blur(3px); }
```

### Superseded workspace-only state:

```css
body.has-active-overlay .workspace-surface { filter: blur(3px); transition: filter 0.2s ease; }
```

### Current accepted state (inactive Shell layer contract):

```css
body.has-active-overlay .shell-inactive-layer {
    filter: var(--glass-blur-shell);
    transition: filter 0.2s ease;
}
```

### Enforcement

Overlay activation blur is centralized in `apps/Shell/styles/components.css`. Inactive Shell layers opt in with `.shell-inactive-layer`; active panels, drawers, and portal overlays must remain outside that marker. Operator-specific backdrop and panel placement remains in `apps/Shell/styles/operator.css`.

## 5. Migration Status

| Phase | Scope | Status |
|---|---|---|
| **Phase 1** | Add `.workspace-surface` alias; move blur target to canonical name; update tests | ✅ **Complete** |
| **Phase 1.1** | Move shared blur law to `components.css`; blur explicitly marked inactive Shell layers | ✅ **Complete** |
| **Phase 2** | Migrate JS `document.querySelector('.main-content')` calls → `.workspace-surface` | ⏸️ **Deferred** — no business need currently |
| **Phase 3** | Remove `.main-content` compatibility alias | ⏸️ **Deferred** — requires full dependency audit before removal |

### Current verification

- Blur target: `body.has-active-overlay .shell-inactive-layer`
- Operator inactive layers: topbar, app shell, bottom navigation
- Active panels: unmarked and sharp
- Layout rules: still target `.main-content` (unchanged)
- JS queries: still reference `.main-content` (compatible via class alias)

## 6. Architecture Guidance

Future agents **must**:

- Use **"Workspace Surface"** terminology (not "main content area" or "app shell")
- Target `.workspace-surface` for workspace-only behaviors (dim, visibility)
- **Avoid** targeting `.app-shell` for workspace-only effects — `.app-shell` also contains the desktop sidebar (Navigation Surface)
- Treat `.workspace-surface` as the canonical Workspace Surface identifier
- Preserve `.main-content` compatibility alias until Phase 3 explicitly removes it
- When adding new overlay components, wire them to the shared `__setShellOverlayActive()` contract instead of implementing independent blur logic
- Mark inactive Shell layers with `.shell-inactive-layer`; shared overlay activation CSS belongs in `apps/Shell/styles/components.css`
- Keep active overlay panels outside `.shell-inactive-layer` so they remain sharp

### Prohibited patterns

```
body.has-active-overlay .app-shell { ... }      /* BANNED — blurs sidebar */
body.has-active-overlay .main-content { ... }    /* LEGACY — use workspace-surface */
body.has-active-overlay .workspace-surface { filter: ... } /* LEGACY overlay blur law */
```

### Allowed pattern

```
body.has-active-overlay .shell-inactive-layer { ... }  /* CANONICAL */
```

## 7. References

- `apps/Shell/styles/components.css` — shared inactive-layer overlay activation law
- `apps/Shell/styles/operator.css` — operator-specific overlay placement only
- `apps/Shell/Composers/OperatorSurfaceComposer.php` — inactive Shell layer markers and `__setShellOverlayActive()`
- `apps/Shell/Services/OperatorLayerWrapperComposer.php` — action panel wired to shared overlay state (lines 385, 399)
- `AGENTS.md` — session summary documenting the contract (lines 485–489)
