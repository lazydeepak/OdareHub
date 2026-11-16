# Identity and Tenant Migration Plan — Shared → Manufacturing → Injection Molding

**Status:** Design baseline (IPM-002). **No SQL in this document is executed by this task.**
**Date:** 2026-11-17
**Companion:** `schema-proposal.sql` (DDL), `schema-ownership-contract.md` (ownership), `architecture-verification.md` (evidence).

---

## 1. Migration principles

1. **Additive-only.** No column dropped, retyped, renamed, or made `NOT NULL` until an explicit, evidenced audit gate passes. Legacy data is never destroyed to satisfy a new model.
2. **Reversible per phase.** Every phase either creates new objects or adds nullable columns with matching down-migrations (drop added columns/tables only; base data untouched).
3. **No silent assignment.** `organization_id` is backfilled only under the determinism rule (§4.3). Everything else stays `NULL` and is **reported**, never guessed.
4. **Identity is explicit.** Legacy → shared identity resolution goes through `shared_item_identity_map` with `proposed → verified → rejected` states. No casts, no name matching in application code, no "best effort" substitution.
5. **Ledger-governed.** All phases are applied through the existing `core_app_migrations` ledger semantics (`app_key` + `migration_name` unique, checksum, `applied`/`failed` states) and `AppMigrationService`. Failed rows are preserved by `executePlannedStep()`'s contract; no automatic retry.
6. **Application compatibility is a separate axis.** Physical schema phases (below) land before any application reads them. Dual-read/dual-write shims are a later, explicitly authorized milestone (§9).

## 2. Preconditions (Phase 0 — read-only, before any phase executes)

| # | Check | Pass criterion |
|---|---|---|
| P0.1 | Inventory legacy tables and column types from migration files, not `tools/restore/live-schema.sql` (verified stale) | Signed-off table list |
| P0.2 | Detect non-unique `parts_number` cross-(potential)tenant collisions | Report; zero unresolved duplicates before Phase 2 verification |
| P0.3 | Count `products` with NULL/blank `item_ref`; count orphan `item_ref` targets (none can resolve — `shared_items` is empty by construction) | Report counts |
| P0.4 | Confirm exactly one tenant root: `SELECT COUNT(*) FROM org_companies WHERE is_primary = 1` | Exactly 1 for the single-primary-org backfill rule to apply; otherwise skip backfill (§4.3) |
| P0.5 | Confirm no existing `ipm_*`/`shared_items`/`shared_item_identity_map` objects | Zero |
| P0.6 | Verify module install order and ledger state on a **runner-owned disposable source root** with attested MySQL (existing `OH_*` guards) | Attestation record |
| P0.7 | Baseline snapshot counts of all Manufacturing tables (row counts per table) | Stored as migration evidence |

## 3. Phase 1 — Shared Foundation persistence (additive)

**Scope (Shared/Foundation-owned migration, `apps/Platform` or `shared/Item/Foundation/migrations/` placement per Shared owner decision):**

1. `shared_items` (BIGINT UNSIGNED PK) — mirrors `canonical-item-contract.md` §4 exactly, with the corrected `BIGINT` type.
2. `shared_item_identity_map` — verification-gated mapping ledger.
3. `Core` / module ledger registration under a Shared app key; version `1.0.0`.

**Guarantees:** no legacy table is touched; `shared_items` starts empty; install is idempotent (`CREATE TABLE IF NOT EXISTS`); rollback = drop the two new tables (safe while empty or while only test data exists; otherwise export first).

**Verification gate G1:** both tables exist; ledger row present; `probe_item_identity.php` (enhanced to exercise DB mode only when pointed at a disposable runner) passes; zero rows in `shared_item_identity_map`.

## 4. Phase 2 — Identity mapping (explicit, operator-attested)

### 4.1 Candidate generation (read-only SELECT)

Propose mappings only from **high-confidence, unique, deterministic evidence**:

- `products.parts_number` → `shared_items.code_ref` (exact, case-sensitive match candidate)
- `materials.material_code` → candidate as well (same item under a material role) — marked `mapping_status='proposed'` first
- `manufacturing_bom.finished_item_ref` / line `component_item_ref` → `legacy_table='products'`, `legacy_id` = the INT id

### 4.2 Verification (writes only to the map table)

- A proposed row becomes `verified` only with `verified_by_user_id` + `verified_at` set (enforced by the proposed `chk_identity_map_verified` constraint).
- Ambiguous or contradictory candidates (duplicate `code_ref` proposals across sources without explicit selection) are recorded as `rejected`, never silently merged.
- `shared_items` rows are created **only** for verified mappings (insert-or-select by `code_ref`, transactional).

### 4.3 Tenant backfill determinism rule (applies to Phase 3)

Backfill `organization_id` on legacy Manufacturing tables **iff all** of the following hold:

1. `SELECT COUNT(*) FROM org_companies WHERE is_primary = 1` returns exactly **1**;
2. all `organization_id` columns being added are currently absent or NULL;
3. the single primary `org_companies` row is `is_active = 1`.

Then: `UPDATE <table> SET organization_id = :primary_org_id WHERE organization_id IS NULL` in one transaction per table, row counts recorded. Otherwise: **no backfill**; every legacy row stays NULL; application scope handling treats NULL as "unresolved/unowned — not readable by tenant-scoped paths" (fail closed) until individually verified.

## 5. Phase 3 — Manufacturing tenant columns (additive, nullable)

Apply the standard additive pattern to every global Manufacturing-owned table (full normative list in §6 — 27 tables): `ADD COLUMN organization_id INT NULL AFTER id`, `ADD KEY idx_...`, `ADD CONSTRAINT fk_..._organization FOREIGN KEY (organization_id) REFERENCES org_companies(id) ON DELETE RESTRICT ON UPDATE CASCADE`.

- Children additionally get INDEX coverage for `(organization_id, …)` composite auth pushdown.
- `ALTER TABLE ... ADD CONSTRAINT IF NOT EXISTS` is **not** portable; migrations are therefore executed as per-table steps in the `executePlannedStep()` exact-step runner so a mid-phase failure preserves ledger evidence and stops the sequence.
- FK creation on large tables takes metadata locks; schedule in a maintenance window; no concurrent DDL.
- **Never** flip to `NOT NULL` here. NOT NULL is Phase 7, gated.

## 6. Phase 3 table coverage (normative)

| Module family | Tables (27) |
|---|---|
| Products | `products`, `part_molds`, `part_machine_map`, `part_material_map` |
| Machines | `machines` |
| MaterialManagement | `materials`, `material_orders`, `material_reservations`, `material_ledger`, `material_capacity` |
| Bom | `manufacturing_bom`, `manufacturing_bom_line` |
| Production | `production_plans`, `production_entries`, `daily_orders`, `pre_orders` |
| Upstream/demand (assembly demand reuses this) | `mfg_part_demands`, `mfg_procurement_demands`, `mfg_production_plan_candidates`, `mfg_upstream_generation_links`, `mfg_stage_readiness` |
| Assembly / demand | `mfg_assembly_entries` |
| QC | `qc_plans`, `qc_entries` |
| Dispatch | `dispatch_entries`, `dispatch_entry_transitions` |
| Ledger | `stock_ledger_entries` |

`manufacturing_bom_line` additionally gets denormalized `organization_id` for authorization pushdown; equality with parent `manufacturing_bom.organization_id` is enforced by the Bom module service transaction.

## 7. Phase 4 — Manufacturing identity reconciliation (additive)

1. `products.item_id BIGINT UNSIGNED NULL` + FK → `shared_items` + index.
2. `manufacturing_bom.finished_item_id BIGINT UNSIGNED NULL` + FK → `shared_items`.
3. `manufacturing_bom_line.component_item_id BIGINT UNSIGNED NULL` + FK → `shared_items`.
4. `organization_id` on both BOM tables (Phase 3 pattern).
5. Application read order: new columns → verified map → explicit unresolved. Legacy columns (`item_ref`, `finished_item_ref`, `component_item_ref`) remain intact and read-only; they are removed only by a future milestone with retention evidence.

## 8. Phase 5 — IPM module schema (new tables)

Executes only after Phase 3 backfill attestation (IPM tables are NOT NULL by construction; legacy ambiguity must be resolved first):

1. `ipm_mold_revisions`, `ipm_mold_cavities` (revision snapshot)
2. `ipm_mold_maintenance` (shot-count history)
3. `ipm_process_recipes` (versioned process recipes; typed columns; bounded `extra_parameters` JSON)
4. `ipm_production_context` (production_entries extension without ALTER)
5. `ipm_lots`, `ipm_lot_relationships`, `ipm_lot_consumptions`
6. `ipm_serials` — **only** if the owner enables serial tracking for the organization (feature flag; contract says optional)

Migration ordering within the phase: 5.1 → 5.2 (depends on revisions) → 5.3 → 5.4 → 5.5 (needs `ipm_mold_revisions`) → 5.6.
Owner-declared dependencies: `InjectionMolding` depends on `Products`, `Machines`, `Bom`, `ProductionEntries`, `MaterialManagement`, `QCEntries`, `DispatchEntries` (by app_key; version floors to be set by the module-boundary milestone).

## 9. Phase 6+ — Application compatibility (separate, explicitly authorized milestones)

- Dual-read in services: new columns preferred, legacy fallback only when unverified (logged as telemetry, never silent).
- NOT NULL promotion for `organization_id` on legacy tables: requires **two consecutive audits** showing zero unresolved NULLs plus owner sign-off; never automatic.
- NOT NULL promotion for `products.item_id`/BOM item ids: requires zero `proposed` mappings older than one audit cycle plus zero rejected-but-unexplained rows.
- Cosmetic/physical cleanup (drop legacy columns, collapse co-declared BOM DDL, remove `BomService::ensureSchema()`): future milestones only.

## 10. Failure, rollback and recovery

| Phase | Failure mode | Detection | Recovery |
|---|---|---|---|
| 1 | `CREATE TABLE` partial failure (permissions) | ledger row absent / tool error | drop created table(s); re-run; zero data impact |
| 2 | Duplicate `code_ref` proposals | unique key violation on `shared_items` | reject conflicting proposals into map as `rejected`; human decision |
| 3 | ALTER fails mid-family | `executePlannedStep` preserves failed ledger row | stop sequence; fix; resume from failed step only under direct authorization; never auto-retry |
| 3 | FK creation rejected on existing orphans (e.g., `org_companies` row missing for a planned org id) | pre-flight orphan scan (P0.2-like query) | resolve orphans (map to the single primary org under §4.3, else quarantine) then restart step |
| 4 | FK on `item_id` fails (bad BIGINT value) | map verification pre-flight | set offending values NULL; log; re-run |
| 5 | NOT NULL / FK failure | pre-flight single-org attestation | abort phase; legacy untouched |
| any | Corrupted target | checksum mismatch post-verify | restore from verified pre-phase dump into disposable runner; never in-place repair on production without explicit owner approval |

**Rollback doctrine:** rollback is per-phase and forward-fix preferred. Legacy data is never overwritten in place; any rollback restores new objects, not base rows. Pre-phase backups must be restorable on a runner-owned disposable instance before the phase is considered attested.

## 11. What this plan deliberately does NOT do

- Execute any SQL.
- Alter Core (`users`, ACL, Auth, ledger services).
- Rewrite the BOM triple declaration (Products/013 + Bom/001 + `BomService::ensureSchema()`) — see `architecture-verification.md` §7 for the assessment and the proposed consolidation milestone.
- Remove runtime DDL from MaterialManagement/Bom services.
- Promote any column to `NOT NULL`.
- Merge, deduplicate, or renumber any production data.
- Assume tenant identity for any installation other than the §4.3 deterministic case.
