# Studio Nav Composer Link Contract Audit (Docs-Only)

Date: 2026-05-24
Status: Planning/Audit only. No runtime wiring in this slice.
Scope: Define provider-consumer contract for Nav Composer activation without changing source-of-truth ownership.

## Purpose

Document a governed, owner-safe link-candidate contract that allows Studio Nav Composer to preview candidate navigation links without inventing links or mutating runtime artifacts.

## 1) Current Audit Findings

- No concrete Nav Composer route exists in current Studio manifest.
- Navigation/Menu tool card is planned/disabled and has `href = null`.
- Existing Studio nav-linking flow is generated-artifact safety wiring, not a general Nav Composer runtime surface.
- Route/nav nodes are treated as inspect-only in current Studio loadability rules.

## 2) Ownership Rule

- Apps/modules own route/nav/link declarations.
- Nav Composer composes owner-owned navigation resources.
- Studio may discover and present candidates but does not become business navigation truth.
- No fake links. No hardcoded sample business links.

## 3) Proposed Candidate Shape

Each candidate record should include:

- `candidate_id`
- `owner_app`
- `owner_module`
- `source_type`
- `source_key`
- `label`
- `route_path`
- `route_name`
- `current_nav_key`
- `current_url`
- `proposed_url`
- `permission_key`
- `visibility`
- `status`
- `diagnostics`

## 4) Field Semantics (Contract Notes)

- `candidate_id`: stable deterministic id for compare/diff in UI.
- `owner_app`, `owner_module`: artifact owner identity.
- `source_type`: where candidate came from (`route_registry`, `navigation_artifact`, `library_node`, `surface_contract`).
- `source_key`: owner-specific key in source.
- `label`: owner-provided label candidate (or explicit empty if unavailable).
- `route_path`: canonical owner route path candidate.
- `route_name`: optional named route token if present in owner contracts.
- `current_nav_key`: existing navigation key if present.
- `current_url`: current owner navigation URL if present.
- `proposed_url`: preview-only normalized URL suggestion derived from owner data.
- `permission_key`: permission guard key if known from owner contracts.
- `visibility`: visibility rule token (for example `visible_if`).
- `status`: one of `linked`, `missing_nav`, `mismatch`, `ambiguous`, `blocked`.
- `diagnostics`: machine-readable reasons and checks.

## 5) Candidate Providers (Allowed Reads)

Providers may read:

- app/module `navigation.php`
- registered routes
- Studio library/resource explorer metadata
- surface contribution contracts
- route/nav introspection services

Providers must not:

- invent links
- hardcode sample routes
- bypass permissions
- write navigation artifacts

## 6) Nav Composer Consumer Behavior (Phase 1)

Phase-1 Nav Composer must be read-only:

- display candidates only
- show `missing` / `linked` / `mismatch` / `ambiguous` states
- no save/apply button
- no DB writes
- no route mutation
- no `navigation.php` mutation

## 7) Provider vs Consumer Contract Alignment

Required alignment before runtime implementation:

- Candidate key names exactly match this contract.
- Status enum is shared by provider and consumer.
- Diagnostics payload is stable and non-localized keys for machine checks.
- Consumer does not infer ownership beyond provider payload.
- Consumer renders read-only evidence (owner app/module/source).

## 8) Filtering And Safety Gates

Candidate list must preserve governance filters:

- app enabled/disabled state
- permission metadata availability
- route registry consistency
- navigation artifact integrity
- surface contribution validity

Any filtered candidate should emit explicit diagnostic reason instead of silent drop.

## 9) Not In Scope (This Plan)

- no runtime route registration
- no controller wiring
- no persistence writes
- no apply/rollback behavior
- no ownership transfer
- no Core/Shell modifications

## 10) Recommended Activation Path

- Start with read-only Nav Composer shell route.
- Add read-only provider adapter that emits this contract.
- Render candidate table with diagnostics only.
- Add governed mutation phases only after explicit contract approval.
