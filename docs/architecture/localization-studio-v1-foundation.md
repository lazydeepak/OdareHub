# Localization Studio v1 Foundation

**Status**: Read-only discovery UI implemented — inspection + coverage scan only (no edit/save/apply).
**Date**: 2026-05-31

> Localization Studio is a governed Studio worker for locale resource inspection,
> validation, and future edit proposals. It must never become the source of truth
> for translations.

---

## 1. Architecture Position

Studio is a governed worker. Apps/modules own their locale resources.

- Localization Studio discovers, inspects, validates, and later proposes edits.
- Runtime must continue reading existing locale files as it does today.
- Studio must not become the source of truth for translations.
- Studio must not own or host locale files.
- Studio must not intercept or replace runtime translation loading.

---

## 2. Owners of Locale Resources

Locale files are **owner-owned** — they live with the app or module that provides
the translated text. Ownership follows view ownership.

| Owner layer | Locale path pattern |
|---|---|
| App-level | `apps/{AppName}/lang/{locale}.php` |
| Module-level | `apps/{AppName}/modules/{ModuleName}/lang/{locale}.php` |
| Studio Tool (governed) | `apps/Studio/Tools/{ToolName}/lang/{locale}.php` |
| Shell | `apps/Shell/lang/{locale}.php` |
| Platform | `apps/Platform/lang/{locale}.php` |
| Plugin | `plugins/{PluginName}/lang/{locale}.php` |
| Core (locked) | `app/lang/{locale}.php` |

Each app/module owns its locale keys, values, and file lifecycle. Studio may
inspect and propose changes, but writes are the owner's responsibility.

---

## 3. Supported Languages

| Code | Language | Status |
|---|---|---|
| `en` | English | Active |
| `ja` | Japanese | Active |
| `ne` | Nepali | Active |

Additional languages may be added in the future, but the file-naming convention
(`lang/{locale}.php`) must remain consistent. Studio must discover locale files
by scanning known owner paths, not by maintaining a hardcoded language list.

---

## 4. Locale File Discovery Model

Studio discovers locale files by scanning these patterns:

```
apps/*/lang/{locale}.php
apps/*/modules/*/lang/{locale}.php
apps/Studio/Tools/*/lang/{locale}.php
plugins/*/lang/{locale}.php
app/lang/{locale}.php
```

Discovery is read-only. Studio must not:
- Maintain a database cache of locale content as source truth.
- Copy locale files into Studio-owned storage.
- Generate merged locale files that replace runtime loading.

---

## 5. Read-Only Inspection Phase (v1)

The inspection phase provides diagnostics only:

- **Locale inventory**: List all locale files grouped by owner and language.
  Report missing language variants (e.g., app has `en` and `ja` but no `ne`).
- **Key coverage**: For each locale file, report key set completeness across
  language variants. Report missing or extra keys.
- **Key naming convention check**: Verify keys follow the owner's naming pattern
  (typically `{owner}.{module}.{view}.{key}` or tool-local keys).
- **Orphan key detection**: Flag keys in locale files that have no corresponding
  usage in the owner's PHP/JS source.
- **Duplication detection**: Report identical key-value pairs across different
  locale files that should be shared or are suspicious.
- **File integrity**: Report syntax errors, non-array returns, or empty files.

No mutations. No proposals. No save/apply during v1.

---

## 6. Future Edit/Apply/Backup Workflow (v2+ Sketch)

The intended future workflow (NOT implemented in v1):

```
Discover → Inspect → Propose edits → Review diff → Backup → Apply → Verify
```

1. **Discover**: Scan locale files (v1).
2. **Inspect**: Validate, report gaps (v1).
3. **Propose edits**: Studio shows editor surface with proposed key/value changes.
4. **Review diff**: Studio presents a structured diff of proposed changes.
5. **Backup**: Before any write, Studio creates a backup copy of the original
   locale file in a Studio-managed backup area.
6. **Apply**: Studio writes the modified locale file back to the owner path.
   The owner retains ownership — Studio is a governed worker.
7. **Verify**: Post-apply validation confirms file integrity and key coverage.

Key constraints:
- Apply must be owner-approved (approval workflow).
- Apply must create a backup before writing.
- Apply must never write to Core (`app/lang/`) without explicit approval.
- Apply must respect file ownership boundaries.
- Apply must not create locale files for owners that have none.

---

## 7. Validation Rules

### Naming Convention
- Keys should match `{owner}.{module}.{view}.{key}` for app/module locale files.
- Tool-local files (under `apps/Studio/Tools/*/lang/`) may use flat key names.
- Keys must not contain spaces.
- Keys must be unique within a locale file.

### File Structure
- Each locale file must return a PHP array.
- The array must be non-empty or intentionally documented as empty.
- Keys must be string values.
- Values must be scalar (string, numeric) or null. Nested arrays are allowed
  but should follow a consistent pattern within the owner.

### Cross-Language Consistency
- All keys present in `en.php` must also exist in `ja.php` and `ne.php`.
- Extra keys in non-English files (without English equivalent) are warnings.
- Empty/trivial values (empty strings, placeholder text like "TODO") should be
  reported as warnings.

### Safety
- Values must not contain executable PHP or HTML tags (XSS prevention).
- Values must not reference superglobals (`$_GET`, `$_POST`, `$_SERVER`, etc.).
- Values must not contain `exec`, `eval`, `system`, `passthru`, `shell_exec`,
  `popen`, or `assert` function calls.

---

## 8. Forbidden Shortcuts

| Shortcut | Reason |
|---|---|
| Database translation tables in Studio | Runtime must read files as today; database bypasses owner ownership |
| Studio-owned locale file cache | Studio must not become source of truth |
| Merged/compiled locale bundles | Runtime must load per-owner locale files directly |
| Runtime translation override in Studio | Studio is a governed worker, not a runtime layer |
| Hardcoded language list in code | Languages must be discovered from filesystem; hardcoded list will drift |
| Writing locale files outside owner path | Ownership boundary violation |
| `localStorage`/`sessionStorage` locale cache in Studio UI | Persistence bypasses server authority |
| AJAX PUT/PATCH save without server round-trip | Save authority must be server-side only |
| Editing Core (`app/lang/`) without explicit approval | Core Lock Rule |

---

## 9. File List

```
apps/Studio/Tools/LocalizationStudio/
  tool.json          — Tool registration (placeholder skeleton)
  README.md          — Placeholder documentation

scripts/architecture/
  check_localization_studio_boundaries.sh  — Read-only diagnostic
```

---

## 10. Key Architecture Laws

1. **Locale ownership follows view ownership** — apps/modules own their locale files.
2. **Studio is a governed worker** — it may inspect, validate, and propose, but
   not own or host translation data.
3. **Runtime must never depend on Studio** — the system runs normally when
   Studio is disabled or absent.
4. **Backup before apply** — any future write must create a recoverable backup
   before modifying locale files.
5. **Core locale is locked** — editing `app/lang/` requires explicit approval.
6. **Discovery, not hardcoding** — Studio must discover locale files by scanning
   known patterns, not by maintaining a hardcoded list.

---

## 8. Read-Only Discovery UI (Commit `f5eb7ee7`)

The first Localization Studio slice is a **read-only discovery UI**:
inspect-only coverage scanning, no edit/save/apply capabilities.

### Route

- `GET /apps/studio/tools/localization-studio`
- Guarded by `StudioToolInstancePolicyService::isEnabled('localization_studio')`
- Controller: `StudioController::localizationStudioPreview()`
- Rendering: `tool_placeholder.php` with data model from `LocalizationStudioDiscoveryService::scan()`

### Discovery Service

- Scans `apps/*/lang/`, `apps/*/modules/*/lang/`, `plugins/*/lang/`, `app/lang/`
- Computes per-owner coverage: total keys, keys per language, coverage %
- Detects missing locale files and key mismatches across languages
- Returns structured data for read-only display

### UI Features (verified via Playwright — 39/39 checks pass)

- Hero section with "Inspection Only / Read-only" badge
- Edit/Apply disabled notices
- Summary metrics: total owners, total keys, locale files, coverage, languages
- Coverage table: Owner × Language (English/Japanese/Nepali) with keys and % bars
- Missing locales section (warning or "all present")
- Key mismatch section (items or "none reported")
- Collapsible file path details with per-language path breakdown
- No forms, save/apply buttons, or submit controls
- Navigation links stay within Studio

### Read-Only Drilldown Add-on

The next safe slice keeps v1 inspection-only and adds operator-free
diagnostic depth:

- Owner search/filter on the coverage table.
- Filters for Missing JA, Missing NE, and key mismatch.
- Expandable owner rows with keys grouped by language.
- Exact missing-key lists per language, computed from the owner union key set.
- Locale file path and key count for each discovered locale file.
- Copy-only clipboard export for displayed key lists.

This add-on does not add edit fields, generated translations, missing-file
creation, save/apply routes, database tables, or runtime translation-loading
changes.

### Safety Validation

| Gate | Status |
|---|---|
| PHP lint (7 files) | PASS |
| JSON validation (tool.json) | PASS |
| Diagnostic (30 invariants) | PASS |
| Aggregate architecture gates (19 checks) | PASS |
| git diff --check | PASS |
| Playwright smoke (39 assertions) | ALL PASS |

### Files

| File | Purpose |
|---|---|
| `apps/Studio/Controllers/StudioController.php` | Controller method |
| `apps/Studio/routes.php` | Route registration |
| `apps/Studio/config/studio_tool_policy.php` | Policy entry |
| `apps/Studio/Tools/LocalizationStudio/manifest.php` | Tool manifest |
| `apps/Studio/Tools/LocalizationStudio/tool.json` | Tool registration |
| `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php` | Discovery service |
| `apps/Studio/Tools/LocalizationStudio/Views/preview.php` | Read-only UI view |
| `apps/Studio/Views/pages/home.php` | Home card with en/ja/ne translations |
| `scripts/architecture/check_localization_studio_boundaries.sh` | Diagnostic gate |
