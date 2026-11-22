# IPM-008 Central Product Read Boundary

**Status:** Bounded foundation implemented; runtime adoption remains limited.

## Read architecture inventory

A source inventory found **43 runtime PHP files** containing **74 pre-existing
`FROM products` query sites** outside test code. The sources span:

- Products detail/edit, Part 360, master snapshot, reports, dashboard, and
  Item Reference;
- QR, Shell work-entry/parts/data exchange, and Manufacturing operational
  projections;
- BOM, Demand, Processing, Production, Dispatch, QC, Orders, and search
  consumers.

The existing organization seam is
`ProductOrganizationScope::resolve()`, which delegates to the Platform
authenticated membership resolver and returns an opaque
`OrganizationMembershipContext`. It rejects missing, malformed, spoofed, and
unauthorized selections. Legacy Products with `organization_id IS NULL` remain
legacy data and are not assigned implicitly.

## Chosen seam

`ProductReadService` is Products-owned and accepts only
`OrganizationMembershipContext`. It provides the minimum lookup operations
justified by the currently adopted consumer:

- `findById()`;
- `findByPartNumber()`;
- `findByQrIdentity()`.

Each SQL branch applies `organization_id=?` at the database query boundary.
There is no raw organization-ID overload, unrestricted fallback, list API, or
implicit NULL-owner policy.

`ProductScanAdmissionService` remains the QR-specific admission adapter. It
resolves request input through `ProductOrganizationScope`, then delegates the
actual Product read to `ProductReadService`. This preserves the accepted QR
route, payload, generic anonymous failure, and authorized response structure.

## Bounded adoption

Only `/qr/product/scan` is migrated in this slice. Products detail, Part 360,
listing/search, selectors, BOM, Data Exchange, Shell, and operational
consumers are not silently switched because their current callers do not
uniformly provide validated organization context and some combine Product
reads with broader assignment, workflow, or aggregate semantics.

No unrestricted new public API was added. Existing bypass surfaces remain
documented and are not claimed isolated.

## Verification and limits

- Product read boundary probe: **14/14**.
- QR boundary probe: **36/36**.
- Product ownership contract: **26/26**.
- Product Item mapping contract: **19/19**.
- Platform organization context: **16/16**.
- Membership authority: **30/30**.
- Database-backed same-tenant, cross-tenant, NULL-owner, pagination, count,
  and migration assertions remain pending governed `OH_IPM` infrastructure.
