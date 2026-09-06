# Shell Overlay Adapter Contract

## Purpose

Adapters are temporary, thin bindings between existing candidate DOM/content and `SusankhyaOS.ShellOverlay`.

## Allowed inputs

- stable overlay type/ID;
- one Shell-owned definition name;
- trigger, surface, backdrop, or scroll-target selectors/IDs;
- small open/close callbacks;
- narrowly justified content-sensitive overrides.

## Allowed controller interaction

- `manager.open(config)`
- `manager.close(id, reason)`
- candidate-local `syncState` compatibility while legacy callers remain

## Forbidden adapter ownership

Adapters must not own or repeat:

- definition metadata;
- active stack/order;
- generic Escape or outside routing;
- scroll-lock counters;
- visual strength/effect policy;
- a registration or session subsystem.

## Removal condition

An adapter may be removed when its caller can pass the same binding directly to the controller and browser parity confirms its content, accessibility, responsive, and cleanup behavior.
