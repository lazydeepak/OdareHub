# Migration Inventory (Target Architecture)

Date: 2026-04-18
Owner: Repository Architecture Governance
Status: Inventory only (no runtime moves in this change)

Update 2026-05-22: `apps/Shell` and `apps/Platform` now have manifests, routes, navigation files, and local ownership guidance. The remaining gap is explicit system-app contract semantics, documented in `docs/architecture/shell-platform-system-app-contract-gap.md`; do not treat the older "missing manifest" rows below as permission to redesign routes or move runtime behavior.

## Target Ownership Model

- /app -> Core Engine
- /apps/Shell -> Shell System App
- /apps/Platform -> Platform System App
- /apps/Manufacturing -> Business App
- /apps/SBAIO -> Business App
- /plugins -> Cross-cutting Plugins
- /packages -> Package Manager

## Decision Legend

- Keep: keep in current location for now.
- Move now: tiny non-breaking ownership prep recommended now.
- Move later: planned migration after contract and compatibility gates.

## A. Current Top-Level Ownership Snapshot

| Current path | Current responsibility | Target owner | Decision | Risks/dependencies |
| --- | --- | --- | --- | --- |
| /app/Core | Kernel, routing, auth/session, renderer, registry runtime | Core Engine | Keep | Core lock policy; no business logic leakage allowed |
| /app/Navigation | Shared navigation composition config and module map | Shell System App (final), Core Engine (temporary host) | Move later | SidebarBuilder currently reads these files directly; requires Shell contract extraction |
| /app/Dashboard | Shared home widget config and composition | Shell System App (final), Core Engine (temporary host) | Move later | DashboardBuilder currently in core with Base coupling |
| /app/Actions | Shared action definitions for cards/zones | Shell System App (final), Core Engine (temporary host) | Move later | ActionBuilder and route-existence checks rely on current path |
| /apps/Shell | No runtime files yet (AGENTS only) | Shell System App | Move now | Missing manifest/routes/navigation contract blocks ownership transition |
| /apps/Platform | No runtime files yet (AGENTS only) | Platform System App | Move now | Platform governance currently implemented under Base plugin routes |
| /apps/Manufacturing | Canonical business app runtime, routes, services, views, manifest | Business App | Keep | Legacy alias load and plugin bridge still active |
| /apps/SBAIO | Canonical business app runtime, routes, services, views, manifest | Business App | Keep | Legacy plugin bridge still active for module routes |
| /plugins/Base | Cross-cutting plus many platform and shell-facing surfaces | Cross-cutting Plugins (strict) + Platform/Shell extraction target | Move later | Mixed responsibilities; high blast radius for /me and /ops |
| /plugins/AdminTools, /plugins/ACL, /plugins/Audit, /plugins/Bus | Operational cross-cutting plugin features | Cross-cutting Plugins | Keep | Verify no business route drift into plugin scope |
| /packages | Package artifacts and lifecycle storage | Package Manager | Keep | Runtime lifecycle APIs live in /app services and PackageManager core service |

## B. Major Route Surface Classification

| Current path | Current responsibility | Target owner | Decision | Risks/dependencies |
| --- | --- | --- | --- | --- |
| plugins/Base/routes.php:/me | Personal workspace landing orchestration and host-surface merge | Shell System App (surface), Platform System App (policy), Base plugin (cross-cutting helpers) | Move later | Used by role assignment, host regions, notification and admin panel injection |
| plugins/Base/routes.php:/ops/platform-admin-dashboard | Platform governance dashboard | Platform System App | Move later | Dependent on RoleDashboardsController and assignment context logic |
| plugins/Base/routes.php:/ops/access-control | Access control board | Platform System App | Move later | Security-critical assignment operations and CSRF enforcement |
| plugins/Base/routes.php:/ops/user-control | User control board/detail | Platform System App | Move later | Used by recovery/setup link flows and assignment normalization |
| plugins/Base/routes.php:/ops/audit-log | Audit explorer route dispatch | Platform System App | Move later | Depends on AuditLogService scope filtering and ACL |
| app/AppManager/routes.php:/admin/app-manager* and /admin/setup* | App lifecycle/setup admin routes | Platform System App | Move later | Controller namespace currently under /app/AppManager; policy and setup state coupling |
| apps/Manufacturing/routes.php:/apps/manufacturing/* | Canonical manufacturing app routes | Business App (Manufacturing) | Keep | Keep aliases until compatibility window closes |
| apps/Manufacturing/Routes/*.php:/manufacturing/* and legacy root aliases | Compatibility and transition routes for manufacturing | Business App (Manufacturing) | Move later | Existing bookmarks, role dashboards, and runtime contract entries rely on aliases |
| apps/SBAIO/routes.php:/apps/sbaio/* | Canonical SBAIO routes | Business App (SBAIO) | Keep | Legacy module plugin loading remains for bridge continuity |

## C. View and Shared Renderer Surface Classification

| Current path | Current responsibility | Target owner | Decision | Risks/dependencies |
| --- | --- | --- | --- | --- |
| app/Core/View.php | Core rendering engine, namespaced view resolution, layout wrapping | Core Engine | Keep | Foundation renderer; protected by core lock |
| public/views/layouts/* | Shared app/auth layout chrome and shells | Shell System App (final), Core Engine (temporary host) | Move later | Used globally by View.php; move requires stable shell contract |
| public/views/partials/ownership_summary.php | Shared ownership lane summary partial | Platform System App (domain), Shell System App (render slot) | Move later | Consumed in multi-surface operational pages |
| public/views/partials/ownership_workboard.php | Shared ownership workboard partial | Platform System App (domain), Shell System App (render slot) | Move later | Referenced by governance and manufacturing oversight surfaces |
| plugins/Base/Views/ops/* | /ops dashboards, user-control and admin boards | Platform System App | Move later | Tight coupling with RoleDashboardsController and assignment services |
| plugins/Base/Views/auth/* | Login/setup/reset account auth pages | Shell System App | Move later | Must coordinate with Auth flows and setup lifecycle |
| apps/Manufacturing/Views/* | Manufacturing imports/exports/restores pages | Business App (Manufacturing) | Keep | Already app-owned |
| apps/SBAIO/Views/* | SBAIO dashboard and lifecycle pages | Business App (SBAIO) | Keep | Already app-owned |

## D. Service and Composition Surface Classification

| Current path | Current responsibility | Target owner | Decision | Risks/dependencies |
| --- | --- | --- | --- | --- |
| app/Core/SidebarBuilder.php | Sidebar composition, visibility, dynamic source merge | Shell System App (final), Core Engine (temporary host) | Move later | Reads /app/Navigation/sidebar.php and registry data; broad route coupling |
| app/Core/DashboardBuilder.php | Home zone/widget assembly and action attachment | Shell System App (final), Core Engine (temporary host) | Move later | Imports Base service for assignment-aware filtering |
| app/Core/Router.php | Route registration and dispatch engine | Core Engine | Keep | Core lock; global runtime dependency |
| app/Services/AppRuntimeLoader.php + registry services | App runtime discovery/load and registration | Core Engine | Keep | Required by app manifest contract and boot pipeline |
| plugins/Base/Services/HostSurfaceRegistryService.php | Host surface contribution registry and merge | Shell System App (surface contract) + Cross-cutting plugin hooks | Move later | Shared by /me and app host surfaces |
| plugins/Base/Services/UserDashboardAssignmentService.php | Assignment context, dashboard routing, quick link normalization | Platform System App | Move later | Used by SidebarBuilder, DashboardBuilder, RoleDashboardsController, /me flow |
| plugins/Base/Services/MyWorkService.php | Core workboard data for /me | Shell System App (presentation) + Platform System App (governance state) | Move later | Critical home flow and operator scope calculations |
| apps/Manufacturing/Services/HostSurfaceContributionService.php | Manufacturing contributions into host surfaces | Business App (Manufacturing) | Keep | Hook contract already app-owned |
| apps/SBAIO/Services/HostSurfaceContributionService.php | SBAIO contributions into host surfaces | Business App (SBAIO) | Keep | Hook contract already app-owned |

## E. Manifest and Contract Surface Classification

| Current path | Current responsibility | Target owner | Decision | Risks/dependencies |
| --- | --- | --- | --- | --- |
| apps/Manufacturing/manifest.json | Business app contract: routes, hooks, menus, widgets, runtime contract | Business App (Manufacturing) | Keep | Contains compatibility aliases that must be phased safely |
| apps/SBAIO/manifest.json | Business app contract and host-surface hooks | Business App (SBAIO) | Keep | Legacy bridge plugin list still active |
| apps/Shell/manifest.json | Shell app contract exists; explicit system-app ownership/lifecycle semantics still need additive contract fields | Shell System App | Move later | Missing explicit system-app contract fields can cause ownership drift |
| apps/Platform/manifest.json | Platform app contract exists; explicit system-app ownership/lifecycle semantics still need additive contract fields | Platform System App | Move later | Missing explicit system-app contract fields can cause governance ownership drift |
| plugins/*/plugin.json | Plugin metadata and lifecycle declarations | Cross-cutting Plugins | Keep | Must remain removable and non-business |

## F. Shell vs Platform vs Base (Focused Findings)

1. Shell ownership is not yet instantiated in runtime:
- /apps/Shell has only AGENTS guidance and no manifest/routes/navigation code.
- Shell composition currently executes via core builder services and Base route/view wiring.

2. Platform ownership is partially implemented but physically misplaced:
- /apps/Platform has no runtime files.
- Platform governance routes and views are in plugins/Base routes/controllers/views.

3. Base plugin currently mixes cross-cutting and system-app responsibilities:
- Cross-cutting pieces: notifications, host-surface registry, shared utilities.
- System-app pieces currently in Base: /ops platform dashboards, access control, user control, and admin governance navigation.

4. Manufacturing and SBAIO are closer to target ownership:
- They already own manifests and canonical /apps/{app}/ routes.
- They still carry significant compatibility aliases and plugin bridge dependencies.

## G. Recommended Tiny Non-Breaking Prep (Now)

These are ownership prep actions only (no runtime behavior move):

1. Add minimal app contract stubs for /apps/Shell and /apps/Platform:
- manifest.json with app_key, type=system, entry, empty routes/hooks arrays.
- navigation.php placeholders with contract key and empty items.
- routes.php placeholders that register no business routes.

2. Introduce explicit target-owner tags in existing Base governance route definitions:
- Mark /ops platform-admin, access-control, user-control as Platform migration-owned.
- Mark /me shell composition points as Shell migration-owned.

3. Create migration tracker IDs for each move-later surface in this document and use them in follow-up PRs.

## H. Deferred Move Queue (Execution Order)

1. Shell contract extraction:
- Move navigation, widget, and action composition ownership from /app configs to /apps/Shell contracts.

2. Platform governance extraction:
- Move /ops platform governance controllers/views/routes from plugins/Base to /apps/Platform with compatibility redirects.

3. Base plugin hardening:
- Retain only cross-cutting provider/utility behavior; remove system-app ownership.

4. Compatibility alias retirement:
- Remove legacy /manufacturing/* and root aliases after usage observation and comms window.

## AGENT-COMPLIANCE-CHECKLIST

Core touched? NO

AGENTS.md followed? YES

All routes under /apps/{app}? PARTIAL (legacy compatibility aliases intentionally retained)

Hardcoded UI removed? NO CHANGE (inventory-only task)

Server-side auth enforced? NO CHANGE (no runtime edits)

Orphan code removed? NO CHANGE (inventory-only task)

Risks remaining:
- Platform governance surfaces currently live in plugins/Base instead of /apps/Platform.
- Shell composition ownership still hosted under /app and Base-adjacent runtime.
- Manufacturing compatibility aliases remain broad and still marked as canonical in some runtime contract entries.
- /apps/Shell and /apps/Platform missing manifests/contracts increase migration drift risk.
