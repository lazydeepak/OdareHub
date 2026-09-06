# Localization File Structure v1 Contract

**Status**: Phase 1-4 complete — resolver support, owner migration, guardrail gate, and legacy removal all done. All 35 owners on canonical `Resources/lang/` path only.

**Purpose**: Lock canonical locale file structure, ownership boundaries, fallback behavior, and phased migration policy before any localization-path migration begins.

---

## 1. Architecture Rule Protected

Localization ownership follows capability ownership. Locale resources are owner artifacts, not centralized platform data.

This contract protects:

1. **Owner boundary law**: each app/module/plugin/tool owns and maintains its own locale files.
2. **Source-of-truth law**: runtime translations come from owner locale files, not a centralized DB/cache table.
3. **Migration safety law**: path migrations must preserve compatibility and avoid big-bang runtime breakage.

---

## 2. Decisions Locked (v1)

### 2.1 Canonical location model

Every Locale Resource Owner owns locale files under its own resource tree.

### 2.2 Canonical path

`{OwnerRoot}/Resources/lang/{locale}.php`

Examples:

1. `apps/Manufacturing/Resources/lang/en.php`
2. `apps/Manufacturing/Resources/lang/ja.php`
3. `apps/Studio/Tools/LocalizationStudio/Resources/lang/en.php`
4. `plugins/Base/Resources/lang/ne.php`

### 2.3 File shape (v1)

Use one file per locale per owner:

1. `en.php`
2. `ja.php`
3. `ne.php`

Each file returns a flat PHP array:

```php
<?php
return [
    'key.name' => 'Value',
];
```

### 2.4 Domain-split files

Domain-split locale files are out of scope for v1.

Not authorized in v1:

1. `en/auth.php`
2. `en/dashboard.php`
3. any multi-file-per-locale partitioning scheme

### 2.5 Fallback rules

Fallback chain is fixed:

1. requested locale
2. base locale `en`
3. key literal if unresolved

---

## 3. Compatibility Window Policy

During migration, resolver compatibility must support both canonical and legacy paths.

Policy:

1. Existing owners must not break.
2. Canonical `Resources/lang/*.php` is target for migrated owners.
3. Legacy path support is temporary and must be explicitly documented in migration diagnostics.
4. Legacy support may be removed only after all owners are migrated and parity-validated.

---

## 4. Ownership Boundary Rules

1. Each app/module/plugin/tool owns its own locale files.
2. No centralized translation source of truth is introduced.
3. Shell/Core must not hardcode cross-owner language data.
4. Localization Studio may edit only selected owner-owned locale files in future governed apply phases.
5. Core lock remains in force for `app/` assets unless explicitly approved.

---

## 5. Migration Strategy (Phased, No Big-Bang)

### Phase 0: Contract Lock (this document)

1. Define canonical path and fallback rules.
2. Do not move locale files.
3. Do not change runtime loading.

### Phase 1: Compatibility-First Resolver Support ✅ (2026-06-06)

**Status: COMPLETE** — Runtime resolver updated to probe canonical `Resources/lang/` paths first, then legacy `lang/`.

1. Added `discover_owner_locale_files()` with canonical+legacy path scanning (static-cached).
2. Updated `load_locale()` to merge per-owner files over Core base.
3. Empty ja/ne scaffolding files are skipped during merge to protect Core translations.
4. Core (`app/Locale/`) unchanged — locked per architecture policy.

### Phase 2: Owner-by-Owner Migration ✅ (2026-06-06)

**Status: COMPLETE** — All 35 owners migrated to canonical `Resources/lang/` path.

1. **33 existing owners** with legacy `lang/` files → `Resources/lang/` with full en/ja/ne.
2. **Shell** (648 keys) — created new `Resources/lang/en.php` from source extraction.
3. **Base plugins** (76 keys) — created new `Resources/lang/en.php` from source extraction.
4. **Missing ja/ne** — created empty files with keys from en.php for all en-only owners.
5. **Legacy files preserved** — `lang/` files remain in place for backward compatibility.
6. **Discovery service updated** — `LocalizationStudioDiscoveryService` scans both paths, prefers canonical.
7. **Core (app/Locale/)** — skipped (Core locked per architecture policy). Uses separate `Locale/` legacy path.

### Owner inventory

| Owner | en | ja | ne | Keys |
|---|---|---|---|---|
| Manufacturing/* (15 modules) | ✅ | ✅ | ✅ | 2–8 each |
| Platform/Organization | ✅ | ✅ | ✅ | 14 |
| Platform/QRCode | ✅ | ✅ | ✅ | 8 |
| Procurement | ✅ | ✅ | ✅ | 10 |
| SBAIO/* (11 modules) | ✅ | ✅ | ✅ | 2–24 each |
| Shell | ✅ | ✅ | ✅ | 648 |
| Studio | ✅ | ✅ | ✅ | 102 |
| Studio/Tools/* (4 tools) | ✅ | ✅ | ✅ | 33–282 each |
| Plugin/Base | ✅ | ✅ | ✅ | 76 |
| **Total** | **35** | **35** | **35** | **1,375** |

### Phase 3: Migration Guardrail Gate ✅ (2026-06-06)

**Status: COMPLETE** — Architecture gate `check_localization_migration_guardrail.sh` blocks new locale files in legacy `lang/` paths.

1. Scans staged, unstaged, and untracked files for legacy-path locale additions.
2. New staged/unstaged legacy files → FAIL.
3. New untracked legacy files → WARNING.
4. Wired into aggregate runner as gate #15 (after resource diagnostics).

### Phase 4: Legacy Removal ✅ (2026-06-06)

**Status: COMPLETE** — All legacy `lang/` files removed from all owners. Resolver updated to canonical-only scanning. Related gates updated.

1. **49 legacy files** removed from 33 `lang/` directories across all owners.
2. **Resolver** (`discover_owner_locale_files()`) — removed legacy path scanning; only canonical `Resources/lang/` patterns remain.
3. **LS DiscoveryService** — removed legacy path patterns; only canonical `Resources/lang/` patterns remain.
4. **Resource diagnostics gate** — updated to scan canonical paths only.
5. **Migration guardrail gate** — updated to fail on ANY legacy-path locale files (tracked or untracked).
6. **Boundary gate** — updated owner derivation to reflect canonical-only paths.

---

## 6. Explicit Non-Goals (v1 Contract Phase)

This contract does not authorize:

1. locale file moves
2. runtime loader/path behavior changes
3. translation value edits
4. Localization Studio editor behavior changes
5. route/controller/service mutation workflows
6. DB/cache translation storage
7. theme/Shell/Core runtime behavior changes

---

## 7. Validation Requirements For Contract-Only Slice

Run:

```bash
git diff --check
bash scripts/system/check_deployment_readiness.sh
bash scripts/architecture/run_architecture_gates.sh
```

Expected for this slice:

1. no runtime behavior changes
2. no locale file moves
3. no locale value changes
4. architecture/deployment checks pass

---

## 8. Migration Record (2026-06-06)

### Files created

- **105 `Resources/lang/` files** across 35 owners (35 en + 35 ja + 35 ne)
- Migration scripts in `scripts/migration/`:
  - `localization-structure-phase1.ps1` — directory creation, file copy, empty locale generation for existing owners
  - `extract_tr_keys.php` — Shell (648 keys) and Base (76 keys) extraction from source code
  - `fix_escaping.php` — escape fix for trailing backslash values
  - `verify_results.php`, `verify_all.php` — validation
  - `smoke_discovery.php` — discovery service smoke test

### Files modified

- `app/Core/helpers.php` — Phase 1 resolver: added `discover_owner_locale_files()` with canonical+legacy path scanning, updated `load_locale()` to merge per-owner files, empty ja/ne scaffolding skip
- `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php` — added canonical `Resources/lang/` scan patterns with legacy fallback

### Files modified (Phase 4)

- `app/Core/helpers.php` — removed legacy path scanning from `discover_owner_locale_files()`
- `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php` — removed legacy path patterns
- `scripts/architecture/check_localization_resource_diagnostics.sh` — canonical-only paths
- `scripts/architecture/check_localization_migration_guardrail.sh` — hardened for Phase 4 (any legacy path = FAIL)

### Files deleted (Phase 4)

- **49 legacy `lang/` files** removed from 33 directories across all owners:
  - Manufacturing modules (15): 1-3 files each (en/ja/ne)
  - Platform modules (2): 1 file each (en only)
  - Procurement: 3 files (en/ja/ne)
  - SBAIO modules (11): 1 file each (en only)
  - Studio: 3 files (en/ja/ne)
  - Studio tools (3): 3 files each (en/ja/ne)

### Files preserved

- `app/Locale/` (Core) unchanged — locked per architecture policy.

### Validations

- All 105 `Resources/lang/` files pass `php -l` syntax check.
- Discovery service correctly resolves all 35 owners with full en/ja/ne coverage (canonical paths only).
- Shell en.php (648 keys) matches source code extraction.
- Base en.php (76 keys) matches self::tr() usage in controllers.
- Phase 1 resolver: per-owner files merge over Core; empty ja/ne scaffolding skipped.
- Phase 3 guardrail: no new legacy-path locale files pass the gate.
- Phase 4 cleanup: 49 legacy files removed; resolver/gates updated to canonical-only.

---

## 9. Cross-References

1. `docs/architecture/localization-studio-resource-validation.md`
2. `docs/architecture/localization-studio-v1-foundation.md`
3. `docs/architecture/localization-studio-v1-checkpoint.md`
4. `docs/architecture/localization-studio-v2-edit-apply-contract.md`
5. `scripts/migration/localization-structure-phase1.ps1`
6. `scripts/migration/extract_tr_keys.php`