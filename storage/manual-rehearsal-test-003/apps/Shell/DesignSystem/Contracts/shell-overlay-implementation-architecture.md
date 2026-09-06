# Shell Overlay — Minimal Implementation Architecture

## Decision

Shell owns one browser controller: `SusankhyaOS.ShellOverlay`.

Candidates do not implement generic overlay policy. They supply an ID, a Shell-owned preset, DOM bindings, and content-specific callbacks only where required.

## Shell-owned definitions

- `dropdown`: local non-modal surface.
- `drawer`: modal page surface with scroll lock.
- `sidebar`: page surface without Escape or scroll lock.
- `viewport`: full-viewport surface without Escape.

Each definition owns backdrop, Escape, outside-click, scroll-lock, focus-restoration, and visual-preset defaults.

## Public controller

- `manager.open(config)`
- `manager.close(id)`
- `manager.toggle(config)`
- `manager.closeTop()`
- `manager.isOpen(id)`

The controller owns one active-instance map and one ordered ID stack. Open and close operations are idempotent.

## Ownership boundary

Shell owns generic overlay mechanics, shared definitions, visual presets, z-layer tokens, and the canonical host. Candidates own their content, content-specific keyboard interaction, and DOM bindings.

No parallel PHP lifecycle simulation, candidate-specific stack, or separate public lifecycle namespace is permitted.

## Migration rule

Legacy candidate handlers remain only until the corresponding controller behavior is wired and browser-parity tested. Generic behavior must not have two runtime authorities after candidate migration.
