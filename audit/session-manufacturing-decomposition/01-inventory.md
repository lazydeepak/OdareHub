# Manufacturing Module Inventory — Read-Only Audit

Source path: apps/Manufacturing/
Audit type: Read-only decomposition audit (Session C)
Date: $(date +%Y-%m-%d)
Status: NO FILE MOVES, NO COMMIT, NO PUSH

=== APP-LEVEL ===
App: Manufacturing (bundle, id=manufacturing, version=1.0.0)
Type: business
Native modules: daily_orders, production_plans, production_queue, assembly_plans, assembly_entries
Legacy plugins (18): Products, Workflow, Supply, Coverage, MaterialManagement, Machines, PartMachineMap, PreOrders, DailyOrders, ProductionPlans, ProductionQueue, Ledger, ProductionEntries, AssemblyPlans, AssemblyEntries, QCPlans, QCEntries, DispatchEntries
Dependencies: QRCode>=1.0.0
Own routes: 40+ canonical routes, 20+ aliases (compatibility/deprecated)
Own controllers: 15 controllers covering assembly, coverage, daily orders, demand, dispatch, production operations, stage transitions, export/import/restore.
Own services: 23+ (engine, route registry, dashboard blocks, adapter contributions, search provider, widget registry, demand engine, handoff tracking, execution services, etc.)
Own UI surfaces: admin (dashboard blocks, charts, reports), operator (sidebar/focus/views/data-exchange/breadcrumb/actions), display (floor CSS, workspace contributions).
Own migrations: 001 (operational model), 002 (stage transitions), 003 (assembly execution), 004 (packaging stage), 005 (upstream supply)
Own permissions: manufacturing.view, manufacturing.manage, materials.*, production workflow.
Hooks: host_surface (me, approval_inbox, role_inbox), operator_surface (sidebar, focus, data_exchange, breadcrumbs, actions, labels), widget_builder_dataset, boot.

=== MODULE INVENTORY (18 modules) ===
Module | Key | Type | Description | Own DB Tables (migrations) | Main Routes | Controllers | Services | Consumers / Requires
--- | --- | --- | --- | --- | --- | --- | --- | ---
Products | Products | business_entity (IPM) | Parts master / product definition; owns product catalog for Manufacturing | products (CREATE + 11 ALTER migrations) | /apps/manufacturing/products | ProductsController | Part360Service, PartEngineeringSchemaService, ProductWidgetRegistry, PartsMasterSnapshotService, PartExecutionRouteResolver | Required by: DailyOrders, AssemblyEntries, AssemblyPlans, DispatchEntries, Ledger, PartMachineMap, MaterialManagement, PreOrders. Provides core entity: Product.
Machines | Machines | business_entity (IPM) | Machine master / equipment registry | machines, machine_specs (2 migrations) | /machines | MachinesController | MachinesWidgetRegistry, MachineLeaderDashboardService | Required by: PartMachineMap. Independent business entity.
MaterialManagement | MaterialManagement | business_entity (planning) | Controlled material planning, stock, coverage, storage governance; references external tables (materials, part_material_map, material_ledger, etc. declared as required_tables) | No own SQL migration file listed separately (uses declared required_tables reference) | /apps/manufacturing/materials/* (master, mapping, stock, receipt, coverage, planning, orders, capacity, cost) | MaterialManagementController | MaterialManagementService, MaterialAccessService, MaterialSchemaService, MaterialManagementWidgetRegistry | Own domain; optional ACL/Audit. References: materials, part_material_map, etc.
PartMachineMap | PartMachineMap | business_entity (IPM) | Maps parts (Products) to machines (Machines) for eligibility | part_machine_map (001) | /apps/manufacturing/materials/master? or own route file | PartMachineMapController | PartMachineMapWidgetRegistry | Requires: Products>=1.0.0, Machines>=1.0.0
PreOrders | PreOrders | business_entity | Forecast / reserved demand planning | pre_orders (001) | /apps/manufacturing/pre-orders (routes) | PreOrdersController | PreOrderWidgetRegistry | Requires: Products>=1.0.0
DailyOrders | DailyOrders | business_entity | Actual daily demand orders (IPM) | daily_orders (001), coverage explainability (002) | /apps/manufacturing/daily-orders | DailyOrdersController | DailyOrderService, Order360Service, DailyOrderWidgetRegistry | Requires: Products>=1.0.0
ProductionPlans | ProductionPlans | planning | Demand-backed production scheduling / planning | production_plans (001), workflow governance (002), added_by (003) | /apps/manufacturing/production-plans | ProductionPlansController | ProductionPlanService, ProductionPlanWidgetRegistry | Requires: Products, ProductionEntries (via dependency chain from module contracts). Module contract: production_plans.
ProductionEntries | ProductionEntries | process_execution | Production execution entries / work records | production_entries (001) | /production-entries | ProductionEntriesController | ProductionEntryService, ProductionEntryWidgetRegistry | Requires: Products. Process execution.
ProductionQueue | ProductionQueue | service/dashboard_only (no schema in module folder) | Production queue / board view. Module folder has plugin.json, routes, dashboard, bootstrap, widget registry. References external production_plans/products/machines tables at runtime. | N/A (no migrations in folder) | /manufacturing/production-queue, /production (alias) | QueueBoardService | ProductionQueueWidgetRegistry | References tables at runtime.
QCPlans | QCPlans | business_entity | QC planning assignments / schedules | qc_plans (001), assigned_to (002) | /qc-plans | QCPlansController | QCPlanService (implied by services folder), QCPlanWidgetRegistry | Own planning module.
QCEntries | QCEntries | process_execution | Quality control execution entries / inspection records | qc_entries (001), workflow governance (002) | /qc-entries | QCEntriesController | QCEntryService, QCEntryWidgetRegistry, QcLeaderDashboardService | Process execution; requires workflow governance.
AssemblyPlans | AssemblyPlans | planning | Demand-backed assembly planning before QC | assembly_plan_contract (001) | /apps/manufacturing/assembly-plans | AssemblyPlansController | AssemblyPlanService, AssemblyPlanWidgetRegistry | Requires: Products, DailyOrders, ProductionEntries
AssemblyEntries | AssemblyEntries | process_execution | Completed assembly execution records before QC release | assembly_entries (001) | /apps/manufacturing/assembly-queue | AssemblyEntriesController | AssemblyEntryService, AssemblyEntryWidgetRegistry | Requires: Products, AssemblyPlans
DispatchEntries | DispatchEntries | process_execution | Dispatch-ready / dispatched quantity records; execution lifecycle with workflow governance (003, 004) | dispatch_entries (001), workflow governance (002), workflow governance add (003), dispatch execution columns (004) | /dispatch-entries, /dispatch (alias) | DispatchEntriesController | DispatchEntryService, DispatchEntryWidgetRegistry, DispatchWorkflow, DispatchLeaderDashboardService | Requires: Products, DailyOrders, ProductionPlans, ProductionEntries, QCEntries. Most complex dependency chain.
Coverage | Coverage | dashboard_only | Manufacturing coverage calculations, dashboard, shortage analysis. No DB migrations. | None (no SQL file) | /manufacturing/coverage, /manufacturing/coverage/index.php (admin view) | CoverageController | CoverageService, CoverageWidgetRegistry | Independent; requires Base only. Optional ACL/Audit.
Workflow | Workflow (plugins) | governance | Workflow governance / transition engine; stage transitions; workflow registry / policy / registry engine. Service-only module under modules/Workflow. | workflow_approval_events (external table referenced in manifest notifications) | /manufacturing/stage-board, stage-release, stage-override, /manufacturing/stage-board, workflow notifications | Workflow services (transition engine, policy, registry) | WorkflowGovernance, WorkflowPolicy, WorkflowTransitionEngine, WorkflowRegistry | Module-level service; provides workflow governance for Planning/Process modules (ProductionPlans, ProductionEntries, DispatchEntries, QCEntries, AssemblyPlans, AssemblyEntries). Own routes and services.
Supply | Supply (plugin) | service/integration? | Supply / upstream supply. Module folder only: plugin.json, routes.php, install.php. No controllers/services/views listed in file output (minimal). | None listed separately (may reference external supply tables) | routes.php only (minimal) | Minimal module; service-level or integration module.
Ledger | Ledger | business_entity (engine) | Stock ledger with balances / movement history. References external tables (qr_stock_updates referenced in maintenance). Own table: stock_ledger_entries. Own routes: /ledger (implied by routes file). Controllers: LedgerController. Services: LedgerWidgetRegistry. Reports: manufacturing.ledger.overview. Views: add, index, export, reconcile, report. Own migrations: 001. References external qr_stock_updates. | stock_ledger_entries (001)

Note: Module contracts from plugin.json confirm dependency chain clearly:
- Products is base entity required by most.
- Machines is independent base entity.
- MaterialManagement requires Base only (references external tables).
- DailyOrders requires Products.
- ProductionPlans requires Products and ProductionEntries (through dependency in contracts, though plugin.json shows only Products directly; ProductionQueue references production_plans/products/machines at runtime).
- ProductionEntries requires Products.
- AssemblyPlans requires Products, DailyOrders, ProductionEntries.
- AssemblyEntries requires Products, AssemblyPlans.
- DispatchEntries requires Products, DailyOrders, ProductionPlans, ProductionEntries, QCEntries.
- QCPlans and QCEntries are relatively independent (QCPlans requires Base; QCEntries requires workflow governance but not explicitly listed in plugin.json requires — depends on workflow module integration).
- Coverage requires Base only.
- PartMachineMap requires Products + Machines.
- PreOrders requires Products.
- Ledger requires Products.

=== APP-LEVEL SERVICE CONTRACT REFERENCES ===
Key observations from Services/ folder:
- ManufacturingSearchProvider (search contract for search service) — provides Manufacturing-specific search catalog
- DemandEngineService, DemandExecutionService, DemandRouteDecisionService, DemandWorkBucketService — demand planning and routing
- ProductionOperationService, ProcessingOperationService — process execution orchestration
- AssemblyPlanService, StageTransitionService — stage/workflow transition logic
- DispatchOpsService, HandoffBoardService, QueueBoardService — operational board/workflow surfaces
- ManufacturingDashboardBlockService — admin surface contributions (dashboard blocks)
- ModuleReportRegistryService, ModuleSurfaceWidgetFactory, WidgetBlueprintRuntimeContributionService — module-level report/surface/widget contracts
- HostSurfaceContributionService / OperatorSurfaceContributionService — Shell host surface contributions (admin/operator/display surfaces)
- WidgetBuilderDatasetContributionService — Studio widget builder dataset contributions
- ManufacturingLegacyImportService / ManufacturingRestoreService / ManufacturingImportService / ManufacturingExportService — migration/import/export lifecycle
- Order360Service — cross-stage order/supply tracking
- OperatorLayerAdapters (Assembly, Coverage, DailyOrders, Demand, Dispatch, Machines, Materials, QC, Production, Processing) — adapter contracts feeding Shell operator layer
