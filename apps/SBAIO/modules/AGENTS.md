# /apps/SBAIO/modules/AGENTS.md

> This directory owns SBAIO module surfaces.
> Every new view, dashboard, chart, queue, or summary surface must declare widget intent before UI is built.

---

## Scope

Apply these rules to all existing and new SBAIO modules under this directory.

Use this file when creating or changing:
- dashboards
- attendance and payroll work surfaces
- queues and review lists
- charts and watchlists
- summary cards
- quick links
- admin/operator/display host-surface contributions, including legacy `/me`

Follow `../../../docs/experience-composition-architecture-plan.md` for experience composition. SBAIO modules own their capability catalogs and runtime meaning; ACL authorizes them, Workspace Profile shapes their role experience, and renderers eventually consume `ResolvedExperience`.

---

## Core Modeling Rule

Always keep these concepts separate:

- `view_kind` = render shape of the contribution
- `widget_type` = what kind of component it is
- `placement_zone` = where it appears
- `interaction_profiles` = who it is for

Do not let page names, role names, or route paths stand in for widget modeling.

---

## Locked View Kinds

Use these contribution render shapes:

- `kpi`
- `table`
- `form`
- `chart`
- `cards`
- `queue`
- `timeline`
- `mixed`

---

## Locked Widget Types

Use only these unless a new type is explicitly approved:

- `action`
- `informative`
- `alert`
- `queue`
- `progress`
- `chart`
- `timeline`
- `approval`
- `watchlist`
- `reference`
- `activity_feed`
- `display`

Starter set for most module work:
- `action`
- `informative`
- `alert`
- `queue`
- `progress`
- `chart`
- `reference`

---

## Locked Placement Zones

Use only these placement zones:

- `primary_work`
- `operator_actions`
- `monitoring`
- `supporting_visibility`
- `dashboard_summary`
- `display_wall`

---

## Required Widget Fields

Every module-owned widget, panel, view, or host-surface contribution should declare:

- `view_kind`
- `widget_key`
- `widget_type`
- `title` or `label`
- `interaction_profiles`
- `placement_zone`
- `priority`
- `surface_key`
- `supports_empty_state`
- `supports_clickthrough`

Add these when the module has stronger access semantics:

- `access_authorities`
- `permission_profile`
- `required_permissions`
- `required_modules`

---

## Profile Mapping

### Worker mode

- `primary_work`: `alert`, `queue`, `informative`
- `operator_actions`: `action`
- `monitoring`: `progress`, `chart`, `watchlist`
- `supporting_visibility`: `reference`, small `informative`, small `queue`

### Leader mode

- `primary_work`: `alert`, `queue`, `approval`
- `operator_actions`: `action`, `approval`
- `monitoring`: `chart`, `progress`, `timeline`, `watchlist`
- `supporting_visibility`: `reference`, `activity_feed`

### Read-only / display

- Keep read-only surfaces concise and non-interactive
- Use `display` only for ambient large-format status surfaces
- Do not expose mutating actions in `read_only` or `display`

---

## SBAIO-Specific Rules

- Attendance, payroll, leave, and staffing surfaces must prioritize the next operational decision.
- Do not turn `/me` into a second copy of attendance, payroll, or leave workflows.
- Use `watchlist` for secondary attention areas and `alert` only for direct exception pressure.
- If a tile is primarily navigation, classify it as `reference`, not `action`.
- Use `activity_feed` only when recency of change is the point of the surface.

---

## Surface Ownership Rules

- Canonical module feature routes stay under `/apps/sbaio/...`, but ownership belongs to the active module that provides the feature.
- `/admin/*`, `/u/*`, `/displays/*`, and legacy `/me` are composed runtime surfaces, not duplicate module workflow surfaces.
- Module widgets may contribute summaries, links, queues, and monitoring to admin/operator/display experiences through owner catalogs, adapters, widgets, or manifests.
- Per-user experience overrides may hide/order/pin module contributions, but must not grant access or redefine module capability ownership.
- Do not hide operational workflow logic inside shared shell surfaces.
- App-level SBAIO routes should compose suite-level workflows, not duplicate module-owned forms, queues, reports, or detail pages.

---

## Module Maturity Rules

Classify every SBAIO module by type before expanding it:

- `business_entity`
- `planning`
- `process_execution`
- `dashboard_only`
- `service_only`
- `governance`
- `integration`

Business entity modules should normally provide:

- schema or required table declaration
- routes
- controller
- `Views/index.php`
- add/edit/detail views when records are user-managed
- widget contributions when useful
- module-owned reports/exports when the report is about that module
- lifecycle-aware permissions and menu

Process execution modules should normally provide:

- schema or required table declaration
- routes
- controller
- `Views/index.php`
- queue, approval, or review surface when process-driven
- status and audit behavior
- widget contributions
- operational reports/exports when useful
- lifecycle-aware permissions and menu

Service-only modules may omit UI, but that omission must be intentional and documented.

---

## Report Ownership Rules

Module-owned reports:

- use one module's domain data
- print or export one module's records
- answer one module's operational question

App-owned reports:

- combine multiple modules
- describe suite-level SBAIO flow
- summarize cross-module operations

Platform-owned report logic:

- report/export engine
- PDF infrastructure
- scheduling
- audit
- saved filters

---

## Chart And Dashboard Rules

- A chart must answer a real staffing, attendance, payroll, or approval question.
- Do not add a chart where a queue or alert communicates the decision faster.
- Avoid duplicate KPI cards that restate the same number in the same zone.

---

## Required References

Follow these with priority:
- `../AGENTS.md`
- `../../../AGENTS.md`
- `../../../docs/experience-composition-architecture-plan.md`
- `../../../docs/architecture/MODULE-CONTRACT.md`
- `../../../docs/architecture/MODULE-COMPLETENESS-MATRIX.md`
- `../../../docs/access-control-view-architecture.md`

---

## Completion Requirement

Report:
- widget types added or changed
- placement zones used
- interaction profiles targeted
- whether admin/operator/display contribution logic changed
- whether canonical module routes changed
