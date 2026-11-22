# IPM-007 Product Access-Closure Preparation

**Status:** First QR boundary implemented; remaining closure not authorized.
**Date:** 2026-11-23
**Prerequisite evidence:** `docs/architecture/ipm/ipm-005-product-organization-ownership-dependency-reconciliation.md`
**Source baseline:** `de7a0d7cdb78cf61d4f532a435d3fbe2569a6e9e`

## 1. Scope and boundary

IPM-007 is the next bounded Manufacturing Product access-closure milestone.
It reuses the completed IPM-005 evidence and does not repeat the 71-file
inventory. It does not start mold-revision work, add a new Product master,
change BOM identity, change Shared Item identity, or activate organization
authorization on existing installations.

IPM-006 remains **IMPLEMENTED, ACTIVE BY DEFAULT, DATABASE ACCEPTANCE
PENDING**. Its database gate is owner-controlled and must not be treated as a
reason to invent a second ownership implementation. IPM-007 preparation is
database-independent; runtime acceptance and activation remain dependent on
the governed `OH_IPM` database result and the owner decisions listed below.

The 47 future bypasses remain classified as unscoped legacy surfaces, not
confirmed current cross-organization violations. Only the bounded QR scan
disclosure boundary described below is implemented; no other Product read or
mutation closure is claimed.

## 2. First coherent slice: public QR Product scan

### Finding

`GET /qr/product/scan` is the one confirmed public Product disclosure path.
`apps/Platform/modules/QRCode/routes.php` admits it without
`Auth::requireAdmin` or an organization context, and
`apps/Platform/modules/QRCode/Controllers/QRCodeController.php::scanProduct()`
calls `findProduct()` before rendering the Product result. The current lookup
accepts a Product ID plus part number and therefore cannot prove Product
organization ownership.

### Safe first-slice decision and implementation

The route remains public for QR identification compatibility, but an unscoped
public lookup does not return Product details. The implementation uses a
Products-owned scan admission service. A caller-provided `organization_id` is
only input to the existing authenticated membership resolver; it is not
authorization evidence. Do not infer authorization from the Product ID, part
number, `item_ref`, BOM references, Shared Item identity, an unverified
session, or a QR URL generated before Product ownership was established.

The implemented first slice is:

1. `ProductScanAdmissionService` accepts an opaque organization context
   produced by `ProductOrganizationScope`; the raw request value is only
   resolver input.
2. `QRCodeController::scanProduct()` obtains and validates that admission
   before Product SQL. Missing, malformed, spoofed, NULL-owned,
   cross-organization, and ambiguous evidence return one generic unavailable
   result without Product disclosure.
3. The public route, QR payload, and admitted success view remain stable.
4. Database-free contract coverage proves the boundary before database-backed
   acceptance.

The default implementation posture is **deny unscoped public scans**, not
silently expose legacy NULL-owned Products. A signed or otherwise bounded
public scan token is a separate owner decision and must not be improvised.

### Actual first-slice files

The implementation is bounded to:

- `apps/Platform/modules/QRCode/routes.php`
- `apps/Platform/modules/QRCode/Controllers/QRCodeController.php`
- `apps/Manufacturing/modules/Products/Services/ProductScanAdmissionService.php`
- `apps/Manufacturing/modules/Products/Tests/probe_product_scan_organization_boundary.php`

No other Product read query was edited. The remaining public scan credential
decision and the other 46 future bypasses remain outside this slice.

### First-slice acceptance criteria

- No Product SQL occurs before scan admission.
- Public requests with missing, malformed, duplicate, or conflicting
  identifiers fail closed.
- No organization is accepted from an untrusted query parameter.
- Legacy `organization_id IS NULL` Products are not treated as public/global.
- A verified organization receives only an owned Product, subject to the
  approved scan policy.
- Existing Product identity, part-number matching, label URL generation, and
  scan-result error shape remain compatible.
- Static proof covers route admission order and absence of pre-admission lookup.
- Database proof covers migration 015, owner filtering, NULL denial, and
  cross-organization denial when `OH_IPM` infrastructure is authorized.

## 3. Closure sequence after the first slice

### Stage A — Contract and central read seam

Approve the public QR policy, NULL-owner policy for reads, administrator
cross-organization policy, dependent-row policy, and Product read-admission
owner. Define one Products-owned owner-aware read projection with explicit
identity and organization predicates. Preserve existing assignment/module
scope as an additional constraint.

**Central opportunities:** Products list/detail, Parts Master snapshot, Part
360, Product selectors, and QR Product label/scan can reuse the same admission
and projection contract. This is the only safe consolidation opportunity
before editing direct consumers.

### Stage B — Products-module read and selector closure

Close the read-only and selector paths before broad mutations:

1. `ProductsController::index` / `PartsMasterSnapshotService`
2. `ProductsController::part360` / `Part360Service::build`
3. Product add/import forms and report/export sources
4. Product edit form lookup
5. QR label lookup and the first-slice QR scan boundary

NULL-owned and cross-organization Products fail closed for organization-scoped
users. Existing legacy rows remain physically intact.

### Stage C — Products-module mutation closure

After read admission is proven, close edit, delete, active-state, item-ref,
mold, material, active-machine, and responsible-user operations. Every
mutation must authorize the current Product owner before schema setup,
dependent lookup, or mutation. Ordinary update/import cannot reassign
ownership. Each batch remains atomic.

### Stage D — Direct Manufacturing consumers

Change direct Product SQL and selectors in this order:

1. production/processing/dispatch selectors and submitted Product IDs;
2. Daily Orders, Production Plans, Assembly Plans, QC Plans;
3. Production Entries, QC Entries, Ledger, Part-machine maps;
4. Material Management and queue/coverage/dashboard aggregates;
5. demand, stage, upstream/downstream, handoff, and My Work joins;
6. BOM reads after dependent-row policy is approved.

Each consumer needs a focused owner/scope proof. No generic
`organization_id` predicate may be copied into BOM or dependent rows without
the accepted policy for those rows.

### Stage E — Shell and QR closure

Close Shell Product selectors and read-only composition only through a
verified owner-scoped Product projection:

- `PartsTableService::getPartsTable`
- `WorkEntryComposer`
- `OperatorSurfaceComposer`
- `OperatorRecentActivityComposer`
- `OperatorDashboardInsightsComposer`
- Shell Data Exchange Product resolution

Then close the remaining QR stock/report/export joins and stock mutations with
the same Product owner admission. Operator `/u/*` confinement and existing
assignment scope remain separate constraints.

### Stage F — Legacy and dependent-row classification

Classify legacy NULL Products and rows that reference them without backfill or
inference. Decide whether each dependent record is denied, maintenance-only,
or governed by an explicit inherited organization contract. Keep
`item_ref`, `finished_item_ref`, `component_item_ref`, Product IDs, and Shared
Item IDs stable.

## 4. Required decisions and dependencies

Before runtime implementation:

1. Public QR scan policy: deny, authenticated organization context, or an
   owner-approved signed/opaque scan credential.
2. Products-owned read-admission interface and Platform/Manufacturing owner.
3. Whether platform administrators may select an organization for reads and
   mutations, with audit requirements.
4. NULL-owner behavior for every read, selector, mutation, report, and export.
5. Dependent-row policy for BOM, orders, production, QC, dispatch, material,
   machine, ledger, and demand records.
6. Mapping read/write capability after Product ownership is proven.
7. Independent `OH_IPM` disposable infrastructure and authorization for
   migration and runtime acceptance.

No implementation should use Product number, creator, assignment, `item_ref`,
BOM identity, or Shared Item identity as an ownership substitute.

## 5. Verification plan

### Database-independent preparation

- Add a focused QR admission contract probe: malformed input, missing
  admission, public default denial, NULL owner, cross-owner, valid owner,
  route ordering, and no direct pre-admission SQL.
- Add static checks for each central adapter and direct consumer migration.
- Run existing Product ownership **26/26**, mapping **19/19**, isolated runner
  **17/17**, architecture, deletion, lint, diff, and read-only readiness checks.

### Governed database acceptance

Only after the owner supplies attested `OH_IPM` infrastructure, run through
the existing isolated runner:

- migration 015 apply/replay and schema/FK/index checks;
- QR owner filtering and public denial;
- Products read/selector/mutation denial for NULL and cross-owner rows;
- transaction rollback and no partial batch writes;
- Product identity, mapping, BOM, and dependent-row compatibility;
- source/configuration manifest preservation and runner-owned cleanup.

The installed, development, production, and incident databases remain
forbidden test targets.

## 6. Stop conditions

Stop and request an owner decision if the first slice requires an inferred
public scan token or organization, a new ACL presentation field, a Product or
Shared Item identity rewrite, a legacy NULL backfill, a duplicate Product/BOM
master, a database connection without `OH_IPM` attestation, or a runtime
feature flag used to mask migration failure.

## 7. Recommendation

Proceed with the **IPM-007 QR Product scan admission contract and
database-free boundary probe** as the first independently testable slice after
the public scan policy and adapter owner are approved. This is preparation,
not runtime activation. Do not begin mold-revision implementation until the
first slice and its dependent Product read policy are resolved.
