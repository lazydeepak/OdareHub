# Experience Composition Architecture Plan

**Status**: Implemented Architecture Map with Compatibility Bridges
**Date**: 2026-05-18
**Scope**: ACL, Workspace Profile, Studio, app/module-owned experience catalogs, and runtime layer rendering

---

## 1. Decision Summary

OdareHub should separate authorization from experience composition.

The intended model is:

```text
App/Module-owned capability catalog
  -> ACL authorization filter
  -> Workspace Profile role-experience shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

Studio participates as a governed builder/editor hired by app and module owners. Studio creates, modifies, analyzes, diffs, and applies artifacts on behalf of owners, then hands those artifacts back to the owning app/module. Studio keeps records for diagnostics and future upgrades, but Studio does not own runtime business capabilities.

---

## 2. Current Truth In The System

### 2.1 ACL Currently Does Two Jobs

`user_dashboard_assignments` and related ACL services currently control access and also shape experience.

Current access responsibilities include:

- authority role / account type
- operational role
- assigned apps
- access profiles / role packs
- permissions
- view/table/chart access
- module visibility
- cross-functional grants
- scoped machines, parts, tasks, departments, branches

Current experience-shaping responsibilities include:

- `me_dashboard_blocks`
- `me_plugin_cards`
- `display_surfaces`
- `operator_views`
- `workspace_profile_key`
- default app
- default landing page
- module visibility as both access and UI filter

This creates ambiguity: ACL is both security law and presentation/layout law.

### 2.2 Workspace Profile Exists As Runtime Shaping Law

The `workspace_profiles` table stores role-experience fields:

- `owner_type`
- `owner_key`
- `authority_role`
- `governance_scope`
- `landing_route`
- `nav_sections`
- `quick_actions`
- `module_visibility`
- `permissions`
- `dashboard_blocks`
- `assigned_apps`
- `access_profiles`
- `widget_discovery`

Runtime consumes key Workspace Profile fields through the resolved experience bridge and Shell surfaces:

- `nav_sections` shape operator sidebar experience when present.
- `quick_actions` shape operator quick-action strips when present.
- `module_visibility` participates in authorization/visibility shaping.
- `dashboard_blocks` shape admin/display surfaced blocks through resolved experience consumers.
- `workspace_profile_key` pins a user to a profile.

Some profile fields still have compatibility behavior and conservative shape guards. Studio Apply supports known safe shapes and blocks unknown JSON shapes rather than flattening crafted operator sidebars.

### 2.3 Runtime Layers Consume Resolved Experience With Compatibility Defaults

The Operator, Admin, and Display layers now route their current visibility decisions through `ResolvedExperienceConsumerService` where applicable:

- Operator view gates use resolved operator view tokens, with `dashboard` and `account` preserved as always-addressable compatibility defaults.
- Display panels use resolved display panel tokens.
- Admin dashboard blocks and plugin cards use resolved admin tokens plus legacy authority/app normalization.
- Per-user override artifacts in `user_surface_overrides` are preferred over inline compatibility CSV fields.

The Operator Layer still uses Shell-owned routes, adapters, and templates for rendering:

- routes are declared in `apps/Shell/routes.php`
- sidebar defaults are built by `OperatorLayerSidebarService`
- main content is selected by focus flags in `OperatorSurfaceComposer`
- individual pages are PHP templates under Shell and app-owned operator view folders

This means the current renderer is not a pure generic catalog renderer, but its access/visibility decisions are aligned with the resolved experience bridge.

### 2.4 Studio Is The Governed Experience Composer

Studio exists as an optional System App and owns governed Analyze / Changes / Apply tooling for:

- Workspace Profile experience proposals.
- Per-user override artifact proposals.
- Route -> view linking.
- Navigation -> view linking.

Studio re-runs analysis server-side before Apply, requires matching plan fingerprints, blocks ACL-denied/unknown capabilities, blocks permission grants, and keeps runtime capability ownership with the owner app/module.

---

## 3. Canonical Roles

### 3.1 Apps And Modules

Apps and modules own capabilities and runtime meaning.

Examples:

- Manufacturing owns production, machines, materials, QC, dispatch, and their operator/display/admin capability catalogs.
- Platform owns governance/admin capability catalogs.
- SBAIO owns staff, timecard, attendance, and payroll capability catalogs.

Apps/modules own:

- capability IDs
- routes/views/widgets/panels
- data adapter contracts
- view/component CSS and runtime styling assets for owned views
- permissions required by each capability
- interaction profile compatibility
- owner lifecycle
- runtime rendering assets or render adapters

Studio may help create or modify these artifacts, including CSS, but the artifact is handed back to the owner.

CSS follows the same ownership rule as views:

- App/module view CSS belongs to the parent app/module that owns the view.
- Shell owns wrapper chrome, shared layout primitives, global theme tokens, and cross-app utilities only.
- Studio is tooling. Studio may generate, edit, preview, diff, and apply CSS for an owner, but Studio must not become the runtime owner of that CSS.
- Platform/Base may bridge or diagnose CSS ownership during migration, but must not absorb app/module page-specific styling.
- Global theme tokens are shared law; owner CSS should consume them instead of creating isolated color/font/spacing systems.

### 3.2 ACL

ACL owns hard authorization.

ACL answers:

- Who is this user?
- Which apps/modules are legally accessible?
- Which actions are allowed?
- Which data scopes apply?
- Which view/table/chart/data tokens are authorized?
- Which permissions and role packs apply?

ACL must be enforceable server-side. A layout decision must never grant authorization.

### 3.3 Workspace Profile

Workspace Profile is ACL-governed experience policy.

Workspace Profile answers:

- Which authorized capabilities are part of this role/persona experience?
- What default landing route should this role receive?
- What sidebar/nav grouping should this role receive?
- Which widgets/panels/quick actions are prominent?
- What presentation density, interaction profile, and dashboard composition apply?

Workspace Profile must never expose a capability that ACL denies.

### 3.4 Access Control Experience Override

Access Control may assign or pin profiles, and it may apply per-user presentation overrides.

The per-user override answers:

- Which profile-filtered items should this specific user see?
- What order/grouping/pinning should this user receive?
- Which items are hidden for this user?

It must not create new capabilities.
It must not override ACL denial.
It must not become a separate hidden profile system.

### 3.5 Studio

Studio owns the governed composition workflow and diagnostics.

Studio may:

- inspect owner catalogs
- create or modify owner artifacts
- create or modify owner CSS/styling artifacts through governed workflows
- edit workspace profile definitions
- edit per-user override artifacts
- preview a resolved experience
- analyze differences
- apply changes through safe gates
- keep provenance, history, and diagnostics

Studio must not:

- own Manufacturing/SBAIO/Platform runtime business capabilities
- own runtime CSS for app/module views after handover
- grant permissions
- make Core depend on Studio
- make business apps depend on Studio at runtime
- move Studio logic into `/ops` or unrelated Platform surfaces

### 3.6 Runtime Layers

Runtime layers render the resolved result.

- Admin layer renders admin/governance experiences.
- Operator layer renders operator/user workspaces.
- Display layer renders readonly kiosk/display experiences.

Renderers should continue converging on resolved experience objects rather than independently inventing layout shape. Current Shell services already consume the resolved experience bridge for operator/admin/display visibility while preserving compatibility defaults.

---

## 4. Architectural Map

```text
Owner Apps / Modules
  - own capability catalog, routes, widgets, panels, data contracts
        |
        v
ACL Authorization
  - identity, roles, assigned apps, permissions, scopes
  - hard server-side access law
        |
        v
Workspace Profile
  - role/persona experience policy
  - landing, nav, widgets, quick actions, interaction profile
        |
        v
User Experience Override
  - per-user hide/order/pin/arrange within allowed/profiled set
        |
        v
Runtime Renderers
  - /admin/*, /u/*, /displays/*

Studio sits beside the pipeline:
  - editor/analyzer/diff/apply/diagnostics/provenance
  - hired by owners, hands artifacts back to owners
  - may edit profiles/overrides through governed workflows
```

---

## 5. Proposed Resolved Experience Contract

The system needs a single resolved shape that renderers can consume.

Working name:

```text
ResolvedExperience
```

Suggested structure:

```json
{
  "version": "resolved_experience.v1",
  "surface": "operator",
  "target_user_id": 1,
  "authority_role": "app_user",
  "default_app": "manufacturing",
  "workspace_profile": {
    "profile_key": "manufacturing_operator",
    "source": "workspace_profiles"
  },
  "catalog": {
    "owner_capabilities_count": 24,
    "authorized_count": 20,
    "profiled_count": 12,
    "override_count": 10
  },
  "nav_sections": [],
  "quick_actions": [],
  "views": [],
  "widgets": [],
  "panels": [],
  "display_surfaces": [],
  "diagnostics": []
}
```

Every item should carry diagnostics:

```json
{
  "key": "manufacturing.production.board",
  "owner_type": "app",
  "owner_key": "manufacturing",
  "route": "/u/{user}/production",
  "allowed_by_acl": true,
  "included_by_workspace_profile": true,
  "included_by_user_override": true,
  "hidden_reason": null,
  "required_permissions": ["ops.my_work.view"],
  "required_modules": ["production"]
}
```

This makes support questions answerable:

- Why can this user see this?
- Why can this user not see this?
- Is it denied by ACL?
- Is it hidden by workspace profile?
- Is it hidden by per-user override?
- Does owner catalog expose it?

---

## 6. Conflict Rules

### 6.1 ACL Denies, Profile Includes

Result: hidden/blocked.

Reason:

```text
acl_denied
```

Workspace Profile cannot override ACL denial.

### 6.2 ACL Allows, Profile Hides

Result: not visible by default, route may remain technically authorized depending on surface policy.

Reason:

```text
profile_hidden
```

For operator/display surfaces, route availability should eventually follow the resolved experience unless an owner marks a route as always-addressable.

### 6.3 Profile Includes, User Override Hides

Result: hidden for the user.

Reason:

```text
user_override_hidden
```

### 6.4 User Override Includes, Profile Hides

Default result: blocked unless override is explicitly allowed to re-include profile-hidden items.

Recommended initial policy:

```text
blocked_by_profile
```

This prevents per-user overrides from becoming shadow profiles.

### 6.5 Owner Catalog Removes Item

Result: hidden and diagnostic warning.

Reason:

```text
owner_capability_missing
```

Studio diagnostics should help repair stale profile/override references.

---

## 7. Current Transitional Fields

These fields exist today and should be classified before migration:

| Field | Current Role | Target Role |
|---|---|---|
| `me_dashboard_blocks` | Admin `/me` layout | Transitional per-user admin override |
| `me_plugin_cards` | Admin `/me` cards | Transitional per-user admin override |
| `display_surfaces` | Display panel selection | Transitional display override |
| `operator_views` | Operator route gate | Transitional operator override |
| `workspace_profile_key` | Pinned profile | Keep as profile assignment/pin |
| `module_visibility` / `user_module_visibility` | Access and UI filter | Move toward ACL/module authorization; profile handles experience shaping |
| `workspace_profiles.nav_sections` | Role nav override | Keep, but resolve against owner catalog and ACL |
| `workspace_profiles.quick_actions` | Role quick actions | Keep, but resolve against owner catalog and ACL |
| `workspace_profiles.dashboard_blocks` | Stored but underused | Promote through resolved experience |

---

## 8. Migration Plan

### Phase 0: Policy Lock

- Document the separation of ACL, Workspace Profile, Studio, owner catalogs, and runtime renderers.
- Mark ACL experience fields as transitional.
- Prohibit adding new owner/business experience catalogs directly inside ACL.
- Preserve current runtime behavior during this phase.

### Phase 1: Inventory Owner Capabilities

- Create an owner capability catalog contract.
- Inventory existing operator/admin/display surfaces.
- Identify capability owner for each surface.
- Record required permissions/modules/routes/adapters.
- Include current hardcoded operator pages in the inventory.

### Phase 2: Build ResolvedExperience Service

- Add a service that resolves:
  - owner catalog
  - ACL authorization
  - workspace profile shaping
  - user override
  - diagnostics
- Initially run in read-only diagnostics mode.
- Do not change renderers yet.

### Phase 3: Diagnostics UI

- Add an admin diagnostics surface showing:
  - owner capability
  - ACL status
  - workspace profile status
  - user override status
  - final visible/hidden decision
- Link from Access Control detail and Workspace Profile detail.

### Phase 4: Move Experience Editor Out Of ACL Detail

- Rename current ACL Experience tab to a bridge/summary.
- Add a link to a Studio-powered Experience Composer.
- Keep ACL as the guardrail and assignment surface.
- Composer edits profile/user override artifacts, not permissions.

### Phase 5: Runtime Consumption

- Update Operator, Admin, and Display renderers to consume `ResolvedExperience`.
- Keep legacy fallbacks until parity is proven.
- Add route/nav/content mismatch tests.

### Phase 6: Retire Transitional Fields

- Migrate:
  - `me_dashboard_blocks`
  - `me_plugin_cards`
  - `display_surfaces`
  - `operator_views`
- Replace with canonical profile/user override artifacts.
- Keep compatibility reads until existing installations are migrated.

---

## 9. Open Questions

1. Should `Workspace Profile` be assigned only per user, or also by groups/teams?
2. Should a per-user override be allowed to re-include a profile-hidden but ACL-allowed item?
3. Which owner catalogs are code-owned, DB-owned, manifest-owned, or Studio-generated?
4. Should display surfaces be modeled as part of the same resolved experience contract or a specialized display contract?
5. Should route access for ACL-allowed but profile-hidden items be denied or merely hidden from navigation?
6. How should localization keys be attached to owner capability catalog items?
7. What is the first runtime layer to migrate: Display, Operator, or Admin?

---

## 10. Recommended Immediate Next Step

Do not add more independent experience-shaping fields to ACL.

Next implementation should be read-only:

1. Build an owner capability inventory for `/u/*` and `/displays/*`.
2. Build a read-only `ResolvedExperience` diagnostic service.
3. Compare the diagnostic result against current runtime output.
4. Only after the diagnostic result matches the intended law, move editing into Studio.
