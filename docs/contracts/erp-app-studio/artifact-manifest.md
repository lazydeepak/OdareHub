# ERP App Studio Artifact Manifest Contract

Schema: `studio.artifact-manifest.v1`

Status: dry-run compile contract only

Defines planned artifacts returned by the non-writing compile plan.

Normalized artifact types:

- `app`
- `module`
- `route`
- `controller`
- `service`
- `view`
- `navigation`
- `permission`
- `translation`
- `dashboard_surface`
- `workflow_surface`
- `migration_file`
- `package_manifest`

Required fields per planned artifact:

| Field | Meaning |
|---|---|
| `artifact_id` | Deterministic Studio artifact identifier. |
| `artifact_type` | One of the normalized artifact types above. |
| `owning_app` | Declared owning app key. |
| `owning_module` | Declared owning module key, or empty for app-layer artifacts. |
| `ownership_scope` | `managed`, `unmanaged`, `external`, or `unknown`. |
| `upgrade_safe` | Boolean indicating whether future upgrades can proceed without ownership/drift review. |
| `customization_zone` | `protected`, `generated`, `user_editable`, or `none`. |
| `target_path` | Intended future path. |
| `source_template` | Template key that would drive generation. |
| `template_version` | Template contract version. |
| `change_type` | `create`, `update`, `noop`, or `conflict`. |
| `risk_level` | `low`, `medium`, `high`, or `blocked`. |
| `dependencies` | Artifact IDs this artifact depends on. |
| `generated_by` | Generator identifier. |
| `studio_project_id` | Studio project/draft identifier. |
| `content_hash` | Placeholder hash only; no content is generated in this phase. |
| `before_hash` | Placeholder future diff input. |
| `after_hash` | Placeholder future diff input. |
| `target_exists` | Boolean indicating whether the target path currently exists. |
| `drift_status` | `clean`, `modified`, `unknown`, `external`, `conflict`, or `unmanaged`. |
| `diff_status` | `pending`, `unavailable`, or `conflict-ready`. |
| `diff_summary` | Short metadata-only diff readiness summary. |
| `human_diff_summary` | Human-readable summary for future review screens. |
| `machine_diff_summary` | Structured future diff input metadata. |
| `human_summary` | Human-readable plan line. |
| `machine_summary` | Structured summary for future diff/approval automation. |

Compile snapshot identity fields:

| Field | Meaning |
|---|---|
| `compile_id` | Deterministic identity derived from the dry-run bundle hash. |
| `bundle_hash` | Deterministic placeholder hash of the planned bundle. |
| `artifact_count` | Count of planned artifacts. |
| `dependency_check_count` | Count of dependency checks. |
| `conflict_check_count` | Count of conflict checks. |
| `drift_check_count` | Count of drift checks. |
| `generated_at` | Deterministic placeholder timestamp strategy; snapshots are not persisted yet. |

Compile output groups artifacts as:

- `app_layer`
- `module_layer`
- `view_layer`
- `navigation_layer`
- `permission_layer`
- `package_layer`

Dependency checks are non-executing and cover:

- Module requires app
- View requires module
- Navigation requires route/view target
- Permission requires surface/view/action target
- Package manifest requires app/module metadata

Conflict checks are report-only and cover:

- Target path already exists
- Target owned by another app/module
- Missing dependency
- Unsafe target path
- Unsupported artifact type
- Ownership scope conflicts
- Unmanaged targets
- External artifacts
- Modified generated artifacts

Ownership scopes:

- `managed`: the planned target belongs to the declared app/module scope and can be governed by Studio metadata.
- `unmanaged`: the target exists in a user-editable zone without Studio ownership metadata.
- `external`: the target is outside the declared ownership boundary.
- `unknown`: the target cannot be classified safely, usually because the path or artifact type is blocked.

Customization zones:

- `protected`: future changes require explicit governance because the artifact controls execution, security, migration, or package lifecycle.
- `generated`: Studio-generated output that should remain reproducible from templates.
- `user_editable`: planned output where future manual customization is expected and must be preserved.
- `none`: no customization zone can be assigned.

Drift statuses:

- `clean`: no existing target or ownership drift was detected.
- `modified`: a generated/protected target already exists and requires human diff review.
- `unknown`: Studio cannot classify the current target state yet.
- `external`: the target belongs outside the declared app/module ownership.
- `conflict`: path or artifact type is blocked.
- `unmanaged`: an existing user-editable target has no Studio ownership metadata.

Diff readiness is metadata-only and includes:

- `before_hash` placeholder
- `after_hash` placeholder
- `target_exists`
- `drift_status`
- `diff_status`
- `diff_summary`
- `human_diff_summary`
- `machine_diff_summary`

Grouped human summaries include:

- Added artifacts
- Changed artifacts
- No-op artifacts
- Conflicts
- Blocked items
- Ownership warnings
- Drift warnings

The compile contract must not write files, execute migrations, mutate DB rows, publish packages, or edit arbitrary code.
