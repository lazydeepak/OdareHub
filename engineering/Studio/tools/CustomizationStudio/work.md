# Studio/tools/CustomizationStudio — Work

## Current Focus

Prepare existing customization capabilities for incremental consolidation into a unified, reusable Studio styling workflow.

The current focus is capability truth, canonical discovery, and reusable read-only boundaries. Runtime Appearance activation and broad mutation consolidation remain deferred.

## In Progress

- Review and commit the Customization Studio consolidation-preparation working tree.
- Keep the pre-existing `apps/apps/` symlink scan issue separate from the socket-catalog path repair.
- Confirm no unreported local changes are mixed into the preparation slice.

## Next

- Extend the shared validation facade with read-only dependency scanning — `validateFileReferences()` to detect stale path/namespace references in owner `.css` source files.
- Add `discoverTokensForSource()` to surface all socket-relevant tokens available to a given source path.
- Scope-bound `readiness()` so it can accept a specific owner or `radius.scale` target.
- Do not enable runtime consumption, Shell insertion, Special Effects persistence, or Style Compliance guarded repair in the next slice.

## Blocked

- Runtime style consumption remains intentionally disabled across `StyleConsumptionSurface`, `RuntimeStyleApplication`, `ShellInsertion`, and `RenderedAdminProof`.
- Broad Appearance migration execution is blocked until reusable validation, snapshot, and mutation boundaries are proven.
- The Customization Studio source-home scan has a pre-existing symlink-traversal concern involving `apps/apps/`; it is not caused by the DesignSystem path repair and should be handled independently.

## Completed

### 2026-07-19 — First reusable shared validation capability

- Filled in all four existing `Shared/Services/` scaffolding stubs (`CssPathResolver`, `OwnerResolver`, `StyleDiffService`, `StyleValidationService`) instead of creating new parallel services.
- `StyleValidationService` is the unified read-only facade that delegates to `VisualCustomizerMetadataService::discoverSocketCatalogsMap()` and `TokenImpactDiscoveryService::exploreToken()` — no duplication of parsing or discovery.
- Value validation extracted from `CssTokenEditorSaveService::validateValue()`: 5 checks (empty, `{}<>`, `expression()`, `javascript:`, non-data `url()`).
- Integration proof: `VisualCustomizerMetadataService::discover()` now returns `validation_readiness` key — no behavioral change to existing callers.
- Added `probe_shared_validation.php` with 11 sections, 58+ assertions.
- All validation passes: PHP lint ✅, shared validation probe ✅ (58+ pass), capability preparation probe ✅ (74 pass), shell style catalog gate ✅, style compliance CSS ownership ✅, customization studio boundary gate ⚠️ (pre-existing `apps/apps/` symlink issue only).
- Eight deferred scaffolding stubs remain empty: `StyleToolRouter`, `StyleSnapshotService` (Services); `StyleMutationContract`, `StyleSnapshotContract`, `StyleHandoffContract`, `StyleAuthorityContract`, `TokenOwnershipContract`, `StyleValidationContract` (Contracts).

### 2026-07-18 — Customization Studio consolidation preparation

- Repaired active socket-catalog references from the obsolete `apps/Shell/Style/Resources/socket-catalog/` path to the canonical `apps/Shell/DesignSystem/Resources/socket-catalog/` path.
- Updated Visual Customizer metadata discovery and Token Impact Explorer catalog discovery.
- Updated affected Platform, Shell, and Customization Studio architecture gates and probes.
- Added `CustomizationStudioCapabilityInventory` as a manifest-driven read-only inventory for all nine registered subtools.
- Wired the capability inventory into the Customization Studio landing model.
- Corrected CSS Selector Inspector placeholder flags so it no longer advertises mutation, approval, owner-artifact writes, diff, snapshot, or rollback.
- Updated the landing page in English, Japanese, and Nepali to describe the workspace as an active gateway rather than a globally disabled preview.
- Preserved all runtime-consumption disablement and all currently disabled mutation capabilities.
- Added `probe_capability_preparation.php` with more than 50 assertions covering canonical catalog discovery, manifest truth, inventory state, landing copy, and disabled runtime flags.

### Style Compliance scanner — Foundation rendering detection
- `detectFoundationConcerns()` method added to `StyleComplianceScannerService.php`. Parses CSS rule blocks and detects table-like selectors missing overflow properties.
- Token/candidate tables wrapped in `<div class="table-wrap">`.
- Foundation stat added to stats strip as a passive diagnostic.

### Style Compliance — Guarded Repair Engine (code complete, manifest pending)
- `StyleComplianceRepairEngineService` created with input integrity, readiness re-check, source resolution, surgical replacement, snapshot, atomic write, and post-apply validation.
- `styleComplianceRepairExecute()` handler and POST route wired.
- Browser Apply Repair UI exists with readiness check and confirmation dialog.

### Style Compliance — UX Acceptance
- Probe assertions fixed after stats strip rendering order correction.
- Browser verification completed for desktop and mobile in the prior record.

### Style Compliance — Locale externalization
- Inline locale closure extracted to shared `_locale.php`.
- Human labels in `_result_sections.php` made locale-driven.

### Style Compliance — Migration calibration
- Migration fixture CSS files created covering migration states.
- Shared shell selector pattern documentation clarified.

### Special Effects — Appearance State V1.6
- Appearance State Consistency section added with `AppearanceStateResolver` output.
- Read-only browser appearance observer added.

### Foundation scaffolding
- Initial Engineering Workspace seed structure created for Customization Studio.

## Evidence

- No verification evidence recorded yet.
