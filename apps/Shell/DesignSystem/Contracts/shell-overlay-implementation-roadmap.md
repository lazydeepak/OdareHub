# Shell Overlay — Minimal Migration Roadmap

## Target

One Shell-owned dynamic controller fed by small candidate bindings.

## Status

Implementation complete. All ten inventoried candidates use the single controller and Shell-owned definitions. Generic Escape/outside dismissal, focus restoration, reference-counted scroll lock, visual activation, and strength control are centralized. Candidate-local code is limited to DOM/content callbacks and content-sensitive behavior.

The adapter files remain as named binding factories so large composers do not duplicate binding construction. They are not lifecycle or policy authorities.

## Guardrails

- No new overlay service hierarchy or PHP behavioral mirror.
- No candidate-owned z-index, backdrop policy, focus policy, or scroll-lock mechanism.
- No database, route, controller, or business-domain changes.
- Each migration must preserve markup, content behavior, accessibility state, and visual parity.
