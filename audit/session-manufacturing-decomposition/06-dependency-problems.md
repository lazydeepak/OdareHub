# Dependency Problems — Manufacturing Decomposition Audit

=== IDENTIFIED CYCLES ===

No code-level cycles detected in Manufacturing plugin dependency contracts (plugin.json requires form a directed acyclic graph by inspection). However, implicit operational cycles exist by design:

- Production workflow cycle: DemandEngine → ProductionPlans → ProductionEntries → ProductionQueue → Execution (ProductionOperation / ProcessingOperation) → StageTransition → QCEntries / AssemblyEntries → DispatchEntries → HandoffBoard. This is a lifecycle cycle, not an architectural dependency cycle that blocks extraction.
- ProductionPlans requires ProductionEntries (plugin contract); ProductionQueue references production_plans at runtime (runtime dependency). No reverse dependency exists in plugin contracts.
- Coverage (analytics) reads demand/state data produced by DemandEngine / ProductionModules; no reverse dependency exists.

=== CROSS-DOMAIN WRITES ===

Evidence: Manufacturing's `manifest.json` purge section drops Manufacturing-specific columns from `products` and `dispatch_entries`, and drops Manufacturing-specific tables (`mfg_part_demands`, `mfg_stage_readiness`, `mfg_assembly_entries`). This confirms Manufacturing owns these domain tables directly. The `products` table alterations indicate Manufacturing extends a master catalog rather than owning it independently.

Cross-domain write risk: Manufacturing writes manufacturing-specific fields back into the shared master (`products`). If Product is promoted to Shared App, Manufacturing's manufacturing-specific fields must either be separated into Manufacturing extension tables or the shared master must explicitly reserve extension columns. This is a significant schema contract issue.

=== DUPLICATED ENTITIES ===

- Product identity and catalog: `Products` module owns `products` master; Manufacturing extends it. No separate external catalog module dependency found. Concept is not duplicated externally, but Manufacturing's dependency on it is absolute.
- Material / Inventory tracking: `MaterialManagement` uses `materials` and related external tables; `Ledger` uses `stock_ledger_entries`. Potential overlap with external inventory/supply domain, but no duplicate module found in Manufacturing.
- Demand: `DailyOrders` (actual demand) and `PreOrders` (forecast/reserved demand) are separate Manufacturing modules; `DemandEngine` calculates demand. Demand concepts are Manufacturing-owned, not duplicated externally.
- Machine: Only Manufacturing's `Machines` module; no duplicate.

=== TABLES WITH AMBIGUOUS OWNERSHIP ===

Table / Concept | Manufacturing Ownership Evidence | Ambiguity / Shared Potential
--- | --- | ---
`products` | Manufacturing owns `Products` module; 11 ALTER migrations extend `products` with manufacturing-specific fields; `manifest.json` purge drops manufacturing-specific columns from `products`. | Shared catalog master potential (already noted by user direction — Products/Items). Manufacturing's manufacturing extensions must be separated or preserved.
`materials`, `part_material_map`, `material_ledger`, `material_orders`, `material_capacity`, `material_reservations` | MaterialManagement module declares these as `required_tables`. No Manufacturing SQL migration creates them independently. They are referenced, not owned by Manufacturing module folder. | Ambiguous — these tables may be owned by an external inventory/supply/app module, or they may be Manufacturing-owned but referenced by module contracts. Without external module evidence, ownership remains Manufacturing domain but unclear if they are Manufacturing-only or shared.
`machines`, `machine_specs` | Manufacturing `Machines` module creates these; Manufacturing module folder owns them. | Manufacturing-specific; no ambiguity.
`mfg_part_demands`, `mfg_stage_readiness`, `mfg_assembly_entries`, `mfg_upstream_generation_links`, `mfg_production_plan_candidates`, `mfg_procurement_demands` | Manufacturing bundle/app migrations create these; `manifest.json` lists purge targets. | Manufacturing-only; no ambiguity.
`dispatch_entries`, `production_plans`, `production_entries`, `qc_entries`, `assembly_entries`, `daily_orders`, `pre_orders`, `stock_ledger_entries`, `part_machine_map`, `part_molds` | Manufacturing module migrations or module contracts own these. | Manufacturing-only for most; `part_machine_map` is Manufacturing-specific mapping.

=== DIRECT IMPLEMENTATION IMPORTS THAT SHOULD BECOME CONTRACTS ===

Evidence from Manufacturing code (Services/Controllers):

- Controllers/services reference Manufacturing's own service classes directly (`App\Manufacturing\Services\*`). These are internal implementation imports appropriate for Manufacturing app/module scope; they do not cross suite boundaries. No issue for Manufacturing internal architecture.
- Manufacturing services reference `platform_user_context_contract()` and `platform_user_access_policy_contract()` extensively. These are correct contract references (Platform contracts), not direct implementation imports.
- Manufacturing adapter contracts (`OperatorLayerAdapters`) reference Manufacturing module services/data. These are Manufacturing adapter implementations feeding Shell; they are Manufacturing-owned contracts that Shell consumes through adapter mechanism (not direct Shell import of Manufacturing business logic). This is appropriate.
- Manufacturing module controllers (e.g., ProductsController) reference `platform_admin` / `platform_user_context_contract()` and `platform_user_access_policy_contract()`. These are Platform contract calls, correct.
- Manufacturing modules reference `App\Core\` contracts/services (core framework). No Manufacturing business logic leaks into Core.
- No Manufacturing module imports `App\Studio\` directly; Manufacturing runs independently from Studio (per AGENTS.md rules and manifest rules).
- No Manufacturing module imports `App\Parties\` directly (verified by grep — zero hits). Manufacturing's demand/order/customer concepts are Manufacturing-owned, not Parties-derived.

Conclusion: Manufacturing's import patterns are largely appropriate — Manufacturing uses Platform contracts correctly, does not import Studio or Parties, and keeps Manufacturing business logic inside Manufacturing boundary. No major contract violations found.

=== UI COUPLING THAT BLOCKS EXTRACTION ===

Manufacturing's host-surface contributions (`HostSurfaceContributionService`, `OperatorSurfaceContributionService`) provide admin/operator/display surfaces to Shell. These contributions are Manufacturing-owned contracts that Shell consumes. They do not represent Shell importing Manufacturing logic; rather, Manufacturing provides contracts to Shell. This is appropriate and does not block Manufacturing extraction — Manufacturing can remain Manufacturing-owned while contributing to Shell.

Manufacturing's widget/dataset/dashboard chart contracts (`WidgetBuilderDatasetContributionService`, `ManufacturingDashboardBlockService`, `ModuleSurfaceWidgetFactory`, `ModuleReportRegistryService`) are Manufacturing-owned contracts that Shell/Studio consume. These should stay Manufacturing-owned; they are Manufacturing Suite contracts, not Shared App contracts.

No evidence of Manufacturing UI directly embedded in Shell code or Shell importing Manufacturing controllers/services directly. Shell's adapter mechanism (`OperatorLayerAdapters`) consumes Manufacturing adapter contracts cleanly.

=== SUMMARY OF PROBLEMS ===
1. `products` table ownership ambiguity: Manufacturing owns manufacturing-specific columns; core product identity could be shared. Requires schema separation contract before any Product extraction.
2. `materials` / related table ownership ambiguity: MaterialManagement references these; unclear if Manufacturing-only or shared inventory domain.
3. No direct Manufacturing import of Parties — Manufacturing's party-related concepts (customer/demand/supplier references) are Manufacturing-owned, not Parties-derived. Shared App promotion for Parties requires Manufacturing to adopt Parties contracts (low coupling, safe); Manufacturing does not currently depend on Parties.
4. Manufacturing's core dependency on Products module: All Manufacturing core modules depend on Products; Products cannot be relocated without restructuring Manufacturing's core identity.
5. Manufacturing's workflow/stage governance (Workflow module) is tightly bound to Planning/Process modules; extraction would break Manufacturing lifecycle.
6. Manufacturing's module dependency DAG is clean (no cycles); operational lifecycle cycles are by design and not structural cycles.
