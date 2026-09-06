# Report Designer — P1 Foundation Implementation Plan

Status: Implementation planning. Architecture complete. No runtime changes, DB changes, UI changes, or routes authorized by this document.

Class: Classification B — Architecture complete, implementation planning allowed.

---

## 1. Scope

This plan covers the first foundation layer of Report Designer implementation:

| Layer | In scope | Not in scope |
|---|---|---|
| ReportResource | Value object class, typed properties, ArrayAccess backward compatibility | Any schema changes, DB changes, plugin.json changes |
| Validation | Validation service, stages, result types, rule set | 50 rules from Validation Contract — subset for P1 |
| Compilation | Compilation pipeline class, resolved runtime contract | Production compilation engine, registry service changes |
| Runtime contract | Runtime consumer contract interface, field access boundaries | Any actual consumer implementation |

Everything in this plan is **Studio-owned tooling**. No app/module runtime is affected. No Core code is touched.

---

## 2. Dual Tool Identity Resolution

**Finding**: Two tools exist — `ReportDesigner` (placeholder, enabled, route registered) and `ReportBuilder` (planned manifest, unlinked, no route).

**Decision**: Keep `ReportDesigner` as the canonical Studio tool. The `ReportBuilder` manifest at `apps/Studio/Tools/ReportBuilder/manifest.php` is an unlinked artifact. Do not remove it (leave as legacy), but all implementation goes into `apps/Studio/Tools/ReportDesigner/`.

**Naming convention**:
- Tool key: `report_designer`
- Route: `/apps/studio/tools/report-designer`
- Namespace: `Apps\Studio\Tools\ReportDesigner`
- Dir: `apps/Studio/Tools/ReportDesigner/`
- Phase matches: `status: 'active'` (foundation services), not yet `governed_draft`

---

## 3. Files Proposed

| File | Purpose | Status |
|---|---|---|
| `apps/Studio/Tools/ReportDesigner/manifest.php` | Tool manifest — does not exist yet | Create |
| `apps/Studio/Tools/ReportDesigner/ValueObjects/ReportResource.php` | Typed value object with ArrayAccess | Create |
| `apps/Studio/Tools/ReportDesigner/ValueObjects/Param.php` | Param sub-resource value object | Create |
| `apps/Studio/Tools/ReportDesigner/ValueObjects/ExportSource.php` | ExportSource sub-resource value object | Create |
| `apps/Studio/Tools/ReportDesigner/Services/ReportValidationService.php` | Validation pipeline with stages and result types | Create |
| `apps/Studio/Tools/ReportDesigner/Services/ReportCompilationService.php` | Compilation pipeline producing ResolvedReportContract | Create |
| `apps/Studio/Tools/ReportDesigner/Contracts/ResolvedReportContract.php` | Resolved runtime contract interface | Create |
| `apps/Studio/Tools/ReportDesigner/Views/index.php` | Replace 5-line placeholder with tool page | Modify |
| `apps/Studio/Tools/ReportDesigner/Views/foundation-test.php` | Read-only diagnostic console for validation+compilation | Create (optional) |

**No changes to**:
- `apps/Studio/Controllers/StudioController.php` — existing `toolPage()` route is sufficient
- `apps/Studio/routes.php` — route already registered
- `apps/Studio/Services/StudioToolInstancePolicyService.php` — policy already enabled
- Any `ModuleReportRegistryService` — not touched
- Any `plugin.json` — not touched
- Any Core (`/app`) — not touched

---

## 4. ReportResource Value Object

### 4.1 Design decisions

1. **Typed readonly properties** — PHP 8.1+ `readonly` properties. Immutable after construction.
2. **ArrayAccess** — implement `ArrayAccess` so existing callers using `$report['field']` work without modification.
3. **Factory constructor from array** — `public static function fromArray(array $data): self` — accepts the current array shape from `ModuleReportRegistryService::activeReports()`.
4. **Graceful defaults** — absent optional fields receive defaults matching the Resource Contract.
5. **No magic** — no JSON serialization embedded (separate concern). No validation embedded (validation is a separate service).
6. **Strict types** — required fields are non-nullable; optional fields are nullable with getter defaults.

### 4.2 Class structure

```php
final class ReportResource implements ArrayAccess
{
    // ── Identity (immutable after declaration) ──
    public readonly string $report_key;
    public readonly string $owner;

    // ── Computed Identity (resolved during compilation) ──
    public readonly string $owner_app;
    public readonly string $owner_module;
    public readonly string $module_dir;

    // ── Presentation ──
    public readonly string $title;
    public readonly string $description;     // default ""
    public readonly string $category;        // default "operational"
    public readonly string $visualization;   // default "table"

    // ── Access ──
    public readonly string $permission;
    public readonly string $lifecycle;

    // ── Views ──
    public readonly string $view;
    public readonly ?string $export_view;
    public readonly array $pdf_views;        // string[], default []

    // ── Parameters ──
    public readonly array $parameters;       // Param[], default []

    // ── Export ──
    public readonly array $export_formats;   // string[], default []
    public readonly ?array $export_sources;  // ExportSource[]|null, default null

    // ── Scope ──
    public readonly string $scope;           // default ""

    public function __construct(array $data) { ... }
    public static function fromArray(array $data): self { ... }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool { ... }
    public function offsetGet(mixed $offset): mixed { ... }
    public function offsetSet(mixed $offset, mixed $value): void { /* throw */ }
    public function offsetUnset(mixed $offset): void { /* throw */ }
}
```

### 4.3 ArrayAccess behavior

| Operation | Behavior |
|---|---|
| `$r['report_key']` | Returns `$this->report_key` |
| `$r['nonexistent']` | Returns `null` (backward compat) |
| `$r['field'] = value` | Throws `\RuntimeException('ReportResource is read-only')` |
| `unset($r['field'])` | Throws `\RuntimeException('ReportResource is read-only')` |
| `isset($r['field'])` | Returns true if field exists and is non-null |

### 4.4 Graceful default mapping

| Field | Default when absent |
|---|---|
| `description` | `""` |
| `category` | `"operational"` |
| `visualization` | `"table"` |
| `parameters` | `[]` |
| `export_formats` | Derived: if `export_view` set → `['csv']`; else `[]` |
| `export_sources` | `null` |
| `pdf_views` | `[]` |
| `scope` | `""` |
| `owner_app` | `""` |
| `owner_module` | `""` |
| `module_dir` | `""` |

### 4.5 Param value object

```php
final class Param
{
    public readonly string $key;
    public readonly string $type;     // date|daterange|select|text|boolean
    public readonly string $label;
    public readonly bool $required;   // default false
    public readonly array $options;   // string[], default []
    public readonly mixed $default;   // default null

    public function __construct(array $data) { ... }
    public static function fromArray(array $data): ?self { ... }
}
```

### 4.6 ExportSource value object

```php
final class ExportSource
{
    public readonly string $table;
    public readonly string $order_by;       // default ""
    public readonly string $direction;      // default "ASC"
    public readonly string $filename_prefix; // default ""

    public function __construct(array $data) { ... }
    public static function fromArray(array $data): ?self { ... }
}
```

### 4.7 Constructor preconditions

| Field | Precondition | Severity if violated (in service, not in constructor) |
|---|---|---|
| `report_key` | Non-empty string | Error — compilation blocked |
| `owner` | One of `app\|module\|platform` | Error — compilation blocked |
| `title` | Non-empty string | Error — compilation blocked |
| `permission` | Non-empty string | Error — compilation blocked |
| `lifecycle` | One of `active_only\|installed\|always` | Error — compilation blocked |
| `view` | Non-empty string | Error — compilation blocked |
| `parameters[].type` | One of `date\|daterange\|select\|text\|boolean` | Error — parameter excluded |
| `export_formats[]` | Each: `csv\|pdf\|xlsx` | Warning — format skipped |
| `export_sources[].table` | Non-empty | Error — source excluded |

The constructor accepts any data and does not throw on invalid input. Validation is the validation service's responsibility. This keeps the value object a pure data carrier.

---

## 5. Validation Architecture

### 5.1 Design decisions

1. **Separate service** — `ReportValidationService` is a standalone class with no dependencies on registry services.
2. **Stage-based** — runs through 4 stages matching the Validation Contract: Declaration Parse → Compilation → System Tools → Runtime Readiness.
3. **Result type** — `ValidationResult` with `passed: bool`, `errors: ValidationMessage[]`, `warnings: ValidationMessage[]`, `info: ValidationMessage[]`.
4. **Per-field rules** — each rule is a method returning a `ValidationMessage[]`.
5. **P1 subset** — P1 implements Stage 1 (Declaration Parse) and Stage 2 (Compilation) rules. Stage 3 (System Tools) and Stage 4 (Runtime) are framework hooks only.
6. **No DB access** — P1 validation avoids DB queries. Permission existence checks and table existence checks are Stage 3 (future).
7. **No compilation dependency** — validation can run before compilation. The compiler can optionally use validation results to filter.

### 5.2 ValidationStage enum (string-backed)

```php
enum ValidationStage: string
{
    case DECLARATION_PARSE = 'declaration_parse';
    case COMPILATION = 'compilation';
    case SYSTEM_TOOLS = 'system_tools';
    case RUNTIME = 'runtime';
}
```

### 5.3 ValidationMessage

```php
final class ValidationMessage
{
    public readonly string $rule_id;       // e.g., "V-001"
    public readonly string $field;         // e.g., "report_key"
    public readonly string $message;       // human-readable
    public readonly string $severity;      // critical|error|warning|info
    public readonly string $stage;         // ValidationStage value
    public readonly ?string $value;        // the offending value (if applicable)

    public function __construct(string $ruleId, string $field, string $message, string $severity, string $stage, ?string $value = null) { ... }
}
```

### 5.4 ValidationResult

```php
final class ValidationResult
{
    public readonly bool $passed;
    public readonly array $errors;      // ValidationMessage[]
    public readonly array $warnings;    // ValidationMessage[]
    public readonly array $info;        // ValidationMessage[]

    public function __construct(array $errors, array $warnings, array $info) { ... }

    public function hasCritical(): bool { ... }
    public function hasErrors(): bool { ... }
    public function allMessages(): array { ... }
    public function forField(string $field): array { ... }
    public function forSeverity(string $severity): array { ... }
}
```

`$passed` is `true` when: zero errors AND zero criticals. Warnings and info do not affect `$passed`.

### 5.5 ReportValidationService

```php
final class ReportValidationService
{
    /**
     * Run all applicable stages on a compiled ReportResource.
     * Stages are run in order. Errors from earlier stages may skip later stages.
     */
    public function validate(ReportResource $resource, array $options = []): ValidationResult { ... }

    /**
     * Run a specific stage only.
     */
    public function validateStage(ReportResource $resource, ValidationStage $stage, array $options = []): ValidationResult { ... }

    // ── Stage 1: Declaration Parse rules (P1) ──

    /** V-001: report_key is non-empty */
    public function checkReportKeyPresence(ReportResource $resource): array { ... }

    /** V-002: report_key format convention (advisory) */
    public function checkReportKeyConvention(ReportResource $resource): array { ... }

    /** V-003: owner is valid value */
    public function checkOwner(ReportResource $resource): array { ... }

    /** V-004: title is non-empty */
    public function checkTitle(ReportResource $resource): array { ... }

    /** V-005: permission is non-empty */
    public function checkPermission(ReportResource $resource): array { ... }

    /** V-006: lifecycle is valid value */
    public function checkLifecycle(ReportResource $resource): array { ... }

    /** V-007: view is non-empty */
    public function checkView(ReportResource $resource): array { ... }

    /** V-008: parameters[].type is valid */
    public function checkParameterTypes(ReportResource $resource): array { ... }

    /** V-009: parameters[].key is unique within report */
    public function checkParameterKeyUniqueness(ReportResource $resource): array { ... }

    // ── Stage 2: Compilation rules (P1) ──

    /** V-010: view path exists (file system check) */
    public function checkViewPathExists(ReportResource $resource, string $moduleDir): array { ... }

    /** V-011: export_view path exists (if declared) */
    public function checkExportViewPathExists(ReportResource $resource, string $moduleDir): array { ... }

    /** V-012: pdf_views paths exist (if declared) */
    public function checkPdfViewPathsExist(ReportResource $resource, string $moduleDir): array { ... }

    /** V-013: export_sources.table is non-empty (if declared) */
    public function checkExportSourceTable(ReportResource $resource): array { ... }

    /** V-014: export_formats are valid (if declared) */
    public function checkExportFormats(ReportResource $resource): array { ... }

    /** V-015: description is safe (advisory — no HTML in description) */
    public function checkDescriptionSafety(ReportResource $resource): array { ... }

    // ── Stage 3: System Tools (hook only — not P1) ──

    /** Placeholder for Stage 3 rules */
    public function checkSystemTools(ReportResource $resource): array { return []; }

    // ── Stage 4: Runtime (hook only — not P1) ──

    /** Placeholder for Stage 4 rules */
    public function checkRuntime(ReportResource $resource): array { return []; }
}
```

### 5.6 P1 validation rules (Stage 1 + Stage 2)

| ID | Rule | Stage | Severity | Condition |
|---|---|---|---|---|
| V-001 | `report_key` present | Declaration Parse | Error | Empty or non-string |
| V-002 | `report_key` convention | Declaration Parse | Warning | Does not match `{app}.{module}.{purpose}` |
| V-003 | `owner` valid | Declaration Parse | Error | Not `app\|module\|platform` |
| V-004 | `title` present | Declaration Parse | Error | Empty or non-string |
| V-005 | `permission` present | Declaration Parse | Error | Empty or non-string |
| V-006 | `lifecycle` valid | Declaration Parse | Error | Not `active_only\|installed\|always` |
| V-007 | `view` present | Declaration Parse | Error | Empty or non-string |
| V-008 | Parameter types valid | Declaration Parse | Error | Any param has invalid type |
| V-009 | Parameter keys unique | Declaration Parse | Error | Duplicate keys within report |
| V-010 | View path exists | Compilation | Error | File not found at resolved path |
| V-011 | Export view path exists | Compilation | Warning | File not found (if `export_view` set) |
| V-012 | PDF view paths exist | Compilation | Warning | Any path not found |
| V-013 | Export source table present | Compilation | Error | Empty table name (if `export_sources` set) |
| V-014 | Export formats valid | Compilation | Warning | Any format not `csv\|pdf\|xlsx` |
| V-015 | Description safe | Compilation | Warning | Contains HTML tags |

---

## 6. Compilation Pipeline

### 6.1 Design decisions

1. **Separate service** — `ReportCompilationService` produces a `ResolvedReportContract` from input data.
2. **Pipeline stages** — Source → Validate → Compile → Resolve → Runtime Consumer.
3. **P1 focus** — P1 implements the compile stage that takes validated data and produces a typed `ReportResource`. No registry interaction. No DB interaction.
4. **The compilation pipeline is NOT a replacement for `ModuleReportRegistryService`** — it is a Studio-owned tooling service that mirrors the compilation logic for preview/diff/validation purposes. The actual registry service remains the runtime source of truth.
5. **Source input** — accepts raw `plugin.json` report entry array, or an array-shaped report from the registry.

### 6.2 Pipeline flow

```php
final class ReportCompilationService
{
    /**
     * Full pipeline: Source → Validate → Compile → ResolvedReportContract.
     * Returns a CompilationResult with the compiled contract and validation results.
     */
    public function compile(array $sourceData, array $options = []): CompilationResult { ... }

    /**
     * Validation-only: runs validation without producing a compiled contract.
     */
    public function validateOnly(array $sourceData): ValidationResult { ... }

    /**
     * Compile from existing ReportResource: runs compilation resolution only.
     */
    public function resolve(ReportResource $resource): ResolvedReportContract { ... }

    // ── Internal stages ──

    /** Stage 1: Convert source array → ReportResource value object */
    private function toReportResource(array $sourceData): ReportResource { ... }

    /** Stage 2: Validate the ReportResource */
    private function validate(ReportResource $resource): ValidationResult { ... }

    /** Stage 3: Resolve into runtime contract */
    private function resolveToContract(ReportResource $resource): ResolvedReportContract { ... }
}
```

### 6.3 CompilationResult

```php
final class CompilationResult
{
    public readonly bool $succeeded;
    public readonly ?ReportResource $resource;
    public readonly ?ResolvedReportContract $contract;
    public readonly ValidationResult $validation;

    public function __construct(bool $succeeded, ?ReportResource $resource, ?ResolvedReportContract $contract, ValidationResult $validation) { ... }
}
```

### 6.4 Compilation behavior

| Input state | Validation result | Compilation output |
|---|---|---|
| Valid source data | PASS | `CompilationResult(succeeded: true, resource, contract, validation)` |
| Required field missing | FAIL (Error) | `CompilationResult(succeeded: false, null, null, validation)` |
| Valid with warnings | PASS (warnings) | `CompilationResult(succeeded: true, resource, contract, validation)` |
| Empty array | FAIL (Error) | `CompilationResult(succeeded: false, null, null, validation)` |

### 6.5 P1 compilation scope

P1 compilation handles:

1. **`toReportResource()`** — reads the 8 current `plugin.json` report fields + 10 computed/optional fields. Resolves graceful defaults. Returns the typed `ReportResource` value object. Does NOT resolve file paths (no filesystem access).

2. **`validate()`** — delegates to `ReportValidationService`. Runs Stage 1 + Stage 2.

3. **`resolveToContract()`** — produces the `ResolvedReportContract`. This is where file paths and module metadata would be resolved (P1 filesystem-hooks for Stage 2 validation only).

**What P1 compilation does NOT do:**
- Does not query `installed_plugins` for module activation
- Does not filter by lifecycle
- Does not resolve `owner_app` from manifests
- Does not resolve `module_dir` from filesystem
- Does not interact with any DB
- Does not write anything
- Does not cache anything

---

## 7. Resolved Runtime Contract

### 7.1 Design decisions

1. **Interface contract** — `ResolvedReportContract` is a read-only interface that provides the exact fields each runtime consumer needs. It is NOT the same as `ReportResource` — it is a resolved view.
2. **Consumer-specific views** — the contract may eventually provide methods scoped to consumer needs, but P1 keeps it as a field accessor.
3. **P1 scope** — the contract wraps a `ReportResource` with resolved metadata. Since P1 does not access filesystem/DB, `module_dir` and `owner_app` come from input data or defaults.

### 7.2 ResolvedReportContract

```php
final class ResolvedReportContract
{
    public readonly ReportResource $resource;
    public readonly string $resolved_module_dir;  // resolved absolute path
    public readonly string $resolved_owner_app;   // resolved app name
    public readonly string $resolved_view_path;   // resolved absolute view path
    public readonly bool $view_exists;            // whether view file exists
    public readonly array $resolved_export_sources; // ExportSource[] with defaults

    public function __construct(ReportResource $resource, array $resolutionData) { ... }

    // Consumer field accessors (read-only views)

    /**
     * Fields for Shell navigation consumption.
     * Shell must NOT receive view, module_dir, export_sources.
     */
    public function forNavigation(): array { ... }

    /**
     * Fields for render consumption.
     */
    public function forRender(): array { ... }

    /**
     * Fields for export consumption.
     */
    public function forExport(): array { ... }

    /**
     * Full data (all fields).
     */
    public function toArray(): array { ... }
}

final class NavigationContract
{
    public readonly string $report_key;
    public readonly string $title;
    public readonly string $permission;
    public readonly string $lifecycle;
    public readonly string $category;
    public readonly string $visualization;

    public function __construct(ReportResource $r) { ... }
}

final class RenderContract
{
    public readonly string $report_key;
    public readonly string $view;
    public readonly string $module_dir;
    public readonly string $resolved_view_path;
    public readonly array $parameters;

    public function __construct(ReportResource $r, array $resolutionData) { ... }
}
```

### 7.3 Consumer boundary enforcement

| Consumer | Gets | Never gets |
|---|---|---|
| Navigation | `report_key`, `title`, `permission`, `lifecycle`, `category`, `visualization` | `view`, `module_dir`, `export_sources`, `parameters` |
| Render (report view) | `report_key`, `view`, `module_dir` (resolved), `parameters`, `scope` | `export_formats`, `export_sources`, `pdf_views` |
| Export CSV | `report_key`, `export_sources`, `export_formats` | `view`, `title`, `description`, `parameters` |
| Export PDF | `report_key`, `pdf_views`, `module_dir` | `export_sources`, `export_formats` |
| Dashboard card | `report_key`, `title`, `visualization`, `permission`, `category` | `view`, `module_dir`, `export_sources`, `parameters` |
| Operator widget | `report_key`, `title`, `visualization` | `view`, `permission`, `module_dir`, `export_sources` |
| Display panel | `report_key`, `title` | `view`, `permission`, `module_dir`, `export_sources` |
| Export history | `report_key`, `owner_app` | All other fields |
| Studio Designer | All fields | Nothing restricted |

---

## 8. Risks and Implementation Order

### 8.1 Risk assessment

| Risk | Severity | Mitigation |
|---|---|---|
| ArrayAccess backward compat gaps | Medium | Write test that loads current `findActiveReport()` output and reads every field via `['field']` syntax. Fix any gaps before merging. |
| Validation service grows unchecked | Low | P1 rules are explicitly scoped to 15 rules. New rules require contract change. |
| Compilation service overlaps with registry service | Medium | Document clearly: compilation service is Studio-owned tooling, not a registry replacement. The two may share logic in the future but are separate now. |
| Dual tool identity (ReportDesigner vs ReportBuilder) | Low | Use `ReportDesigner` as canonical. Leave `ReportBuilder` manifest as legacy. Document this decision. |
| P1 scope creep | Medium | Strict gate: no DB, no filesystem (except specific path existence checks), no registry interaction, no routes, no UI, no migrations. |

### 8.2 Implementation order

```
Phase 1.1: Value Objects (foundation)
  1. Param value object
  2. ExportSource value object
  3. ReportResource value object with ArrayAccess

Phase 1.2: Validation Service
  4. ValidationStage enum
  5. ValidationMessage value object
  6. ValidationResult value object
  7. ReportValidationService with V-001 through V-015

Phase 1.3: Compilation Service
  8. ResolvedReportContract
  9. NavigationContract, RenderContract (consumer contracts)
  10. CompilationResult
  11. ReportCompilationService

Phase 1.4: Integration
  12. Tool manifest (manifest.php)
  13. Read-only diagnostic view (foundation-test.php)
  14. Update tool placeholder view

Phase 1.5: Validation
  15. Architecture consistency check
  16. Ownership validation
  17. Runtime boundary validation
  18. Boundary gate creation (if needed)
```

### 8.3 Dependencies between phases

```
Phase 1.1 (Value Objects)
  └─ Phase 1.2 (Validation) — depends on ReportResource
      └─ Phase 1.3 (Compilation) — depends on Validation + ReportResource
          └─ Phase 1.4 (Integration) — depends on all above
              └─ Phase 1.5 (Validation) — depends on all above
```

No external dependencies on:
- ModuleReportRegistryService
- Any DB
- Any route or controller
- Any app/module code
- Any Core code

---

## 9. Open Questions

| Question | Status | Resolution |
|---|---|---|
| Should `ReportResource` constructor throw on invalid data? | Decided: No. Constructor is lenient. Validation is the validation service's responsibility. | See Section 4.7 |
| Should `Param` and `ExportSource` be inner classes of `ReportResource`? | Decided: No. Separate files in `ValueObjects/` namespace. | — |
| Should `ResolvedReportContract` implement `ArrayAccess`? | Decided: No. It is a design concept, not a backward compat layer. Consumers use typed methods. | — |
| P1 includes path-existence checks (V-010, V-011, V-012). Does this require filesystem access? | Yes. The validation service accepts an optional `moduleDir` parameter for these checks. | See Section 5.5 |
| Should the compilation service cache resolved results? | Decision deferred. P1 does not cache. | — |
| Does `ReportBuilder/manifest.php` need to be removed? | Decided: No. Leave as legacy. | See Section 2 |
| Does the existing 5-line `Views/index.php` need to be replaced? | Yes — P1 updates it to show an "under construction" page with version metadata. | See Section 3 |

---

## 10. Readiness Assessment

| Criterion | Status |
|---|---|
| Architecture complete (Classification B) | ✅ Seven existing contracts totalling 3,430 lines |
| Ownership model clear | ✅ App/Module owns report meaning; Studio owns only tooling |
| Resource contract defined | ✅ ReportResource with 19 fields across 5 categories |
| Validation rules specified | ✅ 50 rules across 4 stages; P1 picks 15 |
| Compilation pipeline separated | ✅ Source → Validate → Compile → Resolve → Runtime |
| Runtime contracts identified | ✅ Consumer-specific field matrices documented |
| No Core changes | ✅ All /app files unchanged |
| No app/module changes | ✅ No plugin.json, no ModuleReportRegistryService changes |
| No DB changes | ✅ No migrations, no schema changes |
| No route changes | ✅ Route already registered |
| No UI changes | ✅ View is placeholder; diagnostic view is optionally internal |
| No runtime behavior | ✅ Services are Studio-owned tooling only |
| Boundary gate exists | ⚠️ Not yet — create in Phase 1.5 if needed (existing Studio boundary gates likely cover this) |

**Ready for Phase 1.1 implementation.**

---

## 11. Verification Approach

After implementation, verify:

1. **PHP lint**: `php -l` on all new files
2. **Architecture gates**: `bash scripts/architecture/run_architecture_gates.sh` — must pass
3. **ArrayAccess compatibility**: Create a PHP script that loads `ModuleReportRegistryService::activeReports()` output for each module type and reads every field via `['field']` syntax against the new `ReportResource`
4. **Validation correctness**: For each of the 15 P1 rules, test with valid data (passes) and invalid data (detects failure)
5. **Compilation round-trip**: Feed a `plugin.json` entry array → compile → `toArray()` → verify field integrity
6. **Ownership confinement**: No Studio file writes to app/module paths; no DB calls; no registry service calls
