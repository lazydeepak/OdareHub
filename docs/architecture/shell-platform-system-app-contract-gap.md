# Shell and Platform System App Contract Gap

Status: architecture checkpoint and system-app contract reference.

This note updates the older migration inventory statement that `apps/Shell` and `apps/Platform` had no runtime app contracts. Both directories now contain app manifests, local ownership guidance, and explicit system-app ownership metadata.

## Current State

### Shell

Current files:

- `apps/Shell/manifest.json`
- `apps/Shell/AGENTS.md`
- `apps/Shell/routes.php`
- `apps/Shell/navigation.php`
- `apps/Shell/layout_contract.php`
- Shell-owned services, composers, views, sidebar sources, and runtime CSS.

Current ownership:

- Shell is the generic runtime house/wrapper.
- Shell owns runtime frame composition, admin/operator/display wrappers, shared layout primitives, route-surface orchestration, contribution normalization, and generic Shell CSS.
- Shell composes app/module contributions after they are resolved or normalized.
- Shell must not own business capability meaning, ACL policy, Workspace Profile policy, Studio edit truth, or app/module page-specific CSS.

Current manifest contract:

- `apps/Shell/manifest.json` declares `system_app_contract` and `ownership_contract`.
- Shell is typed as `framework`; the `system_app_contract.owner_layer` field documents its Charter v1 "Shell System App" ownership.
- Lifecycle flags such as `can_disable`, `can_uninstall`, and `can_export` remain unchanged runtime compatibility flags. The manifest now documents restricted lifecycle policy metadata; it does not enforce lifecycle behavior.
- `/me` compatibility appears in Shell route metadata, but `/me` must remain legacy compatibility rather than a new primary runtime truth.

### Platform

Current files:

- `apps/Platform/manifest.json`
- `apps/Platform/AGENTS.md`
- `apps/Platform/routes.php`
- `apps/Platform/navigation.php`
- `apps/Platform/Contracts/*.php`
- Platform-owned services, governance views, modules, migrations, and CSS.

Current ownership:

- Platform is a system app for governance and shared infrastructure.
- Platform owns organization/settings, access-control and user-control operations, platform governance dashboards/tools, diagnostic entry points, and governed assignment/profile operations.
- Platform may assign roles, permissions, profiles, and diagnostic entry points.
- Platform must not own app/module runtime experience catalogs, app business meaning, Shell chrome, or Studio editor/builder truth.

Current manifest contract:

- `apps/Platform/manifest.json` has `type=system` and declares `system_app_contract` and `ownership_contract`.
- Platform declares `/ops/*` governance surfaces as canonical current routes. That is current runtime truth, not permission to redesign routes or migrate route families.
- Lifecycle flags such as `can_disable`, `can_uninstall`, and `can_export` remain unchanged runtime compatibility flags. The manifest now documents restricted lifecycle policy metadata; it does not enforce lifecycle behavior.
- Some governance extraction debt remains documented in `docs/architecture/migration-inventory-2026-04-18.md`, especially older Base and `/ops` compatibility surfaces.

## Target Contract Shape

Future manifest work should be additive and should not change runtime behavior by itself. A future system-app contract may declare:

- `system_app_contract.version`
- `system_app_contract.owner_layer`
- `system_app_contract.runtime_role`
- `system_app_contract.owns`
- `system_app_contract.must_not_own`
- `system_app_contract.route_policy`
- `system_app_contract.lifecycle_policy`
- `system_app_contract.compatibility_surfaces`
- `system_app_contract.source_of_truth_policy`

Until such fields exist, agents must treat the local `AGENTS.md` files, Architecture Charter v1, and this checkpoint as the ownership contract.

## Minimal Contract Proposal

The first manifest contract slice is additive metadata only. It does not change app loading, route registration, lifecycle behavior, export behavior, disable behavior, uninstall behavior, permissions, migrations, or runtime rendering.

Use two top-level fields:

- `system_app_contract`: what kind of system app this is and how lifecycle/runtime policy should be interpreted.
- `ownership_contract`: what the app owns and must not own.

Recommended minimal shape:

```json
{
  "system_app_contract": {
    "version": "system-app-contract.v1",
    "owner_layer": "Shell|Platform",
    "runtime_role": "generic_runtime_shell|platform_governance",
    "lifecycle_policy": {
      "disable": "restricted_contract_only",
      "uninstall": "restricted_contract_only",
      "export": "metadata_and_assets_only",
      "notes": "Current can_disable/can_uninstall/can_export flags are runtime compatibility flags until lifecycle enforcement is updated."
    },
    "route_policy": {
      "canonical_prefixes": [],
      "compatibility_surfaces": [],
      "must_not_reinterpret_routes": true
    },
    "source_of_truth_policy": "compose_resolved_contracts_only"
  },
  "ownership_contract": {
    "owns": [],
    "must_not_own": [],
    "contributes_to": [],
    "depends_on": []
  }
}
```

### Shell Minimal Values

Shell should declare:

- `owner_layer`: `Shell`
- `runtime_role`: `generic_runtime_shell`
- `owns`:
  - runtime frame and wrapper composition
  - admin/operator/display wrapper chrome
  - shared layout primitives
  - generic Shell CSS and theme-token usage
  - contribution normalization before rendering
  - rendering of already-resolved admin/operator/display experience data
- `must_not_own`:
  - Core primitives
  - business logic or business workflow meaning
  - app/module capability catalogs
  - ACL authorization policy
  - Workspace Profile policy
  - Studio drafts, previews, or runtime truth
  - app/module page-specific CSS
  - new primary links to compatibility aliases
- `route_policy.canonical_prefixes`:
  - `/u/{username}`
  - `/admin/{username}`
  - `/displays`
- `route_policy.compatibility_surfaces`:
  - `/me`
  - `/me2`
- `source_of_truth_policy`: Shell composes resolved/compiled contracts and normalized contributions; it must not invent independent runtime truth.

Shell lifecycle restrictions:

- Shell should be treated as required runtime infrastructure in production instances.
- Disable/uninstall/export flags in the current manifest are compatibility metadata only until a governed lifecycle policy enforces system-app restrictions.
- Export may include Shell metadata/assets for diagnostics or package portability, but must not imply Shell can be safely removed from a running instance.

### Platform Minimal Values

Platform should declare:

- `owner_layer`: `Platform`
- `runtime_role`: `platform_governance`
- `owns`:
  - platform governance surfaces
  - access-control and user-control operations
  - role/profile/assignment administration surfaces
  - diagnostics and route/app/system inspection surfaces
  - organization/platform settings
  - package/app lifecycle administration surfaces
  - System Tools entry points and diagnostics
  - conditional Studio entry links/diagnostics when Studio is installed/enabled
- `must_not_own`:
  - Core primitives
  - Shell runtime chrome or wrapper composition
  - app/module business workflows
  - app/module runtime capability catalogs
  - Studio editor/builder implementation or draft runtime truth
  - direct permission bypasses
  - uncontrolled admin shortcuts outside Core/auth/audit governance
- `route_policy.canonical_prefixes`:
  - `/apps/platform`
  - `/ops/*` current governance surfaces
  - `/admin/*` current governance/admin surfaces owned by Platform
- `route_policy.compatibility_surfaces`:
  - `/ops/dashboard`
  - legacy Base/ops bridges documented in migration inventory
- `source_of_truth_policy`: Platform governs and diagnoses; it must not become runtime owner of app/module business meaning or experience layout truth.

Platform lifecycle restrictions:

- Platform should be treated as required governance infrastructure in production instances.
- Disable/uninstall/export flags in the current manifest are compatibility metadata only until a governed lifecycle policy enforces system-app restrictions.
- Export may include Platform metadata/assets for diagnostics or package portability, but must not imply Platform governance can be bypassed or removed from a running instance.

## What This Contract Does Not Do

- It does not change app loading.
- It does not change lifecycle behavior.
- It does not reinterpret `/u`, `/admin`, `/displays`, `/me`, `/ops`, or `/apps/platform` routes.
- It does not migrate Platform routes out of compatibility locations.
- It does not grant Shell or Platform new runtime ownership.

## Read-Only Diagnostic

Use this diagnostic to inspect current Shell/Platform system-app contract shape without mutating runtime files:

```bash
scripts/architecture/check_system_app_contracts.sh
```

The diagnostic reports:

- whether each system app has a manifest, local `AGENTS.md`, routes, and navigation contract file
- key manifest fields
- route counts
- whether explicit `system_app_contract` or `ownership_contract` fields exist
- lifecycle policy warnings for system apps
- whether future minimal contract fields are present once they are added

Warnings are not runtime failures. They identify documentation/contract gaps for future narrow architecture work.

## Architecture Rules Protected

- Core is locked.
- Shell is generic.
- Apps contribute; Shell composes.
- ACL authorizes only.
- Runtime consumes resolved/compiled contracts.
- Studio edits but does not own runtime truth.
- No hidden duplicate source of truth.

## What Not To Do

- Do not migrate routes as part of this documentation gap.
- Do not redesign app loading.
- Do not remove compatibility aliases.
- Do not change Shell or Platform runtime behavior merely because the diagnostic reports a contract warning.
- Do not polish ERP, Manufacturing, SBAIO, Payroll, or other reference apps while addressing this gap.
