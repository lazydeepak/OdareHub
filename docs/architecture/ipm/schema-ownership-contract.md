# IPM Schema Ownership Contract — Shared → Manufacturing → Injection Molding

**Status:** Design baseline (IPM-002). No SQL executed. No runtime changes.
**Date:** 2026-11-17
**Planner decisions:** D1–D5 approved as the design baseline.
**Verification companion:** `architecture-verification.md` (source evidence).

Ownership classes used below:

- **Shared identity** — canonical cross-domain identity owned by Shared Foundation.
- **Platform/tenant** — Platform-owned organization and membership authority.
- **Manufacturing master/child** — Manufacturing-owned domain data.
- **IPM extension** — Injection Molding domain extension (Under Manufacturing's product boundary, owned by the `InjectionMolding` module).
- **Core ledger** — `core_app_*` installation/migration machinery (Core-owned, locked).
- **Legacy transitional** — Preserved, deprecated, never edited in place during IPM work.

Tenant classes:

- **Global reference** — not organization-owned; shared system-wide (same pattern as `parties`, `org_companies` root).
- **Organization-owned** — tenant-scoped; must carry `organization_id` (INT → `org_companies(id)`) once verified.
- **Dependent child** — scoped via parent; carries a **denormalized** `organization_id` for authorization pushdown and composite uniqueness, enforced equal to its parent by application contract.

---

## 1. Shared Foundation

| Object | Owner | Kind | Tenant class | Existing state | Role in IPM design |
|---|---|---|---|---|---|
| `shared_items` (proposed) | Shared Foundation (`shared/Item/Foundation`) | Shared identity (master) | Global reference | Designed only — `canonical-item-contract.md`; no table yet; `ItemIdentityService` uses JSON test store; Composer `Shared\` autoload not registered | **Canonical item identity.** `item_id BIGINT UNSIGNED`. Single source of truth for "what is this thing". |
| `shared_item_identity_map` (proposed) | Shared Foundation | Mapping ledger | Global reference | Does not exist | Explicit mapping: `legacy_table` + `legacy_id` (INT) → `shared_items.item_id`. Verification-gated (`proposed` → `verified` / `rejected`). Replaces implicit casting/substitution. |
| `parties` | Shared (`apps/Parties`) | Shared identity | Global reference (DB-wide, BIGINT) | Committed; `Parties v1 Slice 4`; no tenant columns; `PartyService` probes | Vendor/customer/owner identity for IPM records. IPM references `parties.id` where a party is needed; does not duplicate supplier/customer masters. |

## 2. Platform — Organization and tenant authority

| Object | Owner | Kind | Tenant class | Existing state | Role |
|---|---|---|---|---|---|
| `org_companies` | Platform (Organization) | Tenant root | Global (tenant registry) | Committed; INT PK; `org_companies.id` is the `organization_id` target for every FK in this design | Tenant root. Unchanged. |
| `org_branches`, `org_hierarchy`, `org_fiscal_settings` | Platform | Tenant hierarchy | Organization-owned (by `company_id`) | Committed | Referenced, never duplicated by IPM. |
| `org_user_memberships` / `user_organization_memberships` | Platform | Membership authority | Organization-scoped association | Committed (`006_create_user_organization_memberships.sql`); fail-closed resolver exists (`AuthenticatedOrganizationContextResolver`) | Authorization evidence. IPM mutation paths must require resolved organization context (same pattern as `ProcurementOrganizationScope`). |
| `AuthenticatedOrganizationContextResolver` | Platform | Service boundary | — | Verified present | The only sanctioned org-context source for IPM authorization. No IPM-specific ACL. |

## 3. Manufacturing — existing (preserved, reconciled)

| Object | Owner | Kind | Tenant class | Existing state | Action |
|---|---|---|---|---|---|
| `products` | Manufacturing (Products) | Master | **Organization-owned (target)** | Committed; INT PK; `parts_number` global UNIQUE; INT `item_ref` (nullable, idx) | **Preserved.** Gains nullable `organization_id` + `item_id BIGINT NULL` FK → `shared_items`. `item_ref` legacy column preserved (deprecated, never re-pointed silently). |
| `machines` | Manufacturing (Machines) | Master | Organization-owned (target) | Committed; INT PK; clamp/shot/platen/mold-height columns | **Preserved.** Gains nullable `organization_id`. |
| `part_molds` | Manufacturing (Products) | Master | Organization-owned (target) | Committed; INT PK; `UNIQUE(product_id, mold_code)`; no FKs enforced | **Reconciled into IPM by reference, not moved.** IPM adds `ipm_mold_revisions` 1:N. No column retype. No data move in this phase. |
| `part_machine_map` | Manufacturing | Junction | Organization-owned (target) | Committed; UNIQUE(product_id, machine_id, is_active) | Preserved. Gains nullable `organization_id`. |
| `part_material_map` | Manufacturing | Junction / yield config | Organization-owned (target) | Committed | Preserved. Gains nullable `organization_id`. |
| `materials` (+ `material_orders`, `material_reservations`, `material_ledger`, `material_capacity`) | Manufacturing (MaterialManagement) | Master / child | Organization-owned (target) | Committed via runtime DDL (`MaterialSchemaService`) — **not migration-owned** | Preserved. Tenant column added by a guarded migration; runtime DDL remains a documented compatibility exception until separately authorized. |
| `manufacturing_bom`, `manufacturing_bom_line` | Manufacturing (Bom) | Master / child | Organization-owned (target) | Committed; co-declared in Products/013 + Bom/001; INT opaque refs; no FKs | Co-declaration **documented, not rewritten** (see `architecture-verification.md` §7). Gains nullable `organization_id`, `finished_item_id` / `component_item_id` BIGINT NULL. |
| `production_plans`, `production_queue`*, `production_entries`, `daily_orders`, `pre_orders` | Manufacturing (Production*) | Master / transaction | Organization-owned (target) | Committed (`production_queue` is service-level; see module) | Preserved. Gain `organization_id`. `production_entries` is the IPM genealogy anchor; extended by IPM via additive join table, never retyped. |
| `mfg_assembly_entries`, `mfg_part_demands`, `mfg_procurement_demands`, `mfg_production_plan_candidates`, `mfg_upstream_generation_links`, `mfg_stage_readiness` | Manufacturing | Transaction / demand | Organization-owned (target) | Committed | Preserved. Gain `organization_id`. |
| `qc_plans`, `qc_entries` | Manufacturing (QC) | Master / transaction | Organization-owned (target) | Committed; workflow columns present | Preserved. Gain `organization_id`. QC records may reference IPM lots; no IPM columns added to QC tables in Phase 1 — linked via `ipm_lot_relationships.reference_type='qc'`. |
| `dispatch_entries` (+ transition tables) | Manufacturing (Dispatch) | Transaction | Organization-owned (target) | Committed | Preserved. Gain `organization_id`. Shipment lots link via `ipm_lot_relationships.reference_type='dispatch'`. |
| `stock_ledger_entries` | Manufacturing (Ledger) | Immutable-ish ledger | Organization-owned (target) | Committed; polymorphic references | Preserved. Gain `organization_id`. |
| `mfg_dispatch_bundle_*`, `operator_tasks`, etc. | Manufacturing / Shell | Runtime | Mixed | Committed | Out of scope for IPM Phase 1; classified but not altered. |

## 4. IPM extension (proposed — `InjectionMolding` module)

| Object | Owner | Kind | Tenant class | Cardinality | Deletion policy |
|---|---|---|---|---|---|
| `ipm_mold_revisions` | IPM module | Child of `part_molds` (one-to-many) | Dependent child (`organization_id` denormalized) | 1 mold → N revisions | RESTRICT on mold; revisions are audit history |
| `ipm_mold_cavities` | IPM module | Grandchild | Dependent child | 1 revision → N cavities | CASCADE within revision (snapshot children) |
| `ipm_mold_maintenance` | IPM module | Transaction / history | Dependent child | 1 mold → N maintenance events | RESTRICT (audit evidence) |
| `ipm_process_recipes` | IPM module | Master (versioned) | Organization-owned | 1 mold_revision → N recipes (per scope window) | RESTRICT; superseded, never deleted |
| `ipm_recipe_parameters`? — **rejected** | — | — | — | Typed columns on `ipm_process_recipes` instead | JSON only as bounded `extra_parameters` |
| `ipm_production_context` | IPM module | Child of `production_entries` | Dependent child | 1 production_entry → ≤1 context row | RESTRICT on entry |
| `ipm_lots` | IPM module | Master (lot identity) | Organization-owned | N lots per item; hierarchical via `parent_lot_id` | RESTRICT |
| `ipm_lot_relationships` | IPM module | Junction / lineage | Dependent child | M:N lot ↔ lot | RESTRICT |
| `ipm_lot_consumptions` | IPM module | Child of production entry | Dependent child | M:N lot ↔ production entry | RESTRICT |
| `ipm_serials` (optional) | IPM module | Extension | Dependent child | 1 lot → N serials | RESTRICT; feature-gated |

## 5. Prohibited patterns (this contract forbids)

- No duplicate Product, Machine, Material, Item, Work Order, or Mold master.
- No typing new identity as INT where canonical identity is `BIGINT UNSIGNED` (`products.id` is a **legacy domain identifier**, preserved as-is, never substituted).
- No `NOT NULL organization_id` before the §"Backfill determinism rule" in the migration plan is satisfied and attested.
- No runtime `CREATE TABLE IF NOT EXISTS` in IPM services (no `ensureSchema()` class of DDL).
- No foreign key from Shared to a tenant-owned table, or cross-tenant FK of any kind. FK `organization_id` targets only `org_companies(id)`; all other FKs are within the same organization and enforced in service transactions.
- No JSON blobs as substitutes for essential keys, FKs, or enums.
- No cascade deletes across identity or audit tables.

## 6. Cross-domain relationship map (target state)

```text
org_companies (tenant root)
  ├─ products ──item_id──> shared_items (global identity)
  │     └─ part_molds ──< ipm_mold_revisions ──< ipm_mold_cavities
  │             └─ ipm_mold_maintenance (shot-life, PM)
  │     └─ products ──< ipm_process_recipes (mold_revision × machine × material)
  ├─ production_entries ──< ipm_production_context (mold_revision, recipe, operator, shift, cavity)
  │     └─ ipm_lot_consumptions >── ipm_lots (inputs consumed)
  │     └─ ipm_lots (output lot, produced_by_production_entry_id)
  ├─ ipm_lots ──< ipm_lot_relationships >── ipm_lots (split/merge/rework/scrap/quarantine)
  │     └─ ipm_serials (optional extension)
  └─ qc_entries / dispatch_entries (linked via ipm_lot_relationships.reference_type)
```

Cardinality and lifecycle dependencies are encoded in `schema-proposal.sql`.
