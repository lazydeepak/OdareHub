# OH-AUTO1-095 — Work-Area and Process-Area Authority Inventory

**Project:** OdareHub
**Status:** Complete — read-only repository inventory
**Date:** 2026-10-15
**Baseline:** `846cbe330a47855c667abbcb23696feeafc39c81`
**Owner:** Manufacturing, with Platform/Base authority inputs

## 1. Executive finding

The repository has enough existing evidence to support a bounded, read-only
operator area summary, but it does **not** have an authoritative independent
`work_area` or `process_area` authorization dimension.

The existing authority model is:

```text
resolved assignment context
  -> assigned app/module visibility and route authority
  -> machine/part/task-type operational scope
  -> Manufacturing-owned projections and action checks
```

The existing `MyWorkContributionService` area methods are presentation
classification helpers:

- `primaryWorkArea()` maps the resolved operational role to a label;
- `crossWorkAreas()` maps cross-functional access bundle names to labels;
- `workAreaForItem()` maps entity types to labels.

None of those methods grants data access, narrows SQL, or authorizes an
action. They must not be treated as security scope.

The next milestone can therefore expose computed area/process presentation
only when it is derived from already-authorized Manufacturing records and
existing route/module authority. It must not add new scope fields, infer
authority from a label, or consume the current broad My Work queue as if it
were already scope-safe.

## 2. Inventory boundary and evidence method

This was a read-only source and migration inventory. It did not connect to an
installed database, execute SQL, run a browser, activate a module, modify
runtime code, or inspect production state.

Primary evidence paths:

- `plugins/Base/migrations/004_dashboard_assignment_model.sql`
- `plugins/Base/Services/UserDashboardAssignmentService.php`
- `plugins/Base/migrations/008_user_app_roles.sql`
- `apps/Manufacturing/Services/MyWorkContributionService.php`
- `apps/Manufacturing/Services/OperatorSurfaceContributionService.php`
- `apps/Manufacturing/Services/ProcessingOperationService.php`
- `apps/Manufacturing/Services/ProductionOperationService.php`
- `apps/Manufacturing/Services/StageTransitionService.php`
- `apps/Manufacturing/Services/HandoffTrackingContributionService.php`
- `app/Core/MyWorkEntityQuery.php`
- `app/Core/MyWorkEntityAdapter.php`
- `apps/Manufacturing/migrations/001_mfg_operational_model.sql`
- `apps/Manufacturing/migrations/002_mfg_stage_transitions.sql`
- `apps/Manufacturing/migrations/003_mfg_assembly_execution.sql`

The Core files were inspected only. Core remains locked and no Core change is
recommended by this inventory.

## 3. Existing contract and schema inventory

### 3.1 Resolved assignment context — Platform/Base input

`UserDashboardAssignmentService::resolveUserContext()` is the existing
assignment-context resolver. It reads the user, dashboard assignment, role
duties, per-app operational roles, and operational scope, then composes an
effective context.

The resolved context includes, among other values:

| Evidence | Source/normalization | Meaning | Authority classification |
|---|---|---|---|
| `authority_role` | account/role normalization | platform/app/operator authority class | Security/authority input |
| `assigned_apps`, `active_assigned_apps` | assignment/profile composition plus enabled-app filtering | application assignment | Security/authority input |
| `access_profiles`, `selected_role_packs` | role-pack/profile registry and context normalization | capability/profile evidence | Security/authority input |
| `permissions` | account, profile, cross-functional, assignment, baseline composition | operation permission evidence | Security/authority input |
| `view_access`, `table_access`, `chart_access` | assignment/profile composition | authorized surface/data categories | Security/authority input |
| `module_visibility` | profile, cross-functional, explicit module visibility, active-app filtering | module availability | Security/authority input |
| `duty_codes` | account/profile/cross-functional/assignment composition | workflow duty evidence | Security/workflow input |
| `account_class`, `dashboard_type` | normalized assignment and role evidence | account and experience shape | Security/experience input; not a data scope by itself |
| `app_roles` | `user_app_roles` | per-app operational role | Operational role input; not a standalone record scope |
| `cross_functional_access` | parsed known bundles and levels | cross-functional capability grants | Security/capability input; not a direct row predicate |
| `operator_views` | assignment field and allowlisted view normalization | presentation narrowing | Presentation policy; never an authority grant |
| `scope`, `scopes` | `user_operational_scopes` aggregation | machine/part/task and optional organizational dimensions | Operational data scope |

The resolver also returns `workspace_profile`. Per the experience-composition
architecture, that profile may shape landing, navigation, and prominence only;
it cannot grant access or replace Manufacturing authority.

### 3.2 Operational scope schema

`plugins/Base/migrations/004_dashboard_assignment_model.sql` defines
`user_operational_scopes` with these columns:

| Column | Resolved output | Meaning | Current enforcement evidence |
|---|---|---|---|
| `machine_id` | `scope.machine_ids` | permitted machine identity | Enforced by Manufacturing machine/production projections and adapters |
| `part_id` | `scope.part_ids` | permitted product/part identity | Enforced by Parts, Processing, Machines, and production projections |
| `task_type` | `scope.task_types` | task/work category hint | Present in the resolved contract; enforcement is owner/path-specific |
| `department_code` | first non-empty `scope.department_code` | organizational dimension | Stored/resolved, but no independent area authorization path was found |
| `branch_code` | first non-empty `scope.branch_code` | organizational dimension | Stored/resolved, but no independent area authorization path was found |
| `ownership_role` | first non-empty `scope.ownership_role` | ownership dimension | Stored/resolved, but no independent area authorization path was found |
| `is_active` | filters source rows | active scope row marker | Enforced by Base resolver query |

Multiple active rows are unioned for machine, part, and task IDs. The scalar
department, branch, and ownership values take the first non-empty value rather
than forming a validated multi-value restriction. This is an important limit:
those fields cannot currently be treated as a complete, independently enforced
multi-dimensional scope.

An empty machine/part list retains the existing owner-specific meaning in some
Manufacturing adapters: authorized unrestricted scope. Missing, malformed, or
denied scope must remain fail-closed according to each owner contract; callers
must not reinterpret an absent scope as unrestricted.

### 3.3 Role/profile and cross-functional registry

Base owns the existing profile and bundle registries used to compose the
context. Manufacturing-specific examples include:

- `production_operations`, `assembly_operations`, `qc_operations`, and
  `dispatch_operations`;
- `production_coordination`, `assembly_coordination`, `qc_coordination`, and
  `dispatch_coordination`;
- `manufacturing_planning`, `manufacturing_reporting`,
  `manufacturing_approval`, `readonly_observer`, and
  `materials_readonly`.

Known cross-functional bundles include `cross_order_access`,
`cross_production_access`, `cross_assembly_access`, `cross_qc_access`, and
`cross_dispatch_access`, each with view/work/approve/manage levels.

These registries are meaningful authority inputs because they contribute
permissions, module visibility, duty codes, access profiles, and route/data
access. Their bundle names are also used by `crossWorkAreas()` as display
labels. The display mapping is not itself permission enforcement.

## 4. Authority-versus-presentation classification

### 4.1 Security and authorization scope

The following are authority-bearing or authority-supporting evidence:

1. authenticated user identity and same-user `/u/{username}` confinement;
2. active assigned Manufacturing app;
3. active module visibility and existing Platform route authority;
4. resolved access profiles, permissions, duty codes, account class, and app
   roles;
5. valid machine and part scope from `user_operational_scopes`;
6. owner-specific task-type handling where the consuming service supports it;
7. record-level product/part and transition checks in Manufacturing services.

OH-AUTO1-093/094 provide direct evidence for Processing: product scope and
exact Assembly/QC module tokens are checked before queries or mutations, and
read-only/display evidence denies mutation. `MachinesAdapter` similarly
requires resolved identity, assignment, module access, and a valid scope
object, then keeps rows, machine choices, sections, and KPIs aligned.

### 4.2 Operational assignment

Operational assignment is represented by the resolved role/profile and scope
context, not by a work-area label. The existing values can answer questions
such as:

- which app/modules are assigned;
- which machine and part identities are in scope;
- which task types are declared;
- which operational role and cross-functional bundles shape the assignment;
- which existing routes/actions pass authority.

They cannot currently answer, as an independent contract, “which named work
area is authorized?” or “which process area is authorized?”

### 4.3 Work classification and presentation grouping

`MyWorkContributionService` defines the following presentation functions:

| Function | Current rule | Classification | Security conclusion |
|---|---|---|---|
| `primaryWorkArea($ctx)` | substring match on `operational_role`; default `production` | role-derived presentation | Not an authority grant; default can mislead if rendered as scope |
| `crossWorkAreas($ctx)` | substring match on keys in `cross_functional_access` | capability/presentation grouping | Bundle authority must be checked separately; label is not a row predicate |
| `workAreaForItem($item)` | entity type to `order`, `assembly`, `qc`, `dispatch`, or `production` | item classification | Does not authorize the item or action |
| `grouped_sections` | compares item classification with primary/cross labels | dashboard grouping | Safe only after the item is independently authorized |

The `operator_views` field can narrow which named operator views are shown,
but it is a presentation filter. It cannot be used to grant a hidden view,
record, or mutation.

### 4.4 Workflow stage and process classification

`StageTransitionService::stagePathForProduct()` is an existing product-rule
classification contract. It derives the ordered path from product operational
attributes:

```text
production -> [assembly] -> [qc] -> [packaging] -> dispatch
```

The relevant existing product attributes are `supply_mode`,
`requires_assembly`, `requires_ipm_qc`, and `dispatch_as_is`. The service also
defines stage states such as `pending`, `eligible`, `released`, `in_progress`,
`complete`, `blocked`, and `skipped`.

This is a workflow/process classification, not a user authorization scope.
The stage-readiness migration is keyed by `(product_id, ref_date, stage)` and
stores release/override evidence; it has no user-area ownership dimension.

`HandoffTrackingContributionService` adds workflow handoff stages such as
`production_to_qc`, `qc_to_dispatch`, `dispatch_to_completion`, and
`order_risk`. These identify cross-stage handoff state and SLA meaning, not
independent user access.

No authoritative Manufacturing work-center, routing-step, process-area, or
user-to-process-area contract was found. `route_family` and
`execution_path_label` occur in upstream execution records as operational
provenance, not as an established authorization dimension.

## 5. Existing operator capabilities

### 5.1 Capabilities that are already scope-aware

The following are viable inputs for a future computed presentation, subject to
their existing service boundaries:

- `ProcessingOperationService::operatorFocus()` — filters products and
  Assembly/QC rows by resolved `part_ids`, and reports exact lane authority;
- `ProcessingOperationService::authorizeOperatorMutation()` — performs
  record/product/module/transition checks before mutation;
- `ProductionOperationService::build()` — passes resolved machine and part IDs
  into production-plan and machine queries;
- `MachinesAdapter` — maintains normalized machine/part scope across rows,
  filters, sections, choices, and supported KPIs;
- Manufacturing Parts projection — requires authenticated identity, active
  assignment, Products module access, and effective part scope before detail;
- Manufacturing operator contribution — filters navigation and focus views by
  existing module visibility, `operator_views`, and route authority.

These services can support a read-only area summary only by describing records
they already returned after their own authority checks. A new summary must not
query broader tables and group afterward.

### 5.2 Capabilities that are not yet safe for computed-scope presentation

The current My Work path is not a complete scope-safe source for “current” or
“upcoming” work:

- `MyWorkContributionService::fetchRuntimeBackedEntities()` calls
  `MyWorkEntityQuery` without passing the resolved `$scope`;
- `MyWorkEntityQuery` fetches daily orders, production entries, production
  plans, QC entries, and dispatch entries with status filters and limits, but
  does not apply machine/part predicates;
- the legacy QC, dispatch, daily-order, production-entry, and production-plan
  queue methods accept `$scope` in their signatures in some cases but their
  SQL shown in this inventory does not apply the machine/part restrictions;
- approval items are permission-gated but are not shown as part-scope-filtered
  projections;
- handoff items are selected by user/owner role and module filtering, not by
  the resolved machine/part scope.

`MyWorkEntityAdapter` adds SLA and policy enrichment. It does not add
machine/part scope predicates. Therefore it cannot be cited as a scope
enforcement layer.

This is an identified follow-up boundary, not a defect to fix under
OH-AUTO1-095. The Core query classes are locked and no Core correction is
authorized here.

### 5.3 Authorized navigation and actions

Manufacturing operator navigation is assembled by
`OperatorSurfaceContributionService`. It checks module visibility and optional
`operator_views`; role links in `MyWorkContributionService` additionally call
`UserDashboardAssignmentService::routeAccessDecision()` for GET authority.

This supports the following interaction rule:

> A computed area/process presentation may link only to an existing same-user
> operator route that passes the existing route authority. It must not create a
> new action, route, or mutation permission from an area label.

Existing Processing and task/action paths provide examples of the required
pattern: the Shell surface can render owner-provided actions, while
Manufacturing remains the authority for data scope and mutation eligibility.

## 6. Unsupported or ambiguous dimensions

The inventory found no authoritative contract for:

1. `work_area_id` or equivalent user-to-area relation;
2. `process_area_id` or equivalent user-to-process relation;
3. work-center assignment or machine-section authority beyond existing
   machine IDs;
4. routing-step authorization;
5. department/branch as a fully enforced multi-value data scope;
6. ownership-role as a row-level authorization predicate;
7. a scope-aware common My Work query contract across all entity types;
8. a read-only, schema-free common stage projection for operator use.

The following names must remain explicitly classified as non-authoritative
unless a future owner contract changes that status:

- `primary_work_area` and `cross_work_areas` output values;
- entity-type labels from `workAreaForItem()`;
- `operator_views`, `me_dashboard_blocks`, and `workspace_profile`;
- workflow stage labels and handoff stage keys;
- route-family and execution-path labels;
- department, branch, and ownership strings when no consuming owner predicate
  proves their enforcement.

## 7. Owner responsibility map

| Concern | Owner | Current contract | OH-AUTO1-095 conclusion |
|---|---|---|---|
| identity, role/account normalization | Core/Base existing auth and assignment path | resolved user context | Consume; do not duplicate |
| assignment/profile composition | Plugin/Base | `UserDashboardAssignmentService` | Source of effective evidence; not a new area catalog |
| machine/part/task operational scope | Base schema + Manufacturing consumers | `user_operational_scopes`, owner adapters/services | Use existing dimensions only |
| module/capability authority | Platform/Base plus Manufacturing module declarations | module visibility, permissions, route authority | Required before projection/navigation |
| Manufacturing record projection | Manufacturing | Processing, Production, Parts, Machines services | Owner must enforce predicates before projection |
| work-area labels | Manufacturing `MyWorkContributionService` | derived role/entity grouping | Presentation only |
| process/stage classification | Manufacturing | product attributes, stage transitions, handoffs | Workflow classification only |
| operator wrapper/rendering | Shell | `/u/{username}` composition | Render owner-provided data; no business authority |
| common My Work query/adapters | Core/legacy shared path | `MyWorkEntityQuery`, `MyWorkEntityAdapter` | Not scope-safe enough for OH-AUTO1-096 without a separately approved owner boundary |
| Studio composition | Studio | governed tooling only | Not involved in runtime authority |

## 8. Recommended operator interaction model

The next operator experience should use three distinct layers:

1. **Authorized work identity** — show the effective operational role and
   existing area labels as explanatory presentation, never as permission
   evidence.
2. **Scoped work lanes** — show only rows returned by a Manufacturing-owned
   projection that applies existing machine/part/task and module authority
   before ordering, grouping, counting, or limiting.
3. **Process context** — show stage/path labels only for an already-authorized
   product/record. A stage label can explain why work is waiting; it cannot
   make a record visible or actionable.

For navigation:

- preserve `/u/{username}/*` confinement;
- filter by resolved view/module authority first;
- re-check existing GET route authority for every emitted destination;
- suppress action forms for read-only/display profiles;
- keep mutation authorization in the existing Manufacturing service;
- do not expose counts, badges, or “upcoming” lists from an unscoped query.

For read-only/display users, render only the owner-provided projection with
explicit display/read-only mode. Never reuse operator mutation permission or
infer an area grant from a display label.

## 9. Precisely bounded OH-AUTO1-096 milestone

### Proposed objective

**OH-AUTO1-096 — Manufacturing scope-safe work/process area summary**

Add one Manufacturing-owned, read-only projection for the existing worker
surface. The projection may return:

- primary and cross-functional area labels derived from the existing resolved
  context, explicitly marked as presentation metadata;
- process-stage labels derived from product/record data already returned by a
  scope-aware Manufacturing service;
- a bounded list of current/upcoming records only when the source projection
  has applied existing machine/part/task/module authority before limits;
- existing authorized same-user destination URLs, after route authority
  revalidation.

### Explicit implementation boundary

- Modify only Manufacturing-owned service/projection and its focused isolated
  proof, plus the existing Shell contribution handoff if needed to consume the
  returned data.
- Do not add `work_area`, `process_area`, department, branch, work-center, or
  routing-step columns or permission fields.
- Do not modify Core `MyWorkEntityQuery`, `MyWorkEntityAdapter`, routing, ACL,
  or the Base resolver.
- Do not use `primaryWorkArea()`, `crossWorkAreas()`, or
  `workAreaForItem()` as authorization.
- Do not consume the current broad My Work queue as scope-safe unless every
  selected source is proven to apply existing scope predicates before its
  limit.
- Do not add a new route, mutation, workflow engine, or database migration.
- Keep the unresolved Base `ensureSchema()` dependency outside the milestone.

### Acceptance proof

The milestone should have isolated fixture coverage proving, at minimum:

1. valid machine/part/task and module evidence yields only in-scope records;
2. missing, malformed, denied, or conflicting scope yields no records;
3. area labels never expand record scope;
4. stage labels never expand record scope;
5. cross-functional labels require their existing capability/module evidence;
6. unauthorized destinations are omitted or safely suppressed;
7. read-only and display contexts expose no mutation actions;
8. counts and limits are computed after scope filtering;
9. empty authorized scope preserves only the existing owner-defined meaning;
10. no Core, schema, database, browser, deployment, or production operation is
    required.

If the required current/upcoming source cannot meet these conditions without a
Core or new-authority change, OH-AUTO1-096 must narrow to the area/process
summary metadata only and record the broader My Work queue as a separate
future milestone.

## 10. Decision and conclusion

OH-AUTO1-095 does not authorize implementation of new area/process scope. The
existing system supports computed presentation from existing authority, not a
new area authorization model.

The safe next step is the bounded OH-AUTO1-096 projection described above,
with Manufacturing retaining business meaning and authority, Shell retaining
composition/rendering only, and Base/Core remaining unchanged.

## 11. Evidence index

| Decision | Evidence |
|---|---|
| Operational dimensions are machine/part/task plus optional organizational fields | `plugins/Base/migrations/004_dashboard_assignment_model.sql:12-29`; `UserDashboardAssignmentService::scopeForUser()` |
| Effective assignment evidence is composed before handoff | `plugins/Base/Services/UserDashboardAssignmentService.php:1738-1933` |
| Cross-functional bundles contribute authority but are also display-mapped | `UserDashboardAssignmentService.php:435-557`; `MyWorkContributionService::crossWorkAreas()` |
| Primary/item work-area values are derived labels | `MyWorkContributionService.php:956-1015` |
| Operator views/module access shape presentation/navigation | `OperatorSurfaceContributionService.php:40-179, 375-442` |
| Processing applies part/module scope before projection and mutation checks | `ProcessingOperationService.php:20-170` |
| Production applies machine/part scope before query results | `ProductionOperationService.php:227-310` |
| Product process path is derived from product attributes | `StageTransitionService.php:9-136` |
| Stage-readiness storage has product/date/stage, not user area authority | `apps/Manufacturing/migrations/002_mfg_stage_transitions.sql:1-25` |
| Shared My Work queries do not accept or apply the resolved scope | `app/Core/MyWorkEntityQuery.php:26-181`; `MyWorkContributionService.php:430-484` |
| My Work enrichment is SLA/policy enrichment, not scope enforcement | `app/Core/MyWorkEntityAdapter.php:53-184` |
| Existing operator links are route-authority filtered | `MyWorkContributionService::buildRoleLinks()` at `MyWorkContributionService.php:1023-1055` |
