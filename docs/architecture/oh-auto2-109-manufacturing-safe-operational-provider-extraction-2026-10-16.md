# OH-AUTO2-109 — Manufacturing Safe Operational Provider Extraction

**Date:** 2026-10-16
**Status:** Accepted candidate extracted and governed
**Scope:** One `/me` summary contribution; non-Core; isolated non-database proof

## Selected candidate

Material Management's aggregate summary was selected because OH-AUTO2-104
already proves `MaterialManagementService::summaryReadOnly()` checks source
storage before reading, avoids schema preparation and demand recalculation, and
performs zero writes. The contribution exposes only aggregate counts; it does
not emit material identities, rows, employee data, or arbitrary operational
records.

## Extraction boundary

`HostMaterialSummaryContributionService` is a separate Manufacturing-owned
provider. It calls only:

1. the existing Platform module policy for `materials`,
2. the existing `MaterialAccessService::canAccessAny()` permission contract,
3. the existing `MaterialManagementService::summaryReadOnly()` source path.

It does not call `HostSurfaceContributionService`,
`ModuleSurfaceWidgetFactory`, or any dynamic module widget registry. The
composite Manufacturing provider remains unchanged for all other surfaces.

The descriptor is `manufacturing.me.material_summary` and is admitted as
`read_only_aggregate` with `summary_widgets`, `module_authority_aggregate`,
and `material_module_acl` evidence. It requires existing Manufacturing app
assignment plus the existing `materials` module authority.

## Output and authorization

- Region: `summary_cards` only
- View kind: `kpi`
- Widget type: `alert` when shortage/inbound risk exists, otherwise
  `informative`
- Placement: `dashboard_summary`
- Profiles: leader, admin, read-only
- Destination: existing GET route `/apps/manufacturing/materials`, retained
  for downstream route authorization
- Data: shortage-risk, delayed-inbound, and storage-overflow aggregate counts
- Missing policy, denied module authority, unavailable storage, incompatible
  storage, or exceptions: empty contribution

## Isolated proof

`apps/Manufacturing/Tests/probe_host_material_summary_extraction.php` invokes
the extracted callback with fake policy, authorization, storage, schema, and
demand collaborators. It also invokes the real Platform admission service
against the version-controlled Manufacturing descriptor and rejects a
tampered provider binding.

Result: **16/16 assertions passed**.

The existing proofs remain green:

- Material read safety: **18/18**
- Provider authorization: **12/12**
- Restricted containment: **15/15**
- Governed admission: **9/9**
- Approved provider inventory/order: **7/7**

No installed database, SQL, schema, migration, browser, activation,
deployment, or Core operation was performed.

## Remaining suppression

The broad Manufacturing summary and monitoring callbacks remain separately
admitted only under their existing OH-AUTO2-107 status and are not replaced by
this extraction. No SBAIO personnel, attendance, payroll, leave, or watchlist
provider was enabled.
