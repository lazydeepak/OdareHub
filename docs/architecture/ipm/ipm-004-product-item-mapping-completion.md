# IPM-004 Product-to-Shared Item Mapping

**Status:** Bounded schema and verification complete; runtime mutation deferred.
**Date:** 2026-11-23
**Baseline:** `c4e33627c2300d66f280693f2f4c5cbf55347ba1`

## Cardinality decision

The mapping is many Products to one canonical Shared Item, with each Product
having at most one mapping:

- `product_id` is unique;
- `item_id` is intentionally not unique.

The repository contains no Product variant, revision, or customer-specific
identity model that could establish separate canonical identities. The Shared
Items extension contract also states that one Shared Item may be presented in
different Manufacturing contexts. That is stronger evidence for allowing
multiple Product representations than the legacy `item_ref` rule is for
forbidding them. `PartItemRefService` rejects duplicate values only in the
legacy nullable `products.item_ref` compatibility column; it is not an
authoritative cardinality contract for the new mapping table.

Separate canonical Shared Items for one Product remain rejected by the unique
Product constraint. A future owner decision may narrow the relation, but this
milestone does not encode that irreversible restriction.

## Implemented scope

`apps/Manufacturing/modules/Products/migrations/014_product_item_map.sql`
adds `manufacturing_product_item_map` with:

- `product_id INT` foreign key to `products(id)`;
- `item_id BIGINT UNSIGNED` foreign key to `shared_items(item_id)`;
- unique constraint on `product_id` only;
- explicit `item_id` index for reverse lookup and foreign-key performance;
- `ON DELETE RESTRICT` and `ON UPDATE CASCADE` for both references;
- no backfill, `organization_id`, user/actor field, Product alteration, BOM
  alteration, or generic identity-map table.

The migration is owned by the existing Products legacy-module migration path.
No mapping service or runtime route was added.

## Tenant boundary

Runtime mapping mutation is intentionally unavailable. Products has no
`organization_id`, no Product-owned organization resolver integration, and no
authoritative organization ownership for legacy rows. Shared Items are global
and tenant-free by contract. Existing Product role/assignment checks do not
prove organization membership and cannot safely authorize a cross-domain
mapping write.

No organization was inferred, no legacy Product was assigned, and no
unassigned administrative mapping state was invented. A future mutation
milestone requires an approved Product organization contract and the existing
Platform authenticated organization context before adding writes or tenant
scoping.

## BOM compatibility

`finished_item_ref` and `component_item_ref` remain unchanged. `BomService`
continues to resolve them through `products.item_ref`; this milestone adds no
second BOM identity-resolution path and performs no BOM backfill.

The mapping can support a later BOM migration by providing a verified
Product-to-Shared Item relation, but legacy BOM columns remain the current
Manufacturing-local reference seam until a separately authorized widening and
backfill milestone.

## Verification

- Product mapping contract probe: **19/19**
- PHP lint: mapping probes and isolated runner
- Guarded disposable database probe: migration apply/replay, FK enforcement,
  mapping creation/lookup, duplicate Product rejection, multiple Product
  representations of one Item, missing-reference rejection, transaction
  rollback, restrictive deletion, concurrent duplicate mapping, and absence
  of inferred tenant column
- Existing Shared Item compatibility and BOM behavior were inspected and
  preserved.

The governed runner starts only the runner-owned temporary MySQL instance with
`--no-defaults`, loopback binding, an attested temporary socket/datadir, and
`--mysqlx=0`. Disabling X Plugin is required because MySQL 9.6 otherwise
opens its separate wildcard X Protocol listener, which would fail the existing
no-wildcard disposable authorization check. This changes neither the
authorization policy nor any installed/production configuration.

No installed/development/production database, deployment, module activation,
or merge to `main` is part of this milestone.

## Deferred

- Runtime mapping mutation and UI.
- Product organization ownership and tenant-scoped reads/writes.
- Automatic Shared Item creation or legacy backfill.
- `products.item_ref` retirement or repointing.
- BOM identity-column migration.
- Narrowing the mapping to one Product per Shared Item without stronger owner
  evidence.
