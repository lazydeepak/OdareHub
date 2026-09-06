# Manufacturing App Boundary Migration

Date: 2026-04-06

> Historical migration snapshot.
> This document records boundary migration status at the time of implementation.
> Current authoritative governance policy remains in `AGENTS.md` and `docs/architecture/*`.

## Objective

Make Manufacturing a removable business app so disable/uninstall/purge actions remove Manufacturing runtime and ownership artifacts together, while keeping core ERP platform services stable.

## Core Platform (Permanent)

The following remain permanent platform capabilities:

- Auth/session/users
- Access control and assignment engine
- Audit framework
- Files/storage
- App Manager and app registry lifecycle
- Route/navigation registry runtime
- Notification framework
- Approval/handoff framework
- Search framework
- Theme/language/currency
- Dashboard assignment engine

## Before (Hybrid Boundary)

Manufacturing ownership leaked outside the app boundary in these places:

- Legacy plugin bridge in app services kept manufacturing plugins available even when Manufacturing app was disabled/missing.
- Core dashboard config hardcoded Manufacturing widgets.
- Core handoff engine referenced Manufacturing app classes directly.
- App Manager had no app-level purge action.
- Manufacturing role dashboards in Base routes were always available regardless of Manufacturing app status.
- Legacy bridge plugin ownership list incorrectly included Bus (core platform plugin).

## After (App-Owned Boundary)

### Runtime ownership enforcement

- Manufacturing-owned legacy plugins now load only when Manufacturing app status is enabled.
- Disabling/uninstalling Manufacturing deactivates Manufacturing-owned legacy plugin runtime.
- Bus is no longer treated as Manufacturing-owned.

### Lifecycle behavior

- Disable:
  - Sets Manufacturing app modules/hooks disabled.
  - Deactivates Manufacturing legacy plugin runtime.
- Uninstall (soft):
  - Removes Manufacturing runtime from active app loading.
  - Keeps data/schema (non-destructive).
- Purge (destructive):
  - Deactivates and purges Manufacturing legacy plugins using plugin uninstall purge behavior.
  - Drops Manufacturing app-declared tables/columns from manifest purge policy.
  - Removes app runtime artifacts from core app registry tables.
  - Removes installed app directory and app registry row.

### Dashboard ownership

- Manufacturing leader/cockpit widgets were removed from core widget definitions.
- Equivalent widgets were moved to Manufacturing app manifest widget hooks.

### Handoff decoupling

- Core handoff engine no longer depends directly on Manufacturing classes.
- Manufacturing app registers resolver callbacks into the core handoff framework during app runtime load.

### Route gating

- Manufacturing-owned role dashboards routed from Base are now gated by Manufacturing app enabled state.
- When Manufacturing is disabled/uninstalled/purged, these routes return not found.

## Ownership Mapping

Manufacturing app now owns these runtime families:

- Demand/Coverage/Execution workspaces under apps/Manufacturing routes/services/controllers.
- Manufacturing navigation contract in apps/Manufacturing/navigation.php.
- Manufacturing widgets via app manifest hooks.
- Legacy bridged modules loaded only under Manufacturing app status:
  - Products
  - Machines
  - PartMachineMap
  - PreOrders
  - ProductionEntries
  - QCPlans
  - QCEntries
  - DispatchEntries
  - Ledger

## Legacy Compatibility Routes Kept Temporarily

These are still present and intentionally retained as redirects under Manufacturing app runtime:

- /dispatch -> /dispatch-entries
- /qc -> /qc-entries
- /production -> /manufacturing/production-queue
- /mfg -> /apps/manufacturing
- /assembly-plans -> /manufacturing/assembly-plans
- /ops/cockpit -> /manufacturing/cockpit
- /ops/production-dashboard -> /manufacturing/production-dashboard
- /ops/assembly-dashboard -> /manufacturing/assembly-dashboard
- /ops/qc-dashboard -> /manufacturing/qc-dashboard
- /ops/dispatch-dashboard -> /manufacturing/dispatch-dashboard

## Remaining Technical Debt / Blockers

- Physical code location is still mixed for legacy modules (plugins/*). Ownership is runtime-bound to Manufacturing app, but source files are not fully relocated under apps/Manufacturing yet.
- CoverageService and some operational semantics still live in core and should be moved behind app-owned resolvers/providers in a follow-up migration.
- Manufacturing role dashboard route handlers are still implemented in Base plugin; they are now gated, but can be fully moved into Manufacturing app in a later phase.
- Purge uses declarative table/column removal plus plugin uninstall purges; cross-app shared table ownership must be reviewed before enabling automatic purge in multi-app production installs.

## Recommended Next Phase

- Move legacy bridged plugin code into app-scoped modules under apps/Manufacturing/Modules.
- Replace plugin-level migrations with app-scoped module migrations and ownership tags.
- Move manufacturing role dashboard controllers/routes from Base into Manufacturing app runtime.
- Add app-owned search/notification provider registration contracts and remove remaining manufacturing assumptions from core defaults.
