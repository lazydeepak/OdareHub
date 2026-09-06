# Manufacturing Phase 2 Route Normalization

Date: 2026-04-06

> Historical migration snapshot.
> This document records Phase 2 transition state and compatibility mappings at that time.
> Current authoritative routing policy is defined in `docs/architecture/ROUTING-STANDARD.md`.
> If this file conflicts with current policy, follow `docs/architecture/ROUTING-STANDARD.md`.

## Canonical Route Policy

Policy implemented in this phase:

1. Every Manufacturing capability has one canonical Manufacturing-owned route.
2. Compatibility/UX aliases are allowed, but aliases remain owned by Manufacturing.
3. Canonical + aliases are registered by the Manufacturing app runtime, so disable/uninstall/purge removes all of them together.
4. Navigation and search should point to canonical routes; aliases remain for backward compatibility.

## Current Route Classification (Runtime Source of Truth)

Legend:
- Canonical = primary app-native route
- Alias = intentional redirect to canonical route
- Legacy leftover = low-value historical alias (removed in this phase)

| Route | Classification | Canonical Target | Notes |
|---|---|---|---|
| /apps/manufacturing | Canonical | self | Manufacturing portal entry |
| /manufacturing/demands | Canonical | self | Demand workspace |
| /manufacturing/coverage | Canonical | self | Coverage dashboard |
| /manufacturing/production-queue | Canonical | self | Queue surface |
| /manufacturing/qc-queue | Canonical | self | Queue surface |
| /manufacturing/dispatch-ops | Canonical | self | Dispatch ops surface |
| /manufacturing/assembly-queue | Canonical | self | Queue surface |
| /manufacturing/assembly-plans | Canonical | self | Assembly plans surface |
| /manufacturing/cockpit | Canonical | self | Supervisor cockpit |
| /manufacturing/production-dashboard | Canonical | self | Role dashboard |
| /manufacturing/assembly-dashboard | Canonical | self | Role dashboard |
| /manufacturing/qc-dashboard | Canonical | self | Role dashboard |
| /manufacturing/dispatch-dashboard | Canonical | self | Role dashboard |
| /daily-orders | Canonical | self | Retained as business module canonical |
| /production-plans | Canonical | self | Retained as business module canonical |
| /products | Canonical (bridged) | self | Retained while Products is bridged under Manufacturing ownership |
| /dispatch | Alias | /dispatch-entries | Legacy shorthand retained |
| /qc | Alias | /qc-entries | Legacy shorthand retained |
| /production | Alias | /manufacturing/production-queue | Legacy shorthand retained |
| /mfg | Alias | /apps/manufacturing | Legacy shorthand retained |
| /assembly-plans | Alias | /manufacturing/assembly-plans | Bookmark compatibility |
| /ops/cockpit | Alias | /manufacturing/cockpit | Temporary UX/compat alias |
| /ops/production-dashboard | Alias | /manufacturing/production-dashboard | Temporary UX/compat alias |
| /ops/assembly-dashboard | Alias | /manufacturing/assembly-dashboard | Temporary UX/compat alias |
| /ops/qc-dashboard | Alias | /manufacturing/qc-dashboard | Temporary UX/compat alias |
| /ops/dispatch-dashboard | Alias | /manufacturing/dispatch-dashboard | Temporary UX/compat alias |
| /ops/production-leader-dashboard | Alias | /manufacturing/production-dashboard | Legacy role alias retained |
| /ops/assembly-leader-dashboard | Alias | /manufacturing/assembly-dashboard | Legacy role alias retained |
| /ops/qc-leader-dashboard | Alias | /manufacturing/qc-dashboard | Legacy role alias retained |
| /ops/dispatch-leader-dashboard | Alias | /manufacturing/dispatch-dashboard | Legacy role alias retained |
| /assy-plan | Legacy leftover | /manufacturing/assembly-plans | Removed |
| /assy-plans | Legacy leftover | /manufacturing/assembly-plans | Removed |
| /manufacturing/assy-plan | Legacy leftover | /manufacturing/assembly-plans | Removed |
| /manufacturing/assy-plans | Legacy leftover | /manufacturing/assembly-plans | Removed |

## Canonical Route Map

| Capability | Canonical Route |
|---|---|
| Manufacturing portal | /apps/manufacturing |
| Demand workspace | /manufacturing/demands |
| Coverage dashboard | /manufacturing/coverage |
| Production queue | /manufacturing/production-queue |
| QC queue | /manufacturing/qc-queue |
| Dispatch ops | /manufacturing/dispatch-ops |
| Assembly queue | /manufacturing/assembly-queue |
| Assembly plans | /manufacturing/assembly-plans |
| Supervisor cockpit | /manufacturing/cockpit |
| Production dashboard | /manufacturing/production-dashboard |
| Assembly dashboard | /manufacturing/assembly-dashboard |
| QC dashboard | /manufacturing/qc-dashboard |
| Dispatch dashboard | /manufacturing/dispatch-dashboard |

## Alias Keep/Remove List

Keep:
- /dispatch
- /qc
- /production
- /mfg
- /assembly-plans
- /ops/cockpit
- /ops/production-dashboard
- /ops/assembly-dashboard
- /ops/qc-dashboard
- /ops/dispatch-dashboard
- /ops/production-leader-dashboard
- /ops/assembly-leader-dashboard
- /ops/qc-leader-dashboard
- /ops/dispatch-leader-dashboard

Removed:
- /assy-plan
- /assy-plans
- /manufacturing/assy-plan
- /manufacturing/assy-plans

## Remaining Source-Boundary Violations

These are still intentionally transitional and should be addressed in the next phase:

1. Manufacturing dashboard route handlers still call Base plugin classes/services:
   - apps/Manufacturing/Routes/role_dashboards.php
   - plugins/Base/Controllers/RoleDashboardsController.php
   - plugins/Base/Services/SupervisorCockpitService.php
   - public/views/plugins/Base/supervisor_cockpit.php
2. Manufacturing workboard routes still call plugin controllers directly:
   - apps/Manufacturing/Routes/workboards.php
   - plugins/Machines/Controllers/MachinesController.php
   - plugins/QCEntries/Controllers/QCEntriesController.php
   - plugins/DispatchEntries/Controllers/DispatchEntriesController.php
3. Manufacturing still bridges plugin module routes under app ownership:
   - apps/Manufacturing/routes.php (legacy bridge plugin loading)

## Architectural Recommendation: /ops vs /manufacturing

Recommendation implemented:
- Use /manufacturing/* for Manufacturing-owned canonical business surfaces.
- Keep /ops/* as compatibility aliases while links/bookmarks migrate.

Rationale:
- Ownership clarity: app namespace shows domain owner explicitly.
- Lifecycle correctness: aliases and canonicals are both app-owned and disappear together.
- Platform flexibility: /ops can later be promoted as a true cross-app composition layer without Manufacturing ownership ambiguity.

## Validation Status

Post-change checks:
- Canonical dashboard routes load.
- /ops dashboard routes load as aliases.
- assy* aliases are absent.
- Lifecycle matrix remains green: 174 PASS / 0 FAIL / 5 SKIP.
