# Localization Studio v1 Read-only Completion Checkpoint

**Status**: ✅ Complete — read-only inspection/reporting chain fully implemented.
**Date**: 2026-06-01
**Included in**: `f03ca754` (latest), built on `9d3fbfda`, `82e580f2`, `424cae86`, `f5eb7ee7`

> Localization Studio v1 is a **read-only governed inspector**. It discovers, inspects,
> validates, reports, and exports (client-side only). It does not edit, save, apply,
> generate, or mutate any locale resource, file, database, cache, or runtime state.
>
> **Next**: Localization Studio v2 (edit/apply) architecture contract →
> [`localization-studio-v2-edit-apply-contract.md`](localization-studio-v2-edit-apply-contract.md)

---

## 1. What v1 Includes

### Discovery & Inspection (`LocalizationStudioDiscoveryService.php`)
- Scans 7 owner path patterns: `apps/*/lang/`, `apps/*/modules/*/lang/`, `apps/Studio/Tools/*/lang/`, `plugins/*/lang/`, `app/lang/`
- 82 locale files scanned across 32 owners
- 44 locale files represented in Studio view
- Returns per-owner coverage, union keys, missing files, key mismatches, and `values` maps

### Resource Validation Diagnostic (`check_localization_resource_diagnostics.sh`)
- Parse validity
- Unsupported locale codes
- Duplicate keys
- Naming convention violations
- Dangerous values (XSS, superglobals, exec functions)
- Boundary violations (Core/module/app cross-contamination)
- Cross-locale key parity (keys missing in ja/ne vs en)
- Wired as gate #13 in aggregate runner

### Translation Key Detail Inspector (preview.php)
- Click any key in any key list or missing-key list
- Overlay panel shows values across en/ja/ne with file paths and missing status
- Backdrop close + Escape key close
- Pre-computed embedded JSON (no AJAX)

### Coverage Dashboard (preview.php)
- Per-locale coverage cards with progress bars
- English 100%, Japanese 72%, Nepali 72%
- Lowest-coverage owners ranking table

### Missing Translation Worklist (preview.php)
- 192 entries across 28 owners with incomplete ja/ne translations
- Client-side filterable by owner/key/locale
- Client-side sortable by column (header click)
- Group headers by owner
- Key truncation at 50 chars with tooltip
- Report summary bar (total entries, owners, locales, top incomplete)
- Filtered-count display
- Mobile-responsive CSS

### Client-side TSV Copy/Export
- "Copy as TSV" button copies current filtered/sorted worklist to clipboard
- Uses `navigator.clipboard.writeText()` with `execCommand('copy')` fallback
- Reads from pre-computed in-memory data only
- No server-side file generation, no `/tmp` writes, no DB/cache writes
- No download headers, no file handles, no AJAX

---

## 2. Files Involved

| File | Role |
|---|---|
| `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php` | Scan service — returns groups, summary, supported_locales, `values` maps |
| `apps/Studio/Tools/LocalizationStudio/Views/preview.php` | Main tool view — inline CSS + JS, key detail panel, coverage dashboard, worklist table |
| `apps/Studio/Tools/LocalizationStudio/tool.json` | Tool registration (`runtime_behavior: false`) |
| `apps/Studio/Tools/LocalizationStudio/README.md` | Tool documentation |
| `scripts/architecture/check_localization_studio_boundaries.sh` | Boundary diagnostic (30 invariants) |
| `scripts/architecture/check_localization_resource_diagnostics.sh` | Locale file content validation diagnostic |
| `docs/architecture/localization-studio-v1-foundation.md` | Architecture ownership model + inspection phase scope |
| `docs/architecture/localization-studio-v1-checkpoint.md` | **This file** — v1 completion checkpoint |

---

## 3. What Validation Is Enforced

### Resource Diagnostic (`check_localization_resource_diagnostics.sh`)
- PHP file parse validity
- Unsupported locale codes (only `en`, `ja`, `ne` allowed)
- Duplicate keys within a locale file
- Naming convention (`{owner}.{module}.{view}.{key}` or flat)
- Dangerous values (XSS vectors, superglobals, exec functions)
- Boundary violations (owner keys crossing into other owner files)
- Cross-locale key parity (keys present in `en` but missing in `ja`/`ne`)

### Boundary Diagnostic (`check_localization_studio_boundaries.sh` — 30 invariants)
- Folder and file existence
- `tool.json` has valid JSON, `runtime_behavior: false`, `routes_enabled: true`
- No save/apply/write behavior in any PHP/JS file
- No POST routes for localization-studio
- No `localStorage`, `file_put_contents`, `fwrite` in any file
- No `fopen`, `tempnam`, `tmpfile`, `Content-Disposition`, `header.*download`, `header.*attachment`, `header.*csv`
- No `function save(`, `function apply(`, `function write(` in Services
- No PUT/PATCH curl in Services
- No runtime code (Shell/Platform/Core) references LocalizationStudio
- Core locale files unchanged (`app/lang/`)
- No locale files stored inside LocalizationStudio folder
- No untracked locale cache files

---

## 4. UI Inspection/Reporting Features

| Feature | Location | Notes |
|---|---|---|
| Key detail inspector | preview.php | Click key → overlay with values across locales |
| Coverage dashboard | preview.php | Progress bars + ranking table |
| Missing worklist | preview.php | 192 entries, filterable, sortable, group headers |
| TSV clipboard export | preview.php | Client-side only, no server writes |
| Summary metrics | preview.php | Total entries, owners, locales, top incomplete |

---

## 5. What Is Intentionally NOT Included (v1 boundary)

| Feature | Status | Reason |
|---|---|---|
| Edit/apply workflow | ❌ Not included | Separate v2+ design required |
| Translation generation | ❌ Not included | Would violate read-only boundary |
| Save/submit buttons | ❌ Not included | Read-only tool |
| AJAX endpoints | ❌ Not included | All data pre-computed, embedded as JSON |
| Route additions | ❌ Not included | Single route from v1 foundation |
| Locale file writes | ❌ Not included | Owner-owned, not Studio-owned |
| DB/cache writes | ❌ Not included | Read-only diagnostic |
| `localStorage` persistence | ❌ Not included | Explicitly forbidden by diagnostic |
| Placeholder-mismatch validation | ❌ Not included | Future enhancement |
| Orphan-key detection | ❌ Not included | Future enhancement |
| Automated translation suggestions | ❌ Not included | Future enhancement |
| Runtime localization loading changes | ❌ Not included | Core Lock Rule |
| Server-side file downloads | ❌ Not included | Client-side TSV only, explicitly guarded |

---

## 6. Current Counts

| Metric | Value |
|---|---|
| Locale files scanned | 82 |
| Owners covered | 32 |
| Locale files represented in Studio view | 44 |
| Missing translation entries | 192 |
| Owners with incomplete translations | 28 |
| Affected locales | `ja`, `ne` |
| Coverage (en) | 100% |
| Coverage (ja) | 72% |
| Coverage (ne) | 72% |

---

## 7. Runtime Behavior

**Unchanged.** The system loads and resolves translations exactly as before.
- `app/Core/Locale.php` — unmodified
- `app/lang/` — unmodified
- All owner locale files — unmodified
- No runtime hooks, overrides, or intercepts added
- No translation loading path changed
- No `config/` or bootstrap changes

Studio runs as an optional System App. When disabled or absent, all business apps,
Shell, Platform, and Core operate identically.

---

## 8. Boundary Diagnostic Protection for TSV Export

The client-side TSV copy is explicitly protected by these diagnostic invariants:

| Invariant | Pattern checked | Protection |
|---|---|---|
| No `fopen` | `fopen(` | Prevents server-side file generation |
| No `tempnam` | `tempnam(` | Prevents `/tmp` temp file writes |
| No `tmpfile` | `tmpfile(` | Prevents `/tmp` temp file writes |
| No `Content-Disposition` | `Content-Disposition` | Prevents server-side download headers |
| No `header.*download` | `header.*download` | Prevents server-side download trigger |
| No `header.*attachment` | `header.*attachment` | Prevents server-side attachment response |
| No `header.*csv` | `header.*csv` | Prevents server-side CSV delivery |
| No `localStorage` | `localStorage` | Prevents client-side file persistence |
| No `file_put_contents` | `file_put_contents` | Prevents any file write |
| No `fwrite` | `fwrite` | Prevents any file write |

These invariants were added in the checkpoint (`f03ca754`) to formally document
the export boundary already established by the v1 design.

---

## 9. Future Edit/Apply Direction (v2+)

When v2 edit/apply workflow is designed, it MUST include:

1. **Separate design document** — Not implemented in v1
2. **Analyze phase** — Read current state before editing
3. **Diff phase** — Show proposed changes before any write
4. **Preview phase** — Visual review of changes
5. **Approve step** — Owner confirmation before apply
6. **Apply phase** — Write locale files (with backup)
7. **Snapshot step** — Backup original files before modification
8. **Rollback capability** — Restore from snapshot

Key constraints (from foundation doc):
- Apply must be owner-approved
- Apply must create a backup before writing
- Apply must never write to Core (`app/lang/`) without explicit approval
- Apply must respect file ownership boundaries
- Apply must not create locale files for owners that have none
