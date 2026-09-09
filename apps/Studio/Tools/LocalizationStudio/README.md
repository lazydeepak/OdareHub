# Localization Studio

**Status**: Read-only discovery and drilldown UI. No writes, no save/apply,
no runtime behavior.

## Purpose

Localization Studio is a governed Studio worker for locale resource inspection,
validation, and future edit proposals across OdareHub apps and modules.

## Current Phase

v1 inspection slice — read-only diagnostics and key drilldown only.

- Route: `GET /apps/studio/tools/localization-studio`.
- Owner search/filter.
- Missing JA / missing NE / key mismatch filters.
- Expandable owner rows with per-language key lists.
- Exact missing keys per language.
- Locale file paths and key counts.
- Copy-only key list export to clipboard.
- No locale file edits.
- No locale file moves.
- No translation loading changes.
- No database translation tables.
- No import/export.
- No Core changes.

## Architecture Reference

See `docs/architecture/localization-studio-v1-foundation.md` for full
architecture position, ownership model, discovery patterns, validation rules,
and forbidden shortcuts.
