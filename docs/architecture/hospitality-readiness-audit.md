# Hospitality Suite Readiness Audit

Status: Docs-only readiness audit. No runtime behavior. No file moves. No PHP changes.

Date: 2026-08-23

Builds on:

- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md` (planning baseline)
- `docs/discussions/hospitality-suite-preimplementation-notes.md` (discussion note)
- `ARCHITECTURE.md`, `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md`
- `docs/architecture/APP-CONTRACT.md`, `docs/architecture/MODULE-CONTRACT.md`

## 1. Purpose

This audit completes the readiness gate defined by the planning baseline so that a future
Hospitality App can be created without repeating historical terminology drift between
app, suite, bundle, plugin, and module.

It classifies what exists today, records where legacy wording could mislead Hospitality
implementation, frames the open ownership decisions, and locks the first usable scope.

## 2. Ownership Classification (Current State)

Classification follows the ownership types defined in
`app-ownership-classification-and-hospitality-readiness.md`.

### 2.1 Core Engine (`/app`)

| Area | Evidence | Ownership type |
|---|---|---|
| Auth, sessions, routing, DB, registries, ACL enforcement, migration primitives | `app/Core/*` | Core Engine |
| App/module/plugin/package lifecycle | `app/AppManager/*`, `app/Core/PackageManager.php`, `app/Core/PluginManager.php` | Core Engine |
| Legacy suite lifecycle services | `app/Services/SuiteSetupService.php`, `SuiteExportService.php`, `SuiteRestoreService.php`; `/admin/setup/suites/*` routes; `ScaffoldGeneratorService::generateSuite()`; suite profiles in `SetupProfileService` | Core Engine (legacy vocabulary, not first-class suite architecture) |
| Business logic | none permitted | n/a - Core is locked |

Core contains no Hospitality or business-domain knowledge. No Core change is authorized
for Hospitality.

### 2.2 Apps under `apps/`

| Path | Manifest type | Ownership type | Notes |
|---|---|---|---|
| `apps/Shell` | framework | System App | Generic runtime frame/wrapper. Owns composition chrome only. |
| `apps/Platform` | system | System App | Platform governance surfaces; owns no business-domain meaning. |
| `apps/Studio` | system | System App (governed authoring workbench) | ~29 tools under `apps/Studio/Tools/`. Diagnoses/proposes; must not generate or own Hospitality runtime truth. |
| `apps/Manufacturing` | business | Domain App | Reference app. Modules are Manufacturing-owned (see 2.3). |
| `apps/SBAIO` | business | Domain App | Reference app (staff/payroll/etc.). Suite wording here is product language only. |
| `apps/Procurement` | business | Domain App today, Shared-App candidate (see section 4) | v0.1.0; modules: suppliers, requests, purchase_orders, receipts. No AGENTS.md / engineering workspace yet. |
| `apps/Generated/*` | generated sample manifests | Generated reference artifacts | Scaffold output (hardening_app, inventory_app, lifecycle_app, sample_app, etc.). Not product apps. |

### 2.3 Manufacturing modules

All modules under `apps/Manufacturing/modules/` are Manufacturing-owned App Modules.
Notably:

| Module | Evidence | Classification |
|---|---|---|
| `Products` | manifest `plugin_modules` entry with `feature_key: parts_master`; surface `/apps/manufacturing/products`; naming convention "Item Code = Part Number" | Manufacturing-owned Parts/Parts-Master module. Not a shared Items/Product app seed. |

The remaining modules (Machines, PreOrders, DailyOrders, ProductionPlans, ProductionEntries,
QCPlans, QCEntries, AssemblyPlans, AssemblyEntries, DispatchEntries, Coverage, Supply,
MaterialManagement, PartMachineMap, ProductionQueue, Ledger, Workflow) are all
single-parent App Modules per the MODULE-CONTRACT.

### 2.4 Plugins under `plugins/`

| Path | plugin.json | Ownership type |
|---|---|---|
| `plugins/Base` | yes | Plugin / Legacy Bridge foundation (older platform-era services incl. `SuitePermissionTemplateService`) |
| `plugins/ACL` | yes | Plugin / Adapter (authorization provider adjacent to Core ACL enforcement) |
| `plugins/AdminTools` | yes | Plugin / System Tools surface |
| `plugins/Audit` | yes | Plugin / Adapter (audit channel) |
| `plugins/Bus` | yes | Plugin / Adapter (event bus technical provider) |

No plugin owns business-domain workflow meaning. This matches ARCHITECTURE.md's rule
that plugins are extension/provider mechanisms only.

### 2.5 Platform engines under `platform/`

| Path | Ownership type | Notes |
|---|---|---|
| `platform/Reports` | Platform Engine | Reusable non-business reporting capability. "suite" inside `ReportDefinitionValidator.php` is validation/test-suite vocabulary, not lifecycle suite. |
| `platform/Search` | Platform Engine | Owner-declared search providers; no Core domain knowledge. |
| `platform/Style` | Platform Engine | Style chain remains owner-declared; runtime consumption disabled by default. |
| `platform/Labels` | Platform Engine | Label pipeline proof; owner-owned resources. |
| `platform/Recovery` | Platform Engine / System Tool foundation | Maintenance boundary. |
| `platform/Updates` | Platform Engine / lifecycle capability | Relates to package lifecycle. |
| `platform/Security` | Platform Engine | Governance/security boundary. |
| `platform/Engineering` | Platform Engine | Engineering workspace contracts. |

### 2.6 Studio tools

All tools under `apps/Studio/Tools/` (AppBuilder, ModuleBuilder, PluginBuilder,
ReportDesigner, LabelDesigner, CustomizationStudio, OwnerStructureScan, etc.) are
Studio Tools: governed authoring/diagnosis workers. They own drafts, diffs, snapshots,
and handover records - never runtime truth for any app, including a future Hospitality App.

## 3. Legacy Wording Inventory (Confusion Risks)

The following existing wording is legacy metadata, legacy service naming, or product
language. None of it is first-class suite/bundle architecture. Hospitality must not
interpret any of it as authorization to create new layers.

| Finding | Location | Risk if misread | Correct reading |
|---|---|---|---|
| `"package_type": "bundle"` in every app manifest | `apps/{Shell,Platform,Studio,Manufacturing,SBAIO,Procurement}/manifest.json` | Suggests bundles are a distinct installable layer alongside apps | Legacy packaging vocabulary. The App is the installable unit; loaders treat each manifest as one app. |
| `legacy_bridge_plugins` + `native_modules` | `apps/SBAIO/manifest.json` | Suggests SBAIO features are plugins | Historical migration record: former plugin-era features are now native app modules. |
| `plugin_modules` key | `apps/Manufacturing/manifest.json` | Suggests Manufacturing ships business plugins | Registration mechanism name only; entries are App Modules (canonical/compatibility kinds). |
| Core `Suite*` services and `/admin/setup/suites/*` routes | `app/Services/Suite{Setup,Export,Restore}Service.php`, `app/AppManager/Controllers/SetupController.php`, `ScaffoldGeneratorService::generateSuite()` | Suggests suites are a real runtime/lifecycle layer | Legacy lifecycle vocabulary from an older packaging era. "Suite" here means install-profile grouping, not a composition parent. |
| Suite permission templates | `plugins/Base/Services/SuitePermissionTemplateService.php` | Suggests plugin-owned suite authority | Legacy bridge service inside the Base compatibility plugin. |
| Product-language "Suite" in SBAIO views/services | `apps/SBAIO/Views/*`, `SbaioLegacyImportService` | Suggests "suite" is an ownership concept | Marketing/product phrasing for one Domain App; single app owner underneath. |
| Terminology registry row "App ... Suite when describing runtime navigation" | `docs/identity/terminology-registry.md` | Contradicts current App-as-truth law | Historical identity vocabulary; superseded by the planning baseline for lifecycle questions. |
| "View Suite" | `docs/architecture/MODULE-CONTRACT.md` (view modeling section), `ARCHITECTURE.md` view model | Could be confused with product suites | View-model vocabulary (table/form/etc.). Unrelated to lifecycle suites. Do not rename; do not reuse for Hospitality. |
| Test/validation "suite" usage | `platform/Reports/ReportDefinitionValidator.php` and test tooling | Low risk; generic English | Validation-suite vocabulary only. |

Rule going forward:

```text
New documentation must use:
- App      = installable runtime unit
- Module   = feature area owned by exactly one app
- Extension= ownership label for domain-owned augmentation of a shared app (documentation-level until loader support exists)
- Suite    = product/composition language only (e.g. "Hospitality Suite")
```

Legacy occurrences above stay as-is. They are documented debt, not patterns to copy.

## 4. Procurement: Shared App or Domain App

Decision framing (recorded; promotion still requires explicit approval):

```text
Procurement today: Domain App implementation.
Procurement trajectory: Shared App candidate.
Promotion condition: explicit approval plus cross-domain contracts.
```

Evidence:

- Current shape is a standalone business app (`apps/Procurement`) with app-scoped
  permissions (`procurement.view/manage/approve`), its own routes under
  `/apps/procurement`, and four modules (suppliers, requests, purchase_orders, receipts).
- Its concepts (suppliers, purchase requests/orders, receipts) are generically reusable
  across domains (Manufacturing purchasing, Hospitality purchasing), which is why the
  Shared App question exists at all.
- It lacks everything a Shared App requires today: multi-domain data scoping,
  per-domain permission mapping, owner-neutral entity contracts, and a second consuming
  domain proving reuse.

Consequences:

- Hospitality must not depend on Procurement as a shared dependency.
- Hospitality must not reach into `/apps/procurement` internals.
- If Hospitality v1 needs purchasing, it uses minimal local records or defers entirely;
  it does not fork Procurement code either.
- A future promotion to Shared App would need its own approved contract change, not a
  side effect of Hospitality work.

## 5. Manufacturing Products Ownership (Recorded)

Manufacturing `Products` is a Manufacturing-owned Parts/Parts-Master App Module:

- manifest evidence: `feature_key: parts_master`, surface `/apps/manufacturing/products`
- naming convention: Item Code = Part Number (ARCHITECTURE.md data-modeling rules)

It is not a shared Items/Product app seed. A future shared Items/Product app may be
proposed someday, but until such an app is explicitly approved, nothing should treat
`apps/Manufacturing/modules/Products` as reusable outside Manufacturing, and Hospitality
must own its own local item records if v1 needs any.

## 6. `extensions/` Loader Support Check

Checked loaders and discovery paths:

| Loader | Discovery pattern | Scans `extensions/`? |
|---|---|---|
| `app/Core/PluginManager.php` | glob `apps/*/modules/*` | No |
| `app/Core/PackageManager.php` | glob `apps/*/modules/<name>` | No |
| `app/Core/SidebarBuilder.php` | glob `apps/*/modules*/navigation.php` | No |
| `app/Core/RouteRuntimeAuthority.php` | glob `apps/*/modules*/navigation.php` | No |
| Locale resolution (`app/Core/helpers.php`) | `apps/*/modules*/Resources/lang/$lang.php` | No |

Additionally, no directory named `extensions` exists anywhere under `apps/`, `plugins/`,
or `platform/`.

Decision:

```text
extensions/ is a documentation-level ownership convention only.
There is no loader support. Do not create apps/Hospitality/extensions expecting discovery.
```

For the first Hospitality version, if an extension-shaped capability is unavoidable
(e.g. local folio/billing records), implement it as a normal runtime-compatible module
under `apps/Hospitality/modules/` carrying explicit ownership metadata, for example:

```json
{
  "ownership_type": "app_extension",
  "extension_of": null,
  "note": "local stand-in until a shared Billing app is approved"
}
```

This keeps the ownership intent visible without inventing a new runtime mechanism.

## 7. Deferred vs Required for First Usable Hospitality

### Required (as Hospitality-local modules, not shared apps)

First usable scope stays limited to:

| Capability | Implementation home |
|---|---|
| Rooms | Hospitality-owned module (Rooms) |
| Guests | Hospitality-owned local guest records (see deferred Parties) |
| Reservations | Hospitality-owned module (Reservations) |
| Check-in / check-out | Hospitality-owned module (FrontDesk) |
| Housekeeping status | Hospitality-owned module (Housekeeping) |
| Basic folio / charges | Hospitality-owned local folio records (see deferred Billing) |

None of these require Core changes, new loaders, Shell changes, or Studio generation.

### Deferred shared apps (must not block or leak into v1)

| Shared app | Status for v1 | Hospitality v1 stance |
|---|---|---|
| Billing | Deferred | Local folio/charges records only; clearly marked as a local stand-in. |
| Items | Deferred | Avoid item catalogs; if unavoidable, minimal local records. |
| Inventory | Deferred | Out of scope; housekeeping tracks room status, not stock. |
| Parties | Deferred | Guests live locally in Hospitality until a shared Parties app is approved. |
| Accounting | Deferred | Folio totals remain operational records; no posting/export integration. |
| Procurement (as shared) | Deferred (Domain App today) | No dependency, no forking. |

Boundary rule: a local stand-in must never masquerade as the shared app. Naming,
permissions, and docs must say "Hospitality-local" so a later shared-app promotion is a
clean migration instead of a silent takeover.

## 8. Readiness Gate Result

Baseline checklist from `app-ownership-classification-and-hospitality-readiness.md`:

- [x] Classify current apps, modules, plugins, platform engines, and Studio tools by ownership type (section 2)
- [x] Identify legacy `suite` and `bundle` wording that is lifecycle vocabulary rather than first-class suite architecture (section 3)
- [x] Decide/frame whether `Procurement` is a Shared App or Domain App (section 4 - framed; formal promotion needs explicit approval)
- [x] Record that Manufacturing `Products` is currently Manufacturing-owned, not a shared Products app (section 5)
- [x] Define where App Extensions live for the first Hospitality version (section 6 - as regular modules with ownership metadata; no loader support for `extensions/`)
- [x] Document which shared apps are deferred versus required for the first usable Hospitality App (section 7)
- [x] Keep first usable scope limited to rooms, guests, reservations, check-in/check-out, housekeeping status, basic folio/charges (sections 7)

Result: readiness gate satisfied at the planning level. The next step would be a separate
active task (e.g. `docs/active/hospitality-app-foundation.md`) defining the smallest app
skeleton and module list. That task is not created by this audit.

## 9. Non-Goals Reaffirmed

This audit does not authorize:

- creating `apps/Hospitality`
- adding a `suites/` loader or any new loader
- changing Core, Shell, Platform, Studio, or plugin/runtime behavior
- moving or renaming existing files, apps, or modules
- making Studio generate Hospitality
- creating shared Billing, Items, Inventory, Parties, or Accounting apps

## 10. Open Questions Carried Forward

These remain tentative and belong to the future foundation task:

1. Exact schema/data model for the first Hospitality prototype (minimum tables for
   rooms/guests/reservations/folio without creating shared-app debt).
2. Whether folio/charges should be a FrontDesk sub-surface or its own module at creation time.
3. When Procurement promotion to Shared App should be formally evaluated (needs a second
   consuming domain or an approved contract effort).
4. Whether a shared Parties app should precede multi-domain guest/customer needs, and what
   triggers that evaluation.
5. Whether `extensions/` ever earns loader support, or stays a documentation-level
   ownership label permanently.
