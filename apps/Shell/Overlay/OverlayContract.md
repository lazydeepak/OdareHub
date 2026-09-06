# Shell Overlay Framework — Overlay Contract (v1)

## Goal
Define the canonical Shell overlay contract so all Shell-owned overlays share:
- one overlay host
- one activation lifecycle model
- one backdrop + portal rendering contract
- one accessibility baseline
- one z-layer / stacking token set
- one focus + escape + scroll-lock contract

This phase is **infrastructure only**. No migrations to existing overlays.

## Non-goals (Phase 1)
- No app migrations
- No owner migrations
- No CSS cleanup outside Shell
- No JS rewrites outside Shell
- No changes to current overlay behavior

## Canonical Overlay Host
- Host element (Shell-owned portal container):
  - `div.shell-overlay#shellOverlay[data-shell-overlay-surface="v1"]`
- Location: `public/views/layouts/footer.php` (Shell already provides this)

### Contract invariants
1. There must be exactly **one** `#shellOverlay` on the page.
2. The host must be fixed viewport container with pointer-events disabled at base layer.
3. Host must remain Shell-owned (apps never create/duplicate it).

## Validation hooks (probes)
- Probe scripts assert host existence & uniqueness.
- Probe scripts assert Shell overlay manager/focus contract JS entrypoints exist (Phase 1).

