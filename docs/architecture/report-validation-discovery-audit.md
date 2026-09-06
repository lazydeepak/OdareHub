# Report Validation Contract — Discovery Audit

Status: Pre-contract discovery audit. Read-only. No runtime changes, DB changes, UI changes, or implementation authorized.

Purpose: Define the complete validation model for `ReportResource` before writing the formal Report Validation Contract. Uses only fields defined in the Report Resource Contract. Does not introduce new schema fields, redesign the ReportResource, or change ownership boundaries.

---

## 1. Validation Scope

### 1.1 Field-to-validation-type matrix

Every `ReportResource` field and its validation requirements across five validation dimensions:

| Field | Structural | Ownership | Permission | Runtime | Compilation |
|---|---|---|---|---|---|
| `report_key` | Required | Required | — | Required | Required |
| `owner` | Required | Required | — | — | Required |
| `owner_app` | Computed | Required | — | Required | Required |
| `owner_module` | Computed | Required | — | — | Required |
| `module_dir` | Computed | Required | — | Required | Required |
| `title` | Required | Required | — | Required | Required |
| `description` | Optional | — | — | — | — |
| `category` | Optional | — | — | Optional | Optional |
| `visualization` | Optional | — | — | Optional | Optional |
| `permission` | Required | Required | Required | Required | Required |
| `lifecycle` | Required | Required | — | Required | Required |
| `view` | Required | Required | — | Required | Required |
| `export_view` | Optional | Required | — | Optional | Optional |
| `pdf_views` | Optional | Required | — | Optional | Optional |
| `parameters` | Optional | — | — | Required | Optional |
| `export_formats` | Optional | — | — | Optional | Optional |
| `export_sources` | Optional | — | — | Required | Optional |
| `scope` | Optional | — | — | Optional | — |

**Legend:**
- **Required** — validation always performed at this stage
- **Optional** — validation performed only when field is present
- **Computed** — field is derived, not declared; validation checks derivation integrity
- **—** — no validation at this stage

### 1.2 Layer responsibility matrix

| Layer | Structural | Ownership | Permission | Runtime | Compilation |
|---|---|---|---|---|---|
| **App/Module** | Declares valid values | Declares correct ownership | Declares existing key | — | — |
| **Platform** | Schema validation on parse | Owner-app/module resolution gate | Permission existence gate | Export engine validation | Compilation pipeline owner |
| **Studio** | Preflight (draft only) | Draft ownership check | Draft permission check | — | — |
| **System Tools** | Read-only diagnostic | Read-only ownership check | Read-only existence check | — | Read-only consistency check |
| **Compilation Pipeline** | Required field presence | Owner resolve | Permission existence (warning) | — | Block/allow on errors |
| **Shell Runtime** | — | — | ACL authorization gate | Consumer-specific field check | — |

### 1.3 Validation stage pipeline

```text
plugin.json declaration
  │
  ├─ Declaration Parse (Platform)
  │   ├─ Structural validation (field presence, type, format)
  │   ├─ Ownership validation (owner values)
  │   ├─ Lifecycle validation (valid lifecycle strings)
  │   ├─ Parameter schema validation (type, key uniqueness)
  │   └─ Error → exclude from registry
  │
  ├─ Compilation (ModuleReportRegistryService)
  │   ├─ Module activation filter
  │   ├─ Lifecycle filter
  │   ├─ Owner_app resolve (from manifest)
  │   ├─ Owner_module resolve (from directory)
  │   ├─ Module_dir resolve (from filesystem)
  │   ├─ View path existence check
  │   ├─ Export_view path existence check (if declared)
  │   ├─ Pdf_views path existence check (if declared)
  │   ├─ Permission existence check (advisory warning)
  │   ├─ Report_key uniqueness check across all apps
  │   ├─ Graceful default application
  │   └─ Error → exclude from compiled output; Warning → include, log
  │
  ├─ System Tools (read-only diagnostic)
  │   ├─ Export_sources table existence check
  │   ├─ Permission existence verification
  │   ├─ Cross-app key uniqueness verification
  │   └─ Ownership boundary compliance check
  │
  └─ Runtime Consumption (Shell)
      ├─ ACL permission gate before rendering navigation/dashboard
      ├─ Report_resource field presence check per consumer
      └─ View path exists at render time (fallback safety)
```

---

## 2. Required Field Validation

### 2.1 Validation matrix for required fields

| Field | Valid | Warning | Error |
|---|---|---|---|
| `report_key` | Non-empty string matching `{app}.{module}.{purpose}` convention | Key does not match `{app}.{module}.{purpose}` pattern but is non-empty | Empty or missing; non-string type; duplicate key across all apps |
| `owner` | `"app"` \| `"module"` \| `"platform"` (case-insensitive) | — | Missing; empty; unrecognized value |
| `title` | Non-empty string, any valid UTF-8 | — | Missing; empty; non-string type |
| `permission` | Non-empty string, key exists in permission registry | Key does not exist in permission registry (may be pending) | Missing; empty; non-string type |
| `lifecycle` | `"active_only"` \| `"installed"` \| `"always"` (case-insensitive) | — | Missing; empty; unrecognized value |
| `view` | Non-empty string, file exists at module-relative path | — | Missing; empty; non-string type; file does not exist |

### 2.2 Auto-default for required fields

No required field receives an auto-default. If a required field is missing or invalid, the report is excluded from compilation. This is intentional — every required field is essential for the report to function.

### 2.3 Existing declaration compatibility

All 32 current declarations pass every required-field check:
- `report_key`: all 32 are non-empty, all follow `{app}.{module}.{purpose}` convention
- `owner`: all 32 are `"module"`
- `title`: all 32 are non-empty
- `permission`: all 32 are non-empty (some reference `app.manufacturing.access`, `app.sbaio.access`, `app.platform.access`; Manufacturing-specific ones like `manufacturing.assembly_entries.view` and `workflow.production_plan.approve` may or may not exist in the permission registry — this is a warning-level check)
- `lifecycle`: all 32 are `"active_only"`
- `view`: all 32 are `"Views/report.php"` (existence depends on actual filesystem)

### 2.4 Edge cases per field

**report_key:**
- Contains spaces → Error (report_key must be a routable identifier)
- Contains uppercase → Warning (convention is lowercase `{app}.{module}.{purpose}`, but uppercase is technically valid)
- Contains special characters other than `.` and `_` → Error
- Empty after trim → Error
- Exceeds 255 characters → Warning (storage constraint in export history)
- Null or non-string → Error

**owner:**
- `"App"` (capitalized) → Valid (case-insensitive)
- `"Application"` → Error (not one of the three valid values)
- `"module "` (trailing space) → Warning (trimmed before comparison in registry service, but space suggests data quality issue)
- `""` (empty) → Error
- `"core"` → Error (Core does not own reports)

**title:**
- String with only whitespace → Error
- HTML tags in title → Warning (title is for display; HTML may cause rendering issues)
- Extremely long title (>200 chars) → Warning (display truncation risk)
- Emoji in title → Warning (encoding compatibility, not a hard error)

**permission:**
- Key exists but is deprecated → Warning
- Key references a non-existent module → Warning (permission likely unresolved)
- Key format does not match `{app}.{scope}.{action}` convention → Warning (non-blocking)

**lifecycle:**
- `"ACTIVE_ONLY"` → Valid (case-insensitive)
- `"always"` → Valid (but currently none use this)
- `"never"` → Error
- `""` (empty) → Error (registry service defaults to `"active_only"` today, but validation should require explicit declaration)

**view:**
- Path with directory traversal (`../`) → Error
- Path pointing outside module directory → Error (security boundary violation)
- File exists but zero bytes → Warning (likely broken view)
- File exists but is a directory → Error

---

## 3. Key Uniqueness Rules

### 3.1 Global uniqueness

`report_key` must be **globally unique** across all apps, modules, and plugins.

| Scope | Uniqueness requirement | Rationale |
|---|---|---|
| **Global** | All 32 existing keys are unique | Export history, navigation, and dashboard consumers key by `report_key` without app prefix disambiguation |
| **App-level** | Not sufficient alone | Two apps could independently declare `"daily_orders.overview"` — both would collide in global consumers |
| **Module-level** | Implicit when key follows `{app}.{module}.{purpose}` convention | Module scope is encoded in the key itself, not in a separate scope field |

### 3.2 Uniqueness error conditions

| Condition | Severity | Handling |
|---|---|---|
| Two reports with identical `report_key` | **Error** | Both reports excluded from compilation; error logged |
| Same `report_key` declared in two different apps | **Error** | Collision likely from convention violation; both excluded |
| Same `report_key` in same module's `reports[]` array | **Error** | Duplicate within single `plugin.json` — configuration error |
| Stale `report_key` from deactivated module collides with active module's key | **Error** | Active module's report wins; deactivated module's report is already filtered by activation gate |

### 3.3 Collision handling strategy

```
Collision detected for report_key "manufacturing.daily_orders.overview"
  ├─ Source A: Manufacturing/DailyOrders (active)
  ├─ Source B: Manufacturing/DailyOrders (duplicate in same array)
  └─ Resolution:
      ├─ Both excluded if within same plugin.json array (declaration error)
      ├─ Source A wins if Source B from deactivated module (activation filter)
      ├─ Both excluded if from two different active modules (needs manual fix)
      └─ Error logged, no silent override
```

### 3.4 Upgrade compatibility

| Scenario | Behavior | Risk |
|---|---|---|
| Module A adds a report key that collides with future Module B's key | Error at Module B activation time | Low — keys are declared by module, collision is compile-time |
| Module changes `report_key` between versions | New key is treated as a different report; old export history records retain old key | Medium — consumers (navigation, dashboard) must update references |
| Two versions of same module installed simultaneously (should not happen) | Both processed; collision error | Low — prevented by module lifecycle gates |

### 3.5 Validation implementation model

```text
Step 1: Collect all report_key values from all active modules (cross-app)
Step 2: Build frequency map
Step 3: Any key with count > 1 → Error
Step 4: Exclude all reports with colliding keys from compilation
Step 5: Log collision details for manual resolution
```

The uniqueness check is a **compilation-time** validation. It must be performed after module activation filtering but before the ReportResource array is returned. It is already implicitly enforced by the registry service's design (no two modules today share a key), but no explicit duplicate check exists.

---

## 4. Ownership Validation

### 4.1 Ownership validation matrix

| Check | Input | Severity | Condition |
|---|---|---|---|
| Owner value valid | `owner` field | Error | Not `"app"`, `"module"`, or `"platform"` |
| Owner value present | `owner` field | Error | Missing or empty |
| Owner app resolves | `owner_app` (computed from manifest) | Error | Cannot resolve `owner_app` from module manifest |
| Owner app exists in registry | `owner_app` (computed) | Warning | App key not found in app registry (may be uninstalled) |
| Owner module resolves | `owner_module` (computed from directory) | Error | Module directory does not exist or is unreadable |
| Owner matches declaration source | `owner` field + source location | Error | `owner: "platform"` declared by business module; `owner: "app"` declared by service-only module |
| Owner module activated | `owner_module` (computed) | Error | Module is not active (filtered by activation gate) |

### 4.2 Owner-source matching rules

| Declared `owner` | Allowed source locations | Error if |
|---|---|---|
| `"module"` | Any module directory | Declared in an app-level manifest (not inside a module) |
| `"app"` | App-level manifest or app root | Declared inside a module — module scopes are narrower than app |
| `"platform"` | Platform app only | Declared in Manufacturing or SBAIO app |

### 4.3 Computed ownership validation

| Computed field | Validation | Severity |
|---|---|---|
| `owner_app` | Resolved from module manifest `owner_app` field | Error if `owner_app` missing or empty in manifest |
| `owner_app` | Resolved value starts with `"apps/"`? | Error — `owner_app` should be app identifier, not filesystem path |
| `owner_module` | Lowercased module directory name | Warning — directory name may not match expected module key |
| `module_dir` | Directory exists and is readable | Error — filesystem path invalid |
| `module_dir` | Located under known app root | Warning — path outside expected `/apps/{app}/modules/` structure |

### 4.4 Ownership validation layer

| Layer | Role |
|---|---|
| **App/Module** | Declares `owner` correctly; ensures manifest has `owner_app` |
| **Platform (compilation)** | Resolves and validates `owner_app`, `owner_module`, `module_dir` |
| **Platform (registration)** | Validates owner matches declaration source |
| **System Tools** | Read-only diagnostic: cross-check owner declarations against filesystem structure |
| **Studio** | Preflight: verify draft's declared owner matches source module |
| **Shell Runtime** | Not involved — ownership is resolved at compilation, not checked at render time |

---

## 5. Permission Validation

### 5.1 Permission validation matrix

| Check | Severity | Condition | When |
|---|---|---|---|
| Permission key present | Error | `permission` field missing or empty | Declaration parse |
| Permission key is string | Error | Non-string type | Declaration parse |
| Permission key exists in registry | Warning | Key not found in any app/module permission declaration | Compilation |
| Permission key refers to deprecated permission | Warning | Key exists but marked deprecated | Compilation |
| Permission key owned by different app | Warning | Key belongs to different app than report's `owner_app` | Compilation |
| Permission key exists but is inactive | Info | Permission declared but module owning it is inactive | System Tools diagnostic |

### 5.2 Severity rationale

Permission existence is **Warning**, not Error, because:
- Permissions may be added by other modules that are installed later
- The permission registry may not be fully populated at compilation time in all environments
- A missing permission does not break report structure — it only affects access gating
- The ACL authorization gate at runtime will deny access regardless of compilation warnings

### 5.3 Permission key format validation

| Pattern | Severity | Example |
|---|---|---|
| `{app}.{module}.{action}` | Valid (preferred) | `manufacturing.assembly_entries.view` |
| `{app}.{scope}.{action}` | Valid | `app.manufacturing.access` |
| Empty key | Error | `""` |
| Key only contains wildcards | Warning | `*.*.*` |
| Key with spaces | Error | `app manufacturing access` |
| Key exceeding 255 chars | Warning | Storage constraint |

### 5.4 Lifecycle compatibility

| Permission state | Report lifecycle | Result |
|---|---|---|
| Permission key exists, module active | `active_only` | Full access |
| Permission key exists, module active | `installed` | Compiled, but Shell may suppress |
| Permission key exists, module active | `always` | Always compiled |
| Permission key exists, module inactive | `active_only` | Report excluded by activation filter before permission check |
| Permission key missing | Any | Warning logged, report compiles, runtime ACL denies |

### 5.5 Permission validation layer

| Layer | Role |
|---|---|
| **App/Module** | Declares a valid permission key referencing owned or existing permission |
| **Platform (compilation)** | Warning-level existence check against permission registry |
| **System Tools** | Read-only cross-check: permission existence, ownership alignment, deprecation status |
| **Studio** | Preflight: warn if draft references unknown permission |
| **Shell Runtime** | ACL authorization gate — final access decision at render time. This is the only gate that truly matters for security |

### 5.6 Runtime ACL is the definitive gate

The compilation permission check is advisory only. Runtime ACL authorization is the hard gate. Compilation warnings about missing permissions cannot block a report from rendering — only the runtime ACL can deny or permit access.

```text
Compilation: "permission key X not found in registry" → Warning
  └─ ReportResource compiled with permission field intact
      └─ Shell renders navigation/dashboard entry
          └─ Runtime ACL: "does user have key X?"
              ├─ Yes → render
              └─ No → suppress
```

---

## 6. View Validation

### 6.1 View path validation matrix

| Check | Field | Severity | Condition |
|---|---|---|---|
| View path present | `view` | Error | Missing or empty |
| View path is string | `view` | Error | Non-string type |
| View file exists | `view` | Error | File does not exist at resolved absolute path |
| View file readable | `view` | Warning | File exists but is not readable |
| View file is PHP | `view` | Warning | File exists but is not a `.php` file |
| View path traversal | `view` | Error | Contains `..` or absolute path reference |
| View outside module dir | `view` | Error | Resolved path is not under `module_dir` |
| Export view present (if declared) | `export_view` | Warning | Missing or empty when declared |
| Export view file exists | `export_view` | Warning | File does not exist (advisory — report view may serve as export) |
| Export view outside module dir | `export_view` | Error | Resolved path not under `module_dir` |
| PDF view array is array | `pdf_views` | Warning | Non-array type |
| Each PDF view path exists | `pdf_views[]` | Warning | File does not exist |
| Each PDF view outside module dir | `pdf_views[]` | Error | Path not under `module_dir` |

### 6.2 View ownership alignment

| Check | Severity | Condition |
|---|---|---|
| View file owner matches report owner | Info | View file is in a different module's directory than the declaring module |
| View file in app views, not module views | Info | View path references `../../` into app-level views (possible cross-module dependency) |

### 6.3 Invalid reference classification

| Reference type | Example | Severity |
|---|---|---|
| Path traversal | `../../config/db.php` | Error |
| Absolute path | `/etc/passwd` | Error |
| Missing extension | `Views/report` | Warning |
| Wrong extension | `Views/report.html` | Warning (not PHP) |
| Directory instead of file | `Views/` | Error |
| Non-existent module | `../OtherModule/Views/report.php` | Error |

### 6.4 View validation layer

| Layer | Role |
|---|---|
| **App/Module** | Declares correct module-relative path |
| **Platform (compilation)** | Resolves path to absolute, checks existence, checks module boundary |
| **System Tools** | Read-only diagnostic: view path integrity across all reports |
| **Studio** | Preflight: verify draft view paths exist and are within module boundary |
| **Shell Runtime** | Fallback existence check at render time (compile-time check should catch this, but runtime safety check is cheap) |

### 6.5 Current state assessment

All 32 reports declare `view: "Views/report.php"`. The existence of this file depends on each module. The compilation pipeline already implicitly requires this file — if the file does not exist, the report would fail to render, but the current registry service does not explicitly check file existence at compilation time.

---

## 7. Parameter Validation

### 7.1 Parameter sub-resource schema (from Report Resource Contract)

```yaml
Param:
  key:       string   # REQUIRED  — parameter identifier
  type:      string   # REQUIRED  — "date" | "daterange" | "select" | "text" | "boolean"
  label:     string   # REQUIRED  — localized label
  required:  boolean  # OPTIONAL  — default false
  options:   string[] # OPTIONAL  — for "select" type
  default:   any      # OPTIONAL  — default value
```

### 7.2 Parameter validation matrix

| Check | Field | Severity | Condition |
|---|---|---|---|
| Key present | `key` | Error | Missing or empty |
| Key is string | `key` | Error | Non-string type |
| Key unique within report | `key` (per report) | Error | Duplicate key in same report's `parameters[]` array |
| Key matches `[a-z_]+` pattern | `key` | Warning | Convention — lowercase with underscores |
| Type present | `type` | Error | Missing or empty |
| Type is valid | `type` | Error | Not one of `date`, `daterange`, `select`, `text`, `boolean` |
| Label present | `label` | Error | Missing or empty |
| Label is string | `label` | Error | Non-string type |
| Required is boolean | `required` | Warning | Non-boolean type (auto-coerced, but advisory) |
| Options present when type=select | `options` | Warning | Type is `select` but `options` is missing or empty |
| Options is array when present | `options` | Warning | Non-array type |
| Default type matches param type | `default` | Warning | String default for `date` type; boolean default for `boolean` type |
| Default value in options (select) | `default` + `options` | Info | Type is `select` but default value not in options array |

### 7.3 Parameter count validation

| Check | Severity | Condition |
|---|---|---|
| Zero parameters | Info | Report has no filters — valid, many reports are fixed-query |
| Excessive parameters (>10) | Warning | More than 10 parameters may indicate report trying to be a query builder |
| Single parameter | — | No check needed (most common pattern) |

### 7.4 Parameter type-specific validation

| Type | Valid | Warning |
|---|---|---|
| `date` | String in `YYYY-MM-DD` format | Non-date string as default |
| `daterange` | Object/string with start and end | Default not parseable as date range |
| `select` | Options array non-empty | Options empty; default not in options |
| `text` | Any string | Empty options array (text doesn't need options) |
| `boolean` | `true`/`false` | Non-boolean default (will be coerced) |

### 7.5 Current state

No module currently declares parameters. All 32 reports are fixed-query. Parameter validation is documented for future use when Studio Report Designer proposes parameterized reports.

---

## 8. Compilation Validation

### 8.1 Pre-compilation checks

Before compilation begins, the source declaration is validated for structural integrity:

| Check | Severity | Block compilation? |
|---|---|---|
| `plugin.json` is valid JSON | Error | Yes |
| `reports` key is an array | Error | Yes — skip malformed declaration |
| Each report entry is an object (associative array) | Error | Yes — skip individual malformed entry |
| `report_key` present and non-empty | Error | Yes — no key means no identity |
| `owner` present and valid | Error | Yes — no owner means no ownership boundary |
| `lifecycle` present and valid | Error | Yes — no lifecycle means no filter rule |

### 8.2 Compilation-time validation

Checks performed during the compilation pipeline (current + future):

| Check | Severity | Outcome |
|---|---|---|
| Module is active | Error | **Block** — report excluded from compilation output |
| Lifecycle is `active_only` | Error on non-matching | **Block** — report excluded unless `"installed"` or `"always"` |
| `report_key` globally unique | Error | **Block** — both colliding reports excluded |
| `view` file exists | Error | **Block** — report excluded (cannot render without view) |
| `owner_app` resolves from manifest | Error | **Block** — cannot determine owning app |
| `owner_module` resolves from directory | Error | **Block** — cannot determine owning module |
| `module_dir` resolves from filesystem | Error | **Block** — cannot resolve view paths |
| `permission` exists in registry | Warning | **Compile with warning** — permission may be pending |
| `export_view` file existence (if declared) | Warning | **Compile with warning** — export may use report view |
| `pdf_views` file existence (if declared) | Warning | **Compile with warning** — each path checked independently |
| `parameters[].type` valid | Error | **Block** — parameter type is structurally invalid |
| `parameters[].key` unique within report | Error | **Block** — duplicate parameter keys |
| `export_sources.table` exists in DB | Info | **Compile** — advisory only |

### 8.3 Auto-default application

When compilation succeeds with absent optional fields, the compiler applies graceful defaults (Section 3.1 of Report Resource Contract). Auto-defaults are applied only after all structural validations pass — a missing required field prevents compilation entirely, so no defaults are applied.

### 8.4 Compilation output states

```
Source declaration
  │
  ├─ Pre-compilation checks pass?
  │   ├─ No → ERROR: Report excluded. Log reason.
  │   └─ Yes → Continue
  │
  ├─ Compilation checks pass?
  │   ├─ No (Error severity) → ERROR: Report excluded. Log reason.
  │   ├─ No (Warning severity only) → WARNING: Report compiled. Log warnings. Include in output.
  │   └─ Yes → SUCCESS: Report compiled. Include in output.
  │
  └─ Compiled ReportResource
      └─ Optional: System Tools read-only diagnostic
```

### 8.5 Error → warning escalation rules

| Rule | Example |
|---|---|
| Error may never be downgraded to warning by consumer | A missing `view` path cannot be ignored at runtime |
| Warning may be escalated to error by policy | A missing permission may be treated as error in production environments |
| Info may be escalated to warning by policy | Advisory table existence check escalated by operations policy |

---

## 9. Runtime Validation

### 9.1 What belongs at compilation vs runtime

| Check | Stage | Rationale |
|---|---|---|
| Field presence | Compilation | Absence means report cannot function |
| File path existence | Compilation | File should exist; runtime render will fail anyway |
| Permission key existence | Compilation (warning) | Advisory; runtime ACL is definitive |
| Permission authorization | Runtime | ACL requires user context, not available at compilation |
| Owner resolution | Compilation | Owner is a declaration property, not runtime-dependent |
| View rendering | Runtime | Report data varies by user, date, parameters |
| Parameter validation | Compilation (schema) + Runtime (values) | Schema at compile time, values at render time |
| Export format availability | Compilation | Formats are declaration properties |

### 9.2 Runtime validation by consumer

**Navigation sidebar:**
- Report exists in compiled registry (already validated)
- Current user has `permission` via ACL → if denied, entry is suppressed
- No additional runtime validation needed

**Admin dashboard cards:**
- Report exists in compiled registry
- Current user has `permission` via ACL
- `visualization` field present (defaults to `"table"` if absent)
- KPI data is loaded from module service (not report-specific validation)

**Operator widgets:**
- Report exists in compiled registry
- Widget data comes from adapter, not directly from ReportResource
- ReportResource provides `title` and `visualization` hint only

**Display panels:**
- Report title for display label
- KPI data loaded from module data services
- No permission check (display is public kiosk; data is pre-filtered by adapter)

**Report view rendering:**
- View path exists (compile-time check, runtime safety fallback)
- Parameters from URL query match declared `parameters` schema
- Module is still active (double-check at render time for `lifecycle: "active_only"`)
- User has `permission` via ACL

**CSV export:**
- Report exists in compiled registry
- `export_sources.table` exists (compile-time advisory, runtime hard check)
- User has `permission` via ACL
- `lifecycle` permits export (active_only reports are exportable if active)

**PDF export:**
- `pdf_views[0]` path exists (compile-time check, runtime safety fallback)
- `PdfService` is available
- User has `permission` via ACL

**Export history recording:**
- `report_key` and `owner_app` are present (both are always available from compiled resource)
- No additional validation — export history is a passive audit log

**Scheduled execution (future):**
- Report exists in compiled registry
- Lifecycle permits scheduling (`"installed"` or `"always"`)
- Parameters from schedule config match declared `parameters` schema
- System-level permission (not user-level) is checked

### 9.3 Runtime validation severity model

Runtime checks use a simplified severity model:

| Severity | Behavior |
|---|---|
| **Deny** | Block the operation. User sees 403 or suppressed UI element. |
| **Fallback** | Use default behavior. Example: `visualization` missing → render as `"table"`. |
| **Log** | Record warning. Continue with operation. Example: export table advisory check at runtime. |

---

## 10. Validation Severity Model

### 10.1 Standard severity levels

| Level | Symbol | Meaning | Pipeline behavior | Examples |
|---|---|---|---|---|
| **Critical** | 🔴 | Violation introduces security or integrity risk | Block compilation; block render; alert operator | Permission ownership bypass; view path traversal; code injection in report_key |
| **Error** | 🔴 | Report cannot function without this field | Block compilation; report excluded from registry | Missing `report_key`; missing `view` file; invalid `owner` value |
| **Warning** | 🟡 | Report functions but has quality or future-risk issue | Compile with warning; include in registry; log | Permission key not found; view file zero bytes; module directory mismatch |
| **Info** | 🔵 | Advisory observation, no action required | Compile normally; record in diagnostic output | Report has no parameters; report uses default visualization; table advisory check |

### 10.2 Severity assignment rules

1. **Security violations** are always Critical — path traversal, code injection, permission bypass
2. **Structural defects** are always Error — missing required field, invalid format, missing file
3. **Best-practice deviations** are Warning — convention violations, legacy patterns, advisory existence
4. **Observations** are Info — default values, structural metadata, file ownership info

### 10.3 Severity mapping by field

| Field | Critical | Error | Warning | Info |
|---|---|---|---|---|
| `report_key` | — | Missing; duplicate; special characters | Non-convention format | Key length |
| `owner` | — | Missing; invalid value | — | — |
| `owner_app` | — | Cannot resolve | Manifest missing `owner_app` | — |
| `owner_module` | — | Cannot resolve | Directory name mismatch | — |
| `module_dir` | Path traversal | Does not exist | Outside expected structure | — |
| `title` | — | Missing; empty | HTML in title; excessive length | — |
| `description` | — | — | — | Absent (default applied) |
| `category` | — | — | Invalid category value | Absent (default `"operational"`) |
| `visualization` | — | — | Invalid visualization value | Absent (default `"table"`) |
| `permission` | Permission bypass | Missing; empty | Not in registry; deprecated | Cross-app permission key |
| `lifecycle` | — | Missing; invalid value | — | Uncommon lifecycle value |
| `view` | Path traversal | Missing; not found | Zero bytes; non-PHP file | Same as module default view |
| `export_view` | Path traversal | Outside module dir | Not found | Absent (uses report view) |
| `pdf_views` | Path traversal | Outside module dir | Path not found | Absent (no PDF export) |
| `parameters` | — | Schema violation (type, key) | Type-default mismatch | Zero parameters |
| `export_formats` | — | — | Invalid format string | Absent (default applied) |
| `export_sources` | — | Table name invalid | Table not found in DB | Absent (legacy fallback) |
| `scope` | — | — | — | Absent (unclassified) |

### 10.4 Severity escalation and de-escalation rules

| Rule | Example |
|---|---|
| Warning may be escalated to Error by environment policy | Production environment: missing permission key is an error, not a warning |
| Info may be escalated to Warning by project convention | Legacy `CSV_REPORT_SOURCES` usage flagged as warning to encourage migration |
| Error may never be de-escalated to Warning by consumer | Missing `view` path means report cannot render — no consumer can fix this |
| Critical may never be suppressed | Security bypass is always actionable |

---

## 11. Studio Validation Role

### 11.1 Allowed validation activities

| Activity | Description | Severity handling |
|---|---|---|
| **Preflight validation** | Validate draft ReportResource against the same rules as compilation | Draft may contain errors; errors block save/proposal, not editing |
| **Preview validation** | Validate that a draft can produce a meaningful report preview | Errors prevent preview rendering; warnings displayed in preview header |
| **Change record generation** | Document which validation rules passed/failed in the change record | Warnings and errors recorded in `validation_gates` and `validation_status` fields of change record |
| **Structural validation** | Check schema compliance before proposing changes | Same structural rules as compilation |
| **Permission advisory** | Warn if draft uses permission from a different owner | Warning displayed in designer UI |
| **View existence check** | Verify draft view paths exist before preview | Error prevents preview; warning logged |

### 11.2 Not allowed validation activities

| Activity | Why prohibited |
|---|---|
| **Override validation failures** | Studio must not bypass ownership rules. A report with an invalid permission key must not be deployed regardless of Studio's UI. |
| **Bypass ownership rules** | Studio cannot change `owner`, `report_key`, or `lifecycle` of a module-owned report in production. |
| **Deploy invalid resources** | Studio must not write invalid ReportResource data to module artifacts. The 12-step workflow (Section 3 of Report Designer Operating Contract) requires owner approval for every change. |
| **Suppress compilation errors** | Studio drafts may contain errors, but those errors must be surfaced to the user, not hidden. |
| **Bypass ACL** | Studio must not grant view permission to a report the viewer is not authorized to see. |

### 11.3 Studio validation boundaries

```text
Studio Report Designer
  │
  ├─ Load current ReportResource (read from plugin.json)
  ├─ User edits title, parameters, visualization, etc.
  ├─ Studio validates draft:
  │   ├─ Structural: all required fields present? ✅/❌
  │   ├─ Schema: valid parameter types? ✅/❌
  │   ├─ Permission: key exists? ✅/⚠️
  │   ├─ Views: paths exist? ✅/❌
  │   └─ Ownership: matches source module? ✅/❌
  │
  ├─ If errors: block proposal; show errors in UI
  ├─ If warnings: allow proposal; show warnings in UI
  ├─ If valid: generate change record → await owner approval
  │
  └─ On approval:
      ├─ System Tools validates final proposal
      ├─ Owner integrates change into module artifact
      └─ Next compilation picks up updated declaration
```

### 11.4 Studio validation vs compilation validation

| Aspect | Studio preflight | Compilation pipeline |
|---|---|---|
| When | During editing, before proposal | During module registry build |
| Source | Draft ReportResource (in-memory) | Declared ReportResource (plugin.json) |
| Severity treatment | Errors block proposal; warnings allow | Errors block compilation; warnings include |
| Ownership impact | Draft ownership is advisory | Compilation ownership is authoritative |
| Permission check | Advisory existence check | Warning existence + runtime ACL |
| File existence | Check draft view paths | Check declared view paths |
| Output | Validation result displayed in designer UI | Compiled ReportResource or exclusion |

---

## 12. Recommended Report Validation Contract Structure

Based on this audit, the formal Report Validation Contract should follow this outline:

```markdown
# Report Validation Contract

Status: [status line]
Purpose: Define validation rules for ReportResource across declaration, compilation, and runtime.

## 1. Validation Pipeline Overview

    Source → Declaration Parse → Compilation → ReportResource → Runtime Consumption

## 2. Severity Model

    2.1 Severity levels: Critical, Error, Warning, Info
    2.2 Severity assignment rules
    2.3 Severity escalation/de-escalation rules

## 3. Declaration Validation

    3.1 Structural validation per required field
    3.2 Structural validation per optional field
    3.3 Structural validation per computed field
    3.4 Parameter schema validation
    3.5 ExportSource schema validation

## 4. Ownership Validation

    4.1 Owner value validation
    4.2 Owner-app resolution validation
    4.3 Owner-module resolution validation
    4.4 Owner-source matching rules
    4.5 Computed ownership validation

## 5. Key Uniqueness Validation

    5.1 Global uniqueness requirement
    5.2 Collision detection and handling
    5.3 Upgrade compatibility

## 6. Permission Validation

    6.1 Permission key existence check (advisory)
    6.2 Permission ownership alignment
    6.3 Permission lifecycle compatibility
    6.4 Runtime ACL is the definitive gate

## 7. View Path Validation

    7.1 View path existence check (blocking)
    7.2 Export view path check (advisory)
    7.3 PDF view path checks (advisory)
    7.4 Path traversal and module boundary enforcement
    7.5 Runtime fallback check

## 8. Parameter Validation

    8.1 Param schema validation (type, key, label)
    8.2 Type-specific validation rules
    8.3 Uniqueness within report

## 9. Compilation Validation

    9.1 Pre-compilation structural gates
    9.2 Compilation-time validation rules
    9.3 Auto-default application rules
    9.4 Compilation output states (exclude / include-with-warning / include)
    9.5 Error → warning escalation rules

## 10. Runtime Validation

    10.1 Compilation vs runtime: what goes where
    10.2 Consumer-specific validation:
        - Navigation
        - Dashboard cards
        - Operator widgets
        - Display panels
        - Report view rendering
        - CSV export
        - PDF export
        - Export history
        - Scheduled execution (future)

## 11. Studio Validation Role

    11.1 Allowed: preflight, preview, change record generation
    11.2 Prohibited: override failures, bypass ownership, deploy invalid resources
    11.3 Studio preflight vs compilation validation mapping

## 12. Validation Implementation Model

    12.1 Recommended validation interface shape
    12.2 Integration with ModuleReportRegistryService
    12.3 Error reporting model

## 13. Current-State Assessment

    13.1 All 32 current declarations pass required validation
    13.2 Known gaps in current implementation
    13.3 Compatibility guarantee (zero-break)

## 14. Cross-Contract Alignment

    Report Resource Contract
    Report Designer Operating Contract
    Resolved Runtime Contract Pipeline
    Surface Contribution Contract
    MODULE-CONTRACT.md

## 15. Non-Goals

    What this contract does not authorize.

## 16. Validation

    Architecture gates to run.
```

---

## Summary of Key Decisions

| Question | Decision |
|---|---|
| What blocks compilation? | Missing or invalid required field; unresolved owner; missing view file; duplicate report_key; invalid parameter schema |
| What compiles with warning? | Missing permission key; missing export_view file; missing pdf_views file; convention violations |
| What is info only? | Default values; advisory table existence; parameter count; view file ownership |
| When is permission checked? | Advisory at compilation (warning); definitive at runtime (ACL gate) |
| Can Studio override validation? | No — Studio may preflight but must not bypass ownership or deploy invalid resources |
| Who owns uniqueness? | Platform — global uniqueness check during compilation |
| Who owns view existence? | Platform — file existence check during compilation; runtime fallback safety |
| What is runtime-only validation? | ACL authorization; parameter value validation; module active double-check |
| Are current reports affected? | No — all 32 current declarations pass all required validations |

AGENT-COMPLIANCE-CHECKLIST.md

Core touched? NO

AGENTS.md followed? YES

Architecture rules followed: Report Validation Discovery Audit reinforces ownership boundaries already established in Report Resource Contract. No new schema fields introduced. No runtime, DB, or UI changes authorized.
