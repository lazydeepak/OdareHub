# Batch 5 DB Menus Runtime Fallback Diagnostic

Status: executed as read-only diagnostic and documentation only.

No runtime behavior was changed. Core files were not edited. No DB data was
deleted or mutated. Navigation/menu rendering was not changed.

## Architecture Basis

- Core is locked.
- Apps/modules/plugins own capability and navigation contributions.
- Shell composes runtime surfaces.
- Runtime should converge on resolved/compiled contracts.
- Hidden duplicate menu truth must be classified before navigation cleanup.

## Diagnostic Script

- script: `scripts/architecture/check_db_menus_runtime_fallback.sh`
- mode: read-only diagnostic
- current result: pass with warnings
- local DB evidence during this run: 88 `menus` rows, including 46 rows with
  `source_type='app'` and 42 rows with no `source_type`
- static inventory counts during this run: 20 `menu.php` files, 17
  `navigation.php` files, and 38 manifest/plugin JSON files with `menus`
  metadata

Warnings are expected in this batch: DB `menus` is still read and written by
runtime/search/bootstrap/lifecycle paths, so it cannot be removed or demoted
without a later migration plan.

## Direct Answers

1. Who reads DB `menus`?
   - `app/Core/SidebarBuilder.php` reads it for compatibility fallback,
     dynamic extension items, and URL dedupe.
   - `app/Core/SearchService.php` reads it as search enrichment.
   - `plugins/Base/bootstrap.php` includes a legacy `base_render_sidebar()`
     helper that renders directly from `menus`.
   - Diagnostics and documentation read references only.

2. Who writes DB `menus`?
   - `plugins/Base/bootstrap.php` defines `base_register_menus()` and seeds Base
     menus on boot.
   - Many module/plugin bootstraps call `base_register_menus()` with local
     `menu.php` files.
   - `app/Services/AppRuntimeRegistryService.php` deletes app-sourced rows and
     inserts enabled app hook menu payloads.
   - `app/Core/PluginManager.php` truncates `menus` during active plugin reload.
   - `AppRuntimeRegistryService` also adds compatibility source columns when
     missing.

3. Is DB `menus` still required for runtime sidebar rendering?
   - Current answer: yes as compatibility runtime fallback.
   - `SidebarBuilder` still reads DB `menus`; Base's legacy renderer can render
     directly from it. Removing it would be a runtime behavior change.

4. Is DB `menus` duplicating `navigation.php` contracts?
   - Current answer: partially yes.
   - `navigation.php`, manifest `menus`, module `menu.php`, and DB `menus`
     overlap. `SidebarBuilder` explicitly includes URLs from DB `menus` in
     dedupe logic so DB-seeded items do not duplicate contract items.

5. Can future cleanup treat DB `menus` as fallback/cache, or must it remain
   authoritative?
   - Current answer: it may become fallback/cache only after parity proof.
   - Today it remains COMPATIBILITY_TRUTH plus RUNTIME_FALLBACK and
     SEARCH_ENRICHMENT because runtime and lifecycle paths still depend on it.

6. What must be true before DB `menus` can be reduced/migrated?
   - Sidebar and search parity must pass without DB `menus`.
   - Existing rows must map to owner `navigation.php`, manifest `menus`, or
     documented legacy seed entries.
   - `base_register_menus()` writers must be retired, redirected, or made
     explicitly cache-only.
   - App lifecycle sync semantics must be preserved or replaced by owner
     contract compilation.
   - Studio/generated/package flows must be confirmed not to depend on DB
     `menus`.

## Classification

| Source/path | Usage | Classification | Cleanup decision |
|---|---|---|---|
| DB table `menus` | runtime fallback, search enrichment, compatibility registry | RUNTIME_FALLBACK / SEARCH_ENRICHMENT / COMPATIBILITY_TRUTH / INVESTIGATE | keep; do not delete or demote |
| `plugins/Base/migrations/001_base_tables.sql` | creates legacy `menus` table | LEGACY_SEED | keep until migration replacement exists |
| `plugins/Base/bootstrap.php` | defines `base_register_menus()`, writes rows, legacy sidebar read | COMPATIBILITY_TRUTH | keep; future migration must redirect writers first |
| module/plugin `menu.php` files | owner-local legacy menu declarations | LEGACY_SEED / COMPATIBILITY_TRUTH | keep until mapped to owner navigation/manifest contracts |
| module/plugin bootstraps | call `base_register_menus()` | COMPATIBILITY_TRUTH | keep until writer retirement plan exists |
| `app/Services/AppRuntimeRegistryService.php` | lifecycle menu sync and source columns | COMPATIBILITY_TRUTH | Core-owned runtime registry behavior; do not edit in cleanup batch |
| `app/Core/SidebarBuilder.php` | reads DB menus for fallback/dynamic extension/dedupe | RUNTIME_FALLBACK / CORE_LOCKED | keep; requires approved Core/Shell extraction plan |
| `app/Core/SearchService.php` | reads DB menus for search results | SEARCH_ENRICHMENT / CORE_LOCKED | keep until search replacement exists |
| `app/Core/PluginManager.php` | truncates menus on active plugin reload | COMPATIBILITY_TRUTH / CORE_LOCKED | keep until plugin registry migration exists |
| app manifests `menus` entries | owner app menu metadata | SOURCE_OF_TRUTH candidate | compare against navigation contracts before migration |
| `navigation.php` files | owner navigation contributions | SOURCE_OF_TRUTH | preserve as navigation truth; DB rows may duplicate/enrich |

## Runtime Coupling Summary

- DB `menus` is not safe to remove.
- DB `menus` is not cleanly authoritative either; it is a compatibility registry
  that still affects runtime sidebar and search behavior.
- `navigation.php` contracts are the owner navigation direction, but they do
  not yet fully replace the legacy DB/menu.php/bootstrap paths.
- Future work should be parity-first: prove what runtime/sidebar/search output
  would look like without DB `menus` before changing behavior.

## Future Safe Work

1. Add a parity report comparing DB `menus` rows to owner `navigation.php`,
   manifest `menus`, and `menu.php` declarations.
2. Decide whether `menu.php` files become owner navigation contracts or are
   compiled into a cache.
3. Design a Core/Shell extraction path for the `SidebarBuilder` DB fallback
   before touching runtime rendering.
4. Keep DB `menus` cleanup blocked until writer paths and search enrichment are
   replaced or explicitly retained as cache.
