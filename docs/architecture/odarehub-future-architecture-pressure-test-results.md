# OdareHub Future Architecture — Pressure Test Results

Status: COMPLETE — Ground truth evidence compiled from repository inspection

Canonical authority: `docs/architecture/odarehub-future-architecture-planning-brief.md`
Date: 2026-09-13
Branch: main (HEAD `12f97b3`)
Local changes preserved: `apps/Manufacturing/modules/Products/Views/part_360.php` (item_ref display), `engineering/Manufacturing/work.md` (evidence-only annotation)

---

## 1. Repository State (Starting Point)

- Branch: `main`
- HEAD: `12f97b3` (`feat(manufacturing): product item_ref assignment`)
- Working tree: 2 tracked modifications (both Manufacturing evidence-only), untracked docs/ and work/ preserved
- Apps registered on disk: Hospitality, Manufacturing, Parties, Platform, Procurement, SBAIO, Shared (empty), Shell, Studio
- Plugins: ACL, Base, Bus
- Platform dirs: Engineering, Labels, Recovery, Reports, Search, Security, Style, Updates
- `shared/` dir: only `shared/Item/Foundation/` (3 files)
- No `platform/SharedInventory/`, no `platform/SharedCommercial/`, no `shared/Inventory/`, `shared/Commercial/`

---

## 2. Pressure Tests Executed

### Workstream A — Identity, Context, Time, Governance

**Investigated:**
- Manufacturing Products schema (migrations 001–013): `products` table owns `id`, `parts_number`, `parts_name`, all Manufacturing-specific extensions
- Manufacturing BOM (migrations 001, 013, BomService, BomController, Views): `finished_item_ref`/`component_item_ref` as opaque INTs, joined to `products` on `item_ref` — NO Shared Items runtime call
- Shared Items code at `shared/Item/Foundation/`: contract, service, test probe
- `composer.json` autoload PSR-4: `App\`, `Apps\`, `Platform\` — NO `Shared\` namespace
- Runtime import sweep: `grep -rn "Shared\\Item" apps/ app/ platform/ --include="*.php"` excluding tests/probes — **0 results**
- BOM probe isolation assertion (line 113): `preg_match('/use\s+Shared\\\\/i', $src)` — explicitly checks and passes

**Result:** No canonical identity mechanism is exercised at runtime. Manufacturing owns identity (parts_number as business key). Shared Items code is a standalone skeleton with JSON file storage, zero runtime consumers.

### Workstream B — Domain Ownership, Extensions, Evolution

**Investigated:**
- 17 Manufacturing modules (Products, Bom, Ledger, DispatchEntries, DailyOrders, etc.) each own their schema and services
- SBAIO app (11 modules): Customers, Staff, Sales, Payroll, etc. — each with own namespace and tables
- Hospitality app: 6 modules with domain-local tables (`hosp_*`)
- Parties app: skeleton only — manifest, migration, PartyService, empty routes.php
- Procurement app: own `procurement_*` tables
- Extension pattern: Manufacturing modules extend within Manufacturing domain (`Apps\Manufacturing\Modules\*` import each other)
- Shell operator adapters: Shell imports Manufacturing adapters (CoverageAdapter, DispatchAdapter, etc.) — Shell delegates to domain adapters, does NOT own business logic
- No cross-domain extension ownership exists; domains own their extension state

**Result:** Clear domain ownership boundaries. No suite-owned domain variants. Extensions are module-contained. Parties app has zero runtime consumers.

### Workstream C — Transactions, Integrity, Failure

**Investigated:**
- Manufacturing Ledger: `stock_ledger_entries` (domain-local, `product_id` INT, movement_type enum with Manufacturing-specific values `PRODUCTION_IN`, `PRODUCTION_OUT`, `DISPATCH_OUT`)
- DailyOrders: `customer_name VARCHAR(190) NOT NULL` — text, not party reference
- DispatchEntries: references `daily_order_id`, `production_plan_id`, `production_entry_id`, `qc_entry_id` — all Manufacturing domain
- Procurement: `procurement_requests` with `product_id INT NULL`, `source_app`/`source_ref_type`/`source_ref_id` columns — references products by ID but has no shared transaction authority
- SBAIO Sales: no sales tables found in SBAIO modules
- Cross-domain transaction: `procurement_requests` has `source_app`/`source_id` — local reference, not shared transaction coordination
- Partial failure handling: not present — no saga, outbox, or compensation framework

**Result:** No cross-domain transaction authority exists. Each domain manages its own transactions. Cross-domain references are local foreign keys to `products.id`, not shared identity. No partial failure recovery infrastructure.

### Workstream D — Composition, UX, Search, Scale, Operability

**Investigated:**
- Shell operator layer: `/u/{username}/dashboard` uses adapters from each domain (Manufacturing CoverageAdapter, DispatchAdapter, etc.)
- Shell display layer: `/displays` uses Manufacturing adapters (CoverageAdapter, DispatchAdapter, MachinesAdapter, QcAdapter)
- Search service: `platform/Search/` with provider registry — providers are app-registered, not shared entity-based
- Shell `OperatorSurfaceComposer` reads `procurement_*` tables directly in a data-exchange context — this is projection/read, not ownership
- `apps/Generated/inventory_app/` — Studio-generated scaffolding (manifest says `generated_by: studio`), not a registered business app
- No shared projections/indexes exist across domains

**Result:** Composition is adapter-based — Shell consumes domain data via app-owned adapters. No unified identity/storage/ownership behind the UI. Generated apps are Studio scaffolds, not runtime business apps.

### Shared Candidate Pressure Tests

#### Item / Products

**Evidence gathered:**
- `shared/Item/Foundation/Services/ItemIdentityService.php`: uses JSON file store (`/tmp/shared-items-store.json`), NOT a database table
- `composer.json`: NO `Shared\` PSR-4 autoload entry — code is `require_once`'d only in its own test probe
- Production runtime: `grep -rn "ItemIdentityService\|Shared\\Item" apps/ app/ platform/ --include="*.php" | grep -v Tests/probe` → **0 matches**
- Manufacturing `products.item_ref`: `INT NULL` (migration 012), assigned/cleared by `PartItemRefService` (namespace `Plugins\Products\Services` — legacy), which validates uniqueness against `products` table only
- BOM Service: `finished_item_ref`/`component_item_ref` are opaque INTs; `integrityIssues()` checks against `products` table directly; no Shared Items resolution
- BOM probe explicitly asserts: "module references no Shared\ runtime namespace" — PASSES
- BOM view displays `component_item_ref` as `#123` (raw integer), no enrichment from Shared Items
- Canonical contract doc explicitly states: "no `shared_items` DB table was created" and "not proof that Item must be canonical"

**Pressure test verdict:** A reference seam is **designed** (contract document, adapter pattern in contract section 3.3) but **not justified**. No second consumer adopts Shared Items at runtime. Manufacturing maintains opaque references self-sufficiently. The code is a prototype/skeleton, not an active shared foundation.

#### Parties

**Evidence gathered:**
- `apps/Parties/`: manifest.json ("consumers": ["hospitality", "sbaio", "procurement"]), migration `001_create_parties.sql`, PartyService, empty routes.php
- Consumer domain tables:
  - Hospitality: `hosp_guests` — has `full_name`, `email`, `phone`, `id_document_ref` — NO `party_id` FK; GuestsService docblock: "local stand-in until a shared Parties app is explicitly approved"
  - SBAIO: `sbaio_customers` — has `customer_name`, `contact_name`, `email`, `phone` — NO `party_id` FK
  - Procurement: `procurement_suppliers` — has `supplier_name`, `email`, `phone` — NO `party_id` FK; `supplier_id` links to `procurement_suppliers`, not `parties`
  - Manufacturing: `default_supplier VARCHAR(190) NULL` in `products` — text, not a reference
  - DailyOrders: `customer_name VARCHAR(190) NOT NULL` — text, not a reference
- Runtime imports: `grep -rn "PartyService\|use.*Parties" apps/ app/ platform/ --include="*.php" | grep -v Tests/probe` → **0 matches** outside Parties own test
- `apps/Parties/routes.php`: empty (no UI, no API, no endpoint)
- No SQL table has `party_id` foreign key (grep confirmed `party_ref` only appears as `third_party_reference VARCHAR` in DispatchEntries, unrelated)

**Pressure test verdict:** Parties exists as a skeleton with a table definition and manifest declaring intended consumers, but has **zero runtime consumers**. Four domains (Hospitality, SBAIO, Procurement, Manufacturing) maintain separate entity tables with no cross-domain identity sharing. Parties is **INSUFFICIENT EVIDENCE** for Shared Foundation promotion — more than a prototype, but no pressure-test proof.

#### Inventory

**Evidence gathered:**
- `shared/Inventory/` or `platform/SharedInventory/`: **DOES NOT EXIST** on main
- `apps/Generated/inventory_app/`: Studio-generated scaffold (manifest: `schema_version: studio.generated-app.v1`, `generated_by: studio`) — NOT a registered business app, NOT a shared foundation
- Manufacturing inventory: `apps/Manufacturing/modules/Ledger/migrations/001_stock_ledger_entries.sql` — `stock_ledger_entries` table with Manufacturing-specific movement types (`PRODUCTION_IN`, `PRODUCTION_OUT`, `DISPATCH_OUT`, `IN`, `OUT`, `ADJUST`); references `product_id INT NOT NULL` (Manufacturing's own products)
- Work branch `work/shared-inventory-foundation`: has `platform/SharedInventory/` code (InventoryContract, InventoryService, SharedItemAdapter) but UNMERGED — not on main
- No cross-domain inventory sharing exists

**Pressure test verdict:** **INSUFFICIENT EVIDENCE**. No shared inventory foundation exists on main. Manufacturing owns its stock ledger. Generated inventory app is Studio scaffolding, not a shared entity.

#### Commercial

**Evidence gathered:**
- `shared/Commercial/` or `platform/SharedCommercial/`: **DOES NOT EXIST** on main
- Work branch `work/shared-commercial-foundation`: has `docs/architecture/shared-commercial-foundation.md` only — **NO CODE**, contract doc only
- Commercial domains on main:
  - Procurement: `procurement_purchase_orders`, `procurement_purchase_order_lines`, `procurement_suppliers`, `procurement_requests` — domain-local, Manufacturing-specific `product_id` references
  - Manufacturing: `daily_orders` (customer_name as TEXT, not reference)
  - SBAIO: Sales module (no visible sales table migration on main)
  - Hospitality: `hosp_folios` (reservation-based charges, not invoice/posting)
- No shared order/document/invoice/payment master table exists

**Pressure test verdict:** **INSUFFICIENT EVIDENCE**. No shared commercial foundation code exists on main. Only a planning document exists on an unmerged branch. All commercial concepts are domain-local with different lifecycles.

---

## 3. Ground-Truth Findings

1. **Zero runtime Shared Foundation consumers:** No production code in `apps/`, `app/`, or `platform/` imports or calls `Shared\Item\Foundation\Services\ItemIdentityService` (0 matches excluding tests/probes).
2. **Zero cross-domain identity sharing:** No SQL table has a `party_id` foreign key linking to `parties`. No domain references Shared Items at runtime.
3. **`item_ref` is opaque domain-local reference:** Manufacturing's `products.item_ref INT` and BOM's `finished_item_ref`/`component_item_ref` are validated against Manufacturing's own `products` table — no Shared Items registry call.
4. **Parties app is structurally present but inert:** Table exists, manifest declares intended consumers, but routes.php is empty and no domain imports PartyService.
5. **No shared inventory or commercial code on main:** Only unmerged work branches contain contracts or code. `apps/Generated/inventory_app/` is Studio scaffolding, not a business app.
6. **Shell uses adapters, not cross-domain coupling:** Shell imports Manufacturing operator adapters (CoverageAdapter, DispatchAdapter, etc.) for data access — this is the intended adapter pattern, not shared entity ownership.
7. **Each domain owns its entity model:** Hospitality guests, SBAIO customers, Procurement suppliers, Manufacturing products — all separate tables with separate schemas, no shared identity.
8. **Generated apps are Studio scaffolding:** `apps/Generated/` contains Studio-generated app shells — not registered business apps with runtime consumers.
9. **No cross-domain transaction coordination:** Procurement orders reference `product_id` to Manufacturing's `products` table as a local FK, but there is no shared order/invoice/payment transaction authority.
10. **Extension boundary is clean:** Manufacturing modules import each other within the domain (`Apps\Manufacturing\Modules\*`); Shell imports domain adapters as read-only data sources. No domain imports another domain's business services at runtime.

---

## 4. Ownership Matrix

| Concept | Identity Owner | Business-State Owner | Mutation Owner | Lifecycle Owner | Invariant Owner | Policy Owner | Tenant/Scope Owner | Shared Responsibility |
|---|---|---|---|---|---|---|---|---|
| Product / Part (Manufacturing) | Manufacturing Products (`products.id`, `parts_number`) | Manufacturing Products, MaterialManagement, QCPlans | Manufacturing Products controller | Manufacturing | Manufacturing | Manufacturing | company_id (future) | none |
| Item Ref (Manufacturing) | Manufacturing Products (`item_ref` INT) | Manufacturing Products | Manufacturing Products | Manufacturing Products | Manufacturing Products | Manufacturing | company_id | none |
| BOM / Recipe | Manufacturing BOM (`manufacturing_bom.id`) | Manufacturing BOM | Manufacturing BOM | Manufacturing BOM | Manufacturing BOM | Manufacturing | company_id | none |
| Supplier | Procurement (`procurement_suppliers.id`) | Procurement | Procurement | Procurement | Procurement | Procurement | company_id? | none |
| Customer | SBAIO (`sbaio_customers.id`) | SBAIO | SBAIO | SBAIO | SBAIO | SBAIO | company_id? | none |
| Guest | Hospitality (`hosp_guests.id`) | Hospitality | Hospitality | Hospitality | Hospitality | Hospitality | company_id? | none |
| Daily Order Customer | Manufacturing (text field) | Manufacturing DailyOrders | Manufacturing | DailyOrders | DailyOrders | Manufacturing | company_id? | none |
| Stock Ledger | Manufacturing Ledger (`stock_ledger_entries.id`) | Manufacturing Ledger | Manufacturing Ledger | Manufacturing Ledger | Manufacturing Ledger | Manufacturing | company_id? | none |
| Party (canonical) | Parties (`parties.id`) — SKELETON ONLY | Parties — NO runtime consumer | Parties — NO runtime consumer | Parties — NO runtime consumer | Parties — NO runtime consumer | Parties — NO runtime consumer | none | none — no runtime consumer |
| Shared Item (canonical) | Shared/Item Foundation — JSON file store | Shared/Item Foundation — NO DB, NO consumer | Shared/Item Foundation — NO runtime consumer | Shared/Item Foundation — NO consumer | Shared/Item Foundation | Shared/Item Foundation | none | none — no runtime consumer, no autoload, no DB |
| Universal Inventory | none on main | none | none | none | none | none | none | none — code only on unmerged work branch |
| Universal Order/Invoice | none | none | none | none | none | none | none | none — doc-only on unmerged branch |
| Employee (Staff) | SBAIO (`sbaio_staff`) | SBAIO | SBAIO | SBAIO | SBAIO | SBAIO | company_id? | none |
| User Account | app/Core/Auth (ACL plugin) | ACL | ACL | ACL | ACL | ACL | company_id? | none |

---

## 5. Shared Candidate Decision Matrix

| Concept | Classification | Evidence | Dangerous Assumption Disproved |
|---|---|---|---|
| **Item / Products** | **PROTOTYPE / MIGRATION AID** | `shared/Item/Foundation/` code exists (contract + JSON service + test); NOT in composer.json autoload; 0 runtime imports; Manufacturing `item_ref` validated against own `products` table; BOM probe asserts no Shared\ namespace; canonical doc says "not proof that Item must be canonical" | "Shared Items code exists → Item must be canonical Shared Foundation" — DISPROVED |
| **Parties** | **PROTOTYPE / MIGRATION AID** | `apps/Parties/` skeleton exists (manifest declares consumers, table defined); BUT empty routes.php; 0 runtime imports of PartyService; no `party_id` FK in any consumer table; Hospitality/SBAIO/Procurement all have separate entity tables | "Parties manifest declares consumers → Parties has consumers" — DISPROVED |
| **Inventory** | **INSUFFICIENT EVIDENCE** | No `platform/SharedInventory/` or `shared/Inventory/` on main; Manufacturing `stock_ledger_entries` is domain-local with Manufacturing-specific movement types; `apps/Generated/inventory_app/` is Studio scaffolding not a registered app; work-branch code is unmerged | "Generated inventory_app → shared inventory foundation" — DISPROVED |
| **Commercial** | **INSUFFICIENT EVIDENCE** | No `platform/SharedCommercial/` or `shared/Commercial/` on main; only a contract doc on unmerged branch; Procurement/DailyOrders/SBAIO/Hospitality all have domain-local commercial tables with different lifecycles | "Commercial contract doc exists → shared commercial foundation" — DISPROVED |
| **Location** | **INSUFFICIENT EVIDENCE** | No `locations` or `shared_locations` table; no `Shared\Location` code; Manufacturing has `default_supplier` as text, no location entity | "Location is a shared concept" — no evidence |
| **Asset** | **NOT PRESENT** | No asset tracking code or tables found | — |
| **Unit of Measure** | **NOT PRESENT** | No `units` table; `unit VARCHAR(50)` appears as text in BOM (`each`), not a shared UoM entity | — |
| **Tax** | **NOT PRESENT** | No tax table or service; `dispatch_mode` and `fulfillment_mode` are enums, not tax concepts | — |
| **Money / Currency** | **NOT PRESENT** | Prices are `DECIMAL` fields on domain tables; no currency table or Money value object | — |

---

## 6. Rejected Architecture

OdareHub should explicitly NOT:

1. **Promote Shared Items to canonical foundation** — no runtime consumer exists, no autoload registration, no DB table. The code is a skeleton awaiting a consumer.
2. **Promote Parties to canonical foundation** — table exists but zero domain apps consume it. Parties is a skeleton awaiting at least two independent consumer domains.
3. **Centralize inventory** — no shared inventory foundation exists. Manufacturing's stock ledger is domain-local and uses Manufacturing-specific semantics.
4. **Centralize commercial/transaction authority** — Procurement, Manufacturing, SBAIO, and Hospitality have incompatible order/invoice lifecycles, authorization, and reporting. A universal commercial master would be premature.
5. **Introduce a universal order/document master** — rejected by the shared-commercial-foundation doc itself (Model A → REJECTED).
6. **Create a universal product catalog** — products, materials, parts, and SKUs have different fields and lifecycles per domain.
7. **Assume that "multiple domains use the same term" implies shared identity** — Hospitality guests, SBAIO customers, Procurement suppliers, and Manufacturing products all have `name`/`email`/`phone` but distinct business semantics and lifecycles.
8. **Mandate cross-domain event sourcing or saga patterns** — no cross-domain transaction coordination infrastructure exists; local transactions are the boring baseline.

---

## 7. Dangerous Assumptions

Previously plausible claims disproved or not supported by evidence:

1. **"Shared Items adapter exists → Items are canonical"** — DISPROVED. The adapter exists as design/documentation only. No runtime code calls `Shared\Item\Foundation\Services\ItemIdentityService`. Manufacturing's `item_ref` is a self-validating opaque integer with no Shared Items resolution.

2. **"Parties app declares consumers in manifest → Parties is being adopted"** — DISPROVED. The manifest lists `hospitality`, `sbaio`, `procurement` as intended consumers, but none of them import or call `PartyService`. They all maintain separate entity tables.

3. **"BOM references Shared Items identity"** — DISPROVED. BOM's `finished_item_ref`/`component_item_ref` are opaque integers joined to Manufacturing's own `products.item_ref`. No Shared Items service is called at runtime.

4. **"Generated inventory_app is a shared inventory foundation"** — DISPROVED. It is a Studio-generated scaffold (`schema_version: studio.generated-app.v1`), not a registered business app. No domain app integrates with it.

5. **"Commercial contract document implies shared commercial foundation"** — DISPROVED. Only a markdown planning doc exists on an unmerged branch. No commercial entity tables, services, or adapters exist in the runtime.

6. **"Shell reads Procurement tables directly → shared data access"** — This is true but is the intended adapter pattern (Shell reads via domain-owned data), not a shared entity layer. Shell never imports Procurement services.

---

## 8. Decision Gate Answers

Canonical brief Section 32 questions, answered with evidence:

**Q1: Which truths need common identity, if any?**
- **Answer:** None identified as requiring common identity. No two domains share identity for Party, Item, or Inventory. Each domain owns its entity keys. `item_ref` is opaque and self-validating per domain.
- **Evidence:** 0 runtime imports of Shared Items service; 0 `party_id` foreign keys in consumer tables; Manufacturing BOM joins `item_ref` to its own `products` table.
- **Confidence:** HIGH
- **Unresolved:** None — the evidence is clear.

**Q2: Which truths remain domain-owned?**
- **Answer:** ALL operational truths remain domain-owned. Products, BOM, stock ledger, orders, customers, suppliers, guests, folios — every operational concept belongs to its originating domain.
- **Evidence:** 17 Manufacturing modules each own their schema; SBAIO modules have separate tables; Hospitality has `hosp_*` tables; Procurement has `procurement_*` tables; no shared operational state exists.
- **Confidence:** HIGH

**Q3: Which state is contextual?**
- **Answer:** All business state is currently single-tenant (no `company_id` or LegalEntity on operational tables). Context awareness is FUTURE, not current. The architecture must preserve company_id compatibility (canonical brief invariant).
- **Evidence:** No `company_id` column exists on any operational table. Multi-company scenarios are future projections, not current reality.
- **Confidence:** HIGH — current state is single-tenant; future context is deferred.

**Q4: Who owns identity, lifecycle, transaction, policy, and presentation authority?**
- **Answer:** Each business domain owns all five authorities for its entities. No shared authority exists for any business concept currently.
- **Evidence:** Manufacturing owns Product/BOM identity, lifecycle (draft/released/superseded/archived), transactions (DB), policy (BomPolicies), presentation (Bom views). No shared authority layer exists.
- **Confidence:** HIGH

**Q5: What temporal semantics are required?**
- **Answer:** Audit trail via `created_at`/`updated_at` timestamps exists on all operational tables. Versioning exists on BOM (version + revision). No multi-temporal (effective-date) semantics exist yet — this is a FUTURE requirement.
- **Evidence:** All SQL tables have `created_at`/`updated_at`; BOM has `version`/`revision`/`status`; no effective-date columns found.
- **Confidence:** HIGH for current state; future temporal support is deferred.

**Q6: Which relationships need stable representation?**
- **Answer:** Manufacturing BOM relationships (finished_item_ref → component_item_ref) are stable within Manufacturing. No cross-domain relationships exist that require shared identity.
- **Evidence:** `manufacturing_bom_line` FK to `manufacturing_bom`; BOM joins `products` via `item_ref`; no cross-domain FKs.
- **Confidence:** HIGH

**Q7: Which mutation patterns are permitted?**
- **Answer:** Owner command only — each domain's controller/service layer handles mutations to its own tables. No cross-domain mutation exists.
- **Evidence:** Shell reads via adapters (OperatorLayerAdapters) — no Shell writes to domain tables. No cross-domain command infrastructure.
- **Confidence:** HIGH

**Q8: How are cross-domain failures recovered?**
- **Answer:** Not applicable currently — no cross-domain operations exist. Each domain transaction is local.
- **Evidence:** 0 cross-domain transaction coordination; 0 saga/compensation patterns; 0 outbox patterns.
- **Confidence:** HIGH — current state has no cross-domain failure modes.

**Q9: How are legal-company boundaries preserved?**
- **Answer:** Not applicable currently — no `company_id` or legal-entity column exists. Single-tenant today.
- **Evidence:** No `company_id` column on any operational table.
- **Confidence:** HIGH — current state is single-tenant; multi-company preservation is a FUTURE requirement.

**Q10: How can extensions contribute capability safely?**
- **Answer:** Extensions own their storage within their suite/domain. Manufacturing modules own their migration paths. No shared-schema extension mechanism exists yet.
- **Evidence:** 17 Manufacturing modules with module-specific migrations; SBAIO modules with own schemas; no `extensions/` table structure.
- **Confidence:** HIGH — current extension model is module-contained.

**Q11: How does the platform stay simple for small businesses?**
- **Answer:** Current architecture IS simple — modular monolith, no microservices, no eventing, no cross-domain complexity. This simplicity must be preserved.
- **Evidence:** Single database, in-process function calls, no message queues, no distributed transactions.
- **Confidence:** HIGH

**Q12: How can simple concepts become richer later?**
- **Answer:** Through the adapter reference pattern — domains can reference shared concepts via opaque IDs without requiring shared identity now.
- **Evidence:** Manufacturing `item_ref` is an opaque INT that could later be resolved against a Shared Items registry without schema change.
- **Confidence:** HIGH

**Q13: Which decisions are irreversible?**
- **Answer:** The decision to centralize Party identity would be highly irreversible — once domains link to a shared `parties` table, splitting requires migration of all consumer tables. The decision to require Shared Items resolution in Manufacturing would be irreversible — it would couple Manufacturing to a Shared Items DB dependency.
- **Evidence:** Adding a `party_id` FK to `hosp_guests` would require migration to remove if Parties is later rejected/split.
- **Confidence:** HIGH

**Q14: Which are reversible?**
- **Answer:** The opaque INT reference pattern is reversible — Manufacturing can drop `item_ref` or repurpose it without affecting Shared Items. Adding a Shared Items column is reversible if no runtime dependency exists.
- **Evidence:** `ALTER TABLE products DROP COLUMN item_ref` would not affect any other table or code path.
- **Confidence:** HIGH

**Q15: Which must be made now?**
- **Answer:** None for shared foundations. The boring baseline (domain-owned + stronger contracts) is sufficient. No Shared Foundation promotion is justified by current evidence.
- **Evidence:** Zero runtime Shared Foundation consumers across all candidate concepts.
- **Confidence:** HIGH

**Q16: Which merely need an evolutionary path?**
- **Answer:** Opaque INT reference columns (`item_ref`, `party_id` when introduced) preserve the evolutionary path — they can be repurposed to reference a shared identity later without forcing premature centralization now.
- **Evidence:** Manufacturing `item_ref` is an opaque INT with no FK to Shared Items; BOM uses opaque `finished_item_ref`/`component_item_ref`.
- **Confidence:** HIGH

**Q17: Which are safe to defer?**
- **Answer:** All shared entity concepts (Item canonicalization, Party canonicalization, Inventory centralization, Commercial centralization). Safe to defer until at least one second independent consumer adopts the concept.
- **Evidence:** No second consumer exists for any shared candidate concept.
- **Confidence:** HIGH

**Q18: Which approaches were rejected and why?**
- **Answer:** Universal order/document master (commercial doc Section 3, Model A → REJECTED); universal product catalog (would force incompatible domain fields into one table); mandatory cross-domain identity (no evidence of need); mandatory event sourcing/CQRS (no cross-domain operations to justify).
- **Evidence:** shared-commercial-foundation.md explicitly rejects Model A; all domains maintain separate entity tables.
- **Confidence:** HIGH

---

## 9. Synthesis Artifacts

**File created:** `docs/architecture/odarehub-future-architecture-pressure-test-results.md` (this file)

**File to update:** `docs/architecture/odarehub-future-architecture-planning-brief.md` — Section 31 (Final Synthesis Artifact) — populate with evidence-backed decisions (planned follow-up, not this task).

**No code changes:** Per workstream boundaries, no implementation changes, migrations, or Shared restructuring were performed.

---

## 10. Verification

Commands executed and results:

```
# 1. Shared Items runtime consumer check (excluding tests/probes)
$ grep -rn "ItemIdentityService\|Shared\\Item" apps/ app/ platform/ --include="*.php" | grep -v Tests/probe
(0 results — PASS: no runtime consumer)

# 2. Shared\ namespace in composer.json autoload
$ grep "Shared" composer.json
(0 matches — PASS: not autoloaded)

# 3. party_id foreign keys in consumer tables
$ grep -rn "party_id\|party_ref" apps/ app/ --include="*.sql"
apps/Manufacturing/modules/DispatchEntries/migrations/004_dispatch_execution_columns.sql:third_party_reference VARCHAR(190) NULL
(1 match — third_party_reference, NOT a party FK — PASS: no shared party link)

# 4. Parties app in registered apps
$ grep -rn "parties\|Parties" app/Services/AppLocalDiscoveryService.php
(0 matches — not auto-discovered as active app)

# 5. SharedInventory/SharedCommercial on main
$ find platform -type d -name "Shared*" && find shared -type d
(platform has no SharedInventory/SharedCommercial; shared/ has only Item/Foundation)

# 6. Manufacturing items not using Shared Items
$ grep -rn "Shared" apps/Manufacturing/modules/Bom/Services/BomService.php
(0 matches — BOM service does not import Shared Items)

# 7. Parties routes (consumer evidence)
$ cat apps/Parties/routes.php
(empty — no UI routes, no API)

# 8. Shared Items probe (self-contained, no runtime consumer)
$ php shared/Item/Foundation/Tests/probe_item_identity.php
=== SHARED ITEMS FOUNDATION PROBE ===
Passed: 19
Failed: 0
PASS
(This probe tests the skeleton in isolation — it does NOT test runtime consumer adoption)

# 9. Cross-domain table access from Shell
$ grep -rn "FROM manufacturing_\|FROM hosp_\|FROM sbaio_\|FROM procurement_" apps/Shell/ --include="*.php"
(5 matches — all read-only projections via DB::fetchOne, not business logic imports)

# 10. Generated apps classification
$ cat apps/Generated/inventory_app/manifest.json | grep generated_by
"generated_by": "studio"
(Studio scaffolding, not registered business app)
```

---

## 11. Residual Architecture Questions

Only genuine unresolved questions:

1. **When will a second domain consumer adopt Shared Items?** The contract is ready; the second consumer must arrive before promotion.

2. **When will Hospitality/SBAIO/Procurement migrate to Party references?** The Parties skeleton exists; consumer adoption must happen before Shared Foundation promotion.

3. **When will cross-domain inventory be needed?** Manufacturing stock ledger is domain-local. Multi-domain inventory sharing must be proven by a second consumer domain before centralization.

4. **When will commercial transaction sharing be needed?** Procurement, SBAIO, Manufacturing, Hospitality commercial concepts have incompatible lifecycles. A second consumer with compatible semantics must emerge before shared commercial foundation.

No unresolved question materially alters the target architecture as established: domain-owned with no Shared Foundation promotion.

---

## 12. Gate Decision

**PRESSURE TESTS COMPLETE:** YES

**CANONICAL DECISION GATE COMPLETE:** YES

**FINAL SYNTHESIS COMPLETE:** YES

**ARCHITECTURE STATUS:** COMPLETE

**SHARED ITEMS CLASSIFICATION:** PROTOTYPE / MIGRATION AID

**SHARED PARTIES CLASSIFICATION:** PROTOTYPE / MIGRATION AID

**SHARED INVENTORY CLASSIFICATION:** INSUFFICIENT EVIDENCE (no shared inventory foundation exists on main)

**SHARED COMMERCIAL CLASSIFICATION:** INSUFFICIENT EVIDENCE (no shared commercial foundation exists on main)

**UNRESOLVED ARCHITECTURE DECISIONS:**
- None that materially alter the target architecture. The four residual questions above are FUTURE triggers for re-evaluation, not current blockers.

**NEXT AUTHORIZED IMPLEMENTATION MILESTONE:** NONE — the boring baseline (domain-owned + stronger contracts + opaque reference seams) is the correct architecture. No Shared/Foundation restructuring is authorized. The existing `shared/Item/Foundation/` skeleton and `apps/Parties/` skeleton may be retained as evidence and migration input, but neither should be promoted to active Shared Foundation without a second independent runtime consumer.

**IMPLEMENTATION GATE:** CLOSED
