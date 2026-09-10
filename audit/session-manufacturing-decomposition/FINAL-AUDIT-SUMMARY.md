# MANUFACTURING SHARED-VS-SUITE DECOMPOSITION AUDIT — SESSION C SUMMARY

Audit performed: READ-ONLY ONLY. No source code modified. No commits. No pushes.
Audit workspace: audit/session-manufacturing-decomposition/
Audit files produced:
- 01-inventory.md (manufacturing module/app inventory)
- 02-dependency-graph.md (internal/external dependency tracing)
- 03-domain-ownership.md (entity/concept ownership per domain)
- 04-classification.md (shared/suite/core/extension classification with criteria)
- 05-extraction-difficulty.md (coupling, risk, DB, API, UI, consumer, order scores)
- 06-dependency-problems.md (cycles, cross-domain writes, ambiguous ownership, duplicate entities, import patterns, UI coupling)
- 07-architecture-recommendation.md (proposed structure, why not promote Products now, why Parties is best first)
- 08-extraction-sequence.md (ordered sequence; first candidate: Parties; explanation of lower-risk/higher-value vs alternatives)
- 09-final-report.md (this file — synthesis)

=== KEY FINDINGS ===

1. Manufacturing bundle owns 15 controllers, 23+ services, 18 module plugins (business_entity/planning/process_execution/dashboard/service), 40+ canonical routes, 5 native modules, 15 app-level services, and extensive operator/admin/display contributions.
2. Manufacturing does NOT import Parties directly (verified by grep — 0 hits). Manufacturing operates independently from Studio (confirmed by AGENTS.md rules; no Studio runtime dependency). Manufacturing uses Platform contracts (`platform_user_context_contract`, `platform_user_access_policy_contract`) correctly; no direct Platform business dependency.
3. Products (catalog master) is Manufacturing's strongest shared-candidate concept, BUT Manufacturing core depends on it absolutely (all modules directly/transitively require Products; 11 ALTER migrations on `products` embed manufacturing-specific fields). Classification: Manufacturing Core — NOT Shared App in this audit phase. Shared master contract possible only with schema separation.
4. Machines (machine registry) is Manufacturing-specific; no cross-suite consumer evidence found.
5. MaterialManagement references external `materials` / `material_ledger` / `material_orders` / etc. (declared `required_tables`); ownership ambiguous — requires clarification before any shared inventory promotion.
6. Coverage Analytics / Demand Workspace are Manufacturing-specific analytics (dashboard_only / service modules); safe as Manufacturing Extensions, not Shared Apps.
7. Workflow / Governance is Manufacturing lifecycle engine; tightly bound to Planning/Process; Manufacturing Core only.
8. Label Design (manufacturing product label context/template) is Manufacturing Extension; label rendering pipeline is Platform-level.
9. Parties is the safest first extraction (confirmed by repository direction, zero Manufacturing import, cross-suite party/customer/supplier master, low restructuring risk).

=== FIRST EXTRACTION RECOMMENDED ===
Candidate: Parties (Shared App contract adoption / boundary clarification)
Why lower-risk than alternatives: Manufacturing operates independently today; adoption is additive; user direction confirms; no Manufacturing restructuring needed; no database migration; contract adoption clarifies domain boundary safely.
Why higher-value: Cross-suite party/customer/supplier master; establishes shared contract framework; supports Manufacturing's implicit party references (DailyOrders/PreOrders/MaterialManagement) through stable contracts rather than implicit duplication.
Not selected: Products (core identity dependency; 11 ALTER migrations; breaks Manufacturing if relocated); MaterialManagement (ambiguous external table ownership; complex restructuring); Machines (Manufacturing-specific); Workflow (core lifecycle).

=== WHAT WAS NOT CHANGED ===
- Zero PHP files edited in apps/Manufacturing/ or elsewhere.
- Zero database migrations created/deleted/modified.
- Zero routes added/removed.
- Zero controllers/services/views changed.
- Zero manifest/plugin contracts edited.
- Zero git commits or pushes.
- Zero file moves or directory renames.

=== NEXT RECOMMENDATION (NOT EXECUTED) ===
1. Establish Parties contract boundary (add optional dependency in Manufacturing manifest; document contract reference points in Manufacturing services controlling demand/order/material references).
2. Design schema separation contract if Product Catalog Master promotion is pursued in future (separate core identity fields from Manufacturing-specific extensions; establish Platform-level catalog contract).
3. Confirm external inventory/supply table ownership (`required_tables` in MaterialManagement) before any Material shared master promotion.
4. Lock Manufacturing Suite Core contract (routes, permissions, module dependency rules, DB ownership) before any Manufacturing restructuring.
5. Lock Manufacturing Suite Extension rules (Coverage, Supply, Label Context) with independent enablement documentation.
