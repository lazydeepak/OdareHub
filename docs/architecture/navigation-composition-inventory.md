# Navigation Composition Inventory

Status: Read-only diagnostic baseline
Date: 2026-05-24
Owner: Shell architecture / platform governance

## Purpose

This inventory documents the current runtime navigation composition landscape without changing behavior.
It is the baseline for removing ownership confusion while keeping compatibility intact.

## Active Runtime Boundary

- Runtime entry at [public/views/layouts/header.php](public/views/layouts/header.php) composes sidebar payload through `Apps\\Shell\\Services\\ShellRuntimeMenuComposer`.
- Shell compatibility composer is [apps/Shell/Services/ShellRuntimeMenuComposer.php](apps/Shell/Services/ShellRuntimeMenuComposer.php).
- Current implementation delegates to `App\\Core\\SidebarBuilder` for compatibility.

## Legacy Compatibility Bridge (Do Not Expand)

These are legacy bridge artifacts and must not receive new business navigation truth:

- [app/Navigation/sidebar.php](app/Navigation/sidebar.php)
- [app/Navigation/sidebar_sources.php](app/Navigation/sidebar_sources.php)
- [app/Navigation/sidebar_sources/core.php](app/Navigation/sidebar_sources/core.php)
- [app/Navigation/sidebar_sources/admin.php](app/Navigation/sidebar_sources/admin.php)
- [app/Navigation/sidebar_sources/apps.php](app/Navigation/sidebar_sources/apps.php)
- [app/Navigation/modules.php](app/Navigation/modules.php)

## Contribution Files (navigation.v1)

### App-level contributions

- [apps/Manufacturing/navigation.php](apps/Manufacturing/navigation.php)
- [apps/Platform/navigation.php](apps/Platform/navigation.php)
- [apps/Procurement/navigation.php](apps/Procurement/navigation.php)
- [apps/SBAIO/navigation.php](apps/SBAIO/navigation.php)
- [apps/Shell/navigation.php](apps/Shell/navigation.php)
- [apps/Studio/navigation.php](apps/Studio/navigation.php)

### Module-level contributions

- [apps/Platform/modules/Organization/navigation.php](apps/Platform/modules/Organization/navigation.php)

### Generated contributions

- [apps/Generated/hardening_app/hardening_module/navigation.php](apps/Generated/hardening_app/hardening_module/navigation.php)
- [apps/Generated/inventory_app/parts_master/navigation.php](apps/Generated/inventory_app/parts_master/navigation.php)
- [apps/Generated/inventory_app/stock_entries/navigation.php](apps/Generated/inventory_app/stock_entries/navigation.php)
- [apps/Generated/lifecycle_app/lifecycle_module/navigation.php](apps/Generated/lifecycle_app/lifecycle_module/navigation.php)
- [apps/Generated/manufacturing_app/production_plan/navigation.php](apps/Generated/manufacturing_app/production_plan/navigation.php)
- [apps/Generated/manufacturing_studio/assemblyentries_studio/navigation.php](apps/Generated/manufacturing_studio/assemblyentries_studio/navigation.php)
- [apps/Generated/manufacturing_studio/manufacturing_studio/navigation.php](apps/Generated/manufacturing_studio/manufacturing_studio/navigation.php)
- [apps/Generated/rollback_app/rollback_module/navigation.php](apps/Generated/rollback_app/rollback_module/navigation.php)
- [apps/Generated/sample_app/sample_module/navigation.php](apps/Generated/sample_app/sample_module/navigation.php)

### Plugin contributions

- [plugins/AdminTools/navigation.php](plugins/AdminTools/navigation.php)

## Known Ownership Risk

- Base cross-owner Manufacturing portal declaration was removed in Slice 2.
- Current checker baseline has no ownership warnings or duplicate owner conflicts.

## Diagnostic Coverage Required

The navigation duplicate checker must report:

- duplicate URL values across owners
- duplicate `source_key` values across owners
- duplicate `menu_key` values across owners
- owner/path mismatch (item owner not matching owning file scope)
- `nav_visible:false` suppression counts and occurrences
- new business-route declarations added under legacy `app/Navigation`

## Baseline Snapshot (2026-05-24)

Checker: [scripts/architecture/check_navigation_composition_duplicates.sh](scripts/architecture/check_navigation_composition_duplicates.sh)

- discovered contribution files: 18
- total navigation items: 109
- suppressed (`nav_visible:false`) items: 34
- invalid files: 0
- hard failures: 0
- warnings: 28

Primary warning classes:

- app-level files using module-specific `owner` values (Manufacturing and SBAIO)
- one duplicate URL across files: `/apps/manufacturing`
- plugin cross-scope concern visible in duplicate URL signal (Base + Manufacturing)

Interpretation:

- Runtime is stable and contract-valid enough for read-only inventory work.
- Highest-priority ownership cleanup remains Base-declared Manufacturing portal link.
- Module-level owner granularity inside app-owned files is currently treated as advisory warning, not blocker.

## Slice 2 Update (2026-05-24)

- Removed Base-owned cross-owner portal shortcut from [plugins/Base/navigation.php](plugins/Base/navigation.php).
- Manufacturing app entry remains owned by [apps/Manufacturing/navigation.php](apps/Manufacturing/navigation.php).
- Re-ran [scripts/architecture/check_navigation_composition_duplicates.sh](scripts/architecture/check_navigation_composition_duplicates.sh):
  - hard failures: 0
  - warnings: 27 (down from 28)
  - duplicate URL warning for `/apps/manufacturing` resolved

## Slice 3 Update (2026-05-24)

- Normalized app-level item owners to app-scoped values in:
  - [apps/Manufacturing/navigation.php](apps/Manufacturing/navigation.php)
  - [apps/SBAIO/navigation.php](apps/SBAIO/navigation.php)
- Re-ran [scripts/architecture/check_navigation_composition_duplicates.sh](scripts/architecture/check_navigation_composition_duplicates.sh):
  - hard failures: 0
  - warnings: 0 (down from 27)
  - legacy bridge protection: pass

## Slice 4 Update (2026-05-24)

- Removed legacy Base navigation contributor: [plugins/Base/navigation.php](plugins/Base/navigation.php).
- Migrated hidden `/ops/*` internal compatibility entries into [apps/Platform/navigation.php](apps/Platform/navigation.php) under Platform ownership.
- Result: plugin navigation ownership now reflects active contributors only (`plugins/AdminTools/navigation.php`).

## Required Safety Rules

- Keep Core locked (no direct Core migration in this slice).
- Keep `app/Navigation` as compatibility bridge only.
- Keep Shell as composition boundary owner.
- Keep apps/modules/plugins as navigation truth owners.

## Next Slice After This Inventory

- Integrate [scripts/architecture/check_navigation_composition_duplicates.sh](scripts/architecture/check_navigation_composition_duplicates.sh) into the architecture gate runner.
- Keep the checker read-only and use it as a regression guard for new navigation contributions.
