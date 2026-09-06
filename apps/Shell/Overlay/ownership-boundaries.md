# Shell Overlay Framework — Ownership boundaries (Phase 1)

## Shell owns
- `#shellOverlay` portal host and the `.shell-overlay` container contract.
- Overlay Manager and Registry infrastructure (APIs + stubs).
- Backdrop, focus, scroll-lock, escape, and portal rendering contracts (as interfaces).
- Z-layer token usage rules (no new z-index hardcoding outside Shell tokens).

## Apps own
- Overlay payload selection (type + content).
- Overlay markup content and ARIA labels/semantic text.
- Trigger elements (`aria-expanded` on buttons) and any direct user interactions.

## Forbidden in Phase 1 (by requirement)
- No behavior changes to existing overlays.
- No migrations of Avatar Menu, Notifications, Search, Action Panel, Mobile Action Sheet.
- No CSS cleanup outside Shell.
- No JS rewrites outside Shell.

## Allowed Phase 1 changes
- Adding new Shell-only files (docs + PHP stubs + JS infrastructure stubs).
- Adding new Shell-only probes/gates that validate presence of the infrastructure.

