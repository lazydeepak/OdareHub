# Cross-Domain Shared Candidate Scan

Date: 2026-09-19
Branch: main
Scope: discovery audit only — no implementation, no code/schema/database/config changes.
Closed: Manufacturing decomposition, Shared Items, Shared Parties, Inventory/Stock (per planner instruction).
Status: COMPLETE — one artifact created; no other files modified.

---

## Executive Conclusion

The bounded scan found **one genuine cross-domain pressure candidate (Tier A)**: the Manufacturing-owned stock ledger has a genuine Platform→Manufacturing runtime dependency through the Platform QRCode module (`qr_stock_updates` → `stock_ledger_entries` via `source_module='QRCode'`), but this is an **integration adapter feeding a domain-owned ledger**, not a shared inventory foundation.

**Zero candidates meet the A-tier threshold of "two or more independent domain consumers" of a shared service/runtime/schema.** Platform Organization tables, Parties identity, and Procurement/Manufacturing product references are all domain-owned or local schema references with zero genuine cross-domain runtime consumers. The only genuine cross-domain data flow is QRCode→Manufacturing (Platform as adapter), which is a one-way integration dependency, not a shared foundation.

**Tier counts:** A: 1 (QRCode→Manufacturing stock integration), B: 4 (Organization, Parties identity, Product references, Staff identity), C: 7 (Location/Address/Site, Asset, Unit of Measure, Currency/Money, Tax, Pricing, Payments/Accounting), D: 5 (Contact, Notes/Comments, Tags/Categories, Status/Workflow, Document/Attachment/File, Generated scaffolds, Test-only, Documentation-only, Unmerged work).

---

## Top-Level Domain Inventory

| Domain/App | Role | Key Tables/Concepts | Cross-Domain Consumers |
|---|---|---|---|
| `apps/Manufacturing` | Domain app | products, stock_ledger_entries, mfg_procurement_demands, machines, production/plans/entries, dispatch, qc, bom | Owns stock ledger; consumes no shared services |
| `apps/Hospitality` | Domain app | hosp_guests, hosp_reservations, hosp_rooms, hosp_folios, hosp_housekeeping_status | Owns guest/capacity; zero cross-domain consumers |
| `apps/Procurement` | Domain app | procurement_suppliers, procurement_requests, procurement_purchase_orders, procurement_purchase_order_lines, procurement_receipts | Owns supplier/PO; reads Manufacturing `mfg_procurement_demands` via source_ref linkage |
| `apps/SBAIO` | Domain app | sbaio_staff, sbaio_sales, sbaio_expenses, sbaio_tasks, sbaio_attendance, sbaio_leave, sbaio_timecards, sbaio_payroll_runs, sbaio_notices, sbaio_schedule_templates | Owns staff/customer/sales/attendance; zero cross-domain consumers |
| `apps/Parties` | Domain app (inert skeleton) | parties (party_type, display_name, email, phone) | Zero consumers; routes.php is inert |
| `apps/Platform` | Platform capabilities | org_companies, org_branches, org_fiscal_settings, org_hierarchy, org_audit_log, qr_stock_updates, branding_assets, platform_widget_blueprints | Owns Organization tables; QRCode adapter writes qr_stock_updates |
| `apps/Shared` | Shared foundation | `shared/Item/Foundation/` (ItemIdentityContract, ItemIdentityService, probe only) | Zero runtime consumers; prototype only |
| `apps/Shell` | UI frame | composition services, operator layer adapters, logo resolution | Composition only; no domain data ownership |
| `apps/Studio` | Tooling | visual customizer tables, generated scaffolds | Tooling only; generated apps are scaffolds |
| `apps/Generated` | Generated scaffolds | sample_app, lifecycle_app, manufacturing_studio, inventory_app, hardening_app, runtime | Generated artifacts; not registered business apps |

---

## Candidate Concept Classification

Classification categories: genuine shared service, runtime dependency, schema reference, duplicated implementation, same vocabulary/different semantics, compatibility artifact, generated scaffold, test-only, documentation-only, unmerged work.

### Tier A — Demonstrated shared pressure

| Candidate | Evidence | Classification | Consumers | Tier |
|---|---|---|---|---|
| **Stock updates via QRCode → Manufacturing stock ledger** | `apps/Platform/modules/QRCode/migrations/001_qr_stock_updates.sql` creates `qr_stock_updates`; `apps/Platform/modules/QRCode/Controllers/QRCodeController.php` writes to `qr_stock_updates` (lines 147-148); `apps/Manufacturing/modules/Ledger/LedgerMaintenance.php` reads `qr_stock_updates` and reconciles into `stock_ledger_entries` with `source_module='QRCode'` (lines 92-109); `apps/Manufacturing/modules/Ledger/Controllers/LedgerController.php` exposes QRCode stock add route (lines 240-261) | **Runtime dependency** — genuine cross-domain data flow (Platform QRCode → Manufacturing Ledger) | 2 apps (Platform, Manufacturing) | **A** |

Note: This is a genuine cross-domain pressure but it is an **integration adapter**, not a shared foundation. The ledger remains Manufacturing-owned; QRCode is a producer of stock updates, not an inventory owner.

### Tier B — Plausible but unproven

| Candidate | Evidence | Classification | Consumers | Tier |
|---|---|---|---|---|
| **Organization/Company/Branch** | `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` creates `org_companies`, `org_branches`, `org_fiscal_settings`; `apps/Platform/modules/Organization/Services/OrganizationService.php` owns all CRUD; `apps/Shell/Services/LogoResolverService.php` reads `org_companies.logo_path` for branding | **Schema reference** — Platform-owned tables consumed by Shell branding only; zero domain consumers | 1 app (Platform) + Shell branding read | **B** |
| **Product references (Procurement→Manufacturing)** | `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `product_id INT` in `procurement_requests`, `procurement_purchase_order_lines`; `apps/Procurement/Services/ProcurementOverviewService.php` links `procurement_requests` to `mfg_procurement_demands` via `source_app='manufacturing'`, `source_ref_type='mfg_procurement_demands'`, `source_ref_id=CAST(d.id AS CHAR)`; `apps/Manufacturing/migrations/005_mfg_upstream_supply_tables.sql` owns `mfg_procurement_demands` | **Schema reference** — Procurement references Manufacturing-owned demand table via source_ref; no FK, no shared service | 2 apps (Procurement, Manufacturing) | **B** |
| **Staff identity (SBAIO)** | `apps/SBAIO/modules/Staff/install.php` creates `sbaio_staff`; `apps/SBAIO/modules/Attendance/install.php` has `staff_id INT NULL`; `apps/SBAIO/modules/Timecards/install.php` has `staff_id INT NULL`, `related_staff_id INT NULL`; `apps/SBAIO/modules/Tasks/install.php` has `related_staff_id INT NULL`; `apps/SBAIO/modules/Notices/install.php` has `related_staff_id INT NULL` | **Duplicated implementation** — same vocabulary across SBAIO modules but all local to SBAIO; zero cross-domain consumers | 1 app (SBAIO) | **B** |
| **Customer identity (SBAIO)** | `apps/SBAIO/modules/Customers/install.php` creates `sbaio_customers` with `customer_name`, `contact_name`, `email`, `phone`, `status`; `apps/SBAIO/modules/Sales/install.php` stores `customer_name` inline | **Duplicated implementation** — customer identity duplicated as name-only in sales; all SBAIO-local | 1 app (SBAIO) | **B** |

### Tier C — Domain-owned

| Candidate | Evidence | Classification | Consumers | Tier |
|---|---|---|---|---|
| **Location/Address/Site** | Only `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` has `address_line_1`, `city`, `state`, `postal_code`, `country` on org tables; no domain table has location columns | **Schema reference** — Platform-owned only | 1 app (Platform) | **C** |
| **Asset** | `apps/Manufacturing/modules/Machines/migrations/001_machines.sql` creates `machines`; `apps/Manufacturing/modules/PartMachineMap/migrations/001_part_machine_map.sql` creates `part_machine_map`; `apps/Manufacturing/modules/Products/migrations/010_products_active_machine_assignment.sql` adds `active_machine_id` to products | **Domain-owned** — Manufacturing asset concept only | 1 app (Manufacturing) | **C** |
| **Unit of Measure** | No `uom`, `unit`, or `measure` tables/columns found in any domain migration | **Schema reference** — absent | 0 | **C** |
| **Currency/Money** | `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` has `base_currency` on org_companies; `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` has `rate`, `unit_amount`; `apps/SBAIO/modules/Sales/install.php` has `amount`; `apps/SBAIO/modules/Expenses/install.php` has `amount`; `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `unit_price` | **Same vocabulary/different semantics** — currency only on Platform org; money fields domain-local with no shared currency service | 4 apps (Platform, Hospitality, SBAIO, Procurement) but **different semantics**, no shared service | **C** |
| **Tax** | `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` has `tax_no` and `default_tax_mode` on org tables | **Schema reference** — Platform-owned only | 1 app (Platform) | **C** |
| **Pricing** | `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `unit_price`; `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` has `rate` | **Same vocabulary/different semantics** — domain-local pricing fields, no shared pricing service | 2 apps (Procurement, Hospitality) but **different semantics** | **C** |
| **Payments** | No payment tables/columns found in any domain migration | **Schema reference** — absent | 0 | **C** |
| **Accounting references** | `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` has `org_fiscal_settings` (fiscal_year_start, fiscal_year_end, default_tax_mode); `apps/SBAIO/modules/Payroll/install.php` has `sbaio_payroll_runs` | **Schema reference** — Platform fiscal settings + SBAIO payroll-local; no shared accounting service | 2 apps (Platform, SBAIO) but **different semantics** | **C** |
| **Supplier/customer/guest identity** | `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `procurement_suppliers` (supplier_name, contact_name, email, phone); `apps/SBAIO/modules/Customers/install.php` has `sbaio_customers`; `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` has `hosp_guests` | **Duplicated implementation** — three separate identity tables with same fields, all domain-local; no Parties FKs | 3 apps (Procurement, SBAIO, Hospitality) but **different semantics**, no shared service | **C** |
| **Item/product references** | `apps/Manufacturing/modules/Products/migrations/001_products.sql` owns `products`; `apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql` has `finished_item_ref`/`component_item_ref` (opaque INT to Manufacturing products); `apps/Manufacturing/migrations/001_mfg_operational_model.sql` has `product_id` in mfg_part_demands; `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `product_id` in procurement_requests/lines | **Schema reference** — Manufacturing product identity referenced by Procurement via local `product_id` (no FK, no shared service) | 2 apps (Manufacturing, Procurement) but **different semantics** | **C** |

### Tier D — Artifact/noise

| Candidate | Evidence | Classification | Consumers | Tier |
|---|---|---|---|---|
| **Contact** | `apps/Parties/migrations/001_create_parties.sql` has `party_type`, `display_name`, `email`, `phone`; `apps/Procurement/migrations/20260426_0001_procurement_core.sql` has `contact_name` on suppliers; `apps/SBAIO/modules/Customers/install.php` has `contact_name`; `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` has `guest` identity | **Duplicated implementation** — contact fields exist in multiple local tables but no shared contact service | 3 apps (Parties, Procurement, SBAIO, Hospitality) but **different semantics** | **D** |
| **Notes/Comments** | `note`/`notes` columns in `hosp_guests`, `hosp_reservations`, `hosp_housekeeping_status`, `procurement_requests`, `procurement_receipts`, `qr_stock_updates`, `mfg_part_demands` | **Duplicated implementation** — free-text notes, no shared notes service | 5 apps (Hospitality, Procurement, Platform, Manufacturing) but **different semantics** | **D** |
| **Tags/Categories** | `apps/SBAIO/modules/Expenses/install.php` has `category_name`; `apps/SBAIO/modules/Notices/install.php` has `notice_type`; `apps/SBAIO/modules/Tasks/install.php` has `task_type`, `task_bucket` | **Duplicated implementation** — domain-local categorization | 1 app (SBAIO) | **D** |
| **Status/Workflow** | Status enums everywhere: `reservation_status`, `folio_status`, `charge_status`, `request_status`, `po_status`, `line_status`, `receipt_status`, `supplier_status`, `sale_status`, `expense_status`, `staff` status, `hk_status`, `mfg_*_status` | **Duplicated implementation** — same vocabulary with different semantics per domain; no shared workflow service | 5 apps (Hospitality, Procurement, SBAIO, Manufacturing, Platform) but **different semantics** | **D** |
| **Document/Attachment/File** | `apps/Platform/modules/Organization/migrations/002_create_branding_assets.sql` has `branding_assets`; `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql` has `logo_path`, `website` | **Schema reference** — Platform branding only; no shared document service | 1 app (Platform) | **D** |
| **Generated scaffolds** | `apps/Generated/inventory_app/`, `apps/Generated/sample_app/`, `apps/Generated/lifecycle_app/`, `apps/Generated/manufacturing_studio/`, `apps/Generated/hardening_app/`, `apps/Generated/runtime/` — all `schema_version: studio.generated-app.v1`, `generated_by: studio` | **Generated scaffold** — not registered business apps, no runtime consumers | 0 | **D** |
| **Test-only** | `tests/probes/manufacturing/probe_inventory_contract_reference.php` (11/11 PASS) — verifies no Shared Inventory adoption; `apps/Procurement/Tests/probe_behavioral_*` — procurement behavioral tests | **Test-only** — verification artifacts, not production | 0 | **D** |
| **Documentation-only** | `docs/architecture/odarehub-future-architecture-pressure-test-results.md`, `docs/architecture/current-ownership-inventory.md`, `docs/shared-items-foundation/`, `docs/shared-parties-foundation/` | **Documentation-only** — audit/concept docs, no runtime | 0 | **D** |
| **Unmerged work** | `work/shared-inventory-foundation/` (unmerged branch with `platform/SharedInventory/`), `work/shared-parties-foundation/` (unmerged branch) | **Unmerged work** — not on main, not runtime | 0 | **D** |

---

## Genuine Consumer Counts

| Concept | Genuine Cross-Domain Consumers | Independent Domain Consumers | Tier |
|---|---|---|---|
| Stock updates (QRCode→Manufacturing) | 2 (Platform, Manufacturing) | 1 independent (Manufacturing) + 1 adapter (Platform) | A |
| Product references (Procurement→Manufacturing) | 2 (Procurement, Manufacturing) | 1 independent (Manufacturing) + 1 reference (Procurement) | B |
| Organization tables | 1 (Platform) + Shell branding read | 0 | B |
| Staff identity | 1 (SBAIO) | 0 | B |
| Customer identity | 1 (SBAIO) | 0 | B |
| All other candidates | 0 | 0 | C/D |

**Genuine shared service count: 0** — no shared service is consumed by two or more independent domains.
**Genuine runtime dependency count: 1** — QRCode→Manufacturing stock updates (integration adapter).
**Genuine schema reference count: 2** — Procurement→Manufacturing product/demand references; Platform Organization→Shell branding.
**Duplicated implementation count: 6** — Staff, Customer, Supplier/Guest/Contact identity, Notes, Status/Workflow, Tags/Categories.
**Same vocabulary/different semantics count: 6** — Currency/Money, Tax, Pricing, Payments, Accounting references, Location/Address/Site.
**Compatibility artifact count: 0**.
**Generated scaffold count: 6** (sample_app, lifecycle_app, manufacturing_studio, inventory_app, hardening_app, runtime).
**Test-only count: 2** (inventory contract probe, procurement behavioral probes).
**Documentation-only count: 3** (pressure test results, current ownership, shared foundation docs).
**Unmerged work count: 2** (shared inventory foundation, shared parties foundation).

---

## Cross-Domain Consumer Matrix

| Domain | Reads Shared/Platform Data | Writes Shared/Platform Data | Owns Cross-Domain Data |
|---|---|---|---|
| Manufacturing | Reads its own `mfg_*` tables; no shared reads | Owns `stock_ledger_entries`; consumes QRCode updates | Yes — stock ledger, products, demands |
| Procurement | Reads `mfg_procurement_demands` via `source_ref` linkage | Writes `procurement_*` tables | No — references Manufacturing demands |
| SBAIO | No shared reads | Owns `sbaio_*` tables | No |
| Hospitality | No shared reads | Owns `hosp_*` tables | No |
| Platform | Owns `org_*` tables; owns `qr_stock_updates` | Owns `org_*` tables; QRCode writes `qr_stock_updates` | Yes — organization, branding, QR stock updates |
| Parties | No reads | Owns `parties` table (inert) | No — zero consumers |
| Shell | Composition only | No writes | No |
| Studio | Tooling only | No writes | No |

---

## Existing Shared Inventory Runtime Status

| Check | Result |
|---|---|
| `platform/SharedInventory/` exists on main | **No** |
| `shared/Inventory/` exists on main | **No** |
| `SharedInventoryService` references | **Zero** (only in unmerged `work/shared-inventory-foundation/`) |
| `shared_inventory%` tables | **Zero** |
| `Shared\` PSR-4 autoload entry | **Zero** |
| `apps/Generated/inventory_app/` | Studio-generated scaffold, not a shared foundation |
| `work/shared-inventory-foundation/` | Unmerged branch, not on main |

---

## Architecture Contradictions

| # | Contradiction | Evidence | Resolution |
|---|---|---|---|
| 1 | **Platform Organization tables have no domain consumers** | `org_companies`, `org_branches`, `org_fiscal_settings` are Platform-owned; only Shell's `LogoResolverService` reads `org_companies.logo_path` | Organization is Platform-owned configuration, not a shared domain entity |
| 2 | **Parties identity table has zero consumers** | `apps/Parties/migrations/001_create_parties.sql` creates `parties`; `apps/Parties/routes.php` is inert; no `party_id`/`party_ref` FKs in any consumer table | Parties is a prototype skeleton, not a shared foundation |
| 3 | **Procurement product references are local, not shared** | `procurement_requests.product_id` and `procurement_purchase_order_lines.product_id` are local INT columns; no FK to Manufacturing products; linkage via `source_app/source_ref_type/source_ref_id` string fields | Procurement is a consumer of Manufacturing demands via source_ref, not a shared product consumer |
| 4 | **SBAIO staff identity is duplicated** | `sbaio_staff` exists; `sbaio_tasks.related_staff_id`, `sbaio_attendance.staff_id`, `sbaio_timecards.staff_id`, `sbaio_notices.related_staff_id` all reference local staff | Staff is SBAIO-owned; no cross-domain pressure |
| 5 | **Customer/supplier/guest identity is duplicated** | `procurement_suppliers`, `sbaio_customers`, `hosp_guests` each have name/email/phone fields independently | Identity is domain-local; no shared identity foundation |
| 6 | **Generated inventory app is not a shared foundation** | `apps/Generated/inventory_app/` has `schema_version: studio.generated-app.v1`, `generated_by: studio`; no DB tables; no runtime consumers | Generated scaffolds are Studio artifacts, not shared apps |
| 7 | **Manufacturing stock ledger is domain-owned but has a Platform adapter** | `stock_ledger_entries` is Manufacturing-owned; `qr_stock_updates` is Platform-owned; QRCode feeds Manufacturing via `source_module='QRCode'` | This is the one genuine cross-domain pressure, but it is an integration adapter, not a shared foundation |

---

## Blockers

| # | Blocker |
|---|---|
| 1 | **No shared service consumed by two or more independent domains** — only Platform QRCode→Manufacturing stock updates exists, and it is an adapter, not a shared foundation |
| 2 | **Parties identity foundation is inert** — no consumers, no FKs, no routes |
| 3 | **Platform Organization tables have no domain consumers** — only Shell branding reads |
| 4 | **Generated scaffolds are not real apps** — no DB tables, no routes, no consumers |
| 5 | **Unmerged work branches are not on main** — `work/shared-inventory-foundation/` and `work/shared-parties-foundation/` are not part of the runtime |
| 6 | **Cross-domain product references are local** — Procurement uses local `product_id` with source_ref linkage, not shared identity |

---

## Evidence-Supported Next Decision

**Do not create a shared foundation for any candidate concept in this scan.** The only genuine cross-domain pressure is the QRCode→Manufacturing stock integration, which is already an integration adapter feeding a domain-owned ledger and does not justify a shared inventory foundation. The Parties identity, Platform Organization, and Procurement→Manufacturing product references all remain domain-owned or local schema references with zero genuine shared consumers.

Next decision boundary: if a shared foundation is desired, first obtain evidence of **two or more independent domain consumers** of a shared service/runtime/schema — currently only one genuine cross-domain runtime dependency exists (Platform QRCode→Manufacturing stock updates), and it is an adapter, not a foundation.

---

*Audit complete. No changes were made to any code, schema, configuration, or working-tree state. Existing audit artifacts and working-tree modifications remain untouched.*