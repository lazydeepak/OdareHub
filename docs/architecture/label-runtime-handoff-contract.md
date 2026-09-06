# Label Runtime Handoff Contract

Status: Planning contract only. This document defines the future handoff from owner-owned label resources to a shared runtime pipeline. It does not authorize runtime implementation, rendering, printing, QR generation, route activation, database runtime providers, or owner-app integration.

---

## 1. Purpose

Label Designer now produces owner-owned context, template, and rule resources. A future runtime needs one deterministic boundary for accepting an owner invocation, resolving those resources, validating business data, applying presentation-only rules, and handing a resolved request to Platform output adapters.

This contract is the canonical design-time-to-runtime handoff. Existing resource, validation, rule, and render-pipeline contracts remain authoritative for their narrower subjects.

## 2. Runtime Ownership Boundary

### 2.1 Studio

Studio owns design-time resource authoring, preview, migration, diagnostics, diff, guarded apply, and rollback evidence only.

Studio must not:

- invoke production label output;
- supply runtime business data;
- become a runtime resource registry or provider;
- implement the Platform renderer or print pipeline;
- add runtime routes from Label Designer.

### 2.2 Owner App Or Module

The owner app/module owns:

- the business decision to request a label;
- the business object and data lookup;
- construction of the approved `data_payload`;
- selection of owner, context, template, rule enablement, and output target;
- the owner-facing permission and workflow that initiates the request.

The owner supplies the data payload. Label Designer must not fetch arbitrary runtime database data on its behalf.

### 2.3 Platform

Platform owns the future shared resource resolution, rule evaluation, render, export, and print pipeline. Platform receives a validated `LabelRuntimeRequest`, produces a deterministic result, and delegates approved output targets to Platform-owned adapters.

Platform must remain owner-agnostic. It must not make owner business decisions or discover arbitrary owner data.

### 2.4 Core

Core owns governance contracts for owner identity, lifecycle, canonical paths, permissions, and audit requirements. This planning contract does not authorize a Core code change.

## 3. LabelRuntimeRequest

The future runtime boundary accepts one immutable `LabelRuntimeRequest`:

```json
{
  "owner_key": "Manufacturing/Products",
  "context_key": "manufacturing.product.label",
  "template_key": "manufacturing.product.label.100x50_mm",
  "rules_enabled": true,
  "data_payload": {
    "product_name": "Example product",
    "expiry_date": "2027-06-30"
  },
  "output_target": "preview_html",
  "requested_by": "user:42",
  "request_source": "manufacturing.products.product-detail",
  "trace_id": "lbl_01J...",
  "dry_run": true,
  "preview_mode": true
}
```

| Field | Required | Contract |
|---|---|---|
| `owner_key` | Yes | Canonical owner key; determines the owner root. |
| `context_key` | Yes | Context resource key under the owner root. |
| `template_key` | Yes | Template resource key under the owner root. |
| `rules_enabled` | Yes | Boolean. When false, no rules are loaded or applied. |
| `data_payload` | Yes | Owner-supplied field/value map; no query or provider instructions. |
| `output_target` | Yes | One approved target identifier from Section 7. |
| `requested_by` | Yes | Authenticated actor or governed service identity. |
| `request_source` | Yes | Stable owner route, action, job, or service identifier. |
| `trace_id` | Yes | Unique correlation identifier prepared before rendering. |
| `dry_run` | Yes | When true, no print or external output side effect is permitted. |
| `preview_mode` | Yes | When true, output is inspectable preview output only. |

Callers must not provide filesystem paths, SQL, credentials, permission overrides, Studio snapshot identifiers, or executable callbacks.

## 4. Required Pre-Render Validation Chain

Every future render, export, or print attempt must complete these checks in order:

1. Owner exists.
2. Owner lifecycle is allowed for label runtime use.
3. Context resolves from the canonical owner-contained context path.
4. Template resolves from the canonical owner-contained template path.
5. Context and template metadata match the request owner.
6. Template `context_ref` is compatible with the selected context.
7. When `rules_enabled` is true, every selected or matching rule reference resolves under the same owner.
8. `data_payload` contains every required context/template field.
9. Every payload field is allowed by the context.
10. Resources and rules contain no forbidden runtime behavior.
11. The requested output target is allowed for the caller and current mode.
12. The actor and request source satisfy permissions.
13. An audit trace containing `trace_id` is prepared before pipeline execution.

No output adapter may run when a blocking check fails. Validation results must be deterministic for the same request and resource versions.

## 5. Data Policy

- Runtime must not fetch arbitrary DB data from Label Designer.
- The owner app/module supplies `data_payload` from its governed business service.
- Label Designer Phase 1 database discovery is bootstrap/design-time assistance only.
- Discovery metadata is not a runtime connection, query, provider, or authorization.
- Approval of a DB-backed runtime provider requires a separate future contract and architecture gate.
- Platform must reject SQL, table names used as query instructions, credentials, or provider callbacks embedded in a runtime request or label resource.

## 6. Rule Evaluation Boundary

Rules may affect presentation only. The runtime allowlist remains exactly:

1. `show_badge`
2. `hide_field`
3. `show_warning`
4. `set_style_token`

Rules must not perform DB access, HTTP requests, file reads outside governed owner resource loading, file writes, permission changes, application state mutation, print execution, or QR generation.

### 6.1 Deterministic Evaluation Order

1. Resolve enabled rules compatible with the request owner, context, and template.
2. Reject or skip invalid rules according to the deterministic failure model before applying effects.
3. Sort rules by numeric `priority`, lower values first; missing priority is `100`.
4. Break equal-priority ties by ascending `rule_key`.
5. Evaluate each rule's conditions in declared order against `data_payload` only.
6. Apply a matching rule's effects in declared order.
7. Record each applied rule and effect in the in-memory resolved presentation state and audit result.

### 6.2 Conflict Behavior

The initial conflict strategy is `first_match_wins`. Once a target/effect category has been set by the first matching rule in deterministic order, a later conflicting effect must not replace it. Independent effects on different targets or effect categories may all apply.

An unknown conflict strategy is invalid. Future strategies require a versioned contract change; callers and resources may not invent them.

## 7. Output Target Model

These identifiers define future capability categories; none is implemented or authorized by this slice.

| Target | Status | Meaning |
|---|---|---|
| `preview_html` | First read-only target | Inspectable HTML with no print side effect. |
| `print_html` | Future | Print-oriented HTML produced by Platform. |
| `pdf` | Future | Platform-generated PDF export. |
| `zpl/thermal` | Later | Thermal-printer command output behind a dedicated adapter contract. |
| `image/png` | Later | Raster image output behind a dedicated adapter contract. |

`dry_run` or `preview_mode` requests must reject targets with physical or external output behavior. Target availability must be explicit; unsupported targets are not silently substituted.

## 8. Deterministic Failure Model

Runtime failures return a stable code, human-readable message, `trace_id`, and no partial output:

| Code | Meaning |
|---|---|
| `context_not_found` | The context cannot be resolved under the canonical owner root. |
| `template_not_found` | The template cannot be resolved under the canonical owner root. |
| `rule_not_found` | A required enabled rule reference cannot be resolved. |
| `owner_mismatch` | Request and resource ownership metadata/path do not agree. |
| `payload_missing_required_field` | A required context/template field has no payload value. |
| `payload_field_not_allowed` | The payload includes a field not allowed by the context. |
| `forbidden_behavior` | A resource or request attempts prohibited runtime behavior. |
| `permission_denied` | The actor or request source lacks permission. |
| `output_target_not_allowed` | The target is unknown, unavailable, or disallowed for the current mode. |

The first blocking failure in validation-chain order is the primary code. All detected failures may be retained as diagnostics, but ordering must remain stable.

## 9. Audit Model

Before execution, the future runtime prepares an audit record. After completion or rejection, it records:

- `owner_key`
- `context_key`
- `template_key`
- rules applied
- output target
- `requested_by`
- timestamp
- `trace_id`
- result status

Audit preparation must not mutate owner resources. Audit persistence, retention, and storage ownership require a future implementation contract.

## 10. Explicit Non-Goals

This slice adds:

- no runtime route;
- no QR route wiring or QR generation;
- no print button wiring or print execution;
- no DB runtime provider approval;
- no Platform renderer implementation;
- no Manufacturing page integration;
- no context, template, or rule mutation;
- no runtime implementation in Label Designer.

## 11. Contract Relationships

- `docs/architecture/label-runtime-contract.md` defines the broader runtime baseline.
- `docs/architecture/label-resource-contract.md` defines owner-owned resource placement.
- `docs/architecture/label-validation-contract.md` defines resource validation.
- `docs/architecture/label-rule-resource-contract.md` defines declarative rule resources.
- `docs/architecture/label-render-pipeline-contract.md` defines the future Platform pipeline.

Any future implementation must update these contracts together when it changes a shared term, field, target, failure code, or ownership boundary.
