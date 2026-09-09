# Label Resource Contract

Status: Architecture contract baseline. Guarded owner-owned Label Context create is authorized in Label Designer for this slice. Template/rule create-edit-apply, runtime behavior changes, renderer/export/print behavior, migrations, SQL builders, and Core edits remain unauthorized.

This contract defines the owner-owned resource shapes that future OdareHub Label Designer work must target. Label Designer may discover and design these resources, but it must not become their source of truth. Source truth remains under the owning app, module, or plugin.

## 1. Owner Resource Family

Label resources live under the owner root:

| Resource | Standard path | Meaning |
|---|---|---|
| Label Context Resource | `{OwnerRoot}/Resources/labels/contexts/` | Owner, business purpose, allowed fields, data source boundary, and print/use location |
| Label Template Resource | `{OwnerRoot}/Resources/labels/templates/` | Visual layout/design bound to one label context |
| Label Rule Set Resource | `{OwnerRoot}/Resources/labels/rules/` | Reusable owner-owned conditional style and business presentation rules that templates or contexts may attach |

Owner roots may be app roots, module roots, or plugin roots. Studio must treat these as owner artifacts.

## 2. Label Context Resource

A Label Context Resource declares one label type's business meaning.

Required intent:

- Identify the owner app/module/plugin.
- Declare a stable context key.
- Declare the business purpose.
- Declare allowed fields.
- Declare the data source boundary.
- Declare where the label is printed or used.
- Reference the default template.
- Optionally reference owner-owned rule sets.

Canonical fields:

| Field | Required | Meaning |
|---|---:|---|
| `schema` | Yes | Resource schema identifier, e.g. `odarehub.label.context.v1` |
| `context_key` | Yes | Stable unique key, e.g. `manufacturing.pallet` |
| `owner` | Yes | Owner metadata: app/module/plugin and owner root |
| `purpose` | Yes | Business purpose of the label |
| `allowed_fields` | Yes | Field keys and metadata allowed on this label |
| `data_source_boundary` | Yes | Owner-controlled data source boundary; not raw global DB access |
| `print_locations` | Yes | Owner runtime surfaces/routes/actions where printing is triggered |
| `template_ref` | Yes | Default owner-owned template file reference |
| `rules_refs` | No | Owner-owned rule set references |
| `permissions` | No | Permissions required to view/print/design this label |

Example keys:

```text
manufacturing.pallet
manufacturing.part
inventory.bin_location
lazypos.product_price
dispatch.delivery_case
```

## 3. Label Template Resource

A Label Template Resource defines visual layout for one label context. It does not own business meaning.

Required intent:

- Bind to exactly one label context.
- Declare visual blocks and field placement.
- Declare barcode/QR/plain text slots as layout features.
- Consume only fields allowed by the label context.
- Reference rule sets only by owner-owned `rules_ref`.

Canonical fields:

| Field | Required | Meaning |
|---|---:|---|
| `schema` | Yes | Resource schema identifier, e.g. `odarehub.label.template.v1` |
| `template_key` | Yes | Stable template key |
| `context_key` | Yes | Label context this template is bound to |
| `owner` | Yes | Owner metadata matching or delegating from the context owner |
| `size` | Yes | Label size and orientation metadata |
| `blocks` | Yes | Layout blocks, rows, slots, and text/image/barcode placements |
| `field_bindings` | Yes | Mapping from allowed field keys to template slots |
| `rules_refs` | No | Rule sets applied to presentation decisions |
| `preview_sample` | No | Owner-approved sample data for read-only preview |

Template files are owner artifacts. Studio drafts are proposals until approved and handed back.

## 4. Label Rule Set Resource

A Label Rule Set Resource declares reusable owner-owned presentation rules. It is not a general business automation engine.

Rule sets may express:

- Conditional visibility of template blocks.
- Conditional text/formatting choices.
- Owner-approved presentation warnings.
- Print constraints, such as copy limits or required fields.
- Context/template compatibility rules.

Rule sets must not:

- Mutate business data.
- Grant permissions.
- Trigger printing.
- Call arbitrary runtime routes.
- Become a parallel workflow engine.
- Read DB tables outside the owner-approved context boundary.

Canonical fields:

| Field | Required | Meaning |
|---|---:|---|
| `schema` | Yes | Resource schema identifier, e.g. `odarehub.label.rules.v1` |
| `rules_key` | Yes | Stable rule set key |
| `owner` | Yes | Owner metadata |
| `applies_to` | Yes | Contexts/templates this rule set may attach to |
| `conditions` | Yes | Field-based condition declarations |
| `effects` | Yes | Presentation-only effects |
| `constraints` | No | Print/use constraints enforced by owner runtime or Platform pipeline |

## 5. Example Resource Map

| Context key | Likely owner | Context path | Template path | Rules path |
|---|---|---|---|---|
| `manufacturing.pallet` | Manufacturing app/module | `Resources/labels/contexts/manufacturing.pallet.*` | `Resources/labels/templates/manufacturing.pallet.*` | `Resources/labels/rules/manufacturing.pallet.*` |
| `manufacturing.part` | Manufacturing Products module | `Resources/labels/contexts/manufacturing.part.*` | `Resources/labels/templates/manufacturing.part.*` | `Resources/labels/rules/manufacturing.part.*` |
| `inventory.bin_location` | Inventory app/module | `Resources/labels/contexts/inventory.bin_location.*` | `Resources/labels/templates/inventory.bin_location.*` | `Resources/labels/rules/inventory.bin_location.*` |
| `lazypos.product_price` | LazyPOS app/module | `Resources/labels/contexts/lazypos.product_price.*` | `Resources/labels/templates/lazypos.product_price.*` | `Resources/labels/rules/lazypos.product_price.*` |
| `dispatch.delivery_case` | Dispatch owner app/module | `Resources/labels/contexts/dispatch.delivery_case.*` | `Resources/labels/templates/dispatch.delivery_case.*` | `Resources/labels/rules/dispatch.delivery_case.*` |

These are examples only. Guarded context create may produce owner files under:

```text
{OwnerRoot}/Resources/labels/contexts/{context_key}.label-context.json
```

Template and rule files are not created in this slice.

## 6. Read-Only Discovery Boundary

Label Designer may safely discover:

- Which owners already have `Resources/labels/`.
- Existing context/template/rule filenames.
- Counts and owner-relative paths.
- Installed/known owners from owner manifests or Studio app registry.
- Owner-scoped candidate DB tables/views by conservative naming and prefix conventions.
- Fields/columns for candidate DB sources after owner-scoped filtering.

Candidate DB sources are not approved label sources. No label context is created yet, no field selection is saved, and owner approval/context creation comes later.

Label Designer may provide a guarded context creation flow:

- Select owner/app/module/plugin.
- Choose label purpose.
- Choose candidate DB source.
- Select fields.
- Preview illustrative label context JSON.
- Preview generated context JSON before apply.
- Require explicit confirmation before create.
- Create snapshot metadata before owner file write.
- Write only to `{OwnerRoot}/Resources/labels/contexts/{context_key}.label-context.json`.

The guarded flow must not write templates or rules, must not overwrite existing context files, and must not write outside the selected owner root.

Label Designer may also provide a disabled/read-only template creation preview flow:

- Select an existing owner-owned label context.
- Choose a label size.
- Select fields from the selected context's allowed fields.
- Preview illustrative template JSON.

This preview flow must not write template files, must not create template routes, and must keep template create/apply disabled until snapshot/diff/validation/confirmation safeguards are implemented for templates.

Label Designer must not discover in this slice:

- Arbitrary global DB tables for unrestricted browsing.
- Runtime field metadata from sources outside the selected owner boundary.
- Print renderer capabilities.
- Owner data provider contracts.

Label Designer must not:

- Create template/rule files.
- Edit template/rule files.
- Save/apply drafts.
- Parse and validate owner label file content as runtime truth.
- Mark candidate DB sources as globally approved.
- Bind labels to arbitrary tables.
- Move Manufacturing QR runtime code.

## 7. Future Apply Boundary

Future direct writes to owner label resources require:

- Owner-scoped analysis.
- Diff preview.
- Approval.
- Backup/snapshot before writing.
- Future direct edit must target selected owner-owned context/template/rule resources only.
- Path-safety validation.
- Rollback evidence.
- Server-side authorization.
- Handover record.

The detailed apply lifecycle, snapshot location, target path rules, snapshot metadata, and diagnostics are defined by:

- `docs/architecture/label-designer-apply-snapshot-safety-contract.md`
- `docs/architecture/label-designer-template-apply-snapshot-safety-contract.md`
- `docs/architecture/label-validation-contract.md` (context/template validation rules before preview, render, print, or runtime use)
- `docs/architecture/label-runtime-contract.md` (how owner label resources are consumed at runtime)
- `docs/architecture/label-rule-resource-contract.md` (owner-owned label rule resource architecture, validation, and runtime placement)
- `docs/architecture/label-resource-metadata-contract.md` (metadata migration apply workflow for legacy resources)
- `docs/architecture/label-render-pipeline-contract.md` (Platform render pipeline that consumes owner resources at runtime)

This document does not authorize that implementation.
