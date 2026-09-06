# Label Designer Template Apply/Snapshot Safety Contract

Status: Architecture contract baseline for future guarded template creation. This document defines required safeguards for template create/update flows before any template write route or server-side write implementation is enabled. It does not authorize template writes in this slice.

## 1. Purpose

After context-create governance has been established, template creation must follow the same guarded owner-resource lifecycle:

```text
snapshot -> diff -> validation -> confirmation -> owner-folder write -> diagnostics -> rollback information
```

UI wording equivalent:

```text
snapshot -> diff -> validation -> confirmation -> owner-folder write -> diagnostics -> rollback information
```

This contract applies to template resource writes under:

```text
OwnerRoot/Resources/labels/templates/
```

## 2. Scope For Future Guarded Template Create

Future guarded template create may allow:

1. Select owner-owned label context.
2. Select label size/layout mode.
3. Select context-allowed fields.
4. Preview illustrative template JSON.
5. Confirm create intent.
6. Create snapshot before write.
7. Write only to owner template path.
8. Run post-write diagnostics.
9. Persist rollback metadata.

Until these safeguards are fully implemented server-side, template create/write remains disabled.

## 3. Required Apply Lifecycle

Every future template apply must follow this order:

1. Select owner.
2. Select context.
3. Resolve owner template target path.
4. Generate proposed template JSON.
5. Validate owner boundary.
6. Validate template schema.
7. Validate selected fields belong to selected context.
8. Validate selected label size/layout preset.
9. Check duplicate template key/path.
10. Show diff/preview.
11. Require explicit confirmation.
12. Create snapshot before write.
13. Write only to owner template path.
14. Run post-apply diagnostics.
15. Record rollback metadata.

No step may be skipped by client-side controls, hidden route handlers, or direct service invocation.

## 4. Snapshot Location And Metadata

Template apply snapshots must be stored under governance evidence root:

```text
storage/studio-snapshots/label-designer/
```

Required metadata fields:

- `owner`
- `resource_type` = `template`
- `context_key`
- `template_key`
- `target_path`
- `previous_content`
- `proposed_content` or `diff`
- `action` (`create` or `update`)
- `actor`
- `timestamp`
- `validation_result`
- `rollback_hint`
- `snapshot_id`

Snapshots are evidence only, not runtime source of truth.

## 5. Target Path And Duplicate Rules

Template writes must obey:

- Write only under `OwnerRoot/Resources/labels/templates/`.
- No writes outside selected owner root.
- No path traversal.
- No overwrite through create flow.
- Create fails if `template_key` or resolved path already exists.
- Update requires explicit update intent and cannot be silently inferred from create.
- Duplicate checks must evaluate both key collisions and resolved target-path collisions.

## 6. Required Diagnostics

Before and after future template apply, diagnostics must verify:

- owner exists.
- selected context exists and is owner-owned.
- context key valid.
- template key valid.
- template path valid.
- selected fields are subset of context allowed fields.
- selected label size/layout is allowed.
- generated JSON valid.
- duplicate rules enforced.
- no runtime print/export side effects.
- no QR runtime change.
- no Manufacturing runtime route change.
- no Core coupling.
- no unrestricted SQL builder.
- no Studio-owned permanent label storage.

## 7. Rollback Contract

Rollback must be auditable and owner-bounded:

- For create: delete newly created owner template file.
- For update: restore previous owner template content.
- Rollback must not target files outside selected owner root.
- Rollback metadata must link to originating snapshot/apply id.
- Record actor, timestamp, path, action, and outcome.

## 8. Explicit Non-Goals In This Slice

This slice is contract/diagnostic preparation only.

- No template create route is enabled.
- No template write/apply implementation is enabled.
- No visual canvas editor behavior is enabled.
- No rule create/edit/apply behavior is enabled.
- No runtime print/export behavior is enabled.
- No migration, DB table creation, or SQL builder behavior is added.

## 9. Relationship To Existing Contracts

This contract extends:

- `docs/architecture/label-designer-apply-snapshot-safety-contract.md` (general owner-resource safety lifecycle)
- `docs/architecture/label-resource-contract.md` (resource ownership and path contracts)
- `docs/architecture/label-designer-operating-contract.md` (Studio ownership and boundary rules)

This document does not authorize implementation by itself.

## 10. Relationship To Label Validation Contract

Template resource files created through the guarded template-create flow must satisfy the validation rules in:

- `docs/architecture/label-validation-contract.md`

Validation of template schema, context/template compatibility, field binding consistency, duplicate key rules, owner boundary, label size convention, and forbidden runtime assumptions must pass before a snapshot is written. The validation contract does not authorize render, print, or runtime behavior.

## 11. Relationship To Label Rule Resource Contract

Rule resources that may reference templates in the future must follow the architecture, ownership, and validation boundaries defined by:

- `docs/architecture/label-rule-resource-contract.md`

The rule resource contract does not authorize implementation of rule creation, execution, or rendering.

## 12. Relationship To Label Runtime Contract

Template resources created through the guarded flow are consumed at runtime per:

- `docs/architecture/label-runtime-contract.md`

The runtime contract defines how owner-owned template resources are loaded, validated against their referenced context, paired with data payloads, and submitted to the Platform render pipeline. It does not authorize implementation of a renderer, print pipeline, or output engine.

## 13. Relationship To Label Render Pipeline Contract

Template resources created through the guarded flow are consumed by the Platform render pipeline per:

- `docs/architecture/label-render-pipeline-contract.md`

The render pipeline contract defines the canonical render chain, Resolved Label Model, output adapters, rule interaction, and error handling for production label output. It does not authorize implementation of a renderer, print pipeline, or output engine.
