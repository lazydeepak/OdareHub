# Studio — Work

## Current Focus

Repository Scanner Search V1 — complete and committed.

## In Progress

None.

## In Progress

- [x] Fix HTTP 500 regression on Engineering Workspace `work.md` viewer: wrapped `WorkTaskParser::parseSupportedTaskLines()` and viewer `require` in try/catch with controlled error output. Commit 6c680fa0.

## Next

- CSS Live Editor tool label indicator: show `work.md` completion (checked/total) in the Studio page header

## Blocked

- None recorded.

## Completed

### Engineering Workspace header links in shared Studio page header
- Created `apps/Studio/Services/StudioWorkspaceRouteMapper.php` — canonical page-route-to-workspace mapping source.
- Modified `apps/Studio/Views/pages/tool_placeholder.php` — computes workspace links from the current request path and renders Overview/Work/Rules/Decisions links in the shared nav bar.
- Added CSS to `apps/Studio/styles/gui_studio.css` — `.gs-tool-nav-start`, `.gs-tool-nav-workspace`, `.gs-tool-nav-ws-link` for right-aligned compact link group.
- Seeded mappings: Studio, Studio/tools/CustomizationStudio, Studio/tools/LocalizationStudio, Studio/tools/LabelDesigner, Studio/tools/ReportDesigner.
- Links only rendered for Platform Admin, only when workspace files exist.
- Uses existing `EngineeringWorkspaceResolver::buildWorkspaceLinks()` — no new viewer, resolver, permission gate, or route.
- No raw filesystem paths exposed in HTML/URLs.
- Validation: PHP lint, `git diff --check`.

### Engineering Workspace `work.md` Checklist Interaction V1 (6c680fa0)

- [x] HTTP 500 fix: try/catch around parser + viewer require degrades to controlled error state
- Created `apps/Studio/Tools/EngineeringWorkspace/Services/WorkTaskParser.php` — parses `- [ ]`/`- [x]` lines with fenced code block exclusion, provides `parseSupportedTaskLines()`, `findTaskByOrdinal()`, `toggleTaskLine()`, `fingerprint()`.
- Added POST route `/apps/studio/engineering-workspaces/toggle-work-item` in `apps/Studio/routes.php`.
- Added controller handler `engineeringWorkspaceToggleWorkItem()` in `apps/Studio/Controllers/StudioController.php` — full validation (CSRF, fingerprint, ordinal, line hash, document type, target state, authorization), reuses `EngineeringWorkspaceResolver::writeWithFingerprint` for atomic write, redirect with session flash for success/error/stale.
- Updated `apps/Studio/Tools/EngineeringWorkspace/Views/viewer.php` — flash messages for toggle success/error/stale with "Reload Current Document" link; work.md view mode renders interactive checkboxes via JS (disabled checkbox replacement + form submit); no-JS fallback `<details>` form with task ordinal select and target state checkbox.
- Updated controller `engineeringWorkspaceViewer()` — passes `work_tasks`, `toggle_success`, `toggle_error`, `toggle_stale` to view model.
- Created `apps/Studio/tests/probe_work_toggle.php` — 59 assertions covering parser, fenced code, find, toggle (both directions), same-state rejection, invalid ordinal, extra whitespace, fingerprint stability, integration with canonical write path.
- Validation: probe_work_toggle 59/59 pass, probe_engineering_workspace_editing 26/26 pass, probe_route_mapper 23/23 pass, probe_engineering_workspace_contract 334/334 pass, probe_developer_strip_context 30/30 pass, PHP lint 4/4 files, `check_studio_boundary.sh` PASS, `check_studio_enforcement_readiness.sh` PASS, `git diff --check` PASS.

### Phase 2: Expand workspace links to Studio home + owner-backed pages
- Modified `apps/Studio/Views/pages/home.php` — added workspace links (Overview/Work/Rules/Decisions) in the hero section, uses `StudioWorkspaceRouteMapper` + `EngineeringWorkspaceResolver`, Platform Admin only.
- Added CSS `.gs-home-ew-links` to `apps/Studio/styles/gui_studio.css` — compact link group with accent colors and hover state in the home hero layout.
- Modified `public/views/layouts/admin-wrapper-open.php` — added workspace link rendering for owner-backed pages using inline URL-to-workspace mapping.
- Fixed `StudioWorkspaceRouteMapper` prefix matching — `/apps/studio` now exact-match only.
- Created `apps/Studio/tests/probe_route_mapper.php` — 23 route resolution tests.

## Evidence

- No verification evidence recorded yet.
