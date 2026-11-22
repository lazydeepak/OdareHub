# IPM-009 Product Detail Read Boundary — Blocker Assessment

**Status:** Blocked for activation; no unsafe partial migration made.

## Selected operation and aliases

The selected operation is the existing Products Part 360 detail handler:

- canonical `GET /apps/manufacturing/products/360`;
- compatibility alias `GET /products/360`, which redirects while preserving the
  query string;
- both reach `ProductsController::part360()`.

The canonical route retains `products.360.view` ACL admission. The handler
currently calls `Part360Service::build($id)`.

## Blocker 1 — no organization-context route contract

The detail route currently accepts only the Product `id` (plus presentation
query state). It does not establish or forward a selected `organization_id`.
Existing detail links also do not carry a validated organization selection.
`ProductOrganizationScope::resolve()` intentionally requires valid selection
input and is not an implicit unrestricted fallback.

Adding a hard requirement for a new query value without updating the existing
caller contract would turn normal existing detail links into unauthorized
failures. Inferring organization from Product ownership, creator, assignment,
or `item_ref` would violate the accepted Platform membership contract.

## Blocker 2 — Part 360 is a multi-query projection

The detail response is not only the primary Product row. `Part360Service` also
reads:

- demand, orders, pre-orders, stock ledger, planned supply, production,
  QC, dispatch, and handoff records keyed by `product_id`;
- part molds and material mappings;
- machine catalog rows used for mold-fit and active-machine projections;
- a global active material catalog for the material selector;
- assigned and available user responsibility data.

The related operational tables do not carry a Product organization ownership
column in their current migrations. Their product linkage can be meaningful
only after an authorized Product identity is established, but that does not
by itself authorize the global material, machine, or user catalog queries.
There is no existing organization-aware contract for those secondary
projections. Filtering only the primary Product row would therefore not meet
IPM-009's complete detail-response boundary.

## Secondary dependency matrix

| Part 360 source/query | Current relationship | Current ownership evidence | Safe derivation from Product ownership |
|---|---|---|---|
| `products` + stock-balance subquery | Primary `p.id`; ledger rows by `product_id` | Product has nullable `organization_id`; ledger has no organization column | Primary row can be admitted through `ProductReadService`; ledger is only safely reachable after that identity is authorized |
| `daily_orders`, `pre_orders` | `product_id` | Neither migration has organization ownership | Product linkage gives a bounded relationship, but no independent organization proof or foreign key |
| `production_plans`, `production_entries` | `product_id`, plus `machine_id` | No organization column; workflow/audit fields are local records | Product linkage bounds the rows, but machine and workflow records remain independently unowned |
| `qc_plans`, `qc_entries` | `product_id`, plus links to orders/plans/entries | No organization column | Product linkage bounds the rows; related record ownership and cross-link integrity are not tenant contracts |
| `dispatch_entries` | `product_id`, plus order/plan/QC references | No organization column; multiple cross-stage references | Product linkage is insufficient to establish ownership of every referenced workflow record |
| `handoff_tracking` | Entity type/ID derived from the preceding order/production/QC/dispatch rows | No Product or organization column; generic entity table | It inherits only an application-level chain, not an organization authorization proof |
| `part_molds`, `part_material_map` | Direct `product_id`; material map also has `material_id` | Product module tables have no organization column; no ownership FK | Product linkage can bound direct rows, but material identity has no organization contract |
| `materials` / `materialOptions()` | `material_id` join for mapped rows; all active materials for selector | Current material schema has no organization ownership in this boundary | Not safely derivable; selector exposes a global catalog in the response |
| `machines` / machine-fit and usage queries | `active_machine_id`, `machine_id`, or all active machines | Current machine schema has no organization ownership | Not safely derivable; response includes global machine catalog and fit data |
| `user_operational_scopes`, `users`, dashboard assignments | `part_id` for assigned user; all eligible users for selector | User list is governed by identity/membership, not Product ownership; query has no org filter | Not safely derivable; available responsibility users can disclose unrelated identities |
| `item_ref` and BOM/Shared Item identity | Product scalar only; Part 360 does not query BOM tables | Existing compatibility contracts preserve `products.item_ref` and BOM references | No identity change is needed, but absence of BOM reads does not authorize the other secondary data |

The detail view also emits links to other workflows. Those links are
navigation, not authorization evidence; their target routes require their own
boundaries and are outside this slice.

## Decision

Do not activate Part 360 through `ProductReadService` yet. Do not add an
unrestricted fallback or duplicate resolver. The IPM-008 seam remains
operational only for QR, while this probe freezes the unsafe detail path as
unmigrated.

This is not a system-wide Product isolation claim. Product listing/search,
selectors, BOM, Data Exchange, Shell, and other Manufacturing consumers remain
unmigrated as previously recorded.

## Evidence

- Product detail blocker probe: **20/20**.
- Existing Product read boundary: **14/14**.
- Existing QR boundary: **36/36**.
- Product ownership: **26/26**.
- Product Item mapping: **19/19**.
- Platform organization context: **16/16**.
- Membership authority: **30/30**.
- Isolated source-root safety: **17/17**.
- Architecture gates: **PASS**.
- Read-only deployment readiness: **PASS**.

No database-backed acceptance was run. `OH_IPM` attestation remains required
before disposable MySQL verification.

## Required next closure slice

First establish an owner-approved organization-context contract for the
Products detail surface and decide whether the related operational records,
machine/material catalogs, and responsibility data are shared reference data
or require organization ownership. Then introduce one context-aware Part 360
projection only after every emitted secondary dataset has an explicit
authorization disposition.
