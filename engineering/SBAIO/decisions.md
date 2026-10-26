# SBAIO — Decisions

### 2026-10-26 — OH-AUTO3-SBAIO-SALES-PAGING-001 forward keyset contract

**Decision: Use a Sales-owned opaque forward keyset cursor; do not add a generic pagination framework or route.**

The cursor records version, the last displayed `sale_date` (including NULL),
the last displayed positive `id`, and a SHA-256 fingerprint of normalized
`sale_ref`, status, and inclusive date filters. It is decoded and validated
before database access. A changed filter set, malformed structure, invalid
calendar date, invalid ID, or invalid fingerprint fails closed without a read.

The next-page predicate preserves the existing order: non-NULL cursors use
`sale_date < cursor_date OR sale_date IS NULL OR (sale_date = cursor_date AND
id < cursor_id)`; NULL cursors use `sale_date IS NULL AND id < cursor_id`.
Values remain parameterized and each page fetches at most 51 rows to display
50. Existing authorization, filters, reports, exports, routes, schema,
mutations, and lifecycle semantics remain unchanged.


### 2026-10-26 — OH-AUTO3-SBAIO-SALES-SEARCH-001 bounded retrieval contract

**Decision: Extend the existing Sales-owned read service rather than introduce a generic search framework or new route.**

Search accepts exact `sale_ref`, one of the established `draft`, `open`,
`pending`, `won`, `completed`, or `closed` statuses, and inclusive `sale_from`
/ `sale_to` calendar dates. Each supplied value is validated before any DB read;
the service binds values as parameters and uses one overflow row (`LIMIT 51`)
to expose a bounded 50-row result plus `truncated` state. Ordering is fixed to
`sale_date DESC, id DESC`.

The existing Sales route continues to apply administrator authorization before
the controller invokes search. No permission, lifecycle, schema, mutation,
report, export, organization, or cross-domain authorization contract changes.


### 2026-10-26 — OH-AUTO3-SBAIO-SALES-REPORT-001 report read ownership

**Decision: Keep Sales report query ownership in `SalesService`, with the route/controller supplying a prepared payload to the view.**

The existing report route continues to enforce the administrator guard and
active `sbaio.sales.overview` registry admission before invoking the controller
render handoff. `SalesService::reportData()` retains the four existing reads:
the 90-day aggregate, 90-day status grouping, 90-day customer ranking limited
to 15 rows, and recent sales ordered by date/id limited to 25 rows. Its
default-on-error result matches the former view behavior.

The report view is presentation-only and no longer imports or calls DB or
report-registry services. This is a Sales-owned extraction, not a generic
reporting framework; no widget type, placement zone, interaction profile,
admin/operator/display contribution, or canonical route changed.


### 2026-10-16 — OH-AUTO2-105S DB-backed probe admission

**Status:** Accepted — reuse the existing disposable entry-point guard

Every SBAIO DB-backed behavioral probe must establish explicit disposable
repository, isolated source-root, configuration, and process/socket/datadir
attestation before loading the application autoloader or DB layer. Missing,
ambiguous, repository-root, installed-like, or otherwise unverified evidence
fails closed. The guard does not redirect a probe to another database.

The existing disposable orchestrator owns the authorized `OH_SBAIO` execution
branch. Static and configuration probes remain the verification path when no
disposable database is authorized. No Core capability, database cleanup, or
incident remediation is implied by this decision.

## Decision Log

### 2026-10-16 — OH-AUTO2-108 SBAIO operational provider boundary

**Status:** Accepted — retain suppression

SBAIO personnel, attendance, timecard, payroll, leave, and watchlist host
contributions remain suppressed. Existing app/module access and route
authority do not independently establish record-level personnel authorization;
no new visibility rule or authorization field may be created as a migration
shortcut.

### 2026-10-16 — OH-AUTO2-105 SBAIO `/me` personnel-data boundary

**Status:** Accepted — suppress until record-level authority exists

Attendance exceptions, staff names/codes, payroll runs, timecards, and leave
requests are not exposed through SBAIO `/me` summary or watchlist providers.
The existing app/module and route contracts do not independently prove
record-level personnel authorization for this host surface. The provider
therefore returns no sensitive collection before module widgets execute.
Existing canonical `/apps/sbaio/...` routes remain unchanged; host navigation
continues to be subject to the existing route-access filter. A future
record-level contract is required before restoring useful personnel widgets.

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
