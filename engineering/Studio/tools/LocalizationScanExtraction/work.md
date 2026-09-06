# Studio/tools/LocalizationScanExtraction — Work

## Current Focus

Tool is at `scanner_wired` status with full scanner, classification, migration planner, and apply engine implemented. Next focus is stabilization and quality gate hardening.

## In Progress

- None recorded.

## Next

- Evaluate need for multi-locale inline migration support.
- Evaluate integration between migration planner and LocalizationStudio handoff.
- Harder quality gates for suggestion acceptance thresholds.

## Blocked

- None recorded.

## Completed

- Scanner with inline text detection, classification, and 8 category filters (Human-facing, Already localized, Missing owner keys, Shared/external, Possibly unused, Internal, Ambiguous).
- Classification reconciliation — duplicate-free findings with exactly one status per key.
- Extraction preview generation with risk assessment.
- Full system scan across all owners with scope filtering.
- Locale externalization: `en.php`, `ja.php`, `ne.php` (148 keys each) replacing inline dict.
- Persistent safety bar with scanner/file-write/runtime/extraction status indicators.
- Correction/apply workflow with snapshot and rollback.
- Inline migration planner V1 — classify, suggest keys (42-entry common UI text map), build plan.
- Inline migration apply engine — English-only, snapshot-before-write, atomic write, opcache invalidation.
- History store and retrieval with pruning.
- Rollback service with ownership boundary verification.
- Suggestion quality gate service.
- File integrity validator.
- Migration planner test probe: 43/43 assertions pass.
- Boundary gate: `check_localization_scan_extraction_boundaries.sh` with ~100 invariants.

## Evidence

- Boundary gate: `check_localization_scan_extraction_boundaries.sh` — 100+ invariants pass.
- Migration planner probe: 43/43 assertions pass.
- Scanner fixture tests pass.
- Full system scan works across all owners.
