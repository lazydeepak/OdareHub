# Hospitality Operator Actions Plan - Slice 1: Housekeeping Status Update

Status: Active implementation handoff for one narrowly-scoped operator mutation.

Owner: Hospitality App (apps/Hospitality)

Created: 2026-08-24

Builds on:

- `docs/active/hospitality-operator-composition-plan.md` (read-only operator surface)
- `docs/experience-composition-architecture-plan.md` (resolution pipeline; ACL owns
  authorization, profiles shape experience and never grant authority)

## 1. Why Housekeeping Is The First Operator Mutation

The existing admin mutation path is small, low operational risk, and already
delegates to a single service method:

`HousekeepingController::status()` -> `hospitality.manage` -> CSRF ->
`HousekeepingService::updateStatus(roomId, post)`.

Check-in/check-out, reservation transitions, and folio charge add/void carry
materially higher business risk (money, stay lifecycle) and are explicitly deferred.
Slice 1 proves ONE safe jailed mutation path before any expansion.

## 2. Authorization Model (no new permission key)

- Viewing the focus: app assignment to `hospitality` + `hospitality.view`
  (unchanged from the read-only surface).
- Mutating housekeeping status: additionally `hospitality.manage`.
- Workspace Profile / Experience Override composition can hide surfaces but can
  never grant authority. Every mutation request re-checks `hospitality.manage`
  server-side at the action edge.

## 3. Route And Confinement Contract

One bounded Shell seam mirrors the proven `/u/production/plan/status` precedent:

```text
POST /u/{username}/hospitality/housekeeping/status
```

- Username resolution happens through Shell's existing operator POST pipeline
  (`$resolveOperatorPostRoute('hospitality', false, 'hospitality')`): session,
  TV-display jail, hospitality assignment gate - all server-side.
- **Self-workspace binding**: a Hospitality operator mutation executes only when the
  requested `/u/{username}` belongs to the authenticated user (compared via Shell's
  `WorkspaceWrapperRegistry::handleFromIdentity`). Mismatched handles redirect to
  the AUTHENTICATED user's own operator dashboard before any handler runs.
  Privileged admin inspection of another user's workspace does NOT confer
  cross-user mutation authority - admins use the Hospitality admin surface for
  other-user changes. No new permission key models this; it is an ownership rule
  of the action edge itself.
- Success and error redirects always go to `/u/{username}/hospitality` (jailed).
  The admin controller's `/apps/hospitality/housekeeping` redirect must never be
  reused from the operator edge.
- No generic operator-action framework is introduced; exactly one route is added.

## 4. Business-Service Reuse (single source of truth)

The app-owned action edge (`Apps\Hospitality\Controllers\OperatorActions`)
performs ONLY:

1. `hospitality.manage` check (`AclPolicy::can`),
2. CSRF validation (`Auth::requireCsrf`, 419 on failure),
3. input forwarding to `HousekeepingService::updateStatus()`,
4. jailed success/error redirect with flash keys in session.

No duplicated status list, SQL, validation, or persistence logic exists anywhere;
`HousekeepingService::STATUSES` / `updateStatus()` remain the single domain truth.
`HousekeepingService` itself is not modified.

## 5. CSRF Model

Mandatory on every mutation attempt via `Auth::requireCsrf` with the jailed return
path; invalid/expired tokens render the platform's standard 419 response targeting
the same operator surface.

## 6. UI Behavior

`Views/operator/hospitality.php` gains a compact status select + save control per
housekeeping row, rendered only when the signed-in operator has
`hospitality.manage`. View-only users continue to see exactly the previous
read-only snapshot. The form action is jailed under `/u/{username}/*`, carries the
CSRF token, uses only canonical statuses from the service contract, needs no
JavaScript, adds no inline style, and reuses Shell/Hospitality styling primitives.
Success/error feedback renders from session flash keys using localized strings;
raw exception/internal codes are never shown.

Schema-pending state exposes no mutation control (form lives inside the rendered-
board branch only).

## 7. Audit Decision

Repository ground truth shows no sanctioned generic business-action audit mechanism
for admin mutations of this class (the Manufacturing operator/admin mutations write
through services/SQL without an audit sink). Per the no-invention rule this slice:

- does NOT create an audit table or schema,
- documents that the operator mutation uses identical semantics to the existing
  admin mutation (same service call),
- records richer business-action auditing as a platform-level follow-up.

## 8. Failure Behavior

Service validation failures surface as localized, generic flash messages
("Unable to update status"); specific internal error codes stay in logs/session
only. Permission denial yields the localized forbidden state; unassigned users are
redirected by the Shell pipeline before the handler runs.

## 9. Slice Ledger And Explicitly Deferred Actions

- **Slice 1 (landed): Housekeeping status update** - the proven pattern:
  assignment + own-handle binding + operator-view eligibility +
  `hospitality.manage` + CSRF + jailed redirect over the single domain service.
- **Slice 2 (landed): Front Desk check-in ONLY** - same pattern delegating to
  `FrontDeskService::checkIn()` (which itself delegates to
  `ReservationsService::transition()`). No checkout, no folio work.
- **Slice 3 (implemented): Front Desk check-out ONLY** - same confined pattern
  delegating to `FrontDeskService::checkout()`. Prerequisite hardening included:
  VOID (previously non-transactional/unserialized) is now aligned to the same
  pattern checkout/add-charge ALREADY used: one transaction, reservation-row
  FOR UPDATE serialization, rollback-on-failure. Behavioral forced-failure proof
  for void: a foreign lock on the reservation row makes the void time out and
  roll back atomically; retry after release fully succeeds.

Authorization clarification (supervisor-verified): app assignment controls
Hospitality AVAILABILITY (visibility/data-source eligibility); `hospitality.view`
and `hospitality.manage` remain EXPLICIT ACL authority. Assignment alone must never
be confused with manage authority. The supported provisioning path for explicit
authority is the assignment row's `permissions` field
(`user_dashboard_assignments.permissions`, e.g.
`hospitality.view,hospitality.manage`), honored through
`AclPolicy::effectivePermissionsForContext()` - verified working end-to-end.

## 10. Platform provisioning gap BLOCKING live route execution (disclosed)

The Base-owned resolved-experience view-token catalog
(`plugins/Base/Services/ResolvedExperienceDiagnosticsService.php::OPERATOR_VIEWS`)
is a hardcoded list without app-contributed focus slugs. Because the shared
operator view gate (`resolveOperatorGetRoute`/`resolveOperatorPostRoute`) requires
the focus token to appear in `ResolvedExperienceConsumerService::operatorViews()`,
every `/u/{username}/hospitality` request - GET focus AND operator actions alike -
is redirected to the dashboard before rendering/handler execution whenever the
token cannot be provisioned. No sanctioned UI can provision it today because the
catalog itself omits it.

Generic fix (IMPLEMENTED, uncommitted): `operatorCatalog()` now merges
app-contributed focus slugs discovered from the authoritative materialized
runtime source - `core_app_hooks` rows of enabled apps declaring
`{app}.operator.focus_views` (the sanctioned operator_surface/focus_views
contract). Safety rules: normalized-token validation; built-in tokens always win
collisions; duplicates collapse; disabled/uninstalled apps disappear via an
enabled-status join; unrelated hook regions ignored. The override normalizer
(`normalizeOperatorOverrideTokens`) accepts contributed tokens identically.
No Hospitality name appears anywhere in Base; no Core changes.

Verified behaviorally (real router closures in isolated child processes):
- GET /u/{username}/hospitality passes the resolved-experience gate and renders
  the contributed focus;
- own-handle Housekeeping update and Front Desk check-in mutate through their
  single domain services with jailed redirects;
- cross-handle, invalid-CSRF, unassigned, and view-only boundaries all deny with
  zero state change.
Authenticated browser smoke remains separate deferred evidence.

Authorization rule (restated): assignment = availability; `hospitality.view` /
`hospitality.manage` = explicit ACL authority provisioned via the assignment
`permissions` field or role matrix; manage remains mandatory for every mutation.

- **Slice 4 (implemented): Add Front Desk Charge ONLY** - canonical
  reservation-scoped command `FrontDeskService::addChargeForReservation()`
  (server-side checked_in enforcement closing the crafted-POST gap; atomic
  folio-create + charge-insert with reservation-row serialization); admin
  controller migrated to the same command; operator UI exposes one add-charge
  control per in-house row for manage holders using CHARGE_TYPES from the
  service.
- **Slice 5 (landed): Cancel booked reservation ONLY** - same confined pattern
  delegating to `FrontDeskService::cancelReservation()`
  (`POST /u/hospitality/front-desk/cancel`, `hospitality.manage` + CSRF + own-handle
  binding). Proof: `probe_operator_slice8_cancel.php` 21/21 (non-booked states
  rejected, unknown id rejected, cancellation creates no folio).
- **Slice 6 (landed): Mark booked reservation no-show ONLY** - same confined pattern
  delegating to `FrontDeskService::markReservationNoShow()`
  (`POST /u/hospitality/front-desk/no-show`). Proof: `probe_operator_slice9_no_show.php`
  18/18 (booked -> no_show succeeds, stay timestamps remain NULL, non-booked states
  rejected, unknown id rejected).
- **Charge void remains explicitly deferred**: destructive financial-record
  mutation requiring its own review (authorization/concurrency/audit).

Also still deferred: reservation create,
room/guest CRUD, housekeeping history/supply/maintenance workflows, staff
scheduling, new permissions, schema changes, shared-app dependencies, and any
generic operator-action framework beyond this bridge.

### Regression coverage correction (2026-09-12)

The eight operator probes were never registered in the aggregate runner, so the
operator action slices had no suite-level protection and slice 9's probe defects
(undefined `$resBk`, unsnapshotted `$beforeRows`, no fixture pre-cleanup) went
undetected: the canonical `booked -> no_show` assertion had never actually run.
Fixed in this session; `run_all_hospitality_probes.php` now runs foundation +
operator groups (16/16 groups, 494 assertions).
