# IPM-012 — Mold Ownership Contract and Revision Activation Readiness

**Status:** Contract complete; ownership implementation and revision activation
remain deferred.

**Baseline:** `ebf02f9cdf047109ad86ee5ae5d1915e92273932`

## 1. Bounded decision

The repository supports a defensible future ownership model, but not an
independently safe ownership implementation in this milestone.

Select **Option A: `part_molds.organization_id`** as the target authoritative
ownership source. A mold is currently created and displayed as a child of one
Product, and the existing identity constraint is
`UNIQUE(product_id, mold_code)`. No source evidence demonstrates legitimate
multi-organization sharing of one physical mold identity.

Do not add an organization column only to revisions and call the boundary
secure. Do not backfill legacy molds, infer ownership from Product, creator,
assignment, `item_ref`, BOM, or Shared Item, or expose revision operations
before ordinary mold mutation paths enforce the same context.

Option B, an organization-to-mold relation, remains a future change only if an
owner-approved requirement demonstrates genuine shared physical molds. It
would need an explicit relation lifecycle, effective dates, conflict policy,
organization-scoped permissions, and revision ownership semantics. No such
evidence exists in the current repository.

## 2. Canonical mold and actual ownership evidence

The canonical master is Products-owned:

```text
part_molds.id INT AUTO_INCREMENT PRIMARY KEY
```

It contains `product_id`, `mold_code`, `mold_name`, tooling specifications,
status, notes, timestamps, and `UNIQUE(product_id, mold_code)`. The IPM
revision table correctly references this `INT` key.

Current ownership evidence is insufficient:

- `part_molds` has no `organization_id` or organization FK.
- The Product relationship is a domain relationship, not current mold
  ownership proof.
- Existing legacy Products may have NULL organization ownership.
- No mold-to-organization relation exists.
- No mold owner service or organization-aware mold repository exists.
- The existing mold authorization is assignment/admin based, not membership
  based.

The revision `organization_id` therefore remains a future authorization anchor,
not evidence that the referenced mold belongs to that organization.

## 3. Ordinary mutation and read paths

### 3.1 Creation

Both creation aliases converge on `ProductsController::createMold()`:

```text
POST /apps/manufacturing/products/molds/add
POST /products/molds/add
    -> ProductsController::createMold($_POST)
```

The route requires Manufacturing app access and CSRF. The controller calls
`PartEngineeringSchemaService::ensureSchema()`, then permits a platform/app
admin or the active `user_operational_scopes` assignee for the Product, and
inserts directly into `part_molds`.

It does not resolve `ProductOrganizationScope`, validate an opaque membership
context, verify an organization-owned Product, or persist an organization
owner.

### 3.2 Deletion

Both deletion aliases converge on `ProductsController::deleteMold()`:

```text
POST /apps/manufacturing/products/molds/delete
POST /products/molds/delete
    -> ProductsController::deleteMold(mold_id, product_id)
```

The same app-access, CSRF, and lead/admin check is used. The deletion query is
`DELETE FROM part_molds WHERE id=? LIMIT 1`; the supplied Product ID is used
for authorization/redirect but is not part of the delete predicate.

This is an existing integrity concern independent of IPM revision activation:
the future ownership slice must require an owner-scoped mold lookup using both
canonical mold identity and validated organization context before deletion.

### 3.3 Reads and privileged maintenance

Part 360 reads molds through `Part360Service::molds($productId)` with only
`WHERE product_id = ?`; it is already blocked by IPM-010 and remains
unmodified.

`PartEngineeringSchemaService` is a privileged schema-maintenance utility. Its
current callers are:

- `ProductsController::createMold()` — ordinary mold creation;
- `ProductsController::deleteMold()` — ordinary mold deletion;
- `ProductsController::createMaterialMap()`,
  `ProductsController::deleteMaterialMap()`, and
  `ProductsController::assignActiveMachine()` — neighboring Product
  engineering writes;
- `Part360Service::build()` — Part 360 read preparation; and
- `Products/install.php` — module installation preparation.

It performs runtime `CREATE TABLE` and conditional `ALTER TABLE` operations.
The install hook is the legitimate preparation caller; the ordinary mutation
and Part 360 callers are runtime DDL dependencies that must be removed only
after migration coverage and installation compatibility are verified. The
service is not ownership evidence and must not be removed blindly.

The seven entries above are direct source call sites, not seven complete
execution paths. The canonical and compatibility mold POST aliases converge
on `createMold()` or `deleteMold()` through `Products/routes.php`; neighboring
material-map and active-machine aliases converge on their corresponding
`ProductsController` handlers; and Part 360 request paths reach
`Part360Service::build()`, which enters the guarded schema-initialization
branch before assembling its read projection. The probe checks these route and
call-graph anchors separately from the direct-call inventory. Test probes and
fixtures are excluded only from the recursive source inventory; they are not
treated as runtime callers.

No other ordinary mold creation, edit, or deletion handler was found.

### 3.4 Legacy DDL transition

The target transition is from request-time repair to migration-owned schema:

1. Treat Products migrations `008_part_molds_and_material_yield.sql` and
   `009_part_molds_machine_fit_alignment.sql` as the canonical source for the
   mold and machine-fit columns/indexes currently repaired by
   `PartEngineeringSchemaService`.
2. Add the future nullable ownership migration beside those Products
   migrations; do not put ownership DDL in InjectionMolding runtime hooks.
3. Extend the Products installation/migration preparation path so a fresh
   installation applies the complete migration set before routes are usable.
   Preserve the install hook during this compatibility transition, but make
   it an installation-only coordinator rather than a request-time repair
   dependency.
4. Prove fresh install, upgrade, replay/idempotency, and an already-installed
   legacy schema before removing `ensureSchema()` from `createMold()`,
   `deleteMold()`, material-map writes, and `Part360Service` preparation.
5. After that proof, remove the request-time calls first; retain only the
   migration/install authority needed for supported installation compatibility.
   Remove the compatibility fallback itself only after an explicitly recorded
   release/upgrade window and clean migration evidence.

Until those steps are complete, runtime DDL is a documented compatibility
dependency, not an accepted permanent architecture. IPM-012 does not remove
or alter it.

## 4. Recommended ownership contract

### 4.1 Target schema

Add a future Products-owned additive migration:

```text
part_molds.organization_id INT NULL
  -> indexed
  -> FK org_companies(id) ON DELETE RESTRICT
```

The column must initially remain nullable because existing rows are unresolved.
The migration must not backfill or infer ownership.

The nullable transition is a compatibility state, not a secure runtime state.
The ownership contract becomes active only after all ordinary write paths
require explicit validated context and reject NULL-owned targets.

### 4.2 Product relationship

For a new or edited mold, the service must:

1. resolve explicit organization selection through `ProductOrganizationScope`;
2. retain only the resulting opaque membership context;
3. read the Product through `ProductReadService` using that context;
4. require the Product and mold organization to match;
5. persist the context organization on the mold in the same transaction.

A Product ID alone is not authorization evidence.

### 4.3 Legacy behavior

- Existing `part_molds.organization_id IS NULL` rows remain preserved.
- Organization-scoped mold reads exclude NULL-owned rows.
- Organization-scoped edits and deletes deny NULL-owned rows.
- No automatic Product-based, creator-based, assignment-based, or ID-based
  ownership inference is allowed.
- An explicit administrative migration/backfill, if ever approved, must be a
  separately audited data decision and is outside IPM-012.

## 5. Revision authorization contract

The following behavior is defined but not activated:

| Operation | Required authorization and consistency |
|---|---|
| Draft creation | Validated membership context, `products.manage`/future IPM mutation permission, owner-scoped mold lookup, matching organization persisted atomically |
| Revision lookup/list | Validated context; `ipm_mold_revisions.organization_id = context.organizationId()` and owner-scoped mold join |
| Draft editing | Same context and owner match; draft only; transaction/row lock; released definitions immutable |
| Release | Same context, manage/approval authority, draft row lock, release evidence, no overlapping effective revision |
| Supersession | Same context and approval authority; existing released row locked; explicit replacement relation or deterministic successor contract |
| Archival | Same context and lifecycle authority; no deletion of history |
| Deletion | Draft-only at most, restricted by downstream references; released/superseded/archived history retained |
| Cross-organization access | Deny before detail disclosure; do not rely on revision organization alone when mold ownership is unresolved |
| Legacy NULL-owned mold | Deny revision creation, read, edit, release, supersession, and archival through organization-scoped APIs |

No operation above has a runtime API in IPM-012.

## 6. Consistency and concurrency requirements

Application predicates alone are insufficient. The eventual activation slice
must provide:

1. a Products-owned `part_molds.organization_id` FK;
2. an owner-scoped mold lookup used by every mutation;
3. a transaction and row lock covering mold ownership validation and revision
   insert/update;
4. a policy for ownership changes after revisions exist;
5. a database constraint preventing deletion of an owned mold referenced by
   revisions;
6. a rule that ownership changes are denied once revisions exist, or are
   performed through an audited transfer operation that atomically updates all
   dependent records;
7. revision-level organization matching enforced by service logic and,
   where MySQL composite constraints are practical, database constraints;
8. effective-window overlap and one-current-release checks under a single
   writer/locking policy.

The existing IPM-011 FK already provides restrictive deletion from revisions
to `part_molds` and `org_companies`; it cannot prove that both rows belong to
the same organization while `part_molds` lacks ownership.

If existing revision rows reference a mold whose future ownership remains
`NULL`, those rows are preserved and are not assigned an organization by
inference. Organization-scoped revision reads and all revision mutations deny
the unresolved mold until an explicitly approved data-resolution decision
exists. The ownership migration must not silently convert an existing revision
organization into mold ownership.

## 7. Why implementation is deferred

The smallest apparently easy change—adding a nullable mold organization
column—would not close the boundary because:

- both ordinary mold mutation aliases converge on handlers without context;
- the creation path directly inserts the mold after assignment/admin checks;
- the deletion predicate is not owner-scoped;
- Part 360 currently reads molds without organization context;
- the Product detail path is explicitly blocked under IPM-010;
- ordinary paths invoke runtime schema DDL;
- legacy NULL-owned molds require fail-closed treatment.

Implementing only the column would create contradictory partial ownership
semantics between Products and IPM revisions. The safe next implementation
slice must combine the Products ownership migration, both alias-equivalent
mutation paths, owner-scoped reads/deletes, and runtime-DDL removal or
migration coverage. That is broader than a documentation-only change but
still bounded to mold ownership; it is not a system-wide Manufacturing tenant
rewrite.

## 8. Staged activation plan

| Stage | Classification | Required closure |
|---|---|---|
| 1. Ownership migration | Preparatory | Add nullable `part_molds.organization_id`, index and restrictive FK; preserve NULLs; prove migration idempotency in disposable `OH_IPM` infrastructure |
| 2. Context admission | Preparatory | Add a Products-owned mold ownership seam reusing `ProductOrganizationScope` and `ProductReadService`; reject missing, revoked, cross-organization, and NULL-owned evidence |
| 3. Existing writes | Enforcement | Update `createMold()` and `deleteMold()` behind their existing app access, CSRF, and lead/admin checks; require validated membership context and owner-scoped predicates on both canonical and compatibility aliases |
| 4. Authorized reads | Enforcement | Add only bounded owner-scoped mold reads needed by the approved surface; keep Part 360 blocked until its own authorization contract is separately accepted |
| 5. Revision consistency | Enforcement | Add revision services only after mold owner matching, transaction/row locking, lifecycle rules, overlap policy, and legacy NULL denial are proven; no release workflow is implied by this stage alone |
| 6. Runtime DDL retirement | Preparatory prerequisite | Move schema responsibility to Products migrations/install preparation, verify existing installation compatibility, then remove runtime `ensureSchema()` calls from mold mutations, Part 360, and neighboring runtime paths |
| 7. Revision activation | Enforcement | Only after stages 1–6 and governed database acceptance pass; expose no revision route until owner, permission, consistency, and concurrency proofs are complete |

Stages 1, 2, and 6 prepare the boundary. Stages 3–5 enforce it for the
existing mold surface and revision internals. Stage 7 is the first point at
which revision operations may be considered for activation; IPM-012 performs
none of these changes.

## 9. Earliest independently testable slice

The next authorized implementation slice should be:

1. additive Products migration for nullable `part_molds.organization_id`;
2. database-free `ProductMoldOwnership` admission contract over existing
   `ProductOrganizationScope` and `ProductReadService`;
3. static/runtime-shaped tests for both canonical and compatibility aliases;
4. no new revision endpoint and no Part 360 activation;
5. disposable database proof, when `OH_IPM` is independently attested, for
   exact types, legacy NULL preservation, FK behavior, and restrictive delete.

Only after that slice passes should the repository decide whether to gate the
existing mold creation/deletion handlers or introduce a separately named,
owner-scoped mold mutation path.

## 10. Verification and non-goals

IPM-012 is contract-only; the focused source-boundary probe verifies the
documented current state and does not claim runtime ownership enforcement.

No schema, route, controller, service, runtime DDL, revision API, Part 360,
BOM, production, maintenance, database, deployment, or activation change is
authorized by this milestone.

`OH_IPM` database acceptance remains pending. Static source verification must
not be represented as migration execution.
