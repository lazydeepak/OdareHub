# Owner Capability Catalog Contract

## Status
- Phase 1 design artifact for [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md)
- Drafted 2026-05-19
- Read-only: this contract describes the **shape** of capability declarations. It does not move any code yet.

## Purpose

The Owner Capability Catalog is the **first stage** of the resolution law:

```text
Owner Capability Catalog        <- this contract
  -> ACL authorization filter
  -> Workspace Profile shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

Each app or module **declares** the capabilities it owns. ACL filters them by authorization, Workspace Profile shapes them by role, and runtime surfaces render the result. Without a stable catalog contract, the downstream layers have nothing to filter or shape.

## Capability Entry Shape

A capability entry is a single addressable piece of UX owned by an app or module. Each entry **must** carry the following fields:

| Field                  | Type   | Required | Meaning                                                                                       |
|------------------------|--------|----------|-----------------------------------------------------------------------------------------------|
| `key`                  | string | yes      | Globally unique identifier, dotted: `{surface}.{kind}.{token}` (e.g. `operator.view.coverage`) |
| `token`                | string | yes      | Surface-local short identifier used in override CSV/JSON fields (e.g. `coverage`)             |
| `label`                | string | yes      | Default human label; localization handled via `localization_key`                              |
| `surface`              | enum   | yes      | One of `admin`, `operator`, `display`                                                         |
| `kind`                 | enum   | yes      | One of `view`, `dashboard_block`, `quick_link_card`, `panel`, `widget`, `nav_item`            |
| `owner_type`           | enum   | yes      | One of `app`, `module`, `platform`                                                            |
| `owner_key`            | string | yes      | App key or module key the capability belongs to                                               |
| `route`                | string | optional | Canonical runtime route template; may contain `{user}`                                        |
| `localization_key`     | string | optional | i18n key resolving to the display label; falls back to `label`                                |
| `required_apps`        | array  | optional | App keys that must be in user's `assigned_apps` for ACL to allow                              |
| `required_modules`     | array  | optional | Module keys that must appear in `module_visibility` (when set) for ACL to allow               |
| `required_permissions` | array  | optional | Permission strings required at ACL layer                                                      |
| `override_field`       | string | optional | Name of the legacy ACL field carrying user overrides (`operator_views`, `display_surfaces`, `me_dashboard_blocks`, `me_plugin_cards`); transitional, will retire in Phase 6 |

Future fields (not in this draft): `data_contract`, `adapter_class`, `widget_discovery_hint`, `health_check_route`.

## Ownership Rules

- A capability **must** have exactly one owner (`owner_type` + `owner_key`).
- `owner_type=app` capabilities live in the app's manifest area; `owner_type=module` capabilities live in the module's manifest area; `owner_type=platform` is reserved for cross-cutting platform tooling.
- ACL **does not own capabilities**. ACL only authorizes them.
- Workspace Profile **does not own capabilities**. Workspace Profile selects from the union of allowed-by-ACL capabilities.
- Studio **does not own capabilities**. Studio composes overrides; the generated artifacts hand back to the app/module.

## Discovery (Phase 1 Direction)

In Phase 1 we **inventory** existing capabilities. The catalog is currently embedded inside `Plugins\Base\Services\ResolvedExperienceDiagnosticsService::operatorCatalog()`, `displayCatalog()`, and `adminCatalog()`. That is a temporary location.

Target direction (deferred to a later phase):
- Each app exposes a `capabilityCatalog(): array` static method or a `manifest.json` `capabilities[]` section.
- Modules expose the same shape under their module folder.
- A registry service aggregates declarations on boot.
- The diagnostics service consumes the registry instead of hard-coded arrays.

This contract is the schema both the inventory and the future registry must conform to.

## Validation Expectations

When the registry is built, each entry will be validated for:

1. `key` uniqueness across all owners.
2. `surface` and `kind` must be one of the enumerated values.
3. `owner_type` and `owner_key` must resolve to a real app or module in `core_apps` / `core_app_modules`.
4. `route` (if present) must match the routing standard for its surface (`/admin/*`, `/u/*`, `/displays/*`).
5. `required_apps` and `required_modules` referenced must exist.
6. `localization_key` (if present) must resolve in at least `en`; `ja` and `ne` are warned but not blocked at registry time.

## Versioning

- Contract version: `owner_capability.catalog.v1`
- Future field additions must be additive. Removing or renaming a required field is a breaking change requiring a new contract version.
- The diagnostics service envelope already carries `version` tokens (e.g. `resolved_experience.diagnostic.v1`). The registry will mirror this pattern.

## Relationship to Existing Data

- The current `meDashboardBlockCatalog()` and `mePluginCardCatalog()` arrays in `UserDashboardAssignmentService` are de-facto admin catalogs. Phase 1 inventory will map them into this shape verbatim before any restructuring.
- The hard-coded `OPERATOR_VIEWS` and `DISPLAY_PANELS` constants in `ResolvedExperienceDiagnosticsService` are de-facto operator/display catalogs. Same treatment.

## Out of Scope (For This Contract Draft)

- Capability dependency graphs (cross-capability ordering).
- Capability variants per workspace profile (handled by Workspace Profile shaping, not the catalog).
- Studio-generated capabilities (handled by Studio publishing into the owning app/module's catalog file).
- Runtime data contracts for adapters and widgets (separate `widget-contribution-runtime-contract.md`).


## Addendum: declarative operator focus_tokens (2026-08-24)

Owner apps that contribute operator focus views through the
`operator_surface` / `region=focus_views` hook MUST declare their capability
tokens additively via `focus_tokens` on that hook (see
`docs/app-manifest-spec.md`, "Operator focus_views hook contract"). The
declaration is the capability-identity source for the Base resolved-experience
compatibility bridge; the provider `view_map` remains the rendering map and MUST
stay in exact parity with the declaration (continuously enforced by
`scripts/architecture/check_operator_focus_declaration_parity.sh`). Hook keys
identify hooks and are never capability tokens.
