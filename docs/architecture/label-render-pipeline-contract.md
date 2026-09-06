# Label Render Pipeline Contract

> **Status**: Architecture contract — documentation only. No implementation authorized.

---

## Table of Contents

- [Part A — Canonical Render Chain](#part-a--canonical-render-chain)
- [Part B — Ownership Model](#part-b--ownership-model)
- [Part C — Resolved Label Model](#part-c--resolved-label-model)
- [Part D — Render Stages](#part-d--render-stages)
- [Part E — Output Adapter Contract](#part-e--output-adapter-contract)
- [Part F — Error Handling](#part-f--error-handling)
- [Part G — Rule Interaction](#part-g--rule-interaction)
- [Part H — Data Policy](#part-h--data-policy)
- [Part I — Studio Relationship](#part-i--studio-relationship)
- [Part J — Explicit Non-Goals](#part-j--explicit-non-goals)
- [Part K — Cross-References](#part-k--cross-references)

---

## Part A — Canonical Render Chain

The future render pipeline consumes validated owner-owned label resources outside Studio. The chain defines every stage from runtime action to output.

```text
Owner Runtime Action
    ↓
LabelRuntimeRequest
    ↓
Validation Layer
    ↓
Context Resolution
    ↓
Template Resolution
    ↓
Rule Resolution
    ↓
Resolved Label Model
    ↓
Platform Render Pipeline
    ↓
Output Adapter
```

### Stage Responsibilities

#### 1. Owner Runtime Action
The owner (app, module, or plugin) decides to render a label. This is a business decision — printing a QR code on a product, displaying a bin label on a kiosk, exporting a shipping label to PDF. The action originates from owner-controlled code, not from Studio or Platform infrastructure.

**Owner provides**: owner identity, context selection, template selection, data payload, output target selection, and any runtime parameters (quantity, copies, printer).

#### 2. LabelRuntimeRequest
The action is normalized into a canonical request object. The request shape is defined in the [Label Runtime Contract](label-runtime-contract.md) (Section 4).

**Required fields**: `owner_key`, `context_key`, `template_key`, `data_payload`.

**Optional fields**: `rule_context_key`, `rules_filter`, `output_target`, `output_options`, `runtime_params`, `actor`.

#### 3. Validation Layer
The request is validated against the [Label Validation Contract](label-validation-contract.md) before any resource resolution occurs. Validation is stateless, read-only, and produces a diagnostic set with PASS/WARN/FAIL/ERROR severity.

**Validated at this stage**:
- Required fields present.
- Owner key format and boundary.
- Context key and template key format.
- `data_payload` structure (keys present, types match, no forbidden values).
- Output target is a recognized category (see Part E).

**Blocking rule**: FAIL or ERROR severity at this stage halts the pipeline before any resources are loaded.

#### 4. Context Resolution
The context resource file is located and loaded from owner resource paths. Resolution follows the canonical path pattern `{OwnerRoot}/Resources/labels/contexts/`.

**Resolution checks**:
- Context file exists and is readable.
- Context is valid JSON and matches the schema `susankhya.label.context.v1`.
- Context has required fields (`context_key`, `allowed_fields`, `owner_key`).
- Context `owner_key` matches the request `owner_key`.

**Failure mode**: Missing or invalid context produces an ERROR severity diagnostic. The pipeline halts.

#### 5. Template Resolution
The template resource file is located and loaded from owner resource paths. Resolution follows the canonical path pattern `{OwnerRoot}/Resources/labels/templates/`.

**Resolution checks**:
- Template file exists and is readable.
- Template is valid JSON and matches the schema `susankhya.label.template.v1`.
- Template `context_ref.context_key` resolves to the selected context (compatibility check).
- Template `owner_key` matches the request `owner_key` or is delegated via `context_ref.owner_key`.

**Failure mode**: Missing or invalid template produces an ERROR severity diagnostic. The pipeline halts.

#### 6. Rule Resolution
Enabled rules for the selected context/template pair are loaded from owner rule paths. Rules are optional. If no rules exist, the pipeline proceeds without rule evaluation.

**Resolution checks**:
- Rule files exist at expected paths.
- Rules are valid JSON and match the schema `susankhya.label.rule.v1`.
- Rules reference valid field keys, operators, and effect types.
- Rules are within the owner boundary.

**Failure mode**: Invalid rule files produce WARN severity diagnostics. The pipeline does NOT halt for rule errors — rules that fail validation are skipped, and a warning is recorded. See [Label Rule Resource Contract](label-rule-resource-contract.md) for rule validation boundaries.

#### 7. Resolved Label Model
All resolved resources and rule effects are assembled into a single canonical Resolved Label Model (see Part C). This model is the contract boundary between resolution and rendering.

**Checks before model assembly**:
- All field bindings are satisfied between context and template.
- No duplicate field keys exist in the final resolved set.
- Rule effects are applied to the model (visibility, styling, badges, warnings).

**Failure mode**: Field binding mismatches produce FAIL severity. The pipeline halts before model construction.

#### 8. Platform Render Pipeline
The Resolved Label Model is handed to the Platform-owned render pipeline. This stage owns rendering logic — layout calculation, typography, color, spacing, output dimensions, and content arrangement.

**Platform responsibilities**:
- Consume the Resolved Label Model.
- Produce an intermediate render representation (geometry, text runs, images, barcodes).
- Hand the render representation to the selected Output Adapter.

**Platform does not own**:
- Resource resolution (completed in stages 3-6).
- Business data (provided by owner in the request).
- Output-specific formatting (delegated to adapter).

#### 9. Output Adapter
The final stage converts the render representation into a specific output format. Each adapter is Platform-owned and maps to an output category (see Part E).

**Adapter responsibilities**:
- Accept the render representation from the pipeline.
- Convert to the target format (HTML, PDF, PNG, SVG, ZPL, etc.).
- Return the output artifact (string, binary, stream, or file reference).
- Report output-specific errors.

**Failure mode**: Adapter errors produce ERROR severity. The output is not delivered. The owner may retry with a different adapter or inspect adapter diagnostics.

---

## Part B — Ownership Model

### Owner Owns

| Resource | Description |
|---|---|
| `contexts` | Business purpose declarations, field definitions, data source boundaries |
| `templates` | Layout definitions, field-to-template bindings, visual structure |
| `rules` | Presentation conditions, visibility logic, styling overrides |
| business data | Runtime data payload provided at render time |
| runtime invocation decision | When and why a label is rendered |
| output selection request | Which output target (print, display, export) and adapter to use |

Owner resources are stored in owner-scoped paths under `{OwnerRoot}/Resources/labels/`. The owner is responsible for resource correctness — Platform validates but does not create or modify owner resources.

### Platform Owns

| Component | Description |
|---|---|
| render pipeline | The canonical chain (stages 3-9) that consumes validated resources |
| resolved model construction | Assembly of context, template, rules, and data into Resolved Label Model |
| output adapters | Format-specific converters (HTML, PDF, PNG, SVG, ZPL, thermal) |
| renderer implementations | Layout engine, typography system, geometry calculation |
| future output engines | Additional render backends as needed |

Platform components are shared infrastructure. No app, module, or plugin owns or modifies the render pipeline at runtime.

### Studio Owns

| Activity | Description |
|---|---|
| authoring | Context, template, and rule creation in Label Designer |
| preview | Read-only HTML preview of label resources with sample data |
| validation | Pre-authoring validation of resource correctness |
| migration | Single-resource metadata migration for legacy resources |
| snapshot workflows | Snapshot-before-write governance for all authoring actions |

Studio **never** becomes a runtime renderer, output engine, or print pipeline. Studio preview is an authoring aid, not a production render target.

### Core Owns

| Function | Description |
|---|---|
| governance | System-level rules for resource ownership, lifecycle, and conformance |
| ACL | Access control for label resource read/write/execute permissions |
| contracts | Architecture contracts that define boundaries between owners |
| audit | Logging of label resource changes and render attempts |
| path safety | Validation that resource paths stay within owner boundaries |
| resource validation | Structural validation of resource JSON schemas |

---

## Part C — Resolved Label Model

The Resolved Label Model is the canonical runtime object produced by pipeline stages 3-7 and consumed by stage 8 (Platform Render Pipeline). Both the Studio Preview Renderer and the future Platform Render Pipeline should conceptually consume the same model shape.

**This section defines the contract shape. No implementation is authorized.**

```text
ResolvedLabelModel
{
    owner_key: string
    context: ContextSnapshot
    template: TemplateSnapshot
    rules: RuleSnapshot[]
    data: DataPayload
    resolved_blocks: Block[]
    diagnostics: Diagnostic[]
}
```

### Owner Key
The owning app, module, or plugin that owns the label resources. Must match the `owner_key` from the request and all resolved resources.

### Context Snapshot
A read-only copy of the resolved context resource at render time:
- `context_key`
- `schema`
- `allowed_fields` (field definitions with keys, labels, types)
- `data_source_boundary` (informational — not used at render time)
- `owner_key`

### Template Snapshot
A read-only copy of the resolved template resource at render time:
- `template_key`
- `schema`
- `size` (width, height, unit, orientation)
- `layout` (block definitions, field bindings, roles)
- `context_ref` (linking back to the resolved context)
- `owner_key`

### Rule Snapshot
Resolved rules that passed validation:
- `rule_key`
- `condition` (the resolved condition expression)
- `effects` (resolved effect array)
- `validation_status` (PASS, WARN, or SKIPPED)

Rules that fail validation are excluded from the snapshot. A WARN diagnostic is recorded.

### Data Payload
The owner-provided data for field substitution at render time:
- Flat key-value map
- Keys must match `allowed_fields` from the context
- Values must match field types from the context
- Unrecognized keys are ignored (with WARN diagnostic)

### Resolved Blocks
The rendered layout blocks with field data and rule effects applied:
- Each block has a `role` (title, field_row, barcode, image, text_block, owner_signoff)
- Each block has `resolved_fields` containing field key, label, value, and type
- Each block has `rule_effects` applied (visibility, styling, badges, warnings)
- Each block has computed `render_properties` (position, size, styling)

### Diagnostics
The cumulative diagnostic set from all pipeline stages:
- Array of Diagnostic objects
- Each diagnostic: `stage`, `severity` (PASS/WARN/FAIL/ERROR), `code`, `message`, `field` (optional)
- Diagnostics flow through the entire pipeline and are recorded on the model

---

## Part D — Render Stages

Each stage in the canonical chain is expanded here with required checks and behavior.

### Stage 1: Validate Request

**Purpose**: Reject malformed or incomplete requests before any resources are touched.

**Required checks**:
- `owner_key` is present and non-empty.
- `context_key` is present and non-empty.
- `template_key` is present and non-empty.
- `data_payload` is present and is an array/object.
- Output target (if provided) is a recognized category from Part E.
- No forbidden fields or unexpected top-level keys.

**Halting**: FAIL or ERROR at this stage halts the pipeline. No resources are loaded.

### Stage 2: Resolve Resources

**Purpose**: Load context, template, and rule files from owner resource paths.

**Required checks**:
- Context file exists at `{OwnerRoot}/Resources/labels/contexts/`.
- Template file exists at `{OwnerRoot}/Resources/labels/templates/`.
- Rule files exist at `{OwnerRoot}/Resources/labels/rules/` (if rules_filter is provided).
- All files are readable JSON and match their schema versions.

**Halting**: Missing context or template file halts the pipeline. Missing rule files produce WARN and proceed.

### Stage 3: Validate Resources

**Purpose**: Verify structural validity of each loaded resource.

**Required checks**:
- Context schema is `susankhya.label.context.v1`.
- Template schema is `susankhya.label.template.v1`.
- Rule schema (if present) is `susankhya.label.rule.v1`.
- Context has `allowed_fields` with required field metadata.
- Template layout blocks reference valid field keys from context.
- Template `context_ref.context_key` matches the requested context.
- Owner keys on all resources match the request `owner_key` or are delegated.

**Halting**: Context or template validation FAIL/ERROR halts the pipeline. Rule validation FAIL produces WARN and rule exclusion.

### Stage 4: Load Owner Data

**Purpose**: Make the owner-provided data payload available for field substitution.

**Required checks**:
- All `data_payload` keys match `allowed_fields` in the context.
- Data values match declared types (string, number, date, barcode, image, etc.).
- Required fields are present (fields marked `required: true`).
- Unrecognized keys produce WARN diagnostics.

**Data policy**: See Part H. Phase 1 accepts `data_payload` as-is. No data provider contract is required.

**Halting**: Missing required fields or type mismatches produce FAIL. Pipeline halts.

### Stage 5: Apply Rules

**Purpose**: Evaluate enabled rules against the data and apply effects to the model.

**Required checks**:
- Rule conditions are evaluated in declared order.
- Rule effects are applied to matching layout blocks or fields.
- Visibility rules produce `hidden` render property.
- Style rules produce `style_overrides` on the target block/field.
- Badge rules produce `badges` array on the target.
- Warning rules produce `warnings` array on the target.

**Rule interaction**: See Part G for detailed rules on what rules may and may not do.

**Halting**: Rule evaluation errors produce WARN severity. The rule is skipped. Pipeline does NOT halt for rule errors.

### Stage 6: Build Resolved Model

**Purpose**: Assemble all resolved resources, rule effects, and data into the canonical Resolved Label Model.

**Required checks**:
- All field bindings are satisfied (context fields → template layout → data values).
- No duplicate resolved blocks.
- No empty required blocks.
- Final diagnostics are aggregated from all stages.

**Halting**: Field binding failures produce FAIL. Pipeline halts before render.

### Stage 7: Hand Off to Output Adapter

**Purpose**: Pass the Resolved Label Model to the Platform Render Pipeline for adapter-specific output.

**Required checks**:
- Output target is specified (or defaults to `html_preview`).
- Adapter for the target is available.
- Render pipeline accepts the model and produces output.

**Halting**: Missing or unavailable adapter produces ERROR. Output is not delivered.

---

## Part E — Output Adapter Contract

Output adapters are Platform-owned format converters that transform a render representation into a specific output format. This section defines the contract boundary only — no implementation, no engine selection logic, no output generation.

### Future Adapter Categories

| Category | Description | Owner |
|---|---|---|
| `html_preview` | HTML document for browser preview (Studio, Shell, embedded viewers) | Platform |
| `browser_print` | HTML+CSS print layout for browser-native printing | Platform |
| `pdf` | Portable Document Format output | Platform |
| `png` | Raster image output (pixel-based) | Platform |
| `svg` | Vector image output (resolution-independent) | Platform |
| `zpl` | Zebra Programming Language for thermal/label printers | Platform |
| `thermal` | Generic thermal printer protocol (EPL, CPCL, ESC/POS subsets) | Platform |

### Adapter Contract

Each adapter must satisfy:

1. **Input**: Receives the render representation from the Platform Render Pipeline (not the raw Resolved Label Model).
2. **Output**: Returns the output artifact as a string, binary blob, stream, or file reference.
3. **Diagnostics**: Reports adapter-specific errors with ERROR severity. May report WARN for degraded output (font substitution, image fallback, size adjustments).
4. **Stateless**: Adapters are stateless — no cached state, no connection pooling, no session data.
5. **No side effects**: Adapters must not modify application state, write to DB, send network requests, or trigger workflow actions.
6. **No business logic**: Adapters must not interpret business data, apply domain rules, or make owner decisions.

---

## Part F — Error Handling

### Severity Model

| Severity | Meaning | Pipeline halts? |
|---|---|---|
| `PASS` | All validation rules satisfied. | No |
| `WARN` | Non-blocking issue. Output may be degraded. | No |
| `FAIL` | One or more blocking rules violated. | Yes |
| `ERROR` | Resource or infrastructure failure. Cannot proceed. | Yes |

### Error Classification

| Category | Source | Severity | Halts? |
|---|---|---|---|
| **Resource errors** | Missing files, unreadable files, invalid JSON | ERROR | Yes |
| **Validation errors** | Missing fields, schema mismatch, owner mismatch | FAIL | Yes |
| **Rule errors** | Invalid rule file, unparseable condition, unknown effect | WARN | No (rule skipped) |
| **Data binding errors** | Missing required fields, type mismatch, key mismatch | FAIL | Yes |
| **Render-blocking errors** | Adapter unavailable, render pipeline failure, output failure | ERROR | Yes |
| **Adapter errors** | Adapter-specific failure (format unsupported, conversion failed) | ERROR | Yes |

### Stage-Level Halting Rules

| Stage | Halts on | Proceeds on |
|---|---|---|
| Validate Request | FAIL, ERROR | PASS, WARN |
| Resolve Resources | ERROR (context/template missing) | WARN (rule missing) |
| Validate Resources | FAIL, ERROR (context/template) | WARN (rule) |
| Load Owner Data | FAIL | PASS, WARN |
| Apply Rules | Never | Any (rule errors = WARN) |
| Build Resolved Model | FAIL | PASS, WARN |
| Hand Off to Adapter | ERROR | PASS, WARN |

---

## Part G — Rule Interaction

Rules affect the resolved label model at presentation time only. Rule evaluation occurs before final model generation in Stage 5 (Apply Rules).

### Rules Affect

| Effect | Description |
|---|---|
| `visibility` | Hide or show blocks/fields based on data conditions |
| `styling` | Override CSS tokens, colors, fonts, sizes on target elements |
| `badges` | Add informational badges (text labels with status colors) to blocks or fields |
| `warnings` | Add warning messages to blocks or fields (displayed as alert banners) |
| `conditional sections` | Show/hide entire layout sections based on data conditions |

### Rules Do NOT

| Action | Reason |
|---|---|
| Query database | Rules are presentation-only. Data access is owner responsibility via `data_payload` |
| Modify data | Rules must not alter, transform, or recalculate data values |
| Call services | Rules must not invoke external services or system functions |
| Perform workflow actions | Rules must not trigger print, export, email, or any side effect |
| Change ownership | Rules must not reassign or delegate label resource ownership |
| Change permissions | Rules must not grant, revoke, or modify access permissions |
| Access runtime state | Rules must not read session, request, or application state |

### Rule Resolution Order

Rules are evaluated in the order they are declared in the resource's rules array. Rule order matters — later rules may override or compound effects of earlier rules on the same target. No parallel or unordered evaluation is permitted at the architecture level.

### Rule Lifecycle in Pipeline

```text
Template loading → Rule file discovery → Rule validation → Rule evaluation in order
    → Effect collection → Model application → Resolved Label Model
```

Rules are resolved once per render request. Rule outcomes are snapshotted into the Resolved Label Model's `RuleSnapshot` array.

---

## Part H — Data Policy

Data policy aligns with the [Label Runtime Contract](label-runtime-contract.md) (Section 6).

### Phase 1: Owner Provides Data Payload

The owner includes a flat `data_payload` key-value map in the `LabelRuntimeRequest`. The pipeline validates keys against `allowed_fields` from the resolved context and satisfies type constraints. No data provider contract exists in Phase 1.

**Phase 1 rules**:
- `data_payload` is required and must be non-empty.
- Keys must exist in `allowed_fields`.
- Values must match declared field types.
- Owner is responsible for data correctness and completeness.

### Phase 2 (Future): Owner Data Provider Contract

A future `DataProvider` contract may define how owners register data sources that the render pipeline can query at render time. This contract is not defined, authorized, or implemented in the current architecture.

**Phase 2 extension points** (documented, not authorized):
- `DataProvider` resource type under `{OwnerRoot}/Resources/labels/providers/`.
- Data provider registration with schema, query capability, and runtime binding.
- Pipeline integration point between Stage 3 (Validate Resources) and Stage 4 (Load Owner Data).

### Data Policy Exclusions

The render pipeline:
- Must not cache or store `data_payload` values beyond the render request.
- Must not persist, log, or transmit data payload values to external systems.
- Must not interpret data payload values as business logic instructions.
- Must not derive permissions or access control from data payload content.

---

## Part I — Studio Relationship

### Preview Renderer vs. Render Pipeline

The Studio Label Designer Preview Renderer and the future Platform Render Pipeline are related but distinct systems.

| Aspect | Preview Renderer | Render Pipeline |
|---|---|---|
| Owner | Studio | Platform |
| Purpose | Authoring validation aid | Production label output |
| Input | Context + template + sample data | Resolved Label Model |
| Output | HTML preview (browser display) | Multiple adapter targets |
| Data | In-memory sample data only | Owner-provided `data_payload` |
| Validation | Full validation per label contracts | Full validation per label contracts |
| Rules | Rule sandbox (in-memory) | Rule resolution (file-based) |
| Output targets | HTML only | HTML, PDF, PNG, SVG, ZPL, thermal |
| Persistence | None | Output artifact delivered |
| Authority | Read-only governance | Authorized consumption |

### Shared Contract

Both systems should conceptually consume the same Resolved Label Model shape (Part C). The Preview Renderer constructs a simplified version using sample data, while the Render Pipeline constructs the production version using owner-provided data. The shared contract ensures that preview behavior matches production behavior.

### Studio Must Not

- Replace the Render Pipeline.
- Become a runtime renderer.
- Produce production output (PDF, PNG, ZPL, thermal, browser print).
- Accept production data payloads.
- Bypass Platform render governance.

---

## Part J — Explicit Non-Goals

This contract defines the architecture for a future render pipeline. The following are explicitly out of scope and not authorized:

```text
No renderer implementation.
No output engine implementation.
No printer integration.
No QR generation.
No barcode generation.
No runtime routes.
No runtime APIs.
No scheduling.
No queueing.
No provider implementation.
No data provider contract.
No PDF, PNG, SVG, ZPL, or thermal output generation.
No platform render infrastructure.
No owner runtime invocation code.
No print workflow integration.
No label stock or printer management.
No output caching or retry logic.
No audit event schemas for render actions.
No permissions integration for render access.
No Shell or admin surface render targets.
```

This contract is a prerequisite for future implementation planning. It is not an implementation grant.

---

## Part K — Cross-References

This contract is part of the Label Designer architecture family. It is consistent with and extends the following sibling contracts:

- `docs/architecture/label-runtime-contract.md` — Defines the `LabelRuntimeRequest` shape, runtime chain, ownership rules, data access policy, and output target identifiers consumed by this pipeline.
- `docs/architecture/label-validation-contract.md` — Defines validation rules, severity model, diagnostics, and render-blocking conditions used by pipeline stages 1, 3, and 5.
- `docs/architecture/label-resource-contract.md` — Defines owner resource paths, canonical fields, and the resource family that this pipeline consumes.
- `docs/architecture/label-rule-resource-contract.md` — Defines rule resource architecture, validation, and runtime placement used by pipeline Stage 5 (Apply Rules).
- `docs/architecture/label-resource-metadata-contract.md` — Defines metadata-first ownership analysis and migration for legacy resources consumed by this pipeline.
- `docs/architecture/label-designer-operating-contract.md` — Defines Studio ownership, tool registration, and the relationship between authoring (Studio) and production (Platform).
- `docs/architecture/label-designer-apply-snapshot-safety-contract.md` — Defines the guarded create lifecycle that produces owner resources consumed by this pipeline.

The following contracts have been updated to reference this document:

- `label-runtime-contract.md` — Section 9 (Relationship To Existing Contracts) and Section 11 (Recommended Next Contract).
- `label-validation-contract.md` — Section 10 (Relationship To Existing Contracts).
- `label-resource-contract.md` — Section 7 (Future Apply Boundary).
- `label-rule-resource-contract.md` — Section 16 (Relationship To Existing Contracts).
- `label-resource-metadata-contract.md` — Cross-References section.
- `label-designer-operating-contract.md` — Section 6 (Relationship With Existing Pipeline) and Section 14 (Next Contract Sequence).
- `label-designer-apply-snapshot-safety-contract.md` — Section 11 (Relationship To Label Validation Contract).
