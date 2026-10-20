# OH-AUTO2-MFG-003 — Processing Operator Workflow Completeness Assessment

**Date:** 2026-10-20
**Baseline:** `6afa6eec0108c96e7690ebafab3475cccce17f09`
**Authorization:** Read-only source investigation and documentation only
**Status:** Assessment complete; one bounded usability correction identified

## 1. Boundary and method

This assessment traced the existing `/u/{username}/processing` operator
journey through Shell routing, the Manufacturing Processing adapter and
authority service, the operator Processing view, the four POST handlers, and
the existing isolated probes. It did not run a database, SQL, browser, HTTP,
activation, deployment, migration, or Material aggregate operation.

The assessment does not reopen the completed OH-AUTO1-093/OH-AUTO1-094 scope,
read-only, module-authority, or transition-hardening work. It records only a
user-facing gap demonstrated by the current source path.

## 2. Exact operator execution graph

### Read and presentation path

```text
GET /u/processing
  -> resolveOperatorGetRoute('processing', 'manufacturing')
  -> OperatorLayerService::render()
  -> OperatorSurfaceComposer::buildProcessingFocusData()
  -> ProcessingAdapter::getData()
  -> ProcessingOperationService::operatorFocus()
  -> operatorScope(user)
       -> active Manufacturing app
       -> exact Assembly/QC module authority
       -> normalized part_ids scope
       -> mutation eligibility from assignment evidence
  -> scoped Assembly/QC reads
  -> Shell operator Processing view
```

The projection returns separate `assembly_allowed`, `qc_allowed`, and
`can_mutate` flags. The view uses those flags before rendering lane navigation,
create forms, or row actions.

### Mutation path

```text
POST /u/processing/assembly/status
  -> resolveOperatorPostRoute('processing', false, 'manufacturing')
  -> CSRF validation
  -> exact target status validation ('approved')
  -> ProcessingOperationService::authorizeOperatorMutation('assembly_status', ...)
  -> direct update of the authorized demand record
  -> success/error flash
  -> normalized same-user Processing redirect

POST /u/processing/qc/status
  -> same route identity and CSRF boundary
  -> target validation ('verified by qc' or 'approved by authority')
  -> authorizeOperatorMutation('qc_status', ...)
  -> direct update of the authorized QC record
  -> success/error flash
  -> normalized same-user Processing redirect

POST /u/processing/assembly/create
  -> same route identity and CSRF boundary
  -> date/product/quantity validation
  -> authorizeOperatorMutation('assembly_create', ...)
  -> schema preparation and existing-record upsert
  -> success/error flash and date/product redirect

POST /u/processing/qc/create
  -> same route identity and CSRF boundary
  -> date/product/quantity validation
  -> authorizeOperatorMutation('qc_create', ...)
  -> QC plan insert
  -> success/error flash and date/product redirect
```

## 3. Journey classification

| Journey step | Classification | Evidence and result |
|---|---|---|
| Assigned Assembly/QC work appears | **COMPLETE** | `operatorFocus()` resolves scope before reads, projects only authorized lanes/rows, and `probe_operator_processing_scope.php` proves Assembly-only, QC-only, denied, malformed, and mixed-scope behavior. |
| Operator reaches Processing | **COMPLETE** | `/u/processing` uses the existing same-user operator GET resolver and renders the Manufacturing-owned Processing projection through the adapter. |
| Correct lane/action is presented | **COMPLETE** | Separate module flags and `can_mutate` suppress unauthorized navigation, forms, and row actions. |
| Authorized operator submits an existing action | **COMPLETE** | Four operator POST handlers use the Manufacturing authority service; CSRF and same-user redirect confinement are present. |
| Validation and lifecycle authority enforce the operation | **COMPLETE** | Exact Assembly/QC target statuses, record identity, part scope, module authority, and assignment-based mutation eligibility are enforced by existing checks. |
| Updated state is visible after submission | **DEFECTIVE** | The status handlers accept a caller-provided redirect path but the view supplies bare `/u/{user}/processing`, dropping the active date/product filter. The mutation flash appears, but the updated record can disappear from the selected result window. |
| Persistence atomicity and live runtime behavior | **BLOCKED** | No database or HTTP execution is authorized in this assessment. Existing source paths include schema preparation and direct writes; live transaction/concurrency behavior remains unverified and is not a basis for speculative correction here. |

## 4. Demonstrated functional gap

### Processing status action loses active filter context

**Observed source behavior**

The Processing view reads `processing_date` and `product_id` from the current
projection and uses them to render the selected work window. However, the
Assembly status forms set:

```text
redirect_to = /u/{username}/processing
```

The QC status forms do the same. The route's
`normalizeOperatorRedirect()` correctly confines the path to the same user's
operator surface, but it does not restore query parameters that were omitted
by the form.

The create forms already preserve `processing_date` and `product_id` in their
server-built redirect. The status forms are therefore inconsistent with the
same page's established context-preservation pattern.

**Severity:** Medium usability defect.

**User impact:** An operator working a selected date or part approves/verifies
a row, receives a success flash, and is returned to the default Processing
window rather than the window containing the acted-on row. The action is not
necessarily lost, but confirmation requires reselecting the prior context and
can make the workflow appear incomplete.

**Classification:** **DEFECTIVE**, with a small independently testable fix.

## 5. Completed capabilities and evidence

- Scoped GET projection: `probe_operator_processing_scope.php`, **35/35**.
- Work/process summary lane projection: `probe_operator_work_process_summary.php`,
  **23/23**.
- Exact Assembly and QC module authority is kept separate; no broader
  Processing token grants both lanes.
- Denied, read-only, display, missing, malformed, and mixed assignment evidence
  fails closed.
- Authorized Assembly worker retains Assembly actions only; authorized QC
  worker retains QC actions only.
- Status transitions are constrained to the existing approved Assembly and
  verified/approved QC destinations.
- Forms include CSRF tokens and remain within `/u/{username}/*`.
- Success and error flashes are already projected by the Processing composer.

These are existing contracts and evidence, not new implementation work in
MFG-003.

## 6. Smallest independently testable implementation candidate

In a subsequent authorized implementation assignment:

1. Build the status-form redirect from the already selected, server-rendered
   `processing_date` and `product_id` context, or submit an equivalent
   server-generated return URL.
2. Keep `normalizeOperatorRedirect()` as the final same-user confinement
   boundary.
3. Preserve the existing action, record ID, CSRF, authority call, status
   validation, flash behavior, and route paths.
4. Add an offline static/fixture proof that Assembly and QC status forms retain
   the selected context while malformed/external redirects still fall back
   safely.

No new permission, lifecycle status, service, database field, schema change, or
Shell business authority is required by this candidate.

## 7. Remaining blockers and decisions

- This assessment does not authorize the correction; a separate implementation
  assignment is required.
- Live persistence, transaction, and concurrency behavior remains unverified
  because database and HTTP execution are prohibited here.
- Assembly/QC execution-progress capture remains a separate broader product
  candidate. Existing Processing planning/status actions do not prove that a
  new floor execution-progress workflow is authorized or contract-complete.
- Fulfillment dispatch mutation and Material aggregate restoration remain
  blocked under their existing MFG-002 and OH-AUTO2-112 decisions.

## 8. Recommendation

**IMPLEMENT** the bounded status-redirect context-preservation correction in a
future separately authorized task. It is an independently testable usability
defect with existing authorization, lifecycle, CSRF, and route-confinement
contracts. Do not expand that task into Processing scope redesign, execution
progress capture, Fulfillment dispatch, Material restoration, or live database
verification.

OH-AUTO2-MFG-003 itself made no production-code changes.
