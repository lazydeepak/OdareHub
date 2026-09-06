# Report Resource Contract — Canonical Compiled ReportResource Schema

Status: Architecture contract baseline. Documentation-only. No runtime changes, DB changes, or UI changes are authorized by this document.

Purpose: Define the canonical compiled `ReportResource` schema, field classification (required/optional/computed/runtime-only), ownership matrix per field, compilation model from `plugin.json` declarations, compatibility mapping for existing declarations, and integration with the Resolved Runtime Contract pipeline.

## 1. Field Inventory — Current Declaration vs. Compiled vs. Runtime

Reports exist at three lifecycle stages. Each stage has a distinct field set:

### 1.1 Declaration stage (`plugin.json`)

Current source fields, present in all 32 report declarations across 3 apps:

```json
{
  "report_key":   "manufacturing.daily_orders.overview",
  "title":        "Daily Orders Report",
  "view":         "Views/report.php",
  "export_view":  "Views/export.php",
  "owner":        "module",
  "scope":        "daily_orders",
  "permission":   "app.manufacturing.access",
  "lifecycle":    "active_only"
}
```

All 32 declarations follow this exact 8-field pattern. No module currently declares `pdf_views`, `parameters`, `export_formats`, `export_sources`, `visualization`, `category`, or `description`.

### 1.2 Compiled stage (`ModuleReportRegistryService::activeReports()`)

Fields added during compilation by all three `ModuleReportRegistryService` implementations (Manufacturing, SBAIO, Platform):

| Field | Value | Derivation |
|---|---|---|
| `module` | `DailyOrders` | Module display name from `plugin.json` `name` field |
| `module_dir` | `/apps/Manufacturing/modules/DailyOrders` | Resolved absolute filesystem path |

### 1.3 Runtime-only fields (not in declaration or ReportResource)

| Field | Location | Consumer |
|---|---|---|
| `status` | `core_export_runs` | `ExportHistoryService` — export lifecycle state |
| `file_name` | `core_export_runs` | Generated export filename |
| `file_path` | `core_export_runs` | Storage path |
| `created_by` | `core_export_runs` | User who triggered export |
| `created_at` | `core_export_runs` | Export timestamp |
| `summary_json` | `core_export_runs` | Row count, export metadata |
| Rendered report data | Controller/View response | Query results passed to report view template |

### 1.4 Legacy side-channel — SBAIO CSV_REPORT_SOURCES

SBAIO's `ModuleReportRegistryService` maintains a hardcoded `CSV_REPORT_SOURCES` constant (11 entries) mapping `report_key` to:

```php
'table'          => 'sbaio_attendance_daily',
'order_by'       => 'id',
'direction'      => 'DESC',
'filename_prefix'=> 'sbaio-attendance'
```

This is the legacy form of what the `export_sources` field would contain. No other app has an equivalent constant.

## 2. Canonical Compiled ReportResource Schema

```yaml
ReportResource:
  # ── Identity (immutable after declaration) ──
  report_key:    string   # REQUIRED  — globally unique, "{app}.{module}.{purpose}"
  owner:         string   # REQUIRED  — "app" | "module" | "platform"

  # ── Computed Identity (resolved during compilation) ──
  owner_app:     string   # COMPUTED  — resolved from module manifest's "owner_app"
  owner_module:  string   # COMPUTED  — module directory name, lowercased
  module_dir:    string   # COMPUTED  — resolved absolute filesystem path

  # ── Presentation ──
  title:         string   # REQUIRED  — localized display name
  description:   string   # OPTIONAL  — localized description
  category:      string   # OPTIONAL  — "operational" | "analytical" | "audit" | "executive"
  visualization: string   # OPTIONAL  — "table" | "chart" | "kpi" | "mixed"

  # ── Access ──
  permission:    string   # REQUIRED  — permission key for access control
  lifecycle:     string   # REQUIRED  — "active_only" | "installed" | "always"

  # ── Views ──
  view:          string   # REQUIRED  — path to report view template (module-relative)
  export_view:   string   # OPTIONAL  — path to export view template (module-relative)
  pdf_views:     string[] # OPTIONAL  — array of PDF view paths (module-relative)

  # ── Parameters ──
  parameters:    Param[]  # OPTIONAL  — declared filter/parameter definitions

  # ── Export ──
  export_formats: string[]     # OPTIONAL  — "csv" | "pdf" | "xlsx"
  export_sources: ExportSource # OPTIONAL  — table mapping for CSV streaming

  # ── Scope ──
  scope:         string   # OPTIONAL  — domain scope identifier
```

### 2.1 Sub-resource: Param

```yaml
Param:
  key:       string   # REQUIRED  — parameter identifier
  type:      string   # REQUIRED  — "date" | "daterange" | "select" | "text" | "boolean"
  label:     string   # REQUIRED  — localized label
  required:  boolean  # OPTIONAL  — default false
  options:   string[] # OPTIONAL  — for "select" type
  default:   any      # OPTIONAL  — default value
```

### 2.2 Sub-resource: ExportSource

```yaml
ExportSource:
  table:           string   # REQUIRED  — database table name
  order_by:        string   # OPTIONAL  — default column for ordering
  direction:       string   # OPTIONAL  — "ASC" | "DESC"
  filename_prefix: string   # OPTIONAL  — export file name prefix
```

## 3. Field Classification

| Category | Fields | Present in current declarations? |
|---|---|---|
| **Required** | `report_key`, `owner`, `title`, `permission`, `lifecycle`, `view` | All 32 declarations |
| **Optional** | `description`, `category`, `export_view`, `pdf_views`, `visualization`, `parameters`, `export_formats`, `export_sources`, `scope` | `export_view` and `scope` in all 32; others absent |
| **Computed** | `owner_app`, `owner_module`, `module_dir` | None in declarations; added by registry service |
| **Runtime-only** | `status`, `file_name`, `file_path`, `created_by`, `created_at`, `summary_json`, rendered data | Export history + view response only |

### 3.1 Graceful defaults for absent optional fields

When an optional field is absent from the declaration (current state — all modules), the ReportResource compiler assigns:

| Field | Default |
|---|---|
| `description` | `""` (empty) |
| `category` | `"operational"` |
| `visualization` | `"table"` |
| `parameters` | `[]` (empty array) |
| `export_formats` | If `export_view` exists → `["csv"]`; if `pdf_views` exists → add `"pdf"`; otherwise `[]` |
| `export_formats` (legacy compat) | If `CSV_REPORT_SOURCES` entry exists (SBAIO) → add `"csv"` |
| `export_sources` | Lookup from legacy `CSV_REPORT_SOURCES` constant (SBAIO only); otherwise `null` |
| `pdf_views` | `[]` (empty array) |
| `scope` | `""` (empty) |

### 3.2 Field classification justification

**Required** status means the field must be present in every report declaration. Without it, the report cannot function:
- `report_key`: universal identity — all consumers reference reports by key
- `owner`: determines ownership layer and validation scope
- `title`: display name for navigation, dashboard cards, operator widgets, display panels
- `permission`: access control gating for all consumers
- `lifecycle`: determines whether report appears in active registry
- `view`: path to the report rendering template

**Optional** status means the field may be absent. The compiler applies a sensible default:
- `description`, `category`, `visualization`: presentation hints — no functional impact when absent
- `export_view`: not all reports need a styled export template (some use the report view directly)
- `pdf_views`: PDF export is controller-driven today; optional future declaration
- `parameters`: some reports are fixed-query with no user filters
- `export_formats`, `export_sources`: SBAIO-only today via legacy constant; future optional declaration
- `scope`: domain grouping — absent means unclassified

## 4. Ownership Matrix per Field

| Field | Owner | Editor | Validator | Consumer(s) |
|---|---|---|---|---|
| `report_key` | Module | Module (declaration) | Platform (uniqueness gate) | Registry, Shell, Export, Navigation |
| `owner` | Module | Module (declaration) | Platform (valid value gate) | Registry, Shell |
| `owner_app` | Module | — (computed) | Platform (manifest resolve) | Registry, Shell, Export |
| `owner_module` | Module | — (computed) | Platform (manifest resolve) | Registry, Export (CSV source lookup) |
| `module_dir` | Module | — (computed) | Platform (resolve) | Registry |
| `title` | Module | Module / Studio (draft) | — | Shell, Navigation, Dashboard, Operator, Display |
| `description` | Module | Module / Studio (draft) | — | Shell |
| `category` | Module | Module / Studio (draft) | — | Shell (dashboard grouping) |
| `visualization` | Module | Module / Studio (draft) | — | Shell (dashboard card rendering hint) |
| `permission` | Module | Module | System Tools (permission existence gate) | Shell (access control gate) |
| `lifecycle` | Module | Module | Platform (validity gate) | Registry (active filter) |
| `view` | Module | Module / Studio (draft) | Platform (file existence gate) | Shell (rendering) |
| `export_view` | Module | Module / Studio (draft) | Platform (file existence gate) | Export engine |
| `pdf_views` | Module | Module | Platform (file existence gate) | Export engine |
| `parameters` | Module | Module / Studio (draft) | Platform (schema validation) | Report view renderer, URL builder |
| `export_formats` | Module | Module / Studio (draft) | — | Export engine, Navigation |
| `export_sources` | Module | Module (hardcoded today) | System Tools (table existence) | Export engine (CSV streaming) |
| `scope` | Module | Module | — | Registry |

### 4.1 Owner pattern

Every field is owned by the declaring module. Studio may propose draft changes to presentation fields (`title`, `description`, `category`, `visualization`), parameter fields, and view paths — but Studio is an editor, not an owner. Platform validates structural integrity (file existence, permission validity, key uniqueness). Shell consumes the compiled resource downstream.

### 4.2 Smallest-owner-wins alignment

From the Report Designer Operating Contract (Section 1.1): the module that owns a report's domain data also owns its meaning. The ownership matrix above enforces this: every field traces ownership to the declaring module, not to Platform, Shell, Core, or Studio.

## 5. Compilation Model

### 5.1 Pipeline

```text
plugin.json "reports"[] declaration
  └─ ModuleReportRegistryService::activeReports()
      ├─ Filter by module activation (installed_plugins.status = "active")
      ├─ Filter by lifecycle ("active_only" == "active_only")
      ├─ Resolve owner_app   (from plugin.json "owner_app" or module parent app)
      ├─ Resolve owner_module (lowercased module directory name)
      ├─ Resolve module_dir   (filesystem path)
      ├─ Apply graceful defaults  (for absent optional fields)
      ├─ Resolve export_sources  (legacy CSV_REPORT_SOURCES fallback for SBAIO)
      └─ Return ReportResource[]
```

### 5.2 Current compilation steps (already implemented)

Today's `ModuleReportRegistryService::activeReports()` performs steps 1–4:

| Step | Implementation | Gate |
|---|---|---|
| Filter by module activation | `statusMap()` → DB query `installed_plugins` | Present in all 3 services |
| Filter by lifecycle | `$lifecycle === 'active_only'` | Present in all 3 services |
| Resolve `module` | `$manifest['name']` or basename fallback | Present in all 3 services |
| Resolve `module_dir` | `dirname($manifestPath)` | Present in all 3 services |

### 5.3 Future compilation additions (documentation only)

When modules begin declaring optional fields, the compiler adds:

| Step | Input | Output |
|---|---|---|
| Resolve `owner_app` | Manifest `owner_app` field | `"manufacturing"`, `"sbaio"`, `"platform"` |
| Resolve `owner_module` | Lowercased `module` | `"daily_orders"`, `"attendance"` |
| Apply graceful defaults | Absent optional fields | See Section 3.1 |
| Resolve legacy `export_sources` | `CSV_REPORT_SOURCES` constant | Entries for 11 SBAIO report_keys |

These FUTURE steps are documented here so the contract is complete. They are NOT implemented and NOT authorized by this document.

### 5.4 Validation gates during compilation

| Rule | Severity | Check | When |
|---|---|---|---|
| `report_key` globally unique | Error | No two reports share a key | Compilation |
| `report_key` matches `{app}.{module}.{purpose}` | Warning | Convention enforcement | Compilation |
| `owner` is `app`, `module`, or `platform` | Error | Must be valid | Declaration parse |
| `permission` exists in permission registry | Warning | Advisory — permission might be added later | Compilation |
| `lifecycle` is `active_only`, `installed`, or `always` | Error | Must be valid | Declaration parse |
| `view` path exists | Error | File must exist at module-relative path | Compilation |
| `export_view` path exists (if declared) | Warning | Advisory — file may be optional | Compilation |
| `pdf_views` paths exist (if declared) | Warning | Per path | Compilation |
| `parameters[].type` is valid | Error | Must be one of: date, daterange, select, text, boolean | Declaration parse |
| `parameters[].key` unique within report | Error | No duplicate parameter keys | Declaration parse |
| `export_sources.table` exists in DB | Info | Advisory — table may be in another schema | System Tools |

### 5.5 Current vs. future validation

The validation gates for `report_key` uniqueness, `view` path existence, `owner` validity, and `lifecycle` validity are already implicitly enforced by the registry service (it skips invalid entries). The formal validation rules above document the intended contract. No validation code changes are authorized by this document.

## 6. Compatibility Mapping from Existing Declarations

### 6.1 plugin.json → ReportResource field mapping

| plugin.json field | ReportResource field | Transformation |
|---|---|---|
| `report_key` | `report_key` | Pass through (trimmed) |
| `title` | `title` | Pass through (trimmed) |
| `view` | `view` | Pass through (trimmed) |
| `export_view` | `export_view` | Pass through (trimmed) |
| `owner` | `owner` | Pass through (trimmed, default `"module"`) |
| `scope` | `scope` | Pass through (trimmed) |
| `permission` | `permission` | Pass through (trimmed) |
| `lifecycle` | `lifecycle` | Lowercased, default `"active_only"` |
| *(not in plugin.json)* | `module` | `$manifest['name']` (compilation) |
| *(not in plugin.json)* | `module_dir` | `dirname($manifestPath)` (compilation) |
| *(not in plugin.json)* | `owner_app` | From manifest `owner_app` (future computed) |
| *(not in plugin.json)* | `owner_module` | Lowercased `module` (future computed) |
| *(not in plugin.json)* | `description` | Default `""` |
| *(not in plugin.json)* | `category` | Default `"operational"` |
| *(not in plugin.json)* | `visualization` | Default `"table"` |
| *(not in plugin.json)* | `parameters` | Default `[]` |
| *(not in plugin.json)* | `export_formats` | Derived from `export_view` existence + legacy constant |
| *(not in plugin.json)* | `export_sources` | Lookup from `CSV_REPORT_SOURCES` (SBAIO only) |
| *(not in plugin.json)* | `pdf_views` | Default `[]` |

### 6.2 Compatibility guarantee

No existing `plugin.json` declaration requires modification. The ReportResource compiler reads the existing 8 fields, applies computed fields as today, and fills absent optional fields with graceful defaults. Zero modules need updating.

### 6.3 Legacy CSV_REPORT_SOURCES migration path

The 11-entry `CSV_REPORT_SOURCES` constant in `apps/SBAIO/Services/ModuleReportRegistryService.php` is the legacy form of `export_sources`. Migration path:

1. **Phase 0 (current)**: `CSV_REPORT_SOURCES` constant is the only source of export table mappings. No `export_sources` field in any `plugin.json`.
2. **Phase 1 (contract established)**: ReportResource schema defines `export_sources` as an optional field. Compiler falls back to `CSV_REPORT_SOURCES` constant when the field is absent. No changes to any module.
3. **Phase 2 (opt-in adoption)**: Individual SBAIO modules add `export_sources` to their `plugin.json` declaration. Compiler prefers the declaration field over the legacy constant.
4. **Phase 3 (constant retirement)**: When all 11 SBAIO reports have adopted the declaration field, the `CSV_REPORT_SOURCES` constant may be removed. This phase is not authorized by this document.

## 7. Resolved Runtime Contract Integration

### 7.1 Current pipeline position

Reports already participate in the Resolved Runtime Contract pipeline:

```text
plugin.json reports[] declaration
  └─ ModuleReportRegistryService::activeReports()
      └─ Compilation (filter, resolve, validate)
          └─ Compiled ReportResource[]
              └─ Shell consumption
                  ├─ Navigation entries (report link)
                  ├─ Dashboard cards (KPI summary)
                  ├─ Operator widgets (chart/data)
                  ├─ Display panels (kiosk data)
                  └─ Report view/export rendering
```

### 7.2 ReportResource as a resource type in the pipeline

The Resolved Runtime Contract Pipeline (`docs/architecture/resolved-runtime-contract-pipeline.md`) defines resource types as inputs to the compiler/resolver:

> "Surface contribution resources: navigation/sidebar, operator/admin/display cards/panels, reports, actions/links."

ReportResource is one of these resource types. It follows the pipeline rules:

| Pipeline rule | ReportResource compliance |
|---|---|
| Owner resources remain owner-truth | Plugin.json is the source truth; ReportResource is a compiled view |
| Resources read without ownership transfer | Registry reads from module filesystem; no ownership change |
| Studio drafts are not compiler inputs | Designer workflow produces drafts; only compiled resource enters runtime |
| Runtime consumes resolved contracts only | Shell consumers read from registry, not from plugin.json directly |
| Preserve provenance | ReportResource retains `report_key` and `module_dir` linking back to owner |

### 7.3 Cache invalidation

From the Resolved Runtime Contract Pipeline:

> "App/module manifest or capability changes: invalidate affected app/module contracts and any dependent surface contracts."

ReportResource cache invalidation triggers:

| Change | Invalidation scope |
|---|---|
| plugin.json `reports[]` added, removed, or modified | All compiled ReportResources for that app |
| Module activated or deactivated | All compiled ReportResources for that module |
| `permission` key modified | Permission-dependent consumers (navigation, dashboard) |
| `lifecycle` changed | Report inclusion in active registry |
| `view` / `export_view` / `pdf_views` path change | Report view rendering cache |

## 8. Current-State Inventory (Baseline)

As of this document, the current report inventory is:

| App | Report count | Modules with reports |
|---|---|---|
| Manufacturing | 18 | AssemblyEntries, AssemblyPlans, Coverage, DailyOrders, DispatchEntries, Ledger, Machines, MaterialManagement, PartMachineMap, PreOrders, ProductionEntries, ProductionPlans, ProductionQueue, Products, QCEntries, QCPlans, Supply, Workflow |
| SBAIO | 11 | Attendance, Customers, Expenses, Leave, Notices, Payroll, Sales, Schedules, Staff, Tasks, Timecards |
| Platform | 3 | CompanySetup, Organization, QRCode |
| **Total** | **32** | |

All 32 declarations use the same 8-field pattern. No extended fields exist. No `pdf_views` are declared (PDF export is controller-driven). No `parameters` are declared (all reports are fixed-query). No `export_sources` are declared in plugin.json (SBAIO uses the legacy `CSV_REPORT_SOURCES` constant).

## 9. ReportResource Value Object Shape (Documentation Only)

The ReportResource may eventually be represented as a PHP value object:

```php
final class ReportResource
{
    // Identity
    public readonly string $report_key;
    public readonly string $owner;
    public readonly string $owner_app;      // computed
    public readonly string $owner_module;   // computed
    public readonly string $module_dir;     // computed

    // Presentation
    public readonly string $title;
    public readonly string $description;    // default ""
    public readonly string $category;       // default "operational"
    public readonly string $visualization;  // default "table"

    // Access
    public readonly string $permission;
    public readonly string $lifecycle;

    // Views
    public readonly string $view;
    public readonly ?string $export_view;   // null when absent
    public readonly array $pdf_views;       // string[], default []

    // Parameters
    public readonly array $parameters;      // Param[], default []

    // Export
    public readonly array $export_formats;  // string[], default []
    public readonly ?array $export_sources; // ExportSource|null, default null

    // Scope
    public readonly string $scope;          // default ""
}
```

This shape is documented here for completeness. No PHP class implementation is authorized by this document.

### 9.1 ArrayAccess bridge for compatibility

If a `ReportResource` class is implemented in the future, it must implement `ArrayAccess` so that existing callers using `$report['report_key']` continue to work without modification. This is the recommended migration strategy:

```php
// Existing caller (no change needed)
$report = ModuleReportRegistryService::findActiveReport('manufacturing.daily_orders.overview');
$key = $report['report_key'];  // works via ArrayAccess

// New caller (typed)
$resource = new ReportResource($compiled);
$key = $resource->report_key;  // works via typed property
```

## 10. Consumption Mapping

| Consumer | Required ReportResource fields | Current implementation |
|---|---|---|
| Navigation sidebar entry | `report_key`, `title`, `permission` | Navigation catalogs reference report URL |
| Admin dashboard cards | `report_key`, `title`, `visualization`, `permission` | `ManufacturingDashboardBlockService` renders KPI cards |
| Operator widgets (adapters) | `report_key`, `title`, `visualization` | Operator adapters produce KPI data from module services |
| Display panels (kiosk) | `report_key`, `title` | Display panels show KPI strip via query data |
| Report view rendering | `report_key`, `view`, `module_dir`, `parameters` | Route handler resolves view and injects params |
| CSV export | `report_key`, `export_sources.table`, `export_sources.order_by`, `export_sources.direction`, `export_sources.filename_prefix` | SBAIO `streamCsvForReport()` uses `CSV_REPORT_SOURCES` |
| PDF export | `report_key`, `pdf_views[]`, `module_dir` | Controller-level `PdfService` instantiation |
| HTML export view | `report_key`, `export_view`, `module_dir` | Route handler renders export template |
| Export history | `report_key`, `owner_app` (as suite_key) | `ExportHistoryService` records to `core_export_runs` |
| Studio Report Designer | All fields (read); `parameters`, `visualization`, `title`, `description`, `category` (edit) | Designer analyzes current resource (read-only placeholder today) |
| Scheduled execution (future) | `report_key`, `parameters`, `export_formats` | Not implemented |

### 10.1 All consumers reference report_key

Every consumer uses `report_key` as the universal identifier. No consumer independently resolves a report by title, scope, or filesystem path. This makes `report_key` the single source-of-truth identity across all runtime surfaces.

## 11. Non-Goals

The ReportResource schema must not contain:

| Prohibited | Rationale |
|---|---|
| Raw SQL | SQL belongs to module services and controllers, not report metadata. SBAIO's `CSV_REPORT_SOURCES` table names are the outermost boundary — they name a table, not a query. |
| Arbitrary code | Reports are declarative resources. No callbacks, executable expressions, or template injection. |
| Runtime state | ReportResource is a compiled contract, not a live data container. Rendered data (`rows`, `total`, `kpi_values`) belongs in a separate response object. |
| User-specific settings | `parameters['default']` is a declaration-level default. Per-user filter preferences belong to a separate user preferences layer (future). |
| Generated report data | Query results, KPI values, and chart series belong to `ReportResult` or equivalent, not the resource. |
| Bi-directional references | Circular references between reports (e.g., report A referencing report B's parameters) are not supported. |
| DB connection credentials | Report definitions are metadata. Connection config belongs to the environment configuration. |
| Environment-specific paths | All paths are module-relative. Compilation resolves them to absolute paths at build time. |
| Permission grant definitions | `permission` references an existing authorization key. It must not define new permissions. |
| ACL/Workspace Profile bypass | ReportResource must not contain fields that override or bypass authorization or profile shaping. |

## 12. Cross-Contract Alignment

| Contract | Alignment |
|---|---|
| **Report Designer Operating Contract** (`docs/architecture/report-designer-operating-contract.md`) | ReportResource is the compiled resource that Report Designer reads and proposes changes to. Editable fields (Section 4) match the Optional + Studio-editable fields in this contract. |
| **Resolved Runtime Contract Pipeline** (`docs/architecture/resolved-runtime-contract-pipeline.md`) | ReportResource is a surface contribution resource type consumed by the compiler/resolver. Compilation and validation rules in this contract align with pipeline input rules (Section 1) and ownership rules (Section 5). |
| **Surface Contribution Contract** (`docs/architecture/surface-contribution-contract.md`) | Reports are resources consumed by surfaces, not a new contribution type. This contract aligns with the "Reports" contribution type expectations (lines 130-134). |
| **MODULE-CONTRACT.md** (`docs/architecture/MODULE-CONTRACT.md`) | Module owns report meaning. Ownership matrix (Section 4) enforces smallest-owner-wins. |
| **APP-CONTRACT.md** (`docs/architecture/APP-CONTRACT.md`) | App-level report ownership boundary is preserved. Platform owns engine/report machinery, not report definitions. |
| **Studio Operating Contract** (`docs/architecture/studio-operating-contract.md`) | Studio edits compiled resources via governance workflow but does not own runtime truth. ReportResource is read by Studio, edited as draft, handed back to module. |
| **Business App/Module Ownership Contract** (`docs/architecture/business-app-module-ownership-contract.md`) | Module report declarations in `plugin.json` remain the canonical owner artifact. |

## 13. Validation

Use the current aggregate architecture gates:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

This Report Resource Contract is documentation-only. It does not authorize:
- PHP class implementation for `ReportResource`
- Modifications to any `ModuleReportRegistryService`
- Changes to `plugin.json` declarations in any module
- DB schema changes
- Route or controller changes
- UI template or composer changes
- Export engine changes

## 14. Next Contract Sequence

After this contract is accepted, the next document in sequence is:

1. **Report Validation Contract** — defines report definition validity rules: key uniqueness, permission existence, view path existence, lifecycle consistency, parameter schema rules (P1)
2. **Report Runtime Contract** — defines how compiled `ReportResource` objects are consumed by Shell surfaces (P1)

Implementation must not begin until the Report Validation Contract and Report Runtime Contract exist alongside this contract.
