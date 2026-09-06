# MODULE CONTRACT

Modules are internal app features with their own lifecycle, ownership, and UI surfaces.

Core runs the system. Apps compose business flows. Modules own focused business capabilities. Platform provides shared governance and infrastructure.

Business App and Module ownership baseline is documented in `docs/architecture/business-app-module-ownership-contract.md`. This file defines module maturity and capability details; the ownership baseline defines the minimal app/module declaration fields and must-not-own boundaries that prevent reference apps from becoming ad-hoc sample code.

---

## Module Types

Every module must declare or document one primary type:

- `business_entity`
- `planning`
- `process_execution`
- `dashboard_only`
- `service_only`
- `governance`
- `integration`

Do not treat all module folders as equal. A service-only module does not need CRUD pages. A process execution module must not be only a service wrapper.

---

## Maturity Levels

### Level 0: Service Module

Minimum:
- manifest or plugin declaration
- service class or hook
- lifecycle behavior
- dependency declaration when needed

No page UI is required.

### Level 1: UI Shell Module

Minimum:
- routes
- controller or route handler
- `Views/index.php`
- menu or navigation contribution
- server-side permission check

### Level 2: CRUD Module

Minimum:
- Level 1 requirements
- schema or migration ownership
- create/edit/detail views when user-managed
- validation
- permissions
- install/update/uninstall behavior

### Level 3: Operational Module

Minimum:
- Level 2 requirements
- queue, workboard, or leader/operator view when process-driven
- status transitions
- audit trail
- widget contributions
- operational reports or exports when needed

### Level 4: Analytical Module

Minimum:
- Level 3 requirements
- dashboards
- charts
- drilldowns
- saved filters or report definitions
- scheduled/exportable reporting when needed

---

## Capability Declarations

Modules should explicitly declare the capabilities they provide:

- routes
- views
- forms
- schema
- migrations
- controllers
- services
- permissions
- menus
- widgets
- charts
- reports
- exports
- lifecycle hooks
- localization

Missing capabilities should be intentional, not accidental.

Manifest contract fields:

```json
{
  "package_type": "module",
  "owner_app": "manufacturing",
  "module_key": "assembly_entries",
  "module_type": "process_execution",
  "target_maturity_level": "L3",
  "declared_capabilities": [
    "routes",
    "views",
    "forms",
    "schema",
    "widgets",
    "reports",
    "permissions",
    "lifecycle_hooks"
  ],
  "lifecycle_contract": {
    "routes": "active_only",
    "menus": "active_only",
    "widgets": "active_only",
    "reports": "active_only",
    "schema": "installed_or_active"
  },
  "activation_contract_enforced": true
}
```

`declared_capabilities` is the target contract, not proof that the capability is already complete. Health checks must compare declared capabilities against real files, registered surfaces, schema, and lifecycle behavior.

When `activation_contract_enforced` is true, activation must hard-block if any declared capability is missing from the module file surface. Use this flag only after the module has been promoted to its target maturity level.

---

## Completeness Scoring

Completeness scoring is a diagnostic signal, not a replacement for architectural judgment.

Score each declared capability:

- `2`: complete and owned by the module
- `1`: partial, app-owned, lifecycle-inconsistent, or missing enforcement
- `0`: missing
- `n/a`: not required for the module type

Suggested score bands:

- `90-100`: mature
- `70-89`: usable with tracked gaps
- `40-69`: partial module
- `1-39`: baby module or shell
- `0`: undeclared or non-functional

Minimum required capability groups by type:

| Module type | Minimum maturity | Required capability groups |
| --- | --- | --- |
| `service_only` | L0 | manifest, services, lifecycle hooks, dependencies when needed |
| `governance` | L0-L2 | manifest, services, permissions, lifecycle hooks, routes/views only when interactive |
| `integration` | L0-L2 | manifest, routes or services, dependencies, lifecycle hooks |
| `dashboard_only` | L1-L2 | routes, views, services, permissions, menus or dashboard contribution |
| `business_entity` | L2 | routes, controllers, views, forms, schema, permissions, menus |
| `planning` | L3 | routes, controllers, views, schema, status or approval behavior, widgets, reports when useful |
| `process_execution` | L3 | routes, controllers, views, forms, queue/workboard, schema, status transitions, audit, widgets, reports when useful |

Health checks should fail hard only for required contract violations and report warnings for optional maturity gaps.

Run the local health check with:

```bash
php tools/module_health_check.php
php tools/module_health_check.php --json
```

The runner is read-only. It compares manifest-declared target capabilities against module files and reports missing, partial, and complete capability groups.

---

## View Ownership

Module-owned views are full feature surfaces for one module.

Recommended view suite:

- `Views/index.php`: canonical module home, list, workspace, or table
- `Views/add.php`: create form
- `Views/edit.php`: edit or correction form
- `Views/detail.php`: detail, audit, approval, or release view
- `Views/queue.php`: work queue for execution modules
- `Views/leader.php`: leader or operator workboard
- `Views/report.php`: human-readable report page
- `Views/pdf_*.php`: print or PDF layout

App-owned views compose multiple modules and should not duplicate module-owned forms, queues, or reports.

---

## Route Ownership

All business URLs must remain under `/apps/{app}/...`.

Canonical module feature routes should be registered by the active module lifecycle while preserving the app URL shape:

```text
/apps/manufacturing/assembly-plans
/apps/manufacturing/assembly-entries
```

The app owns cross-module orchestration routes:

```text
/apps/manufacturing
/apps/manufacturing/processing-operation
/apps/manufacturing/stage-board
```

Compatibility aliases must be temporary and must not be used as primary links.

---

## Report Ownership

The smallest owner wins.

Module owns:
- reports that use one module's domain data
- module PDFs and print views
- module exports
- module report definitions
- module report permissions

App owns:
- cross-module dashboards
- cross-stage reports
- executive summaries
- end-to-end traceability views

Platform owns:
- report engine
- export engine
- PDF infrastructure
- scheduled report framework
- report audit framework
- saved filter framework

Technical engines do not own document meaning. For example, a PDF conversion service owns rendering mechanics, while the module owns its PDF route, template, data, permissions, filename semantics, and audit target.

Module report declarations should live in the module manifest:

```json
{
  "reports": [
    {
      "report_key": "manufacturing.assembly_entries.execution",
      "title": "Assembly Execution",
      "view": "Views/report.php",
      "export_view": "Views/export.php",
      "owner": "module",
      "scope": "assembly_entries",
      "permission": "manufacturing.assembly_entries.view",
      "lifecycle": "active_only"
    }
  ]
}
```

Report keys must be globally unique and should use `{app}.{module}.{purpose}` naming.

---

## Widget And Dashboard Ownership

### Module-owned Surfaces (single-module concern)

**Data Visualizations:**
- Module dashboards (KPI, status dashboards for one module)
- Module charts (any data visualization of module data)
- Module diagrams (workflow, state machine, process diagrams)
- Module tables/lists (entity grids, queues)
- Module forms (add/edit/detail interactions)
- Module reports (structured data output)
- Module exports (CSV, PDF, Excel for module data)

**Interaction Patterns:**
- Filters specific to one module's data
- Module-specific saved report definitions
- Module-specific widget cards to be placed on app surfaces

### App-owned Surfaces (cross-module concern)

- Cross-module dashboards (combining widgets from multiple modules)
- Cross-stage diagrams (manufacturing flow, approval chains)
- End-to-end visualizations (order-to-cash, plan-to-delivery)
- Widget orchestration and placement (which modules' widgets appear where)

### Platform-owned Engines (infrastructure)

- Dashboard composition framework
- Widget registry and placement zones
- Form rendering and validation framework
- Table/grid rendering engine
- Chart rendering and styling engine
- Report execution engine
- Export generation (PDF, CSV, Excel conversion)

**Rule:** If a surface's primary data source is one module, it is module-owned. Platform provides the rendering engine; modules own the views and data access.

Module widgets are cards for placement on `/me` and app dashboards—do not build module functionality duplicated inside host surfaces.

---

## Schema Ownership

Module schema owns tables primarily controlled by one module.

App schema owns cross-module orchestration state.

Platform schema owns governance, accounts, permissions, lifecycle, audit infrastructure, and localization infrastructure.

---

## Localization Ownership

Module-specific labels, report names, empty states, statuses, widget titles, and action labels should be owned by the module localization surface.

Do not place module-specific business labels in core/global language files unless the label is truly shared platform vocabulary.

Recommended module locale layout:

```text
modules/{Module}/lang/en.php
modules/{Module}/lang/ja.php
modules/{Module}/lang/ne.php
```

Recommended key naming:

```text
{module_key}.title
{module_key}.menu
{module_key}.reports.{report_key}.title
{module_key}.actions.{action_key}
{module_key}.empty.{surface_key}
```

Modules may start with `en.php` only, but promoted L3/L4 modules should add all supported product languages before user-facing rollout.

---

## Lifecycle Behavior

Lifecycle must affect runtime surfaces:

```text
not installed -> no module routes, menu, widgets, reports, or schema guarantee
installed     -> schema and registration available; runtime surfaces inactive
active        -> routes, menu, widgets, reports, and dashboards available
inactive      -> schema remains; runtime surfaces unavailable or controlled unavailable state
uninstalled   -> runtime registration removed; destructive purge must be explicit
```

Routes, menus, widgets, reports, and dashboards must not silently bypass module activation state.

---

## Security

Every module route and action must enforce server-side authorization.

Navigation hiding is not authorization.

Reports and exports must use the same permission standard as interactive pages.
