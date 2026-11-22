# IPM-011 — InjectionMolding Module Foundation and Mold Revision Schema

**Status:** Schema foundation implemented; runtime revision management remains
unavailable.

**Baseline:** `47fded55526fc231b2234b2543d19d97fe3846b4`

## 1. Decision

Create the Manufacturing-owned `InjectionMolding` module using the existing
module manifest, dependency, migration, install, update, uninstall, and
activation contracts. Add only the additive `ipm_mold_revisions` schema.

Do not add recipes, production execution, genealogy, scheduling, costing,
maintenance workflows, Part 360 integration, mutation routes, or a second mold
master in this milestone.

The schema is not a claim that revision creation is tenant-safe. The existing
Products mold master has no authoritative organization ownership. Until that
dependency is resolved, the new table is preparation for a future authorized
service, not an active mutation surface.

## 2. Existing module and lifecycle

Before this milestone, no `apps/Manufacturing/modules/InjectionMolding`
directory, manifest, route, or migration existed. The module now follows the
existing Manufacturing conventions:

- `plugin.json` declares `suite=manufacturing`, `group=ipm`,
  `module_type=business_entity`, `owner_app`, `module_key=InjectionMolding`,
  `migrations_path=migrations`, required table, dependency on Products, and
  schema-only capabilities.
- `routes.php` exists but registers no routes.
- `install.php`, `update.php`, and `uninstall.php` contain no runtime DDL,
  data rewrite, or purge behavior.
- `001_create_ipm_mold_revisions.sql` is discovered and applied by the existing
  `PluginManager` migration path; no migration runner was added.
- The Manufacturing bundle manifest includes `InjectionMolding` in its
  existing module/legacy discovery list.

The module is intentionally not activated and has no runtime capability beyond
the schema contract.

## 3. Canonical mold master and ownership evidence

The canonical mold master is:

```text
part_molds.id INT AUTO_INCREMENT PRIMARY KEY
```

It is Products-owned and has the existing unique identity
`UNIQUE(product_id, mold_code)`. The table contains mold dimensions, cavity,
machine-fit, runner, cycle, status, and notes fields.

The verified ownership limitation is decisive: `part_molds` has no
`organization_id`, organization foreign key, or equivalent authoritative
membership binding. Its `product_id` relationship does not by itself prove
organization ownership, especially where legacy Products ownership may be
NULL. Ownership must not be inferred from Product creator, assignment,
`item_ref`, BOM, or Shared Item identity.

Accordingly, `ipm_mold_revisions.organization_id` records the organization
context required by the future IPM contract, but it does not authorize access
to the referenced mold master today.

## 4. Revision schema

`ipm_mold_revisions` is additive and organization-owned:

| Column/group | Contract |
|---|---|
| `revision_id` | New `BIGINT UNSIGNED` surrogate primary key |
| `mold_id` | Required `INT`, FK to Products-owned `part_molds.id` |
| `organization_id` | Required `INT`, FK to Platform `org_companies.id` |
| `revision_no` | Required positive integer; unique per canonical mold |
| `status` | `draft`, `released`, `superseded`, or `archived` |
| specification fields | Snapshot-compatible mold dimensions, cavity, runner, cycle, machine-fit, and notes |
| effective window | Optional `effective_from`/`effective_to` with ordered-date check |
| release evidence | `released_at` required for released, superseded, and archived rows |
| audit identity | Optional existing `users.id` references for creator/releaser |
| timestamps | Created and updated timestamps |

The uniqueness key is `(mold_id, revision_no)`, not merely
`(organization_id, revision_no)`. This avoids allowing two organization rows
to claim different revisions for the same unresolved global mold identity.
Organization-scoped uniqueness can be reconsidered only after authoritative
mold ownership is established.

All identity foreign keys use restrictive deletion. A Product mold,
organization, creator, or releaser cannot be deleted while a revision
references it. No cascade can erase revision history.

## 5. Minimal lifecycle decision

The schema provides vocabulary and integrity checks for a future lifecycle:

1. **Draft** — editable only through a future authorized service.
2. **Released** — release evidence is required.
3. **Superseded** — release evidence remains required; later service logic must
   identify the replacement and enforce single-writer rules.
4. **Archived** — release evidence remains required; retention is preserved.

Released definitions are intended to be immutable, but immutability cannot be
enforced safely by this DDL alone without a mutation service, audit contract,
and concurrency policy. No release, supersession, retirement, or downstream
reference API is implemented here.

Duplicate revision numbers are blocked by the unique key. Effective-window
overlap, one-current-released-revision rules, and replacement relationships
remain future service-level validations. No recipes or production references
are introduced prematurely.

## 6. Runtime authorization boundary

No IPM-local organization resolver or global current-organization state exists.
Future runtime services must:

1. receive the existing explicit organization selection;
2. resolve it through `ProductOrganizationScope` and Platform membership
   validation;
3. retain only the validated opaque membership context;
4. authorize the referenced mold through a separately accepted Products
   ownership contract; and
5. apply the organization predicate to every IPM revision read or mutation.

A revision row containing `organization_id` is not sufficient authorization.
Until step 4 exists, revision mutation and runtime revision reads remain
unavailable. No controller, service, POST route, permission, UI, Part 360
projection, or BOM integration was added.

## 7. Database acceptance boundary

No database process, connection, SQL execution, migration application, or
infrastructure provisioning occurred in IPM-011. The governed `OH_IPM`
acceptance remains outstanding.

When independently authorized, the disposable acceptance must verify:

- module discovery, dependency ordering, and migration ledger ownership;
- exact `INT`/`BIGINT`/organization FK compatibility;
- creation and idempotent replay;
- unique revision numbers and lifecycle checks;
- restrictive deletion of mold, organization, and user identities;
- preservation of existing `part_molds` rows;
- absence of duplicate mold tables or runtime DDL;
- rollback and cleanup behavior.

Static probes are not database proof.

## 8. Verification and next milestone

IPM-011 module foundation probe: **43/43**.

The next bounded milestone is **IPM-012: read-only mold revision contract
preparation**, only after owner approval of the unresolved Products mold
organization-ownership dependency. It may define a read-only resolver/service
shape and database-free denial cases, but must not expose revision mutation or
claim tenant-safe mold access until that dependency is closed.

Part 360 remains separately blocked under IPM-010. No Part 360, BOM, Product,
machine, material, production, QC, dispatch, or maintenance behavior changed.
