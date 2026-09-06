# ARCHITECTURE.md

# IPM ERP Architecture

## Purpose

This repository builds a company-specific ERP platform for IPM Co. Ltd. with a reusable ERP Core and installable business apps.

This is not a generic ERP clone.
The system must reflect IPM's real operations, terminology, roles, workflows, and constraints.

The architecture must remain:

- modular
- maintainable
- app-driven
- workflow-oriented
- role-aware
- extensible for future business domains

---

## Product Model

The platform is organized into the following layers and mechanisms:

1. Core Engine
2. Shell
3. Platform
4. Apps
5. Modules
6. Plugins (extension mechanism)
7. Packages (lifecycle mechanism)

Canonical hierarchy:

Core Engine -> Shell + Platform -> Apps -> Modules
Plugins + Packages = extension/lifecycle mechanisms

---

## 1. Core Engine

The Core is the platform foundation.

It contains platform-level capabilities only and must remain reusable across business domains.

### Core responsibilities

- authentication
- sessions
- user management
- roles and permissions
- policy / ACL enforcement
- audit logs
- dashboard framework
- navigation framework
- app registry
- module registration lifecycle
- setup / recovery flow
- configuration / settings
- notifications framework
- search framework
- file/document support
- workflow infrastructure
- reporting/export infrastructure
- install / upgrade / disable / uninstall support

### Core must not contain

Do not place business-domain logic in Core unless it is truly reusable across multiple apps.

Examples of things that do not belong in Core:

- machine production logic
- IPM-specific demand formulas
- QC business rules specific to manufacturing
- dispatch flow rules specific to IPM
- material slot planning logic

---

## 2. Apps

An App is an installable business domain package built on top of Core.

Apps are the main business containers.

### Examples

- Manufacturing
- Materials
- SBAIO
- HR
- Procurement

### App responsibilities

Each app should own its business domain and provide:

- manifest
- routes
- navigation
- permissions
- migrations / install hooks
- dashboards
- services
- module registration
- business screens
- business workflows

### App rules

- Every business domain should be modeled as an app if it stands on its own operationally.
- An app can contain multiple modules.
- An app must remain responsible for its own business logic.
- Disabling an app must remove its menus, routes, dashboards, and operational surfaces safely.

---

## 3. Modules

A Module is a functional unit inside an app.

A module is not a top-level business package.
A module belongs to exactly one parent app.

### Manufacturing app module examples

- Parts Master
- Machines
- Part-Machine Map
- Pre Orders
- Daily Orders
- Demand
- Production Plans
- Production Entries
- QC
- Assembly
- Dispatch
- Coverage
- Reports

### Materials app module examples

- Material Master
- Material Stock
- Material Coverage
- Material Planning
- Purchase / Delivery Planning
- Storage / Slot Control
- Material Cost

### Module rules

- A module must not pretend to be its own app.
- A module must register into its parent app.
- A module may expose routes, services, views, and reports through app-owned registration.
- A module must not create unrelated top-level navigation roots.

---

## 4. Plugins

A Plugin is an extension or provider mechanism.

Plugins are not the main business-domain container.

### Good plugin use cases

- PDF export adapter
- search provider
- notification channel
- integration connector
- dashboard widget provider
- storage provider
- import/export provider

### Bad plugin use cases

- naming a full business domain as a plugin
- putting Manufacturing as a plugin
- putting Materials as a plugin
- using plugin terminology where app or module is correct

### Plugin rules

- Plugins extend platform or app capability
- Plugins should be optional when possible
- Plugins should integrate through stable interfaces/hooks
- Plugins should not become a dumping ground for business logic

---

## Terminology Rules

Use these terms consistently across code, UI, documentation, and admin screens.

### Canonical meanings

- Core Engine = runtime/platform foundation
- Shell = composition UI frame
- Platform = system governance capabilities
- App = installable business domain package
- Module = functional unit inside an app
- Plugin = extension/provider/integration mechanism
- Package = install/export/import/release lifecycle mechanism

### Do not mix terms

Avoid using:

- app when you mean plugin
- plugin when you mean module
- module when you mean app

Architecture language must stay clean and stable.

---

## Current Business Direction

The first major business target is IPM manufacturing operations.

Primary functional focus:

- parts
- materials
- machines
- demand planning
- production planning
- production execution
- QC
- assembly
- dispatch
- stock visibility
- coverage visibility
- readiness visibility
- operational accountability

---

## Business Principles

## Demand model

Planning must follow this logic:

- Pre Orders = forecast demand signal
- Daily Orders = real near-term operational demand
- Buffer / Essential Stock = readiness protection
- Net Need = actual need after considering available and planned supply

### Official direction

- Pre Orders influence forecast pressure and future planning
- Daily Orders drive immediate execution needs
- Buffer recovery must be included in demand logic
- Effective available supply must reduce new production need

---

## Production model

The system must understand real operational flow, not only quantities.

Each part may need operational attributes such as:

- production source: in-house / third-party
- requires QC: yes / no
- requires assembly: yes / no
- dispatch as-is: yes / no
- delivery flow:
  - company to destination
  - third-party to destination
  - third-party to company to destination
- activity type:
  - active
  - passive / replacement / hoyohin

---

## Materials model

Materials are first-class operational entities.

The system must support:

- material master
- material stock
- material coverage
- material demand from production plans
- purchase/delivery planning
- expected incoming supply
- storage / slot awareness
- usage and cost visibility
- shortage and risk visibility

Material planning is important because storage is limited and production depends on material readiness.

---

## Role Model

The platform must support role-based operational work, not only technical admin roles.

### Platform-side roles

- Platform Admin
- App Admin
- Read-only / Viewer
- General User / Editor

### Operational roles

- Office / Planning staff
- Production Leader
- Machine Leader
- QC Leader
- Assembly Leader
- Dispatch Leader
- Management Viewer

### Important rule

Operational leaders are usually editor-level operational roles, not full platform administrators.

For example:

- QC Leader = operational role
- Dispatch Leader = operational role
- Machine Leader = operational role

They may have dedicated dashboards without becoming platform admins.

## View Modeling Architecture

The platform uses a formal 6-concept model for view vocabulary:

**Surface** (page), **View Suite** (table/form/etc.), **Interaction Profile** (worker/leader UX), **Access Authority** (who), **Control Scope** (platform/app/etc.), **Permission Profile** (capabilities).

Permissions = capability. Interaction Profile = experience.

Current experience composition law is:

```text
App/Module-owned capability catalog
  -> ACL authorization filter
  -> Workspace Profile role-experience shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

Runtime host surfaces (`/admin/*`, `/u/*`, `/displays/*`, and legacy `/me`) compose authorized owner contributions without duplicating module workflow logic.

See [docs/experience-composition-architecture-plan.md](docs/experience-composition-architecture-plan.md) for current policy and [docs/access-control-view-architecture.md](docs/access-control-view-architecture.md) for historical vocabulary.

---

## Folder Structure

Recommended canonical structure for this codebase:

```text
/app
  /...               # Core runtime layer
/apps
  /Manufacturing
    manifest.json
    bootstrap.php
    routes.php
    navigation.php
    permissions.php
    /modules
      /PartsMaster
      /Machines
      /PartMachineMap
      /PreOrders
      /DailyOrders
      /Demand
      /ProductionPlans
      /ProductionEntries
      /QC
      /Assembly
      /Dispatch
      /Coverage
      /Reports
  /Materials
    manifest.json
    bootstrap.php
    routes.php
    navigation.php
    permissions.php
    /modules
      /MaterialMaster
      /MaterialStock
      /MaterialCoverage
      /MaterialPlanning
      /PurchasePlanning
      /StorageSlots
      /MaterialCost
  /SBAIO
    ...
/plugins
  /PdfExport
  /SearchProvider
  /NotificationEmail
  /StorageAdapter
  /IntegrationConnector
/storage
/public
/tests
/docs
```

### Migration guidance

If legacy platform code still exists under old plugin-oriented folders, the normalization target is:

- platform/base logic -> move toward Core
- business packages -> move toward Apps
- subfeatures inside business packages -> move toward Modules
- adapters/integrations/providers -> move toward Plugins

---

## Routing Rules

Routes must reflect ownership clearly.

### Core routes

Examples:

- /setup
- /login
- /logout
- /me
- /admin/...

These belong to Core or platform admin surfaces.

### App routes

Examples:

- /apps/manufacturing/...
- /apps/materials/...
- /apps/sbaio/...

### Module routes

Module routes should live under their parent app route space.

Good:

- /apps/manufacturing/parts
- /apps/manufacturing/machines
- /apps/manufacturing/daily-orders
- /apps/materials/stock

Bad:

- unrelated top-level routes for business modules
- random legacy paths with no app ownership
- business routes registered in platform areas without reason

---

## Navigation Rules

Navigation must be registered by the owning layer.

### Navigation ownership

- Core nav comes from Core
- App nav comes from the app
- Modules contribute into their parent app
- Plugins may contribute only when appropriate

### Navigation principles

- avoid menu duplication
- avoid dead links
- avoid orphan module links
- hide disabled app/module surfaces automatically
- keep app grouping clear
- use stable metadata for grouping and ownership

---

## Linking Rules

Cross-links between entities and modules must be intentional and stable.

Examples:

- Part -> Machine Map
- Daily Order -> Demand -> Production Plan
- Production Entry -> QC
- QC -> Dispatch readiness
- Dispatch -> Order / Part / Plan

### Rules

- Prefer route helpers or route registry use
- Avoid scattered hardcoded path strings
- Fail gracefully when a dependency is disabled or unavailable
- Cross-links must respect permissions

---

## Data Modeling Rules

Use relational, explicit, clean data design.

### Rules

- avoid unnecessary duplicated free-text fields
- prefer foreign keys / relations where ownership is known
- keep mapping tables lean
- use explicit status fields
- keep auditability in mind
- distinguish master data, planning data, execution data, and reporting data

### Naming conventions

- Item Code = Part Number
- Item Name = Part Name
- Part-Machine Map should remain relational and minimal
- avoid duplicating part name/number into map tables unless justified

### Expected major entities

- Part
- Material
- Machine
- Pre Order
- Daily Order
- Demand Snapshot / Demand Record
- Production Plan
- Production Entry
- QC Plan
- QC Entry
- Assembly Plan / Assembly Entry
- Dispatch Entry
- Stock Ledger
- Material Purchase / Delivery Plan
- User Assignment
- Audit Log

---

## Workflow Direction

The system should represent actual operational flow.

### Demand flow

Pre Orders / Daily Orders / Buffer
-> demand calculation
-> net need
-> production + material planning

### Production flow

Production Plan
-> Production Entry
-> QC if required
-> Assembly if required
-> Dispatch readiness

### Material flow

Material demand from production
-> compare against stock + incoming supply
-> detect shortage / delay / risk / storage issue

### Dispatch flow

Ready quantity
-> packaging / cases / pallets / truck loading
-> dispatch completion and traceability

The platform must clearly show ownership and handoff between stages.

---

## Dashboards and UX

This ERP must be operationally useful.

### UX principles

- role-based
- action-oriented
- clear status visibility
- quick filters
- search-aware
- minimal clutter
- mobile-friendly where practical
- print/PDF-ready for necessary operational views
- no dead screens
- no generic ERP overload without operational value

### Dashboard expectations by role

Each role should quickly see:

- what needs attention now
- what is assigned
- what is blocked
- what is due
- what is short
- what is late
- what is ready

Examples:

- Production view: machine load, shortage risk, current/next plan
- QC view: pending checks, blocked lots, due today
- Dispatch view: ready items, partials, blocked dispatches
- Admin view: system health, assignments, exceptions, configuration, audit

---

## Search and Visibility

Search must eventually support:

- parts
- materials
- machines
- orders
- plans
- entries
- dispatches
- users
- documents

### Rule

Search results must always respect permissions and enabled app/module state.

Users should only see data and screens they are allowed to access.

---

## Localization

The system should support multilingual UI, starting with:

- English
- Japanese

### Localization rules

- use systematic translation keys
- avoid mixed translated/untranslated labels
- normalize operational terms
- keep terminology stable across modules

---

## Development Principles

Every implementation should follow these standards.

### 1. Maintainability

- modular structure
- explicit ownership
- clean folder layout
- migrations for schema changes
- minimal duplication

### 2. Operational usefulness

- prioritize real workflow value
- avoid theoretical overdesign
- focus on readiness, shortage, accountability, and flow

### 3. Extensibility

- future apps must fit the same platform model
- avoid hardcoded one-off architecture
- keep interfaces stable

### 4. Auditability

- log important actions
- log status transitions
- preserve traceability
- support approval visibility where needed

### 5. Security

- enforce permissions server-side
- do not rely only on UI restrictions
- protect destructive actions
- hide inaccessible surfaces safely

---

## Non-Goals

Avoid spending early effort on:

- generic enterprise complexity with no IPM value
- large accounting/finance scope before operations stabilize
- cosmetic overcustomization before workflows work
- cloning SAP/Odoo structures blindly
- overbuilding analytics before execution flow is reliable

---

## Recommended Build Order

### Phase 1: Core foundation

- auth
- users
- roles / permissions
- navigation
- dashboards
- app registry
- audit
- settings
- setup/recovery
- lifecycle support

### Phase 2: Master data

- Parts Master
- Material Master
- Machines
- Part-Machine Mapping
- assignments

### Phase 3: Demand and planning

- Pre Orders
- Daily Orders
- demand engine
- stock/coverage baseline
- net need calculation

### Phase 4: Execution

- Production Plans
- Production Entries
- QC
- Assembly
- Dispatch

### Phase 5: Material planning

- material demand from production
- shortage tracking
- incoming supply planning
- storage / slot awareness

### Phase 6: Dashboards and reports

- role dashboards
- Part 360
- Order 360
- coverage/readiness reports
- export / print / PDF

### Phase 7: Hardening

- ACL cleanup
- route cleanup
- link normalization
- localization cleanup
- demo data
- production-readiness fixes

---

## Feature Design Checklist

Whenever implementing a feature, document these points:

1. purpose
2. ownership layer: Core / App / Module / Plugin
3. data/entities involved
4. routes/screens involved
5. permissions involved
6. workflow impact
7. dashboard/report impact
8. migration/install impact

---

## Architecture Guardrails

Before merging new work, verify:

1. no business domain is mislabeled as plugin
2. every module has exactly one parent app
3. Core contains only platform concerns
4. routes match ownership
5. navigation is registered by the owner
6. cross-links are stable and permission-aware
7. disabling apps/modules does not leave dead links or broken UI

---

## Final Architecture Statement

IPM ERP is a modular platform with ERP Core as the platform layer, Apps as installable business domains, Modules as functional units inside apps, and Plugins only for extension/integration behavior.

The first business focus is IPM manufacturing and materials operations, including demand planning, stock/coverage, production, QC, assembly, dispatch, and operational accountability.

This architecture must remain clean, role-aware, installable, and extensible as the platform grows.
