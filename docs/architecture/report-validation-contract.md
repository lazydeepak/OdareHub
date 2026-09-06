# Report Validation Contract

Status: Architecture contract baseline. Documentation-only. No runtime changes, DB changes, UI changes, or implementation authorized.

Purpose: Define validation rules for `ReportResource` across declaration, compilation, and runtime. Formalizes validation scope, ownership, severity, and failure handling. Reinforces ownership boundaries established in the Report Resource Contract. Uses only fields defined in that contract — no new schema fields, no redesign of ReportResource.

---

## 1. Validation Pipeline Overview

ReportResource validation occurs at four stages:

```text
plugin.json declaration
  │
  ├─ Stage 1: Declaration Parse (Platform)
  │   ├─ Structural: field presence, type, format
  │   ├─ Ownership: owner values, lifecycle values
  │   ├─ Schema: parameter types, export source shape
  │   └─ Outcome: error → excluded from registry
  │
  ├─ Stage 2: Compilation (ModuleReportRegistryService)
  │   ├─ Activation: module is active
  │   ├─ Lifecycle: matches filter criteria
  │   ├─ Resolution: owner_app, owner_module, module_dir
  │   ├─ Existence: view path, export_view path, pdf_views paths
  │   ├─ Uniqueness: report_key globally unique
  │   ├─ Permission: key existence check (advisory)
  │   └─ Outcome: error → excluded; warning → included with log
  │
  ├─ Stage 3: System Tools (read-only diagnostic)
  │   ├─ Table existence: export_sources.table
  │   ├─ Permission cross-check: existence, ownership, deprecation
  │   ├─ Ownership boundary: compliance with module/app ownership rules
  │   └─ Outcome: diagnostic report, no runtime effect
  │
  └─ Stage 4: Runtime Consumption (Shell / Export Engine)
      ├─ ACL authorization: user has required permission
      ├─ Parameter value validation: user-supplied values match schema
      ├─ View existence: fallback safety check at render time
      └─ Outcome: deny access, suppress UI element, or render
```

### 1.1 Pipeline ownership

| Stage | Owner | Scope |
|---|---|---|
| Declaration Parse | Platform (registry service) | Schema correctness |
| Compilation | Platform (ModuleReportRegistryService) | Resolution, existence, uniqueness |
| System Tools | System Tools (read-only) | Boundary compliance, advisory checks |
| Runtime | Shell / ACL / Export Engine | Authorization, parameter values, render safety |

### 1.2 What each pipeline stage validates

| Aspect | Declaration Parse | Compilation | System Tools | Runtime |
|---|---|---|---|---|
| Field presence | ✅ Required | — | — | — |
| Field type/format | ✅ | — | — | — |
| Owner validity | ✅ | — | — | — |
| Lifecycle validity | ✅ | — | — | — |
| Parameter schema | ✅ | — | — | — |
| Module activation | — | ✅ | — | — |
| Lifecycle filter | — | ✅ | — | — |
| Owner resolve | — | ✅ | — | — |
| Path existence | — | ✅ | — | ✅ (fallback) |
| Key uniqueness | — | ✅ | — | — |
| Permission existence | — | ✅ (advisory) | ✅ | — |
| Table existence | — | — | ✅ (advisory) | — |
| Permission cross-check | — | — | ✅ | — |
| ACL authorization | — | — | — | ✅ |
| Parameter values | — | — | — | ✅ |

---

## 2. Validation Scope

### 2.1 Field-to-validation-type matrix

Every ReportResource field and its validation requirements across five dimensions:

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

### 2.2 Layer responsibility matrix

| Layer | Structural | Ownership | Permission | Runtime | Compilation |
|---|---|---|---|---|---|
| **App/Module** | Declares valid values | Declares correct ownership | Declares existing key | — | — |
| **Platform** | Schema validation on parse | Owner-app/module resolution gate | Permission existence gate (advisory) | Export engine validation | Compilation pipeline owner |
| **Studio** | Preflight (draft only) | Draft ownership check | Draft permission check | — | — |
| **System Tools** | Read-only diagnostic | Read-only ownership check | Read-only existence check | — | Read-only consistency check |
| **Compilation Pipeline** | Required field presence | Owner resolve | Permission existence (warning) | — | Block/allow on errors |
| **Shell Runtime** | — | — | ACL authorization gate | Consumer-specific field check | — |

### 2.3 Validation dimension definitions

| Dimension | Definition | Enforcement layer |
|---|---|---|
| **Structural** | Field presence, type correctness, format validity, schema compliance (Param, ExportSource) | Declaration Parse (Platform) |
| **Ownership** | Owner value validity, owner-app resolve, owner-module resolve, owner-source matching | Compilation (Platform) |
| **Permission** | Permission key existence, ownership alignment, lifecycle compatibility (advisory); ACL authorization (definitive) | Compilation (advisory) + Runtime ACL (definitive) |
| **Runtime** | Consumer-specific checks: ACL gate, parameter values, view fallback, module active double-check | Shell / Export Engine |
| **Compilation** | Module activation, lifecycle filter, path existence, key uniqueness, computational field resolution | ModuleReportRegistryService (Platform) |

---

## 3. Severity Model

### 3.1 Standard severity levels

| Level | Symbol | Meaning | Pipeline behavior | Examples |
|---|---|---|---|---|
| **Critical** | 🔴 | Security or integrity risk | Block compilation; block render; alert operator | Path traversal in view; code injection in report_key; permission ownership bypass |
| **Error** | 🔴 | Report cannot function without correction | Block compilation; report excluded from registry | Missing report_key; missing view file; invalid owner value |
| **Warning** | 🟡 | Report functions but has quality or future-risk issue | Compile with warning; include in registry; log | Permission key not found; non-convention report_key; zero-byte view file |
| **Info** | 🔵 | Advisory observation, no action required | Compile normally; record in diagnostic output | Report has no parameters; default visualization applied; legacy CSV_SOURCE fallback used |

### 3.2 Severity assignment rules

| Rule | Applies to | Rationale |
|---|---|---|
| Security violations are always **Critical** | Path traversal, code injection, permission bypass | Runtime integrity and access boundary risk |
| Structural defects are always **Error** | Missing required field, invalid format, missing file | Report cannot function without the field |
| Best-practice deviations are **Warning** | Convention violations, advisory existence, legacy patterns | Report functions but signals future risk |
| Observations are **Info** | Default values, structural metadata, advisory checks | No action needed; recorded for diagnostics |

### 3.3 Severity escalation and de-escalation rules

| Operation | Allowed? | Example |
|---|---|---|
| Error downgraded to Warning | **Never** | Missing view path cannot be ignored by any consumer |
| Warning escalated to Error | Yes (environment policy) | Production environment treats missing permission as Error |
| Info escalated to Warning | Yes (project convention) | Legacy CSV_REPORT_SOURCES usage flagged as Warning |
| Critical downgraded | **Never** | Security bypass is always actionable |

### 3.4 Severity mapping by field

| Field | Critical | Error | Warning | Info |
|---|---|---|---|---|
| `report_key` | — | Missing; duplicate; special characters | Non-convention format | Key length |
| `owner` | — | Missing; invalid value | — | — |
| `owner_app` | — | Cannot resolve | Manifest missing owner_app | — |
| `owner_module` | — | Cannot resolve | Directory name mismatch | — |
| `module_dir` | Path traversal | Does not exist | Outside expected structure | — |
| `title` | — | Missing; empty | HTML in title; excessive length | — |
| `description` | — | — | — | Absent (default applied) |
| `category` | — | — | Invalid category value | Absent (default operational) |
| `visualization` | — | — | Invalid visualization value | Absent (default table) |
| `permission` | Permission bypass | Missing; empty | Not in registry; deprecated | Cross-app permission key |
| `lifecycle` | — | Missing; invalid value | — | Uncommon lifecycle value |
| `view` | Path traversal | Missing; not found | Zero bytes; non-PHP file | Same as module default view |
| `export_view` | Path traversal | Outside module dir | Not found | Absent (uses report view) |
| `pdf_views` | Path traversal | Outside module dir | Path not found | Absent (no PDF export) |
| `parameters` | — | Schema violation (type, key) | Type-default mismatch | Zero parameters |
| `export_formats` | — | — | Invalid format string | Absent (default applied) |
| `export_sources` | — | Table name invalid | Table not found in DB | Absent (legacy fallback) |
| `scope` | — | — | — | Absent (unclassified) |

---

## 4. Required Field Validation

### 4.1 Required fields

The Report Resource Contract defines six required fields:

- `report_key` — global identity
- `owner` — ownership layer
- `title` — display name
- `permission` — access control key
- `lifecycle` — inclusion policy
- `view` — rendering template path

### 4.2 Validation per required field

| Field | Valid | Warning | Error |
|---|---|---|---|
| `report_key` | Non-empty string matching `{app}.{module}.{purpose}` convention | Key does not match convention but is non-empty | Missing; empty; non-string; duplicate globally; special characters other than `.` and `_`; exceeds 255 chars |
| `owner` | `"app"` \| `"module"` \| `"platform"` (case-insensitive) | Trailing whitespace (trimmed) | Missing; empty; unrecognized value; `"core"` |
| `title` | Non-empty string, any valid UTF-8 | HTML tags; exceeds 200 chars; emoji content | Missing; empty; non-string; whitespace-only |
| `permission` | Non-empty string, key exists in permission registry | Key not in registry; deprecated key; cross-app ownership | Missing; empty; non-string |
| `lifecycle` | `"active_only"` \| `"installed"` \| `"always"` (case-insensitive) | — | Missing; empty; unrecognized value |
| `view` | Non-empty string, file exists at module-relative path | Zero-byte file; non-PHP file extension | Missing; empty; non-string; file not found; path traversal; outside module directory |

### 4.3 Auto-default prohibition

No required field receives an auto-default. A missing or invalid required field blocks compilation. This is enforced because every required field is essential for the report to function — no consumer can compensate for an absent identity, owner, permission, lifecycle, title, or view path.

### 4.4 Compatibility

All 32 current declarations pass every required-field check. Zero existing modules are affected by these rules.

---

## 5. Key Uniqueness Validation

### 5.1 Uniqueness scope

`report_key` must be **globally unique** across all apps, modules, and plugins.

| Scope | Requirement | Rationale |
|---|---|---|
| **Global** (all apps) | No duplicate keys allowed | Export history, navigation, and dashboard consumers key by `report_key` without app prefix disambiguation |
| **App-level** | Not sufficient alone | Two apps could independently declare overlapping keys |
| **Module-level** | Implicit when key follows `{app}.{module}.{purpose}` convention | Module scope is encoded in the key itself, not in a separate field |

### 5.2 Uniqueness error conditions

| Condition | Severity | Handling |
|---|---|---|
| Two reports with identical `report_key` | Error | Both excluded from compilation; error logged |
| Same `report_key` declared in two different apps | Error | Collision likely from convention violation; both excluded |
| Same `report_key` in same module's `reports[]` array | Error | Duplicate within single plugin.json — configuration error |
| Stale key from deactivated module collides with active module's key | Error | Active module's report wins; deactivated module already filtered by activation gate |

### 5.3 Collision handling

```text
Collision detected for report_key "manufacturing.daily_orders.overview"
  ├─ Source A: Manufacturing/DailyOrders (active)
  ├─ Source B: Any second source
  └─ Resolution:
      ├─ Both excluded if within same plugin.json array (declaration error)
      ├─ Source A wins if Source B from deactivated module (activation filter)
      ├─ Both excluded if from two different active modules (needs manual fix)
      └─ Error logged, no silent override, no last-writer-wins
```

### 5.4 Uniqueness implementation model

```
Step 1: Collect all report_key values from all active modules (cross-app)
Step 2: Build frequency map
Step 3: Any key with count > 1 → Error
Step 4: Exclude all reports with colliding keys from compilation
Step 5: Log collision details for manual resolution
```

The uniqueness check is a **compilation-time** validation. It must be performed after module activation filtering but before the ReportResource array is returned.

### 5.5 Upgrade compatibility

| Scenario | Behavior | Risk |
|---|---|---|
| Module A adds a key that collides with future Module B's key | Error at Module B activation time | Low — collision is compile-time, not runtime |
| Module changes report_key between versions | New key is treated as different report; old export history records retain old key | Medium — consumers must update references |
| Two versions of same module installed simultaneously (prevented by lifecycle) | Both processed; collision error | Low — prevented by module lifecycle gates |

---

## 6. Ownership Validation

### 6.1 Ownership validation rules

| Check | Input | Severity | Condition |
|---|---|---|---|
| Owner value valid | `owner` field | Error | Not `"app"`, `"module"`, or `"platform"` |
| Owner value present | `owner` field | Error | Missing or empty |
| Owner app resolves | `owner_app` (computed from manifest) | Error | Cannot resolve owner_app from module manifest |
| Owner app exists in registry | `owner_app` (computed) | Warning | App key not found in app registry |
| Owner module resolves | `owner_module` (computed from directory) | Error | Module directory does not exist or is unreadable |
| Owner matches declaration source | `owner` field + source location | Error | owner: "platform" declared by business module |
| Owner module activated | `owner_module` (computed) | Error | Module is not active (filtered by activation gate) |

### 6.2 Owner-source matching

| Declared `owner` | Allowed source locations | Error if |
|---|---|---|
| `"module"` | Any module directory | Declared in app-level manifest (not inside a module) |
| `"app"` | App-level manifest or app root | Declared inside a module |
| `"platform"` | Platform app only | Declared in Manufacturing or SBAIO |

### 6.3 Computed ownership validation

| Computed field | Validation | Severity |
|---|---|---|
| `owner_app` | Resolved from module manifest owner_app field | Error if owner_app missing or empty in manifest |
| `owner_app` | Resolved value starts with `"apps/"` | Error — owner_app should be app identifier, not filesystem path |
| `owner_module` | Lowercased module directory name | Warning — directory name may not match expected module key |
| `module_dir` | Directory exists and is readable | Error — filesystem path invalid |
| `module_dir` | Located under known app root | Warning — path outside expected `/apps/{app}/modules/` structure |

### 6.4 Ownership validation layer

| Layer | Role |
|---|---|
| **App/Module** | Declares correct owner; ensures manifest has owner_app |
| **Platform (compilation)** | Resolves and validates owner_app, owner_module, module_dir |
| **Platform (registration)** | Validates owner matches declaration source |
| **System Tools** | Read-only diagnostic: cross-check owner declarations against filesystem structure |
| **Studio** | Preflight: verify draft's declared owner matches source module |
| **Shell Runtime** | Not involved — ownership is resolved at compilation |

---

## 7. Permission Validation

### 7.1 Permission validation rules

| Check | Severity | Condition | Stage |
|---|---|---|---|
| Permission key present | Error | Field missing or empty | Declaration parse |
| Permission key is string | Error | Non-string type | Declaration parse |
| Permission key exists in registry | Warning | Key not found in any app/module permission declaration | Compilation |
| Permission key refers to deprecated key | Warning | Key exists but marked deprecated | Compilation |
| Permission key owned by different app | Warning | Key belongs to different app than report's owner_app | Compilation |
| Permission key exists but is inactive | Info | Permission declared but owning module is inactive | System Tools |
| User has this permission | **Definitive gate** | ACL authorization at render time | Runtime |

### 7.2 Severity rationale for advisory existence

Permission existence at compilation is **Warning**, not Error, because:

1. Permissions may be added by other modules installed later
2. The permission registry may not be fully populated at compilation time
3. A missing permission does not break report structure — it only affects access gating
4. The ACL authorization gate at runtime will deny access regardless of compilation warnings

### 7.3 Runtime ACL is the definitive gate

```text
Compilation: "permission key X not found in registry" → Warning
  └─ ReportResource compiled with permission field intact
      └─ Shell renders navigation/dashboard entry
          └─ Runtime ACL: "does user have key X?"
              ├─ Yes → render entry
              └─ No → suppress entry
```

The compilation permission check is advisory only. Runtime ACL authorization is the hard gate. Compilation warnings about missing permissions cannot block a report from rendering — only the runtime ACL can deny or permit access.

### 7.4 Permission key format validation

| Pattern | Severity | Example |
|---|---|---|
| `{app}.{module}.{action}` | Valid (preferred) | `manufacturing.assembly_entries.view` |
| `{app}.{scope}.{action}` | Valid | `app.manufacturing.access` |
| Empty key | Error | `""` |
| Key with spaces | Error | `app manufacturing access` |
| Key exceeding 255 chars | Warning | Storage constraint |

### 7.5 Lifecycle compatibility

| Permission state | Report lifecycle | Result |
|---|---|---|
| Permission exists, module active | `active_only` | Full access (subject to ACL) |
| Permission exists, module active | `installed` | Compiled, Shell may suppress |
| Permission exists, module active | `always` | Always compiled |
| Permission exists, module inactive | `active_only` | Report excluded by activation filter before permission check |
| Permission missing | Any | Warning logged; report compiles; runtime ACL denies |

---

## 8. View Validation

### 8.1 View path validation rules

| Check | Field | Severity | Condition | Stage |
|---|---|---|---|---|
| View path present | `view` | Error | Missing or empty | Declaration parse |
| View path is string | `view` | Error | Non-string type | Declaration parse |
| View file exists | `view` | Error | File does not exist at resolved absolute path | Compilation |
| View file readable | `view` | Warning | File exists but is not readable | Compilation |
| View file is PHP | `view` | Warning | File exists but is not a `.php` file | Compilation |
| View path traversal | `view` | Critical | Contains `..` or absolute path reference | Compilation |
| View outside module dir | `view` | Error | Resolved path is not under module_dir | Compilation |
| Export view present (if declared) | `export_view` | Warning | Missing or empty when declared | Declaration parse |
| Export view file exists | `export_view` | Warning | File does not exist (advisory) | Compilation |
| Export view outside module dir | `export_view` | Error | Resolved path not under module_dir | Compilation |
| PDF view array is array | `pdf_views` | Warning | Non-array type | Declaration parse |
| Each PDF view path exists | `pdf_views[]` | Warning | File does not exist | Compilation |
| Each PDF view outside module dir | `pdf_views[]` | Error | Path not under module_dir | Compilation |

### 8.2 Invalid reference classification

| Reference type | Example | Severity |
|---|---|---|
| Path traversal | `../../config/db.php` | Critical |
| Absolute path | `/etc/passwd` | Critical |
| Missing extension | `Views/report` | Warning |
| Wrong extension | `Views/report.html` | Warning (not PHP, may still be valid) |
| Directory instead of file | `Views/` | Error |
| Non-existent module reference | `../OtherModule/Views/report.php` | Error |

### 8.3 View validation layer

| Layer | Role |
|---|---|
| **App/Module** | Declares correct module-relative path |
| **Platform (compilation)** | Resolves path to absolute; checks existence; checks module boundary |
| **System Tools** | Read-only diagnostic: view path integrity across all reports |
| **Studio** | Preflight: verify draft view paths exist and are within module boundary |
| **Shell Runtime** | Fallback existence check at render time (safety net only) |

---

## 9. Parameter Validation

### 9.1 Parameter schema (from Report Resource Contract)

```yaml
Param:
  key:       string   # REQUIRED  — parameter identifier
  type:      string   # REQUIRED  — "date" | "daterange" | "select" | "text" | "boolean"
  label:     string   # REQUIRED  — localized label
  required:  boolean  # OPTIONAL  — default false
  options:   string[] # OPTIONAL  — for "select" type
  default:   any      # OPTIONAL  — default value
```

### 9.2 Parameter validation rules

| Check | Field | Severity | Condition | Stage |
|---|---|---|---|---|
| Key present | `key` | Error | Missing or empty | Declaration parse |
| Key is string | `key` | Error | Non-string type | Declaration parse |
| Key unique within report | `key` | Error | Duplicate key in same report's `parameters[]` | Declaration parse |
| Key matches `[a-z_]+` | `key` | Warning | Convention — lowercase with underscores | Declaration parse |
| Type present | `type` | Error | Missing or empty | Declaration parse |
| Type is valid | `type` | Error | Not one of: date, daterange, select, text, boolean | Declaration parse |
| Label present | `label` | Error | Missing or empty | Declaration parse |
| Label is string | `label` | Error | Non-string type | Declaration parse |
| Required is boolean | `required` | Warning | Non-boolean type (auto-coerced) | Declaration parse |
| Options present when type=select | `options` | Warning | Type is `select` but options missing or empty | Declaration parse |
| Options is array when present | `options` | Warning | Non-array type | Declaration parse |
| Default type matches param type | `default` | Warning | String default for `date` type; boolean default for `boolean` | Declaration parse |
| Default in options (select) | `default` + `options` | Info | Type is `select` but default not in options array | Declaration parse |
| Parameter count > 10 | — | Warning | Excessive parameters may indicate design issue | Declaration parse |

### 9.3 Type-specific validation

| Type | Valid example | Warning |
|---|---|---|
| `date` | `"2026-06-01"` | Non-date string as default |
| `daterange` | `{"start": "2026-06-01", "end": "2026-06-30"}` | Default not parseable as date range |
| `select` | `["option1", "option2"]` | Options empty; default not in options |
| `text` | `"any string"` | Empty options array (text doesn't need options) |
| `boolean` | `true` or `false` | Non-boolean default (will be coerced) |

### 9.4 Current state

No module currently declares parameters. All 32 reports are fixed-query. Parameter validation is documented for future use when Studio Report Designer proposes parameterized reports. Validation rules are defined now so the contract is complete.

---

## 10. Compilation Validation

### 10.1 Pre-compilation gates

Before compilation begins, the source declaration must pass structural gates:

| Check | Severity | Block compilation? |
|---|---|---|
| plugin.json is valid JSON | Error | Yes — skip entire malformed manifest |
| `reports` key is an array | Error | Yes — skip entire malformed entry |
| Each report entry is an object (associative array) | Error | Yes — skip individual malformed entry |
| `report_key` present and non-empty | Error | Yes — no key means no identity |
| `owner` present and valid | Error | Yes — no owner means no ownership boundary |
| `lifecycle` present and valid | Error | Yes — no lifecycle means no filter rule |
| `title` present and non-empty | Error | Yes — no title means no display name |
| `permission` present and non-empty | Error | Yes — no permission means no access control |

### 10.2 Compilation-time gates

Checks performed during the compilation pipeline:

| Check | Severity | Outcome |
|---|---|---|
| Module is active | Error | **Exclude** — report excluded from compilation output |
| Lifecycle is `active_only` | Error on no-match | **Exclude** — report excluded unless `"installed"` or `"always"` |
| `report_key` globally unique | Error | **Exclude** — both colliding reports excluded |
| `view` file exists | Error | **Exclude** — report excluded (cannot render) |
| `owner_app` resolves from manifest | Error | **Exclude** — cannot determine owning app |
| `owner_module` resolves from directory | Error | **Exclude** — cannot determine owning module |
| `module_dir` resolves from filesystem | Error | **Exclude** — cannot resolve view paths |
| `permission` exists in registry | Warning | **Compile with warning** — advisory check only |
| `export_view` file existence (if declared) | Warning | **Compile with warning** — export uses report view |
| `pdf_views` file existence (if declared) | Warning | **Compile with warning** — per path |
| `parameters[].type` valid | Error | **Exclude** — schema violation |
| `parameters[].key` unique within report | Error | **Exclude** — duplicate parameter keys |
| `export_sources.table` exists in DB | Info | **Compile** — advisory only |

### 10.3 Auto-default application

When compilation succeeds with absent optional fields, graceful defaults are applied:

| Absent field | Default |
|---|---|
| `description` | `""` |
| `category` | `"operational"` |
| `visualization` | `"table"` |
| `parameters` | `[]` |
| `export_formats` | Derived from export_view / pdf_views / legacy CSV_SOURCES |
| `export_sources` | Legacy CSV_REPORT_SOURCES fallback (SBAIO only) |
| `pdf_views` | `[]` |
| `scope` | `""` |

Defaults are applied only after all structural validations pass. A missing required field prevents compilation entirely, so no defaults apply.

### 10.4 Compilation output states

```text
Source declaration
  │
  ├─ Pre-compilation checks pass?
  │   ├─ No → STATE EXCLUDED. Report excluded. Log reason.
  │   └─ Yes → Continue
  │
  ├─ Compilation checks pass?
  │   ├─ No (Error severity) → STATE EXCLUDED. Report excluded. Log reason.
  │   ├─ No (Warning severity only) → STATE WARNING. Report compiled. Log warnings. Include.
  │   └─ Yes → STATE PASS. Report compiled. Include.
  │
  └─ Compiled ReportResource
      └─ (Optional) System Tools read-only diagnostic
```

### 10.5 Exclusion logging

Every exclusion must record:

| Field | Content |
|---|---|
| `report_key` | The key that failed, or `"unknown"` if key was missing |
| `module` | Source module name (if determinable) |
| `app` | Source app name |
| `reason` | Human-readable failure description |
| `severity` | `"error"` \| `"critical"` |
| `field` | The ReportResource field that failed validation |
| `rule` | The validation rule that failed |

---

## 11. Runtime Validation

### 11.1 Compilation vs runtime boundary

| Check | Belongs at | Rationale |
|---|---|---|
| Field presence | Compilation | Absence means report cannot function |
| File path existence | Compilation | File should exist; runtime render will fail anyway |
| Permission key existence | Compilation (Warning) | Advisory; runtime ACL is definitive |
| **Permission authorization** | **Runtime** | ACL requires user context, not available at compilation |
| Owner resolution | Compilation | Owner is a declaration property, not runtime-dependent |
| View rendering | Runtime | Report data varies by user, date, parameters |
| Parameter schema | Compilation | Schema is declaration metadata |
| **Parameter values** | **Runtime** | Values come from user input at request time |
| Export format availability | Compilation | Formats are declaration properties |
| Module active double-check | Runtime (safety) | Module may be deactivated between compilation and render |

### 11.2 Consumer-specific runtime validation

**Navigation sidebar:**
- Report exists in compiled registry (pre-validated)
- User has `permission` via ACL → if denied, entry is suppressed
- No additional runtime validation

**Admin dashboard cards:**
- Report exists in compiled registry
- User has `permission` via ACL
- `visualization` field present (defaults to `"table"` if absent)
- KPI data loaded from module service (not ReportResource validation)

**Operator widgets:**
- Report exists in compiled registry
- Widget data from adapter, not directly from ReportResource
- ReportResource provides `title` and `visualization` hint only

**Display panels:**
- Report title for display label
- KPI data from module data services
- No permission check (display is public kiosk; data is pre-filtered)

**Report view rendering:**
- View path exists (compile-time check; runtime safety fallback)
- Parameters from URL match declared `parameters` schema
- Module is still active (double-check for `lifecycle: "active_only"`)
- User has `permission` via ACL

**CSV export:**
- Report exists in compiled registry
- `export_sources.table` exists (compile-time advisory; runtime hard check if used)
- User has `permission` via ACL
- `lifecycle` permits export (active_only reports are exportable if active)

**PDF export:**
- `pdf_views[0]` path exists (compile-time check; runtime safety fallback)
- `PdfService` is available
- User has `permission` via ACL

**Export history recording:**
- `report_key` and `owner_app` present (always available from compiled resource)
- Passive audit log — no additional validation

**Scheduled execution (future):**
- Report exists in compiled registry
- Lifecycle permits scheduling (`"installed"` or `"always"`)
- Parameters from schedule config match declared schema
- System-level permission (not user-level)

### 11.3 Runtime severity model

Runtime checks use a simplified model:

| Severity | Behavior |
|---|---|
| **Deny** | Block the operation. User sees 403 or suppressed UI element. |
| **Fallback** | Use default behavior. Example: visualization missing → render as `"table"`. |
| **Log** | Record warning. Continue with operation. |

---

## 12. Studio Validation Responsibilities

### 12.1 Allowed validation activities

| Activity | Description | Severity handling |
|---|---|---|
| **Preflight validation** | Validate draft ReportResource against the same rules as compilation | Errors block save/proposal; warnings displayed in UI |
| **Preview validation** | Validate that a draft can produce a meaningful report preview | Errors prevent preview rendering; warnings displayed in preview header |
| **Change record generation** | Document which validation rules passed/failed in change record | Warnings/errors recorded in validation_gates and validation_status |
| **Structural validation** | Check schema compliance before proposing changes | Same structural rules as compilation |
| **Permission advisory** | Warn if draft uses permission from a different owner | Warning displayed in designer UI |
| **View existence check** | Verify draft view paths exist before preview | Error prevents preview; warning logged |

### 12.2 Prohibited validation activities

| Activity | Why prohibited |
|---|---|
| **Override validation failures** | Studio must not bypass ownership rules. An invalid report must not be deployed regardless of Studio's UI. |
| **Bypass ownership rules** | Studio cannot change `owner`, `report_key`, or `lifecycle` of a module-owned report in production. |
| **Deploy invalid resources** | Studio must not write invalid ReportResource data to module artifacts. The 12-step workflow requires owner approval. |
| **Suppress compilation errors** | Studio drafts may contain errors, but errors must be surfaced, not hidden. |
| **Bypass ACL** | Studio must not grant view permission the viewer is not authorized to see. |
| **Change critical/error to warning** | Severity levels are fixed. Studio cannot reclassify a path traversal as a warning. |

### 12.3 Studio validation boundaries

```text
Studio Report Designer
  │
  ├─ Load current ReportResource (read from plugin.json)
  ├─ User edits title, parameters, visualization, etc.
  ├─ Studio validates draft:
  │   ├─ Structural: required fields present? ✅/❌
  │   ├─ Schema: valid parameter types? ✅/❌
  │   ├─ Permission: key exists? ✅/⚠️
  │   ├─ Views: paths exist? ✅/❌
  │   └─ Ownership: matches source module? ✅/❌
  │
  ├─ Errors → block proposal; show errors in UI
  ├─ Warnings → allow proposal; show warnings in UI
  ├─ Valid → generate change record → await owner approval
  │
  └─ On approval:
      ├─ System Tools validates final proposal
      ├─ Owner integrates change into module artifact
      └─ Next compilation picks up updated declaration
```

### 12.4 Studio vs compilation validation

| Aspect | Studio preflight | Compilation pipeline |
|---|---|---|
| When | During editing, before proposal | During module registry build |
| Source | Draft ReportResource (in-memory) | Declared ReportResource (plugin.json) |
| Errors | Block proposal | Block compilation |
| Warnings | Allow proposal | Include with warning |
| Ownership | Draft ownership is advisory | Compilation ownership is authoritative |
| Permission | Advisory existence check | Warning existence + runtime ACL |
| File existence | Check draft view paths | Check declared view paths |
| Output | Validation result in designer UI | Compiled ReportResource or exclusion |

---

## 13. Failure Handling Model

### 13.1 Failure type classification

| Failure type | Severity | Handling |
|---|---|---|
| Structural violation | Error | Report excluded; logged |
| Security violation | Critical | Report excluded; logged; alert operator |
| Ownership violation | Error | Report excluded; logged |
| Uniqueness collision | Error | Both reports excluded; logged |
| Missing view file | Error | Report excluded; logged |
| Permission advisory | Warning | Report included; warning logged |
| Path traversal | Critical | Report excluded; logged; alert operator |
| Parameter schema violation | Error | Report excluded; logged |

### 13.2 Exclusion rules

An excluded ReportResource must:

1. Be omitted from the compiled registry output
2. Be logged with reason, field, severity, and source module
3. Not be available to any runtime consumer
4. Not be rendered in navigation, dashboard, operator, or display surfaces
5. Not be available for export or scheduled execution

### 13.3 Warning rules

A ReportResource compiled with warnings must:

1. Be included in the compiled registry output
2. Be logged with all warnings and their details
3. Be available to all runtime consumers (subject to ACL)
4. Surface warnings in Studio diagnostics (when applicable)

### 13.4 Recovery model

| Scenario | Recovery |
|---|---|
| Error at compilation | Fix the declaration in plugin.json; recompile |
| Critical at compilation | Fix the security issue immediately; recompile |
| Warning at compilation | No recovery needed; address when convenient |
| Runtime ACL denial | No recovery needed — access control is working correctly |
| Runtime view fallback | Fix view file path at the declaration level |

---

## 14. Cross-Contract Alignment

| Contract | Alignment |
|---|---|
| **Report Resource Contract** (`docs/architecture/report-resource-contract.md`) | This contract validates the ReportResource schema defined in the Resource Contract. Every field, sub-resource (Param, ExportSource), and computed field is validated against the schema that contract defines. |
| **Report Designer Operating Contract** (`docs/architecture/report-designer-operating-contract.md`) | Studio validation role (Section 12) aligns with the Designer's governed worker model. Studio may preflight but must not bypass or override validation. |
| **Resolved Runtime Contract Pipeline** (`docs/architecture/resolved-runtime-contract-pipeline.md`) | Compilation validation (Section 10) defines the gate that ReportResources must pass before entering the resolved runtime contract pipeline. Stage separation (declaration → compilation → runtime) aligns with pipeline stages. |
| **Surface Contribution Contract** (`docs/architecture/surface-contribution-contract.md`) | Runtime validation (Section 11) aligns with consumer-specific field requirements. Reports are resources consumed by surfaces; each consumer validates only the fields it needs. |
| **MODULE-CONTRACT.md** | Ownership validation (Section 6) enforces smallest-owner-wins. Module owns all fields; owner-source matching prevents ownership boundary violations. |
| **APP-CONTRACT.md** | Owner-source matching (Section 6.2) preserves app-level ownership. Platform-level reports can only be declared by the Platform app. |
| **Studio Operating Contract** (`docs/architecture/studio-operating-contract.md`) | Studio validation (Section 12) follows the Studio governance workflow: analyze → edit → validate → propose → approve → hand over. |
| **Business App/Module Ownership Contract** | Validation reinforces module-level ownership. Compilation errors are always scoped to the declaring module. |
| **Report Validation Discovery Audit** (`docs/architecture/report-validation-discovery-audit.md`) | This contract formalizes the audit findings. Every rule in this contract is traceable to a finding in the audit. |

---

## 15. Non-Goals

This contract does not authorize:

- PHP class implementation for ReportResource or validation engine
- Modifications to any ModuleReportRegistryService
- Changes to plugin.json declarations in any module
- DB schema changes
- Route or controller changes
- UI template or composer changes
- Export engine changes
- New ReportResource fields
- Ownership boundary changes
- Validation that bypasses existing authorization (ACL remains the definitive permission gate)

---

## 16. Validation

Run the current aggregate architecture gates:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

This Report Validation Contract is documentation-only. It does not authorize implementation.

---

## Appendix A: Validation Rule Index

| Rule ID | Field | Check | Severity | Stage |
|---|---|---|---|---|
| V-001 | `report_key` | Present and non-empty | Error | Declaration |
| V-002 | `report_key` | String type | Error | Declaration |
| V-003 | `report_key` | Convention `{app}.{module}.{purpose}` | Warning | Declaration |
| V-004 | `report_key` | No special chars except `.` and `_` | Error | Declaration |
| V-005 | `report_key` | ≤ 255 chars | Warning | Declaration |
| V-006 | `report_key` | Globally unique | Error | Compilation |
| V-007 | `owner` | Present and valid value | Error | Declaration |
| V-008 | `owner` | Matches declaration source | Error | Compilation |
| V-009 | `owner_app` | Resolves from manifest | Error | Compilation |
| V-010 | `owner_app` | Valid app identifier | Error | Compilation |
| V-011 | `owner_module` | Resolves from directory | Error | Compilation |
| V-012 | `module_dir` | Directory exists and readable | Error | Compilation |
| V-013 | `module_dir` | Under expected app root | Warning | Compilation |
| V-014 | `module_dir` | No path traversal | Critical | Compilation |
| V-015 | `title` | Present and non-empty | Error | Declaration |
| V-016 | `title` | String type | Error | Declaration |
| V-017 | `title` | No HTML tags | Warning | Declaration |
| V-018 | `title` | ≤ 200 chars | Warning | Declaration |
| V-019 | `description` | String type | Info | Declaration |
| V-020 | `category` | Valid category value | Warning | Declaration |
| V-021 | `visualization` | Valid visualization value | Warning | Declaration |
| V-022 | `permission` | Present and non-empty | Error | Declaration |
| V-023 | `permission` | String type | Error | Declaration |
| V-024 | `permission` | Key exists in registry | Warning | Compilation |
| V-025 | `permission` | Not deprecated | Warning | Compilation |
| V-026 | `permission` | No ACL bypass | Critical | Compilation |
| V-027 | `lifecycle` | Present and valid value | Error | Declaration |
| V-028 | `view` | Present and non-empty | Error | Declaration |
| V-029 | `view` | File exists | Error | Compilation |
| V-030 | `view` | No path traversal | Critical | Compilation |
| V-031 | `view` | Under module directory | Error | Compilation |
| V-032 | `export_view` | File exists (if declared) | Warning | Compilation |
| V-033 | `export_view` | Under module directory (if declared) | Error | Compilation |
| V-034 | `pdf_views` | Is array (if declared) | Warning | Declaration |
| V-035 | `pdf_views[]` | File exists (if declared) | Warning | Compilation |
| V-036 | `pdf_views[]` | Under module directory (if declared) | Error | Compilation |
| V-037 | `parameters[].key` | Present and non-empty | Error | Declaration |
| V-038 | `parameters[].key` | Unique within report | Error | Declaration |
| V-039 | `parameters[].type` | Present and valid type | Error | Declaration |
| V-040 | `parameters[].label` | Present and non-empty | Error | Declaration |
| V-041 | `parameters[].required` | Boolean type | Warning | Declaration |
| V-042 | `parameters[].options` | Present when type=select | Warning | Declaration |
| V-043 | `parameters[].default` | Type matches parameter type | Warning | Declaration |
| V-044 | `export_formats` | Valid format string | Warning | Declaration |
| V-045 | `export_sources.table` | Valid table name | Error | Declaration |
| V-046 | `export_sources.table` | Exists in DB | Info | System Tools |
| V-047 | `scope` | String type | Info | Declaration |
| V-048 | Module activation | Module is active | Error | Compilation |
| V-049 | Lifecycle filter | Lifecycle permits inclusion | Error | Compilation |
| V-050 | Owner-source match | owner matches declaration source | Error | Compilation |
