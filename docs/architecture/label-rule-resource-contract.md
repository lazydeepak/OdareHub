# Label Rule Resource Contract V1

## 1. Objective

Define the architecture, ownership, validation boundaries, and future runtime position of owner-owned label rules.

This is an architecture/documentation contract. It does not authorize rule creation, execution, rendering, or runtime evaluation.

## 2. Current State

Completed chain:

```text
Context
    ↓
Template
    ↓
Preview Renderer
```

Existing contracts:

- Label Resource Contract ✅
- Label Validation Contract ✅
- Label Runtime Contract ✅
- Read-only Preview Renderer ✅

This slice defines the Rules layer — the next link in the architecture chain.

## 3. Future Chain

```text
Context
    ↓
Template
    ↓
Rules          ← this contract
    ↓
Data
    ↓
Render Pipeline
    ↓
Output
```

Rules insert between Template resolution and Data injection. Rules modify presentation behavior only. Rules do not own data, access databases, execute code, perform side effects, or trigger workflows.

## 4. Ownership Model

### Owner owns

- Rule resources — the `rules_key`, `conditions`, `effects`, and `constraints` declarations
- Rule intent — what presentation behavior the rule expresses
- Rule lifecycle — creation, update, disable, retirement per the owner's governance
- Rule assignment — which templates or contexts a rule set applies to

### Platform owns

- Future rule evaluation engine — stateless, read-only, presentation-only
- Future render integration — applies resolved rule effects during rendering
- Output target adapters — converts rule effects into rendered output

### Studio owns

- Authoring — rule set creation, field binding, condition/effect editing
- Validation — static analysis per this contract's validation rules
- Preview — resolved rule effects shown in the read-only preview renderer
- Snapshot/apply — guarded write to owner rule resource paths

### Core owns

- Governance — ACL, ownership enforcement, path-safety validation
- Contracts — this document and its sibling contracts
- Diagnostics — architecture gate invariants, cross-contract consistency

## 5. Canonical Resource Location

Rules must live at:

```text
{OwnerRoot}/Resources/labels/rules/
```

Examples:

```text
apps/Manufacturing/modules/Products/Resources/labels/rules/
apps/SBAIO/modules/Sales/Resources/labels/rules/
plugins/Base/Resources/labels/rules/
```

Studio owns none of these resources. Rule resources are owner-owned files that follow the same resource lifecycle as contexts and templates.

## 6. Rule Resource Purpose

Rules may influence:

| Category | Description |
|---|---|
| **visibility** | Show or hide a block, field, or section based on data values |
| **styling** | Apply highlight, emphasis, or muted presentation to matched items |
| **highlighting** | Draw attention to values meeting criteria (e.g., above/below threshold) |
| **badges** | Attach status badges to blocks or fields (e.g., HOLD, PASS, REVIEW) |
| **warnings** | Show presentation warnings when data values violate business conventions |
| **conditional sections** | Include/exclude entire layout sections based on data presence or value |
| **placeholder substitution** | Override sample/demo placeholder text with conditional alternatives |
| **layout variants** | Choose between layout block arrangements based on data shape |

### Examples

```text
IF quantity > 1000
→ highlight quantity block
```

```text
IF qc_status = "HOLD"
→ show HOLD banner badge
```

```text
IF expiry_date EXISTS
→ show expiry section
```

```text
IF serial_number MISSING
→ show warning banner
```

Rules influence presentation only. They are evaluated after context/template resolution and applied before rendering.

## 7. Explicitly Forbidden

Rules may never:

| Category | Forbidden |
|---|---|
| **Data access** | query databases, execute SQL, perform HTTP requests, call external services |
| **Side effects** | invoke workflows, send notifications, write files, write database records |
| **Output** | call printers, generate QR payloads, generate barcode payloads, produce output targets directly |
| **State mutation** | change business state, change permissions, change ownership, modify user sessions |
| **Code execution** | load arbitrary files, execute PHP, execute JavaScript, evaluate arbitrary expressions |
| **Business logic** | enforce business rules, implement pricing/approval/compliance logic, replace owner decision-making |
| **Runtime bypass** | bypass validation, bypass ACL, bypass owner boundary checks, escalate privileges |

Rules are **declarative presentation resources only**.

## 8. Rule Resource Shape

### Canonical Schema

```json
{
  "schema": "susankhya.label.rule.v1",
  "rule_key": "product_qty_highlight",
  "owner_key": "manufacturing.products",
  "label": "Highlight large quantities",
  "target_scope": {
    "type": "block",
    "block_role": "field_rows",
    "field_keys": ["quantity"]
  },
  "conditions": [
    {
      "source": "field",
      "field_key": "quantity",
      "operator": "greater_than",
      "value": "1000",
      "value_type": "number"
    }
  ],
  "effects": [
    {
      "type": "highlight",
      "style": "emphasis",
      "severity": "info"
    }
  ],
  "constraints": {
    "max_applications": 1,
    "conflict_resolution": "first_match_wins"
  }
}
```

### Required Fields

| Field | Type | Description |
|---|---|---|
| `schema` | string | Must be `"susankhya.label.rule.v1"` |
| `rule_key` | string | Unique key within the owner's rule namespace. Pattern: `[a-z0-9._-]+` |
| `owner_key` | string | The owner key that declares this rule. Must match the owning app/module/plugin key |
| `conditions` | array | Array of condition objects. At least one condition required |
| `effects` | array | Array of effect objects. At least one effect required |

### Optional Fields

| Field | Type | Description |
|---|---|---|
| `label` | string | Human-readable label for Studio UI and diagnostics |
| `description` | string | Longer description of the rule's intent |
| `target_scope` | object or null | Scope constraint (see Section 9). If null, rule applies label-wide |
| `constraints` | object | Resolution constraints: `max_applications`, `conflict_resolution`, `run_after` |
| `tags` | array of strings | Categorization tags for Studio organization |
| `enabled` | bool | Whether the rule is active. Default `true` |
| `priority` | int | Evaluation priority (lower = earlier). Default `100` |
| `metadata` | object | Owner-specific metadata. Must not contain runtime-dynamic fields |

### Versioning

- Schema version is pinned to `"susankhya.label.rule.v1"`.
- Future schema versions must update the version string (e.g., `"susankhya.label.rule.v2"`).
- The renderer must reject unknown schema versions with a clear diagnostic failure.
- Backward-incompatible changes require a new schema version.

### Ownership Metadata

Rules must include `owner_key` matching the filesystem owner path. The `owner_key` must be validated against the `{OwnerRoot}` path at write time and at load time.

### Validation Metadata

Rules may include a `metadata.validation` object:

```json
{
  "metadata": {
    "validation": {
      "created_by": "...",
      "created_at": "...",
      "applied_by_studio": true,
      "last_validated": "...",
      "validation_version": "1"
    }
  }
}
```

This metadata is reserved for Studio write provenance. It must not be used for runtime decisions.

## 9. Target Scopes

### Valid Scope Categories

| Scope `type` | Description | Applicable To |
|---|---|---|
| `template` | Rule applies to the entire template | Template-level effects (layout variant, watermark) |
| `block` | Rule targets a specific layout block by `block_role` | Block visibility, styling, conditional sections |
| `field` | Rule targets one or more specific fields by `field_key` | Field highlighting, badges, warnings |
| `field_group` | Rule targets a group of fields by group identifier | Group-level styling, conditional sections |
| `layout_section` | Rule targets a named layout section | Section visibility, layout variants |
| `label` | Rule applies label-wide (no scope restriction) | Global warnings, global emphasis |

### Scope Validation Rules

- `template` scope requires no additional fields.
- `block` scope requires `block_role` (string). May include optional `field_keys` array to narrow within the block.
- `field` scope requires `field_keys` (array of strings, at least one).
- `field_group` scope requires `group_key` (string).
- `layout_section` scope requires `section_key` (string).
- `label` scope is equivalent to no target_scope or `target_scope: null`.

Scope fields must reference declared `block_role`, `field_key`, `group_key`, or `section_key` values in the target template. References to undeclared keys must produce a validation warning.

## 10. Conditions

### Allowed Condition Types

| Operator | Source | Description |
|---|---|---|
| `exists` | `field` | Field has a non-empty value in the data payload |
| `missing` | `field` | Field has an empty or absent value |
| `equals` | `field` | Field value equals the specified `value` |
| `not_equals` | `field` | Field value does not equal the specified `value` |
| `greater_than` | `field` | Field value (numeric) is greater than `value` |
| `less_than` | `field` | Field value (numeric) is less than `value` |
| `greater_than_or_equal` | `field` | Field value (numeric) is >= `value` |
| `less_than_or_equal` | `field` | Field value (numeric) is <= `value` |
| `contains` | `field` | Field value (string) contains `value` as substring |
| `starts_with` | `field` | Field value (string) starts with `value` |
| `ends_with` | `field` | Field value (string) ends with `value` |
| `matches` | `field` | Field value (string) matches regex pattern `value` |

### Condition Shape

```json
{
  "source": "field",
  "field_key": "quantity",
  "operator": "greater_than",
  "value": "1000",
  "value_type": "number"
}
```

| Field | Required | Description |
|---|---|---|
| `source` | Yes | Currently only `"field"`. Future: `"composite"` for compound conditions |
| `field_key` | Yes | The field to evaluate |
| `operator` | Yes | One of the allowed operators above |
| `value` | Yes | The comparison value (string, will be coerced per `value_type`) |
| `value_type` | No | Type hint: `"string"`, `"number"`, `"boolean"`. Default `"string"` |

### Condition Constraints

- Conditions must reference declared template fields or context fields only.
- Conditions must not reference runtime state, session data, user identity, or external sources.
- Conditions must not reference database tables or queries.
- Regex patterns (`matches`) must be statically validable and must not use `e` (eval) modifier.
- Compound conditions (AND/OR/NOT across multiple conditions) are reserved for a future contract version.

## 11. Effects

### Allowed Effect Types

| Effect `type` | Description | Parameters |
|---|---|---|
| `show` | Ensure the target scope is visible | — |
| `hide` | Hide the target scope | — |
| `highlight` | Apply visual emphasis | `style`: `"emphasis"`, `"muted"`, `"accent"`; `severity`: `"info"`, `"warning"`, `"critical"`, `"success"` |
| `warning` | Display a presentation warning | `message`: string; `severity`: `"info"`, `"warning"`, `"critical"` |
| `badge` | Attach a badge label | `text`: string; `style`: `"info"`, `"warning"`, `"critical"`, `"success"`, `"neutral"` |
| `emphasis` | Apply text-level emphasis | `level`: `"bold"`, `"italic"`, `"underline"`, `"strikethrough"`, `"highlight"` |
| `layout_variant` | Switch to an alternative layout block arrangement | `variant_key`: string (must match a declared variant in the template) |
| `placeholder` | Replace a placeholder value | `field_key`: string; `value`: string (static replacement only) |

### Effect Shape

```json
{
  "type": "highlight",
  "style": "emphasis",
  "severity": "warning"
}
```

### Effect Constraints

- Effects must reference target scope elements that exist in the resolved template.
- `layout_variant` effects must reference declared variant keys in the template.
- `placeholder` effects must reference declared template fields.
- Effects must not perform side effects (no writes, no DB access, no service calls).
- Effects must not generate output independently (no printing, no QR/barcode generation).
- Multiple effects on the same scope must have explicit conflict resolution strategy.

## 12. Validation Rules

### Severity Model

Aligned with the Label Validation Contract:

| Severity | Meaning |
|---|---|
| `PASS` | Check passed |
| `WARN` | Non-blocking concern |
| `FAIL` | Rule is invalid, render blocked |
| `ERROR` | Critical validation failure, render blocked |

### Validation Checks

| ID | Check | Severity | Description |
|---|---|---|---|
| R001 | Schema version | FAIL | Rule schema must be `"susankhya.label.rule.v1"` |
| R002 | Rule key present | FAIL | `rule_key` must be a non-empty string matching `[a-z0-9._-]+` |
| R003 | Rule key unique | FAIL | No duplicate `rule_key` within the same owner |
| R004 | Owner key present | FAIL | `owner_key` must match the owning app/module/plugin key |
| R005 | Owner boundary | FAIL | Rule resource path must be under `{OwnerRoot}/Resources/labels/rules/` |
| R006 | Conditions present | FAIL | At least one condition required |
| R007 | Conditions valid | FAIL | Each condition must have valid `source`, `field_key`, `operator` |
| R008 | Field exists | WARN | Condition field_key should exist in context fields |
| R009 | Operator allowed | FAIL | Operator must be in the allowed operators list |
| R010 | Effects present | FAIL | At least one effect required |
| R011 | Effect type allowed | FAIL | Effect type must be in the allowed effect types list |
| R012 | Target scope valid | WARN | If target_scope is provided, its type must be valid |
| R013 | Scope field exists | WARN | target_scope field_keys should exist in template fields |
| R014 | No runtime coupling | FAIL | Must not reference DB, services, sessions, or external state |
| R015 | No forbidden keywords | FAIL | Must not contain runtime execution keywords (see Section 7) |
| R016 | File path convention | FAIL | File must be under `{OwnerRoot}/Resources/labels/rules/` with `.json` extension |
| R017 | JSON parseable | FAIL | Rule file must be valid JSON |
| R018 | Context/template compatibility | WARN | Rule conditions should reference fields that exist in the target context or template |
| R019 | Regex safe | FAIL | `matches` operator regex must not contain `e` modifier |

### Render-Blocking Failures

R001, R002, R003, R004, R005, R006, R007, R009, R010, R011, R014, R015, R016, R017, and R019 are render-blocking. If any produce `FAIL` or `ERROR`, the rule must not be applied during rendering.

## 13. Runtime Placement

### Position In The Runtime Chain

```text
Context
    ↓  (resolve context metadata, field definitions, allowed sources)
Template
    ↓  (resolve layout, field bindings, label size)
Rules          ← evaluated here
    ↓  (apply conditional presentation modifications)
Data
    ↓  (inject owner-provided or provider-resolved data payload)
Render Pipeline
    ↓  (render resolved label with rule effects applied)
Output
```

### Evaluation Protocol

1. Load Context and Template per the Label Runtime Contract.
2. Load all rule sets referenced by `rules_refs` in the template or context.
3. Validate each rule set per Section 12. Skip rules with render-blocking failures.
4. Sort active rules by `priority` (lower = earlier). Default priority: `100`.
5. Evaluate conditions against the data payload **after** data is injected (Phase 1: owner direct data).
6. Apply matching effects in priority order.
7. Resolve conflicts according to the rule's `conflict_resolution` strategy.
8. Pass resolved presentation state to the render pipeline.

### Rule Constraints At Runtime

- Rules evaluate conditions against the data payload **only**. No DB queries, no service calls, no HTTP requests.
- Rules produce a resolved presentation state only. No side effects, no state changes.
- Rules must not bypass the pre-render validation checklist defined in the Label Runtime Contract.
- Rule evaluation failure (e.g., unparseable condition, missing field) must produce a diagnostic warning but must not cause the render to fail entirely.
- Rule evaluation must be deterministic: same input data must always produce the same resolved presentation.

### Rule Lifecycle At Runtime

- Rules are loaded from owner resource files at render time, same as contexts and templates.
- Rule resources follow the same staleness/mtime detection as contexts and templates.
- Runtime must validate that `rules_refs` entries exist before attempting to load them.
- Missing rule resources referenced by `rules_refs` must produce a `FAIL` diagnostic.

## 14. Studio Boundary

### Studio May

- Author rule resources — create rule JSON files with conditions and effects
- Preview resolved rule effects in the read-only label preview renderer
- Validate rule resources against this contract's validation rules
- Snapshot rule resources before write (backup current content)
- Apply rule resources to owner paths via guarded write (snapshot → diff → validation → write → diagnostics)
- Diagnose rule resources — check schema, owner boundary, field references, duplicate keys

### Studio Must Not

- Execute rules in production runtime
- Invoke workflows or side effects during rule authoring or preview
- Invoke printers, output engines, or QR/barcode generators
- Become a business rules engine
- Bypass owner boundary during rule resource creation
- Bypass ACL or permission checks
- Store rule resources in Studio-owned permanent storage
- Load or execute arbitrary code from rule resources
- Generate rule conditions or effects from runtime data

## 15. Non-Goals

This contract does not:

- Authorize rule creation or mutation in any code path
- Authorize rule execution, evaluation, or rendering
- Authorize printing, QR generation, barcode generation, or output engine implementation
- Define compound conditions (AND/OR/NOT) — reserved for a future contract version
- Define rule set composition across multiple owners
- Define rule version migration or upgrade paths
- Define rule UI in Studio (condition builder, effect picker) — reserved for a future implementation slice
- Define renderer-side effect implementation (highlight rendering, badge rendering)
- Authorize database access, data provider contracts, or business logic in rules
- Override or bypass the Label Runtime Contract or Label Validation Contract

## 16. Relationship To Existing Contracts

This contract extends the label architecture family. The following sibling contracts are referenced:

- `docs/architecture/label-designer-operating-contract.md` — Operating context, owner paths, label lifecycle
- `docs/architecture/label-resource-contract.md` — Owner resource family, Label Rule Set Resource schema foundation
- `docs/architecture/label-validation-contract.md` — Validation severity model, render-blocking rules
- `docs/architecture/label-runtime-contract.md` — Runtime chain position, pre-render validation, data policy
- `docs/architecture/label-designer-apply-snapshot-safety-contract.md` — Snapshot/apply lifecycle for all owner resource types
- `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md` — Template-specific apply lifecycle
- `docs/architecture/label-render-pipeline-contract.md` — Platform render pipeline, Resolved Label Model, render stages, and rule interaction within the pipeline

## 17. Cross-Reference Updates

The following contracts have been updated to reference this document:

- `label-designer-operating-contract.md` — Section 14 (Next Contract Sequence) now includes Label Rule Resource Contract
- `label-resource-contract.md` — Section 4 (Label Rule Set Resource) and Section 7 (Future Apply Boundary) reference this contract
- `label-validation-contract.md` — Section 10 (Relationship To Existing Contracts) now includes this contract; Section 11 updated
- `label-runtime-contract.md` — Section 7 (Rule Resource Placement) references this contract; Section 11 updated
- `label-designer-apply-snapshot-safety-contract.md` — Section 11 now includes this contract reference
- `label-designer-template-apply-snapshot-safety-contract.md` — Section 11 now includes this contract reference
- `label-render-pipeline-contract.md` — Part G (Rule Interaction) and Part K (Cross-References) reference this contract

## 18. Next Contract Sequence

After this contract is accepted:

1. **Rule Resource Implementation** — Guarded rule creation following context/template patterns: preview, snapshot, validation, owner-path write, diagnostics
2. **Read-only Rule Preview in Label Preview Renderer** — Apply resolved rules to the existing preview renderer to show conditional effects
3. **Platform Rule Evaluation Engine** — Stateless, read-only, presentation-only condition evaluator
4. **Render Integration** — Render pipeline consumes resolved rule effects
