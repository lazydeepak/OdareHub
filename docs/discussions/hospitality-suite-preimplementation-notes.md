# Hospitality Suite Pre-implementation Notes

Status: Discussion note. Not approved implementation scope by itself.

Date captured: 2026-08-23

Related planning baseline:

- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`

## Context

Hospitality Suite has been discussed as a future Susankhya OS product track. The main risk is starting it with unclear ownership vocabulary and accidentally creating another mixed layer of app, suite, plugin, and module terminology.

## Confirmed Direction

Hospitality Suite is the product/composition name.

Runtime implementation should start as:

```text
Product name: Hospitality Suite
Runtime owner: Hospitality App
App key: hospitality
Code location: apps/Hospitality
```

Do not create a first-class `suites/` runtime layer yet.

## Initial App Shape

The first runtime-compatible shape under discussion is:

```text
apps/Hospitality/
  manifest.json
  routes.php
  modules/
    Reservations/
    Rooms/
    FrontDesk/
    Housekeeping/
  extensions/
    Billing/
    Items/
    Inventory/
```

`extensions/` is still tentative. Loader support must be checked before relying on it. If needed, extensions can initially be represented as runtime-compatible modules with explicit ownership metadata.

## First Usable Scope

The first Hospitality version should stay limited to:

- rooms
- guests
- reservations
- check-in and check-out
- housekeeping status
- basic folio and charges

## Deferred Shared Apps

These may become shared apps later, but should not block the first usable Hospitality App:

- Billing
- Items
- Inventory
- Parties
- Accounting

The first Hospitality implementation may need basic local versions or app extensions, but it should not pretend those are mature shared apps.

## Important Boundaries

- Do not modify Core for Hospitality unless a platform law requires it and explicit approval is given.
- Do not add a `suites/` loader.
- Do not rename existing app/module/plugin runtime concepts.
- Do not make Studio generate Hospitality as the primary creation path.
- Do not polish sample/reference apps unless the work exposes a platform architecture issue.
- Keep App as runtime truth until a real Suite registry, manifest, and lifecycle service exist.

## Readiness Questions

Before implementation starts, resolve:

- Is Procurement a Shared App or Domain App?
- Where should Hospitality app extensions live for the first version?
- Which shared apps are deferred and which are required?
- Is Manufacturing `Products` a Manufacturing-owned Parts module or a future shared Items/Product app seed?
- Which existing `suite` wording is legacy metadata and which is product/composition language?

## Possible Graduation Path

1. Finish ownership classification readiness.
2. Create `docs/active/hospitality-app-foundation.md`.
3. Define the smallest app skeleton and module list.
4. Add runtime-compatible manifests/routes without Core changes.
5. Validate with app/module ownership gates.

