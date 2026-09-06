# Studio/tools/LocalizationStudio — Rules

## Read First

Read `overview.md`, `work.md`, `rules.md`, and `decisions.md` before meaningful work in this workspace.

## Change Rules

1. All locale file writes must be owner-path-scoped with path traversal protection.
2. All edits must go through snapshot-before-write pattern (`storage/studio-snapshots/`).
3. All writes must use atomic tempfile+rename.
4. English reference values are display-only — never auto-fill non-EN keys.
5. Create-file must allow preview before apply, with explicit confirmation.
6. Session flash is one-time (read-and-clear).
7. Submit buttons must not be disabled before form submission — use `form.submit()` explicitly.

## Validation Rules

1. Locale files must parse as valid PHP.
2. Locale keys must follow naming conventions (no dangerous values, no boundary violations).
3. Cross-locale key parity must be verified (en baseline vs ja/ne).
4. Unsupported locale codes must be rejected.
5. Duplicate keys within a file must be flagged.
6. Key detail inspector must verify value presentation matches file content.
7. Post-apply diagnostics must verify write success, snapshot creation, and file integrity.

## Ownership Boundaries

- **Apps/modules/plugins** own their locale resources — Studio is governed worker, not owner.
- **Core (`/app`)** locale files are locked — Studio must not modify.
- **Studio** owns tool manifest, services, views, controllers, and boundary gates.
- Locale files follow ownership: `{OwnerRoot}/Resources/lang/{locale}.php`.
- Fallback chain: requested locale → `en` → key literal.

## Completion Rule

Do not report completion until the requested validation evidence is recorded.
