# IPM Architecture Verification — Source-Evidenced Findings

**Status:** IPM-002 verification record. **Read-only. No database, no execution, no runtime change.**
**Date:** 2026-11-17
**Baseline:** `43111ce` → IPM-001 report → `634b041` → IPM-002 docs.

Method: static inspection of migrations, services, manifests, and contracts in `/Users/lazydeepak/dev/OdareHub-IPM` (branch `ipm`). Every finding cites its source. Where evidence is absent, the gap is stated — nothing is inferred as working.

---

## 1. Verified implementation facts (source-backed)

| # | Fact | Source |
|---|---|---|
| F1 | `products` table: INT PK; `parts_number` global UNIQUE; migrations 001–011 (~15 ALTERs); `item_ref INT NULL` added by migration 012 with index | `apps/Manufacturing/modules/Products/migrations/001..013*.sql` (`012_add_item_ref.sql`, `013_add_manufacturing_bom.sql`) |
| F2 | `part_molds`: INT PK, `UNIQUE(product_id, mold_code)`, injection-molding columns (cavity_count, is_family_mold, tool_weight_kg, clamp/shot/dims, runner_type, cycle_time_sec, preferred_machine_group/type, status) | `Products/migrations/008_part_molds_and_material_yield.sql`, `009_part_molds_machine_fit_alignment.sql`; also present in stale `tools/restore/live-schema.sql` lines ~1418 |
| F3 | Machines carry injection-molding specs: `clamping_force_ton`, `shot_capacity_g`, `tie_bar_spacing_mm`, `platen_size_mm`, `min/max_mold_height_mm`, `preferred_materials`, `machine_group`, `machine_type` | `Machines/migrations/002_machine_specs.sql` |
| F4 | `part_material_map`: qty per part, usage, `yield_parts_per_kg`, `scrap_pct`, effective windows, uniques on (product, material) pairs | `tools/restore/live-schema.sql` (part_material_map), live implementation in Products module |
| F5 | `manufacturing_bom` / `manufacturing_bom_line` exist; version/revision/status; **no FKs from lines to items**; lines FK→bom with CASCADE; opaque INT refs validated by `BomService` against `products`; **DDL co-declared byte-identically in Products/013 and Bom/001**; `BomService::ensureSchema()` also issues runtime `CREATE TABLE IF NOT EXISTS` (BOM triple declaration) | `Products/migrations/013_add_manufacturing_bom.sql`, `Bom/migrations/001_bom_contract.sql`, `Bom/BomService.php` lines 68–105 |
| F6 | `shared_items` table **does not exist** anywhere (schema, migration, runtime). `ItemIdentityService` is JSON-file-backed (`/tmp/shared-items-store.json`); `ItemIdentityContract` exists; `probe_item_identity.php` is DB-free; Composer `Shared\` PSR-4 mapping **absent** from `composer.json` | `shared/Item/Foundation/**`, `composer.json` (no matching key) |
| F7 | Shared Parties: `parties` BIGINT PK, DB-wide, no tenant columns, `NO_AUTOMATIC_DEDUPE` service, committed (`001_create_parties.sql`, `PartyService.php`) | `apps/Parties/**` |
| F8 | Platform Organization: `org_companies` INT PK; `org_user_memberships` BIGINT PK, generated active-uniqueness key, full FK set to users/org_companies, validity CHECK constraints; fail-closed `AuthenticatedOrganizationContextResolver`; Procurement adopts the same pattern via `ProcurementOrganizationScope` | `Organization/migrations/001,004,006`, `Platform/Services/AuthenticatedOrganizationContextResolver.php`, `Procurement/Services/ProcurementOrganizationScope.php` |
| F9 | Procurement tenant pattern: committed migration `20261031_000001_add_request_organization_ownership.sql` adds **nullable** `organization_id INT` + FK, **no backfill of legacy rows**, comment "Existing rows remain NULL/unowned; no creator, email, or provenance backfill" | `apps/Procurement/migrations/20261031_000001_add_request_organization_ownership.sql` |
| F10 | Core migration ledger: `core_app_migrations` (unique `(app_key, migration_name)`, `applied`/`failed`, checksum); exact-step `executePlannedStep()` preserves failed rows; per-owner lexical batch execution | `app/Services/AppMigrationService.php`, `AppManager/migrations/20260401_000001_create_core_app_platform_tables.sql` lines 36–47 |
| F11 | Manufacturing `production_entries`: no lot/batch/serial; FK-less (machine_id/product_id by index); `qc_entries` has approval/workflow columns and product_id/daily_order_id/production_plan_id links | live-schema.sql (production_entries ~line 1580, qc_entries ~line 1603), module DDL |
| F12 | `stock_ledger_entries`: enum movements incl. `PRODUCTION_IN/OUT`; polymorphic `reference_no`/`source_module`/`source_id`; no FKs; Manufacturing-owned | `Ledger/migrations/001_stock_ledger_entries.sql` |
| F13 | **Zero** matches for `lot_number` / `batch_number` / `serial` in any committed migration — no genealogy primitives exist | repo-wide SQL grep |
| F14 | No `organization_id` exists in **any** Manufacturing migration — Manufacturing is fully global today | repo-wide grep on `apps/Manufacturing/**/*.sql` |
| F15 | Module lifecycle: Manufacturing modules declare `plugin.json` (`suite`, `group: ipm`, `owner_app`, `module_type`, `target_maturity_level`); Manufacturing `manifest.json` declares bundle deps (`QRCode>=1.0.0`) and native/legacy module sets; modules install via `install.php` + `core_app_migrations` | `apps/Manufacturing/manifest.json`, `modules/*/plugin.json`, `modules/Products/install.php` |
| F16 | IPM terminology exists only as legacy group labels and UI strings ("Parts master for IPM workflows"); no `InjectionMolding` module, route family, or permission keys exist | `modules/Products/plugin.json`, `Products/Resources/lang/en.php` |
| F17 | Runtime DDL is widespread: ~45 Manufacturing PHP files contain `CREATE TABLE` (services, controllers, `install.php`); `MaterialSchemaService` creates `materials` family at runtime; OH-AUTO1-047/048 removed runtime DDL only from selected paths (stage transitions, demand reads, upstream) — NOT from MaterialManagement or Bom | repo grep; OH docs (history preserved) |

## 2. Contract verification results

### 2.1 D1 — Shared Item persistence

- **Compatible.** `canonical-item-contract.md` §3–4 already specifies `shared_items` (status enum draft/active/deprecated/archived; code_ref; name; category_ref; metadata_ref). IPM-002 adopts it verbatim + resolves the type contradiction (§3.2).
- **Conflict to record:** the contract's table proposal used `item_id BIGINT UNSIGNED` while committed `products.item_ref` and `mfg_procurement_demands`-style references are `INT`. Resolved by the additive-widening design in `schema-proposal.sql` PHASE 4 (new `item_id` columns; legacy columns preserved). No silent type unification.
- **Incompatibility to document:** `shared_item_identity_map` is **new** — not in any prior contract. It is required precisely because implicit substitution is rejected. Flagged as a design addition, not a silent extension of the canonical contract.

### 2.2 D2 — INT/BIGINT reconciliation

- `users.id` INT, `org_companies.id` INT, Manufacturing ids INT, `parties.id` BIGINT, `org_user_memberships.id` BIGINT, `procurement_*` BIGINT — **mixed identity widths are the established reality.** IPM-002 therefore keeps Manufacturing ids INT (legacy domain identifiers), canonical identity BIGINT UNSIGNED, tenant FK INT.
- `manufacturing_bom.finished_item_ref` / line `component_item_ref INT` — **cannot be widened in place** without breaking the co-declared DDL identity and existing rows. Hence new `finished_item_id`/`component_item_id BIGINT NULL` columns. Documented as a permanent two-phase identity seam until Phase 9 legacy-column retirement milestone.

### 2.3 D3 — Manufacturing tenant scope

- **Target:** `organization_id INT NULL` (matching Platform DDL exactly) added to all Manufacturing-owned global tables; NOT NULL deferred to the audit-gated Phase 7/9.
- **Evidence the pattern is viable:** Procurement's committed `20261031_000001` migration is the precedent (nullable INT + FK + no backfill + fail-closed scope resolution).
- **Incompatibility to record:** Manufacturing has **no** org-context plumbing today — no resolver calls, no scope keys, no indexes on tenant columns. The tenant column is additive; enforcement is Phase 6+ application work, not a schema migration. Claiming tenant isolation "exists" for Manufacturing today would be false.

### 2.4 D4 — Genealogy granularity

- Lot-first model adopted; splits/merges/rework/scrap/quarantine via `ipm_lot_relationships`; consumption M:N via `ipm_lot_consumptions`; output linkage via `produced_by_production_entry_id`; serial optional via `ipm_serials`.
- **Honest limit:** shot-level traceability is **not** claimed. `ipm_production_context.cavity_no` records the cavity used; per-cavity quantities would require per-entry cavity counts which `production_entries` does not capture. If shot-level genealogy is required later, it is a data-capture problem (operator capture / machine integration), not a schema-only change.

### 2.5 D5 — IPM module boundary

- Dedicated Manufacturing-owned `InjectionMolding` module is compatible with existing conventions (`plugin.json`, module owns `ipm_*` tables only, no duplicate masters).
- `part_molds` ownership remains Products-owned in this phase; IPM references by FK. Phase separation documented in `ipm-module-boundary.md` §4.

## 3. BOM triple-declaration — assessment (not rewritten)

**State:** (a) Products/013, (b) Bom/001 — byte-identical DDL, deliberately idempotent co-declaration; (c) `BomService::ensureSchema()` executes the same DDL at runtime on every service entry.

**Risks:** drift between (a)/(b) silently breaks module installs on greenfield environments; (c) masks migration-ledger gaps and couples reads to DDL; any IPM change that touches BOM DDL in one location only perpetuates the flaw.

**IPM-002 stance:** no rewrite in this task. Proposed consolidation milestone (separately authorized): make Bom/001 the single owner of DDL; convert Products/013 into a verified no-op/absence marker for fresh installs (keeping historical application for upgrades); remove `ensureSchema()` after disposable-verified proof that every install path applies Bom/001 (via `core_app_migrations`). Until then, any BOM schema change MUST be applied to all three loci and a divergence probe should be added.

## 4. Migration verification (static only)

- Type check: all new `organization_id` references type `INT` ↔ `org_companies.id` INT — consistent with Platform DDL (`001_create_organization_tables.sql`) and Procurement migration.
- FK targets exist: `users.id` INT, `part_molds.id` INT, `machines.id` INT, `materials.id` INT, `production_entries.id` INT, `manufacturing_bom.id` INT — all INT, matching references.
- New `BIGINT UNSIGNED` keys never collide with legacy ids.
- `CHECK` constraints used are supported by the MySQL series in use (8.x/9.x per OH-AUTO1-068 disposable runs); `chk_identity_map_verified` and release-approval checks rely on nullable semantics, verified compatible.
- Unique keys are tenant-scoped (`organization_id, …`) where cross-tenant collision is possible (`uq_ipm_lot_code`, `uq_ipm_recipe`).
- `ipm_mold_cavities` CASCADE is deliberately confined to draft-snapshot children; no audit table cascades.

## 5. Unresolved risks / genuine blockers

| # | Risk / blocker | Owner | Impact |
|---|---|---|---|
| B1 | Material management tables are **not migration-owned** (runtime DDL). Phase 3 expects them in the additive pattern but cannot guarantee FK creation on a drifted live schema until a disposable-verified migration-coverage probe passes | MaterialManagement owner | Phase 3 may exclude `materials` family until verified; IPM recipes referencing `materials.id` remain INT without FK until then |
| B2 | Zero visibility into live production data quality (orphan `parts_number` dupes, `is_active` drift). P0.2/P0.3 are unexecuted by definition | DBA / owner | Backfill determinism rule cannot be attested without one data audit on a disposable restore |
| B3 | `sales/dispatch` links to lots require QC/Dispatch cooperation (`reference_type` convention) — no written acceptance from those module owners | QC, Dispatch owners | Genealogy end-to-end closure unproven beyond production entry |
| B4 | Party linkage for suppliers/vendors: `materials.vendor_id` is currently unconstrained text-ish; binding lots to `parties.id` requires Party backfill decision (Session B) | Parties owner | Raw-material lot genealogy incomplete until Parties lands |
| B5 | Shot-level genealogy / machine-integration data capture does not exist anywhere | Product owner | Any "shot-level" claim would be false today |
| B6 | `ipm_serials` enablement is an owner policy decision; design assumes it stays off by default | Owner | Serial tables may remain uncreated; contract documents them as optional |
| B7 | `ipm_mold_revisions` effective-window overlap cannot be excluded by SQL unique index; requires service-layer `SELECT … FOR UPDATE` + validation; concurrent release transactions must be single-writer per mold | IPM module (future) | Documented; no implementation authorized |
| B8 | Legacy BOM refs (`finished_item_ref`) remain INT until Phase 9; IPM consumers must not treat them as shared item ids | IPM module (future) | Contract explicitly forbids silent substitution |

**Not blockers:** single-organization installs (determinism rule covers them), lack of live DB access for this phase (by design), absence of `InjectionMolding` module scaffold (Phase 5 ordering).

## 6. Recommendation — first implementation slice

**IPM-003 (recommended): Shared Foundation persistence + identity-map migration, disposable-verified.**

Scope: `shared_items` + `shared_item_identity_map` migrations as a Shared-owned app lifecycle step; `probe_item_identity.php` extended (DB mode behind the existing disposable attestation); zero change to Manufacturing.

Rationale: every downstream phase (identity reconciliation, tenant backfill, IPM tables) consumes `shared_items` and the map ledger. It is the smallest slice that is independently verifiable end-to-end with existing runner-owned tooling, reversible (two new tables, zero legacy impact), and unblocks all IPM schema work without exposing production data.

Proposed follow-on sequence thereafter: IPM-004 read-only Manufacturing identity/tenant audit on disposable restore → IPM-005 IPM module scaffold + migrations 001–006 → IPM-006 lot genealogy read path → IPM-007 Part360/mold-revision UI (separately authorized).

## 7. Verification evidence summary

- Files inspected: 60+ (migrations, services, manifests, contracts, live-schema snapshot) — all paths quoted above.
- Live database touched: **none**.
- Tests executed in this phase: none (static only).
- Schema executed: **none**.
- Commits produced: see git log on `ipm` (IPM-002 deliverables commit).
