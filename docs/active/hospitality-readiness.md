# Hospitality Suite Readiness

Status: Active planning task. No runtime behavior. No app skeleton. No PHP changes.

Owner: Architecture / Product planning

Started: 2026-08-23

## Objective

Prepare OdareHub for a future Hospitality Suite implementation by settling ownership language, runtime boundaries, first usable scope, and readiness checks before any `apps/Hospitality` code is created.

This task exists because Hospitality should not repeat older terminology drift between app, suite, plugin, module, bundle, and shared capability.

## Architecture Law Involved

- Core is locked.
- Shell is generic.
- Apps own business logic.
- Apps contribute; Shell composes.
- Runtime consumes owner-owned contracts.
- No hidden duplicate source of truth.

## Owner and Source Areas

Primary planning sources:

- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`
- `docs/discussions/hospitality-suite-preimplementation-notes.md`
- `ARCHITECTURE.md`
- `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`
- `docs/architecture/APP-CONTRACT.md`
- `docs/architecture/MODULE-CONTRACT.md`

Current audit targets:

- `apps/`
- `plugins/`
- `platform/`
- `engineering/`
- app and plugin manifests
- existing docs that use `suite`, `bundle`, `app`, `module`, or `plugin` as lifecycle terms

## Confirmed Direction

Hospitality Suite is the product/composition name.

Runtime implementation should start as:

```text
Product name: Hospitality Suite
Runtime owner: Hospitality App
App key: hospitality
Code location: apps/Hospitality
```

Use **App** as current runtime and lifecycle truth.

Use **Suite** only as product/composition language until a real suite registry, manifest, and lifecycle service exist.

Do not create a first-class `suites/` runtime layer during the first Hospitality implementation.

## First Usable Scope

Keep the first Hospitality version limited to:

- rooms
- guests
- reservations
- check-in and check-out
- housekeeping status
- basic folio and charges

## Tentative Runtime Shape

Current discussion shape:

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

`extensions/` is not yet approved. Loader support must be checked before relying on it. If needed, extensions may first be represented as runtime-compatible modules with explicit ownership metadata.

## Non-goals

This readiness task does not authorize:

- creating `apps/Hospitality`
- adding a `suites/` loader
- changing Core
- changing app/module/plugin runtime behavior
- moving existing Manufacturing, SBAIO, Procurement, Shell, Platform, Studio, or Plugin files
- renaming existing apps or modules
- making Studio generate Hospitality
- creating shared Billing, Items, Inventory, Parties, or Accounting apps
- polishing Manufacturing/SBAIO/Procurement unless required to clarify platform ownership

## Readiness Checklist

Completed 2026-08-23 via `docs/architecture/hospitality-readiness-audit.md`.

- [x] Classify current apps by ownership type. (audit 2.2)
- [x] Classify current modules by ownership type. (audit 2.3)
- [x] Classify current plugins/adapters by ownership type. (audit 2.4)
- [x] Classify current platform engines by ownership type. (audit 2.5)
- [x] Classify current Studio tools by ownership type. (audit 2.6)
- [x] Identify legacy `suite` wording that is metadata or product language, not first-class suite architecture. (audit 3)
- [x] Identify `bundle` wording that implies lifecycle behavior. (audit 3 - `"package_type": "bundle"` in all six app manifests is legacy packaging vocabulary)
- [x] Decide whether `Procurement` is a Shared App or Domain App. (audit 4 - framed as Domain App today, Shared-App candidate; promotion requires explicit approval)
- [x] Record that Manufacturing `Products` is currently a Manufacturing-owned Parts module unless a future shared Items/Product app is explicitly approved. (audit 5)
- [x] Decide where App Extensions live for the first Hospitality version. (audit 6 - regular modules with explicit ownership metadata; no loader support for `extensions/`)
- [x] Document which shared apps are deferred versus required for first usable Hospitality. (audit 7)
- [x] Confirm the first usable Hospitality scope stays limited. (audit 7)
- [ ] Define the next active task, likely `docs/active/hospitality-app-foundation.md`. (intentionally not created by this audit; needs separate start)

## Initial Ownership Classification Targets

Known app roots:

| Path | Current interpretation | Readiness action |
|---|---|---|
| `apps/Shell` | System App | Confirm manifest and owner rules. |
| `apps/Platform` | System App | Confirm governance/platform scope. |
| `apps/Studio` | System App / governed authoring workbench | Confirm Studio is not runtime owner for Hospitality. |
| `apps/Manufacturing` | Domain App | Confirm modules remain Manufacturing-owned. |
| `apps/SBAIO` | Domain App | Confirm suite wording is legacy/product language only. |
| `apps/Procurement` | Shared App or Domain App, undecided | Resolve before Hospitality implementation. |

Known platform engine roots:

| Path | Current interpretation | Readiness action |
|---|---|---|
| `platform/Reports` | Platform Engine | Confirm reusable non-business capability. |
| `platform/Search` | Platform Engine | Confirm provider model supports apps without Core domain knowledge. |
| `platform/Style` | Platform Engine | Confirm style ownership remains owner-declared. |
| `platform/Labels` | Platform Engine | Confirm label/report resource ownership model. |
| `platform/Recovery` | Platform Engine / System Tool foundation | Confirm maintenance boundary. |
| `platform/Updates` | Platform Engine / lifecycle capability | Confirm relationship to packages. |
| `platform/Security` | Platform Engine | Confirm governance boundary. |

Known plugin roots:

| Path | Current interpretation | Readiness action |
|---|---|---|
| `plugins/Base` | Plugin / legacy bridge foundation | Confirm whether any business logic remains here. |
| `plugins/ACL` | Plugin / Adapter or legacy platform extension | Confirm relationship to Core ACL. |
| `plugins/AdminTools` | Plugin / System Tools surface | Confirm if it should stay plugin or move under a system-app model later. |
| `plugins/Audit` | Plugin / Adapter | Confirm platform audit boundary. |
| `plugins/Bus` | Plugin / Adapter | Confirm technical-provider role. |

## Open Questions

Carried forward to the future foundation task (see audit section 10):

- Exact minimum data model for the first Hospitality prototype (rooms/guests/reservations/folio) without creating shared-app debt.
- Whether folio/charges should be a FrontDesk sub-surface or its own module at creation time.
- When Procurement promotion to Shared App should be formally evaluated (needs a second consuming domain or an approved contract effort).
- Whether a shared Parties app should precede multi-domain guest/customer needs, and what triggers that evaluation.
- Whether `extensions/` ever earns loader support, or stays a documentation-level ownership label permanently.

Resolved by the audit (2026-08-23):

- Procurement: Domain App today, Shared-App candidate. No Hospitality dependency or forking.
- Manufacturing `Products`: Manufacturing-owned Parts module; not a shared Items/Product app seed.
- `extensions/`: documentation-level convention only; no loader scans it. First-version extensions are regular modules with explicit ownership metadata.
- Deferred shared apps: Billing, Items, Inventory, Parties, Accounting, and shared Procurement must not block v1.
- Required v1 capabilities are all Hospitality-local: rooms, guests, reservations, check-in/check-out, housekeeping status, basic folio/charges.

## Validation Plan

For this readiness task:

- Documentation review only.
- No runtime tests required unless code is changed.
- Final output should be a readiness classification document or an update to the existing architecture planning baseline.

Before any future Hospitality runtime implementation:

- Run app/module ownership gates.
- Run architecture gates if runtime architecture changes are proposed.
- Treat any Core change as blocked until explicitly approved.

## Current Status

Readiness audit complete (2026-08-23). Docs-only.

Audit artifact: `docs/architecture/hospitality-readiness-audit.md`

Evidence summary:

- App manifests inspected: `apps/{Shell,Platform,Studio,Manufacturing,SBAIO,Procurement}/manifest.json` (all carry legacy `"package_type": "bundle"`).
- Loader discovery paths verified: `app/Core/PluginManager.php`, `app/Core/PackageManager.php`, `app/Core/SidebarBuilder.php`, `app/Core/RouteRuntimeAuthority.php` all glob only `apps/*/modules*`; no loader scans any `extensions/` directory and none exists in the repo.
- Legacy suite vocabulary located: `app/Services/Suite{Setup,Export,Restore}Service.php`, `/admin/setup/suites/*` routes in `app/AppManager/Controllers/SetupController.php`, `ScaffoldGeneratorService::generateSuite()`, `plugins/Base/Services/SuitePermissionTemplateService.php`.
- Manufacturing `Products` confirmed as the Parts-Master module via manifest `feature_key: parts_master` and surface `/apps/manufacturing/products`.
- Procurement shape confirmed from its manifest: v0.1.0, app-scoped permissions, four modules (suppliers, requests, purchase_orders, receipts), no cross-domain contracts.

Validation:

- Documentation review only. No runtime tests required; no code changed.
- `git diff --check` clean; diff contains docs files only.

## Handoff Notes

This readiness task is complete. The follow-on implementation brief now exists at
`docs/active/hospitality-app-foundation.md` and is the active task; it defines the app
skeleton, module list, data model, routes, and coding sequence. Do not start
`apps/Hospitality` from this file; start from the foundation brief when its section 11
coding sequence is explicitly approved.

