# CLEANUP & LIFECYCLE

## Zero Junk Rule

No unused:
- routes
- controllers
- UI components
- permissions
- hooks
- reports
- widgets
- dashboard cards
- localization keys for removed surfaces

---

## Install

- register only declared features
- validate all routes resolve
- apply required schema or migrations
- register permissions
- keep runtime surfaces inactive unless explicitly activated

Installed does not mean active.

---

## Activation

When active, the module may expose:

- routes
- menus
- widgets
- reports
- dashboards
- exports
- scheduled jobs

Activation must validate dependencies and required schema state.

---

## Deactivation

When inactive, the module must remove or suppress runtime surfaces:

- routes must not expose active business actions
- menus must disappear or show controlled unavailable state
- widgets and dashboard cards must not load live module actions
- reports and exports must not run as active module features

Data and schema may remain.

---

## Update

- remove deprecated routes
- update references
- remove duplicates
- migrate reports and widgets with their owning module
- keep compatibility aliases temporary and documented

---

## Removal

Must remove:
- routes
- navigation
- widgets
- permissions
- hooks
- reports
- exports
- scheduled jobs
- dashboard cards

System must behave as if feature never existed.

Destructive data purge must be explicit and separate from uninstall unless approved by the module contract.

---

## Rule

No orphaned artifacts allowed.
No "cleanup later".

Lifecycle behavior must match `docs/architecture/MODULE-CONTRACT.md`.
