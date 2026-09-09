# Label Validation Contract

Status: Architecture contract baseline. This contract defines what makes a Label Context and Label Template resource valid before preview, render, print, or runtime use. It does not authorize implementation of preview, render, print, or runtime consumption. All validation rules are declarative — they must be enforceable by static analysis of the resource files alone, without DB queries, runtime state, or business data.

---

## 1. Context Schema Rules

Every Label Context Resource must conform to schema `odarehub.label.context.v1`. This section defines mandatory and optional fields, their types, and the value constraints that produce a valid context.

### 1.1 Required Fields

| Field | Type | Constraint |
|---|---|---|
| `schema` | string | Must equal `odarehub.label.context.v1` exactly. |
| `context_key` | string | Must be non-empty. Must match `[a-z0-9._-]+`. Must be unique per owner. |
| `purpose` | string | Must be non-empty, plain-text business description. |
| `allowed_fields` | array of objects | Must contain at least one field entry. Each entry must have a unique `field_key`. |
| `data_source_boundary` | object | Must declare the data source that produces field values at print time. |

### 1.2 Optional Fields

| Field | Type | Default / Behavior |
|---|---|---|
| `print_locations` | object | May be `{"status": "owner_defined_later"}` during authoring. When defined, must contain owner runtime surfaces/routes. |
| `template_ref` | object | May be `{"status": "owner_template_selected_later"}` during authoring. When defined, must reference an existing owner template key or path. |
| `rules_refs` | array of strings | May be empty. Each entry must reference an existing owner rule set key. |
| `permissions` | array of strings | May be empty. Each entry must be a known permission key. |

### 1.3 Field Entry Schema

Each entry in `allowed_fields` must be an object with:

| Field | Required | Constraint |
|---|---|---|
| `field_key` | Yes | Must match `[A-Za-z0-9_]+`. Must be unique across all entries in the same context. |
| `label` | Yes | Non-empty display label. |
| `source_column` | No | Column or property name in the data source. Defaults to `field_key` if absent. |

### 1.4 Data Source Boundary Rules

The `data_source_boundary` object must declare the owner-scoped data source. During Phase 1 (bootstrap DB discovery mode), allowed forms:

```json
{
    "source_name": "...",
    "source_type": "manual_setup",
    "status": "owner_scoped_candidate_selected"
}
```

Future forms may include `source_type: "table"`, `source_type: "view"`, or `source_type: "api"`, but the source must always resolve to owner-scoped data, never arbitrary global access.

### 1.5 Context Key Uniqueness

`context_key` must be globally unique across all owners. The convention `{owner_namespace}.{label_purpose}` (e.g. `manufacturing.product.label`) provides implicit uniqueness by owner namespace prefix, but the validator must treat any two context files with the same `context_key` across different owners as a collision.

### 1.6 Context File Path Convention

Context files must be named `{context_key}.label-context.json` and reside under `{OwnerRoot}/Resources/labels/contexts/`. The filename stem must match `context_key` exactly after both are normalized to lowercase.

### 1.7 Schema Versioning

`odarehub.label.context.v1` is the initial and only version. Future versions (`v2`, etc.) must be additive-only — no field may be removed or changed in meaning. A context file that declares an unrecognized schema version is invalid for all purposes.

---

## 2. Template Schema Rules

Every Label Template Resource must conform to schema `odarehub.label.template.v1`.

### 2.1 Required Fields

| Field | Type | Constraint |
|---|---|---|
| `schema` | string | Must equal `odarehub.label.template.v1` exactly. |
| `template_key` | string | Must be non-empty. Must match `[a-z0-9._-]+`. Must be unique per owner. |
| `context_ref` | object | Must reference an existing, valid owner-owned context. |
| `layout` | object | Must declare a valid `label_size` and at least one `block`. |
| `fields` | array of objects | Must contain at least one field entry. |

### 2.2 Optional Fields

| Field | Type | Default / Behavior |
|---|---|---|
| `write_status` | string | Authoring metadata only. Not validated for render/preview. |
| `rules_refs` | array of strings | May be empty. Each entry must reference an existing owner rule set key. |
| `preview_sample` | object | Owner-approved sample data for read-only preview. |
| `notes` | array of strings | Authoring metadata only. Not validated for render/preview. |

### 2.3 Context Ref Validation

`context_ref` must be an object with:

| Field | Required | Constraint |
|---|---|---|
| `context_key` | Yes | Must match `context_key` of an existing context file. |
| `context_file` | No | Project-relative path to the context file. Must resolve if present. |
| `owner_key` | No | Must match the context's owner if present. |
| `owner_type` | No | Must match the context's owner type if present. |

At minimum, `context_key` must be present and resolvable to exactly one context file in the label resource tree.

### 2.4 Block Validation

`layout.blocks` must be an array of objects with:

| Field | Required | Constraint |
|---|---|---|
| `block_key` | Yes | Unique within the template. Must match `[a-z0-9._-]+`. |
| `role` | Yes | Must be one of the known block roles (see 2.4.1). |

#### 2.4.1 Known Block Roles

| Role | Purpose |
|---|---|
| `title_and_identity` | Header block — product name, logo, context identity |
| `field_rows` | Data rows — field label/value pairs from the context |
| `owner_signoff_qr_placeholder` | Footer block — QR/barcode, signoff, size/origin info |
| `barcode_slot` | Dedicated barcode or QR code render area |
| `image_slot` | Static image or logo area |
| `text_block` | Free-text or instructions area |

Block roles are not exhaustive — new roles may be added through amendment. A block with an unrecognized role is not invalid by itself but may affect renderer compatibility.

### 2.5 Template Key Uniqueness

`template_key` must be unique per owner. The convention `{context_key}.{size}` (e.g. `manufacturing.product.label.100x50_mm`) provides implicit scoping by context, but a validator must reject any duplicate keys within the same owner folder, even if they reference different contexts.

### 2.6 Template File Path Convention

Template files must be named `{template_key}.json` and reside under `{OwnerRoot}/Resources/labels/templates/`. The filename stem must match `template_key` exactly after normalization.

### 2.7 Schema Versioning

`odarehub.label.template.v1` is the initial version. Same additive-only rule as contexts.

### 2.8 Write Status Contract

The `write_status` field is authoring metadata only. Valid values:

| Value | Meaning |
|---|---|
| `created` | Written by a create flow. Initial state. |
| `draft` | Pending owner approval (future). |
| `approved` | Owner-approved for runtime consumption (future). |
| `deprecated` | No longer active; retained for rollback continuity. |

Runtime consumers must not render templates with `write_status` other than `created` or `approved`. A missing `write_status` defaults to `created`.

---

## 3. Field Binding Rules

### 3.1 Context-to-Template Field Consistency

Every field entry in a template's `fields` array must satisfy:

1. `field_key` exists in the referenced context's `allowed_fields[*].field_key`.
2. `field_key` is used at most once in the template (no duplicate field bindings).
3. The template must not reference fields outside the context's allowed set.

### 3.2 Template Field Shape

Each field entry in the template must be an object with:

| Field | Required | Constraint |
|---|---|---|
| `field_key` | Yes | Must match `[A-Za-z0-9_]+`. Must exist in referenced context's `allowed_fields`. |
| `label` | No | Display label; defaults to the context's label for this field_key if absent. |
| `source_column` | No | Data column; defaults to the context's source_column for this field_key if absent. |

If `label` or `source_column` are present, they must not contradict the context's values for the same `field_key` (warn on mismatch, do not block).

### 3.3 Field Count Rules

- Contexts must declare at least one allowed field.
- Templates must include at least one field binding.
- Templates should not include all context fields unless that is the intentional layout. No hard upper limit, but renderer contracts may cap visible fields per label size.

### 3.4 Implicit vs Explicit Binding

All field bindings in a template are explicit. There is no "inherit all from context" shortcut. Every field printed on a label must be declared in the template's `fields` array.

---

## 4. Context/Template Compatibility

### 4.1 Direct Reference

A template is compatible with a context if and only if:

1. `context_ref.context_key` matches the context's `context_key`.
2. All template fields are within the context's `allowed_fields`.
3. The context is valid per Section 1.
4. The context file exists at the time of validation.

### 4.2 Orphaned Template

A template whose `context_ref.context_key` points to a context that no longer exists is orphaned. Orphaned templates must not be rendered or printed. Validation must report orphaned templates as a separate severity from structural invalidity — an orphaned template may be structurally valid JSON but is semantically dead.

### 4.3 Orphaned Context

A context with no templates referencing it is not invalid but may be flagged as unused during diagnostics. A context is not orphaned by definition — contexts exist independently as business purpose declarations.

### 4.4 Multiple Templates Per Context

A single context may have multiple templates. Each template must have a unique `template_key` within the owner. No restriction on how many templates reference the same context, as long as each satisfies the compatibility rules.

### 4.5 Cross-Owner Reference

A template in owner A must not reference a context owned by owner B without explicit cross-owner delegation declared in both resources. Cross-owner references are out of scope for this contract version — the validator must reject them.

---

## 5. Duplicate Key Rules

### 5.1 Context Key Uniqueness

`context_key` must be globally unique across the entire label resource tree. No two owner folders may contain context files with the same `context_key`. Detection method: scan all `{OwnerRoot}/Resources/labels/contexts/*.label-context.json` files and extract `context_key`.

### 5.2 Template Key Uniqueness

`template_key` must be unique per owner. A template key collision across different owners is not a violation — `manufacturing.product.label.100x50_mm` and `lazypos.product.label.100x50_mm` are distinct templates. Detection method: scan per-owner template folder.

### 5.3 Duplicate File Path

No two context files may resolve to the same filesystem path. No two template files may resolve to the same filesystem path. This is enforced by file creation guards (create flow blocks on `is_file`), but validation must also detect pre-existing collisions.

### 5.4 Duplicate Within File

Within a single context file, `field_key` entries in `allowed_fields` must be unique. Within a single template file, `block_key` entries in `layout.blocks` must be unique, and `field_key` entries in `fields` must be unique.

---

## 6. Owner Boundary Rules

### 6.1 Resource Path Containment

Every label resource file must reside under `{OwnerRoot}/Resources/labels/` and one of its canonical subdirectories. The owner root is the filesystem path of the owning app, module, or plugin. Paths outside this containment are invalid.

### 6.2 Owner Key Resolution

The owner is inferred from the resource's filesystem location, not from embedded JSON fields. If a file at `apps/Manufacturing/modules/Products/Resources/labels/contexts/product-label.label-context.json` declares `context_key`, the owner is `Manufacturing/Products`. The embedded `context_ref.owner_key` (if present) must match the filesystem-inferred owner or be absent.

### 6.3 Owner Lifecycle Classification

Only lifecycle owners may host label resources. Non-lifecycle owners (Shell, Studio, Platform, plugins) must not have `Resources/labels/` folders. Validation must reconcile against the lifecycle classification from `LabelDesignerResourceReadinessService::checkReadiness()` or an equivalent owner registry.

### 6.4 Owner Resource Directory Structure

The directory structure under an owner root must be exactly:

```text
{OwnerRoot}/Resources/labels/
    contexts/       (*.label-context.json files)
    templates/      (*.json files)
    rules/          (*.json files, future)
```

Files outside these subdirectories are not recognized as label resources. Empty directories are valid (owner may have contexts but no templates yet).

### 6.5 No Orphan Files

Every `.json` file in `Resources/labels/templates/` must have a corresponding context (per Section 4.2). Every `.label-context.json` file in `Resources/labels/contexts/` must be parseable as `odarehub.label.context.v1`. Files that fail parse or schema validation must be reported as invalid and must not participate in template binding resolution.

---

## 7. Label Size Rules

### 7.1 Size Key Convention

Label sizes are declared as `{width}x{height}_{unit}`, e.g. `100x50_mm`. The size key must match `[0-9]+x[0-9]+_[a-z]+`.

### 7.2 Unit Constraints

Supported units:

| Unit | Meaning |
|---|---|
| `mm` | Millimeters. Preferred for physical label stock. |
| `in` | Inches. Alternative for imperial-standard environments. |
| `px` | Pixels. Screen-only preview; not valid for physical print. |

A template using an unrecognized unit is invalid for print but may still be valid for screen preview with appropriate warnings.

### 7.3 Size-to-Renderer Compatibility

No renderer exists yet. When a renderer pipeline is defined, a template whose `layout.label_size` exceeds the renderer's physical or logical constraints must be reported as incompatible but not structurally invalid. The renderer contract will define its own size envelope.

### 7.4 Size in Template vs Context

Label size is a template property, not a context property. A context may have templates in multiple sizes. Validation must not couple size validation to context-level data.

---

## 8. Forbidden Runtime Assumptions

### 8.1 Resources Must Be Statically Validable

No validation rule in this contract may require:

- A running database connection.
- Active user sessions or authentication state.
- Live print renderer state.
- QR/bar code rendering engine availability.
- Network access or remote service availability.
- Runtime business data (product names, batch numbers, quantities).
- Runtime permission checks beyond what the owner path implies.

### 8.2 Resources Must Not Reference Runtime State

Label resource files must not contain:

- Embeddings of runtime data or sample values as source of truth.
- Hardcoded file paths outside the owner resource tree.
- Absolute filesystem paths.
- Environment-specific values (DB host, environment name, secret keys).
- SQL queries or table names that bypass the declared `data_source_boundary`.

### 8.3 Resources Must Not Trigger Side Effects

Validation and static analysis of label resources must be read-only. Reading a context or template file must not:

- Trigger database writes.
- Send network requests.
- Execute print commands.
- Generate QR or barcode images.
- Mutate any application state.
- Log validation events at error level (informational diagnostics are permitted).

### 8.4 No Business Data Validation

Validation must not verify that `data_source_boundary` references a real table, that `source_column` values match actual DB columns, or that field values will exist at print time. Those are runtime concerns owned by the data provider contract.

### 8.5 No Renderer Contract Validation

This contract does not define renderer capabilities. No validate rule may check label size against a future renderer's DPI, margins, font availability, or color support. Renderer-specific validation belongs in a separate renderer contract.

### 8.6 No Permissions Enforcement

Validation must not check whether the current user has permission to view, print, or edit a label resource. Permissions are a Core governance concern and must be enforced at the access point (route handler, API, print trigger), not at the resource file level.

### 8.7 No Lifecycle State Validation

The `write_status` field (Section 2.8) is informative metadata. Validation may warn about unexpected values but must not reject a resource solely on `write_status` except where the status implies an unambiguous invalid state (e.g., a template with `write_status: "deprecated"` may be structurally valid but the creator must know it is marked inactive).

### 8.8 Metadata Migration Diagnostics (MIG001-MIG007)

Post-migration diagnostics verify that a metadata migration apply was successful. Diagnostics use the severity model from Section 9.2.

| Rule | Check | Severity | Description |
|---|---|---|---|
| MIG001 | Migration completed | PASS | Resource now has top-level owner_key |
| MIG002 | Migration not required | PASS | Resource already has owner_key |
| MIG003 | Snapshot and backup | PASS/WARN/FAIL | Snapshot created; backup exists |
| MIG004 | Migration applied | PASS/FAIL/ERROR | File exists, owner_key added, JSON valid, owner matches path |
| MIG005 | Migration blocked | FAIL/ERROR | Preconditions not met (confirmation, writable, readable, valid JSON) |
| MIG006 | Path and owner validation | FAIL | Canonical path check, owner lifecycle eligibility |
| MIG007 | Post-migration consistency | WARN/FAIL | Template context_ref owner_key alignment, resource type preservation |

Migration validation must NOT trigger side effects (Section 8.3), check runtime state, modify permissions, or read DB state.

---

## 9. Diagnostics Before Future Renderer

### 9.1 Diagnostic Purpose

Before a future renderer consumes a label context/template pair, diagnostics must verify:

1. The context and template are both valid per Sections 1-8.
2. The template's `context_ref.context_key` resolves to exactly one context file.
3. All field bindings satisfy Section 3.
4. The owner boundary (Section 6) is satisfied.
5. No duplicate keys exist in the render scope.
6. No forbidden runtime assumptions (Section 8) are present.

### 9.2 Diagnostic Severity Classification

| Severity | Meaning | Render blocking? |
|---|---|---|
| `PASS` | All validation rules satisfied. | No |
| `WARN` | Non-blocking issue. Template may render with degraded behavior. | No |
| `FAIL` | One or more blocking rules violated. | Yes — must not render or print |
| `ERROR` | Resource file is unparseable (not valid JSON, missing required fields). | Yes — cannot proceed |

### 9.3 Render-Blocking Failures

The following conditions are always render-blocking:

- Invalid or missing `schema` field.
- Unparseable JSON file.
- `context_ref.context_key` refers to a context that does not exist or fails validation.
- Template field references a `field_key` not in the context's `allowed_fields`.
- Resource file is outside owner boundary.
- Orphaned template (per Section 4.2).
- Cross-owner reference without delegation.

### 9.4 Pre-Render Validation Workflow

The future renderer must execute this validation order:

```text
load template JSON -> validate schema -> resolve context -> validate context schema ->
validate field binding consistency -> validate owner boundary ->
check duplicate keys -> check orphan status -> report PASS/WARN/FAIL/ERROR
```

A `FAIL` or `ERROR` at any step must prevent rendering and report the reason. A `WARN` must be recorded but the renderer may proceed at owner discretion.

### 9.5 Diagnostics Metadata

The diagnostic result for a render request must include:

| Field | Meaning |
|---|---|
| `template_key` | Template being validated |
| `context_key` | Resolved context |
| `owner_key` | Owner inferred from template path |
| `severity` | Highest severity across all checks |
| `checks` | Array of individual check results with rule_id, status, message |
| `render_allowed` | Boolean: true if no FAIL or ERROR |
| `timestamp` | Validation timestamp |

### 9.6 Diagnostics vs Static Analysis

Diagnostics (Section 9) are the runtime gate before rendering. Static analysis (Sections 1-8) is the structural gate during authoring and maintenance. They share the same rules but have different audiences and triggers:

- Static analysis runs during Studio authoring, commit hooks, and periodic audits.
- Diagnostics run on-demand before every render or print request.
- Both must agree on the validation outcome for the same resource pair.

### 9.7 Future Renderer Contract Scope

The renderer contract (not this document) will define:

- Physical label size constraints and DPI.
- Supported block roles and their render behavior.
- Barcode/QR symbology support.
- Font and image embedding rules.
- Print pipeline integration.
- Multi-template selection rules.
- Data provider contract for field values.

This contract does not authorize that renderer contract or any render implementation.

---

## 10. Relationship To Existing Contracts

This contract is referenced by and consistent with:

- `docs/architecture/label-resource-contract.md` — Owner resource ownership and canonical fields.
- `docs/architecture/label-designer-apply-snapshot-safety-contract.md` — Apply lifecycle and snapshot safety for context creation.
- `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md` — Apply safety for template creation.
- `docs/architecture/label-designer-operating-contract.md` — Studio boundary, ownership model, and non-goals.
- `docs/architecture/label-runtime-contract.md` — Runtime consumption of owner label resources, including validation requirements, data access policy, and output targets.
- `docs/architecture/label-rule-resource-contract.md` — Owner-owned label rule resource architecture, validation boundaries, and future runtime position.
- `docs/architecture/label-render-pipeline-contract.md` — Platform render pipeline, Resolved Label Model, render stages, and error handling; uses validation rules and severity model defined in this contract.

This contract does not authorize any implementation of a renderer, print pipeline, QR generation, barcode generation, data provider, or runtime label consumption.

---

## 11. Non-Goals

This contract does not:

- Define the renderer API or data provider contract.
- Authorize runtime label printing or export behavior.
- Define label rule set execution semantics (see `docs/architecture/label-rule-resource-contract.md` for the rule resource architecture contract).
- Define cross-owner label sharing or delegation.
- Define version migration or upgrade paths for label resources.
- Define permissions enforcement or access control for label resources.
- Replace owner approval or governance workflows.
