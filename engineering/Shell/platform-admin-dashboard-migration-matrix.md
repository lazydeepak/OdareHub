# Platform Admin Dashboard → Unified Admin Migration Matrix

**Status:** Platform Admin technical parity verified — duplicate source removed
**Date:** 2026-07-13
**Removed source:** `/ops/platform-admin-dashboard`
**Target home:** `/admin/{username}` plus specialist System Tools/Governance views

## Migration law

The source dashboard remained operational until every unique control, metric, status, feedback path, and specialist link had a verified destination. After explicit removal approval, the duplicate route, aliases, links, candidate, and manifest declaration were deleted.

## Content placement matrix

| Source content/control | Current source | Upgraded destination | Migration action | Current decision |
|---|---|---|---|---|
| Production/Development/Demo mode badge | Role-dashboard hero | System Tools → Platform Mode and Platform Admin Status | Shared authoritative service; no duplicate mutation logic | Live parity verified |
| Platform mode selector | `platform_mode_selector.php` | System Tools → Platform Mode, with optional overview summary | Existing selector component reused at `/admin/system-tools/platform-mode`; legacy host retained | Implementing |
| Mode success/error feedback | Role-dashboard query notices | Final Platform Mode host | Fixed localized results; pre-mutation failure decision table | Live success/wrong-method verified; failures probe-verified |
| Active Apps metric | Platform dashboard KPI | System Tools → Apps & Package Lifecycle | Exact owner value links to Apps | Live parity: `5` |
| Installed Apps/Plugins metric | Platform dashboard KPI | System Tools → Apps & Package Lifecycle | Exact owner value links to Application Inventory | Live parity: `27` |
| Failed Migrations metric | Development-only KPI | System Tools → Apps/health specialists | Exact owner value and tone retained | Live parity: `0` |
| Schema Sync Gaps + Sync action | Development-only KPI/action | Unified snapshot → Base workspace handoff | Exact count retained; mutation deliberately stays with its guarded owner | Live parity: `0`; safety retained |
| Log Files / Log Size | Development-only KPIs | System Tools → Logs, Reports & Audit | Exact owner values link to specialist evidence | Live parity: `2` / `0.01 MB` |
| Access Control Board assignment count | Platform KPI | Governance → Platform Operations → Access Control | Exact owner value links to canonical specialist | Live parity: `1` |
| Operational scope row count | Platform KPI | Governance → Platform Operations → Access Control | Exact owner value links to canonical specialist | Live parity: `0` |
| Module visibility rule count | Platform KPI | Governance → Experience & Visibility | Exact owner value links to Navigation Tree | Live parity: `0` |
| Platform Admin Authority explanation | Dashboard section | Governance → Platform Operations | Identity, experience, communications, audit lanes separated | Implemented |
| PHP version | Environment section | System Tools → Environment & Release | Exact owner value and specialist handoff | Live parity: `8.3.32` |
| Runtime environment | Environment section | System Tools → Environment & Release | Infrastructure environment remains distinct from behavior mode | Live parity: `production` |
| Route integrity guidance | Environment section | System Tools → Health & Diagnostics → Architecture Health | Canonical specialist destination linked | Implemented |
| Parent Admin Surfaces | Dashboard quick links | Unified launcher, KPI handoffs, Platform Operations, and Catalog | Every unique destination remains reachable; no compatibility source deleted | Platform Admin live parity verified |
| Notification count/link | Dashboard hero/actions | Existing global notification UI | Global header notification surface remains canonical on both pages | Live parity verified |
| Backup/export monitoring placeholder | Dashboard placeholder | System Tools → Backup, Export & Restore Map | Unified read-only activity monitor consumes five authoritative histories and preserves specialist action owners | Promoted; placeholder removed |

## App Admin dashboard migration

The former `/ops/app-admin-dashboard` payload now renders completely inside Unified Admin. Assigned-app filtering occurs before Manufacturing KPI queries; non-Manufacturing and empty contexts receive an explicit fail-closed payload. Deterministic full-payload and scope probes verified parity before route deletion.

## Route disposition decision

| Route/surface | Decision | Reason |
|---|---|---|
| `/admin/{username}` | **Keep primary** | Role-aware launcher, owner KPI snapshot, assigned-app surfaces, and explicit degraded states. |
| `/ops/platform-admin-dashboard` | **Removed** | Technical parity was verified and the user explicitly approved deletion. Unified Admin and specialist workspaces own all former responsibilities. |
| `/ops/app-admin-dashboard` | **Removed** | Full KPI, scope, quick-link, placeholder, and empty-state parity is integrated into Unified Admin; deletion was explicitly continued by the user. |
| Platform/Admin dashboard aliases | **Keep redirects** | Preserve bookmarks and configured landing-page compatibility. |
| `/admin/platform-admin-links` | **Retain hidden compatibility** | Repaired and upgraded into a localized, shared-wrapper review page; link content is grouped by upgraded owner placement without joining primary navigation. |
| Resilience activity monitor | **Promoted** | AdminTools owns read-only aggregation; specialist services retain persisted records, mutations, downloads, retention, and retry behavior. |

## Implementation sequence

1. ✅ Extract a read-only platform status payload from the existing role-dashboard provider without duplicating queries.
2. ✅ Add platform-admin-only summary cards with direct specialist handoffs.
3. ✅ Add a System Tools Platform Mode view that reuses the existing service and selector behavior.
4. ✅ Move mode feedback and return routing to the specialist selector host.
5. ✅ Validate POST boundaries, CSRF decisions, confirmations, mode gates, and explicit degraded states.
6. ✅ Perform live parity comparison between the source dashboard and upgraded destinations.
7. Platform Admin and restricted-role contracts are verified; real App Admin signed-in acceptance remains fixture-blocked.
8. ✅ After explicit user approval, delete the duplicate source dashboard, aliases, declarations, and comparison links.

## Parity acceptance checklist

- [x] Mode status and deployment lock state are visible in Platform Admin Status and the specialist workspace.
- [x] All three platform modes can be selected through an authorized UI; Development was restored after the acceptance sequence.
- [x] Success and wrong-method feedback are live-verified; expired/missing CSRF, invalid mode, deployment lock, and return confinement are decision-table verified before mutation.
- [x] Unified KPI values come from the exact legacy dashboard provider; no queries are duplicated.
- [x] Schema Sync count has exact parity; the unified snapshot imports no mutation action, and the owner action keeps POST/CSRF/confirmation safety.
- [x] Development-only diagnostics remain governed by the existing `admin_diagnostics` feature rule; Developer launcher visibility is live-verified as hidden in Demo/Production and restored in Development.
- [x] Parent-surface navigation has no missing unique destination; the navigation duplicate diagnostic passes.
- [ ] App-admin experience is tested independently.
- [x] Source deletion occurred only after explicit user approval.

## Compatibility presentation

The duplicate Platform Admin Dashboard route and its platform-only aliases are removed. Navigation, widgets, Platform Operations, Platform Mode, specialist back-links, catalog evidence, mode feedback, and stored landing options now resolve to Unified Admin or their owning specialist. App Admin compatibility remains separate and retained.
