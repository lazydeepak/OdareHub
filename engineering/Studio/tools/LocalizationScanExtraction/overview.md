# Studio/tools/LocalizationScanExtraction

## Purpose

Studio tool for scanning PHP/JS source files to detect inline text that should be localized, classifying findings, and providing governed correction workflows. Identifies human-facing strings missing locale keys, suggests migration plans, and applies corrections with snapshot and rollback support.

## Target State

Full-cycle localization hygiene tool: detect → classify → plan → preview → apply → rollback. Currently at `scanner_wired` status with read-only classification + inline migration planning and apply engine for the `en` locale.

## Responsibilities

- Scan source files across selected owners for inline human-facing text (headings, buttons, labels, status messages, etc.)
- Classify findings by semantic category (navigation, action, heading, label, status, error_message, etc.)
- Detect missing owner keys, shared/external key usage, possibly unused keys, ambiguous patterns, and technical/ignored strings
- Reconcile classification to eliminate duplicate findings (each finding has exactly one status: matched/shared/unresolved)
- Generate extraction previews showing suggested locale key, English value, source replacement, and risk assessment
- Provide inline migration planner: classify each `inline_text` finding as `ready_to_migrate`, `needs_review`, or `rejected`; suggest canonical keys via common UI text map
- Apply inline migration for `en` locale: add locale keys to English file + replace inline text with `__()` calls, with snapshot-before-write
- Full system scan across all owners with scope filtering
- History storage and retrieval of past correction reports
- Rollback from snapshots with owner-boundary verification
- Report unsupported locale codes, file integrity validation, and quality gating

## Boundaries

- **Must not** propose, apply, or generate locale file content outside governed workflows
- Inline migration apply is only supported for English (`en`) locale
- **Must not** modify non-text content (logic, templates, structural code)
- **Must not** validate translation accuracy or detect placeholder mismatches
- **Must not** modify Core (`/app`) files
- All writes are owner-path-scoped with path traversal protection
- Commands are read-only unless explicitly authorized (inline migration apply requires confirmation)
- Snapshot storage under `storage/studio-snapshots/localization-scan-extraction/`

## Canonical Source Areas

- `apps/Studio/Tools/LocalizationScanExtraction`
- `engineering/Studio/tools/LocalizationScanExtraction`
- `docs/architecture/localization-file-structure-v1-contract.md`
- `scripts/architecture/check_localization_scan_extraction_boundaries.sh`
- `scripts/architecture/check_localization_resource_diagnostics.sh`

## Dependencies

- `apps/Studio/Controllers/StudioController.php` — controller handlers
- `apps/Studio/routes.php` — route registration
- `apps/Studio/Services/StudioToolInstancePolicyService.php` — tool policy guard
- `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioEditService.php` — owner/path resolution
- `apps/Studio/Tools/LocalizationScanExtraction/Services/LocalizationScanService.php` — main scanner
- `apps/Studio/Tools/LocalizationScanExtraction/Services/FullSystemScanService.php` — full system scan
- Boundary gate: `check_localization_scan_extraction_boundaries.sh`

## Related Workspaces

- `engineering/Studio/tools/LocalizationStudio` — sibling locale resource management tool
- `engineering/Studio` — parent workspace

## Non-goals

- Translation accuracy validation
- Automatic translation generation or machine translation
- Multi-locale inline migration (English-only apply)
- Locale file creation (use LocalizationStudio for that)
- Runtime behavior changes
- Print/export/QR generation
