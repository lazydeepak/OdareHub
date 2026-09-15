# Manufacturing — Decisions

## Decision Log

### 2026-09-15 — Workflow Services Composer Autoload Mapping (Scoped, Candidate A)
**Status:** Accepted (scoped loader/contract authorization; not yet committed)

**Context**
Manufacturing Workflow module declares `Plugins\Workflow\Services\*` classes under `apps/Manufacturing/modules/Workflow/Services/`, and six known consumers reference those FQCNs directly (DispatchEntries, ProductionPlans, QCEntries controllers, `apps/Manufacturing/Services/MyWorkContributionService.php`, `plugins/Base/Services/GovernanceInboxService.php`). Only one `Plugins\Workflow\Services\` class (`WorkflowGovernance`) had an `App\Core\WorkflowGovernance` compatibility bridge (documented as "Compatibility bridge only", zero runtime consumers), which also created a hidden load-order dependence for every other `Plugins\Workflow\Services\*` class.

**Decision**
Add exactly one PSR-4 mapping to `composer.json`:
`"Plugins\\Workflow\\Services\\": "apps/Manufacturing/modules/Workflow/Services/"`.
Workflow consumers already use `Plugins\Workflow\Services\*`; this compatibility bridge is not the primary runtime API. The mapping removes the hidden bridge/load-order dependence for Workflow Services only and does NOT authorize general migration of all `Plugins\` families; broader `Plugins\` loader policy remains a separate architecture decision.

**Consequences**
Removes hidden load-order dependence and enables cold Composer resolution of Workflow Services FQCNs. The `App\Core\WorkflowGovernance` bridge remains in place as legacy compatibility. No namespace promotion (`Plugins\{Module}` remains compatibility vocabulary per `docs/architecture/current-ownership-inventory.md` §5.1).

**Evidence**
Gate rule: `docs/architecture/current-ownership-inventory.md:82` (compatibility vocabulary must not be removed/normalized without loader/contract authorization and architecture approval — satisfied by scoped authorization; mapping is additive, nothing removed or normalized). Prior precedent: `Platform\` PSR-4 mapping added without separate ADR. `composer validate` PASS; acceptance tests AT-1..AT-6 recorded in task report.

### YYYY-MM-DD — Decision title
**Status:** Proposed / Accepted / Superseded

**Context**
Why the decision was needed.

**Decision**
What was decided.

**Consequences**
What this enables, constrains, or requires next.

**Evidence**
Relevant validation, source references, or implementation links.
