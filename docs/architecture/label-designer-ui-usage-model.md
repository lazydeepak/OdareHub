# Label Designer UI Usage Model

Status: Planning and information-architecture contract only.

Date: 2026-06-13

This document defines how people should use Label Designer before any UI
redesign is implemented. It replaces resource-type navigation as the target
user experience while preserving the existing context, template, rule,
metadata, diagnostics, preview, snapshot, and ownership contracts.

It does not authorize UI implementation, runtime rendering, printing, QR or
barcode generation, export, Platform runtime work, database runtime providers,
new routes, or changes to owner-owned resources.

## 1. Product Direction

Label Designer should become a guided label builder backed by governed,
owner-owned resources.

Users should think in this order:

```text
Choose the label and owner
  -> define the label's content and size
  -> arrange its presentation
  -> add optional presentation rules
  -> verify the result
  -> review governance status
```

Users should not need to understand the context/template/rule file split before
they can understand or preview a label. That split remains the backend resource
model and becomes progressively disclosed technical detail.

### 1.1 Primary design principles

1. Organize the UI around user intent, not resource implementation.
2. Keep owner selection and label identity visible throughout the workflow.
3. Present validation as a short status summary before diagnostic detail.
4. Keep raw JSON, paths, schema keys, and diagnostic codes collapsed by default.
5. Use plain-language tasks for business/config users and progressively reveal
   technical controls for Studio/admin and developer/platform users.
6. Keep Studio preview clearly labeled as sample-data design preview.
7. Keep runtime dry-run and its negative case matrix in Governance only.
8. Do not show print, export, QR, barcode, deploy, or runtime-test actions.

### 1.2 Relationship to earlier workspace plans

`label-designer-workspace-structure-contract.md` and
`label-designer-ux-refactor-plan.md` describe how to separate the existing
Contexts/Templates/Rules/Maintenance implementation. They remain useful records
of the current implementation, but their resource-centric navigation is not the
target product model.

This document governs the next UI redesign. The new top-level workspaces are:

```text
Overview | Build Label | Rules | Preview | Governance
```

Contexts and Templates become implementation details within Build Label.
Maintenance capabilities become governed sections within Governance.

## 2. User Modes

User mode controls disclosure and permitted actions. It must not grant
permissions; authorization continues to come from existing Studio, owner, and
governance controls.

### 2.1 Business/config user

**Goal**

Create or review a usable label configuration for an approved business owner
without learning the resource file model.

**Tasks**

- Choose an owner and an existing label.
- Start a new guided label definition where creation is authorized.
- Select label purpose, size, fields, and field order.
- Review plain-language rule behavior.
- Preview with sample values.
- Resolve understandable validation blockers.
- Hand the configuration to an authorized Studio/admin user when an advanced or
  guarded action is required.

**Should see**

- Owner and label identity.
- Guided steps and completion status.
- Label dimensions and selected fields.
- Visual sample-data preview.
- Plain-language validation summary: Ready, Needs attention, or Blocked.
- Existing rules expressed as "When ... then ..." statements.
- Clear empty states and the next available action.

**Hidden or collapsed**

- Raw JSON.
- Filesystem paths and owner roots.
- Schema names and resource keys unless needed for identification.
- Diagnostic codes and full diagnostic tables.
- Metadata migration, folder readiness, resource scans, runtime dry-run, and
  negative scenario matrix.
- Candidate database table/column details; when exposed for authorized setup,
  describe them as design-time source candidates.

**Allowed actions**

- Select and inspect owner-owned labels.
- Change in-memory or request-scoped builder selections.
- Run design-time validation and sample-data preview.
- Use the rule sandbox when exposed as a guided, presentation-only interaction.
- Submit guarded create actions only when ACL and lifecycle policy already allow
  them and the existing confirmation contract is preserved.

**Forbidden actions**

- Print, export, generate QR/barcodes, or invoke owner runtime actions.
- Configure runtime database queries or providers.
- Bypass validation, ownership, confirmation, snapshot, or ACL checks.
- Edit raw JSON directly in the default experience.
- Apply metadata migrations or create owner folders.

### 2.2 Studio/admin user

**Goal**

Author and govern owner-owned label resources through Studio's guarded
workflows while preserving ownership, validation, snapshots, and provenance.

**Tasks**

- Perform all business/config tasks.
- Create context, template, and rule resources through guided workflows.
- Inspect resource readiness and ownership compatibility.
- Review detailed validation before guarded create.
- Create missing label resource folders when explicitly authorized.
- Preview and apply metadata migration through the existing guarded workflow.
- Inspect snapshots, target paths, provenance, and handoff evidence.

**Should see**

- All guided workspaces.
- Advanced status summaries and owner lifecycle information.
- Guarded action panels in Governance.
- Snapshot and target-path consequences before writes.
- Detailed diagnostics on demand.
- Resource keys and paths in collapsed technical detail.

**Hidden or collapsed**

- Raw JSON and full diagnostics by default.
- Runtime dry-run request payload and scenario rows until expanded.
- Architecture reference material and legacy implementation terminology.
- Any control implying production output or runtime activation.

**Allowed actions**

- All actions already authorized by the existing guarded create, folder
  readiness, and metadata migration services.
- Expand raw JSON, paths, diagnostics, and provenance.
- Run read-only resource diagnostics and runtime dry-run validation.
- Perform exact-confirmation guarded writes supported by current services.

**Forbidden actions**

- Add update/delete/overwrite behavior where only create is supported.
- Invoke production rendering, print, export, QR, barcode, or owner runtime.
- Create a runtime data provider or query arbitrary business data.
- Treat Studio preview or dry-run as proof of production output.
- Transfer ownership of resources from the app/module/plugin to Studio.

### 2.3 Developer/platform user

**Goal**

Inspect contracts, diagnostics, resource shape, and future runtime handoff
readiness without turning Label Designer into a runtime implementation surface.

**Tasks**

- Inspect resource JSON, metadata, resolved paths, and diagnostic codes.
- Review context/template/rule compatibility.
- Inspect sample preview resolution and rule evaluation.
- Run and inspect the read-only runtime dry-run and negative case matrix.
- Diagnose legacy metadata and resource-boundary problems.
- Verify that proposed UI behavior maps to existing service contracts.

**Should see**

- Everything available to Studio/admin users.
- Developer detail drawers for JSON, schemas, paths, diagnostic codes, and
  read-only resolved models.
- Governance references to architecture contracts.
- Runtime dry-run request, summary, diagnostics, and scenario matrix.
- Explicit labels distinguishing design-time preview from future Platform
  runtime.

**Hidden or collapsed**

- Technical details remain collapsed initially so the default layout is shared
  with other modes and does not become a diagnostics dashboard.
- Repetitive PASS rows should remain behind "View all checks."

**Allowed actions**

- Inspect all read-only technical detail.
- Run existing validation, discovery, preview, sandbox, diagnostics, migration
  preview, and dry-run services within their current contracts.
- Use guarded create/apply actions only when separately authorized.

**Forbidden actions**

- Enable or implement Platform runtime from this UI.
- Add print/export/QR/barcode controls or adapters.
- Add database runtime providers, SQL inputs, credentials, or arbitrary data
  lookup.
- Add runtime routes or make owner applications depend on Studio.
- Modify Core or bypass owner-root containment.

## 3. Workspace Map

```text
Label Designer
|
+-- Overview
|   +-- owner and label selector
|   +-- label inventory and status
|   +-- workflow progress
|   +-- issues requiring attention
|
+-- Build Label
|   +-- label identity and purpose
|   +-- source/field selection
|   +-- label size and layout
|   +-- guided review
|   +-- advanced resource JSON
|
+-- Rules
|   +-- existing presentation rules
|   +-- plain-language rule builder
|   +-- in-memory rule test
|   +-- guarded rule creation
|
+-- Preview
|   +-- sample-data controls
|   +-- single visual preview
|   +-- rule effect summary
|   +-- validation summary
|   +-- advanced resolved detail
|
+-- Governance
    +-- readiness and ownership
    +-- resource diagnostics
    +-- guarded folder and metadata actions
    +-- runtime dry-run validation
    +-- negative case matrix
    +-- contracts and technical reference
```

The selected owner and label form persistent page context across all
workspaces. Switching workspaces must not silently change either selection.

## 4. Workspace Specifications

### 4.1 Overview

**Purpose**

Orient the user, answer "what labels exist and what needs attention?", and
provide a clear entry into the next task.

**Primary cards or sections**

- Owner selector with owner type and lifecycle state.
- Label list grouped by business label identity, not separate resource files.
- Label status card: context present, template present, rules count, preview
  readiness, governance status.
- Continue building card with the next incomplete step.
- Attention card summarizing blocking errors and warnings.
- Recent guarded activity or provenance summary when available from the current
  model; no new audit persistence is implied.

**Allowed actions**

- Change owner or label selection.
- Start Build Label for an eligible owner.
- Continue an incomplete builder state.
- Open Preview, Rules, or Governance for the selected label.

**Hidden advanced details**

- Resource file count, paths, keys, schemas, and diagnostic codes.
- Raw discovery output.
- Runtime dry-run details.

**Empty states**

- No lifecycle-eligible owners: explain that no owner can currently host label
  resources; link to Governance for administrators.
- Eligible owner with no labels: offer "Build first label" when authorized.
- Context without template: show "Continue label setup" rather than exposing a
  broken resource pair.
- Template without compatible context: mark Blocked and direct technical users
  to Governance.

**Validation states**

- Ready: compatible resources and no blocking diagnostics.
- Needs attention: warnings or incomplete guided steps.
- Blocked: missing/incompatible resources or error diagnostics.
- Not checked: diagnostics have not completed.

**Existing backend relationships**

- `LabelDesignerDiscoveryService::discover()`
- `LabelDesignerResourceReadinessService::checkReadiness()`
- `LabelDesignerResourceDiagnosticsService::scanAll()`
- Existing controller model values for discovered resources and selected owner.

### 4.2 Build Label

**Purpose**

Guide the user through defining one label without requiring awareness of the
context/template resource split.

**Primary cards or sections**

1. Identity: owner, label name/key, purpose.
2. Data fields: approved source candidate, allowed fields, required fields.
3. Label setup: size, orientation where already supported, selected fields.
4. Layout: field order and available presentation blocks using current template
   capabilities; do not imply unsupported drag-and-drop editing.
5. Review: plain-language summary, validation summary, guarded create actions.
6. Advanced details: context JSON, template JSON, target paths, and validation
   rows in separate collapsed panels.

The UI may present this as a stepper, but context and template writes remain
separate guarded operations until a future contract explicitly defines an
atomic multi-resource transaction.

**Allowed actions**

- Select owner, purpose, design-time source candidate, fields, and size.
- Build context and template previews in memory/request state.
- Run existing validation.
- Create context or template through the existing guarded, confirmed,
  snapshot-before-write services.

**Hidden advanced details**

- Context/template terminology in the primary flow.
- JSON previews, schema names, target paths, source table metadata, and
  per-check diagnostics.
- Snapshot storage paths.

**Empty states**

- No owner selected: require owner selection.
- Owner not lifecycle eligible: explain why building is unavailable.
- No approved/candidate fields: explain that fields cannot be configured and
  expose technical discovery only to authorized advanced modes.
- Existing context but no template: resume at label setup.
- Duplicate context/template key: block creation and offer selection of the
  existing label; do not offer overwrite.

**Validation states**

- Incomplete: required guided selections are missing.
- Ready to create context.
- Context created; template setup can continue.
- Ready to create template.
- Blocked by FAIL/ERROR; show a short remediation list first.
- Created; link to Preview as the primary next action.

**Existing backend relationships**

- `LabelDesignerDataSourceDiscoveryService::discover()`
- `LabelDesignerContextCreateService::buildPreview()` and `createContext()`
- `LabelDesignerTemplatePreviewService::buildPreview()`
- `LabelDesignerTemplateCreateService::buildPreview()` and `createTemplate()`
- `LabelDesignerResourceReadinessService::isLabelLifecycleOwner()`
- Existing guarded context/template POST routes remain unchanged during an
  initial visual migration.

### 4.3 Rules

**Purpose**

Let users understand and author optional presentation-only behavior for the
selected label.

**Primary cards or sections**

- Rule summary: enabled rules, matched-rule example count, and status.
- Existing rules as plain-language statements.
- Guided rule builder: "When [field] [operator] [value], then [effect]."
- Test rule with current sample data.
- Guarded create review and confirmation.
- Advanced JSON, target scope, priority, diagnostic rows, and resource path.

**Allowed actions**

- Select compatible context/template implicitly through the selected label.
- Configure only the currently allowlisted conditions and effects.
- Test a temporary rule in memory.
- Create a new rule through the existing guarded create-only service.
- Enable or disable rule loading for design preview where already supported;
  this does not change the stored resource.

**Hidden advanced details**

- Resource target scopes, style-token internals, raw JSON, priority, conflict
  strategy, and diagnostic codes.
- Context/template selectors when the selected label already resolves them.

**Empty states**

- No selected label: direct to Overview.
- Label lacks compatible context/template: direct to Build Label.
- No rules: explain that rules are optional and the label can still preview.
- No prior preview: allow rule construction, but direct testing to Preview or
  create a sample preview through the same design-time preview service.

**Validation states**

- Valid and testable.
- Condition not matched: informational, not an error.
- Effect applied in sample preview.
- Blocked by invalid field, operator, effect, target, owner, or duplicate key.
- Created successfully; no update/delete action is introduced.

**Existing backend relationships**

- `LabelDesignerRuleCreateService::buildPreview()` and `createRule()`
- `LabelDesignerRuleSandboxService::evaluateTemporaryRule()`
- `LabelDesignerPreviewRendererService` for shared sample preview state.
- Existing presentation-only effect allowlist and create-only route contract.

### 4.4 Preview

**Purpose**

Provide one focused design-time verification surface for the selected label
using sample data.

**Primary cards or sections**

- Preview setup: selected label, sample values, and rules on/off.
- Single visual label preview.
- Content summary: field count, dimensions, and applied rules.
- Validation summary with blocking issues before the preview.
- Advanced resolved fields, sample payload, rule evaluation, HTML/model detail,
  and full diagnostics.

**Allowed actions**

- Generate or edit sample values in memory where supported.
- Render the existing Studio HTML preview.
- Toggle loading of compatible presentation rules for comparison.
- Inspect applied/skipped rules and design-time diagnostics.
- Return to Build Label or Rules with the same owner/label context.

**Hidden advanced details**

- Full diagnostics table, check codes, resolved model, generated HTML, resource
  paths, and raw sample payload.
- Runtime request/dry-run data, which belongs in Governance.

**Empty states**

- No label selected: direct to Overview.
- Context exists without template: direct to Build Label.
- Preview blocked: show the first actionable issues and preserve selections.
- No rules: render normally and state that no presentation rules were applied.

**Validation states**

- Preview ready.
- Preview ready with warnings.
- Preview blocked by resource or field compatibility.
- Rule matched, rule skipped, or rules disabled.

**Existing backend relationships**

- `LabelDesignerPreviewRendererService::buildResolvedPreview()`
- `validatePreviewPreconditions()`, `generateSampleData()`,
  `resolveFields()`, `buildLayoutBlocks()`, and `renderHtmlPreview()`
- Existing preview session/model values.
- Rule sandbox results may annotate the same preview, but must not create a
  second competing preview engine or imply Platform runtime output.

### 4.5 Governance

**Purpose**

Contain technical readiness, diagnostics, guarded maintenance, provenance, and
future-runtime validation away from the primary authoring journey.

**Primary cards or sections**

- Governance summary: owner readiness, resource health, metadata status, and
  blocking issue count.
- Ownership and folder readiness.
- Resource diagnostics by owner and label.
- Guarded folder creation.
- Metadata analysis, migration preview, and migration apply.
- Runtime handoff dry-run summary.
- Negative case scenario matrix.
- Contracts, paths, schemas, and reference implementation details.

Sections with write capability must be visually separated from read-only
diagnostics and require the existing exact confirmations.

**Allowed actions**

- Run read-only readiness, metadata analysis, resource diagnostics, runtime
  dry-run, and scenario matrix evaluation.
- Expand detailed diagnostics and raw request/resource information.
- Create missing folders or apply metadata migration only through existing
  guarded services, authorization, snapshot, backup, and confirmation.

**Hidden advanced details**

- Detailed PASS rows, per-resource diagnostic tables, raw request JSON, scenario
  rows, paths, and contract references are collapsed by default.
- Business/config users should not see this workspace unless explicitly allowed.

**Empty states**

- No owners: explain lifecycle prerequisites.
- No resources: readiness summary and guarded folder action for authorized
  administrators.
- No legacy metadata: show "No migration needed."
- Dry-run fixture unavailable: show governance validation unavailable; do not
  substitute a runtime action.

**Validation states**

- Healthy: no blocking resource or dry-run failures.
- Attention: informational or warning diagnostics.
- Blocked: resource errors or an invalid dry-run request.
- Migration available: legacy metadata detected, preview required.
- Migration ready: preview passes and exact confirmation is still required.

**Existing backend relationships**

- `LabelDesignerResourceReadinessService`
- `LabelDesignerResourceMetadataService`
- `LabelDesignerMetadataMigrationService`
- `LabelDesignerResourceDiagnosticsService`
- `LabelDesignerRuntimeDryRunValidator::validateSample()`
- `LabelDesignerRuntimeDryRunValidator::validateScenarioMatrix()`
- `LabelDesignerDiscoveryService` for advanced inventory detail.

The dry-run remains validation-only. It must not call a renderer, adapter,
printer, exporter, QR generator, owner action, database provider, or Platform
runtime pipeline.

## 5. User Journey Map

### 5.1 Business/config journey

```text
Overview
  -> choose owner and label
  -> Build Label
  -> choose purpose, fields, size, and layout
  -> Preview
  -> inspect sample result and resolve plain-language blockers
  -> Rules (optional)
  -> test presentation behavior
  -> Preview
  -> hand off or complete authorized guarded creation
```

The journey ends with a validated owner-owned design resource state. It does
not end with printing, exporting, deployment, or runtime activation.

### 5.2 Studio/admin journey

```text
Overview
  -> select lifecycle-eligible owner
  -> Governance readiness check
  -> Build Label guarded context/template creation
  -> Rules guarded rule creation (optional)
  -> Preview design verification
  -> Governance detailed diagnostics and provenance review
```

If readiness or metadata blocks authoring, the admin resolves it in Governance
and returns to the same selected label.

### 5.3 Developer/platform journey

```text
Overview
  -> inspect selected owner/label status
  -> Preview resolved design-time model
  -> Governance resource diagnostics
  -> Governance runtime dry-run summary
  -> expand request, diagnostic codes, and negative case matrix
  -> compare results with architecture contracts
```

This journey verifies contracts only. It does not cross into Platform runtime
implementation or production output.

## 6. Allowed and Forbidden Action Matrix

| Action | Business/config | Studio/admin | Developer/platform | Boundary |
|---|---|---|---|---|
| Browse owners and labels | Allowed | Allowed | Allowed | Read-only discovery |
| Build in-memory context/template preview | Allowed | Allowed | Allowed | Design-time only |
| Render Studio sample preview | Allowed | Allowed | Allowed | Not production runtime |
| Test temporary presentation rule | Allowed | Allowed | Allowed | In-memory, no side effects |
| Create context/template/rule | ACL-dependent | Allowed when authorized | ACL-dependent | Existing guarded create only |
| View raw JSON and paths | Hidden by default | Expandable | Expandable | Read-only unless guarded create |
| Run resource diagnostics | Summary | Allowed | Allowed | Read-only |
| Create missing folders | Forbidden | Allowed when authorized | ACL-dependent | Existing guarded action |
| Preview metadata migration | Forbidden | Allowed | Allowed | Read-only preview |
| Apply metadata migration | Forbidden | Allowed when authorized | ACL-dependent | Snapshot + backup + confirmation |
| Run runtime dry-run | Hidden/forbidden | Read-only summary | Allowed | Validation-only |
| View negative case matrix | Hidden/forbidden | Expandable | Allowed | In-memory fixtures |
| Edit raw JSON directly | Forbidden | Forbidden in redesign | Forbidden in redesign | Use governed services |
| Update/delete/overwrite resources | Forbidden | Forbidden | Forbidden | Not currently authorized |
| Print label | Forbidden | Forbidden | Forbidden | Future owner + Platform runtime |
| Export PDF/image/ZPL | Forbidden | Forbidden | Forbidden | Future Platform adapters |
| Generate QR/barcode | Forbidden | Forbidden | Forbidden | Future Platform capability |
| Configure runtime DB provider or SQL | Forbidden | Forbidden | Forbidden | Separate future contract required |
| Invoke owner runtime action | Forbidden | Forbidden | Forbidden | Studio must remain optional |

## 7. Migration from Current UI

The migration should preserve current backend services and routes first, then
change orchestration only in separately approved implementation slices.

| Current UI section | Target workspace | Target presentation |
|---|---|---|
| Contexts: architecture | Governance | Collapsed contracts/reference |
| Contexts: future label types | Remove from primary UI | Documentation link only |
| Contexts: resource contracts | Governance | Collapsed technical reference |
| Contexts: existing resource discovery | Overview + Governance | Label inventory summary; raw files collapsed |
| Contexts: DB discovery | Build Label | Advanced design-time source candidate step |
| Contexts: guarded context creation | Build Label | Identity and Data Fields steps |
| Contexts: ownership guard | Persistent page context + Governance | Owner badge and collapsed explanation |
| Templates: template creation | Build Label | Label Setup, Layout, and Review steps |
| Templates: preview renderer | Preview | Single focused preview surface |
| Rules: rule sandbox | Rules | Test rule interaction using shared preview |
| Rules: rule creation | Rules | Guided builder and guarded create review |
| Maintenance: readiness | Governance | Governance summary and readiness section |
| Maintenance: folder creation | Governance | Separated guarded action |
| Maintenance: metadata analysis/migration | Governance | Collapsed advanced maintenance |
| Maintenance: runtime dry-run | Governance | Governance-only summary and detail |
| Maintenance: negative case matrix | Governance | Developer detail, collapsed by default |
| Maintenance: resource diagnostics | Governance | Summary first, per-resource checks later |
| Maintenance: reference implementation | Governance | Collapsed historical/reference detail |

### 7.1 Proposed implementation sequence

This sequence is future planning only:

1. Introduce the five-workspace navigation and persistent owner/label context
   without changing routes, services, or write behavior.
2. Build Overview from existing discovery, readiness, and diagnostics models.
3. Compose current context and template forms into the guided Build Label
   journey while retaining separate guarded submissions.
4. Move the single design-time preview into Preview and reuse it from Rules.
5. Reframe Rules around plain-language presentation behavior.
6. Move all maintenance, diagnostics, dry-run, matrix, JSON, and reference
   detail into Governance with progressive disclosure.
7. Add role-aware disclosure only after ACL behavior is explicitly mapped;
   never infer authorization from the selected visual mode.

Each implementation slice must preserve existing POST contracts unless a later
approved contract explicitly changes them.

## 8. Validation and Disclosure Model

Every workspace should use the same summary-first pattern:

```text
Status
  Ready | Needs attention | Blocked | Not checked

Summary
  short user-facing explanation

Next action
  one primary remediation or continuation action

Details (collapsed)
  warnings, errors, PASS rows, codes, JSON, paths, schemas
```

Rules:

- Show blocking errors before warnings.
- Do not lead with PASS counts.
- Translate diagnostic messages into task language without discarding the
  canonical code.
- Keep the canonical code visible in expanded details for support and audit.
- Collapse repetitive successful checks.
- Never describe Studio preview as runtime validation.
- Never describe runtime dry-run as rendering, output generation, or production
  readiness.

## 9. Boundary Proof

This usage model introduces no runtime behavior because:

1. It creates only this planning document.
2. It adds no PHP, routes, controllers, services, forms, buttons, JavaScript,
   CSS, resource files, or database changes.
3. It does not authorize a Platform renderer, output adapter, print dispatcher,
   QR/barcode generator, export engine, runtime data provider, or owner
   integration.
4. Studio preview remains sample-data, design-time HTML produced by the existing
   Studio service.
5. Runtime dry-run remains an in-memory validator over deterministic fixtures
   and is confined to Governance.
6. Context, template, and rule resources remain owned by their app, module, or
   plugin under canonical owner paths.
7. Existing guarded writes retain owner containment, validation, exact
   confirmation, snapshot, backup where applicable, and create-only limits.
8. Core remains locked and unchanged.

## 10. Redesign Acceptance Criteria

A future UI redesign is aligned with this model only when:

- The top-level workspaces are Overview, Build Label, Rules, Preview, and
  Governance.
- A first-time user can understand the label workflow without learning the
  context/template/rule file split.
- Business/config users see a guided workflow and plain-language status.
- Raw JSON, paths, schemas, and detailed diagnostics are collapsed by default.
- Preview has one clear design-time output surface.
- Runtime dry-run and the negative case matrix appear only in Governance.
- Existing authorization and guarded write contracts remain intact.
- No print, export, QR, barcode, deploy, runtime-provider, or production runtime
  action is visible or implied.

## 11. Required Validation for This Planning Slice

```bash
bash scripts/architecture/check_label_designer_boundaries.sh
git diff --check
```
