# Shell Overlay Framework Architecture

## Runtime owner

`SusankhyaOS.ShellOverlay` is the single browser runtime and policy authority.

It owns:

- four definitions: `dropdown`, `drawer`, `sidebar`, `viewport`;
- one active-instance `Map` and ordered ID stack;
- idempotent open/close/toggle operations;
- top-eligible Escape and outside dismissal;
- focus restoration;
- reference-counted scroll lock;
- visual preset selection and strength control.

## Candidate boundary

A candidate supplies only:

- stable `id`/`type`;
- Shell definition name (`preset`);
- trigger/surface/backdrop bindings;
- small DOM open/close callbacks;
- explicit policy overrides only for content-sensitive behavior, such as search-owned Escape.

Candidates retain content rendering and content-specific interaction. They do not own a registry, stack, generic dismissal, scroll lock, or visual policy.

## Public API

- `manager.open(config)`
- `manager.close(id, reason)`
- `manager.toggle(config)`
- `manager.closeTop(reason, eligibility)`
- `manager.isOpen(id)`
- `visualEffects.setStrength(value)`
- `visualEffects.clearStrengthOverride()`
- `visualEffects.getState()`

There is no separate runtime registry or PHP behavioral mirror.
