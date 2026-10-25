# Manufacturing — Work

## 2026-10-25 — OH-AUTO3-MFG-OWN-001 active Assembly/QC operator boundary

- [x] Confirmed the integrated Production baseline remains `89aed08d` with
  `origin/main` parity; completed Production scope work was not reopened.
- [x] Verified the active `/u/assembly` and `/u/qc` routes previously loaded
  operator data without passing the authenticated operator to their adapters.
  Assembly queried `mfg_assembly_entries` without a part predicate and QC
  passed `null` into the existing provider, bypassing its scope filtering.
- [x] Reused `ProcessingOperationService`'s established assignment, module,
  lane, denied-scope, and part-scope decision for active operator reads.
- [x] Assembly now fails closed before reads when Assembly authority is absent
  and applies the authorized part predicate before its existing limit. QC now
  fails closed for denied/missing QC authority and receives the operator in
  the existing `QcLeaderDashboardService` call.
- [x] Added `probe_operator_assembly_qc_scope.php`; focused proof passes
  **8/8** and existing Processing scope proof remains **35/35**.
- [x] PHP lint and diff hygiene passed. No mutation route, schema, database,
  Core, deployment, activation, or unrelated artifact operation occurred.

## 2026-10-25 — OH-AUTO3-MFG-SCOPE-004 strict Production input validation

- [x] Replaced direct `int`/`float` coercion in both existing Shell Production
  POST routes with Manufacturing-owned raw-input normalizers.
- [x] Positive identifiers now accept only scalar unsigned decimal integers
  within platform integer range; partial strings, fractions, signs, exponent
  notation, arrays, missing values, zero, negative, and overflow values fail
  closed.
- [x] Production quantities now reject non-scalar, partial, non-finite,
  negative, and overflowing values while preserving the existing finite float,
  zero, rounding, and DECIMAL(14,2) semantics.
- [x] Extended the isolated Production authorization probe from **14/14** to
  **39/39**, including malformed identifiers/quantities, overflow, valid
  values, and route-boundary static assertions.
- [x] PHP lint and diff hygiene passed. Database, schema, Core, deployment,
  activation, and production operations remain untouched.

## 2026-10-25 — OH-AUTO3-MFG-SCOPE-002 Production operator authorization remediation

- [x] Re-verified the accepted SCOPE-001 contract against current source. The
  existing `production` module token, Manufacturing assignment context,
  `machine_ids`/`part_ids` scope, and mutation profiles are authoritative
  existing predicates.
- [x] Added the Manufacturing-owned `ProductionOperationService::operatorFocus`
  projection. It denies before reads and applies part/machine predicates before
  grouping, ordering, and limits.
- [x] Added Manufacturing-owned status and create authorization. Missing,
  foreign, stale, locked, denied-scope, read-only, and noncanonical legacy
  records fail closed.
- [x] Replaced Shell Production direct reads with the Manufacturing projection
  and replaced direct status/create SQL with Manufacturing delegation. Existing
  CSRF and same-user redirect handling remain in Shell.
- [x] Canonical operator targets are `InProgress` and `Completed`; historical
  `Pending`/spaced `In Progress` values are not silently accepted as canonical
  mutation states.
- [x] Added `apps/Manufacturing/Tests/probe_operator_production_authorization.php`;
  isolated authorization coverage passes **14/14**.
- [x] PHP lint, existing Manufacturing operator regressions, and diff hygiene
  passed. No Core, schema, database, deployment, activation, or push operation
  occurred.

## 2026-10-25 — OH-AUTO3-MFG-SCOPE-001 Production operator authorization contract

- [x] Traced the active Production operator read path through the Shell route,
  `OperatorSurfaceComposer::buildProductionFocusData()`, and the Production
  operator view. Direct `daily_orders`/`production_plans` reads are not
  pre-limit scoped.
- [x] Traced the active status mutation path. Shell validates CSRF and an
  opaque plan ID, then directly updates `production_plans.status` without
  Manufacturing record authorization, owner workflow delegation, approval
  synchronization, or proven audit-hook execution.
- [x] Compared `ProductionOperationService::build()` with the completed
  Processing operator contract. Existing Production row predicates are
  reusable, but `scopeForUser()` is not an operator authority boundary and
  must not be reused unchanged.
- [x] Confirmed the entity workflow candidate transitions are
  `Planned -> InProgress -> Completed`, while `Pending`, `In Progress`, and
  `in_progress` remain compatibility values requiring owner confirmation.
- [x] Created the design-only contract:
  `docs/architecture/oh-auto3-mfg-scope-001-production-operator-authorization-contract.md`.
- [x] No runtime, route, schema, database, Core, deployment, activation, or
  unrelated-owner change was made. Implementation recommendation is
  **OWNER DECISION REQUIRED**.

## 2026-10-24 — OH-AUTO3-MFG-002 scoped machine workboard filters

- [x] Forwarded only the bounded `machine_id` and `section` query keys from
  Shell composition into the Manufacturing-owned machine projection.
- [x] Added strict scalar filter normalization: positive decimal machine IDs,
  existing 120-byte section capacity, valid UTF-8, and control-character
  rejection. Invalid nonblank filters fail closed with an empty projection.
- [x] Added localized GET machine/section controls and a same-user clear link;
  no route, permission, mutation, schema, database, or Core change occurred.
- [x] Added `apps/Manufacturing/Tests/probe_operator_machine_workboard_filters.php`;
  focused filter coverage passed **24/24** and existing machine projection
  coverage passed **27/27**.
- [x] PHP lint, diff hygiene, Core lock, operator confinement, business
  app/module contracts, architecture gates **PASS**, deletion-family gates
  **28/28**, and deployment readiness **PASS** in read-only verify mode.

## 2026-10-22 — OH-AUTO3-006 scoped Parts operator search

- [x] Added bounded server-side `q` search to the existing Parts list across
  the already projected `parts_name`, `parts_number`, and `model` fields.
- [x] Preserved the existing Manufacturing scope predicate before filtering,
  deterministic ordering, and `LIMIT 500`; invalid, array-shaped, blank,
  oversized, and denied inputs fail closed.
- [x] Added a database-free regression probe:
  `apps/Manufacturing/Tests/probe_operator_parts_search_filter.php` —
  **18/18** assertions. Existing Parts scope and detail-authority probes pass
  **14/14** and **21/21**.
- [x] No status/category filter was invented because those fields are not part
  of the current Parts projection. No route, permission, mutation, schema,
  database, activation, browser, HTTP, deployment, or Core change occurred.
- [x] PHP lint, `git diff --check`, architecture gates, deletion-family gates
  **28/28**, and deployment readiness passed in read-only verify mode.

## Current Focus

OH-AUTO1-093 scope-aware Processing workspace and mutation gates are complete.
Continue
through the same owner-contract pattern for parts, work areas, and process
areas only as separately bounded milestones.

## 2026-10-20 — OH-AUTO2-MFG-004 Processing status redirect context preservation

- [x] Implemented the smallest Manufacturing Processing usability correction
  in the existing operator view; no route, service, status, authorization, or
  CSRF semantics changed.
- [x] Assembly and QC status forms now preserve the server-rendered valid
  `processing_date` and `product_id` in the same-user `/u/{username}/processing`
  return URL.
- [x] Missing, invalid-date, and unlisted-product contexts safely fall back to
  the bare same-user Processing path; query values use RFC3986 encoding and
  HTML-safe form attributes.
- [x] Rendered-form regression probe passed **21/21**; Processing scope passed
  **35/35**; work/process summary passed **23/23**.
- [x] Existing record IDs, CSRF fields, Manufacturing status authorization,
  route confinement, and creation redirects were verified unchanged.
- [x] No database, SQL, schema, migration, browser, HTTP, activation,
  deployment, Fulfillment, Material, SBAIO, unrelated-owner, or Core operation
  was performed.
- [x] Focused probe: `apps/Manufacturing/Tests/probe_operator_processing_redirect_context.php`.

## 2026-10-20 — OH-AUTO2-MFG-001 operator workflow gap assessment

- [x] Completed the read-only Manufacturing operator workflow map across
  Production, Processing, Fulfillment/Preparation/Dispatch, Parts, Machines,
  Assembly, QC, Orders, Demand, Coverage, Materials, and Handoff.
- [x] Distinguished completed scope/read-safety safeguards from product gaps;
  Manufacturing's disabled runtime is not treated as a source defect.
- [x] Identified the strongest product gap as dispatch lifecycle continuation:
  operator Fulfillment can create dispatch entries, while the existing
  `DispatchOpsService` lifecycle methods (`markReady`, `prepare`, `complete`)
  are not exposed through the operator surface.
- [x] Ranked two additional candidates: machine/assembly execution progress
  capture and operator handoff ownership. Both remain lower-confidence or
  higher-dependency work.
- [x] Recommended dispatch lifecycle continuation for a separately authorized
  implementation/design slice. No implementation was authorized or made.
- [x] Full report: `docs/architecture/oh-auto2-mfg-001-manufacturing-operator-workflow-gap-assessment-2026-10-20.md`.

## 2026-10-20 — OH-AUTO2-MFG-002 Fulfillment dispatch operator contract

- [x] Reconciled the local MFG-001 report and Git state. The report is present
  locally but untracked and therefore not independently verified from GitHub
  `main`; no unrelated changes were overwritten or discarded.
- [x] Traced administrative dispatch routes, `DispatchOpsController`,
  `DispatchOpsService`, operator POST routing, assignment/view admission, and
  existing dispatch workflow hints.
- [x] Classified `markReady`, `prepare`, and `complete` as **BLOCKED** for
  operator exposure: no record-level operator scope, incomplete legal-status
  transition contract, runtime schema ensure, no transaction boundary, and no
  required audit integration.
- [x] Confirmed no action is READY or ALREADY IMPLEMENTED for the operator
  surface; existing admin workflows do not satisfy the operator contract.
- [x] Recorded the smallest future boundary and offline test strategy. No
  production code, route, database, SQL, schema, activation, deployment,
  Material aggregate, or Core change was made.
- [x] Full report: `docs/architecture/oh-auto2-mfg-002-fulfillment-dispatch-operator-contract-2026-10-20.md`.

## 2026-10-20 — OH-AUTO2-MFG-003 Processing operator workflow completeness

- [x] Traced the complete Processing operator journey from assigned Assembly/QC
  projection through lane presentation, CSRF-protected POST handlers,
  Manufacturing authority checks, persistence path, flash feedback, and reload.
- [x] Confirmed completed scope, lane authority, read-only denial, transition,
  and operator route-confinement evidence; focused offline probes passed
  Processing **35/35** and work/process summary **23/23**.
- [x] Identified one reproducible usability defect: Assembly/QC status forms
  redirect to bare `/u/{username}/processing` and drop the active
  `processing_date`/`product_id` context, unlike the create forms.
- [x] Classified the defect as **DEFECTIVE**, medium usability severity, with
  a small future context-preserving redirect correction.
- [x] Classified live persistence/transaction behavior as **BLOCKED** by the
  no-database/no-HTTP restriction, not as a speculative source defect.
- [x] Recommended **IMPLEMENT** only through a subsequent bounded assignment;
  no production code, route, database, SQL, schema, activation, deployment,
  Fulfillment, Material, or Core change was made.
- [x] Full report: `docs/architecture/oh-auto2-mfg-003-processing-operator-workflow-completeness-assessment-2026-10-20.md`.

## 2026-10-16 — OH-AUTO2-112 record-scope authority investigation

- [x] Traced existing assignment scope authority and Material Management source
  relationships without database access or source implementation changes.
- [x] Confirmed reusable part/machine query filters exist, but Material,
  ledger, reservation, order, capacity, demand, and plan inputs lack one
  complete authoritative record-scope mapping.
- [x] Classified the restoration decision **OPTION C — BLOCKED**. Module
  permission is not whole-domain Material authorization; OH-AUTO2-111 remains
  in force.
- [x] Isolated fixture verification: Parts **14/14**, Processing **35/35**,
  restricted My Work **21/21**, preflight **17/17**.
- [x] Full findings: `docs/architecture/oh-auto2-112-manufacturing-record-scope-authority-query-contract-2026-10-16.md`.

## 2026-10-16 — OH-AUTO2-111 Material aggregate authorization containment

- [x] Removed the unsafe `/me` Material aggregate hook and capability
  descriptor after restricted-scope differential verification.
- [x] Preserved the inactive provider source, `summaryReadOnly()`, ordinary
  Material operations, six navigation capabilities, and other suppressed
  providers.
- [x] Registry containment/inventory proof: **11/11**; inactive-source proof:
  **17/17**; Material read safety remains **18/18**.
- [x] No new record-scope model, route, schema, database, activation, or
  deployment operation.

## 2026-10-16 — OH-AUTO2-111 Material aggregate containment

- [x] Removed the unsafe Material aggregate host hook and trusted capability
  descriptor after OH-AUTO2-110 proved restricted scopes produced identical
  counts.
- [x] Preserved the extracted source as inactive code and preserved
  `summaryReadOnly()` plus ordinary Material Management operations.
- [x] Confirmed six navigation descriptors remain and the suppressed provider
  is not loaded by the governed registry.
- [x] No new scope model, route, schema, database, or provider migration.

## 2026-10-16 — OH-AUTO2-109 Material summary extraction

- [x] Added the isolated Manufacturing-owned Material summary provider and
  exact manifest capability descriptor.
- [x] Required existing `materials` module authority and retained the
  `summaryReadOnly()` missing/incompatible-storage fail-closed path.
- [x] Kept the composite provider, monitoring region, routes, and schema-backed
  ordinary Material consumers unchanged.
- [x] Extraction proof: **16/16**; Material read safety: **18/18**; existing
  authorization/containment/admission/inventory proofs remain **12/12**,
  **15/15**, **9/9**, and **7/7**.

## 2026-10-16 — OH-AUTO2-108 operational provider migration inventory

- [x] Audited Manufacturing `/me` summary and monitoring callbacks and their
  module widget dependencies.
- [x] Confirmed Material Management read-only evidence (**18/18**) does not
  cover the composite summary callback's broad order, approval, and dynamic
  module reads.
- [x] Left both operational regions unadmitted; no descriptor, route, schema,
  or business-provider change was made.
- [!] A future bounded split may be considered only with pre-limit record scope,
  destination authority, and transitive no-mutation evidence.

## 2026-10-16 — OH-AUTO2-105 Manufacturing host-provider scope authorization

- [x] Manufacturing `/me` summary and module-widget providers now require the
  existing Platform app/module access policy before provider file loading or
  operational queries.
- [x] Manufacturing approval counts require separate existing Ops module
  authority; route filtering remains downstream for emitted links.
- [x] The established Material Management `summaryReadOnly()` path remains
  intact and is not bypassed.
- [x] Isolated host-provider authorization proof: **12/12**; restricted host
  containment remains **15/15**. No Core, schema, database, browser,
  activation, deployment, or route operation.

### OH-AUTO2-105R incident correction

The “No Core, schema, database...” sentence above is retained as the original
OH-AUTO2-105 implementation record. It is not a complete incident statement:
an existing DB-backed SBAIO probe was accidentally run during the milestone
against the repository's configured local development target. See
`AGENT-COMPLIANCE-CHECKLIST.md` and `engineering/SBAIO/work.md`. No cleanup or
further database operation is authorized; impact remains undetermined.

## 2026-10-16 — OH-AUTO2-104 Material Management read-only summary boundary

- [x] Added `MaterialManagementService::summaryReadOnly()` for host-surface
  consumers; it preflights required storage and returns unavailable on missing
  or incompatible source evidence.
- [x] Read-only summary mode bypasses both Material schema initialization and
  Demand schema/recalculation paths while preserving existing calculations when
  storage is valid.
- [x] Material host widgets no longer fabricate zero summaries when their source
  is unavailable; ordinary `summary()`/setup consumers retain schema authority.
- [x] Isolated read-safety proof: **18/18**; host containment **15/15**;
  preflight **17/17**; restricted containment **21/21**. No Core, schema,
  installed database, browser, activation, deployment, or production operation.

## 2026-10-16 — OH-AUTO2-101 notification read-path safety

- [x] My Work uses notification read results that do not call notification
  schema initialization or escalation sweeps.
- [x] Missing notification storage is represented as unavailable rather than
  a verified empty notification state; recipient filters remain unchanged.
- [x] Restricted My Work still returns before notification retrieval, while
  explicit escalation service entry points remain available separately.
- [x] Notification proof: **16/16**; preflight **17/17**; containment **21/21**;
  visibility **14/14**. No Core, route, schema, database, activation,
  deployment, or browser operation.

## 2026-10-16 — OH-AUTO2-100 restricted My Work non-mutating preflight

- [x] Manufacturing My Work accepts a preflight-resolved context and returns
  the existing unavailable containment payload before any schema initializer,
  queue collection, notification reader, escalation sweep, or role-link path.
- [x] Explicit unrestricted calls retain the existing schema-backed path;
  unrestricted notification/escalation safety remains deferred to OH-AUTO2-101.
- [x] Restricted preflight/build proof: **17/17**; existing restricted
  containment proof: **21/21**. No Core, schema, route, database, activation,
  deployment, or production operation.

Future Manufacturing work touching identity, Shared/Foundation, extensions, suite ownership,
or cross-domain authority is subordinate to
`docs/architecture/odarehub-future-architecture-planning-brief.md`.

No Shared/Foundation code, schema, storage, or runtime restructuring is authorized until the
brief's pressure-test workstreams and synthesis complete.

## In Progress

- [x] OH-AUTO1-095 — completed the read-only work-area/process-area authority
  inventory. Existing machine/part/task and assignment evidence is authoritative;
  `MyWorkContributionService` area values are presentation-only, and the broad
  My Work queue is not uniformly scope-safe. See
  `docs/architecture/oh-auto1-095-work-process-area-authority-inventory.md`.

- [x] OH-AUTO1-096 — implemented the bounded Manufacturing-owned scope-safe
  work/process presentation projection with Assembly/QC only. The broad My
  Work queue, machine/production sources without equivalent pre-limit scope
  proof, and product-stage derivation without product attributes remain
  excluded. See `docs/architecture/oh-auto1-096-scope-safe-work-process-summary-2026-10-15.md`.

- [x] OH-AUTO1-088 — normalized the owner-shaped runtime scope at the adapter
  boundary, aligned top-level sections with scoped filters, intersected
  part-only machine choices with scoped records, and suppressed unproven
  quantity totals.

- [x] OH-AUTO1-089 — strictly validates canonical scope IDs, preserves the
  owner `[-1]` denial sentinel, rejects malformed/mixed restrictions, and
  applies the same validation to quantity provenance.

- [x] OH-AUTO1-090 — Manufacturing-owned Parts list and detail selection
  requires authenticated identity, active Manufacturing assignment, Products
  module access, and effective part scope before pagination or Part360 build.

- [x] OH-AUTO1-091 — strict direct/search/part-number selector reconciliation
  and isolated fake-provider coverage preserve authorized part identity and
  stop unauthorized selection before any Part360 builder. Shell filters detail
  actions through same-user URL and existing Platform GET route authority.

- [x] OH-AUTO1-092 — operator detail now uses `Part360Service::buildReadOnly()`;
  the shared projection path skips schema repair while preserving admin
  `build()`. Missing required schema fails unavailable without mutation.

- [x] OH-AUTO1-093 — the operator Processing projection uses the
  Manufacturing-owned adapter and strict existing part/module authority; all
  four existing operator POST handlers authorize before mutation or schema
  setup, with read-only/display and invalid scope/transition requests denied.

- [x] OH-AUTO1-094 — Processing POST authority now preserves read-only and
  display denial for both status and creation mutations, uses exact existing
  `assembly`/`qc` module authority without mixed-surface aliases, filters each
  lane before projection, and hides unauthorized lane actions in Shell. The
  isolated proof is **35/35**; no database, activation, production, or Core
  operation. The existing Base context resolver still performs transitive
  `ensureSchema()` before returning context; that cross-owner dependency is
  documented, not broadened, and remains unresolved.

- [x] OH-AUTO1-087 — passed authenticated operator identity and effective
  assignment context into `MachinesAdapter`; Manufacturing retains machine,
  part, task-type, queue, filter, section, and KPI scope enforcement. Display
  callers use an explicit read-only display mode and do not receive operator
  identity.

- [ ] Keep Manufacturing identity/extension planning aligned with the canonical future architecture brief.
- [ ] Manufacturing Phase 3 evidence (BOM / product identity / revision) is retained as evidence and migration input only — it must not be used to conclude the final future architecture. See `docs/active/future-architecture-planning-manufacturing-phase3-evidence.md`.

## Next

- [x] OH-AUTO1-096 — Manufacturing scope-safe work/process area summary,
  completed with focused proof; no new area authority fields.
- [x] OH-AUTO1-097 — contained the broad My Work projection for restricted
   operational scopes before any limited queue source is queried. Explicit empty
   scope remains unrestricted; malformed and denied evidence fails closed.
- [x] OH-AUTO1-098 — preserved the containment payload and made its unavailable
   state visible through Shell. Notifications and role links remain suppressed
   because their current providers perform schema/escalation work or can expose
   operational entity details. Focused visibility proof: **14/14**.
- [ ] OH-AUTO1-034 — Reconcile updated integration baseline and source inventory.

## Blocked

- Nested invocation inside a caller-owned transaction remains unverified; current callers do not wrap `generate()`.

## Completed

- OH-AUTO1-097 — restricted-scope My Work containment: **21/21** focused
  assertions. Restricted machine/part/task and organizational scopes produce
  no records, counts, notifications, or links before broad queue collection;
  explicit empty scope retains existing unrestricted behavior. No Core,
  database, schema, route, activation, deployment, or production operation.

- OH-AUTO1-098 — containment visibility: **14/14** focused rendered-output and
  source-invocation assertions. Restricted scopes show explicit unavailable
  state rather than verified zero work; unrestricted scope renders no notice.
  No notifications, role links, out-of-scope identities/counts/URLs, schema,
  database, route, activation, deployment, browser, or Core operation.

- OH-AUTO1-088 — machine projection consistency and runtime-shaped proof:
  **15/15** assertions. No database, SQL, activation, display-route, schema,
  production, or Core operation.

- OH-AUTO1-089 — fail-closed scope normalization and negative fixture proof:
  **24/24** assertions. No database, SQL, activation, display-route, schema,
  production, or Core operation.

- OH-AUTO1-090 — operator Parts authorization and list/detail consistency:
  **14/14** assertions; machine regression **27/27**. No database, schema
  initialization, activation, display-route, production, or Core operation.

- OH-AUTO1-091 — detail authority and isolation proof: **20/20** assertions.
  Authorized Part360 reads remain blocked from read-only certification because
  `Part360Service::build()` calls `MaterialSchemaService::ensureSchema()` and
  `PartEngineeringSchemaService::ensureSchema()`, both of which contain
  CREATE/ALTER initialization. No speculative refactor was made.

- OH-AUTO1-092 — instrumented Part360 read-only proof **8/8**; detail/action
  and scope regressions **21/21**, **14/14**, and **27/27**. No database,
  schema, activation, production, or Core operation.

- OH-AUTO1-093 — isolated Processing scope/mutation proof **16/16**. No
  installed database, schema change, activation, production operation, or Core
  change.

- OH-AUTO1-019 — Manufacturing operator adapters now return a stable localized diagnostic key instead of raw exception text while retaining existing server-side error logging. See `docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md`.
- OH-AUTO1-020 — Static audit identified unverified target/link atomicity in upstream supply synchronization; no correction made without disposable failure-path evidence. See `docs/architecture/transactional-business-verification-audit-2026-10-14.md`.
- OH-AUTO1-030 — Verified `UpstreamSupplyGenerationService::generate()` through both in-house and third-party branches and added transaction boundaries around target/link pairs.
- OH-AUTO1-032 — Verified concurrent in-house and third-party generation and added duplicate-key recovery using the existing target/link uniqueness contract.

## Evidence

- Manufacturing behavioral probe: 21/21 passed against a fresh independently attested disposable MySQL instance.
- Verified target/link rollback on controlled link failure; no orphan target or link remained.
- Repeat generation updated existing target/link pairs without duplicates.
- Temporary datadir, socket, credentials, and process were removed; installed ServBay remained unchanged.
- Remaining concurrency and update-path limits are recorded in `docs/architecture/transactional-business-verification-audit-2026-10-14.md`.
- Nested transaction composability is not claimed: no existing caller-owned transaction was found, and the generic DB layer has no savepoint/depth abstraction.
- Manufacturing concurrency probe: 11/11 passed; both branches leave one target and one link and retries remain idempotent.
- The OH-AUTO1-030 rollback probe was rerun: 21/21 passed.
- Consolidated regression runner passed all six suites; Manufacturing transaction/concurrency probes passed 21/21 and 11/11.

## OH-AUTO1-047 — Manufacturing runtime DDL safety (2026-10-14)

Status: Static/mock correction complete; no database execution authorized.

`DemandController::debugGenerateUpstreamSupply()` reached
`UpstreamSupplyGenerationService::generate()`, which previously called both
the upstream schema helper and `DemandEngineService::previewRouteDecisions()`;
the latter also called `DemandEngineService::ensureSchema()`. The upstream
tables are owned by `apps/Manufacturing/migrations/005_mfg_upstream_supply_tables.sql`,
and the demand table is owned by `migrations/001_mfg_operational_model.sql`.

Removed the two runtime schema-ensure calls while retaining the explicit helper
for migration/test preparation. Focused static boundary probe: 14/14. PHP
lint and diff hygiene pass. No migration, schema, transaction, idempotency, or
concurrency behavior was changed. Other Manufacturing services with separate
legacy schema-preparation paths remain outside this bounded correction.

## OH-AUTO1-048 — Remaining Manufacturing runtime DDL (2026-10-14)

Status: Static/mock correction complete; no database execution authorized.

Removed runtime schema-ensure calls from `StageTransitionService`'s
`computeForDate()`, `releaseStage()`, and `overrideStageStatus()` paths. The
stage readiness, assembly, and shared demand tables are migration-owned, and
the paths are reachable from Manufacturing bootstrap, queue, dispatch, and
production surfaces.

The same migration-owned helpers were removed from
`DemandExecutionService::productionDemandMapByDate()`, `qcDemandRows()`, and
`assemblyDemandRows()`.

The processing execution generator and DispatchOps preparation paths remain
blocked from correction: their runtime-created tables have no corresponding
repository migrations. Removing those helpers would break supported legacy
installations without an authorized migration contract.

DemandEngine recalculation/adjust/approval/list and AssemblyPlan plan/execution
methods remain as additional runtime-ensure callers for a separately bounded
review because their helpers include legacy compatibility ALTER operations.

Focused remaining-boundary probe: 20/20. OH-AUTO1-047 probe: 14/14. PHP lint,
ownership/Core-lock checks, and diff hygiene pass. No database or SQL execution.
