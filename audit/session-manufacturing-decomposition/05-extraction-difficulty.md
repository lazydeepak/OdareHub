# Extraction Difficulty Assessment — Read-Only Audit

=== SHARED CANDIDATE: Parties ===
Current state: Parties is a separate app/module at apps/Parties/. Manufacturing does not import Parties directly. Manufacturing defines its own party-related concepts (customer/demand through DailyOrders/PreOrders/Coverage/Demands; supplier/concept through MaterialManagement; no direct supplier master).
Coupling: LOW — Manufacturing operates independently; no Parties import found in Manufacturing PHP code (verified by grep; zero hits for App\Parties or Plugins\Parties).
Migration risk: LOW — Manufacturing could reference Parties contracts (if established) without major restructuring; Manufacturing's current independence means adding a contract dependency is low-impact, but removing Manufacturing's implicit party references would require contract design first.
DB ownership complexity: LOW — Parties owns its tables; Manufacturing does not share party tables directly.
API compatibility risk: MEDIUM — Manufacturing's demand/order surfaces reference customer concepts (DailyOrders, PreOrders, Demands, Coverage). If Parties contract is established, Manufacturing modules would need to reference party IDs/contracts rather than local keys. Requires contract design.
UI coupling: LOW — Manufacturing UI surfaces reference domain concepts, not party IDs directly.
Test coverage: Unknown without inspection; Manufacturing's tests would need contract-level integration tests.
Number of consumers: Manufacturing (potential future consumer); potentially other suites (SBAIO, Platform, etc.).
Expected extraction order: FIRST (lowest risk, cross-suite value already recognized by user direction). Recommended only after contract design (not in this audit).

=== MANUFACTURING CORE — Products (Catalog Master) ===
Why NOT shared now: Manufacturing core concepts depend directly on Products module. All 18 modules either require Products directly or depend on modules that require Products. Migration would require Manufacturing to import Products externally; Manufacturing's core identity (production, quality, dispatch, assembly, planning) is tightly bound to product identity.
Coupling: HIGH — All Manufacturing modules either directly or transitively depend on Products.
Migration risk: HIGH — Manufacturing has 11 ALTER migrations on `products`. Manufacturing-specific fields (`production_source`, `requires_qc`, `requires_assembly`, `dispatch_as_is`, `cycle_time`, etc.) are embedded in the master table.
DB ownership complexity: HIGH — Manufacturing extends `products` extensively; shared master contract would require schema separation (core identity vs. manufacturing extensions).
API compatibility: HIGH — Manufacturing controllers/services use product IDs extensively; contract redesign needed.
UI coupling: HIGH — Module surfaces reference product data extensively.
Test coverage: Unknown; high dependency surface means high regression risk.
Number of consumers: Manufacturing (all modules); potentially external if shared master contract is established.
Expected order: NOT FIRST; should follow after lower-risk shared candidates (Parties) and should only proceed with explicit contract design separating core identity from Manufacturing extensions.

=== MANUFACTURING CORE — Machines ===
Coupling: MEDIUM — PartMachineMap requires Machines; Manufacturing dashboards/workboards reference Machines. Independent of Products.
Migration risk: LOW-MEDIUM — Machine master is independent; could be extracted as Manufacturing extension or shared entity if cross-suite need is established, but no cross-suite consumer evidence exists.
DB ownership: LOW — Machines owns `machines`, `machine_specs`.
API compatibility: LOW — Independent module with clear controller/service contract.
UI coupling: LOW-MEDIUM — Machine surfaces are Manufacturing-specific (machine leader, dashboard, reports).
Expected order: Not a Shared App candidate currently. Could become Manufacturing Suite Core or Manufacturing Extension; not first extraction.

=== MANUFACTURING CORE — MaterialManagement (Material Planning / Supply) ===
Coupling: MEDIUM-HIGH — MaterialManagement is a major planning/business module with routes (`/materials/*`), controllers, services, widget registry, reports, and references to external material tables.
Migration risk: MEDIUM — Module has declared `required_tables` referencing external `materials`, `part_material_map`, `material_ledger`, etc. This indicates potential overlap or dependency with external inventory/supply domain.
DB ownership: MEDIUM — MaterialManagement uses its own surfaces but references external tables; unclear if these tables are Manufacturing-owned or shared with external supply/inventory.
API compatibility: MEDIUM — MaterialAccessService and MaterialSchemaService provide domain contracts.
Expected order: Not a Shared App candidate without clarification of `required_tables` ownership and cross-suite inventory contract.

=== MANUFACTURING CORE — Workflow / Stage Transition ===
Coupling: HIGH — Workflow module provides transition engine; Planning/Process modules (ProductionPlans, ProductionEntries, AssemblyPlans, AssemblyEntries, DispatchEntries, QCEntries) depend on workflow governance.
Migration risk: HIGH — Workflow is Manufacturing-specific stage governance; extraction would break Manufacturing lifecycle management.
DB ownership: MEDIUM — Workflow references `workflow_approval_events` (external/update table) and manages stage/readiness/state.
Expected order: Manufacturing Core only.

=== MANUFACTURING EXTENSION — Coverage / Demand Analytics ===
Coupling: LOW-MEDIUM — Coverage is dashboard-only; DemandEngine services are Manufacturing-specific. No module depends on Coverage as a required dependency (Coverage plugin.json requires Base only, optional ACL/Audit).
Migration risk: LOW — Coverage analytics could become Manufacturing Extension; however, no cross-suite consumer evidence exists.
Expected order: Not shared; Manufacturing Extension.

=== MANUFACTURING EXTENSION — Label Context / Template ===
Coupling: LOW — Label resources (`manufacturing.products.product.label.*`) are Manufacturing-specific extensions of product data. Label pipeline is Platform-level.
Migration risk: LOW — Manufacturing label resources can stay Manufacturing Extension; platform-level pipeline handles rendering.
Expected order: Manufacturing Extension.
