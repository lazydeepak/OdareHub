# Classification Criteria — Read-Only Audit

=== CLASSIFICATION FRAMEWORK ===
Applied to each Manufacturing component (module/app/service/entity/concept) based on repository evidence.

=== SHARED CANDIDATES EVALUATED ===

Candidate | Evidence for Shared | Evidence Against Shared | Classification Decision | Rationale
--- | --- | --- | --- | ---
Products (catalog master) | Used extensively by Manufacturing; `products` table altered by Manufacturing but serves as master for Manufacturing modules; user direction mentions Products/Items as considered but requires fresh evidence. Manufacturing's Products module defines product identity and extends with manufacturing-specific fields; no external suite directly imports Products module (verified by grep). | Manufacturing owns extensive manufacturing-specific extensions on `products` (11 ALTER migrations). Products module's `plugin.json` declares `suite: manufacturing`, `group: ipm`, `category: business`. No evidence of non-Manufacturing consumers in code. No external module requires Products except Manufacturing's own modules. | MANUFACTURING CORE (with shared-master awareness) | Although Products acts as master for Manufacturing, there is no evidence of external suite dependency. Extracting Products as Shared App would relocate Manufacturing's core catalog master outward, creating outward dependency for Manufacturing core concepts (production plans, assembly, QC, dispatch all reference products). Per classification rule: "Classify as Manufacturing Core when extracting it would make Manufacturing depend outward for its own core concepts." This applies strongly to Product. Not a safe Shared App extraction at this stage. Products should stay Manufacturing Core until cross-suite consumer contracts are established.
Material / Inventory (MaterialManagement domain) | MaterialManagement references `materials`, `material_ledger`, etc.; could overlap with supply/inventory suite. User direction does not explicitly identify Material as shared. No external module dependency evidence. | Manufacturing owns full material planning/stock/cost/receipt/orders surfaces. Material domain is Manufacturing-specific. No non-Manufacturing consumer found. | MANUFACTURING CORE | Stay Manufacturing-specific.
Machine (Machines module) | Independent machine master; no external suite dependency. Could be shared if equipment management is cross-suite. | No external consumer found; Manufacturing-specific machine specs and assignments (`machine_specs`, `part_machine_map`, `production_source` on products). | MANUFACTURING CORE | Manufacturing-specific; not shared.
Demand / Coverage Analytics (Coverage module + DemandEngine) | Coverage analytics could provide cross-suite visibility into manufacturing capacity and shortages. Demand engine calculates demand for Manufacturing. No external consumer found in code. | Coverage module is Manufacturing-only (dashboard_only); DemandEngine and DemandDashboard are Manufacturing-specific. No external module references these services. | MANUFACTURING CORE / SUITE EXTENSION | Coverage and demand analytics are Manufacturing-specific analytics. Could potentially become Manufacturing Extension or shared analytics service if promoted, but no evidence supports this now.
Party / Customer / Supplier | Manufacturing uses customer/demand concepts through DailyOrders/PreOrders, but does not own customer/supplier master. Parties (separate app) covers these. Manufacturing does not import Parties directly. | No direct dependency; Manufacturing operates independently. Parties exists as separate shared-capable app. | SHARED APP (Parties — already identified by user direction) | Parties is the correct shared candidate for cross-suite party/customer/supplier concepts. Manufacturing should eventually reference Parties contracts, not duplicate them.
Label Design / Label Context | Manufacturing Products module defines label context/template (`manufacturing.products.product.label.*`) for product identification. Label rendering pipeline is Platform-level (future). Label design contracts reference manufacturing label resources. | Manufacturing owns product-specific label context; label design is module-specific extension of a platform label system. No cross-suite label contract evidence. | MANUFACTURING SUITE EXTENSION (label context) / PLATFORM (label pipeline) | Manufacturing's label resources should remain Manufacturing-owned; the label rendering engine is Platform-level.
Workflow / Stage Transition / Governance (Workflow module) | Workflow module provides workflow governance (`WorkflowPolicy`, `WorkflowTransitionEngine`, `WorkflowRegistry`, `WorkflowGovernance`). Planning and process modules (ProductionPlans, ProductionEntries, AssemblyPlans, AssemblyEntries, DispatchEntries, QCEntries, QCPlans) depend on it. Workflow is Manufacturing-specific (manufacturing stage transitions). | Workflow module is Manufacturing-owned; no external suite references Manufacturing workflow contracts. No external consumer of Manufacturing workflow services found. | MANUFACTURING CORE | Essential Manufacturing workflow/domain behavior; must stay Manufacturing core.
Assembly / Production / Dispatch / QC / Daily Orders / Plans | All process execution/planning modules. Essential Manufacturing workflow. | No cross-suite consumer evidence. | MANUFACTURING CORE | Essential Manufacturing core.
Ledger / Stock Ledger | Manufacturing-specific inventory tracking (`stock_ledger_entries`). References external `qr_stock_updates`. Could overlap with inventory suite, but no evidence of external dependency. | Manufacturing owns ledger records for manufacturing domain. | MANUFACTURING CORE / EXTENSION (if inventory is shared) | Not a Shared App without cross-suite inventory contract evidence.

=== SUITE EXTENSION CLASSIFICATION ===
Components that should remain optional Manufacturing extensions (not Shared Apps):
- Coverage analytics / dashboard (manufacturing-specific reporting)
- Demand analytics / workspace (manufacturing demand planning)
- Label context/template for manufacturing products (manufacturing-specific label design)
- Workflow governance extensions specific to manufacturing stages (manufacturing-specific stage transition policies)
- Module-specific widget/dataset contributions to Studio (CustomizationStudio) — Manufacturing provides dataset contributions, not shared dataset contracts.
- Module-specific report/export contracts (module-owned reports, not cross-module reports)
- Operator adapter contracts feeding Shell operator layer (manufacturing adapter contracts, not generic adapter contracts)
- Manufacturing-specific dashboard charts (throughput, assembly output, QC pass rate, dispatch volume)
- Manufacturing-specific display floor styles and workspace surfaces

=== MANUFACTURING CORE CLASSIFICATION ===
Components that must stay Manufacturing Core (essential Manufacturing workflow/domain behavior):
- Products (catalog master with manufacturing extensions) — core entity, must stay Manufacturing-owned.
- Machines (machine registry) — core manufacturing entity.
- MaterialManagement (material planning, stock, orders, coverage, cost, capacity) — core manufacturing supply/inventory workflow.
- ProductionPlans, ProductionEntries, ProductionQueue — core production workflow.
- DailyOrders, PreOrders — core demand/order workflow.
- AssemblyPlans, AssemblyEntries — core assembly workflow.
- QCPlans, QCEntries — core quality workflow.
- DispatchEntries — core dispatch/fulfillment workflow.
- Workflow / Stage Transition / Governance — core manufacturing lifecycle management.
- Ledger (manufacturing stock/inventory tracking) — core inventory tracking.
- Manufacturing app-level services: DemandEngine, DemandExecution, DemandRouteDecision, DemandWorkBucket, AssemblyPlanService, ProductionPlanService, ProductionOperationService, ProcessingOperationService, DispatchOpsService, QueueBoardService, HandoffBoardService, StageTransitionService, ManufacturingDashboardBlockService, SearchProvider.
- Manufacturing routes (canonical business routes under /apps/manufacturing/*, module routes, compatibility aliases).
- Manufacturing permissions, menus, widgets.

=== SHARED APP CANDIDATE VERDICT ===
- Parties: Confirmed Shared App candidate (already identified by user direction; no Manufacturing import of Parties, but concept is cross-suite).
- Products (catalog master): NOT a Shared App extraction target at this stage. Manufacturing's core concepts depend on it directly; extraction would create outward dependency. Should remain Manufacturing Core. Shared master contract could be established (Platform Catalog / Shared Catalog concept) separately, but Manufacturing's product master must not be relocated out of Manufacturing in this audit.
- Material / Inventory: NOT a Shared App extraction target; Manufacturing-specific domain with no cross-suite consumer evidence.
- Machine: Manufacturing-specific; not shared.
- Label Design (label pipeline): Platform-level rendering pipeline; Manufacturing provides label context as Manufacturing extension.
- Workflow / Governance: Manufacturing-specific stage/workflow governance; not shared.
