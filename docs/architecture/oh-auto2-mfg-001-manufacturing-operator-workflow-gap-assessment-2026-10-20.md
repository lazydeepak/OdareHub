# OH-AUTO2-MFG-001 — Manufacturing Operator Workflow Gap Assessment

**Date:** 2026-10-20
**Baseline:** `5835e25d60b58cc3fd597e8727547181da827cdd`
**Authorization:** Read-only source investigation and documentation only
**Status:** Assessment complete; implementation not authorized

## 1. Boundary and method

This assessment inspected Manufacturing workspace records, the Manufacturing
route/catalog surface, Shell `/u/*` handoff routes, Manufacturing operator
views, owner adapters/services, and existing isolated probes. It did not run a
database, SQL, browser, HTTP, activation, deployment, migration, or Material
aggregate operation. The unrelated Procurement logs, backup, and PHPUnit
cache were preserved.

The assessment distinguishes:

- a completed authorization/read-safety boundary from a user-facing product
  capability;
- a monitoring/read-only surface from a missing mutation workflow; and
- a source gap from the fact that Manufacturing is disabled in the current
  runtime environment.

## 2. Existing operator workflow map

| Surface | Current capability | Evidence |
|---|---|---|
| Dashboard / work-process summary | Scope-safe Assembly/QC lanes and same-user Processing destinations | `OperatorWorkProcessSummaryService`; `probe_operator_work_process_summary.php` |
| Parts / Part detail | Scoped list, strict selection, read-only detail projection | `OperatorPartsWorkspaceService`, `OperatorPartDetailContributionService`; Parts `14/14`, Part360 `8/8` |
| Production | Create plan; start and complete plan status; coverage and queue views | `production.php`; `/u/production/plan/create`, `/u/production/plan/status` |
| Processing | Create Assembly/QC plans; approve Assembly; verify/approve QC | `processing.php`; four `/u/processing/*` POST routes; Processing `35/35` |
| Fulfillment / Preparation | Create preparation bundle from ready quantities; list bundles | `fulfillment.php`; `/u/preparation/bundle/create` |
| Fulfillment / Dispatch | Create dispatch entry; list dispatch queue | `fulfillment.php`; `/u/dispatch/create` |
| Machines | Scoped run-now, queue, delayed, QC-waiting monitoring and KPIs | `MachinesAdapter`; machine projection `27/27` and related scope proofs |
| Assembly | Read-only assembly entry/KPI view | `AssemblyAdapter`, `assembly.php` |
| QC | Read-only QC queue/failed-recheck/ready-dispatch monitoring view | `QcAdapter`, `qc.php` |
| Orders / Demand / Coverage | Filtered read-only operational views and same-user part links | corresponding operator views/adapters |
| Materials | Existing operator read surface remains separately bounded; aggregate `/me` summary is suppressed | OH-AUTO2-111/112; restoration blocked |
| Shift handoff | Write note, acknowledge own/other notes; admin handoff board has owner assignment/escalation | `handoff.php`, `HandoffBoardService` |

## 3. Findings

### Completed safeguards are not new gaps

The following are complete and must not be reissued as product work:

- Parts identity, list/detail selection, scope filtering, and read-only
  Part360 boundary (`probe_operator_parts_scope.php`, `probe_part360_read_only.php`).
- Processing pre-query scope, separate Assembly/QC authority, mutation
  authorization, read-only/display denial, and transition checks
  (`probe_operator_processing_scope.php`).
- Machine projection scope consistency, malformed-scope fail-closed behavior,
  quantity provenance, and dashboard summary containment.
- Restricted My Work and Material aggregate admission/read-safety containment.

Manufacturing's disabled runtime is therefore not evidence of a source defect.

### Evidence-backed functional gaps

#### Gap A — Operator dispatch lifecycle stops at creation

The operator fulfillment surface exposes creation and queue display, but no
operator route or form for the existing dispatch lifecycle operations
`DispatchOpsService::markReady()`, `prepare()`, or `complete()`. The operator
create handler writes a dispatch row with `dispatch_status = Ready`,
`approval_status = Draft`, and `completion_status = draft`; the operator UI
then only renders those statuses. The canonical service already contains the
next lifecycle methods, while the admin DispatchOps controller exposes a
separate completion path.

This is a genuine product-completeness gap, not an authorization defect. It is
independently shapeable as a bounded operator workflow, but requires an
owner-approved dispatch record-scope and transition contract before runtime
writes are enabled.

#### Gap B — Operator execution progress is not available on machine/assembly views

The operator Machines and Assembly views are monitoring-only. The existing
`ProcessingOperationService::updateAssemblyProgress()` and
`updateQcProgress()` methods demonstrate an execution vocabulary and write
model, but no `/u/*` route or operator form connects execution progress to the
machine/assembly operator surfaces. This could be valuable for floor users,
but it is broader than Gap A because it needs a complete execution identity,
machine/part scope, quantity, retry, and transaction contract.

This remains a candidate, not an authorized implementation scope.

#### Gap C — Handoff ownership is split between operator notes and admin board

The operator handoff surface supports note creation and acknowledgment. The
owner assignment/escalation methods exist in `HandoffBoardService`, but are
exposed by the admin handoff board rather than the operator workflow. A
floor-level “accept/assign this handoff” action might improve ownership
continuity, but the inspected source does not establish that operators should
own assignment decisions. This is lower-confidence product work and should
not be implemented without an explicit role/ownership decision.

## 4. Candidate ranking

| Rank | Candidate | User value | Risk/dependencies | Offline testability |
|---|---|---|---|---|
| 1 | Operator dispatch lifecycle continuation: prepare/mark-ready/complete from the unified Fulfillment surface | High; closes the visible create-to-complete loop | Medium-high; requires dispatch scope, transition authority, CSRF, audit/transaction behavior, and migration-owned storage confirmation | High for pure authorization/state-machine probes and fake service tests; live DB remains separately required |
| 2 | Machine/assembly execution progress capture | High for floor execution and quantity truth | High; requires machine/part identity, quantity semantics, duplicate/retry behavior, and cross-stage transaction rules | Medium; substantial fake-provider proof is possible, but production behavior needs isolated DB evidence |
| 3 | Operator handoff ownership/acceptance | Medium; may reduce unowned queue work | High uncertainty; role ownership and relationship to admin assignment are not established | Medium-low until product ownership is decided |

## 5. Recommendation

Recommend **Gap A** as the next product-design/implementation candidate:

> Define and implement a bounded operator dispatch lifecycle continuation on
> the existing unified `/u/{username}/fulfillment?tab=dispatch` surface, using
> the existing DispatchOps service vocabulary and preserving the operator
> identity, CSRF, route confinement, and record-scope boundaries.

This recommendation is **not implementation authorization**. Before coding,
the owner must approve:

1. which operator profiles may mark ready, prepare, or complete;
2. exact allowed source and target statuses;
3. dispatch-entry and product/destination scope evidence;
4. transaction and audit requirements; and
5. an isolated fake/fixture proof plus separately authorized disposable DB
   verification for actual persistence behavior.

No implementation is recommended for the Material aggregate. No Core, Shell,
schema, migration, activation, or unrelated-owner work is implied.

## 6. Evidence index

- Routes: `apps/Shell/routes.php:864-1058`, `:1512-1705`, `:1856-1890`.
- Operator views: `apps/Manufacturing/Views/operator/production.php`,
  `processing.php`, `fulfillment.php`, `assembly.php`, `qc.php`,
  `handoff.php`.
- Dispatch service: `apps/Manufacturing/Services/DispatchOpsService.php:224-321`.
- Processing service: `apps/Manufacturing/Services/ProcessingOperationService.php:1-260`.
- Existing scope evidence: `apps/Manufacturing/Tests/probe_operator_parts_scope.php`,
  `probe_operator_processing_scope.php`,
  `probe_operator_work_process_summary.php`,
  `probe_machine_scope_projection.php`.
- Blocked Material decision:
  `docs/architecture/oh-auto2-112-manufacturing-record-scope-authority-query-contract-2026-10-16.md`.

## 7. Assessment outcome

OH-AUTO2-MFG-001 is complete as a read-only assessment. One concrete product
candidate is justified (dispatch lifecycle continuation), two lower-ranked
candidates are recorded with explicit uncertainty, and no production change
was made.
