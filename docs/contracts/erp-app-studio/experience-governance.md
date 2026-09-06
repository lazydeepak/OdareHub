# Studio Experience Governance Contract

Studio owns the governed Analyze -> Changes -> Apply workflow for experience composition. This contract covers Workspace Profile and per-user override edits only; it does not grant permissions and it does not create runtime capabilities.

Canonical endpoints:

- `POST /apps/studio/experience-governance/analyze`
- `POST /apps/studio/experience-governance/apply`

Both endpoints require an authenticated `platform_admin` user and CSRF validation.

## Resolution Law

Every proposal is evaluated against a caller-provided `ResolvedExperience` snapshot:

```text
Owner capability catalog
  -> ACL authorization
  -> Workspace Profile shaping
  -> User override
  -> Studio analysis/apply gate
```

Studio blocks proposals that reference capabilities missing from the owner catalog, capabilities denied by ACL, direct permission grants, unsupported persistence fields, or unsupported JSON shapes.

## Analyze Payload

`resolved_experience` and `proposal` may be posted as arrays or JSON strings.

```json
{
  "resolved_experience": {
    "version": "resolved_experience.diagnostic.v1",
    "surface": "operator",
    "target_user_id": 7,
    "workspace_profile": {
      "profile_key": "manufacturing_operator"
    },
    "items": []
  },
  "proposal": {
    "artifact_type": "workspace_profile",
    "surface": "operator",
    "kind": "view",
    "items": [
      {
        "token": "production",
        "label": "Production",
        "route": "/u/{user}/production",
        "section": "Manufacturing",
        "icon": "factory"
      }
    ]
  }
}
```

The response contains:

- `analysis`: catalog/ACL/profile/user-override decisions
- `apply_plan`: deterministic planned writes
- `provenance.fingerprint`: confirmation fingerprint for Apply

## Apply Payload

Apply re-runs Analyze and rebuilds the apply plan server-side from `resolved_experience` and `proposal`. The client must submit the fingerprint produced by the matching Analyze response.

```json
{
  "resolved_experience": {},
  "proposal": {},
  "apply_fingerprint": "abc123"
}
```

If the fingerprint does not match the server-derived plan, Apply is blocked.

## Supported Targets

Workspace Profile:

| Proposal surface/kind | Field | Strategy |
|---|---|---|
| `operator:view` | `workspace_profiles.nav_sections` | Section object with `items` list |
| `operator:quick_action` | `workspace_profiles.quick_actions` | List of quick action objects |
| `admin:quick_action` | `workspace_profiles.quick_actions` | List of quick action objects |
| `operator:module` | `workspace_profiles.module_visibility` | JSON token list |
| `admin:module` | `workspace_profiles.module_visibility` | JSON token list |
| `display:module` | `workspace_profiles.module_visibility` | JSON token list |
| `admin:block` | `workspace_profiles.dashboard_blocks` | JSON token list |
| `display:panel` | `workspace_profiles.dashboard_blocks` | JSON token list |

Per-user override:

| Proposal surface/kind | Field | Strategy |
|---|---|---|
| `operator:view` | `user_surface_overrides.operator_views` | CSV token set |
| `display:panel` | `user_surface_overrides.display_surfaces` | CSV token set |
| `admin:block` | `user_surface_overrides.me_dashboard_blocks` | CSV token set |
| `admin:card` | `user_surface_overrides.me_plugin_cards` | CSV token set |

## Blocked Shapes

Studio intentionally blocks these cases until a shape-aware strategy exists:

- `nav_sections` using module-only objects such as `{ "modules": [...] }`
- `nav_sections` entries without an `items` list
- quick action entries that are not objects
- quick action include proposals without `label` and `url`
- invalid JSON or associative maps where a list is required
- system-locked Workspace Profiles

These blocks preserve crafted operator sidebars and prevent Studio from flattening or rewriting business-owned runtime meaning.

## Required Proposal Payloads

Operator nav includes require:

- `token`
- `label`
- `route`
- `section`

Quick action includes require:

- `token`
- `label`
- `url`

Remove/hide operations may omit display payloads because they remove by token.

## Non-Goals

- No permission grants
- No Core dependency on Studio
- No business app runtime dependency on Studio
- No new ACL experience-shaping fields
- No direct mutation from Access Control Experience Layout
