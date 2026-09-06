# Operator Avatar Menu Viewport Fix Audit (2026-06-05)

## Scope
Operator shell avatar/profile menu only on `/u/{username}/dashboard`.

## Root cause
Avatar panel used static right offsets and viewport width assumptions (`right: 6px`, `width: min(340px, 92vw)`), which could allow edge clipping on narrow/safe-area-constrained viewports.

## Fix
Adjusted operator avatar panel positioning/sizing in shell CSS to be viewport-safe while preserving existing design:
- Safe right anchoring with clamp:
  - `right: clamp(8px, calc(8px + var(--safe-area-right)), 24px)`
- Viewport-safe width and max-width:
  - `max-width: calc(100vw - var(--safe-area-left) - var(--safe-area-right) - 24px)`
- Mobile override updated with same safe-area-aware bounds.
- Added explicit `left: auto` in overlay context to avoid accidental offscreen placement.

## Files changed
- `apps/Shell/styles/operator.css`

## Validation summary
- Browser smoke (`/u/lazydeepak/dashboard`): avatar panel fully visible on desktop and mobile viewport checks.
- Horizontal overflow: none detected in open-panel state.
- Overlay behavior: unchanged by this slice (no JS or overlay-logic edits).
- `git diff --check`: PASS
- Architecture gates: PASS
- Deployment readiness: PASS
