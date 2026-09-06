# Shell Overlay Framework — Ownership boundaries (Phase 1)

## Shell owns
- `#shellOverlay` portal host and the `.shell-overlay` container contract.
- Overlay Manager orchestration and Registry infrastructure (APIs + stubs).
- Portal, stack, backdrop, focus, dismissal, escape, and scroll-lock contracts (as Shell-owned services or service interfaces).
- Z-layer token usage rules (no new z-index hardcoding outside Shell tokens).

### Shell ownership rules
- Manager coordinates overlay lifecycle and delegates specialized behavior.
- Manager must not become the long-term owner of every behavior implementation.
- Registry owns overlay type metadata only; it must not own live instance state.
- Portal owns host resolution and mount/unmount invariants.
- Stack owns deterministic overlay ordering for escape, focus, and outside-click decisions.
- Focus owns capture, transfer, restoration, and focus-trap policy.
- Backdrop owns framework backdrop rendering invariants and nested backdrop participation.
- Dismissal owns escape and outside-click routing rules.
- ScrollLock owns reference-counted scroll-lock acquisition and release.

## Apps own
- Overlay payload selection (type + content).
- Overlay markup content and ARIA labels/semantic text.
- Trigger elements (`aria-expanded` on buttons) and any direct user interactions.

### App boundary rules
- Apps must not create or duplicate the Shell overlay host.
- Apps must not own framework stack, focus, dismissal, backdrop, portal, or scroll-lock behavior.
- Apps may keep legacy behavior until a compatibility adapter exists and a migration is approved.

## Forbidden in Phase 1 (by requirement)
- No behavior changes to existing overlays.
- No migrations of Avatar Menu, Notifications, Search, Action Panel, Mobile Action Sheet.
- No CSS cleanup outside Shell.
- No JS rewrites outside Shell.

## Allowed Phase 1 changes
- Adding new Shell-only files (docs + PHP stubs + JS infrastructure stubs).
- Adding new Shell-only probes/gates that validate presence of the infrastructure.

## Authority map
- Contract owns framework invariants.
- Runtime Protocol owns lifecycle, APIs, state transitions, events, stack behavior, and error semantics.
- Inventory owns observed current implementations and must label unknowns as `Needs Classification`.
- Ownership owns responsibility boundaries and forbidden authority transfers.
