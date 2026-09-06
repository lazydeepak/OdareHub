# Studio Tool Lifecycle Contract

Status: contract baseline for Studio Tool lifecycle governance.

Purpose: define how Studio Tools are modeled and governed without changing runtime behavior or ownership truth.

This document is architecture guidance only. It does not authorize runtime behavior changes, Core edits, Studio feature expansion, or file movement.

Related extension:

- `docs/architecture/studio-customization-tools-contract.md`
- `docs/architecture/theme-tool-lifecycle-contract.md` (ThemeDoctor Lifecycle Contract)

## 1. Terminology Contract

- Business Apps have Modules.
- Studio has Tools.
- Studio Tools may have module-like lifecycle.
- Tools are governed workers, not runtime business owners.

## 2. Studio Tool vs Business Module Distinction

Business Module:

1. Belongs to a Business App owner.
2. Owns runtime business capability semantics.
3. Participates in runtime delivery contracts as business owner.

Studio Tool:

1. Belongs to Studio owner (`owner = studio`).
2. Performs governed worker functions (analyze, edit, diff, validate, apply plan).
3. Must not become hidden runtime business truth.
4. May have module-like lifecycle controls only for the tool itself, not for business ownership transfer.

## 3. Module-like Lifecycle For Studio Tools

Studio Tools may use lifecycle controls comparable to modules:

1. install / register
2. enable / disable
3. role-gated visibility
4. environment-gated execution
5. risk-gated execution approval

Lifecycle constraint:

- Tool lifecycle state governs tool availability only.
- Tool lifecycle state does not grant business runtime ownership.

## 4. Tool Manifest Shape (Baseline)

Required baseline fields:

- tool_key
- label
- category
- risk_level
- default_enabled
- can_disable
- required_permissions
- allowed_environments
- routes
- views
- services
- owner = studio

Example shape:

```yaml
tool_key: view_editor
label: View Editor
category: composition
risk_level: medium
default_enabled: true
can_disable: true
required_permissions:
  - studio.tools.view_editor.use
allowed_environments:
  - local
  - staging
routes:
  - /apps/studio/tools/view-editor
views:
  - tools/view_editor.php
services:
  - Apps\\Studio\\Services\\StudioViewIntrospectionService
owner: studio
```

## 5. Instance-level Tool Enable/Disable Policy

Per-instance policy must be explicit and auditable. Example:

```yaml
studio_tools:
  view_editor: enabled
  menu_editor: enabled
  theme_tool: enabled
  app_builder: enabled # read-only placeholder
  module_builder: enabled # read-only placeholder
  db_schema_tool: enabled # read-only placeholder
```

Policy requirements:

1. Explicit allow state per tool.
2. Deterministic fallback (`disabled`) for unknown tool keys.
3. State evaluation before route dispatch and before service execution.
4. An enabled placeholder may expose inspection and lifecycle intent only; it must not expose mutation services until its execution guards and governed apply path exist.

## 6. Role and Permission Gates

Gates apply in this order:

1. Tool enabled state gate.
2. Role gate.
3. Permission gate (`required_permissions`).
4. Environment gate.
5. Risk gate.
6. Execution guard.

Rules:

- A failed gate blocks execution and mutation paths.
- Visibility must be suppressed when role/permission gates fail.
- Tool presence in navigation must not imply execution permission.

## 7. Environment Gates

Tool execution must check allowed environments (`allowed_environments`) before any mutating operation.

Examples:

- High-risk tools disabled in production.
- Schema-editing tools disabled outside controlled maintenance windows.

## 8. Risk-level Gates

Risk-level gate maps tool operation classes to approval and guard requirements.

Risk baseline:

1. low: read-only or metadata inspection.
2. medium: governed contract edits with diff and approval checkpoint.
3. high: generated artifact apply, schema-affecting, or publish-adjacent operations.

Required control:

- medium/high risk tools require explicit pre-execution policy checks and audit evidence.

## 9. Execution Guard Requirement

Execution guard is mandatory before invoking tool services or applying writes.

Execution guard must verify:

1. tool exists and is enabled in instance policy.
2. actor role and permissions satisfy manifest requirements.
3. current environment is allowed.
4. risk gate conditions are satisfied.
5. operation target remains within owner-approved boundaries.

## 10. UI Visibility Requirement

UI visibility must be derived from the same gates used for execution.

Rules:

1. No visible action without executable authorization path.
2. Hidden tools must not be callable via unguarded routes.
3. Disabled tools show no mutating UI controls.

## 11. No Hidden Runtime Truth Rule

Studio tools must not create hidden runtime truth.

Forbidden patterns:

1. Runtime decisions based on private Studio-only state not reflected in owner contracts.
2. Silent fallback to ungoverned stores for business ownership semantics.
3. Tool-only flags that alter runtime business behavior outside approved owner artifacts.

Required pattern:

- Runtime consumes approved owner artifacts and governed contracts only.

## 12. Cross-Contract Alignment

This contract aligns with:

- `docs/architecture/studio-operating-contract.md`
- `docs/architecture/studio-change-lifecycle-apply-contract.md`
- `docs/architecture/studio-change-record-schema-baseline.md`
- `docs/architecture/business-app-module-ownership-contract.md`
- `docs/architecture/resolved-runtime-contract-pipeline.md`
- `docs/architecture/studio-customization-tools-contract.md`

## 13. Validation

Read-only validation script:

- `scripts/architecture/check_studio_tool_lifecycle_contract.sh`

This slice is documentation-only.

## 14. ThemeTool Lifecycle Mutation Contract

ThemeTool lifecycle mutation planning is split from this baseline and defined in:

- `docs/architecture/theme-tool-lifecycle-contract.md`

Required anchor:

1. ThemeTool mutation remains blocked until governed contract rollout slices are implemented.
2. ThemeTool lifecycle flow must follow `analyze -> preview -> diff -> approve -> snapshot -> apply -> rollback`.
3. ThemeTool editing workflow ownership does not transfer runtime source-of-truth ownership from Platform/System.
