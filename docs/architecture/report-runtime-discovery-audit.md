# Report Runtime Contract — Discovery Audit

Status: Pre-contract discovery audit. Read-only. No runtime changes, DB changes, UI changes, or implementation authorized.

Purpose: Define how validated ReportResources move through runtime and are consumed by Shell, navigation, dashboards, exports, displays, and future consumers. Uses only fields defined in the Report Resource Contract. Does not redesign ReportResource, validation, or Report Designer.

---

## 1. Runtime Lifecycle

### 1.1 Complete runtime path

```text
Source Declaration (plugin.json)
  │
  ├─ Stage: Declaration Parse
  │   Owner: Platform
  │   Artifact: plugin.json "reports"[] entry
  │   Responsibility: Structural schema validation
  │   Runtime visibility: None (pre-runtime)
  │
  ├─ Stage: Validation
  │   Owner: Platform (Report Validation Contract rules)
  │   Artifact: Validated report entry
  │   Responsibility: Required fields, owner, lifecycle, parameter schema
  │   Runtime visibility: None (pre-runtime)
  │
  ├─ Stage: Compilation
  │   Owner: ModuleReportRegistryService (Platform)
  │   Artifact: Compiled ReportResource array
  │   Responsibility: Filter by activation/lifecycle; resolve owner_app, owner_module, module_dir; check path existence; enforce uniqueness
  │   Runtime visibility: None (pre-runtime)
  │
  ├─ Stage: Resolved Runtime Contract
  │   Owner: Compiler/Resolver (Platform/Runtime boundary)
  │   Artifact: ReportResource in registry
  │   Responsibility: Merge compiled resources into runtime-accessible registry; ACL filtering at query time
  │   Runtime visibility: Available to all consumers via registry lookup
  │
  └─ Stage: Runtime Consumer
      Owner: Shell / App / Export Engine
      Artifact: ReportResource consumed per consumer contract
      Responsibility: Render, navigate, export, display within wrapper and ACL constraints
      Runtime visibility: End-user visible
```

### 1.2 Key architectural boundaries

| Transition | Boundary | What changes |
|---|---|---|
| Declaration → Validation | `plugin.json` parse | Schema validated, errors excluded |
| Validation → Compilation | Activation + lifecycle filter | Inactive or wrong-lifecycle reports excluded |
| Compilation → Resolved Runtime Contract | Registry build | Computed fields resolved, defaults applied, verified ReportResource produced |
| Resolved Runtime Contract → Consumer | ACL gate + wrapper confinement | Per-user visibility applied; route/wrapper normalization enforced |

### 1.3 Runtime visibility across stages

| Stage | Registry-visible? | Consumer-visible? | User-visible? |
|---|---|---|---|
| Source Declaration | No (raw file) | No | No |
| Validation | No (transient) | No | No |
| Compilation | No (transient) | No | No |
| Resolved Runtime Contract | Yes (registry) | Yes (via lookup) | No (not rendered) |
| Consumer rendering | Yes (registry) | Yes (active consumption) | Yes (rendered output) |

### 1.4 Current implementation state

Today's implementation collapses stages 2-4 into `ModuleReportRegistryService::activeReports()`:

```php
// Single method handles validation, compilation, and registry
ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview')
    ├─ Scans plugin.json (declaration)
    ├─ Filters by activation + lifecycle (validation)
    ├─ Resolves owner, module, module_dir (compilation)
    └─ Returns ReportResource array (registry)
```

This is functionally correct but leaves no clean separation between the compiled contract and its runtime consumption. The contract stages define the target architecture regardless of current implementation packaging.

---

## 2. Runtime Consumers

### 2.1 Consumer inventory

| Consumer | Current status | ReportResource fields consumed | Consumption pattern |
|---|---|---|---|
| **Report view rendering** | ✅ Active (32 routes) | `report_key`, `view`, `module_dir`, `title`, `owner`, `scope`, `permission` | Route handler calls `findActiveReport(key)` → passes to view |
| **CSV export** | ✅ Active (SBAIO only) | `report_key`, `export_sources.table`, `export_sources.order_by`, `export_sources.direction`, `export_sources.filename_prefix` | `streamCsvForReport()` uses hardcoded CSV_REPORT_SOURCES |
| **PDF export** | ✅ Active (Manufacturing modules) | `report_key`, `module_dir` | Controller resolves module_dir, renders PDF view via PdfService |
| **HTML export view** | ✅ Active (per module) | `report_key`, `export_view`, `module_dir`, `title`, `owner`, `scope` | Route handler calls `findActiveReport(key)` → passes to export view |
| **Export history** | ✅ Active | `report_key`, `owner_app` (as suite_key) | ExportHistoryService records target_key, suite_key |
| **Navigation sidebar** | ⚠️ Indirect only | `report_key`, `title`, `permission` | No navigation entry currently references individual report_key (SBAIO has aggregate `/apps/sbaio/reports` entry) |
| **Admin dashboard cards** | ❌ Not consuming | `report_key`, `title`, `visualization`, `permission` | ManufacturingDashboardBlockService processes widget contributions, not report resources |
| **Operator widgets** | ❌ Not consuming | `report_key`, `title`, `visualization` | Operator adapters produce KPI data from module services directly |
| **Display panels** | ❌ Not consuming | `report_key`, `title` | Display data from module adapters, not from report registry |
| **Scheduled execution** | 🔮 Future | `report_key`, `parameters`, `export_formats`, `permission` | Not implemented |
| **API consumers** | 🔮 Future | TBD | Not implemented |
| **Aggregate report listing** | ✅ Active (SBAIO) | None (runs own queries) | SbaioDashboardController::reports() does NOT use ModuleReportRegistryService |
| **Studio Report Designer** | ✅ Read-only placeholder | All fields (read); parameters, visualization, title, description, category (edit) | Designer reads from registry, never runtime dependency |

### 2.2 Consumer field requirements matrix

| Field | Report view | CSV export | PDF export | HTML export | Export history | Navigation | Dashboard | Operator | Display | Scheduled (future) |
|---|---|---|---|---|---|---|---|---|---|---|
| `report_key` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `owner` | ✅ | — | — | ✅ | — | — | — | — | — | — |
| `owner_app` | — | — | — | — | ✅ (suite_key) | — | — | — | — | — |
| `owner_module` | — | — | — | — | — | — | — | — | — | — |
| `module_dir` | ✅ (view path resolve) | — | ✅ (view path) | ✅ (view path) | — | — | — | — | — | — |
| `title` | ✅ | — | — | ✅ | — | ✅ | ✅ | ✅ | ✅ | — |
| `description` | — | — | — | — | — | — | — | — | — | — |
| `category` | — | — | — | — | — | — | ✅ | — | — | — |
| `visualization` | — | — | — | — | — | — | ✅ | ✅ | — | — |
| `permission` | ✅ (ACL) | ✅ (ACL) | ✅ (ACL) | ✅ (ACL) | — | ✅ (ACL) | ✅ (ACL) | — (adapter-level) | — (kiosk) | ✅ |
| `lifecycle` | ✅ (render-time check) | ✅ | ✅ | ✅ | — | ✅ | ✅ | — | — | ✅ |
| `view` | ✅ | — | — | — | — | — | — | — | — | — |
| `export_view` | — | — | — | ✅ | — | — | — | — | — | — |
| `pdf_views` | — | — | ✅ | — | — | — | — | — | — | — |
| `parameters` | ✅ (URL param binding) | — | — | — | — | — | — | — | — | ✅ |
| `export_formats` | — | — | — | — | — | — | — | — | — | ✅ |
| `export_sources` | — | ✅ | — | — | — | — | — | — | — | — |
| `scope` | ✅ (display) | — | — | ✅ | — | — | — | — | — | — |

### 2.3 Consumer-consumption pattern details

**Report view rendering** (route handler pattern):
```php
// Every module with a report has this pattern in its routes.php
$report = ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview');
if (!is_array($report)) {
    http_response_code(404);
    echo t('common.report_unavailable');
    return null;
}
$view->render('Coverage::report.php', ['report' => $report]);
```

**CSV export** (SBAIO only, in ModuleReportRegistryService):
```php
// Hardcoded mapping of report_key to DB table
private const CSV_REPORT_SOURCES = [
    'sbaio.attendance.overview' => [
        'table' => 'sbaio_attendance_daily',
        'order_by' => 'id',
        'direction' => 'DESC',
        'filename_prefix' => 'sbaio-attendance'
    ],
    // ... 10 more entries
];

// Direct CSV streaming from DB table
public static function streamCsvForReport(string $reportKey): bool
```

**PDF export** (controller-level):
```php
// Controller resolves path, instantiates PdfService independently
$pdfService = new PdfService();
$pdfService->renderPdfFromView($viewPath, $data);
```

**Export history** (passive audit):
```php
// Called after successful/failed export
ExportHistoryService::recordSuccess('module', $reportKey, 'sbaio', 'csv', $filename, $path, $metadata);
```

---

## 3. Shell Consumption Boundary

### 3.1 What Shell is allowed to know

Shell may access these ReportResource fields for composition and rendering:

| Field | Shell access | Rationale |
|---|---|---|
| `report_key` | ✅ Allowed | Identity — Shell uses key to reference, link, and look up reports |
| `title` | ✅ Allowed | Display — Shell renders report titles in navigation, cards, widgets |
| `permission` | ✅ Allowed | Access control — Shell gates visibility by ACL |
| `lifecycle` | ✅ Allowed | Inclusion — Shell checks lifecycle for rendering decisions |
| `owner` | ✅ Allowed | Ownership display — Shell may show "owned by Module X" |
| `owner_app` | ✅ Allowed | App context — Shell uses for suite-key in export history |
| `category` | ✅ Allowed | Grouping — Shell may group reports by category in listings |
| `visualization` | ✅ Allowed | Render hint — Shell may adjust card rendering based on type |
| `scope` | ✅ Allowed | Domain label — Shell may display scope for context |
| `description` | ✅ Allowed | Tooltip/help — Shell may show description on hover or in listings |
| `parameters` | ✅ Allowed | Filter UI — Shell needs parameter schema to build filter forms |
| `export_formats` | ✅ Allowed | Export actions — Shell needs format list to render export buttons |

### 3.2 What Shell must NOT access

| Field | Shell access | Rationale |
|---|---|---|
| `view` | ⛔ Indirect only | Shell does not include view templates. Route handler resolves the view path. Shell may pass `module_dir` for the route to resolve, but Shell itself does not read or render view templates. |
| `export_view` | ⛔ Indirect only | Same as view — route handler resolves, Shell does not read. |
| `pdf_views` | ⛔ Indirect only | Same — route handler resolves, Shell does not read. |
| `module_dir` | ⛔ Indirect only | Shell may pass to route resolver but must not read filesystem paths directly. |
| `export_sources` | ⛔ Not Shell's concern | Export source tables are engine-level metadata, not Shell composition data. |

### 3.3 Shell boundary rules

```
Shell knows:        Shell does NOT know:
  report_key          module DB schema
  title               report SQL queries
  permission          report data values
  lifecycle           internal module state
  owner               module service internals
  visualization       filesystem paths (beyond URL construction)
  parameters          export engine internals
  export_formats      PDF rendering mechanics
```

### 3.4 Current Shell consumption gap

Today, Shell does not consume ReportResource for navigation, dashboard, operator, or display surfaces. Reports are accessed through module-specific route handlers directly. The Shell-to-report relationship is:

```text
User clicks module link in Shell sidebar
  └─ Module route handler renders report view
      └─ Report view accesses ModuleReportRegistryService
```

A future state may include Shell composing report links into navigation, report KPI cards into dashboards, or report visualizations into operator surfaces. But today:

- **Navigation**: No individual report entries in Shell sidebar
- **Dashboard**: ManufacturingDashboardBlockService does not read reports
- **Operator**: Operator adapters bypass report registry entirely
- **Display**: Display panels query module data directly, not report resources

---

## 4. Runtime Ownership

### 4.1 Ownership per runtime concern

| Concern | Owner | Rationale |
|---|---|---|
| **Report meaning** | App/Module (smallest-owner-wins) | Module owns columns, filters, parameters, semantics |
| **Report rendering** | App/Module | Module provides view template and renders it with its data |
| **Report discovery** | Platform (ModuleReportRegistryService) | Platform aggregates plugin.json from all modules across apps |
| **Report navigation** | Shell (composition) | Shell composes navigation and surface entries; entries reference report routes |
| **Report export mechanics** | Platform (ExportHistoryService, PdfService) | Platform owns CSV streaming, PDF conversion, export audit |
| **Report execution** | App/Module | Module controller renders the report with module data |
| **Report permissions** | Platform (ACL) | ACL is platform-level authorization; module declares required key |
| **Report caching** | Platform/Shell | Registry cache is Platform-owned; output/response cache is Shell-owned |
| **Report validation** | Platform (compilation) | Platform validates structure, ownership, uniqueness |
| **Report editing** | Studio (governed worker) | Studio proposes changes; module approves and integrates |

### 4.2 Smallest-owner-wins at runtime

```text
Module A declares report "manufacturing.assembly_entries.execution"
  │
  ├─ Module A owns:
  │   ├─ What the report means (columns, filters, KPI definitions)
  │   ├─ How the report renders (view template, data queries)
  │   ├─ What data the report shows (business logic, SQL)
  │   └─ Who can see the report (permission requirement)
  │
  ├─ Platform owns:
  │   ├─ Where to find the report (registry service)
  │   ├─ How to export it (CSV streaming, PDF conversion)
  │   ├─ What was exported (export history audit)
  │   └─ Whether the user is authorized (ACL)
  │
  └─ Shell owns:
      ├─ Where to show the report (sidebar, dashboard, cards)
      ├─ How to confine the report (wrapper rules, route families)
      └─ What to show when denied (suppressed UI elements)
```

### 4.3 Non-overlapping ownership principle

| Concern | Must NOT be owned by | Risk |
|---|---|---|
| Report meaning | Shell, Core, Studio | Shell becomes business owner; Core becomes domain owner; Studio bypasses module |
| Report rendering | Shell (except composition) | Shell would need to know module-internal view logic |
| Report data | Shell, Platform, Studio | Data ownership bypasses module authority |
| Report permissions | Shell, Module | Shell cannot grant; Module cannot enforce (ACL is platform-level) |
| Report caching | Individual modules | Cache must be platform or Shell for cross-module consistency |

---

## 5. Runtime Guarantees

### 5.1 Guaranteed properties

Consumers may safely assume these properties are true for any ReportResource retrieved from the registry:

| Property | Guarantee | Source |
|---|---|---|
| All required fields present | `report_key`, `owner`, `title`, `permission`, `lifecycle`, `view` exist and are non-empty | Report Validation Contract V-001, V-007, V-015, V-022, V-027, V-028 |
| `report_key` is globally unique | No duplicate key exists across all active modules | Report Validation Contract V-006 |
| `owner_app` is resolved | Derived from module manifest during compilation | Report Resource Contract Section 2 |
| `owner_module` is resolved | Lowercased module directory name | Report Resource Contract Section 2 |
| `module_dir` exists on filesystem | Resolved absolute path to module directory | Report Validation Contract V-012 |
| `view` path existed at compilation time | File validation passed | Report Validation Contract V-029 |
| Module was active at compilation time | Activation filter passed | Report Validation Contract V-048 |
| `lifecycle` permits inclusion | Lifecycle filter passed | Report Validation Contract V-049 |
| Graceful defaults applied | All absent optional fields filled with safe defaults | Report Resource Contract Section 3.1 |
| `permission` is a string | Non-empty string type | Report Validation Contract V-023 |

### 5.2 Non-guaranteed properties

Consumers must NOT assume these are true at render time:

| Property | Why not guaranteed | Mitigation |
|---|---|---|
| Module is still active | Module may be deactivated between compilation and render | Render-time check of lifecycle + activation |
| `view` file still exists | File may be deleted between compilation and render | Render-time fallback error |
| `permission` key still exists | Permission may be removed between compilation and render | Runtime ACL handles this — denied = suppressed |
| `export_sources.table` exists | DB table may be renamed or dropped | Runtime hard check in export engine |
| User has permission | ACL is per-user, evaluated at render time | Runtime ACL gate |
| Parameters match current schema | Schema may change between compilation and render | Runtime parameter value validation |
| Report data is fresh | ReportResource contains metadata, not data | Data is queried at render time by module |
| Cache is fresh | Cache may be stale if invalidation trigger missed | Cache invalidation model (Section 7) |

### 5.3 Compilation-time vs render-time guarantee boundary

```text
COMPILATION-TIME GUARANTEES (consumers may rely on):
  ├─ Field structure is valid
  ├─ Owner and app are resolved
  ├─ View path existed
  ├─ Key is globally unique
  ├─ Module was active
  └─ Defaults are applied

RENDER-TIME CHECKS (consumers must perform):
  ├─ ACL authorization (permission)
  ├─ Module active check (lifecycle)
  ├─ Parameter value validation
  ├─ View file existence (safety fallback)
  └─ Export source availability (safety fallback)
```

### 5.4 Forward compatibility guarantees

| Future change | Guarantee |
|---|---|
| New optional field added to ReportResource | Existing consumers unchanged; new field ignored until consumed |
| New consumer added | Existing ReportResource unchanged; new consumer reads existing fields |
| Report declaration edited via Studio | Runtime unchanged until next compilation picks up updated declaration |
| Module deactivated | Report automatically excluded from registry via activation filter |

---

## 6. Runtime Failure Model

### 6.1 Failure conditions and handling

| Failure | Severity | Consumer behavior | Fallback |
|---|---|---|---|
| Report not in registry (missing key) | Error | 404 response for route; omit entry for navigation | Show "Report unavailable" message |
| Module deactivated between compilation and render | Warning | Render-time lifecycle check: if `active_only` and module inactive, deny | Show "Report unavailable" message |
| Permission denied (ACL) | Deny | Suppress navigation entry; 403 for direct URL; omit export button | No fallback — access control is intentional |
| View file missing after deployment | Error | Route handler attempts include → PHP warning | Catch error, show "Report unavailable" |
| Export view file missing | Warning | Export route attempts include → PHP warning | Fall back to report view; log warning |
| Export source table missing (CSV) | Error | streamCsvForReport returns false | Log failure; return 500 |
| PDF view file missing | Error | PdfService receives non-existent path | Log failure; return 500 |
| Export history write failure | Log only | try/catch in caller suppresses exception | Export proceeds; audit skipped |
| Parameters from URL don't match schema | Warning | Route handler binds mismatched params | Default values used; log warning |
| Cache stale | Log only | Consumer gets stale data until next invalidation | Acceptable if invalidation scope is bounded |
| Compilation error (report excluded) | Error | Report never reaches runtime | Registry omits report; no consumer sees it |
| Registry unavailable | Critical | All consumers fail | All report routes return errors; fallback to "No reports available" |

### 6.2 Consumer-specific failure behavior

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

### 6.3 Failure classification by severity

| Severity | Effect | Required logging |
|---|---|---|
| **Critical** | System-wide impact — all report consumers affected | Compilation failure logged; operator alert |
| **Error** | Single report affected; consumer shows error | Failure logged with report_key and reason |
| **Deny** | User-level access denied; operation blocked | Optional audit log (ACL deny) |
| **Warning** | Fallback behavior activated; user may see degraded experience | Warning logged |
| **Log only** | Transient issue; no user impact | Debug log |

---

## 7. Caching Model

### 7.1 What may be cached

| Cacheable | Scope | Owner | Example |
|---|---|---|---|
| Compiled ReportResource (all fields) | Per-app or global | Platform | `ModuleReportRegistryService::activeReports()` output |
| ACL-filtered report list | Per-user or per-role | Shell | Navigation entries for user with resolved permissions |
| Resolved report navigation entries | Per-user | Shell | Sidebar links after ACL + profile shaping |
| Report view output (HTML fragment) | Per-report + per-parameters | App/Module | Rendered report table or chart |
| Export source lookup | Per-report | Platform | Table mapping for CSV streaming (SBAIO CSV_REPORT_SOURCES) |

### 7.2 What must NOT be cached

| Not cacheable | Reason |
|---|---|
| Report data (query results) | Data freshness is module responsibility; report resource is metadata only |
| User-specific parameter values | Values are per-request; caching would leak user context |
| ACL authorization decisions | Permissions may change between requests; re-check at render time |
| Module activation state | Activation may change at any time; re-check at registry build |

### 7.3 Cache ownership

| Cache | Owner | Scope | Lifetime |
|---|---|---|---|
| Compiled ReportResource registry | Platform (ModuleReportRegistryService) | Per-app (Manufacturing, SBAIO, Platform) | Until invalidation trigger |
| Resolved navigation | Shell | Per-user or per-role | Session or invalidation |
| Report view output (HTML) | App/Module (controller/controller cache) | Per-report | Request or configurable TTL |
| Export source mapping | Platform (CSV_REPORT_SOURCES constant) | Global | Application lifetime (hardcoded) |

### 7.4 Cache invalidation triggers

| Trigger | Cache invalidated | Scope |
|---|---|---|
| Module activated or deactivated | Compiled ReportResource registry | Affected app's registry |
| plugin.json reports[] changed | Compiled ReportResource registry | Affected app's registry |
| Permission key added or removed | ACL-filtered report list; resolved navigation | Per-role or per-user |
| Workspace Profile changed | Resolved navigation | Affected user(s) |
| User override changed | Resolved navigation | Affected user |
| App manifest changed (owner_app) | Compiled ReportResource registry | Affected app's registry |
| Deployment / runtime rebuild | All caches | Global |

### 7.5 Invalidation cascade

```text
Module DailyOrders deactivated
  │
  ├─ Invalidation trigger: installed_plugins.status changed
  │
  ├─ Platform cache:
  │   ├─ Manufacturing ModuleReportRegistry rebuilds
  │   └─ manufacturing.daily_orders.overview excluded from registry
  │
  ├─ Shell cache:
  │   ├─ Navigation entries for manufacturing.daily_orders.overview removed
  │   └─ Dashboard cards referencing the report removed
  │
  └─ Export engine:
      ├─ Export history unaffected (historical records preserved)
      └─ New export attempts for that report_key → "report unavailable"
```

### 7.6 Cache freshness rules

| Rule | Enforcement |
|---|---|
| No stale security state | ACL decisions never cached; re-evaluated per request |
| No stale visibility state | Permission changes immediately affect navigation visibility |
| Bounded recomputation | Only affected app's registry rebuilt on module activation change |
| Deterministic invalidation keys | report_key is the cache key; module activation status is the invalidation signal |

---

## 8. Export Runtime

### 8.1 Export ownership boundaries

| Concern | Owner | Description |
|---|---|---|
| **Export intent** | Module (ReportResource) | Module declares export_view, pdf_views, export_formats, export_sources in ReportResource |
| **Export mechanics** | Platform | Platform provides CSV streaming, PDF conversion, export history audit |
| **Export actions** | Shell | Shell renders export buttons/links in surface composition |
| **Export templates** | Module | Module provides export_view and pdf_views templates within its module directory |

### 8.2 Current export implementation

```text
CSV export (SBAIO only):
  ModuleReportRegistryService::streamCsvForReport(report_key)
    ├─ Looks up table mapping from CSV_REPORT_SOURCES constant
    ├─ Streams CSV directly from DB table
    ├─ Records success/failure to ExportHistoryService
    └─ Returns bool (true = streamed, false = failed)

PDF export (Manufacturing modules):
  Controller (ProductionPlansController, QCPlansController, etc.)
    ├─ Resolves PDF view path from module directory
    ├─ Instantiates PdfService (Core-owned Dompdf wrapper)
    ├─ Renders module-owned PDF view template
    └─ Records to ExportHistoryService (optional)

HTML export (all modules):
  Route handler
    ├─ Calls findActiveReport(report_key)
    ├─ Renders export_view template from module
    └─ Passes report metadata to view
```

### 8.3 Runtime boundaries for export

| Aspect | Module boundary | Platform boundary | Core boundary |
|---|---|---|---|
| CSV streaming | Declares table name (export_sources) | Performs streaming loop | — |
| PDF generation | Provides PDF view template | Instantiates PdfService | Dompdf wrapper mechanics |
| HTML export | Provides export_view template | — | — |
| Export audit | — | Records to core_export_runs | — |
| Export format list | Declares export_formats | — | — |

### 8.4 Export security

| Check | When | Who |
|---|---|---|
| User has permission to view report | Before export action | ACL (Platform) |
| User has permission to export (if different from view) | Before export action | ACL (Platform) — future when separate export permission exists |
| Export source table exists | At export time | Export engine (Platform) |
| Export view path exists | At compilation (advisory) + render time | ModuleReportRegistryService + controller |
| Export history write | After export completes | ExportHistoryService (Platform) |

### 8.5 Future export considerations

The ReportResource declares `export_formats` as an optional field. When a module adds this field, Shell can dynamically render export buttons without hardcoding format support. The contract between module and export engine becomes:

```text
Module declares: export_formats: ["csv", "pdf"]
  └─ Shell renders: [CSV button] [PDF button]
      ├─ CSV → Platform's CSV streaming engine
      └─ PDF → Platform's PdfService + module's pdf_views[0]
```

This is not implemented today. Current export actions are hardcoded in module controllers.

---

## 9. Runtime Security

### 9.1 Security enforcement points

| Enforcement point | What it enforces | Owner | When |
|---|---|---|---|
| **Module activation gate** | Inactive modules cannot expose reports | Platform (ModuleReportRegistryService) | Compilation |
| **Lifecycle filter** | Reports with lifecycle: active_only → only when module active | Platform (ModuleReportRegistryService) | Compilation |
| **View path boundary** | View templates must be within module directory | Platform (compilation validation) | Compilation |
| **ACL authorization** | User must have permission key to view/render/export | Shell (ACL service) | Runtime — every render |
| **Export authorization** | User must have permission to export (uses same key) | Export engine / controller | Runtime — before export |
| **Wrapper confinement** | Report routes must stay within admin/operator/display wrapper | Shell | Runtime — route resolution |
| **View path safety** | View files must be within module directory (render-time check) | Route handler | Runtime — render time |
| **Export source safety** | Table names validated against `[a-z0-9_]+` pattern | Export engine (streamCsvForReport) | Runtime — before query |

### 9.2 Security principle mapping

| Principle | Application |
|---|---|
| **Defense in depth** | Permission checked at compilation (advisory), ACL at runtime (definitive), and export authorization (separate check) |
| **Least privilege** | ReportResource contains only metadata — no credentials, no SQL, no data |
| **Separation of concerns** | Module declares permission; Platform enforces ACL; Shell suppresses denied content |
| **Fail secure** | Missing report → 404; permission denied → 403; missing view → 500; all deny access |
| **Secure defaults** | Optional fields default to safe values (e.g., lifecycle defaults to active_only) |

### 9.3 Where security belongs

| Security concern | Belongs in | Does NOT belong in |
|---|---|---|
| **Permission declaration** | Module (plugin.json) | Shell, Core, Platform |
| **Permission existence validation** | Compilation (Platform) | Runtime, Shell |
| **Permission enforcement** | Runtime ACL (Platform) | Compilation, module controllers, views |
| **View path safety** | Compilation (Platform) validation | View templates, route handlers |
| **Route confinement** | Shell (wrapper normalization) | Module controllers, route definitions |
| **Export table validation** | Export engine (Platform) | Module services, views |
| **Data access control** | Module controllers/services | ReportResource, Shell, Platform |

### 9.4 What must never happen at runtime

| Prohibited | Risk |
|---|---|
| Bypass ACL for any report render or export | Unauthorized data exposure |
| Allow path traversal in view paths | Filesystem access outside module boundary |
| Cache ACL decisions across requests | Stale authorization — user's permissions may change |
| Grant permissions via ReportResource fields | Ownership bypass — module declares, ACL enforces |
| Use module_dir to access files outside module boundary | Module boundary violation |

---

## 10. Studio Exclusion

### 10.1 Runtime independence from Studio

| Question | Answer |
|---|---|
| Can runtime operate without Studio installed? | **Yes.** Studio is an optional app. Runtime does not depend on any Studio service, route, composer, or data. |
| Can reports execute without Studio? | **Yes.** Reports are declared in plugin.json, compiled by ModuleReportRegistryService, and rendered by module controllers. Studio is never involved. |
| Can ReportResource exist without Studio? | **Yes.** ReportResource is produced by the Platform-owned compilation pipeline. Studio does not participate. |
| Can ExportHistoryService function without Studio? | **Yes.** Export history is a Platform concern. Studio never reads or writes export audit data. |
| Can runtime be deployed when Studio is disabled? | **Yes.** All 32 reports function normally regardless of Studio's enabled/disabled status. |

### 10.2 Studio-runtime interaction boundary

```text
Studio Report Designer (optional app)
  │
  ├─ Reads ReportResource from registry (read-only)
  ├─ Produces draft ReportResource (Studio-local)
  ├─ Validates draft (Studio preflight)
  ├─ Generates change record (Studio artifact)
  │
  └─ Handover to module (owner approval required)
      └─ Module updates plugin.json
          └─ Next compilation includes updated declaration
              └─ Runtime consumes updated ReportResource
```

Studio is upstream of runtime. It reads resource definitions and proposes changes. It never intercepts, modifies, or replaces runtime consumption.

### 10.3 No Studio dependency in any runtime path

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

## 11. Recommended Report Runtime Contract Structure

Based on this audit, the formal Report Runtime Contract should follow this outline:

```markdown
# Report Runtime Contract

Status: [status line]
Purpose: Define how validated ReportResources are consumed at runtime.

## 1. Runtime Lifecycle

    1.1 Complete runtime path (Source → Validation → Compilation → Resolved Runtime → Consumer)
    1.2 Stage ownership, artifacts, and responsibilities
    1.3 Runtime visibility across stages

## 2. Runtime Consumers

    2.1 Consumer inventory (current + future)
    2.2 Consumer consumption patterns
    2.3 Field requirements per consumer

## 3. Shell Consumption Boundary

    3.1 What Shell is allowed to know
    3.2 What Shell must not access
    3.3 Shell boundary rules
    3.4 Current Shell consumption state

## 4. Runtime Ownership

    4.1 Ownership per runtime concern
    4.2 Smallest-owner-wins at runtime
    4.3 Non-overlapping ownership principle

## 5. Runtime Guarantees

    5.1 Guaranteed properties (compilation-verified)
    5.2 Non-guaranteed properties (render-time check required)
    5.3 Compilation-time vs render-time boundary
    5.4 Forward compatibility guarantees

## 6. Runtime Failure Model

    6.1 Failure conditions and handling
    6.2 Consumer-specific failure behavior
    6.3 Failure classification by severity
    6.4 Error messaging conventions

## 7. Caching Model

    7.1 Cacheable artifacts and scope
    7.2 Non-cacheable artifacts
    7.3 Cache ownership
    7.4 Cache invalidation triggers
    7.5 Invalidation cascade model
    7.6 Cache freshness rules

## 8. Export Runtime

    8.1 Export ownership boundaries
    8.2 Current export implementation state
    8.3 Runtime boundaries for export
    8.4 Export security

## 9. Runtime Security

    9.1 Security enforcement points
    9.2 Security principle mapping
    9.3 Where security belongs
    9.4 Prohibited runtime behaviors

## 10. Studio Exclusion

    10.1 Runtime independence verification
    10.2 Studio-runtime interaction boundary
    10.3 No Studio dependency in any runtime path

## 11. Current-State Consumption Assessment

    11.1 What works today (report rendering, export, export history)
    11.2 What does not exist yet (navigation, dashboard, operator, display)
    11.3 Consumption gap analysis

## 12. Cross-Contract Alignment

    Report Resource Contract
    Report Validation Contract
    Report Designer Operating Contract
    Resolved Runtime Contract Pipeline
    Surface Contribution Contract

## 13. Non-Goals

    What this contract does not authorize.

## 14. Validation

    Architecture gates to run.
```

---

## 12. Non-Goals

The Report Runtime Contract must not define:

| Prohibited | Rationale |
|---|---|
| Report editing | Editing is Studio's governed worker domain (Report Designer Operating Contract) |
| Validation rules | Validation is defined in the Report Validation Contract |
| Report Designer UX | Designer UI is Studio implementation detail |
| SQL execution | SQL belongs to module services and controllers, not runtime metadata |
| Report generation logic | Data querying, aggregation, and KPI calculation are module concerns |
| Resource ownership changes | Ownership boundaries are established in MODULE-CONTRACT.md and Report Resource Contract |
| New ReportResource fields | Adding fields is a schema change, not a runtime contract concern |
| ReportResource redesign | The schema is stable; runtime contract describes consumption only |
| Database schema changes | DB design is outside runtime consumption scope |
| ACL redesign | ACL is a separate platform capability; runtime contract defines how it gates reports |

---

## Summary of Key Decisions

| Question | Decision |
|---|---|
| What is the runtime lifecycle? | Declaration → Validation → Compilation → Resolved Runtime Contract → Consumer (4-stage pipeline) |
| Who owns runtime report meaning? | App/Module (smallest-owner-wins) |
| Who owns report discovery? | Platform (ModuleReportRegistryService) |
| Who owns runtime security? | ACL enforcement at runtime (Platform); permission declaration by module |
| What is Shell allowed to know? | report_key, title, permission, lifecycle, owner, category, visualization, scope, description, parameters, export_formats |
| What is Shell NOT allowed to access? | view path, module_dir, export_sources (indirect only via route handlers) |
| What is guaranteed at runtime? | All required fields exist, key is unique, owner resolved, defaults applied, module was active at compilation |
| What is NOT guaranteed? | Module still active, view file still exists, permission key still exists, user has permission |
| How does Studio relate to runtime? | Studio is not a runtime dependency. Runtime operates without Studio. |
| What caching is allowed? | Compiled ReportResource registry (platform), navigation entries (shell), export source lookup (platform) |
| What caching is forbidden? | Report data (query results), ACL decisions, user parameter values, module activation state |
| When is export authorized? | Same ACL as report view (future: separate export permission) |
| What happens on failure? | Missing report → 404; permission denied → 403/suppressed; view missing → 500; export unavailable → error response |

AGENT-COMPLIANCE-CHECKLIST.md

Core touched? NO

AGENTS.md followed? YES

Architecture rules reinforced: smallest-owner-wins, Studio worker model, Resolved Runtime Contract pipeline, Shell composition boundaries, Platform engine ownership, non-overlapping ownership principle.
