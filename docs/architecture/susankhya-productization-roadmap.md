# Susankhya Productization Roadmap

Status: planning baseline. No runtime behavior, installer, update, file move, or refactor is authorized by this document.

Date: 2026-08-19

This roadmap consolidates the existing app-ownership and local-update planning baselines. It keeps Susankhya OS a modular monolith while productization foundations are made explicit.

## 1. Current Ground Truth

Susankhya is currently a **modular monolith**: one PHP repository and deployment unit with Core runtime code in `app/`, top-level apps in `apps/`, reusable Platform code in `platform/`, technical plugins in `plugins/`, and lifecycle artifacts/services under package and storage paths.

| Concept | What the running architecture recognizes today | Productization reading |
| --- | --- | --- |
| App | Top-level `apps/*/manifest.json` bundle registered in `core_apps`; local discovery, install, enable, disable, and app runtime artifacts operate on this unit. | The current primary runtime and lifecycle unit. |
| Module | App-owned feature directory, normally `apps/{App}/modules/{Module}`, with a lifecycle manifest currently named `plugin.json`; discovered and operated through the legacy plugin/module manager. | A child of exactly one app, despite legacy metadata and namespaces. |
| Plugin | Technical extension/provider mechanism under `plugins/*/plugin.json`; legacy module services also use `Plugins\\...` namespaces. | The target meaning is narrower than current usage. Some present module/plugin vocabulary is compatibility debt. |
| Package | ZIP/import/export/release transport and lifecycle mechanism. `PackageManager`, `AppPackageService`, and release services validate and carry artifacts. | Transport does not determine runtime ownership. |
| Suite | `SuiteSetupService`, `UpgradeAssistantService`, release metadata, and a few Studio services use suite labels for business app grouping. No `suites/` registry, manifest type, loader, or independent suite lifecycle exists. | Legacy lifecycle/product vocabulary, not a first-class runtime concept. |

Current evidence includes `AppLocalDiscoveryService`, `AppRegistryService`, `PackageManager`, `AppPackageService`, `ModuleLifecycleService`, `SuiteSetupService`, and `UpgradeAssistantService`. Their names and metadata are not yet fully aligned, but their active behavior is sufficient to state the boundary above.

Current ownership facts that must remain true:

- Shell, Platform, and Studio are top-level system applications with system-app contracts.
- Manufacturing, SBAIO, and Procurement are top-level business apps today.
- Manufacturing `Products` is a Manufacturing-owned Parts module today; it is not a shared Items or Products app.
- Report Designer is a Studio tool. The report registry/engine is Platform-owned. Report definitions, data meaning, permissions, layouts, and metadata remain app/module-owned.
- Existing `package_type: bundle` on app manifests is lifecycle packaging vocabulary. It does not make the app a Suite.

## 2. Naming Rules To Freeze

Use these terms in all new productization, Hospitality, installer, update, and architecture work:

| Term | Frozen meaning |
| --- | --- |
| Core Engine | Generic kernel/runtime primitives in `app/`; locked unless an approved generic capability requires change. |
| System App | Installable system-facing app: Shell, Platform, or Studio. |
| Domain App | Installable app that owns a bounded business domain: Manufacturing, SBAIO, future Hospitality. Do not use “Vertical App.” |
| Shared App | Future reusable business-domain app with an independently justified cross-domain contract: Items, Parties, Inventory, Billing, or Accounting. |
| Module | Focused feature owned by one parent app. It is not a top-level business app. |
| App Extension | Parent-app-owned augmentation of a shared app through an explicit future contract. It is not automatically a plugin or an independent app. |
| Plugin | Optional technical provider, adapter, integration, renderer, search, storage, mail, PDF, QR, or export capability. It must not hide a business domain. |
| Package | Install/update/import/export/release lifecycle artifact or workflow. It transports an owner artifact but does not own its runtime meaning. |
| Suite | Product/commercial or legacy grouping label only until an explicit suite registry, manifest, resolver, and coordinated lifecycle contract are approved. |

Keep legacy `suite`, `bundle`, `plugin.json`, and `Plugins\\...` references where runtime compatibility currently depends on them. New plans and UI labels must identify them as legacy terminology rather than treat them as target architecture.

## 3. Target App-Type Model

The first introduced app types are intentionally small:

| App type | Initial owners | Rule |
| --- | --- | --- |
| `system_app` | Shell, Platform, Studio | Owns system surface responsibilities defined by its system-app contract, never business workflow meaning. |
| `domain_app` | Manufacturing, SBAIO, future Hospitality | Owns bounded business meaning, routes, workflows, modules, and domain-facing data contracts. |
| `shared_app` | Future Items, Parties, Inventory, Billing, Accounting | Introduce only after at least two real domain apps need the same stable contract and migration ownership is approved. |
| `support_app` | Not introduced now | Add only with evidence that the capability has independent app lifecycle, routes, permissions, data ownership, and operational meaning that do not fit Platform, a shared app, or an existing domain app. |

`Procurement` remains a current business app pending an explicit evidence-based decision between `domain_app` and `shared_app`; do not classify it by aspiration alone.

Hospitality begins as one `domain_app` at `apps/Hospitality` with modules such as Rooms, Guests, Reservations, Front Desk, Housekeeping, and a deliberately bounded folio/charges capability. It may be marketed as a Hospitality Suite without making Suite runtime truth.

## 4. Modules, Extensions, Plugins, Packages

Modules belong to exactly one app. The app owns cross-module orchestration; each module owns its focused routes, views, services, schema/migrations, reports, exports, permissions, lifecycle declarations, and domain semantics.

Extensions also belong to an app. A future Hospitality-to-Billing or Hospitality-to-Items integration must first be modeled as an explicit app-extension contract with declared host app, target shared app, permissions, data boundary, lifecycle dependency, and uninstall/disable behavior. Do not rely on an `extensions/` directory as a runtime feature until loader and lifecycle support has been designed and validated. Until then, use a compatible module boundary with explicit ownership metadata.

Plugins remain technical providers/adapters. They may expose stable contracts, hooks, or capability registrations, but cannot own hidden business routes, business data semantics, or cross-module workflow decisions. Existing Platform QR and legacy module plugin namespaces require inventory and migration plans, not an immediate move.

Packages remain lifecycle transport. An app package, module package, plugin package, report artifact, snapshot, or release archive keeps its original owner after packaging. Package metadata must record identity, version, compatibility, checksum, migrations, dependencies, rollback evidence, and verification outcome without becoming a competing ownership registry.

## 5. Deployment And Update Foundation

Keep the first deployment/update path local and explicit:

```text
readiness -> deterministic release package -> local channel manifest
  -> compatibility preview -> explicit apply -> verification -> recovery
```

The existing `local-deployment-update-channel-plan.md` is the delivery baseline. V1 must prove a filesystem-backed channel before any hosted delivery service. The future private service at `update.susankhya.com` should distribute signed/verified release metadata and packages only after the local channel, compatibility contract, backup/restore process, and apply/verify recovery behavior are proven.

`cloud.susankhya.com` begins as a customer, license, tenant/control, entitlement, and release-access portal. It is not business-data synchronization, replication, or a cloud runtime mandate.

## 6. Backup/Restore Requirement

No installer or updater may be considered product-ready without a tested backup/restore contract. Before any mutating apply operation, it must:

1. identify the exact installed version, app/module states, manifest checksums, and schema version;
2. create and verify an application/filesystem backup plus a database backup or an explicit database recovery point;
3. record the release/channel identity, package checksum, migration plan, and operator approval;
4. apply in a bounded, observable sequence;
5. run post-apply health, migration, route/app discovery, and critical workflow verification; and
6. provide a documented restore procedure that returns both code and schema to a compatible known state.

Snapshot creation alone is insufficient. Restore must be rehearsed on a representative local installation before a Windows installer or online update service is approved.

## 7. Installer Scope

A Windows installer is later productization work, not an app refactor. Its V1 scope is deliberately boring:

- prerequisite detection and explicit supported-environment checks;
- installation of a known release package and its documented runtime dependencies;
- creation/validation of writable directories, configuration, database connection, and first-run setup;
- invocation of existing readiness, installation, migration, and verification contracts only after their behavior is consolidated;
- upgrade preview, explicit operator confirmation, backup/restore evidence, and a supportable failure report.

It must not initially provide silent updates, cloud synchronization, marketplace/plugin-store behavior, arbitrary package installs, or unattended schema-changing upgrades. The installer consumes approved package/update contracts; it must not invent alternate app/module ownership or lifecycle rules.

## 8. Studio As Doctor, Later

Studio remains an optional `system_app`, never a runtime dependency of business apps. Its eventual “doctor” role is governed diagnosis and proposal, not autonomous repair.

Target diagnostic scope:

- inventory apps, modules, app extensions, plugins, packages, routes, permissions, reports, schemas, assets, and migrations;
- classify declared versus observed ownership and flag legacy terminology, duplicate sources of truth, missing lifecycle declarations, and broken compatibility;
- assess package/update compatibility, backup readiness, migration risk, and post-update health;
- produce explainable change proposals, diffs, approvals, snapshots, handovers, and rollback evidence.

Studio must not silently reclassify an owner, generate Hospitality as its first use case, bypass permissions, directly become the update agent, or own live report/business definitions. Report Designer stays Studio-owned; report engine/registry stays Platform-owned; report owner artifacts stay with the declaring app/module.

## 9. Hospitality Readiness Gate

Hospitality implementation begins only when all of the following are recorded and reviewed:

- current top-level apps, modules, root plugins, Platform engines, packages, and Studio tools are classified by the frozen ownership vocabulary;
- every current `suite`, `bundle`, `plugin.json`, and legacy `Plugins\\...` usage is inventoried as canonical, compatibility-only, or ambiguous;
- Procurement receives an evidence-based app-type decision;
- Manufacturing Products/Parts is explicitly retained as Manufacturing-owned until a real shared Items/Products extraction case is approved;
- the first app-extension convention is specified without assuming unsupported runtime loader behavior;
- Hospitality’s bounded module map, data ownership, dependencies, permissions, routes, and lifecycle plan are reviewed;
- the local deployment/update baseline has a proven readiness, package, preview, backup/restore, apply, and verification story; and
- no gate requires a first-class Suite runtime, shared-app extraction, or Studio auto-upgrader to satisfy the initial Hospitality scope.

## 10. Work Order / Phases

| Phase | Outcome | Explicit boundary |
| --- | --- | --- |
| 0. Freeze vocabulary | Approve this roadmap and cross-reference the existing ownership/update baselines. | Documentation only. |
| 1. Ownership inventory | Read-only classification of current owners and legacy naming, including report and QR/PDF/provider boundaries. | No moves, renames, loader edits, or lifecycle behavior changes. |
| 2. Product delivery contract | Define release manifest, compatibility, backup/restore, recovery, and verification requirements. | No installer/update apply implementation yet. |
| 3. Local update proof | Build and validate local channel package discovery, preview, explicit apply, verification, and recovery on a controlled install. | No hosted service, background updater, or cloud sync. |
| 4. Installer readiness | Define supported Windows deployment, prerequisites, first-run, support, and rollback acceptance tests. | Installer consumes proven contracts; it does not redefine ownership. |
| 5. Hospitality design gate | Approve the Hospitality domain-app/module map and any compatible extension convention. | Keep shared-app extraction and Suite runtime deferred. |
| 6. Private updates | Design `update.susankhya.com` as a private package/metadata distribution service once local update proof is complete. | No business-data sync. |
| 7. Doctor capability | Add Studio diagnostics/proposals only after ownership and lifecycle contracts are stable. | No autonomous upgrader or hidden runtime authority. |

## 11. Explicit Non-Goals For Now

Do not start any of the following from this roadmap:

- full Hospitality implementation or broad Manufacturing/SBAIO/Procurement refactors;
- first-class Suite loader, registry, manifests, or lifecycle;
- large shared-app extraction for Items, Parties, Inventory, Billing, or Accounting;
- cloud business-data synchronization, replication, or multi-tenant cloud ERP runtime;
- `update.susankhya.com` implementation, automatic background updates, marketplace distribution, or unattended update apply;
- Windows installer implementation;
- Studio auto-upgrader, autonomous doctor/repair execution, or Studio-generated Hospitality;
- runtime code moves, namespace renames, file/folder moves, or terminology-only refactors.

## Related Baselines

- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`
- `docs/architecture/local-deployment-update-channel-plan.md`
- `docs/architecture/system-app-plugin-package-boundaries.md`
- `docs/architecture/business-app-module-ownership-contract.md`
- `docs/architecture/report-designer-operating-contract.md`
- `docs/architecture/report-runtime-contract.md`