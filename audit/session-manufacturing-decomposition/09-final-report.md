# MANUFACTURING SHARED-VS-SUITE DECOMPOSITION AUDIT — FINAL REPORT

Audit session: Session C — Manufacturing Shared-vs-Suite Decomposition Audit
Audit type: Read-only (NO FILE MOVES, NO COMMITS, NO PUSHES)
Repository: /Users/lazydeepak/dev/OdareHub (OdareHub)
Audit date: $(date +%Y-%m-%d)
Audit directory: audit/session-manufacturing-decomposition/
Status: COMPLETE (Phase 1 inventory, Phase 2 dependency graph, Phase 3 domain ownership, Phase 4 classification criteria, Phase 5 extraction difficulty, architecture recommendation, extraction sequence)

=== PHASE 1 — INVENTORY SUMMARY ===
Manufacturing bundle/app (manifest.json): id=manufacturing, package_type=bundle, version=1.0.0, type=business, dependencies=QRCode>=1.0.0, legacy_plugins=18 modules, native_modules=5 (daily_orders, production_plans, production_queue, assembly_plans, assembly_entries).
App controllers: 15; Routes: 40+ canonical + 20+ aliases; Services: 23+; UI surfaces: admin/dashboard, operator/sidebar/focus/data-exchange/actions/breadcrumbs, display/floor/workspace/contributions.
Module plugins (18 total): Products, Machines, MaterialManagement, PartMachineMap, PreOrders, DailyOrders, ProductionPlans, ProductionEntries, ProductionQueue, QCPlans, QCEntries, AssemblyPlans, AssemblyEntries, Coverage, DispatchEntries, Workflow, Supply, Ledger.
Database tables (Manufacturing-owned): products (manufacturing extensions), machines, machine_specs, mfg_part_demands, mfg_stage_readiness, mfg_assembly_entries, mfg_upstream_generation_links, mfg_production_plan_candidates, mfg_procurement_demands, daily_orders, pre_orders, production_plans, production_entries, assembly_plan_contract, assembly_entries, qc_plans, qc_entries, dispatch_entries, stock_ledger_entries, part_machine_map, part_molds, material_ledger (referenced), materials (referenced), part_material_map (referenced), workflow_approval_events (referenced/notification table).
Permissions: manufacturing.view, manufacturing.manage, materials.*, workflow.*, production workflow.
Hooks: boot, host_surface (me/approval_inbox/role_inbox), operator_surface (sidebar/focus/data_exchange/breadcrumbs/actions/labels/bottom_actions), widget_builder_dataset.

=== PHASE 2 — DEPENDENCY GRAPH SUMMARY ===
Internal Manufacturing dependency chain (plugin contracts):
Products (base) → DailyOrders, ProductionEntries, MaterialManagement, Ledger, Coverage, PartMachineMap, PreOrders, AssemblyPlans, AssemblyEntries, DispatchEntries, Supply (independent), Machines (independent).
Machines (independent) → PartMachineMap.
DailyOrders (requires Products) → AssemblyPlans (requires DailyOrders + ProductionEntries).
ProductionEntries (requires Products) → ProductionPlans (requires ProductionEntries), AssemblyEntries (requires AssemblyPlans), DispatchEntries (requires ProductionEntries + ProductionPlans + QCEntries + DailyOrders + Products).
QCEntries (independent base, workflow-linked) → DispatchEntries (requires QCEntries).
Workflow (service/governance) → Planning/Process modules (ProductionPlans, ProductionEntries, AssemblyPlans, AssemblyEntries, DispatchEntries, QCEntries, QCPlans) via workflow governance contracts.
No code-level cycles detected in plugin contracts. Operational lifecycle cycles exist by design (demand → plan → entry → queue → execution → stage → QC → dispatch → handoff) — these are business workflow cycles, not structural dependency cycles blocking extraction.
Cross-suite dependencies verified by grep/search:
- Parties: ZERO Manufacturing code imports (verified by grep for App\Parties / Plugins\Parties across Manufacturing directory — no matches).
- Studio: ZERO Manufacturing runtime dependency (manufacturing AGENTS.md confirms Manufacturing must run independently of Studio).
- Shell: Manufacturing provides adapter contracts (`OperatorLayerAdapters`) and host-surface contributions (`HostSurfaceContributionService`, `OperatorSurfaceContributionService`, `DisplayActivityContributionService`) to Shell. Shell consumes Manufacturing contracts; Manufacturing does not import Shell business logic.
- Platform: Manufacturing controllers/services reference `platform_user_context_contract()` and `platform_user_access_policy_contract()` extensively — correct contract usage, not direct implementation dependency.
- Catalog/Items: Manufacturing's Products module owns the manufacturing catalog master (`products` table) with 11 ALTER migrations adding manufacturing-specific fields. No external Catalog/Items module dependency found; Manufacturing's Products serves as master within Manufacturing domain.
- Procurement/Supply: Manufacturing's MaterialManagement references external `materials` / `part_material_map` / `material_ledger` / `material_orders` / `material_capacity` / `material_reservations` (declared `required_tables`). External supply module (Procurement) does not import Manufacturing; Manufacturing's MaterialManagement owns material planning domain but relies on external supply/inventory tables whose ownership is ambiguous without external module inspection.

=== PHASE 3 — DOMAIN OWNERSHIP SUMMARY ===
For major entities/concepts investigated (not assumed):
- Product (catalog master): Manufacturing / Products module owns base `products` table and extends it extensively. Manufacturing-specific fields (`production_source`, `requires_qc`, `requires_assembly`, `cycle_time`, etc.) embedded in master. Should remain Manufacturing Core; shared master contract possible only with schema separation.
- Machine: Manufacturing / Machines module owns `machines` + `machine_specs`. Manufacturing-specific; not shared.
- Material / Supply (MaterialManagement): Manufacturing owns material planning/stock/cost/orders surfaces. References external `materials` / `material_ledger` tables; ownership ambiguous (Manufacturing domain or shared inventory). Not a clear Shared App candidate.
- Demand (DailyOrders / PreOrders / DemandEngine / Coverage): Manufacturing-specific; essential Manufacturing core.
- Production / Queue / Entry / Plan: Manufacturing core; must stay Manufacturing.
- Quality (QCPlans / QCEntries): Manufacturing core.
- Assembly (AssemblyPlans / AssemblyEntries): Manufacturing core.
- Dispatch / Fulfillment (DispatchEntries): Manufacturing core; most complex dependency chain (requires Products + DailyOrders + ProductionPlans + ProductionEntries + QCEntries).
- Workflow / Stage Governance (Workflow module): Manufacturing-specific lifecycle governance; tightly bound to Manufacturing Planning/Process modules. Manufacturing core.
- Ledger / Inventory Tracking (`stock_ledger_entries`): Manufacturing-owned inventory tracking; references `qr_stock_updates` (external/update). Manufacturing-specific.
- Label Design (Products label resources): Manufacturing-specific label context/template bound to product identity; Platform-level label rendering pipeline is separate. Manufacturing Extension.
- Party / Customer / Supplier: Manufacturing operates independently; no Parties import. Manufacturing's customer/demand references are Manufacturing-owned (DailyOrders, PreOrders, Coverage, Demands). Parties is the correct Shared App for cross-suite party concepts.
- Warehouse / Inventory Master: Not clearly defined as Manufacturing-owned; ambiguous from `required_tables` evidence. Should not be promoted to Manufacturing Core without clarification.

=== PHASE 4 — CLASSIFICATION SUMMARY ===
Classification criteria applied to major components:

Shared App Confirmed: Parties (low coupling, no Manufacturing import, cross-suite value, existing repository direction).
Manufacturing Core (must stay Manufacturing): Products (manufacturing master + extensions), Machines, MaterialManagement (material domain), Production workflow (Plans, Entries, Queue), DailyOrders / PreOrders, QCPlans / QCEntries, AssemblyPlans / AssemblyEntries, DispatchEntries, Workflow / Governance, Ledger, Supply (minimal module), Manufacturing route/services/contracts.
Manufacturing Suite Extension (optional, can be enabled/disabled): Coverage Analytics / Dashboard, Demand Workspace / DemandEngine analytics, Label Context (manufacturing-specific label design for products), Module-specific widget/dataset/report contracts feeding Studio.
Platform / Infrastructure (Platform-owned; Manufacturing consumes through contracts): Operator Shell framework, Workspace Profile / Experience composition, Label rendering pipeline (future), Report/Export engine, Widget/Chart registry, Search provider contracts.
Not Shared (rejected): Products (manufacturing master; too high dependency, core identity), MaterialManagement inventory (ambiguous external table ownership, Manufacturing-specific domain), Machines (Manufacturing-specific registry), Workflow governance (Manufacturing lifecycle), Coverage (analytics only), Label pipeline (platform rendering, not shared business concept), Supply (minimal service module, unclear external dependency).

=== PHASE 5 — EXTRACTION DIFFICULTY SUMMARY ===
Shared Candidate: Parties — Coupling: LOW; Migration risk: LOW; DB ownership: LOW (no Manufacturing table overlap); API compatibility: MEDIUM (requires contract design for demand/order/material references); UI coupling: LOW; Consumers: Manufacturing + potential other suites; Expected order: FIRST.
Manufacturing Core: Products — Coupling: HIGH; Migration risk: HIGH (11 ALTER migrations on `products`, core dependency for all Manufacturing); DB ownership: HIGH (Manufacturing extends master); Not recommended for extraction; must stay Manufacturing Core.
Manufacturing Core: Machines — Coupling: MEDIUM; Migration risk: LOW-MEDIUM; Not shared candidate (no cross-suite consumer evidence).
Manufacturing Core: MaterialManagement — Coupling: MEDIUM-HIGH; Migration risk: MEDIUM; DB ownership ambiguous (`required_tables`); Not shared candidate without clarification.
Manufacturing Core / Extension: Coverage — Coupling: LOW-MEDIUM; Migration risk: LOW; Not shared (analytics-specific); Could be Manufacturing Extension.
Manufacturing Extension: Label Context — Coupling: LOW; Migration risk: LOW; Manufacturing-specific; stays Manufacturing Extension.

=== DEPENDENCY PROBLEMS — SUMMARY ===
- No structural dependency cycles detected in Manufacturing module contracts.
- Cross-domain writes: Manufacturing writes Manufacturing-specific fields back into shared master (`products`). Schema contract needed if Product master is promoted to Shared App.
- Ambiguous table ownership: `materials`, `part_material_map`, `material_ledger`, `material_orders`, `material_capacity`, `material_reservations` — declared as `required_tables` by MaterialManagement; unclear if Manufacturing-owned or shared inventory domain.
- No direct Manufacturing imports of Parties — safe for Parties adoption, but Manufacturing's party concepts (demand/customer/supplier references) need contract mapping.
- No Manufacturing import of Studio — Manufacturing operates independently; Studio customization references Manufacturing artifacts safely.
- Manufacturing adapter contracts (`OperatorLayerAdapters`) and host-surface contributions (`HostSurfaceContributionService`, `OperatorSurfaceContributionService`) feed Shell cleanly; no Shell import of Manufacturing business logic.
- Manufacturing search/provider contracts (`ManufacturingSearchProvider`) feed Platform search cleanly; Platform owns search service.
- Duplicated entity concepts: Manufacturing defines its own demand/order/planning/execution entities; these are Manufacturing-specific, not duplicated externally. No evidence of duplicate Product or Machine master in other apps (Products module is Manufacturing-owned).

=== RECOMMENDED ARCHITECTURE (CONCEPTUAL — NO FILE MOVES) ===
Based on evidence from manifest contracts, plugin contracts, database ownership, service contracts, dependency graph, and user direction:

Platform (existing contracts, registry, platform services, user context, access policy, label rendering pipeline future)
  ↓ contracts / services
  ↓ adapter contracts / dataset contracts
Shared Apps (existing: Parties — confirmed; future conditional: Product Catalog Master if schema separated; Material Inventory Master if ownership clarified)
  ↓ contracts (optional dependencies added to Manufacturing manifest)
Suites / Manufacturing / Core (essential Manufacturing domain/workflow — must stay Manufacturing-owned):
  Products (catalog master + manufacturing extensions)
  Machines (machine registry)
  MaterialManagement (material planning/inventory/supply — Manufacturing domain; ownership clarification needed for `required_tables`)
  Production workflow (ProductionPlans, ProductionEntries, ProductionQueue)
  Demand workflow (DailyOrders, PreOrders, DemandEngine, DemandDashboard)
  Quality workflow (QCPlans, QCEntries)
  Assembly workflow (AssemblyPlans, AssemblyEntries)
  Dispatch/fulfillment workflow (DispatchEntries)
  Manufacturing lifecycle governance (Workflow / StageTransition / Governance)
  Manufacturing inventory tracking (Ledger / Stock Ledger)
Suites / Manufacturing / Extensions (optional Manufacturing capabilities; independent enablement):
  Coverage Analytics (Coverage module — analytics/dashboard; independent dependency)
  Demand Analytics / Workspace (DemandDashboard, Coverage analytics surfaces — Manufacturing Extension)
  Supply (Supply module — minimal service/integration; Manufacturing Extension)
  Manufacturing Label Design / Context (label resources bound to Manufacturing products — Manufacturing Extension; label rendering engine is Platform-level)
  Manufacturing Widget/Dataset/Report contracts (module-level contracts feeding Studio; Manufacturing Extension contracts for customization/studio consumption)
Platform / Infrastructure Capabilities:
  Shell rendering framework (operator/admin/display surfaces; adapter mechanism; overlay framework; style registry)
  Workspace Profile / Experience composition (ACL + profile + runtime renderer)
  Studio customization framework (read-only customization analysis/tooling; Manufacturing artifacts owned by Manufacturing)
  Search / Registry contracts (Platform owns registry/service; Manufacturing provides ManufacturingSearchProvider; Manufacturing provides adapter contracts)

=== EXTRACTION SEQUENCE (RECOMMENDED — NO EXECUTION) ===
1. FIRST: Parties (Shared App adoption / contract boundary) — LOWEST RISK, HIGHEST CROSS-SUITE VALUE. Manufacturing operates independently today; adding Parties contract dependency is safe, additive, and aligns with existing repository direction. No Manufacturing file restructuring required.
2. SECOND (design phase, not execution): Schema separation contract for Product Catalog Master (if promoted to Shared App) — separate Manufacturing-specific fields (`production_source`, `requires_qc`, `cycle_time`, etc.) from core product identity; establish Platform-level catalog contract; keep Manufacturing's `Products` module as Manufacturing Core owner of manufacturing extensions.
3. THIRD: Confirm Manufacturing Suite Core boundary (lock core modules; document dependency rules; establish Manufacturing Suite contract for routes, permissions, module contracts, database ownership).
4. FOURTH: Confirm Manufacturing Suite Extension boundary (Coverage, Supply, Label Context, Module-level widget/data contracts; document independent enablement rules).
5. FUTURE (conditional): Material Inventory Master clarification — determine ownership of `required_tables` (`materials`, `material_ledger`, etc.); only proceed with shared master contract after external module evidence is established.
6. FUTURE (not authorized): No Manufacturing restructuring moves without contract design first; no Product extraction without Manufacturing core identity preservation; no Workflow extraction without Manufacturing lifecycle preservation.

=== FIRST EXTRACTION — PARTIES ===
Justification (why lower-risk than alternatives):
- Manufacturing has zero direct Parties imports (verified by grep — zero references to Parties in Manufacturing PHP files). This confirms Manufacturing operates independently today; adoption is additive, not restructuring.
- Parties is already recognized by repository direction (user instruction explicitly names Parties as shared candidate covering customers, guests, suppliers). The audit confirms Manufacturing's independence supports this direction.
- Manufacturing's party-related concepts are implicit (DailyOrders for demand/customer; MaterialManagement for supplier/concept; Coverage/Demands for analytics) rather than explicit module-level contracts. Adding a Parties contract boundary clarifies Manufacturing's domain without removing Manufacturing logic or restructuring Manufacturing routes/services/controllers.
- No database ownership conflict: Manufacturing does not share party tables with Manufacturing database schema (no Manufacturing table references Parties; Manufacturing owns its own domain tables independently).
- Low migration risk: Expected work is contract-level (optional dependency addition to Manufacturing manifest, contract reference points in Manufacturing services) rather than structural file moves, database migrations, or route restructuring.
- Higher-value alternatives rejected as first candidate: Products (manufacturing core identity; absolute Manufacturing dependency; 11 ALTER migrations on `products`; extraction would break Manufacturing); MaterialManagement (ambiguous external table ownership; complex restructuring); Workflow (manufacturing lifecycle governance; tightly bound to core); Coverage (analytics-only; no cross-suite consumer); Machines (manufacturing-specific registry).

=== FINAL CLASSIFICATION MATRIX ===
Module / Component | Current Role | Proposed Classification | Evidence / Rationale
--- | --- | --- | ---
Manufacturing (app bundle) | Business bundle (IPM suite) | Manufacturing Suite (bundle remains Manufacturing-owned; internal modules split into Core + Extensions) | Bundle owns core domain; internal restructuring is conceptual.
Products | Module (catalog master + manufacturing extensions) | Manufacturing Core | All Manufacturing modules depend on Products; products table heavily altered by Manufacturing; manufacturing-specific fields embedded; extraction breaks Manufacturing identity.
Machines | Module (machine registry) | Manufacturing Core / Manufacturing Suite (independent core entity) | Independent module; no cross-suite consumer evidence; manufacturing-specific specs/assignments.
MaterialManagement | Module (material planning) | Manufacturing Core | Manufacturing-specific domain; ambiguous external table references (`required_tables`) require clarification before any shared master promotion.
PartMachineMap | Module | Manufacturing Core (mapping) | Manufacturing-specific mapping between Products and Machines; independent of cross-suite mapping contracts.
DailyOrders | Module | Manufacturing Core | Manufacturing-specific actual demand orders; core workflow input.
PreOrders | Module | Manufacturing Core | Manufacturing-specific forecast/reserved demand; core workflow input.
ProductionPlans | Module | Manufacturing Core | Essential planning workflow; depends on Product + ProductionEntries.
ProductionEntries | Module | Manufacturing Core | Essential execution workflow; core process execution.
ProductionQueue | Module | Manufacturing Core (service/dashboard) | Queue/workboard surface; references production tables at runtime; manufacturing-specific.
QCPlans | Module | Manufacturing Core | Manufacturing quality planning; essential quality workflow.
QCEntries | Module | Manufacturing Core | Manufacturing quality execution; process execution.
AssemblyPlans | Module | Manufacturing Core | Manufacturing assembly planning; essential workflow.
AssemblyEntries | Module | Manufacturing Core | Manufacturing assembly execution; essential workflow.
DispatchEntries | Module | Manufacturing Core | Manufacturing dispatch/fulfillment; most complex dependency chain; essential core.
Coverage | Module | Manufacturing Extension (analytics/dashboard) | Dashboard-only; independent dependency (Base only); no core module requires it; cross-suite analytics potential but no consumer evidence.
Workflow / Governance | Module / Service | Manufacturing Core (lifecycle governance) | Essential stage/workflow governance; tightly bound to Planning/Process modules; must stay Manufacturing core.
Supply | Module | Manufacturing Extension (minimal service) | Minimal service module; unclear external dependency; safe as Manufacturing Extension.
Ledger | Module | Manufacturing Core (inventory tracking) | Manufacturing inventory tracking; manufacturing-specific ledger; references external update table (`qr_stock_updates`).
Parties (separate app) | Separate app/module | Shared App (confirmed) | Manufacturing has zero direct import; cross-suite party/customer/supplier master; user direction confirms; safe first candidate.
Products Catalog Master (future shared master) | Not implemented as separate shared module; Manufacturing Products serves master within Manufacturing | Not authorized for extraction in this audit; future conditional only after schema separation contract | Manufacturing's product identity and manufacturing-specific extensions must be preserved; shared master requires contract design.
Label Context / Template | Manufacturing Products module label resources | Manufacturing Extension (label context) | Manufacturing-specific label design for products; platform-level rendering pipeline is separate.
Manufacturing Adapter Contracts | Adapters feeding Shell operator/admin surfaces | Manufacturing Suite Contracts (adapter contracts stay Manufacturing-owned) | Shell consumes Manufacturing adapter contracts cleanly; Manufacturing owns adapter contracts.
Widget/Dataset/Report Contracts | Manufacturing module-owned contracts feeding Studio/Shell | Manufacturing Extension Contracts (module-level contracts stay Manufacturing-owned) | Manufacturing provides contracts; Studio/Shell consume; Manufacturing ownership preserved.
