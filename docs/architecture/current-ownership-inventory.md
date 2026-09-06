# Current Ownership Inventory

Status: Phase 1 read-only inventory. No runtime behavior, ownership transfer, file move, renaming, installer, update, or Hospitality implementation is authorized by this document.

Date: 2026-08-19

## 1. Purpose

Record the repository's current ownership evidence using the frozen vocabulary in `susankhya-productization-roadmap.md`. Descriptor location and active discovery/lifecycle behavior take precedence over legacy names.

## 2. Inventory Summary

| Inventory area | Current evidence | Frozen classification |
| --- | --- | --- |
| Top-level apps | 6 direct `apps/*/manifest.json` inputs to `AppLocalDiscoveryService` | 3 System Apps, 2 Domain Apps, 1 undecided business app |
| App modules | 32 `apps/*/modules/*/plugin.json` descriptors | App Modules; every descriptor names an `owner_app` |
| Root plugins | 5 `plugins/*/plugin.json` descriptors | Technical providers / adapters |
| Platform root | `platform/Engineering`, `Labels`, `Reports`, `Search`, `Security`, `Style` | Platform Engines |
| Studio tools | 29 `apps/Studio/Tools/*/manifest.php` descriptors | Studio-owned governed tools |
| Generated artifacts | nested `apps/Generated/**/manifest.json` files | Generated/staging artifacts; not direct app discovery inputs |

`AppLocalDiscoveryService` scans only direct `apps/*/manifest.json` files, writes their manifest metadata to `core_apps`, and refreshes runtime artifacts. It does not discover a `suites/` root or an `extensions/` root. `StudioOwnerDiscoveryService` separately recognizes app, module, root-plugin, platform, engineering-workspace, and generated owners for diagnosis.

## 3. Current Top-Level Apps

| Path | Manifest `type` | Productization classification | Evidence / note |
| --- | --- | --- | --- |
| `apps/Shell/manifest.json` | `framework` | System App | Has `system_app_contract`; owns wrapper composition. Raw `framework` is not yet a frozen app type. |
| `apps/Platform/manifest.json` | `system` | System App | Governance surface app; distinct from the root `platform/` engine area. |
| `apps/Studio/manifest.json` | `system` | System App | Optional governed tooling app. |
| `apps/Manufacturing/manifest.json` | `business` | Domain App | Owns Manufacturing routes and its 18 modules. |
| `apps/SBAIO/manifest.json` | `business` | Domain App | Owns SBAIO routes and its 11 modules. |
| `apps/Procurement/manifest.json` | `business` | Undecided business app | Current top-level app; retain as undecided between Domain App and Shared App pending evidence. |

All six app manifests currently declare `package_type: bundle`. This is active package-parser vocabulary for a top-level app archive, not evidence of a first-class Suite runtime.

`apps/Generated/**/manifest.json` is excluded from this table because it is nested below `apps/Generated`, not a direct input to `AppLocalDiscoveryService`. It must not be treated as an installed or shared app solely from its generated descriptor.

## 4. Current App Modules

Each current module is a feature owned by its parent app even though its descriptor is named `plugin.json` and several classes retain `Plugins\\...` namespaces.

| Parent App | Module count | Current module directories |
| --- | ---: | --- |
| Manufacturing | 18 | `AssemblyEntries`, `AssemblyPlans`, `Coverage`, `DailyOrders`, `DispatchEntries`, `Ledger`, `Machines`, `MaterialManagement`, `PartMachineMap`, `PreOrders`, `ProductionEntries`, `ProductionPlans`, `ProductionQueue`, `Products`, `QCEntries`, `QCPlans`, `Supply`, `Workflow` |
| SBAIO | 11 | `Attendance`, `Customers`, `Expenses`, `Leave`, `Notices`, `Payroll`, `Sales`, `Schedules`, `Staff`, `Tasks`, `Timecards` |
| Platform | 3 | `CompanySetup`, `Organization`, `QRCode` |

All 32 descriptor files are under `apps/{App}/modules/{Module}/plugin.json`, declare `package_type: module`, and carry parent ownership through `owner_app`. `ModuleLifecycleService` and `PackageManager::canonicalModulePath()` operate on that parent-app module location.

Specific retained ownership:

| Item | Classification | Evidence |
| --- | --- | --- |
| `apps/Manufacturing/modules/Products` | Manufacturing App Module, displayed as Parts | `owner_app: manufacturing`, `module_type: business_entity`; not a Shared App. |
| `apps/Platform/modules/QRCode` | Platform App Module / integration capability | `owner_app: platform`, `module_type: integration`; current Manufacturing references are integration debt, not a transfer of Manufacturing business meaning. |
| Platform Organization and CompanySetup | Platform App Modules | Current governance/integration modules under the Platform system app. |

No current `apps/**/extensions/` directory exists. App Extension is therefore a future ownership convention, not a currently discoverable runtime object.

## 5. Current Root Plugins / Technical Providers

| Path | Declared role | Productization classification |
| --- | --- | --- |
| `plugins/ACL/plugin.json` | RBAC / permissions engine | Technical provider / governance adapter |
| `plugins/AdminTools/plugin.json` | administration toolkit | Technical provider / legacy administration bridge |
| `plugins/Audit/plugin.json` | audit logging engine | Technical provider |
| `plugins/Base/plugin.json` | menus, app manager, settings | Technical provider / legacy foundation bridge |
| `plugins/Bus/plugin.json` | event bus enhancement | Technical provider |

All five currently declare `package_type: plugin` and `suite: core`. They are current technical providers, not Domain Apps, Shared Apps, or app-owned business modules.

`App\\Core\\PdfService`, mail services, export history/services, and the Platform search-provider contracts are technical capabilities. They remain engine/provider concerns even when an app or module owns the user-facing PDF, mail, export, or search result meaning.

## 6. Platform Engines

| Path | Current evidence | Classification |
| --- | --- | --- |
| `platform/Engineering` | workspace bootstrap, execution gate, dispatcher, and tests | Platform Engine: engineering governance |
| `platform/Labels` | label runtime request, resolver, rules, model builder, HTML preview proof | Platform Engine: label pipeline |
| `platform/Reports` | `ReportDefinitionRepository`, `ReportDefinitionValidator` | Platform Engine: report-definition validation/repository surface; see report ambiguity below |
| `platform/Search` | authorized index, provider context, provider registry | Platform Engine: authorized search contracts |
| `platform/Security` | authority, workspace resolver, markdown/content contracts | Platform Engine: security/governance utilities |
| `platform/Style` | reader contracts, consumption surface, runtime/proof/insertion scaffolding | Platform Engine: governed style capability |

These root Platform engines are not `apps/Platform`. `apps/Platform` is a System App that provides administration/governance surfaces; it also presently hosts Platform-owned app modules and one report registry implementation.

## 7. Studio-Owned Tools

All tool manifests are located beneath `apps/Studio/Tools` and are classified as Studio Tools, not runtime apps or Platform engines.

| Status/category evidence | Tools |
| --- | --- |
| Available / discovery / governance / validation | Audit History, Engineering Workspaces, Helper Tool, Localization Studio, Owner Structure Scan, Resource Explorer, Validation Center |
| Active or guarded specialized tools | Report Designer, Label Designer, Localization Scan Extraction |
| Read-only | Nav Menu Tool |
| Preview / metadata ambiguity | Customization Studio declares no `owner` field in its manifest and reports `preview`; classify as Studio-owned by location, with missing manifest ownership metadata noted. |
| Planned authoring/governance tools | App Builder, Change Control Center, DB Schema Tool, Form Builder, Import Export Mapper, Integration Designer, Module Builder, Notification Template Designer, Package Tool, Permission Profile Tool, Plugin Builder, Report Builder, Route Designer, Rule Designer, View Editor, Widget Builder, Workflow Designer |

`apps/Studio/Tools/ReportDesigner/manifest.php` declares `owner: studio` and identifies `report_builder` as a migration predecessor. Report Designer is a Studio-owned authoring/governance tool, not the runtime report engine or the business owner of a report.

## 8. Package / Release / Upgrade Machinery

| Path / service | Current behavior | Classification |
| --- | --- | --- |
| `app/Core/PackageManager.php` | Reads ZIP `manifest.json` or `plugin.json`; targets app bundles, app modules, or root plugins. | Package lifecycle transport; legacy naming remains active. |
| `app/Services/AppPackageService.php` and `AppInstallService.php` | Validates and installs top-level app `package_type: bundle` archives. | App-package transport/install machinery. |
| `app/Services/AppRegistryService.php` and `AppLocalDiscoveryService.php` | Registers direct app manifests in `core_apps`. | App lifecycle registry/discovery. |
| `app/Services/ModuleLifecycleService.php` | Operates child descriptors through the plugin/module manager. | Module lifecycle with legacy suite/plugin vocabulary. |
| `app/Services/ReleasePackagingService.php` | Builds release archives with core, selected “suites,” and modules. | Release-package machinery; `suites/` archive path is compatibility-only terminology. |
| `app/Services/DeploymentReadinessService.php`, `UpgradeAssistantService.php`, `SuiteSetupService.php`, `VersionCatalogService.php` | Evaluate, configure, preview, and apply core/app-group/module lifecycle operations. | Deployment/upgrade machinery with active Suite vocabulary. |
| `app/Services/ReleaseHistoryService.php`, `RestoreHistoryService.php`, `SuiteExportService.php`, `SuiteRestoreService.php` | Record/export/restore lifecycle data. | Package/recovery machinery; names require later vocabulary alignment. |

No current local update-channel implementation, hosted update service, Windows installer, or first-class Suite manifest/loader was found. The local-update plan remains planning-only.

## 9. Report Ownership

| Concern | Current repository truth | Frozen classification |
| --- | --- | --- |
| Report design workflow | `apps/Studio/Tools/ReportDesigner` | Studio-owned Tool |
| Report-definition validation/repository | `platform/Reports/ReportDefinitionValidator.php`, `ReportDefinitionRepository.php` | Platform Engine capability |
| Active report discovery | `apps/Manufacturing/Services/ModuleReportRegistryService.php`, `apps/SBAIO/Services/ModuleReportRegistryService.php`, `apps/Platform/Services/ModuleReportRegistryService.php` | Platform-owned registry responsibility is the target; current implementation is duplicated by owner app/app-module area. |
| Report definitions and metadata | `reports` arrays in all 32 current module `plugin.json` descriptors | App Module-owned artifacts and business meaning |
| Report rendering, data, permissions, exports | Module routes/views/services and declared report metadata | App Module-owned meaning; technical export/PDF mechanics remain provider/engine concerns |

`platform/Reports/Definitions` currently has no JSON report definition files. `ReportDefinitionRepository` can persist definitions below that Platform path, while the report operating contract says runtime report definitions remain app/module-owned. This is a documented **ambiguity** for a later contract/repository alignment; it does not authorize moving definitions or changing Report Designer behavior now.

`ReportDefinitionValidator` currently requires a `suite` field, which is compatibility-only terminology under the frozen vocabulary.

## 10. Legacy Terminology Inventory

| Term / evidence | Classification | Current reading |
| --- | --- | --- |
| App | canonical | Direct top-level `apps/*/manifest.json` discovery and `core_apps` registration are current runtime truth. |
| Module | canonical | Parent-app feature with `owner_app`, regardless of descriptor filename. |
| Plugin | canonical only for root technical providers | `plugins/*/plugin.json` is a technical-provider mechanism; it is misleading when applied to app modules. |
| `plugin.json` under `apps/*/modules` | compatibility-only | Active module descriptor format; does not change the module's app ownership. |
| `Plugins\\...` namespace under app modules | compatibility-only | Legacy class/loader namespace used by current app module code and widget providers. |
| `suite` fields and `Suite*Service` names | compatibility-only | Used for business-app grouping, setup, release, restore, report validation, and UI context; no first-class suite runtime exists. |
| `bundle` and `package_type: bundle` | compatibility-only | Active archive/app-package parser shape; not an app-type or Suite proof. |
| `suites/` inside release archives | compatibility-only | Release archive layout name only. |
| `package_type` | canonical lifecycle field, ambiguous values | Correctly describes package shape, but `bundle`/`plugin` values overlap legacy ownership words. |
| `extension` / App Extension | ambiguous | No runtime extension directory or loader exists. Uses in PHP file-extension handling and UI placeholders do not establish an App Extension model. |
| Manifest `type` / `app_type` | ambiguous | Current values include `business`, `system`, and `framework`; they do not yet equal the frozen `system_app`, `domain_app`, or `shared_app` model. |
| `Generated` app/module manifests | ambiguous | Generated artifacts are discoverable by Studio diagnostics but not direct top-level runtime app discovery. |

## 11. Ambiguous Or Needs Decision

| Item | Evidence | Needed later |
| --- | --- | --- |
| Procurement classification | Current top-level `type: business` app; no evidence yet establishes a stable cross-domain contract. | Decide Domain App versus Shared App through real dependency and ownership evidence. |
| App Extension convention | No `extensions/` directory or loader; only future planning references/placeholders. | Design an explicit contract before adding any extension artifact. |
| App manifest type vocabulary | `business`, `system`, `framework` differ from frozen target app types. | Define an additive migration/compatibility plan, not a terminology-only rewrite. |
| Report repository versus owner artifacts | Module `reports` declarations coexist with writable `platform/Reports/Definitions` repository, currently empty. | Resolve source-of-truth and handover contract before expanding Report Designer. |
| Report registry location | Three near-identical active registries scan each owner app's module descriptors. | Define a Platform-owned registry consolidation plan only after source-of-truth decision. |
| QR boundary | Platform QRCode module has optional Manufacturing module dependencies and domain-facing surfaces. | Inventory generic QR capability versus Manufacturing workflow consumers before changing ownership. |
| Generated artifacts | Nested generated manifests are visible to Studio but outside direct top-level app discovery. | Clarify staging/publish promotion and cleanup policy before treating them as products. |

## 12. Hospitality Implications

Hospitality remains a future Domain App at `apps/Hospitality`, not a Suite runtime object. It must begin with a top-level app manifest and app-owned modules only after the outstanding ownership inventory is reviewed.

For the first usable scope:

- keep Rooms, Guests, Reservations, Front Desk, Housekeeping, and bounded folio/charges meaning inside Hospitality;
- do not create a Shared Items, Parties, Inventory, Billing, or Accounting app merely to start Hospitality;
- keep any future connection to a shared app as a proposed App Extension contract until loader/lifecycle support exists;
- keep Products/Parts owned by Manufacturing until a separately approved shared Items/Products extraction proves the need; and
- do not require a first-class Suite runtime, cloud synchronization, Windows installer, private update service, or Studio doctor automation to define Hospitality.

## 13. Next Work

1. Review this inventory against the productization roadmap and close the Hospitality readiness items it evidences.
2. Complete the local deployment/update documentation baseline: readiness entrypoints, release-package flow, channel manifest, preview, recovery, and tested backup/restore requirements.
3. Produce a read-only legacy terminology map with precise compatibility owners and migration prerequisites, beginning with suite/bundle/plugin/module/report vocabulary.
4. Decide Procurement's app type from real domain/dependency evidence.
5. Define the App Extension contract before any Hospitality extension implementation.
6. Define report-definition source-of-truth and Platform registry consolidation as a separate contract; do not move or rename current report artifacts from this inventory.

## Evidence Sources

- `app/Services/AppLocalDiscoveryService.php`
- `app/Services/AppRegistryService.php`
- `app/Core/PackageManager.php`
- `app/Services/ModuleLifecycleService.php`
- `app/Services/ReleasePackagingService.php`
- `app/Services/DeploymentReadinessService.php`
- `app/Services/UpgradeAssistantService.php`
- `apps/Studio/Services/StudioOwnerDiscoveryService.php`
- `apps/Studio/Tools/ReportDesigner/manifest.php`
- `platform/Reports/ReportDefinitionRepository.php`
- `platform/Reports/ReportDefinitionValidator.php`
- `docs/architecture/susankhya-productization-roadmap.md`