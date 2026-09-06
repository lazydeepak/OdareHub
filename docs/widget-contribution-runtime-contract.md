# Surface Contribution Runtime Contract

This document defines the runtime contract for app/module-owned surface contributions rendered by host-surface compositions.

Current experience composition policy is defined in [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md). Contributions are owner catalog inputs that should eventually be filtered through ACL, Workspace Profile, and per-user override into `ResolvedExperience`.

Terminology note:
- `Surface Contribution` is the preferred parent term.
- `widget_type` remains part of the contract as the operational-purpose field.
- This file name is retained for continuity with existing repo references.

## Scope

Applies to:
- host-surface contribution providers
- module-owned widget registries
- app-level aggregator services
- shell-rendered composed workspaces

Does not authorize duplicating business workflows inside shell views.

## Core Model

Keep these concepts separate:

- `surface contribution`: the module/app-owned object contributed into a host surface
- `view_kind`: the render shape of that contribution
- `widget_type`: what kind of operational component it is
- `placement_zone`: where it appears in the composed surface
- `interaction_profiles`: which user experiences may see it

Do not use route names or role labels as a substitute for this model.

Keep this layer separate from the page/surface architecture in `docs/access-control-view-architecture.md`:

- `View Suite` remains the page-level render pattern for a full Surface
- `view_kind` is the contribution-level render shape inside host surfaces such as `/admin/*`, `/u/*`, `/displays/*`, and legacy `/me`

## Required Fields

Each contribution item should define:

- `surface_key`
- `app_key`
- `view_kind`
- `widget_key`
- `widget_type`
- `placement_zone`
- `interaction_profiles`
- `title` or `label`
- `priority` (or `weight`)
- `supports_empty_state`
- `supports_clickthrough`

Recommended when the contribution needs stable identity or stronger capability semantics:

- `access_authorities`
- `permission_profile`

Backward compatibility:

- existing contributions without explicit `view_kind` are normalized by runtime defaults
- existing `kind` values used by monitoring sections continue to work and are mapped into `view_kind`

## Allowed View Kinds

- `kpi`
- `table`
- `form`
- `chart`
- `cards`
- `queue`
- `timeline`
- `mixed`

## Allowed Widget Types

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

## Allowed Placement Zones

- `primary_work`
- `operator_actions`
- `monitoring`
- `supporting_visibility`
- `dashboard_summary`
- `display_wall`

## Runtime Behavior

- Contributions are filtered by route access and `interaction_profiles`.
- `view_kind` describes render shape; it does not replace `widget_type`.
- `placement_zone` drives where the widget is rendered.
- Widget rendering style may vary by `widget_type`.
- Unknown or missing taxonomy fields are normalized by runtime defaults.

## Contract Shape

A contribution object may represent a KPI block, table, form launcher, queue, chart, or mixed section.

Examples:

- `view_kind = kpi`, `widget_type = informative`
- `view_kind = table`, `widget_type = queue`
- `view_kind = form`, `widget_type = action`
- `view_kind = chart`, `widget_type = progress`

The shell should place and render contributions by contract. It should not own business workflow logic.

## Ownership Pattern

- Module-owned surface contributions should be defined in module registries.
- App-level host-surface services should aggregate module registries.
- Shell views render composition outputs; they do not own business workflow logic.

## Validation Checklist

For each surface contribution change:

- verify `view_kind`, `widget_type`, `placement_zone`, and `interaction_profiles`
- verify taxonomy fields are present and valid
- verify expected visibility across worker/leader/read_only/display
- verify route access filtering is still enforced
- verify empty-state and clickthrough behavior
- verify no duplicate widget ownership across module/app layers
