# Studio/tools/LocalizationScanExtraction — Rules

## Read First

Read `overview.md`, `work.md`, `rules.md`, and `decisions.md` before meaningful work in this workspace.

## Change Rules

1. Scanner is read-only — no source file mutation without explicit apply authorization.
2. Inline migration apply is English-only — reject non-`en` locale candidates.
3. All applies must snapshot before write with atomic tempfile+rename.
4. Rollback must verify owner-boundary containment before restoring.
5. Source replacements must use safe attributes only (`title`, `alt`, `placeholder`, `aria-label`).
6. Element types for apply must be allowlisted (heading, button, link, label, status, etc.).
7. All commands must preserve `extractable: false` on findings until explicitly released.

## Validation Rules

1. Classification must produce exactly one status per finding (matched/shared/unresolved) — no duplicates.
2. Migration planner must determine state per candidate: `ready_to_migrate`, `needs_review`, or `rejected`.
3. Key suggestions must use the common UI text map before generating owner-prefixed keys.
4. Rejected candidates must not appear in apply proposals.
5. Correction reports must include snapshot paths, diagnostics, and action results.
6. History store must prune old entries (retention policy).
7. File integrity must be validated before and after apply.

## Ownership Boundaries

- **Apps/modules/plugins** own their source files — scanner discovers but Studio does not own.
- **Core (`/app`)** is locked — scanner must block or skip Core files.
- **Studio** owns the scanner tool, services, views, locale files, and boundary gates.
- Scanner findings are diagnostic-only — they do not imply authorization to modify.
- Owner paths are resolved via `LocalizationStudioEditService::getOwnersWithPaths()`.

## Completion Rule

Do not report completion until the requested validation evidence is recorded.
