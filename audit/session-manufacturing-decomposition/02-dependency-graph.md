# Manufacturing Dependency Graph — Read-Only Audit

## Internal Manufacturing Dependencies (Module Plugin Contracts)
Based on plugin.json `requires` and `optional` fields:

- Products → Base
- Machines → Base
- MaterialManagement → Base (optional: ACL, Audit)
- Coverage → Base (optional: ACL, Audit)
- DailyOrders → Base + Products
- ProductionPlans → Base + Products (contract shows production_plans surface)
- ProductionEntries → Base + Products
- PartMachineMap → Base + Products + Machines
- PreOrders → Base + Products
- AssemblyPlans → Base + Products + DailyOrders + ProductionEntries
- AssemblyEntries → Base + Products + AssemblyPlans
- QCPlans → Base
- QCEntries → Base (implicitly linked to workflow module)
- DispatchEntries → Base + Products + DailyOrders + ProductionPlans + ProductionEntries + QCEntries
- Ledger → Base + Products
- Supply (minimal) → Base
- Workflow (service) → Base; provides workflow governance referenced by Planning/Process modules

Dependency depth (longest chain):
Base → Products → DailyOrders → AssemblyPlans → AssemblyEntries
Base → Products → ProductionEntries → ProductionPlans → DispatchEntries
Base → Products → ProductionEntries → QCEntries (indirect)

## External Dependencies (Code / Runtime / DB Ownership)
- Shell: Manufacturing uses Shell's operator/admin/display surfaces extensively (HostSurfaceContributionService, OperatorSurfaceContributionService, adapter contracts, sidebar/focus/actions/breadcrumbs/contributions). No direct Shell import of Manufacturing classes found; Manufacturing provides adapters and contributions to Shell.
- Platform: Manufacturing controllers/services reference `platform_user_context_contract()` and `platform_user_access_policy_contract()` extensively. This is a runtime/platform service dependency (not compile-time code import of business logic). Manufacturing also uses Platform search, registry contracts.
- Parties: No direct reference to Parties in Manufacturing code (verified by grep; zero hits for App\Parties or Plugins\Parties). Manufacturing owns its own supplier/customer/domain party concepts implicitly through module contracts, but does not import Parties module.
- Studio: Manufacturing does not depend on Studio at runtime (as per AGENTS.md rules). Studio customization references Manufacturing artifacts (theme, customization studio, label design, etc.) through Studio-owned services that reference Manufacturing module paths; Manufacturing does not import Studio.
- Items/Catalog: Manufacturing's Products module defines product catalog concepts (`products` table). There is no separate Catalog/Items module dependency declared. Products module provides `catalog_snapshot` reporting. No external Items module is required by Manufacturing.
- Procurement: No direct Manufacturing dependency found (Procurement is a separate app). No code imports of Procurement services found in Manufacturing.
- Inventory/Stock: Manufacturing's Ledger (`stock_ledger_entries`) and MaterialManagement (`material_ledger` / `stock` references) handle inventory concepts, but these are Manufacturing-owned. No external Inventory app dependency declared.

## Cross-Domain Writes / Shared Table Access
- Manufacturing writes to `mfg_part_demands`, `mfg_stage_readiness`, `mfg_assembly_entries`, `mfg_upstream_generation_links`, `mfg_production_plan_candidates`, `mfg_procurement_demands` (Manufacturing-only tables).
- Manufacturing reads and modifies `products` (altered extensively by Products module; this is Manufacturing-owned for manufacturing domain, but Products serves as the master catalog).
- Manufacturing reads/references `machines` (Manufacturing-owned machine master).
- Manufacturing references `dispatch_entries`, `production_plans`, `production_entries`, `qc_entries` (all owned by Manufacturing modules or Manufacturing-controlled contracts).
- MaterialManagement references external `materials`, `part_material_map`, `material_ledger`, `material_orders`, `material_capacity`, `material_reservations` tables — these are Manufacturing-domain but may be shared if MaterialManagement is considered cross-suite.
- Ledger references `stock_ledger_entries` (Manufacturing-owned) and `qr_stock_updates` (external/update table, possibly shared or core-managed).

## Cycle / Duplication Evidence
- Module dependency chains show no cycles in plugin contracts (DAG verified by top-level review).
- However, some operational cycles exist implicitly: ProductionEntries feeds ProductionPlans (through dependency chain), and ProductionPlans feeds ProductionQueue; execution creates new entries; stages transition; handoffs track across stages. This is workflow cycle by design, not an architectural cycle that blocks extraction.
- Duplicated entity concepts detected:
  - `products` serves as catalog master; Manufacturing's Products module extends it with manufacturing-specific fields (`production_source`, `requires_qc`, `requires_assembly`, `dispatch_as_is`, etc.). This indicates Manufacturing depends on a shared product master that it extends locally.
  - `machines` is Manufacturing-specific machine master.
  - `material_ledger` / `materials` are Manufacturing-specific supply/inventory concepts.
- No direct cycle detected in database ownership at module contract level.
