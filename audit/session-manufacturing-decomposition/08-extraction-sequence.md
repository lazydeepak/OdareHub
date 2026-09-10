# Extraction Sequence — Manufacturing Decomposition Audit

=== FIRST EXTRACTION CANDIDATE: Parties (Shared App Adoption / Contract Boundary)

Why Parties is lower-risk and higher-value than alternatives:
- Manufacturing has NO direct code dependency on Parties today (verified by grep; zero references to App\Parties or Plugins\Parties in Manufacturing PHP files). This means adoption is additive, not restructuring.
- Parties is already recognized by repository direction (user instruction references Parties as shared candidate covering customers, guests, suppliers). The audit confirms Manufacturing's independence supports this.
- Manufacturing's party-related concepts are implicit (demand/customer through DailyOrders/PreOrders; supplier/concept through MaterialManagement) rather than explicit module-level contracts. Adding a Parties contract boundary would clarify Manufacturing's domain without removing Manufacturing logic.
- No database ownership conflict: Manufacturing does not share party tables directly (no Manufacturing table references Parties tables; Manufacturing owns demand/order/material tables independently).
- No UI restructuring needed: Manufacturing surfaces reference domain concepts through Manufacturing-owned controllers/services; adopting a Parties contract does not change Manufacturing route structure or module contracts.
- Migration risk: LOW. Expected work: add optional Parties dependency to Manufacturing manifest (optional), establish contract reference points in Manufacturing services (MaterialAccessService / DailyOrderService / CoverageController / DemandEngine) where party/customer/supplier references exist conceptually, and document contract usage. No file moves, no route deletions, no database migrations required for Manufacturing.
- Higher-value alternatives rejected:
  - Products (catalog master): Manufacturing core dependency is absolute; all Manufacturing modules depend on Products. Extraction would break Manufacturing core identity. Not safe first candidate.
  - MaterialManagement (material planning): Module owns major planning/business surfaces; unclear external table ownership (`required_tables` references); high restructuring risk. Not safe first candidate.
  - Coverage Analytics: Independent analytics module; could be Manufacturing Extension, not Shared App. Not cross-suite value.
  - Workflow Governance: Essential Manufacturing lifecycle engine; tightly bound to Planning/Process modules. Not safe first candidate.
  - Label Design / Pipeline: Manufacturing label context is Manufacturing-specific; platform-level pipeline is separate domain. Not first candidate.

=== RECOMMENDED ORDERED SEQUENCE (CONCEPTUAL — NO EXECUTION AUTHORIZED)

1. Parties — Shared App adoption / contract boundary (lowest risk, cross-suite value, confirms existing direction).
2. Schema/contract design — Separate Manufacturing-specific product extensions (`production_source`, `requires_qc`, etc.) from core product identity contract if Product shared master is pursued in future. Not a file move; design contract only.
3. Manufacturing Suite Core definition — Lock core modules: Products, Machines, MaterialManagement, ProductionPlans, ProductionEntries, ProductionQueue, DailyOrders, PreOrders, QCPlans, QCEntries, AssemblyPlans, AssemblyEntries, DispatchEntries, Workflow, Ledger. Define Manufacturing Suite contract (routes, permissions, services, database ownership, module dependency rules).
4. Manufacturing Suite Extensions — Confirm Coverage (analytics/dashboard), Supply (minimal service), Label Context (product label design) as Manufacturing Extensions. Document independent enablement rules.
5. Manufacturing Core — Confirm Products, Workflow, Planning/Process modules must stay Manufacturing Core; document extraction prohibition and dependency justification.
6. Cross-suite contract design — If future shared master contracts (Product Catalog Master, Material Inventory Master, Machine Registry) are pursued, design Platform-level contracts before Manufacturing restructuring. Not authorized in this audit.

=== FIRST EXTRACTION — DETAILS FOR PARTIES ===

Candidate: Parties (Shared App)
Why shared: Covers cross-suite concepts (customers, guests, suppliers) recognized by existing repository direction; Manufacturing operates independently (no direct import); Manufacturing's demand/order/material surfaces reference party concepts implicitly; adding contract boundary clarifies domain without restructuring Manufacturing logic.
Current coupling: LOW (zero Manufacturing code imports). Manufacturing's party-related concepts are implicit (DailyOrders/PreOrders for demand/customer; MaterialManagement for supplier/concept; Coverage/DemandEngine for demand analytics).
Extraction difficulty: LOW — no Manufacturing file restructuring needed; contract adoption is additive.
Required contract: Parties must expose stable contracts (customer identity, supplier identity, party role/access) that Manufacturing can reference optionally; Manufacturing should maintain its own domain-specific extensions (demand orders, pre-orders, material orders, coverage analytics) independently.
Recommended order: FIRST (before any Manufacturing restructuring or Product/Material analysis).

=== WHAT IS NOT IN THIS SEQUENCE ===
- No Product extraction (catalog master) in Phase 1 — too high dependency, Manufacturing core identity depends on it.
- No MaterialManagement extraction — unclear external table ownership, high restructuring risk.
- No Workflow extraction — Manufacturing lifecycle engine; must stay Manufacturing core.
- No Coverage/Analytics extraction as Shared App — Manufacturing-specific analytics; no cross-suite consumer evidence.
- No Label Pipeline extraction — Manufacturing label context stays Manufacturing Extension; platform-level rendering is separate.
