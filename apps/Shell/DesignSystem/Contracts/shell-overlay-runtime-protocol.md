# Shell Overlay Runtime Protocol

## Open

`manager.open(config)` resolves the Shell definition, returns the existing instance for a duplicate ID, records the instance, acquires definition-owned scroll lock, applies the DOM callback, and updates visual state.

## Close

`manager.close(id, reason)` is idempotent. It removes the instance, releases scroll lock, runs the close callback, restores trigger/previous focus when enabled, clears visual state as needed, and emits the compatibility close event.

## Dismissal

- One document Escape listener closes only the top eligible instance.
- One document click listener closes only the top eligible outside-dismissible instance.
- Trigger and surface bindings are excluded from outside dismissal.
- `outsideSurfaceSelf` supports roots that also act as their backdrop.
- Content-sensitive candidates may disable generic Escape or focus restoration.

## Definitions

- `dropdown`: Escape/outside enabled, no backdrop or scroll lock, local visual.
- `drawer`: Escape/outside enabled, backdrop and scroll lock enabled, page visual.
- `sidebar`: outside enabled, Escape/scroll lock disabled, page visual.
- `viewport`: root-outside behavior configurable, Escape/scroll lock disabled, viewport visual.

Candidate code must not repeat these defaults.
