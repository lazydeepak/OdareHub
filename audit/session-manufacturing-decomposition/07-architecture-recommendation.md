# Recommended Target Architecture — Manufacturing Decomposition Audit

Proposed target structure based on repository evidence (manifest contracts, module contracts, database ownership, dependency graph, route contracts, service contracts):

=== CURRENT STATE SUMMARY ===
Manufacturing (bundle/app) owns:
- 15 controllers
- 23+ app-level services
- 18 module plugins (Products, Machines, MaterialManagement, PartMachineMap, PreOrders, DailyOrders, ProductionPlans, ProductionEntries, ProductionQueue, QCPlans, QCEntries, AssemblyPlans, AssemblyEntries, Coverage, DispatchEntries, Workflow, Supply, Ledger)
- App-level routes (canonical + compatibility aliases)
- Module-level routes (each module owns focused surfaces)
- Database schema: Manufacturing-owned tables (mfg_*, product extensions, module tables) + Manufacturing-owned modules
- UI surfaces: admin/dashboard, operator/sidebar/focus/data-exchange/actions, display/floor/workspace contributions
- Widget/dataset contracts feeding Shell operator/admin surfaces

=== PROPOSED TARGET STRUCTURE ===
Based on evidence (no file moves authorized; this is conceptual architecture plan):

Platform (existing)
  - Shared registry contracts, platform contracts, user context, access policy
  - Style/theme registry (Platform-owned, separate audit)
  - Platform-owned label rendering pipeline (future)

Shared Apps (existing direction — Parties confirmed; others conditional)
  - Parties (confirmed Shared App candidate — cross-suite party/customer/supplier master; no Manufacturing import; Manufacturing could adopt contract safely)
  - Potential future: Product Catalog Master (only after Manufacturing-specific fields separated; NOT in this phase)
  - Potential future: Material/Inventory Master (only after `required_tables` ownership clarified; NOT in this phase)
  - Potential future: Machine Registry (only if cross-suite equipment management is confirmed; NOT in this phase)

Suites / Manufacturing / Core (essential Manufacturing workflow/domain)
  - Products (product catalog master + manufacturing extensions) — Manufacturing Core
  - Machines (machine registry) — Manufacturing Core or Manufacturing Suite (independent entity)
  - MaterialManagement (material planning, stock, coverage, orders, receipt, cost, capacity) — Manufacturing Core
  - ProductionPlans, ProductionEntries, ProductionQueue — Manufacturing Core (production workflow)
  - DailyOrders, PreOrders — Manufacturing Core (demand/workflow)
  - QCPlans, QCEntries — Manufacturing Core (quality workflow)
  - AssemblyPlans, AssemblyEntries — Manufacturing Core (assembly workflow)
  - DispatchEntries — Manufacturing Core (dispatch/fulfillment workflow)
  - Workflow / StageTransition / Governance — Manufacturing Core (manufacturing lifecycle governance)
  - Ledger — Manufacturing Core (manufacturing inventory tracking)

Suites / Manufacturing / Extensions (optional Manufacturing capabilities; could be enabled/disabled independently)
  - Coverage Analytics / Dashboard — Manufacturing Extension (analytics/dashboard module; independent dependency; no core dependency)
  - Demand Workspace / DemandEngine — Manufacturing Extension (analytics/planning workspace; could remain Manufacturing Suite Extension or Manufacturing Core depending on workflow design)
  - Supply — Manufacturing Extension (minimal service module; unclear external dependency; safe as extension)
  - Label Design / Label Context (Manufacturing-specific label design for products) — Manufacturing Extension (label rendering pipeline is Platform-level; Manufacturing provides label context/template contracts)

Platform / Infrastructure Capabilities (Platform-owned; Manufacturing consumes through contracts)
  - Operator Layer / Shell rendering framework (existing)
  - Workspace Profile / Experience composition (existing)
  - Label rendering engine (future — Manufacturing provides label contracts; Platform provides rendering pipeline)
  - Report/Export engine (existing — Manufacturing owns module reports; Platform owns engine)
  - Widget/Chart registry (existing — Manufacturing contributes datasets; Platform owns builder/runtime)
  - Search provider contracts (Manufacturing provides ManufacturingSearchProvider to Platform; Platform owns search service)

=== WHY NOT PROMOTE PRODUCTS AS SHARED APP NOW ===
Evidence:
- Manufacturing's `manifest.json` purge section drops columns from `products` that are Manufacturing-specific (production_source, requires_qc, requires_assembly, dispatch_as_is, delivery_flow, activity_type, cycle_time, etc.).
- Manufacturing's `plugin.json` for Products declares `suite: manufacturing`, `group: ipm`, `owner_app: manufacturing`, `module_key: Products`.
- All Manufacturing modules either directly or transitively depend on Products.
- Manufacturing controllers/services reference `products` extensively (product identity, part details, catalog health, supply model, execution planning).
- No external suite module requires Products (verified by code inspection; only Manufacturing's own modules reference Products directly).
- Extracting Products as Shared App would make Manufacturing depend outward for its own core identity; this violates the classification rule: "Classify as Manufacturing Core when extracting it would make Manufacturing depend outward for its own core concepts."

Recommendation: Products stays Manufacturing Core. Shared master contract can be established conceptually (Platform Catalog Master contract) but Products module must not be relocated out of Manufacturing in this audit phase.

=== WHY PARTIES IS THE SAFEST FIRST SHARED CANDIDATE ===
Evidence:
- Manufacturing does not import Parties (zero hits in Manufacturing PHP code).
- Manufacturing operates independently for party-related concepts (customer/demand through DailyOrders/PreOrders/Coverage; supplier/concept implicit through MaterialManagement but not explicitly imported from Parties).
- Parties exists as separate app/module with its own contracts.
- Manufacturing could adopt Parties contracts safely (add Parties as optional dependency, reference party contracts in Manufacturing services) without restructuring core Manufacturing logic.
- No Manufacturing database table directly duplicates Parties tables (no evidence of duplicate customer/supplier tables in Manufacturing migrations).
- User direction explicitly identifies Parties as shared candidate.
- Low extraction/migration risk; high cross-suite value (Parties is already recognized as cross-suite; Manufacturing adoption would establish contract boundary clearly).
