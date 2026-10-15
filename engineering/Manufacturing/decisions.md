# Manufacturing — Decisions

## Decision Log

### 2026-10-16 — OH-AUTO1-097 restricted-scope My Work containment

**Status:** Accepted — fail-closed containment without Core changes

The broad My Work projection must not expose records or derived counts to a
user with non-empty machine, part, task-type, department, branch, or ownership
scope because the selected shared sources do not apply those predicates before
their limits. Manufacturing now contains those scopes before handoff,
approval, or operational queue collection and returns the existing payload
shape with explicit unavailable containment state.

Malformed scope and the established denied sentinel also fail closed. An
explicitly empty, structurally valid scope retains its existing owner-defined
unrestricted meaning. This is containment, not a replacement authorization
system and not post-limit filtering.

The fix remains in `apps/Manufacturing`; Core `MyWorkEntityQuery`, the shared
adapter, Base context resolution, routes, schema, and scope fields are
unchanged. The existing transitive Base `ensureSchema()` dependency remains
outside this milestone.

**Evidence:** restricted-scope probe **21/21**; work/process summary **23/23**;
Processing **35/35**; Parts **14/14**; machine projection **27/27**.

### 2026-10-15 — OH-AUTO1-096 scope-safe work/process summary

**Status:** Accepted — bounded Assembly/QC dashboard contribution

**Decision**

The operator dashboard may show a compact Manufacturing work/process summary
only from the existing `ProcessingAdapter` projection. Assembly and QC lanes
are included only from that projection's exact authority flags and already
scope-filtered rows. The summary does not consume the broad My Work queue,
query broader records, or treat area labels as authorization.

Same-user Processing links are checked through the existing operator GET route
authority; a missing or denied resolver omits the link. An authorized empty
lane remains visible as empty, while denied or unavailable evidence produces no
lane or count. Area labels remain presentation-only.

Process-stage descriptions, machine/production summaries, new area fields,
permissions, routes, migrations, Core changes, and database/browser operations
remain outside this milestone because their required authority or product
attribute contracts were not established.

**Evidence**

The isolated summary/render proof passes **23/23**. Existing Processing,
worker-task, and role-home proofs pass **35/35**, **41/41**, and **26/26**.

### 2026-10-15 — OH-AUTO1-095 work/process-area authority inventory

**Status:** Accepted as inventory and implementation boundary

**Decision**

Manufacturing does not currently have an independent authoritative
`work_area` or `process_area` scope dimension. Existing authorization is based
on resolved assignment evidence, active app/module authority, machine/part/task
operational scope, existing permissions/duties, and owner-specific record and
transition checks.

`MyWorkContributionService::primaryWorkArea()`, `crossWorkAreas()`, and
`workAreaForItem()` remain presentation classifications. They must not grant
record visibility, action access, or mutation authority. Product stage paths
and handoff stages remain workflow classification, not user scope.

The broad shared My Work queue is not accepted as a scope-safe source for a
future area/process projection because its current runtime-backed and legacy
queries do not consistently apply resolved machine/part predicates before
limits. The Core query path remains unchanged and outside this milestone.

**Consequence**

The next bounded milestone may expose computed area/process presentation only
from already-authorized Manufacturing projections and existing route authority.
It must not add new area fields, permission catalogs, migrations, Core changes,
or infer authority from labels. The proposed boundary is recorded as
OH-AUTO1-096 in
`docs/architecture/oh-auto1-095-work-process-area-authority-inventory.md`.

**Evidence**

The inventory cites the Base assignment/scope resolver, operational-scope
migration, Manufacturing Processing/Production/Machines boundaries, operator
contribution service, stage transition contract, and shared My Work query paths.

### 2026-10-15 — OH-AUTO1-094 Processing authority closure

**Status:** Accepted

**Decision**

Processing remains a mixed operator surface, but Assembly and QC are separate
existing module authorities. Manufacturing now grants the Assembly lane only
from the exact `assembly` module token and the QC lane only from the exact
`qc` token; `processing` and `processing_operation` are not aliases for either
lane. Unauthorized lanes are not queried or projected, and both lane authority
flags are handed to Shell for action rendering.

The existing interaction profile remains mutation authority at POST time.
`read_only` and `display` profiles fail inside the Processing authority gate
before Processing record lookup or writes for both create and status
operations. The existing Assembly schema preparation remains after this
authority gate and is not moved or broadened. However, the caller-owned Base
`UserDashboardAssignmentService::resolveUserContext()` invokes its existing
`ensureSchema()` before returning context, so this milestone does not claim
that all transitive schema initialization occurs after authorization. That
cross-owner dependency remains unresolved and unchanged. No new permission
framework or scope dimension was introduced.

**Evidence**

The isolated Processing proof passes **35/35**, including authoritative
`readonly_observer`, `materials_readonly`, `read_only` duty, display, mixed,
missing, and malformed evidence; Assembly-only/QC-only workers; neither-
module, mixed-record, both read-only status denials, exact lane authority, all
four POST authority calls, and GET/POST mutation-eligibility consistency. The
proof does not inject `interaction_profile`; Processing derives mutation
eligibility from the existing resolved assignment evidence and fails closed
when positive authority evidence is absent or malformed.

### 2026-10-15 — OH-AUTO1-093 scope-aware Processing workspace and mutation gates

**Status:** Accepted

**Decision**

Manufacturing owns the operator Processing projection and authority. The
operator adapter delegates to `ProcessingOperationService::operatorFocus()`;
it requires authenticated Manufacturing assignment, existing Platform module
access, and strict positive `part_ids` scope before querying products, assembly
demands, or QC plans. Scope predicates are applied before ordering and limits.

The four existing operator POST handlers call
`authorizeOperatorMutation()` before any schema initializer or write. Creation
checks the requested product; status changes resolve the target plan's product
and current status, then preserve only the existing assembly and QC
transitions. `read_only`/`display` profiles, denied/malformed/mixed scopes,
out-of-scope products, and unauthorized modules fail closed. No new scope
dimension or permission framework was introduced.

### 2026-10-15 — OH-AUTO1-092 non-mutating Part360 operator read

**Status:** Accepted

**Decision**

`Part360Service::build()` remains the existing admin entry point and retains
schema initialization. A shared private calculation path now supports the
owner-controlled `buildReadOnly()` entry point, which skips both schema
initializers and reuses the existing Part360 projection logic. The operator
detail contribution calls only this read-only entry point.

The isolated fake DB proves the authorized projection executes no mutation
method and emits only SELECT/SHOW reads. Missing `part_molds` schema fails as
an unavailable read and does not invoke repair. The transitive
`PartExecutionRouteResolver` is pure; no Part360 downstream service contains
write SQL outside the skipped initializers.

### 2026-10-15 — OH-AUTO1-091 detail authority and read-only boundary

**Status:** Accepted with blocker

**Decision**

Manufacturing continues to declare Part-detail business actions, while Shell
filters those actions before rendering through same-user `/u/{username}/*`
normalization and the existing Platform GET route authority. Inaccessible,
cross-user, malformed, or read-only-profile actions are omitted; part access
does not imply mutation permission.

The isolated fake-row provider shares strict selector reconciliation with the
runtime boundary. Conflicting direct IDs, mixed direct/text selectors, array or
malformed selectors, unauthorized search/part-number matches, and denied scope
all fail closed.

The authorized Part360 path remains explicitly non-certified as read-only:
`Part360Service::build()` calls both schema initializers before its reads, and
those initializers can execute CREATE TABLE and ALTER TABLE operations. This
milestone does not modify that broad service or introduce a second data engine.

### 2026-10-15 — OH-AUTO1-090 operator Parts scope boundary

**Status:** Accepted

**Decision**

Manufacturing owns the operator Parts selection boundary through
`OperatorPartsWorkspaceService`. It requires authenticated identity, active
Manufacturing assignment, enabled app state, Products module access, and a
present scope object. Part restrictions are applied in the SQL predicate before
the list `LIMIT`; direct IDs, part numbers, and search selection use the same
authorized predicate.

`OperatorPartDetailContributionService` resolves an authorized product before
calling `Part360Service::build()`. Unauthorized or unavailable products return
the existing empty detail state, so Part360's schema initialization is never
reached for them. The operator read path itself adds no schema initialization.

Explicit empty `part_ids` remains the established authorized-unrestricted case;
missing, malformed, zero, negative, or denied scope fails closed. Shell only
passes authenticated context and renders the owner-provided projection.

### 2026-10-15 — OH-AUTO1-089 fail-closed scope normalization

**Status:** Accepted

**Decision**

`MachinesAdapter` accepts only explicit empty arrays or canonical positive
machine/part IDs in the owner scope response. Integer casts are not used to
interpret scope tokens: alphabetic, suffixed, zero, negative, mixed, and
contradictory values fail closed. The existing owner `[-1]` sentinel is
recognized as denied scope; denied scope publishes no operator rows, machine
choices, sections, or derived work KPIs.

Quantity provenance uses the same strict normalization. A provenance record
with malformed or denied restrictions cannot authorize quantity totals, even
when the main scope is valid. Explicit empty restrictions remain the existing
authorized-unrestricted case only when the owner provides them as valid arrays.

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
