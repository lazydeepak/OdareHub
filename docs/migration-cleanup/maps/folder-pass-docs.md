# Folder Pass: docs

Detailed cleanup pass for `docs/`.

## Snapshot

- Total markdown files in `docs/`: 128
- Markdown-heavy zones:
  - `docs/architecture`: 26 files
  - `docs/runtime`: 27 files
  - `docs/migration-cleanup`: 37 files
- Root-level markdown files in `docs/`: 25 files

## Current Structure Health

- Strongly organized areas already exist: `architecture`, `contracts`, `identity`, `runtime`, `migration-cleanup`.
- Main fragmentation issue is still `docs/*.md` at root (mixed topics in one level).

## Candidate Cleanup Actions (safe, non-breaking first)

1. Introduce root-level docs index by domain (architecture, runtime, operator, studio, roadmap).
2. Move root-level docs into subfolders in small topic batches.
3. Keep `docs/migration-cleanup/` as process/control plane (do not mix with domain docs).
4. Preserve canonical architecture charter location and links.

## Proposed Batch Order

1. Move operator-related docs from `docs/` root to `docs/runtime/`.
   Status: partially complete for safe operator docs in Batch 1; realtime docs
   with non-doc references were skipped.
2. Move studio-specific operational reports from `docs/` root to `docs/runtime/` or `docs/architecture/` based on intent.
3. Move plan/phase docs from `docs/` root into `docs/architecture/` when contract-oriented, else `docs/runtime/`.

## Guardrails

- No content rewrites during move pass.
- Update links in same commit as each batch.
- Validate with architecture/deployment checks after each batch.
