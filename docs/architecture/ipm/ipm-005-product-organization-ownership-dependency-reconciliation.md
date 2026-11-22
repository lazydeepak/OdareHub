# IPM-005 Product Organization Ownership Dependency Reconciliation

**Status:** Bounded audit complete; implementation blocked pending owner
decisions and staged bypass closure.

**Date:** 2026-11-23

**Baseline:** `faff5b8da2c9ef63ccb1a222e5708fefe63b7937`

## Executive finding

Products does not currently have an authoritative organization-ownership
contract. The `products` table has no organization column, the Products
creation/import paths do not resolve an authenticated organization, and the
existing role, app-access, assignment, and `products.360.view` checks do not
prove organization membership.

The IPM-004 mapping table has exact foreign keys, but foreign keys only prove
identity existence. They do not prove that the authenticated actor may use the
referenced Product. Adding a mapping route or service before Product ownership
exists would therefore create a cross-organization authorization bypass.

No Product schema, mapping runtime, authorization, or database behavior was
changed in IPM-005.

## Evidence

### Product storage and lifecycle

- `apps/Manufacturing/modules/Products/migrations/001_products.sql` creates
  `products` with `id`, names, number, and timestamp fields only.
- `apps/Manufacturing/modules/Products/install.php` adds legacy operational
  columns and performs compatibility initialization, but has no organization
  identity or membership validation.
- `apps/Manufacturing/modules/Products/Controllers/ProductsController.php`
  creates and imports Product rows without an organization context.
- Product creation, editing, deletion, lifecycle changes, import, item-ref
  assignment, mold/material mapping, machine assignment, and responsible-user
  assignment are admitted through Manufacturing app access, CSRF, broad admin
  roles, or Product assignment checks.
- Legacy Product rows therefore cannot be safely assigned to an organization by
  inference from creator, assignment, `item_ref`, BOM references, or current
  user membership.

### Existing organization authority

`Apps\Platform\Services\AuthenticatedOrganizationContextResolver` is the
authoritative existing seam. It requires:

1. an authenticated positive user identity;
2. an explicitly selected organization;
3. repository-backed membership evidence;
4. active organization and valid membership/lifecycle evidence; and
5. integrity agreement between the selected organization and resolved context.

`ProcurementOrganizationScope` is an owner adapter over that seam. It does not
establish Product ownership and must not be copied as a second tenancy
framework.

### Product and mapping access inventory

The initial source scan found **71 Manufacturing PHP files** containing direct
Product SQL or Product-backed verification/query paths. That is a reference
count, not a change count. Grouping compatibility aliases and shared callers
by their actual authorization boundary produces **48 distinct access
surfaces**:

| Surface group | Distinct paths | Representative entry points | Current admission | Boundary classification |
|---|---:|---|---|---|
| Products module | 20 | index/list, Part 360, add/edit, create/update/delete, lifecycle, item-ref, mold, material, machine, responsible-user, import, report, export | Manufacturing app access; CSRF on POST; lead/admin or admin checks on selected mutations; `products.360.view` on Part 360 | **Confirmed unscoped runtime surface.** No organization context or Product owner predicate. |
| Manufacturing Product consumers | 20 | Product widget/snapshot, Part 360, operator parts/detail, search, BOM, Production/Processing/Dispatch/Daily Orders/Plans/Assembly/QC/Production Entries, Ledger, PartMachineMap, Material Management, queue/dashboard/report aggregates | Varies by route: app access, module permission, assignment/scope, operator identity, or service caller | **Confirmed unscoped runtime surface.** Existing authorization is not organization membership. |
| Shell consumers | 5 | PartsTable, operator Work Entry, operator dashboard/recent activity/insights composition, Data Exchange Product resolution | Admin/route authority, operator identity/assignment, or Data Exchange adapter admission | **Confirmed unscoped runtime surface.** Some are read-only; Data Exchange includes Product-linked import resolution. |
| Platform QR consumers | 2 | Product label/scan and QR stock/report workflows | Admin for label/stock/report; public/authenticated route admission for scan | **Confirmed unscoped runtime surface.** Public QR scan is especially sensitive because `findProduct()` has no organization check. |
| Platform seed/maintenance | 1 | Demo seed and cleanup | Runner/maintenance authority, not a normal user route | **Not externally reachable in the audited runtime.** Still requires an explicit maintenance boundary before tenant ownership is claimed. |

The 48 count is by effective handler/service boundary. The two Products
compatibility aliases (`/products/*` and `/apps/manufacturing/products/*`)
share handlers and are counted once; report/export and the Product module
mutation families are counted by logical operation, not by URL spelling.
Views, probes, migration files, and duplicate SQL statements inside one service
are not additional enforcement paths.

#### Three required authorization classifications

1. **Confirmed existing violation: 0.** Products has no authoritative
   organization ownership contract. Therefore the repository cannot
   demonstrate that a current operation violates an established Product
   ownership rule or that a particular row belongs to organization A rather
   than B. Existing global Product access is historical/legacy behavior and is
   not retroactively labeled a security breach.
2. **Confirmed future enforcement bypass: 47.** The first four inventory groups
   are externally reachable normal-user runtime surfaces. Their route/service
   evidence shows an existing auth, ACL, assignment, or role gate, but no
   organization membership or Product-owner predicate. If the proposed
   `products.organization_id` contract were introduced without changing these
   surfaces, each could continue to select, disclose, or mutate Product data by
   identifier and would bypass the new boundary.
3. **Unverified exposure: 1.** Platform demo seed/cleanup writes and deletes
   Products, but its production reachability and invocation authority were not
   established in this audit. It is not counted as a current user-facing
   violation or future runtime bypass; it requires an explicit maintenance
   classification before ownership activation.

The one confirmed public Product disclosure path is `/qr/product/scan`. It is
also one of the 47 future enforcement bypasses: no admin gate, organization
context, or Product owner predicate is present. This is evidence of an
unscoped legacy path, not evidence of an existing cross-organization violation.

### Confirmed future-bypass evidence and remediation

The following rows enumerate every confirmed future bypass surface. A count in
the first column means the row represents equivalent handlers with the same
route/admission boundary; compatibility aliases are not double-counted.

| Count | Route/entry point | Underlying handler/service | Existing authorization | Required enforcement change |
|---:|---|---|---|---|
| 1 | `GET /apps/manufacturing/products` | `renderProducts` → `ProductsController::index` → `PartsMasterSnapshotService` | Manufacturing app access; assignment/scope filtering | Resolve explicit organization; add Product-owner predicate to snapshot/list query. |
| 1 | `GET /apps/manufacturing/products/360?id=` | `renderProduct360` → `ProductsController::part360` → `Part360Service::build` | `products.360.view` only | Require Product owner admission before detail query and all nested projections. |
| 1 | `GET /apps/manufacturing/products/add` | `renderProductAdd` → `ProductsController::addForm` | Manufacturing app access | Require explicit organization context and capability; bind form/create packet. |
| 1 | `POST /apps/manufacturing/products/add` and `/products/add` | `$createProduct` → `ProductsController::create` | App access + CSRF | Persist verified organization; reject missing/invalid/spoofed context. |
| 1 | `GET /apps/manufacturing/products/edit?id=` | `renderProductEdit` → `ProductsController::editForm` | App access + lead/admin Product check | Owner-aware Product admission before read/edit form. |
| 1 | `POST /apps/manufacturing/products/edit` and alias | `$updateProduct` → `ProductsController::update` | App access + CSRF + lead/admin check | Require current organization owns Product; prevent ordinary ownership change. |
| 1 | `POST /apps/manufacturing/products/delete` and alias | `$deleteProduct` → `ProductsController::delete` | App access + CSRF + admin role | Require explicit owner context and approved delete policy. |
| 2 | `POST .../activate`, `.../deactivate` and aliases | `$activateProduct`/`$deactivateProduct` → `setActiveState` | App access + CSRF + admin role | Add Product-owner admission to both lifecycle operations. |
| 1 | `POST .../item-ref` and alias | `$assignProductItemRef` → `assignItemRef` → `PartItemRefService` | App access + CSRF + lead/admin check | Add owner admission; preserve opaque `item_ref` semantics. |
| 2 | `POST .../molds/add`, `.../molds/delete` and aliases | `createMold`/`deleteMold` | App access + CSRF + lead/admin check | Add owner admission before engineering schema/read/write. |
| 2 | `POST .../materials/add`, `.../materials/delete` and aliases | `createMaterialMap`/`deleteMaterialMap` | App access + CSRF + lead/admin check | Add owner admission before material lookup/map mutation. |
| 1 | `POST .../active-machine` and alias | `assignActiveMachine` | App access + CSRF + lead/admin check | Add owner admission before Product and machine mutation. |
| 1 | `POST .../responsible-user` and alias | `assignResponsibleUser` | App access + CSRF + lead/admin check | Add owner admission; preserve separate operational-scope rules. |
| 1 | `GET /apps/manufacturing/products/import` | `renderProductImport` → `importForm` | Manufacturing app access | Require organization-scoped import capability. |
| 1 | `POST /apps/manufacturing/products/import` and alias | `$importProducts` → `importCsv` | App access + CSRF | Require one verified organization for all inserted/upserted rows; no cross-org upsert by number. |
| 1 | `GET .../products/report` | Products report route → active report registry | Manufacturing app access | Scope report source/query by Product owner or explicitly classify report global. |
| 1 | `GET .../products/export` | Products export route → active report registry | Manufacturing app access | Apply the same report/export owner policy; do not rely on route access alone. |
| 1 | Manufacturing Product widgets/monitoring | `ProductWidgetRegistry` → `ModuleSurfaceWidgetFactory` | Host/module route authority | Pass verified organization context into Product summary/rows and suppress NULL/cross-org rows. |
| 1 | Manufacturing Parts Master service | `PartsMasterSnapshotService` | Controller app access and assignment scope | Centralize owner predicate in Product read adapter; preserve assignment filtering as an additional constraint. |
| 1 | Manufacturing admin Part 360 callers | `Part360Service::build` | Calling module route/`products.360.view` | Make `build` require verified Product admission or expose an owner-aware read-only variant. |
| 1 | Operator parts list/detail | `OperatorPartsWorkspaceService`, `OperatorPartDetailContributionService` | Authenticated same-user `/u/*`, module/part scope | Intersect existing part scope with Product organization ownership before Product SQL. |
| 1 | Manufacturing search | `ManufacturingSearchProvider` | Search candidate route authority/path allowlist | Add owner predicate to Product search expressions and reauthorize result URLs. |
| 1 | BOM reads | `BomService` Product joins via opaque `item_ref` | Manufacturing BOM route/app access | Add an explicit dependent-row/Product ownership policy first; do not reinterpret BOM refs. |
| 1 | Production operation selectors/reads | `ProductionOperationService` | Manufacturing route/app/operation checks | Scope selector and Product joins; validate submitted Product IDs against current organization. |
| 1 | Processing operation selectors/reads | `ProcessingOperationService` | Existing module/scope/transition authority | Scope Product selectors and all Product joins before records/actions. |
| 1 | Dispatch operations | `DispatchOpsController`/`DispatchOpsService` | Manufacturing dispatch route/CSRF/module checks | Scope selector and dispatch Product joins; reject cross-org Product IDs. |
| 1 | Daily Orders | `DailyOrdersController`/`Order360Service` | Existing route/app/CSRF and order authority | Scope Product selectors and order joins; define dependent-row policy. |
| 1 | Production Plans | `ProductionPlansController` | Route/app/CSRF and plan authority | Scope Product selector, plan reads, and submitted Product IDs. |
| 1 | Assembly Plans | `AssemblyPlansController`/`AssemblyPlanService` | Module route/app/CSRF | Scope Product selector and plan joins. |
| 1 | QC Plans | `QCPlansController` | Module route/app/CSRF | Scope Product selector and QC-plan reads/writes. |
| 1 | Production Entries | `ProductionEntriesController` | Module route/app/CSRF/approval rules | Scope Product selector and entry joins. |
| 1 | QC Entries | `QCEntriesController`/`QcLeaderDashboardService` | Module route/app/CSRF/approval rules | Scope Product selector, dashboards, and linked Product actions. |
| 1 | Ledger | `LedgerController` | Manufacturing ledger route/app/CSRF | Scope Product selector and ledger Product joins; preserve source linkage. |
| 1 | Part-machine map | `PartMachineMapController` | Module route/app/CSRF | Scope Product selector and map joins. |
| 1 | Material Management | `MaterialManagementService` | Module authority/read/mutation paths | Scope Product selectors, material joins, and aggregates. |
| 1 | Queue/coverage/dashboard aggregates | `QueueBoardService`, `ProductionQueueWidgetRegistry`, `ManufacturingDashboardBlockService`, Coverage reports | Host/module/app authority | Add owner predicates to each aggregate query or route through a scoped Product projection. |
| 1 | Demand/stage/upstream/downstream aggregates | `DemandEngineService`, `DemandWorkBucketService`, `StageTransitionService`, `UpstreamSupplyGenerationService`, `DemandExecutionService`, Handoff/My Work joins | Existing workflow/assignment/route authority | Add dependent-row organization policy and Product-owner joins; direct SQL requires direct edits. |
| 1 | Shell Parts table | `PartsTableService::getPartsTable` | Shell host composition | Supply only an owner-scoped Product projection; no direct global `products` query. |
| 1 | Shell Work Entry | `WorkEntryComposer` Product selector/joins | Authenticated operator wrapper/assignment | Scope Product selector and joined rows to current organization plus operator scope. |
| 1 | Shell operator dashboard | `OperatorSurfaceComposer` Product-backed sections | Authenticated `/u/*` and existing operator access | Add organization intersection to every Product-backed query or suppress unavailable data. |
| 1 | Shell recent activity/insights | `OperatorRecentActivityComposer`, `OperatorDashboardInsightsComposer` | Authenticated `/u/*` | Add Product-owner predicate to all joins; preserve existing read-only behavior. |
| 1 | Shell Data Exchange | `DataExchangeService` Product resolution in import paths | Operator context, adapter admission, CSRF; admin approval for queued jobs | Resolve Product only through owner-aware adapter; bind imported rows to verified organization. |
| 1 | QR Product label/scan | `QRCodeController::productLabel`, `scanProduct`, `findProduct` | Admin for label; no admin gate for scan | Require Product owner context for label; add an explicit scan policy or deny unscoped public lookup. |
| 1 | QR stock/report | `QRCodeController::stockIndex`, `stockAddForm`, `stockCreate`, QR report/export joins | Admin + CSRF on mutation | Add owner admission to Product selection, stock joins, and ledger updates. |

The rows above are grouped by equivalent handler and route boundary, not by
every SQL statement. Products routes with aliases are one path; consumers with
separate owners or separate route admission are separate paths. Central fixes
can cover the Products module, Parts Master, Part 360, and any consumer that
delegates to a new owner-aware Product projection. Direct changes remain
required for the operational aggregate SQL, Shell composers, QR controller,
Data Exchange, BOM-dependent reads, and any selector that currently queries
`products` itself.

The Products module route inventory is therefore **20 logical operation paths,
not 40 URL registrations**. The 71-file count is useful for migration impact
search, while 48 is the distinct enforcement-surface count requested for
closure planning.

## Why a mapping-only implementation is unsafe

The IPM-004 table is intentionally global in structure:

```text
manufacturing_product_item_map.product_id -> products.id
manufacturing_product_item_map.item_id    -> shared_items.item_id
```

`shared_items` is a global canonical identity by contract. That is compatible
with many Product representations mapping to one Shared Item, but it does not
authorize the Product side of the relation.

Without Product ownership evidence, a caller could:

- select a Product ID from another organization;
- map it to a globally valid Shared Item;
- read or mutate the mapping through a newly added service;
- continue to observe the same Product through an existing unscoped
  Manufacturing, Shell, Platform, BOM, or report path.

Adding `organization_id` only to the mapping table would not repair this. It
would duplicate ownership rather than derive authorization from the Product
owner, and it would leave Product and downstream access paths equivalent
unrestricted bypasses.

## Required owner decisions

Implementation remains blocked until Manufacturing and Platform owners approve
these decisions:

1. **Product ownership model.** Is a Product owned by exactly one organization,
   nullable for legacy/unassigned rows, or explicitly global? No inference is
   safe.
2. **Creation and import authority.** Must Product creation/import require an
   explicitly selected active organization membership? What permission or
   Manufacturing capability is required in addition to membership?
3. **Legacy NULL behavior.** Must organization-scoped users be denied all
   reads/writes to `organization_id IS NULL` Products? If a maintenance/admin
   path exists, its authority and audit boundary must be explicit.
4. **Cross-domain rows.** Are existing demand, order, production, QC,
   dispatch, material, machine, BOM, and ledger rows allowed to reference
   Products whose organization is NULL or different? The repository has no
   general evidence to infer an answer.
5. **Mapping authority.** Which existing Manufacturing permission/capability
   authorizes mapping writes and reads after Product ownership is proven?
6. **Shared Item lifecycle.** Are `draft`, `active`, deprecated, and archived
   Shared Items all map-eligible, or is there an explicit lifecycle admission
   contract? The mapping must not invent one.
7. **Global administrative access.** Whether platform administrators may operate
   across organizations, and if so, how explicit organization selection,
   auditability, and route/query scoping are enforced.

### Recommended ownership contract

The recommended default is:

- Every **new** Product must persist a positive `organization_id` selected
  explicitly by the authenticated actor and accepted by
  `AuthenticatedOrganizationContextResolver`; the actor must also retain the
  existing Manufacturing capability required by the operation.
- `organization_id` remains nullable during migration so existing rows are
  preserved. NULL is an **unassigned/legacy state**, not a wildcard or
  organization-global state.
- Organization-scoped reads and all organization-scoped mutations deny NULL
  Products. A separate platform maintenance workflow may inspect or classify
  NULL rows only if its owner, explicit selection, audit trail, and no-inference
  behavior are approved.
- Ownership is immutable through ordinary Product edit/update/import flows.
  Reassignment, if required, is a separate audited operation requiring
  explicit source and destination organization contexts, membership evidence,
  capability, conflict checks, and an owner-approved effect on dependent rows.
  No creator, current user, assignment, `item_ref`, BOM reference, or Product
  number may infer ownership.
- Product-to-Shared Item mapping remains many-to-one as accepted in IPM-004.
  Mapping authorization is derived from the Product's organization, while the
  Shared Item remains globally identified. Mapping writes and reads require
  verified current organization ownership of the Product plus the approved
  mapping capability; no duplicate `organization_id` is added to the mapping
  table.
- Existing BOM `item_ref`, `finished_item_ref`, and `component_item_ref`
  remain opaque Manufacturing references. They do not establish ownership and
  are not rewritten as part of Product ownership adoption.

This is a recommendation for owner review, not implementation authority.

## Staged closure plan

| Stage | Deliverable | Dependencies | Independently safe? | Boundary result |
|---|---|---|---|---|
| 0. Contract approval | Accept ownership, NULL, admin, reassignment, dependent-row, and mapping policies | Manufacturing + Platform owner decisions | Yes; documentation only | No runtime change |
| 1. Schema preparation | Nullable `products.organization_id`, FK/index, no backfill; migration/replay probe | Stage 0; disposable DB authority | Yes, if no runtime consumer trusts the column | **Preparatory only; not tenant enforcement** |
| 2. Central evidence seam | Products-owned adapter over Platform resolver; strict context and Product-owner predicates; DB-free fixtures | Stage 0; Platform resolver contract | Yes if unused by old paths; no claim of global closure | Makes approved checks reusable, but direct SQL can still bypass |
| 3. New-record ownership activation | Create/import require verified explicit organization context; update/ordinary lifecycle deny NULL and cross-org Products | Stages 1–2; capability decision | Yes for new Product writes and explicitly migrated Product writes | **Earliest genuinely enforceable boundary for new Product ownership** |
| 4. Product-module closure | Migrate all 20 logical Products operations and both aliases to central admission; deny NULL/cross-org reads and writes | Stages 1–3; route/ACL review | Yes for the Products module | Enforceable Products-module boundary, not yet whole-system |
| 5. Consumer closure | Migrate the 27 other runtime surfaces (20 Manufacturing, 5 Shell, 2 Platform QR); classify global/admin/maintenance exceptions; remove or gate bypasses | Stage 4; per-owner route and read-scope decisions | Only after each owner’s focused proof passes | **Earliest system-wide Product boundary** |
| 6. Mapping capability | Add runtime mapping service/route after Product admission; use IPM-004 schema, Shared Item lifecycle admission, ownership predicate, concurrency proof | Stages 3–5; mapping capability decision | Yes after Stage 5 | Tenant-safe Product→Shared Item mapping |
| 7. Legacy and dependent-row closure | Explicitly classify NULL Products and dependent rows; no inferred backfill; separately decide BOM/reference migration | Stages 4–6; owner decisions | No until dependent policy is accepted | Removes residual ambiguity without changing BOM identity silently |

Stage 1 alone must not be presented as a tenant boundary. Stage 3 is the first
stage that can genuinely enforce ownership for newly created Products. Stage 5
is the first stage that can honestly claim a **system-wide** Product ownership
boundary because it closes the 47 normal-user Product surfaces.

## Explicitly deferred

- No `organization_id` column was added to `products`.
- No legacy Product ownership was inferred or backfilled.
- No mapping mutation/read route or service was added.
- No existing Product consumer was silently scoped or denied.
- No BOM identity or `item_ref` behavior was changed.
- No Shared Item tenant model was introduced.
- No installed, development, incident, or production database was contacted.
- No deployment, activation, branch, worktree, or merge-to-`main` operation was
  performed.

## Audit verification record

- Distinct enforcement surfaces: **48** (20 Products, 20 Manufacturing, 5
  Shell, 2 Platform QR, 1 Platform maintenance).
- Normal-user runtime surfaces without organization admission: **47**.
- Confirmed current cross-organization violations: **0**, because Product
  ownership is not currently modeled.
- Confirmed public Product disclosure path: **1** (`/qr/product/scan`).
- Manufacturing reference count: **71 files**; aliases and shared handlers were
  not double-counted.
- Direct mapping contract probe: **19/19**.
- `git diff --check`: passed before the closure update.
- No database, SQL, browser, deployment, activation, or production operation
  was performed.
