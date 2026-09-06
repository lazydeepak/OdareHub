# Label Runtime Contract

Status: Architecture contract baseline. This contract defines how owner-owned Label Context, Label Template, and future Label Rule resources are consumed at runtime by apps, modules, plugins, and the Platform render pipeline. It does not authorize implementation of a renderer, print pipeline, QR generation, barcode generation, PDF/PNG/ZPL/HTML output engines, data providers, runtime routes, or any production label output.

The canonical request, validation, rule-evaluation, failure, and audit handoff for future implementation is defined in `docs/architecture/label-runtime-handoff-contract.md`. Where this older baseline uses different request fields or output-target names, the handoff contract governs future implementation. Neither document authorizes runtime code.

---

## 1. Runtime Chain

Label resources are consumed at runtime through a well-defined chain. Every link has a single owner. No link may be skipped, bypassed, or substituted by a different layer.

### 1.1 Canonical Chain

```text
Owner runtime action
  ↓  (business logic decides: print this label for this business object)
Context resource
  ↓  (resolves: which business purpose, which allowed fields, which data source)
Template resource
  ↓  (resolves: which layout, which field bindings, which size)
Optional rule resources (future)
  ↓  (applies presentation/conditional adjustments within owner constraints)
Owner-provided data
  ↓  (field values resolved at runtime by the owner's business logic)
Platform render pipeline
  ↓  (transforms resolved template + data into output bytes)
Output target
```

### 1.2 Load Protocol

Each resource file is loaded from the owner's filesystem at `{OwnerRoot}/Resources/labels/`:

| Step | Resource | Resolution path |
|---|---|---|
| 1 | Context | `{OwnerRoot}/Resources/labels/contexts/{context_key}.label-context.json` |
| 2 | Template | `{OwnerRoot}/Resources/labels/templates/{template_key}.json` |
| 3 | Rules (future) | `{OwnerRoot}/Resources/labels/rules/{rules_key}.json` |

No runtime may load resources from outside the resolved owner root. No runtime may load resources from Studio storage, snapshot paths, or draft directories.

### 1.3 Chain Invariant

Every render or print request must satisfy:

```text
owner path + context_key + template_key + data → output
```

No render request may omit the owner path. No render request may use a context or template that does not belong to the requesting owner.

---

## 2. Ownership Rules

### 2.1 Owner App/Module/Plugin Owns

- Label Context resources — business purpose, field declarations, data source boundary.
- Label Template resources — layout, block structure, field bindings.
- Label Rule resources (future) — presentation rules, conditional behavior.
- Business data — runtime field values for each label instance.
- Runtime invocation — the decision to print or render a label.
- Print/use decision — which label context/template applies to which business object.
- Print trigger — the route, action, or event that initiates label output.

The owner is the sole authority on what business object triggers which label and with what data. No other layer may override this decision.

### 2.2 Platform Owns

- Shared render pipeline — the abstracted sequence from resolved template + data to output bytes.
- Output abstraction — engine-neutral render interface that output targets implement.
- Common renderer services — barcode/QR encoding, image embedding, font resolution, layout composition.
- Future output engines — PDF, PNG/SVG, ZPL, thermal printer, browser print adapters.
- Render pipeline configuration — DPI, page size, margins, default fonts, output format selection.

Platform render services must be stateless, idempotent, and owner-agnostic. They receive a fully resolved render request and produce output. They must not load owner resources, resolve owner data, or make business decisions.

### 2.3 Studio Owns

- Authoring UI — context/template/rule creation and editing workflows.
- Preview UI — read-only visual preview within Studio boundaries.
- Validation workflow — static analysis per the Label Validation Contract.
- Snapshot/apply workflow — guarded owner resource writes with rollback evidence.

Studio must never become runtime owner. Studio must never serve label render requests, provide label data, or participate in print execution.

### 2.4 Core Owns

- Governance — which owners may host label resources.
- ACL — who may invoke print actions, who may author labels, who may view rendered output.
- Audit — log of print/use events for compliance.
- Contract enforcement — validation of the runtime chain, path safety, and owner boundaries.
- Path safety — prevention of traversal, cross-owner resource loading, and arbitrary file reads.

Core governance is enforced at every entry point into the runtime chain. No validation step may be skipped by a route handler, API endpoint, or service layer.

---

## 3. Runtime Request Shape

### 3.1 LabelRuntimeRequest Definition

A future runtime request object (PHP class, JSON payload, or service DTO) must carry these fields:

| Field | Type | Required | Constraint |
|---|---|---|---|
| `owner_key` | string | Yes | Must resolve to a lifecycle-eligible owner. Must match the owner of the context and template. |
| `context_key` | string | Yes | Must match a valid context file under the owner. |
| `template_key` | string | Yes | Must match a valid template file under the owner. |
| `data_payload` | object or array | Yes | Field key → value map. Must contain all fields required by the template. |
| `output_target` | string | Yes | One of the known output target identifiers (Section 5). |
| `options` | object | No | Renderer options: copies, DPI override, label_stock, orientation override, metadata. |

### 3.2 Required Fields

`owner_key`, `context_key`, `template_key`, `data_payload`, and `output_target` are always required. A request missing any of these fields must be rejected before resource loading begins.

### 3.3 Optional Fields

| Field | Default | Behavior |
|---|---|---|
| `options.copies` | 1 | Number of physical copies. Capped by renderer or owner constraint. |
| `options.dpi` | Renderer default | Override output resolution. |
| `options.orientation` | Template default | Override layout orientation if renderer supports it. |
| `options.metadata` | null | Optional correlation id, request origin, audit context. |

### 3.4 Forbidden Fields

A runtime request must not contain:

- Filesystem paths to owner resources (paths are derived from `owner_key` + `context_key` + `template_key`, never provided directly).
- SQL queries or data source credentials (data comes from `data_payload`, not from DB).
- Studio snapshot identifiers or draft identifiers (runtime consumes only published owner resources).
- Raw binary data for barcode/QR images (encoding is a Platform renderer concern).
- Permissions overrides (ACL is enforced by Core, not the request).

### 3.5 Owner Boundary Requirement

The resolved owner root for `owner_key` must match the owner root that contains the resolved context and template files. A request with `owner_key = "Manufacturing/Products"` must use a context and template from `apps/Manufacturing/modules/Products/Resources/labels/`. A mismatch is a cross-owner violation and must be rejected.

### 3.6 Context/Template Compatibility Requirement

The resolved template's `context_ref.context_key` must match the resolved context's `context_key`. A request using `template_key = "manufacturing.product.label.100x50_mm"` whose context ref points to `manufacturing.product.label` must use `context_key = "manufacturing.product.label"`. Any mismatch is a compatibility violation and must be rejected.

---

## 4. Runtime Validation Requirements

### 4.1 Pre-Render Validation Checklist

Before any resource file is loaded or any output is generated, runtime validation must verify:

| # | Check | When it fails |
|---|---|---|
| 1 | `owner_key` resolves to a known owner. | FAIL |
| 2 | Owner is label-lifecycle eligible (per lifecycle classification). | FAIL |
| 3 | Context file exists at resolved path. | FAIL |
| 4 | Template file exists at resolved path. | FAIL |
| 5 | Context belongs to the same owner as the request `owner_key`. | FAIL |
| 6 | Template belongs to the same owner as the request `owner_key`. | FAIL |
| 7 | Context `context_key` matches template `context_ref.context_key`. | FAIL |
| 8 | Template `fields[*].field_key` are all within context `allowed_fields[*].field_key`. | FAIL |
| 9 | Label size is valid per Section 7 of the Label Validation Contract. | WARN (renderer may reject at output stage if unsupported) |
| 10 | No unresolved required bindings — every template field has a corresponding entry in `data_payload`. | FAIL |
| 11 | No Studio-only preview flags are present in the resource files (e.g. `write_status: "draft"` that has not been approved, `notes` containing `preview_only`). | WARN or FAIL per owner policy |
| 12 | No cross-owner resource usage — context, template, and rules (future) all resolve under the same owner root. | FAIL |
| 13 | No path traversal — `context_key` and `template_key` do not contain `..`, `/`, or null bytes. | FAIL |
| 14 | No arbitrary file loading — file paths are derived from owner root + known subdirectory + validated key, never from user-provided path strings. | FAIL |

### 4.2 Validation Flow

```text
receive request
  → validate request shape (all required fields present)
  → resolve owner root from owner_key
  → resolve context path from owner root + context_key
  → resolve template path from owner root + template_key
  → validate owner boundary (request owner == file owner)
  → validate context file (parse, schema check)
  → validate template file (parse, schema check)
  → validate context/template compatibility
  → validate field bindings
  → validate data_payload completeness
  → check forbidden flags
  → check path safety
  → report PASS/WARN/FAIL/ERROR
```

### 4.3 Severity Classification

| Severity | Meaning | Blocks rendering? |
|---|---|---|
| `PASS` | All checks pass. | No |
| `WARN` | Non-blocking issue. Label may render with degraded or warned behavior. | No |
| `FAIL` | One or more blocking rules violated. | Yes |
| `ERROR` | Unparseable resource, missing required file, or invalid request structure. | Yes |

### 4.4 Render-Blocking Failures

The following are always render-blocking:

- Invalid or missing `owner_key`, `context_key`, `template_key`, or `data_payload`.
- Owner not found or not lifecycle-eligible.
- Context or template file does not exist.
- Cross-owner resource usage.
- Context/template key mismatch.
- Template field references a field not allowed by the context.
- Data payload missing a required template field.
- Path traversal detected.
- Owner root resolution fails.

### 4.5 Validation Result Shape

Every validation run must produce a result with:

| Field | Meaning |
|---|---|
| `owner_key` | Resolved owner |
| `context_key` | Requested context key |
| `template_key` | Requested template key |
| `severity` | Highest severity across all checks |
| `checks` | Array of individual check results: `{check_id, status, message}` |
| `render_allowed` | Boolean: `true` only if no `FAIL` or `ERROR` |
| `timestamp` | Validation timestamp |

---

## 5. Data Access Policy

### 5.1 Phase 1 — Direct Owner Data

In Phase 1, the owner app/module/plugin provides `data_payload` directly. The data payload is a field key → value map resolved by the owner's business logic at the time of the print/use request.

Example flow:

```text
Manufacturing Products module decides to print a label for product ID 42
  → module queries its own DB for product_name, product_code, batch_number, etc.
  → module builds data_payload: {"product_name": "...", "product_code": "...", ...}
  → module calls LabelRuntimeRequest with owner_key, context_key, template_key, data_payload, output_target
  → Platform render pipeline produces the label
```

No universal data provider contract is required in Phase 1. Each owner implements data resolution in its own existing service layer.

### 5.2 Phase 2 — Future Owner Label Data Provider

A future `OwnerLabelDataProviderInterface` (or equivalent contract) may be introduced to standardize data resolution across owners. This contract would:

- Define a `resolve(owner_key, context_key, business_object_id): array` method.
- Allow Platform render services to request data directly for bulk/scheduled operations.
- Centralize field type coercion, formatting, and localization.

Phase 2 is not authorized by this contract. Do not implement data providers now.

### 5.3 Studio DB Discovery Is Not Runtime

Studio's Bootstrap DB Discovery Mode (Phase 1 authoring) scans candidate tables and columns for authoring convenience. This discovery is:

- **Authoring only** — used to select fields and build context `allowed_fields`.
- **Not runtime** — the runtime must not depend on Studio DB discovery metadata.
- **Not a provider** — the discovered source name is a human-readable reference, not a runtime connection string.

Runtime data resolution is the owner's responsibility, not Studio's.

### 5.4 Data Payload Contract

The `data_payload` map must:

- Contain a value for every `field_key` declared in the template's `fields` array.
- Use string values (`number`, `date`, and `boolean` fields must be string-formatted by the owner before submission).
- Not contain binary data (barcode/QR encoding is a Platform render concern).
- Be flat (no nested objects or arrays; each field is a single string).

Future contract amendments may introduce type declarations and formatting rules, but Phase 1 is string-only.

---

## 6. Output Targets

### 6.1 Target Identifiers

Output targets are identified by well-known string constants. Each target maps to a Platform-owned output engine or adapter.

| Identifier | Category | Meaning |
|---|---|---|
| `html_preview` | Screen | Browser-renderable HTML for read-only preview on screen. |
| `pdf` | File | Portable Document Format for download, email, or archival. |
| `png` | Image | Raster image for display, embedding, or preview. |
| `svg` | Vector | Scalable vector graphic for high-quality rendering or editing. |
| `zpl` | Thermal | Zebra Programming Language for thermal transfer / direct thermal printers. |
| `browser_print` | Screen+Print | Print-optimized HTML that triggers the browser native print dialog. |

### 6.2 Target Ownership

All output engines are Platform-owned services. They must:

- Accept a fully resolved render request (resolved template + resolved data + target identifier).
- Produce output bytes in the requested format.
- Be stateless — no side effects, no DB writes, no audit logging (audit is Core's responsibility).
- Be swappable — the render pipeline selects an engine by target identifier; adding a new target must not require changes to owner code.

### 6.3 Engine Contract (Future)

Each output engine must implement a future contract such as:

```php
interface LabelOutputEngine {
    public function render(ResolvedLabel $label, OutputTarget $target): OutputResult;
}
```

This contract is not defined or implemented by this document. The contract's existence is noted here to clarify that output engines are abstracted, not hard-wired.

### 6.4 Output Target Ownership Boundary

- Owner code selects the output target by identifier string.
- Platform provides the engine that handles that identifier.
- No owner code may bypass the Platform render pipeline to call an output engine directly.
- No output engine may load owner resources, resolve owner data, or make business decisions.

---

## 7. Rule Resource Placement

### 7.1 Future Optional Resource

Label Rule sets are future optional owner resources. They are not implemented in this slice. The full rule resource architecture, validation, and runtime placement contract is defined by:

- `docs/architecture/label-rule-resource-contract.md`

This section summarizes the runtime placement only.

### 7.2 Placement In The Runtime Chain

```text
Context + Template + Rules + Data
                      ↑
              Rules adjust presentation
              before Platform render
```

Rules insert between Template resolution and Platform render:

1. Load context.
2. Load template.
3. Load optional rule sets referenced by context or template.
4. Apply rules to the resolved layout/fields.
5. Present the adjusted layout + data to the Platform render pipeline.

### 7.3 Rule Constraints

Rules are owner-owned presentation resources. They must obey:

- No data access — rules cannot query databases, call APIs, or read files outside the `Resources/labels/rules/` directory.
- No side effects — rules cannot write files, send network requests, or mutate application state.
- No permissions bypass — rules cannot grant or deny access to label data or features.
- No business logic — rules cannot change field values, compute new fields, or make decisions about which template to use (template selection is the owner's invocation decision).
- No arbitrary code execution — rules are declarative JSON, not executable scripts.

Rule capabilities are limited to:

- Conditional visibility of template blocks.
- Conditional text formatting or font selection.
- Conditional barcode/QR encoding parameters.
- Owner-approved presentation warnings (e.g., "expiry date is within 7 days").

### 7.4 Rule Lifecycle

Rules follow the same owner resource lifecycle as contexts and templates:

- Authoring in Studio → snapshot → validation → owner folder write → rollback evidence.
- Runtime validation must check that `rules_refs` entries exist and are valid.
- A rule set that fails validation must not block rendering of the template; the template renders without rules applied.

---

## 8. Studio Boundary

### 8.1 Studio May

| Activity | Boundary |
|---|---|
| Preview | Render read-only HTML preview of label context/template within Studio UI. Preview must not trigger print, file download, or external output. |
| Validate | Run static analysis per the Label Validation Contract. Report PASS/WARN/FAIL/ERROR. |
| Author | Create, edit, and manage owner label resources through guarded workflows. |
| Snapshot | Write snapshot evidence before any owner resource write. |
| Apply | Write validated label resources to owner paths after snapshot + confirmation. |
| Diff | Show structural differences between proposed and existing label resources. |
| Diagnose | Run post-write diagnostics on newly created or modified resources. |

### 8.2 Studio Must Not

| Prohibited | Reason |
|---|---|
| Print production labels | Studio is authoring tooling, not a print runtime. Production labels must be initiated by the owning app/module through Platform infrastructure. |
| Execute runtime business workflows | Studio must not trigger business operations (dispatch, packing, shipping) as a side effect of authoring. |
| Become label registry | Studio drafts, snapshots, and diagnostics are governance evidence, not runtime source of truth. Runtime consumes only owner-published resource files. |
| Own runtime data | Studio must not serve as a data provider for label rendering. Data is owned by the app/module that invokes printing. |
| Bypass owner invocation | Studio must not create routes or API endpoints that trigger label output directly without passing through the owning app/module. |
| Call printers directly | Studio must not have any route, service, or configuration that sends bytes to a physical or virtual printer. |
| Generate QR payloads as business truth | Studio may preview QR/bar code placeholders, but runtime QR payload generation is the owner's responsibility. |
| Load runtime resources from snapshot paths | Studio must not reference `storage/studio-snapshots/` paths in any runtime or preview context outside Studio. |

---

## 9. Relationship To Existing Contracts

This contract is referenced by and consistent with:

- `docs/architecture/label-resource-contract.md` — Owner resource ownership, canonical fields, and read-only discovery boundary.
- `docs/architecture/label-validation-contract.md` — Static validation rules for context and template resources before any runtime use.
- `docs/architecture/label-designer-apply-snapshot-safety-contract.md` — Guarded context-create lifecycle that produces owner resources consumed at runtime.
- `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md` — Guarded template-create lifecycle that produces owner resources consumed at runtime.
- `docs/architecture/label-designer-operating-contract.md` — Studio boundary, ownership model, tool registration, and cross-contract alignment.
- `docs/architecture/label-rule-resource-contract.md` — Rule resource architecture, shape, validation, and runtime placement.
- `docs/architecture/label-runtime-handoff-contract.md` — Canonical owner-to-Platform request, validation, rule, failure, and audit handoff.
- `docs/architecture/label-render-pipeline-contract.md` — Defines the Platform render pipeline, Resolved Label Model, output adapters, and render stages that consume the `LabelRuntimeRequest` defined in this contract.

This contract does not authorize any implementation of a renderer, print pipeline, output engine, data provider, runtime route, or production label output. It lays the architectural foundation for those future implementations.

---

## 10. Non-Goals

This contract does not:

- Define the renderer API, `ResolvedLabel` object, or `OutputResult` DTO.
- Authorize implementation of any output engine (PDF, PNG, SVG, ZPL, thermal, browser print).
- Authorize implementation of a data provider contract or data resolution service.
- Define print scheduling, queuing, or retry behavior.
- Define label stock management, printer configuration, or device discovery.
- Define label preview in non-Studio contexts (e.g., Shell, admin, operator surfaces).
- Define cross-owner label sharing, delegation, or multi-tenant label routing.
- Replace the owner's existing print workflows or introduce new print routes.
- Define audit event schemas or compliance reporting for print operations.

---

## 11. Recommended Next Contract

After this contract, the recommended next slice is:

```text
Read-Only Label Preview Renderer
```

A client-side or server-side preview renderer that:

- Accepts a resolved LabelRuntimeRequest (owner_key + context_key + template_key + data_payload).
- Validates per the Label Validation Contract.
- Produces an HTML preview for Studio or Shell surfaces.
- Exists entirely in read-only mode — no print, no file download, no output engine.
- Confirms the runtime chain contracts before print infrastructure is built.

This next slice is the bridge between authoring and runtime: it proves the contract works end-to-end without enabling production output.

Do not skip to print/QR/runtime integration before the preview renderer exists.

After the preview renderer, the recommended next contract is:

- `docs/architecture/label-rule-resource-contract.md` — defines the architecture, validation boundaries, and future runtime position of owner-owned label rules. This contract does not authorize rule implementation, execution, or rendering.

After the rule resource contract, the recommended next contract is:

- `docs/architecture/label-render-pipeline-contract.md` — defines the Platform render pipeline, Resolved Label Model, output adapters, render stages, and error handling. This contract does not authorize implementation of a renderer, print pipeline, or output engine.
