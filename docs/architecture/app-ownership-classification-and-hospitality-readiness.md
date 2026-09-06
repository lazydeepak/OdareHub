# App Ownership Classification and Hospitality Readiness

Status: Planning baseline. No runtime behavior. No file moves. No PHP changes.

Date: 2026-08-17

## Context

The repository has accumulated mixed terminology across several eras:

- app, bundle, and suite have sometimes described the same lifecycle object
- plugin and module have sometimes overlapped for business features
- some runtime services still use `suite` as legacy grouping metadata
- current app manifests and runtime loaders recognize apps as the installable unit

Before starting Hospitality, the system needs a clear ownership model so new work does not add another layer of mixed terminology.

## Decision

Use **App** as the current runtime and lifecycle truth.

Use **Suite** only as product/composition language until a real suite registry, manifest, and lifecycle service exist.

For Hospitality:

```text
Product name: Hospitality Suite
Runtime owner: Hospitality App
App key: hospitality
Code location: apps/Hospitality
```

Do not create a first-class `suites/` runtime layer yet.

## Ownership Types

Every existing and future capability should be classified into one of these ownership types:

| Type | Meaning | Examples |
|---|---|---|
| Core Engine | runtime foundation, not an app | auth, routing, DB, app loader, permission enforcement |
| System App | installable system surface app | Shell, Platform, Studio |
| Platform Engine | reusable non-business capability engine | Reports, Search, Style, Labels |
| Shared App | reusable business app across multiple domains | future Billing, Items, Inventory, Parties, Accounting |
| Domain App | business/domain-specific runtime app | Manufacturing, SBAIO, Hospitality |
| App Module | feature area owned by exactly one app | Manufacturing Daily Orders, Hospitality Rooms |
| App Extension | domain-owned augmentation of a shared app | future Hospitality Billing Extension |
| Studio Tool | governed authoring, diagnosis, or upgrade tool | Report Designer, Customization Studio |
| Plugin / Adapter | optional technical provider or integration | PDF adapter, notification channel, connector |
| Legacy Bridge | compatibility layer from older plugin/module architecture | legacy bridge plugins under app lifecycle |

## Suite Boundary

A future first-class Suite would be a composition and lifecycle parent, not merely a label.

It would manage:

- the suite/domain app
- suite-owned modules
- suite-owned extensions
- required shared app dependencies
- coordinated install, enable, upgrade, disable, and uninstall behavior

Until that exists, `Hospitality App` acts as the runtime host for the product called `Hospitality Suite`.

## Hospitality Shape

Initial runtime-compatible shape:

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

`extensions/` is a proposed ownership convention. Loader support must be checked before relying on it. If needed, extensions may initially be implemented as runtime-compatible modules with explicit `ownership_type = app_extension` metadata.

## Studio Direction

Studio remains a long-term system doctor and architect workbench.

Studio should eventually:

- inspect apps, modules, extensions, plugins, routes, permissions, reports, styles, labels, and schemas
- classify ownership types
- detect inconsistencies
- recommend upgrades
- prepare migration proposals
- validate and snapshot proposed changes

Studio should not be the primary creator of Hospitality yet. The repository must first have a settled ownership model that Studio can understand.

Rule:

```text
Studio may diagnose and propose changes across owners.
Studio must not silently take ownership away from apps, modules, extensions, or platform engines.
```

## Readiness Gate Before Hospitality

Before implementation starts:

- [ ] classify current apps, modules, plugins, platform engines, and Studio tools by ownership type
- [ ] identify legacy `suite` and `bundle` wording that is lifecycle vocabulary rather than first-class suite architecture
- [ ] decide whether `Procurement` is a Shared App or Domain App
- [ ] record that Manufacturing `Products` is currently a Manufacturing-owned Parts module, not a shared Products app
- [ ] define where App Extensions live for the first Hospitality version
- [ ] document which shared apps are deferred versus required for the first usable Hospitality App
- [ ] keep Hospitality first usable scope limited to rooms, guests, reservations, check-in/check-out, housekeeping status, and basic folio/charges

## Non-Goals

This planning baseline does not authorize:

- adding a `suites/` loader
- changing app/module/plugin runtime behavior
- renaming existing apps or modules
- moving Manufacturing, SBAIO, Procurement, Shell, Platform, or Studio files
- making Studio generate Hospitality
- creating shared Billing, Items, Inventory, Parties, or Accounting apps immediately
