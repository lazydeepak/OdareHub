# OH-AUTO1-096 — Scope-Safe Work/Process Summary

**Status:** Complete — bounded read-only operator projection
**Date:** 2026-10-15
**Baseline:** `66e351d9ea2bc3beb02ff28af0955def33aa39e9`
**Owner:** Manufacturing projection; Shell composition/rendering

## Boundary

The dashboard contribution uses only the existing
`ProcessingOperationService::operatorFocus()` projection through
`ProcessingAdapter`. That projection applies existing part scope before its
Assembly/QC limits and emits exact `assembly_allowed` and `qc_allowed` lane
authority. `OperatorWorkProcessSummaryService` summarizes those returned rows;
it does not query the broad My Work queue or fetch a broader table and group
afterward.

Assembly and QC are included only when their existing authority flags are true.
Unauthorized rows, malformed rows, denied projections, and missing route
authority produce no leaked lane, count, record, or link. An authorized lane
with no rows is reported as an empty lane, distinct from unavailable/denied
data. Links remain same-user `/u/{username}/processing` destinations and are
emitted only after the existing GET route resolver approves the path.

The summary is computed only on the dashboard surface. Existing task/current/
upcoming work presentation remains unchanged.

## Presentation limits

The existing `MyWorkContributionService` area helpers are used only to add
optional presentation labels after lane authorization. They cannot create a
lane or authorize a record. Process-stage descriptions are intentionally
omitted: the selected Processing projection does not provide the required
product operational attributes, and this slice does not add a second product
query or invoke `StageTransitionService`.

Machine/production projections and the broad My Work queue are excluded because
their equivalent pre-limit scope contract was not established for this summary.
No new area permission, scope field, route, migration, or Core behavior was
introduced.

## Implementation

- `apps/Manufacturing/Services/OperatorWorkProcessSummaryService.php`
  provides the read-only summary and pure projection summarizer.
- `apps/Manufacturing/Services/OperatorLayerAdapters/WorkProcessSummaryAdapter.php`
  exposes the owner contribution to Shell as pure data.
- `apps/Shell/Composers/OperatorSurfaceComposer.php` requests the contribution
  only for the dashboard surface.
- `apps/Shell/Views/operator/dashboard.php` renders the compact secondary
  summary using existing generic operator widget primitives.
- `app/Locale/{en,ja,ne}.php` contains the summary labels and states.
- `apps/Manufacturing/Tests/probe_operator_work_process_summary.php` proves
  projection filtering, lane authority, empty/unavailable states, route
  confinement, and actual dashboard markup.

## Evidence

- Work/process summary proof: **23/23**
- Existing Processing scope/mutation proof: **35/35**
- Existing worker dashboard/task proof: **41/41**
- Existing role-shaped home proof: **26/26**
- Surface contribution contract gate: **PASS**
- Architecture gates: **49/49 PASS**
- Deletion family gates: **28/28 PASS**
- Deployment readiness: **PASS**
- PHP lint and `git diff --check`: **PASS**

No installed database, schema, browser, activation, deployment, production
operation, or Core modification was performed. Existing lifecycle logs and the
PHPUnit cache were preserved.
