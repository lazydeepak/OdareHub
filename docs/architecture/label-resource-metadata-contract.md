# Label Resource Metadata Contract V1 (label-resource-metadata-contract.md)

## Purpose

Define canonical metadata requirements for every owner-owned label resource type (Context, Template, Rule). Resources must be self-describing and ownership-valid without requiring discovery fallback. This is the **Metadata-First Ownership** contract.

## Ownership Model

- **Owner** = app, module, or plugin that owns the resource.
- **Resource** = a JSON file under `{OwnerRoot}/Resources/labels/{contexts,templates,rules}/`.
- **Metadata** = fields declared *inside* the resource JSON file, not in discovery or external files.

## Context Resource Metadata

### Required Fields

| Field | Type | Description |
|---|---|---|
| `schema` | string | Must be `odarehub.label.context.v1` |
| `owner_key` | string | Canonical owner key (e.g. `Manufacturing/Products`) |
| `owner_type` | string | Canonical owner type (`app`, `module`, or `plugin`) |
| `owner_root` | string | Repository-relative canonical owner root |
| `resource_type` | string | Must be `context` |
| `context_key` | string | Unique context identifier |
| `purpose` | string | Business purpose of the label context |
| `allowed_fields` | array | Field definitions with `field_key`, `label`, `source_column` |

### Optional Fields

| Field | Type | Description |
|---|---|---|
| `owner` | object | Extended owner metadata (`owner_key`, `owner_type`, `root_path`) |
| `data_source_boundary` | object | Data source reference |
| `print_locations` | object | Print/use location intent |
| `template_ref` | object | Linked template reference |
| `rules_refs` | array | Linked rule keys |
| `permissions` | array | Permission constraints |

### Validation Requirements

- `schema` must match `odarehub.label.context.v1` exactly.
- `owner_key` must match the owner root the file is stored under.
- `context_key` must be unique per owner.
- File must be stored at `{OwnerRoot}/Resources/labels/contexts/{context_key}.label-context.json`.

### Ownership Requirements

- `owner_key` at top level must match the owner root directory.
- If `owner.owner_key` is present, it must match `owner_key`.
- Discovery must verify `owner_key` matches the file's path owner root.

## Template Resource Metadata

### Required Fields

| Field | Type | Description |
|---|---|---|
| `schema` | string | Must be `odarehub.label.template.v1` |
| `owner_key` | string | Canonical owner key |
| `owner_type` | string | Canonical owner type (`app`, `module`, or `plugin`) |
| `owner_root` | string | Repository-relative canonical owner root |
| `resource_type` | string | Must be `template` |
| `template_key` | string | Unique template identifier |
| `context_ref` | object | Must contain `context_key` matching a valid context |
| `layout` | object | Must contain `label_size` and `blocks` |

### Optional Fields

| Field | Type | Description |
|---|---|---|
| `write_status` | string | Lifecycle status (`created`, `active`, `draft`) |
| `fields` | array | Bound fields |
| `style_tokens` | array | CSS variable references |
| `notes` | array | Annotations |
| `context_ref.owner_key` | string | Owner key in context ref (may duplicate top-level `owner_key`) |
| `context_ref.owner_type` | string | Owner type in context ref |

### Validation Requirements

- `schema` must match `odarehub.label.template.v1` exactly.
- `owner_key` at top level must match the owner root.
- `template_key` must be unique per owner.
- `context_ref.context_key` must match an existing context resource.
- File must be stored at `{OwnerRoot}/Resources/labels/templates/{template_key}.json`.

### Ownership Requirements

- Top-level `owner_key` is the canonical ownership field.
- `context_ref.owner_key` is legacy metadata; may be omitted when top-level `owner_key` exists.
- Both must match if both are present.

### Migration Note

Existing template resources may have `owner_key` only inside `context_ref`. These are legacy resources. The top-level `owner_key` must be added for compliance.

## Rule Resource Metadata

### Required Fields

| Field | Type | Description |
|---|---|---|
| `schema` | string | Must be `odarehub.label.rule.v1` |
| `owner_key` | string | Canonical owner key |
| `rule_key` | string | Unique rule identifier |
| `conditions` | array | Condition expressions |
| `effects` | array | Effect declarations |

### Optional Fields

| Field | Type | Description |
|---|---|---|
| `context_key` | string | Bound context key |
| `template_key` | string | Bound template key |
| `enabled` | bool | Whether rule is active |
| `write_status` | string | Lifecycle status |

### Validation Requirements

- `schema` must match `odarehub.label.rule.v1` exactly.
- `owner_key` must match the owner root.
- `rule_key` must be unique per owner.
- File must be stored at `{OwnerRoot}/Resources/labels/rules/{rule_key}.json`.

### Ownership Requirements

- `owner_key` must match the owner root.
- Rule resources already include top-level `owner_key` (compliant by default).

## Migration Expectations

1. Context resources without `owner_key` are **legacy** and must be migrated.
2. Template resources without top-level `owner_key` are **legacy** and must be migrated.
3. Rule resources are **compliant** (already include `owner_key`).
4. Migration is **owner-responsibility**, not Studio-automated.
5. Studio may **preview and diagnose** migration readiness.
6. Studio may **apply** migration with explicit owner confirmation, snapshot before write, and backup creation.
7. Studio applies a **single resource at a time** — no bulk migration.
8. Phase 4.1 migration adds only missing top-level `owner_key`, `owner_type`, `owner_root`, and `resource_type`. No rename, move, or business-field changes.

## Validation Checks

### M001 — Metadata Completeness
Verifies that the resource has all required metadata fields: `schema`, `owner_key`, and resource-specific required fields (e.g., `context_key` for contexts, `template_key` for templates). PASS if all present; WARN if discoverable but missing top-level owner_key.

### M002 — Owner Ownership Consistency
Verifies that the declared `owner_key` matches the file path owner root and the extended `owner.owner_key` if present. FAIL on mismatch; WARN on missing extended owner metadata.

## Metadata Status Classification

| Status | Definition |
|---|---|
| **Compliant** | All required metadata fields present and valid |
| **Legacy** | Required metadata fields missing; discovery fallback used |
| **Partial** | Some metadata present but incomplete |
| **Missing** | Critical metadata absent (schema, owner_key, resource key) |

## Studio Boundary

- Studio **May** preview, diagnose, and report metadata readiness.
- Studio **May** suggest migration paths and generate preview diffs.
- Studio **May** apply guarded single-resource metadata migration with:
  - explicit owner confirmation (checkbox or modal)
  - snapshot before write stored in `storage/studio-snapshots/label-designer/`
  - backup (`.metadata-migration-backup` file) at resource location before write
- Studio **Must Not** auto-migrate resources without explicit owner action.
- Studio **Must Not** bulk migrate or batch-rewrite resources.
- Studio **Must Not** modify resources it does not own.
- Studio **Must Not** bypass validation checks before any metadata change.
- Studio **Must Not** change resource ownership, rename/move files, or alter business meaning.

## Phase 2: DataProvider Ownership

In Phase 2, the **DataProvider** will be a separate owner type responsible for sample data generation and test fixtures. The DataProvider contract will extend this metadata contract with additional fields specific to data provisioning. Until Phase 2, the Label Preview Renderer generates in-memory sample data only — no DataProvider resources exist.

## Runtime Owner

The **Runtime Owner** is the app, module, or plugin that consumes the label resource at runtime (e.g., printing, display, export). The Runtime Owner may differ from the Metadata Owner (owner of the resource file). The resource's `owner_key` always refers to the Metadata Owner. Runtime Owning is documented in the [Label Runtime Contract](label-runtime-contract.md).

## Cross-References

- [Label Resource Contract](label-resource-contract.md)
- [Label Rule Resource Contract](label-rule-resource-contract.md)
- [Label Validation Contract](label-validation-contract.md)
- [Label Designer Operating Contract](label-designer-operating-contract.md)
- [Label Runtime Contract](label-runtime-contract.md)
- [Label Render Pipeline Contract](label-render-pipeline-contract.md)
