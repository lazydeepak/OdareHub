# /apps/Manufacturing/modules/AGENTS.md

> This directory owns Manufacturing module surfaces.
> Every new view, dashboard, chart, queue, or workboard must declare surface-contribution intent before UI is built.

---

## Scope

Apply these rules to all existing and new Manufacturing modules under this directory.

Use this file when creating or changing:
- dashboards
- workboards
- queues
- charts
- monitoring panels
- summary cards
- action tiles
- admin/operator/display host-surface contributions, including legacy `/me`

Follow `../../../docs/experience-composition-architecture-plan.md` for experience composition. Manufacturing modules own their capability catalogs and runtime meaning; ACL authorizes them, Workspace Profile shapes their role experience, and renderers eventually consume `ResolvedExperience`.

---

## Core Modeling Rule

Always keep these concepts separate:

- `view_kind` = render shape of the contribution
- `widget_type` = what kind of component it is
- `placement_zone` = where it appears
- `interaction_profiles` = who it is for

Do not collapse these into route names, role names, or page titles.

Rules:

- `View Suite` stays page-level for full module surfaces
- `view_kind` is contribution-level for admin/operator/display host-surface composition
- `widget_type` is operational purpose, not render shape

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

## Manufacturing-Specific Rules

- Worker views must prioritize current job, next job, required action, and blocker visibility.
- Do not rebuild full module workflow logic inside `/me`; contribute summaries, actions, queues, and monitoring only.
- A chart is not a queue, and a queue is not an alert. Choose the real widget type.
- If a widget exists only to navigate, classify it as `reference`, not `action`.
- If the widget represents stalled risk or exception pressure, classify it as `alert` or `watchlist`, not generic `informative`.
- If the widget shows handoff or release movement, prefer `approval` or `queue` based on whether action is pending or records are listed.

---

## Surface Ownership Rules

- Canonical module feature routes stay under `/apps/manufacturing/...`, but ownership belongs to the active module that provides the feature.
- `/admin/*`, `/u/*`, `/displays/*`, and legacy `/me` are composed runtime surfaces, not second module implementations.
- Module surface contributions may feed admin/operator/display experiences through owner catalogs, adapters, widgets, or manifests, but module forms and execution logic stay module-owned.
- Per-user experience overrides may hide/order/pin module contributions, but must not grant access or redefine module capability ownership.
- Do not create duplicate dashboards that restate the same workflow with different labels.
- App-level Manufacturing routes should compose cross-stage workflows, not duplicate module-owned forms, queues, reports, or detail pages.

---

## Module Maturity Rules

Classify every Manufacturing module by type before expanding it:

- `business_entity`
- `planning`
- `process_execution`
- `dashboard_only`
- `service_only`
- `governance`
- `integration`

Planning modules should normally provide:

- schema or schema contract
- routes
- controller
- `Views/index.php`
- detail or approval surface when needed
- widget contributions when useful
- module-owned reports/exports when the report is about that module
- lifecycle-aware permissions and menu

Process execution modules should normally provide:

- schema or migrations
- routes
- controller
- `Views/index.php`
- add/edit or correction forms
- queue or leader/operator workboard
- status and audit behavior
- widget contributions
- execution reports/exports when useful
- lifecycle-aware permissions and menu

Service-only and governance modules may omit UI, but that omission must be intentional and documented.

---

## Report Ownership Rules

Module-owned reports:

- use one module's domain data
- print or export one module's records
- answer one module's operational question

App-owned reports:

- combine multiple modules
- describe cross-stage Manufacturing flow
- summarize end-to-end processing status

Platform-owned report logic:

- report/export engine
- PDF infrastructure
- scheduling
- audit
- saved filters

---

## Chart And Dashboard Rules

- A chart must answer an operational question, not fill space.
- A dashboard must mix only the widget types needed for that audience.
- Do not add charts when a queue or alert communicates the decision faster.
- Do not add KPI cards that duplicate the same number already shown in the same zone.

---

## Required References

Follow these with priority:
- `../AGENTS.md`
- `../../../AGENTS.md`
- `../../../docs/experience-composition-architecture-plan.md`
- `../../../docs/architecture/MODULE-CONTRACT.md`
- `../../../docs/architecture/MODULE-COMPLETENESS-MATRIX.md`
- `../../../docs/access-control-view-architecture.md`
- `../../../docs/widget-contribution-runtime-contract.md`

---

## Completion Requirement

Report:
- view kinds added or changed
- widget types added or changed
- placement zones used
- interaction profiles targeted
- whether admin/operator/display contribution logic changed
- whether canonical module routes changed
