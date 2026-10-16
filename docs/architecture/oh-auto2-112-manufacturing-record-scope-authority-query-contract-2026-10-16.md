# OH-AUTO2-112 — Manufacturing Record-Scope Authority and Query Contract

**Date:** 2026-10-16
**Status:** Investigation complete — **OPTION C: BLOCKED**
**Scope:** Read-only source investigation and isolated fixture verification

## Executive decision

The existing system has an authoritative operational-scope representation for
machine, part, task-type, department, branch, and ownership dimensions. It
does not currently provide a complete Material Management record-scope
contract that can be applied consistently to every source used by the Material
aggregate.

Material module permission (`MaterialAccessService::canAccessAny()`) proves
module/section authority only. It does not prove access to the complete
material universe and cannot authorize `summaryReadOnly()` as a whole-domain
aggregate.

Material aggregate restoration is therefore blocked. The OH-AUTO2-111
containment remains required. No new authorization model, source filter, or
provider admission is introduced by this investigation.

## Scope authority and semantics matrix

| Dimension | Authoritative source | Representation | Deny semantics | Existing enforcement | Material support |
|---|---|---|---|---|---|
| Machine | `UserDashboardAssignmentService::readOnlyPreflight()` over `user_operational_scopes` | `scope.machine_ids` | `-1` becomes restricted/unavailable | Production and machine projections filter `machine_id` before delivery | **Unsupported for Material**: Material sources have no machine relationship |
| Part/product | Same preflight | `scope.part_ids` | `-1`, mixed denied/positive, malformed values fail closed | Processing and Parts services filter/authorize `product_id` before limits/details | **Partial only**: `part_material_map` links products to materials, but the Material aggregate does not accept or apply this scope |
| Task type | Same preflight | `scope.task_types` | `-1` becomes restricted/unavailable | Used by role/work projections and containment decisions | **Unsupported for Material**: no task-type relation in Material source tables |
| Department | Same preflight | `scope.department_code` | Non-empty is restricted/unavailable in read-only My Work containment | Current contained My Work path suppresses unsupported reads | **Unsupported for Material**: no source column/join contract |
| Branch | Same preflight | `scope.branch_code` | Non-empty is restricted/unavailable in read-only My Work containment | Current contained My Work path suppresses unsupported reads | **Unsupported for Material**: no source column/join contract |
| Ownership role | Same preflight | `scope.ownership_role` | Non-empty is restricted/unavailable in read-only My Work containment | Current contained My Work path suppresses unsupported reads | **Unsupported for Material**: no source column/join contract |

An empty scope is treated as unrestricted only after valid identity,
assignment, and scope preflight. It is not evidence that every Material row is
authorized for every caller.

## Material source-to-scope mapping

| Aggregate source | Existing row identity/relationship | Scope evidence found | Query-time status |
|---|---|---|---|
| `materials` | `materials.id` | No machine, part, department, branch, ownership, or user assignment field | Queried as the complete material set in `materialCoverageModelRows()` |
| `material_ledger` | `material_id`; optional `reference_type/reference_id` | No authoritative scope relationship; `reference_*` is not an authorization contract | Aggregated by material in `baseStockRows()` without scope predicate |
| `material_reservations` | `material_id`; optional demand source fields | No user/scope relationship; source metadata is not resolved to authorized records | Aggregated by material in `baseStockRows()` without scope predicate |
| `material_orders` | `material_id` | No machine/part/department/branch/ownership relationship | Loaded by `rawOrderRows()` without scope predicate |
| `material_capacity` | Nullable `material_id`, `location_code` | No operational assignment relationship | Loaded by `capacitySummaryRawRows()` without scope predicate |
| `part_material_map` | `product_id` ↔ `material_id` | Provides a possible part-to-material join only | Used for demand calculation, but no authorized `part_ids` input is applied |
| `products` | `products.id` | Product IDs can be scoped by existing part authority | Joined for labels and demand drivers; current Material aggregate starts from all materials/demand |
| `mfg_part_demands` | `product_id`, demand date/type | Product scope is theoretically applicable | `openMaterialDemandRows()` retrieves all production demand in the time window |
| `production_plans` | `product_id`, `machine_id`, plan date | Machine and product fields exist in this source | `productionPlanDemandRows()` is unscoped and is not sufficient to authorize the other aggregate inputs |

The missing relationship is material-wide, not merely a missing final filter.
Ledger, reservation, order, and capacity rows can affect counts and quantities
without carrying a product or machine key. Filtering demand rows by product
alone would not authorize those independent inputs.

## Aggregate and query enforcement findings

`MaterialManagementService::summaryReadOnly()` only suppresses schema
initialization and demand recalculation. It calls the existing summary path.
That path counts every row returned by the unscoped coverage and order models.

The relevant source behavior is:

- `materialCoverageModelRows()` iterates all `materials` rows;
- `baseStockRows()` aggregates ledger, reservations, inbound orders, issues,
  and adjustments by `material_id` without scope predicates;
- `capacitySummaryRawRows()` aggregates active capacity rows by material;
- `rawOrderRows()` loads all material orders;
- `openMaterialDemandRows()` loads all production demand joined through
  `part_material_map` for the date window;
- `productionPlanDemandRows()` loads all eligible production plans joined to
  the material map;
- `summary()` converts those rows into whole-domain status and delay counts.

There is no query-time scope input to these methods, no source-level
fail-closed decision for restricted Material requests, and no proof that an
aggregate result is limited before aggregation. The current implementation
therefore cannot satisfy filter-before-retrieval, filter-before-aggregation,
or filter-before-enrichment requirements for a Material host contribution.

## Existing safe query patterns that are reusable

The investigation found bounded patterns, but they do not solve the Material
relationship gap:

1. `ProcessingOperationService` applies `product_id IN (...)` to products,
   Assembly, and QC queries before `LIMIT 40` and before returning rows.
2. `ProductionOperationService` applies machine and product predicates in the
   production query and machine projection.
3. `HostSurfaceContributionService::scopeFilters()` applies owner-provided
   field mappings in SQL before count/sum results are returned.
4. `OperatorPartsWorkspaceService` rejects malformed/denied part scope and
   authorizes detail selection before the read-only Part360 call.
5. `MyWorkContributionService` deliberately returns unavailable rather than
   claiming that department, branch, ownership, or denied scope is enforced
   by an unsupported queue source.

These patterns prove that source-level enforcement is possible where a source
field is explicitly mapped. They do not authorize treating `material_id` as a
part ID or treating module permission as unrestricted record authority.

## Isolated differential verification

No database connection, SQL execution, schema preparation, activation,
browser test, or live application test was performed.

Existing isolated fixture probes were run to verify the relevant authority
properties:

- Operator Parts scope: **14/14** — list/detail consistency, malformed and
  denied handling, authorization before provider reads.
- Operator Processing scope: **35/35** — part predicates before limits,
  lane-specific authority, denied/malformed scope, and no mutation calls.
- Restricted My Work containment: **21/21** — unsupported department, branch,
  ownership, machine, and part restrictions fail closed.
- Restricted non-mutating preflight: **17/17** — malformed and denied
  evidence does not reach collection.

These are source-level proofs of existing patterns, not proof of installed
database contents or proof that Material rows are authorized.

## Candidate minimum owner-level contract

An eventual Manufacturing-owned Material query contract must be separately
authorized and must, at minimum:

1. accept only the existing authoritative preflight context, not browser scope
   claims or module permission alone;
2. normalize identity and scope with explicit unrestricted, restricted, denied,
   malformed, and unavailable states;
3. declare which dimensions each Material source supports;
4. fail closed when any aggregate input lacks an authoritative mapping;
5. apply predicates at every source query before retrieval, pagination,
   aggregation, enrichment, and URL generation;
6. preserve denied sentinels and reject mixed denied/positive evidence;
7. prove consistent cross-source material inclusion for materials, ledger,
   reservations, orders, capacity, demand, and plan inputs;
8. keep aggregation read-only and avoid schema initialization or demand
   recalculation in the host path; and
9. independently authorize any destination links rather than deriving URLs
   from aggregate output.

The contract must either establish an authoritative material-to-scope
relationship for every input or explicitly exclude that input. It must not
silently ignore unsupported department, branch, ownership, task, or machine
restrictions.

## Final classification and blockers

**OPTION C — BLOCKED.**

Exact blockers:

- no material-level or whole-domain authorization contract exists;
- Material source tables are keyed by material/product relationships that do
  not consistently carry the user's operational scope;
- independent stock, ledger, reservation, order, and capacity inputs cannot be
  authorized from product scope alone;
- `summaryReadOnly()` has no scope parameter and delegates to unscoped
  aggregation;
- department, branch, ownership, task, and machine restrictions cannot be
  mapped to all Material sources from repository evidence;
- no independent whole-domain authority was found, so Option B is not viable.

Required architectural decisions before implementation:

1. identify and approve the authoritative relationship for each Material source
   row, or explicitly declare that source unsupported;
2. decide whether a bounded Material record-scope contract can be formed from
   existing fields without adding a new authorization model; and
3. define the source-level query and aggregation proof before any capability
   descriptor or `/me` provider is restored.

OH-AUTO2-111 containment remains in force. No Material aggregate reactivation
or additional operational-provider migration is authorized by this report.
