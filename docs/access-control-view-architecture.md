# Access Control & View Architecture

## Status
- Historical canonical decision for the first access/view-modeling pass
- Formalized on 2026-04-19
- Superseded for ACL, Workspace Profile, Studio, and runtime experience composition by [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md)
- Still useful for vocabulary such as Surface, View Suite, Interaction Profile, Access Authority, Control Scope, Permission Profile, and Surface Contribution

Current resolution law:

```text
App/Module-owned capability catalog
  -> ACL authorization filter
  -> Workspace Profile role-experience shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

`/me` references in this document are legacy/admin-composition history. Operator workspaces now live under `/u/{username}/*`, admin governance under `/admin/{username}/*`, and readonly display surfaces under `/displays/*`.

## Composition Boundary (Phase 0 Policy Lock)

ACL **governs** experience but does **not own experience composition**.

- ACL is the authorization filter: identity, authority role, assigned apps, permissions, scopes, and server-side allow/deny.
- ACL must not be the source of new experience-shaping fields. Capability catalogs live with their owning app/module; composition policy lives in Workspace Profile; per-user overrides live as targeted artifacts.
- Transitional ACL-stored fields (`me_dashboard_blocks`, `me_plugin_cards`, `display_surfaces`, `operator_views`) are read-only carriers during migration. Do not introduce new fields of this shape on ACL records.
- New experience-shaping work belongs in Workspace Profile or the Studio-powered Experience Composer, not in Access Control.

See [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md) for the full ownership map and migration phases.

## Purpose

This document records the platform's **view modeling architecture** so UI modeling, access assignment, and host-surface composition avoid mixing route shape, role labels, permissions, and UX behavior into the same concept.

This pass is intentionally architectural, not a broad runtime rewrite.

## Scope

This model governs:
- Platform governance surfaces such as Access Control Board and User Control Board
- Existing and future app/module operational surfaces
- User assignment data and admin UX
- legacy `/me` composition rules and vocabulary for newer `/admin/*`, `/u/*`, and `/displays/*` composition

This model does **not** authorize duplicating module workflow logic inside `/me`, `/u/*`, `/admin/*`, `/displays/*`, or shell surfaces.

## Canonical Concepts

The platform models views using six distinct concepts.

### 1. Surface

**Surface** = business page or operational surface.

Examples:
- `/me`
- Approval Inbox
- Production Dashboard
- Material Stock
- Access Control Board

Surface answers:
- What page or work area is this?
- What feature or operational context does it represent?

Surface is the canonical page-level concept.

### 2. View Suite

**View Suite** = UI shape.

Allowed examples:
- `table`
- `form`
- `chart`
- `board`
- `cards`
- `display`
- `mixed`

View Suite answers:
- What rendering pattern dominates this Surface?
- Is the Surface primarily tabular, card-based, form-driven, chart-oriented, or mixed?

View Suite is about presentation structure, not authority or permissions.

### 3. Interaction Profile

**Interaction Profile** = UX behavior contract.

Canonical examples:
- `worker`
- `leader`
- `admin`
- `read_only`
- `display`

Interaction Profile answers:
- What experience style does the user receive on this Surface?
- Is the Surface optimized for doing work, supervising, governing, passively viewing, or wall-display presentation?

Interaction Profile governs experience patterns such as:
- inline action density
- review and exception posture
- visibility of drill-down controls
- summary vs queue emphasis
- edit affordance posture

Interaction Profile is **not** a synonym for permission or editability.

### 4. Access Authority

**Access Authority** = who may receive or use the view.

Canonical examples:
- `platform_admin`
- `app_admin`
- `leader`
- `worker`
- `observer`

Access Authority answers:
- Which classes of users may be assigned or granted this Surface?
- At what authority level is this Surface intended to operate?

Critical rule:
- **Platform Admin** and **App Admin** are distinct authority classes and must never be collapsed into one generic `admin`.

### 5. Control Scope

**Control Scope** = what the Surface governs.

Canonical examples:
- `platform`
- `app`
- `module`
- `personal`
- `shared`

Control Scope answers:
- Is this Surface governing platform-wide state, app-level state, module-level state, a personal workspace, or a shared operational area?

### 6. Permission Profile

**Permission Profile** = what operations are allowed.

Permission Profile answers:
- What the user may do on the Surface
- Which operations are allowed or forbidden
- Which actions, transitions, exports, approvals, or edits are actually authorized

Permission Profile is capability, not UX style.

## Golden Rule

**Permissions define capability; Interaction Profile defines experience.**

Examples:
- Permission Profile allows approval actions + Interaction Profile `leader`:
  oversight-oriented review experience with decision controls
- Permission Profile allows read-only access + Interaction Profile `leader`:
  supervisory KPI/queue experience without edit authority
- Permission Profile allows create/update actions + Interaction Profile `worker`:
  action-first work surface with task completion affordances

Never infer one from the other.

## Why These Concepts Stay Separate

### Surface vs View Suite

A Surface is the page-level feature.
A View Suite is the rendering shape of that Surface.

Example:
- Material Stock = Surface
- `table` = View Suite

### View Suite vs Interaction Profile

Two Surfaces can both use `table` while giving very different experiences.

Example:
- Worker queue table
- Admin governance table

Same View Suite, different Interaction Profile.

### Interaction Profile vs Permission Profile

This is the most important separation in the model.

Interaction Profile controls:
- how the Surface feels
- how actions are arranged
- how information is prioritized

Permission Profile controls:
- what actions are actually allowed
- whether create/update/approve/export/delete operations exist

This prevents the platform from treating `read_only`, `admin`, or `leader` as disguised ACL values.

### Access Authority vs Control Scope

These answer different questions.

Access Authority:
- who is allowed to receive the Surface

Control Scope:
- what domain level the Surface governs

Example:
- Access Control Board
  - Access Authority: `platform_admin`
  - Control Scope: `platform`

Example:
- Material Stock
  - Access Authority: `worker`, `leader`, `observer` depending on assignment
  - Control Scope: `module`

## Relationship Model

Recommended architecture relationship:

```text
Surface
  -> has one primary View Suite
  -> has one Interaction Profile per delivered experience
  -> is assignable through Access Authority rules
  -> is classified by Control Scope
  -> is governed by Permission Profile capability rules
```

This deliberately avoids a single-field `view_type` model.

## Host-Surface Composition Rule

`/admin/*`, `/u/*`, `/displays/*`, and legacy `/me` are **composed runtime surfaces**, not duplicate workflow implementations.

That means:
- host surfaces may aggregate or compose assigned/allowed Surfaces
- host surfaces may render composition regions, cards, summaries, links, queues, panels, and display widgets
- host surfaces must not reimplement module workflows as a second copy of module logic

Examples:
- acceptable:
  - summary cards
  - assigned queues
  - quick links into app/module Surfaces
  - cross-surface orchestration
- not acceptable:
  - rebuilding Material Receipt logic directly inside `/me`
  - cloning a module workboard with separate state rules
  - introducing shell-owned business behavior that belongs in apps/modules

Shell owns composition.
Apps and modules own workflow logic.

## Platform Admin vs App Admin

These remain permanently distinct.

### Platform Admin

Platform Admin governs:
- platform-wide governance state
- system setup
- routing/governance/access architecture
- cross-app administrative control

Platform Admin Surfaces usually have:
- Access Authority `platform_admin`
- Control Scope `platform`
- Interaction Profile `admin`

### App Admin

App Admin governs:
- app-level operational governance
- app configuration and assignment within one app boundary
- app-specific intervention and oversight

App Admin Surfaces usually have:
- Access Authority `app_admin`
- Control Scope `app`
- Interaction Profile `admin` or `leader` depending on the Surface

Platform Admin is not just a stronger App Admin.
App Admin is not a reduced Platform Admin.

They are different authority classes in the architecture.

## Terminology Direction

Use these terms consistently in future docs and implementation.

### Preferred canonical terms

- `Surface`
- `View Suite`
- `Interaction Profile`
- `Access Authority`
- `Control Scope`
- `Permission Profile`

### Host-surface contribution terms

Use these terms for dynamic contributions rendered inside `/me` or other host pages:

- `Surface Contribution` = module/app-owned contribution object
- `View Kind` = render shape of the contribution
- `Widget Type` = operational purpose of the contribution
- `Placement Zone` = host placement target

Rule:

- `View Suite` is page-level
- `View Kind` is contribution-level
- `Widget Type` is not a synonym for render shape

### Contribution migration matrix

Use this matrix to decide whether a host-surface contribution still belongs in an app-level host service or should be moved into a module-owned registry.

Canonical rule:

- `HostSurfaceContributionService` may route, annotate, merge, sort, or gate visibility.
- Module registries should own module-specific queries, counts, entries, and widget payload shape.
- `/me` remains shell-composed; apps contribute widgets, not duplicate workflow implementations.

#### Current codebase matrix

| App | Host service | Region | Current provider | Source of truth | Status | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `summary_cards` | `ProductionEntryWidgetRegistry` | `apps/Manufacturing/modules/ProductionEntries/Services/ProductionEntryWidgetRegistry.php` | Migrated | Module-owned KPI contribution |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `summary_cards` | `QCEntryWidgetRegistry` | `apps/Manufacturing/modules/QCEntries/Services/QCEntryWidgetRegistry.php` | Migrated | Module-owned KPI contribution |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `summary_cards` | `DispatchEntryWidgetRegistry` | `apps/Manufacturing/modules/DispatchEntries/Services/DispatchEntryWidgetRegistry.php` | Migrated | Module-owned KPI contribution |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `monitoring_sections` | `ProductWidgetRegistry` | `apps/Manufacturing/modules/Products/Services/ProductWidgetRegistry.php` | Migrated | Product risk/watchlist contribution |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `header_actions` | Host service inline | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | Keep in host | Shell/app navigation action, not module business logic |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `quick_links` | Host service inline | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | Keep in host | App-level cross-module references |
| Manufacturing | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | `monitoring_sections` | Host service inline + module merge | `apps/Manufacturing/Services/HostSurfaceContributionService.php` | Mixed by design | Host may add shared monitoring sections while merging module sections |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `summary_cards` | `AttendanceWidgetRegistry` | `apps/SBAIO/modules/Attendance/Services/AttendanceWidgetRegistry.php` | Migrated | Module owns counts and recent missing-punch entries |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `summary_cards` | `TimecardWidgetRegistry` | `apps/SBAIO/modules/Timecards/Services/TimecardWidgetRegistry.php` | Migrated | Module owns draft timecard data |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `summary_cards` | `PayrollWidgetRegistry` | `apps/SBAIO/modules/Payroll/Services/PayrollWidgetRegistry.php` | Migrated | Module owns draft payroll data |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `summary_cards` | `LeaveWidgetRegistry` | `apps/SBAIO/modules/Leave/Services/LeaveWidgetRegistry.php` | Migrated | Module owns pending leave data |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `watchlist_items` | `AttendanceWidgetRegistry` | `apps/SBAIO/modules/Attendance/Services/AttendanceWidgetRegistry.php` | Migrated | Module-owned watchlist item |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `watchlist_items` | `TimecardWidgetRegistry` | `apps/SBAIO/modules/Timecards/Services/TimecardWidgetRegistry.php` | Migrated | Module-owned watchlist item |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `watchlist_items` | `PayrollWidgetRegistry` | `apps/SBAIO/modules/Payroll/Services/PayrollWidgetRegistry.php` | Migrated | Module-owned watchlist item |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `watchlist_items` | `LeaveWidgetRegistry` | `apps/SBAIO/modules/Leave/Services/LeaveWidgetRegistry.php` | Migrated | Module-owned watchlist item |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `header_actions` | Host service inline | `apps/SBAIO/Services/HostSurfaceContributionService.php` | Keep in host | App landing action, not module business logic |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `quick_links` | Host service inline | `apps/SBAIO/Services/HostSurfaceContributionService.php` | Keep in host | App-level cross-module references |
| SBAIO | `apps/SBAIO/Services/HostSurfaceContributionService.php` | `monitoring_sections` | Host service inline assembly | `apps/SBAIO/Services/HostSurfaceContributionService.php` | Keep in host | Host composes queue section from module-owned watchlist items |

#### Migration decision rule

Move a contribution out of the host service when all of the following are true:

- the widget depends on one module's tables or service queries
- the widget's labels, entry rows, or thresholds are module-specific
- the widget can be delivered as a standalone contribution for one or more regions

Keep a contribution in the host service when any of the following are true:

- it is a shell/app navigation action rather than module business state
- it merges multiple module contributions into one composed shell section
- it applies visibility or assignment gating across modules rather than computing module data

#### Next migration targets

- Manufacturing can keep its current host-owned `header_actions` and `quick_links` unless those become module-specific.
- If Manufacturing grows more module-specific monitoring blocks, prefer new registries over expanding host-owned query logic.
- Any future SBAIO module contributing into `/me` should add a registry first and only then be wired through `moduleWidgetProviders()`.

### Terms that should be treated carefully

#### `role`

Use `role` only when you truly mean:
- a user role label
- a legacy operational profile
- a compatibility mapping

Do **not** use `role` as a substitute for:
- Surface
- Interaction Profile
- Access Authority
- Permission Profile

#### `dashboard type`

Treat this as transitional legacy language in existing code.

Where possible, interpret it as a mixed legacy field that currently blends:
- landing intent
- Surface selection
- Interaction Profile hints

Future work should split this into the canonical model instead of extending it as-is.

#### `view`

Use carefully.

`view` may mean:
- a rendered page in generic English
- a legacy token category in current code
- a future Surface-level concept

When precision matters, prefer:
- Surface
- View Suite
- rendered template

#### `mode`

Use only for actual automation/manual behavior toggles or compatibility controls.
Do not use `mode` as a generic substitute for architecture concepts.

#### `admin`

Never use bare `admin` when the distinction matters.
Say:
- Platform Admin
- App Admin
- admin Interaction Profile

## Current Repo Conflicts To Resolve In Favor Of This Model

The following older assumptions are now subordinate to this architecture:

### Conflict 1: "dashboard type" acting as a combined identity + experience + landing field

Current code and docs still use `dashboard_type` as a mixed concept.

Decision:
- treat `dashboard_type` as transitional legacy modeling
- do not expand that mixed field as the future architecture

### Conflict 2: `/me` described as if it were a workflow surface

Some older language treats My Work as if it owns workflow behavior.

Decision:
- `/me` is the composed workspace entry
- workflow logic remains app/module-owned

### Conflict 3: ambiguous use of "role" to mean authority, UX posture, and permissions

Decision:
- use Access Authority, Interaction Profile, and Permission Profile separately

### Conflict 4: generic `admin` language

Decision:
- Platform Admin and App Admin remain distinct classes

## Implementation Order

Follow this sequence for rollout.

1. **Access Control Board enhancement**
   - expose the new assignment model clearly
   - separate authority, experience, scope, and capability instead of blending them

2. **Old view normalization**
   - audit existing Surfaces
   - classify existing routes and pages into the canonical model
   - identify mixed legacy fields that need decomposition

3. **New view creation**
   - create future Surfaces against the canonical model
   - do not invent new blended one-field classifications

4. **User assignment**
   - align assignment UX and data modeling with the new concepts
   - preserve compatibility where needed, but do not let compatibility define the architecture

5. **`/me` composition**
   - compose assigned Surfaces and regions
   - keep `/me` shell-owned and non-duplicative

## Rollout Checklist

### Phase 1: Access Control Board enhancement
- [ ] Inventory current assignment fields that blend authority, interaction, landing, and permissions
- [ ] Define admin UX sections for Access Authority, Interaction Profile, Control Scope, and Permission Profile
- [ ] Mark legacy fields such as `dashboard_type` and compatibility profiles as transitional in the UI
- [ ] Ensure Platform Admin and App Admin remain separate assignment choices

### Phase 2: Old view inventory and normalization
- [ ] Build inventory of current Surfaces across Platform, Shell, Manufacturing, SBAIO, and admin areas
- [ ] Classify each Surface with:
  - Surface name
  - View Suite
  - Interaction Profile
  - Access Authority
  - Control Scope
  - Permission Profile source
- [ ] Identify conflicting or duplicate Surface ownership
- [ ] Record transitional mappings from old terms to canonical terms

### Phase 3: Existing view classification cleanup
- [ ] Normalize docs and code comments where mixed language causes architecture confusion
- [ ] Identify where `dashboard_type`, `role`, `mode`, and `view` are overloaded
- [ ] Add migration notes for fields/tokens that cannot be split immediately

### Phase 4: New view rollout
- [ ] Require new UI work to declare Surface, View Suite, Interaction Profile, Access Authority, and Control Scope explicitly
- [ ] Add worker/leader/admin/read_only/display view variants only where justified
- [ ] Keep Permission Profile capability decisions separate from UX profile decisions

### Phase 5: Assignment model updates
- [ ] Update assignment services/contracts to carry canonical concepts directly
- [ ] Preserve compatibility mappings without letting them become the primary model
- [ ] Introduce explicit Surface assignment logic where the runtime is ready

### Phase 6: `/me` composition rules
- [ ] Compose host surfaces from assigned Surfaces and shell/runtime composition regions
- [ ] Prevent host surfaces from becoming a second implementation of module workflow logic
- [ ] Keep app/module quick links and summaries contribution-based
- [ ] Define which Surfaces may contribute cards, tables, links, or monitoring sections into admin/operator/display host surfaces

### Contribution Contract Direction

- [ ] Use `Surface Contribution` as the parent term for dynamic host-surface composition objects
- [ ] Keep `widget_type` as the operational-purpose field
- [ ] Introduce `view_kind` as the contribution-level render shape field
- [ ] Keep `View Suite` reserved for full Surface/page render patterns

### Next Pending Roadmap Phase: Worker-Perspective UI Creation

Status: Pending after existing-view normalization stabilization.

Goal:
- Build operational worker-facing UI/surfaces used day to day by ordinary users, dynamically driven by effective access and canonical governance concepts.
- Design from worker, operational leader, read-only observer, and TV/display perspectives, not from platform-admin layout assumptions.

#### 1) Worker `/me` experience
- [ ] `/me` shows only relevant assigned app/module surfaces for the current user
- [ ] Current work appears first
- [ ] Next/queued work appears second
- [ ] Read-only supporting visibility appears only where effective access allows it
- [ ] Admin clutter and raw governance terminology are excluded from worker view

#### 2) Worker execution views
- [ ] Production / Assembly / QC / Dispatch worker surfaces prioritize execution over admin summaries
- [ ] Current job, next job, required action, and status are always explicit
- [ ] Noise is reduced for operational focus
- [ ] Management/governance tools remain hidden unless effective access level allows them

#### 3) Role-shaped surfaces
- [ ] UI behavior differs by Worker / Leader / Admin / Read-only / TV-Display
- [ ] Shape is derived from Interaction Profile + effective module access + computed scope + read-only/display assignment
- [ ] No hardcoded per-page role assumptions

#### 4) Computed scope in worker UI
- [ ] Worker surfaces respect assigned machines, assigned parts, and assigned work/process area
- [ ] Read-only cross-role visibility is shown only when effective access allows
- [ ] Scope is visible for operational clarity but not editable in worker screens

#### 5) Read-only / display UI
- [ ] Display-safe surfaces exist for TV/dashboard and observer use
- [ ] Display-safe surfaces remain simplified and read-only

#### 6) Worker-perspective navigation and quick links
- [ ] Navigation shows what matters now for execution
- [ ] Navigation and quick links are dynamically shaped by effective access
- [ ] Platform-admin clutter and technical labels are removed from worker nav
- [ ] Workboards, queues, dashboards, and action surfaces are prioritized appropriately

Rules:
- [ ] Do not build worker UI as reduced copies of platform-admin UI
- [ ] Do not duplicate governance logic in worker screens
- [ ] Keep `/me` as composed workspace with effective visible surfaces
- [ ] Preserve canonical model: Access Authority, Interaction Profile, Control Scope, Permission Profile, Surface/View Suite
- [ ] Keep `Surface Contribution`, `View Kind`, `Widget Type`, and `Placement Zone` distinct in host-surface composition
- [ ] Runtime behavior is generated from effective access, not hardcoded role pages

Success condition:
- [ ] Platform delivers a clear worker-perspective UI layer that is operational, focused, and role-appropriate while remaining dynamically governed by the same canonical model.

## Minimal Scaffolding Guidance

Small structural scaffolding is allowed only when it helps rollout without forcing premature runtime behavior.

Acceptable examples:
- constants or enum placeholders in Platform-owned architecture layers
- registry-contract TODO anchors
- transitional comments marking mixed legacy fields

Not acceptable in this pass:
- full runtime registry behavior
- new broad database modeling
- shell-level workflow duplication
- one-field "view type" shortcuts that collapse the model

## Related Docs

- [docs/architecture/ROUTING-STANDARD.md](docs/architecture/ROUTING-STANDARD.md)
- [docs/unified-operational-landing-strategy.md](docs/unified-operational-landing-strategy.md)
- [docs/phase6-3-platform-assignment-interface-plan.md](docs/phase6-3-platform-assignment-interface-plan.md)
- [docs/architecture/migration-inventory-2026-04-18.md](docs/architecture/migration-inventory-2026-04-18.md)
- [docs/widget-contribution-runtime-contract.md](docs/widget-contribution-runtime-contract.md)
