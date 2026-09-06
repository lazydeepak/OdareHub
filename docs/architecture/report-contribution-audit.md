# Report Contribution Audit

Status: Verification audit. Read-only. No runtime changes, DB changes, UI changes, or implementation authorized.

Purpose: Verify whether existing Susankhya OS contribution mechanisms are sufficient for report discovery, exposure, navigation, dashboard integration, display integration, and export access. This is a verification audit — it does not design a new contribution system or create a new `report_contributions` type unless a proven gap exists.

---

## 1. Existing Contribution Inventory

### 1.1 All contribution mechanisms

| # | Mechanism | Declared in | Metadata fields | Can carry external ref? |
|---|---|---|---|---|
| 1 | **Navigation entries** | `navigation.php` per app | `source_key`, `key`, `feature_key`, `module_token`, `label`, `url`, `menu_key`, `visible_if`, `nav_visible`, `order`, `priority`, `always_visible`, `style`, `active_patterns`, `type` | **No native `report_key` field.** Closest fields: `key`, `feature_key`, `source_key`. None defined for report references. |
| 2 | **Dashboard blocks (Manufacturing)** | `ManufacturingDashboardBlockService` | `block_key`, localized `label` | **No.** Simple key-value map only. |
| 3 | **Admin activity/panel blocks** | `AdminActivityDashboardBlockService`, `AdminDashboardPanelBlockService` | `module`, `source`, `title`, `subtitle`, `status`, `url`, `key`, `cards[]` | **Potentially.** Cards have `key` but no `report_key` reference field. |
| 4 | **Workspace Profile (DB JSON)** | `workspace_profiles` table (Base plugin) | `profile_key`, `name`, `landing_route`, `nav_sections[]`, `quick_actions[]`, `widget_discovery[]`, `dashboard_blocks[]` | **Yes.** `quick_actions` and `dashboard_blocks` are extensible JSON arrays. Could carry `report_key`. |
| 5 | **Widget declarations** | `*WidgetRegistry.php` per module + `ModuleSurfaceWidgetFactory` | `widget_key`, `view_kind`, `widget_type`, `placement_zone`, `interaction_profiles`, `title`, `value`, `meta`, `url`, `tone`, `weight`, `surface_key`, `rows[]`, `columns[]`, `toolbar_actions[]`, `config` (includes `report_url`, `export_url`) | **Yes.** Arrays are freeform. Already carries `report_url` via config. `report_key` could be added. |
| 6 | **Host surface hook registry** | `HostSurfaceRegistryService` (DB `core_app_hooks`) | `widget_key`, `widget_type`, `view_kind`, `placement_zone`, `interaction_profiles`, `access_authorities`, `permission_profile`, `surface_key`, `app_key`, `priority`, `region`, `url`, `rows[]`, `toolbar_actions[]` | **Yes.** Provider payloads are freeform arrays that pass through normalization. |
| 7 | **Operator adapters** | `OperatorLayerAdapters/*Adapter.php` | Adapter-specific (varies: `summary`, `kpi`, `rows`, `risk_bands`, etc.) | **Yes.** Return arbitrary arrays. Could include `report_key` in data payload. |
| 8 | **Display panels** | `DisplaySurfaceComposer` + `floor.php` | Boolean toggles (`overview`, `machines`, `dispatch`, `qc`, `activity`) | **Not natively.** Panels are configured as simple string toggles, not extensible structures. |
| 9 | **Quick actions** | Workspace Profile DB JSON + `OperatorWorkboardService` | `label`, `url`, `icon` (profile); `action_label`, `action_url`, `record_id` (workboard) | **Yes.** JSON arrays are extensible. Could carry `report_key`. |
| 10 | **Operator widget provider catalog** | `OperatorWidgetProviderContributionService` | `provider_name`, `module_key`, `provider_key`, `class`, `file` | **No.** Registration catalog only — no data fields. |
| 11 | **plugin.json `reports[]`** | `plugin.json` per module | `report_key`, `title`, `view`, `export_view`, `owner`, `scope`, `permission`, `lifecycle` | **YES — this is the canonical `report_key` declaration.** |
| 12 | **ModuleReportRegistryService** | Per-app service (Platform, Manufacturing, SBAIO) | `module`, `report_key`, `title`, `owner`, `scope`, `permission`, `view`, `export_view`, `lifecycle`, `module_dir` | **YES — reads `report_key` from plugin.json and serves it at runtime.** |

### 1.2 Contribution type summary (from Surface Contribution Contract)

The Surface Contribution Contract defines these contribution types:

| Contribution type | `report_key` fit |
|---|---|
| Navigation / sidebar | Could carry `report_key` as optional cross-reference |
| Operator widgets & cards | Could carry `report_key` via existing freeform arrays |
| Admin cards & blocks | Could carry `report_key` via existing card structures |
| Display panels | Could carry `report_key` if panel structure extended |
| **Reports** | **Already has `report_key` — this is the canonical declaration** |
| Actions, buttons, links | Could carry `report_key` |
| CSS and assets | Not applicable |
| Permissions | Not applicable |
| Workspace & landing | Could carry `report_key` |
| Integration / capability | Not applicable |

---

## 2. Report Exposure Mapping

### 2.1 How the 32 reports become visible to users today

| Exposure path | Reports using it | How user reaches it |
|---|---|---|
| **Direct URL** (typed or bookmarked) | All 29 routable reports | User must know or guess URL pattern `/{app}/{module}/report` |
| **From module operational page** | All 29 routable reports | User navigates to module page (e.g., Coverage dashboard), appends `/report` to URL |
| **SBAIO Analytics hub** (sidebar entry) | 0 individual report links | SBAIO sidebar has nav entry `sbaio_reports → /apps/sbaio/reports`, but this page runs its own DB queries and does not reference individual report_keys |
| **Report → Export cross-link** | All 29 routable reports | Report view template contains "Open Export" link to `/export` route |
| **Admin bulk export pages** | All Manufacturing + SBAIO modules | `/apps/manufacturing/exports`, `/apps/sbaio/exports` — app-level export system |
| **Navigation sidebar** | **0 reports** | No navigation entry references any individual `/report` route |
| **Dashboard cards** | **0 reports** | No dashboard block references a report_key |
| **Widgets** (operator/admin) | **0 reports** | No widget declaration references a report_key |
| **Display panels** | **0 reports** | No display panel references a report_key |
| **Quick actions** | **0 reports** | No quick action references a report route |

### 2.2 Report-to-exposure mapping (complete)

| report_key | Route handler? | Nav entry? | Dashboard card? | Widget? | Display? | Export? |
|---|---|---|---|---|---|---|
| manufacturing.coverage.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.daily_orders.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.production_plans.next_two_weeks | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.dispatch_entries.execution | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ PDF+HTML |
| manufacturing.assembly_plans.readiness | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.assembly_entries.execution | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.qcentries.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.qc_plans.range | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ PDF+HTML |
| manufacturing.machines.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.material_management.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.products.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.production_queue.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.production_entries.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.pre_orders.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.part_machine_map.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.ledger.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| manufacturing.workflow.overview | ❌ (stale) | ❌ | ❌ | ❌ | ❌ | ❌ |
| manufacturing.supply.overview | ❌ (stale) | ❌ | ❌ | ❌ | ❌ | ❌ |
| platform.organization.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| platform.qrcode.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ HTML |
| platform.company_setup.overview | ❌ (stale) | ❌ | ❌ | ❌ | ❌ | ❌ |
| sbaio.attendance.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.customers.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.expenses.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.leave.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.notices.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.payroll.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.sales.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.schedules.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.staff.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.tasks.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |
| sbaio.timecards.overview | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ CSV+HTML |

**Totals**: 32 declared, 29 routable, 3 stale, 0 with nav entry, 0 with dashboard card, 0 with widget, 0 with display panel.

### 2.3 Current exposure architecture

```text
plugin.json reports[]
  │
  └─ ModuleReportRegistryService (compilation + registry)
      │
      ├─ Route handler (manually calls findActiveReport)
      │   └─ Renders report view / export view
      │
      └─ No automatic exposure to:
          ├─ Navigation (no sidebar entries)
          ├─ Dashboard (no cards)
          ├─ Widgets (no widget keys)
          ├─ Operator surfaces (no adapter refs)
          └─ Display panels (no panel refs)
```

---

## 3. `report_key` Reference Capability

### 3.1 Per-mechanism assessment

| Mechanism | Can reference `report_key` now? | Classification | Evidence |
|---|---|---|---|
| Navigation `navigation.php` | **No native field** | Not supported — but can be added | Closest fields (`key`, `feature_key`, `source_key`) are app-internal identifiers, not designed for external resource references |
| Dashboard blocks (Manufacturing) | **No** | Not supported | Block catalog is simple key-value map; no extensible structure |
| Admin activity/panel blocks | **Potentially** | Partially supported | Cards have `key` field that could optionally resolve to a report_key |
| Workspace Profile JSON | **Yes** | Fully supported | `quick_actions`, `dashboard_blocks`, `nav_sections` are extensible JSON arrays |
| Module widget registries | **Yes** | Fully supported | Return freeform arrays; any field can be added. Already carries `report_url` in config |
| HostSurfaceRegistryService | **Yes** | Fully supported | Provider payload passes through normalization as freeform arrays |
| Operator adapters | **Yes** | Fully supported | Return arbitrary arrays; could include `report_key` in data payload |
| Display panels | **No** | Not supported | Panels are configured as boolean toggles in composer, not extensible structures |
| Quick actions (Profile) | **Yes** | Fully supported | JSON arrays are extensible |
| Quick actions (Workboard) | **Yes** | Fully supported | Action arrays are freeform |
| Operator widget provider catalog | **No** | Not supported | Registration-only; no data fields |
| **plugin.json reports[]** | **YES** | **Already has `report_key`** | Canonical declaration source |
| **ModuleReportRegistryService** | **YES** | **Serves `report_key` at runtime** | Runtime lookup mechanism |

### 3.2 Classification summary

| Classification | Count | Mechanisms |
|---|---|---|
| **Fully supported** (can carry report_key today) | 6 | Workspace Profile JSON, Widget registries, HostSurfaceRegistry, Operator adapters, Quick actions (Profile), Quick actions (Workboard) |
| **Partially supported** (can be extended to carry report_key) | 1 | Admin activity/panel blocks (card `key` could resolve to report_key) |
| **Not supported** (no extensible mechanism; would need structural change) | 4 | Navigation entries, Dashboard blocks (Manufacturing), Display panels, Operator widget provider catalog |
| **Already has report_key** (canonical source) | 2 | plugin.json reports[], ModuleReportRegistryService |

---

## 4. Gap Analysis

### 4.1 Proven gaps (evidence-based)

Only gaps with direct evidence are recorded:

| # | Gap | Evidence | Affected reports |
|---|---|---|---|
| G-01 | **No navigation entry references any individual report route** | Zero `navigation.php` entries across Manufacturing (26 items), SBAIO (15 items), Platform (24 items) contain a `/report` URL. Reports are reachable only by direct URL typing or URL construction from module page. | All 32 |
| G-02 | **No dashboard card references any report_key or report route** | `ManufacturingDashboardBlockService` processes operational widgets only. No KPI card links to a report. No dashboard block is driven by `report_key`. | All 32 |
| G-03 | **No operator widget references any report_key or report route** | All 10 operator adapters produce operational data (coverage, dispatch, machines, QC, assembly, materials). None reference report_key. Widget registries across 18 Manufacturing modules do not include report_key. | All 32 |
| G-04 | **No display panel references any report_key** | Display panels are boolean-toggled KPI strips. No panel is configured or identified by report_key. | All 32 |
| G-05 | **3 reports are declared but have no route handler** | Workflow, Supply, and CompanySetup modules declare reports in plugin.json but have no routes.php handler. Reports exist in registry but return 404 on access. | 3 (manufacturing.workflow.overview, manufacturing.supply.overview, platform.company_setup.overview) |
| G-06 | **Manufacturing has no report aggregator hub** | SBAIO has `/apps/sbaio/reports` (suite-level analytics page). Manufacturing has no equivalent. Users must know individual URL patterns. | All 18 Manufacturing reports |
| G-07 | **Manufacturing HTML export views are placeholder stubs** | Manufacturing module export views render "module-owned export is active" status text, not actual export output. Export functionality at module level is absent (bulk export at app level is separate). | All 18 Manufacturing reports |
| G-08 | **No automatic Shell-level report discovery** | Shell has no mechanism to discover "which reports are available" and compose them into navigation, dashboard, or operator surfaces. Each module manually registers its report route. | All 32 |

### 4.2 What is NOT a gap

| Claimed gap | Assessment | Reason |
|---|---|---|
| Reports need their own contribution type | **Not a gap** | Existing mechanisms can carry report_key. Missing report references are a usage gap, not an architecture gap. |
| Navigation cannot carry report references | **Not a gap** | Navigation entry metadata can be extended to include optional `report_key` field. |
| Dashboard cannot display report links | **Not a gap** | Dashboard blocks can include report_key in card structures. |
| Operators cannot access reports | **Not a gap** | Operator adapters and widget registries can carry report_key. |
| Display cannot show report data | **Not a gap** | Display panel structure can be extended to reference report resources. |

---

## 5. Surface Contribution Compatibility

### 5.1 Contract alignment

| Surface Contribution Contract requirement | Report Resource Contract | Report Runtime Contract | Compatible? |
|---|---|---|---|
| Reports are resources, not surfaces | ✅ Report = resource | ✅ Surface = consumer | ✅ |
| Reports are consumed by surfaces | ✅ Navigation, dashboard, operator, display | ✅ Each consumer reads specific fields | ✅ |
| No new contribution type needed | ✅ Existing types reference report_key | ✅ Existing types can carry report_key | ✅ |
| Owner declares report semantics | ✅ App/Module owns all fields | ✅ Module owns meaning/rendering/data | ✅ |
| Shell composes from owner resources | ✅ Shell reads registry, not plugin.json | ✅ Shell reads report_key, title, permission | ✅ |
| ACL is the authorization gate | ✅ permission field in Resource | ✅ ACL enforced at runtime | ✅ |
| Display contributions must be readonly | ✅ Reports are metadata, not mutations | ✅ Display reads title only | ✅ |
| Studio edits resources but does not own them | ✅ Studio proposes, module approves | ✅ Studio excluded from runtime | ✅ |

### 5.2 Metadata compatibility

```
Current contribution metadata         Can carry report_key?
─────────────────────────────         ────────────────────
navigation.php fields                 ✅ (add optional field)
widget config (report_url exists)     ✅ (add report_key alongside)
quick_actions JSON                    ✅ (extensible JSON)
dashboard_blocks JSON                 ✅ (extensible JSON)
host surface provider payload         ✅ (freeform arrays)
operator adapter return data          ✅ (freeform arrays)
display panel config                  ⚠️ (needs structural extension)
Manufacturing dashboard blocks        ⚠️ (needs structural extension)
```

The Surface Contribution Contract already defines reports as a contribution type. Adding `report_key` as an optional cross-reference to existing mechanisms is an extension, not a redesign.

---

## 6. Dynamic Discovery

### 6.1 Can Shell discover report-related resources?

| Question | Answer | Mechanism |
|---|---|---|
| Can navigation discover reports? | **Yes** | Shell can call `ModuleReportRegistryService::activeReports()` to enumerate all compiled reports, then generate navigation entries for reports the user has permission to access. |
| Can dashboard cards discover reports? | **Yes** | Dashboard block services can call `ModuleReportRegistryService::activeReports()` and create cards for reports matching a `visualization` type or `category`. |
| Can widgets discover reports? | **Yes** | Widget registries can reference `ModuleReportRegistryService::findActiveReport()` in their contribution logic. |
| Can display panels discover reports? | **Yes** | Display composer can call `ModuleReportRegistryService::activeReports()` and populate panel data with report metadata. |
| Can Shell discover reports without reading plugin.json? | **Yes** | `ModuleReportRegistryService` is the dedicated discovery mechanism. Shell reads from the registry, never from plugin.json directly. |

### 6.2 What dynamic discovery requires

Dynamic discovery of reports by any consumer follows this pattern:

```text
Shell consumer wants to show reports
  │
  └─ Calls ModuleReportRegistryService::activeReports()
      ├─ Returns compiled ReportResource[] (pre-validated, pre-compiled)
      ├─ Each ReportResource contains: report_key, title, permission, lifecycle
      └─ Consumer filters by ACL and lifecycle
          └─ Renders navigation entries / dashboard cards / widget data
```

No new mechanism is needed. `ModuleReportRegistryService` already provides the discovery function. What is missing is the **consumer-side code** that calls this service and renders the results — which is implementation work, not architecture design.

### 6.3 Missing metadata for concrete integration

For each consumer, the missing piece is not the discovery mechanism but whether the consumer needs report-specific metadata beyond what `ModuleReportRegistryService` provides:

| Consumer | Discovery mechanism | Missing metadata for direct integration |
|---|---|---|
| Navigation | `activeReports()` → ACL filter → generate entries | None — report_key, title, permission sufficient |
| Dashboard cards | `activeReports()` filter by `category` + `visualization` | None — ReportResource already has category and visualization |
| Operator widgets | `activeReports()` → adapter data linking | Operator widgets need a way to express "this widget is powered by report_key X" in their declaration |
| Display panels | `activeReports()` → panel configuration | Display panels need a structural extension from boolean toggles to resource-keyed panels |

The navigation and dashboard cases require no metadata changes. Operator widgets and display panels would benefit from minor metadata extensions but are not blocked by a fundamental architecture gap.

---

## 7. New Contribution Type Test

### 7.1 Evaluation

Justification for a new `report_contributions` type:

| Claim | Evidence | Verdict |
|---|---|---|
| "Reports need a dedicated contribution type because they have unique metadata" | Reports already have a dedicated declaration mechanism: `plugin.json reports[]`. This is already more structured than a contribution type would be. | **Rejected.** Reports already have a canonical declaration with richer metadata than any contribution type provides. |
| "Existing mechanisms cannot reference report_key" | Navigation entries, widgets, quick actions, and workspace profiles are all extensible and can carry `report_key`. | **Rejected.** The mechanisms support it — they just are not using it yet. |
| "Shell cannot discover reports dynamically" | `ModuleReportRegistryService::activeReports()` provides full discovery. | **Rejected.** The discovery mechanism already exists. |
| "Widget registries need report metadata to render report cards" | Widget registries already carry `report_url` in config. Adding `report_key` is a one-field extension. | **Rejected.** Existing config is already structured for report references. |
| "Display panels need to know which report drives their data" | Display panels currently use boolean toggles. This is a structural limitation of the display panel configuration, not a report contribution problem. | **Not a report contribution issue.** The display panel structure needs extension independently of whether reports exist. |
| "A new type would make report discovery consistent across all consumers" | `ModuleReportRegistryService` already provides a single consistent discovery interface. No new type needed. | **Rejected.** Consistency is already achieved through the registry service. |

### 7.2 Conclusion

**No new `report_contributions` type is required.**

The existing mechanisms are sufficient:

1. **Reports are already declared** in `plugin.json` with a dedicated schema richer than any contribution type.
2. **Discovery already exists** via `ModuleReportRegistryService`.
3. **Existing contribution types can carry `report_key`** as an optional cross-reference.
4. **The missing behavior is consumer-side implementation**, not architecture.

### 7.3 What a new type would add (and why it is unnecessary)

| Would add | Unnecessary because |
|---|---|
| A new structure parallel to `plugin.json reports[]` | `plugin.json reports[]` already declares report metadata |
| A new discovery mechanism parallel to `ModuleReportRegistryService` | Registry service already provides discovery |
| A new hook registration parallel to `HostSurfaceRegistryService` | Host surface hooks can already carry any data |
| A new navigation entry source parallel to `navigation.php` | Navigation entries can carry `report_key` as optional field |

Creating a `report_contributions` type would add a parallel metadata system without solving any consumer-side implementation gap. The correct approach is to extend existing mechanisms to optionally carry `report_key`.

---

## 8. Ownership Verification

### 8.1 Ownership preservation check

| Ownership rule | Preserved by | Risk from extension |
|---|---|---|
| **App/Module owns report meaning** | Report declared in module `plugin.json`. Module controls report_key, title, view, parameters, permission. | Low — extending existing mechanisms to carry report_key does not change who owns the report definition. |
| **App/Module owns report rendering** | Module provides view template, export template, data queries. | Low — Shell would consume only report_key, title, permission — not view paths or data. |
| **Platform owns report discovery** | `ModuleReportRegistryService` is Platform-owned. | Low — Extension would read from this service, not replace it. |
| **Platform owns export mechanics** | CSV streaming, PdfService, ExportHistoryService are Platform-owned. | Low — Export intent remains with module (declares export_formats). Mechanics remain with Platform. |
| **Shell owns composition** | Shell reads from registry, filters by ACL, generates surface entries. | Low — This is exactly what Shell already does for widgets and navigation. |
| **Shell must not access module internals** | Shell reads only report_key, title, permission, lifecycle — not view paths, module_dir, export_sources. | Low — Boundary is explicitly defined in the Runtime Contract. |
| **Studio is not a runtime dependency** | All runtime paths are Studio-independent. | Low — Extension relies on registry, not Studio artifacts. |
| **ACL is the definitive security gate** | Permission checked at runtime by ACL. | Low — Shell would suppress entries the user lacks permission for. No change to how permissions are enforced. |

### 9.2 Non-overlapping ownership verified

| Concern | Current owner | Would extension change? |
|---|---|---|
| Report meaning | Module | No |
| Report rendering | Module | No |
| Report data | Module | No |
| Report permissions | Platform ACL | No |
| Report discovery | Platform | No |
| Report navigation composition | Shell | Yes — Shell would gain the ability to compose report entries. This is within Shell's existing responsibility for navigation/dashboard/operator composition. |
| Export mechanics | Platform | No |
| Report caching | Platform/Shell | No |

The extension would give Shell the ability to compose report entries into navigation, dashboard, operator, and display surfaces using the same ReportResource metadata that route handlers already use. This is within Shell's existing composition responsibility and does not shift ownership.

---

## 9. Recommendation

### 9.1 Verdict

**A. Existing contribution system is sufficient** with small metadata extensions.

No new contribution type is required. Reports are already fully declared in `plugin.json`, discovered via `ModuleReportRegistryService`, and consumable by all existing contribution mechanisms. The gaps identified in Section 4 are **usage gaps** (report references are not present in navigation/widget/dashboard/display declarations) but not **architecture gaps** (the mechanisms support carrying report_key).

### 9.2 Recommended extensions (documentation only — not implementation)

The following extensions would enable existing mechanisms to carry report_key:

| Mechanism | Extension | Status |
|---|---|---|
| Navigation entries | Add optional `report_key` field to `navigation.php` item schema | Future: Surface Contribution Contract update + navigation metadata extension |
| Widget `$config` | Add `report_key` to `ModuleSurfaceWidgetFactory` config array alongside existing `report_url`/`export_url` | Future: Widget factory config extension |
| Widget declarations | Add optional `report_key` field to widget array in widget registries | Future: Contribution declaration extension |
| Dashboard blocks | Add optional `report_key` field to dashboard block/card structures | Future: Dashboard block metadata extension |
| Display panels | Extend from boolean toggle to resource-keyed panel configuration | Future: Display panel structure extension |
| Quick actions | Add optional `report_key` field to workspace profile quick_actions JSON | Future: Profile schema extension |

These extensions are additive and optional. No existing declaration requires modification. No `plugin.json` changes are needed.

### 9.3 What implementation would look like (not authorized)

```text
Implementation path for each consumer:

Navigation:
  ModuleReportRegistryService::activeReports()
    → Filter by lifecycle, permission
    → Generate navigation entries from report_key + title
    → Subject to ACL gate
    → No navigation.php changes needed (Shell generates entries)

Dashboard cards:
  Dashboard block service calls activeReports()
    → Filter by category or visualization type
    → Generate KPI cards linked to report URLs
    → Card data from module adapters, not ReportResource

Operator widgets:
  Widget registry adds report_key to widget declaration
    → Shell reads report_key, title, visualization hint
    → Widget data from operator adapters (existing mechanism)

Display panels:
  Panel config extended to accept report_key
    → Shell shows report title on panel
    → Panel data from display adapters (existing mechanism)
```

### 9.4 What must not happen

| Prohibited | Why |
|---|---|
| Creating `report_contributions` contribution type | Adds parallel metadata system without solving any implementation gap |
| Moving report declarations out of `plugin.json` | `plugin.json` is the canonical source; registry is the compiled view |
| Shell reading view paths or module_dir | Violates Shell consumption boundary (Report Runtime Contract Section 4) |
| Bypassing ACL for any report reference | ACL is the definitive security gate (Report Runtime Contract Section 9) |
| Creating runtime dependency on Studio | Studio is optional; all report mechanisms must work without it |

---

## 10. Deliverables

### 10.1 Contribution capability matrix

| Mechanism | report_key field today | Can carry report_key? | Extension needed |
|---|---|---|---|
| Navigation entries | ❌ None | ✅ Yes (add optional field) | navigation.php schema |
| Dashboard blocks (Mfg) | ❌ None | ❌ No (simple key-value) | Dashboard block structure |
| Admin activity/panel blocks | ❌ None | ⚠️ Partially (card key could resolve) | Card metadata extension |
| Workspace Profile JSON | ❌ None | ✅ Yes (extensible) | None — JSON already flexible |
| Module widget registries | ❌ None | ✅ Yes (freeform arrays) | Optional field addition |
| HostSurfaceRegistryService | ❌ None | ✅ Yes (freeform payloads) | None — payload already flexible |
| Operator adapters | ❌ None | ✅ Yes (freeform returns) | None — return already flexible |
| Display panels | ❌ None | ❌ No (boolean config) | Display panel configuration |
| Quick actions (Profile) | ❌ None | ✅ Yes (extensible JSON) | None — JSON already flexible |
| Quick actions (Workboard) | ❌ None | ✅ Yes (freeform arrays) | None — arrays already flexible |
| **plugin.json reports[]** | **✅ YES** | **✅ Canonical source** | **None** |
| **ModuleReportRegistryService** | **✅ YES** | **✅ Runtime lookup** | **None** |

### 10.2 Gap matrix

| Gap ID | Description | Severity | Can existing mechanisms solve it? | Requires new type? |
|---|---|---|---|---|
| G-01 | No nav entries for reports | Medium | ✅ Yes — navigation metadata can carry report_key | No |
| G-02 | No dashboard cards for reports | Low | ✅ Yes — dashboard blocks can reference report_key | No |
| G-03 | No operator widgets for reports | Low | ✅ Yes — widget declarations can carry report_key | No |
| G-04 | No display panels for reports | Low | ✅ Yes — panel config can be extended | No |
| G-05 | 3 stale reports with no route handler | Low | Needs route handler, not contribution system | No |
| G-06 | No Manufacturing report hub | Low | Needs aggregator page, not contribution system | No |
| G-07 | Manufacturing export stubs | Low | Needs module-level export, not contribution system | No |
| G-08 | No automatic Shell-level discovery | Medium | ✅ Yes — ModuleReportRegistryService already enables this | No |

All 8 gaps are solvable through existing mechanisms. None require a new contribution type.

### 10.3 Ownership verification matrix

| Ownership rule | Verified against | Status |
|---|---|---|
| Module owns report meaning | plugin.json is the canonical source | ✅ Preserved |
| Platform owns discovery | ModuleReportRegistryService is Platform-owned | ✅ Preserved |
| Platform owns export mechanics | CSV/PdfService/ExportHistoryService are Platform-owned | ✅ Preserved |
| Shell composes from registry | Shell reads from registry, not plugin.json | ✅ Preserved |
| Shell does not access module internals | Shell reads only safe fields (report_key, title, permission) | ✅ Preserved |
| ACL is definitive security gate | Runtime ACL enforces permission before any consumption | ✅ Preserved |
| Studio is not a runtime dependency | All mechanisms work without Studio | ✅ Preserved |
| Non-overlapping ownership | Each layer has distinct responsibility | ✅ Preserved |

### 10.4 Recommendation summary

```text
Verification: Existing contribution system is SUFFICIENT.

Evidence:
  - Reports already have canonical declaration (plugin.json reports[])
  - Reports already have runtime discovery (ModuleReportRegistryService)
  - 6 of 12 contribution mechanisms can carry report_key today
  - Remaining 4 need only optional field additions (not architectural changes)
  - No gap requires a new contribution type

Recommended action:
  - Add optional report_key field to navigation, widget, dashboard,
    and display contribution metadata (future implementation)
  - Do NOT create report_contributions type
  - No plugin.json changes needed
  - No existing declaration requires modification
  - No ownership boundary changes needed

Key constraint:
  - All extensions are additive and optional
  - Zero existing code requires modification
```

AGENT-COMPLIANCE-CHECKLIST.md

Core touched? NO

AGENTS.md followed? YES

Architecture rules reinforced: report remains a resource, surface remains a consumer, smallest-owner-wins preserved, Studio worker model preserved, no new contribution type created without evidence of genuine architectural gap.
