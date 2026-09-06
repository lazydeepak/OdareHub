# MODULE COMPLETENESS MATRIX

This matrix tracks whether modules are mature feature modules, service modules, or incomplete shells.

Use this document before moving route ownership, adding reports, or promoting a module as production-ready.

---

## Legend

- `yes`: present and owned by the module
- `partial`: present but incomplete, app-owned, or lifecycle-inconsistent
- `no`: missing
- `n/a`: not required for this module type

---

## Manufacturing Snapshot

| Module | Type | Target | Routes | Views | Forms | Schema | Widgets | Reports | Dashboard | Lifecycle |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| AssemblyPlans | planning | L3 | yes | yes | yes | yes | yes | yes | no | partial |
| AssemblyEntries | process_execution | L3 | yes | yes | yes | yes | yes | yes | no | partial |
| Coverage | dashboard_only | L2 | yes | no | n/a | no | no | no | no | partial |
| DailyOrders | business_entity | L3 | yes | yes | yes | yes | no | partial | yes | partial |
| DispatchEntries | process_execution | L3 | yes | yes | yes | yes | yes | yes | yes | partial |
| Ledger | business_entity | L2 | yes | yes | yes | yes | no | partial | no | partial |
| Machines | business_entity | L3 | yes | yes | yes | yes | no | no | yes | partial |
| MaterialManagement | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| PartMachineMap | business_entity | L2 | yes | yes | yes | yes | no | no | yes | partial |
| PreOrders | business_entity | L2 | yes | yes | yes | yes | no | no | yes | partial |
| ProductionEntries | process_execution | L3 | yes | yes | yes | yes | yes | no | yes | partial |
| ProductionPlans | planning | L3 | yes | yes | yes | yes | no | yes | yes | partial |
| ProductionQueue | dashboard_only | L2 | yes | yes | n/a | no | no | no | yes | partial |
| Products | business_entity | L3 | yes | yes | yes | yes | yes | no | yes | partial |
| QCEntries | process_execution | L3 | yes | yes | yes | yes | yes | partial | yes | partial |
| QCPlans | planning | L3 | yes | yes | yes | yes | no | yes | yes | partial |
| Supply | service_only | L0 | yes | n/a | n/a | n/a | no | no | n/a | partial |
| Workflow | governance | L0 | yes | n/a | n/a | n/a | no | no | n/a | partial |

---

## SBAIO Snapshot

| Module | Type | Target | Routes | Views | Forms | Schema | Widgets | Reports | Dashboard | Lifecycle |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Attendance | process_execution | L3 | yes | yes | no | partial | yes | yes | no | partial |
| Customers | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| Expenses | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| Leave | process_execution | L3 | yes | yes | no | partial | yes | yes | no | partial |
| Notices | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| Payroll | process_execution | L3 | yes | yes | no | partial | yes | yes | no | partial |
| Sales | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| Schedules | planning | L2 | yes | yes | no | partial | no | yes | no | partial |
| Staff | business_entity | L2 | yes | yes | no | partial | no | yes | no | partial |
| Tasks | process_execution | L2 | yes | yes | no | partial | no | yes | no | partial |
| Timecards | process_execution | L3 | yes | yes | no | partial | yes | yes | no | partial |

---

## Platform Snapshot

| Module | Type | Target | Routes | Views | Forms | Schema | Widgets | Reports | Dashboard | Lifecycle |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| CompanySetup | integration | L0 | yes | n/a | n/a | n/a | no | no | n/a | partial |
| Organization | governance | L2 | yes | yes | partial | partial | no | no | no | partial |
| QRCode | integration | L2 | yes | yes | partial | partial | no | no | no | partial |

---

## Metadata Classification

Module manifests should include explicit classification fields:

```json
{
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
  ]
}
```

`declared_capabilities` represents the target module contract. It is not a claim that every capability is already fully implemented.

The health-check runner should use this matrix and the manifest fields to score current completeness.

Type classification is required on every module. It is not metadata-only. It drives:

- required capability expectations per module family
- ownership boundaries between module/app/platform surfaces
- maturity promotion decisions and gap prioritization

---

## Type To Ownership Guide

### Module-owned (all single-module UI and data)

**UI Surfaces (visualizations and interactions for one module's data):**
- Entity reports
- Operational reports
- Module dashboards (KPI, status dashboards specific to one module)
- Module charts (data visualizations of module data)
- Module diagrams (workflow, state, process diagrams for one module)
- Module table views (lists, grids for module entities)
- Module form views (add/edit/detail forms for module entities)
- Module PDFs/prints (single-module document generation)
- Module exports (single-module data export formats)
- Module widgets (card/tile/panel contributions to host surfaces)

**Non-UI:**
- Module schema/migrations
- Module permissions (policy rules for one module)
- Module lifecycle hooks (install, activate, deactivate, uninstall)
- Menu/navigation contributions (module's own nav items)

### App-owned (cross-module orchestration)

**UI Surfaces (visualizations combining multiple modules):**
- Cross-module dashboards (combining data from multiple modules)
- Cross-stage diagrams (manufacturing flow, approval chains, handoff pipelines)
- Cross-stage reports (reports spanning multiple modules)
- Executive summaries (high-level status across the app)
- Processing pipeline views (end-to-end workflow visualizations)
- Reports combining multiple modules

**Non-UI:**
- App navigation/composition (which modules are exposed, in what order)
- App routing orchestration (canonical vs. alias route management)
- App-level widget placement decisions (which modules' widgets appear where)

### Platform-owned (infrastructure and engines)

**UI Framework:**
- Dashboard framework/composition engine
- Widget registry and placement zones (`/me` host surfaces)
- Form rendering and validation framework
- Table/grid rendering framework
- Chart rendering engine (styling, export, interaction patterns)
- Shell composition and `/me` orchestration

**Data Engines:**
- Report execution engine
- Export engine (PDF, CSV, Excel generation)
- Scheduled report framework

**Governance:**
- Permissions and audit framework (ACL engine, audit logging)
- Role definitions and capability grants
- Localization contracts (translation keys and loading)

**Rule:** If a surface visualizes or allows interaction with one module's data or workflow—regardless of format (chart, form, diagram, table, dashboard)—it is module-owned. The platform provides the rendering engines; modules own the views themselves.

---

## Type-Aware Required Capability Baseline

Health reporting must evaluate these required capability groups by `module_type` even when declarations are incomplete.

| Module Type | Required capability baseline |
| --- | --- |
| `service_only` | `services`, `lifecycle_hooks` |
| `governance` | `services`, `permissions`, `lifecycle_hooks` |
| `integration` | `routes`, `lifecycle_hooks` |
| `dashboard_only` | `routes`, `views`, `permissions` |
| `business_entity` | `routes`, `controllers`, `views`, `forms`, `schema`, `permissions` |
| `planning` | `routes`, `controllers`, `views`, `schema`, `permissions`, `reports` |
| `process_execution` | `routes`, `controllers`, `views`, `forms`, `schema`, `permissions`, `reports` |

Local runner:

```bash
php tools/module_health_check.php
```

---

## Scoring Rules

Each capability is scored as:

| Score | Meaning |
| --- | --- |
| `2` | Complete and module-owned |
| `1` | Partial, app-owned, lifecycle-inconsistent, or missing enforcement |
| `0` | Missing |
| `n/a` | Not required for the module type |

Capability score groups:

- routes
- controllers
- views
- forms
- schema
- widgets
- reports
- dashboard
- permissions
- lifecycle
- localization
- tests

Module maturity band:

| Percent | Band |
| --- | --- |
| `90-100` | mature |
| `70-89` | usable with tracked gaps |
| `40-69` | partial module |
| `1-39` | baby module or shell |
| `0` | undeclared or non-functional |

Assembly currently remains below target because module-owned routes, controllers, views, forms, reports, and lifecycle route ownership are still incomplete.

---

## Priority Gaps

1. Assembly modules must be promoted from lifecycle shells to mature planning/execution modules.
2. Route ownership must become lifecycle-aware before compatibility aliases are retired.
3. Reports must be moved or created under module ownership when they describe one module's data.
4. Service-only modules must be explicitly classified so they are not judged as missing UI.
5. Dashboard-only modules must declare why forms and schema are not required.
6. SBAIO modules need form/report/dashboard maturity decisions; most are currently index-only operational shells.
7. Platform modules should stay governance/integration-oriented and avoid becoming business modules.

---

## Assembly Target State

### AssemblyPlans

Target level: L3 planning module.

Required:
- module-owned routes under `/apps/manufacturing/assembly-plans`
- controller
- `Views/index.php`
- `Views/detail.php`
- approval/release actions
- schema contract or migrations
- widget registry
- module report/export for assembly plan readiness
- permissions
- lifecycle-aware menu and route registration

### AssemblyEntries

Target level: L3 process execution module.

Required:
- module-owned routes under `/apps/manufacturing/assembly-entries`
- module-owned queue route under `/apps/manufacturing/assembly-queue` if queue is assigned to this module
- controller
- `Views/index.php`
- `Views/add.php`
- `Views/edit.php`
- `Views/queue.php` or `Views/leader.php`
- status and audit behavior
- schema migrations
- widget registry
- execution report/export
- permissions
- lifecycle-aware menu and route registration

---

## Maintenance Rule

Update this matrix whenever a module gains or loses any of these capabilities:

- route
- controller
- view
- form
- schema
- widget
- report
- dashboard
- lifecycle hook
- permission surface

---

## Report Registry Declarations

Initial module-owned report declarations:

| Report Key | Owner Module | Scope | Lifecycle |
| --- | --- | --- | --- |
| `manufacturing.assembly_plans.readiness` | AssemblyPlans | assembly plan readiness and approvals | active_only |
| `manufacturing.assembly_entries.execution` | AssemblyEntries | assembly execution output and release status | active_only |
