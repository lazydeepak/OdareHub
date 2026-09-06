# Shell Overlay Contract

## Goal

All Shell overlays use one host, one controller, one definition set, one visual/token system, and one generic lifecycle authority.

## Canonical host

Exactly one `div.shell-overlay#shellOverlay[data-shell-overlay-surface="v1"]` is rendered by Shell.

## Definitions

Shell owns `dropdown`, `drawer`, `sidebar`, and `viewport`. Candidates select a definition; they do not repeat its backdrop, dismissal, scroll-lock, focus, or visual defaults.

## Controller ownership

Shell owns active instances, ordering, generic Escape/outside dismissal, focus restoration, reference-counted scroll lock, and visual state.

Candidates own content, semantic labels, DOM bindings/callbacks, and content-specific interaction such as search navigation or camera cleanup.

## Accessibility

Candidate markup retains the correct semantic role, accessible name, `aria-modal` where applicable, and trigger `aria-expanded`. The controller restores trigger focus where the selected definition enables it.

## Tokens

Overlay CSS consumes Shell z-layer and visual tokens. Candidates must not introduce private overlay z-index systems or generic visual policy.

## Exceptions

An explicit candidate override is allowed only when behavior is content-sensitive and cannot be represented by its shared definition. Current examples are search-owned Escape and disabled focus restoration on outside search dismissal.
