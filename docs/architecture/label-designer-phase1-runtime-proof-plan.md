# Label Designer Phase 1 Runtime Proof Plan

- **Status**: Planning only — no implementation authorized
- **Classification**: B — Architecture complete, runtime implementation planning allowed
- **Preceding audit**: `label-designer-implementation-readiness-audit.md`
- **Cross-references**: All 9 sibling contracts in `docs/architecture/label-*.md`

---

## Part A — Runtime Proof Goals

### What the proof must demonstrate

| # | Goal | Validation |
|---|---|---|
| 1 | Resource resolution works | Real context + template JSON files are loaded from owner resource paths by key, not by filesystem path. |
| 2 | Runtime request structure works | A `LabelRuntimeRequest`-shaped input is accepted, validated, and rejected if malformed. |
| 3 | Validation works | The 14-check pre-render validation (Runtime Contract §4.1) executes and emits correct PASS/WARN/FAIL/ERROR. |
| 4 | Rules resolve correctly | One or more rule files are discovered, parsed, validated, and excluded on FAIL with WARN diagnostic. |
| 5 | Resolved model can be built | All pipeline stages produce a `ResolvedLabelModel` with all 7 required fields populated. |
| 6 | Pipeline stages execute in order | Each stage runs, produces diagnostics, and halts correctly on FAIL/ERROR. |
| 7 | HTML adapter receives model | The `html_preview` output adapter accepts a `ResolvedLabelModel` and returns a non-empty HTML string. |
| 8 | Output can be viewed | HTML output renders in a browser with resolved field values and rule effects. |
| 9 | Ownership boundaries preserved | Cross-owner requests, path traversal, and resource-owner mismatch are all rejected. |
| 10 | No Studio dependency | The proof runs without Studio classes, Studio composer, Studio routes, or Studio session state. |

### What the proof is NOT trying to prove

- Production throughput (no benchmarks, no concurrency)
- Printer hardware compatibility
- PDF/PNG/SVG/ZPL/thermal output
- QR or barcode encoding
- Data provider contracts or live DB queries
- Webhook or workflow integration
- Bulk or batch rendering
- User-facing UI (CLI-only, or internal diagnostic endpoint)
- Browser print layout fidelity
- Security hardening beyond path traversal and owner boundary

---

## Part B — ResolvedLabelModel Planning

### Definition from Render Pipeline Contract (§C)

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

### Field audit against existing code

| Field | In ResolvedLabelPreview? | Gap |
|---|---|---|
| `owner_key` | No | Missing — Preview uses implicit owner from preview context; runtime model must be explicit. |
| `context` | Yes (`$context`) | Compatible — Preview stores raw array; runtime contract expects `ContextSnapshot` shape. No structural gap. |
| `template` | Yes (`$template`) | Compatible — Same pattern as context. |
| `rules` | No | Missing — Preview has no rule snapshot; rules are evaluated in-memory for the rule sandbox but not preserved as a snapshot on the model. |
| `data` | Partial (`$sampleData`) | Renamed — Preview uses `sampleData` (generated); runtime expects `data` (owner-provided). Conceptually same shape. Different source. |
| `resolved_blocks` | Yes (`$layoutBlocks`) | Compatible — Preview stores resolved blocks with field values and render properties. |
| `diagnostics` | Yes (`$diagnostics`) | Compatible — Same array-of-Diagnostic pattern. |

### Readiness assessment

| Category | Count |
|---|---|
| Already defined (contract only) | Full shape |
| Already implemented (Studio) | 5 of 7 fields — gaps in `owner_key` and `rules` |
| Needs implementation (Platform) | `ResolvedLabelModel` value object in Platform namespace |
| Needs clarification | None — contract and existing Studio implementation are aligned |

### Construction stages

1. Accept `LabelRuntimeRequest` → extract `owner_key`, `context_key`, `template_key`, `data_payload`.
2. Resolve owner root from `owner_key`.
3. Load context JSON → build `ContextSnapshot`.
4. Load template JSON → build `TemplateSnapshot`.
5. Resolve rule files → validate → build `RuleSnapshot[]` (exclude FAIL rules with WARN).
6. Validate data payload against context fields → store as `DataPayload`.
7. Build resolved blocks by mapping template layout against context fields + data values + rule effects.
8. Aggregate diagnostics from all stages.
9. Return complete `ResolvedLabelModel`.

### Ownership

- **Platform** owns the `ResolvedLabelModel` class and the render pipeline that constructs it.
- **Studio** owns the `ResolvedLabelPreview` class (for authoring preview only).
- The two classes may share a common interface or base contract, but must remain in separate namespaces.

---

## Part C — Minimal Platform Render Pipeline

### Stage inventory

| # | Stage | Required? | Halting? | Already proven by Studio Preview? |
|---|---|---|---|---|
| 1 | Validate request | Yes | Yes (FAIL/ERROR halts) | No — Studio uses GET form context, not a structured request object. |
| 2 | Resolve resources | Yes | Yes (missing context/template halts) | Yes — `buildResolvedPreview()` loads both from discovery. |
| 3 | Validate resources | Yes | Yes (schema mismatch halts) | Yes — schema constants and field checks exist. |
| 4 | Load owner data | Yes | Yes (missing required fields halts) | Partial — Preview generates sample data; runtime must accept owner-provided data. |
| 5 | Apply rules | Yes | No (rule errors produce WARN, skip rule) | Partial — Sandbox evaluates rules with sample data; no rule-file persistence layer. |
| 6 | Build resolved model | Yes | Yes (binding failure halts) | Partial — Preview builds resolved blocks but with sample data. |
| 7 | Hand off to adapter | Yes | Yes (missing adapter halts) | No — Preview renders HTML directly, not through an adapter dispatch. |

### Minimal pipeline for Phase 1

```text
Stage 1: RequestValidator
Stage 2: ResourceResolver
Stage 3: ResourceValidator
Stage 4: DataLoader
Stage 5: RuleResolver
Stage 6: ModelBuilder
Stage 7: AdapterDispatcher → HTML Adapter
```

### Boundaries

- Each stage is a separate class in the Platform namespace.
- Stages communicate through a shared `PipelineContext` object that carries the `LabelRuntimeRequest`, loaded resources, intermediate results, and diagnostics.
- No stage may write files, access database, or execute outside the request lifecycle.
- The pipeline coordinator (`coordinator.php` or `RenderPipelineCoordinator`) calls stages in order and halts on FAIL/ERROR.
- Stage 7 (AdapterDispatcher) is a factory that maps the output target to an adapter class.

---

## Part D — HTML Output Adapter

### Purpose

Prove pipeline output is delivered as a viewable HTML document.

### Input

`ResolvedLabelModel` — fully constructed with all resolved fields and rule effects.

### Output

Non-empty HTML string containing:
- Inline CSS with label dimensions
- Resolved field values in layout positions
- Rule effects (badges, warnings, hidden fields, style overrides)
- Diagnostics summary (opt-in, hidden by default)

### Non-goals (explicit)

| Not in scope | Rationale |
|---|---|
| Production rendering | Preview-quality HTML only |
| Print stylesheets | `@media print` is a `browser_print` adapter concern |
| Pixel-perfect positioning | Use inline layout approximation |
| Barcode/QR images | Output `[Barcode Placeholder]` / `[QR Placeholder]` text strings (same as Studio preview) |
| Image embedding | No base64 images, no external image loading |
| Multi-copy output | Single label per HTML document |
| PDF/PNG conversion | Separate adapter categories |

### Contract

| Property | Value |
|---|---|
| Adapter class | `HtmlPreviewAdapter` (Platform namespace) |
| Method | `public static function render(ResolvedLabelModel): string` |
| Stateless | Yes — no constructor args, no static state |
| Diagnostics | Return adapter diagnostics via `PipelineContext`, not embedded in HTML |
| Side effects | None |

---

## Part E — Runtime Entry Point

### Options evaluated

| Option | Pros | Cons |
|---|---|---|
| **CLI command** | No route, no auth, no session. Easy to run, debug, and automate. Hardest to accidentally expose as production endpoint. | Requires CLI setup (autoloader, bootstrap). No browser output without manual file-open. |
| Internal HTTP route | Can view HTML in browser directly. Uses existing autoloader and bootstrap. | Risk of accidental production exposure. Needs auth guard. Needs route registration. More moving parts for a proof. |
| Test harness (PHPUnit) | Existing test infrastructure. Repeatable. CI-compatible. | PHPUnit not designed for visual output review. Assertions on string content only. |
| Diagnostic endpoint | Easy to build on existing Studio controller pattern. | Breaks Studio boundary — Studio must not own runtime render pipeline. Would create coupling immediately. |

### Recommendation

**CLI command** under `scripts/` as the Phase 1 proof entry point.

Rationale:
- Zero exposure risk — no route, no web-accessible endpoint.
- Reuses existing `public/index.php` bootstrap (or a minimal subset) for autoloading.
- Output HTML can be written to a temp file for browser viewing: `php proof.php > /tmp/label-proof.html && open /tmp/label-proof.html`.
- Easy to extend into a test harness later.
- Cannot accidentally become a production route.

Recommended structure:

```
scripts/label-proof/
    proof.php              ← Entry point: parses args, calls pipeline
    LabelRuntimeRequest.php  ← Simple DTO (Platform namespace or standalone)
    PipelineContext.php      ← Shared context object
```

---

## Part F — Validation & Diagnostics

### Required diagnostics for Phase 1 proof

| Diagnostic | Stage | Severity | Halts? |
|---|---|---|---|
| RQ-001: Missing owner_key | 1 — Request Validate | ERROR | Yes |
| RQ-002: Missing context_key | 1 — Request Validate | ERROR | Yes |
| RQ-003: Missing template_key | 1 — Request Validate | ERROR | Yes |
| RQ-004: Missing data_payload | 1 — Request Validate | ERROR | Yes |
| RQ-005: Unknown output_target | 1 — Request Validate | FAIL | Yes |
| RQ-006: Unrecognized top-level key | 1 — Request Validate | WARN | No |
| RS-001: Owner not found | 2 — Resource Resolve | FAIL | Yes |
| RS-002: Owner not lifecycle-eligible | 2 — Resource Resolve | FAIL | Yes |
| RS-003: Context file not found | 2 — Resource Resolve | ERROR | Yes |
| RS-004: Template file not found | 2 — Resource Resolve | ERROR | Yes |
| RS-005: Rule file not found (if filter provided) | 2 — Resource Resolve | WARN | No |
| RV-001: Context schema mismatch | 3 — Resource Validate | ERROR | Yes |
| RV-002: Template schema mismatch | 3 — Resource Validate | ERROR | Yes |
| RV-003: Rule schema mismatch | 3 — Resource Validate | WARN (skip rule) | No |
| RV-004: Owner key mismatch | 3 — Resource Validate | ERROR | Yes |
| RV-005: Context/template key mismatch | 3 — Resource Validate | FAIL | Yes |
| RV-006: Field key mismatch | 3 — Resource Validate | FAIL | Yes |
| DL-001: Missing required field in data | 4 — Data Load | FAIL | Yes |
| DL-002: Type mismatch on data field | 4 — Data Load | FAIL | Yes |
| DL-003: Unrecognized data key | 4 — Data Load | WARN | No |
| RL-001: Rule condition parse error | 5 — Rule Resolve | WARN (skip rule) | No |
| RL-002: Rule effect unknown type | 5 — Rule Resolve | WARN (skip effect) | No |
| MB-001: Duplicate resolved block | 6 — Model Build | FAIL | Yes |
| MB-002: Empty required block | 6 — Model Build | WARN | No |
| AD-001: Adapter not found | 7 — Adapter Dispatch | ERROR | Yes |
| AD-002: Adapter render failure | 7 — Adapter Dispatch | ERROR | Yes |

### Severity mapping (from Validation Contract §9.2)

| Severity | Phase 1 meaning |
|---|---|
| PASS | All checks satisfied for this diagnostic scope |
| WARN | Non-blocking issue logged; pipeline continues; included in output metadata |
| FAIL | Blocking — pipeline halts; no output generated; reported to caller |
| ERROR | Resource or system failure — pipeline halts; reported to caller |

---

## Part G — Risk Analysis

### Risk 1: Ownership drift

| Property | Value |
|---|---|
| Risk | Phase 1 proof circumvents Studio authoring and works directly with owner resource files. If the proof is used as a template for production code, the Studio → owner resource boundary may be bypassed. |
| Impact | Medium — Studio snapshot/metadata tracking could be skipped. |
| Mitigation | Proof documents explicitly that all production entry points must validate resource ownership and lifecycle eligibility. The proof CLI does not write snapshots — it is not a substitute for the Studio authoring workflow. |

### Risk 2: Runtime/Studio coupling

| Property | Value |
|---|---|
| Risk | Phase 1 proof may reuse Studio classes (e.g., `LabelDesignerPreviewRendererService`, `ResolvedLabelPreview`) for convenience, creating an implicit dependency that production code inherits. |
| Impact | High — Platform runtime would then depend on Studio classes, violating the architecture. |
| Mitigation | Proof must NOT import any `Apps\Studio\*` class. If code reuse is needed, shared schema constants or validation logic should be extracted to a neutral namespace (e.g., `Platform\Labels\*`) before any implementation. |

### Risk 3: Rule execution drift

| Property | Value |
|---|---|
| Risk | The Phase 1 proof's rule resolver may diverge from the Studio rule sandbox behavior, leading to different results in preview vs. runtime. |
| Impact | Medium — preview/runtime parity is a quality goal, not a Phase 1 success criterion. |
| Mitigation | Document that Phase 1 rule resolution is a minimal subset (equals/not_equals/empty/not_empty operators, show_badge/hide_field effects). Parity with Studio sandbox is a separate quality gate. |

### Risk 4: Data payload misuse

| Property | Value |
|---|---|
| Risk | Phase 1 proof accepts data payload from CLI arguments or a JSON file. Production code must validate data sources more strictly. |
| Impact | Low — proof is CLI-only, no network exposure. |
| Mitigation | Document that CLI data payload is for proof only. Production data payload must come from an owner-provided source (direct API call, internal service, or future provider contract). |

### Risk 5: Adapter leakage

| Property | Value |
|---|---|
| Risk | The HTML adapter in Phase 1 may grow into a production renderer before proper Platform infrastructure exists. |
| Impact | Medium — feature creep into production rendering without pipeline contract enforcement. |
| Mitigation | Adapter contract explicitly forbids side effects, business logic, file writes, and DB access. Code review gate. |

### Risk 6: Future printer coupling

| Property | Value |
|---|---|
| Risk | Adding ZPL/thermal adapter awareness during Phase 1 planning creates pressure to implement those adapters early. |
| Impact | Low — Risk is contained by explicit "forbidden in Phase 1" list (Part H). |
| Mitigation | Phase 1 explicitly prohibits all non-HTML output. |

---

## Part H — Phase 1 Boundaries

### Forbidden in Phase 1

```text
PDF generation
PNG generation
SVG generation
ZPL generation
Thermal printing
Browser printing
QR code generation
Barcode generation (real encoding)
Provider contracts
Message queues
Job scheduling
Printer integration
Workflow integration
Bulk rendering
Approval workflows
Authentication guards (CLI is unauthenticated by design)
File writes outside /tmp or stdout
Database access
Network requests
Studio namespace imports
Route registration in any web framework
```

### Allowed in Phase 1

```text
CLI entry point
ResolvedLabelModel value object (Platform namespace)
PipelineContext (Platform namespace)
RequestValidator
ResourceResolver
ResourceValidator
DataLoader
RuleResolver (minimal operators)
ModelBuilder
HtmlPreviewAdapter
Scripts directory (scripts/label-proof/)
Temp file output for browser viewing
```

---

## Part I — Success Criteria

All must pass before Phase 1 is complete:

| # | Criterion | How to measure |
|---|---|---|
| 1 | Runtime request accepted | CLI accepts `--owner`, `--context`, `--template`, `--data`, `--output` args. |
| 2 | Resources resolved | CLI loads context + template JSON from the correct owner resource path. |
| 3 | Request validation works | CLI rejects missing required args with ERROR. |
| 4 | Resource validation works | CLI rejects mismatched owner key or schema version. |
| 5 | Rules resolved and applied | CLI with `--rules` filter evaluates rules and includes effects in output. |
| 6 | Resolved model created | CLI output includes all 7 fields of `ResolvedLabelModel`. |
| 7 | HTML output generated | CLI writes a non-empty HTML file with label dimensions and field values. |
| 8 | Ownership boundary preserved | CLI rejects a request where `owner_key` does not match the context/template owner. |
| 9 | Path traversal blocked | CLI rejects a request with `..` in any key. |
| 10 | Zero Studio imports | `grep -r "Apps\\\\Studio" scripts/label-proof/` returns empty. |
| 11 | All 26 diagnostics mapped | Each diagnostic code (RQ-001 through AD-002) is emitted by its corresponding stage. |

---

## Part J — Recommended Implementation Sequence

### Sequence

```
Step 1: ResolvedLabelModel value object
  File:   platform/Labels/ValueObjects/ResolvedLabelModel.php
  Action: Create final class with 7 readonly typed properties.
          Factory method fromArray() + toArray() round-trip.
          Separate namespace from Studio's ResolvedLabelPreview.
  Depends on: Nothing

Step 2: RuntimeRequest DTO
  File:   platform/Labels/ValueObjects/RuntimeRequest.php
  Action: Create final class with owner_key, context_key, template_key,
          data_payload, output_target, options.
          Static factory from CLI args or JSON file.
  Depends on: Nothing

Step 3: PipelineContext
  File:   platform/Labels/Pipeline/PipelineContext.php
  Action: Mutable context object passed between stages.
          Carries request, intermediate results, diagnostics.
  Depends on: Steps 1, 2

Step 4: RequestValidator (Stage 1)
  File:   platform/Labels/Pipeline/RequestValidator.php
  Action: Validate RuntimeRequest shape. Return diagnostics.
          Checks RQ-001 through RQ-006.
  Depends on: Step 2

Step 5: ResourceResolver (Stage 2)
  File:   platform/Labels/Pipeline/ResourceResolver.php
  Action: Resolve owner root from owner_key.
          Load context/template JSON files.
          Check RS-001 through RS-005.
  Depends on: Steps 3, 4

Step 6: ResourceValidator (Stage 3)
  File:   platform/Labels/Pipeline/ResourceValidator.php
  Action: Schema version checks, owner boundary, field key compatibility.
          Checks RV-001 through RV-006.
  Depends on: Step 5

Step 7: DataLoader (Stage 4)
  File:   platform/Labels/Pipeline/DataLoader.php
  Action: Validate data_payload against context field definitions.
          Checks DL-001 through DL-003.
  Depends on: Steps 5, 6

Step 8: RuleResolver (Stage 5)
  File:   platform/Labels/Pipeline/RuleResolver.php
  Action: Load rule files from owner resource path.
          Validate condition and effect structure.
          Evaluate conditions against data.
          Collect effects for model builder.
          Checks RL-001 through RL-002.
  Depends on: Step 5

Step 9: ModelBuilder (Stage 6)
  File:   platform/Labels/Pipeline/ModelBuilder.php
  Action: Assemble resolved blocks from template layout + data + rule effects.
          Build ResolvedLabelModel.
          Checks MB-001 through MB-002.
  Depends on: Steps 6, 7, 8

Step 10: HtmlPreviewAdapter (Stage 7 output)
  File:   platform/Labels/Pipeline/Adapters/HtmlPreviewAdapter.php
  Action: Accept ResolvedLabelModel, return HTML string.
          Inline CSS, field values, rule effects, diagnostics toggle.
  Depends on: Step 9

Step 11: PipelineCoordinator
  File:   platform/Labels/Pipeline/PipelineCoordinator.php
  Action: Orchestrate stages 1-7 in order.
          Halt on FAIL/ERROR. Aggregate diagnostics.
          Return ResolvedLabelModel + final diagnostic set.
  Depends on: Steps 4-10

Step 12: CLI entry point
  File:   scripts/label-proof/proof.php
  Action: Parse CLI args, build RuntimeRequest, call PipelineCoordinator,
          capture ResolvedLabelModel, call HtmlPreviewAdapter,
          write HTML to stdout or /tmp file, display diagnostics summary.
  Depends on: Steps 1-11
```

### Dependencies diagram

```
Step 1 (Model)
  ↑
Step 2 (Request)
  ↑
Step 3 (Context) ← Steps 1, 2
  ↑
Step 4 (ReqVal) ← Step 2
  ↑
Step 5 (ResRes) ← Steps 3, 4
  ↑
Step 6 (ResVal) ← Step 5
  ↑
Step 7 (Data) ← Steps 5, 6
  ↑
Step 8 (Rules) ← Step 5
  ↑
Step 9 (Builder) ← Steps 6, 7, 8
  ↑
Step 10 (Adapter) ← Step 9
  ↑
Step 11 (Coordinator) ← Steps 4-10
  ↑
Step 12 (CLI) ← Steps 1-11
```

---

## Part K — Boundary Gate Impact

### Current boundary gate

- File: `scripts/architecture/check_label_designer_boundaries.sh`
- Invariants: 358
- Scope: Studio-only boundaries (no save, no DB, no print, no QR, route allowlist, etc.)

### Recommended future invariants

The boundary gate must remain Studio-focused. Phase 1 proof lives under `scripts/` and `platform/`, which are outside the Studio boundary. However, the following invariants should be added before Phase 1 implementation begins:

| # | Invariant | Type | Purpose |
|---|---|---|---|
| 1 | No `Apps\\Studio` import in `platform/Labels/` | File scan | Prevent runtime/Studio coupling. |
| 2 | No `Apps\\Studio` import in `scripts/label-proof/` | File scan | Same — proof must be Studio-independent. |
| 3 | No `output_target` = `pdf\|png\|svg\|zpl\|thermal` in Phase 1 code | Content scan | Enforce output adapter boundaries. |
| 4 | `scripts/label-proof/` does not register web routes | Content scan | Prevent accidental route exposure. |
| 5 | `platform/Labels/Pipeline/Adapters/` contains only `HtmlPreviewAdapter.php` | File count | Ensure no extra adapters leak in. |

### Gate update timing

Gate invariants should be updated in a **planning-only commit** before Step 1 implementation begins. This ensures the gate catches violations from the first line of runtime code.

---

## Deliverable Summary

### 1. New planning document path

`docs/architecture/label-designer-phase1-runtime-proof-plan.md`

### 2. Runtime proof goal

Prove that a `LabelRuntimeRequest` can be resolved through all 7 pipeline stages to produce a viewable HTML label output, with all 26 diagnostics correctly emitted, ownership boundaries enforced, and zero Studio coupling.

### 3. Recommended runtime entry point

CLI command under `scripts/label-proof/proof.php` — unauthenticated, no route registration, no session state.

### 4. ResolvedLabelModel readiness assessment

| Category | Assessment |
|---|---|
| Contract defined | Full shape in Render Pipeline Contract |
| Studio analog exists | `ResolvedLabelPreview` covers 5 of 7 fields |
| Gap | `owner_key` and `rules` snapshot missing |
| Action | Create `Platform\Labels\ValueObjects\ResolvedLabelModel` |

### 5. Minimal pipeline plan

7 stages, each a separate class in `platform/Labels/Pipeline/`, coordinated by `PipelineCoordinator`. Stages: RequestValidator → ResourceResolver → ResourceValidator → DataLoader → RuleResolver → ModelBuilder → AdapterDispatcher.

### 6. HTML adapter plan

`platform/Labels/Pipeline/Adapters/HtmlPreviewAdapter` — single `render(ResolvedLabelModel): string` method, stateless, no side effects, preview-quality HTML only.

### 7. Major risks

| Risk | Impact | Mitigation |
|---|---|---|
| Runtime/Studio coupling | High | Forbid `Apps\\Studio` imports in platform code |
| Rule execution drift | Medium | Phase 1 uses minimal operator subset; parity is separate gate |
| Data payload misuse | Low | CLI-only, no network exposure |
| Adapter feature creep | Medium | Contract forbids side effects and non-HTML output |

### 8. Success criteria

11 measurable criteria: CLI arg validation, resource loading, schema validation, rule application, model construction, HTML output, ownership boundary, path traversal blocking, zero Studio imports, all 26 diagnostic codes emitted.

### 9. Recommended implementation sequence

12 steps, ordered by dependency: Value Object → DTO → Context → 5 Pipeline Stages → 2 More Stages → Adapter → Coordinator → CLI.

### 10. Boundary gate recommendations

5 new invariants to add before implementation begins, focused on Studio namespace isolation, output adapter restriction, and route registration prohibition.

---

## Answer

**What is the smallest safe runtime proof that validates the Label Designer architecture?**

A CLI pipeline (`scripts/label-proof/proof.php`) that accepts a structured label request, resolves owner-owned context/template/rule resource files from disk, validates all resources and data per the pre-render checklist, builds a `ResolvedLabelModel`, and renders an HTML preview — all without importing any Studio class, registering any web route, writing any file outside `/tmp`, or touching any output format other than `html_preview`. The proof is deliberately CLI-only to enforce the "no accidental production endpoint" constraint.

---

## Cross-Reference Update

| Document | Update |
|---|---|
| `label-designer-operating-contract.md` | Add Phase 1 proof plan reference in "Next Contract Sequence" section |
| `label-designer-implementation-readiness-audit.md` | Add planning document reference in "Next Steps" section |
| `architecture-gate-coverage-index.md` | No gate update — planning only, no gate changes |
