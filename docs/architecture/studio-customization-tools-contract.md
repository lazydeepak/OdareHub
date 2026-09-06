# Studio Customization Tools Boundary Contract

Status: contract baseline for Studio customization tools.

Purpose: define ownership boundaries and governance for customization tooling without changing runtime behavior.

This document is architecture guidance only. It does not authorize Core edits, runtime behavior changes, or Studio feature implementation.

Authority:

- `docs/architecture/studio-tool-lifecycle-contract.md`
- `docs/architecture/theme-tool-lifecycle-contract.md`

## 1. Core Distinction

- Customization tools are Studio Tools, not System Tools.
- Business Apps have Modules.
- Studio has Tools.
- Studio Tools may have module-like lifecycle.
- Tools are governed workers, not runtime business owners.

## 2. Responsibility Split

- System Tools validate/rebuild/repair/audit customization output.
- Shell renders approved resolved theme and menu contracts.
- Platform controls permissions and instance policy.

Studio Tools:

1. Analyze and edit owner-approved customization resources.
2. Produce governed diffs and apply plans.
3. Persist snapshot and audit evidence.

System Tools:

1. Validate customization output.
2. Rebuild delivery artifacts.
3. Repair drift and verify contract integrity.
4. Audit lifecycle compliance and provenance.

Shell:

1. Renders approved resolved theme and menu contracts.
2. Does not consume unapproved Studio drafts.

Platform:

1. Controls permissions and instance policy.
2. Evaluates tool enablement and execution authorization gates.

Public assets:

1. Public assets are delivery output, not source truth.

Storage:

1. Storage may hold snapshots, audit records, and history.
2. Storage must not hold hidden design truth that bypasses owner contracts.

## 3. Customization Tool Catalog (Future Tools)

### 3.1 ThemeTool

- owner = studio
- editable resources:
  - owner theme token contracts
  - approved app/module theme mapping contracts
- forbidden resources:
  - Core runtime styling internals
  - direct mutation of delivery-output-only paths as source truth
- risk level: medium
- default enabled policy: enabled
- required permission: `studio.tools.theme_tool.use`
- output contract: governed theme token contract + apply record
- required validation:
  - token schema validation
  - owner boundary validation
  - asset publish readiness check
- rollback/snapshot requirement: required before apply

ThemeTool lifecycle planning anchor:

1. v0: preview-only (current guarded state)
2. v1: governed draft lifecycle operations (Deferred pending the Appearance domain contract)
3. v2: approved apply/delete/default/rollback operations (Deferred)

Theme ownership split:

1. ThemeTool owns editing workflow and draft artifacts.
2. Platform/System owns approved instance theme registry truth.
3. Shell consumes resolved approved active/default theme only.
4. `public/assets` remains delivery output only.

Reference:

- `docs/architecture/theme-tool-lifecycle-contract.md`

### 3.2 CssTool

- owner = studio
- editable resources:
  - owner app/module CSS source contracts
  - governed selector-scoping metadata
- forbidden resources:
  - Shell wrapper-only contract areas not assigned to target owner
  - Core styling internals
- risk level: medium
- default enabled policy: enabled
- required permission: `studio.tools.css_tool.use`
- output contract: owner-scoped CSS change contract + diff + apply record
- required validation:
  - owner-css boundary validation
  - selector-scope validation
  - localization/theme token policy validation
- rollback/snapshot requirement: required before apply

### 3.3 MenuEditor

- owner = studio
- editable resources:
  - owner navigation/menu contracts (`navigation.php` and governed nav artifacts)
- forbidden resources:
  - ACL authorization truth
  - ad-hoc runtime menu truth bypass stores
- risk level: high
- default enabled policy: enabled
- required permission: `studio.tools.menu_editor.use`
- output contract: governed menu/navigation contract delta + resolved preview
- required validation:
  - route ownership validation
  - wrapper confinement validation
  - role-visibility contract validation
- rollback/snapshot requirement: required before apply

### 3.4 BrandingTool

- owner = studio
- editable resources:
  - governed branding contracts (logos, brand tokens, naming metadata)
- forbidden resources:
  - direct runtime mutation of unrelated owner business artifacts
  - hidden branding toggles outside approved contracts
- risk level: medium
- default enabled policy: enabled
- required permission: `studio.tools.branding_tool.use`
- output contract: branding contract package + publish-ready delivery plan
- required validation:
  - branding asset policy validation
  - delivery-output provenance validation
  - accessibility and format checks
- rollback/snapshot requirement: required before apply

### 3.5 FontTool

- owner = studio
- editable resources:
  - approved font contract references and token assignments
- forbidden resources:
  - unmanaged runtime font injection paths
  - direct Core typography overrides
- risk level: medium
- default enabled policy: enabled
- required permission: `studio.tools.font_tool.use`
- output contract: font contract + token mapping delta
- required validation:
  - font allowlist/license gate
  - performance budget validation
  - fallback chain validation
- rollback/snapshot requirement: required before apply

## 4. Owner Preservation Rules

How app/module-owned resources stay owner-owned:

1. Studio writes through owner contracts only.
2. Studio apply operations must emit owner-targeted artifacts with provenance.
3. Runtime reads owner-approved resolved contracts, not Studio drafts.
4. Studio state cannot become independent runtime source truth.

## 5. Public Asset and Storage Rules

Why `public/assets` must not become source truth:

1. It is delivery output and can be regenerated.
2. It can be optimized/transformed for delivery, so it is not canonical authoring truth.
3. Treating it as source truth would bypass governance and owner contract lineage.

Storage policy:

1. Storage may hold snapshots, audit entries, and lifecycle history.
2. Storage must not hold hidden design truth that changes runtime behavior outside owner-approved contracts.

## 6. Specific Answers

1. Which customization resources belong to Studio tools?
   - Theme token contracts, owner-scoped CSS contracts, owner navigation/menu contracts, branding contracts, and font token/reference contracts.

2. Which validation/rebuild actions belong to System Tools?
   - Contract validation, resolved output validation, artifact rebuild/publish, drift repair checks, and audit/provenance verification.

3. Which runtime rendering responsibilities belong to Shell?
   - Rendering approved resolved theme/menu contracts and wrapper-level presentation consumption only.

4. Which authorization/policy responsibilities belong to Platform?
   - Permission grants, instance tool enable/disable policy, and execution gate authorization decisions.

5. How do app/module-owned CSS and menu resources stay owner-owned?
   - Studio edits only through owner-targeted governed contracts with provenance and apply records; runtime consumes approved owner/resolved contracts.

6. Why public/assets must not become source truth?
   - Public assets are delivery output, may be regenerated/transformed, and cannot replace canonical owner/governed source contracts.

7. What is the first safe implementation step after this contract?
   - Implement a read-only execution guard and visibility gate evaluator for ThemeTool/CssTool/MenuEditor/BrandingTool/FontTool using existing Platform policy and Studio manifest semantics, without enabling mutations.

## 7. Validation

Read-only validation script:

- `scripts/architecture/check_studio_customization_tools_contract.sh`

This slice is documentation-only.
