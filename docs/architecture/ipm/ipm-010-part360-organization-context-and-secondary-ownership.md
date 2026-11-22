# IPM-010 — Part 360 Organization Context and Secondary Data Ownership

**Status:** Implementation-ready contract; Part 360 activation remains blocked.

**Baseline:** `0042de4f754567c4e1842f79ee1209ab73402cb3`

## 1. Decision

IPM-010 does not activate the existing Part 360 response. The safe outcome is
to define the admission and ownership contract first, because the current
response combines an unscoped Product lookup with relationship-only,
legacy-global, and user/catalog projections.

The existing Products-owned `ProductOrganizationScope` is the only proposed
context mechanism. No second resolver, session framework, inferred ownership,
or unrestricted fallback is authorized.

## 2. Organization-context contract

### 2.1 Admission source

Part 360 must receive an explicit selected organization identifier from the
existing authenticated request context, using the same input pattern already
used by Products creation/import and QR admission. The value is resolver input,
not authorization evidence.

Admission must call:

```text
ProductOrganizationScope::resolve(selected_organization_id)
  -> AuthenticatedOrganizationContextResolver
  -> OrganizationMembershipRepository
  -> OrganizationMembershipResolver
  -> OrganizationMembershipContext
```

Only the returned `OrganizationMembershipContext` may reach
`ProductReadService` or a future scoped Part 360 projection.

### 2.2 Required outcomes

- Missing, malformed, array-shaped, zero, overflow, or unavailable selection:
  deny before Product SQL.
- Unauthenticated, inactive, revoked, expired, contradictory, duplicate, or
  cross-user membership evidence: deny before Product SQL.
- Organization switching: resolve the newly selected organization on every
  request; never retain an earlier context as authorization.
- Direct requests that bypass navigation: follow the same resolver and deny
  when selection or membership evidence is absent.
- Legacy `products.organization_id IS NULL`: deny for organization-scoped
  detail; do not infer ownership or backfill it.
- Cross-organization or nonexistent Product: return the same generic
  unavailable/not-found outcome after the owner-filtered read.

The existing `/products/360` alias must preserve the query string when
redirecting to `/apps/manufacturing/products/360`. The canonical handler must
not authorize from the alias or from the raw URL alone.

### 2.3 Response compatibility rule

Context admission and Product admission can be implemented independently as
database-free components. Complete Part 360 activation cannot occur until
every emitted section has an authorization disposition. A context-aware
primary Product row must not be combined with the existing unscoped secondary
queries.

## 3. Secondary ownership matrix

| Dataset / Part 360 section | Relationship to requested Product | Current ownership evidence | Can Product ownership authorize it now? | Required disposition |
|---|---|---|---|---|
| `products` | Primary `p.id`; stock balance is joined by `product_id` | `products.organization_id` exists but is nullable | Yes, through `ProductReadService` only | Stage B: owner-filtered primary admission |
| `stock_ledger_entries` | Product-linked balance and recent ledger rows | Manufacturing-owned, no organization column or FK | Not independently; a row may be legacy/unowned | Stage C: approve inherited Product relationship or add owner contract; otherwise omit/deny |
| `daily_orders`, `pre_orders` | Direct `product_id` timeline and demand | No organization column; no FK | Relationship is bounded to the requested Product but not authoritative ownership | Stage C: explicit dependent-row policy plus integrity proof |
| `production_plans`, `production_entries` | Direct `product_id`; also machine/workflow references | No organization column; no FK | Not by Product ownership alone | Stage C: independent ownership or approved inherited relationship |
| `qc_plans`, `qc_entries` | Product and cross-stage links to orders/plans/production | No organization column; no FK | No; cross-links need their own integrity policy | Stage C: QC owner contract and cross-link policy |
| `dispatch_entries` | Product plus order/plan/QC references | No organization column; no FK | No; Product linkage is not dispatch authorization | Stage C: Dispatch owner contract and cross-link policy |
| `handoff_tracking` | Generic entity type/ID derived from related workflow rows | No Product or organization column | No; it inherits an application chain, not tenant proof | Stage C: explicit inherited ownership or exclude |
| `part_molds` | Direct `product_id`; Products-owned current mold master | No organization column; no FK to `products` | Not yet; direct linkage is not an ownership column | Stage C: Products mold ownership contract; no IPM duplicate |
| `part_material_map` | Direct `product_id` and `material_id`; snapshot fields | No organization column; no FK | Not yet; mapping and material identity have separate semantics | Stage C: mapping ownership/integrity policy |
| `materials` / `materialOptions()` | Mapped material joins plus all active materials for selector | Runtime-created global master; no organization column | No for an organization-scoped response; selector may expose unrelated vendor/material data | Exclude from scoped detail or establish an approved shared-catalog policy |
| `machines` / fit, assignment, usage | `machine_id`/`active_machine_id`; some queries enumerate active machines | Global Manufacturing master; no organization column | No; machine rows and Product rows have independent identity | Exclude or establish an approved shared-machine policy and related-record policy |
| `users`, `user_operational_scopes`, assignments | Assigned user by `part_id`; available users by Manufacturing assignment/app role | Identity/assignment data, not Product organization ownership | No; available-user query can disclose unrelated identities | Exclude responsibility-management data or establish independent membership/scope authorization |
| `item_ref`, BOM/Shared Item identity | Product scalar compatibility field; Part 360 does not query BOM | Existing identity contracts; no Part 360 ownership proof | Preserve identity only; it is not an organization substitute | No rewrite; BOM remains outside this activation |

### 3.1 Relationship-only is not ownership

`WHERE related.product_id = ?` prevents returning rows for a different
Product ID, but it does not prove that an unowned related row was created for
the same organization. The relationship can become an inherited authorization
contract only after the owner approves its semantics and the write/read
integrity path prevents ambiguous or cross-organization links.

### 3.2 Shared masters are not silently tenantized

`materials` and `machines` currently behave as global Manufacturing masters.
Adding duplicate tenant masters or copying a generic `organization_id` predicate
would change identity and compatibility semantics. A future shared-catalog
decision must define whether fields are globally discloseable and whether
mutations are separately authorized. Until then, these datasets cannot be
included in a complete organization-scoped Part 360 response.

## 4. Staged closure sequence

### Stage A — Context admission

Reuse `ProductOrganizationScope`; add a Part 360-specific admission wrapper
only if it is a thin, tested adapter that does not resolve identity itself.
Validate context before `Part360Service` or any secondary SQL. Preserve both
route aliases and query strings. This stage is independently testable and
does not authorize or activate the existing full response.

### Stage B — Product admission

Call `ProductReadService::findById($id, $context)` and preserve Product
identity, `item_ref`, and generic denial behavior. This stage is independently
testable for valid membership, same-organization Product, cross-organization
Product, NULL-owned Product, nonexistent Product, and malformed selection.

### Stage C — Related-record authorization

Resolve each matrix row before projection activation. The preferred order is:

1. define the dependent-row policy (inherited, independently owned, shared, or
   unavailable);
2. add additive owner columns/contracts only where policy requires them, with
   no legacy backfill or inferred ownership;
3. move each query behind its owning service and require the validated context;
4. prove cross-links, NULL/legacy behavior, and no unrelated rows;
5. preserve existing IDs and BOM/Shared Item identity semantics.

This stage is not a license to tenantize all Manufacturing. It is limited to
datasets emitted by the selected Part 360 operation.

### Stage D — Complete projection activation

Activate only a new context-aware Part 360 projection after all included
sections pass Stage C. If `material_options`, machine catalogs, or available
users are not authorized, a separately named read-only Product detail view may
omit those management-only sections. The existing Part 360 response must not
silently degrade or be relabeled tenant-safe.

## 5. Exact activation blockers

1. No selected organization context currently reaches `part360()`.
2. `Part360Service::build()` performs a primary Product lookup without
   `organization_id`.
3. Product-linked operational tables have no established inherited ownership
   contract or uniform organization columns.
4. `handoff_tracking` is polymorphic and has no Product/organization identity.
5. `part_molds` and `part_material_map` are direct Product dependencies but
   remain legacy-unowned.
6. Global materials and machines have no approved shared-catalog disclosure
   policy.
7. Responsibility includes an available-user directory not constrained by
   Product organization.
8. Existing Part 360 includes management forms/actions; omitting unsafe
   management data would change the established response and requires a
   separately named capability.
9. Governed `OH_IPM` database acceptance is still unavailable.

## 6. InjectionMolding dependency decision

Full Part 360 closure is **not a prerequisite** for the next InjectionMolding
milestone. The repository already has an implementation-ready module boundary:

- `InjectionMolding` is not scaffolded yet;
- it must own only additive `ipm_*` tables;
- every IPM-owned table carries mandatory `organization_id`;
- `part_molds`, Products, Machines, BOM, Production, QC, Dispatch, and
  MaterialManagement remain existing owners;
- IPM must reference existing identities and must not create duplicate masters;
- Part 360 integration is explicitly sequenced after the module's own
  organization-aware read services.

Therefore, do not expand IPM into a broad Manufacturing tenant-isolation
program merely because Part 360 currently displays legacy-global data.

## 7. Recommendation

Proceed with the **InjectionMolding module contract/scaffold and additive
schema design** as the next bounded milestone, subject to the existing owner
decisions and `OH_IPM` gate. Keep full Part 360 closure deferred and explicitly
blocked. The first IPM implementation should be read-only revision/recipe
services over organization-owned `ipm_*` records, with no Part 360 wiring.

Return to Part 360 only after:

1. the explicit context admission is accepted;
2. Product admission is implemented without changing identity semantics;
3. each matrix row has an approved ownership/disclosure policy;
4. database-free and governed disposable tests prove the complete projection;
5. any unsafe management-only section is either independently authorized or
   moved to a separately named capability.

## 8. Evidence and non-goals

IPM-010 source probe: **24/24**.

Existing evidence remains valid: Product read **14/14**, QR **36/36**, Product
ownership **26/26**, Item mapping **19/19**, Platform context **16/16**,
membership authority **30/30**, isolated source-root **17/17**, architecture
gates **PASS**, and read-only deployment readiness **PASS**.

No Part 360 runtime behavior, route, schema, BOM, Shared Item identity,
database, deployment, activation, or InjectionMolding module scaffold changed
in this milestone.
