# ResolvedExperience Contract

## Status
- Phase 2 design artifact for [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md)
- Pairs with [owner-capability-catalog-contract.md](owner-capability-catalog-contract.md)
- Drafted 2026-05-19
- **Read-only**: this contract documents the shape currently produced by `Plugins\Base\Services\ResolvedExperienceDiagnosticsService::resolve()`. It does not introduce new behavior.

## Purpose

`ResolvedExperience` is the **output** of the resolution law:

```text
Owner Capability Catalog
  -> ACL authorization filter
  -> Workspace Profile shaping
  -> User-specific Experience Override
  -> ResolvedExperience    <- this contract
  -> Runtime renderer
```

Phase 2's promise is read-only: the service computes `ResolvedExperience` and exposes it to diagnostics UI, but **runtime renderers continue to use the legacy paths**. Phase 5 will replace renderer inputs with this envelope.

## Envelope Shape (`resolved_experience.diagnostic.v1`)

```text
{
  version: "resolved_experience.diagnostic.v1",
  mode: "read_only" | "profile_only",
  surface: "admin" | "operator" | "display",
  target_user_id: integer,                # 0 when mode = "profile_only"
  authority_role: string,
  dashboard_type: string,
  workspace_profile: {
    profile_key: string,
    name: string,
    is_pinned: boolean
  },
  summary: {
    visible_count: integer,
    hidden_count: integer,
    owner_capabilities_count: integer,
    acl_denied_count: integer,
    profile_hidden_count: integer,
    user_override_hidden_count: integer,
    stale_reference_count: integer,
    runtime_parity_missing_count: integer,
    runtime_parity_extra_count: integer
  },
  stale_references: [
    {
      source: "workspace_profile" | "user_override",
      field: string,                       # e.g. "operator_views", "nav_sections"
      token: string,                       # the offending raw value
      normalized_token: string,
      reason: string                       # e.g. "not_in_owner_catalog"
    }
  ],
  runtime_parity: {
    runtime_expected_tokens: [string],
    diagnostics_visible_tokens: [string],
    missing_in_diagnostics: [string],
    extra_in_diagnostics: [string]
  },
  items: [
    {
      key: string,                         # globally unique, dotted
      token: string,                       # surface-local token
      label: string,
      surface: string,
      kind: string,
      owner_type: "app" | "module" | "platform",
      owner_key: string,
      route: string,
      override_field: string,
      allowed_by_acl: boolean,
      acl_reason: string,
      included_by_workspace_profile: boolean,
      profile_reason: string,
      included_by_user_override: boolean,
      override_reason: string,
      visible: boolean,
      hidden_reason: ""               # one of "", "acl_denied", "profile_hidden", "user_override_hidden"
    }
  ]
}
```

## Modes

| Mode             | Input                              | `target_user_id` | User-override fields applied | Use case                                                        |
|------------------|------------------------------------|------------------|------------------------------|-----------------------------------------------------------------|
| `read_only`      | `assignmentRow` (+ resolved profile)| user's id        | yes                          | Access Control detail diagnostics — full per-user resolution    |
| `profile_only`   | `profile` only                     | 0                | no                           | Workspace Profile detail diagnostics — what the profile alone produces |

Both modes share the same envelope shape; only `mode`, `target_user_id`, and the override-related counts differ.

## Decision Order (per item)

For each capability `C` in the catalog the resolver produces:

1. **ACL check** — sets `allowed_by_acl` and `acl_reason`. Inputs: `authority_role`, `assigned_apps`, `module_visibility`, `C.required_apps`, `C.required_modules`, `C.required_permissions`.
2. **Workspace Profile check** — sets `included_by_workspace_profile` and `profile_reason`. Inputs: resolved profile's nav/quick-action/module-visibility maps.
3. **User Override check** — sets `included_by_user_override` and `override_reason`. Inputs: `assignmentRow[C.override_field]` (skipped in `profile_only` mode).
4. **Final visibility**:
   - `visible = allowed_by_acl AND included_by_workspace_profile AND included_by_user_override`
   - `hidden_reason = ""` when visible, else the first stage that excluded the item, in the order ACL → profile → override.

## Stale Reference Detection

A token that appears in a Workspace Profile field (`operator_views`, `nav_sections`, etc.) or a user-override field but does **not** match any capability in the owner catalog is reported in `stale_references`. This is how renames or removals at the owner layer surface as actionable cleanup work without breaking runtime.

## Runtime Parity Block

`runtime_parity` compares the set of tokens **diagnostics says will be visible** with the set of tokens **current runtime assignment fields imply will render**. Mismatches signal that diagnostics and the legacy renderer have drifted; useful as a regression guard during Phase 5 rewrites.

## Stability Guarantees

- Field additions are **additive** — consumers must ignore unknown keys.
- Renaming or removing a field is a breaking change requiring a new version token (`resolved_experience.diagnostic.v2`).
- Enum values may be extended (new `hidden_reason`, new `kind`); consumers must treat unknown values as opaque.

## Consumers (Current and Planned)

| Consumer | Today | Phase |
|---|---|---|
| Access Control detail diagnostics partial | live | Phase 3 ✅ |
| Workspace Profile detail diagnostics partial | live | Phase 3 ✅ |
| Test suite (`tests/UserDashboardAssignmentServiceTest.php`) | live | Phase 2 ✅ |
| Operator runtime composer | planned — read-only fallback first | Phase 5 |
| Display runtime composer | planned — read-only fallback first | Phase 5 |
| Admin/`/me` runtime composer | planned — read-only fallback first | Phase 5 |
| Studio Experience Composer | planned | Phase 4 |

## Non-Goals

- Mutating ACL, Workspace Profile, or user-override state. The contract is **read-only**.
- Side-effecting runtime rendering. Phase 2 explicitly preserves current behavior.
- Capability discovery — covered by the [owner capability catalog contract](owner-capability-catalog-contract.md).
- Authoring tools — covered by Studio.
