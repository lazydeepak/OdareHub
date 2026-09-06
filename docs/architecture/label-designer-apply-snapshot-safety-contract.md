# Label Designer Apply/Snapshot Safety Contract

Status: Architecture contract baseline with guarded Label Context, Label Template, and Label Rule create enabled. This document authorizes owner-scoped create-only flows with snapshot-before-write, boundary/schema/source/field validation, confirmation, diagnostics, and rollback metadata. It does not authorize edit/apply/update/delete writes, unrestricted DB browsing, SQL builders, QR runtime changes, Manufacturing runtime changes, print behavior changes, renderer/export/print implementation, migrations, or Core edits.

## 1. Purpose

Label Designer may later directly create or edit app/module/plugin-owned label context, template, and rule resources. That future direct-edit capability is allowed only after the apply path enforces:

```text
snapshot -> diff -> validation -> confirmation -> owner-folder write -> diagnostics -> rollback information
```

The same lifecycle may be described in UI copy as:

```text
snapshot → diff → validation → confirmation → owner-folder write → diagnostics → rollback information
```

Label Designer now permits guarded create-only flows for Label Context, Label Template, and Label Rule resources under owner paths.

Guarded workflow summary for future direct editing:

```text
load selected owner resource -> generate proposed change -> validate owner boundary -> validate schema -> validate fields/source -> preview diff -> confirm -> create snapshot before write -> write only to owner resource path -> run diagnostics -> keep rollback metadata
```

The same lifecycle may be represented in UI copy as:

```text
load selected owner resource → generate proposed change → validate owner boundary → validate schema → validate fields/source → preview diff → confirm → create snapshot before write → write only to owner resource path → run diagnostics → keep rollback metadata
```

## 2. Required Future Apply Lifecycle

Every future Label Designer apply must follow this order:

1. Select owner.
2. Select target resource type: context/template/rule.
3. Resolve target owner path.
4. Generate proposed JSON.
5. Validate owner boundary.
6. Validate resource schema.
7. Validate selected source/fields.
8. Check duplicate key/path.
9. Show diff/preview.
10. Create snapshot before write.
11. Write only to owner resource path.
12. Run post-apply diagnostics.
13. Record rollback metadata.

No step may be skipped by a route shortcut, client-side request, hidden form, batch operation, or direct service call.

## 3. Snapshot Location

Snapshots for Label Designer context create writes must live under:

```text
storage/studio-snapshots/label-designer/
```

This location is governance evidence only. It is not runtime truth, not an owner label resource folder, and not a registry consumed by runtime printing.

## 4. Snapshot Metadata

Each future snapshot record must include enough information to audit and roll back a create/update operation:

| Field | Meaning |
|---|---|
| `owner` | Selected app/module/plugin owner metadata |
| `resource_type` | One of `context`, `template`, or `rule` |
| `target_path` | Absolute or project-relative owner resource path selected for apply |
| `previous_content` | Previous file content if file existed; null only for explicit create |
| `proposed_content` or `diff` | Proposed JSON content or structural diff being applied |
| `action` | `create` or `update` |
| `user` or `actor` | Authenticated user or system actor if available |
| `timestamp` | Apply attempt timestamp |
| `validation_result` | Boundary, schema, field, duplicate, and side-effect validation summary |
| `rollback_hint` | Human-readable restore instruction and/or snapshot identifier |

Snapshot metadata is not a permission grant, approval substitute, or runtime label definition.

## 5. Target Path Rules

Writes are allowed only after the selected owner and target path pass strict path validation:

- Contexts only under `OwnerRoot/Resources/labels/contexts/`.
- Templates only under `OwnerRoot/Resources/labels/templates/`.
- Rules only under `OwnerRoot/Resources/labels/rules/`.
- No writes to Studio permanent resource truth.
- No writes outside selected owner root.
- No path traversal.
- No overwrite without explicit update flow.

The selected target path must be derived from the resolved owner root and target resource type. User-provided names may influence only the validated filename/key portion after normalization.

Duplicate handling requirements:

- Create flow must fail if `context_key`/`template_key`/`rules_key` already exists in the resolved owner path.
- Update flow must require explicit update intent and must reject silent overwrite attempts from create flow.
- Duplicate checks must evaluate both key identity and resolved target path collisions.

## 6. Required Diagnostics

Before and after future apply, diagnostics must verify:

- owner exists.
- resource path valid.
- JSON valid.
- context/template/rule key valid.
- key unique.
- fields exist in selected candidate owner source.
- no runtime print/export side effect.
- no unsupported print/export side effect.
- no QR runtime change.
- no Manufacturing runtime route change.
- no Core coupling.
- no unrestricted SQL builder.
- no Studio-owned permanent label storage.
- no forbidden runtime coupling.

The post-apply diagnostics must run after the owner-folder write and must be recorded with rollback metadata. A failed diagnostic must leave enough evidence to restore the prior owner resource from the snapshot.

## 7. Rollback Contract

Rollback must be explicit, auditable, and bounded to the selected owner resource path.

- For `create`, rollback removes the newly created owner resource file and records the removal event.
- For `update`, rollback restores `previous_content` to the same owner path.
- Rollback may only target files that were part of the same validated apply attempt.
- Rollback must not write outside the selected owner root.
- Rollback evidence must include actor, timestamp, target path, action, and result.
- Rollback metadata must link to the originating apply attempt via snapshot identifier or equivalent correlation id.

## 8. Source-Of-Truth Boundary

Owner resource folders remain the final destination and source truth:

- `OwnerRoot/Resources/labels/contexts/`
- `OwnerRoot/Resources/labels/templates/`
- `OwnerRoot/Resources/labels/rules/`

Studio may keep proposed JSON, previews, diffs, snapshots, diagnostics, and rollback metadata as governance evidence. Studio must not become permanent label context/template/rule storage, long-lived draft truth for runtime, or the print runtime owner.

## 9. Forbidden Shortcuts

Future implementation must still block:

- Creating a Label Designer POST route before apply safety is enforced.
- Enabling a create/save/apply/edit control before server-side validation exists.
- Writing owner resources without a snapshot.
- Writing snapshots after a failed pre-write validation.
- Writing outside the selected owner resource path.
- Overwriting an existing resource through the create flow.
- Treating candidate DB sources as approved label sources.
- Binding arbitrary global DB tables.
- Triggering QR, Manufacturing, Platform print/export, or Core runtime behavior from Label Designer.

## 10. Explicit Non-Goals For This Slice

This slice enables guarded context create, guarded template create, guarded rule create, read-only template preview, and rule preview sandbox.

- No context/template/rule edit/apply/update/delete behavior is enabled in this slice (except metadata migration).
- No overwrite flow is enabled in this slice.
- No DB table creation or SQL builder behavior is added in this slice.

This contract is a prerequisite for future implementation; it is not an implementation grant.

## 11. Metadata Migration Snapshot Contract

Metadata migration applies to legacy label resources that lack a top-level `owner_key`. The guarded migration write follows the same snapshot-before-write pattern as create operations.

### 11.1 Snapshot Naming Convention

```
storage/studio-snapshots/label-designer/metadata-migration-{safeResourceKey}-{snapshotId}.json
```

### 11.2 Snapshot Payload

| Field | Description |
|---|---|
| `action` | `metadata-migration` |
| `resource_path` | Relative path of the migrated resource |
| `resource_type` | One of `context`, `template`, `rule` |
| `original_json` | Full JSON content before migration |
| `proposed_json` | Full JSON content after migration (with added owner_key) |
| `path_owner` | Owner key inferred from resource path |
| `migration_reason` | Explanation of why migration was applied |
| `snapshot_id` | Unique snapshot identifier |
| `created_at` | ISO 8601 timestamp |
| `migration_scope` | `single-resource` |

### 11.3 Migration Safety Rules

- Backup file (`{resourcePath}.metadata-migration-backup`) is created before the write, distinct from the snapshot.
- Post-migration diagnostics run after the write: MIG001 (migration applied), MIG003 (snapshot, backup), MIG004 (owner_key added, JSON validity, owner match).
- If the write fails, the backup is restored.
- If any post-migration diagnostic returns FAIL or ERROR severity, the migration is flagged but not rolled back (the backup remains available).

## 11. Relationship To Label Validation Contract

Resource files created through the guarded context-create flow must satisfy the validation rules in:

- `docs/architecture/label-validation-contract.md`

Validation of context schema, fields, key uniqueness, owner boundary, and path containment must pass before a snapshot is written. The validation contract does not authorize render, print, or runtime behavior.

### 11.1 Relationship To Label Rule Resource Contract

Rule resources created through future guarded flows must follow the architecture, ownership, validation boundaries, and runtime placement defined by:

- `docs/architecture/label-rule-resource-contract.md`

The rule resource contract does not authorize implementation of rule execution, rendering, or runtime evaluation.

### 11.2 Relationship To Label Runtime Contract

Resources created through the guarded flow are consumed at runtime per:

- `docs/architecture/label-runtime-contract.md`

The runtime contract defines how owner-owned context resources are loaded, validated, paired with templates, and submitted to the Platform render pipeline. It does not authorize implementation of a renderer, print pipeline, or output engine.

### 11.3 Relationship To Label Render Pipeline Contract

Resources created through the guarded flow are consumed by the Platform render pipeline per:

- `docs/architecture/label-render-pipeline-contract.md`

The render pipeline contract defines the canonical render chain, Resolved Label Model, output adapters, error handling, rule interaction, data policy, and Studio relationship. It does not authorize implementation of a renderer, print pipeline, or output engine.
