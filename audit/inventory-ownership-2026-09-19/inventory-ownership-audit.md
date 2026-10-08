# Inventory / Stock Ownership — Architecture Audit

Date: 2026-09-19
Branch: main
HEAD: `0fae8e2ba91e9654e6501f74e193f85b282f6a23`
Scope: Inventory/Stock ownership across domains. Manufacturing decomposition, Shared Items, Shared Parties, and related audits excluded per closed context.

---

## Executive Conclusion

Inventory/stock remains **domain-owned by Manufacturing** with **insufficient evidence for a shared foundation**. Manufacturing owns the canonical `stock_ledger_entries` table (movement types: `IN`,`OUT`,`ADJUST`,`PRODUCTION_IN`,`PRODUCTION_OUT`,`DISPATCH_OUT`), `qr_stock_updates` (integration adapter), and derived quantities (`usable_stock_qty`, `qc_pass_qty`, `dispatched_qty`). No shared inventory foundation exists: `platform/SharedInventory/` and `shared/Inventory/` are absent on `main`; the work branch `work/shared-inventory-foundation/` is unmerged. `apps/Generated/inventory_app/` is a Studio-generated scaffold (`schema_version: studio.generated-app.v1`), not a registered business app with runtime consumers. Zero genuine cross-domain consumers reference inventory services; domains use adapters for read-only data exchange. Inventory is **domain-owned, not shared**.

---

## A. Inventory capability inventory

Files/classes/interfaces/services:

| Path | Role |
|---|---|
| `apps/Manufacturing/modules/Ledger/migrations/001_stock_ledger_entries.sql` | `stock_ledger_entries`: `id`, `product_id`, `movement_type` (`IN`/`OUT`/`ADJUST`/`PRODUCTION_IN`/`PRODUCTION_OUT`/`DISPATCH_OUT`), `qty_delta`, `balance_after`, `reference_no`, `source_module`, `source_id`, `notes`, timestamps |
| `apps/Manufacturing/modules/Products/migrations/004_products_supply_model.sql` | `products`: `supply_mode` (`in_house`/`third_party`), `fulfillment_mode` (`via_ipm`/`direct`), `requires_ipm_qc`, `default_supplier`, `stocked_at_ipm`, `default_procurement_lead_days`, `default_supply_note` |
| `apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql` | `manufacturing_bom`: `finished_item_ref` (opaque INT to Manufacturing `products`), `version`, `revision`, `status`, notes; `manufacturing_bom_line`: `component_item_ref` (opaque INT), `quantity`, `unit`, `sequence` |
| `apps/Manufacturing/modules/DailyOrders/migrations/002_daily_orders_coverage_explainability.sql` | `daily_orders`: `usable_stock_qty`, `qc_pass_qty`, `dispatched_qty`, `usable_supply_qty`, `open_demand_qty`, `forecast_pressure_qty`, `coverage_last_recalculated_at` |
| `apps/Platform/modules/QRCode/migrations/001_qr_stock_updates.sql` | `qr_stock_updates`: `product_id`, `movement_type` (`IN`/`OUT`/`ADJUST`), `qty`, `reference_no`, `notes`, timestamps (integration adapter feeding Manufacturing Ledger) |
| `apps/Generated/inventory_app/stock_entries/Providers/StockEntriesProvider.php` | Studio-generated scaffold: JSON-backed `stock_entries` module (not DB; no routes; not registered business app) |
| `apps/Generated/inventory_app/parts_master/Providers/PartsMasterProvider.php` | Studio-generated scaffold: JSON-backed `parts_master` module (not DB; not registered business app) |
| `apps/Manufacturing/modules/Ledger/Services/LedgerWidgetRegistry.php` | Ledger widget registry (domain-owned) |
| `apps/Manufacturing/modules/Ledger/Controllers/LedgerController.php` | Ledger controller (domain-owned) |
| `apps/Manufacturing/modules/Ledger/LedgerMaintenance.php` | Ledger maintenance (domain-owned) |
| `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` | `hosp_reservations`: `qty` (capacity, not inventory stock) |
| `tests/probes/manufacturing/probe_inventory_contract_reference.php` | Bounded probe verifying no Shared Inventory adoption |

Persistence/schema: Manufacturing-owned tables (`stock_ledger_entries`, `qr_stock_updates`, `products`, `manufacturing_bom`, `manufacturing_bom_line`, `daily_orders`). No `shared_inventory`, `inventory`, or `stock` tables in `shared/` or `platform/`. No shared inventory schema exists.

Routes/APIs: Manufacturing Ledger has routes (`apps/Manufacturing/modules/Ledger/routes.php`); QRCode has routes (`apps/Platform/modules/QRCode/routes.php`); inventory_app generated routes are Studio scaffolding (no DB integration).

Loader/service registration: `composer.json` PSR-4 maps `Apps\` → `apps/` (covers Manufacturing `Plugins\Ledger`), `Platform\` → `platform/` (covers Platform QRCode). No `Shared\` PSR-4 entry.

Tests: probe exists (`tests/probes/manufacturing/probe_inventory_contract_reference.php`); 11/11 PASS (static/unit via file inspection).

Compatibility layers: none — inventory is Manufacturing-domain only.

---

## B. Ownership matrix

| Concept | Identity Owner | Business-State Owner | Mutation Owner | Lifecycle Owner | Policy Owner |
|---|---|---|---|---|---|
| Inventory / Stock | Manufacturing (`Plugins\Ledger`) | Manufacturing (stock levels via `balance_after`, `qty_delta`) | Manufacturing (`postEntry()`, `rebuild()`, QRCode adapter) | Manufacturing (product lifecycle, BOM) | Manufacturing (movement types, reconciliation rules) |
| Product Master | Manufacturing (`Plugins\Products`) | Manufacturing (product attributes, supply mode) | Manufacturing (`PartItemRefService` for BOM refs) | Manufacturing (product lifecycle) | Manufacturing (supply/fulfillment modes) |
| Bill of Materials | Manufacturing (`Plugins\Products`) | Manufacturing (BOM structure, quantities) | Manufacturing (`BomService`) | Manufacturing (BOM version/revision/status) | Manufacturing (BOM policies) |
| Reservations (Hospitality) | Hospitality (`Apps\Hospitality`) | Hospitality (guest capacity via `hosp_reservations.qty`) | Hospitality (`ReservationsService`) | Hospitality (reservation status) | Hospitality (capacity management) |
| Generated inventory_app | Studio (scaffold) | None (not registered) | None (JSON file only) | None (Studio-generated) | None (Studio tooling) |

Manufacturing exclusively owns identity, business state, mutation, lifecycle, and policy for inventory/stock. No other domain owns inventory state.

---

## C. Operational-truth matrix

| Domain | Persists Inventory ID? | Calls Inventory Runtime? | Local Inventory Authoritative? | Sync Required/Implemented? |
|---|---|---|---|---|
| Manufacturing | Yes (`products.id` → `stock_ledger_entries.product_id`) | Yes (`LedgerController`, `LedgerMaintenance`, `postEntry()`) | Yes (Manufacturing owns `stock_ledger_entries`) | N/A (single source) |
| Hospitality | No (only `hosp_reservations.qty` for capacity) | No (reads Manufacturing `stock_ledger_entries` via adapters only for display) | No (capacity only, not stock) | No (read-only projection via adapters) |
| SBAIO | No (no inventory tables) | No | No | No |
| Procurement | No (references `product_id` as local FK only) | No (reads Manufacturing stock via adapters only) | No (local reference only) | No (read-only via adapters) |
| Platform (QRCode) | Yes (as integration adapter) | Yes (`qr_stock_updates` table) | Yes (feeds Manufacturing Ledger) | Yes (feeds Manufacturing Ledger) |

Only Manufacturing persists and mutates authoritative inventory state. Other domains have only read-only projections or local foreign key references.

---

## D. Cross-domain consumer matrix

| Reference | Classification | Evidence |
|---|---|---|
| `apps/Manufacturing/Services/Search/ManufacturingSearchProvider.php` | **domain-owned** | Reads `stock_ledger_entries` via `DB::query()` (Manufacturing-owned) |
| `apps/Platform/modules/QRCode/Views/stock_index.php` / `stock_add.php` | **integration adapter** | Reads/writes `qr_stock_updates` (feeds Manufacturing Ledger) |
| `apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php` | **diagnostic only** | Displays `shell_inventory_shared_title` (locale key only; no runtime service) |
| `apps/Hospitality/Services/GuestsService.php` | **domain-owned** | Manages `hosp_guests`; no inventory service calls |
| `apps/SBAIO/modules/Customers/Services/CustomersService.php` | **domain-owned** | Manages `sbaio_customers`; no inventory service calls |
| `apps/Procurement/` (all) | **domain-owned** | Manages `procurement_*` tables; references `product_id` as local FK only (no inventory service calls) |
| `apps/Generated/inventory_app/` (all) | **Studio scaffold** | JSON file storage; not registered business app; zero runtime imports |
| `shared/Item/Foundation/` (all) | **prototype** | JSON file store; zero runtime imports outside test |
| `apps/Parties/` (all) | **prototype skeleton** | Empty `routes.php`; zero `party_id` FKs in consumer tables; Hospitality/SBAIO/Procurement explicitly prohibit dependency |

**Genuine runtime consumer count**: 0 (zero cross-domain calls to inventory services; only Manufacturing-owned LedgerController/LedgerMaintenance mutate inventory; all other access is read-only via adapters or local FKs).

**Independent domain consumer count**: 0 (no domain has adopted inventory services; Manufacturing owns all mutation/state).

**Demonstrated second consumer**: No — zero consumers demonstrated.

---

## E. Existing Shared Inventory runtime status

Shared Inventory does **not exist** on `main`:
- `platform/SharedInventory/` directory: **absent**
- `shared/Inventory/` directory: **absent**
- `shared_inventory%` or `SharedInventory` tables: **absent** (verified via probe: `SHOW TABLES LIKE 'shared_inventory%'` → null)
- `Shared\Inventory` namespace: **zero imports** in `apps/`, `platform/`, `plugins/`
- `SharedInventoryService` / `InventoryContract`: **zero references** outside `work/shared-inventory-foundation/` (unmerged branch)
- `work/shared-inventory-foundation/`: contains `platform/SharedInventory/` code (InventoryContract, InventoryService, SharedItemAdapter) but **UNMERGED** — not on `main`
- `docs/architecture/odarehub-future-architecture-pressure-test-results.md`: **"INSUFFICIENT EVIDENCE"** verdict for Shared Inventory (Section 3.3, lines 113-119)
- `docs/architecture/current-ownership-inventory.md`: confirms `DOMAIN-OWNED` for Inventory (Section 3.5)
- `docs/architecture/odarehub-future-architecture-planning-brief.md`: states "No universal Inventory authority exists on `main`" (line 22); lists Manufacturing-owned modules as domain-owners (lines 22-23); `work/shared-inventory-foundation/` remains unmerged

---

## F. Shared-foundation pressure test

Per `docs/architecture/odarehub-future-architecture-pressure-test-results.md` (Section 3.3, lines 113-119):

- **Evidence gathered**:
  - `shared/Inventory/` or `platform/SharedInventory/`: **DOES NOT EXIST** on main
  - `apps/Generated/inventory_app/`: Studio-generated scaffold (`schema_version: studio.generated-app.v1`, `generated_by: studio`) — **NOT** a registered business app, **NOT** a shared foundation
  - Manufacturing inventory: `apps/Manufacturing/modules/Ledger/migrations/001_stock_ledger_entries.sql` — `stock_ledger_entries` table with Manufacturing-specific movement types (`PRODUCTION_IN`, `PRODUCTION_OUT`, `DISPATCH_OUT`, `IN`, `OUT`, `ADJUST`); references `product_id INT NOT NULL` (Manufacturing's own products)
  - Work branch `work/shared-inventory-foundation/`: has `platform/SharedInventory/` code (InventoryContract, InventoryService, SharedItemAdapter) but **UNMERGED** — not on main
  - No cross-domain inventory sharing exists

- **Pressure test verdict**: **"INSUFFICIENT EVIDENCE"**. No shared inventory foundation exists on main. Manufacturing owns its stock ledger. Generated inventory app is Studio scaffolding, not a shared entity.

Additional pressure test evidence from `docs/architecture/odarehub-future-architecture-planning-brief.md`:
- DG-Q17 (Safe to defer?): **YES** — All shared entity concepts deferred: Items (prototype); Parties (prototype); Inventory (`DOMAIN-OWNED` — Manufacturing-owned); Commercial (`DOMAIN-OWNED` — Manufacturing-owned)
- DG-Q18 (Rejected approaches?): Universal Inventory Authority rejected because "`DOMAIN-OWNED`; Manufacturing `Plugins\Ledger` Manufacturing-owned; Manufacturing `Plugins\MaterialManagement` Manufacturing-owned; Manufacturing `Plugins\MaterialAccessService` Manufacturing rules; `work/shared-inventory-foundation/` unmerged; `shared/Inventory/` missing"

---

## G. Ownership conflicts/mismatches

| Mismatch | Evidence | Resolution |
|---|---|---|
| **Directory/namespace claim vs. autoload** | `shared/Item/Foundation/` claims `Shared\Item\Foundation` namespace but no `Shared\` PSR-4 autoload entry in `composer.json`; code is `require_once`'d only in its own test probe | Accept prototype status; no shared foundation claim justified |
| **Contract claims independence vs. Manufacturing-local vocabulary** | `docs/shared-items-foundation/canonical-item-contract.md` claims independence but all vocabulary is Manufacturing-local (`item_ref`, BOM references); Manufacturing `item_ref` validated against own `products` table (not Shared Items service) | Accept prototype status; contract generic but unadopted; lifecycle semantics defined but unexercised |
| **Manifest aspirational vs. runtime inert** | `apps/Parties/manifest.json` declares `shared_contract.consumers = [hospitality,sbaio,procurement]` but `apps/Parties/routes.php` is empty/inert; zero `party_id` FKs in any consumer table; Hospitality/SBAIO/Procurement all prohibit Parties dependency | Accept prototype status; manifest aspirational; no runtime consumers |
| **Studio scaffold misrepresented as shared app** | `apps/Generated/inventory_app/` has `manifest.json` with `app_key: "inventory_app"` but is Studio-generated (`generated_by: studio`), not a registered business app; uses JSON file storage, not DB; no domain integrates with it | Accept Studio tooling status; not a business app; generated scaffolding only |

No actual ownership conflicts exist — all mismatches are resolved by recognizing prototype/scaffold status.

---

## H. Blockers

| # | Blocker |
|---|---|
| 1 | **Zero shared inventory foundation** — no `platform/SharedInventory/` or `shared/Inventory/` on `main` |
| 2 | **Zero genuine runtime consumers** — no domain imports or calls inventory services; Manufacturing owns all mutation/state |
| 3 | **Generated inventory_app is Studio scaffold** — `apps/Generated/inventory_app/` uses JSON storage, not DB; not a registered business app; zero runtime imports |
| 4 | **Manufacturing movement types are domain-specific** — `stock_ledger_entries.movement_type` includes `PRODUCTION_IN`, `PRODUCTION_OUT`, `DISPATCH_OUT` (Manufacturing-specific semantics) |
| 5 | **No shared inventory table** — `SHOW TABLES LIKE 'shared_inventory%'` returns null |
| 6 | **Work branch unmerged** — `work/shared-inventory-foundation/` contains shared inventory code but remains unmerged |
| 7 | **Inventory is Manufacturing-domain owned** — each domain owns its own inventory/commercial schemas (Procurement: `procurement_*`; SBAIO: `sbaio_sales`; Hospitality: capacity-only `hosp_reservations.qty`) |
| 8 | **Shell uses adapters, not shared entity** — Shell reads Manufacturing data via domain-owned adapters (CoverageAdapter, DispatchAdapter, etc.); no cross-domain coupling behind UI |

---

## I. Evidence-supported next decision

**Do not promote Inventory to shared foundation yet.** The evidence supports retaining current classification: Manufacturing-owned domain inventory with zero genuine cross-domain consumers. Next decision boundary: obtain explicit approval for (a) which consumer adopts first (Hospitality guest capacity has partial evidence only), (b) inventory placement (FK vs reference in consumer tables), (c) migration/backfill authorization, and (d) orchestrated transition plan. Inventory code itself must not change until that decision is made.

---

*Audit complete. No changes were made to any code, schema, configuration, or working-tree state. The existing Shared Parties audit (`audit/shared-parties-foundation-2026-09-19/`) remains untouched and uncommitted.*