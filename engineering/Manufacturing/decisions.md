# Manufacturing — Decisions

## Decision Log

### 2026-10-15 — OH-AUTO1-088 machine projection consistency

**Status:** Accepted

**Decision**

`MachinesAdapter::projectScopedData()` consumes the actual owner response
shape: `scope.scope_machine_ids` and `scope.scope_part_ids`. Missing or
malformed scope is rejected rather than interpreted as unrestricted. The
normalized scope is used consistently for job rows, assigned machines,
filters, top-level sections, and count KPIs. Part-only restrictions narrow
machine choices to machines represented by authorized scoped records.

Quantity totals are published only when the owner response includes matching
`MachineLeaderDashboardService::fetchPlanRows` complete-window provenance.
Otherwise the adapter suppresses them as unavailable; it never sums truncated
queue slices as totals.

### 2026-10-15 — OH-AUTO1-087 machine scope handoff

**Status:** Accepted

**Decision**

The Shell operator composer passes the authenticated user to the existing
Manufacturing `MachinesAdapter`; it no longer invokes the machine dashboard
service with `null`. The adapter requires resolved Platform context, active
Manufacturing assignment, machine-module access, and a present scope object.
Missing identity or scope evidence fails closed. An existing resolved empty
machine/part scope retains its established authorized-unrestricted meaning.

`MachineLeaderDashboardService` remains the owner of scope-aware SQL and task
type behavior. Its machine filters and sections are narrowed to the effective
machine scope, and the adapter applies a pure post-projection guard so rows,
assigned-machine lists, filters, sections, and count KPIs cannot diverge.
Display composition calls the adapter with explicit `display` mode and no
operator identity; no operator permission is reused for display authorization.

**Consequences**

- Machine and part restrictions apply to current work, queues, delayed/QC
  records, filters, sections, and count KPIs.
- No new scope policy, route, schema, or business logic exists in Shell.
- The existing display surface remains read-only and preserves its prior
  display-owned data path.

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
