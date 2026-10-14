# OH-AUTO1-019 — Operational Diagnostics and Observability Audit

**Date:** 2026-10-14
**Mode:** Read-only audit with one bounded non-Core correction
**Database/configuration changes:** None
**Production activation/deployment:** Not performed

## Scope and authority

This audit inspected existing application, Platform, Shell, plugin, release,
recovery, lifecycle, identity-security, and administrative health surfaces. Core
was not modified and no database-backed probe was run against the installed
database. The correction stays within the Shell operator presentation boundary
and Manufacturing operator adapters.

## Diagnostic surface inventory

| Surface | Producer | Destination | Correlation/severity | Finding |
|---|---|---|---|---|
| Application lifecycle | `AppPlatformLogger`, `AppLifecycleService` | `storage/logs/app-lifecycle.log`, PHP error log, dedupe JSON | action/app key; dedupe signature and occurrence count | Useful lifecycle visibility; raw exception text may include internal paths in server logs. |
| Identity/security events | `IdentitySecurityLogService` | `identity_security_events` table | event/outcome, hashed email/IP | Email is masked, email/IP are hashed; metadata remains caller-controlled and needs contract review. Schema is ensured at runtime, so DB-backed behavior was not exercised. |
| Business audit | `AuditLogService` | `audit_activity_log` table | entity/event/action/actor | Workflow/state audit exists; actor email/display name and caller metadata are stored. No correlation ID or redaction policy is enforced centrally. Core-owned concern; not changed. |
| PHP/runtime failures | direct `error_log()` calls | PHP/server error destination | mostly class/method text; no common request ID | Broad but fragmented. Some paths log raw exception messages and traces. No global application exception handler was found outside Core/bootstrap ownership. |
| Operator read surfaces | Shell composer/adapters plus operator views | server logs and authenticated `/u/*` HTML | no correlation ID | **Corrected:** raw exception text was returned in operator display payloads. Stable localization key is now returned/rendered while server logging remains. |
| Plugin/admin tool failures | AdminTools routes/controllers | server error log and admin session flash | no common correlation ID | Export routes already use generic user flash text; app-management controller logs raw message and trace server-side. Admin-only, but retention/redaction is not governed. |
| Release/update readiness | Platform Updates services/probes | structured arrays, evidence files, CLI output | check identifiers/readiness status | Offline evidence and compatibility diagnostics are explicit. Live deployment/recovery acceptance remains unavailable. |
| Recovery | Platform Recovery services/CLI | recovery metadata, CLI output, filesystem artifacts | recovery-point ID/checksums | Metadata validation and checksum-oriented evidence exist. No destructive restore rehearsal was run. |
| Health/admin diagnostics | `HealthDashboardService`, `InstallVerificationService`, `ArchitectureHealthController` | authenticated admin pages | status cards, warnings/errors | Existing diagnostic surfaces are useful but DB-backed and not exercised in this audit. |
| Background/realtime | operator realtime/cache services | websocket/CLI/stdout and returned status arrays | no request/job correlation ID | Raw Redis exception output/status and unauthenticated handshake were corrected in OH-AUTO1-038/039; correlation and retention remain separate concerns. |

## Verified defect and correction

Before this slice, these execution paths copied `$e->getMessage()` into data
consumed by authenticated operator views:

- `apps/Manufacturing/Services/OperatorLayerAdapters/{Assembly,Coverage,Dispatch,Machines,Materials,Qc}Adapter.php`
- `apps/Shell/Composers/OperatorSurfaceComposer.php`
- operator views under `apps/Shell/Views/operator/`

The same exception was also written to the existing server-side `error_log()`
path. A database error, connection error, filesystem path, or dependency
failure could therefore be rendered to an operator instead of remaining a
server diagnostic.

The correction:

1. Keeps the existing server-side `error_log()` call for diagnosis.
2. Replaces display payloads with `operator.common.data_unavailable`.
3. Translates that key in English, Japanese, and Nepali operator locale files.
4. Makes affected operator views translate error keys rather than echoing raw
   payload values.
5. Adds `apps/Shell/Tests/probe_operator_diagnostic_redaction.php`, which is
   static/read-only and does not bootstrap the application or connect to DB.

## Sensitive-information findings

### No confirmed secret value exposure in inspected persisted logs

The inspected `storage/logs/app-lifecycle.log` contained lifecycle context and
internal filesystem paths, but no password/token/secret/API-key markers. This
is evidence about the inspected files only, not a retention guarantee.

### Confirmed governance gaps, not corrected due to ownership/scope

- `IdentitySecurityLogService::log()` accepts arbitrary metadata and JSON
  encodes it without a central sensitive-key allowlist.
- `AuditLogService::logEvent()` persists caller-provided reason, note, diff,
  and metadata without a central redaction contract.
- Several server-side `error_log()` calls include raw exception messages; one
  admin-only path also includes a full trace. These are controlled server-side
  destinations but have no common retention, redaction, or correlation policy.
- `OperatorRealtimeCacheManager` echoes raw Redis exception messages and
  returns one raw error field from cache status. This requires a separately
  authorized realtime/operational diagnostic contract.

No Core change, database change, global logging framework, production config
change, or live error injection was authorized for these findings.

## Realtime authentication correction — OH-AUTO1-039

The realtime boundary is reachable from the operator browser through
`OperatorRealtimeService::onOpen()` and the standalone
`bin/operator-realtime-server`. The former placeholder accepted any non-empty
token and username without consulting the session.

The correction now requires the existing browser session cookie, validates the
session through `Auth::isLoggedIn()`/`Auth::user()`, compares the supplied token
with the session CSRF token using `hash_equals()`, binds the requested handle to
the verified identity subject to existing privileged-role rules, and enforces
`UserAssignmentContext::accessPolicy()->routeAccessDecision()` for the requested
operator view. Missing or unavailable authority fails closed. The same
authority is rechecked before subscriptions and KPI delivery, so account/session
invalidation is not treated as permanent connection authorization.

No new token format, signing key, session store, database schema, or live
WebSocket configuration was introduced. The isolated redaction/authentication
probe passes **81/81**; live WebSocket acceptance remains unavailable and gated.

## OH-AUTO1-040 — Handshake security findings

The standalone bootstrap constructs `WsServer` inside `HttpServer` directly.
It does not use Ratchet's `OriginCheck` middleware, and the repository contains
no trusted-origin configuration contract for the documented split deployment
(`erp.example` browser surface versus `realtime.example` WebSocket host).
The Origin header is therefore not authoritatively validated today. Missing,
trusted, and untrusted Origin cases cannot be classified as safely accepted or
rejected without first deciding the deployment-owned origin authority. A
same-host assumption would break the documented separate realtime-host
deployment; an arbitrary allowlist would create a new security authority.

The existing client also places the CSRF token in `?auth=`. That is part of the
existing protocol contract and may appear in reverse-proxy/access logs. The
compatible correction removes the token-bearing URL from browser console debug
output. It does not pretend to remove proxy/server access-log exposure. Moving
the credential to a header/subprotocol or a server-issued opaque handshake
credential requires a separately approved protocol migration.

The isolated probe explicitly verifies the current middleware state and the
safe client logging behavior. Origin enforcement remains a **blocked security
decision**, not a passed control.

## Failure classification coverage

| Failure class | Visibility | Assessment |
|---|---|---|
| Expected validation failure | route flash/HTTP validation response | Present, but some action paths expose service exception text. |
| Authentication failure | identity-security event service plus auth responses | Present; Core-owned end-to-end handling remains unverified. |
| Authorization denial | HTTP status/route guards and some audit paths | Present; ACL deny-precedence remains an existing blocker. |
| Lifecycle rejection/failure | lifecycle log and status/error fields | Present; plugin rollback and universal lifecycle authority remain blocked. |
| Database transaction failure | thrown exception, local logs, selected generic responses | Fragmented; transaction behavior was not run against installed DB. |
| Missing dependency | exception/log or readiness diagnostic | Present in many local paths; no universal correlation ID. |
| Plugin activation failure | lifecycle log and activation rollback result | Visible; partial-route rollback remains Core escalation. |
| Background-job failure | limited realtime/CLI output | Incomplete; job registry/guards are not verified. |
| Release integrity failure | readiness evidence and compatibility diagnostics | Present offline; live acceptance remains gated. |
| Recovery verification failure | recovery metadata/CLI diagnostics | Present offline; restore rehearsal remains pending. |

## Remaining blockers and acceptance requirements

- Core/security owner must resolve or accept `SEC-AUTH-001`.
- Core/platform owners must decide lifecycle materialization, universal route
  enforcement, and transactional plugin rollback authority.
- A governed correlation-ID and redaction contract is needed before attempting
  centralized observability changes.
- Disposable isolated-DB behavioral probes remain required for Procurement and
  multi-write workflows; the installed database must not be used.
- Background-job and realtime failure visibility requires an inventory of the
  actual deployed worker/runtime model.
- Production acceptance still requires authorized live HTTP, deployment, and
  recovery rehearsal evidence.

## Validation record

- Focused redaction probe: expected to run without DB/bootstrap.
- PHP lint: changed PHP files.
- Architecture gates, deletion gates, and deployment readiness: run as bounded
  non-duplicated suites where environment permits.
- `git diff --check`.

## Recommended next three assignments

1. **OH-AUTO1-020 — Isolated transactional behavior verification:** provision
   an explicitly disposable DB and run Procurement cancellation/request/supplier
   probes plus bounded multi-write rollback checks.
2. **OH-AUTO1-021 — Consolidated production-readiness reconciliation:** assemble
   the remaining Core/security/lifecycle blockers into an owner decision matrix;
   do not patch Core without approval.
3. **OH-AUTO1-022 — Operational acceptance plan:** define correlation,
   retention, log-write failure, worker, live HTTP, deployment, and recovery
   acceptance criteria without implementing a second logging framework.
