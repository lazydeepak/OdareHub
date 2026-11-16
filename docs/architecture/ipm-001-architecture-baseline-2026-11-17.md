# IPM-001 Architecture Baseline — Shared → Manufacturing → Injection Molding

**Date:** 2026-11-17
**Branch:** `ipm`
**Worktree:** `/Users/lazydeepak/dev/OdareHub-IPM`
**HEAD / origin/ipm:** `43111ce1b146fd91fd9e857e2fea359fb62814a7` (parity verified)
**Baseline:** `43111ce` (`docs(procurement): preserve runtime acceptance evidence`, 2026-10-31)
**Scope:** Read-only inventory + baseline assessment. No implementation, no DB, no migration execution, no deployment.
**Repo:** 16,461 tracked files; 71 migration `*.sql` files (79 unique `CREATE TABLE` names across them).

---

## 1. Repository and worktree state

- `ipm` was **created** from `origin/main` @ `43111ce…` (it did not exist locally or on the remote).
- Worktree `/Users/lazydeepak/dev/OdareHub-IPM` is clean, on `ipm`, upstream `origin/ipm`; local–remote commit parity confirmed after push.
- The primary checkout `/Users/lazydeepak/dev/OdareHub` was **not** modified. Its dirty worktree (16 modified tracked files, 17 untracked entries), stash `stash@{0}`, lifecycle logs, and prunable worktrees were preserved untouched. The `ipm` branch does **not** contain any of that uncommitted work (notably: Procurement PO organization-ownership migration `20261031_000002…`, `OH-MFG-ARCH-001-round-2…pdf`, and the `docs/architecture/oh-auto3-/oh-auto4-*` untracked records).
- `git fetch --prune` removed the stale remote-tracking ref `origin/fix/architecture-gates-8fbb45f` (already absent on the remote). No local branch, tag, stash, or working file was affected.
- No database connection, migration execution, activation, deployment, or source implementation change occurred.

## 2. Capability map

### 2.1 Shared Foundation

| Capability | Status | Evidence |
|---|---|---|
| Shared Item identity contract + minimal service (`shared/Item/Foundation`) | **Existing, verified (minimal slice)** | `ItemIdentityContract.php`, `ItemIdentityService.php` — storage is an isolated JSON file (`/tmp/shared-items-store.json`), **not production DB**; no `shared_items` table exists yet; `probe_item_identity.php` passes (19/19 historically). Composer `Shared\` autoload **not registered**. No runtime consumer. |
| Canonical item contract + manufacturing extension contract | **Existing and verified (docs)** | `docs/shared-items-foundation/canonical-item-contract.md`, `manufacturing-product-extension.md` |
| BOM/Recipe schema referencing item refs | **Existing, verified (schema only)** | `manufacturing_bom` / `manufacturing_bom_line` (Products migration 013 + Bom migration 001 co-declared, intentionally identical and idempotent). `BomService` validates opaque INT refs against Manufacturing `products`; no cross-domain identity registry is consulted. `BomService::ensureSchema()` also runs `CREATE TABLE IF NOT EXISTS` at runtime (compatibility DDL). |
| `products.item_ref` reference adoption | **Existing, verified (schema only)** | Products migration 012 adds `item_ref INT NULL` + index. No backfill. No Shared consumer. |
| Shared Parties | **Existing but incomplete / unverified maturity** | `apps/Parties/manifest.json` declares a `parties` shared_app (`parties.view`/`parties.manage`, consumers: hospitality, sbaio, procurement) with `001_create_parties.sql` + `PartyService` + probe. Session B must independently verify production readiness against the Party contract. |
| Shared Inventory | **Missing** | No shared inventory module. Manufacturing `Ledger` (`stock_ledger_entries`) and `MaterialManagement` (materials, orders, reservations, ledger, coverage) are Manufacturing-owned and product/material-specific. |
| Shared Commercial / Sales / Cost | **Missing** | None. |

### 2.2 Manufacturing (19 modules with `plugin.json`)

Products, Machines, PartMachineMap, MaterialManagement, Coverage, Supply, Ledger, PreOrders, DailyOrders, ProductionPlans, ProductionQueue, ProductionEntries, AssemblyPlans, AssemblyEntries, QCPlans, QCEntries, DispatchEntries, Bom, Workflow.

| Capability | Status | Evidence |
|---|---|---|
| Parts/Products master | **Existing and verified** | `products` (migrations 001–011), ~15 ALTERs accumulated; `parts_number` globally UNIQUE; `parts_name`, `model`, `producer`, `lead`, `is_active`. |
| Machines master with injection-molding machine specs | **Existing and verified** | `machines` + 002 machine_specs: `clamping_force_ton`, `shot_capacity_g`, `tie_bar_spacing_mm`, `platen_size_mm`, `min/max_mold_height_mm`, `preferred_materials`, `machine_group`, `machine_type`. |
| Mold (tool) master — IPM-specific | **Existing and verified (schema + CRUD)** | `part_molds` (Products 008, aligned 009): `mold_code`, `cavity_count`, `is_family_mold`, `tool_weight_kg`, mold dims, `min_clamp_ton_required`, `min_shot_g_required`, `runner_type`, `cycle_time_sec`, `preferred_machine_group/type`, `status`. CRUD routes (`/apps/manufacturing/products/molds/add|delete`), `ProductsController::createMold/deleteMold`, Part360 rendering + “Complete mold dimensions, clamp, shot…” guidance. **No revision/version history, no maintenance history, no shot counter, no cavity run-rate efficiency tracking.** |
| Part↔Machine fit | **Existing and verified** | `part_machine_map`; machine-fit alignment with molds (Products 009/010). |
| Part↔Material yield | **Existing and verified** | `part_material_map` (incl. `yield_parts_per_kg`, `scrap_pct`, effective-window uniqueness). |
| Materials master + orders + coverage + ledger | **Existing but incomplete** | `materials`, `material_orders`, `material_reservations`, `material_ledger`, `material_capacity`, coverage logic. **Runtime DDL still present** in `MaterialSchemaService` / `MaterialManagementService` (used by setup/mutation paths); read paths are non-mutating (OH-AUTO2-104). |
| Production planning / execution | **Existing and verified** | `production_plans`, `production_queue`, `production_entries` (machine_id, product_id, produced/rejected/good qty, Draft status, no lot/serial/batch columns). |
| Assembly | **Existing and verified** | `mfg_assembly_entries`, AssemblyPlans. |
| Quality | **Existing and verified** | `qc_plans`, `qc_entries` (incl. workflow_state/approval governance columns). |
| Dispatch | **Existing and verified** | `dispatch_entries`, transitions, workflow governance. |
| Procurement demand bridge | **Existing and verified** | `mfg_procurement_demands` (unique product+date+reference), feeds Procurement. |
| BOM/Recipe | **Existing but minimal** | `manufacturing_bom(_line)`: versioned (`version`, `revision`), draft/released/superseded/archived. **Composition only — not a versioned process recipe** (no mold params, temperatures, pressures, cycle targets). Runtime `ensureSchema()` DDL duplicates migration DDL. |
| Upstream/demand generation links | **Existing but incomplete** | `mfg_upstream_generation_links`, `mfg_part_demands`, `mfg_production_plan_candidates`, `mfg_stage_readiness`. |
| Maintenance (machines/molds preventive & corrective) | **Missing** | No maintenance module, table, or route anywhere. |
| Traceability / genealogy (lot/serial/batch: resin lot → shot → part → shipment) | **Missing** | Zero `lot_number`/`batch_number`/`serial` matches in any migration; `stock_ledger_entries` has only polymorphic `reference_no`/`source_module`/`source_id`; production entries have no genealogy linkage. |
| Tenant isolation for Manufacturing | **Missing** | No `organization_id` in any Manufacturing migration/service; `parts_number` unique globally. Only Procurement (`procurement_requests.organization_id`, merged `20261031_000001`) and Platform Organization (`org_companies`, `org_user_memberships`) carry tenant ownership. |

### 2.3 Injection Molding (productization delta)

**Existing and verified (embedded in Products/Machines):** mold master (`part_molds`), clamp/shot/mold-height machine specs, part–mold–machine fit alignment, part–material yield/scrap, Part360 engineering surface, mold add/delete routes.
**Existing but incomplete:** molds are unversioned; no mold maintenance/repair/shot-life; no material-yield actuals vs standard; no cycle-time actuals vs `cycle_time_sec`; family molds are modeled (`is_family_mold`) but not operationally supported.
**Missing:** mold revision history, process recipe (versioned injection parameters per mold×machine×material), genealogy/traceability, maintenance (mold + machine PM), IPM-specific permissions, dedicated IPM module/workspace, tenant scoping.
**Terminology:** UI/docs use “IPM” (“Parts master for IPM workflows”, `group: "ipm"` in module plugin.json); no `InjectionMolding` module or package exists.

## 3. Schema ownership and relationship findings

**Ownership today (as-implemented, not as-documented):**

- **Core** (`core_app_*` tables): install/lifecycle/migration/export/release ledgers. Locked; unchanged.
- **Platform**: `org_companies`, `org_branches`, `org_hierarchy`, `org_fiscal_settings`, `org_audit_log`, `org_user_memberships`, `user_organization_memberships` — the tenant root and membership authority. `organization_id INT` + FK to `org_companies` is the established tenant pattern.
- **Manufacturing**: all product/part/mold/machine/material/production/QC/dispatch tables. **No tenant columns. Global keys.** FK discipline is inconsistent: `manufacturing_bom_line → manufacturing_bom` (CASCADE) is the only true FK; `part_molds → products`, `part_machine_map → products/machines`, `mfg_procurement_demands → products` are index-only, no declared constraints. This is deliberate legacy style, not a design decision.
- **BOM identity seam**: `finished_item_ref`/`component_item_ref` are **opaque INTs** against Manufacturing `products.id`, explicitly not the canonical Shared Item ID (`BomService` docblock). `products.item_ref` is likewise `INT`, while the Shared Items design proposal specifies `BIGINT`. **Type contract drift** between design doc (`canonical-item-contract.md` §4) and implementation.
- **Live restore snapshot** `tools/restore/live-schema.sql` is **stale** (106 tables; lacks `manufacturing_bom`, `user_organization_memberships`, `products.item_ref`). It must not be used as a schema source of truth. Authoritative current shape = 71 migration files + module co-declared migrations + known runtime DDL (MaterialSchemaService, BomService::ensureSchema, Dispatch/QC/Workflow install.php).
- **Runtime (compatibility) DDL** still exists in ~45 Manufacturing PHP entry points (services, controllers, `install.php`). OH-AUTO1-047/048 removed it from selected paths only; `BomService` and `MaterialSchemaService` remain runtime-DDL owners by design today. Any IPM extension must not add to this class.
- **Duplication / triplication:** identity of “what is made” exists in three places: `products` (Manufacturing master), `materials` (MaterialManagement, materials are not items), and the future Shared Item — with `manufacturing_bom` referencing the third abstractly and BOM DDL **co-declared in two migration files** (Products/013 and Bom/001) that must be kept byte-identical.
- **Ecosystem convention**: modules declare `plugin.json` (`suite`, `group`, `owner_app`, `module_type`, `target_maturity_level`); apps declare `manifest.json` (`dependencies`, `migrations_path`); core installs via `AppMigrationService` + `core_app_migrations` ledger; `/apps/AppManager` exposes install/upgrade flows.

## 4. Architectural risks and duplication

1. **Identity drift (High):** Shared Item contract (`BIGINT shared_items`) vs implemented references (`INT products.id`, `INT products.item_ref`). Choosing one path late will force either a renumbering migration or a permanent impedance adapter.
2. **BOM co-declaration (High, latent):** `manufacturing_bom` DDL exists twice (Products/013 + Bom/001) *and* a third runtime copy in `BomService::ensureSchema()`. Three sources must agree; drift breaks module install.
3. **No tenancy in Manufacturing (High):** any IPM schema added without `organization_id` will either be retrofitted (invasive) or permanently single-tenant against a multi-tenant platform.
4. **Genealogy absence (High for IPM):** no lot/batch/serial construct anywhere; IPM genealogy would have to be built from scratch on `production_entries` — needs an explicit owner decision on granularity (per-shot inspect? per-lot? per pallet?) before schema.
5. **Runtime DDL sprawl (Medium):** ongoing install-time `CREATE TABLE IF NOT EXISTS` in application paths bypasses migration governance and the disposable-verification guards; adding IPM tables this way would violate the established migration ledger.
6. **Unique constraints without tenant discriminator (Medium):** `parts_number`, `machines.machine_no`, `material_code`, `part_molds(product_id, mold_code)` — all global. Tenant isolation requires either composite keys or enforced single-org deployment.
7. **Documentation-vs-implementation gap (Low/Medium):** live-schema.sql, `current-ownership-inventory.md` (“18 modules” vs 19 present), and Shared Items contract “not executed” language superseded by the 2026-09-12 slice. Docs must be corrected when IPM design begins.
8. **Procurement PO ownership (Note):** uncommitted PO `organization_id` migration exists only in the primary checkout (OH-AUTO4-PROC-006 evidence in another worktree); absent from `ipm` and from `origin/ipm`. Not an IPM blocker, but IPM genealogy/report consumers must not assume it is merged.

## 5. Proposed schema extension strategy

Layering, preserving existing owners:

1. **Shared → Shared Item (identity only).** Implement the designed `shared_items` table (additive migration, Shared/Foundation-owned, `BIGINT`) per `canonical-item-contract.md`, *after* owner sign-off on (a) the `INT→BIGINT item_ref` type reconciliation and (b) `shared_items` vs continued JSON test store. Until sign-off, opaque refs remain.
2. **Manufacturing extension tables.** Keep `products`, `part_molds`, `part_material_map` as the Manufacturing extension of item identity; no new master for “part”. BOM stays Manufacturing-owned; if Shared Item lands, `item_ref` columns widen to `BIGINT` in one governed migration, and the co-declared BOM DDL is collapsed to a single owner (Bom module) with Products/013 reduced to a guarded no-op or version-gated co-declaration.
3. **Injection Molding extension tables (new module `InjectionMolding`, Manufacturing-owned).** Additive only, never altering historical tables in place:
   - `ipm_mold_revisions` (1:N of `part_molds`): spec snapshot per revision (dims, clamp, shot, runner, cycle, machine group/type, approval state, effective window). One-to-many.
   - `ipm_process_recipes` (+ `ipm_process_recipe_lines` if parameterized): versioned injection process parameters keyed by `(mold_id, machine_id, material_id, recipe_version)` — the versioned process recipe. One-to-many from mold; many-to-many with materials via the line table.
   - `ipm_mold_maintenance` (1:N of `part_molds`): shot-life counters, PM/repair records — plugin-optional if licensing demands separation.
   - `ipm_genealogy_lots` (or equivalent): lot identity + parent links for resin batch → production entry → QC lot → dispatch lot. Many-to-many material↔output via junction tables.
   - Tenant isolation: every new table carries `organization_id INT NOT NULL` + FK to `org_companies` (nullable only if the owner explicitly approves a single-org deployment mode; default NOT NULL).
4. **Many-to-many:** generalize `part_machine_map` rather than add parallel maps; new junctions (`ipm_recipe_materials`, `ipm_genealogy_entry_refs`) follow the same composite-PK + FK pattern as `manufacturing_bom_line`.
5. **Plugin-owned optional data:** optional IPM capabilities (e.g., hot-runner monitoring, OEE feed integration) ship as plugins with their own migrations, activated via the AppManager lifecycle; they reference IPM IDs, never own core IPM identity.
6. **Migration/upgrade safety:** additive-only migrations under a new `apps/Manufacturing/modules/InjectionMolding/migrations/`; idempotent; co-declaration forbidden (single owner per table); verification exclusively through the runner-owned disposable-source-root toolchain (`OH_*` attestations) once authorized; historical migration files never edited.
7. **Documentation hygiene:** regenerate `tools/restore/live-schema.sql` or deprecate it; reconcile module counts.

## 6. Prioritized implementation dependencies

1. **Owner decisions (blocking):** (a) Shared Item DB persistence now or defer; (b) `item_ref` INT→BIGINT path; (c) Manufacturing tenant scope (org-scoped by default vs single-org stance); (d) genealogy granularity (lot vs serial vs both); (e) IPM module boundary (new `InjectionMolding` module vs extending Products/Machines).
2. **Shared Items persistence slice** (if approved): `shared_items` migration + resolver + disposable verification; BOM/products refs widened.
3. **Manufacturing migration consolidation:** collapse BOM co-declaration to one owner; remove `BomService::ensureSchema()` runtime DDL after migration coverage is attested; MaterialManagement runtime DDL remains a documented compatibility exception until separately authorized.
4. **`InjectionMolding` module scaffold:** manifest/plugin.json, module contract (`docs/architecture/MODULE-CONTRACT.md` alignment), migrations per §5.3, no routes/UI until schema + service contract is verified.
5. **Genealogy design:** model `production_entries` extension (nullable `lot_id`, `mold_revision_id`, `recipe_id`) before any write path exists.
6. **IPM capabilities sequencing:** mold revisioning → process recipes → maintenance → genealogy → tenant retrofit for Manufacturing (large; needs its own milestone).

## 7. Recommended first bounded implementation task

**IPM-002 (recommended): Injection Molding module contract + additive schema design (docs-only), gated on owner decisions 6.1.**

Deliverable: `docs/architecture/ipm-002-injection-molding-schema-contract.md` defining the exact DDL for `ipm_mold_revisions`, `ipm_process_recipes`, `ipm_mold_maintenance`, `ipm_genealogy_lots` (keys, FKs, tenant columns, uniqueness, effective windows, migration order, co-declaration prohibition), plus the products/BOM reconciliation plan (item_ref widening) — with **no SQL executed and no module scaffolded**.

Rationale: every IPM schema decision below this contract depends on the two unresolved owner decisions (Shared Item persistence, Manufacturing tenant scope). Implementing tables first guarantees rework. The contract also forces resolution of the BOM co-declaration and `BomService` runtime DDL before IPM tables join the same runtime cohort.

Fallback if the owner wants pure mechanics first: **IPM-002′** — mold revisioning additive slice only (`ipm_mold_revisions` + read-only service + probes, tenant column nullable with explicit single-org stance recorded), deferring recipes/genealogy/maintenance. Still requires the same owner decisions; scope shrinks, risk does not.

**Explicit non-goals for the first task:** no Core changes, no DB connections/migrations, no routes/UI, no runtime DDL, no backfill of legacy data, no changes to the dirty primary worktree.

## 8. Validation performed

- Worktree isolation and parity: verified (`ipm` @ `43111ce`, `origin/ipm` same, clean status).
- Evidence gathered: migrations (71 files), module `plugin.json`s (19), manifests, service docblocks, live-schema snapshot (flagged stale), Shared Items contract docs, git history.
- No tests, gates, databases, or browsers were run (docs-only slice in a new worktree). `git diff --check` is clean for this report.
