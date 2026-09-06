# Report Designer Operating Contract

Status: Architecture contract baseline. No implementation, runtime behavior change, route change, Core edit, or app/module generation is authorized by this document.

Report Designer is a Studio-owned governed tool area for report authoring workflows. It is a worker/tool, not a runtime source of truth for report definitions.

## 1. Report Ownership Model

Existing ownership from MODULE-CONTRACT.md and APP-CONTRACT.md is preserved unchanged:

| Layer | Owns |
|---|---|
| **App/Module** | Report meaning: definition, layout, filters, parameters, visualization choice, permissions, export semantics, navigation entry |
| **Platform** | Report engine: discovery registry (`ModuleReportRegistryService`), export infrastructure (`ExportHistoryService`, `core_export_runs`), PDF infrastructure (`PdfService`), future scheduled framework, audit framework |
| **Core** | Technical engine only: `PdfService` (Dompdf wrapper) — owns rendering mechanics, not document meaning |
| **Shell** | Runtime consumption: dashboard blocks, operator cards, display panels that reference or embed report views |
| **Studio (Report Designer)** | Editing workflow only: draft reports, previews, diffs, validation, change records, handover proposals |

### 1.1 The smallest-owner-wins rule

The module that owns a report's domain data also owns its meaning:

```
Module A declares report "manufacturing.assembly_entries.execution"
  → Module A owns: columns, filters, parameters, permissions, export format, PDF filename
  → Platform owns: the CSV streaming loop, the PDF converter, the export audit table
  → Report Designer may propose a new filter or column layout
  → Only Module A (or its owning app) can approve and integrate the change
```

### 1.2 What Report Designer owns

Report Designer owns only:

- **Drafts**: in-progress report definitions that are not yet approved by the owner
- **Previews**: rendered report views using sample or filtered data for validation
- **Diffs**: structural comparison between current module-owned report and proposed draft
- **Validation results**: schema compliance, permission validity, lifecycle consistency, parameter rules
- **Change records**: documented proposals describing what changed and what approval is needed
- **Handover records**: provenance trail from draft through approval to owner artifact integration

### 1.3 What Report Designer must not own

Report Designer must not:

- Become runtime report definition truth
- Become the source of truth for report declarations
- Grant or bypass report permissions
- Deploy reports to production without owner approval
- Directly write to module `plugin.json` or schema files as part of normal operation
- Create hidden source-of-truth stores for report definitions
- Bypass the Resolved Runtime Contract pipeline
- Own live business logic semantics for any report

## 2. Report Is a Resource, Not a Surface

Reports are **resources** — structured metadata objects owned by apps/modules. They are not surfaces.

| This | Not this |
|---|---|
| Report is a resource type | Report is a surface (like Topbar, Navigation, Workspace, Overlay) |
| Reports are declared in plugin.json | Reports are UI components in Shell |
| Reports are consumed by surfaces | Reports are themselves surfaces |

Implication: Reports do not need their own contribution type. Existing contribution types (navigation, dashboard cards, operator widgets, display panels) can reference report resources by `report_key`. The Surface Contribution Contract does not need a new `report_contributions` type.

```
App/module declares report resource
  → Navigation entry links to report view
  → Dashboard card renders report KPI summary
  → Operator widget embeds report chart
  → Display panel shows report data
  → All via existing contribution types referencing the report_key
```

## 3. Standard Studio Workflow Applied to Reports

The standard Studio workflow from the Studio Operating Contract applies:

| Step | Report Designer behavior |
|---|---|
| 1. Load resource | Read report declaration from `plugin.json` or active registry (`ModuleReportRegistryService`) |
| 2. Identify owner | Resolve `owner_app` and `owner_module` from report metadata and app manifest |
| 3. Analyze current state | Show current report definition: columns, filters, parameters, permissions, visualization |
| 4. Validate constraints | Check report_key uniqueness, permission validity, lifecycle consistency, parameter schema |
| 5. Propose change | Designer UI edits: add/remove columns, change filters, adjust parameters, select visualization |
| 6. Produce diff | Structural diff between current module report and proposed draft |
| 7. Render preview | Execute report with sample data to show visual output |
| 8. Collect approval | Owner (app/module) approves or rejects the proposed change |
| 9. Apply approved change | Write updated declaration to module `plugin.json` (or equivalent owner artifact) |
| 10. Record snapshot | Capture before/after state for rollback |
| 11. Support rollback | Governance records enable revert to previous approved state |
| 12. Emit handover record | Document the change: what changed, who approved, when, which artifact was updated |

### 3.1 Draft lifecycle

```
Start editing
  → Draft is Studio-local (in-memory or session)
  → Draft is NOT runtime truth
  → Draft may be saved for later (Studio-local storage only)
  → Draft is NOT visible to runtime consumers
  → On approval, draft compiles to owner artifact update
  → On rejection, draft is either revised or discarded
```

## 4. Resource Types Report Designer Works With

Report Designer works with one primary resource type:

**Report resource** — a compiled report definition with this structure:

| Field | Source | Editable by Designer? |
|---|---|---|
| `report_key` | `plugin.json` declaration (immutable) | No — key is the identity |
| `owner` | Declaration (`app`/`module`/`platform`) | No — ownership is fixed |
| `title` | Declaration | Yes — propose new localized title |
| `description` | Declaration | Yes — propose new description |
| `scope` | Declaration | No — domain scope is fixed |
| `permission` | Declaration | No — permission is owned by module |
| `lifecycle` | Declaration | No — lifecycle is module policy |
| `view` | Declaration (template path) | Yes — propose alternative view |
| `export_view` | Declaration | Yes — propose alternative export |
| `parameters` | Declaration array | **Yes** — add, remove, modify parameter definitions |
| `visualization` | Declaration (`table`/`chart`/`kpi`/`mixed`) | **Yes** — change visualization type |
| `export_formats` | Declaration (`csv`/`pdf`/`xlsx`) | Yes — add/remove formats |
| `category` | Declaration | Yes — reclassify |

The editable fields are what Report Designer can propose changes to. Non-editable fields are owned by the declaring module and must not be changed through the designer workflow.

## 5. Relationship With Existing Pipeline

Reports already participate in the Resolved Runtime Contract pipeline:

```
Source Resource
  └─ plugin.json reports[] declarations
      └─ ModuleReportRegistryService.activeReports()
          └─ Compilation (filter by module activation, resolve owner, resolve permissions)
              └─ Resolved Runtime Contract
                  └─ Shell consumption (dashboard blocks, navigation, operator cards)
```

Report Designer adds a side channel:

```
Studio Report Designer
  └─ Load current report resource (from plugin.json or registry)
      └─ Edit (parameters, visualization, columns)
          └─ Draft (Studio-local)
              └─ Validate (schema, permissions, lifecycle)
                  └─ Diff (current vs proposed)
                      └─ Preview (render with sample data)
                          └─ Change Record (proposed delta)
                              └─ Owner approval
                                  └─ Handover → owner artifact update
                                      └─ Next compilation picks up updated declaration
```

The pipeline remains unchanged. Report Designer inserts a governed editing workflow upstream of compilation.

## 6. Risk Classification

Based on the Studio Tool Lifecycle Contract risk levels:

| Report Designer activity | Risk level | Rationale |
|---|---|---|
| View existing report definition | **Read-only** | No change possible |
| Edit parameters (add/remove/modify) | **Low** | Affects report UI only, no data mutation |
| Change visualization type | **Low** | Changes rendering only, no data mutation |
| Change export formats | **Low** | Affects export options only |
| Change columns/layout | **Medium** | May affect data query scope |
| Apply changes to module plugin.json | **High** | Modifies owner-owned artifact directly |
| Approve and deploy without owner consent | **Critical** | Bypasses ownership — blocked by contract |

The designer must gate high-risk and critical actions behind the Studio governance workflow (approval collection, change record, handover record).

## 7. Boundary With Other Tools

| Tool | Boundary |
|---|---|
| **Customization Studio** | Customization Studio owns style/theme editing. Report Designer owns report definition editing. Reports may reference theme tokens, but Report Designer does not edit styles. |
| **Localization Studio** | Localization Studio owns translation files. Report Designer may reference localized strings (`title`, `description`, `parameter.label`), but does not edit translations. |
| **Schema Tool** | Schema Tool owns schema/field editing. Report Designer may reference schema fields as parameter or column sources, but does not edit schema. |
| **View/Layout Editor** | View Editor owns template editing. Report Designer may reference report views, but does not edit HTML/PHP templates directly. |

## 8. Non-Goals

Report Designer must not become:

| Trap | Boundary |
|---|---|
| **BI platform** | No OLAP, data warehouse, or multi-source joins. Reports consume module data through existing query boundaries. |
| **Direct SQL editor** | No raw SQL input. Designer works with declared parameters and domain filters. |
| **Spreadsheet replacement** | No cell-level editing, formula engine, or pivot tables. Reports are structured views. |
| **Permission bypass** | Designer cannot create reports exposing data the viewer is not authorized to see. All report permissions are owned by the declaring module. |
| **Runtime dependency** | The system runs normally when Report Designer is not installed. Designer-created reports become runtime artifacts only after owner approval and handover. |
| **Definition source of truth** | Module `plugin.json` and DB schema remain source of truth. Designer drafts are proposals until accepted. |

## 9. Existing Implementation Status

The current Report Designer at `apps/Studio/Tools/ReportDesigner/` is a **placeholder only**:

| Artifact | Status |
|---|---|
| Tool registration in Studio manifest | ✅ Route registered `/apps/studio/tools/report-designer` |
| Tool policy: `'report_designer' => 'enabled'` | ✅ Declared in `studio_tool_policy.php` |
| Tool default instance policy | ⚠️ `'report_designer' => 'disabled'` (default off) |
| View implementation | ⏳ Placeholder only (5-line metadata array) |
| Description text | ✅ Correct: "Read-only Report Designer workbench surface. Report definitions remain app/module-owned artifacts." |
| Test coverage | ✅ Route test exists (`StudioManifestReportDesignerRouteTest.php`) |

The placeholder is intentionally read-only. No designer UI, report resource editing, or report compilation exists yet. This contract must be finalized before any implementation begins.

## 10. Cross-Contract Alignment

This contract aligns with:

- **Studio Operating Contract**: `docs/architecture/studio-operating-contract.md` — Report Designer follows the standard Studio identity, workflow, and must-not-own rules
- **Studio Tool Lifecycle Contract**: `docs/architecture/studio-tool-lifecycle-contract.md` — Report Designer is a Studio Tool with governed lifecycle
- **MODULE-CONTRACT.md**: `docs/architecture/MODULE-CONTRACT.md` — Report ownership (smallest-owner-wins) is preserved unchanged
- **APP-CONTRACT.md**: `docs/architecture/APP-CONTRACT.md` — App/module report ownership boundary is respected
- **Surface Contribution Contract**: `docs/architecture/surface-contribution-contract.md` — Reports are resources consumed by surfaces, not a new contribution type
- **Resolved Runtime Contract Pipeline**: `docs/architecture/resolved-runtime-contract-pipeline.md` — Reports are a resource type in the compilation pipeline
- **Business App/Module Ownership Contract**: `docs/architecture/business-app-module-ownership-contract.md` — Module report declarations are preserved as owner artifacts

## 11. Validation

Use the current architecture gates:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

This Report Designer Operating Contract is documentation-only. It does not authorize implementation, runtime changes, or route changes.

## 12. Next Contract Sequence

After this contract is accepted, the next document in sequence is:

1. **Report Resource Contract** — canonical schema for compiled `ReportResource` objects, validation rules, and compilation behavior from `plugin.json` declarations (P0 prerequisite for implementation)
2. **Report Validation Contract** — defines report definition validity rules: key uniqueness, permission existence, view path existence, lifecycle consistency, parameter schema rules (P1)
3. **Report Runtime Contract** — defines how compiled `ReportResource` objects are consumed by Shell surfaces (P1)

Implementation must not begin until the Report Resource Contract exists.
