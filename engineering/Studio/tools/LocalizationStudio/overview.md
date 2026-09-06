# Studio/tools/LocalizationStudio

## Purpose

Governed Studio worker for locale resource inspection, validation, and edit/apply workflows. Discovers locale files across all owners (apps, modules, plugins, Studio tools), analyzes coverage, validates file integrity, and provides a governed edit/save/snapshot/rollback workflow for locale key management.

## Target State

Studio-owned inspection + governed edit tool that enables platform admins to manage locale resource coverage across the entire codebase without bypassing owner ownership of locale files.

**Current status**: Active — read-only discovery + governed edit/apply with snapshot and rollback (v1+v2).

## Responsibilities

- Discover locale files across all owner paths via canonical `Resources/lang/{locale}.php` patterns (with legacy path fallback during migration window)
- Report per-owner locale coverage/status (present files, missing files, supported locales)
- Validate locale file content integrity (parse validity, duplicate keys, naming conventions, boundary violations, cross-locale key parity)
- Provide key detail inspector with value previews
- Governed create-file workflow: create missing locale files from English keys with preview → confirmation → apply
- Governed edit workflow: add/edit locale keys with preview → diff → apply → snapshot → rollback
- Show English reference values for missing non-EN keys during editing
- Post-apply structured UX: snapshot path, keys written, diagnostics, action links

## Boundaries

- **Must not** become the source of truth for translations — owners own their locale files
- **Must not** host or own locale files — writes go to owner paths
- **Must not** intercept or replace runtime translation loading
- **Must not** validate translation accuracy, detect orphaned keys, or detect placeholder mismatches
- **Must not** propose, apply, or generate locale file content outside governed workflows
- Reads and writes are always owner-path-scoped with path traversal protection
- Snapshots stored under `storage/studio-snapshots/`

## Canonical Source Areas

- `apps/Studio/Tools/LocalizationStudio`
- `engineering/Studio/tools/LocalizationStudio`
- `docs/architecture/localization-studio-v1-foundation.md`
- `docs/architecture/localization-studio-v1-checkpoint.md`
- `docs/architecture/localization-file-structure-v1-contract.md`
- `docs/architecture/localization-studio-resource-validation.md`
- `scripts/architecture/check_localization_studio_boundaries.sh`
- `scripts/architecture/check_localization_resource_diagnostics.sh`

## Dependencies

- `apps/Studio/Controllers/StudioController.php` — controller handlers
- `apps/Studio/routes.php` — route registration
- `apps/Studio/Services/StudioToolInstancePolicyService.php` — tool policy guard
- `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php` — file discovery
- `apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioEditService.php` — edit/apply/snapshot
- Boundary gate: `check_localization_studio_boundaries.sh`
- Resource diagnostic gate: `check_localization_resource_diagnostics.sh`

## Related Workspaces

- `engineering/Studio/tools/LocalizationScanExtraction` — sibling scan+extraction tool
- `engineering/Studio` — parent workspace

## Non-goals

- Translation accuracy validation
- Orphaned key detection
- Placeholder mismatch detection
- Automatic translation generation or machine translation
- Owner locale file migration (path migration is a separate contract)
- Centralized translation storage or runtime
