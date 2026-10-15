# SBAIO — Decisions

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
