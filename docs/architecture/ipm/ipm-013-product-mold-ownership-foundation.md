# IPM-013 — Product Mold Ownership Foundation

**Status:** Preparatory foundation implemented; runtime enforcement and
revision activation remain disabled.

**Baseline:** `cd97c584866d1c9b17af246708b55db6c96bd120`

## 1. Scope

IPM-013 adds only:

1. a Products-owned additive migration for nullable
   `part_molds.organization_id`; and
2. a database-free `ProductMoldOwnership` admission component.

The migration is not executed by this milestone. The component is not wired
into mold handlers, compatibility aliases, Part 360, or revision routes.

## 2. Migration decision

`016_part_molds_organization_ownership.sql` extends the existing canonical
Products mold table with:

```text
organization_id INT NULL
KEY idx_part_molds_organization (organization_id)
FOREIGN KEY (organization_id) REFERENCES org_companies(id)
  ON DELETE RESTRICT ON UPDATE CASCADE
```

The migration follows the Products migration sequence after the existing mold
and Product ownership migrations. It does not recreate `part_molds`, change
the `INT` mold identity, add a duplicate master, update historical rows, or
infer ownership from Product, creator, assignment, or revision data.

The nullable column is a compatibility state for unresolved legacy rows. It is
not itself an active authorization guarantee.

## 3. Admission contract

`Plugins\Products\Services\ProductMoldOwnership` exposes:

- `resolveOrganization(...)`: delegates explicit organization selection to the
  existing `ProductOrganizationScope` and Platform membership resolver;
- `admit(array $mold, ?OrganizationMembershipContext $context)`: admits a
  mold only when the opaque context is valid and the mold's strict positive
  `organization_id` matches the context organization.

The component performs no SQL, does not resolve session state, and does not
accept a raw organization ID as ownership proof. Invalid, missing, NULL,
malformed, and cross-organization ownership all fail closed. Denied results
contain no mold row.

## 4. Legacy and revision boundary

Existing NULL-owned molds remain preserved but are denied by this
organization-scoped admission component. Existing IPM revisions remain
unchanged; `ipm_mold_revisions.organization_id` is not converted into mold
ownership evidence.

Future revision reads and mutations must first establish authoritative mold
ownership, then require revision organization equality with that owner. This
requires transaction/row-lock policy for revision creation and ownership
changes, restrictive dependent deletion, and an explicit policy for ownership
changes after revisions exist. Those persistence and concurrency controls are
not implemented here.

## 5. Runtime compatibility boundary

Existing mold creation/deletion handlers, compatibility aliases, Part 360,
runtime `ensureSchema()` calls, and revision routes remain unchanged. The
existing runtime DDL compatibility service remains in place until migration
coverage and installation compatibility are proven in governed disposable
infrastructure.

No Product or mold request path invokes `ProductMoldOwnership` in IPM-013.
The component is an admission foundation for a later bounded enforcement
slice, not an implicit authorization activation.

## 6. Verification

- Product mold ownership foundation probe: **45/45**
- IPM-010 corrected Part 360 probe: **24/24**
- IPM-011 module foundation probe: **43/43**
- IPM-012 ownership contract probe: **38/38**
- Product ownership contract: **26/26**
- Product detail boundary: **20/20**
- Product read boundary: **14/14**
- Authenticated organization context: **16/16**
- Organization membership authority: **30/30**
- Isolated source-root runner: **17/17**

Aggregate architecture, deletion-family, and read-only deployment gates remain
required before closeout. Governed `OH_IPM` database acceptance remains
pending.
