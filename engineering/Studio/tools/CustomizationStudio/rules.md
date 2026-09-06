# Studio/tools/CustomizationStudio — Rules

## Read First

Before doing meaningful work in this workspace, read the four workspace files: `overview.md`, `work.md`, `rules.md`, and `decisions.md`.

## Change Rules

- Update `work.md` with current state when starting or finishing meaningful work.
- Add to `decisions.md` only when a real architectural decision was made.
- No task IDs, claim IDs, locks, percentages, snapshots, or complex structured task metadata.

## Validation Rules

- The Style Compliance evidence probe (`Tests/probe_evidence.php`) is the canonical diagnostic truth for scanner correctness.
- Run relevant probes before reporting scanner changes as complete.
- Run `check_customization_studio_boundaries.sh` and `check_style_compliance_css_ownership.sh` before reporting completion on CSS changes.

## Ownership Boundaries

- Customization Studio is Studio-owned. Do not create Shell, Platform, or business-app dependencies.
- Design Token Editor may write to `resources/themes/`. All other tools are read-only by manifest unless explicitly enabled.
- Shared CSS uses `sc-*` prefix classes in `apps/Studio/styles/style-compliance.css`.

## Completion Rule

Before reporting any change as complete, run PHP lint on touched PHP files, relevant probes/gates, and `git diff --check`.
