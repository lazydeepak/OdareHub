# Report Runtime Contract

Status: Architecture contract baseline. Documentation-only. No runtime changes, DB changes, UI changes, or implementation authorized.

Purpose: Define how validated ReportResources move through runtime and are consumed by Shell, navigation, dashboards, exports, displays, and future consumers. Formalizes runtime lifecycle, consumer boundaries, guarantees, failure handling, caching, export ownership, security, and Studio exclusion. Uses only fields defined in the Report Resource Contract. Preserves smallest-owner-wins, Studio worker model, and Resolved Runtime Contract architecture.

---

## 1. Runtime Lifecycle

### 1.1 Complete runtime path

A ReportResource passes through four stages before reaching a runtime consumer:

```text
plugin.json declaration
  │
  ├─ Stage 1: Declaration Parse
  │   Owner: Platform
  │   Responsibility: Structural schema validation, field presence, type/format checks
  │   Runtime visibility: None
  │   Outcome: Structural errors → report excluded before compilation
  │
  ├─ Stage 2: Validation
  │   Owner: Platform (Report Validation Contract rules)
  │   Responsibility: Required field validation, ownership validation, permission existence (advisory), parameter schema
  │   Runtime visibility: None
  │   Outcome: Validation errors → report excluded; warnings → included with log
  │
  ├─ Stage 3: Compilation
  │   Owner: ModuleReportRegistryService (Platform)
  │   Responsibility: Module activation filter, lifecycle filter, owner_app/module/module_dir resolution, view path existence, key uniqueness, graceful defaults
  │   Runtime visibility: None
  │   Outcome: Compilation errors → report excluded; success → compiled ReportResource produced
  │
  ├─ Stage 4: Resolved Runtime Contract
  │   Owner: Compiler/Resolver (Platform/Runtime boundary)
  │   Responsibility: Compiled ReportResource enters runtime registry; available for consumer lookup
  │   Runtime visibility: Registry-visible, consumer-accessible via lookup
  │   Outcome: ReportResource available for consumption
  │
  └─ Stage 5: Runtime Consumer
      Owner: Shell / App / Export Engine / Module Controller
      Responsibility: Render, navigate, export, display within ACL and wrapper constraints
      Runtime visibility: End-user visible
      Outcome: Report rendered, navigation entry shown, export streamed, or error returned
```

### 1.2 Stage boundaries

| Transition | Gate | What changes |
|---|---|---|
| Declaration → Validation | plugin.json parse | Schema validated, structural errors excluded |
| Validation → Compilation | Activation + lifecycle filter | Inactive or wrong-lifecycle reports excluded |
| Compilation → Resolved Runtime Contract | Registry entry | Computed fields resolved, defaults applied, verified ReportResource produced |
| Resolved Runtime Contract → Consumer | ACL gate + wrapper confinement | Per-user visibility applied; route/wrapper normalization enforced |

### 1.3 Runtime visibility across stages

| Stage | Registry-visible? | Consumer-visible? | User-visible? |
|---|---|---|---|
| Source Declaration | No (raw file) | No | No |
| Validation | No (transient) | No | No |
| Compilation | No (transient) | No | No |
| Resolved Runtime Contract | Yes (registry) | Yes (via lookup) | No (not rendered) |
| Consumer rendering | Yes (registry) | Yes (active consumption) | Yes (rendered output) |

### 1.4 Current implementation reality

Today's implementation collapses stages 2-4 into `ModuleReportRegistryService::activeReports()`:

```php
// Single method handles validation, compilation, and registry
ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview')
    ├─ Scans plugin.json (declaration)
    ├─ Filters by activation + lifecycle (validation)
    ├─ Resolves owner, module, module_dir (compilation)
    └─ Returns ReportResource array (registry)
```

This is functionally correct but leaves no clean separation between the compiled contract and its runtime consumption. The Resolved Runtime Contract stage for reports is implicit (the return value of `activeReports()`), not an explicitly materialized contract like ResolvedExperience. The stage boundaries define the target architecture regardless of current implementation packaging.

---

## 2. Current-State Consumer Reality

### 2.1 What exists today

| Consumer | Status | Consumption pattern |
|---|---|---|
| **Report view rendering** | ✅ Active (32 routes) | Route handler calls `findActiveReport(key)` → passes to module-owned view |
| **CSV export** | ✅ Active (SBAIO only) | Hardcoded `CSV_REPORT_SOURCES` constant in `ModuleReportRegistryService`; streams DB table directly |
| **PDF export** | ✅ Active (Manufacturing modules) | Controller resolves `module_dir`, instantiates `PdfService`, renders module-owned PDF view |
| **HTML export view** | ✅ Active (per module) | Route handler calls `findActiveReport(key)` → passes to module-owned export view |
| **Export history** | ✅ Active | `ExportHistoryService` records `target_key` (report_key), `suite_key` (owner_app), `export_type` |
| **SBAIO aggregate reports page** | ✅ Active | Does NOT use ModuleReportRegistryService; runs own DB queries |

### 2.2 What does NOT exist today

| Consumer | Status | Current behavior |
|---|---|---|
| **Navigation sidebar** | ❌ Not consuming ReportResource | No navigation entry references individual `report_key`. Reports are reached through module-specific routes (e.g., `/apps/manufacturing/coverage/report`), not through sidebar entries driven by the report registry. |
| **Admin dashboard cards** | ❌ Not consuming ReportResource | `ManufacturingDashboardBlockService` processes widget contributions, not report resources. No dashboard card is driven by a `report_key`. |
| **Operator widgets** | ❌ Not consuming ReportResource | Operator adapters produce KPI data from module services directly. No operator widget references the report registry. |
| **Display panels** | ❌ Not consuming ReportResource | Display panels query module data directly. No display panel references report resources. |
| **Scheduled execution** | 🔮 Future | Not implemented. |

### 2.3 Key current-state observation

The report resource contract stack (Resource, Validation, Runtime contracts) defines the target architecture. The current implementation predates these contracts. None of the consumer gaps block existing functionality — they simply mean the report registry is not yet the driver for Shell-level report navigation, dashboard composition, operator widgets, or display panels.

---

## 3. Runtime Consumers — Boundaries and Requirements

### 3.1 Consumer field matrix

| Field | Report view | CSV export | PDF export | HTML export | Export history | Navigation | Dashboard | Operator | Display | Scheduled (future) |
|---|---|---|---|---|---|---|---|---|---|---|
| `report_key` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `owner` | ✅ | — | — | ✅ | — | — | — | — | — | — |
| `owner_app` | — | — | — | — | ✅ | — | — | — | — | — |
| `module_dir` | ✅\(^1\) | — | ✅\(^1\) | ✅\(^1\) | — | — | — | — | — | — |
| `title` | ✅ | — | — | ✅ | — | ✅ | ✅ | ✅ | ✅ | — |
| `description` | — | — | — | — | — | ✅ | ✅ | ✅ | — | — |
| `category` | — | — | — | — | — | ✅ | ✅ | — | — | — |
| `visualization` | — | — | — | — | — | ✅ | ✅ | ✅ | — | — |
| `permission` | ✅\(^2\) | ✅\(^2\) | ✅\(^2\) | ✅\(^2\) | — | ✅\(^2\) | ✅\(^2\) | —\(^3\) | —\(^4\) | ✅\(^2\) |
| `lifecycle` | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | — | — | ✅ |
| `view` | ✅ | — | — | — | — | — | — | — | — | — |
| `export_view` | — | — | — | ✅ | — | — | — | — | — | — |
| `pdf_views` | — | — | ✅ | — | — | — | — | — | — | — |
| `parameters` | ✅ | — | — | — | — | — | — | — | — | ✅ |
| `export_formats` | — | — | — | — | — | — | — | — | — | ✅ |
| `export_sources` | — | ✅ | — | — | — | — | — | — | — | — |
| `scope` | ✅ | — | — | ✅ | — | — | — | — | — | — |

*(1) module_dir is resolved by the route handler for view path construction, not consumed by Shell directly.*
*(2) permission is used for ACL gating at runtime; the ReportResource carries the string, the ACL evaluates it.*
*(3) Operator adapters perform their own data-level access control.*
*(4) Display panels are kiosk surfaces; data is pre-filtered by the adapter, not ACL-gated at render time.*

### 3.2 Consumer definitions

**Navigation sidebar:**
- Reports may appear as navigation entries keyed by `report_key`
- Shell reads `report_key`, `title`, `permission` (for ACL), `lifecycle`, `category` (for grouping)
- Shell must NOT read `view`, `module_dir`, `export_sources`
- Navigation entries are resolved through ACL: entries the user lacks permission for are suppressed
- If a report link is clicked and the report is missing from registry → 404
- If ACL denies the user → navigation entry is suppressed

**Dashboard cards:**
- Cards may reference a `report_key` to display KPI summaries or visualization hints
- Shell reads `report_key`, `title`, `visualization`, `permission`, `lifecycle`, `category`, `description`
- Shell must NOT read `view`, `module_dir`, `export_sources`
- Card data (KPI values) comes from module adapters, not from ReportResource
- If the report is missing from registry → card is omitted
- If ACL denies → card is suppressed

**Operator widgets:**
- Widgets may reference a `report_key` for title and visualization hint
- Shell reads `report_key`, `title`, `visualization`, `description`
- Shell must NOT read `view`, `module_dir`, `permission`, `export_sources`
- Widget data comes from operator adapters, not from ReportResource
- Data-level access control is the adapter's responsibility
- If the report is missing → widget is omitted

**Display panels:**
- Kiosk/display panels may show report title as a label
- Shell reads `report_key`, `title`
- Shell must NOT read `view`, `module_dir`, `permission`, `export_sources`
- Data comes from module display adapters (pre-filtered, readonly)
- No ACL gate at render time (kiosk surface; data is pre-filtered)
- If the report is missing → panel is omitted

**Operator surfaces:**
- Operator surface (the workspace under `/u/{username}/*`) may reference reports for action menu entries or summary cards
- Shell reads same fields as operator widgets
- Subject to operator wrapper confinement (all links stay within `/u/{username}/*`)
- If the report is missing → action/card is omitted

**Admin surfaces:**
- Admin surface may reference reports for dashboard cards, navigation entries, or export actions
- Shell reads same fields as dashboard cards + navigation
- Subject to admin wrapper rules
- If the report is missing → card/entry omitted; ACL gate applies

**Export engine:**
- Reads `report_key`, `export_view` (for HTML), `pdf_views` (for PDF), `export_sources` (for CSV), `module_dir` (for view resolution), `lifecycle` (for inclusion)
- Export engine does NOT read `title`, `description`, `category`, `visualization`, `scope`
- ACL check uses `permission` from the report
- If export source is unavailable → 500 error
- If permission denied → 403
- Export history is recorded by `ExportHistoryService` after the export completes

**Scheduled execution (future):**
- Reads `report_key`, `parameters` (for parameter binding), `export_formats` (for export format selection), `permission` (system-level, not user-level)
- Shell must NOT read `view`, `module_dir`, `export_sources`
- If report is missing → skip execution; if permission missing → skip; if export format unavailable → skip that export step

**API consumers (future):**
- API may expose report resources for external consumption
- Reads `report_key`, `title`, `parameters`, `export_formats`
- ACL gate applies (API authentication + authorization)
- API must not expose `module_dir`, `view`, `export_sources` as raw paths

### 3.3 Consumer consumption patterns

**Report view rendering** (current pattern, all modules):
```php
$report = ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview');
if (!is_array($report)) {
    http_response_code(404);
    echo t('common.report_unavailable');
    return null;
}
$view->render('Coverage::report.php', ['report' => $report]);
```

**Navigation** (target pattern — not yet implemented):
```php
// Shell reads from registry, filters by ACL
$reports = ModuleReportRegistryService::activeReports();
foreach ($reports as $report) {
    if (ACL::hasPermission($report['permission'])) {
        $navEntries[] = [
            'key' => $report['report_key'],
            'title' => $report['title'],
            'url' => $this->reportUrl($report['report_key']),
        ];
    }
}
```

---

## 4. Shell Consumption Boundary

### 4.1 What Shell is allowed to know

Shell may access these ReportResource fields for composition, rendering, and navigation:

| Field | Shell access | Rationale |
|---|---|---|
| `report_key` | ✅ Allowed | Identity — Shell uses key to reference, link, and look up reports |
| `title` | ✅ Allowed | Display — Shell renders report titles in navigation, cards, widgets |
| `permission` | ✅ Allowed | Access control — Shell gates visibility by ACL |
| `lifecycle` | ✅ Allowed | Inclusion — Shell checks lifecycle for rendering decisions |
| `owner` | ✅ Allowed | Ownership display — Shell may show module ownership |
| `owner_app` | ✅ Allowed | App context — Shell uses for route construction and grouping |
| `category` | ✅ Allowed | Grouping — Shell may group reports by category in listings |
| `visualization` | ✅ Allowed | Render hint — Shell may adjust card rendering based on type |
| `scope` | ✅ Allowed | Domain label — Shell may display scope for context |
| `description` | ✅ Allowed | Tooltip/help — Shell may show description on hover or in listings |
| `parameters` | ✅ Allowed | Filter UI — Shell needs parameter schema to build filter forms |
| `export_formats` | ✅ Allowed | Export actions — Shell may render export buttons based on format list |

### 4.2 What Shell must NOT access

| Field | Shell access | Rationale |
|---|---|---|
| `view` | ⛔ Indirect only | Shell does not include or render view templates. The route handler resolves the view path using `module_dir`. |
| `export_view` | ⛔ Indirect only | Same as view — route handler resolves, Shell does not read. |
| `pdf_views` | ⛔ Indirect only | Same — route handler resolves, Shell does not read. |
| `module_dir` | ⛔ Indirect only | Shell may pass to route resolver but must not read filesystem paths directly. |
| `export_sources` | ⛔ Not Shell's concern | Export source tables are engine-level metadata, not Shell composition data. |

### 4.3 Shell boundary rules

```
Shell knows:                Shell does NOT know:
  report_key                  module DB schema
  title                       report SQL queries
  permission                  report data values
  lifecycle                   internal module state
  owner                       module service internals
  visualization               filesystem paths (beyond URL construction)
  parameters                  export engine internals
  export_formats              PDF rendering mechanics
  category                    Dompdf configuration
  scope
  description
```

---

## 5. Runtime Ownership

### 5.1 Ownership per runtime concern

| Concern | Owner | Rationale |
|---|---|---|
| **Report meaning** | App/Module | Module owns columns, filters, parameters, semantics. Smallest-owner-wins. |
| **Report rendering** | App/Module | Module provides view template and renders it with its own data queries. |
| **Report discovery** | Platform | `ModuleReportRegistryService` aggregates plugin.json from all modules across apps. |
| **Report navigation** | Shell (composition) | Shell composes navigation and surface entries; entries reference report routes. |
| **Report export mechanics** | Platform | Platform provides CSV streaming, PDF conversion, export audit. |
| **Report execution** | App/Module | Module controller renders the report with module data. |
| **Report permissions** | Platform (ACL) | ACL is platform-level authorization; module declares the required key. |
| **Report caching** | Platform / Shell | Registry cache is Platform-owned; output/response cache is Shell-owned. |
| **Report validation** | Platform | Platform validates structure, ownership, uniqueness (Report Validation Contract). |
| **Report editing** | Studio (governed worker) | Studio proposes changes; module approves and integrates. Studio is NOT a runtime dependency. |

### 5.2 Smallest-owner-wins at runtime

```text
Module declares report
  │
  ├─ Module owns:
  │   ├─ Report meaning (columns, filters, KPI definitions)
  │   ├─ Report rendering (view template, data queries)
  │   ├─ Report data (business logic, SQL, aggregation)
  │   └─ Permission requirement (declares the key)
  │
  ├─ Platform owns:
  │   ├─ Where to find the report (registry service)
  │   ├─ How to export it (CSV streaming, PDF conversion, export audit)
  │   ├─ Whether the user is authorized (ACL enforcement)
  │   └─ Whether the report is valid (compilation validation)
  │
  ├─ Shell owns:
  │   ├─ Where to show the report (sidebar, dashboard, cards)
  │   ├─ How to confine the report (wrapper rules, route families)
  │   └─ What to show when denied (suppressed UI elements, fallback messages)
  │
  └─ Core owns:
      ├─ PDF rendering mechanics (PdfService / Dompdf wrapper)
      └─ Nothing else — Core must not own report meaning, data, or permissions
```

### 5.3 Non-overlapping ownership principle

| Concern | Must NOT be owned by | Risk |
|---|---|---|
| Report meaning | Shell, Core, Studio | Shell becomes business owner; Core becomes domain owner; Studio bypasses module |
| Report rendering | Shell (except composition) | Shell would need to know module-internal view logic |
| Report data | Shell, Platform, Studio | Data ownership bypasses module authority over its domain |
| Report permissions | Shell, Module | Shell cannot grant; Module cannot enforce (ACL is platform-level) |
| Report caching | Individual modules in isolation | Cache must be Platform or Shell for cross-module consistency |

---

## 6. Runtime Guarantees

### 6.1 Guaranteed properties

Consumers may safely assume these properties are true for any ReportResource retrieved from the registry:

| Property | Guarantee | Source |
|---|---|---|
| All required fields present | `report_key`, `owner`, `title`, `permission`, `lifecycle`, `view` exist and are non-empty | Validation Contract V-001, V-007, V-015, V-022, V-027, V-028 |
| `report_key` is globally unique | No duplicate key across all active modules | Validation Contract V-006 |
| `owner_app` is resolved | Derived from module manifest during compilation | Resource Contract Section 2 |
| `owner_module` is resolved | Lowercased module directory name | Resource Contract Section 2 |
| `module_dir` exists on filesystem | Resolved absolute path to module directory | Validation Contract V-012 |
| `view` path existed at compilation time | File validation passed | Validation Contract V-029 |
| Module was active at compilation time | Activation filter passed | Validation Contract V-048 |
| `lifecycle` permits inclusion | Lifecycle filter passed | Validation Contract V-049 |
| Graceful defaults applied | Absent optional fields filled with safe defaults | Resource Contract Section 3.1 |
| `permission` is a non-empty string | String type validation | Validation Contract V-023 |
| `export_sources` table name is safe | Matches `[a-z0-9_]+` pattern | Runtime Contract Section 8.4 |

### 6.2 Non-guaranteed properties

Consumers must NOT assume these are true at render time:

| Property | Why not guaranteed | Mitigation |
|---|---|---|
| Module is still active | Module may be deactivated between compilation and render | Render-time lifecycle check; if `active_only` and inactive → deny |
| `view` file still exists | File may be deleted between compilation and render | Render-time fallback error message |
| `permission` key still exists | Permission may be removed between compilation and render | Runtime ACL handles this — denied = suppressed |
| `export_sources.table` exists | DB table may be renamed or dropped | Runtime hard check in export engine |
| User has permission | ACL is per-user, evaluated at render time | Runtime ACL gate — definitive |
| Parameters match current schema | Schema may change between compilation and render | Runtime parameter value validation |
| Report data is fresh | ReportResource contains metadata, not data | Data is queried at render time by module |
| Cache is fresh | Cache may be stale if invalidation trigger missed | Cache invalidation model (Section 7) |

### 6.3 Compilation-time vs render-time boundary

```text
COMPILATION-TIME GUARANTEES (consumers may rely on):
  ├─ Field structure is valid
  ├─ Owner and app are resolved
  ├─ View path existed at compilation time
  ├─ Key is globally unique
  ├─ Module was active at compilation time
  └─ Defaults are applied for absent optional fields

RENDER-TIME CHECKS (consumers must perform):
  ├─ ACL authorization (permission)
  ├─ Module active check (lifecycle — for active_only reports)
  ├─ Parameter value validation against declared schema
  ├─ View file existence (safety fallback)
  └─ Export source availability (safety fallback)
```

### 6.4 Forward compatibility guarantees

| Future change | Guarantee |
|---|---|
| New optional field added to ReportResource | Existing consumers unchanged; new field ignored until consumed |
| New consumer added | Existing ReportResource unchanged; new consumer reads existing fields |
| Report declaration edited via Studio | Runtime unchanged until next compilation picks up updated declaration |
| Module deactivated | Report automatically excluded from registry via activation filter |

---

## 7. Runtime Failure Model

### 7.1 Failure conditions and handling

| Failure | Severity | Consumer behavior | Fallback |
|---|---|---|---|
| Report not in registry (missing key) | Error | 404 for route; omit entry for navigation/card/widget/display | Show "Report unavailable" message |
| Module deactivated between compilation and render | Warning | Render-time lifecycle check: if active_only and module inactive, deny | Show "Report unavailable" message |
| Permission denied (ACL) | Deny | Suppress navigation entry; 403 for direct URL; omit export button | No fallback — access control is intentional |
| View file missing after deployment | Error | Route handler attempts include → PHP warning | Catch error, show "Report unavailable" |
| Export view file missing | Warning | Export route attempts include → PHP warning | Fall back to report view; log warning |
| Export source table missing (CSV) | Error | streamCsvForReport returns false | Log failure; return 500 |
| PDF view file missing | Error | PdfService receives non-existent path | Log failure; return 500 |
| Export history write failure | Log only | try/catch in caller suppresses exception | Export proceeds; audit skipped |
| Parameters from URL don't match schema | Warning | Route handler binds mismatched params | Default values used; log warning |
| Cache stale | Log only | Consumer gets stale data until next invalidation | Acceptable if invalidation scope is bounded |
| Compilation error (report excluded) | Error | Report never reaches runtime | Registry omits report; no consumer sees it |
| Registry unavailable | Critical | All consumers fail | All report routes return errors; fallback to "Reports unavailable" |

### 7.2 Consumer-specific failure behavior

| Consumer | Report missing | Permission denied | View missing | Export unavailable |
|---|---|---|---|---|
| **Navigation** | Omit entry | Omit entry | N/A | N/A |
| **Dashboard cards** | Omit card | Omit card | N/A | N/A |
| **Report view rendering** | 404 with message | 403 | 500 with message | N/A |
| **Operator widgets** | Omit widget | Omit widget (adapter-level) | N/A | N/A |
| **Display panels** | Omit panel | N/A (kiosk) | N/A | N/A |
| **CSV export** | 404 | 403 | N/A | 500 (source missing) |
| **PDF export** | 404 | 403 | 500 | 500 |
| **HTML export** | 404 | 403 | 500 (fallback to report view) | N/A |
| **Scheduled (future)** | Skip execution | Skip execution | Skip execution | Skip export step |

### 7.3 Failure classification by severity

| Severity | Effect | Required logging |
|---|---|---|
| **Critical** | System-wide impact — all report consumers affected | Compilation failure logged; operator alert |
| **Error** | Single report affected; consumer shows error | Failure logged with `report_key` and reason |
| **Deny** | User-level access denied; operation blocked | Optional audit log (ACL deny) |
| **Warning** | Fallback behavior activated; user may see degraded experience | Warning logged |
| **Log only** | Transient issue; no user impact | Debug log |

---

## 8. Caching and Invalidation

### 8.1 Cacheable artifacts

| Cacheable | Scope | Owner | Lifetime |
|---|---|---|---|
| Compiled ReportResource (all fields) | Per-app | Platform | Until invalidation trigger |
| ACL-filtered report list | Per-user or per-role | Shell | Session or invalidation |
| Resolved report navigation entries | Per-user | Shell | Session or invalidation |
| Report view output (HTML fragment) | Per-report + per-parameters | App/Module | Request or configurable TTL |
| Export source lookup | Per-report | Platform | Application lifetime (currently hardcoded) |

### 8.2 What must NOT be cached

| Not cacheable | Reason |
|---|---|
| Report data (query results) | Data freshness is module responsibility; ReportResource is metadata only |
| User-specific parameter values | Values are per-request; caching would leak user context across requests |
| ACL authorization decisions | Permissions may change between requests; re-check at render time |
| Module activation state | Activation may change at any time; re-check at registry build |

### 8.3 Cache invalidation triggers

| Trigger | Cache invalidated | Scope | Owner of rebuild |
|---|---|---|---|
| Module activated or deactivated | Compiled ReportResource registry | Affected app's registry | Platform |
| plugin.json reports[] changed | Compiled ReportResource registry | Affected app's registry | Platform |
| Permission key added or removed | ACL-filtered report list; resolved navigation | Per-role or per-user | Shell |
| Workspace Profile changed | Resolved navigation | Affected user(s) | Shell |
| User override changed | Resolved navigation | Affected user | Shell |
| App manifest changed (owner_app) | Compiled ReportResource registry | Affected app's registry | Platform |
| Deployment / runtime rebuild | All caches | Global | Platform |

### 8.4 Invalidation cascade

```text
Module DailyOrders deactivated
  │
  ├─ Trigger: installed_plugins.status changed
  │
  ├─ Platform cache:
  │   ├─ Manufacturing ModuleReportRegistry rebuilds
  │   └─ manufacturing.daily_orders.overview excluded from registry
  │
  ├─ Shell cache:
  │   ├─ Navigation entries for manufacturing.daily_orders.overview removed
  │   ├─ Dashboard cards referencing the report removed
  │   └─ Operator widgets referencing the report removed
  │
  └─ Export engine:
      ├─ Export history unaffected (historical records preserved)
      └─ New export attempts for that report_key → "report unavailable"
```

### 8.5 Cache freshness rules

| Rule | Enforcement |
|---|---|
| No stale security state | ACL decisions never cached; re-evaluated per request |
| No stale visibility state | Permission changes immediately affect navigation visibility |
| Bounded recomputation | Only affected app's registry rebuilt on module activation change |
| Deterministic invalidation keys | `report_key` is the cache key; module activation status is the invalidation signal |

---

## 9. Export Runtime

### 9.1 Export ownership boundaries

| Concern | Owner | Description |
|---|---|---|
| **Export intent** | App/Module (ReportResource) | Module declares `export_view`, `pdf_views`, `export_formats`, `export_sources` in ReportResource. Module decides what formats to support. |
| **Export mechanics** | Platform | Platform provides CSV streaming engine, PDF conversion (`PdfService`), export history audit (`ExportHistoryService`). Platform performs the actual export work. |
| **Export actions** | Shell | Shell renders export buttons/links in surface composition. Shell reads `export_formats` to determine which buttons to show. |
| **Export templates** | App/Module | Module provides `export_view` and `pdf_views` templates within its module directory. Module owns the layout and content of export output. |

### 9.2 Current export implementation

```text
CSV export (SBAIO only):
  ModuleReportRegistryService::streamCsvForReport(report_key)
    ├─ Looks up table mapping from CSV_REPORT_SOURCES constant
    ├─ Validates table name against [a-z0-9_]+ pattern
    ├─ Streams CSV directly from DB table (SELECT * FROM table ORDER BY ... LIMIT 5000)
    ├─ Records success/failure to ExportHistoryService
    └─ Returns bool (true = streamed, false = failed)

PDF export (Manufacturing modules):
  Controller (ProductionPlansController, QCPlansController, etc.)
    ├─ Resolves PDF view path from module_dir + pdf_views[0]
    ├─ Instantiates PdfService (Core-owned Dompdf wrapper)
    ├─ Renders module-owned PDF view template
    └─ Records to ExportHistoryService (optional)

HTML export (all modules):
  Route handler
    ├─ Calls findActiveReport(report_key)
    ├─ Renders export_view template from module
    └─ Passes report metadata to view
```

### 9.3 Runtime boundaries for export

| Aspect | App/Module boundary | Platform boundary | Core boundary |
|---|---|---|---|
| CSV streaming | Declares table name (`export_sources.table`) | Performs streaming loop; validates table name | — |
| PDF generation | Provides PDF view template | Instantiates PdfService; renders template | Dompdf wrapper mechanics |
| HTML export | Provides `export_view` template | — | — |
| Export audit | — | Records to `core_export_runs` via `ExportHistoryService` | — |
| Export format list | Declares `export_formats` in ReportResource | — | — |
| Export authorization | Declares `permission` requirement | ACL check before export | — |

### 9.4 Export security

| Check | When | Owner | Severity on failure |
|---|---|---|---|
| User has permission to view report | Before any export action | ACL (Platform) | Deny — 403 |
| Export source table name is safe | Before CSV streaming | Export engine (Platform) | Error — 500 |
| Export source table exists | At export time | Export engine (Platform) | Error — 500 |
| Export view path exists | At compilation (advisory) + render time | ModuleReportRegistryService + controller | Warning → fallback |
| Export history write | After export completes | ExportHistoryService (Platform) | Log only |

---

## 10. Runtime Security

### 10.1 Security enforcement points

| Enforcement point | What it enforces | Owner | When |
|---|---|---|---|
| Module activation gate | Inactive modules cannot expose reports | Platform (ModuleReportRegistryService) | Compilation |
| Lifecycle filter | Reports with lifecycle `active_only` require active module | Platform (ModuleReportRegistryService) | Compilation |
| View path boundary | View templates must be within module directory | Platform (compilation validation) | Compilation |
| ACL authorization | User must have `permission` key to view/render/export | Shell / ACL service | Runtime — every render |
| Export authorization | User must have permission to export (uses same key as view) | Export engine / controller | Runtime — before export |
| Wrapper confinement | Report routes must stay within assigned wrapper (admin/operator/display) | Shell | Runtime — route resolution |
| View path safety | View files must be within module directory (render-time check) | Route handler | Runtime — render time |
| Export source safety | Table names validated against `[a-z0-9_]+` pattern | Export engine | Runtime — before query |

### 10.2 Security principle mapping

| Principle | Application |
|---|---|
| **Defense in depth** | Permission checked at compilation (advisory), ACL at runtime (definitive), export authorization (separate check). No single point of failure. |
| **Least privilege** | ReportResource contains only metadata — no credentials, no SQL, no report data. Module controllers hold the actual data access logic. |
| **Separation of concerns** | Module declares permission requirement. Platform enforces ACL. Shell suppresses denied content. No layer performs another's role. |
| **Fail secure** | Missing report → 404; permission denied → 403; missing view → 500. All denial paths return errors rather than partial data. |
| **Secure defaults** | Optional fields default to safe values (e.g., `lifecycle` defaults to `active_only`, not `always`). |

### 10.3 Where security belongs

| Security concern | Belongs in | Does NOT belong in |
|---|---|---|
| Permission declaration | App/Module (plugin.json) | Shell, Core, Platform |
| Permission existence validation | Compilation (Platform) | Runtime, Shell |
| Permission enforcement | Runtime ACL (Platform) | Compilation, module controllers, views |
| View path safety | Compilation (Platform) validation | View templates, route handlers |
| Route confinement | Shell (wrapper normalization) | Module controllers, route definitions |
| Export table validation | Export engine (Platform) | Module services, views |
| Data access control | Module controllers/services | ReportResource, Shell, Platform |

### 10.4 Prohibited runtime behaviors

| Prohibited | Risk |
|---|---|
| Bypass ACL for any report render or export | Unauthorized data exposure — ACL is the definitive gate |
| Allow path traversal in view paths | Filesystem access outside module boundary |
| Cache ACL decisions across requests | Stale authorization — user's permissions may change between requests |
| Grant permissions via ReportResource fields | Ownership bypass — module declares, ACL enforces. ReportResource must not contain grant semantics. |
| Use `module_dir` to access files outside module boundary | Module boundary violation — `module_dir` is for view resolution only |

---

## 11. Studio Exclusion

### 11.1 Runtime independence from Studio

| Question | Answer |
|---|---|
| Can runtime operate without Studio installed? | **Yes.** Studio is an optional app. Runtime does not depend on any Studio service, route, composer, or data store. |
| Can reports execute without Studio? | **Yes.** Reports are declared in plugin.json, compiled by ModuleReportRegistryService, and rendered by module controllers. Studio is never involved in report execution. |
| Can ReportResource exist without Studio? | **Yes.** ReportResource is produced by the Platform-owned compilation pipeline. Studio does not participate in compilation. |
| Can ExportHistoryService function without Studio? | **Yes.** Export history is a Platform concern. Studio never reads or writes export audit data. |
| Can runtime be deployed when Studio is disabled? | **Yes.** All 32 reports function normally regardless of Studio's enabled/disabled status. |

### 11.2 Studio-runtime interaction boundary

```text
Studio Report Designer (optional app)
  │
  ├─ Reads ReportResource from registry (read-only access)
  ├─ Produces draft ReportResource (Studio-local storage only)
  ├─ Validates draft (Studio preflight validation — advisory only)
  ├─ Generates change record (Studio artifact)
  │
  └─ Handover to module (owner approval required)
      └─ Module updates plugin.json declaration
          └─ Next compilation picks up updated declaration
              └─ Runtime consumes updated ReportResource
```

Studio is **upstream** of runtime. It reads resource definitions and proposes changes. It never intercepts, modifies, or replaces runtime consumption. The pipeline from declaration to runtime consumer is Studio-independent.

### 11.3 No Studio dependency in any runtime path

```
Runtime path                             Studio required?
─────────────────────────────────────    ────────────────
ReportResource compilation               No
Report view rendering                    No
CSV export                              No
PDF export                              No
Navigation composition                   No
Dashboard rendering                     No
Operator widget rendering               No
Display panel rendering                 No
Export history recording                No
ACL authorization                       No
```

---

## 12. Cross-Contract Alignment

| Contract | Alignment |
|---|---|
| **Report Resource Contract** (`docs/architecture/report-resource-contract.md`) | This contract describes how compiled ReportResources are consumed. Every consumer field requirement references a field defined in the Resource Contract. No new fields introduced. |
| **Report Validation Contract** (`docs/architecture/report-validation-contract.md`) | Runtime guarantees (Section 6) reference Validation Contract rule IDs (V-001 through V-050). Guaranteed properties are exactly those validated at compilation. Non-guaranteed properties are those that can change between compilation and render time. |
| **Report Designer Operating Contract** (`docs/architecture/report-designer-operating-contract.md`) | Studio exclusion (Section 11) aligns with the Designer's non-ownership model. Studio is upstream of runtime — it reads resources and proposes changes but never intercepts runtime consumption. |
| **Resolved Runtime Contract Pipeline** (`docs/architecture/resolved-runtime-contract-pipeline.md`) | The runtime lifecycle (Section 1) maps to the compiler-resolver pipeline stages. ReportResource is one of the surface contribution resource types. The Resolved Runtime Contract stage is where compiled reports enter the runtime registry. Cache invalidation (Section 8) follows the pipeline's invalidation model. |
| **Surface Contribution Contract** (`docs/architecture/surface-contribution-contract.md`) | Consumer boundaries (Section 3) align with the contribution-type expectations. Reports are resources consumed by surfaces — not a new contribution type. Each consumer (navigation, dashboard, operator, display) reads only the fields relevant to its surface role. |
| **MODULE-CONTRACT.md** | Runtime ownership (Section 5) enforces smallest-owner-wins. Module owns report meaning, rendering, and execution. Platform owns discovery and export mechanics. Shell owns composition. |
| **APP-CONTRACT.md** | App-level ownership preserved. Platform-owned reports can only originate from the Platform app. |
| **Studio Operating Contract** | Studio exclusion (Section 11) follows the non-ownership model. Studio validates and proposes; it does not hold runtime truth. |
| **Business App/Module Ownership Contract** | Module-level ownership of report declarations is preserved unchanged. Runtime never overrides module ownership. |

---

## 13. Non-Goals

The Report Runtime Contract must not define:

| Prohibited | Rationale |
|---|---|
| Report editing | Editing is Studio's governed worker domain (Report Designer Operating Contract) |
| Validation rules | Validation is defined in the Report Validation Contract |
| Report Designer UX | Designer UI is Studio implementation detail |
| SQL execution | SQL belongs to module services and controllers, not runtime metadata |
| Report generation logic | Data querying, aggregation, and KPI calculation are module concerns |
| Resource ownership changes | Ownership boundaries established in MODULE-CONTRACT.md and Report Resource Contract |
| New ReportResource fields | Adding fields is a schema change, not a runtime contract concern |
| ReportResource redesign | The schema is stable; runtime contract describes consumption only |
| Database schema changes | DB design is outside runtime consumption scope |
| ACL redesign | ACL is a separate platform capability; runtime contract defines how it gates reports |
| New contribution type | Reports are resources consumed by existing contribution types (navigation, dashboard, operator, display) — not a new surface type |
| Implementation plan | This contract defines what runtime should do, not how to implement it |

---

## 14. Validation

Run the current aggregate architecture gates:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

This Report Runtime Contract is documentation-only. It does not authorize:
- Modifications to any `ModuleReportRegistryService`
- Changes to any route handler, controller, or view
- Changes to `plugin.json` declarations in any module
- DB schema changes
- UI template or composer changes
- Export engine changes
- ACL changes
- Navigation, dashboard, operator, or display changes that would add report consumption
- Implementation of any target-state pattern described in this contract

---

## Appendix A: Key Architecture Decisions

| Decision | Contract position |
|---|---|
| What is Shell allowed to know? | 11 fields: report_key, title, permission, lifecycle, owner, owner_app, category, visualization, scope, description, parameters, export_formats |
| What must Shell NOT access? | view, export_view, pdf_views, module_dir, export_sources |
| What is guaranteed at runtime? | Required fields exist, key unique, owner resolved, module was active, path existed at compilation, defaults applied |
| What is NOT guaranteed? | Module still active, view file still exists, permission key still exists, user has permission, cache is fresh |
| Who owns report rendering? | App/Module — module controllers and view templates |
| Who owns report discovery? | Platform — ModuleReportRegistryService |
| Who owns export mechanics? | Platform — CSV streaming, PdfService, export audit |
| Who owns report permissions? | Platform ACL — module declares, ACL enforces |
| What may be cached? | Compiled registry, ACL-filtered lists, navigation entries, view output |
| What must NOT be cached? | Report data, user parameter values, ACL decisions, module activation state |
| Is Studio a runtime dependency? | No. Runtime operates without Studio in every path. |
| What happens on report missing? | Omit from navigation/cards/widgets; 404 on direct URL; skip scheduled execution |
| What happens on permission denied? | Omit from navigation/cards; 403 on direct URL; deny export |
| What happens on view missing? | 500 with "Report unavailable" message |
| What happens on export unavailable? | 500; log failure |
