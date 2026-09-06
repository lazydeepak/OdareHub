# Owner Capability Inventory (Phase 1)

## Status
- Phase 1 inventory artifact for [experience-composition-architecture-plan.md](experience-composition-architecture-plan.md)
- Conforms to [owner-capability-catalog-contract.md](owner-capability-catalog-contract.md)
- Snapshot captured 2026-05-19 from `Plugins\Base\Services\ResolvedExperienceDiagnosticsService::capabilityCatalog()`
- Read-only inventory; no code restructuring in this artifact.

## Source Of Truth (Today)

All capabilities below are currently **code-owned** with hardcoded definitions in:
- `plugins/Base/Services/ResolvedExperienceDiagnosticsService.php` — operator + display catalogs
- `plugins/Base/Services/UserDashboardAssignmentService.php` — `meDashboardBlockCatalog()` + `mePluginCardCatalog()` consumed by `adminCatalog()`

Target ownership (Phase 1+): move per-app/module capabilities into their owning app/module's manifest area. Platform-owned capabilities stay in Platform. See contract for the migration target.

## Operator Surface (`/u/*`) — 25 capabilities

### Platform-owned (9)

| Key | Token | Route | Override field |
|---|---|---|---|
| operator.view.dashboard | dashboard | /u/{user}/dashboard | operator_views |
| operator.view.work-entry | work-entry | /u/{user}/work-entry | operator_views |
| operator.view.data-exchange | data-exchange | /u/{user}/data-exchange | operator_views |
| operator.view.critical | critical | /u/{user}/critical | operator_views |
| operator.view.recent | recent | /u/{user}/recent | operator_views |
| operator.view.tasks | tasks | /u/{user}/tasks | operator_views |
| operator.view.handoff | handoff | /u/{user}/handoff | operator_views |
| operator.view.account | account | /u/{user}/account | operator_views |
| operator.view.notifications | notifications | /u/{user}/notifications | operator_views |
| operator.view.messages | messages | /u/{user}/messages | operator_views |
| operator.view.preferences | preferences | /u/{user}/preferences | operator_views |

(11 entries — platform shell + cross-cutting operator views)

### Manufacturing-module-owned (13)

| Key | Token | Required module | Route |
|---|---|---|---|
| operator.view.production | production | production | /u/{user}/production |
| operator.view.demand | demand | demand | /u/{user}/demand |
| operator.view.orders | orders | orders | /u/{user}/orders |
| operator.view.parts | parts | parts | /u/{user}/parts |
| operator.view.coverage | coverage | coverage | /u/{user}/coverage |
| operator.view.machines | machines | machines | /u/{user}/machines |
| operator.view.processing | processing | processing | /u/{user}/processing |
| operator.view.assembly | assembly | assembly | /u/{user}/assembly |
| operator.view.qc | qc | qc | /u/{user}/qc |
| operator.view.fulfillment | fulfillment | dispatch | /u/{user}/fulfillment |
| operator.view.preparation | preparation | preparation | /u/{user}/preparation |
| operator.view.dispatch | dispatch | dispatch | /u/{user}/dispatch |
| operator.view.materials | materials | materials | /u/{user}/materials |

All require `assigned_apps` to include `manufacturing`.

### SBAIO-module-owned (1)

| Key | Token | Route |
|---|---|---|
| operator.view.sbaio | sbaio | /u/{user}/sbaio |

Requires `assigned_apps` to include `sbaio`.

## Display Surface (`/displays/*`) — 5 capabilities

All Manufacturing-owned, override field `display_surfaces`.

| Key | Token | Required module |
|---|---|---|
| display.panel.overview | overview | — |
| display.panel.machines | machines | machines |
| display.panel.dispatch | dispatch | dispatch |
| display.panel.qc | qc | qc |
| display.panel.activity | activity | activity |

All require `assigned_apps` to include `manufacturing`. Route: `/displays/user/{user}`.

## Admin Surface (`/admin/*` and legacy `/me`) — 20 capabilities

### Dashboard Blocks — 10 entries (override field `me_dashboard_blocks`)

| Key | Token | Owner |
|---|---|---|
| admin.block.admin_dashboard_panels | admin_dashboard_panels | app:platform |
| admin.block.top_navigation_module_launcher | top_navigation_module_launcher | module:manufacturing |
| admin.block.global_controls | global_controls | module:manufacturing |
| admin.block.search_alerts | search_alerts | module:manufacturing |
| admin.block.operational_summary | operational_summary | module:manufacturing |
| admin.block.primary_work_widgets | primary_work_widgets | module:manufacturing |
| admin.block.monitoring_widgets | monitoring_widgets | module:manufacturing |
| admin.block.detailed_work_tables | detailed_work_tables | module:manufacturing |
| admin.block.platform_admin_tools | platform_admin_tools | app:platform |
| admin.block.plugin_dashboards_charts | plugin_dashboards_charts | module:manufacturing |

Manufacturing ownership of most admin dashboard blocks is a known inheritance from the `/me` era — they will be re-evaluated as Phase 5 moves admin runtime onto ResolvedExperience. Some of these are arguably platform-owned (operational summary, monitoring) and should be revisited during the manifest migration.

### Quick Link Cards — 10 entries (override field `me_plugin_cards`)

| Key | Owner | Route | Required permissions |
|---|---|---|---|
| admin.card.approval_inbox | app:manufacturing | /admin/{user} | — |
| admin.card.notifications | app:manufacturing | /admin/{user} | — |
| admin.card.cross_role_handoff | app:manufacturing | /admin/{user} | — |
| admin.card.platform_admin_dashboard | app:platform | /ops/platform-admin-dashboard | platform.admin |
| admin.card.platform_setup | app:platform | /admin/setup | platform.admin |
| admin.card.access_control_board | app:platform | /ops/access-control | platform.admin |
| admin.card.user_control_board | app:platform | /ops/user-control | platform.admin |
| admin.card.user_dashboard | app:platform | /ops/user-dashboard | platform.admin |
| admin.card.admin_tools | app:platform | /admin/apps | platform.admin |
| admin.card.route_diagnostics | app:platform | /admin/routes | platform.admin |

## Ownership Classification Summary

| Classification | Today | Target after Phase 1+ |
|---|---|---|
| Code-owned (hardcoded constants/arrays) | **all 50 capabilities** | platform-owned only |
| Manifest-owned (app/module declaration) | 0 | manufacturing + sbaio capabilities |
| DB-owned | 0 | none planned for capability declarations |
| Studio-generated | 0 | possible for derivative views/widgets only; never primary capability declarations |

## Migration Order Recommendation

1. **Manufacturing operator views (13)** — highest concentration, cleanest grouping. Move first into `apps/Manufacturing/.../capabilities/` once a registry exists.
2. **Display panels (5)** — small set, same Manufacturing ownership pattern.
3. **SBAIO view (1)** — trivially follows the same shape.
4. **Platform operator views (11)** — should remain in Platform but be declared explicitly rather than embedded in diagnostics service.
5. **Admin dashboard blocks (10)** — re-evaluate owner per block before moving; current Manufacturing ownership of monitoring/operational summary blocks is suspect.
6. **Quick link cards (10)** — already cleanly split by `adminCardOwner()` helper; lift declaration alongside.

## Known Gaps

- **Localization keys**: no entry currently carries a `localization_key` field; labels are passed through `meDashboardBlockCatalog()` / `mePluginCardCatalog()` which already localize, but operator/display labels are token-only. Adding `localization_key` should accompany the manifest migration, not block this inventory.
- **`required_permissions`**: only admin quick-link cards declare them today. Operator and display surfaces rely on `authority_role` filtering at the ACL layer. Permission strings will become more important as Workspace Profile shaping grows.
- **`route` precision**: many admin entries point to `/admin/{user}` (the surface root), not a per-block deep link. This reflects current rendering reality but loses precision for diagnostics.
