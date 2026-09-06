# Label Designer Operating Contract

Status: Architecture contract baseline with guarded context-create plus disabled/read-only template-preview foundation. Runtime print/export behavior, template/rule writes, migrations, Core edits, and business-runtime coupling remain unauthorized.

Label Designer is a Studio-owned governed tool area for label template authoring workflows. It is a builder/editor, not a runtime source of truth for label definitions. Labels are **resources** owned by apps, modules, and plugins.

## 1. Label Ownership Model

| Layer | Owns |
|---|---|
| **App/Module/Plugin** | Label context, template, rules, and meaning: business purpose, allowed fields, data source boundary, print/use location, template file, rule file, print trigger, and runtime label behavior |
| **Platform** | Shared render/export/print pipeline: label rendering engine, print dispatch, export infrastructure |
| **Core** | Governance only: permissions, path-safety, contracts, and authorization rules for label resource access |
| **Shell** | Runtime consumption: operator cards, display panels, or dashboard blocks that reference or embed label data |
| **Studio (Label Designer)** | Editing workflow only: draft templates, previews, diffs, validation, change records, handover proposals |

### 1.1 The owner-wins rule

The app/module that owns a label's business domain also owns its meaning:

```
Module A declares label context "manufacturing.pallet"
  → Module A owns: label context, allowed fields, template file, rule file, print entry point, runtime trigger
  → Platform owns: the label rendering engine, print pipeline, export format converter
  → Studio (Label Designer) may propose a new field layout or template
  → Only Module A (or its owning app) can approve and integrate the change
```

### 1.2 What Label Designer owns

Label Designer owns only:

- **Drafts**: in-progress label templates that are not yet approved by the owner
- **Previews**: rendered label views using sample data for validation
- **Diffs**: structural comparison between current owner-owned label and proposed draft
- **Validation results**: schema compliance, field existence, path safety, lifecycle consistency
- **Change records**: documented proposals describing what changed and what approval is needed
- **Handover records**: provenance trail from draft through approval to owner artifact integration

### 1.3 What Label Designer must not own

Label Designer must not:

- Become runtime label definition truth
- Become the source of truth for label declarations
- Grant or bypass label permissions
- Deploy labels to production without owner approval
- Directly write to owner label context/template/rule files as part of normal operation
- Create hidden source-of-truth stores for label definitions
- Bypass the Resolved Runtime Contract pipeline
- Own live business logic semantics for any label

## 2. Label Is a Resource, Not a Surface

Labels are **resources** — structured metadata objects owned by apps/modules. They are not surfaces.

| This | Not this |
|---|---|
| Label is a resource type | Label is a surface (like Topbar, Navigation, Workspace, Overlay) |
| Labels are declared in owner resource paths | Labels are UI components in Shell |
| Labels are consumed by surfaces | Labels are themselves surfaces |

Implication: Labels do not need their own contribution type. Existing contribution types (navigation, dashboard cards, operator widgets, display panels) can reference label resources by label context key. The Surface Contribution Contract does not need a new `label_contributions` type.

```
App/module declares label context resource
  → Navigation entry links to label print view
  → Dashboard card renders label preview
  → Operator widget triggers label print
  → Display panel shows label data
  → All via existing contribution types referencing the label_context
```

## 3. Owner Resource Paths

Labels follow a standard artifact layout under each owner's root:

| Path | Purpose |
|---|---|
| `{OwnerRoot}/Resources/labels/contexts/` | Label context declarations: purpose, owner, allowed fields, data source boundary, print/use location |
| `{OwnerRoot}/Resources/labels/templates/` | Label template files: layout, field positions, styling, QR/bar code placement |
| `{OwnerRoot}/Resources/labels/rules/` | Label rule files: validation, field constraints, print constraints, and owner-specific label policy |

Examples:

```
apps/Manufacturing/Resources/labels/contexts/manufacturing.pallet.php
apps/Manufacturing/Resources/labels/contexts/manufacturing.case.php
apps/Inventory/Resources/labels/contexts/inventory.bin_location.php
apps/LazyPOS/Resources/labels/contexts/lazypos.product_price.php
apps/Manufacturing/Resources/labels/rules/manufacturing.pallet.php
```

## 4. Label Resource Contracts

Detailed resource contract shapes are defined in:

- `docs/architecture/label-resource-contract.md`

### 4.1 Label Context Resource

A label context declares:

| Field | Purpose | Owned by |
|---|---|---|
| `context_key` | Unique identifier (e.g., `manufacturing.pallet`) | Owner |
| `owner` | Owning app/module/plugin | Owner |
| `purpose` | Business purpose of the label | Owner |
| `allowed_fields` | Fields that may appear on the label | Owner |
| `rules_ref` | Reference to owner-owned rule file, when the label needs validation or print constraints | Owner |
| `data_source` | Data source boundary for label values | Owner |
| `print_locations` | Where the label is printed or used | Owner |
| `template_ref` | Reference to the template file | Owner |

### 4.2 Label Template Resource

A label template declares the visual layout/design bound to one label context. It owns field placement, label dimensions, QR/barcode/text slots, and presentation blocks, but it must only bind fields allowed by its context.

### 4.3 Label Rule Set Resource

A label rule set declares reusable owner-owned conditional style/business presentation rules that contexts or templates may attach. Rule sets may define conditional visibility, formatting, required presentation warnings, copy/print constraints, and compatibility rules. Rule sets must not mutate business data, grant permissions, trigger printing, or become a workflow engine.

## 5. Standard Studio Workflow Applied to Labels

| Step | Label Designer behavior |
|---|---|
| 1. Load resource | Read label context from owner resource path or active registry |
| 2. Identify owner | Resolve `owner_app` and `owner_module` from label context metadata |
| 3. Analyze current state | Show current context definition: allowed fields, template, data source |
| 4. Validate constraints | Check field existence, path safety, lifecycle consistency, print route validity |
| 5. Propose change | Designer UI edits: add/remove fields, adjust layout, change template |
| 6. Produce diff | Structural diff between current owner label and proposed draft |
| 7. Render preview | Render label with sample data to show visual output |
| 8. Collect approval | Owner (app/module) approves or rejects the proposed change |
| 9. Apply approved change | Write updated context/template to owner resource path |
| 10. Record snapshot | Capture before/after state for rollback |
| 11. Support rollback | Governance records enable revert to previous approved state |
| 12. Emit handover record | Document the change: what changed, who approved, when, which artifact was updated |

### 5.1 Draft lifecycle

```
Start editing
  → Draft is Studio-local (in-memory or session)
  → Draft is NOT runtime truth
  → Draft may be saved for later (Studio-local storage only)
  → Draft is NOT visible to runtime consumers
  → On approval, draft compiles to owner artifact update
  → On rejection, draft is either revised or discarded
```

## 6. Relationship With Existing Pipeline

Labels participate in the Resolved Runtime Contract pipeline:

```
Source Resource
  └─ Owner resource path labels/contexts/ declarations
      └─ Label Registry (compile by owner activation, resolve permissions)
          └─ Resolved Runtime Contract
              └─ Label Render Pipeline (see label-render-pipeline-contract.md)
                  └─ Shell consumption (operator cards, display panels, print triggers)
```

The Label Render Pipeline Contract defines the canonical render chain, Resolved Label Model, output adapters, and error handling for platform consumption of owner-owned label resources.

Label Designer adds a side channel:

```
Studio Label Designer
  └─ Load current label context (from owner resource path)
      └─ Edit (fields, layout, template)
          └─ Draft (Studio-local)
              └─ Validate (schema, paths, lifecycle)
                  └─ Diff (current vs proposed)
                      └─ Preview (render with sample data)
                          └─ Change Record (proposed delta)
                              └─ Owner approval
                                  └─ Handover → owner artifact update
                                      └─ Next compilation picks up updated context
```

The pipeline remains unchanged. Label Designer inserts a governed editing workflow upstream of compilation.

## 7. Risk Classification

Based on the Studio Tool Lifecycle Contract risk levels:

| Label Designer activity | Risk level | Rationale |
|---|---|---|
| View existing label context/template/rule resources | **Read-only** | No change possible |
| Edit field order or layout | **Low** | Affects template display only, no data mutation |
| Add/remove allowed fields | **Low** | Changes available fields on label, no data mutation |
| Change template styling | **Low** | Changes visual appearance only |
| Modify label context metadata | **Medium** | May affect data source or print location configuration |
| Apply changes to owner resource path | **High** | Modifies owner-owned artifact directly |
| Approve and deploy without owner consent | **Critical** | Bypasses ownership — blocked by contract |

## 8. Boundary With Other Tools

| Tool | Boundary |
|---|---|
| **Customization Studio** | Customization Studio owns theme/style editing. Label Designer owns label template field/layout editing. Labels may reference theme tokens, but Label Designer does not edit styles. |
| **Localization Studio** | Localization Studio owns translation files. Label Designer may reference localized strings for label fields, but does not edit translations. |
| **Report Designer** | Report Designer owns report definition editing. Labels are independent resources — a label is not a report. A report may contain label data, but Label Designer does not edit report definitions. |
| **View Editor** | View Editor owns HTML/PHP template editing. Label Designer owns label template files (which are distinct from view templates). |

## 9. Reference Implementation: Manufacturing QR Product Label

The current Label Designer page at `apps/Studio/Tools/LabelDesigner/` contains a read-only preview modeled from the Manufacturing QR Product Label workflow (Part360 Output/Labels and QR product label routes).

This is the **first reference implementation** only. It demonstrates:

- Source surface integration points (`/apps/manufacturing/products/360`, `/qr/product/label`)
- Print and scan route contracts
- Field defaults and order for a QR product label
- Layout blocks (header, detail rows, footer with QR image)
- Ownership guard (Studio owns workbench; app/module owns runtime meaning)

The Manufacturing QR Product Label must remain visible as the reference implementation. Future label types will be added alongside it, not replacing it.

## 10. Non-Goals

Label Designer must not become:

| Trap | Boundary |
|---|---|
| **Hardcoded to Manufacturing** | Labels are generic resources. Manufacturing QR Product Label is one reference implementation. |
| **Hardcoded to QR** | QR encoding is one label technology. Labels may use bar codes, RFID, or plain text. |
| **Hardcoded to Part360** | Part360 is one integration surface. Label contexts come from any app/module. |
| **Hardcoded to LazyPOS** | LazyPOS product labels are another future label type, not the sole purpose. |
| **Direct DB editor** | No raw SQL or direct DB mutation. Designer works with declared label context fields. |
| **Print runtime** | Label Designer does not execute print commands. Print is triggered by the owning app/module via Platform infrastructure. |
| **Permission bypass** | Designer cannot create labels exposing data the viewer is not authorized to see. All label permissions are owned by the declaring module. |
| **Runtime dependency** | The system runs normally when Label Designer is not installed. Designer-created labels become runtime artifacts only after owner approval and handover. |
| **Definition source of truth** | Owner resource paths remain source of truth. Designer drafts are proposals until accepted. |

## 11. Existing Implementation Status

The current Label Designer at `apps/Studio/Tools/LabelDesigner/` is a **guarded context-create implementation**:

| Artifact | Status |
|---|---|
| Tool registration in Studio manifest | ✅ Routes registered `/apps/studio/tools/label-designer` and guarded context create POST |
| Tool policy: `'label_designer' => 'enabled'` | ✅ Declared in `studio_tool_policy.php` |
| Tool default instance policy | ⚠️ `'label_designer' => 'disabled'` (default off) |
| Preview view implementation | ✅ Guarded context-create flow with JSON preview and confirmation |
| Template creation preview foundation | ✅ Disabled/read-only flow: select existing context, choose label size, select context fields, preview illustrative template JSON; no write |
| Generic label resource architecture | ✅ Documented inline and in this contract |
| Label resource contract | ✅ Documented in `docs/architecture/label-resource-contract.md` |
| Read-only owner resource discovery | ✅ Lists existing owner label resource files if present; shows empty state when none exist |
| Read-only candidate DB source discovery | ✅ Lists owner-scoped candidate tables/views and fields by conservative naming conventions; sources are not approved and no context is created |
| Guarded context creation | ✅ Owner/source/purpose/field selection, JSON preview, confirmation, snapshot-before-write, and owner-path guarded context create |
| Apply/snapshot safety contract | ✅ Defined in `docs/architecture/label-designer-apply-snapshot-safety-contract.md`; documentation and diagnostics only |
| Template apply/snapshot safety contract | ✅ Defined in `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md`; documentation and diagnostics only |
| Reference implementation display | ✅ Manufacturing QR Product Label visible as first reference |
| Multi-locale support (EN/JA/NE) | ✅ All labels, architecture terms, and examples localized |
| Test coverage | ✅ Label Designer boundary architecture gate exists |

The current implementation supports context create and read-only template preview only. Template/rule editing or writing and runtime print/export behavior remain out of scope. Owner-source-of-truth boundaries and apply/snapshot safety contract remain mandatory.

## 12. Cross-Contract Alignment

This contract aligns with:

- **Studio Operating Contract**: `docs/architecture/studio-operating-contract.md` — Label Designer follows the standard Studio identity, workflow, and must-not-own rules
- **Studio Tool Lifecycle Contract**: `docs/architecture/studio-tool-lifecycle-contract.md` — Label Designer is a Studio Tool with governed lifecycle
- **APP-CONTRACT.md**: `docs/architecture/APP-CONTRACT.md` — App/module label ownership boundary is respected
- **Surface Contribution Contract**: `docs/architecture/surface-contribution-contract.md` — Labels are resources consumed by surfaces, not a new contribution type
- **Resolved Runtime Contract Pipeline**: `docs/architecture/resolved-runtime-contract-pipeline.md` — Labels are a resource type in the compilation pipeline
- **Business App/Module Ownership Contract**: `docs/architecture/business-app-module-ownership-contract.md` — Module label declarations are preserved as owner artifacts
- **Label Resource Contract**: `docs/architecture/label-resource-contract.md` — Context/template/rule resource meanings and read-only discovery boundary
- **Label Designer Apply/Snapshot Safety Contract**: `docs/architecture/label-designer-apply-snapshot-safety-contract.md` — required future lifecycle for owner-resource writes: snapshot, diff, validation, confirmation, owner-folder write, diagnostics, and rollback information
- **Label Designer Template Apply/Snapshot Safety Contract**: `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md` — required future lifecycle for guarded template writes under owner template paths
- **Label Validation Contract**: `docs/architecture/label-validation-contract.md` — defines what makes label context/template resources valid before preview, render, print, or runtime use
- **Label Runtime Contract**: `docs/architecture/label-runtime-contract.md` — defines how owner-owned label resources are consumed at runtime

## 13. Validation

Use the current architecture gates:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

This Label Designer Operating Contract is documentation-only. It does not authorize implementation, runtime changes, or route changes.

## 14. Next Contract Sequence

After this contract is accepted, the next document in sequence is:

1. **Label Resource Contract** — canonical schema for compiled label context/template/rule objects, validation rules, and compilation behavior from owner resource paths (P0 prerequisite for implementation)
2. **Label Validation Contract** ✅ — `docs/architecture/label-validation-contract.md` — defines what makes label context/template resources valid before preview, render, print, or runtime use. Covers: context schema rules, template schema rules, field binding rules, context/template compatibility, duplicate key rules, owner boundary rules, label size rules, forbidden runtime assumptions, and diagnostics before future renderer (P1)
3. **Label Runtime Contract** ✅ — `docs/architecture/label-runtime-contract.md` — defines how owner-owned label resources are consumed at runtime. Covers: runtime chain, ownership rules, request shape, validation requirements, data access policy, output targets, rule resource placement, and Studio boundary (P1)
4. **Label Rule Resource Contract** ✅ — `docs/architecture/label-rule-resource-contract.md` — defines the architecture, ownership, validation boundaries, and future runtime position of owner-owned label rules. Covers: rule resource shape, target scopes, conditions, effects, validation rules, runtime placement, and Studio boundary (P2)
5. **Label Resource Metadata Contract** ✅ — `docs/architecture/label-resource-metadata-contract.md` — defines metadata-first ownership analysis, legacy fallback detection, and guarded metadata migration apply for label context/template resources. Covers: metadata requirements, migration expectations, Studio boundary, M001/M002 validation, and cross-references to all sibling contracts (P1)
6. **Label Render Pipeline Contract** ✅ — `docs/architecture/label-render-pipeline-contract.md` — defines the Platform render pipeline, canonical render chain, Resolved Label Model, ownership model, output adapter categories, error handling, rule interaction, and data policy. This contract does not authorize runtime implementation. (P1)
7. **Label Designer Phase 1 Runtime Proof Plan** ✅ — `docs/architecture/label-designer-phase1-runtime-proof-plan.md` — planning-only document scoping the smallest safe runtime proof: CLI pipeline, ResolvedLabelModel, 7-stage coordinator, HTML adapter, 26 diagnostics, zero Studio coupling. (P1)

Implementation must not begin until the Label Resource Contract and Phase 1 Runtime Proof Plan are accepted.
